<?php
/**
 * Exercises roova_account_stay_status() outside WordPress: which of the four
 * states a booking is in. Every surface reads it — the Bookings tab, the order
 * page, the confirmation, who may review, VIP and cashback — so a wrong answer
 * here is wrong everywhere at once.
 *
 * Same shape as the other test scripts: a flat script with a `check()` helper,
 * WordPress stubbed to what inc/account.php touches at include time, and an
 * order stubbed as the two methods the function asks it.
 */

define( 'ABSPATH', __DIR__ );

function add_action() {}

function add_filter() {}

function apply_filters( $tag, $value ) {
	return $value;
}

function __( $text ) {
	return $text;
}

/**
 * An order, as the two things roova_account_stay_status() asks it.
 */
class Roova_Test_Order {
	/** @var string */
	public $status;

	/** @var bool */
	public $needs;

	public function __construct( $status, $needs = false ) {
		$this->status = $status;
		$this->needs  = $needs;
	}

	public function has_status( $status ) {
		return in_array( $this->status, (array) $status, true );
	}

	public function needs_payment() {
		return $this->needs;
	}
}

require __DIR__ . '/../roova/inc/account.php';

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

$today     = '2026-09-19';
$past      = '2026-09-17';
$future    = '2026-09-25';
$processing = new Roova_Test_Order( 'processing' );
$completed  = new Roova_Test_Order( 'completed' );

echo "\nPaid, left to the calendar\n";
check( 'processing, check-out in the future: upcoming', roova_account_stay_status( $processing, $future, $today ), 'upcoming' );
check( 'processing, check-out today: completed', roova_account_stay_status( $processing, $today, $today ), 'completed' );
check( 'processing, check-out passed: completed', roova_account_stay_status( $processing, $past, $today ), 'completed' );
check( 'on-hold that is paid, check-out passed: completed', roova_account_stay_status( new Roova_Test_Order( 'on-hold' ), $past, $today ), 'completed' );

echo "\nMarked Completed in the dashboard\n";
check( 'completed before the check-out date: completed', roova_account_stay_status( $completed, $future, $today ), 'completed' );
check( 'completed after it: still completed', roova_account_stay_status( $completed, $past, $today ), 'completed' );
check( 'completed with no dates on the line: completed', roova_account_stay_status( $completed, '', $today ), 'completed' );

echo "\nThe states that outrank the calendar and the dashboard\n";
check( 'cancelled beats a check-out in the past', roova_account_stay_status( new Roova_Test_Order( 'cancelled' ), $past, $today ), 'cancelled' );
check( 'refunded reads as cancelled', roova_account_stay_status( new Roova_Test_Order( 'refunded' ), $future, $today ), 'cancelled' );
check( 'failed reads as cancelled', roova_account_stay_status( new Roova_Test_Order( 'failed' ), $past, $today ), 'cancelled' );
check( 'unpaid is payment due, even past check-out', roova_account_stay_status( new Roova_Test_Order( 'pending', true ), $past, $today ), 'payment' );
check( 'on-hold awaiting a bank transfer is payment due', roova_account_stay_status( new Roova_Test_Order( 'on-hold', true ), $future, $today ), 'payment' );

printf( "\n%d checks, %d failures\n", $checks, $failures );
exit( $failures > 0 ? 1 : 0 );
