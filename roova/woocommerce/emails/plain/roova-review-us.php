<?php
/**
 * "Review us" email (plain text). See emails/roova-review-us.php.
 *
 * @package Roova
 *
 * @var WC_Order|false $order              Order.
 * @var string         $email_heading      Heading.
 * @var string         $additional_content Closing text from the settings.
 * @var string         $hotel_name         Hotel name.
 * @var string         $review_url         Hotel page link, at the review form.
 * @var string         $check_in           Check-in date, Y-m-d.
 * @var string         $check_out          Check-out date, Y-m-d.
 * @var WC_Email       $email              Email object.
 */

defined( 'ABSPATH' ) || exit;

$roova_first_name = $order ? $order->get_billing_first_name() : '';

echo "=\n= " . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n=\n\n";

if ( $roova_first_name ) {
	/* translators: %s: customer first name */
	echo esc_html( sprintf( __( 'Hi %s,', 'roova' ), $roova_first_name ) ) . "\n\n";
} else {
	echo esc_html__( 'Hi,', 'roova' ) . "\n\n";
}

/* translators: %s: hotel name */
echo esc_html( sprintf( __( 'Thank you for staying at %s. We hope you had a wonderful time.', 'roova' ), $hotel_name ) ) . "\n\n";

echo esc_html__( 'Could you spare a minute to tell other guests how it went? Score the cleanliness, the location and the service, and add a few words if you like.', 'roova' ) . "\n\n";

if ( $check_in && $check_out && function_exists( 'roova_format_date' ) ) {
	/* translators: %s: stay dates */
	echo esc_html( sprintf( __( 'Your stay: %s', 'roova' ), roova_format_date( $check_in ) . ' — ' . roova_format_date( $check_out ) ) ) . "\n";
}
if ( $order ) {
	/* translators: %s: order number */
	echo esc_html( sprintf( __( 'Booking: #%s', 'roova' ), $order->get_order_number() ) ) . "\n";
}

/* translators: %s: hotel name */
echo "\n" . esc_html( sprintf( __( 'Review %s', 'roova' ), $hotel_name ) ) . ":\n" . esc_url_raw( $review_url ) . "\n\n";

echo "----------------------------------------\n\n";

if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n----------------------------------------\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
