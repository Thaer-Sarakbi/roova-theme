<?php
/**
 * Booking rows: holds, commits and the lock that makes them race-proof.
 *
 * The critical section is guarded with a MySQL named lock rather than a
 * transaction. WooCommerce runs its own transactions during checkout, and an
 * inner COMMIT would end the outer one early — a named lock gives us the same
 * mutual exclusion with none of that risk.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Roova_Holds
 */
class Roova_Holds {

	/**
	 * Cron hook that clears expired holds.
	 */
	const CLEANUP_HOOK = 'roova_release_expired_holds';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedule' ) );
		add_action( 'init', array( __CLASS__, 'schedule_cleanup' ) );
		add_action( self::CLEANUP_HOOK, array( __CLASS__, 'cleanup' ) );

		add_action( 'woocommerce_cart_item_removed', array( __CLASS__, 'on_cart_item_removed' ), 10, 2 );
		add_action( 'woocommerce_cart_item_restored', array( __CLASS__, 'on_cart_item_restored' ), 10, 2 );
		add_action( 'woocommerce_cart_emptied', array( __CLASS__, 'on_cart_emptied' ) );
	}

	/* ---------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------ */

	/**
	 * How long a hold survives without checkout, in minutes.
	 *
	 * @return int
	 */
	public static function hold_minutes() {
		$minutes = (int) roova_option( 'hold_minutes', 30 );
		if ( $minutes < 5 ) {
			$minutes = 30;
		}
		return (int) apply_filters( 'roova_hold_minutes', $minutes );
	}

	/**
	 * Expiry timestamp for a new hold, in site time.
	 *
	 * @return string MySQL datetime.
	 */
	protected static function hold_expiry() {
		return gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) + ( self::hold_minutes() * MINUTE_IN_SECONDS ) );
	}

	/* ---------------------------------------------------------------------
	 * Locking
	 * ------------------------------------------------------------------ */

	/**
	 * Take an exclusive lock on a room.
	 *
	 * @param int $room_id Room product ID.
	 * @return bool True when the lock was granted.
	 */
	public static function lock( $room_id ) {
		global $wpdb;

		$name = self::lock_name( $room_id );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$got = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $name, 10 ) );

		return '1' === (string) $got;
	}

	/**
	 * Release a room lock.
	 *
	 * @param int $room_id Room product ID.
	 */
	public static function unlock( $room_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::lock_name( $room_id ) ) );
	}

	/**
	 * Lock name, namespaced per site so multisite installs never collide.
	 * MySQL caps lock names at 64 characters.
	 *
	 * @param int $room_id Room product ID.
	 * @return string
	 */
	protected static function lock_name( $room_id ) {
		global $wpdb;
		return 'roova_' . substr( md5( $wpdb->prefix . DB_NAME ), 0, 16 ) . '_' . absint( $room_id );
	}

	/* ---------------------------------------------------------------------
	 * Rows
	 * ------------------------------------------------------------------ */

	/**
	 * Insert a booking row.
	 *
	 * @param array $data Row data.
	 * @return int Inserted row ID, or 0 on failure.
	 */
	public static function insert( $data ) {
		global $wpdb;

		$defaults = array(
			'room_id'       => 0,
			'hotel_id'      => 0,
			'order_id'      => 0,
			'order_item_id' => null,
			'session_id'    => '',
			'cart_item_key' => '',
			'status'        => 'hold',
			'check_in'      => '',
			'check_out'     => '',
			'units'         => 1,
			'adults'        => 1,
			'children'      => 0,
			'guest_name'    => '',
			'note'          => '',
			'created_at'    => current_time( 'mysql' ),
			'expires_at'    => null,
		);

		$data = wp_parse_args( $data, $defaults );

		$row = array(
			'room_id'       => absint( $data['room_id'] ),
			'hotel_id'      => absint( $data['hotel_id'] ),
			'order_id'      => absint( $data['order_id'] ),
			'order_item_id' => $data['order_item_id'] ? absint( $data['order_item_id'] ) : null,
			'session_id'    => substr( (string) $data['session_id'], 0, 64 ),
			'cart_item_key' => substr( (string) $data['cart_item_key'], 0, 64 ),
			'status'        => sanitize_key( $data['status'] ),
			'check_in'      => roova_sanitize_date( $data['check_in'] ),
			'check_out'     => roova_sanitize_date( $data['check_out'] ),
			'units'         => max( 1, (int) $data['units'] ),
			'adults'        => max( 0, (int) $data['adults'] ),
			'children'      => max( 0, (int) $data['children'] ),
			'guest_name'    => substr( sanitize_text_field( $data['guest_name'] ), 0, 200 ),
			'note'          => sanitize_textarea_field( $data['note'] ),
			'created_at'    => $data['created_at'],
			'expires_at'    => $data['expires_at'],
		);

		if ( ! $row['room_id'] || ! $row['check_in'] || ! $row['check_out'] ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->insert( Roova_Schema::table(), $row );

		return $inserted ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Update a booking row.
	 *
	 * @param int   $id   Row ID.
	 * @param array $data Columns to change.
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->update( Roova_Schema::table(), $data, array( 'id' => absint( $id ) ) );
	}

	/**
	 * Delete a booking row.
	 *
	 * @param int $id Row ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (bool) $wpdb->delete( Roova_Schema::table(), array( 'id' => absint( $id ) ) );
	}

	/**
	 * Fetch a single row.
	 *
	 * @param int $id Row ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = Roova_Schema::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ), ARRAY_A );
		// phpcs:enable
		return $row ? $row : null;
	}

	/**
	 * The hold row for a cart line, if there is one.
	 *
	 * WooCommerce builds a cart item key by hashing the product and its cart
	 * item data, so two guests booking the same room for the same dates get the
	 * *same* key. Every lookup must therefore be scoped to one session, or one
	 * guest would read and overwrite another's hold.
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param string $session_id    Session to look in. Defaults to the current one.
	 * @return array|null
	 */
	public static function get_by_cart_item_key( $cart_item_key, $session_id = null ) {
		global $wpdb;

		$session_id = ( null === $session_id ) ? roova_session_id() : $session_id;

		if ( ! $cart_item_key || ! $session_id ) {
			return null;
		}

		$table = Roova_Schema::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE cart_item_key = %s AND session_id = %s AND status = 'hold' ORDER BY id DESC LIMIT 1",
				(string) $cart_item_key,
				(string) $session_id
			),
			ARRAY_A
		);
		// phpcs:enable

		return $row ? $row : null;
	}

	/**
	 * Rows attached to an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array[]
	 */
	public static function get_by_order( $order_id ) {
		global $wpdb;

		$table = Roova_Schema::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d ORDER BY check_in ASC", absint( $order_id ) ), ARRAY_A );
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	/* ---------------------------------------------------------------------
	 * Holds
	 * ------------------------------------------------------------------ */

	/**
	 * Place a hold for a cart line, checking availability inside the room lock.
	 *
	 * @param array $booking Booking data (room_id, check_in, check_out, units, adults, children, cart_item_key).
	 * @return int|WP_Error Row ID, or an error explaining why the stay is not available.
	 */
	public static function place_hold( $booking ) {
		$room_id   = absint( $booking['room_id'] );
		$check_in  = roova_sanitize_date( $booking['check_in'] );
		$check_out = roova_sanitize_date( $booking['check_out'] );
		$units     = max( 1, (int) $booking['units'] );
		$session   = isset( $booking['session_id'] ) ? (string) $booking['session_id'] : roova_session_id();

		if ( ! $room_id || ! $check_in || ! $check_out ) {
			return new WP_Error( 'roova_invalid_stay', __( 'Please choose a valid check-in and check-out date.', 'roova' ) );
		}

		$locked = self::lock( $room_id );

		$available = Roova_Availability::available_units( $room_id, $check_in, $check_out, array(
			'exclude_cart_item_key' => isset( $booking['cart_item_key'] ) ? (string) $booking['cart_item_key'] : '',
		) );

		if ( $available < $units ) {
			if ( $locked ) {
				self::unlock( $room_id );
			}
			return new WP_Error(
				'roova_unavailable',
				$available > 0
					/* translators: %d: number of rooms still free */
					? sprintf( _n( 'Only %d room of this type is left for those dates.', 'Only %d rooms of this type are left for those dates.', $available, 'roova' ), $available )
					: __( 'That room is fully booked for the dates you picked.', 'roova' )
			);
		}

		$id = self::insert( array(
			'room_id'       => $room_id,
			'hotel_id'      => roova_get_room_hotel_id( $room_id ),
			'session_id'    => $session,
			'cart_item_key' => isset( $booking['cart_item_key'] ) ? $booking['cart_item_key'] : '',
			'status'        => 'hold',
			'check_in'      => $check_in,
			'check_out'     => $check_out,
			'units'         => $units,
			'adults'        => isset( $booking['adults'] ) ? (int) $booking['adults'] : 1,
			'children'      => isset( $booking['children'] ) ? (int) $booking['children'] : 0,
			'expires_at'    => self::hold_expiry(),
		) );

		if ( $locked ) {
			self::unlock( $room_id );
		}

		if ( ! $id ) {
			return new WP_Error( 'roova_hold_failed', __( 'We could not hold that room. Please try again.', 'roova' ) );
		}

		return $id;
	}

	/**
	 * Attach a hold to a cart item key once WooCommerce has generated it.
	 *
	 * @param int    $id            Row ID.
	 * @param string $cart_item_key Cart item key.
	 */
	public static function attach_cart_item_key( $id, $cart_item_key ) {
		self::update( $id, array( 'cart_item_key' => substr( (string) $cart_item_key, 0, 64 ) ) );
	}

	/**
	 * Push the expiry of every hold in the current session forward.
	 *
	 * @param string $session_id Session ID.
	 */
	public static function touch_session_holds( $session_id = '' ) {
		global $wpdb;

		$session_id = $session_id ? $session_id : roova_session_id();
		if ( ! $session_id ) {
			return;
		}

		$table = Roova_Schema::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET expires_at = %s WHERE session_id = %s AND status = 'hold'",
				self::hold_expiry(),
				$session_id
			)
		);
		// phpcs:enable
	}

	/**
	 * When the first of a visitor's holds runs out.
	 *
	 * Checkout counts down to it, so the guest can see how long the rooms in
	 * their cart stay theirs.
	 *
	 * @param string $session_id Session ID. Defaults to the current one.
	 * @return string MySQL datetime in site time, or '' when nothing is held.
	 */
	public static function session_expiry( $session_id = '' ) {
		global $wpdb;

		$session_id = $session_id ? $session_id : roova_session_id();
		if ( ! $session_id ) {
			return '';
		}

		$table = Roova_Schema::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$expiry = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MIN(expires_at) FROM {$table} WHERE session_id = %s AND status = 'hold' AND expires_at IS NOT NULL",
				$session_id
			)
		);
		// phpcs:enable

		return $expiry ? (string) $expiry : '';
	}

	/**
	 * Change the units/dates on an existing hold, re-checking availability.
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param int    $units         New unit count.
	 * @return true|WP_Error
	 */
	public static function update_hold_units( $cart_item_key, $units ) {
		$row = self::get_by_cart_item_key( $cart_item_key );
		if ( ! $row ) {
			return new WP_Error( 'roova_no_hold', __( 'That booking is no longer being held.', 'roova' ) );
		}

		$units   = max( 1, (int) $units );
		$room_id = (int) $row['room_id'];

		$locked    = self::lock( $room_id );
		$available = Roova_Availability::available_units( $room_id, $row['check_in'], $row['check_out'], array(
			'exclude_ids' => array( (int) $row['id'] ),
		) );

		if ( $available < $units ) {
			if ( $locked ) {
				self::unlock( $room_id );
			}
			return new WP_Error(
				'roova_unavailable',
				/* translators: %d: number of rooms still free */
				sprintf( _n( 'Only %d room of this type is left for those dates.', 'Only %d rooms of this type are left for those dates.', max( 0, $available ), 'roova' ), max( 0, $available ) )
			);
		}

		self::update( (int) $row['id'], array(
			'units'      => $units,
			'expires_at' => self::hold_expiry(),
		) );

		if ( $locked ) {
			self::unlock( $room_id );
		}

		return true;
	}

	/* ---------------------------------------------------------------------
	 * Releasing
	 * ------------------------------------------------------------------ */

	/**
	 * Drop the hold behind a removed cart line.
	 *
	 * @param string  $cart_item_key Cart item key.
	 * @param WC_Cart $cart          Cart.
	 */
	public static function on_cart_item_removed( $cart_item_key, $cart ) {
		unset( $cart );
		self::release_by_cart_item_key( $cart_item_key );
	}

	/**
	 * Put a hold back when a removed line is restored.
	 *
	 * @param string  $cart_item_key Cart item key.
	 * @param WC_Cart $cart          Cart.
	 */
	public static function on_cart_item_restored( $cart_item_key, $cart ) {
		$items = $cart ? $cart->get_cart() : array();
		if ( ! isset( $items[ $cart_item_key ]['roova_booking'] ) ) {
			return;
		}

		$booking                  = $items[ $cart_item_key ]['roova_booking'];
		$booking['cart_item_key'] = $cart_item_key;
		$booking['units']         = isset( $items[ $cart_item_key ]['quantity'] ) ? (int) $items[ $cart_item_key ]['quantity'] : 1;

		$result = self::place_hold( $booking );
		if ( is_wp_error( $result ) && function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );
		}
	}

	/**
	 * Release every hold in the session when the cart is emptied.
	 */
	public static function on_cart_emptied() {
		// Checkout empties the cart after creating the order; those holds have
		// already been converted to bookings and are no longer 'hold' rows.
		self::release_by_session( roova_session_id() );
	}

	/**
	 * Delete the hold for a cart line.
	 *
	 * Session scoped for the same reason as get_by_cart_item_key(): the key
	 * alone is shared between guests booking the same room and dates.
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param string $session_id    Session to delete from. Defaults to the current one.
	 */
	public static function release_by_cart_item_key( $cart_item_key, $session_id = null ) {
		global $wpdb;

		$session_id = ( null === $session_id ) ? roova_session_id() : $session_id;

		if ( ! $cart_item_key || ! $session_id ) {
			return;
		}

		$table = Roova_Schema::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE cart_item_key = %s AND session_id = %s AND status = 'hold'",
				(string) $cart_item_key,
				(string) $session_id
			)
		);
		// phpcs:enable
	}

	/**
	 * Delete every hold owned by a session.
	 *
	 * @param string $session_id Session ID.
	 */
	public static function release_by_session( $session_id ) {
		global $wpdb;

		if ( ! $session_id ) {
			return;
		}

		$table = Roova_Schema::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE session_id = %s AND status = 'hold'", (string) $session_id ) );
		// phpcs:enable
	}

	/* ---------------------------------------------------------------------
	 * Cleanup
	 * ------------------------------------------------------------------ */

	/**
	 * Add the five-minute cron interval.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function cron_schedule( $schedules ) {
		if ( ! isset( $schedules['roova_five_minutes'] ) ) {
			$schedules['roova_five_minutes'] = array(
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every five minutes (Roova)', 'roova' ),
			);
		}
		return $schedules;
	}

	/**
	 * Make sure the cleanup event is scheduled.
	 */
	public static function schedule_cleanup() {
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'roova_five_minutes', self::CLEANUP_HOOK );
		}
	}

	/**
	 * Clear out expired rows.
	 *
	 * Availability queries already ignore anything past its expiry, so this is
	 * tidy-up rather than something correctness depends on: abandoned carts are
	 * deleted, and unpaid orders that ran out of time are marked cancelled so
	 * the bookings screen tells the truth. Paying such an order still revives
	 * the booking, because order status always wins.
	 */
	public static function cleanup() {
		global $wpdb;

		$table = Roova_Schema::table();
		$now   = current_time( 'mysql' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE status = 'hold' AND expires_at IS NOT NULL AND expires_at < %s",
				$now
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'cancelled', expires_at = NULL
				 WHERE status = 'pending' AND expires_at IS NOT NULL AND expires_at < %s",
				$now
			)
		);
		// phpcs:enable
	}
}
