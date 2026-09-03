<?php
/**
 * View order: one booking, in its own document.
 *
 * WooCommerce's `view-order` endpoint keeps its URL, its login gate and its
 * ownership check — none of that is reimplemented here. What the theme replaces
 * is what the endpoint *draws*: `roova_view_order_template()` sends it to
 * `view-order.php`, which prints its own document for the reason checkout, the
 * two auth pages and the account dashboard do — the design's whole header is a
 * wordmark and a way back, and nothing on the page should lead anywhere but
 * further into the booking.
 *
 * This is the one endpoint under My account the theme takes over. Every other
 * one — edit-address, payment-methods, lost-password, customer-logout — is left
 * to WooCommerce inside header.php/footer.php, which is what keeps the parts
 * that have to work while signed out working. `roova_is_account_dashboard()` is
 * still false here, so the dashboard's own assets and form handlers stay off.
 *
 * Everything on the page is read from the order at render time: the stay from
 * the line's `_roova_booking` meta, the totals from the order's own totals, the
 * customer from the order's billing fields, the payment from the gateway that
 * took it. Nothing is transcribed from the design handoff.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * Routing
 * ---------------------------------------------------------------------- */

/**
 * The order this page is showing, or null when this is not that page.
 *
 * WooCommerce puts the order ID in the endpoint's query var and has already
 * decided whether this visitor may see it. The customer check below is the same
 * one its own template runs, so a guessed ID gets nothing.
 *
 * @return WC_Order|null
 */
function roova_view_order() {
	static $cache = false;

	if ( false !== $cache ) {
		return $cache;
	}

	$cache = null;

	if ( ! function_exists( 'is_account_page' ) || ! is_account_page() || ! is_user_logged_in() ) {
		return $cache;
	}

	if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'view-order' ) ) {
		return $cache;
	}

	$order_id = absint( get_query_var( 'view-order' ) );
	$order    = $order_id ? wc_get_order( $order_id ) : false;

	if ( $order instanceof WC_Order && (int) $order->get_user_id() === get_current_user_id() ) {
		$cache = $order;
	}

	return $cache;
}

/**
 * Is this the view-order page, drawn by the theme?
 *
 * @return bool
 */
function roova_is_view_order() {
	if ( ! roova_view_order() ) {
		return false;
	}

	/**
	 * Filter whether the theme takes over WooCommerce's view-order endpoint.
	 *
	 * Off sends the endpoint back to WooCommerce's own template inside the
	 * normal site header.
	 *
	 * @param bool $take_over Default true.
	 */
	return (bool) apply_filters( 'roova_use_view_order_template', true );
}

/**
 * Render a single order through the theme's own document.
 *
 * @param string $template Template path.
 * @return string
 */
function roova_view_order_template( $template ) {
	if ( ! roova_is_view_order() ) {
		return $template;
	}

	$found = locate_template( array( 'view-order.php' ) );

	return $found ? $found : $template;
}
add_filter( 'template_include', 'roova_view_order_template', 100 );

/* -------------------------------------------------------------------------
 * The order, read line by line
 * ---------------------------------------------------------------------- */

/**
 * The stays on an order: one row per line item.
 *
 * The same shape `roova_account_stays()` builds a card from, read straight off
 * this order rather than out of the member's whole history — an order placed by
 * hand in the admin can carry more than one, and a line that is not a booking at
 * all still deserves a row.
 *
 * The dates come from the line's own `_roova_booking` meta, not the bookings
 * table: that meta is what the order was placed on, and it outlives a booking
 * row that has since been cleaned up.
 *
 * @param WC_Order $order Order.
 * @return array[]
 */
function roova_order_lines( $order ) {
	$lines = array();

	foreach ( $order->get_items() as $item_id => $item ) {
		$booking = $item->get_meta( '_roova_booking', true );
		$booking = is_array( $booking ) ? $booking : array();

		$room_id  = isset( $booking['room_id'] ) ? absint( $booking['room_id'] ) : 0;
		$hotel_id = $room_id ? roova_get_room_hotel_id( $room_id ) : 0;

		$check_in  = roova_sanitize_date( isset( $booking['check_in'] ) ? $booking['check_in'] : '' );
		$check_out = roova_sanitize_date( isset( $booking['check_out'] ) ? $booking['check_out'] : '' );
		$total     = (float) $order->get_line_total( $item, true, false );

		$lines[] = array(
			'item_id'    => (int) $item_id,
			'name'       => $item->get_name(),
			'room_id'    => $room_id,
			'hotel_id'   => $hotel_id,
			'hotel'      => $hotel_id ? get_the_title( $hotel_id ) : '',
			'hotel_url'  => $hotel_id ? get_permalink( $hotel_id ) : '',
			'check_in'   => $check_in,
			'check_out'  => $check_out,
			'nights'     => roova_nights( $check_in, $check_out ),
			'units'      => max( 1, (int) $item->get_quantity() ),
			'adults'     => isset( $booking['adults'] ) ? (int) $booking['adults'] : 0,
			'children'   => isset( $booking['children'] ) ? (int) $booking['children'] : 0,
			'total'      => $total,
			'total_html' => wc_price( $total, array( 'currency' => $order->get_currency() ) ),
			'image_id'   => roova_order_line_image( $hotel_id, $room_id ),
		);
	}

	return $lines;
}

/**
 * Which post's featured image stands for a line — the hotel's, then the room's.
 *
 * @param int $hotel_id Hotel product ID.
 * @param int $room_id  Room product ID.
 * @return int 0 when neither has one.
 */
function roova_order_line_image( $hotel_id, $room_id ) {
	if ( $hotel_id && has_post_thumbnail( $hotel_id ) ) {
		return (int) $hotel_id;
	}

	if ( $room_id && has_post_thumbnail( $room_id ) ) {
		return (int) $room_id;
	}

	return 0;
}

/**
 * The stay this whole order is about, or null for an order with no booking on it.
 *
 * A Roova cart holds a single booking, so every order but a hand-made one has
 * exactly one — it is what the sub-heading, the buttons and the review link are
 * all read from.
 *
 * @param WC_Order $order Order.
 * @return array|null
 */
function roova_order_primary_line( $order ) {
	foreach ( roova_order_lines( $order ) as $line ) {
		if ( $line['room_id'] ) {
			return $line;
		}
	}

	return null;
}

/**
 * Which of the four states this order is in.
 *
 * The same four the bookings tab draws chips for, decided by the same function,
 * so a stay cannot read "Completed" on one page and "Payment due" on the other.
 *
 * @param WC_Order $order Order.
 * @return string upcoming|completed|cancelled|payment.
 */
function roova_order_status( $order ) {
	$line      = roova_order_primary_line( $order );
	$check_out = $line ? $line['check_out'] : '';

	return roova_account_stay_status( $order, $check_out, roova_today() );
}

/* -------------------------------------------------------------------------
 * The parts the page prints
 * ---------------------------------------------------------------------- */

/**
 * "Placed on 2 September 2026 · 1 night at Ampang Point Star Hotel".
 *
 * Each clause is dropped when there is nothing behind it, so an order with no
 * booking line still gets a sensible sentence.
 *
 * @param WC_Order $order Order.
 * @return string
 */
function roova_order_summary_line( $order ) {
	$parts = array();

	$created = $order->get_date_created();
	if ( $created ) {
		$parts[] = sprintf(
			/* translators: %s: the date the order was placed */
			__( 'Placed on %s', 'roova' ),
			wc_format_datetime( $created, get_option( 'date_format' ) )
		);
	}

	$line = roova_order_primary_line( $order );

	/*
	 * The stay is one clause, not two: "2 nights at Harbour Lights", never
	 * "2 nights · at Harbour Lights". Either half stands alone when the other
	 * is missing.
	 */
	$nights = '';
	if ( $line && $line['nights'] ) {
		$nights = sprintf(
			/* translators: %s: number of nights */
			_n( '%s night', '%s nights', $line['nights'], 'roova' ),
			number_format_i18n( $line['nights'] )
		);
	}

	$hotel = $line && $line['hotel'] ? $line['hotel'] : '';

	if ( $nights && $hotel ) {
		$parts[] = sprintf(
			/* translators: 1: "2 nights", 2: hotel name */
			__( '%1$s at %2$s', 'roova' ),
			$nights,
			$hotel
		);
	} elseif ( $nights ) {
		$parts[] = $nights;
	} elseif ( $hotel ) {
		$parts[] = $hotel;
	}

	return implode( ' · ', $parts );
}

/**
 * The four facts under a booking line: check in, check out, nights, guests.
 *
 * A fact with nothing behind it is left out rather than printed empty — an order
 * line that carries no stay shows none of them and the grid closes up.
 *
 * @param array $line One row from roova_order_lines().
 * @return array[] icon, label, value.
 */
function roova_order_stay_facts( $line ) {
	$facts = array();

	if ( $line['check_in'] ) {
		$facts[] = array(
			'icon'  => 'log-in',
			'label' => __( 'Check in', 'roova' ),
			'value' => date_i18n( 'M j, Y', strtotime( $line['check_in'] . ' 00:00:00' ) ),
		);
	}

	if ( $line['check_out'] ) {
		$facts[] = array(
			'icon'  => 'log-out',
			'label' => __( 'Check out', 'roova' ),
			'value' => date_i18n( 'M j, Y', strtotime( $line['check_out'] . ' 00:00:00' ) ),
		);
	}

	if ( $line['nights'] ) {
		$facts[] = array(
			'icon'  => 'moon',
			'label' => __( 'Nights', 'roova' ),
			'value' => number_format_i18n( $line['nights'] ),
		);
	}

	$guests = roova_account_guest_label( $line );
	if ( $guests ) {
		$facts[] = array(
			'icon'  => 'users',
			'label' => __( 'Guests', 'roova' ),
			'value' => $guests,
		);
	}

	return $facts;
}

/**
 * The customer rows in the sidebar.
 *
 * Read from the order rather than the user record: the order is what the hotel
 * was given on the day, and a member who has since changed their phone number
 * should still see the one this booking carries.
 *
 * @param WC_Order $order Order.
 * @return array[] icon, label, value, wrap.
 */
function roova_order_customer_rows( $order ) {
	$rows = array();

	$name = trim( $order->get_formatted_billing_full_name() );
	if ( $name ) {
		$rows[] = array(
			'icon'  => 'user',
			'label' => __( 'Full name', 'roova' ),
			'value' => $name,
			'wrap'  => false,
		);
	}

	$phone = $order->get_billing_phone();
	if ( $phone ) {
		$rows[] = array(
			'icon'  => 'phone',
			'label' => __( 'Phone number', 'roova' ),
			'value' => $phone,
			'wrap'  => false,
		);
	}

	$email = $order->get_billing_email();
	if ( $email ) {
		$rows[] = array(
			'icon'  => 'mail',
			'label' => __( 'Email address', 'roova' ),
			// An address has no spaces to break at, so it gets to break anywhere.
			'value' => $email,
			'wrap'  => true,
		);
	}

	/**
	 * Filter the customer rows shown on the order page.
	 *
	 * @param array[]  $rows  icon, label, value, wrap.
	 * @param WC_Order $order Order.
	 */
	return apply_filters( 'roova_order_customer_rows', $rows, $order );
}

/**
 * The small line under the payment method's name.
 *
 * The gateway's own reference for this payment, which is the one thing worth
 * quoting at a bank. The date belongs to the state line below it, not here —
 * printing it in both places said the same thing twice.
 *
 * @param WC_Order $order Order.
 * @return string '' when the gateway gave no reference back.
 */
function roova_order_payment_note( $order ) {
	$transaction = $order->get_transaction_id();

	if ( ! $transaction ) {
		return '';
	}

	return sprintf(
		/* translators: %s: the payment gateway's transaction reference */
		__( 'Ref %s', 'roova' ),
		$transaction
	);
}

/**
 * The totals rows above "Total paid".
 *
 * Discounts, fees and taxes are only printed when the order carries them, and
 * taxes follow WooCommerce → Settings → Tax → "Display tax totals" exactly as
 * the checkout summary does — so the two pages cannot disagree about what a stay
 * was charged.
 *
 * @param WC_Order $order Order.
 * @return array[] label, value (HTML), class.
 */
function roova_order_total_rows( $order ) {
	$currency = array( 'currency' => $order->get_currency() );

	$rows = array(
		array(
			'label' => __( 'Subtotal', 'roova' ),
			'value' => wc_price( (float) $order->get_subtotal(), $currency ),
			'class' => '',
		),
	);

	$discount = (float) $order->get_total_discount();
	if ( $discount > 0 ) {
		$rows[] = array(
			'label' => roova_order_discount_label( $order ),
			'value' => '-' . wc_price( $discount, $currency ),
			'class' => 'roova-order__row--discount',
		);
	}

	foreach ( $order->get_fees() as $fee ) {
		$rows[] = array(
			'label' => $fee->get_name(),
			'value' => wc_price( (float) $fee->get_total(), $currency ),
			'class' => '',
		);
	}

	if ( (float) $order->get_shipping_total() > 0 ) {
		$rows[] = array(
			'label' => __( 'Shipping', 'roova' ),
			'value' => wc_price( (float) $order->get_shipping_total(), $currency ),
			'class' => '',
		);
	}

	$taxes = (float) $order->get_total_tax();

	if ( $taxes > 0 && 'itemized' === get_option( 'woocommerce_tax_total_display' ) && $order->get_tax_totals() ) {
		foreach ( $order->get_tax_totals() as $tax ) {
			$rows[] = array(
				'label' => roova_order_tax_label( $tax ),
				'value' => $tax->formatted_amount,
				'class' => 'roova-order__row--tax',
			);
		}
	} elseif ( $taxes > 0 ) {
		$rows[] = array(
			'label' => __( 'Taxes & fees', 'roova' ),
			'value' => wc_price( $taxes, $currency ),
			'class' => 'roova-order__row--tax',
		);
	} else {
		/*
		 * No tax of its own means it is already inside the room rate — a store
		 * whose prices include tax, or one that charges none. Either way an
		 * amount on this line would read as an extra charge.
		 */
		$rows[] = array(
			'label' => __( 'Taxes & fees', 'roova' ),
			'value' => esc_html__( 'Included', 'roova' ),
			'class' => 'roova-order__row--tax',
		);
	}

	/**
	 * Filter the totals rows shown above the order total.
	 *
	 * @param array[]  $rows  label, value, class.
	 * @param WC_Order $order Order.
	 */
	return apply_filters( 'roova_order_total_rows', $rows, $order );
}

/**
 * "Discount", or the coupons that made it — "Discount (WELCOME10)".
 *
 * @param WC_Order $order Order.
 * @return string
 */
function roova_order_discount_label( $order ) {
	$codes = array();

	foreach ( $order->get_coupon_codes() as $code ) {
		$codes[] = strtoupper( $code );
	}

	if ( ! $codes ) {
		return __( 'Discount', 'roova' );
	}

	return sprintf(
		/* translators: %s: comma-separated coupon codes */
		__( 'Discount (%s)', 'roova' ),
		implode( ', ', $codes )
	);
}

/**
 * "SST (10%)" — the rate's own name, with what it actually charged.
 *
 * The percentage is read back off the rate rather than written down here, the
 * same way the checkout summary reads it, so editing the rate changes both.
 *
 * @param stdClass $tax One entry from WC_Order::get_tax_totals().
 * @return string
 */
function roova_order_tax_label( $tax ) {
	$label = isset( $tax->label ) ? $tax->label : __( 'Tax', 'roova' );

	if ( empty( $tax->rate_id ) || ! method_exists( 'WC_Tax', '_get_tax_rate' ) ) {
		return $label;
	}

	$rate = WC_Tax::_get_tax_rate( $tax->rate_id, ARRAY_A );
	if ( empty( $rate['tax_rate'] ) ) {
		return $label;
	}

	// 5.0000 reads as 5, 8.2500 as 8.25.
	$percent = rtrim( rtrim( number_format( (float) $rate['tax_rate'], 4, '.', '' ), '0' ), '.' );
	if ( '' === $percent || '0' === $percent ) {
		return $label;
	}

	return sprintf(
		/* translators: 1: tax name, e.g. SST, 2: percentage, e.g. 10 */
		__( '%1$s (%2$s%%)', 'roova' ),
		$label,
		$percent
	);
}

/**
 * What the total is called.
 *
 * "Total paid" is a claim, so it is only made about an order that was actually
 * paid and stayed that way. One still waiting says so; one that was cancelled,
 * failed or refunded gets the neutral wording, because the money may have gone
 * back and this page must not tell a guest otherwise.
 *
 * @param WC_Order $order Order.
 * @return string
 */
function roova_order_total_label( $order ) {
	if ( $order->needs_payment() ) {
		return __( 'Total due', 'roova' );
	}

	if ( $order->has_status( array( 'cancelled', 'failed', 'refunded' ) ) ) {
		return __( 'Order total', 'roova' );
	}

	return __( 'Total paid', 'roova' );
}

/**
 * The line under the payment method: where this order's money stands.
 *
 * Four honest answers rather than one. A cancelled or refunded order used to
 * read "Paid in full" here, which is the sort of thing a guest quotes back at
 * the front desk — so those now carry WooCommerce's own name for the state the
 * order is really in.
 *
 * @param WC_Order $order Order.
 * @return array icon, label, class ('' for the plain green line).
 */
function roova_order_payment_state( $order ) {
	if ( $order->needs_payment() ) {
		return array(
			'icon'  => 'clock',
			'label' => __( 'Awaiting payment', 'roova' ),
			'class' => 'roova-order__pay-state--due',
		);
	}

	if ( $order->has_status( array( 'cancelled', 'failed', 'refunded' ) ) ) {
		return array(
			'icon'  => 'info',
			'label' => function_exists( 'wc_get_order_status_name' )
				? wc_get_order_status_name( $order->get_status() )
				: __( 'Not paid', 'roova' ),
			'class' => 'roova-order__pay-state--off',
		);
	}

	$paid = $order->get_date_paid();

	return array(
		'icon'  => 'check-circle',
		'label' => $paid
			? sprintf(
				/* translators: %s: the date the payment cleared */
				__( 'Paid in full · %s', 'roova' ),
				wc_format_datetime( $paid, get_option( 'date_format' ) )
			)
			: __( 'Paid in full', 'roova' ),
		'class' => '',
	);
}

/* -------------------------------------------------------------------------
 * Buttons
 * ---------------------------------------------------------------------- */

/**
 * Where "Order again" sends a guest.
 *
 * The hotel page, at this room, carrying the party size the stay was booked for
 * — not straight into the cart. The dates on a finished booking are in the past
 * and the checkout has no date picker, so a one-click re-add would have someone
 * paying for dates they never chose. Picking dates on the hotel page and
 * pressing "Book now" lands on checkout through `Roova_Cart::redirect_after_add()`,
 * which is the door every other booking comes through.
 *
 * @param array|null $line One row from roova_order_lines().
 * @return string '' when the hotel or the room has since gone.
 */
function roova_order_again_url( $line ) {
	if ( ! $line || ! $line['hotel_url'] || ! $line['room_id'] ) {
		return '';
	}

	$args = array();

	if ( $line['adults'] > 0 ) {
		$args['adults'] = $line['adults'];
	}

	if ( $line['children'] > 0 ) {
		$args['children'] = $line['children'];
	}

	if ( $line['units'] > 1 ) {
		$args['rooms'] = $line['units'];
	}

	$url = $args ? add_query_arg( $args, $line['hotel_url'] ) : $line['hotel_url'];

	return $url . '#room-' . absint( $line['room_id'] );
}

/**
 * The buttons under the order card, in the order they are drawn.
 *
 * The design draws one set — order again, download the voucher, write a review —
 * and that is what a finished stay gets. The other three states earn their own:
 * an unpaid order leads with "Pay now", because burying that is the one mistake
 * this page must not make; a stay that has not happened yet cannot be reviewed
 * and there is nothing to re-book; a cancelled one is a record, not an
 * invitation.
 *
 * @param WC_Order $order Order.
 * @return array[] label, url, icon, style (primary|ghost|gold), action.
 */
function roova_order_actions( $order ) {
	$status  = roova_order_status( $order );
	$line    = roova_order_primary_line( $order );
	$actions = array();

	if ( 'payment' === $status ) {
		$actions[] = array(
			'label'  => __( 'Pay now', 'roova' ),
			'url'    => $order->get_checkout_payment_url(),
			'icon'   => 'arrow-right',
			'style'  => 'primary',
			'action' => '',
		);
	} elseif ( 'completed' === $status ) {
		$again = roova_order_again_url( $line );

		if ( $again ) {
			$actions[] = array(
				'label'  => __( 'Order again', 'roova' ),
				'url'    => $again,
				'icon'   => 'repeat',
				'style'  => 'primary',
				'action' => '',
			);
		}
	} elseif ( 'upcoming' === $status && $line && $line['hotel_url'] ) {
		$actions[] = array(
			'label'  => __( 'View hotel', 'roova' ),
			'url'    => $line['hotel_url'],
			'icon'   => 'arrow-right',
			'style'  => 'primary',
			'action' => '',
		);
	}

	/*
	 * The voucher is this page. Printing it is the only download the theme can
	 * honestly offer, and it is the one every browser can save as a PDF — see
	 * the print rules at the foot of order.css, which strip the chrome.
	 */
	$actions[] = array(
		'label'  => __( 'Download voucher', 'roova' ),
		'url'    => '',
		'icon'   => 'download',
		'style'  => 'ghost',
		'action' => 'print',
	);

	// Only where the guest may actually write one: a stay they finished, at a
	// hotel they have not reviewed yet, with reviews still open on it.
	if ( 'completed' === $status && $line && $line['hotel_url'] && $line['hotel_id']
		&& function_exists( 'roova_can_review' ) && roova_can_review( $line['hotel_id'] ) ) {
		$actions[] = array(
			'label'  => __( 'Write a review', 'roova' ),
			'url'    => $line['hotel_url'] . '#reviews',
			'icon'   => 'star',
			'style'  => 'gold',
			'action' => '',
		);
	}

	/**
	 * Filter the buttons under the order card.
	 *
	 * @param array[]  $actions label, url, icon, style, action.
	 * @param WC_Order $order   Order.
	 * @param string   $status  upcoming|completed|cancelled|payment.
	 */
	return apply_filters( 'roova_order_actions', $actions, $order, $status );
}
