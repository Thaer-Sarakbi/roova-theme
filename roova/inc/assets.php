<?php
/**
 * Front-end styles and scripts.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueue the theme's assets.
 */
function roova_enqueue_assets() {
	wp_enqueue_style(
		'roova-fonts',
		'https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap',
		array(),
		null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts is versioned by URL.
	);

	wp_enqueue_style( 'roova-style', ROOVA_URI . 'assets/css/theme.css', array( 'roova-fonts' ), ROOVA_VERSION );
	wp_add_inline_style( 'roova-style', roova_inline_brand_css() );

	wp_enqueue_script( 'roova-theme', ROOVA_URI . 'assets/js/theme.js', array(), ROOVA_VERSION, true );

	wp_localize_script( 'roova-theme', 'roovaData', roova_script_data() );

	if ( is_singular() ) {
		wp_enqueue_style( 'roova-print', ROOVA_URI . 'assets/css/print.css', array( 'roova-style' ), ROOVA_VERSION, 'print' );
	}
}
add_action( 'wp_enqueue_scripts', 'roova_enqueue_assets' );

/**
 * Data handed to the front-end script.
 *
 * @return array
 */
function roova_script_data() {
	$criteria = function_exists( 'roova_get_criteria' ) ? roova_get_criteria() : array();

	$months = array();
	$days   = array();
	global $wp_locale;
	if ( $wp_locale ) {
		for ( $i = 1; $i <= 12; $i++ ) {
			$months[] = $wp_locale->get_month( $i );
		}
		for ( $i = 0; $i <= 6; $i++ ) {
			$days[] = $wp_locale->get_weekday_abbrev( $wp_locale->get_weekday( $i ) );
		}
	}

	return array(
		'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
		'nonce'      => wp_create_nonce( 'roova_ajax' ),
		'searchUrl'  => function_exists( 'roova_search_url' ) ? roova_search_url() : home_url( '/' ),
		'criteria'   => $criteria,
		'today'      => function_exists( 'roova_today' ) ? roova_today() : gmdate( 'Y-m-d' ),
		'startOfWeek' => (int) get_option( 'start_of_week', 1 ),
		'months'     => $months,
		'days'       => $days,
		'mapsKey'    => function_exists( 'roova_option' ) ? roova_option( 'maps_api_key', '' ) : '',
		'i18n'       => array(
			'room'        => __( 'room', 'roova' ),
			'rooms'       => __( 'rooms', 'roova' ),
			'guest'       => __( 'guest', 'roova' ),
			'guests'      => __( 'guests', 'roova' ),
			'night'       => __( 'night', 'roova' ),
			'nights'      => __( 'nights', 'roova' ),
			'selectDates' => __( 'Select dates', 'roova' ),
			'noResults'   => __( 'Nothing matched that search.', 'roova' ),
			'loading'     => __( 'Loading…', 'roova' ),
			'soldOut'     => __( 'Sold out', 'roova' ),
			'close'       => __( 'Close', 'roova' ),
		),
	);
}

/**
 * Preconnect to the Google Fonts hosts.
 *
 * @param array  $urls          URLs.
 * @param string $relation_type Relation.
 * @return array
 */
function roova_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type && wp_style_is( 'roova-fonts', 'enqueued' ) ) {
		$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'roova_resource_hints', 10, 2 );
