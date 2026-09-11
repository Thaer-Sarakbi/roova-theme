<?php
/**
 * Exercises the RoovaVIP rules outside WordPress: which tier a member lands on,
 * and — the part that is money — what its discount takes off a booking.
 *
 * Same shape as test-availability.php and test-cashback.php: a flat script with
 * a `check()` helper and WordPress stubbed down to the handful of functions the
 * pure logic touches. `roova_account_completed_count()` is stubbed as well, so
 * a member's tier can be set by hand without orders behind it.
 */

define( 'ABSPATH', __DIR__ );

function apply_filters( $tag, $value ) {
	return $value;
}

function add_action() {}

function absint( $value ) {
	return abs( (int) $value );
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

function sanitize_text_field( $value ) {
	return trim( (string) $value );
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function number_format_i18n( $number, $decimals = 0 ) {
	return number_format( (float) $number, (int) $decimals );
}

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['roova_options'] ) ? $GLOBALS['roova_options'][ $key ] : $default;
}

function get_user_meta( $user_id, $key, $single = true ) {
	return isset( $GLOBALS['roova_user_meta'][ $user_id ][ $key ] ) ? $GLOBALS['roova_user_meta'][ $user_id ][ $key ] : '';
}

function get_current_user_id() {
	return $GLOBALS['roova_current_user'];
}

function roova_icon_library() {
	return array(
		'percent'    => array( 'Discount', '' ),
		'bed-double' => array( 'Bookings', '' ),
	);
}

function is_admin() {
	return false;
}

function wc_tax_enabled() {
	return true;
}

function wc_get_price_decimals() {
	return 2;
}

/**
 * Nightly rates by room. The real one is in roova/inc/helpers.php and asks the
 * product; here it is whatever a case says it is.
 *
 * @param int $room_id Room product ID.
 * @return float
 */
function roova_room_rate( $room_id ) {
	return isset( $GLOBALS['roova_rates'][ $room_id ] ) ? (float) $GLOBALS['roova_rates'][ $room_id ] : 0.0;
}

/**
 * Just enough of WooCommerce's cart to price a stay against: the lines, and a
 * record of the fees the theme asked it to add.
 */
class WC_Cart {
	public $lines = array();
	public $fees  = array();

	public function __construct( $lines = array() ) {
		$this->lines = $lines;
	}

	public function get_cart() {
		return $this->lines;
	}

	public function add_fee( $name, $amount, $taxable = false, $tax_class = '' ) {
		$this->fees[] = array(
			'name'   => $name,
			'amount' => round( $amount, 2 ),
		);
	}
}

/**
 * One room in a cart, priced the way WooCommerce prices it.
 *
 * @param int $room_id Room product ID.
 * @param int $nights  Nights.
 * @param int $units   Rooms of this type.
 * @return array
 */
function roova_test_line( $room_id, $nights, $units = 1 ) {
	return array(
		'roova_booking' => array( 'room_id' => $room_id, 'nights' => $nights ),
		'line_total'    => roova_room_rate( $room_id ) * $nights * $units,
	);
}

/**
 * Completed stays per member. The real one is in roova/inc/account.php and
 * counts orders; here it is whatever a case says it is.
 *
 * @param int $user_id User ID.
 * @return int
 */
function roova_account_completed_count( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : $GLOBALS['roova_current_user'];

	return isset( $GLOBALS['roova_completed'][ $user_id ] ) ? (int) $GLOBALS['roova_completed'][ $user_id ] : 0;
}

$GLOBALS['roova_rates']        = array( 81 => 210.0, 82 => 160.0 );
$GLOBALS['roova_options']      = array();
$GLOBALS['roova_user_meta']    = array();
$GLOBALS['roova_completed']    = array();
$GLOBALS['roova_current_user'] = 0;

require __DIR__ . '/../roova/inc/vip.php';

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

/* --------------------------------------------------------- the percentage */

check( 'a percentage survives intact', roova_vip_sanitize_percent( 10 ), 10.0 );
check( 'a fractional percentage keeps two places', roova_vip_sanitize_percent( '7.255' ), 7.26 );
check( 'a negative percentage would add money, so it is zero', roova_vip_sanitize_percent( -5 ), 0.0 );
check( 'nothing above 100: the store would owe the guest', roova_vip_sanitize_percent( 140 ), 100.0 );
check( 'rubbish is no discount', roova_vip_sanitize_percent( 'ten per cent' ), 0.0 );
check( 'an empty field is no discount', roova_vip_sanitize_percent( '' ), 0.0 );

check( 'a whole percentage is written without decimals', roova_vip_format_percent( 10 ), '10' );
check( 'a half percentage keeps one', roova_vip_format_percent( 7.5 ), '7.5' );
check( 'and two when it needs them', roova_vip_format_percent( 7.25 ), '7.25' );

/* ------------------------------------------------------------ the amount */

check( '10% of 400', roova_vip_discount_amount( 400, 10 ), 40.0 );
check( '7.5% of 249.90, rounded to the cent', roova_vip_discount_amount( 249.90, 7.5 ), 18.74 );
check( 'a store with no decimals rounds to the unit', roova_vip_discount_amount( 249.90, 7.5, 0 ), 19.0 );
check( 'no discount takes nothing off', roova_vip_discount_amount( 400, 0 ), 0.0 );
check( 'nothing is taken off an empty cart', roova_vip_discount_amount( 0, 10 ), 0.0 );
check( 'a discount never exceeds the amount it comes off', roova_vip_discount_amount( 400, 100 ), 400.0 );
check( 'and never turns a total negative', roova_vip_discount_amount( -50, 10 ), 0.0 );

/* -------------------------------------------------------------- the tiers */

$GLOBALS['roova_options']['roova_vip_tiers'] = array(
	array( 'name' => 'VIP Gold', 'min' => 5, 'discount' => '10', 'benefits' => array() ),
	array( 'name' => 'Bronze', 'min' => 0, 'benefits' => array() ),
	array( 'name' => 'VIP Silver', 'min' => 2, 'discount' => 5.5, 'benefits' => array() ),
);

$tiers = roova_vip_tiers();

check( 'tiers come back sorted by threshold', array_column( $tiers, 'name' ), array( 'Bronze', 'VIP Silver', 'VIP Gold' ) );
check( 'a stored discount is read back as a number', $tiers[2]['discount'], 10.0 );
check( 'a tier saved without one has no discount', $tiers[0]['discount'], 0.0 );
check( 'a tier with no discount key at all is safe to ask', roova_vip_tier_discount( array( 'name' => 'Ad hoc' ) ), 0.0 );
check( 'so is nothing at all', roova_vip_tier_discount( null ), 0.0 );

/* ---------------------------------------------------- what a member gets */

$GLOBALS['roova_completed'][ 21 ] = 0;
check( 'a new member is on the floor tier, which gives nothing', roova_vip_discount_percent( 21 ), 0.0 );

$GLOBALS['roova_completed'][ 22 ] = 3;
check( 'three stays reaches Silver', roova_vip_discount_percent( 22 ), 5.5 );

$GLOBALS['roova_completed'][ 23 ] = 9;
check( 'nine reaches Gold', roova_vip_discount_percent( 23 ), 10.0 );

// An admin pinning a member to a tier decides their discount too.
$GLOBALS['roova_user_meta'][ 21 ]['roova_vip_tier'] = 'VIP Gold';
check( 'a pinned member gets the pinned tier\'s discount', roova_vip_discount_percent( 21 ), 10.0 );

$GLOBALS['roova_user_meta'][ 21 ]['roova_vip_tier'] = 'VIP Titanium';
check( 'a pin naming a tier that no longer exists is ignored', roova_vip_discount_percent( 21 ), 0.0 );

$GLOBALS['roova_current_user'] = 0;
check( 'a signed-out visitor has no tier and no discount', roova_vip_discount_percent(), 0.0 );

/* -------------------------------------------------------- free nights */

check( 'whole nights only', roova_vip_sanitize_nights( '2.7' ), 2 );
check( 'a negative night count is none', roova_vip_sanitize_nights( -1 ), 0 );
check( 'a typo cannot give away a free year', roova_vip_sanitize_nights( 5000 ), 365 );
check( 'rubbish is no free nights', roova_vip_sanitize_nights( 'two' ), 0 );

// A three-night stay in a $210 room.
$stay = new WC_Cart( array( roova_test_line( 81, 3 ) ) );

$one = roova_vip_free_nights_credit( $stay, 1, 630, 2 );
check( 'one free night is worth one night at the room rate', $one['amount'], 210.0 );
check( 'and says so', $one['nights'], 1 );

$two = roova_vip_free_nights_credit( $stay, 2, 630, 2 );
check( 'two free nights are worth two', $two['amount'], 420.0 );
check( 'and say so', $two['nights'], 2 );

$over = roova_vip_free_nights_credit( $stay, 5, 630, 2 );
check( 'never more nights than the stay has', $over['amount'], 630.0 );
check( 'and the count is trimmed with the amount, not after it', $over['nights'], 3 );

// Two rooms of the same type: the credit is one room for one night, not two.
$pair = new WC_Cart( array( roova_test_line( 81, 3, 2 ) ) );
$paircredit = roova_vip_free_nights_credit( $pair, 1, 1260, 2 );
check( 'a free night is one room-night however many rooms are booked', $paircredit['amount'], 210.0 );

// Several lines: the dearest room is the one credited.
$mixed = new WC_Cart( array( roova_test_line( 82, 3 ), roova_test_line( 81, 3 ) ) );
check( 'the dearest room is the one given away', roova_vip_free_nights_credit( $mixed, 1, 1110, 2 )['amount'], 210.0 );

// A ceiling — what the percentage has already taken, or a coupon — trims it.
$tight = roova_vip_free_nights_credit( $stay, 3, 250, 2 );
check( 'the credit never exceeds what is left to discount', $tight['amount'], 210.0 );
check( 'and the count follows it down', $tight['nights'], 1 );

check( 'no free nights, no credit', roova_vip_free_nights_credit( $stay, 0, 630, 2 )['amount'], 0.0 );
check( 'nothing to credit against an empty cart', roova_vip_free_nights_credit( new WC_Cart(), 2, 630, 2 )['amount'], 0.0 );
check( 'nor against a cart with no booking in it', roova_vip_free_nights_credit( new WC_Cart( array( array( 'line_total' => 40.0 ) ) ), 2, 630, 2 )['amount'], 0.0 );

check(
	'the label counts the nights out for the guest',
	roova_vip_free_nights_label( 'VIP Gold', 2 ),
	'VIP Gold: 2 free nights'
);
check(
	'and reads properly when there is only one',
	roova_vip_free_nights_label( 'VIP Gold', 1 ),
	'VIP Gold: 1 free night'
);

/* ------------------------------------------- both of them, on one cart */

/*
 * Both come off the same subtotal, because WooCommerce decides which fee line
 * prints first and a line that only adds up underneath the other one would read
 * as a mistake half the time. The pair is still held to the price of the stay.
 */
$GLOBALS['roova_options']['roova_vip_tiers'] = array(
	array( 'name' => 'Bronze', 'min' => 0 ),
	array( 'name' => 'VIP Gold', 'min' => 5, 'discount' => 10, 'free_nights' => 1 ),
);

$GLOBALS['roova_current_user']    = 31;
$GLOBALS['roova_completed'][ 31 ] = 9;

$cart = new WC_Cart( array( roova_test_line( 81, 3 ) ) );
roova_vip_apply_cart_discount( $cart );

check( 'both benefits reach the summary as their own lines', count( $cart->fees ), 2 );
check( 'the free nights are listed first', $cart->fees[0]['name'], 'VIP Gold: 1 free night' );
check( 'worth one night', $cart->fees[0]['amount'], -210.0 );
check( 'then the percentage', $cart->fees[1]['name'], 'VIP Gold discount (10%)' );
check( 'which is that percentage of the subtotal, whichever line prints first', $cart->fees[1]['amount'], -63.0 );

// A tier that gives everything cannot take more than the stay costs.
$GLOBALS['roova_options']['roova_vip_tiers'] = array(
	array( 'name' => 'VIP Diamond', 'min' => 0, 'discount' => 100, 'free_nights' => 5 ),
);

$whole = new WC_Cart( array( roova_test_line( 81, 3 ) ) );
roova_vip_apply_cart_discount( $whole );

$given = 0.0;
foreach ( $whole->fees as $fee ) {
	$given += $fee['amount'];
}
check( 'a tier that gives everything gives exactly the stay, never more', $given, -630.0 );

// The free nights are the promise that survives the cap; the percentage takes
// whatever the stay has left.
$fees = array();
foreach ( $whole->fees as $fee ) {
	$fees[ $fee['name'] ] = $fee['amount'];
}
check( 'the free nights are honoured in full', $fees['VIP Diamond: 3 free nights'], -630.0 );
check( 'and the percentage takes what is left, which is nothing', count( $whole->fees ), 1 );

// A signed-out visitor is charged the lot.
$GLOBALS['roova_current_user'] = 0;
$guest = new WC_Cart( array( roova_test_line( 81, 3 ) ) );
roova_vip_apply_cart_discount( $guest );
check( 'a guest gets no lines at all', $guest->fees, array() );

/* ------------------------------------------------------------- the label */

check(
	'the label carries the tier and the percentage',
	roova_vip_discount_label( 'VIP Gold', 10 ),
	'VIP Gold discount (10%)'
);

/* ---------------------------------------------------------- the benefits */

$written = array( array( 'icon' => 'clock', 'title' => 'Late checkout', 'note' => '' ) );

$with = roova_vip_tier_benefits( array( 'name' => 'VIP Gold', 'discount' => 10, 'benefits' => $written ) );
check( 'the discount is drawn as a benefit of its own', count( $with ), 2 );
check( 'and leads the list', $with[0]['title'], '10% off every booking' );
check( 'without displacing the written ones', $with[1]['title'], 'Late checkout' );

$without = roova_vip_tier_benefits( array( 'name' => 'Bronze', 'discount' => 0, 'benefits' => $written ) );
check( 'a tier with no discount lists only what was written', count( $without ), 1 );

check( 'a tier with neither has nothing to show', roova_vip_tier_benefits( array( 'name' => 'Bronze', 'discount' => 0, 'benefits' => array() ) ), array() );

$both = roova_vip_tier_benefits( array( 'name' => 'VIP Gold', 'discount' => 10, 'free_nights' => 2, 'benefits' => $written ) );
check( 'free nights are drawn as a benefit too', count( $both ), 3 );
check( 'and lead, as the larger promise', $both[0]['title'], '2 free nights on every stay' );
check( 'with the percentage behind them', $both[1]['title'], '10% off every booking' );

$nightsonly = roova_vip_tier_benefits( array( 'name' => 'VIP Gold', 'free_nights' => 1, 'benefits' => array() ) );
check( 'a tier giving only nights says so on its own', $nightsonly[0]['title'], '1 free night on every stay' );

/* ------------------------------------------------------------------ done */

printf( "\n%d checks, %d failures\n", $checks, $failures );

exit( $failures ? 1 : 0 );
