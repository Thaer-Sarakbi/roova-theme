<?php
/**
 * "Review us": the email a guest gets after their stay, and the link in it.
 *
 * The email itself is a WooCommerce email (inc/emails/class-roova-email-review-us.php),
 * so the client edits it under WooCommerce → Settings → Emails → Review us. This
 * file decides who gets it and when:
 *
 * - A daily job, run each morning, finds paid bookings whose check-out date was
 *   the configured number of days ago (default 1) and emails each guest once.
 * - It reads the bookings table for the dates, because an order's own status
 *   says nothing about when the stay ended: a booking is paid weeks before the
 *   guest arrives, and nothing here ever marks an order "Completed". That is
 *   also why WooCommerce's own review-request email (10.8+) does not fit: it
 *   waits for "Completed" and asks about the products bought, which are rooms,
 *   while reviews here belong to the hotel.
 * - It sends only when roova_can_review() says the guest could actually write
 *   one: a completed stay, an account, no review of that hotel yet, reviews
 *   open. A link to a form that then refuses them is worse than no email.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/** The daily job's hook. */
const ROOVA_REVIEW_US_HOOK = 'roova_review_us_daily';

/** Order meta: the hotels this order has already been asked about. */
const ROOVA_REVIEW_US_META = '_roova_review_us_sent';

/**
 * Register the email with WooCommerce.
 *
 * @param WC_Email[] $emails Emails.
 * @return WC_Email[]
 */
function roova_review_us_email_class( $emails ) {
	require_once ROOVA_DIR . 'inc/emails/class-roova-email-review-us.php';

	if ( class_exists( 'Roova_Email_Review_Us' ) ) {
		$emails['Roova_Email_Review_Us'] = new Roova_Email_Review_Us();
	}

	return $emails;
}
add_filter( 'woocommerce_email_classes', 'roova_review_us_email_class' );

/**
 * The email object, or null when WooCommerce's mailer is not there.
 *
 * @return Roova_Email_Review_Us|null
 */
function roova_review_us_email() {
	if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
		return null;
	}

	$emails = WC()->mailer()->get_emails();

	return isset( $emails['Roova_Email_Review_Us'] ) ? $emails['Roova_Email_Review_Us'] : null;
}

/**
 * The link in the email: the hotel page, at its review box.
 *
 * `roova_review=1` makes the review section draw even when the hotel has no
 * reviews and the visitor is signed out, which is exactly the guest opening
 * this email on a phone. Without it the section is left off such a page, and
 * #reviews would scroll to nothing.
 *
 * @param int $hotel_id Hotel product ID.
 * @return string
 */
function roova_review_us_url( $hotel_id ) {
	return add_query_arg( 'roova_review', '1', get_permalink( absint( $hotel_id ) ) ) . '#reviews';
}

/**
 * Did this visitor arrive from the "Review us" link?
 *
 * @return bool
 */
function roova_review_us_landing() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a display flag on a GET link.
	return ! empty( $_GET['roova_review'] );
}

/* -------------------------------------------------------------------------
 * The daily job
 * ---------------------------------------------------------------------- */

/**
 * Keep the daily job scheduled, for 9:00 in the morning, site time.
 *
 * The morning, because this is an email a guest should read with a moment to
 * spare. On `init`, the way Roova_Holds keeps its cleanup scheduled, so a theme
 * updated in place picks it up without being reactivated.
 */
function roova_review_us_schedule() {
	if ( wp_next_scheduled( ROOVA_REVIEW_US_HOOK ) ) {
		return;
	}

	$next = new DateTimeImmutable( 'today 09:00', wp_timezone() );
	if ( $next->getTimestamp() <= time() ) {
		$next = $next->modify( '+1 day' );
	}

	wp_schedule_event( $next->getTimestamp(), 'daily', ROOVA_REVIEW_US_HOOK );
}
add_action( 'init', 'roova_review_us_schedule' );

/**
 * Stop the job when the theme is switched off.
 */
function roova_review_us_unschedule() {
	wp_clear_scheduled_hook( ROOVA_REVIEW_US_HOOK );
}
add_action( 'switch_theme', 'roova_review_us_unschedule' );

/**
 * Paid stays that checked out between two dates, one row per order and hotel.
 *
 * @param string $earliest First check-out date, Y-m-d.
 * @param string $latest   Last check-out date, Y-m-d.
 * @return array[] order_id, hotel_id, check_in, check_out.
 */
function roova_review_us_candidates( $earliest, $latest ) {
	global $wpdb;

	if ( ! class_exists( 'Roova_Schema' ) ) {
		return array();
	}

	$table = Roova_Schema::table();

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- the theme's own table.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT order_id, hotel_id, MIN(check_in) AS check_in, MAX(check_out) AS check_out
			FROM {$table}
			WHERE status = 'confirmed' AND order_id > 0 AND hotel_id > 0
				AND check_out BETWEEN %s AND %s
			GROUP BY order_id, hotel_id
			ORDER BY check_out ASC",
			$earliest,
			$latest
		),
		ARRAY_A
	);
	// phpcs:enable

	return is_array( $rows ) ? $rows : array();
}

/**
 * Send the email for one stay, if the guest should get it.
 *
 * @param Roova_Email_Review_Us $email Email.
 * @param array                 $row   A roova_review_us_candidates() row.
 * @return string sent | already-sent | no-order | no-account | not-eligible | failed.
 */
function roova_review_us_maybe_send( $email, $row ) {
	$order    = wc_get_order( absint( $row['order_id'] ) );
	$hotel_id = absint( $row['hotel_id'] );

	if ( ! $order instanceof WC_Order ) {
		return 'no-order';
	}

	$sent_for = (array) $order->get_meta( ROOVA_REVIEW_US_META );
	if ( in_array( $hotel_id, array_map( 'absint', $sent_for ), true ) ) {
		return 'already-sent';
	}

	/*
	 * A review needs an account: roova_can_review() checks the stay against the
	 * member's own bookings. A guest checkout has nobody to check it against.
	 */
	$user_id = (int) $order->get_customer_id();
	if ( ! $user_id ) {
		return 'no-account';
	}

	if ( ! function_exists( 'roova_can_review' ) || ! roova_can_review( $hotel_id, $user_id ) ) {
		return 'not-eligible';
	}

	if ( ! $email->trigger( $order->get_id(), $hotel_id, $row['check_in'], $row['check_out'] ) ) {
		return 'failed';
	}

	$sent_for[] = $hotel_id;
	$order->update_meta_data( ROOVA_REVIEW_US_META, array_values( array_unique( array_map( 'absint', $sent_for ) ) ) );
	$order->save();

	$order->add_order_note(
		sprintf(
			/* translators: %s: hotel name */
			__( '"Review us" email sent to the guest for %s.', 'roova' ),
			get_the_title( $hotel_id )
		)
	);

	return 'sent';
}

/**
 * The daily run.
 *
 * Looks back a week past the delay, not just at one day, so a morning the job
 * did not run (WP-Cron needs a visitor to fire) is caught up the next day. The
 * window stops there on purpose: switching the email on must not send it to
 * every guest the site has ever had. Each order carries a record of what it was
 * sent, so the overlap between one day's window and the next sends nothing twice.
 *
 * @return string[] What happened to each stay, keyed "order-hotel". For tests.
 */
function roova_review_us_run() {
	$email = roova_review_us_email();
	if ( ! $email || ! $email->is_enabled() ) {
		return array();
	}

	$latest   = roova_add_days( roova_today(), -$email->get_delay_days() );
	$earliest = roova_add_days( $latest, -(int) apply_filters( 'roova_review_us_catch_up_days', 7 ) );
	$results  = array();

	foreach ( roova_review_us_candidates( $earliest, $latest ) as $row ) {
		$results[ $row['order_id'] . '-' . $row['hotel_id'] ] = roova_review_us_maybe_send( $email, $row );
	}

	return $results;
}
add_action( ROOVA_REVIEW_US_HOOK, 'roova_review_us_run' );
