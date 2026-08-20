<?php
/**
 * The global product attributes the theme runs on: Destination, Amenity and Facilities.
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
 * The attributes the theme needs: slug => label.
 *
 * @return array
 */
function roova_required_attributes() {
	return array(
		'destination' => __( 'Destination', 'roova' ),
		'amenity'     => __( 'Amenity', 'roova' ),
		'facilities'  => __( 'Facilities', 'roova' ),
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
	$created  = false;

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
			$created = true;
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
