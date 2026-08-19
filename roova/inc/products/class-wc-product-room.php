<?php
/**
 * The Room product type.
 *
 * A room is priced per night and belongs to one hotel. Availability is not
 * WooCommerce stock — it is date based and lives in the bookings table — so
 * stock management is deliberately bypassed.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Product_Room
 */
class WC_Product_Room extends WC_Product {

	/**
	 * Product type slug.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'room';
	}

	/**
	 * Rooms are bookable when they have a rate and belong to a hotel.
	 *
	 * @return bool
	 */
	public function is_purchasable() {
		$purchasable = '' !== $this->get_price() && 'publish' === $this->get_status();
		return apply_filters( 'woocommerce_is_purchasable', $purchasable, $this );
	}

	/**
	 * Stock is per date, so the product itself is in stock while it has units.
	 *
	 * @return bool
	 */
	public function is_in_stock() {
		$details = roova_get_room_details( $this->get_id() );
		return $details['units'] > 0;
	}

	/**
	 * Never let WooCommerce reduce a permanent stock figure for a room.
	 *
	 * @return bool
	 */
	public function managing_stock() {
		return false;
	}

	/**
	 * Booking needs dates, so the loop button links to the hotel page.
	 *
	 * @return string
	 */
	public function add_to_cart_url() {
		$hotel_id = roova_get_room_hotel_id( $this->get_id() );
		return $hotel_id ? get_permalink( $hotel_id ) : $this->get_permalink();
	}

	/**
	 * Loop button label.
	 *
	 * @return string
	 */
	public function add_to_cart_text() {
		return __( 'Book now', 'roova' );
	}

	/**
	 * Single product button label.
	 *
	 * @return string
	 */
	public function single_add_to_cart_text() {
		return __( 'Book now', 'roova' );
	}

	/**
	 * Number of identical units of this room type.
	 *
	 * @return int
	 */
	public function get_units() {
		$details = roova_get_room_details( $this->get_id() );
		return (int) $details['units'];
	}

	/**
	 * Price display with the per-night suffix.
	 *
	 * @param string $deprecated Unused, kept to match WC_Product's signature.
	 * @return string
	 */
	public function get_price_html( $deprecated = '' ) {
		if ( '' === $this->get_price() ) {
			return '';
		}

		if ( $this->is_on_sale() ) {
			$price = wc_format_sale_price( wc_get_price_to_display( $this, array( 'price' => $this->get_regular_price() ) ), wc_get_price_to_display( $this ) );
		} else {
			$price = wc_price( wc_get_price_to_display( $this ) );
		}

		$html = $price . ' <span class="roova-per-night">' . esc_html__( '/ night', 'roova' ) . '</span>';

		return apply_filters( 'woocommerce_get_price_html', $html, $this );
	}
}
