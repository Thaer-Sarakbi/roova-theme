<?php
/**
 * Registration and admin wiring for the Hotel and Room product types.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * The product types this theme adds: slug => label.
 *
 * @return array
 */
function roova_product_types() {
	return array(
		'hotel' => __( 'Hotel', 'roova' ),
		'room'  => __( 'Room (bookable)', 'roova' ),
	);
}

/**
 * Make sure the product_type terms exist.
 */
function roova_register_product_type_terms() {
	foreach ( array_keys( roova_product_types() ) as $type ) {
		if ( ! get_term_by( 'slug', $type, 'product_type' ) ) {
			wp_insert_term( $type, 'product_type' );
		}
	}
}
add_action( 'after_switch_theme', 'roova_register_product_type_terms' );
add_action( 'woocommerce_after_register_taxonomy', 'roova_register_product_type_terms' );

/**
 * Add the types to the product data dropdown.
 *
 * @param array $types Existing types.
 * @return array
 */
function roova_product_type_selector( $types ) {
	return array_merge( $types, roova_product_types() );
}
add_filter( 'product_type_selector', 'roova_product_type_selector' );

/**
 * Map product types to their classes.
 *
 * @param string $classname Class name.
 * @param string $type      Product type.
 * @return string
 */
function roova_product_class( $classname, $type ) {
	if ( 'hotel' === $type ) {
		return 'WC_Product_Hotel';
	}
	if ( 'room' === $type ) {
		return 'WC_Product_Room';
	}
	return $classname;
}
add_filter( 'woocommerce_product_class', 'roova_product_class', 10, 2 );

/**
 * Show the price fields for rooms and hide the irrelevant panels for hotels.
 *
 * @param array $tabs Product data tabs.
 * @return array
 */
function roova_product_data_tabs( $tabs ) {
	if ( isset( $tabs['inventory'] ) ) {
		$tabs['inventory']['class'][] = 'hide_if_hotel';
		$tabs['inventory']['class'][] = 'hide_if_room';
	}
	if ( isset( $tabs['shipping'] ) ) {
		$tabs['shipping']['class'][] = 'hide_if_hotel';
		$tabs['shipping']['class'][] = 'hide_if_room';
	}
	if ( isset( $tabs['linked_product'] ) ) {
		$tabs['linked_product']['class'][] = 'hide_if_hotel';
	}
	if ( isset( $tabs['attribute'] ) ) {
		$tabs['attribute']['class'][] = 'show_if_hotel';
		$tabs['attribute']['class'][] = 'show_if_room';
	}
	if ( isset( $tabs['general'] ) ) {
		$tabs['general']['class'][] = 'show_if_room';
		$tabs['general']['class'][] = 'hide_if_hotel';
	}
	return $tabs;
}
add_filter( 'woocommerce_product_data_tabs', 'roova_product_data_tabs' );

/**
 * Rooms are booked from their hotel page, so keep them out of the shop
 * catalogue and site search results by default.
 *
 * @param WP_Query $query Query.
 */
function roova_hide_rooms_from_catalog( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}
	if ( ! apply_filters( 'roova_hide_rooms_from_catalog', true ) ) {
		return;
	}

	/*
	 * Conditional tags are not reliable this early, so ask the query object
	 * itself: the shop archive, a product taxonomy archive, or a site search.
	 */
	$is_catalog = $query->is_post_type_archive( 'product' )
		|| $query->is_tax( get_object_taxonomies( 'product' ) );

	if ( ! $is_catalog && ! $query->is_search() ) {
		return;
	}

	$tax_query   = (array) $query->get( 'tax_query' );
	$tax_query[] = array(
		'taxonomy' => 'product_type',
		'field'    => 'slug',
		'terms'    => array( 'room' ),
		'operator' => 'NOT IN',
	);
	$query->set( 'tax_query', $tax_query );
}
add_action( 'pre_get_posts', 'roova_hide_rooms_from_catalog' );

/**
 * Admin scripts and styles for the product editor.
 *
 * @param string $hook Current admin page.
 */
function roova_admin_product_assets( $hook ) {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( $screen && 'product' === $screen->id ) {
		wp_enqueue_script( 'roova-admin-product', ROOVA_URI . 'assets/js/admin-product.js', array( 'jquery', 'wc-enhanced-select' ), ROOVA_VERSION, true );
		wp_enqueue_style( 'roova-admin', ROOVA_URI . 'assets/css/admin.css', array(), ROOVA_VERSION );
	}

	if ( $screen && in_array( $screen->id, array( 'edit-pa_amenity', 'edit-pa_destination' ), true ) ) {
		wp_enqueue_media();
		wp_enqueue_script( 'roova-admin-term', ROOVA_URI . 'assets/js/admin-term.js', array( 'jquery' ), ROOVA_VERSION, true );
		wp_enqueue_style( 'roova-admin', ROOVA_URI . 'assets/css/admin.css', array(), ROOVA_VERSION );
	}

	if ( $screen && false !== strpos( (string) $screen->id, 'roova-bookings' ) ) {
		wp_enqueue_style( 'roova-admin', ROOVA_URI . 'assets/css/admin.css', array(), ROOVA_VERSION );
	}

	unset( $hook );
}
add_action( 'admin_enqueue_scripts', 'roova_admin_product_assets' );
