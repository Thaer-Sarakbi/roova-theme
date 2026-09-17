<?php
/**
 * Exercises the confirmation page's own logic outside WordPress: which stay
 * forecasts cashback, which does not, and what the "what happens next" list
 * comes to in each case.
 *
 * Same shape as test-cashback.php — a flat script with a `check()` helper and
 * WordPress stubbed down to the handful of functions the pure logic touches.
 * The order is stubbed as the three methods the forecast actually asks it
 * (`has_status`, `needs_payment`, `get_user_id`), and the booking line as the
 * three keys the matcher reads, so this covers the gating and the wording
 * without a database or a WC_Order.
 *
 * The rule worth protecting here: **no matching offer means no step.** Nothing
 * generic is printed in its place, because "you may earn cashback" is not a
 * fact about a booking.
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

function add_filter() {}

function add_action() {}

function remove_action() {}

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

function get_post_meta( $post_id, $key = '', $single = true ) {
	return isset( $GLOBALS['roova_meta'][ $post_id ][ $key ] ) ? $GLOBALS['roova_meta'][ $post_id ][ $key ] : '';
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
	return '';
}

function update_user_meta() {
	return true;
}

function is_user_logged_in() {
	return (bool) $GLOBALS['roova_logged_in'];
}

function get_current_user_id() {
	return $GLOBALS['roova_logged_in'] ? 7 : 0;
}

function roova_icon_library() {
	return array( 'coins' => array( 'Cashback', '' ) );
}

function wp_verify_nonce() {
	return false;
}

function sanitize_email( $email ) {
	return trim( (string) $email );
}

function wp_unslash( $value ) {
	return $value;
}

function current_user_can() {
	return (bool) $GLOBALS['roova_can_read_orders'];
}

/**
 * Just enough of WC() for the guest fallback to read the session's address.
 */
function WC() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- mirrors WooCommerce.
	return $GLOBALS['roova_wc'];
}

class Roova_Test_Session {
	public function get( $key ) {
		if ( 'customer' !== $key ) {
			return '';
		}
		return '' === $GLOBALS['roova_session_email']
			? array()
			: array( 'email' => $GLOBALS['roova_session_email'] );
	}
}

class Roova_Test_WC {
	/** @var Roova_Test_Session|null */
	public $session;
}

/**
 * Not needed by anything under test, and stubbed away so inc/cashback.php's
 * sync never walks a stay list this script does not have.
 *
 * @return array[]
 */
function roova_account_stays() {
	return array();
}

/**
 * The booking line the confirmation reads. The real one is built from the order
 * item's `_roova_booking` meta by roova_order_lines(), in roova/inc/order.php.
 *
 * @return array|null
 */
function roova_order_primary_line() {
	return $GLOBALS['roova_line'];
}

/*
 * The rest of inc/order.php is not exercised here — the forecast and the steps
 * are the new logic; the totals, the chips and the status are the order page's,
 * already covered by the pages that share them.
 */
function roova_order_status() {
	return 'upcoming';
}

function roova_account_stay_chips() {
	return array( 'upcoming' => 'Upcoming' );
}

function roova_order_total_label() {
	return 'Total paid';
}

/**
 * The three things the forecast and the steps ask an order.
 */
class Roova_Test_Order {
	/** @var string */
	public $status = 'processing';

	/** @var bool */
	public $needs = false;

	/** @var int */
	public $user_id = 7;

	public function has_status( $status ) {
		return in_array( $this->status, (array) $status, true );
	}

	public function needs_payment() {
		return $this->needs;
	}

	public function get_user_id() {
		return $this->user_id;
	}

	public function get_customer_id() {
		return $this->user_id;
	}

	public function get_billing_email() {
		return $this->email;
	}

	public function get_id() {
		return 118;
	}

	/** @var string */
	public $email = 'member@example.com';
}

$GLOBALS['roova_titles']    = array( 101 => 'Ampang Point Star Hotel', 102 => 'Tanjung Rhu Retreat' );
$GLOBALS['roova_options']   = array();
$GLOBALS['roova_meta']      = array();
$GLOBALS['roova_logged_in'] = true;
$GLOBALS['roova_line']      = null;

$GLOBALS['roova_can_read_orders'] = false;
$GLOBALS['roova_session_email']   = '';
$GLOBALS['roova_wc']              = new Roova_Test_WC();
$GLOBALS['roova_wc']->session     = new Roova_Test_Session();

require __DIR__ . '/../roova/inc/helpers.php';
require __DIR__ . '/../roova/inc/cashback.php';
require __DIR__ . '/../roova/inc/received.php';

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
 * A booking line of the shape roova_order_lines() returns, reduced to the keys
 * the confirmation reads off it.
 */
function line( $hotel_id, $check_in, $check_out ) {
	return array(
		'hotel_id'  => $hotel_id,
		'hotel'     => get_the_title( $hotel_id ),
		'hotel_url' => 'https://example.test/hotel/',
		'check_in'  => $check_in,
		'check_out' => $check_out,
		'nights'    => roova_nights( $check_in, $check_out ),
	);
}

/**
 * Put one offer in place, exactly as the settings screen's save would leave it.
 */
function offers( $rules ) {
	$GLOBALS['roova_options'][ ROOVA_CASHBACK_OPTION ] = $rules;
}

/** An offer: RM10 for 3 nights or more at any hotel, clearing 7 days after. */
function three_nighter( $args = array() ) {
	return array_merge(
		array(
			'id'         => 'cb3',
			'created'    => '2026-01-01',
			'amount'     => 10,
			'nights'     => 3,
			'hotel'      => 0,
			'clear_days' => 7,
		),
		$args
	);
}

$order = new Roova_Test_Order();

/* ------------------------------------------------------- nights must match */

offers( array( three_nighter() ) );

$GLOBALS['roova_line'] = line( 101, '2026-09-04', '2026-09-07' );
$f                     = roova_received_cashback_forecast( $order );
check( 'a 3-night stay matches the 3-night offer', $f['amount'], 10.0 );
check( 'the forecast carries the nights booked', $f['nights'], 3 );
check( 'it clears clear_days after checkout', $f['clears'], '2026-09-14' );

$GLOBALS['roova_line'] = line( 101, '2026-09-04', '2026-09-09' );
$f                     = roova_received_cashback_forecast( $order );
check( 'a longer stay still matches — nights is a minimum', $f['amount'], 10.0 );

$GLOBALS['roova_line'] = line( 101, '2026-09-04', '2026-09-06' );
check( 'a 2-night stay matches nothing, so there is no forecast', roova_received_cashback_forecast( $order ), null );

$GLOBALS['roova_line'] = line( 101, '2026-09-04', '2026-09-05' );
$steps                 = roova_received_next_steps( $order );
check( 'and no cashback step is printed in its place', count( $steps ), 2 );
check( 'the two that remain are the hotel and the emails', $steps[0]['title'], 'Ampang Point Star Hotel confirms directly' );
check( 'the email step is second', $steps[1]['icon'], 'mail' );

/* ------------------------------------------------------- no offers at all */

offers( array() );
$GLOBALS['roova_line'] = line( 101, '2026-09-04', '2026-09-09' );
check( 'a site running no offers forecasts nothing', roova_received_cashback_forecast( $order ), null );

/* ------------------------------------------------------------ the wrong hotel */

offers( array( three_nighter( array( 'hotel' => 102 ) ) ) );
$GLOBALS['roova_line'] = line( 101, '2026-09-04', '2026-09-09' );
check( 'an offer tied to another hotel does not match', roova_received_cashback_forecast( $order ), null );

$GLOBALS['roova_line'] = line( 102, '2026-09-04', '2026-09-09' );
$f                     = roova_received_cashback_forecast( $order );
check( 'the same offer matches at its own hotel', $f['amount'], 10.0 );

/* ------------------------------------------------------------- offer window */

offers( array( three_nighter( array( 'expires' => '2026-09-05' ) ) ) );
$GLOBALS['roova_line'] = line( 101, '2026-09-04', '2026-09-09' );
check( 'a stay checking out after the offer ends earns nothing', roova_received_cashback_forecast( $order ), null );

offers( array( three_nighter( array( 'created' => '2026-10-01' ) ) ) );
check( 'nor one checking out before it started', roova_received_cashback_forecast( $order ), null );

/* -------------------------------------------------------- most valuable wins */

offers(
	array(
		three_nighter(),
		three_nighter( array( 'id' => 'cb5', 'amount' => 25, 'nights' => 5 ) ),
	)
);
$GLOBALS['roova_line'] = line( 101, '2026-09-04', '2026-09-09' );
$f                     = roova_received_cashback_forecast( $order );
check( 'a stay qualifying for two offers forecasts the bigger one', $f['amount'], 25.0 );

$GLOBALS['roova_line'] = line( 101, '2026-09-04', '2026-09-07' );
$f                     = roova_received_cashback_forecast( $order );
check( 'and the smaller one where only it applies', $f['amount'], 10.0 );

/* --------------------------------------------------------------- signed out */

offers( array( three_nighter() ) );
$GLOBALS['roova_line']      = line( 101, '2026-09-04', '2026-09-09' );
$GLOBALS['roova_logged_in'] = false;
check( 'a guest gets no forecast — there is no ledger to credit', roova_received_cashback_forecast( $order ), null );

$steps = roova_received_next_steps( $order );
check( 'and no cashback step either', count( $steps ), 2 );

$GLOBALS['roova_logged_in'] = true;

/* ------------------------------------------------ somebody else's order */

$order->user_id = 9;
check( 'a member looking at another member\'s order gets no forecast', roova_received_cashback_forecast( $order ), null );
$order->user_id = 7;

/* --------------------------------------------- a stay that cannot complete */

$order->status = 'cancelled';
check( 'a cancelled order forecasts nothing', roova_received_cashback_forecast( $order ), null );
check( 'and has no next steps at all', roova_received_next_steps( $order ), array() );

$order->status = 'refunded';
check( 'nor does a refunded one', roova_received_cashback_forecast( $order ), null );

$order->status = 'failed';
check( 'nor a failed one', roova_received_cashback_forecast( $order ), null );

$order->status = 'processing';

/* ----------------------------------------------------------- the steps read */

$steps = roova_received_next_steps( $order );
check( 'a paid booking gets all three steps', count( $steps ), 3 );
check( 'the cashback step quotes the amount', $steps[2]['title'], '10.00 cashback after checkout' );
check(
	'and the date it lands',
	$steps[2]['note'],
	'Your 5-night stay qualifies. It clears into your Roova balance on Sep 16, 2026.'
);

/*
 * An offer that clears the same day says so rather than printing the checkout
 * date twice — "it clears on Sep 9" beside a checkout of Sep 9 reads as a
 * mistake.
 */
offers( array( three_nighter( array( 'clear_days' => 0 ) ) ) );
$steps = roova_received_next_steps( $order );
check(
	'an offer with no waiting period says the day instead of a date',
	$steps[2]['note'],
	'Your 5-night stay qualifies. It clears into your Roova balance the day you check out.'
);

// Which second email is promised depends on where the money is.
$order->needs = true;
$steps        = roova_received_next_steps( $order );
check( 'an unpaid order is waiting on the payment clearing', strpos( $steps[1]['note'], 'payment clears' ) !== false, true );
$order->needs = false;
$steps        = roova_received_next_steps( $order );
check( 'a paid one on the hotel closing the stay', strpos( $steps[1]['note'], 'stay complete' ) !== false, true );

/* ------------------------------------------------------------ a lost hotel */

offers( array( three_nighter() ) );
$GLOBALS['roova_line'] = line( 555, '2026-09-04', '2026-09-09' );
$steps                 = roova_received_next_steps( $order );
check( 'a hotel this site has deleted drops its step rather than printing a blank', count( $steps ), 2 );
check( 'so the emails step is now first, and the list renumbers', $steps[0]['icon'], 'mail' );

/* ----------------------------------------------------------- no stay at all */

$GLOBALS['roova_line'] = null;
check( 'an order carrying no booking forecasts nothing', roova_received_cashback_forecast( $order ), null );

/* -------------------------------------------------------------- hotel rows */

/*
 * The check-in and check-out times default to 15:00 and 12:00 for every hotel,
 * so they must not be enough on their own to draw a panel headed with the
 * hotel's name and containing no way to reach it.
 */
check( 'a hotel with no address and no number shows no rows at all', roova_received_hotel_rows( 101 ), array() );

$GLOBALS['roova_meta'][101] = array(
	'_roova_address'       => 'Jalan Besar, Seri Kembangan, 43300 Selangor',
	'_roova_phone'         => '+60 3 8945 1200',
	'_roova_checkin_time'  => '15:00',
	'_roova_checkout_time' => '12:00',
);

$rows = roova_received_hotel_rows( 101 );
check( 'address, reception and front desk, in that order', count( $rows ), 3 );
check( 'the address is first', $rows[0]['icon'], 'pin' );
check( 'and links to the map', strpos( $rows[0]['url'], 'google.com/maps' ) !== false, true );
check( 'the number is shown exactly as it was typed', $rows[1]['value'], '+60 3 8945 1200' );
check( 'and dialled with the spaces taken out', $rows[1]['url'], 'tel:+60389451200' );
check( 'the times read as one line', $rows[2]['value'], 'Check in from 15:00 · check out by 12:00' );

// Coordinates beat a text query, the way the hotel page's map prefers them.
$GLOBALS['roova_meta'][101]['_roova_lat'] = '3.0244';
$GLOBALS['roova_meta'][101]['_roova_lng'] = '101.7076';
$rows                                     = roova_received_hotel_rows( 101 );
check( 'the map query uses the coordinates where the hotel has them', strpos( $rows[0]['url'], '3.0244%2C101.7076' ) !== false, true );

// A field holding something that is not a number must not become a dead link.
$GLOBALS['roova_meta'][101]['_roova_phone'] = 'Ask at the desk';
$rows                                       = roova_received_hotel_rows( 101 );
check( 'a number with no digits in it is printed as plain text', $rows[1]['url'], '' );

// No phone at all: the row goes, and the two that remain keep their order.
unset( $GLOBALS['roova_meta'][101]['_roova_phone'] );
$rows = roova_received_hotel_rows( 101 );
check( 'a hotel with no number shows no reception row', count( $rows ), 2 );
check( 'and the front desk times move up', $rows[1]['icon'], 'clock' );

check( 'a line with no hotel behind it shows nothing', roova_received_hotel_rows( 0 ), array() );

/* ------------------------------------------------------------ who may look
 *
 * The order key is not the gate. These are the checks that stop a confirmation
 * — a guest's name, phone number and email — opening to whoever is holding the
 * link, and they must stay no more permissive than WooCommerce's own.
 *
 * The Internal\Utilities\Users class does not exist in this script, so what is
 * exercised below is the public-API fallback.
 */

$gate = new Roova_Test_Order();

// A member's order: that member, signed in, and nobody else.
$gate->user_id              = 7;
$GLOBALS['roova_logged_in'] = true;
check( 'a member sees their own order', roova_received_visitor_may_view( $gate ), true );

$gate->user_id = 9;
check( 'another member does not', roova_received_visitor_may_view( $gate ), false );

$GLOBALS['roova_logged_in'] = false;
$gate->user_id              = 7;
check( 'and a signed-out visitor holding the key does not either', roova_received_visitor_may_view( $gate ), false );

/*
 * A guest order — no account to sign in to, so the address in the session is
 * what identifies the browser that paid.
 */
$gate->user_id = 0;

$GLOBALS['roova_session_email'] = 'member@example.com';
check( 'a guest whose session carries the billing address may look', roova_received_visitor_may_view( $gate ), true );

$GLOBALS['roova_session_email'] = 'someone.else@example.com';
check( 'a guest whose session carries another address may not', roova_received_visitor_may_view( $gate ), false );

$GLOBALS['roova_session_email'] = '';
check( 'nor one with no address in the session at all', roova_received_visitor_may_view( $gate ), false );

// Matching is case-insensitive, the way WooCommerce compares them.
$GLOBALS['roova_session_email'] = 'Member@Example.COM';
check( 'the addresses are compared without case', roova_received_visitor_may_view( $gate ), true );

// A shop manager reading an order is not a stranger holding a link.
$GLOBALS['roova_session_email']   = '';
$GLOBALS['roova_can_read_orders'] = true;
check( 'somebody who may read private orders is let through', roova_received_visitor_may_view( $gate ), true );
$GLOBALS['roova_can_read_orders'] = false;

// An order with no billing address has nothing to verify against.
$gate->email = '';
check( 'an order with no billing address is not gated on one', roova_received_visitor_may_view( $gate ), true );
$gate->email = 'member@example.com';

/* ------------------------------------------------------------------ done */

printf( "\n%d checks, %d failures\n", $checks, $failures );
exit( $failures > 0 ? 1 : 0 );
