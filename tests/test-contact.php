<?php
/**
 * Exercises the contact page's own logic outside WordPress: which channels get
 * a card and what each one links to, how the address and the opening hours are
 * read out of their textareas, and where the map is pointed.
 *
 * Same shape as the other test scripts — a flat script with a `check()` helper
 * and WordPress stubbed down to the handful of functions the pure logic
 * touches. The Customizer is a settable array, so a "what does a fresh install
 * show?" case is one line.
 *
 * The decisions worth covering are the ones a guest notices when they are
 * wrong: a WhatsApp link with a "+" in it opens nothing, a mailto: on a typo'd
 * address bounces, and a card printed for a channel nobody filled in is a dead
 * end on the one page that exists to not be one.
 */

define( 'ABSPATH', __DIR__ );

$GLOBALS['roova_mods']    = array();
$GLOBALS['roova_options'] = array();
$GLOBALS['roova_status']  = array();

function add_action() {}

function add_filter() {}

function apply_filters( $tag, $value ) {
	return $value;
}

function do_action() {}

function __( $text ) {
	return $text;
}

function _x( $text ) {
	return $text;
}

function _n( $single, $plural, $number ) {
	return 1 === (int) $number ? $single : $plural;
}

function absint( $value ) {
	return abs( (int) $value );
}

function esc_attr( $text ) {
	return $text;
}

function esc_html( $text ) {
	return $text;
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function sanitize_text_field( $value ) {
	return trim( (string) $value );
}

function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, (array) $args );
}

function is_email( $email ) {
	return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
}

function get_theme_mod( $key, $default = '' ) {
	return array_key_exists( $key, $GLOBALS['roova_mods'] ) ? $GLOBALS['roova_mods'][ $key ] : $default;
}

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['roova_options'] ) ? $GLOBALS['roova_options'][ $key ] : $default;
}

function get_post_status( $post_id ) {
	return isset( $GLOBALS['roova_status'][ $post_id ] ) ? $GLOBALS['roova_status'][ $post_id ] : false;
}

function get_permalink( $post_id ) {
	return 'https://example.test/?p=' . (int) $post_id;
}

function home_url( $path = '/' ) {
	return 'https://example.test' . $path;
}

function current_time( $format ) {
	return gmdate( $format );
}

function get_the_terms() {
	return array();
}

function get_post_meta() {
	return '';
}

/**
 * WordPress's own add_query_arg does not encode the values it is handed —
 * build_query() passes $urlencode = false — which is why the callers in
 * inc/contact.php rawurlencode() the query themselves. The stub has to behave
 * the same way or the test would hide a double-encoded URL.
 *
 * @param array  $args Query arguments.
 * @param string $url  Base URL.
 * @return string
 */
function add_query_arg( $args, $url ) {
	$pairs = array();
	foreach ( $args as $key => $value ) {
		$pairs[] = $key . '=' . $value;
	}
	return $url . '?' . implode( '&', $pairs );
}

/**
 * The company's own name, which roova_contact_place_url() searches Google Maps
 * with so the office opens as a place rather than a pin.
 *
 * @return string
 */
function get_bloginfo( $show = 'name' ) {
	unset( $show );
	return 'Roova Test';
}

function esc_url_raw( $url, $protocols = null ) {
	$url = trim( (string) $url );

	if ( ! preg_match( '#^https?://#i', $url ) ) {
		return '';
	}

	unset( $protocols );
	return $url;
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

require __DIR__ . '/../roova/inc/helpers.php';
require __DIR__ . '/../roova/inc/contact.php';

$failures = 0;
$checks   = 0;

function check( $label, $actual, $expected ) {
	global $failures, $checks;
	$checks++;
	$ok = $actual === $expected;
	if ( ! $ok ) {
		$failures++;
		printf( "FAIL  %s\n      expected: %s\n      actual:   %s\n", $label, var_export( $expected, true ), var_export( $actual, true ) );
	} else {
		printf( "ok    %s\n", $label );
	}
}

/**
 * Set the Customizer up as the client would have left it.
 *
 * @param array $mods roova_-prefixed settings.
 */
function customize( $mods = array() ) {
	$GLOBALS['roova_mods'] = array();
	foreach ( $mods as $key => $value ) {
		$GLOBALS['roova_mods'][ 'roova_' . $key ] = $value;
	}
}

/**
 * The channel keys in the order they would be printed.
 *
 * @return string[]
 */
function channel_keys() {
	$keys = array();
	foreach ( roova_contact_channels() as $channel ) {
		$keys[] = $channel['key'];
	}
	return $keys;
}

/**
 * One channel by key, or null.
 *
 * @param string $key Channel key.
 * @return array|null
 */
function channel( $key ) {
	foreach ( roova_contact_channels() as $channel ) {
		if ( $key === $channel['key'] ) {
			return $channel;
		}
	}
	return null;
}

/* --------------------------------------------------------- a fresh install */

customize();

check( 'nothing filled in means no channel cards', roova_contact_channels(), array() );
check( 'and no address lines', roova_contact_address_lines(), array() );
check( 'and nothing to put on one line', roova_contact_address_inline(), '' );
check( 'and no map', roova_contact_map_url(), '' );
check( 'and nowhere to open on Google Maps', roova_contact_place_url(), '' );
check( 'and no social links', roova_contact_socials(), array() );

/*
 * The hours ship empty on purpose, unlike the title and the intro: those are
 * copy, and these are a fact a guest turns up on. A plausible stock set would
 * have a fresh install promising the desk is staffed at nine on Saturday before
 * anyone had said so.
 */
check( 'and no opening hours', roova_contact_hours(), array() );

/* ---------------------------------------------------------------- channels */

customize( array(
	'contact_phone'    => '+60 3-2181 6000',
	'contact_whatsapp' => '+60 12-880 6000',
	'contact_email'    => 'hello@roova.com',
) );

check( 'all three filled in, in reading order', channel_keys(), array( 'phone', 'whatsapp', 'email' ) );

$phone = channel( 'phone' );
check( 'the number is shown as it was typed', $phone['value'], '+60 3-2181 6000' );
check( 'and dialled with the spaces taken out', $phone['href'], 'tel:+60321816000' );

$whatsapp = channel( 'whatsapp' );
check( 'WhatsApp keeps the number readable', $whatsapp['value'], '+60 12-880 6000' );
// wa.me takes digits and nothing else: a "+" or a space in the path gives a
// page that cannot open a chat.
check( 'but links with digits only — no plus, no spaces', $whatsapp['href'], 'https://wa.me/60128806000' );
check( 'and opens in its own tab', $whatsapp['external'], true );

$email = channel( 'email' );
check( 'the email address links as a mailto', $email['href'], 'mailto:hello@roova.com' );
check( 'and stays in this tab', $email['external'], false );

customize( array( 'contact_phone' => 'Ask at the front desk' ) );
$phone = channel( 'phone' );
check( 'a number with no digits still shows', $phone['value'], 'Ask at the front desk' );
check( 'but is not made into a dead dial link', $phone['href'], '' );

customize( array( 'contact_email' => 'hello at roova dot com' ) );
check( 'a typo\'d address is not turned into a mailto', channel( 'email' )['href'], '' );
check( 'though the card is still printed', channel( 'email' )['value'], 'hello at roova dot com' );

customize( array( 'contact_whatsapp' => '  ' ) );
check( 'whitespace is not a channel', roova_contact_channels(), array() );

customize( array(
	'contact_email'      => 'hello@roova.com',
	'contact_email_note' => '',
) );
check( 'a cleared note leaves the card without one', channel( 'email' )['note'], '' );

/* ----------------------------------------------------------------- address */

customize( array(
	'contact_address' => "Roova Travel Sdn Bhd\nLevel 9, Tower B, 3 Towers\n\n  296 Jalan Ampang  \n50450 Kuala Lumpur",
) );

$lines = roova_contact_address_lines();
check( 'blank lines are dropped', count( $lines ), 4 );
check( 'the first line is the company', $lines[0], 'Roova Travel Sdn Bhd' );
check( 'and each line is trimmed', $lines[2], '296 Jalan Ampang' );
check(
	'on one line it reads as an address, not a run-on',
	roova_contact_address_inline(),
	'Roova Travel Sdn Bhd, Level 9, Tower B, 3 Towers, 296 Jalan Ampang, 50450 Kuala Lumpur'
);

/* ------------------------------------------------------------------- hours */

customize( array(
	'contact_hours' => "Monday — Friday | 9:00 am — 6:00 pm\nSaturday | 10:00 am — 2:00 pm\n\nClosed on public holidays",
) );

$hours = roova_contact_hours();
check( 'a row per line typed', count( $hours ), 3 );
check( 'days on the left', $hours[0]['days'], 'Monday — Friday' );
check( 'times on the right', $hours[0]['time'], '9:00 am — 6:00 pm' );
// A line with no pipe is one phrase, not a day with a missing time — which is
// how "Closed on public holidays" gets written.
check( 'a line with no pipe is all days', $hours[2]['days'], 'Closed on public holidays' );
check( 'and carries no time', $hours[2]['time'], '' );

customize( array( 'contact_hours' => "\n  \n" ) );
check( 'an emptied hours box gives no rows', roova_contact_hours(), array() );

/* --------------------------------------------------------------------- map */

customize( array( 'contact_address' => "3 Towers\nJalan Ampang" ) );
check( 'with only an address, the map goes looking for it', roova_contact_map_query(), '3 Towers, Jalan Ampang' );
check(
	'as an embed rather than the billable JavaScript API',
	false !== strpos( roova_contact_map_url(), 'output=embed' ),
	true
);
check(
	'with the address encoded into the query',
	false !== strpos( roova_contact_map_url(), 'q=3%20Towers%2C%20Jalan%20Ampang' ),
	true
);
check( 'at the stock zoom', false !== strpos( roova_contact_map_url(), 'z=16' ), true );
/*
 * Tapping the map opens the office as a *place* — by name and address, never
 * by coordinates, which would land on an unnamed pin.
 */
check(
	'tapping the map searches for the office by name and address',
	roova_contact_place_url(),
	'https://www.google.com/maps/search/?api=1&query=Roova%20Test%2C%203%20Towers%2C%20Jalan%20Ampang'
);

customize( array(
	'contact_address'  => "3 Towers\nJalan Ampang",
	'contact_map_link' => 'https://maps.app.goo.gl/67BUxgY2UywQvDKS9',
) );
check(
	'a pasted Share link wins over everything',
	roova_contact_place_url(),
	'https://maps.app.goo.gl/67BUxgY2UywQvDKS9'
);

customize( array(
	'contact_address'  => "3 Towers\nJalan Ampang",
	'contact_map_link' => 'https://example.com/not-google',
) );
check(
	'a link that is not Google Maps is ignored, not printed',
	roova_contact_place_url(),
	'https://www.google.com/maps/search/?api=1&query=Roova%20Test%2C%203%20Towers%2C%20Jalan%20Ampang'
);

customize( array(
	'contact_address'  => "3 Towers\nJalan Ampang",
	'contact_place_id' => 'ChIJ5-rvAcpJzDERfSgcL1jZeNU',
) );
check(
	'the place the Customizer search found is carried on the link',
	roova_contact_place_url(),
	'https://www.google.com/maps/search/?api=1&query=Roova%20Test%2C%203%20Towers%2C%20Jalan%20Ampang&query_place_id=ChIJ5-rvAcpJzDERfSgcL1jZeNU'
);

customize( array( 'contact_address' => "Roova Test Sdn Bhd\nJalan Ampang" ) );
check(
	'an address that already names the company is not prefixed with it again',
	roova_contact_place_url(),
	'https://www.google.com/maps/search/?api=1&query=Roova%20Test%20Sdn%20Bhd%2C%20Jalan%20Ampang'
);

customize( array(
	'contact_lat'      => '3.1637',
	'contact_lng'      => '101.7263',
	'contact_map_link' => '',
) );
check(
	'with a pin and no address, the coordinates are the last resort',
	roova_contact_place_url(),
	'https://www.google.com/maps/search/?api=1&query=3.1637%2C101.7263'
);

/* ------------------------------------------------- what counts as a Maps link */

check( 'a short Share link is a Maps link', roova_maps_link( 'https://maps.app.goo.gl/abc' ), 'https://maps.app.goo.gl/abc' );
check( 'so is a long one', roova_maps_link( 'https://www.google.com/maps/place/X/@3.1,101.7,17z' ), 'https://www.google.com/maps/place/X/@3.1,101.7,17z' );
check( 'so is a country domain', roova_maps_link( 'https://maps.google.com.my/?q=x' ), 'https://maps.google.com.my/?q=x' );
check( 'somebody else\'s site is not', roova_maps_link( 'https://evil.example.com/maps' ), '' );
check( 'nor is a look-alike host', roova_maps_link( 'https://google.com.evil.test/maps' ), '' );
check( 'nor plain words', roova_maps_link( 'ask at the desk' ), '' );
check( 'nor a script URL', roova_maps_link( 'javascript:alert(1)' ), '' );

customize( array( 'contact_address' => "3 Towers\nJalan Ampang" ) );

customize( array(
	'contact_address' => 'Somewhere on Jalan Ampang',
	'contact_lat'     => '3.1637',
	'contact_lng'     => '101.7263',
) );
// Coordinates win: they are only ever filled in because the address alone put
// the pin in the wrong place.
check( 'a pin beats the address', roova_contact_map_query(), '3.1637,101.7263' );

customize( array(
	'contact_address' => 'Jalan Ampang',
	'contact_lat'     => '3.1637',
) );
check( 'half a pin is no pin', roova_contact_map_query(), 'Jalan Ampang' );

customize( array(
	'contact_address' => 'Jalan Ampang',
	'contact_lat'     => 'north a bit',
	'contact_lng'     => 'left a bit',
) );
check( 'and neither is a pin typed in words', roova_contact_map_query(), 'Jalan Ampang' );

customize( array( 'contact_address' => 'Jalan Ampang', 'contact_map_zoom' => 99 ) );
check( 'zoom is clamped to what Google accepts', false !== strpos( roova_contact_map_url(), 'z=21' ), true );

customize( array( 'contact_address' => 'Jalan Ampang', 'contact_map_zoom' => 0 ) );
check( 'at both ends', false !== strpos( roova_contact_map_url(), 'z=1&' ), true );

customize( array( 'contact_lat' => '3.1637', 'contact_lng' => '101.7263' ) );
check( 'a pin with no address is still a map', false !== strpos( roova_contact_map_url(), 'q=3.1637%2C101.7263' ), true );

/* ------------------------------------------------------------- the socials */

customize( array(
	'social_instagram' => 'https://instagram.com/roova',
	'social_linkedin'  => 'https://linkedin.com/company/roova',
	'social_facebook'  => '   ',
) );

$socials = roova_contact_socials();
check( 'only the accounts that were filled in', count( $socials ), 2 );
// The order is the network list's, not the order the client happened to fill
// the boxes in — so the row does not reshuffle itself when one is added.
check( 'in the theme\'s own order', $socials[0]['key'], 'instagram' );
check( 'the second one too', $socials[1]['key'], 'linkedin' );
check( 'each with its name, for the label a screen reader reads', $socials[0]['label'], 'Instagram' );
check( 'and the link as typed', $socials[1]['url'], 'https://linkedin.com/company/roova' );

check( 'a network the theme draws has a mark', '' !== roova_social_icon( 'instagram' ), true );
check( 'and it is filled, not stroked — these are brand glyphs', false !== strpos( roova_social_icon( 'x' ), 'fill="currentColor"' ), true );
check( 'one it does not draws nothing', roova_social_icon( 'myspace' ), '' );
check( 'every network offered can be drawn', array_filter( array_keys( roova_social_networks() ), function ( $key ) {
	return '' === roova_social_icon( $key );
} ), array() );

/* ------------------------------------------------------------- the page id */

$GLOBALS['roova_options'] = array();
check( 'no page recorded yet', roova_contact_page_id(), 0 );
check( 'so the link falls back to the home page', roova_contact_url(), 'https://example.test/' );

$GLOBALS['roova_options']['roova_contact_page_id'] = 42;
$GLOBALS['roova_status'][42]                       = 'publish';
check( 'a published page counts', roova_contact_page_id(), 42 );
check( 'and is what the link points at', roova_contact_url(), 'https://example.test/?p=42' );

// get_permalink() builds a URL for a trashed page just as happily as for a live
// one, and that URL is a 404 — which is why the id is gated on the status.
$GLOBALS['roova_status'][42] = 'trash';
check( 'a trashed one does not', roova_contact_page_id(), 0 );
check( 'and the link goes home instead of to a 404', roova_contact_url(), 'https://example.test/' );

printf( "\n%d checks, %d failures\n", $checks, $failures );
exit( $failures > 0 ? 1 : 0 );
