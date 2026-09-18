<?php
/**
 * Exercises the search results page's own logic outside WordPress: which
 * landmark a hotel card shows beside its location, and the order the results
 * are listed in.
 *
 * Same shape as the other test scripts — a flat script with a `check()` helper
 * and WordPress stubbed down to the handful of functions the pure logic
 * touches. Hotel meta is stubbed, so this covers the picking and the sorting
 * without needing a database or a product.
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['roova_meta'] = array();
$GLOBALS['roova_score'] = array();

function absint( $value ) {
	return abs( (int) $value );
}

function apply_filters( $tag, $value ) {
	return $value;
}

function do_action() {}

function add_action() {}

function add_filter() {}

function __( $text ) {
	return $text;
}

function _n( $single, $plural, $number ) {
	return 1 === (int) $number ? $single : $plural;
}

function esc_html( $text ) {
	return $text;
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function sanitize_text_field( $value ) {
	return trim( (string) $value );
}

function wp_unslash( $value ) {
	return $value;
}

function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, (array) $args );
}

function get_theme_mod( $key, $default = '' ) {
	return $default;
}

function get_option( $key, $default = false ) {
	return $default;
}

function current_time( $format ) {
	return gmdate( $format );
}

function date_i18n( $format, $timestamp ) {
	return gmdate( $format, $timestamp );
}

function number_format_i18n( $number, $decimals = 0 ) {
	return number_format( (float) $number, (int) $decimals );
}

function get_the_terms() {
	return array();
}

function wc_get_product() {
	return null;
}

function get_post_meta( $post_id, $key, $single = true ) {
	return isset( $GLOBALS['roova_meta'][ $post_id ][ $key ] ) ? $GLOBALS['roova_meta'][ $post_id ][ $key ] : '';
}

/**
 * The guest score roova_sort_search_results() ranks by. The real one is in
 * roova/inc/reviews.php, which needs $wpdb.
 *
 * @param int $hotel_id Hotel product ID.
 * @return float
 */
function roova_hotel_rating( $hotel_id ) {
	return isset( $GLOBALS['roova_score'][ $hotel_id ] ) ? (float) $GLOBALS['roova_score'][ $hotel_id ] : 0.0;
}

require __DIR__ . '/../roova/inc/helpers.php';
require __DIR__ . '/../roova/inc/search.php';

$failures = 0;
$checks   = 0;

function check( $label, $actual, $expected ) {
	global $failures, $checks;
	$checks++;
	$ok = $actual === $expected;
	if ( ! $ok ) {
		$failures++;
		printf( "FAIL  %s\n      expected: %s\n      actual:   %s\n", $label, var_export( $expected, true ), var_export( $actual, true ) );
	} else {
		printf( "ok    %s\n", $label );
	}
}

/**
 * Give a hotel a Popular landmarks box.
 *
 * @param int    $hotel_id Hotel product ID.
 * @param string $raw      Textarea contents.
 */
function seed_landmarks( $hotel_id, $raw ) {
	$GLOBALS['roova_meta'][ $hotel_id ]['_roova_landmarks_popular'] = $raw;
}

/**
 * A search result row of the shape roova_search_hotels() returns.
 *
 * @param int        $hotel_id Hotel product ID.
 * @param float|null $rate     Cheapest nightly rate, or null.
 * @return array
 */
function result( $hotel_id, $rate ) {
	return array(
		'hotel_id'         => $hotel_id,
		'product'          => null,
		'rate'             => $rate,
		'has_availability' => null !== $rate,
		'rooms_left'       => null !== $rate ? 4 : 0,
		'rooms'            => array(),
	);
}

/**
 * The hotel IDs in a sorted result list, in order.
 *
 * @param array[] $results Results.
 * @return int[]
 */
function ids( $results ) {
	$out = array();
	foreach ( $results as $row ) {
		$out[] = $row['hotel_id'];
	}
	return $out;
}

/* ------------------------------------------------------------- landmarks */

seed_landmarks( 101, "Plaza Damas | 400 m\nHartamas Shopping Centre | 750 m\nPublika | 1.2 km" );
seed_landmarks( 102, "Petronas Twin Towers\nKLCC Park" );
seed_landmarks( 103, "" );
seed_landmarks( 104, "Batu Caves\nKL Sentral | 2.4 km\nMid Valley" );

$first = roova_hotel_feature_landmark( 101 );

check( 'a hotel with landmarks gets one', is_array( $first ), true );
check( 'it comes with its distance', '' !== $first['distance'], true );
check(
	'and it is one of the hotel\'s own',
	in_array( $first['name'], array( 'Plaza Damas', 'Hartamas Shopping Centre', 'Publika' ), true ),
	true
);

check( 'the same hotel picks the same landmark again', roova_hotel_feature_landmark( 101 ), $first );
check( 'and again after that', roova_hotel_feature_landmark( 101 ), $first );

check( 'landmarks with no distance are not candidates', roova_hotel_feature_landmark( 102 ), null );
check( 'an empty box gives nothing', roova_hotel_feature_landmark( 103 ), null );

$mixed = roova_hotel_feature_landmark( 104 );
check( 'a mixed list picks the only one that carries a distance', $mixed['name'], 'KL Sentral' );
check( 'with the distance the site owner typed', $mixed['distance'], '2.4 km' );

// The list itself is untouched — the whole list is what the card's second row
// prints, distances and all.
check( 'the full list is still three lines', count( roova_hotel_popular_landmarks( 104 ) ), 3 );
check( 'starting with the first line typed', roova_hotel_popular_landmarks( 104 )[0]['name'], 'Batu Caves' );

/* ------------------------------------------------------------------ sort */

$GLOBALS['roova_score'] = array( 201 => 4.2, 202 => 4.8, 203 => 5.0 );

$results = array(
	result( 201, 120.0 ),
	result( 202, 80.0 ),
	result( 203, null ),
);

check( 'recommended leaves the list as search built it', ids( roova_sort_search_results( $results, 'recommended' ) ), array( 201, 202, 203 ) );
check( 'price, low to high', ids( roova_sort_search_results( $results, 'price-low' ) ), array( 202, 201, 203 ) );
check( 'price, high to low', ids( roova_sort_search_results( $results, 'price-high' ) ), array( 201, 202, 203 ) );
check( 'guest rating, best first', ids( roova_sort_search_results( $results, 'rating' ) ), array( 202, 201, 203 ) );

// 203 has the best score of the three and no rooms for these dates. Whatever
// the sort, it stays at the bottom: a price list that opens with a hotel you
// cannot book is not a price list.
check(
	'a hotel with nothing free stays last, even top-rated',
	ids( roova_sort_search_results( $results, 'rating' ) )[2],
	203
);

check( 'sorting does not lose a result', count( roova_sort_search_results( $results, 'price-low' ) ), 3 );

$_GET = array();
check( 'no sort asked for means recommended', roova_search_sort(), 'recommended' );

$_GET['roova_sort'] = 'price-high';
check( 'a known sort is honoured', roova_search_sort(), 'price-high' );

$_GET['roova_sort'] = 'cheapest-ever';
check( 'an invented one falls back to recommended', roova_search_sort(), 'recommended' );

$_GET = array();
check( 'and the option list is the whitelist', array_keys( roova_search_sort_options() ), array( 'recommended', 'price-low', 'price-high', 'rating' ) );

printf( "\n%d checks, %d failures\n", $checks, $failures );
exit( $failures > 0 ? 1 : 0 );
