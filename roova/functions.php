<?php
/**
 * Roova theme bootstrap.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

define( 'ROOVA_VERSION', '1.7.0' );
define( 'ROOVA_DIR', trailingslashit( get_template_directory() ) );
define( 'ROOVA_URI', trailingslashit( get_template_directory_uri() ) );

/**
 * Is WooCommerce active?
 *
 * @return bool
 */
function roova_has_woocommerce() {
	return class_exists( 'WooCommerce' );
}

/**
 * Load a theme file relative to the theme root.
 *
 * @param string $path Relative path.
 */
function roova_require( $path ) {
	$file = ROOVA_DIR . ltrim( $path, '/' );
	if ( is_readable( $file ) ) {
		require_once $file;
	}
}

// Always loaded — these do not depend on WooCommerce.
roova_require( 'inc/setup.php' );
roova_require( 'inc/assets.php' );
roova_require( 'inc/helpers.php' );
roova_require( 'inc/icons.php' );
roova_require( 'inc/customizer.php' );
roova_require( 'inc/template-tags.php' );
roova_require( 'inc/auth.php' );
roova_require( 'inc/verification.php' );

if ( roova_has_woocommerce() ) {
	roova_require( 'inc/attributes.php' );
	roova_require( 'inc/products/class-wc-product-hotel.php' );
	roova_require( 'inc/products/class-wc-product-room.php' );
	roova_require( 'inc/products/register.php' );

	roova_require( 'inc/booking/class-roova-schema.php' );
	roova_require( 'inc/booking/class-roova-availability.php' );
	roova_require( 'inc/booking/class-roova-holds.php' );
	roova_require( 'inc/booking/class-roova-cart.php' );
	roova_require( 'inc/booking/class-roova-orders.php' );
	roova_require( 'inc/booking/class-roova-blocks.php' );

	roova_require( 'inc/search.php' );
	roova_require( 'inc/ajax.php' );
	roova_require( 'inc/woocommerce.php' );
	roova_require( 'inc/checkout.php' );

	// My account: the saved stays and the VIP tiers stand on their own, the
	// account page reads all five features, and reviews and cashback both need
	// its stay list — which is why inc/account.php loads after them and they
	// only ever reach it through a function call, never at include time.
	roova_require( 'inc/likes.php' );
	roova_require( 'inc/vip.php' );
	roova_require( 'inc/cashback.php' );
	roova_require( 'inc/account.php' );
	roova_require( 'inc/reviews.php' );
	roova_require( 'inc/account-tabs.php' );

	// The single-order page reads the stay status from inc/account.php and the
	// "may I review this?" rule from inc/reviews.php, so it loads after both.
	roova_require( 'inc/order.php' );

	if ( is_admin() ) {
		roova_require( 'inc/admin/metabox-hotel-details.php' );
		roova_require( 'inc/admin/metabox-room-details.php' );
		roova_require( 'inc/admin/amenity-icons.php' );
		roova_require( 'inc/admin/bookings-page.php' );
		roova_require( 'inc/admin/order-metabox.php' );
		roova_require( 'inc/admin/vip-settings.php' );
		roova_require( 'inc/admin/vip-user-profile.php' );
		roova_require( 'inc/admin/cashback-settings.php' );
	}

	Roova_Schema::init();
	Roova_Holds::init();
	Roova_Cart::init();
	Roova_Orders::init();
	Roova_Blocks::init();
} else {
	/**
	 * Tell the site owner the theme needs WooCommerce.
	 */
	function roova_woocommerce_missing_notice() {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}
		$install_url = wp_nonce_url(
			self_admin_url( 'update.php?action=install-plugin&plugin=woocommerce' ),
			'install-plugin_woocommerce'
		);
		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Roova needs WooCommerce.', 'roova' ) . '</strong> ';
		printf(
			/* translators: %s: install link */
			esc_html__( 'Hotels, rooms, bookings and checkout all run on WooCommerce. %s to finish setting up the theme.', 'roova' ),
			'<a href="' . esc_url( $install_url ) . '">' . esc_html__( 'Install WooCommerce', 'roova' ) . '</a>'
		);
		echo '</p></div>';
	}
	add_action( 'admin_notices', 'roova_woocommerce_missing_notice' );
}
