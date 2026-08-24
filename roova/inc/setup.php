<?php
/**
 * Theme supports, menus and image sizes.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * Theme setup.
 */
function roova_setup() {
	load_theme_textdomain( 'roova', ROOVA_DIR . 'languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'custom-logo', array(
		'height'      => 48,
		'width'       => 220,
		'flex-height' => true,
		'flex-width'  => true,
	) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );

	// WooCommerce.
	add_theme_support( 'woocommerce', array(
		'thumbnail_image_width' => 540,
		'single_image_width'    => 1200,
	) );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	/*
	 * The footer is three link columns; each is its own menu location so the
	 * client can fill them from Appearance > Menus. Column headings are
	 * Customizer settings (roova_footer_heading_1..3).
	 */
	register_nav_menus( array(
		'primary'  => __( 'Primary menu', 'roova' ),
		'footer'   => __( 'Footer column 1', 'roova' ),
		'footer-2' => __( 'Footer column 2', 'roova' ),
		'footer-3' => __( 'Footer column 3', 'roova' ),
	) );

	add_image_size( 'roova-hotel-card', 640, 420, true );
	add_image_size( 'roova-hotel-hero', 1400, 900, true );
	add_image_size( 'roova-room-thumb', 480, 360, true );
}
add_action( 'after_setup_theme', 'roova_setup' );

/**
 * Declare HPOS / cart-checkout blocks compatibility.
 */
function roova_declare_wc_compat() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', get_template_directory() . '/functions.php', true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', get_template_directory() . '/functions.php', true );
	}
}
add_action( 'before_woocommerce_init', 'roova_declare_wc_compat' );

/**
 * Paint the homepage and the hotel pages white instead of cream.
 *
 * The class flips the --roova-page custom property on <body>; --roova-cream
 * stays warm because it is also the text colour on every dark panel.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function roova_body_classes( $classes ) {
	if ( is_front_page() ) {
		$classes[] = 'roova-page-white';
		return $classes;
	}

	if ( is_singular( 'product' ) && function_exists( 'wc_get_product' ) ) {
		$product = wc_get_product( get_queried_object_id() );
		if ( $product && 'hotel' === $product->get_type() ) {
			$classes[] = 'roova-page-white';
		}
	}

	return $classes;
}
add_filter( 'body_class', 'roova_body_classes' );

/**
 * Content width.
 */
function roova_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'roova_content_width', 1200 );
}
add_action( 'after_setup_theme', 'roova_content_width', 0 );

/**
 * Widget areas.
 */
function roova_widgets_init() {
	register_sidebar( array(
		'name'          => __( 'Footer', 'roova' ),
		'id'            => 'roova-footer',
		'description'   => __( 'Shown in the footer columns.', 'roova' ),
		'before_widget' => '<div id="%1$s" class="widget %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<h4 class="widget-title">',
		'after_title'   => '</h4>',
	) );
}
add_action( 'widgets_init', 'roova_widgets_init' );
