<?php
/**
 * Payment options, the terms checkbox and the Place order button.
 *
 * The cards come from whatever gateways are enabled in WooCommerce → Settings →
 * Payments: their titles, descriptions, icons and order are all the store's own.
 * Nothing here is hard-coded to a particular payment method.
 *
 * The whole block is replaced by WooCommerce after every checkout update, which
 * is what keeps the total in the button honest.
 *
 * @package Roova
 * @see     https://woocommerce.com/document/template-structure/
 * @var WC_Checkout $checkout
 * @var array       $available_gateways
 * @var string      $order_button_text
 */

defined( 'ABSPATH' ) || exit;

$roova_label = roova_place_order_label( $order_button_text );

$roova_button = sprintf(
	'<button type="submit" class="button alt roova-place__button %1$s" name="woocommerce_checkout_place_order" id="place_order" value="%2$s" data-value="%2$s">%2$s</button>',
	esc_attr( function_exists( 'wc_wp_theme_get_element_class_name' ) ? (string) wc_wp_theme_get_element_class_name( 'button' ) : '' ),
	esc_attr( $roova_label )
);
?>
<div id="payment" class="woocommerce-checkout-payment roova-payment">
	<?php if ( WC()->cart->needs_payment() ) : ?>
		<ul class="wc_payment_methods payment_methods methods roova-pay">
			<?php
			if ( ! empty( $available_gateways ) ) {
				foreach ( $available_gateways as $gateway ) {
					wc_get_template( 'checkout/payment-method.php', array( 'gateway' => $gateway ) );
				}
			} else {
				echo '<li class="woocommerce-notice woocommerce-notice--info woocommerce-info roova-pay__none">' . wp_kses_post( apply_filters( 'woocommerce_no_available_payment_methods_message', WC()->customer->get_billing_country() ? esc_html__( 'Sorry, it seems that there are no available payment methods. Please contact us if you require assistance or wish to make alternate arrangements.', 'woocommerce' ) : esc_html__( 'Please fill in your details above to see available payment methods.', 'woocommerce' ) ) ) . '</li>';
			}
			?>
		</ul>
	<?php endif; ?>

	<div class="form-row place-order roova-place">
		<noscript>
			<?php esc_html_e( 'Since your browser does not support JavaScript, or it is disabled, please ensure you click the Update Totals button before placing your order.', 'woocommerce' ); ?>
			<br /><button type="submit" class="button alt" name="woocommerce_checkout_update_totals" value="<?php esc_attr_e( 'Update totals', 'woocommerce' ); ?>"><?php esc_html_e( 'Update totals', 'woocommerce' ); ?></button>
		</noscript>

		<?php wc_get_template( 'checkout/terms.php' ); ?>

		<?php do_action( 'woocommerce_review_order_before_submit' ); ?>

		<?php echo apply_filters( 'woocommerce_order_button_html', $roova_button ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>

		<?php do_action( 'woocommerce_review_order_after_submit' ); ?>

		<p class="roova-place__note">
			<?php echo esc_html( roova_option( 'checkout_reassurance', __( 'Free cancellation until 24 hours before check-in.', 'roova' ) ) ); ?>
		</p>

		<?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
	</div>
</div>
