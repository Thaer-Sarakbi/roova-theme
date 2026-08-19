<?php
/**
 * The "Room Details" product data tab.
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
function roova_room_details_tab( $tabs ) {
	$tabs['roova_room'] = array(
		'label'    => __( 'Room Details', 'roova' ),
		'target'   => 'roova_room_data',
		'class'    => array( 'show_if_room' ),
		'priority' => 21,
	);
	return $tabs;
}
add_filter( 'woocommerce_product_data_tabs', 'roova_room_details_tab' );

/**
 * Render the panel.
 */
function roova_room_details_panel() {
	global $post;

	$details = roova_get_room_details( $post->ID );

	$hotel_options = array( '' => __( '— Select a hotel —', 'roova' ) );
	foreach ( roova_get_hotel_ids() as $hotel_id ) {
		$hotel_options[ $hotel_id ] = get_the_title( $hotel_id );
	}
	?>
	<div id="roova_room_data" class="panel woocommerce_options_panel hidden">

		<div class="options_group">
			<?php
			woocommerce_wp_select( array(
				'id'          => '_roova_hotel_id',
				'label'       => __( 'Hotel', 'roova' ),
				'value'       => $details['hotel_id'] ? (string) $details['hotel_id'] : '',
				'options'     => $hotel_options,
				'desc_tip'    => true,
				'description' => __( 'The hotel this room belongs to. Rooms only appear on their hotel page.', 'roova' ),
			) );

			woocommerce_wp_text_input( array(
				'id'                => '_roova_units',
				'label'             => __( 'Units of this room type', 'roova' ),
				'value'             => $details['units'],
				'type'              => 'number',
				'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
				'desc_tip'          => true,
				'description'       => __( 'How many identical rooms of this type the hotel has. This is what stops double bookings: the room can be booked until every unit is taken on one of the requested nights.', 'roova' ),
			) );

			woocommerce_wp_text_input( array(
				'id'                => '_roova_min_nights',
				'label'             => __( 'Minimum nights', 'roova' ),
				'value'             => $details['min_nights'],
				'type'              => 'number',
				'custom_attributes' => array( 'min' => '1', 'step' => '1' ),
			) );
			?>
			<p class="roova-panel-note">
				<?php esc_html_e( 'The price in the General tab is the rate for one room for one night. Totals are that rate multiplied by the number of nights.', 'roova' ); ?>
			</p>
		</div>

		<div class="options_group">
			<h4 class="roova-panel-heading"><?php esc_html_e( 'Occupancy', 'roova' ); ?></h4>

			<?php
			woocommerce_wp_text_input( array(
				'id'                => '_roova_max_adults',
				'label'             => __( 'Max adults per room', 'roova' ),
				'value'             => $details['max_adults'],
				'type'              => 'number',
				'custom_attributes' => array( 'min' => '1', 'step' => '1' ),
			) );

			woocommerce_wp_text_input( array(
				'id'                => '_roova_max_children',
				'label'             => __( 'Max children per room', 'roova' ),
				'value'             => $details['max_children'],
				'type'              => 'number',
				'custom_attributes' => array( 'min' => '0', 'step' => '1' ),
			) );
			?>
		</div>

		<div class="options_group">
			<h4 class="roova-panel-heading"><?php esc_html_e( 'Room facts', 'roova' ); ?></h4>

			<?php
			woocommerce_wp_text_input( array(
				'id'          => '_roova_size',
				'label'       => __( 'Size', 'roova' ),
				'value'       => $details['size'],
				'placeholder' => '15 m²/161 ft²',
			) );

			woocommerce_wp_text_input( array(
				'id'          => '_roova_beds',
				'label'       => __( 'Beds', 'roova' ),
				'value'       => $details['beds'],
				'placeholder' => __( '1 single bed and 1 double bed', 'roova' ),
			) );

			woocommerce_wp_text_input( array(
				'id'          => '_roova_view',
				'label'       => __( 'View', 'roova' ),
				'value'       => $details['view'],
				'placeholder' => __( 'City view', 'roova' ),
			) );
			?>

			<p class="roova-panel-note">
				<?php esc_html_e( 'Room amenities come from the Amenity attribute in the Attributes tab. Add new amenities under Products → Attributes → Amenity, where each one can be given an icon.', 'roova' ); ?>
			</p>
		</div>
	</div>
	<?php
}
add_action( 'woocommerce_product_data_panels', 'roova_room_details_panel' );

/**
 * Save the room fields.
 *
 * @param WC_Product $product Product being saved.
 */
function roova_save_room_details( $product ) {
	// WooCommerce verifies the product nonce before this hook runs.
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	if ( ! isset( $_POST['product-type'] ) || 'room' !== sanitize_key( wp_unslash( $_POST['product-type'] ) ) ) {
		return;
	}

	$fields = array(
		'_roova_hotel_id'     => 'absint',
		'_roova_units'        => 'absint',
		'_roova_min_nights'   => 'absint',
		'_roova_max_adults'   => 'absint',
		'_roova_max_children' => 'absint',
		'_roova_size'         => 'sanitize_text_field',
		'_roova_beds'         => 'sanitize_text_field',
		'_roova_view'         => 'sanitize_text_field',
	);

	foreach ( $fields as $field => $sanitizer ) {
		if ( ! isset( $_POST[ $field ] ) ) {
			continue;
		}
		$raw   = wp_unslash( $_POST[ $field ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised on the next line.
		$value = call_user_func( $sanitizer, $raw );
		$product->update_meta_data( $field, $value );
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	// Rooms never use WooCommerce stock — availability is per date.
	$product->set_manage_stock( false );
	$product->set_stock_status( 'instock' );
}
add_action( 'woocommerce_admin_process_product_object', 'roova_save_room_details' );

/**
 * Warn when a room has no hotel, since it will not appear anywhere.
 */
function roova_room_missing_hotel_notice() {
	$screen = get_current_screen();
	if ( ! $screen || 'product' !== $screen->id ) {
		return;
	}

	$product = wc_get_product( get_the_ID() );
	if ( ! $product || 'room' !== $product->get_type() ) {
		return;
	}

	if ( ! roova_get_room_hotel_id( $product->get_id() ) ) {
		echo '<div class="notice notice-warning"><p>' .
			esc_html__( 'This room is not linked to a hotel yet, so guests cannot find it. Pick one in the Room Details tab.', 'roova' ) .
			'</p></div>';
	}
}
add_action( 'admin_notices', 'roova_room_missing_hotel_notice' );
