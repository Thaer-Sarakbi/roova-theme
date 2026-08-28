<?php
/**
 * Cart integration: dates in, nightly maths, holds kept in step with the cart.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Roova_Cart
 */
class Roova_Cart {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add_to_cart' ), 10, 3 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_cart_item_data' ), 10, 3 );
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'after_add_to_cart' ), 20, 6 );
		add_filter( 'woocommerce_add_to_cart_redirect', array( __CLASS__, 'redirect_after_add' ), 10, 2 );

		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'set_prices' ), 20 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'item_data' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_name', array( __CLASS__, 'item_name' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_permalink', array( __CLASS__, 'item_permalink' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_quantity', array( __CLASS__, 'quantity_input' ), 10, 3 );

		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'add_order_item_meta' ), 10, 4 );

		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'revalidate_cart' ) );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( __CLASS__, 'on_quantity_update' ), 10, 3 );

		// Before WooCommerce totals the cart it has just loaded (priority 20).
		add_action( 'woocommerce_cart_loaded_from_session', array( __CLASS__, 'enforce_single_item' ), 10 );

		add_action( 'woocommerce_before_cart', array( __CLASS__, 'touch_holds' ) );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'touch_holds' ) );
	}

	/* ---------------------------------------------------------------------
	 * Reading a booking out of the request
	 * ------------------------------------------------------------------ */

	/**
	 * Build a booking payload from the add-to-cart request.
	 *
	 * @param int $room_id Room product ID.
	 * @return array|WP_Error
	 */
	public static function booking_from_request( $room_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce owns the add-to-cart request; these fields only describe the stay.
		$check_in  = isset( $_REQUEST['roova_checkin'] ) ? roova_sanitize_date( sanitize_text_field( wp_unslash( $_REQUEST['roova_checkin'] ) ) ) : '';
		$check_out = isset( $_REQUEST['roova_checkout'] ) ? roova_sanitize_date( sanitize_text_field( wp_unslash( $_REQUEST['roova_checkout'] ) ) ) : '';
		$adults    = isset( $_REQUEST['roova_adults'] ) ? absint( $_REQUEST['roova_adults'] ) : 0;
		$children  = isset( $_REQUEST['roova_children'] ) ? absint( $_REQUEST['roova_children'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! $check_in || ! $check_out ) {
			$criteria  = roova_get_criteria();
			$check_in  = $check_in ? $check_in : $criteria['check_in'];
			$check_out = $check_out ? $check_out : $criteria['check_out'];
			$adults    = $adults ? $adults : $criteria['adults'];
			$children  = $children ? $children : $criteria['children'];
		}

		$nights = roova_nights( $check_in, $check_out );
		if ( ! $nights ) {
			return new WP_Error( 'roova_invalid_dates', __( 'Please pick a check-out date after your check-in date.', 'roova' ) );
		}

		if ( $check_in < roova_today() ) {
			return new WP_Error( 'roova_past_dates', __( 'Check-in cannot be in the past.', 'roova' ) );
		}

		$details = roova_get_room_details( $room_id );

		if ( $nights < $details['min_nights'] ) {
			return new WP_Error(
				'roova_min_nights',
				/* translators: %d: minimum number of nights */
				sprintf( _n( 'This room has a %d night minimum stay.', 'This room has a %d nights minimum stay.', $details['min_nights'], 'roova' ), $details['min_nights'] )
			);
		}

		return array(
			'room_id'   => absint( $room_id ),
			'hotel_id'  => roova_get_room_hotel_id( $room_id ),
			'check_in'  => $check_in,
			'check_out' => $check_out,
			'nights'    => $nights,
			'adults'    => max( 1, $adults ),
			'children'  => max( 0, $children ),
		);
	}

	/**
	 * Find a cart line for the same room and the same dates.
	 *
	 * @param int   $room_id Room product ID.
	 * @param array $booking Booking payload.
	 * @return string Cart item key, or ''.
	 */
	public static function find_matching_cart_item( $room_id, $booking ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '';
		}

		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( empty( $item['roova_booking'] ) ) {
				continue;
			}
			if ( (int) $item['product_id'] !== (int) $room_id ) {
				continue;
			}
			if ( $item['roova_booking']['check_in'] === $booking['check_in'] && $item['roova_booking']['check_out'] === $booking['check_out'] ) {
				return $key;
			}
		}

		return '';
	}

	/**
	 * Units of a room already in the cart for a stay that overlaps the range.
	 *
	 * @param int    $room_id   Room product ID.
	 * @param string $check_in  Y-m-d.
	 * @param string $check_out Y-m-d.
	 * @return int
	 */
	public static function units_in_cart( $room_id, $check_in, $check_out ) {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0;
		}

		$units = 0;
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( empty( $item['roova_booking'] ) || (int) $item['product_id'] !== (int) $room_id ) {
				continue;
			}
			$booking = $item['roova_booking'];
			// Overlap test, same rule the availability query uses.
			if ( $booking['check_in'] < $check_out && $booking['check_out'] > $check_in ) {
				$units += (int) $item['quantity'];
			}
		}

		return $units;
	}

	/* ---------------------------------------------------------------------
	 * One booking at a time
	 * ------------------------------------------------------------------ */

	/**
	 * Does the cart hold a single line?
	 *
	 * The site sells one stay at a time: adding a room empties the cart first,
	 * so what a guest is about to pay for is always the booking they just made.
	 *
	 * @return bool
	 */
	public static function single_item_cart() {
		/**
		 * Filter whether the cart is limited to one line.
		 *
		 * @param bool $single_item True to clear the cart on every add.
		 */
		return (bool) apply_filters( 'roova_single_item_cart', true );
	}

	/**
	 * Empty the cart so a new booking starts from nothing.
	 *
	 * Lines are removed one by one rather than through `empty_cart()`: each
	 * removal fires `woocommerce_cart_item_removed`, which is what releases the
	 * hold behind it, and leaves the cart session intact for the line that is
	 * about to be added. The undo store is cleared with them — the previous
	 * booking was replaced on purpose, and restoring it would put two stays back
	 * in a cart that only ever shows one.
	 *
	 * @return int Number of lines removed.
	 */
	public static function clear_for_new_booking() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0;
		}

		$keys = array_keys( WC()->cart->get_cart() );
		if ( ! $keys ) {
			return 0;
		}

		foreach ( $keys as $key ) {
			WC()->cart->remove_cart_item( $key );
		}

		WC()->cart->set_removed_cart_contents( array() );

		return count( $keys );
	}

	/**
	 * Send the guest to checkout as soon as a room is in the cart.
	 *
	 * There is one booking in the cart and nothing to add to it, so the cart
	 * page has nothing left to decide — "Book now" goes straight to payment.
	 *
	 * WooCommerce only applies this filter when the line really went in and no
	 * error notice was raised, so a room whose hold failed at the last moment
	 * leaves the guest on the hotel page with the reason, where they can pick
	 * again. The empty-cart test covers the same case from the other side.
	 *
	 * @param string          $url            Redirect URL, '' to stay put.
	 * @param WC_Product|null $adding_to_cart Product that was added.
	 * @return string
	 */
	public static function redirect_after_add( $url, $adding_to_cart = null ) {
		/**
		 * Filter whether adding a room jumps straight to checkout.
		 *
		 * @param bool $to_checkout True to redirect to the checkout page.
		 */
		if ( ! apply_filters( 'roova_checkout_after_add', true ) ) {
			return $url;
		}

		if ( ! $adding_to_cart instanceof WC_Product || 'room' !== $adding_to_cart->get_type() ) {
			return $url;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return $url;
		}

		$checkout = wc_get_checkout_url();

		return $checkout ? $checkout : $url;
	}

	/**
	 * Keep only the newest line in a cart that arrived with several.
	 *
	 * Adding a room already clears the cart, so this is the backstop for the
	 * carts that never went through that path: a session saved before the rule
	 * existed, an "order again", or anything added programmatically.
	 *
	 * @param WC_Cart $cart Cart.
	 */
	public static function enforce_single_item( $cart = null ) {
		if ( ! self::single_item_cart() ) {
			return;
		}

		$cart = $cart ? $cart : ( function_exists( 'WC' ) ? WC()->cart : null );
		if ( ! $cart ) {
			return;
		}

		$keys = array_keys( $cart->get_cart() );
		if ( count( $keys ) < 2 ) {
			return;
		}

		// The last line is the most recent add; drop everything before it.
		array_pop( $keys );

		foreach ( $keys as $key ) {
			$cart->remove_cart_item( $key );
		}

		$cart->set_removed_cart_contents( array() );
	}

	/* ---------------------------------------------------------------------
	 * Add to cart
	 * ------------------------------------------------------------------ */

	/**
	 * Reject an add-to-cart that cannot be honoured.
	 *
	 * @param bool $passed     Current state.
	 * @param int  $product_id Product ID.
	 * @param int  $quantity   Quantity.
	 * @return bool
	 */
	public static function validate_add_to_cart( $passed, $product_id, $quantity ) {
		$product = wc_get_product( $product_id );
		if ( ! $product || 'room' !== $product->get_type() ) {
			return $passed;
		}

		// The theme's booking forms carry a nonce. WooCommerce's own add-to-cart
		// links do not, so only check it when it is there.
		if ( isset( $_POST['roova_book_nonce'] ) && ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['roova_book_nonce'] ) ), 'roova_book_room' ) ) {
			wc_add_notice( __( 'Your session expired before that booking went through. Please try again.', 'roova' ), 'error' );
			return false;
		}

		$booking = self::booking_from_request( $product_id );
		if ( is_wp_error( $booking ) ) {
			wc_add_notice( $booking->get_error_message(), 'error' );
			return false;
		}

		$quantity     = max( 1, (int) $quantity );
		$single       = self::single_item_cart();
		$existing_key = $single ? '' : self::find_matching_cart_item( $product_id, $booking );
		$existing_qty = 0;
		if ( $existing_key ) {
			$item         = WC()->cart->get_cart_item( $existing_key );
			$existing_qty = $item ? (int) $item['quantity'] : 0;
		}

		// With a single-item cart there is nothing to add to: the line that is
		// coming in is the whole cart, so it is asking for its own quantity.
		$wanted = $existing_qty + $quantity;

		if ( ! roova_room_fits( $product_id, $booking['adults'], $booking['children'], $wanted ) ) {
			$details = roova_get_room_details( $product_id );
			wc_add_notice(
				sprintf(
					/* translators: 1: adults per room, 2: children per room */
					__( 'This room takes up to %1$d adults and %2$d children per room. Please add another room or pick a bigger one.', 'roova' ),
					$details['max_adults'],
					$details['max_children']
				),
				'error'
			);
			return false;
		}

		/*
		 * The visitor's own hold on this exact line is theirs to grow — and when
		 * the cart is about to be replaced, every hold they own is theirs to take
		 * back, because all of them are seconds from being released.
		 */
		$available = Roova_Availability::available_units(
			$product_id,
			$booking['check_in'],
			$booking['check_out'],
			$single
				? array( 'exclude_session_holds' => true )
				: array( 'exclude_cart_item_key' => $existing_key )
		);

		if ( $available < $wanted ) {
			wc_add_notice(
				$available > 0
					/* translators: %d: rooms left */
					? sprintf( _n( 'Only %d room of this type is left for those dates.', 'Only %d rooms of this type are left for those dates.', $available, 'roova' ), $available )
					: __( 'That room is fully booked for the dates you picked.', 'roova' ),
				'error'
			);
			return false;
		}

		return $passed;
	}

	/**
	 * Clear the cart for the incoming booking and attach the stay to its line.
	 *
	 * This is the hook that empties the cart, rather than the validation filter
	 * above: it fires only when an item is genuinely on its way in — classic add
	 * to cart and the Store API alike — where validation also runs for things
	 * like "order again", which must not wipe the cart a guest is filling.
	 *
	 * @param array $cart_item_data Cart item data.
	 * @param int   $product_id     Product ID.
	 * @param int   $variation_id   Variation ID.
	 * @return array
	 */
	public static function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		unset( $variation_id );

		$product = wc_get_product( $product_id );
		$is_room = $product && 'room' === $product->get_type();
		$booking = $is_room ? self::booking_from_request( $product_id ) : null;

		// A stay we cannot read is not going into the cart — validation has
		// already refused it — so leave whatever is in there alone.
		if ( $is_room && is_wp_error( $booking ) ) {
			return $cart_item_data;
		}

		// Every add starts a fresh cart, whatever is being added: one line in,
		// nothing else beside it.
		if ( self::single_item_cart() && self::clear_for_new_booking() ) {
			wc_add_notice( __( 'Your cart holds one booking at a time, so what was in it has been replaced.', 'roova' ), 'notice' );
		}

		if ( $is_room ) {
			$cart_item_data['roova_booking'] = $booking;
		}

		return $cart_item_data;
	}

	/**
	 * Place or resize the hold once the line exists and its quantity is known.
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param int    $product_id    Product ID.
	 * @param int    $quantity      Quantity added.
	 * @param int    $variation_id  Variation ID.
	 * @param array  $variation     Variation data.
	 * @param array  $cart_item_data Cart item data.
	 */
	public static function after_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		unset( $quantity, $variation_id, $variation, $cart_item_data );

		$item = WC()->cart->get_cart_item( $cart_item_key );
		if ( ! $item || empty( $item['roova_booking'] ) ) {
			return;
		}

		$existing = Roova_Holds::get_by_cart_item_key( $cart_item_key );
		$result   = self::sync_hold( $cart_item_key, $item );

		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );

			if ( $existing ) {
				// The line was already in the cart; only the extra rooms failed,
				// so roll the quantity back to what is genuinely held.
				WC()->cart->set_quantity( $cart_item_key, (int) $existing['units'], false );
			} else {
				WC()->cart->remove_cart_item( $cart_item_key );
			}
		}

		unset( $product_id );
	}

	/**
	 * Create the hold for a line, or resize the existing one.
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param array  $item          Cart item.
	 * @return true|WP_Error
	 */
	public static function sync_hold( $cart_item_key, $item ) {
		$booking  = $item['roova_booking'];
		$quantity = max( 1, (int) $item['quantity'] );

		$existing = Roova_Holds::get_by_cart_item_key( $cart_item_key );

		if ( $existing ) {
			$result = Roova_Holds::update_hold_units( $cart_item_key, $quantity );
			if ( ! is_wp_error( $result ) ) {
				self::remember_hold_id( $cart_item_key, (int) $existing['id'] );
			}
			return $result;
		}

		$result = Roova_Holds::place_hold( array(
			'room_id'       => $booking['room_id'],
			'check_in'      => $booking['check_in'],
			'check_out'     => $booking['check_out'],
			'units'         => $quantity,
			'adults'        => $booking['adults'],
			'children'      => $booking['children'],
			'cart_item_key' => $cart_item_key,
			'session_id'    => roova_session_id(),
		) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		self::remember_hold_id( $cart_item_key, (int) $result );

		return true;
	}

	/**
	 * Record which booking row backs a cart line.
	 *
	 * The row ID travels with the cart into the order, so committing at
	 * checkout can find the exact hold by primary key instead of guessing from
	 * a cart item key that WooCommerce shares between guests.
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param int    $hold_id       Booking row ID.
	 */
	protected static function remember_hold_id( $cart_item_key, $hold_id ) {
		if ( ! WC()->cart || ! isset( WC()->cart->cart_contents[ $cart_item_key ] ) ) {
			return;
		}

		WC()->cart->cart_contents[ $cart_item_key ]['roova_booking']['hold_id'] = (int) $hold_id;
		WC()->cart->set_session();
	}

	/**
	 * Keep the hold in step when the quantity changes on the cart page.
	 *
	 * @param string  $cart_item_key Cart item key.
	 * @param int     $quantity      New quantity.
	 * @param WC_Cart $cart          Cart.
	 */
	public static function on_quantity_update( $cart_item_key, $quantity, $cart ) {
		$items = $cart->get_cart();
		if ( empty( $items[ $cart_item_key ]['roova_booking'] ) ) {
			return;
		}

		$result = Roova_Holds::update_hold_units( $cart_item_key, $quantity );

		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );
			$hold = Roova_Holds::get_by_cart_item_key( $cart_item_key );
			if ( $hold ) {
				// Fall back to whatever is genuinely held.
				$cart->set_quantity( $cart_item_key, (int) $hold['units'], false );
			}
		}
	}

	/**
	 * Extend the visitor's holds while they are working through checkout.
	 */
	public static function touch_holds() {
		Roova_Holds::touch_session_holds();
	}

	/* ---------------------------------------------------------------------
	 * Pricing and display
	 * ------------------------------------------------------------------ */

	/**
	 * Line price is the nightly rate times the number of nights.
	 *
	 * @param WC_Cart $cart Cart.
	 */
	public static function set_prices( $cart ) {
		foreach ( $cart->get_cart() as $item ) {
			if ( empty( $item['roova_booking'] ) || empty( $item['data'] ) ) {
				continue;
			}

			$nights = max( 1, (int) $item['roova_booking']['nights'] );

			/*
			 * Read the nightly rate from the product itself rather than from
			 * the cart's copy: this hook can fire more than once per request
			 * and multiplying an already-multiplied price would compound.
			 */
			$product = wc_get_product( $item['product_id'] );
			if ( ! $product ) {
				continue;
			}

			$rate = (float) $product->get_price( 'edit' );
			$item['data']->set_price( $rate * $nights );
		}
	}

	/**
	 * Stay details under the product name in cart, checkout and emails.
	 *
	 * @param array $data Item data.
	 * @param array $item Cart item.
	 * @return array
	 */
	public static function item_data( $data, $item ) {
		if ( empty( $item['roova_booking'] ) ) {
			return $data;
		}

		$booking  = $item['roova_booking'];
		$hotel_id = (int) $booking['hotel_id'];

		if ( $hotel_id ) {
			$data[] = array(
				'key'   => __( 'Hotel', 'roova' ),
				'value' => get_the_title( $hotel_id ),
			);
		}

		$data[] = array(
			'key'   => __( 'Check in', 'roova' ),
			'value' => roova_format_date( $booking['check_in'] ),
		);
		$data[] = array(
			'key'   => __( 'Check out', 'roova' ),
			'value' => roova_format_date( $booking['check_out'] ),
		);
		$data[] = array(
			'key'   => __( 'Nights', 'roova' ),
			'value' => (int) $booking['nights'],
		);
		$data[] = array(
			'key'   => __( 'Guests', 'roova' ),
			'value' => self::guests_label( $booking['adults'], $booking['children'] ),
		);

		return $data;
	}

	/**
	 * "2 adults, 1 child" style label.
	 *
	 * @param int $adults   Adults.
	 * @param int $children Children.
	 * @return string
	 */
	public static function guests_label( $adults, $children ) {
		$adults   = (int) $adults;
		$children = (int) $children;

		/* translators: %d: number of adults */
		$label = sprintf( _n( '%d adult', '%d adults', $adults, 'roova' ), $adults );

		if ( $children > 0 ) {
			/* translators: %d: number of children */
			$label .= ', ' . sprintf( _n( '%d child', '%d children', $children, 'roova' ), $children );
		}

		return $label;
	}

	/**
	 * Show the room under its hotel's name in the cart.
	 *
	 * @param string $name          Product name markup.
	 * @param array  $item          Cart item.
	 * @param string $cart_item_key Cart item key.
	 * @return string
	 */
	public static function item_name( $name, $item, $cart_item_key ) {
		unset( $cart_item_key );

		if ( empty( $item['roova_booking'] ) ) {
			return $name;
		}

		$hotel_id = (int) $item['roova_booking']['hotel_id'];
		if ( ! $hotel_id ) {
			return $name;
		}

		return $name . '<span class="roova-cart-hotel">' . esc_html( get_the_title( $hotel_id ) ) . '</span>';
	}

	/**
	 * Link cart lines to the hotel page, where the room can be re-booked.
	 *
	 * @param string $permalink Permalink.
	 * @param array  $item      Cart item.
	 * @param string $key       Cart item key.
	 * @return string
	 */
	public static function item_permalink( $permalink, $item, $key ) {
		unset( $key );

		if ( empty( $item['roova_booking']['hotel_id'] ) ) {
			return $permalink;
		}

		return get_permalink( (int) $item['roova_booking']['hotel_id'] );
	}

	/**
	 * Cap the quantity selector at what is actually available.
	 *
	 * @param string $html          Quantity input markup.
	 * @param string $cart_item_key Cart item key.
	 * @param array  $item          Cart item.
	 * @return string
	 */
	public static function quantity_input( $html, $cart_item_key, $item ) {
		if ( empty( $item['roova_booking'] ) ) {
			return $html;
		}

		$booking   = $item['roova_booking'];
		$available = Roova_Availability::available_units(
			$booking['room_id'],
			$booking['check_in'],
			$booking['check_out'],
			array( 'exclude_cart_item_key' => $cart_item_key )
		);

		return woocommerce_quantity_input(
			array(
				'input_name'  => "cart[{$cart_item_key}][qty]",
				'input_value' => $item['quantity'],
				'max_value'   => max( 1, $available ),
				'min_value'   => 1,
				'product_name' => $item['data'] ? $item['data']->get_name() : '',
			),
			$item['data'],
			false
		);
	}

	/* ---------------------------------------------------------------------
	 * Validation on the way to checkout
	 * ------------------------------------------------------------------ */

	/**
	 * Re-check every booking line whenever the cart or checkout is viewed.
	 */
	public static function revalidate_cart() {
		if ( ! WC()->cart ) {
			return;
		}

		foreach ( WC()->cart->get_cart() as $cart_item_key => $item ) {
			if ( empty( $item['roova_booking'] ) ) {
				continue;
			}

			$booking = $item['roova_booking'];
			$product = wc_get_product( $booking['room_id'] );

			if ( ! $product || 'publish' !== $product->get_status() ) {
				wc_add_notice( __( 'A room in your cart is no longer available and has been removed.', 'roova' ), 'error' );
				WC()->cart->remove_cart_item( $cart_item_key );
				continue;
			}

			if ( $booking['check_in'] < roova_today() ) {
				wc_add_notice(
					sprintf(
						/* translators: %s: room name */
						__( 'The stay for %s has passed. Please choose new dates.', 'roova' ),
						$product->get_name()
					),
					'error'
				);
				WC()->cart->remove_cart_item( $cart_item_key );
				continue;
			}

			// Make sure a hold still exists — it may have expired while the
			// cart sat idle.
			$hold = Roova_Holds::get_by_cart_item_key( $cart_item_key );
			if ( ! $hold ) {
				$result = self::sync_hold( $cart_item_key, $item );
				if ( is_wp_error( $result ) ) {
					wc_add_notice(
						sprintf(
							/* translators: 1: room name, 2: reason */
							__( '%1$s: %2$s', 'roova' ),
							$product->get_name(),
							$result->get_error_message()
						),
						'error'
					);
				}
				continue;
			}

			$available = Roova_Availability::available_units(
				$booking['room_id'],
				$booking['check_in'],
				$booking['check_out'],
				array( 'exclude_ids' => array( (int) $hold['id'] ) )
			);

			if ( $available < (int) $item['quantity'] ) {
				wc_add_notice(
					sprintf(
						/* translators: 1: room name, 2: rooms available */
						__( '%1$s no longer has enough rooms free for those dates — %2$d left.', 'roova' ),
						$product->get_name(),
						max( 0, $available )
					),
					'error'
				);
			}
		}
	}

	/* ---------------------------------------------------------------------
	 * Order line items
	 * ------------------------------------------------------------------ */

	/**
	 * Copy the stay onto the order line item so it shows in admin and emails.
	 *
	 * @param WC_Order_Item_Product $line_item Line item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array                 $values    Cart item.
	 * @param WC_Order              $order     Order.
	 */
	public static function add_order_item_meta( $line_item, $cart_item_key, $values, $order ) {
		if ( empty( $values['roova_booking'] ) ) {
			return;
		}

		$booking = $values['roova_booking'];

		$line_item->add_meta_data( __( 'Check in', 'roova' ), roova_format_date( $booking['check_in'] ), true );
		$line_item->add_meta_data( __( 'Check out', 'roova' ), roova_format_date( $booking['check_out'] ), true );
		$line_item->add_meta_data( __( 'Nights', 'roova' ), (int) $booking['nights'], true );
		$line_item->add_meta_data( __( 'Guests', 'roova' ), self::guests_label( $booking['adults'], $booking['children'] ), true );

		if ( ! empty( $booking['hotel_id'] ) ) {
			$line_item->add_meta_data( __( 'Hotel', 'roova' ), get_the_title( (int) $booking['hotel_id'] ), true );
		}

		// Hidden machine-readable copies for the bookings screen and the commit.
		$line_item->add_meta_data( '_roova_booking', $booking, true );
		$line_item->add_meta_data( '_roova_cart_item_key', $cart_item_key, true );

		if ( ! empty( $booking['hold_id'] ) ) {
			$line_item->add_meta_data( '_roova_hold_id', (int) $booking['hold_id'], true );
		}

		unset( $order );
	}
}
