<?php
/**
 * Order notes.
 *
 * The shipping half of this template is gone: rooms are nights in a building,
 * so `roova_cart_needs_shipping()` tells WooCommerce a booking never ships and
 * there is no address to collect. What is left is the optional note, which
 * WooCommerce keeps in the same template.
 *
 * @package Roova
 * @see     https://woocommerce.com/document/template-structure/
 * @var WC_Checkout $checkout
 */

defined( 'ABSPATH' ) || exit;

$roova_notes_enabled = apply_filters( 'woocommerce_enable_order_notes_field', 'yes' === get_option( 'woocommerce_enable_order_comments', 'yes' ) );
?>
<div class="woocommerce-shipping-fields">
	<?php if ( true === WC()->cart->needs_shipping_address() ) : ?>
		<?php
		/*
		 * Only reachable when something that does ship is in the cart alongside
		 * the rooms. Fall back to WooCommerce's own address form rather than
		 * quietly dropping fields the order needs.
		 */
		?>
		<section class="roova-checkout__section roova-checkout__section--shipping">
			<h2 class="roova-checkout__heading"><?php esc_html_e( 'Delivery address', 'roova' ); ?></h2>

			<?php do_action( 'woocommerce_before_checkout_shipping_form', $checkout ); ?>

			<div class="roova-fields">
				<?php
				foreach ( $checkout->get_checkout_fields( 'shipping' ) as $roova_key => $roova_field ) {
					woocommerce_form_field( $roova_key, $roova_field, $checkout->get_value( $roova_key ) );
				}
				?>
			</div>

			<?php do_action( 'woocommerce_after_checkout_shipping_form', $checkout ); ?>
		</section>
	<?php endif; ?>
</div>

<div class="woocommerce-additional-fields">
	<?php do_action( 'woocommerce_before_order_notes', $checkout ); ?>

	<?php if ( $roova_notes_enabled && $checkout->get_checkout_fields( 'order' ) ) : ?>
		<section class="roova-checkout__section">
			<h2 class="roova-checkout__heading">
				<?php esc_html_e( 'Order notes', 'roova' ); ?>
				<span class="roova-checkout__optional"><?php esc_html_e( '(optional)', 'roova' ); ?></span>
			</h2>

			<div class="roova-fields woocommerce-additional-fields__field-wrapper">
				<?php
				foreach ( $checkout->get_checkout_fields( 'order' ) as $roova_key => $roova_field ) {
					roova_checkout_field( $roova_key, $roova_field, $checkout->get_value( $roova_key ) );
				}
				?>
			</div>
		</section>
	<?php endif; ?>

	<?php do_action( 'woocommerce_after_order_notes', $checkout ); ?>
</div>
