<?php
/**
 * Loads every theme file against a minimal WordPress/WooCommerce stub to catch
 * fatals that only appear at include time: redeclared functions, missing
 * classes, and anything the theme calls while it is still loading.
 *
 * Usage:
 *   php tests/test-load.php            # front-end load
 *   php tests/test-load.php admin      # admin load (loads inc/admin/*)
 *   php tests/test-load.php no-wc      # WooCommerce inactive
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'DB_NAME', 'roova_test' );

$args               = array_slice( $argv, 1 );
$GLOBALS['is_admin'] = in_array( 'admin', $args, true );
$with_woocommerce    = ! in_array( 'no-wc', $args, true );

/* --------------------------------------------------------- WordPress */

$GLOBALS['roova_hooks'] = array();

function add_action( $hook, $callback = null, $priority = 10, $args = 1 ) {
	$GLOBALS['roova_hooks'][ $hook ][] = $callback;
	return true;
}

function add_filter( $hook, $callback = null, $priority = 10, $args = 1 ) {
	$GLOBALS['roova_hooks'][ $hook ][] = $callback;
	return true;
}

function remove_action( $hook, $callback = null, $priority = 10 ) {
	return true;
}

function remove_filter( $hook, $callback = null, $priority = 10 ) {
	return true;
}

function apply_filters( $hook, $value = null ) {
	return $value;
}

function do_action( $hook ) {
}

function add_submenu_page() {
}

function add_meta_box() {
}

function get_template_directory() {
	return dirname( __DIR__ ) . '/roova';
}

function get_template_directory_uri() {
	return 'https://example.test/wp-content/themes/roova';
}

function trailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' ) . '/';
}

function is_admin() {
	return (bool) $GLOBALS['is_admin'];
}

function __( $text, $domain = null ) {
	return $text;
}

function _x( $text, $context, $domain = null ) {
	return $text;
}

function _n( $single, $plural, $number, $domain = null ) {
	return 1 === (int) $number ? $single : $plural;
}

function esc_html__( $text, $domain = null ) {
	return $text;
}

function esc_attr__( $text, $domain = null ) {
	return $text;
}

function esc_html_e( $text, $domain = null ) {
	echo $text;
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES );
}

function esc_url( $url ) {
	return $url;
}

function absint( $value ) {
	return abs( (int) $value );
}

function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, (array) $args );
}

function current_time( $format ) {
	return 'mysql' === $format ? gmdate( 'Y-m-d H:i:s' ) : gmdate( $format );
}

function get_theme_mod( $key, $default = '' ) {
	return $default;
}

function get_option( $key, $default = false ) {
	return $default;
}

function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . $path;
}

function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}

function self_admin_url( $path = '' ) {
	return admin_url( $path );
}

function current_user_can() {
	return false;
}

function get_current_screen() {
	return null;
}

function wp_create_nonce( $action ) {
	return 'nonce';
}

function wp_next_scheduled() {
	return false;
}

function taxonomy_exists() {
	return true;
}

function wc_get_page_screen_id() {
	return 'woocommerce_page_wc-orders';
}

/* ------------------------------------------------------- WooCommerce */

class WC_Product {
	public function get_id() {
		return 0;
	}

	public function get_type() {
		return 'simple';
	}

	public function get_price( $context = 'view' ) {
		return '';
	}

	public function get_regular_price() {
		return '';
	}

	public function get_status() {
		return 'publish';
	}

	public function get_permalink() {
		return '';
	}

	public function get_name() {
		return '';
	}

	public function is_on_sale() {
		return false;
	}

	public function is_purchasable() {
		return true;
	}
}

if ( $with_woocommerce ) {
	class WooCommerce {
	}
}

/* -------------------------------------------------------------- load */

require dirname( __DIR__ ) . '/roova/functions.php';

$mode = ( $with_woocommerce ? 'WooCommerce active' : 'WooCommerce inactive' ) . ( $GLOBALS['is_admin'] ? ', admin' : ', front end' );

printf( "ok    theme loaded (%s) — %d hooks registered\n", $mode, count( $GLOBALS['roova_hooks'] ) );
