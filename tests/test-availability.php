<?php
/**
 * Exercises the theme's pure logic (date maths, per-night peak occupancy,
 * landmark parsing, criteria clamping) outside WordPress.
 */

define( 'ABSPATH', __DIR__ );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['roova_now'] = '2026-08-19 09:00:00';

function current_time( $format ) {
	if ( 'mysql' === $format ) {
		return $GLOBALS['roova_now'];
	}
	return gmdate( $format, strtotime( $GLOBALS['roova_now'] ) );
}

function apply_filters( $tag, $value ) {
	return $value;
}

function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, (array) $args );
}

function date_i18n( $format, $timestamp ) {
	return gmdate( $format, $timestamp );
}

function get_theme_mod( $key, $default = '' ) {
	return $default;
}

function absint( $value ) {
	return abs( (int) $value );
}

function get_post_meta( $id, $key, $single = true ) {
	$fixtures = $GLOBALS['roova_meta'];
	return isset( $fixtures[ $id ][ $key ] ) ? $fixtures[ $id ][ $key ] : '';
}

function get_the_terms() {
	return array();
}

function wc_get_product() {
	return null;
}

$GLOBALS['roova_meta'] = array(
	101 => array(
		'_roova_units'        => 3,
		'_roova_max_adults'   => 2,
		'_roova_max_children' => 1,
		'_roova_min_nights'   => 2,
	),
);

require __DIR__ . '/../roova/inc/helpers.php';
require __DIR__ . '/../roova/inc/booking/class-roova-availability.php';

/**
 * Opens up the protected peak-occupancy helper for testing.
 */
class Roova_Availability_Probe extends Roova_Availability {
	public static function peak( $rows, $nights ) {
		return self::peak_units( $rows, $nights );
	}
}

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

/* ------------------------------------------------------------------ dates */

check( 'nights: 29 Aug to 30 Aug is one night', roova_nights( '2026-08-29', '2026-08-30' ), 1 );
check( 'nights: same day is not a stay', roova_nights( '2026-08-29', '2026-08-29' ), 0 );
check( 'nights: reversed dates are not a stay', roova_nights( '2026-08-30', '2026-08-29' ), 0 );
check( 'nights: across a month boundary', roova_nights( '2026-08-30', '2026-09-02' ), 3 );
check( 'nights: rubbish input', roova_nights( 'tomorrow', '2026-08-30' ), 0 );
check( 'sanitize_date rejects 31 February', roova_sanitize_date( '2026-02-31' ), '' );
check( 'night list excludes the check-out day', roova_night_list( '2026-08-29', '2026-09-01' ), array( '2026-08-29', '2026-08-30', '2026-08-31' ) );
check( 'add_days crosses a month', roova_add_days( '2026-08-31', 1 ), '2026-09-01' );

/* -------------------------------------------------------- peak occupancy */

$stay = roova_night_list( '2026-08-29', '2026-09-01' ); // 29, 30, 31.

check(
	'no bookings means nothing occupied',
	Roova_Availability_Probe::peak( array(), $stay ),
	0
);

check(
	'one overlapping booking of 2 units',
	Roova_Availability_Probe::peak(
		array( array( 'units' => 2, 'check_in' => '2026-08-29', 'check_out' => '2026-09-01' ) ),
		$stay
	),
	2
);

check(
	'two bookings on different nights peak at the busier night, not the sum',
	Roova_Availability_Probe::peak(
		array(
			array( 'units' => 2, 'check_in' => '2026-08-29', 'check_out' => '2026-08-30' ),
			array( 'units' => 1, 'check_in' => '2026-08-31', 'check_out' => '2026-09-01' ),
		),
		$stay
	),
	2
);

check(
	'bookings that share a night add up',
	Roova_Availability_Probe::peak(
		array(
			array( 'units' => 2, 'check_in' => '2026-08-29', 'check_out' => '2026-08-31' ),
			array( 'units' => 1, 'check_in' => '2026-08-30', 'check_out' => '2026-09-02' ),
		),
		$stay
	),
	3
);

check(
	'a same-day turnaround does not collide',
	Roova_Availability_Probe::peak(
		array( array( 'units' => 3, 'check_in' => '2026-08-26', 'check_out' => '2026-08-29' ) ),
		$stay
	),
	0
);

check(
	'a booking starting on our check-out day does not collide',
	Roova_Availability_Probe::peak(
		array( array( 'units' => 3, 'check_in' => '2026-09-01', 'check_out' => '2026-09-04' ) ),
		$stay
	),
	0
);

check(
	'a booking that swallows the stay still counts once',
	Roova_Availability_Probe::peak(
		array( array( 'units' => 1, 'check_in' => '2026-08-01', 'check_out' => '2026-10-01' ) ),
		$stay
	),
	1
);

check(
	'units below one are treated as one room',
	Roova_Availability_Probe::peak(
		array( array( 'units' => 0, 'check_in' => '2026-08-30', 'check_out' => '2026-08-31' ) ),
		$stay
	),
	1
);

/* ------------------------------------------------------------- occupancy */

check( 'a party that fits one room', roova_room_fits( 101, 2, 1, 1 ), true );
check( 'too many adults for one room', roova_room_fits( 101, 3, 0, 1 ), false );
check( 'the same party fits across two rooms', roova_room_fits( 101, 3, 0, 2 ), true );
check( 'too many children', roova_room_fits( 101, 2, 3, 1 ), false );

/* ------------------------------------------------------------- landmarks */

$landmarks = roova_parse_landmarks( "Petronas Twin Towers | 20.6 km\n\n  KL Bird Park|17.6 km  \nJalan Alor\n   \n" );
check( 'landmarks: blank lines are skipped', count( $landmarks ), 3 );
check( 'landmarks: name and distance split on the bar', $landmarks[0], array( 'name' => 'Petronas Twin Towers', 'distance' => '20.6 km' ) );
check( 'landmarks: surrounding whitespace is trimmed', $landmarks[1], array( 'name' => 'KL Bird Park', 'distance' => '17.6 km' ) );
check( 'landmarks: a distance is optional', $landmarks[2], array( 'name' => 'Jalan Alor', 'distance' => '' ) );

/* -------------------------------------------------------------- criteria */

$criteria = roova_normalise_criteria( array( 'check_in' => '2026-08-01', 'check_out' => '2026-08-05' ) );
check( 'a stay in the past is pushed to tomorrow', $criteria['check_in'], '2026-08-20' );
check( 'and check-out follows check-in', $criteria['check_out'], '2026-08-21' );

$criteria = roova_normalise_criteria( array( 'check_in' => '2026-09-10', 'check_out' => '2026-09-09' ) );
check( 'check-out before check-in is corrected to one night', $criteria['check_out'], '2026-09-11' );

$criteria = roova_normalise_criteria( array( 'check_in' => '2026-09-10', 'check_out' => '2027-09-10', 'rooms' => 99, 'adults' => 0 ) );
check( 'a stay longer than the cap is trimmed to 60 nights', roova_nights( $criteria['check_in'], $criteria['check_out'] ), 60 );
check( 'rooms are clamped', $criteria['rooms'], 8 );
check( 'there is always at least one adult', $criteria['adults'], 1 );

printf( "\n%d checks, %d failures\n", $checks, $failures );
exit( $failures > 0 ? 1 : 0 );
