<?php
/**
 * Turning checkout into bookings, and keeping bookings in step with order status.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Roova_Orders
 */
class Roova_Orders {

	/**
	 * Hooks.
	 */
	public static function init() {
		// Classic checkout: fires after the order and its line items exist,
		// but before payment is taken.
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'on_checkout' ), 10, 3 );
		// Cart/Checkout Blocks (Store API) equivalent.
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'on_store_api_checkout' ), 10, 1 );

		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'on_status_changed' ), 10, 4 );

		add_action( 'woocommerce_before_delete_order', array( __CLASS__, 'on_order_deleted' ), 10, 1 );
		add_action( 'woocommerce_trash_order', array( __CLASS__, 'on_order_deleted' ), 10, 1 );
		add_action( 'wp_trash_post', array( __CLASS__, 'on_post_trashed' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'on_post_trashed' ) );
		add_action( 'woocommerce_before_delete_order_item', array( __CLASS__, 'on_order_item_deleted' ) );
	}

	/**
	 * How long an unpaid order keeps its rooms, in minutes.
	 *
	 * @return int
	 */
	public static function pending_minutes() {
		return (int) apply_filters( 'roova_pending_order_minutes', (int) roova_option( 'pending_minutes', 60 ) );
	}

	/* ---------------------------------------------------------------------
	 * Checkout
	 * ------------------------------------------------------------------ */

	/**
	 * Classic checkout entry point.
	 *
	 * @param int      $order_id Order ID.
	 * @param array    $posted   Posted data.
	 * @param WC_Order $order    Order.
	 * @throws Exception When a room was taken while the guest was checking out.
	 */
	public static function on_checkout( $order_id, $posted, $order ) {
		unset( $posted );

		$order = $order ? $order : wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		self::commit_order( $order );
	}

	/**
	 * Store API (Blocks) checkout entry point.
	 *
	 * @param WC_Order $order Order.
	 * @throws Exception When a room was taken while the guest was checking out.
	 */
	public static function on_store_api_checkout( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		try {
			self::commit_order( $order );
		} catch ( Exception $error ) {
			// The Store API renders RouteException cleanly in the block
			// checkout; a bare Exception would surface as a server error.
			if ( class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
				throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
					'roova_booking_conflict',
					esc_html( $error->getMessage() ),
					409
				);
			}

			throw $error;
		}
	}

	/**
	 * Convert this order's holds into real bookings.
	 *
	 * Each room is committed inside its own named lock, so two guests racing
	 * for the last unit are serialised and exactly one of them wins.
	 *
	 * @param WC_Order $order Order.
	 * @throws Exception When a stay can no longer be honoured.
	 */
	public static function commit_order( $order ) {
		$committed = array();

		foreach ( $order->get_items() as $item_id => $item ) {
			$booking = $item->get_meta( '_roova_booking', true );
			if ( ! is_array( $booking ) || empty( $booking['room_id'] ) ) {
				continue;
			}

			$room_id       = absint( $booking['room_id'] );
			$units         = max( 1, (int) $item->get_quantity() );
			$cart_item_key = (string) $item->get_meta( '_roova_cart_item_key', true );

			$locked = Roova_Holds::lock( $room_id );

			$hold        = $cart_item_key ? Roova_Holds::get_by_cart_item_key( $cart_item_key ) : null;
			$exclude_ids = $hold ? array( (int) $hold['id'] ) : array();

			$available = Roova_Availability::available_units(
				$room_id,
				$booking['check_in'],
				$booking['check_out'],
				array( 'exclude_ids' => $exclude_ids )
			);

			if ( $available < $units ) {
				if ( $locked ) {
					Roova_Holds::unlock( $room_id );
				}

				// Undo anything already committed for this order so we never
				// leave a half-booked stay behind.
				self::release_order_rows( $order->get_id(), $committed );

				$product = wc_get_product( $room_id );
				$name    = $product ? $product->get_name() : __( 'this room', 'roova' );

				$order->update_status(
					'failed',
					sprintf(
						/* translators: %s: room name */
						__( 'Booking conflict: %s sold out while this order was being placed.', 'roova' ),
						$name
					)
				);

				throw new Exception(
					esc_html(
						sprintf(
							/* translators: %s: room name */
							__( 'Sorry — %s was just booked by someone else for those dates. Please choose different dates or another room.', 'roova' ),
							$name
						)
					)
				);
			}

			$row_data = array(
				'order_id'      => $order->get_id(),
				'order_item_id' => $item_id,
				'status'        => 'pending',
				'units'         => $units,
				'guest_name'    => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
				'expires_at'    => self::pending_expiry(),
			);

			if ( $hold ) {
				Roova_Holds::update( (int) $hold['id'], $row_data );
				$committed[] = (int) $hold['id'];
			} else {
				// The hold expired mid-checkout but the room is still free.
				$new_id = Roova_Holds::insert( array_merge(
					$row_data,
					array(
						'room_id'    => $room_id,
						'hotel_id'   => roova_get_room_hotel_id( $room_id ),
						'session_id' => roova_session_id(),
						'check_in'   => $booking['check_in'],
						'check_out'  => $booking['check_out'],
						'adults'     => isset( $booking['adults'] ) ? (int) $booking['adults'] : 1,
						'children'   => isset( $booking['children'] ) ? (int) $booking['children'] : 0,
					)
				) );
				if ( $new_id ) {
					$committed[] = $new_id;
				}
			}

			if ( $locked ) {
				Roova_Holds::unlock( $room_id );
			}
		}

		if ( $committed ) {
			$order->update_meta_data( '_roova_has_bookings', 'yes' );
			$order->save();
		}
	}

	/**
	 * When the unpaid grace period on a new order runs out.
	 *
	 * @return string|null MySQL datetime, or null for no expiry.
	 */
	protected static function pending_expiry() {
		$minutes = self::pending_minutes();
		if ( $minutes < 1 ) {
			return null;
		}
		return gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) + ( $minutes * MINUTE_IN_SECONDS ) );
	}

	/**
	 * Roll rows back to holds after a failed commit.
	 *
	 * @param int   $order_id Order ID.
	 * @param int[] $row_ids  Rows already committed in this run.
	 */
	protected static function release_order_rows( $order_id, $row_ids ) {
		foreach ( $row_ids as $row_id ) {
			Roova_Holds::update( $row_id, array(
				'status'        => 'cancelled',
				'order_id'      => absint( $order_id ),
				'order_item_id' => null,
				'expires_at'    => null,
			) );
		}
	}

	/* ---------------------------------------------------------------------
	 * Order status
	 * ------------------------------------------------------------------ */

	/**
	 * Booking status for an order status.
	 *
	 * @param string $order_status Order status without the wc- prefix.
	 * @return string
	 */
	public static function map_status( $order_status ) {
		$map = array(
			'pending'    => 'pending',
			'on-hold'    => 'pending',
			'processing' => 'confirmed',
			'completed'  => 'confirmed',
			'cancelled'  => 'cancelled',
			'refunded'   => 'cancelled',
			'failed'     => 'cancelled',
			'checkout-draft' => 'pending',
		);

		return isset( $map[ $order_status ] ) ? $map[ $order_status ] : 'pending';
	}

	/**
	 * Keep booking rows in step with the order.
	 *
	 * @param int      $order_id Order ID.
	 * @param string   $from     Old status.
	 * @param string   $to       New status.
	 * @param WC_Order $order    Order.
	 */
	public static function on_status_changed( $order_id, $from, $to, $order ) {
		unset( $from );

		$order = $order ? $order : wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$new_status = self::map_status( $to );
		$rows       = Roova_Holds::get_by_order( $order_id );

		// A manually created order, or one whose rows were cleaned up: rebuild
		// from the line item meta so admin-entered bookings still block dates.
		if ( ! $rows && in_array( $new_status, array( 'pending', 'confirmed' ), true ) ) {
			self::rebuild_rows_from_order( $order );
			$rows = Roova_Holds::get_by_order( $order_id );
		}

		foreach ( $rows as $row ) {
			$data = array( 'status' => $new_status );

			if ( 'confirmed' === $new_status ) {
				// A paid stay never expires.
				$data['expires_at'] = null;
			} elseif ( 'pending' === $new_status && 'on-hold' === $to ) {
				// Awaiting a bank transfer — the room stays held for the guest.
				$data['expires_at'] = null;
			}

			Roova_Holds::update( (int) $row['id'], $data );
		}

		if ( 'confirmed' === $new_status ) {
			self::flag_overbooking( $order );
		}
	}

	/**
	 * Recreate booking rows from an order's line item meta.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function rebuild_rows_from_order( $order ) {
		foreach ( $order->get_items() as $item_id => $item ) {
			$booking = $item->get_meta( '_roova_booking', true );
			if ( ! is_array( $booking ) || empty( $booking['room_id'] ) ) {
				continue;
			}

			Roova_Holds::insert( array(
				'room_id'       => absint( $booking['room_id'] ),
				'hotel_id'      => roova_get_room_hotel_id( $booking['room_id'] ),
				'order_id'      => $order->get_id(),
				'order_item_id' => $item_id,
				'status'        => 'pending',
				'check_in'      => $booking['check_in'],
				'check_out'     => $booking['check_out'],
				'units'         => max( 1, (int) $item->get_quantity() ),
				'adults'        => isset( $booking['adults'] ) ? (int) $booking['adults'] : 1,
				'children'      => isset( $booking['children'] ) ? (int) $booking['children'] : 0,
				'guest_name'    => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			) );
		}
	}

	/**
	 * Warn the front desk if a paid order ended up overbooking a room.
	 *
	 * A paid stay is never silently dropped — staff resolve it instead.
	 *
	 * @param WC_Order $order Order.
	 */
	protected static function flag_overbooking( $order ) {
		foreach ( Roova_Holds::get_by_order( $order->get_id() ) as $row ) {
			$details = roova_get_room_details( $row['room_id'] );
			$booked  = Roova_Availability::booked_units( $row['room_id'], $row['check_in'], $row['check_out'] );

			if ( $booked > $details['units'] ) {
				$product = wc_get_product( $row['room_id'] );
				$order->add_order_note(
					sprintf(
						/* translators: 1: room name, 2: units booked, 3: units available */
						__( 'Overbooking warning: %1$s has %2$d units booked for these dates but only %3$d exist. Please contact the guest.', 'roova' ),
						$product ? $product->get_name() : '#' . $row['room_id'],
						$booked,
						$details['units']
					)
				);
			}
		}
	}

	/* ---------------------------------------------------------------------
	 * Deletion
	 * ------------------------------------------------------------------ */

	/**
	 * Free the dates when an order is trashed or deleted.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function on_order_deleted( $order_id ) {
		foreach ( Roova_Holds::get_by_order( $order_id ) as $row ) {
			Roova_Holds::update( (int) $row['id'], array(
				'status'     => 'cancelled',
				'expires_at' => null,
			) );
		}
	}

	/**
	 * Legacy post-storage orders.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_post_trashed( $post_id ) {
		if ( 'shop_order' === get_post_type( $post_id ) ) {
			self::on_order_deleted( $post_id );
		}
	}

	/**
	 * Free the dates when a line item is removed from an order.
	 *
	 * @param int $item_id Order item ID.
	 */
	public static function on_order_item_deleted( $item_id ) {
		global $wpdb;

		$table = Roova_Schema::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'cancelled', order_item_id = NULL, expires_at = NULL WHERE order_item_id = %d", absint( $item_id ) ) );
		// phpcs:enable
	}
}
