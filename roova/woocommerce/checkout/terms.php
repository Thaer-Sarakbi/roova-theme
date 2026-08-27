<?php
/**
 * The booking terms checkbox.
 *
 * Shown whether or not a terms page is set — a booking is a commitment on both
 * sides. WooCommerce only validates its own checkbox when a terms page exists,
 * so roova_validate_checkout_terms() covers the other case.
 *
 * @package Roova
 * @see     https://woocommerce.com/document/template-structure/
 */

defined( 'ABSPATH' ) || exit;

if ( ! apply_filters( 'woocommerce_checkout_show_terms', true ) ) {
	return;
}

// The '' fallback matters: without it WooCommerce hands back the homepage when
// no terms page is set, and the link would go somewhere meaningless.
$roova_terms_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'terms', '' ) : '';
$roova_terms_url = $roova_terms_url ? $roova_terms_url : roova_option( 'terms_url', '' );

$roova_terms_link = $roova_terms_url
	? '<a href="' . esc_url( $roova_terms_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'booking terms', 'roova' ) . '</a>'
	: '<strong>' . esc_html__( 'booking terms', 'roova' ) . '</strong>';
?>
<div class="roova-terms" data-roova-field="terms">
	<?php do_action( 'woocommerce_checkout_before_terms_and_conditions' ); ?>

	<p class="form-row validate-required roova-checkbox">
		<label class="roova-checkbox__row" for="terms">
			<input type="checkbox" class="roova-checkbox__box input-checkbox" name="terms" id="terms" <?php checked( apply_filters( 'woocommerce_terms_is_checked_default', isset( $_POST['terms'] ) ), true ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- redisplaying what was posted. ?> />
			<input type="hidden" name="terms-field" value="1" />

			<span class="roova-checkbox__copy">
				<?php
				printf(
					/* translators: %s: link to the booking terms */
					wp_kses_post( __( 'I have read and agree to the %s, cancellation policy and privacy notice.', 'roova' ) ),
					$roova_terms_link // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built and escaped above.
				);
				?>
			</span>
		</label>
	</p>

	<span class="roova-field__error" data-roova-error="terms" role="alert"></span>

	<?php do_action( 'woocommerce_checkout_after_terms_and_conditions' ); ?>
</div>
