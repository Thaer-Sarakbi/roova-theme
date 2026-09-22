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
 * The criteria the "Find a room" page lists hotels for.
 *
 * The dates and party come from roova_get_criteria() as everywhere else, but
 * the destination and hotel filters come from this request's URL only, never
 * from the session. The page lists every hotel unless the guest submitted a
 * destination or hotel name (or followed a destination link), so a search
 * from last week cannot quietly narrow the list when they open the page from
 * the menu.
 *
 * @return array
 */
function roova_search_page_criteria() {
	$criteria = roova_get_criteria();

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only, shareable GET search.
	$criteria['destination'] = isset( $_GET['roova_dest'] ) ? sanitize_text_field( wp_unslash( $_GET['roova_dest'] ) ) : '';
	$criteria['hotel_id']    = isset( $_GET['roova_hotel'] ) ? absint( $_GET['roova_hotel'] ) : 0;
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	return $criteria;
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

/**
 * Is this the search results page?
 *
 * The page template rather than the stored page ID, so a page the client
 * rebuilt by hand and assigned the template still counts — the same rule
 * roova_is_auth_page() follows.
 *
 * @return bool
 */
function roova_is_search_page() {
	return is_page_template( 'template-search.php' );
}

/**
 * The orders the results can be listed in: key => label.
 *
 * @return array
 */
function roova_search_sort_options() {
	return array(
		'recommended' => __( 'Recommended', 'roova' ),
		'price-low'   => __( 'Price · low to high', 'roova' ),
		'price-high'  => __( 'Price · high to low', 'roova' ),
		'rating'      => __( 'Guest rating', 'roova' ),
	);
}

/**
 * The order the visitor asked for.
 *
 * A plain query argument, like the review list's sort: the select posts the
 * page back to itself, so sorting works with JavaScript blocked and a sorted
 * list can be linked to.
 *
 * @return string
 */
function roova_search_sort() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sorts a public list; it changes nothing.
	$sort = isset( $_GET['roova_sort'] ) ? sanitize_key( wp_unslash( $_GET['roova_sort'] ) ) : 'recommended';

	return array_key_exists( $sort, roova_search_sort_options() ) ? $sort : 'recommended';
}

/**
 * Put search results in the visitor's chosen order.
 *
 * Hotels with nothing free for these dates stay at the bottom whatever the
 * sort: "price, low to high" that opens with a sold-out room is not a price
 * list, it is a disappointment in three clicks.
 *
 * @param array[] $results Results from roova_search_hotels().
 * @param string  $sort    One of roova_search_sort_options().
 * @return array[]
 */
function roova_sort_search_results( $results, $sort = '' ) {
	$sort = $sort ? $sort : roova_search_sort();

	if ( 'recommended' === $sort || count( $results ) < 2 ) {
		return $results;
	}

	usort( $results, function ( $a, $b ) use ( $sort ) {
		if ( $a['has_availability'] !== $b['has_availability'] ) {
			return $a['has_availability'] ? -1 : 1;
		}

		if ( 'rating' === $sort ) {
			$left  = function_exists( 'roova_hotel_rating' ) ? roova_hotel_rating( $a['hotel_id'] ) : 0.0;
			$right = function_exists( 'roova_hotel_rating' ) ? roova_hotel_rating( $b['hotel_id'] ) : 0.0;

			if ( $left === $right ) {
				return 0;
			}
			return ( $left > $right ) ? -1 : 1;
		}

		// A hotel with no rate has no price to compare; leave it where it is.
		if ( null === $a['rate'] || null === $b['rate'] || $a['rate'] === $b['rate'] ) {
			return 0;
		}

		if ( 'price-high' === $sort ) {
			return ( $a['rate'] > $b['rate'] ) ? -1 : 1;
		}

		return ( $a['rate'] < $b['rate'] ) ? -1 : 1;
	} );

	return $results;
}
