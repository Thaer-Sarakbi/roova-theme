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

/* -------------------------------------------------------------------------
 * The Home page
 * ---------------------------------------------------------------------- */

/**
 * Make sure the front page has a Home page behind it, under Pages.
 *
 * `front-page.php` renders the front page regardless of what Settings ->
 * Reading says — whether it's "your latest posts" or a static page — so
 * this changes nothing a visitor sees. What it fixes is that a fresh site
 * has no page post for the front page at all, so it never shows up in the
 * Pages list the way Checkout, My account, Sign in, Sign up and Find a room
 * already do.
 *
 * A static front page that is already configured is adopted as it stands,
 * the same way an existing Checkout or Sign in page is.
 */
function roova_ensure_home_page() {
	$page_id = (int) get_option( 'roova_home_page_id' );

	if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
		if ( 'page' !== get_option( 'show_on_front' ) || (int) get_option( 'page_on_front' ) !== $page_id ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $page_id );
		}
		return;
	}

	if ( 'page' === get_option( 'show_on_front' ) ) {
		$existing_front = (int) get_option( 'page_on_front' );
		if ( $existing_front && 'publish' === get_post_status( $existing_front ) ) {
			update_option( 'roova_home_page_id', $existing_front );
			return;
		}
	}

	$existing = get_page_by_path( 'home' );
	if ( $existing && 'page' === $existing->post_type ) {
		if ( 'trash' === $existing->post_status ) {
			wp_untrash_post( $existing->ID );
		}

		if ( 'publish' !== get_post_status( $existing->ID ) ) {
			wp_update_post( array(
				'ID'          => $existing->ID,
				'post_status' => 'publish',
			) );
		}

		update_option( 'roova_home_page_id', $existing->ID );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $existing->ID );
		return;
	}

	$new_id = wp_insert_post( array(
		'post_title'     => __( 'Home', 'roova' ),
		'post_name'      => 'home',
		'post_status'    => 'publish',
		'post_type'      => 'page',
		'post_content'   => '',
		'comment_status' => 'closed',
	) );

	if ( $new_id && ! is_wp_error( $new_id ) ) {
		update_option( 'roova_home_page_id', $new_id );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $new_id );
	}
}
add_action( 'after_switch_theme', 'roova_ensure_home_page' );

/**
 * Check again on admin load, once per release.
 *
 * `after_switch_theme` never fires for a theme updated in place, and the
 * page can be trashed long after activation — the same reasoning as the
 * checkout and auth pages' own once-per-release checks.
 */
function roova_maybe_ensure_home_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( get_option( 'roova_home_version' ) === ROOVA_VERSION ) {
		return;
	}

	roova_ensure_home_page();
	update_option( 'roova_home_version', ROOVA_VERSION );
}
add_action( 'admin_init', 'roova_maybe_ensure_home_page' );

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

	if ( roova_is_hotel_page() ) {
		$classes[] = 'roova-page-white';
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
