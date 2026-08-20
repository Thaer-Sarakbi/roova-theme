<?php
/**
 * Demo content for the local test site: destinations, amenities, facilities,
 * hotels and rooms, plus images in deliberately mismatched aspect ratios so the
 * galleries can be checked against tall, wide and square uploads.
 *
 * Run with: bin/testenv-win.sh seed
 *
 * Re-running replaces the demo products; anything else on the site is left alone.
 */

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

roova_ensure_attributes();
Roova_Schema::install();
roova_create_search_page();

/**
 * Make a JPEG of a given size so galleries can be tested with mixed aspect ratios.
 */
function roova_seed_image( $label, $width, $height, $rgb ) {
	$image = imagecreatetruecolor( $width, $height );
	$back  = imagecolorallocate( $image, $rgb[0], $rgb[1], $rgb[2] );
	imagefilledrectangle( $image, 0, 0, $width, $height, $back );

	// Some banding so the letterboxing and the blurred backdrop are obvious.
	$band = imagecolorallocate( $image, min( 255, $rgb[0] + 45 ), min( 255, $rgb[1] + 45 ), min( 255, $rgb[2] + 45 ) );
	for ( $i = 0; $i < $height; $i += 90 ) {
		imagefilledrectangle( $image, 0, $i, $width, $i + 34, $band );
	}

	$text = imagecolorallocate( $image, 255, 255, 255 );
	imagestring( $image, 5, 24, 24, $label . "  {$width}x{$height}", $text );

	$uploads = wp_upload_dir();
	$name    = sanitize_title( $label ) . "-{$width}x{$height}.jpg";
	$path    = trailingslashit( $uploads['path'] ) . $name;
	imagejpeg( $image, $path, 90 );
	imagedestroy( $image );

	$id = wp_insert_attachment( array(
		'post_mime_type' => 'image/jpeg',
		'post_title'     => $label . " {$width}x{$height}",
		'post_status'    => 'inherit',
	), $path );

	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $path ) );
	update_post_meta( $id, '_wp_attachment_image_alt', $label );

	return $id;
}

/**
 * Create a term and return it.
 */
function roova_seed_term( $name, $taxonomy, $meta = array() ) {
	$term = get_term_by( 'name', $name, $taxonomy );
	if ( ! $term ) {
		$created = wp_insert_term( $name, $taxonomy );
		if ( is_wp_error( $created ) ) {
			WP_CLI::warning( "term $name: " . $created->get_error_message() );
			return null;
		}
		$term = get_term( $created['term_id'], $taxonomy );
	}
	foreach ( $meta as $key => $value ) {
		update_term_meta( $term->term_id, $key, $value );
	}
	return $term;
}

/* ---------------------------------------------------------------- Terms */

$destinations = array();
foreach ( array(
	'Coastal Borneo' => '#0c3b57',
	'Ampang'         => '#1678a8',
	'Kajang'         => '#cf9d3f',
) as $name => $color ) {
	$destinations[ $name ] = roova_seed_term( $name, 'pa_destination', array( 'roova_color' => $color ) );
}

$amenities = array();
foreach ( array(
	'Free Wi-Fi'      => 'wifi',
	'Swimming pool'   => 'pool',
	'Breakfast'       => 'breakfast',
	'Fitness center'  => 'gym',
	'Spa'             => 'spa',
	'Restaurant'      => 'restaurant',
	'Airport shuttle' => 'shuttle',
	'Free parking'    => 'parking',
	'Air conditioning' => 'ac',
	'Shower and bathtub' => 'bathtub',
) as $name => $icon ) {
	$amenities[ $name ] = roova_seed_term( $name, 'pa_amenity', array( 'roova_icon' => $icon ) );
}

// Facilities carry no icon — the theme ticks every one of them.
$facilities = array();
foreach ( array(
	'Free Wi-Fi',
	'Free parking',
	'Check-in [24-hour]',
	'Family room',
	'Luggage storage',
	'Room service [24-hour]',
	'Laundry',
	'Non-smoking rooms',
	'Daily housekeeping',
	'Air conditioning in public area',
	'Daily disinfection in all rooms',
	'Dry cleaning',
) as $name ) {
	$facilities[ $name ] = roova_seed_term( $name, 'pa_facilities' );
}

/* --------------------------------------------------------------- Images */

$images = array(
	'wide'    => roova_seed_image( 'Lobby', 1800, 700, array( 26, 74, 104 ) ),
	'tall'    => roova_seed_image( 'Bathroom', 700, 1500, array( 92, 78, 60 ) ),
	'square'  => roova_seed_image( 'Terrace', 1000, 1000, array( 40, 96, 78 ) ),
	'card'    => roova_seed_image( 'Facade', 1400, 900, array( 62, 52, 92 ) ),
	'room1'   => roova_seed_image( 'Twin room', 1200, 800, array( 120, 92, 56 ) ),
	'room2'   => roova_seed_image( 'Gold twin', 900, 1300, array( 88, 108, 128 ) ),
	'room3'   => roova_seed_image( 'Double room', 1600, 620, array( 54, 84, 74 ) ),
);

/* -------------------------------------------------------------- Helpers */

/**
 * Attach global attribute terms to a product, the way the Attributes tab does.
 */
function roova_seed_attributes( $product_id, $map ) {
	$attributes = array();
	$position   = 0;

	foreach ( $map as $taxonomy => $terms ) {
		$ids = array();
		foreach ( $terms as $term ) {
			if ( $term ) {
				$ids[] = (int) $term->term_id;
			}
		}
		if ( ! $ids ) {
			continue;
		}

		wp_set_object_terms( $product_id, $ids, $taxonomy );

		$attributes[ $taxonomy ] = array(
			'name'         => $taxonomy,
			'value'        => '',
			'position'     => $position++,
			'is_visible'   => 1,
			'is_variation' => 0,
			'is_taxonomy'  => 1,
		);
	}

	update_post_meta( $product_id, '_product_attributes', $attributes );
}

/**
 * Create a product of a Roova type.
 */
function roova_seed_product( $title, $type, $content, $meta, $image_id, $gallery = array() ) {
	$existing = get_page_by_title( $title, OBJECT, 'product' );
	if ( $existing ) {
		wp_delete_post( $existing->ID, true );
	}

	$id = wp_insert_post( array(
		'post_title'   => $title,
		'post_content' => $content,
		'post_status'  => 'publish',
		'post_type'    => 'product',
	) );

	wp_set_object_terms( $id, $type, 'product_type' );
	set_post_thumbnail( $id, $image_id );

	if ( $gallery ) {
		update_post_meta( $id, '_product_image_gallery', implode( ',', $gallery ) );
	}

	foreach ( $meta as $key => $value ) {
		update_post_meta( $id, $key, $value );
	}

	return $id;
}

/* --------------------------------------------------------------- Hotels */

$hotel_description = 'Perched above the bay, this coastal retreat pairs sea-facing rooms with a '
	. 'palm-shaded pool deck and a restaurant that leans hard on the morning catch. The old town '
	. 'markets are a ten minute walk downhill, and the airport shuttle runs on the hour. Rooms are '
	. 'quiet, air conditioned and finished in local hardwood, with blackout curtains for anyone '
	. 'arriving off a night flight.';

$hotel_id = roova_seed_product(
	'Serenity Bay Resort',
	'hotel',
	$hotel_description,
	array(
		'_roova_address'           => '18 Jalan Pantai, Kota Kinabalu, Sabah',
		'_roova_lat'               => '5.9804',
		'_roova_lng'               => '116.0735',
		'_roova_stars'             => '4',
		'_roova_score'             => '8.6',
		'_roova_score_label'       => 'Excellent',
		'_roova_review_count'      => '1284',
		'_roova_score_cleanliness' => '4.6',
		'_roova_score_location'    => '4.8',
		'_roova_score_service'     => '4.4',
		'_roova_checkin_time'      => '15:00',
		'_roova_checkout_time'     => '12:00',
		'_roova_landmarks_popular' => "Signal Hill Observatory | 1.4 km\nGaya Street Market | 900 m\nTanjung Aru Beach | 6.2 km",
		'_roova_landmarks_nearby'  => "Coastal Borneo Pier | 190 m\nWaterfront Esplanade | 450 m\nCity Mosque | 3.1 km",
		'_roova_phone'             => '+60 88 123 456',
	),
	$images['card'],
	array( $images['wide'], $images['tall'], $images['square'] )
);

roova_seed_attributes( $hotel_id, array(
	'pa_destination' => array( $destinations['Coastal Borneo'] ),
	'pa_amenity'     => array_values( array_intersect_key( $amenities, array_flip( array(
		'Free Wi-Fi', 'Swimming pool', 'Breakfast', 'Fitness center', 'Spa', 'Restaurant', 'Airport shuttle', 'Free parking',
	) ) ) ),
	'pa_facilities'  => array_values( $facilities ),
) );

// A second hotel in the same destination, so the destination link has something to filter to.
$sister_id = roova_seed_product(
	'Harbour Lights Hotel',
	'hotel',
	'A small harbour-front hotel a short walk from the ferry terminal.',
	array(
		'_roova_address'      => '4 Jalan Dermaga, Kota Kinabalu, Sabah',
		'_roova_stars'        => '3',
		'_roova_score'        => '7.9',
		'_roova_score_label'  => 'Very good',
		'_roova_review_count' => '412',
	),
	$images['square']
);

roova_seed_attributes( $sister_id, array(
	'pa_destination' => array( $destinations['Coastal Borneo'] ),
	'pa_amenity'     => array_values( array_intersect_key( $amenities, array_flip( array( 'Free Wi-Fi', 'Free parking' ) ) ) ),
	'pa_facilities'  => array_values( array_intersect_key( $facilities, array_flip( array( 'Free Wi-Fi', 'Laundry', 'Luggage storage', 'Non-smoking rooms' ) ) ) ),
) );

// A hotel in another destination, so filtering can be seen to exclude things.
$other_id = roova_seed_product(
	'Ampang Garden Suites',
	'hotel',
	'Serviced suites beside the Ampang park, ten minutes from the city centre.',
	array(
		'_roova_address' => '77 Jalan Ampang, Kuala Lumpur',
		'_roova_stars'   => '4',
	),
	$images['wide']
);

roova_seed_attributes( $other_id, array(
	'pa_destination' => array( $destinations['Ampang'] ),
) );

/* ---------------------------------------------------------------- Rooms */

$rooms = array(
	array(
		'title'   => 'Deluxe Twin Room',
		'price'   => '899',
		'image'   => $images['room1'],
		'gallery' => array( $images['tall'], $images['wide'] ),
		'meta'    => array(
			'_roova_size'         => '15 m²',
			'_roova_beds'         => '2 single beds',
			'_roova_max_adults'   => 2,
			'_roova_max_children' => 1,
			'_roova_units'        => 4,
			'_roova_min_nights'   => 1,
			'_roova_view'         => 'Sea view',
		),
		'amenities' => array( 'Free Wi-Fi', 'Shower and bathtub', 'Air conditioning' ),
	),
	array(
		'title'   => 'Deluxe Gold Twin Room',
		'price'   => '480',
		'image'   => $images['room2'],
		'gallery' => array( $images['square'] ),
		'meta'    => array(
			'_roova_size'         => '15 m²',
			'_roova_beds'         => '2 single beds',
			'_roova_max_adults'   => 2,
			'_roova_max_children' => 2,
			'_roova_units'        => 6,
			'_roova_min_nights'   => 1,
		),
		'amenities' => array( 'Free Wi-Fi', 'Shower and bathtub' ),
	),
	array(
		'title'   => 'Double Room',
		'price'   => '320',
		'image'   => $images['room3'],
		'gallery' => array( $images['tall'] ),
		'meta'    => array(
			'_roova_size'         => '20 m²/215 ft²',
			'_roova_beds'         => '1 queen bed',
			'_roova_max_adults'   => 2,
			'_roova_max_children' => 1,
			'_roova_units'        => 2,
			'_roova_min_nights'   => 1,
		),
		'amenities' => array( 'Free Wi-Fi', 'Air conditioning' ),
	),
);

foreach ( $rooms as $room ) {
	$meta = array_merge( $room['meta'], array(
		'_roova_hotel_id' => $hotel_id,
		'_regular_price'  => $room['price'],
		'_price'          => $room['price'],
		'_manage_stock'   => 'no',
		'_stock_status'   => 'instock',
		'_virtual'        => 'no',
	) );

	$room_id = roova_seed_product(
		$room['title'],
		'room',
		'A quiet room with blackout curtains, a work desk and a rain shower.',
		$meta,
		$room['image'],
		$room['gallery']
	);

	roova_seed_attributes( $room_id, array(
		'pa_amenity' => array_values( array_intersect_key( $amenities, array_flip( $room['amenities'] ) ) ),
	) );
}

// One room for the sister hotel, so it is bookable in search results.
$sister_room = roova_seed_product(
	'Harbour Double',
	'room',
	'Compact double with a harbour outlook.',
	array(
		'_roova_hotel_id'     => $sister_id,
		'_regular_price'      => '210',
		'_price'              => '210',
		'_roova_size'         => '18 m²',
		'_roova_beds'         => '1 double bed',
		'_roova_max_adults'   => 2,
		'_roova_max_children' => 0,
		'_roova_units'        => 3,
		'_manage_stock'       => 'no',
		'_stock_status'       => 'instock',
	),
	$images['card']
);

wc_delete_product_transients( $hotel_id );
wc_delete_product_transients( $sister_id );
wp_cache_flush();

WP_CLI::success( "Hotels: $hotel_id (Serenity Bay), $sister_id (Harbour Lights), $other_id (Ampang). Search page: " . get_option( 'roova_search_page_id' ) );
