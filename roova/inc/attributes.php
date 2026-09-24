<?php
/**
 * The global product attributes the theme runs on: Destination, Amenity, Facilities, Badge,
 * Landmark and Landmark category.
 *
 * They are real WooCommerce attributes so the site owner can add new terms from
 * Products > Attributes without touching code.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * Taxonomy name for destinations.
 *
 * @return string
 */
function roova_destination_taxonomy() {
	return 'pa_destination';
}

/**
 * Taxonomy name for amenities.
 *
 * @return string
 */
function roova_amenity_taxonomy() {
	return 'pa_amenity';
}

/**
 * Taxonomy name for facilities.
 *
 * @return string
 */
function roova_facility_taxonomy() {
	return 'pa_facilities';
}

/**
 * Taxonomy name for the badges pinned to a hotel's search-result card.
 *
 * @return string
 */
function roova_badge_taxonomy() {
	return 'pa_badge';
}

/**
 * Taxonomy name for landmarks — the places a hotel is near.
 *
 * Each term carries its own name and description, a title image, a distance
 * and the Google Maps link that opens it. See roova_landmark_details().
 *
 * @return string
 */
function roova_landmark_taxonomy() {
	return 'pa_landmark';
}

/**
 * Taxonomy name for what kind of place a landmark is.
 *
 * Its own attribute rather than a list in code, so the client can add "Night
 * market" without a developer — the rule the badges follow. The slug is the one
 * WooCommerce itself would make from the label, so an attribute a client
 * created by hand first is adopted rather than duplicated.
 *
 * @return string
 */
function roova_landmark_category_taxonomy() {
	return 'pa_landmark-category';
}

/**
 * The attributes the theme needs: slug => label.
 *
 * @return array
 */
function roova_required_attributes() {
	return array(
		'destination'       => __( 'Destination', 'roova' ),
		'amenity'           => __( 'Amenity', 'roova' ),
		'facilities'        => __( 'Facilities', 'roova' ),
		'badge'             => __( 'Badge', 'roova' ),
		'landmark'          => __( 'Landmark', 'roova' ),

		// Keyed by the slug WooCommerce derives from the label, so the
		// taxonomy is pa_landmark-category however it came to exist.
		'landmark-category' => __( 'Landmark category', 'roova' ),
	);
}

/**
 * Create the theme's attributes if they are missing.
 *
 * Safe to call repeatedly — existing attributes are left alone.
 *
 * @return void
 */
function roova_ensure_attributes() {
	if ( ! function_exists( 'wc_get_attribute_taxonomies' ) || ! function_exists( 'wc_create_attribute' ) ) {
		return;
	}

	$existing = wp_list_pluck( wc_get_attribute_taxonomies(), 'attribute_name' );
	$created  = array();

	foreach ( roova_required_attributes() as $slug => $label ) {
		if ( in_array( $slug, $existing, true ) ) {
			continue;
		}

		$result = wc_create_attribute( array(
			'name'         => $label,
			'slug'         => $slug,
			'type'         => 'select',
			'order_by'     => 'menu_order',
			'has_archives' => false,
		) );

		if ( ! is_wp_error( $result ) ) {
			$created[] = $slug;
		}
	}

	if ( $created ) {
		delete_transient( 'wc_attribute_taxonomies' );
		if ( class_exists( 'WC_Cache_Helper' ) ) {
			WC_Cache_Helper::invalidate_cache_group( 'woocommerce-attributes' );
		}

		// Register the new taxonomies for the remainder of this request so the
		// terms below can be created straight away.
		foreach ( roova_required_attributes() as $slug => $label ) {
			$taxonomy = wc_attribute_taxonomy_name( $slug );
			if ( $taxonomy && ! taxonomy_exists( $taxonomy ) ) {
				register_taxonomy(
					$taxonomy,
					array( 'product' ),
					array(
						'hierarchical' => false,
						'show_ui'      => false,
						'query_var'    => true,
						'rewrite'      => false,
						'label'        => $label,
					)
				);
			}
		}

		/*
		 * Seed the badges only for an attribute this run just created. A site
		 * that has deleted a badge on purpose should not get it back on the
		 * next release.
		 */
		if ( in_array( 'badge', $created, true ) ) {
			roova_seed_badge_terms();
		}

		// Same rule for the landmark categories: a starting vocabulary, seeded
		// once, and never put back once the site has edited it.
		if ( in_array( 'landmark-category', $created, true ) ) {
			roova_seed_landmark_category_terms();
		}

		update_option( 'roova_attributes_created', ROOVA_VERSION );
	}
}
add_action( 'after_switch_theme', 'roova_ensure_attributes' );

/**
 * Also run the check on admin load — covers sites where WooCommerce was
 * activated after the theme.
 */
function roova_maybe_ensure_attributes() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	if ( get_option( 'roova_attributes_created' ) === ROOVA_VERSION ) {
		return;
	}
	roova_ensure_attributes();
	update_option( 'roova_attributes_created', ROOVA_VERSION );
}
add_action( 'admin_init', 'roova_maybe_ensure_attributes' );

/**
 * Destination terms with the number of published hotels in each.
 *
 * @param bool $hide_empty Skip destinations that have no hotels.
 * @return array[] Each: term, count, image_id, color, url.
 */
function roova_get_destinations( $hide_empty = true ) {
	$taxonomy = roova_destination_taxonomy();
	if ( ! taxonomy_exists( $taxonomy ) ) {
		return array();
	}

	$terms = get_terms( array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
		'orderby'    => 'menu_order',
	) );

	if ( is_wp_error( $terms ) || ! $terms ) {
		return array();
	}

	$out = array();
	foreach ( $terms as $term ) {
		$count = roova_destination_hotel_count( $term->term_id );
		if ( $hide_empty && ! $count ) {
			continue;
		}
		$out[] = array(
			'term'     => $term,
			'count'    => $count,
			'image_id' => (int) get_term_meta( $term->term_id, 'roova_image_id', true ),
			'color'    => (string) get_term_meta( $term->term_id, 'roova_color', true ),
			'url'      => roova_destination_url( $term ),
		);
	}

	return $out;
}

/**
 * Fallback coordinates for the towns roova covers, lon/lat.
 *
 * A destination only needs these filled in on its term screen when it is not
 * one of the towns below — the map skips any destination it cannot place.
 *
 * @return array[] Lowercased town name => array( lon, lat ).
 */
function roova_destination_gazetteer() {
	/**
	 * Filter the built-in destination coordinates.
	 *
	 * @param array[] $places Lowercased name => array( lon, lat ).
	 */
	return apply_filters( 'roova_destination_gazetteer', array(
		'ampang'         => array( 101.760, 3.150 ),
		'kajang'         => array( 101.788, 2.993 ),
		'kota damansara' => array( 101.588, 3.152 ),
		'malacca'        => array( 102.249, 2.196 ),
		'melaka'         => array( 102.249, 2.196 ),
		'rawang'         => array( 101.577, 3.321 ),
		'subang'         => array( 101.581, 3.084 ),
		'subang jaya'    => array( 101.581, 3.084 ),
		'taman melawati' => array( 101.749, 3.212 ),
		'kuala lumpur'   => array( 101.694, 3.139 ),
		'petaling jaya'  => array( 101.606, 3.107 ),
		'shah alam'      => array( 101.532, 3.073 ),
		'cheras'         => array( 101.744, 3.098 ),
		'puchong'        => array( 101.617, 3.021 ),
		'klang'          => array( 101.443, 3.045 ),
	) );
}

/**
 * The destinations that can be pinned on the coverage map.
 *
 * Coordinates come from the destination's own term meta first, then from the
 * built-in gazetteer. Destinations with neither are left off the map rather
 * than guessed at.
 *
 * @return array[] Each: name, lon, lat, hotels, url.
 */
function roova_map_places() {
	$gazetteer = roova_destination_gazetteer();
	$places    = array();

	foreach ( roova_get_destinations() as $destination ) {
		$term = $destination['term'];
		$lat  = get_term_meta( $term->term_id, 'roova_lat', true );
		$lon  = get_term_meta( $term->term_id, 'roova_lng', true );

		if ( '' === $lat || '' === $lon ) {
			$key = strtolower( trim( $term->name ) );
			if ( ! isset( $gazetteer[ $key ] ) ) {
				continue;
			}
			$lon = $gazetteer[ $key ][0];
			$lat = $gazetteer[ $key ][1];
		}

		$places[] = array(
			'name'   => $term->name,
			'lon'    => (float) $lon,
			'lat'    => (float) $lat,
			'hotels' => (int) $destination['count'],
			'url'    => $destination['url'],
		);
	}

	/**
	 * Filter the pins on the coverage map.
	 *
	 * @param array[] $places Each: name, lon, lat, hotels, url.
	 */
	return apply_filters( 'roova_map_places', $places );
}

/**
 * Search results filtered to one destination, keeping the visitor's stay.
 *
 * @param WP_Term|string $term Destination term, or a term slug.
 * @return string
 */
function roova_destination_url( $term ) {
	$slug = is_object( $term ) ? $term->slug : (string) $term;
	$url  = roova_criteria_url(
		roova_search_url(),
		array_merge( roova_get_criteria(), array( 'destination' => $slug ) )
	);

	/*
	 * A hotel_id left in the session outranks the destination in
	 * roova_search_hotels(), so clear it explicitly — this link is always
	 * "show me every hotel in this destination".
	 */
	return add_query_arg( 'roova_hotel', 0, $url );
}

/**
 * How many published hotels sit in a destination.
 *
 * @param int $term_id Destination term ID.
 * @return int
 */
function roova_destination_hotel_count( $term_id ) {
	$term_id = absint( $term_id );
	$cache   = wp_cache_get( 'roova_dest_count_' . $term_id, 'roova' );
	if ( false !== $cache ) {
		return (int) $cache;
	}

	$query = new WP_Query( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'tax_query'      => array(
			'relation' => 'AND',
			array(
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => 'hotel',
			),
			array(
				'taxonomy' => roova_destination_taxonomy(),
				'field'    => 'term_id',
				'terms'    => $term_id,
			),
		),
	) );

	$count = (int) $query->found_posts;
	wp_cache_set( 'roova_dest_count_' . $term_id, $count, 'roova', HOUR_IN_SECONDS );

	return $count;
}

/**
 * Clear cached destination counts when a product changes.
 *
 * @param int $post_id Post ID.
 */
function roova_flush_destination_counts( $post_id ) {
	if ( 'product' !== get_post_type( $post_id ) ) {
		return;
	}
	$terms = get_the_terms( $post_id, roova_destination_taxonomy() );
	if ( is_array( $terms ) ) {
		foreach ( $terms as $term ) {
			wp_cache_delete( 'roova_dest_count_' . $term->term_id, 'roova' );
		}
	}
}
add_action( 'save_post_product', 'roova_flush_destination_counts' );
add_action( 'deleted_post', 'roova_flush_destination_counts' );

/*
 * save_post_product runs before WooCommerce writes the product's attribute
 * terms, so on its own it only clears the destination a hotel is leaving. This
 * one fires afterwards and clears the destination it just joined — otherwise
 * the homepage tile keeps yesterday's hotel count for up to an hour.
 */
add_action( 'woocommerce_update_product', 'roova_flush_destination_counts' );

/**
 * Amenity terms attached to a product.
 *
 * @param int $product_id Product ID.
 * @return WP_Term[]
 */
function roova_get_amenities( $product_id ) {
	$terms = get_the_terms( absint( $product_id ), roova_amenity_taxonomy() );
	return is_array( $terms ) ? $terms : array();
}

/**
 * Put a set of attribute terms on a product, as a real product attribute.
 *
 * The terms have to go through the product's attribute list rather than
 * wp_set_object_terms(): WooCommerce's data store rewrites attribute taxonomies
 * on save and drops the relationships for any attribute the product does not
 * carry, so terms set behind its back are deleted moments later. Going through
 * set_attributes() also keeps the Attributes tab showing the same values.
 *
 * Call this from a save hook that runs before $product->save() — for example
 * woocommerce_admin_process_product_object.
 *
 * @param WC_Product $product  Product being saved.
 * @param string     $taxonomy Attribute taxonomy, e.g. pa_amenity.
 * @param int[]      $term_ids Selected term IDs. An empty array removes the attribute.
 */
function roova_set_product_attribute_terms( $product, $taxonomy, $term_ids ) {
	if ( ! $product instanceof WC_Product || ! taxonomy_exists( $taxonomy ) ) {
		return;
	}

	$term_ids   = array_values( array_filter( array_map( 'absint', (array) $term_ids ) ) );
	$attributes = $product->get_attributes();
	$key        = sanitize_title( $taxonomy );

	if ( ! $term_ids ) {
		unset( $attributes[ $key ] );
		$product->set_attributes( $attributes );
		return;
	}

	/*
	 * Clone rather than edit in place. get_attributes() hands back the very
	 * objects the product holds, and WC_Data::set_prop() decides something
	 * changed by comparing old value to new with !== — mutating the original
	 * makes both sides the same instance, so the change is never recorded and
	 * the data store skips writing the terms.
	 */
	$attribute = isset( $attributes[ $key ] ) && $attributes[ $key ] instanceof WC_Product_Attribute
		? clone $attributes[ $key ]
		: new WC_Product_Attribute();

	$attribute->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
	$attribute->set_name( $taxonomy );
	$attribute->set_options( $term_ids );
	$attribute->set_visible( true );
	$attribute->set_variation( false );

	$attributes[ $key ] = $attribute;

	$product->set_attributes( $attributes );
}

/**
 * Facility terms attached to a product.
 *
 * Facilities are the plain "what the hotel has" checklist (free parking, laundry,
 * 24-hour room service…). Unlike amenities they carry no icon — every one is
 * shown with a tick.
 *
 * @param int $product_id Product ID.
 * @return WP_Term[]
 */
function roova_get_facilities( $product_id ) {
	$terms = get_the_terms( absint( $product_id ), roova_facility_taxonomy() );
	return is_array( $terms ) ? $terms : array();
}

/**
 * The badges shipped with a new install: slug => label.
 *
 * Seeded once, when the Badge attribute is first created — after that the list
 * belongs to the site, editable under Products → Attributes → Badge like every
 * other attribute the theme uses.
 *
 * @return array
 */
function roova_default_badges() {
	/**
	 * Filter the badges a new install starts with.
	 *
	 * @param array $badges slug => label.
	 */
	return apply_filters( 'roova_default_badges', array(
		'popular'    => __( 'Popular', 'roova' ),
		'best-sale'  => __( 'Best sale', 'roova' ),
		'best-value' => __( 'Best value', 'roova' ),
		'top-rated'  => __( 'Top rated', 'roova' ),
		'new'        => __( 'New', 'roova' ),
	) );
}

/**
 * Put the default badges in the Badge attribute.
 *
 * Only ever called for an attribute that has just been created, so it cannot
 * resurrect a badge the site deleted.
 *
 * @return void
 */
function roova_seed_badge_terms() {
	$taxonomy = roova_badge_taxonomy();
	if ( ! taxonomy_exists( $taxonomy ) ) {
		return;
	}

	$order = 0;
	foreach ( roova_default_badges() as $slug => $label ) {
		$order++;

		if ( term_exists( $slug, $taxonomy ) ) {
			continue;
		}

		$term = wp_insert_term( $label, $taxonomy, array( 'slug' => $slug ) );
		if ( is_wp_error( $term ) ) {
			continue;
		}

		// menu_order is what the attribute is sorted by, and the first badge on
		// a card is the one drawn in gold — so the order here is the order the
		// site owner sees and can rearrange.
		update_term_meta( $term['term_id'], 'order_' . $taxonomy, $order );
	}
}

/**
 * Badge terms attached to a hotel, in the attribute's own order.
 *
 * Sorted here rather than left to WooCommerce: wc_get_product_terms() is a thin
 * wrapper around wp_get_post_terms() and does nothing with an attribute's
 * ordering, so the badge drawn in gold would otherwise be whichever one sorted
 * first by chance. `order_{$taxonomy}` is the meta WooCommerce's own "Configure
 * terms" screen writes when the list is dragged into order, so ordering the
 * badges there is what decides which one leads on the card.
 *
 * @param int $product_id Product ID.
 * @return WP_Term[]
 */
function roova_get_badges( $product_id ) {
	$product_id = absint( $product_id );
	$taxonomy   = roova_badge_taxonomy();

	if ( ! $product_id || ! taxonomy_exists( $taxonomy ) ) {
		return array();
	}

	$terms = get_the_terms( $product_id, $taxonomy );
	if ( ! is_array( $terms ) || ! $terms ) {
		return array();
	}

	return roova_sort_terms_by_attribute_order( $terms, $taxonomy );
}

/**
 * Put attribute terms in the order the "Configure terms" screen was dragged into.
 *
 * `order_{$taxonomy}` is the meta that screen writes. Neither
 * wc_get_product_terms() nor get_terms() reads it for us, so anything that
 * cares about the client's ordering sorts with this — the badges, whose first
 * term is drawn in gold, and the landmark categories in their dropdown.
 *
 * @param WP_Term[] $terms    Terms to sort.
 * @param string    $taxonomy Attribute taxonomy the order meta belongs to.
 * @return WP_Term[]
 */
function roova_sort_terms_by_attribute_order( $terms, $taxonomy ) {
	usort( $terms, function ( $a, $b ) use ( $taxonomy ) {
		$left  = get_term_meta( $a->term_id, 'order_' . $taxonomy, true );
		$right = get_term_meta( $b->term_id, 'order_' . $taxonomy, true );

		// A term nobody has placed in the order goes after the ones that have.
		if ( '' === $left && '' === $right ) {
			return strcmp( $a->name, $b->name );
		}
		if ( '' === $left ) {
			return 1;
		}
		if ( '' === $right ) {
			return -1;
		}
		if ( (int) $left === (int) $right ) {
			return strcmp( $a->name, $b->name );
		}

		return ( (int) $left < (int) $right ) ? -1 : 1;
	} );

	return $terms;
}

/**
 * The units a landmark's distance can be given in: key => label.
 *
 * Two, deliberately: a landmark is either minutes down the road or a drive
 * away, and "470 m" and "20.6 km" are how the hotel pages have always read.
 *
 * @return array
 */
function roova_landmark_units() {
	/**
	 * Filter the units offered for a landmark's distance.
	 *
	 * @param array $units key => label.
	 */
	return apply_filters( 'roova_landmark_units', array(
		'km' => __( 'km', 'roova' ),
		'm'  => __( 'm', 'roova' ),
	) );
}

/**
 * A landmark's distance as it should read: "20.6 km", "470 m", or '' when the
 * term carries no distance at all.
 *
 * The number is printed as it was typed, minus any trailing zeroes — a client
 * who writes 20.60 means 20.6, and one who writes 470 does not mean 470.0.
 *
 * @param string $distance Stored distance.
 * @param string $unit     Stored unit key.
 * @return string
 */
function roova_landmark_distance_label( $distance, $unit = 'km' ) {
	$distance = trim( (string) $distance );
	if ( '' === $distance || ! is_numeric( $distance ) ) {
		return '';
	}

	$units = roova_landmark_units();
	$unit  = isset( $units[ $unit ] ) ? $unit : key( $units );

	$decimals = 0;
	if ( false !== strpos( $distance, '.' ) ) {
		$decimals = strlen( rtrim( substr( $distance, strpos( $distance, '.' ) + 1 ), '0' ) );
	}

	return number_format_i18n( (float) $distance, min( $decimals, 3 ) ) . ' ' . $units[ $unit ];
}

/**
 * Everything stored on one landmark term.
 *
 * The one door to a landmark: name and description are WordPress's own fields,
 * the rest is term meta written on the Landmark term screen.
 *
 * @param int|WP_Term $term Term or term ID.
 * @return array|null term, name, description, image_id, distance, unit, distance_label,
 *                    category, category_name, map_url.
 */
function roova_landmark_details( $term ) {
	$term = is_object( $term ) ? $term : get_term( absint( $term ), roova_landmark_taxonomy() );

	if ( ! $term instanceof WP_Term ) {
		return null;
	}

	$distance = (string) get_term_meta( $term->term_id, 'roova_distance', true );
	$unit     = (string) get_term_meta( $term->term_id, 'roova_distance_unit', true );
	$category = roova_landmark_category( $term );

	return array(
		'term'           => $term,
		'name'           => $term->name,
		'description'    => $term->description,
		'image_id'       => (int) get_term_meta( $term->term_id, 'roova_image_id', true ),
		'distance'       => $distance,
		'unit'           => $unit ? $unit : 'km',
		'distance_label' => roova_landmark_distance_label( $distance, $unit ),
		'category'       => $category,
		'category_name'  => $category ? $category->name : '',
		'map_url'        => roova_landmark_map_url( $term ),
	);
}

/**
 * Where "open in Google Maps" goes for a landmark.
 *
 * The link the client pasted on the term screen, and otherwise the landmark's
 * own name as a plain Google Maps search — never coordinates, the rule
 * roova_hotel_map_url() follows and for the same reason: a dropped pin has no
 * name, no photos and no opening hours.
 *
 * @param int|WP_Term $term Term or term ID.
 * @return string Empty when the term does not exist.
 */
function roova_landmark_map_url( $term ) {
	$term = is_object( $term ) ? $term : get_term( absint( $term ), roova_landmark_taxonomy() );

	if ( ! $term instanceof WP_Term ) {
		return '';
	}

	$link = roova_maps_link( (string) get_term_meta( $term->term_id, 'roova_map_link', true ) );

	if ( $link ) {
		return $link;
	}

	return add_query_arg(
		array(
			'api'   => 1,
			'query' => rawurlencode( $term->name ),
		),
		'https://www.google.com/maps/search/'
	);
}

/**
 * The landmark categories shipped with a new install: slug => label.
 *
 * Three, the ones the site was asked for. A starting vocabulary rather than a
 * complete one — the whole point of making this an attribute is that the client
 * adds "Night market" and "Hospital" themselves, under Products → Attributes →
 * Landmark category.
 *
 * @return array
 */
function roova_default_landmark_categories() {
	/**
	 * Filter the landmark categories a new install starts with.
	 *
	 * @param array $categories slug => label.
	 */
	return apply_filters( 'roova_default_landmark_categories', array(
		'cafe'          => __( 'Cafe', 'roova' ),
		'shopping-mall' => __( 'Shopping mall', 'roova' ),
		'restaurant'    => __( 'Restaurant', 'roova' ),
	) );
}

/**
 * Put the default categories in the Landmark category attribute.
 *
 * Only ever called for an attribute roova_ensure_attributes() has just created,
 * so it cannot resurrect a category the site deleted on purpose.
 *
 * @return void
 */
function roova_seed_landmark_category_terms() {
	$taxonomy = roova_landmark_category_taxonomy();
	if ( ! taxonomy_exists( $taxonomy ) ) {
		return;
	}

	$order = 0;
	foreach ( roova_default_landmark_categories() as $slug => $label ) {
		$order++;

		if ( term_exists( $slug, $taxonomy ) ) {
			continue;
		}

		$term = wp_insert_term( $label, $taxonomy, array( 'slug' => $slug ) );
		if ( is_wp_error( $term ) ) {
			continue;
		}

		update_term_meta( $term['term_id'], 'order_' . $taxonomy, $order );
	}
}

/**
 * Every landmark category, in the order the client dragged them into.
 *
 * Sorted here rather than in the query, for the reason roova_get_badges() is:
 * get_terms() does nothing with an attribute's own ordering, so a list left to
 * it comes back alphabetical and "Configure terms" appears to do nothing.
 *
 * @return WP_Term[]
 */
function roova_landmark_category_terms() {
	$taxonomy = roova_landmark_category_taxonomy();

	if ( ! taxonomy_exists( $taxonomy ) ) {
		return array();
	}

	$terms = get_terms( array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => false,
	) );

	if ( is_wp_error( $terms ) || ! $terms ) {
		return array();
	}

	return roova_sort_terms_by_attribute_order( $terms, $taxonomy );
}

/**
 * The category a landmark was given, if it still exists.
 *
 * Stored as the category term's ID on the landmark term. A category deleted
 * after it was chosen simply reads as no category — the landmark is not left
 * pointing at a name nobody can see any more.
 *
 * @param int|WP_Term $term Landmark term or term ID.
 * @return WP_Term|null
 */
function roova_landmark_category( $term ) {
	$term = is_object( $term ) ? $term : get_term( absint( $term ), roova_landmark_taxonomy() );

	if ( ! $term instanceof WP_Term ) {
		return null;
	}

	$category_id = (int) get_term_meta( $term->term_id, 'roova_category', true );
	if ( ! $category_id ) {
		return null;
	}

	$category = get_term( $category_id, roova_landmark_category_taxonomy() );

	return ( $category instanceof WP_Term ) ? $category : null;
}
