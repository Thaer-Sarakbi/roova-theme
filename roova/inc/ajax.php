<?php
/**
 * AJAX endpoints for the search box, the calendar and the room modal.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reject a request with a bad or missing nonce.
 */
function roova_check_ajax_nonce() {
	check_ajax_referer( 'roova_ajax', 'nonce' );
}

/**
 * Destination and hotel suggestions for the search box.
 */
function roova_ajax_suggestions() {
	roova_check_ajax_nonce();

	$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

	wp_send_json_success( array( 'items' => roova_destination_suggestions( $search ) ) );
}
add_action( 'wp_ajax_roova_suggestions', 'roova_ajax_suggestions' );
add_action( 'wp_ajax_nopriv_roova_suggestions', 'roova_ajax_suggestions' );

/**
 * Nights that are sold out, so the calendar can grey them out.
 *
 * Accepts either a hotel (every room full) or a single room.
 */
function roova_ajax_unavailable_dates() {
	roova_check_ajax_nonce();

	$hotel_id = isset( $_POST['hotel_id'] ) ? absint( $_POST['hotel_id'] ) : 0;
	$room_id  = isset( $_POST['room_id'] ) ? absint( $_POST['room_id'] ) : 0;
	$from     = isset( $_POST['from'] ) ? roova_sanitize_date( sanitize_text_field( wp_unslash( $_POST['from'] ) ) ) : '';
	$months   = isset( $_POST['months'] ) ? max( 1, min( 6, absint( $_POST['months'] ) ) ) : 3;

	if ( ! $from ) {
		$from = roova_today();
	}

	$to = gmdate( 'Y-m-d', strtotime( $from . ' +' . $months . ' months' ) );

	if ( $room_id ) {
		$dates = Roova_Availability::full_nights( $room_id, $from, $to );
	} elseif ( $hotel_id ) {
		$dates = Roova_Availability::hotel_full_nights( $hotel_id, $from, $to );
	} else {
		$dates = array();
	}

	wp_send_json_success( array(
		'from'  => $from,
		'to'    => $to,
		'dates' => array_values( $dates ),
	) );
}
add_action( 'wp_ajax_roova_unavailable_dates', 'roova_ajax_unavailable_dates' );
add_action( 'wp_ajax_nopriv_roova_unavailable_dates', 'roova_ajax_unavailable_dates' );

/**
 * Photos and details for the room modal.
 */
function roova_ajax_room_details() {
	roova_check_ajax_nonce();

	$room_id = isset( $_POST['room_id'] ) ? absint( $_POST['room_id'] ) : 0;
	$product = $room_id ? wc_get_product( $room_id ) : null;

	if ( ! $product || 'room' !== $product->get_type() ) {
		wp_send_json_error( array( 'message' => __( 'Room not found.', 'roova' ) ), 404 );
	}

	$details = roova_get_room_details( $room_id );

	$image_ids = $product->get_gallery_image_ids();
	if ( $product->get_image_id() ) {
		array_unshift( $image_ids, $product->get_image_id() );
	}

	$images = array();
	foreach ( $image_ids as $image_id ) {
		$src = wp_get_attachment_image_src( $image_id, 'large' );
		if ( $src ) {
			$images[] = array(
				'src' => $src[0],
				'alt' => get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
			);
		}
	}

	$amenities = array();
	foreach ( roova_get_amenities( $room_id ) as $term ) {
		$amenities[] = array(
			'label' => $term->name,
			'icon'  => roova_amenity_icon( $term, 16 ),
		);
	}

	wp_send_json_success( array(
		'id'          => $room_id,
		'name'        => $product->get_name(),
		'description' => wp_kses_post( wpautop( $product->get_description() ) ),
		'size'        => $details['size'],
		'beds'        => $details['beds'],
		'max_adults'  => sprintf(
			/* translators: %d: number of adults */
			_n( 'Max %d adult', 'Max %d adults', $details['max_adults'], 'roova' ),
			$details['max_adults']
		),
		'images'      => $images,
		'amenities'   => $amenities,
		'price_html'  => $product->get_price_html(),
	) );
}
add_action( 'wp_ajax_roova_room_details', 'roova_ajax_room_details' );
add_action( 'wp_ajax_nopriv_roova_room_details', 'roova_ajax_room_details' );
