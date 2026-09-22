<?php
/**
 * "Review us" email (HTML).
 *
 * Sent by Roova_Email_Review_Us after a guest's check-out date. The button opens
 * the hotel page scrolled to its review form (roova_review_us_url()).
 *
 * The button is drawn here rather than through WooCommerce's
 * emails/email-button.php, which only exists from WooCommerce 10.8. It uses the
 * same colour: the store's email base colour, with black or white text on it.
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

$roova_button_bg   = get_option( 'woocommerce_email_base_color', '#7f54b3' );
$roova_button_bg   = $roova_button_bg ? $roova_button_bg : '#7f54b3';
$roova_button_text = function_exists( 'wc_hex_is_light' ) && wc_hex_is_light( $roova_button_bg ) ? '#000000' : '#ffffff';
$roova_first_name  = $order ? $order->get_billing_first_name() : '';
$roova_stay        = ( $check_in && $check_out && function_exists( 'roova_format_date' ) )
	? roova_format_date( $check_in ) . ' — ' . roova_format_date( $check_out )
	: '';

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p>
	<?php
	if ( $roova_first_name ) {
		/* translators: %s: customer first name */
		printf( esc_html__( 'Hi %s,', 'roova' ), esc_html( $roova_first_name ) );
	} else {
		esc_html_e( 'Hi,', 'roova' );
	}
	?>
</p>

<p>
	<?php
	/* translators: %s: hotel name */
	printf( esc_html__( 'Thank you for staying at %s. We hope you had a wonderful time.', 'roova' ), '<strong>' . esc_html( $hotel_name ) . '</strong>' );
	?>
</p>

<p><?php esc_html_e( 'Could you spare a minute to tell other guests how it went? Score the cleanliness, the location and the service, and add a few words if you like.', 'roova' ); ?></p>

<?php if ( $roova_stay || $order ) : ?>
	<p style="margin: 0 0 16px;">
		<?php if ( $roova_stay ) : ?>
			<?php
			/* translators: %s: stay dates */
			printf( esc_html__( 'Your stay: %s', 'roova' ), esc_html( $roova_stay ) );
			?>
			<br />
		<?php endif; ?>
		<?php if ( $order ) : ?>
			<?php
			/* translators: %s: order number */
			printf( esc_html__( 'Booking: #%s', 'roova' ), esc_html( $order->get_order_number() ) );
			?>
		<?php endif; ?>
	</p>
<?php endif; ?>

<p style="margin: 24px 0;">
	<a href="<?php echo esc_url( $review_url ); ?>" style="display:inline-block;padding:16px 32px;background-color:<?php echo esc_attr( $roova_button_bg ); ?>;color:<?php echo esc_attr( $roova_button_text ); ?>;border-radius:4px;font-weight:bold;font-size:15px;text-decoration:none;">
		<?php
		/* translators: %s: hotel name */
		printf( esc_html__( 'Review %s', 'roova' ), esc_html( $hotel_name ) );
		?>
	</a>
</p>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
