<?php
/**
 * Checkout, order-pay and order-received.
 *
 * Its own document rather than header.php + footer.php: the design puts a
 * stripped-back header on this page with no menu and no cart link, so there is
 * nothing to click away from a booking that is one button from being made.
 *
 * The page content is ignored on purpose — see roova_checkout_template(). The
 * shortcode below routes to the right WooCommerce view, and the templates in
 * woocommerce/checkout/ do the rest.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

$roova_received = is_order_received_page();
$roova_pay      = is_checkout_pay_page();
$roova_empty    = ! $roova_received && ! $roova_pay && ( ! WC()->cart || WC()->cart->is_empty() );

if ( $roova_received ) {
	$roova_title = __( 'Booking confirmed', 'roova' );
	$roova_sub   = __( 'Your rooms are held in your name — the details are below and on their way to your inbox.', 'roova' );
} elseif ( $roova_pay ) {
	$roova_title = __( 'Complete payment', 'roova' );
	$roova_sub   = __( 'Finish paying to confirm the rooms we are holding for you.', 'roova' );
} else {
	$roova_title = __( 'Checkout', 'roova' );
	$roova_sub   = roova_checkout_banner_sub();
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>

<body <?php body_class( 'roova-page-white roova-checkout-page' ); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#roova-content"><?php esc_html_e( 'Skip to content', 'roova' ); ?></a>

<?php roova_checkout_header(); ?>

<main id="roova-content" class="roova-checkout">
	<?php roova_checkout_banner( $roova_title, $roova_sub ); ?>

	<div class="roova-checkout__container">
		<?php
		if ( $roova_empty ) {
			wc_print_notices();
			roova_checkout_empty();
		} else {
			echo do_shortcode( '[woocommerce_checkout]' );
		}
		?>
	</div>
</main>

<footer class="roova-checkout__foot">
	<p>
		<?php
		printf(
			/* translators: 1: year, 2: site name */
			esc_html__( '© %1$s %2$s', 'roova' ),
			esc_html( gmdate( 'Y' ) ),
			esc_html( get_bloginfo( 'name' ) )
		);
		?>
	</p>

	<?php
	if ( has_nav_menu( 'footer' ) ) {
		wp_nav_menu(
			array(
				'theme_location' => 'footer',
				'container'      => false,
				'menu_class'     => 'roova-checkout__foot-menu',
				'depth'          => 1,
				'fallback_cb'    => false,
			)
		);
	}
	?>
</footer>

<?php wp_footer(); ?>
</body>
</html>
