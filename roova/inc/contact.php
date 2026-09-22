<?php
/**
 * The Contact us page: the desk a guest reaches for when a page has not
 * answered them.
 *
 * Everything on it is a Customizer setting — the headline, the three channels,
 * the office address, the opening hours, the map and the social links — because
 * every one of them is a promise only the client can make good on. A hardcoded
 * phone number on a contact page is worse than an empty one: it rings somebody
 * else.
 *
 * The three details the footer already carries (roova_contact_phone,
 * roova_contact_email, roova_contact_address) are reused here rather than
 * duplicated. Two phone fields in one Customizer would guarantee that the
 * footer and this page eventually disagree about how to reach the company.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * The page itself
 * ---------------------------------------------------------------------- */

/**
 * Make sure there is a Contact us page, with this template on it.
 *
 * Adopts whatever is already at /contact/, untrashes and republishes a page
 * that was removed, and only creates one when there is nothing to adopt — the
 * same shape as the checkout, account and auth pages' own checks, and for the
 * same reason: the page is linked from the footer and the primary menu, and a
 * trashed page still has a permalink that 404s.
 */
function roova_ensure_contact_page() {
	$page_id = (int) get_option( 'roova_contact_page_id' );

	if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
		// Re-assert the template: a page can be published with the wrong one.
		if ( 'template-contact.php' !== get_page_template_slug( $page_id ) ) {
			update_post_meta( $page_id, '_wp_page_template', 'template-contact.php' );
		}
		return;
	}

	$existing = $page_id ? get_post( $page_id ) : null;
	if ( ! $existing ) {
		$existing = get_page_by_path( 'contact' );
	}

	if ( $existing && 'page' === $existing->post_type ) {
		if ( 'trash' === $existing->post_status ) {
			// Untrashing restores the slug too, which trashing had suffixed.
			wp_untrash_post( $existing->ID );
		}

		if ( 'publish' !== get_post_status( $existing->ID ) ) {
			wp_update_post( array(
				'ID'          => $existing->ID,
				'post_status' => 'publish',
			) );
		}

		update_post_meta( $existing->ID, '_wp_page_template', 'template-contact.php' );
		update_option( 'roova_contact_page_id', $existing->ID );
		return;
	}

	$new_id = wp_insert_post( array(
		'post_title'     => __( 'Contact us', 'roova' ),
		'post_name'      => 'contact',
		'post_status'    => 'publish',
		'post_type'      => 'page',
		'post_content'   => '',
		'comment_status' => 'closed',
	) );

	if ( $new_id && ! is_wp_error( $new_id ) ) {
		update_post_meta( $new_id, '_wp_page_template', 'template-contact.php' );
		update_option( 'roova_contact_page_id', $new_id );
	}
}
add_action( 'after_switch_theme', 'roova_ensure_contact_page' );

/**
 * Check again on admin load, once per release.
 *
 * `after_switch_theme` never fires for a theme uploaded over a live site, which
 * is how this theme is updated.
 */
function roova_maybe_ensure_contact_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( get_option( 'roova_contact_version' ) === ROOVA_VERSION ) {
		return;
	}

	roova_ensure_contact_page();
	update_option( 'roova_contact_version', ROOVA_VERSION );
}
add_action( 'admin_init', 'roova_maybe_ensure_contact_page' );

/**
 * The Contact us page, when it is actually published.
 *
 * Published rather than merely pointed at, for the reason roova_auth_page_id()
 * spells out: a trashed page's permalink is a 404, and this one can end up in a
 * menu the client built by hand.
 *
 * @return int Page ID, or 0.
 */
function roova_contact_page_id() {
	$page_id = (int) get_option( 'roova_contact_page_id' );

	if ( $page_id && 'publish' !== get_post_status( $page_id ) ) {
		return 0;
	}

	return $page_id;
}

/**
 * Link to the Contact us page, or the home page when there isn't one.
 *
 * @return string
 */
function roova_contact_url() {
	$page_id = roova_contact_page_id();

	return $page_id ? get_permalink( $page_id ) : home_url( '/' );
}

/**
 * Is this the Contact us page?
 *
 * The page template rather than the stored ID, so a page the client rebuilt by
 * hand and assigned the template still counts — the rule
 * roova_is_search_page() and roova_is_auth_page() both follow.
 *
 * @return bool
 */
function roova_is_contact_page() {
	return is_page_template( 'template-contact.php' );
}

/* -------------------------------------------------------------------------
 * The details themselves
 * ---------------------------------------------------------------------- */

/**
 * The office address, one line per line the client typed.
 *
 * A postal address is several lines, which is why the setting is a textarea.
 *
 * @return string[]
 */
function roova_contact_address_lines() {
	$raw   = (string) roova_option( 'contact_address', '' );
	$lines = preg_split( '/\r\n|\r|\n/', $raw );
	$out   = array();

	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( '' !== $line ) {
			$out[] = $line;
		}
	}

	return $out;
}

/**
 * The same address on one line, for somewhere too tight to break it.
 *
 * The footer's contact row is a single inline line, and so is the `destination`
 * a Google Maps link carries.
 *
 * @return string
 */
function roova_contact_address_inline() {
	return implode( ', ', roova_contact_address_lines() );
}

/**
 * The opening hours, as rows.
 *
 * One row per line, "Days | Time" — the same shape the hotel landmarks field
 * uses, so a client who has filled one already knows how to fill this. A line
 * with no pipe is treated as the days with no time beside them, which is how
 * "Public holidays — closed" gets written as one phrase.
 *
 * Empty by default, unlike the title and the intro. Those are copy a client will
 * rewrite; opening hours are a fact a guest turns up on, and shipping a plausible
 * set would have a fresh install promising a desk is staffed at nine on Saturday
 * before anyone had said so — the rule the cashback offers follow.
 *
 * @return array[] Each: days, time.
 */
function roova_contact_hours() {
	$raw = (string) roova_option( 'contact_hours', '' );

	$rows = array();

	foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}

		$days = $line;
		$time = '';

		if ( false !== strpos( $line, '|' ) ) {
			$parts = explode( '|', $line, 2 );
			$days  = trim( $parts[0] );
			$time  = trim( $parts[1] );
		}

		if ( '' === $days && '' === $time ) {
			continue;
		}

		$rows[] = array(
			'days' => $days,
			'time' => $time,
		);
	}

	return $rows;
}

/**
 * The three ways to reach the company, as cards.
 *
 * A channel with nothing in it is left out rather than printed empty — the rule
 * roova_hotel_contact() follows for a hotel with no reception number. The notes
 * are the client's own words: the stock ones say what a channel is *for* and
 * never what it answers *by*, because the only opening hours this theme knows
 * are the ones in the hours field.
 *
 * @return array[] Each: key, icon, label, value, href, note, external.
 */
function roova_contact_channels() {
	$phone    = trim( (string) roova_option( 'contact_phone', '' ) );
	$whatsapp = trim( (string) roova_option( 'contact_whatsapp', '' ) );
	$email    = trim( (string) roova_option( 'contact_email', '' ) );

	$channels = array();

	if ( $phone ) {
		$tel = roova_tel_href( $phone );

		$channels[] = array(
			'key'      => 'phone',
			'icon'     => 'phone',
			'label'    => __( 'Call us', 'roova' ),
			'value'    => $phone,
			'href'     => $tel ? 'tel:' . $tel : '',
			'note'     => trim( (string) roova_option( 'contact_phone_note', __( 'Have your booking reference to hand.', 'roova' ) ) ),
			'external' => false,
		);
	}

	if ( $whatsapp ) {
		/*
		 * wa.me takes digits and nothing else — no "+", no spaces, no brackets.
		 * roova_tel_href() keeps a leading plus for diallers, so it comes off
		 * again here.
		 */
		$digits = preg_replace( '/\D+/', '', $whatsapp );

		$channels[] = array(
			'key'      => 'whatsapp',
			'icon'     => 'chat',
			'label'    => __( 'WhatsApp', 'roova' ),
			'value'    => $whatsapp,
			'href'     => $digits ? 'https://wa.me/' . $digits : '',
			'note'     => trim( (string) roova_option( 'contact_whatsapp_note', __( 'Best for a change to a stay that starts soon.', 'roova' ) ) ),
			'external' => true,
		);
	}

	if ( $email ) {
		$channels[] = array(
			'key'      => 'email',
			'icon'     => 'mail',
			'label'    => __( 'Email', 'roova' ),
			'value'    => $email,
			'href'     => is_email( $email ) ? 'mailto:' . $email : '',
			'note'     => trim( (string) roova_option( 'contact_email_note', __( 'Invoices, refunds and anything that needs a paper trail.', 'roova' ) ) ),
			'external' => false,
		);
	}

	return $channels;
}

/**
 * What the map should point at: the pin if there is one, else the address.
 *
 * @return string Query for a Google Maps URL, or '' when there is nothing to show.
 */
function roova_contact_map_query() {
	$lat = trim( (string) roova_option( 'contact_lat', '' ) );
	$lng = trim( (string) roova_option( 'contact_lng', '' ) );

	if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
		return $lat . ',' . $lng;
	}

	return roova_contact_address_inline();
}

/**
 * The embedded map.
 *
 * `output=embed` rather than the Maps JavaScript API the hotel pages use: that
 * one needs a billable API key, and a contact page that shows nothing until
 * somebody sets up Google Cloud billing is a contact page with a hole in it.
 * This works on a fresh install with an address and nothing else.
 *
 * @return string URL, or '' when there is no location to show.
 */
function roova_contact_map_url() {
	$query = roova_contact_map_query();
	if ( ! $query ) {
		return '';
	}

	$zoom = (int) roova_option( 'contact_map_zoom', 16 );
	$zoom = max( 1, min( 21, $zoom ) );

	return add_query_arg(
		array(
			'q'      => rawurlencode( $query ),
			'z'      => $zoom,
			'output' => 'embed',
		),
		'https://www.google.com/maps'
	);
}

/**
 * Where tapping the map goes: the office on Google Maps.
 *
 * The same rule the hotel pages follow (roova_hotel_map_url()), and asked for
 * in the same words: the link the client pasted from Google's Share button
 * first, then the office by *name* and address, and the coordinates only when
 * there is no address to search for. A lat/lng link opens a pin on a blank
 * patch of map — no name, no photos, no opening hours — which is not where
 * somebody looking for the office wants to land.
 *
 * It replaced the "Get directions" button under the address: the whole map is
 * this link now, and Google's own page has a directions button on it.
 *
 * @return string URL, or ''.
 */
function roova_contact_place_url() {
	$link = roova_maps_link( roova_option( 'contact_map_link', '' ) );
	if ( $link ) {
		return $link;
	}

	$address = roova_contact_address_inline();
	$name = trim( (string) get_bloginfo( 'name' ) );

	/*
	 * The name goes in front of the address, because a name is what turns a
	 * search into a place. Not when the address already opens with the company
	 * on its own first line, though — "Roova, Roova Travel Sdn Bhd, Level 9…"
	 * is a worse search than either half.
	 */
	if ( $name && $address && false !== stripos( $address, $name ) ) {
		$name = '';
	}

	$query = $address ? trim( trim( $name . ', ' . $address ), ', ' ) : '';

	if ( ! $query ) {
		$query = roova_contact_map_query();
	}

	if ( ! $query ) {
		return '';
	}

	$args = array(
		'api'   => '1',
		'query' => rawurlencode( $query ),
	);

	// Google's own ID for the place the Customizer's search found: it opens
	// the office's listing rather than whatever the query happens to match.
	$place_id = trim( (string) roova_option( 'contact_place_id', '' ) );

	if ( $place_id ) {
		$args['query_place_id'] = rawurlencode( $place_id );
	}

	return add_query_arg( $args, 'https://www.google.com/maps/search/' );
}

/**
 * The networks the theme can link to: key => label.
 *
 * @return array
 */
function roova_social_networks() {
	return array(
		'instagram' => __( 'Instagram', 'roova' ),
		'facebook'  => __( 'Facebook', 'roova' ),
		'x'         => __( 'X', 'roova' ),
		'tiktok'    => __( 'TikTok', 'roova' ),
		'youtube'   => __( 'YouTube', 'roova' ),
		'linkedin'  => __( 'LinkedIn', 'roova' ),
	);
}

/**
 * The social accounts that have actually been filled in.
 *
 * Nothing is guessed from the site name: a wrong link to somebody else's
 * Instagram is worse than no link at all.
 *
 * @return array[] Each: key, label, url.
 */
function roova_contact_socials() {
	$links = array();

	foreach ( roova_social_networks() as $key => $label ) {
		$url = trim( (string) roova_option( 'social_' . $key, '' ) );
		if ( ! $url ) {
			continue;
		}

		$links[] = array(
			'key'   => $key,
			'label' => $label,
			'url'   => $url,
		);
	}

	return $links;
}

/**
 * A social network's brand mark.
 *
 * Its own tiny library rather than inc/icons.php: every icon there is drawn as
 * a 24x24 stroke so the amenities, the room features and the UI chrome share
 * one language, and these are filled brand glyphs that would come out hollow
 * through roova_icon(). They inherit currentColor the same way.
 *
 * @param string $network Network key.
 * @param int    $size    Pixel size.
 * @return string Safe SVG markup, or ''.
 */
function roova_social_icon( $network, $size = 20 ) {
	$paths = array(
		'instagram' => 'M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.96.24 2.46.44.6.23 1.1.55 1.58 1.03.48.48.8.98 1.03 1.58.2.5.39 1.29.44 2.46.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.24 1.96-.44 2.46a4.26 4.26 0 0 1-1.03 1.58 4.26 4.26 0 0 1-1.58 1.03c-.5.2-1.29.39-2.46.44-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.96-.24-2.46-.44a4.26 4.26 0 0 1-1.58-1.03 4.26 4.26 0 0 1-1.03-1.58c-.2-.5-.39-1.29-.44-2.46C2.17 15.58 2.16 15.2 2.16 12s.01-3.58.07-4.85c.05-1.17.24-1.96.44-2.46.23-.6.55-1.1 1.03-1.58a4.26 4.26 0 0 1 1.58-1.03c.5-.2 1.29-.39 2.46-.44C8.42 2.17 8.8 2.16 12 2.16zm0 1.98c-3.14 0-3.5.01-4.73.07-.94.04-1.45.2-1.79.33-.45.17-.77.38-1.11.72-.34.34-.55.66-.72 1.11-.13.34-.29.85-.33 1.79-.06 1.23-.07 1.59-.07 4.73s.01 3.5.07 4.73c.04.94.2 1.45.33 1.79.17.45.38.77.72 1.11.34.34.66.55 1.11.72.34.13.85.29 1.79.33 1.23.06 1.59.07 4.73.07s3.5-.01 4.73-.07c.94-.04 1.45-.2 1.79-.33.45-.17.77-.38 1.11-.72.34-.34.55-.66.72-1.11.13-.34.29-.85.33-1.79.06-1.23.07-1.59.07-4.73s-.01-3.5-.07-4.73c-.04-.94-.2-1.45-.33-1.79a2.99 2.99 0 0 0-.72-1.11 2.99 2.99 0 0 0-1.11-.72c-.34-.13-.85-.29-1.79-.33-1.23-.06-1.59-.07-4.73-.07zm0 3.37a4.49 4.49 0 1 1 0 8.98 4.49 4.49 0 0 1 0-8.98zm0 1.98a2.51 2.51 0 1 0 0 5.02 2.51 2.51 0 0 0 0-5.02zm5.72-2.2a1.05 1.05 0 1 1-2.1 0 1.05 1.05 0 0 1 2.1 0z',
		'facebook'  => 'M13.5 21.9v-8.2h2.8l.42-3.25H13.5V8.37c0-.94.26-1.58 1.61-1.58h1.72V3.88c-.3-.04-1.32-.13-2.51-.13-2.49 0-4.19 1.52-4.19 4.3v2.4H7.32v3.25h2.81v8.2h3.37z',
		'x'         => 'M17.53 3h3.2l-6.99 7.99L22 21h-6.34l-4.42-5.8L6.16 21H2.95l7.3-8.34L2.3 3h6.5l4.13 5.46L17.53 3zm-1.12 16.06h1.77L7.67 4.84H5.77l10.64 14.22z',
		'tiktok'    => 'M16.6 5.82A4.28 4.28 0 0 1 15.54 3h-3.09v12.4a2.59 2.59 0 1 1-1.85-2.48V9.78a5.66 5.66 0 1 0 4.94 5.61V8.9a7.32 7.32 0 0 0 4.28 1.38V7.19a4.28 4.28 0 0 1-3.22-1.37z',
		'youtube'   => 'M21.6 7.2a2.51 2.51 0 0 0-1.77-1.77C18.25 5 12 5 12 5s-6.25 0-7.83.43A2.51 2.51 0 0 0 2.4 7.2C2 8.79 2 12 2 12s0 3.21.4 4.8a2.51 2.51 0 0 0 1.77 1.77C5.75 19 12 19 12 19s6.25 0 7.83-.43a2.51 2.51 0 0 0 1.77-1.77C22 15.21 22 12 22 12s0-3.21-.4-4.8zM10 15.15V8.85L15.45 12 10 15.15z',
		'linkedin'  => 'M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM3.1 9.98h3.75V21H3.1V9.98zm6.13 0h3.6v1.5h.05a3.95 3.95 0 0 1 3.55-1.95c3.8 0 4.5 2.5 4.5 5.76V21h-3.74v-5.02c0-1.2-.02-2.74-1.67-2.74-1.67 0-1.93 1.3-1.93 2.65V21H9.23V9.98z',
	);

	$network = sanitize_key( $network );
	if ( ! isset( $paths[ $network ] ) ) {
		return '';
	}

	return sprintf(
		'<svg class="roova-social__mark" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="%2$s" /></svg>',
		absint( $size ),
		esc_attr( $paths[ $network ] )
	);
}

/* -------------------------------------------------------------------------
 * The page's markup
 * ---------------------------------------------------------------------- */

/**
 * The contact page's own header: wordmark, the Primary menu and the account
 * button, on navy.
 *
 * The page prints its own document, so this is its whole header — the same
 * arrangement the search results page uses, and for the same reason: a page
 * that prints its own document is still a page of this site, and a header with
 * no way out of it is a dead end.
 */
function roova_contact_page_header() {
	$tagline = roova_option( 'search_tagline', __( 'Global hotel booking', 'roova' ) );
	?>
	<header class="roova-cp__bar">
		<div class="roova-cp__bar-inner">
			<a class="roova-cp__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php echo roova_wordmark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
				<?php if ( $tagline ) : ?>
					<span class="roova-cp__tagline"><?php echo esc_html( $tagline ); ?></span>
				<?php endif; ?>
			</a>

			<?php if ( has_nav_menu( 'primary' ) ) : ?>
				<nav class="roova-cp__menu" aria-label="<?php esc_attr_e( 'Primary menu', 'roova' ); ?>">
					<?php
					wp_nav_menu(
						array(
							'theme_location' => 'primary',
							'container'      => false,
							'menu_class'     => 'roova-menu',
							'depth'          => 2,
							'fallback_cb'    => false,
						)
					);
					?>
				</nav>
			<?php endif; ?>

			<div class="roova-cp__actions">
				<?php roova_account_control(); ?>

				<?php if ( has_nav_menu( 'primary' ) ) : ?>
					<button class="roova-nav__toggle roova-cp__toggle" type="button" data-roova-nav-toggle aria-expanded="false">
						<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'roova' ); ?></span>
						<span aria-hidden="true"></span>
					</button>
				<?php endif; ?>
			</div>
		</div>
	</header>
	<?php
}

/**
 * The hero: the headline, the intro, the number to ring and the photo.
 *
 * With no photo chosen the panel is dropped and the copy runs the full width
 * rather than leaving half a navy band empty — the rule roova_image_band()
 * follows. No stock photograph ships for this page: the others in the theme are
 * rooms and receptions, and a picture of somebody else's office is not a thing
 * to put on a page that says "visit us".
 */
function roova_contact_hero() {
	/*
	 * These defaults have to match the ones registered in the Customizer:
	 * get_theme_mod() falls back to what the caller passes, not to the
	 * setting's own default.
	 */
	$title    = roova_option( 'contact_title', __( 'Talk to a real person about your stay', 'roova' ) );
	$intro    = roova_option( 'contact_intro', __( 'Our reservations desk handles booking changes, invoices, cashback and anything a hotel has not sorted out for you.', 'roova' ) );
	$image_id = (int) roova_option( 'contact_image', 0 );
	$channels = roova_contact_channels();
	$hours    = roova_contact_hours();

	// The first channel is the one worth a button — the number, when there is one.
	$lead = $channels ? $channels[0] : null;
	?>
	<section class="roova-cp__hero <?php echo $image_id ? '' : 'roova-cp__hero--plain'; ?>">
		<div class="roova-cp__hero-inner">
			<div class="roova-cp__hero-copy">
				<span class="roova-eyebrow roova-eyebrow--ruled"><?php esc_html_e( 'Contact us', 'roova' ); ?></span>

				<h1><?php echo esc_html( $title ); ?></h1>

				<?php if ( $intro ) : ?>
					<p class="roova-cp__lede"><?php echo esc_html( $intro ); ?></p>
				<?php endif; ?>

				<?php if ( $lead || $hours ) : ?>
					<div class="roova-cp__hero-actions">
						<?php if ( $lead && $lead['href'] ) : ?>
							<a class="roova-cp__call" href="<?php echo esc_url( $lead['href'] ); ?>"
								<?php echo $lead['external'] ? 'target="_blank" rel="noopener"' : ''; ?>>
								<?php roova_the_icon( $lead['icon'], 17 ); ?>
								<span><?php echo esc_html( $lead['value'] ); ?></span>
							</a>
						<?php elseif ( $lead ) : ?>
							<span class="roova-cp__call roova-cp__call--flat">
								<?php roova_the_icon( $lead['icon'], 17 ); ?>
								<span><?php echo esc_html( $lead['value'] ); ?></span>
							</span>
						<?php endif; ?>

						<?php
						/*
						 * The line beside the number is the first row of the
						 * opening hours, not a sentence of its own: one field
						 * cannot then say "open daily" while the other says the
						 * desk is shut on Sunday.
						 */
						if ( $hours ) :
							?>
							<span class="roova-cp__open">
								<span class="roova-cp__dot" aria-hidden="true"></span>
								<span class="screen-reader-text"><?php esc_html_e( 'Opening hours:', 'roova' ); ?></span>
								<?php
								echo esc_html(
									$hours[0]['time']
										? sprintf(
											/* translators: 1: days, e.g. Monday — Saturday. 2: times, e.g. 9:00 am — 6:00 pm. */
											_x( '%1$s · %2$s', 'opening hours', 'roova' ),
											$hours[0]['days'],
											$hours[0]['time']
										)
										: $hours[0]['days']
								);
								?>
							</span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( $image_id ) : ?>
				<div class="roova-cp__hero-media">
					<?php roova_background_image( $image_id, '', true ); ?>
					<div class="roova-cp__hero-scrim" aria-hidden="true"></div>
				</div>
			<?php endif; ?>
		</div>
	</section>
	<?php
}

/**
 * The three channel cards.
 *
 * A card is a link when its value can be acted on and a plain block when it
 * cannot — a number typed as "ask at the desk" has no digits to dial, and a
 * dead link is worse than plain text. The same rule roova_tel_href() exists
 * for on the hotel pages.
 */
function roova_contact_channels_row() {
	$channels = roova_contact_channels();
	if ( ! $channels ) {
		return;
	}
	?>
	<?php
	/*
	 * No data-roova-reveal on this page, unlike the homepage's sections.
	 * theme.css paints anything carrying that attribute at opacity 0 until
	 * theme.js reveals it, and on a page whose whole job is to carry a phone
	 * number, an address and a map, a script that fails to load would hide
	 * exactly the thing the visitor came for. The homepage can afford to lose
	 * an animation; this page cannot afford to lose its content.
	 */
	?>
	<div class="roova-cp__channels">
		<?php foreach ( $channels as $channel ) : ?>
			<?php
			// The card's insides, so the link and the plain block below can
			// share them without either one being written out twice.
			ob_start();
			?>
			<span class="roova-cp__channel-icon"><?php roova_the_icon( $channel['icon'], 19 ); ?></span>

			<span class="roova-cp__channel-label"><?php echo esc_html( $channel['label'] ); ?></span>
			<span class="roova-cp__channel-value"><?php echo esc_html( $channel['value'] ); ?></span>

			<?php if ( $channel['note'] ) : ?>
				<span class="roova-cp__channel-note"><?php echo esc_html( $channel['note'] ); ?></span>
			<?php endif; ?>
			<?php
			$inside = ob_get_clean();
			$class  = 'roova-cp__channel roova-cp__channel--' . $channel['key'];
			?>

			<?php if ( $channel['href'] ) : ?>
				<a class="<?php echo esc_attr( $class ); ?>" href="<?php echo esc_url( $channel['href'] ); ?>"
					<?php echo $channel['external'] ? 'target="_blank" rel="noopener"' : ''; ?>>
					<?php echo $inside; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped as it was built. ?>
				</a>
			<?php else : ?>
				<div class="<?php echo esc_attr( $class ); ?>">
					<?php echo $inside; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped as it was built. ?>
				</div>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * The office panel: the address, the opening hours and the map.
 *
 * Renders nothing at all when the client has given neither an address nor any
 * hours — an empty "Visit us" panel on every install is noise, not information.
 */
function roova_contact_office() {
	$lines = roova_contact_address_lines();
	$hours = roova_contact_hours();
	$map   = roova_contact_map_url();
	$place = roova_contact_place_url();

	/*
	 * Two maps, and which one is drawn decides how a visitor opens Google Maps.
	 * With a Maps key and a pin, the map is the real Maps JavaScript API and its
	 * red marker is what opens Google — asked for directly: the pin, and nothing
	 * else on the map. Without either, it stays the keyless embed, whose marker
	 * lives inside an <iframe> where no click can reach it, so that one carries a
	 * small "Open in Google Maps" link in the corner instead.
	 */
	$maps_key = roova_option( 'maps_api_key', '' );
	$lat      = trim( (string) roova_option( 'contact_lat', '' ) );
	$lng      = trim( (string) roova_option( 'contact_lng', '' ) );
	$live_map = $maps_key && is_numeric( $lat ) && is_numeric( $lng );

	if ( ! $lines && ! $hours ) {
		return;
	}
	?>
	<section class="roova-cp__office <?php echo $map ? '' : 'roova-cp__office--nomap'; ?>">
		<div class="roova-cp__office-copy">
			<span class="roova-cp__eyebrow"><?php esc_html_e( 'Visit us', 'roova' ); ?></span>

			<h2><?php esc_html_e( 'Where to find us', 'roova' ); ?></h2>

			<?php if ( $lines ) : ?>
				<address class="roova-cp__address">
					<?php roova_the_icon( 'pin', 18 ); ?>
					<span>
						<?php foreach ( $lines as $index => $line ) : ?>
							<?php echo $index ? '<br />' : ''; ?>
							<?php echo esc_html( $line ); ?>
						<?php endforeach; ?>
					</span>
				</address>
			<?php endif; ?>

			<?php if ( $hours ) : ?>
				<div class="roova-cp__hours">
					<?php foreach ( $hours as $row ) : ?>
						<div class="roova-cp__hours-row">
							<span><?php echo esc_html( $row['days'] ); ?></span>
							<?php if ( $row['time'] ) : ?>
								<span class="roova-cp__hours-time"><?php echo esc_html( $row['time'] ); ?></span>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $lines ) : ?>
				<div class="roova-cp__office-actions">
					<?php
					/*
					 * Hidden until assets/js/contact.js unhides it. Copying to
					 * the clipboard is the one thing on this page that cannot be
					 * done without a script, so a visitor with scripts blocked is
					 * shown no button rather than a dead one — the mirror of the
					 * search page's sort button, which is printed and then hidden
					 * once the script has taken over.
					 */
					?>
					<button class="roova-btn roova-btn--ghost roova-cp__btn roova-cp__copy" type="button"
						data-roova-copy="<?php echo esc_attr( roova_contact_address_inline() ); ?>"
						data-roova-copy-done="<?php esc_attr_e( 'Address copied', 'roova' ); ?>"
						hidden>
						<?php roova_the_icon( 'copy', 15 ); ?>
						<span data-roova-copy-label><?php esc_html_e( 'Copy address', 'roova' ); ?></span>
					</button>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $map ) : ?>
			<div class="roova-cp__map">
				<?php if ( $live_map ) : ?>
					<?php
					/*
					 * data-url is what makes the marker clickable; theme.js reads
					 * it. Everything else here is what a hotel page's map canvas
					 * carries, so one initialiser draws both.
					 */
					?>
					<div class="roova-cp__map-canvas"
						data-roova-map
						data-lat="<?php echo esc_attr( $lat ); ?>"
						data-lng="<?php echo esc_attr( $lng ); ?>"
						data-zoom="<?php echo esc_attr( max( 1, min( 21, (int) roova_option( 'contact_map_zoom', 16 ) ) ) ); ?>"
						data-title="<?php esc_attr_e( 'Open in Google Maps', 'roova' ); ?>"
						data-url="<?php echo esc_url( $place ); ?>"></div>
				<?php else : ?>
					<iframe
						title="<?php echo esc_attr( sprintf( /* translators: %s: site name */ __( '%s on Google Maps', 'roova' ), get_bloginfo( 'name' ) ) ); ?>"
						src="<?php echo esc_url( $map ); ?>"
						loading="lazy"
						referrerpolicy="no-referrer-when-downgrade"></iframe>
				<?php endif; ?>

				<?php
				/*
				 * The card over the map repeats the first two lines of the
				 * address rather than carrying words of its own. The design's
				 * "6 min walk from Jelatek LRT" is a fact about one office in
				 * Kuala Lumpur; nothing in the theme knows it for anybody else.
				 */
				if ( $lines ) :
					?>
					<div class="roova-cp__map-card" aria-hidden="true">
						<?php roova_the_icon( 'building', 17 ); ?>
						<span>
							<strong><?php echo esc_html( $lines[0] ); ?></strong>
							<?php if ( isset( $lines[1] ) ) : ?>
								<span><?php echo esc_html( $lines[1] ); ?></span>
							<?php endif; ?>
						</span>
					</div>
				<?php endif; ?>

				<?php
				/*
				 * Only for the embed: its marker is inside an <iframe>, where no
				 * click of ours can reach it, so the corner carries the link
				 * instead. The live map needs none — its red pin *is* the link,
				 * and the map around it stays draggable.
				 */
				if ( $place && ! $live_map ) :
					?>
					<a class="roova-cp__map-open" href="<?php echo esc_url( $place ); ?>" target="_blank" rel="noopener">
						<?php roova_the_icon( 'pin', 15 ); ?>
						<span><?php esc_html_e( 'Open in Google Maps', 'roova' ); ?></span>
					</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * The social row at the foot of the page.
 *
 * Nothing renders when no account has been filled in — five empty squares say
 * less than no row at all.
 */
function roova_contact_follow() {
	$socials = roova_contact_socials();
	if ( ! $socials ) {
		return;
	}
	?>
	<section class="roova-cp__follow">
		<div>
			<h2>
				<?php
				printf(
					/* translators: %s: site name */
					esc_html__( 'Follow %s', 'roova' ),
					esc_html( get_bloginfo( 'name' ) )
				);
				?>
			</h2>
			<p><?php esc_html_e( 'Deals, new destinations and the quickest answers to short questions.', 'roova' ); ?></p>
		</div>

		<div class="roova-cp__socials">
			<?php foreach ( $socials as $social ) : ?>
				<a class="roova-cp__social" href="<?php echo esc_url( $social['url'] ); ?>"
					target="_blank" rel="noopener"
					aria-label="<?php echo esc_attr( $social['label'] ); ?>"
					title="<?php echo esc_attr( $social['label'] ); ?>">
					<?php echo roova_social_icon( $social['key'], 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from the static path list above. ?>
				</a>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}
