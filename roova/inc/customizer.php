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
		'color_deep'  => array( __( 'Navy (headings, buttons)', 'roova' ), '#0d3a52' ),
		'color_ocean' => array( __( 'Ocean (secondary accents)', 'roova' ), '#1d6f96' ),
		'color_gold'  => array( __( 'Gold (eyebrows, hover, stars)', 'roova' ), '#b4823c' ),
		'color_sand'  => array( __( 'Sand (footer, tinted sections)', 'roova' ), '#f6f1e8' ),
		'color_cream' => array( __( 'Cream (page background)', 'roova' ), '#fbf8f3' ),
		'color_ink'   => array( __( 'Ink (body text)', 'roova' ), '#16302f' ),
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
		'hero_eyebrow'  => array( __( 'Eyebrow', 'roova' ), __( 'Klang Valley & Malacca', 'roova' ), 'text' ),
		'hero_title'    => array( __( 'Headline', 'roova' ), __( 'Stay close to everywhere that matters', 'roova' ), 'text' ),
		'hero_subtitle' => array( __( 'Sub-heading', 'roova' ), __( 'Honest rooms in the middle of things — airports, old towns, city edges. Booked direct, priced plainly.', 'roova' ), 'textarea' ),
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
		'label'       => __( 'Hero background image', 'roova' ),
		'description' => __( 'A wide photo — a lobby, a room or a skyline. Shown behind the headline and search bar.', 'roova' ),
		'section'     => 'roova_hero',
		'mime_type'   => 'image',
	) ) );

	$wp_customize->add_setting( 'roova_popular_searches', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_textarea_field',
	) );
	$wp_customize->add_control( 'roova_popular_searches', array(
		'label'       => __( 'Popular searches', 'roova' ),
		'description' => __( 'One per line, shown as pills under the search bar. Use "Label | destination-slug" to point a pill at a destination.', 'roova' ),
		'section'     => 'roova_hero',
		'type'        => 'textarea',
	) );

	/* ---------------------------------------------------------- Guarantees */
	$wp_customize->add_section( 'roova_guarantees', array(
		'title'       => __( 'Booking promises', 'roova' ),
		'panel'       => 'roova_panel',
		'description' => __( 'The four promises under the hero. Clear a title to drop that promise. Only claim what the hotels actually honour.', 'roova' ),
	) );

	foreach ( roova_guarantee_defaults() as $slot => $item ) {
		$wp_customize->add_setting( 'roova_guarantee_' . $slot . '_title', array(
			'default'           => $item['title'],
			'sanitize_callback' => 'sanitize_text_field',
		) );
		$wp_customize->add_control( 'roova_guarantee_' . $slot . '_title', array(
			/* translators: %d: promise number */
			'label'   => sprintf( __( 'Promise %d title', 'roova' ), $slot ),
			'section' => 'roova_guarantees',
			'type'    => 'text',
		) );

		$wp_customize->add_setting( 'roova_guarantee_' . $slot . '_body', array(
			'default'           => $item['body'],
			'sanitize_callback' => 'sanitize_textarea_field',
		) );
		$wp_customize->add_control( 'roova_guarantee_' . $slot . '_body', array(
			/* translators: %d: promise number */
			'label'   => sprintf( __( 'Promise %d text', 'roova' ), $slot ),
			'section' => 'roova_guarantees',
			'type'    => 'textarea',
		) );
	}

	/* -------------------------------------------------------------- Sections */
	$wp_customize->add_section( 'roova_sections', array(
		'title' => __( 'Homepage sections', 'roova' ),
		'panel' => 'roova_panel',
	) );

	$section_fields = array(
		'hotels_eyebrow'       => array( __( 'Hotels eyebrow', 'roova' ), __( 'The collection', 'roova' ) ),
		'hotels_title'         => array( __( 'Hotels heading', 'roova' ), __( 'Our hotels', 'roova' ) ),
		'destinations_eyebrow' => array( __( 'Destinations eyebrow', 'roova' ), __( 'Where we are', 'roova' ) ),
		'destinations_title'   => array( __( 'Destinations heading', 'roova' ), __( 'Explore our destinations', 'roova' ) ),
		'band_eyebrow'         => array( __( 'Photo band eyebrow', 'roova' ), __( 'Klang Valley & Malacca', 'roova' ) ),
		'band_statement'       => array( __( 'Photo band statement', 'roova' ), __( 'A short walk from wherever you\'re headed.', 'roova' ) ),
		'map_eyebrow'          => array( __( 'Map eyebrow', 'roova' ), __( 'Our map', 'roova' ) ),
		'map_title'            => array( __( 'Map heading', 'roova' ), __( 'Areas we cover', 'roova' ) ),
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

	$band_images = array(
		'band_image'  => __( 'Photo band image (above the hotels)', 'roova' ),
		'band2_image' => __( 'Second photo band image (above the map)', 'roova' ),
	);

	foreach ( $band_images as $key => $label ) {
		$wp_customize->add_setting( 'roova_' . $key, array(
			'default'           => '',
			'sanitize_callback' => 'absint',
		) );
		$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, 'roova_' . $key, array(
			'label'     => $label,
			'section'   => 'roova_sections',
			'mime_type' => 'image',
		) ) );
	}

	$toggles = array(
		'show_hotels'       => __( 'Show the hotels section', 'roova' ),
		'show_destinations' => __( 'Show the destinations section', 'roova' ),
		'show_map'          => __( 'Show the coverage map', 'roova' ),
	);

	foreach ( $toggles as $key => $label ) {
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

	/* ------------------------------------------------------------- Header */
	$wp_customize->add_section( 'roova_header', array(
		'title'       => __( 'Header', 'roova' ),
		'panel'       => 'roova_panel',
		'description' => __( 'The links beside the account control — a "Sign in" button for a visitor, a profile icon for a member. Menus themselves live under Appearance > Menus.', 'roova' ),
	) );

	$wp_customize->add_setting( 'roova_support_url', array(
		'default'           => '',
		'sanitize_callback' => 'esc_url_raw',
	) );
	$wp_customize->add_control( 'roova_support_url', array(
		'label'       => __( 'Support link', 'roova' ),
		'description' => __( 'Leave empty to hide it.', 'roova' ),
		'section'     => 'roova_header',
		'type'        => 'url',
	) );

	$wp_customize->add_setting( 'roova_support_label', array(
		'default'           => __( 'Support', 'roova' ),
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'roova_support_label', array(
		'label'   => __( 'Support link text', 'roova' ),
		'section' => 'roova_header',
		'type'    => 'text',
	) );

	/* ----------------------------------------------------------- Checkout */
	$wp_customize->add_section( 'roova_checkout', array(
		'title'       => __( 'Checkout', 'roova' ),
		'panel'       => 'roova_panel',
		'description' => __( 'The banner and the small print on the checkout page. Only promise what the hotels actually honour — the line under the Place order button is the last thing a guest reads before paying.', 'roova' ),
	) );

	$checkout_fields = array(
		'checkout_eyebrow'      => array( __( 'Banner eyebrow', 'roova' ), __( 'Secure checkout', 'roova' ) ),
		'checkout_secure_label' => array( __( 'Header reassurance', 'roova' ), __( 'Secure booking', 'roova' ) ),
		'checkout_reassurance'  => array( __( 'Under the Place order button', 'roova' ), __( 'Free cancellation until 24 hours before check-in.', 'roova' ) ),
		'checkout_signup_text'  => array( __( 'Sign-up invitation', 'roova' ), __( 'Sign up, become a member and get rewards', 'roova' ) ),
	);

	foreach ( $checkout_fields as $key => $data ) {
		$wp_customize->add_setting( 'roova_' . $key, array(
			'default'           => $data[1],
			'sanitize_callback' => 'sanitize_text_field',
		) );
		$wp_customize->add_control( 'roova_' . $key, array(
			'label'   => $data[0],
			'section' => 'roova_checkout',
			'type'    => 'text',
		) );
	}

	$wp_customize->add_setting( 'roova_checkout_banner_image', array(
		'default'           => '',
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, 'roova_checkout_banner_image', array(
		'label'       => __( 'Banner photo', 'roova' ),
		'description' => __( 'A wide reception or check-in photo. The theme ships one, so this can stay empty.', 'roova' ),
		'section'     => 'roova_checkout',
		'mime_type'   => 'image',
	) ) );

	$wp_customize->add_setting( 'roova_terms_url', array(
		'default'           => '',
		'sanitize_callback' => 'esc_url_raw',
	) );
	$wp_customize->add_control( 'roova_terms_url', array(
		'label'       => __( 'Booking terms link', 'roova' ),
		'description' => __( 'Only used when no terms page is set in WooCommerce > Settings > Advanced.', 'roova' ),
		'section'     => 'roova_checkout',
		'type'        => 'url',
	) );

	/* --------------------------------------------- Sign in and sign up */
	$wp_customize->add_section( 'roova_auth', array(
		'title'       => __( 'Sign in and sign up', 'roova' ),
		'panel'       => 'roova_panel',
		'description' => __( 'The photo panel beside the two account forms. The figures are yours to stand behind — clear either one and its column disappears rather than showing a number nobody can back up.', 'roova' ),
	) );

	// These defaults are repeated at the call sites in inc/auth.php and the two
	// page templates: get_theme_mod() falls back to what the caller passes, not
	// to the default registered here, so the pair has to match.
	$auth_fields = array(
		'auth_signin_headline' => array( __( 'Sign in headline', 'roova' ), __( 'Your next stay is two taps away. Member rates apply the moment you sign in.', 'roova' ) ),
		'auth_signup_headline' => array( __( 'Sign up headline', 'roova' ), __( 'Book a room in Malaysia in under a minute, and keep every stay in one place.', 'roova' ) ),
		'auth_stat_1_figure'   => array( __( 'First figure', 'roova' ), __( '1,240+', 'roova' ) ),
		'auth_stat_1_label'    => array( __( 'First figure label', 'roova' ), __( 'Stays across Malaysia', 'roova' ) ),
		'auth_stat_2_figure'   => array( __( 'Second figure', 'roova' ), __( 'Zero', 'roova' ) ),
		'auth_stat_2_label'    => array( __( 'Second figure label', 'roova' ), __( 'Booking fees, always', 'roova' ) ),
	);

	foreach ( $auth_fields as $key => $data ) {
		$wp_customize->add_setting( 'roova_' . $key, array(
			'default'           => $data[1],
			'sanitize_callback' => 'sanitize_text_field',
		) );
		$wp_customize->add_control( 'roova_' . $key, array(
			'label'   => $data[0],
			'section' => 'roova_auth',
			'type'    => 'text',
		) );
	}

	/*
	 * The theme's own switch, not WooCommerce's "Allow customers to create an
	 * account" — that one governs the form on WooCommerce's account page, and
	 * has no bearing on the sign-up page, which calls wc_create_new_customer()
	 * directly. Default on: the theme ships the page, so shipping it closed
	 * would make no sense.
	 */
	$wp_customize->add_setting( 'roova_registration_open', array(
		'default'           => true,
		'sanitize_callback' => 'roova_sanitize_checkbox',
	) );
	$wp_customize->add_control( 'roova_registration_open', array(
		'label'       => __( 'Let guests create accounts', 'roova' ),
		'description' => __( 'Off replaces the sign-up form with a short note, and leaves sign-in working for members who already have an account.', 'roova' ),
		'section'     => 'roova_auth',
		'type'        => 'checkbox',
	) );

	/*
	 * Its own setting rather than the site logo: the header's logo is picked to
	 * sit on the hero photograph and is usually the light, reversed-out version
	 * of a mark, which would vanish against the white form column here.
	 */
	$wp_customize->add_setting( 'roova_auth_logo', array(
		'default'           => '',
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, 'roova_auth_logo', array(
		'label'       => __( 'Logo on these pages', 'roova' ),
		'description' => __( 'The full-colour version of your logo — these pages are white, so the light one used over the hero would disappear. The theme ships one, so this can stay empty.', 'roova' ),
		'section'     => 'roova_auth',
		'mime_type'   => 'image',
	) ) );

	// A photo each: the two pages are seen one after the other, and the same
	// picture twice reads as a page that failed to change.
	$auth_images = array(
		'roova_auth_signin_image' => array(
			__( 'Sign in photo', 'roova' ),
			__( 'Tall crops read best — the panel is a full-height column on a desktop, and at least 700 × 900 keeps it sharp. The theme ships one, so this can stay empty.', 'roova' ),
		),
		'roova_auth_signup_image' => array(
			__( 'Sign up photo', 'roova' ),
			__( 'The same again for the sign-up page. Choose a different picture from the sign-in one.', 'roova' ),
		),
	);

	foreach ( $auth_images as $key => $data ) {
		$wp_customize->add_setting( $key, array(
			'default'           => '',
			'sanitize_callback' => 'absint',
		) );
		$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, $key, array(
			'label'       => $data[0],
			'description' => $data[1],
			'section'     => 'roova_auth',
			'mime_type'   => 'image',
		) ) );
	}

	/* ------------------------------------------------------------- Footer */
	$wp_customize->add_section( 'roova_footer', array(
		'title' => __( 'Footer and contact', 'roova' ),
		'panel' => 'roova_panel',
	) );

	$footer_fields = array(
		'footer_tagline'   => array( __( 'Footer tagline', 'roova' ), __( 'Malaysian hotels, booked direct. Klang Valley and Malacca.', 'roova' ) ),
		'footer_heading_1' => array( __( 'Column 1 heading', 'roova' ), __( 'Stay', 'roova' ) ),
		'footer_heading_2' => array( __( 'Column 2 heading', 'roova' ), __( 'Guests', 'roova' ) ),
		'footer_heading_3' => array( __( 'Column 3 heading', 'roova' ), __( 'Company', 'roova' ) ),
		'footer_note'      => array( __( 'Bottom-right note', 'roova' ), __( 'Made in Malaysia', 'roova' ) ),
		'contact_phone'    => array( __( 'Phone', 'roova' ), '' ),
		'contact_email'    => array( __( 'Email', 'roova' ), '' ),
		'contact_address'  => array( __( 'Address', 'roova' ), '' ),
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
	$deep  = roova_option( 'color_deep', '#0d3a52' );
	$ocean = roova_option( 'color_ocean', '#1d6f96' );
	$gold  = roova_option( 'color_gold', '#b4823c' );
	$sand  = roova_option( 'color_sand', '#f6f1e8' );
	$cream = roova_option( 'color_cream', '#fbf8f3' );
	$ink   = roova_option( 'color_ink', '#16302f' );

	return sprintf(
		':root{--roova-deep:%1$s;--roova-ocean:%2$s;--roova-gold:%3$s;--roova-sand:%4$s;--roova-cream:%5$s;--roova-ink:%6$s;}',
		esc_attr( $deep ),
		esc_attr( $ocean ),
		esc_attr( $gold ),
		esc_attr( $sand ),
		esc_attr( $cream ),
		esc_attr( $ink )
	);
}
