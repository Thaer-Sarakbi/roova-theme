<?php
/**
 * Exercises the cashback rules outside WordPress: which stay earns which
 * reward, and what the three balance figures come to.
 *
 * Same shape as test-availability.php — a flat script with a `check()` helper,
 * WordPress stubbed down to the handful of functions the pure logic touches.
 * The ledger's storage is stubbed too, so this covers the arithmetic and the
 * matching without needing a database.
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['roova_now'] = '2026-09-02 09:00:00';

function current_time( $format ) {
	if ( 'mysql' === $format ) {
		return $GLOBALS['roova_now'];
	}
	return gmdate( $format, strtotime( $GLOBALS['roova_now'] ) );
}

function apply_filters( $tag, $value ) {
	return $value;
}

function do_action() {}

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

function get_post_meta() {
	return '';
}

function get_the_terms() {
	return array();
}

function wc_get_product() {
	return null;
}

function __( $text ) {
	return $text;
}

function _n( $single, $plural, $number ) {
	return 1 === (int) $number ? $single : $plural;
}

function esc_html( $text ) {
	return $text;
}

function wp_strip_all_tags( $text ) {
	return strip_tags( $text );
}

function sanitize_text_field( $value ) {
	return trim( (string) $value );
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function number_format_i18n( $number, $decimals = 0 ) {
	return number_format( (float) $number, (int) $decimals );
}

function get_the_title( $id ) {
	return isset( $GLOBALS['roova_titles'][ $id ] ) ? $GLOBALS['roova_titles'][ $id ] : '';
}

function get_post_status( $id ) {
	return isset( $GLOBALS['roova_titles'][ $id ] ) ? 'publish' : false;
}

function get_option( $key, $default = false ) {
	return isset( $GLOBALS['roova_options'][ $key ] ) ? $GLOBALS['roova_options'][ $key ] : $default;
}

function get_user_meta( $user_id, $key, $single = true ) {
	return isset( $GLOBALS['roova_user_meta'][ $user_id ][ $key ] ) ? $GLOBALS['roova_user_meta'][ $user_id ][ $key ] : '';
}

function update_user_meta( $user_id, $key, $value ) {
	$GLOBALS['roova_user_meta'][ $user_id ][ $key ] = $value;
	return true;
}

function get_current_user_id() {
	return 7;
}

function roova_icon_library() {
	return array(
		'coins' => array( 'Cashback', '' ),
		'moon'  => array( 'Long stay', '' ),
	);
}

/**
 * The stay list roova_cashback_sync() walks. Stubbed here so the rules can be
 * tested without orders — the real one is in roova/inc/account.php.
 *
 * @param int $user_id User ID.
 * @return array[]
 */
function roova_account_stays( $user_id = 0 ) {
	return $GLOBALS['roova_stays'];
}

$GLOBALS['roova_titles']    = array( 101 => 'Ampang Point Star Hotel', 102 => 'Tanjung Rhu Retreat' );
$GLOBALS['roova_options']   = array();
$GLOBALS['roova_user_meta'] = array();
$GLOBALS['roova_stays']     = array();

require __DIR__ . '/../roova/inc/helpers.php';
require __DIR__ . '/../roova/inc/cashback.php';

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
 * A stay row of the shape roova_account_stays() returns.
 */
function stay( $key, $hotel_id, $check_in, $check_out, $status = 'completed' ) {
	return array(
		'key'       => $key,
		'order_id'  => 900,
		'hotel_id'  => $hotel_id,
		'hotel'     => get_the_title( $hotel_id ),
		'check_in'  => $check_in,
		'check_out' => $check_out,
		'nights'    => roova_nights( $check_in, $check_out ),
		'status'    => $status,
	);
}

/**
 * A reward rule, sanitised the way the settings screen's save would leave it.
 */
function reward( $args ) {
	$rewards = roova_cashback_sanitize_rewards( array( $args ) );
	return $rewards[0];
}

/**
 * Reset the stored option, ledger and per-request sync guard between cases.
 */
function reset_state( $rewards, $stays ) {
	$GLOBALS['roova_options'][ ROOVA_CASHBACK_OPTION ] = $rewards;
	$GLOBALS['roova_stays']                            = $stays;
	$GLOBALS['roova_user_meta']                        = array();

	/*
	 * roova_cashback_sync() guards itself with a static — one sync per member
	 * per request, which is what production wants — so each case needs its own
	 * user id to be synced afresh. The step of 10 leaves room for a case that
	 * needs a second id of its own, without colliding with the next case.
	 */
	static $user = 100;
	$user += 10;
	return $user;
}

/* ------------------------------------------------------------- sanitising */

$r = reward( array( 'amount' => '10.005', 'nights' => 7, 'hotel' => 101, 'clear_days' => 14, 'created' => '2026-01-01' ) );
check( 'an amount is rounded to two places', $r['amount'], 10.01 );
check( 'a rule keeps the created date it was given', $r['created'], '2026-01-01' );
check( 'a rule with no icon falls back to coins', $r['icon'], 'coins' );

$r = reward( array( 'amount' => 5, 'nights' => 0 ) );
check( 'a duration under one night is lifted to one', $r['nights'], 1 );
check( 'a rule with no created date is dated today', $r['created'], '2026-09-02' );

check( 'a reward worth nothing is dropped', roova_cashback_sanitize_rewards( array( array( 'amount' => 0, 'nights' => 3 ) ) ), array() );
check( 'a reward worth less than nothing is dropped', roova_cashback_sanitize_rewards( array( array( 'amount' => -5 ) ) ), array() );

$sorted = roova_cashback_sanitize_rewards( array(
	array( 'id' => 'small', 'amount' => 5, 'created' => '2026-01-01' ),
	array( 'id' => 'big', 'amount' => 25, 'created' => '2026-01-01' ),
	array( 'id' => 'mid', 'amount' => 10, 'created' => '2026-01-01' ),
) );
check( 'rules come back most valuable first', array_column( $sorted, 'id' ), array( 'big', 'mid', 'small' ) );

$dupes = roova_cashback_sanitize_rewards( array(
	array( 'id' => 'same', 'amount' => 10, 'created' => '2026-01-01' ),
	array( 'id' => 'same', 'amount' => 20, 'created' => '2026-01-01' ),
) );
check( 'two rules never share an id', $dupes[0]['id'] === $dupes[1]['id'], false );

/* ---------------------------------------------------------------- matching */

$seven = reward( array( 'id' => 'seven', 'amount' => 10, 'nights' => 7, 'hotel' => 101, 'created' => '2026-01-01', 'expires' => '2026-12-31' ) );

check( 'a stay short of the duration does not qualify', roova_cashback_reward_matches( $seven, stay( 'a', 101, '2026-06-01', '2026-06-06' ) ), false );
check( 'a stay of exactly the duration qualifies', roova_cashback_reward_matches( $seven, stay( 'a', 101, '2026-06-01', '2026-06-08' ) ), true );
check( 'a longer stay qualifies too — the duration is a minimum', roova_cashback_reward_matches( $seven, stay( 'a', 101, '2026-06-01', '2026-06-15' ) ), true );
check( 'another hotel does not qualify', roova_cashback_reward_matches( $seven, stay( 'a', 102, '2026-06-01', '2026-06-15' ) ), false );
check( 'a stay that ended before the rule existed does not qualify', roova_cashback_reward_matches( $seven, stay( 'a', 101, '2025-06-01', '2025-06-15' ) ), false );
check( 'a stay that ended after the rule expired does not qualify', roova_cashback_reward_matches( $seven, stay( 'a', 101, '2027-06-01', '2027-06-15' ) ), false );

$any = reward( array( 'id' => 'any', 'amount' => 5, 'nights' => 2, 'hotel' => 0, 'created' => '2026-01-01' ) );
check( 'an all-hotels rule matches any hotel', roova_cashback_reward_matches( $any, stay( 'a', 102, '2026-06-01', '2026-06-04' ) ), true );
check( 'an all-hotels rule with no expiry never runs out', roova_cashback_reward_validity( $any ), 'Always on' );

/* ------------------------------------------------------- the best of a set */

$user = reset_state(
	array(
		array( 'id' => 'any', 'amount' => 5, 'nights' => 2, 'hotel' => 0, 'created' => '2026-01-01' ),
		array( 'id' => 'seven', 'amount' => 10, 'nights' => 7, 'hotel' => 101, 'created' => '2026-01-01' ),
	),
	array()
);

$best = roova_cashback_best_reward( stay( 'a', 101, '2026-06-01', '2026-06-15' ) );
check( 'a stay matching two rules earns the more valuable one', $best['id'], 'seven' );

$best = roova_cashback_best_reward( stay( 'a', 102, '2026-06-01', '2026-06-15' ) );
check( 'at another hotel the same stay earns the all-hotels rule', $best['id'], 'any' );

check( 'a one-night stay matches nothing', roova_cashback_best_reward( stay( 'a', 101, '2026-06-01', '2026-06-02' ) ), null );

/* ------------------------------------------------------------- the ledger */

$user = reset_state(
	array( array( 'id' => 'any', 'amount' => 12.50, 'nights' => 2, 'hotel' => 0, 'created' => '2026-01-01', 'clear_days' => 14 ) ),
	array(
		// Cleared: checked out well over 14 days ago.
		stay( '900-1', 101, '2026-06-01', '2026-06-05' ),
		// Pending: checked out four days ago, so it clears on 2026-09-12.
		stay( '900-2', 102, '2026-08-25', '2026-08-29' ),
		// Neither: still to come.
		stay( '900-3', 101, '2026-10-01', '2026-10-05', 'upcoming' ),
		// Neither: the order was cancelled.
		stay( '900-4', 101, '2026-05-01', '2026-05-06', 'cancelled' ),
	)
);

$balances = roova_cashback_balances( $user );
check( 'only cleared cashback is available', $balances['available'], 12.5 );
check( 'cashback inside its clearing window is pending', $balances['pending'], 12.5 );
check( 'earned all time counts both', $balances['earned'], 25.0 );
check( 'and counts the stays behind them', $balances['stays'], 2 );
check( 'the pending card knows when the next amount lands', $balances['clears'], '2026-09-12' );
check( 'an upcoming stay earns nothing yet', isset( roova_cashback_ledger( $user )['900-3'] ), false );
check( 'a cancelled stay earns nothing', isset( roova_cashback_ledger( $user )['900-4'] ), false );

/* --------------------------------------------------------- frozen amounts */

$user = reset_state(
	array( array( 'id' => 'any', 'amount' => 20, 'nights' => 2, 'hotel' => 0, 'created' => '2026-01-01', 'clear_days' => 0 ) ),
	array( stay( '900-1', 101, '2026-06-01', '2026-06-05' ) )
);

check( 'the stay earns what the rule was worth', roova_cashback_balances( $user )['earned'], 20.0 );

// The client halves the offer. What was already earned must not move.
$GLOBALS['roova_options'][ ROOVA_CASHBACK_OPTION ] = array(
	array( 'id' => 'any', 'amount' => 10, 'nights' => 2, 'hotel' => 0, 'created' => '2026-01-01', 'clear_days' => 0 ),
);

check( 'editing the rule afterwards does not rewrite it', roova_cashback_balances( $user )['earned'], 20.0 );

// And deleting it outright leaves the earning alone.
$GLOBALS['roova_options'][ ROOVA_CASHBACK_OPTION ] = array();

check( 'deleting the rule does not claw it back', roova_cashback_balances( $user )['earned'], 20.0 );

/* ---------------------------------------------------------- a refund later */

$user = reset_state(
	array( array( 'id' => 'any', 'amount' => 15, 'nights' => 2, 'hotel' => 0, 'created' => '2026-01-01', 'clear_days' => 0 ) ),
	array( stay( '900-1', 101, '2026-06-01', '2026-06-05' ) )
);

check( 'the completed stay earns', roova_cashback_balances( $user )['available'], 15.0 );

// The order is refunded after the fact, so the stay stops being a completed one.
$GLOBALS['roova_stays'] = array( stay( '900-1', 101, '2026-06-01', '2026-06-05', 'cancelled' ) );
$user++;
$GLOBALS['roova_user_meta'][ $user ] = $GLOBALS['roova_user_meta'][ $user - 1 ];

check( 'a stay refunded afterwards gives the cashback back', roova_cashback_balances( $user )['available'], 0.0 );

/* ------------------------------------------------------------ redemptions */

$user = reset_state(
	array( array( 'id' => 'any', 'amount' => 40, 'nights' => 2, 'hotel' => 0, 'created' => '2026-01-01', 'clear_days' => 0 ) ),
	array( stay( '900-1', 101, '2026-06-01', '2026-06-05' ) )
);

roova_cashback_ledger( $user );
roova_cashback_record( $user, array( 'key' => 'redeem-1', 'type' => 'redeem', 'amount' => 25, 'label' => 'Redeemed', 'earned' => '2026-08-01' ) );

$balances = roova_cashback_balances( $user );
check( 'a redemption comes off the available balance', $balances['available'], 15.0 );
check( 'but never off what was earned', $balances['earned'], 40.0 );

roova_cashback_record( $user, array( 'key' => 'redeem-1', 'type' => 'redeem', 'amount' => 25, 'label' => 'Redeemed', 'earned' => '2026-08-01' ) );
check( 'recording the same redemption twice only counts once', roova_cashback_balances( $user )['available'], 15.0 );

roova_cashback_record( $user, array( 'key' => 'redeem-2', 'type' => 'redeem', 'amount' => 999, 'label' => 'Too much', 'earned' => '2026-08-02' ) );
check( 'a balance never goes negative', roova_cashback_balances( $user )['available'], 0.0 );

/* -------------------------------------------------------------- activity */

$rows = roova_cashback_activity( $user );
check( 'every ledger entry is an activity row', count( $rows ), 3 );
check( 'the newest row comes first', $rows[0]['date'], 'Aug 2, 2026' );
check( 'a redemption reads as a subtraction', $rows[0]['sign'], "\u{2212}" );
check( 'and is marked applied', $rows[0]['state'], 'Applied' );
check( 'an earning reads as an addition', $rows[2]['sign'], '+' );
check( 'the hotel name is read back from the site', $rows[2]['label'], 'Stay at Ampang Point Star Hotel' );

printf( "\n%d checks, %d failures\n", $checks, $failures );
exit( $failures > 0 ? 1 : 0 );
