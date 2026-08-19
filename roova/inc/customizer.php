<?php
/**
 * Theme options, exposed through the Customizer.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the theme's Customizer panels.
 *
 * @param WP_Customize_Manager $wp_customize Customizer.
 */
function roova_customize_register( $wp_customize ) {
	$wp_customize->add_panel( 'roova_panel', array(
		'title'    => __( 'Roova hotel theme', 'roova' ),
		'priority' => 30,
	) );

	/* ---------------------------------------------------------------- Brand */
	$wp_customize->add_section( 'roova_brand', array(
		'title' => __( 'Brand colours', 'roova' ),
		'panel' => 'roova_panel',
	) );

	$colors = array(
		'color_deep'   => array( __( 'Deep ocean (headers, buttons)', 'roova' ), '#0c3b57' ),
		'color_ocean'  => array( __( 'Ocean (accents, links)', 'roova' ), '#1678a8' ),
		'color_gold'   => array( __( 'Gold (stars, highlights)', 'roova' ), '#cf9d3f' ),
		'color_sand'   => array( __( 'Sand (section backgrounds)', 'roova' ), '#f7f3ea' ),
		'color_ink'    => array( __( 'Ink (body text)', 'roova' ), '#16232b' ),
	);

	foreach ( $colors as $key => $data ) {
		$wp_customize->add_setting( 'roova_' . $key, array(
			'default'           => $data[1],
			'sanitize_callback' => 'sanitize_hex_color',
			'transport'         => 'refresh',
		) );
		$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'roova_' . $key, array(
			'label'   => $data[0],
			'section' => 'roova_brand',
		) ) );
	}

	/* ------------------------------------------------------------------ Hero */
	$wp_customize->add_section( 'roova_hero', array(
		'title' => __( 'Homepage hero', 'roova' ),
		'panel' => 'roova_panel',
	) );

	$hero_fields = array(
		'hero_eyebrow'  => array( __( 'Eyebrow', 'roova' ), __( 'Klang Valley · Selangor', 'roova' ), 'text' ),
		'hero_title'    => array( __( 'Headline', 'roova' ), __( 'Stay close to everywhere that matters to you', 'roova' ), 'text' ),
		'hero_subtitle' => array( __( 'Sub-heading', 'roova' ), __( 'Book direct with us and always get the best rate.', 'roova' ), 'textarea' ),
	);

	foreach ( $hero_fields as $key => $data ) {
		$wp_customize->add_setting( 'roova_' . $key, array(
			'default'           => $data[1],
			'sanitize_callback' => 'textarea' === $data[2] ? 'sanitize_textarea_field' : 'sanitize_text_field',
		) );
		$wp_customize->add_control( 'roova_' . $key, array(
			'label'   => $data[0],
			'section' => 'roova_hero',
			'type'    => $data[2],
		) );
	}

	$wp_customize->add_setting( 'roova_hero_image', array(
		'default'           => '',
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, 'roova_hero_image', array(
		'label'     => __( 'Hero background image', 'roova' ),
		'section'   => 'roova_hero',
		'mime_type' => 'image',
	) ) );

	/* -------------------------------------------------------------- Sections */
	$wp_customize->add_section( 'roova_sections', array(
		'title' => __( 'Homepage sections', 'roova' ),
		'panel' => 'roova_panel',
	) );

	$section_fields = array(
		'hotels_eyebrow'      => array( __( 'Hotels eyebrow', 'roova' ), __( 'The collection', 'roova' ) ),
		'hotels_title'        => array( __( 'Hotels heading', 'roova' ), __( 'Our hotels', 'roova' ) ),
		'destinations_eyebrow' => array( __( 'Destinations eyebrow', 'roova' ), __( 'Where we are', 'roova' ) ),
		'destinations_title'  => array( __( 'Destinations heading', 'roova' ), __( 'Explore our destinations', 'roova' ) ),
	);

	foreach ( $section_fields as $key => $data ) {
		$wp_customize->add_setting( 'roova_' . $key, array(
			'default'           => $data[1],
			'sanitize_callback' => 'sanitize_text_field',
		) );
		$wp_customize->add_control( 'roova_' . $key, array(
			'label'   => $data[0],
			'section' => 'roova_sections',
			'type'    => 'text',
		) );
	}

	foreach ( array( 'show_hotels' => __( 'Show the hotels section', 'roova' ), 'show_destinations' => __( 'Show the destinations section', 'roova' ) ) as $key => $label ) {
		$wp_customize->add_setting( 'roova_' . $key, array(
			'default'           => true,
			'sanitize_callback' => 'roova_sanitize_checkbox',
		) );
		$wp_customize->add_control( 'roova_' . $key, array(
			'label'   => $label,
			'section' => 'roova_sections',
			'type'    => 'checkbox',
		) );
	}

	/* ----------------------------------------------------------- Booking */
	$wp_customize->add_section( 'roova_booking', array(
		'title'       => __( 'Booking', 'roova' ),
		'panel'       => 'roova_panel',
		'description' => __( 'How long rooms are held for guests who have not paid yet.', 'roova' ),
	) );

	$wp_customize->add_setting( 'roova_hold_minutes', array(
		'default'           => 30,
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'roova_hold_minutes', array(
		'label'       => __( 'Cart hold (minutes)', 'roova' ),
		'description' => __( 'Dates are held for this long after a room is added to the cart.', 'roova' ),
		'section'     => 'roova_booking',
		'type'        => 'number',
		'input_attrs' => array( 'min' => 5, 'max' => 240 ),
	) );

	$wp_customize->add_setting( 'roova_pending_minutes', array(
		'default'           => 60,
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'roova_pending_minutes', array(
		'label'       => __( 'Unpaid order hold (minutes)', 'roova' ),
		'description' => __( 'How long an unpaid order keeps its rooms before the dates are released. Paid and on-hold orders never expire.', 'roova' ),
		'section'     => 'roova_booking',
		'type'        => 'number',
		'input_attrs' => array( 'min' => 0, 'max' => 1440 ),
	) );

	/* --------------------------------------------------------------- Maps */
	$wp_customize->add_section( 'roova_maps', array(
		'title'       => __( 'Google Maps', 'roova' ),
		'panel'       => 'roova_panel',
		'description' => __( 'Paste a Google Maps JavaScript API key to show interactive maps on hotel pages. Without a key the theme shows a styled placeholder that links to Google Maps.', 'roova' ),
	) );

	$wp_customize->add_setting( 'roova_maps_api_key', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'roova_maps_api_key', array(
		'label'   => __( 'Google Maps API key', 'roova' ),
		'section' => 'roova_maps',
		'type'    => 'text',
	) );

	/* ------------------------------------------------------------- Footer */
	$wp_customize->add_section( 'roova_footer', array(
		'title' => __( 'Footer and contact', 'roova' ),
		'panel' => 'roova_panel',
	) );

	$footer_fields = array(
		'footer_tagline' => array( __( 'Footer tagline', 'roova' ), __( 'Direct booking, always the best rate.', 'roova' ) ),
		'contact_phone'  => array( __( 'Phone', 'roova' ), '' ),
		'contact_email'  => array( __( 'Email', 'roova' ), '' ),
		'contact_address' => array( __( 'Address', 'roova' ), '' ),
	);

	foreach ( $footer_fields as $key => $data ) {
		$wp_customize->add_setting( 'roova_' . $key, array(
			'default'           => $data[1],
			'sanitize_callback' => 'sanitize_text_field',
		) );
		$wp_customize->add_control( 'roova_' . $key, array(
			'label'   => $data[0],
			'section' => 'roova_footer',
			'type'    => 'text',
		) );
	}
}
add_action( 'customize_register', 'roova_customize_register' );

/**
 * Checkbox sanitiser.
 *
 * @param mixed $checked Value.
 * @return bool
 */
function roova_sanitize_checkbox( $checked ) {
	return ( isset( $checked ) && true === (bool) $checked );
}

/**
 * Brand colours as CSS custom properties.
 *
 * @return string
 */
function roova_inline_brand_css() {
	$deep  = roova_option( 'color_deep', '#0c3b57' );
	$ocean = roova_option( 'color_ocean', '#1678a8' );
	$gold  = roova_option( 'color_gold', '#cf9d3f' );
	$sand  = roova_option( 'color_sand', '#f7f3ea' );
	$ink   = roova_option( 'color_ink', '#16232b' );

	return sprintf(
		':root{--roova-deep:%1$s;--roova-ocean:%2$s;--roova-gold:%3$s;--roova-sand:%4$s;--roova-ink:%5$s;}',
		esc_attr( $deep ),
		esc_attr( $ocean ),
		esc_attr( $gold ),
		esc_attr( $sand ),
		esc_attr( $ink )
	);
}
