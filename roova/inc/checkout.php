<?php
/**
 * Checkout: the classic WooCommerce checkout, laid out to the Roova design.
 *
 * The site's checkout page ships with the Cart & Checkout *blocks*, which render
 * client-side and cannot be templated in PHP. Rather than rewrite the page
 * content behind the client's back, `roova_checkout_template()` routes every
 * checkout view through the theme's own document, which runs the classic
 * `[woocommerce_checkout]` shortcode — so the templates in
 * `woocommerce/checkout/` decide what the page looks like.
 *
 * Everything on the page is read from WooCommerce at render time: the summary
 * from the cart, the payment cards from the enabled gateways, the totals from
 * the cart's own totals. Nothing is hard-coded from the design handoff.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/*
 * Payment belongs in the left column under "Payment options", not at the foot
 * of the summary sidebar, and the coupon row lives inside the sidebar rather
 * than above the form. Both are moved by hand in the templates.
 *
 * WooCommerce registers these on plugins_loaded, so by the time the theme is
 * read they are already in place.
 */
remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );
remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );

/* -------------------------------------------------------------------------
 * The page itself
 * ---------------------------------------------------------------------- */

/**
 * Make sure a Checkout page exists under Pages.
 *
 * WooCommerce creates one when it is installed, but it is not always there: a
 * site set up without running WooCommerce's onboarding, a page someone trashed,
 * a half-finished migration. Without it `is_checkout()` is never true, so the
 * theme's checkout template never runs and the cart has nowhere to go.
 *
 * A checkout page that already exists is adopted as it stands — its content is
 * never touched, so a store using the block checkout keeps it.
 */
function roova_ensure_checkout_page() {
	if ( ! function_exists( 'wc_get_page_id' ) ) {
		return;
	}

	$page_id = (int) wc_get_page_id( 'checkout' );

	if ( $page_id > 0 && 'publish' === get_post_status( $page_id ) ) {
		return;
	}

	// Prefer whatever WooCommerce already points at, then a page at /checkout/.
	$existing = $page_id > 0 ? get_post( $page_id ) : null;
	if ( ! $existing ) {
		$existing = get_page_by_path( 'checkout' );
	}

	if ( $existing && 'page' === $existing->post_type ) {
		if ( 'trash' === $existing->post_status ) {
			// Untrashing restores the slug too, which trashing had suffixed.
			wp_untrash_post( $existing->ID );
		}

		if ( 'publish' !== get_post_status( $existing->ID ) ) {
			wp_update_post( array(
				'ID'          => $existing->ID,
				'post_status' => 'publish',
			) );
		}

		update_option( 'woocommerce_checkout_page_id', $existing->ID );
		return;
	}

	/*
	 * The shortcode, not the block: it is the classic checkout the theme's own
	 * templates render, so the page still works on its own if
	 * `roova_use_checkout_template` is ever turned off — or if the theme is.
	 */
	$content = '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->';

	// WooCommerce's own helper handles the page option and near-duplicates, but
	// it only exists in admin — WP-CLI activates a theme without it.
	if ( function_exists( 'wc_create_page' ) ) {
		wc_create_page( 'checkout', 'woocommerce_checkout_page_id', __( 'Checkout', 'roova' ), $content );
		return;
	}

	$page_id = wp_insert_post( array(
		'post_title'     => __( 'Checkout', 'roova' ),
		'post_name'      => 'checkout',
		'post_status'    => 'publish',
		'post_type'      => 'page',
		'post_content'   => $content,
		'comment_status' => 'closed',
	) );

	if ( $page_id && ! is_wp_error( $page_id ) ) {
		update_option( 'woocommerce_checkout_page_id', $page_id );
	}
}
add_action( 'after_switch_theme', 'roova_ensure_checkout_page' );

/**
 * The taxes a stay is charged, as WooCommerce tax rates.
 *
 * Percentages, names and order are only the starting point — once they are in
 * the tax table the client edits them under WooCommerce → Settings → Tax →
 * Standard rates, and both the summary and the order follow whatever is there.
 *
 * Each needs its own priority: WooCommerce applies one rate per priority, so two
 * rates sharing one would mean only the first is ever charged.
 *
 * @return array[]
 */
function roova_default_tax_rates() {
	/**
	 * Filter the tax rates a fresh install starts with.
	 *
	 * @param array[] $rates Each: name, rate, priority.
	 */
	return apply_filters(
		'roova_default_tax_rates',
		array(
			array(
				'name'     => __( 'Tourism Tax', 'roova' ),
				'rate'     => '5.0000',
				'priority' => 1,
			),
			array(
				'name'     => __( 'SST', 'roova' ),
				'rate'     => '10.0000',
				'priority' => 2,
			),
		)
	);
}

/**
 * Put the stay taxes in the tax table, once, on a store that has none.
 *
 * Guarded on the table being empty: a store with its own rates has had someone
 * think about them, and a theme has no business editing what a hotel charges.
 * After that first run the rates are the client's, and this never touches them
 * again — changing a percentage is a settings change, not a theme update.
 */
function roova_ensure_tax_rates() {
	global $wpdb;

	if ( ! class_exists( 'WC_Tax' ) ) {
		return;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$existing = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_tax_rates" );
	if ( $existing > 0 ) {
		return;
	}

	foreach ( roova_default_tax_rates() as $order => $rate ) {
		WC_Tax::_insert_tax_rate( array(
			// Blank country: a stay is taxed where the hotel is, not where the
			// guest lives, and the checkout collects no address to tax against.
			'tax_rate_country'  => '',
			'tax_rate_state'    => '',
			'tax_rate'          => $rate['rate'],
			'tax_rate_name'     => $rate['name'],
			'tax_rate_priority' => (int) $rate['priority'],
			'tax_rate_compound' => 0,
			'tax_rate_shipping' => 0,
			'tax_rate_order'    => (int) $order,
			'tax_rate_class'    => '',
		) );
	}

	update_option( 'woocommerce_calc_taxes', 'yes' );
	update_option( 'woocommerce_prices_include_tax', 'no' );

	// The room rate is the hotel's, wherever the guest is from.
	update_option( 'woocommerce_tax_based_on', 'base' );

	// One line per tax in the summary, which is the point of naming them.
	update_option( 'woocommerce_tax_total_display', 'itemized' );
}

/**
 * Check again on admin load, once per release.
 *
 * `after_switch_theme` only fires when the theme is switched, so an update
 * uploaded over a live site would never run it — and the checkout page can go
 * missing long after activation.
 */
function roova_maybe_run_store_setup() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	if ( get_option( 'roova_setup_version' ) === ROOVA_VERSION ) {
		return;
	}

	roova_ensure_checkout_page();
	roova_ensure_tax_rates();

	update_option( 'roova_setup_version', ROOVA_VERSION );
}
add_action( 'admin_init', 'roova_maybe_run_store_setup' );
add_action( 'after_switch_theme', 'roova_ensure_tax_rates' );

/* -------------------------------------------------------------------------
 * Routing
 * ---------------------------------------------------------------------- */

/**
 * Render checkout, order-pay and order-received through the theme's document.
 *
 * @param string $template Template path.
 * @return string
 */
function roova_checkout_template( $template ) {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return $template;
	}

	/**
	 * Filter whether the theme takes over the checkout page.
	 *
	 * @param bool $take_over Default true.
	 */
	if ( ! apply_filters( 'roova_use_checkout_template', true ) ) {
		return $template;
	}

	$found = locate_template( array( 'checkout.php' ) );

	return $found ? $found : $template;
}
add_filter( 'template_include', 'roova_checkout_template', 100 );

/**
 * Marker put on WooCommerce's "… has been added to your cart." notice.
 *
 * Tagging it at the source is the only exact way to find it again: notices are
 * stored as rendered HTML with the product name already in them, so matching on
 * the wording would break in every language but this one.
 */
const ROOVA_CART_NOTICE_FLAG = 'roova-cart-added';

/**
 * Tag the add-to-cart notice so checkout can recognise it later.
 *
 * `wc_add_to_cart_message_html` fires for that notice and nothing else.
 *
 * @param string $message Notice HTML.
 * @return string
 */
function roova_tag_add_to_cart_notice( $message ) {
	return '<span class="' . ROOVA_CART_NOTICE_FLAG . '"></span>' . $message;
}
add_filter( 'wc_add_to_cart_message_html', 'roova_tag_add_to_cart_notice' );

/**
 * Drop "… has been added to your cart." on the checkout page.
 *
 * The order summary is right there listing every room, so the notice says
 * nothing the page is not already saying — it only pushes the form down. It is
 * left alone everywhere else, where it is the confirmation that the room landed
 * in the cart.
 *
 * Notices queued by checkout itself are added while the page renders, after this
 * has run, so they are untouched.
 */
function roova_hide_add_to_cart_notice_on_checkout() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || ! WC()->session ) {
		return;
	}

	$notices = wc_get_notices();
	if ( empty( $notices['success'] ) ) {
		return;
	}

	$keep = array();
	foreach ( $notices['success'] as $notice ) {
		$message = is_array( $notice ) && isset( $notice['notice'] ) ? $notice['notice'] : (string) $notice;

		if ( false === strpos( $message, ROOVA_CART_NOTICE_FLAG ) ) {
			$keep[] = $notice;
		}
	}

	if ( count( $keep ) === count( $notices['success'] ) ) {
		return;
	}

	$notices['success'] = $keep;
	wc_set_notices( $notices );
}
add_action( 'template_redirect', 'roova_hide_add_to_cart_notice_on_checkout', 20 );

/**
 * A room is a night in a building — it never ships.
 *
 * Without this WooCommerce would ask for a shipping address and a shipping
 * method, neither of which the checkout design has anywhere to put.
 *
 * @param bool $needs_shipping Whether the cart needs shipping.
 * @return bool
 */
function roova_cart_needs_shipping( $needs_shipping ) {
	if ( ! $needs_shipping || ! WC()->cart ) {
		return $needs_shipping;
	}

	foreach ( WC()->cart->get_cart() as $item ) {
		if ( empty( $item['roova_booking'] ) ) {
			return $needs_shipping; // Something else is in the cart; leave Woo alone.
		}
	}

	return false;
}
add_filter( 'woocommerce_cart_needs_shipping', 'roova_cart_needs_shipping' );

/* -------------------------------------------------------------------------
 * Fields
 * ---------------------------------------------------------------------- */

/**
 * Reduce checkout to the five things a room booking actually needs.
 *
 * A stay has no delivery address, so every address, company, country and state
 * field goes.
 *
 * The name is WooCommerce's own `billing_first_name` / `billing_last_name` pair
 * rather than a single custom field. That is what makes the form fill itself in
 * for a signed-in member: `WC_Checkout::get_value()` looks for a matching getter
 * on the customer object, and there is one for every key here — a custom
 * `billing_full_name` had none, so it always rendered empty however much the
 * store knew about the guest.
 *
 * @param array $fields Checkout fields.
 * @return array
 */
function roova_checkout_fields( $fields ) {
	$fields['billing'] = array(
		'billing_first_name' => array(
			'label'        => __( 'First name', 'roova' ),
			'placeholder'  => __( 'Thaer', 'roova' ),
			'required'     => true,
			'class'        => array( 'roova-field--half' ),
			'autocomplete' => 'given-name',
			'priority'     => 10,
		),
		'billing_last_name'  => array(
			'label'        => __( 'Last name', 'roova' ),
			'placeholder'  => __( 'Ahmad', 'roova' ),
			'required'     => true,
			'class'        => array( 'roova-field--half' ),
			'autocomplete' => 'family-name',
			'priority'     => 20,
		),
		'billing_phone'      => array(
			'label'        => __( 'Phone number', 'roova' ),
			'placeholder'  => __( '+60 12 345 6789', 'roova' ),
			'required'     => true,
			'type'         => 'tel',
			'validate'     => array( 'phone' ),
			'class'        => array( 'roova-field--half' ),
			'autocomplete' => 'tel',
			'priority'     => 30,
		),
		'billing_email'      => array(
			'label'        => __( 'Email address', 'roova' ),
			'placeholder'  => __( 'you@example.com', 'roova' ),
			'required'     => true,
			'type'         => 'email',
			'validate'     => array( 'email' ),
			'class'        => array( 'roova-field--half' ),
			'autocomplete' => 'email',
			'priority'     => 40,
		),
	);

	$fields['shipping'] = array();

	if ( isset( $fields['order']['order_comments'] ) ) {
		$fields['order']['order_comments']['label']       = __( 'Notes about your stay', 'roova' );
		$fields['order']['order_comments']['placeholder']  = __( 'Late check-in around 11pm, high floor if possible, extra pillows…', 'roova' );
		$fields['order']['order_comments']['label_class'] = array();
	}

	return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'roova_checkout_fields', 20 );

/**
 * Stand a country in for the one the form no longer asks for.
 *
 * Taxes, gateways and the order screen all expect a billing country; with no
 * field to fill it, the store's own country is the honest answer.
 *
 * The name used to be split back into a pair here, from a single "Full name"
 * box. It is two fields now, so there is nothing left to split.
 *
 * @param array $data Posted data.
 * @return array
 */
function roova_checkout_posted_data( $data ) {
	if ( empty( $data['billing_country'] ) && function_exists( 'WC' ) && WC()->countries ) {
		$data['billing_country'] = WC()->countries->get_base_country();
	}

	return $data;
}
add_filter( 'woocommerce_checkout_posted_data', 'roova_checkout_posted_data' );

/**
 * Keep the guest's name on the order as one line.
 *
 * @param WC_Order $order Order.
 * @param array    $data  Posted data.
 */
function roova_checkout_create_order( $order, $data ) {
	$first = isset( $data['billing_first_name'] ) ? $data['billing_first_name'] : '';
	$last  = isset( $data['billing_last_name'] ) ? $data['billing_last_name'] : '';
	$name  = trim( $first . ' ' . $last );

	if ( $name ) {
		$order->update_meta_data( '_roova_guest_name', sanitize_text_field( $name ) );
	}
}
add_action( 'woocommerce_checkout_create_order', 'roova_checkout_create_order', 10, 2 );

/**
 * The booking terms have to be accepted, whether or not a terms page is set.
 *
 * WooCommerce only validates its own checkbox when a terms page exists, and
 * the design shows the checkbox regardless — so cover the other case here
 * rather than let an unticked box through.
 *
 * @param array    $data   Posted data.
 * @param WP_Error $errors Errors.
 */
function roova_validate_checkout_terms( $data, $errors ) {
	if ( function_exists( 'wc_terms_and_conditions_checkbox_enabled' ) && wc_terms_and_conditions_checkbox_enabled() ) {
		return; // WooCommerce is already checking.
	}

	if ( empty( $data['terms'] ) ) {
		$errors->add( 'terms', __( 'Please accept the booking terms to continue.', 'roova' ) );
	}
}
add_action( 'woocommerce_after_checkout_validation', 'roova_validate_checkout_terms', 10, 2 );

/* -------------------------------------------------------------------------
 * Live updates
 * ---------------------------------------------------------------------- */

/**
 * Refresh the totals block along with the rest of the order review.
 *
 * WooCommerce replaces `.woocommerce-checkout-review-order-table` and
 * `.woocommerce-checkout-payment` after every update. The totals sit outside
 * both — below the coupon row, which must stay put so WooCommerce's own
 * handler keeps working — so they are registered as a fragment of their own.
 *
 * @param array $fragments Fragments.
 * @return array
 */
function roova_order_review_fragments( $fragments ) {
	ob_start();
	roova_checkout_totals();
	$fragments['.roova-summary__totals'] = ob_get_clean();

	return $fragments;
}
add_filter( 'woocommerce_update_order_review_fragments', 'roova_order_review_fragments' );

/* -------------------------------------------------------------------------
 * Removing a room from the summary
 * ---------------------------------------------------------------------- */

/**
 * Take a room out of the cart from the checkout summary.
 *
 * The cart object belongs to this visitor's session, so removing by key can
 * only ever touch their own line — the key itself is shared between guests
 * booking the same room for the same dates. Freeing the hold is not done here:
 * `woocommerce_cart_item_removed` already runs Roova_Holds::on_cart_item_removed(),
 * which is session-scoped for the same reason.
 */
function roova_ajax_remove_cart_item() {
	roova_check_ajax_nonce();

	$key = isset( $_POST['cart_item_key'] ) ? sanitize_text_field( wp_unslash( $_POST['cart_item_key'] ) ) : '';

	if ( ! $key || ! WC()->cart ) {
		wp_send_json_error( array( 'message' => __( 'That room is no longer in your booking.', 'roova' ) ), 400 );
	}

	$item = WC()->cart->get_cart_item( $key );
	if ( ! $item ) {
		wp_send_json_error( array( 'message' => __( 'That room is no longer in your booking.', 'roova' ) ), 404 );
	}

	$name = $item['data'] ? $item['data']->get_name() : __( 'That room', 'roova' );

	WC()->cart->remove_cart_item( $key );
	WC()->cart->calculate_totals();

	/*
	 * No notice on the way out. An empty checkout reloads into the cart, which
	 * is the Blocks cart — it renders client-side and never prints WooCommerce's
	 * stored notices, so one added here would sit in the session and surface on
	 * whatever classic page the guest opened next.
	 */
	wp_send_json_success(
		array(
			'name'  => $name,
			'empty' => WC()->cart->is_empty(),
		)
	);
}
add_action( 'wp_ajax_roova_remove_cart_item', 'roova_ajax_remove_cart_item' );
add_action( 'wp_ajax_nopriv_roova_remove_cart_item', 'roova_ajax_remove_cart_item' );

/**
 * Put a removed room back.
 *
 * WooCommerce keeps the line in `removed_cart_contents` until the session ends,
 * and restoring it fires `woocommerce_cart_item_restored`, where the theme
 * re-places the hold. That can fail — the dates may have gone to someone else in
 * the meantime — and the failure arrives as a notice rather than a return value,
 * so it is read back out of the notice store and handed to the page.
 */
function roova_ajax_restore_cart_item() {
	roova_check_ajax_nonce();

	$key = isset( $_POST['cart_item_key'] ) ? sanitize_text_field( wp_unslash( $_POST['cart_item_key'] ) ) : '';

	if ( ! $key || ! WC()->cart ) {
		wp_send_json_error( array( 'message' => __( 'That room could not be put back.', 'roova' ) ), 400 );
	}

	WC()->cart->restore_cart_item( $key );

	if ( wc_notice_count( 'error' ) > 0 ) {
		$errors = wc_get_notices( 'error' );
		wc_clear_notices();

		$message = isset( $errors[0]['notice'] ) ? $errors[0]['notice'] : __( 'That room could not be put back.', 'roova' );

		// The restore left a line with no hold behind it; drop it again.
		WC()->cart->remove_cart_item( $key );

		wp_send_json_error( array( 'message' => wp_strip_all_tags( $message ) ), 409 );
	}

	WC()->cart->calculate_totals();

	wp_send_json_success();
}
add_action( 'wp_ajax_roova_restore_cart_item', 'roova_ajax_restore_cart_item' );
add_action( 'wp_ajax_nopriv_roova_restore_cart_item', 'roova_ajax_restore_cart_item' );

/* -------------------------------------------------------------------------
 * Payment cards
 * ---------------------------------------------------------------------- */

/**
 * The icon shown on a payment card.
 *
 * Matching is by gateway ID only. An unknown gateway gets the neutral card
 * icon rather than a guess — the same rule the amenity icons follow.
 *
 * @param WC_Payment_Gateway $gateway Gateway.
 * @return string Icon slug from the theme's library.
 */
function roova_payment_icon( $gateway ) {
	$map = array(
		'bacs'          => 'bank',
		'fpx'           => 'bank',
		'billplz'       => 'bank',
		'toyyibpay'     => 'bank',
		'cheque'        => 'no-fees',
		'cod'           => 'building',
		'roova_hotel'   => 'building',
		'paypal'        => 'wallet',
		'ppcp-gateway'  => 'wallet',
		'grabpay'       => 'wallet',
		'tng'           => 'wallet',
		'boost'         => 'wallet',
		'shopeepay'     => 'wallet',
		'ewallet'       => 'wallet',
		'stripe'        => 'credit-card',
		'stripe_cc'     => 'credit-card',
		'card'          => 'credit-card',
	);

	$id   = $gateway->id;
	$icon = isset( $map[ $id ] ) ? $map[ $id ] : 'credit-card';

	/**
	 * Filter the icon slug used for a payment gateway.
	 *
	 * @param string             $icon    Icon slug.
	 * @param WC_Payment_Gateway $gateway Gateway.
	 */
	return apply_filters( 'roova_payment_icon', $icon, $gateway );
}

/**
 * The small grey note under a payment card's title.
 *
 * Nothing in WooCommerce holds a one-line summary — the description is already
 * used for the detail line the selected card expands — so this is empty unless
 * a site fills it in through the filter.
 *
 * @param WC_Payment_Gateway $gateway Gateway.
 * @return string
 */
function roova_payment_note( $gateway ) {
	/**
	 * Filter the short note shown under a payment method's title.
	 *
	 * @param string             $note    Note text.
	 * @param WC_Payment_Gateway $gateway Gateway.
	 */
	return (string) apply_filters( 'roova_payment_note', '', $gateway );
}

/**
 * The optional gold pill on a payment card ("Popular", "Instant"…).
 *
 * @param WC_Payment_Gateway $gateway Gateway.
 * @return string
 */
function roova_payment_badge( $gateway ) {
	/**
	 * Filter the badge shown on a payment method.
	 *
	 * @param string             $badge   Badge text.
	 * @param WC_Payment_Gateway $gateway Gateway.
	 */
	return (string) apply_filters( 'roova_payment_badge', '', $gateway );
}

/**
 * A button label with the live total on the end: "Place order — RM120.00".
 *
 * It has to be a plain string rather than markup. WooCommerce's checkout script
 * rewrites the button with `.text()` every time a payment method is chosen,
 * reading either the gateway's own `data-order_button_text` or the button's
 * `data-value` — so the total has to be part of both, and the arrow beside it is
 * drawn by CSS, where `.text()` cannot reach it.
 *
 * @param string $text Label without the total.
 * @return string
 */
function roova_place_order_label( $text ) {
	$text = trim( (string) $text );

	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return $text;
	}

	$total = wp_strip_all_tags( WC()->cart->get_total() );
	if ( ! $total || ! $text ) {
		return $text;
	}

	return sprintf(
		/* translators: 1: button label, e.g. Place order, 2: order total */
		__( '%1$s — %2$s', 'roova' ),
		$text,
		$total
	);
}

/* -------------------------------------------------------------------------
 * Holds
 * ---------------------------------------------------------------------- */

/**
 * Seconds until the first of this visitor's holds expires.
 *
 * @return int Seconds, or 0 when nothing is held.
 */
function roova_checkout_hold_seconds() {
	if ( ! class_exists( 'Roova_Holds' ) ) {
		return 0;
	}

	$expiry = Roova_Holds::session_expiry();
	if ( ! $expiry ) {
		return 0;
	}

	return max( 0, strtotime( $expiry ) - strtotime( current_time( 'mysql' ) ) );
}

/* -------------------------------------------------------------------------
 * Markup
 * ---------------------------------------------------------------------- */

/**
 * The checkout header: wordmark on the left, a reassurance on the right.
 *
 * Deliberately not the site header — no menu, no cart link, nothing to click
 * away from a booking that is one button from being made.
 */
function roova_checkout_header() {
	?>
	<header class="roova-checkout__header">
		<a class="roova-checkout__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php echo roova_wordmark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
		</a>

		<p class="roova-checkout__secure">
			<?php roova_the_icon( 'lock', 14 ); ?>
			<?php echo esc_html( roova_option( 'checkout_secure_label', __( 'Secure booking', 'roova' ) ) ); ?>
		</p>
	</header>
	<?php
}

/**
 * The short photo band under the header.
 *
 * @param string $title Heading.
 * @param string $sub   Line under the heading.
 */
function roova_checkout_banner( $title, $sub = '' ) {
	$image_id = (int) roova_option( 'checkout_banner_image', 0 );
	?>
	<div class="roova-checkout__banner">
		<div class="roova-checkout__banner-media">
			<?php roova_background_image( $image_id, 'checkout.jpg', true ); ?>
		</div>
		<div class="roova-checkout__banner-scrim" aria-hidden="true"></div>

		<div class="roova-checkout__banner-inner">
			<p class="roova-checkout__eyebrow">
				<span class="roova-checkout__rule" aria-hidden="true"></span>
				<?php echo esc_html( roova_option( 'checkout_eyebrow', __( 'Secure checkout', 'roova' ) ) ); ?>
			</p>
			<h1 class="roova-checkout__title"><?php echo esc_html( $title ); ?></h1>
			<?php if ( $sub ) : ?>
				<p class="roova-checkout__sub"><?php echo esc_html( $sub ); ?></p>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * "2 rooms held for you — complete your details to confirm."
 *
 * @return string
 */
function roova_checkout_banner_sub() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return '';
	}

	$rooms = (int) WC()->cart->get_cart_contents_count();
	if ( ! $rooms ) {
		return '';
	}

	return sprintf(
		/* translators: %s: number of rooms, already pluralised */
		__( '%s held for you — complete your details to confirm.', 'roova' ),
		sprintf(
			/* translators: %d: number of rooms */
			_n( '%d room', '%d rooms', $rooms, 'roova' ),
			$rooms
		)
	);
}

/**
 * One field, in the design's field shell: caption above, borderless input below.
 *
 * The wrapper keeps WooCommerce's own `form-row` classes so the error styling
 * checkout.js applies still lands somewhere sensible.
 *
 * @param string $key   Field key, e.g. billing_email.
 * @param array  $field Field definition.
 * @param string $value Current value.
 */
function roova_checkout_field( $key, $field, $value = '' ) {
	$field = wp_parse_args(
		$field,
		array(
			'type'         => 'text',
			'label'        => '',
			'placeholder'  => '',
			'required'     => false,
			'class'        => array(),
			'autocomplete' => '',
			'maxlength'    => '',
			'rows'         => 3,
		)
	);

	/*
	 * The shell holds a plain input or a textarea. Anything else — a select, a
	 * country picker, a checkbox a plugin has added — goes back to WooCommerce,
	 * which knows how to draw it. Better a field that looks out of place than
	 * one the guest cannot fill in.
	 */
	if ( ! in_array( $field['type'], array( 'text', 'tel', 'email', 'password', 'number', 'url', 'textarea' ), true ) ) {
		woocommerce_form_field( $key, $field, $value );
		return;
	}

	// get_value() answers null for a field nothing has filled in yet.
	$value = (string) $value;

	$classes = array_merge( array( 'form-row', 'roova-field' ), (array) $field['class'] );
	if ( $field['required'] ) {
		$classes[] = 'validate-required';
	}

	$attributes = array(
		'name'  => $key,
		'id'    => $key,
		'class' => 'roova-field__input',
	);

	if ( $field['placeholder'] ) {
		$attributes['placeholder'] = $field['placeholder'];
	}
	if ( $field['autocomplete'] ) {
		$attributes['autocomplete'] = $field['autocomplete'];
	}
	if ( $field['maxlength'] ) {
		$attributes['maxlength'] = $field['maxlength'];
	}
	if ( $field['required'] ) {
		$attributes['aria-required'] = 'true';
	}

	$attribute_html = '';
	foreach ( $attributes as $name => $attribute ) {
		$attribute_html .= sprintf( ' %s="%s"', esc_attr( $name ), esc_attr( $attribute ) );
	}
	?>
	<p class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" id="<?php echo esc_attr( $key ); ?>_field" data-roova-field="<?php echo esc_attr( $key ); ?>">
		<label class="roova-field__shell" for="<?php echo esc_attr( $key ); ?>">
			<span class="roova-field__label">
				<?php echo esc_html( $field['label'] ); ?>
				<?php if ( $field['required'] ) : ?>
					<abbr class="roova-field__required" title="<?php esc_attr_e( 'Required', 'roova' ); ?>">*</abbr>
				<?php endif; ?>
			</span>

			<?php if ( 'textarea' === $field['type'] ) : ?>
				<textarea rows="<?php echo absint( $field['rows'] ); ?>"<?php echo $attribute_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>><?php echo esc_textarea( $value ); ?></textarea>
			<?php else : ?>
				<input type="<?php echo esc_attr( $field['type'] ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php echo $attribute_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?> />
			<?php endif; ?>
		</label>

		<span class="roova-field__error" data-roova-error="<?php echo esc_attr( $key ); ?>" role="alert"></span>
	</p>
	<?php
}

/**
 * One room in the order summary.
 *
 * @param string $cart_item_key Cart item key.
 * @param array  $cart_item     Cart item.
 */
function roova_checkout_summary_item( $cart_item_key, $cart_item ) {
	$product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
	if ( ! $product ) {
		return;
	}

	$quantity = (int) $cart_item['quantity'];
	$booking  = ! empty( $cart_item['roova_booking'] ) ? $cart_item['roova_booking'] : array();

	$remove_label = sprintf(
		/* translators: %s: room name */
		__( 'Remove %s from your booking', 'roova' ),
		$product->get_name()
	);
	?>
	<div class="roova-summary__item" data-roova-item="<?php echo esc_attr( $cart_item_key ); ?>">
		<button type="button" class="roova-summary__remove" data-roova-remove="<?php echo esc_attr( $cart_item_key ); ?>" aria-label="<?php echo esc_attr( $remove_label ); ?>">
			<?php roova_the_icon( 'close', 14 ); ?>
		</button>

		<div class="roova-summary__media">
			<?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ); ?>
			<span class="roova-summary__qty"><?php echo esc_html( $quantity ); ?></span>
		</div>

		<div class="roova-summary__detail">
			<div class="roova-summary__line">
				<span class="roova-summary__name"><?php echo esc_html( $product->get_name() ); ?></span>
				<span class="roova-summary__price">
					<?php echo wp_kses_post( WC()->cart->get_product_subtotal( $product, $quantity ) ); ?>
				</span>
			</div>

			<p class="roova-summary__unit"><?php echo wp_kses_post( roova_checkout_item_unit( $cart_item ) ); ?></p>

			<?php if ( $booking ) : ?>
				<p class="roova-summary__meta"><?php echo esc_html( roova_checkout_item_meta( $booking ) ); ?></p>
			<?php else : ?>
				<?php echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce escapes it. ?>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * "RM55.00 × 1 night" for a room, "RM55.00 × 2" for anything else.
 *
 * @param array $cart_item Cart item.
 * @return string
 */
function roova_checkout_item_unit( $cart_item ) {
	$product = $cart_item['data'];

	if ( empty( $cart_item['roova_booking'] ) ) {
		return sprintf(
			/* translators: 1: unit price, 2: quantity */
			esc_html__( '%1$s × %2$d', 'roova' ),
			wp_kses_post( wc_price( wc_get_price_to_display( $product ) ) ),
			(int) $cart_item['quantity']
		);
	}

	$booking = $cart_item['roova_booking'];
	$nights  = max( 1, (int) $booking['nights'] );
	$rate    = roova_room_rate( (int) $booking['room_id'] );

	return sprintf(
		/* translators: 1: nightly rate, 2: nights, already pluralised */
		esc_html__( '%1$s × %2$s', 'roova' ),
		wp_kses_post( wc_price( $rate ) ),
		esc_html(
			sprintf(
				/* translators: %d: number of nights */
				_n( '%d night', '%d nights', $nights, 'roova' ),
				$nights
			)
		)
	);
}

/**
 * "ARK SERENDAH HOTEL · Check in 26 Aug 2026 — out 27 Aug 2026 · 1 night · 2 adults"
 *
 * @param array $booking Booking payload from the cart line.
 * @return string
 */
function roova_checkout_item_meta( $booking ) {
	$parts = array();

	if ( ! empty( $booking['hotel_id'] ) ) {
		$parts[] = get_the_title( (int) $booking['hotel_id'] );
	}

	$parts[] = sprintf(
		/* translators: 1: check-in date, 2: check-out date */
		__( 'Check in %1$s — out %2$s', 'roova' ),
		roova_format_date( $booking['check_in'] ),
		roova_format_date( $booking['check_out'] )
	);

	$nights  = max( 1, (int) $booking['nights'] );
	$parts[] = sprintf(
		/* translators: %d: number of nights */
		_n( '%d night', '%d nights', $nights, 'roova' ),
		$nights
	);

	$parts[] = Roova_Cart::guests_label( $booking['adults'], $booking['children'] );

	return implode( ' · ', array_filter( $parts ) );
}

/**
 * Subtotal, discounts, fees, tax and the grand total.
 *
 * Re-rendered as a fragment on every checkout update, so a coupon applied in
 * the row above lands here without a page load.
 */
function roova_checkout_totals() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return;
	}

	$cart = WC()->cart;
	?>
	<div class="roova-summary__totals">
		<div class="roova-summary__rows">
			<div class="roova-summary__row">
				<span><?php esc_html_e( 'Subtotal', 'roova' ); ?></span>
				<span><?php wc_cart_totals_subtotal_html(); ?></span>
			</div>

			<?php foreach ( $cart->get_coupons() as $code => $coupon ) : ?>
				<div class="roova-summary__row roova-summary__row--discount cart-discount coupon-<?php echo esc_attr( sanitize_html_class( $code ) ); ?>">
					<span><?php wc_cart_totals_coupon_label( $coupon ); ?></span>
					<span><?php wc_cart_totals_coupon_html( $coupon ); ?></span>
				</div>
			<?php endforeach; ?>

			<?php foreach ( $cart->get_fees() as $fee ) : ?>
				<div class="roova-summary__row">
					<span><?php echo esc_html( $fee->name ); ?></span>
					<span><?php wc_cart_totals_fee_html( $fee ); ?></span>
				</div>
			<?php endforeach; ?>

			<?php if ( $cart->needs_shipping() && $cart->show_shipping() ) : ?>
				<?php // Shipping renders as table rows; give them a table to sit in. ?>
				<table class="roova-summary__shipping"><tbody>
					<?php wc_cart_totals_shipping_html(); ?>
				</tbody></table>
			<?php endif; ?>

			<?php if ( roova_checkout_itemised_taxes() ) : ?>
				<?php foreach ( $cart->get_tax_totals() as $code => $tax ) : ?>
					<div class="roova-summary__row roova-summary__row--tax tax-rate tax-rate-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
						<span><?php echo esc_html( roova_checkout_tax_label( $tax ) ); ?></span>
						<span><?php echo wp_kses_post( $tax->formatted_amount ); ?></span>
					</div>
				<?php endforeach; ?>
			<?php elseif ( roova_checkout_has_taxes() ) : ?>
				<div class="roova-summary__row roova-summary__row--tax tax-total">
					<span><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></span>
					<span><?php wc_cart_totals_taxes_total_html(); ?></span>
				</div>
			<?php else : ?>
				<div class="roova-summary__row">
					<span><?php esc_html_e( 'Taxes & fees', 'roova' ); ?></span>
					<span><?php echo wp_kses_post( roova_checkout_tax_html() ); ?></span>
				</div>
			<?php endif; ?>
		</div>

		<div class="roova-summary__total">
			<span class="roova-summary__total-label"><?php esc_html_e( 'Total', 'roova' ); ?></span>
			<span class="roova-summary__total-value"><?php wc_cart_totals_order_total_html(); ?></span>
		</div>
	</div>
	<?php
}

/**
 * Is there tax to show as a line of its own?
 *
 * False when tax is switched off, and when prices already include it — then the
 * amount is inside the room rate and a separate line would read as an extra
 * charge.
 *
 * @return bool
 */
function roova_checkout_has_taxes() {
	$cart = WC()->cart;

	return $cart && wc_tax_enabled() && ! $cart->display_prices_including_tax() && (float) $cart->get_taxes_total() > 0;
}

/**
 * Should each tax get its own row?
 *
 * Follows WooCommerce → Settings → Tax → "Display tax totals", so a store that
 * asks for one combined line gets one.
 *
 * @return bool
 */
function roova_checkout_itemised_taxes() {
	return roova_checkout_has_taxes()
		&& 'itemized' === get_option( 'woocommerce_tax_total_display' )
		&& WC()->cart->get_tax_totals();
}

/**
 * "Tourism Tax (5%)" — the rate's own name, with what it is actually charging.
 *
 * The percentage is read back from the rate rather than written down here, so
 * editing it in WooCommerce changes the label too.
 *
 * @param stdClass $tax One entry from WC_Cart::get_tax_totals().
 * @return string
 */
function roova_checkout_tax_label( $tax ) {
	$label = isset( $tax->label ) ? $tax->label : __( 'Tax', 'roova' );

	if ( empty( $tax->tax_rate_id ) || ! method_exists( 'WC_Tax', '_get_tax_rate' ) ) {
		return $label;
	}

	$rate = WC_Tax::_get_tax_rate( $tax->tax_rate_id, ARRAY_A );
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
 * What to show on the "Taxes & fees" line when nothing is itemised.
 *
 * Prices that already include tax say so; a store that adds tax on top shows
 * the amount it is adding.
 *
 * @return string
 */
function roova_checkout_tax_html() {
	$cart = WC()->cart;

	if ( wc_tax_enabled() && ! $cart->display_prices_including_tax() ) {
		$tax = (float) $cart->get_taxes_total();
		if ( $tax > 0 ) {
			return wc_price( $tax );
		}
	}

	return esc_html__( 'Included', 'roova' );
}

/**
 * The membership invitation under "Payment options", for a guest booking
 * without an account.
 *
 * Nothing here interrupts the booking: it is a link away, not a step, and the
 * "Create an account" tickbox in Guest information is still the one-click way to
 * do the same thing without leaving the page. A member sees none of it — they
 * already have what it is offering.
 */
function roova_checkout_signup_cta() {
	if ( is_user_logged_in() || ! function_exists( 'roova_registration_open' ) || ! roova_registration_open() ) {
		return;
	}

	/*
	 * Back to checkout afterwards, so signing up costs the guest nothing but a
	 * detour — the cart, and the holds behind it, are the same session's.
	 */
	$url = roova_signup_url( wc_get_checkout_url() );

	// The default is repeated in inc/customizer.php — see roova_option().
	$text = roova_option( 'checkout_signup_text', __( 'Sign up, become a member and get rewards', 'roova' ) );

	if ( ! $text ) {
		return;
	}
	?>
	<section class="roova-checkout__section roova-checkout__signup">
		<a class="roova-checkout__signup-link" href="<?php echo esc_url( $url ); ?>">
			<?php roova_the_icon( 'tag', 20 ); ?>
			<span class="roova-checkout__signup-text"><?php echo esc_html( $text ); ?></span>
			<?php roova_the_icon( 'arrow-right', 17 ); ?>
		</a>
	</section>
	<?php
}

/**
 * The countdown under the summary.
 *
 * The clock is real: it counts down the earliest expiry among this visitor's
 * holds, which is when the rooms go back on sale.
 */
function roova_checkout_rate_hold() {
	$seconds = roova_checkout_hold_seconds();
	if ( $seconds < 1 ) {
		return;
	}
	?>
	<p class="roova-summary__hold" data-roova-hold="<?php echo esc_attr( $seconds ); ?>">
		<span class="roova-summary__dot" aria-hidden="true"></span>
		<span>
			<?php
			printf(
				/* translators: %s: countdown, e.g. 9:42 */
				esc_html__( 'Rate held for %s', 'roova' ),
				'<span data-roova-hold-time>' . esc_html( roova_format_countdown( $seconds ) ) . '</span>'
			);
			?>
		</span>
	</p>
	<?php
}

/**
 * Seconds as m:ss.
 *
 * @param int $seconds Seconds.
 * @return string
 */
function roova_format_countdown( $seconds ) {
	$seconds = max( 0, (int) $seconds );

	return sprintf( '%d:%02d', floor( $seconds / 60 ), $seconds % 60 );
}

/**
 * Shown instead of the form when there is nothing to pay for.
 */
function roova_checkout_empty() {
	?>
	<div class="roova-checkout__empty">
		<h2><?php esc_html_e( 'Your booking is empty', 'roova' ); ?></h2>
		<p><?php esc_html_e( 'Nothing is being held right now. Pick your dates and we will find the rooms that are free.', 'roova' ); ?></p>
		<a class="roova-btn" href="<?php echo esc_url( roova_search_url() ); ?>">
			<?php esc_html_e( 'Find a room', 'roova' ); ?>
		</a>
	</div>
	<?php
}
