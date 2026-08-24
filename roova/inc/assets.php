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
		'https://fonts.googleapis.com/css2?family=Newsreader:opsz,wght@6..72,300;6..72,400;6..72,500&family=DM+Sans:opsz,wght@9..40,400;9..40,500;9..40,700&display=swap',
		array(),
		null // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts is versioned by URL.
	);

	wp_enqueue_style( 'roova-style', ROOVA_URI . 'assets/css/theme.css', array( 'roova-fonts' ), ROOVA_VERSION );
	wp_add_inline_style( 'roova-style', roova_inline_brand_css() );

	/*
	 * The coverage map draws real Natural Earth geometry, so it needs d3 and
	 * topojson. They are only loaded on the page that has the map, and
	 * theme.js takes them as dependencies so d3 is defined before it runs.
	 */
	$deps = array();
	if ( function_exists( 'roova_show_coverage_map' ) && roova_show_coverage_map() ) {
		foreach ( roova_map_libraries() as $handle => $library ) {
			// No ?ver= — the URL is pinned, and its integrity hash covers exactly
			// what that URL serves.
			wp_enqueue_script( $handle, $library['src'], array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			$deps[] = $handle;
		}
	}

	wp_enqueue_script( 'roova-theme', ROOVA_URI . 'assets/js/theme.js', $deps, ROOVA_VERSION, true );

	wp_localize_script( 'roova-theme', 'roovaData', roova_script_data() );

	if ( is_singular() ) {
		wp_enqueue_style( 'roova-print', ROOVA_URI . 'assets/css/print.css', array( 'roova-style' ), ROOVA_VERSION, 'print' );
	}
}
add_action( 'wp_enqueue_scripts', 'roova_enqueue_assets' );

/**
 * The pinned map libraries, with the hashes their tags are checked against.
 *
 * @return array[] Handle => array( src, integrity ).
 */
function roova_map_libraries() {
	return array(
		'roova-d3'       => array(
			'src'       => 'https://unpkg.com/d3@7.9.0/dist/d3.min.js',
			'integrity' => 'sha384-CjloA8y00+1SDAUkjs099PVfnY2KmDC2BZnws9kh8D/lX1s46w6EPhpXdqMfjK6i',
		),
		'roova-topojson' => array(
			'src'       => 'https://unpkg.com/topojson-client@3.1.0/dist/topojson-client.min.js',
			'integrity' => 'sha384-Ukv1p/xTma6P4/2bY5KzWBw+ydSpXmhCMtyciIQVDJ1RmOxtCYNMF1uXT9T63H67',
		),
	);
}

/**
 * Add subresource integrity to the map library tags.
 *
 * WordPress has no API for the integrity attribute, so the tag is patched on
 * the way out. Without it the browser would run whatever the CDN served.
 *
 * @param string $tag    Script tag.
 * @param string $handle Script handle.
 * @return string
 */
function roova_script_integrity( $tag, $handle ) {
	$libraries = roova_map_libraries();
	if ( ! isset( $libraries[ $handle ] ) ) {
		return $tag;
	}

	return str_replace(
		' src=',
		sprintf( ' integrity="%s" crossorigin="anonymous" src=', esc_attr( $libraries[ $handle ]['integrity'] ) ),
		$tag
	);
}
add_filter( 'script_loader_tag', 'roova_script_integrity', 10, 2 );

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

		/**
		 * Filter the world geometry the coverage map is drawn from.
		 *
		 * Natural Earth 110m country outlines, public domain.
		 *
		 * @param string $url TopoJSON URL.
		 */
		'atlasUrl'   => apply_filters( 'roova_atlas_url', 'https://cdn.jsdelivr.net/npm/world-atlas@2.0.2/countries-110m.json' ),

		/**
		 * Filter the two map framings, as [[west, north], [east, south]].
		 *
		 * @param array $views country => bbox, region => bbox.
		 */
		'atlasViews' => apply_filters( 'roova_atlas_views', array(
			'country' => array( array( 99.3, 7.6 ), array( 119.6, 0.6 ) ),
			'region'  => array( array( 101.35, 3.45 ), array( 102.45, 2.05 ) ),
		) ),

		/**
		 * Filter the country the map fills in navy.
		 *
		 * @param string $name Country name as Natural Earth spells it.
		 */
		'atlasHome'  => apply_filters( 'roova_atlas_home_country', 'Malaysia' ),

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
