<?php
/**
 * The rooms in the order summary.
 *
 * Only the line items: the totals are rendered by roova_checkout_totals(), below
 * the coupon row, and registered as their own refresh fragment. WooCommerce
 * replaces this element wholesale after every checkout update, so it has to
 * stay a single root element carrying `woocommerce-checkout-review-order-table`.
 *
 * @package Roova
 * @see     https://woocommerce.com/document/template-structure/
 * @var WC_Checkout $checkout
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="woocommerce-checkout-review-order-table roova-summary__items">
	<?php
	do_action( 'woocommerce_review_order_before_cart_contents' );

	foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
		$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

		if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 || ! apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
			continue;
		}

		roova_checkout_summary_item( $cart_item_key, $cart_item );
	}

	do_action( 'woocommerce_review_order_after_cart_contents' );
	?>
</div>
