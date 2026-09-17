<?php
/**
 * Order received: the booking confirmed, in its own document.
 *
 * The page "Book now" finally lands on. Until now it was drawn inside
 * `checkout.php` — under the reception photograph, beneath a banner that read
 * "Booking confirmed" while the panel below it said the same thing again. It is
 * now its own document, for the reason view-order, the two auth pages and the
 * account dashboard have theirs: the design's whole header is the wordmark and
 * a way into My bookings, and nothing on a confirmation should lead back
 * towards a checkout that has already been paid.
 *
 * WooCommerce keeps the URL and the gate. A guest reaches this page with the
 * order key in the query string and no account at all, so the key is what
 * `roova_received_order()` checks — exactly as WooCommerce's own template does.
 * A guessed order ID gets nothing.
 *
 * **Everything on the page is read from the order at render time.** The number,
 * the date and the total come off the order; the stay off each line's
 * `_roova_booking` meta, which is what the order was placed on; the customer
 * off the billing fields the guest actually typed; the payment off the gateway
 * that took it; the hotel's address and reception number off the hotel product.
 * Nothing is transcribed from the design handoff, and there is no second source
 * of truth — the status chip is decided by `roova_account_stay_status()`, the
 * same function the bookings tab and the order page use, so one stay cannot
 * read three different things in three places.
 *
 * The one forward-looking figure is the cashback forecast, and it is the most
 * carefully fenced thing here. See `roova_received_cashback_forecast()`.
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
 * Gated on the order key rather than on being logged in, because a guest
 * checkout lands here with no account: the key in the URL is the only thing
 * that proves this browser is the one that just paid. That is the same test
 * WooCommerce's own thankyou.php runs, filters included, so a site already
 * hooking either filter keeps doing so.
 *
 * @return WC_Order|null
 */
function roova_received_order() {
	static $cache = false;

	if ( false !== $cache ) {
		return $cache;
	}

	$cache = null;

	if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
		return $cache;
	}

	$order_id = apply_filters( 'woocommerce_thankyou_order_id', absint( get_query_var( 'order-received' ) ) );

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the key read here is itself the credential; a nonce would break the link out of the confirmation email.
	$raw_key   = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
	$order_key = apply_filters( 'woocommerce_thankyou_order_key', $raw_key );

	if ( ! $order_id || '' === $order_key ) {
		return $cache;
	}

	$order = wc_get_order( $order_id );

	if ( ! $order instanceof WC_Order ) {
		return $cache;
	}

	// hash_equals rather than ===, for the reason the verification tokens use it.
	$valid = method_exists( $order, 'key_is_valid' )
		? $order->key_is_valid( $order_key )
		: hash_equals( (string) $order->get_order_key(), (string) $order_key );

	if ( $valid && roova_received_visitor_may_view( $order ) ) {
		$cache = $order;
	}

	return $cache;
}

/**
 * Would WooCommerce itself show this order to this visitor?
 *
 * The order key alone is **not** the gate, and assuming it was is the mistake
 * this function exists to prevent. WooCommerce puts two more checks in front of
 * its own order-received page, and a confirmation carries a guest's name, phone
 * number and email — so the theme must be no more permissive than the plugin it
 * is dressing.
 *
 * The two checks, in WooCommerce's own order:
 *
 * 1. **A non-guest order is only shown to that customer, signed in.** Otherwise
 *    WooCommerce prints a login form instead. The order key travels — it sits in
 *    browser history, in a shared link, in an email forwarded on — so on its own
 *    it must not open a member's booking to whoever is holding it.
 * 2. **A guest order may need its email address confirmed first.** Inside a short
 *    grace period after checkout, or where the session already knows the buyer's
 *    address, it does not; after that WooCommerce asks.
 *
 * Returning false here does not deny anybody anything: `roova_is_order_received()`
 * simply stops taking the page over, and WooCommerce draws its own login form or
 * email-confirmation form inside checkout.php, exactly as it would if this theme
 * were not installed.
 *
 * @param WC_Order $order Order.
 * @return bool
 */
function roova_received_visitor_may_view( $order ) {
	$customer_id = (int) $order->get_customer_id();

	/**
	 * WooCommerce's own filter, honoured here so a site that has turned the
	 * known-shopper rule off gets the same page it would get without the theme.
	 *
	 * @param bool $verify Default true.
	 */
	$verify = (bool) apply_filters( 'woocommerce_order_received_verify_known_shoppers', true );

	if ( $customer_id ) {
		return ! $verify || get_current_user_id() === $customer_id;
	}

	/*
	 * A guest order. WooCommerce works this out in
	 * Users::should_user_verify_order_email(), including the grace period and
	 * the address a successful confirmation just posted — so call it rather
	 * than re-deriving a rule that has to stay in step with it.
	 */
	$supplied = null;
	if ( wp_verify_nonce( filter_input( INPUT_POST, 'check_submission' ), 'wc_verify_email' ) ) {
		$supplied = sanitize_email( wp_unslash( filter_input( INPUT_POST, 'email' ) ) );
	}

	$users = '\Automattic\WooCommerce\Internal\Utilities\Users';

	if ( is_callable( array( $users, 'should_user_verify_order_email' ) ) ) {
		return ! call_user_func(
			array( $users, 'should_user_verify_order_email' ),
			$order->get_id(),
			$supplied,
			'order-received'
		);
	}

	/*
	 * That class is internal to WooCommerce, so it may move. The fallback is
	 * WooCommerce's own closing condition written with public API and without
	 * the grace period — which makes it stricter, never looser. A guest who has
	 * just paid still passes: checkout put their address in the session.
	 */
	if ( current_user_can( 'read_private_shop_orders' ) ) {
		return true;
	}

	$billing = trim( (string) $order->get_billing_email() );

	// Nothing to verify an address against.
	if ( '' === $billing ) {
		return true;
	}

	if ( null !== $supplied && 0 === strcasecmp( (string) $supplied, $billing ) ) {
		return true;
	}

	$session_email = '';

	if ( function_exists( 'WC' ) && WC()->session ) {
		$customer      = WC()->session->get( 'customer' );
		$session_email = ( is_array( $customer ) && isset( $customer['email'] ) ) ? (string) $customer['email'] : '';
	}

	return '' !== $session_email && 0 === strcasecmp( $session_email, $billing );
}

/**
 * Is this the order-received page, drawn by the theme?
 *
 * @return bool
 */
function roova_is_order_received() {
	if ( ! roova_received_order() ) {
		return false;
	}

	/**
	 * Filter whether the theme draws the confirmation in its own document.
	 *
	 * Off sends the view back through `checkout.php` and
	 * `woocommerce/checkout/thankyou.php`, under the checkout banner — which is
	 * why both of those are still here.
	 *
	 * @param bool $take_over Default true.
	 */
	return (bool) apply_filters( 'roova_use_order_received_template', true );
}

/**
 * Render the confirmation through the theme's own document.
 *
 * Priority 110, above `roova_checkout_template()`'s 100: `is_checkout()` is
 * true on this page too, so checkout would otherwise claim it first.
 *
 * @param string $template Template path.
 * @return string
 */
function roova_order_received_template( $template ) {
	if ( ! roova_is_order_received() ) {
		return $template;
	}

	$found = locate_template( array( 'order-received.php' ) );

	return $found ? $found : $template;
}
add_filter( 'template_include', 'roova_order_received_template', 110 );

/**
 * Take WooCommerce's own order table off the confirmation.
 *
 * `woocommerce_order_details_table` is hooked to `woocommerce_thankyou` at 10,
 * and it prints the whole default view: the line items, the totals *and* the
 * customer details. The template draws all three itself, so left alone the
 * booking appears twice on the page — once designed, once again underneath.
 *
 * Only that one callback is removed, and only on this page. The hook itself
 * still fires, because gateways and plugins hang their own notices on it, and
 * the gateway-specific `woocommerce_thankyou_{id}` — where bank transfer prints
 * the account to pay into — is untouched.
 *
 * @return void
 */
function roova_received_unhook_order_table() {
	if ( ! roova_is_order_received() ) {
		return;
	}

	remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', 10 );
}
add_action( 'template_redirect', 'roova_received_unhook_order_table' );

/**
 * The housekeeping WooCommerce does when it draws this page itself.
 *
 * The template does not run `[woocommerce_checkout]`, which means
 * `WC_Shortcode_Checkout::order_received()` never runs — and that method is not
 * only a renderer. It also empties the cart and clears the
 * `order_awaiting_payment` flag, and skipping those is not cosmetic: a booking
 * left sitting in the cart after it has been paid for still has a hold behind
 * it, and `Roova_Holds` releases a hold when its cart line is removed. A line
 * whose hold is now attached to a paid order must not be in a position to
 * release it.
 *
 * @return void
 */
function roova_received_housekeeping() {
	if ( ! roova_is_order_received() ) {
		return;
	}

	if ( function_exists( 'WC' ) && WC()->session ) {
		unset( WC()->session->order_awaiting_payment );
	}

	if ( function_exists( 'wc_empty_cart' ) ) {
		wc_empty_cart();
	}
}
add_action( 'template_redirect', 'roova_received_housekeeping' );

/* -------------------------------------------------------------------------
 * The head of the page
 * ---------------------------------------------------------------------- */

/**
 * The four tiles across the top: number, date, total, status.
 *
 * Every one is read off the order. "Total paid" is a claim, so the third label
 * comes from `roova_order_total_label()` — the same function the order page
 * uses, so an order that was refunded cannot read "Total paid" here and "Order
 * total" one click away.
 *
 * @param WC_Order $order Order.
 * @return array[] label, value (HTML), tone.
 */
function roova_received_facts( $order ) {
	$status  = roova_order_status( $order );
	$chips   = roova_account_stay_chips();
	$created = $order->get_date_created();

	$facts = array(
		array(
			'label' => __( 'Order number', 'roova' ),
			'value' => esc_html( $order->get_order_number() ),
			'tone'  => '',
		),
		array(
			'label' => __( 'Date', 'roova' ),
			'value' => $created ? esc_html( wc_format_datetime( $created, 'M j, Y' ) ) : '&mdash;',
			'tone'  => '',
		),
		array(
			'label' => roova_order_total_label( $order ),
			'value' => wp_kses_post( $order->get_formatted_order_total() ),
			'tone'  => '',
		),
		array(
			'label' => __( 'Status', 'roova' ),
			'value' => esc_html( isset( $chips[ $status ] ) ? $chips[ $status ] : $status ),
			'tone'  => $status,
		),
	);

	/**
	 * Filter the tiles across the top of the confirmation.
	 *
	 * @param array[]  $facts label, value, tone.
	 * @param WC_Order $order Order.
	 */
	return apply_filters( 'roova_received_facts', $facts, $order );
}

/**
 * The buttons in the confirmation panel.
 *
 * The voucher is this page, so "Download voucher" prints it — the print rules
 * at the foot of received.css strip the chrome, exactly as order.css does for
 * the order page, and the two share `assets/js/order.js`.
 *
 * "View order details" is only offered to the signed-in owner, because that is
 * the only visitor WooCommerce's view-order endpoint lets through. Offering a
 * guest a door that is locked is the mistake the checkout sign-up notice was
 * fixed for.
 *
 * @param WC_Order $order Order.
 * @return array[] label, url, icon, style, action.
 */
function roova_received_actions( $order ) {
	$actions = array();

	/*
	 * An order still waiting for payment leads with paying for it: nothing else
	 * on this page matters more, and a voucher for a stay that is one unpaid
	 * order away from being released is not worth printing yet.
	 */
	if ( $order->needs_payment() ) {
		$actions[] = array(
			'label'  => __( 'Pay now', 'roova' ),
			'url'    => $order->get_checkout_payment_url(),
			'icon'   => 'arrow-right',
			'style'  => 'light',
			'action' => '',
		);
	} else {
		$actions[] = array(
			'label'  => __( 'Download voucher', 'roova' ),
			'url'    => '',
			'icon'   => 'download',
			'style'  => 'light',
			'action' => 'print',
		);
	}

	if ( is_user_logged_in() && (int) $order->get_user_id() === get_current_user_id() ) {
		$actions[] = array(
			'label'  => __( 'View order details', 'roova' ),
			'url'    => $order->get_view_order_url(),
			'icon'   => 'arrow-right',
			'style'  => 'outline',
			'action' => '',
		);
	}

	/**
	 * Filter the buttons in the confirmation panel.
	 *
	 * @param array[]  $actions label, url, icon, style, action.
	 * @param WC_Order $order   Order.
	 */
	return apply_filters( 'roova_received_actions', $actions, $order );
}

/* -------------------------------------------------------------------------
 * Hotel contact
 * ---------------------------------------------------------------------- */

/**
 * Where the hotel is and how to ring it.
 *
 * The two things a guest holding a confirmed booking actually reaches for. Both
 * come off the hotel product — `_roova_address` and `_roova_phone` — and a row
 * with nothing behind it is left out rather than printed empty, which is the
 * rule the hotel page's own contact card follows.
 *
 * The address links to the map, and the number to a dialler through
 * `roova_tel_href()`: a client writes a number to be *read*, and every space
 * and bracket in it either does nothing in a dialler or stops the link working.
 * The number is still shown exactly as typed; only the href is reduced. A field
 * holding something that is not a number at all — "ask at the desk" — comes
 * back with no digits and is printed as plain text rather than becoming a dead
 * link.
 *
 * @param int $hotel_id Hotel product ID.
 * @return array[] icon, label, value, url, wrap.
 */
function roova_received_hotel_rows( $hotel_id ) {
	$hotel_id = absint( $hotel_id );
	$rows     = array();

	if ( ! $hotel_id ) {
		return $rows;
	}

	$details = roova_get_hotel_details( $hotel_id );
	$lat     = trim( (string) $details['lat'] );
	$lng     = trim( (string) $details['lng'] );
	$address = trim( (string) $details['address'] );

	/*
	 * The full street address where the client has written one, and the
	 * destination the hotel sits in otherwise — which is the most a hotel with
	 * no address on it can honestly say about where it is.
	 */
	$place = $address ? $address : roova_hotel_location_label( $hotel_id );

	if ( $place ) {
		// Coordinates beat a text query wherever the hotel carries them, for
		// the same reason the hotel page's map prefers them.
		$query = ( $lat && $lng ) ? $lat . ',' . $lng : $place;

		$rows[] = array(
			'icon'  => 'pin',
			'label' => __( 'Address', 'roova' ),
			'value' => $place,
			'url'   => 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $query ),
			'wrap'  => false,
		);
	}

	$phone = trim( (string) $details['phone'] );
	if ( $phone ) {
		$tel = roova_tel_href( $phone );

		$rows[] = array(
			'icon'  => 'phone',
			'label' => __( 'Reception', 'roova' ),
			'value' => $phone,
			'url'   => $tel ? 'tel:' . $tel : '',
			'wrap'  => false,
		);
	}

	/*
	 * The times are a supporting row, never a reason for the panel to exist.
	 * roova_get_hotel_details() *defaults* them to 15:00 and 12:00, so without
	 * this a hotel carrying no address and no number would still hand back one
	 * row and the template would draw a "Hotel contact" card containing no
	 * contact information at all — the empty-panel problem the hotel page's own
	 * contact card is written to avoid.
	 */
	if ( ! $rows ) {
		return $rows;
	}

	$in  = trim( (string) $details['checkin_time'] );
	$out = trim( (string) $details['checkout_time'] );

	if ( $in && $out ) {
		$rows[] = array(
			'icon'  => 'clock',
			'label' => __( 'Front desk', 'roova' ),
			'value' => sprintf(
				/* translators: 1: check-in time, 2: check-out time */
				__( 'Check in from %1$s · check out by %2$s', 'roova' ),
				$in,
				$out
			),
			'url'   => '',
			'wrap'  => false,
		);
	}

	/**
	 * Filter the hotel contact rows on the confirmation.
	 *
	 * @param array[] $rows     icon, label, value, url, wrap.
	 * @param int     $hotel_id Hotel product ID.
	 */
	return apply_filters( 'roova_received_hotel_rows', $rows, $hotel_id );
}

/* -------------------------------------------------------------------------
 * Cashback, forecast
 * ---------------------------------------------------------------------- */

/**
 * What this stay will earn, if anything — and null is the common answer.
 *
 * The one figure on the page that is about the future, so it is fenced on every
 * side:
 *
 * - **Signed-in members only.** Cashback lands in a member's ledger
 *   (`roova_cashback_ledger()`), and a guest who checked out without an account
 *   has nowhere for it to land. Telling them money is coming to a balance they
 *   do not have is a promise the theme cannot keep.
 * - **Only the member who placed the order**, for the same reason: the ledger it
 *   would land in is theirs, not whoever is holding this link.
 * - **Only where an offer actually matches.** `roova_cashback_best_reward()`
 *   runs the real rule against the real stay — the nights booked against the
 *   offer's minimum, the hotel, and the window the offer runs in — and no match
 *   means no step at all. Nothing generic is printed in its place, because "you
 *   may earn cashback" is not a fact about this booking.
 * - **Only where the stay can still complete.** Cashback is earned at checkout,
 *   so a cancelled, failed or refunded order forecasts nothing.
 *
 * The amount and the clearing date are read off the offer as it stands today,
 * and **nothing is written**: the ledger entry is only made once the stay is
 * actually over, frozen at that moment, by `roova_cashback_sync()`. That split
 * is deliberate — this page is a forecast, not a payout — and it is why editing
 * an offer between now and checkout changes what gets paid, while editing it
 * after checkout does not.
 *
 * @param WC_Order $order Order.
 * @return array|null amount, amount_html, nights, clear_days, clears, reward.
 */
function roova_received_cashback_forecast( $order ) {
	if ( ! is_user_logged_in() || ! function_exists( 'roova_cashback_best_reward' ) ) {
		return null;
	}

	if ( (int) $order->get_user_id() !== get_current_user_id() ) {
		return null;
	}

	if ( ! roova_cashback_enabled() ) {
		return null;
	}

	if ( $order->has_status( array( 'cancelled', 'refunded', 'failed' ) ) ) {
		return null;
	}

	$line = roova_order_primary_line( $order );

	if ( ! $line || ! $line['nights'] || ! $line['check_out'] ) {
		return null;
	}

	/*
	 * The shape roova_cashback_reward_matches() reads: the nights booked, the
	 * hotel they are at, and the day the guest checks out. The same three the
	 * ledger will be handed when the stay is over, which is what makes the
	 * figure quoted here the figure that gets paid.
	 */
	$reward = roova_cashback_best_reward(
		array(
			'nights'    => (int) $line['nights'],
			'hotel_id'  => (int) $line['hotel_id'],
			'check_out' => $line['check_out'],
		)
	);

	if ( ! $reward ) {
		return null;
	}

	return array(
		'amount'      => (float) $reward['amount'],
		'amount_html' => roova_cashback_amount( $reward['amount'] ),
		'nights'      => (int) $line['nights'],
		'clear_days'  => (int) $reward['clear_days'],
		'clears'      => roova_add_days( $line['check_out'], (int) $reward['clear_days'] ),
		'reward'      => $reward,
	);
}

/* -------------------------------------------------------------------------
 * What happens next
 * ---------------------------------------------------------------------- */

/**
 * The numbered steps under the stay.
 *
 * Three where there is something true to say in each, and fewer where there is
 * not: a step with no data behind it drops out and the list renumbers itself in
 * the template, so a booking at a hotel this site has since deleted never
 * prints "confirms directly" with a blank where the name should be.
 *
 * @param WC_Order $order Order.
 * @return array[] icon, title, note.
 */
function roova_received_next_steps( $order ) {
	$steps = array();

	/*
	 * A stay that is not going to happen has no next steps. The panel above
	 * says what went wrong and offers the way out of it instead.
	 */
	if ( $order->has_status( array( 'cancelled', 'refunded', 'failed' ) ) ) {
		return $steps;
	}

	$line  = roova_order_primary_line( $order );
	$hotel = ( $line && $line['hotel'] ) ? $line['hotel'] : '';

	if ( $hotel ) {
		$steps[] = array(
			'icon'  => 'reception',
			'title' => sprintf(
				/* translators: %s: hotel name */
				__( '%s confirms directly', 'roova' ),
				$hotel
			),
			'note'  => __( 'The hotel has your booking and the number you gave us. They get in touch if anything about your arrival needs arranging.', 'roova' ),
		);
	}

	$steps[] = array(
		'icon'  => 'mail',
		'title' => __( 'Two emails from us', 'roova' ),
		/*
		 * Which second email depends on where the money is, so the sentence
		 * does too: an unpaid order is waiting on payment clearing, a paid one
		 * on the hotel closing the stay off.
		 */
		'note'  => $order->needs_payment()
			? __( 'The first is on its way now with this booking on it. A second follows as soon as your payment clears.', 'roova' )
			: __( 'The first is on its way now with this booking on it. A second follows once the hotel marks your stay complete.', 'roova' ),
	);

	$cashback = roova_received_cashback_forecast( $order );

	if ( $cashback ) {
		// wc_price() is a nest of spans; a step title is plain text.
		$amount = wp_strip_all_tags( $cashback['amount_html'] );

		$steps[] = array(
			'icon'  => 'coins',
			'title' => sprintf(
				/* translators: %s: cashback amount, e.g. "RM10.00" */
				__( '%s cashback after checkout', 'roova' ),
				$amount
			),
			'note'  => $cashback['clear_days'] > 0
				? sprintf(
					/* translators: 1: number of nights, 2: the date it clears, e.g. "Sep 19, 2026" */
					__( 'Your %1$s-night stay qualifies. It clears into your Roova balance on %2$s.', 'roova' ),
					number_format_i18n( $cashback['nights'] ),
					date_i18n( 'M j, Y', strtotime( $cashback['clears'] . ' 00:00:00' ) )
				)
				: sprintf(
					/* translators: %s: number of nights */
					__( 'Your %s-night stay qualifies. It clears into your Roova balance the day you check out.', 'roova' ),
					number_format_i18n( $cashback['nights'] )
				),
		);
	}

	/**
	 * Filter the "what happens next" steps.
	 *
	 * @param array[]  $steps icon, title, note.
	 * @param WC_Order $order Order.
	 */
	return apply_filters( 'roova_received_next_steps', $steps, $order );
}
