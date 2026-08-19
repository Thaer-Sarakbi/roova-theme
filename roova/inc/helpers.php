<?php
/**
 * Shared data helpers: hotel + room meta, dates, and the current search criteria.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * Theme option (Customizer setting) with a default.
 *
 * @param string $key     Option key without the roova_ prefix.
 * @param mixed  $default Default value.
 * @return mixed
 */
function roova_option( $key, $default = '' ) {
	return get_theme_mod( 'roova_' . $key, $default );
}

/* -------------------------------------------------------------------------
 * Dates
 * ---------------------------------------------------------------------- */

/**
 * Normalise a date string to Y-m-d, or return '' when it is not a real date.
 *
 * @param string $value Raw date.
 * @return string
 */
function roova_sanitize_date( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}
	$date = DateTime::createFromFormat( 'Y-m-d', $value );
	if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
		return '';
	}
	return $value;
}

/**
 * Today in the site's timezone as Y-m-d.
 *
 * @return string
 */
function roova_today() {
	return current_time( 'Y-m-d' );
}

/**
 * Add days to a Y-m-d date.
 *
 * @param string $date Date.
 * @param int    $days Days to add (may be negative).
 * @return string
 */
function roova_add_days( $date, $days ) {
	$dt = new DateTime( $date . ' 00:00:00' );
	$dt->modify( sprintf( '%+d days', (int) $days ) );
	return $dt->format( 'Y-m-d' );
}

/**
 * Number of nights between two dates. Zero or less means an invalid stay.
 *
 * @param string $check_in  Check-in date.
 * @param string $check_out Check-out date.
 * @return int
 */
function roova_nights( $check_in, $check_out ) {
	$check_in  = roova_sanitize_date( $check_in );
	$check_out = roova_sanitize_date( $check_out );
	if ( ! $check_in || ! $check_out ) {
		return 0;
	}
	$in  = new DateTime( $check_in . ' 00:00:00' );
	$out = new DateTime( $check_out . ' 00:00:00' );
	if ( $out <= $in ) {
		return 0;
	}
	return (int) $in->diff( $out )->days;
}

/**
 * Every night (stay date) in a range. Check-out day is not a night.
 *
 * @param string $check_in  Check-in date.
 * @param string $check_out Check-out date.
 * @return string[] Y-m-d dates.
 */
function roova_night_list( $check_in, $check_out ) {
	$nights = roova_nights( $check_in, $check_out );
	$dates  = array();
	for ( $i = 0; $i < $nights; $i++ ) {
		$dates[] = roova_add_days( $check_in, $i );
	}
	return $dates;
}

/**
 * Format a date the way the design shows it: "Aug 29 / 2026".
 *
 * @param string $date Y-m-d date.
 * @return string
 */
function roova_format_date( $date ) {
	$date = roova_sanitize_date( $date );
	if ( ! $date ) {
		return '';
	}
	return date_i18n( 'M j / Y', strtotime( $date . ' 00:00:00' ) );
}

/* -------------------------------------------------------------------------
 * Search criteria
 * ---------------------------------------------------------------------- */

/**
 * Default search criteria: tomorrow to the day after, one room, two adults.
 *
 * @return array
 */
function roova_default_criteria() {
	return array(
		'destination' => '',
		'hotel_id'    => 0,
		'check_in'    => roova_add_days( roova_today(), 1 ),
		'check_out'   => roova_add_days( roova_today(), 2 ),
		'rooms'       => 1,
		'adults'      => 2,
		'children'    => 0,
	);
}

/**
 * Read the visitor's search criteria: request first, then session, then defaults.
 *
 * Read-only — no nonce needed, these are shareable GET search parameters.
 *
 * @return array
 */
function roova_get_criteria() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$criteria = roova_default_criteria();

	$stored = roova_session_get( 'roova_criteria' );
	if ( is_array( $stored ) ) {
		$criteria = array_merge( $criteria, $stored );
	}

	$from_request = false;
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['roova_dest'] ) ) {
		$criteria['destination'] = sanitize_text_field( wp_unslash( $_GET['roova_dest'] ) );
		$from_request            = true;
	}
	if ( isset( $_GET['roova_hotel'] ) ) {
		$criteria['hotel_id'] = absint( $_GET['roova_hotel'] );
		$from_request         = true;
	}
	if ( isset( $_GET['checkin'] ) ) {
		$date = roova_sanitize_date( sanitize_text_field( wp_unslash( $_GET['checkin'] ) ) );
		if ( $date ) {
			$criteria['check_in'] = $date;
			$from_request         = true;
		}
	}
	if ( isset( $_GET['checkout'] ) ) {
		$date = roova_sanitize_date( sanitize_text_field( wp_unslash( $_GET['checkout'] ) ) );
		if ( $date ) {
			$criteria['check_out'] = $date;
			$from_request          = true;
		}
	}
	foreach ( array( 'rooms', 'adults', 'children' ) as $key ) {
		if ( isset( $_GET[ $key ] ) ) {
			$criteria[ $key ] = absint( $_GET[ $key ] );
			$from_request     = true;
		}
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$criteria = roova_normalise_criteria( $criteria );

	if ( $from_request ) {
		roova_session_set( 'roova_criteria', $criteria );
	}

	$cache = $criteria;
	return $cache;
}

/**
 * Clamp criteria into a usable shape: a stay in the future of at least one night.
 *
 * @param array $criteria Raw criteria.
 * @return array
 */
function roova_normalise_criteria( $criteria ) {
	$defaults = roova_default_criteria();
	$criteria = wp_parse_args( $criteria, $defaults );

	$criteria['check_in']  = roova_sanitize_date( $criteria['check_in'] );
	$criteria['check_out'] = roova_sanitize_date( $criteria['check_out'] );

	if ( ! $criteria['check_in'] || $criteria['check_in'] < roova_today() ) {
		$criteria['check_in'] = $defaults['check_in'];
	}
	if ( ! $criteria['check_out'] || $criteria['check_out'] <= $criteria['check_in'] ) {
		$criteria['check_out'] = roova_add_days( $criteria['check_in'], 1 );
	}

	$max_nights = (int) apply_filters( 'roova_max_nights', 60 );
	if ( roova_nights( $criteria['check_in'], $criteria['check_out'] ) > $max_nights ) {
		$criteria['check_out'] = roova_add_days( $criteria['check_in'], $max_nights );
	}

	$criteria['rooms']    = max( 1, min( 8, (int) $criteria['rooms'] ) );
	$criteria['adults']   = max( 1, min( 16, (int) $criteria['adults'] ) );
	$criteria['children'] = max( 0, min( 12, (int) $criteria['children'] ) );
	$criteria['hotel_id'] = absint( $criteria['hotel_id'] );

	return $criteria;
}

/**
 * Build a URL with the criteria applied as query args.
 *
 * @param string $url      Base URL.
 * @param array  $criteria Criteria.
 * @return string
 */
function roova_criteria_url( $url, $criteria = null ) {
	$criteria = $criteria ? roova_normalise_criteria( $criteria ) : roova_get_criteria();
	$args     = array(
		'checkin'  => $criteria['check_in'],
		'checkout' => $criteria['check_out'],
		'rooms'    => $criteria['rooms'],
		'adults'   => $criteria['adults'],
		'children' => $criteria['children'],
	);
	if ( $criteria['destination'] ) {
		$args['roova_dest'] = $criteria['destination'];
	}
	return add_query_arg( $args, $url );
}

/**
 * The search results page URL.
 *
 * @return string
 */
function roova_search_url() {
	$page_id = (int) get_option( 'roova_search_page_id' );
	if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
		return get_permalink( $page_id );
	}
	if ( function_exists( 'wc_get_page_permalink' ) ) {
		return wc_get_page_permalink( 'shop' );
	}
	return home_url( '/' );
}

/* -------------------------------------------------------------------------
 * Session (WooCommerce session, with a graceful no-op fallback)
 * ---------------------------------------------------------------------- */

/**
 * Read a value from the WooCommerce session.
 *
 * @param string $key Key.
 * @return mixed|null
 */
function roova_session_get( $key ) {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return null;
	}
	return WC()->session->get( $key );
}

/**
 * Write a value to the WooCommerce session.
 *
 * @param string $key   Key.
 * @param mixed  $value Value.
 */
function roova_session_set( $key, $value ) {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return;
	}
	WC()->session->set( $key, $value );
}

/**
 * Stable identifier for the current visitor's cart, used to own booking holds.
 *
 * @return string
 */
function roova_session_id() {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return '';
	}
	$id = WC()->session->get_customer_id();
	return $id ? (string) $id : '';
}

/* -------------------------------------------------------------------------
 * Hotels
 * ---------------------------------------------------------------------- */

/**
 * Hotel detail meta with defaults applied.
 *
 * @param int $hotel_id Hotel product ID.
 * @return array
 */
function roova_get_hotel_details( $hotel_id ) {
	$hotel_id = absint( $hotel_id );
	$keys     = array(
		'address'            => '',
		'lat'                => '',
		'lng'                => '',
		'map_zoom'           => '15',
		'checkin_time'       => '15:00',
		'checkout_time'      => '12:00',
		'stars'              => '0',
		'score'              => '',
		'score_label'        => '',
		'review_count'       => '',
		'score_cleanliness'  => '',
		'score_location'     => '',
		'score_service'      => '',
		'landmarks_popular'  => '',
		'landmarks_nearby'   => '',
		'phone'              => '',
	);

	$details = array();
	foreach ( $keys as $key => $default ) {
		$value           = get_post_meta( $hotel_id, '_roova_' . $key, true );
		$details[ $key ] = ( '' === $value || null === $value ) ? $default : $value;
	}

	return $details;
}

/**
 * Parse a landmark textarea into name/distance pairs.
 *
 * One landmark per line, optionally "Name | 1.2 km".
 *
 * @param string $raw Raw textarea contents.
 * @return array[] Each item: array( 'name' => string, 'distance' => string ).
 */
function roova_parse_landmarks( $raw ) {
	$items = array();
	$lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$name     = $line;
		$distance = '';
		if ( false !== strpos( $line, '|' ) ) {
			$parts    = explode( '|', $line, 2 );
			$name     = trim( $parts[0] );
			$distance = trim( $parts[1] );
		}
		if ( '' === $name ) {
			continue;
		}
		$items[] = array(
			'name'     => $name,
			'distance' => $distance,
		);
	}
	return $items;
}

/**
 * All published hotel products.
 *
 * @param array $args Extra WP_Query args.
 * @return int[] Hotel product IDs.
 */
function roova_get_hotel_ids( $args = array() ) {
	$defaults = array(
		'post_type'              => 'product',
		'post_status'            => 'publish',
		'posts_per_page'         => -1,
		'fields'                 => 'ids',
		'orderby'                => 'menu_order title',
		'order'                  => 'ASC',
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
		'tax_query'              => array(
			array(
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => 'hotel',
			),
		),
	);

	$query = new WP_Query( wp_parse_args( $args, $defaults ) );
	return array_map( 'absint', $query->posts );
}

/**
 * Rooms that belong to a hotel.
 *
 * @param int $hotel_id Hotel product ID.
 * @return int[] Room product IDs.
 */
function roova_get_hotel_room_ids( $hotel_id ) {
	$hotel_id = absint( $hotel_id );
	if ( ! $hotel_id ) {
		return array();
	}

	$query = new WP_Query( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
		'meta_query'     => array(
			array(
				'key'   => '_roova_hotel_id',
				'value' => $hotel_id,
			),
		),
		'tax_query'      => array(
			array(
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => 'room',
			),
		),
	) );

	return array_map( 'absint', $query->posts );
}

/**
 * The hotel a room belongs to.
 *
 * @param int $room_id Room product ID.
 * @return int Hotel product ID, or 0.
 */
function roova_get_room_hotel_id( $room_id ) {
	return absint( get_post_meta( absint( $room_id ), '_roova_hotel_id', true ) );
}

/**
 * Room settings with defaults.
 *
 * @param int $room_id Room product ID.
 * @return array
 */
function roova_get_room_details( $room_id ) {
	$room_id = absint( $room_id );

	$units = get_post_meta( $room_id, '_roova_units', true );
	$units = ( '' === $units ) ? 1 : (int) $units;

	$max_adults   = (int) get_post_meta( $room_id, '_roova_max_adults', true );
	$max_children = (int) get_post_meta( $room_id, '_roova_max_children', true );

	return array(
		'hotel_id'     => roova_get_room_hotel_id( $room_id ),
		'units'        => max( 0, $units ),
		'max_adults'   => $max_adults > 0 ? $max_adults : 2,
		'max_children' => max( 0, $max_children ),
		'size'         => (string) get_post_meta( $room_id, '_roova_size', true ),
		'beds'         => (string) get_post_meta( $room_id, '_roova_beds', true ),
		'min_nights'   => max( 1, (int) get_post_meta( $room_id, '_roova_min_nights', true ) ),
		'view'         => (string) get_post_meta( $room_id, '_roova_view', true ),
	);
}

/**
 * Can a room hold this party (per unit booked)?
 *
 * @param int $room_id  Room product ID.
 * @param int $adults   Adults in total.
 * @param int $children Children in total.
 * @param int $units    Units being booked.
 * @return bool
 */
function roova_room_fits( $room_id, $adults, $children, $units = 1 ) {
	$details = roova_get_room_details( $room_id );
	$units   = max( 1, (int) $units );

	if ( $adults > $details['max_adults'] * $units ) {
		return false;
	}
	if ( $children > $details['max_children'] * $units ) {
		return false;
	}
	return true;
}

/**
 * Nightly rate for a room.
 *
 * @param int $room_id Room product ID.
 * @return float
 */
function roova_room_rate( $room_id ) {
	$product = wc_get_product( $room_id );
	if ( ! $product ) {
		return 0.0;
	}
	return (float) $product->get_price();
}

/**
 * Destination terms attached to a hotel.
 *
 * @param int $hotel_id Hotel product ID.
 * @return WP_Term[]
 */
function roova_get_hotel_destinations( $hotel_id ) {
	$terms = get_the_terms( absint( $hotel_id ), 'pa_destination' );
	return is_array( $terms ) ? $terms : array();
}

/**
 * A short "City, Region" label for a hotel, taken from its destination terms.
 *
 * @param int $hotel_id Hotel product ID.
 * @return string
 */
function roova_hotel_location_label( $hotel_id ) {
	$terms = roova_get_hotel_destinations( $hotel_id );
	if ( $terms ) {
		return $terms[0]->name;
	}
	$details = roova_get_hotel_details( $hotel_id );
	return $details['address'];
}
