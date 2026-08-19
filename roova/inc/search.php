<?php
/**
 * Hotel search: matching a destination, then filtering by real availability.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * Create the search results page on activation, if it is missing.
 */
function roova_create_search_page() {
	$page_id = (int) get_option( 'roova_search_page_id' );

	if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
		return;
	}

	$existing = get_page_by_path( 'find-a-room' );
	if ( $existing ) {
		update_option( 'roova_search_page_id', $existing->ID );
		update_post_meta( $existing->ID, '_wp_page_template', 'template-search.php' );
		return;
	}

	$page_id = wp_insert_post( array(
		'post_title'   => __( 'Find a room', 'roova' ),
		'post_name'    => 'find-a-room',
		'post_status'  => 'publish',
		'post_type'    => 'page',
		'post_content' => '',
	) );

	if ( $page_id && ! is_wp_error( $page_id ) ) {
		update_post_meta( $page_id, '_wp_page_template', 'template-search.php' );
		update_option( 'roova_search_page_id', $page_id );
	}
}
add_action( 'after_switch_theme', 'roova_create_search_page' );

/**
 * Resolve whatever the visitor typed into the destination box.
 *
 * @param string $destination Raw destination value (term slug, term name or hotel name).
 * @return array array( 'term' => WP_Term|null, 'hotel_id' => int, 'text' => string ).
 */
function roova_resolve_destination( $destination ) {
	$result = array(
		'term'     => null,
		'hotel_id' => 0,
		'text'     => (string) $destination,
	);

	$destination = trim( (string) $destination );
	if ( '' === $destination ) {
		return $result;
	}

	$taxonomy = roova_destination_taxonomy();

	if ( taxonomy_exists( $taxonomy ) ) {
		$term = get_term_by( 'slug', sanitize_title( $destination ), $taxonomy );
		if ( ! $term ) {
			$term = get_term_by( 'name', $destination, $taxonomy );
		}
		if ( $term && ! is_wp_error( $term ) ) {
			$result['term'] = $term;
			return $result;
		}
	}

	// Fall back to an exact-ish hotel name match.
	$hotels = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		's'              => $destination,
		'tax_query'      => array(
			array(
				'taxonomy' => 'product_type',
				'field'    => 'slug',
				'terms'    => 'hotel',
			),
		),
	) );

	if ( $hotels ) {
		$result['hotel_id'] = (int) $hotels[0];
	}

	return $result;
}

/**
 * Hotels matching the search criteria, with their availability worked out.
 *
 * @param array $criteria Search criteria.
 * @return array[] Each: hotel_id, product, rate, has_availability, rooms_left.
 */
function roova_search_hotels( $criteria = null ) {
	$criteria = $criteria ? roova_normalise_criteria( $criteria ) : roova_get_criteria();

	$args = array();

	if ( $criteria['hotel_id'] ) {
		$args['post__in'] = array( $criteria['hotel_id'] );
	} else {
		$resolved = roova_resolve_destination( $criteria['destination'] );

		if ( $resolved['term'] ) {
			$args['tax_query'] = array(
				'relation' => 'AND',
				array(
					'taxonomy' => 'product_type',
					'field'    => 'slug',
					'terms'    => 'hotel',
				),
				array(
					'taxonomy' => roova_destination_taxonomy(),
					'field'    => 'term_id',
					'terms'    => $resolved['term']->term_id,
				),
			);
		} elseif ( $resolved['hotel_id'] ) {
			$args['post__in'] = array( $resolved['hotel_id'] );
		}
	}

	$hotel_ids = roova_get_hotel_ids( $args );
	$results   = array();

	foreach ( $hotel_ids as $hotel_id ) {
		$product = wc_get_product( $hotel_id );
		if ( ! $product ) {
			continue;
		}

		$rooms      = Roova_Availability::get_bookable_rooms( $hotel_id, $criteria );
		$rates      = array();
		$rooms_left = 0;

		foreach ( $rooms as $room ) {
			if ( $room['bookable'] && $room['fits'] ) {
				$rooms_left += (int) $room['available'];
				if ( $room['rate'] > 0 ) {
					$rates[] = $room['rate'];
				}
			}
		}

		$results[] = array(
			'hotel_id'         => $hotel_id,
			'product'          => $product,
			'rate'             => $rates ? min( $rates ) : null,
			'has_availability' => (bool) $rates,
			'rooms_left'       => $rooms_left,
			'rooms'            => $rooms,
		);
	}

	// Available hotels first, then cheapest.
	usort( $results, function ( $a, $b ) {
		if ( $a['has_availability'] !== $b['has_availability'] ) {
			return $a['has_availability'] ? -1 : 1;
		}
		if ( null === $a['rate'] || null === $b['rate'] ) {
			return 0;
		}
		if ( $a['rate'] === $b['rate'] ) {
			return 0;
		}
		return ( $a['rate'] < $b['rate'] ) ? -1 : 1;
	} );

	return $results;
}

/**
 * Suggestions for the destination box: destinations first, then hotels.
 *
 * @param string $search Search term.
 * @return array[] Each: label, sub, value, type.
 */
function roova_destination_suggestions( $search = '' ) {
	$search      = trim( (string) $search );
	$suggestions = array();

	foreach ( roova_get_destinations( false ) as $destination ) {
		$term = $destination['term'];
		if ( $search && false === stripos( $term->name, $search ) ) {
			continue;
		}
		$suggestions[] = array(
			'label' => $term->name,
			'sub'   => sprintf(
				/* translators: %d: number of hotels */
				_n( '%d hotel', '%d hotels', $destination['count'], 'roova' ),
				$destination['count']
			),
			'value' => $term->slug,
			'type'  => 'destination',
		);
	}

	$args = array( 'posts_per_page' => 8 );
	if ( $search ) {
		$args['s'] = $search;
	}

	foreach ( roova_get_hotel_ids( $args ) as $hotel_id ) {
		$suggestions[] = array(
			'label' => get_the_title( $hotel_id ),
			'sub'   => roova_hotel_location_label( $hotel_id ),
			'value' => get_the_title( $hotel_id ),
			'type'  => 'hotel',
			'id'    => $hotel_id,
		);
	}

	return $suggestions;
}
