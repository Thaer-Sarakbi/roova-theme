<?php
/**
 * WooCommerce front-end wiring: template routing and catalogue tweaks.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * Route hotel products to the theme's hotel template.
 *
 * @param string $template Template path.
 * @return string
 */
function roova_template_include( $template ) {
	if ( ! is_singular( 'product' ) ) {
		return $template;
	}

	$product = wc_get_product( get_queried_object_id() );
	if ( ! $product ) {
		return $template;
	}

	if ( 'hotel' === $product->get_type() ) {
		$hotel_template = locate_template( array( 'single-hotel.php' ) );
		if ( $hotel_template ) {
			return $hotel_template;
		}
	}

	return $template;
}
add_filter( 'template_include', 'roova_template_include', 99 );

/**
 * A room on its own is not bookable without a hotel context, so send visitors
 * to the hotel page and scroll them to that room.
 */
function roova_redirect_room_to_hotel() {
	if ( ! is_singular( 'product' ) ) {
		return;
	}

	$product = wc_get_product( get_queried_object_id() );
	if ( ! $product || 'room' !== $product->get_type() ) {
		return;
	}

	if ( ! apply_filters( 'roova_redirect_rooms_to_hotel', true ) ) {
		return;
	}

	$hotel_id = roova_get_room_hotel_id( $product->get_id() );
	if ( ! $hotel_id ) {
		return;
	}

	wp_safe_redirect( get_permalink( $hotel_id ) . '#room-' . $product->get_id(), 302 );
	exit;
}
add_action( 'template_redirect', 'roova_redirect_room_to_hotel' );

/**
 * Use the theme's own card markup in product loops.
 */
function roova_setup_loop_templates() {
	remove_action( 'woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open', 10 );
	remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash', 10 );
	remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
	remove_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title', 10 );
	remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );
	remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
	remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close', 5 );
	remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );

	add_action( 'woocommerce_before_shop_loop_item', 'roova_loop_card', 10 );
}
add_action( 'wp', 'roova_setup_loop_templates' );

/**
 * Render a hotel card inside the WooCommerce loop.
 */
function roova_loop_card() {
	global $product;

	if ( ! $product ) {
		return;
	}

	if ( 'hotel' === $product->get_type() ) {
		roova_hotel_card( $product->get_id() );
		return;
	}

	// Anything else falls back to a simple card.
	roova_simple_product_card( $product );
}

/**
 * Wrap WooCommerce pages in the theme container.
 */
function roova_wc_wrapper_start() {
	echo '<div class="roova-wc-page"><div class="wrap">';
}

/**
 * Close the WooCommerce wrapper.
 */
function roova_wc_wrapper_end() {
	echo '</div></div>';
}
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
add_action( 'woocommerce_before_main_content', 'roova_wc_wrapper_start', 10 );
add_action( 'woocommerce_after_main_content', 'roova_wc_wrapper_end', 10 );

/**
 * Four hotels per row in the catalogue.
 *
 * @return int
 */
function roova_loop_columns() {
	return 3;
}
add_filter( 'loop_shop_columns', 'roova_loop_columns' );

/**
 * Keep the sidebar off WooCommerce pages — the theme lays them out full width.
 */
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

/**
 * Add the stay to the "review order" line items on the checkout page.
 *
 * @param string $quantity Quantity markup.
 * @param array  $item     Cart item.
 * @return string
 */
function roova_checkout_item_quantity( $quantity, $item ) {
	if ( empty( $item['roova_booking'] ) ) {
		return $quantity;
	}

	$nights = (int) $item['roova_booking']['nights'];

	return $quantity . '<span class="roova-nights-note">' . esc_html(
		sprintf(
			/* translators: %d: number of nights */
			_n( '%d night', '%d nights', $nights, 'roova' ),
			$nights
		)
	) . '</span>';
}
add_filter( 'woocommerce_checkout_cart_item_quantity', 'roova_checkout_item_quantity', 10, 2 );
