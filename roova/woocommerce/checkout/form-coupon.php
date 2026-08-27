<?php
/**
 * The coupon row in the order summary.
 *
 * Rendered inside the summary card rather than above the form — inc/checkout.php
 * unhooks WooCommerce's own placement. The classes are WooCommerce's on purpose:
 * `a.showcoupon` slides the form open and `form.checkout_coupon` submits over
 * AJAX, both handled by WooCommerce's checkout script. Its reply — "Coupon code
 * applied successfully." or the failure — is inserted just above the form, which
 * is why the notice styles below the toggle are part of this block.
 *
 * @package Roova
 * @see     https://woocommerce.com/document/template-structure/
 * @var WC_Checkout $checkout
 */

defined( 'ABSPATH' ) || exit;

if ( ! wc_coupons_enabled() ) {
	return;
}
?>
<div class="roova-coupon">
	<a href="#" class="showcoupon roova-coupon__toggle">
		<span><?php esc_html_e( 'Add coupons', 'roova' ); ?></span>
		<?php roova_the_icon( 'chevron-down', 17 ); ?>
	</a>

	<form class="checkout_coupon woocommerce-form-coupon roova-coupon__form" method="post">
		<label class="screen-reader-text" for="coupon_code"><?php esc_html_e( 'Coupon code', 'roova' ); ?></label>
		<input type="text" name="coupon_code" class="input-text roova-coupon__input" id="coupon_code" value="" placeholder="<?php esc_attr_e( 'Coupon code', 'roova' ); ?>" />

		<button type="submit" class="button roova-coupon__apply" name="apply_coupon" value="<?php esc_attr_e( 'Apply', 'roova' ); ?>">
			<?php esc_html_e( 'Apply', 'roova' ); ?>
		</button>
	</form>
</div>
