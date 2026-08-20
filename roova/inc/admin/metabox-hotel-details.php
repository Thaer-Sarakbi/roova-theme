<?php
/**
 * The "Hotel Details" product data tab.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * Add the tab.
 *
 * @param array $tabs Product data tabs.
 * @return array
 */
function roova_hotel_details_tab( $tabs ) {
	$tabs['roova_hotel'] = array(
		'label'    => __( 'Hotel Details', 'roova' ),
		'target'   => 'roova_hotel_data',
		'class'    => array( 'show_if_hotel' ),
		'priority' => 21,
	);
	return $tabs;
}
add_filter( 'woocommerce_product_data_tabs', 'roova_hotel_details_tab' );

/**
 * Render the panel.
 */
function roova_hotel_details_panel() {
	global $post;

	$details = roova_get_hotel_details( $post->ID );
	?>
	<div id="roova_hotel_data" class="panel woocommerce_options_panel hidden">

		<div class="options_group">
			<p class="roova-panel-note">
				<?php esc_html_e( 'Photos come from the product image and gallery. The description on the hotel page is the product description.', 'roova' ); ?>
			</p>

			<?php
			woocommerce_wp_select( array(
				'id'      => '_roova_stars',
				'label'   => __( 'Star rating', 'roova' ),
				'value'   => $details['stars'],
				'options' => array(
					'0' => __( 'Not rated', 'roova' ),
					'1' => __( '1 star', 'roova' ),
					'2' => __( '2 stars', 'roova' ),
					'3' => __( '3 stars', 'roova' ),
					'4' => __( '4 stars', 'roova' ),
					'5' => __( '5 stars', 'roova' ),
				),
			) );

			woocommerce_wp_text_input( array(
				'id'          => '_roova_checkin_time',
				'label'       => __( 'Check-in from', 'roova' ),
				'value'       => $details['checkin_time'],
				'placeholder' => '15:00',
			) );

			woocommerce_wp_text_input( array(
				'id'          => '_roova_checkout_time',
				'label'       => __( 'Check-out by', 'roova' ),
				'value'       => $details['checkout_time'],
				'placeholder' => '12:00',
			) );

			woocommerce_wp_text_input( array(
				'id'    => '_roova_phone',
				'label' => __( 'Reception phone', 'roova' ),
				'value' => $details['phone'],
			) );
			?>
		</div>

		<div class="options_group">
			<h4 class="roova-panel-heading"><?php esc_html_e( 'Location', 'roova' ); ?></h4>

			<?php
			roova_attribute_picker(
				roova_destination_taxonomy(),
				wp_get_object_terms( $post->ID, roova_destination_taxonomy(), array( 'fields' => 'ids' ) ),
				'roova_destinations',
				__( 'Destination', 'roova' ),
				__( 'Type to search destinations…', 'roova' )
			);
			?>

			<p class="roova-panel-note">
				<?php esc_html_e( 'What guests filter by in the search box, and the link under the hotel name. The first one is shown as the hotel\'s location. Add or rename them under Products → Attributes → Destination.', 'roova' ); ?>
			</p>

			<?php
			woocommerce_wp_textarea_input( array(
				'id'          => '_roova_address',
				'label'       => __( 'Address', 'roova' ),
				'value'       => $details['address'],
				'desc_tip'    => true,
				'description' => __( 'Shown on the hotel page and used for the "Get directions" link.', 'roova' ),
			) );

			woocommerce_wp_text_input( array(
				'id'          => '_roova_lat',
				'label'       => __( 'Latitude', 'roova' ),
				'value'       => $details['lat'],
				'placeholder' => '3.0451',
				'desc_tip'    => true,
				'description' => __( 'Right-click the spot in Google Maps and copy the first number.', 'roova' ),
			) );

			woocommerce_wp_text_input( array(
				'id'          => '_roova_lng',
				'label'       => __( 'Longitude', 'roova' ),
				'value'       => $details['lng'],
				'placeholder' => '101.5860',
				'desc_tip'    => true,
				'description' => __( 'The second number from the same Google Maps coordinates.', 'roova' ),
			) );

			woocommerce_wp_text_input( array(
				'id'                => '_roova_map_zoom',
				'label'             => __( 'Map zoom', 'roova' ),
				'value'             => $details['map_zoom'],
				'type'              => 'number',
				'custom_attributes' => array( 'min' => '1', 'max' => '20', 'step' => '1' ),
			) );
			?>
		</div>

		<div class="options_group">
			<h4 class="roova-panel-heading"><?php esc_html_e( 'Guest rating', 'roova' ); ?></h4>

			<?php
			woocommerce_wp_text_input( array(
				'id'                => '_roova_score',
				'label'             => __( 'Overall score (0-5)', 'roova' ),
				'value'             => $details['score'],
				'type'              => 'number',
				'custom_attributes' => array( 'min' => '0', 'max' => '5', 'step' => '0.1' ),
			) );

			woocommerce_wp_text_input( array(
				'id'          => '_roova_score_label',
				'label'       => __( 'Score label', 'roova' ),
				'value'       => $details['score_label'],
				'placeholder' => __( 'Very good', 'roova' ),
			) );

			woocommerce_wp_text_input( array(
				'id'                => '_roova_review_count',
				'label'             => __( 'Number of reviews', 'roova' ),
				'value'             => $details['review_count'],
				'type'              => 'number',
				'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			) );

			foreach ( array(
				'_roova_score_cleanliness' => __( 'Cleanliness (0-5)', 'roova' ),
				'_roova_score_location'    => __( 'Location (0-5)', 'roova' ),
				'_roova_score_service'     => __( 'Service (0-5)', 'roova' ),
			) as $key => $label ) {
				woocommerce_wp_text_input( array(
					'id'                => $key,
					'label'             => $label,
					'value'             => $details[ str_replace( '_roova_', '', $key ) ],
					'type'              => 'number',
					'custom_attributes' => array( 'min' => '0', 'max' => '5', 'step' => '0.1' ),
				) );
			}
			?>
		</div>

		<div class="options_group">
			<h4 class="roova-panel-heading"><?php esc_html_e( 'What this hotel offers', 'roova' ); ?></h4>

			<?php
			roova_attribute_picker(
				roova_amenity_taxonomy(),
				wp_get_object_terms( $post->ID, roova_amenity_taxonomy(), array( 'fields' => 'ids' ) ),
				'roova_amenities',
				__( 'Amenities', 'roova' ),
				__( 'Type to search amenities…', 'roova' )
			);
			?>

			<p class="roova-panel-note">
				<?php esc_html_e( 'Shown with their icons in the Amenities panel on the hotel page. Add or rename them under Products → Attributes → Amenity.', 'roova' ); ?>
			</p>

			<?php
			roova_attribute_picker(
				roova_facility_taxonomy(),
				wp_get_object_terms( $post->ID, roova_facility_taxonomy(), array( 'fields' => 'ids' ) ),
				'roova_facilities',
				__( 'Facilities', 'roova' ),
				__( 'Type to search facilities…', 'roova' )
			);
			?>

			<p class="roova-panel-note">
				<?php esc_html_e( 'The ticked checklist shown above "Select your room". Add or rename them under Products → Attributes → Facilities.', 'roova' ); ?>
			</p>
		</div>

		<div class="options_group">
			<h4 class="roova-panel-heading"><?php esc_html_e( "What's nearby", 'roova' ); ?></h4>

			<?php
			woocommerce_wp_textarea_input( array(
				'id'          => '_roova_landmarks_popular',
				'label'       => __( 'Popular landmarks', 'roova' ),
				'value'       => $details['landmarks_popular'],
				'description' => __( 'One landmark per line. Add a distance after a vertical bar, for example: Petronas Twin Towers | 20.6 km', 'roova' ),
				'style'       => 'width:100%;height:160px;',
			) );

			woocommerce_wp_textarea_input( array(
				'id'          => '_roova_landmarks_nearby',
				'label'       => __( 'Nearby landmarks', 'roova' ),
				'value'       => $details['landmarks_nearby'],
				'description' => __( 'One landmark per line, same format: USJ 21 LRT Station | 470 m', 'roova' ),
				'style'       => 'width:100%;height:160px;',
			) );
			?>
		</div>

		<div class="options_group">
			<p class="roova-panel-note">
				<?php
				$rooms = roova_get_hotel_room_ids( $post->ID );
				if ( $rooms ) {
					printf(
						/* translators: %d: number of rooms */
						esc_html( _n( '%d room is linked to this hotel.', '%d rooms are linked to this hotel.', count( $rooms ), 'roova' ) ),
						count( $rooms )
					);
					echo ' ';
					foreach ( $rooms as $room_id ) {
						printf(
							'<a href="%s">%s</a> ',
							esc_url( get_edit_post_link( $room_id ) ),
							esc_html( get_the_title( $room_id ) )
						);
					}
				} else {
					esc_html_e( 'No rooms yet. Create products of type "Room (bookable)" and pick this hotel in their Room Details tab.', 'roova' );
				}
				?>
			</p>
		</div>
	</div>
	<?php
}
add_action( 'woocommerce_product_data_panels', 'roova_hotel_details_panel' );

/**
 * Save the hotel fields.
 *
 * @param WC_Product $product Product being saved.
 */
function roova_save_hotel_details( $product ) {
	// WooCommerce verifies the product nonce before this hook runs.
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	if ( ! isset( $_POST['product-type'] ) || 'hotel' !== sanitize_key( wp_unslash( $_POST['product-type'] ) ) ) {
		return;
	}

	$text_fields = array(
		'_roova_checkin_time',
		'_roova_checkout_time',
		'_roova_phone',
		'_roova_lat',
		'_roova_lng',
		'_roova_map_zoom',
		'_roova_stars',
		'_roova_score',
		'_roova_score_label',
		'_roova_review_count',
		'_roova_score_cleanliness',
		'_roova_score_location',
		'_roova_score_service',
	);

	foreach ( $text_fields as $field ) {
		$value = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		$product->update_meta_data( $field, $value );
	}

	$textarea_fields = array(
		'_roova_address',
		'_roova_landmarks_popular',
		'_roova_landmarks_nearby',
	);

	foreach ( $textarea_fields as $field ) {
		$value = isset( $_POST[ $field ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) ) : '';
		$product->update_meta_data( $field, $value );
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	/*
	 * Destination, amenities and facilities are set as real product attributes,
	 * so the Attributes tab and this panel always agree — and so WooCommerce
	 * does not drop the terms again while saving.
	 */
	roova_set_product_attribute_terms( $product, roova_destination_taxonomy(), roova_posted_attribute_terms( 'roova_destinations' ) );
	roova_set_product_attribute_terms( $product, roova_amenity_taxonomy(), roova_posted_attribute_terms( 'roova_amenities' ) );
	roova_set_product_attribute_terms( $product, roova_facility_taxonomy(), roova_posted_attribute_terms( 'roova_facilities' ) );
}
add_action( 'woocommerce_admin_process_product_object', 'roova_save_hotel_details' );
