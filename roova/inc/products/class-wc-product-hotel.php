<?php
/**
 * The Hotel product type.
 *
 * A hotel is a catalogue-only container: it is never added to the cart. Guests
 * book the Room products linked to it.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Product_Hotel
 */
class WC_Product_Hotel extends WC_Product {

	/**
	 * Product type slug.
	 *
	 * @return string
	 */
	public function get_type() {
		return 'hotel';
	}

	/**
	 * Hotels are never purchasable — only their rooms are.
	 *
	 * @return bool
	 */
	public function is_purchasable() {
		return false;
	}

	/**
	 * Always "in stock" so the hotel stays visible in the catalogue.
	 *
	 * @return bool
	 */
	public function is_in_stock() {
		return true;
	}

	/**
	 * Link to the hotel page rather than an add-to-cart action.
	 *
	 * @return string
	 */
	public function add_to_cart_url() {
		return $this->get_permalink();
	}

	/**
	 * Loop button label.
	 *
	 * @return string
	 */
	public function add_to_cart_text() {
		return __( 'View hotel', 'roova' );
	}

	/**
	 * Single product button label.
	 *
	 * @return string
	 */
	public function single_add_to_cart_text() {
		return __( 'See rooms', 'roova' );
	}

	/**
	 * The lowest nightly rate across this hotel's rooms.
	 *
	 * @return float|string Price, or '' when the hotel has no priced rooms.
	 */
	public function get_lowest_room_rate() {
		$rates = array();
		foreach ( roova_get_hotel_room_ids( $this->get_id() ) as $room_id ) {
			$rate = roova_room_rate( $room_id );
			if ( $rate > 0 ) {
				$rates[] = $rate;
			}
		}
		return $rates ? min( $rates ) : '';
	}

	/**
	 * Show "From RM120 / night" instead of a product price.
	 *
	 * @return string
	 */
	public function get_price_html() {
		$rate = $this->get_lowest_room_rate();
		if ( '' === $rate ) {
			return '';
		}

		$html = sprintf(
			/* translators: 1: "From" label, 2: formatted price, 3: per-night suffix */
			'<span class="roova-from">%1$s</span> %2$s <span class="roova-per-night">%3$s</span>',
			esc_html__( 'From', 'roova' ),
			wc_price( $rate ),
			esc_html__( '/ night', 'roova' )
		);

		return apply_filters( 'woocommerce_get_price_html', $html, $this );
	}
}
