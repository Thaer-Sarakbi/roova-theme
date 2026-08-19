<?php
/**
 * Cart / Checkout Blocks support.
 *
 * The Store API already renders whatever `woocommerce_get_item_data` returns,
 * so stay details show up in the block cart for free. This class adds the same
 * data in machine-readable form for anyone customising the blocks, and blocks
 * checkout when a stay is no longer available.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Roova_Blocks
 */
class Roova_Blocks {

	/**
	 * Namespace used for extended Store API data.
	 */
	const NAMESPACE_KEY = 'roova';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'register_endpoint_data' ) );
	}

	/**
	 * Expose the booking on cart items in the Store API response.
	 */
	public static function register_endpoint_data() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema::IDENTIFIER,
				'namespace'       => self::NAMESPACE_KEY,
				'data_callback'   => array( __CLASS__, 'cart_item_data' ),
				'schema_callback' => array( __CLASS__, 'cart_item_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * Booking payload for a cart item.
	 *
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public static function cart_item_data( $cart_item ) {
		if ( empty( $cart_item['roova_booking'] ) ) {
			return array();
		}

		$booking = $cart_item['roova_booking'];

		return array(
			'hotel'     => $booking['hotel_id'] ? get_the_title( (int) $booking['hotel_id'] ) : '',
			'check_in'  => roova_format_date( $booking['check_in'] ),
			'check_out' => roova_format_date( $booking['check_out'] ),
			'nights'    => (int) $booking['nights'],
			'guests'    => Roova_Cart::guests_label( $booking['adults'], $booking['children'] ),
		);
	}

	/**
	 * Schema for the data above.
	 *
	 * @return array
	 */
	public static function cart_item_schema() {
		return array(
			'hotel'     => array(
				'description' => __( 'Hotel the room belongs to.', 'roova' ),
				'type'        => 'string',
				'readonly'    => true,
			),
			'check_in'  => array(
				'description' => __( 'Check-in date.', 'roova' ),
				'type'        => 'string',
				'readonly'    => true,
			),
			'check_out' => array(
				'description' => __( 'Check-out date.', 'roova' ),
				'type'        => 'string',
				'readonly'    => true,
			),
			'nights'    => array(
				'description' => __( 'Number of nights.', 'roova' ),
				'type'        => 'integer',
				'readonly'    => true,
			),
			'guests'    => array(
				'description' => __( 'Guests staying.', 'roova' ),
				'type'        => 'string',
				'readonly'    => true,
			),
		);
	}
}
