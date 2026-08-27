<?php
/**
 * Checkout form.
 *
 * Two columns: the guest's details, notes and payment on the left, the order
 * summary on the right. Payment is rendered here rather than through
 * `woocommerce_checkout_order_review` — see inc/checkout.php, which unhooks it
 * from the sidebar so it can sit under "Payment options" instead.
 *
 * The summary is a *sibling* of the checkout form, not a child of it. It holds
 * the coupon form, and a browser silently drops a `<form>` nested inside another
 * one — the element would exist in the HTML and never in the DOM. Nothing in the
 * summary is posted with the order, so nothing is lost by keeping it outside.
 *
 * @package Roova
 * @see     https://woocommerce.com/document/template-structure/
 * @var WC_Checkout $checkout
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_checkout_form', $checkout );

// Nothing can be booked by a guest on a store that requires an account.
if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	return;
}
?>

<div class="roova-checkout__grid">

	<form name="checkout" method="post" class="checkout woocommerce-checkout roova-checkout__form roova-checkout__column" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" novalidate>
		<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

		<?php // Guest information — see checkout/form-billing.php. ?>
		<?php do_action( 'woocommerce_checkout_billing' ); ?>

		<?php // Order notes — see checkout/form-shipping.php. ?>
		<?php do_action( 'woocommerce_checkout_shipping' ); ?>

		<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

		<section class="roova-checkout__section roova-checkout__section--payment">
			<h2 class="roova-checkout__heading"><?php esc_html_e( 'Payment options', 'roova' ); ?></h2>
			<?php woocommerce_checkout_payment(); ?>
		</section>
	</form>

	<aside class="roova-summary" aria-label="<?php esc_attr_e( 'Order summary', 'roova' ); ?>">
		<div class="roova-summary__card">
			<div class="roova-summary__top">
				<h2 class="roova-checkout__heading"><?php esc_html_e( 'Order summary', 'roova' ); ?></h2>

				<div id="order_review" class="woocommerce-checkout-review-order">
					<?php do_action( 'woocommerce_checkout_order_review' ); ?>
				</div>

				<?php
				/*
				 * Filled in by checkout.js after a room is removed. It sits outside
				 * #order_review because that whole element is replaced on every
				 * checkout update, which is exactly when this needs to survive.
				 */
				?>
				<p class="roova-summary__undo" data-roova-undo hidden></p>
			</div>

			<?php
			/*
			 * The coupon row sits between the rooms and the totals, and stays out
			 * of every refresh fragment: WooCommerce binds its submit handler to
			 * this form once, and a replaced element would lose it.
			 */
			woocommerce_checkout_coupon_form();

			roova_checkout_totals();
			roova_checkout_rate_hold();
			?>
		</div>
	</aside>

</div>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
