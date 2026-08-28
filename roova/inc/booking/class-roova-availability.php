<?php
/**
 * Availability maths.
 *
 * A room type has N identical units. For any requested stay we look at every
 * booking that overlaps it, add up the units taken on each individual night,
 * and take the busiest night. That is what the stay costs us in inventory.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Roova_Availability
 */
class Roova_Availability {

	/**
	 * Statuses that occupy inventory.
	 *
	 * @return string[]
	 */
	public static function active_statuses() {
		return array( 'hold', 'pending', 'confirmed' );
	}

	/**
	 * Bookings that overlap a stay.
	 *
	 * Overlap rule: an existing booking collides when it starts before our
	 * check-out and ends after our check-in. Same-day turnarounds (one guest
	 * checks out the morning another checks in) do not collide.
	 *
	 * @param int    $room_id   Room product ID.
	 * @param string $check_in  Y-m-d.
	 * @param string $check_out Y-m-d.
	 * @param array  $args      exclude_cart_item_key, exclude_session_holds, exclude_ids.
	 * @return array[] Row objects as associative arrays.
	 */
	public static function get_overlapping_rows( $room_id, $check_in, $check_out, $args = array() ) {
		global $wpdb;

		$args = wp_parse_args( $args, array(
			'exclude_cart_item_key' => '',
			'exclude_session'       => null,
			'exclude_session_holds' => false,
			'exclude_ids'           => array(),
		) );

		$room_id   = absint( $room_id );
		$check_in  = roova_sanitize_date( $check_in );
		$check_out = roova_sanitize_date( $check_out );

		if ( ! $room_id || ! $check_in || ! $check_out ) {
			return array();
		}

		$table    = Roova_Schema::table();
		$statuses = self::active_statuses();
		$status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		$where  = "room_id = %d AND check_in < %s AND check_out > %s AND status IN ({$status_placeholders}) AND ( expires_at IS NULL OR expires_at > %s )";
		$params = array_merge(
			array( $room_id, $check_out, $check_in ),
			$statuses,
			array( current_time( 'mysql' ) )
		);

		/*
		 * A line's own hold must not block that same line from growing. Scope
		 * this to the session: WooCommerce gives two guests the same cart item
		 * key for the same room and dates, and excluding a stranger's hold here
		 * would hand out a room twice.
		 */
		if ( $args['exclude_cart_item_key'] ) {
			$exclude_session = ( null === $args['exclude_session'] ) ? roova_session_id() : $args['exclude_session'];

			if ( $exclude_session ) {
				$where   .= ' AND NOT ( cart_item_key = %s AND session_id = %s )';
				$params[] = $args['exclude_cart_item_key'];
				$params[] = $exclude_session;
			}
		}

		/*
		 * Every hold this visitor owns is a line in their cart, and adding a
		 * booking replaces that cart — so on the way in, their own holds are
		 * about to be released and must not count against them. Same session
		 * scoping as above, and holds only: a row that became an order is not
		 * theirs to take back.
		 */
		if ( $args['exclude_session_holds'] ) {
			$exclude_session = ( null === $args['exclude_session'] ) ? roova_session_id() : $args['exclude_session'];

			if ( $exclude_session ) {
				$where   .= " AND NOT ( status = 'hold' AND session_id = %s )";
				$params[] = $exclude_session;
			}
		}

		$exclude_ids = array_filter( array_map( 'absint', (array) $args['exclude_ids'] ) );
		if ( $exclude_ids ) {
			$where .= ' AND id NOT IN (' . implode( ',', $exclude_ids ) . ')';
		}

		$sql = "SELECT id, room_id, units, check_in, check_out, status, session_id, cart_item_key, order_id FROM {$table} WHERE {$where}";

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		// phpcs:enable

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Units taken on the busiest night of a stay.
	 *
	 * @param int    $room_id   Room product ID.
	 * @param string $check_in  Y-m-d.
	 * @param string $check_out Y-m-d.
	 * @param array  $args      See get_overlapping_rows().
	 * @return int
	 */
	public static function booked_units( $room_id, $check_in, $check_out, $args = array() ) {
		$rows = self::get_overlapping_rows( $room_id, $check_in, $check_out, $args );
		if ( ! $rows ) {
			return 0;
		}
		return self::peak_units( $rows, roova_night_list( $check_in, $check_out ) );
	}

	/**
	 * The highest number of units occupied on any one of the given nights.
	 *
	 * @param array[]  $rows   Booking rows.
	 * @param string[] $nights Y-m-d dates.
	 * @return int
	 */
	protected static function peak_units( $rows, $nights ) {
		if ( ! $nights ) {
			return 0;
		}

		$per_night = array_fill_keys( $nights, 0 );

		foreach ( $rows as $row ) {
			$units = max( 1, (int) $row['units'] );
			foreach ( roova_night_list( $row['check_in'], $row['check_out'] ) as $night ) {
				if ( isset( $per_night[ $night ] ) ) {
					$per_night[ $night ] += $units;
				}
			}
		}

		return $per_night ? max( $per_night ) : 0;
	}

	/**
	 * Units still bookable for a stay.
	 *
	 * @param int    $room_id   Room product ID.
	 * @param string $check_in  Y-m-d.
	 * @param string $check_out Y-m-d.
	 * @param array  $args      See get_overlapping_rows().
	 * @return int
	 */
	public static function available_units( $room_id, $check_in, $check_out, $args = array() ) {
		if ( ! roova_nights( $check_in, $check_out ) ) {
			return 0;
		}

		$details = roova_get_room_details( $room_id );
		$total   = (int) $details['units'];
		if ( $total < 1 ) {
			return 0;
		}

		$available = $total - self::booked_units( $room_id, $check_in, $check_out, $args );

		/**
		 * Filter the available unit count for a room and stay.
		 *
		 * @param int    $available Units left.
		 * @param int    $room_id   Room product ID.
		 * @param string $check_in  Y-m-d.
		 * @param string $check_out Y-m-d.
		 */
		return (int) apply_filters( 'roova_available_units', max( 0, $available ), $room_id, $check_in, $check_out );
	}

	/**
	 * Can this stay be booked?
	 *
	 * @param int    $room_id   Room product ID.
	 * @param string $check_in  Y-m-d.
	 * @param string $check_out Y-m-d.
	 * @param int    $units     Units wanted.
	 * @param array  $args      See get_overlapping_rows().
	 * @return bool
	 */
	public static function is_available( $room_id, $check_in, $check_out, $units = 1, $args = array() ) {
		$units   = max( 1, (int) $units );
		$details = roova_get_room_details( $room_id );

		if ( roova_nights( $check_in, $check_out ) < $details['min_nights'] ) {
			return false;
		}

		return self::available_units( $room_id, $check_in, $check_out, $args ) >= $units;
	}

	/**
	 * Nights on which a room is completely booked out, for greying out the calendar.
	 *
	 * @param int    $room_id Room product ID.
	 * @param string $from    Y-m-d.
	 * @param string $to      Y-m-d (exclusive).
	 * @return string[] Y-m-d dates.
	 */
	public static function full_nights( $room_id, $from, $to ) {
		$details = roova_get_room_details( $room_id );
		$total   = (int) $details['units'];
		$nights  = roova_night_list( $from, $to );

		if ( $total < 1 ) {
			return $nights;
		}
		if ( ! $nights ) {
			return array();
		}

		$rows      = self::get_overlapping_rows( $room_id, $from, $to );
		$per_night = array_fill_keys( $nights, 0 );

		foreach ( $rows as $row ) {
			$units = max( 1, (int) $row['units'] );
			foreach ( roova_night_list( $row['check_in'], $row['check_out'] ) as $night ) {
				if ( isset( $per_night[ $night ] ) ) {
					$per_night[ $night ] += $units;
				}
			}
		}

		$full = array();
		foreach ( $per_night as $night => $used ) {
			if ( $used >= $total ) {
				$full[] = $night;
			}
		}

		return $full;
	}

	/**
	 * Nights on which every room in a hotel is booked out.
	 *
	 * @param int    $hotel_id Hotel product ID.
	 * @param string $from     Y-m-d.
	 * @param string $to       Y-m-d.
	 * @return string[]
	 */
	public static function hotel_full_nights( $hotel_id, $from, $to ) {
		$room_ids = roova_get_hotel_room_ids( $hotel_id );
		if ( ! $room_ids ) {
			return array();
		}

		$full = null;
		foreach ( $room_ids as $room_id ) {
			$room_full = self::full_nights( $room_id, $from, $to );
			$full      = ( null === $full ) ? $room_full : array_intersect( $full, $room_full );
			if ( ! $full ) {
				break;
			}
		}

		return $full ? array_values( $full ) : array();
	}

	/**
	 * Rooms in a hotel that can take the requested stay and party size.
	 *
	 * @param int   $hotel_id Hotel product ID.
	 * @param array $criteria Search criteria.
	 * @return array[] room_id => array( product, available, rate, total ).
	 */
	public static function get_bookable_rooms( $hotel_id, $criteria = null ) {
		$criteria = $criteria ? roova_normalise_criteria( $criteria ) : roova_get_criteria();
		$nights   = roova_nights( $criteria['check_in'], $criteria['check_out'] );
		$rooms    = array();

		foreach ( roova_get_hotel_room_ids( $hotel_id ) as $room_id ) {
			$product = wc_get_product( $room_id );
			if ( ! $product || ! $product->is_purchasable() ) {
				continue;
			}

			$details = roova_get_room_details( $room_id );

			/*
			 * Holds count against availability here, including the visitor's
			 * own — what they already hold is in their cart, not still on sale.
			 * Templates surface that separately as "in your cart".
			 */
			$available = self::available_units( $room_id, $criteria['check_in'], $criteria['check_out'] );
			$in_cart   = class_exists( 'Roova_Cart' ) ? Roova_Cart::units_in_cart( $room_id, $criteria['check_in'], $criteria['check_out'] ) : 0;

			$rate = roova_room_rate( $room_id );

			$rooms[ $room_id ] = array(
				'product'   => $product,
				'details'   => $details,
				'available' => $available,
				'in_cart'   => $in_cart,
				'fits'      => roova_room_fits( $room_id, $criteria['adults'], $criteria['children'], $criteria['rooms'] ),
				'rate'      => $rate,
				'nights'    => $nights,
				'total'     => $rate * max( 1, $nights ) * max( 1, (int) $criteria['rooms'] ),
				'bookable'  => $available >= $criteria['rooms'] && $nights >= $details['min_nights'],
			);
		}

		return $rooms;
	}

	/**
	 * The cheapest bookable nightly rate in a hotel for the given criteria.
	 *
	 * @param int   $hotel_id Hotel product ID.
	 * @param array $criteria Search criteria.
	 * @return array array( 'rate' => float|null, 'has_availability' => bool ).
	 */
	public static function hotel_lowest_rate( $hotel_id, $criteria = null ) {
		$rooms = self::get_bookable_rooms( $hotel_id, $criteria );
		$rates = array();

		foreach ( $rooms as $room ) {
			if ( $room['bookable'] && $room['fits'] && $room['rate'] > 0 ) {
				$rates[] = $room['rate'];
			}
		}

		return array(
			'rate'             => $rates ? min( $rates ) : null,
			'has_availability' => (bool) $rates,
		);
	}
}
