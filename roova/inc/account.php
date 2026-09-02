<?php
/**
 * My account: the shell, the data behind it, and the forms it posts.
 *
 * WooCommerce's My account page is the URL, the login gate and the endpoint
 * router — none of that is reimplemented here. What the theme replaces is the
 * *dashboard view*: `roova_account_template()` sends it to `account.php`, which
 * prints its own document, because the design's whole header is a wordmark, the
 * member's tier and a way out. Every WooCommerce endpoint underneath it —
 * view-order, lost-password, edit-address, payment-methods, customer-logout —
 * is left exactly as WooCommerce renders it, inside the normal site header, so
 * nothing that has to keep working while signed out is touched.
 *
 * The six tabs are one page: every panel is rendered server-side and
 * `assets/js/account.js` shows one at a time. `?tab=` picks the panel that
 * opens, so a link into "Bookings" works with the script blocked and the URL
 * still means something after a save.
 *
 * Everything on the page is read from the site at render time — orders for the
 * bookings, comments for the reviews, user meta for the saved stays and for the
 * cashback ledger, the user record for the profile. Nothing is transcribed from
 * the design handoff.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * The page
 * ---------------------------------------------------------------------- */

/**
 * Make sure a My account page exists under Pages.
 *
 * The same guarantee the checkout page gets, and for a stronger reason: this
 * page is the whole account feature, the header's "Manage account" button, where
 * a signed-out visitor is sent back to after signing in, and where the email
 * confirmation link finally lands. Without it every one of those is a 404 —
 * WooCommerce will happily build a permalink for a page that has been trashed.
 *
 * A page that already exists is adopted as it stands; its content is never
 * touched, so a site that has customised it keeps what it wrote.
 */
function roova_ensure_account_page() {
	if ( ! function_exists( 'wc_get_page_id' ) ) {
		return;
	}

	$page_id = (int) wc_get_page_id( 'myaccount' );

	if ( $page_id > 0 && 'publish' === get_post_status( $page_id ) ) {
		return;
	}

	// Whatever WooCommerce already points at, then a page at /my-account/.
	$existing = $page_id > 0 ? get_post( $page_id ) : null;
	if ( ! $existing ) {
		$existing = get_page_by_path( 'my-account' );
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

		update_option( 'woocommerce_myaccount_page_id', $existing->ID );
		return;
	}

	$content = '<!-- wp:shortcode -->[woocommerce_my_account]<!-- /wp:shortcode -->';

	// WooCommerce's own helper handles the option and near-duplicates, but it
	// only exists in admin — WP-CLI activates a theme without it.
	if ( function_exists( 'wc_create_page' ) ) {
		wc_create_page( 'my-account', 'woocommerce_myaccount_page_id', __( 'My account', 'roova' ), $content );
		return;
	}

	$new_id = wp_insert_post( array(
		'post_title'     => __( 'My account', 'roova' ),
		'post_name'      => 'my-account',
		'post_status'    => 'publish',
		'post_type'      => 'page',
		'post_content'   => $content,
		'comment_status' => 'closed',
	) );

	if ( $new_id && ! is_wp_error( $new_id ) ) {
		update_option( 'woocommerce_myaccount_page_id', $new_id );
	}
}
add_action( 'after_switch_theme', 'roova_ensure_account_page' );

/* -------------------------------------------------------------------------
 * Routing
 * ---------------------------------------------------------------------- */

/**
 * Is this the account dashboard — the view the theme draws itself?
 *
 * An endpoint (view-order, edit-address, lost-password…) is not: those are
 * WooCommerce's own screens and keep WooCommerce's own templates.
 *
 * @return bool
 */
function roova_is_account_dashboard() {
	if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
		return false;
	}

	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url() ) {
		return false;
	}

	return is_user_logged_in();
}

/**
 * Render the account dashboard through the theme's own document.
 *
 * @param string $template Template path.
 * @return string
 */
function roova_account_template( $template ) {
	if ( ! roova_is_account_dashboard() ) {
		return $template;
	}

	/**
	 * Filter whether the theme takes over the My account dashboard.
	 *
	 * @param bool $take_over Default true.
	 */
	if ( ! apply_filters( 'roova_use_account_template', true ) ) {
		return $template;
	}

	$found = locate_template( array( 'account.php' ) );

	return $found ? $found : $template;
}
add_filter( 'template_include', 'roova_account_template', 100 );

/**
 * The tab that opens.
 *
 * @return string
 */
function roova_account_current_tab() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- this only chooses which panel is shown.
	$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
	$tabs      = roova_account_tabs();

	return isset( $tabs[ $requested ] ) ? $requested : 'profile';
}

/**
 * A link to one tab of the account page.
 *
 * @param string $tab Tab key.
 * @return string
 */
function roova_account_tab_url( $tab ) {
	$url = roova_account_url();

	return 'profile' === $tab ? $url : add_query_arg( 'tab', $tab, $url );
}

/**
 * The tab strip, in order.
 *
 * @return array[] key => label, icon, count (null for no pill).
 */
function roova_account_tabs() {
	$tabs = array(
		'profile'  => array(
			'label' => __( 'Profile', 'roova' ),
			'icon'  => 'user',
			'count' => null,
		),
		'bookings' => array(
			'label' => __( 'Bookings', 'roova' ),
			'icon'  => 'bed-double',
			'count' => count( roova_account_stays() ),
		),
		'reviews'  => array(
			'label' => __( 'Reviews', 'roova' ),
			'icon'  => 'star',
			'count' => count( roova_user_reviews() ),
		),
		'likes'    => array(
			'label' => __( 'Likes', 'roova' ),
			'icon'  => 'heart',
			'count' => roova_likes_count(),
		),
	);

	if ( roova_vip_enabled() ) {
		$tabs['vip'] = array(
			'label' => __( 'VIP', 'roova' ),
			'icon'  => 'crown',
			'count' => null,
		);
	}

	// Last in the strip, as the handoff has it.
	if ( roova_cashback_enabled() ) {
		$tabs['cashback'] = array(
			'label' => __( 'Cashback rewards', 'roova' ),
			'icon'  => 'coins',
			'count' => null,
		);
	}

	/**
	 * Filter the account tabs.
	 *
	 * @param array[] $tabs key => label, icon, count.
	 */
	return apply_filters( 'roova_account_tabs', $tabs );
}

/* -------------------------------------------------------------------------
 * Stays
 * ---------------------------------------------------------------------- */

/**
 * Every stay a member has booked, newest first.
 *
 * One row per **booking line**, not per order: an order carries the payment,
 * a line carries the room and the dates, and the card the design draws is a
 * stay. A Roova cart holds a single booking, so the two are usually the same
 * thing — an order placed by hand in the admin is where they part.
 *
 * The dates come from the line's own `_roova_booking` meta rather than the
 * bookings table: the meta is what the order was placed on, and it survives a
 * booking row being cleaned up years later.
 *
 * @param int $user_id User ID, or 0 for the current user.
 * @return array[]
 */
function roova_account_stays( $user_id = 0 ) {
	$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
	if ( ! $user_id || ! function_exists( 'wc_get_orders' ) ) {
		return array();
	}

	static $cache = array();
	if ( isset( $cache[ $user_id ] ) ) {
		return $cache[ $user_id ];
	}

	$orders = wc_get_orders(
		array(
			'customer_id' => $user_id,
			'limit'       => -1,
			'orderby'     => 'date',
			'order'       => 'DESC',
			'status'      => array_keys( wc_get_order_statuses() ),
		)
	);

	$today = roova_today();
	$stays = array();

	foreach ( $orders as $order ) {
		if ( ! $order instanceof WC_Order ) {
			continue;
		}

		$items    = $order->get_items();
		$bookings = array();

		foreach ( $items as $item_id => $item ) {
			$booking = $item->get_meta( '_roova_booking', true );
			if ( is_array( $booking ) && ! empty( $booking['room_id'] ) ) {
				$bookings[ $item_id ] = array( $item, $booking );
			}
		}

		if ( ! $bookings ) {
			continue;
		}

		$single = 1 === count( $bookings );

		foreach ( $bookings as $item_id => $pair ) {
			list( $item, $booking ) = $pair;

			$room_id  = absint( $booking['room_id'] );
			$hotel_id = roova_get_room_hotel_id( $room_id );

			$check_in  = roova_sanitize_date( isset( $booking['check_in'] ) ? $booking['check_in'] : '' );
			$check_out = roova_sanitize_date( isset( $booking['check_out'] ) ? $booking['check_out'] : '' );

			/*
			 * The whole order total for a single-room booking — that is what the
			 * guest paid, taxes included. A multi-line order falls back to the
			 * line, because no single card can claim the lot.
			 */
			$total = $single ? (float) $order->get_total() : (float) $order->get_line_total( $item, true, false );

			$stays[] = array(
				'key'        => $order->get_id() . '-' . $item_id,
				'order_id'   => $order->get_id(),
				'order'      => $order,
				'item_id'    => (int) $item_id,
				'ref'        => '#' . $order->get_order_number(),
				'room_id'    => $room_id,
				'room'       => $item->get_name(),
				'hotel_id'   => $hotel_id,
				'hotel'      => $hotel_id ? get_the_title( $hotel_id ) : $item->get_name(),
				'hotel_url'  => $hotel_id ? get_permalink( $hotel_id ) : '',
				'check_in'   => $check_in,
				'check_out'  => $check_out,
				'nights'     => roova_nights( $check_in, $check_out ),
				'units'      => max( 1, (int) $item->get_quantity() ),
				'adults'     => isset( $booking['adults'] ) ? (int) $booking['adults'] : 0,
				'children'   => isset( $booking['children'] ) ? (int) $booking['children'] : 0,
				'status'     => roova_account_stay_status( $order, $check_out, $today ),
				'total'      => $total,
				'total_html' => wc_price( $total, array( 'currency' => $order->get_currency() ) ),
				'view_url'   => $order->get_view_order_url(),
			);
		}
	}

	/*
	 * Newest stay first — by the dates of the stay itself, not by when it was
	 * paid for. A trip booked months ahead belongs at the top of the list.
	 */
	usort(
		$stays,
		static function ( $a, $b ) {
			return strcmp( $b['check_in'], $a['check_in'] );
		}
	);

	$cache[ $user_id ] = $stays;

	return $stays;
}

/**
 * Which of the four states a stay is in.
 *
 * The design draws three chips; "payment" is a fourth, and it earns its place —
 * an order that still needs paying is not an upcoming stay, and telling a guest
 * their room is booked when it is one failed payment from being released is the
 * one thing this page must not do.
 *
 * @param WC_Order $order     Order.
 * @param string   $check_out Check-out date.
 * @param string   $today     Today, Y-m-d.
 * @return string upcoming|completed|cancelled|payment.
 */
function roova_account_stay_status( $order, $check_out, $today ) {
	if ( $order->has_status( array( 'cancelled', 'refunded', 'failed' ) ) ) {
		return 'cancelled';
	}

	if ( $order->needs_payment() ) {
		return 'payment';
	}

	if ( $check_out && $check_out <= $today ) {
		return 'completed';
	}

	return 'upcoming';
}

/**
 * How many stays count towards the hero's "stays booked" figure.
 *
 * A cancelled stay did not happen; one still waiting for payment has not
 * happened yet.
 *
 * @param int $user_id User ID, or 0 for the current user.
 * @return int
 */
function roova_account_stay_count( $user_id = 0 ) {
	$count = 0;

	foreach ( roova_account_stays( $user_id ) as $stay ) {
		if ( ! in_array( $stay['status'], array( 'cancelled', 'payment' ), true ) ) {
			$count++;
		}
	}

	return $count;
}

/**
 * Completed stays — what a VIP tier is earned with.
 *
 * @param int $user_id User ID, or 0 for the current user.
 * @return int
 */
function roova_account_completed_count( $user_id = 0 ) {
	$count = 0;

	foreach ( roova_account_stays( $user_id ) as $stay ) {
		if ( 'completed' === $stay['status'] ) {
			$count++;
		}
	}

	/**
	 * Filter the completed-booking count a member's VIP tier is read from.
	 *
	 * @param int $count   Completed stays.
	 * @param int $user_id User ID, or 0 for the current user.
	 */
	return (int) apply_filters( 'roova_account_completed_count', $count, $user_id );
}

/**
 * "Aug 26 — 27, 2026", collapsing whatever the two dates share.
 *
 * @param string $check_in  Check-in date.
 * @param string $check_out Check-out date.
 * @return string
 */
function roova_account_date_range( $check_in, $check_out ) {
	$check_in  = roova_sanitize_date( $check_in );
	$check_out = roova_sanitize_date( $check_out );

	if ( ! $check_in || ! $check_out ) {
		return '';
	}

	$in  = strtotime( $check_in . ' 00:00:00' );
	$out = strtotime( $check_out . ' 00:00:00' );

	if ( gmdate( 'Y', $in ) !== gmdate( 'Y', $out ) ) {
		/* translators: 1: check-in date with year, 2: check-out date with year */
		$format = __( '%1$s — %2$s', 'roova' );
		return sprintf( $format, date_i18n( 'M j, Y', $in ), date_i18n( 'M j, Y', $out ) );
	}

	if ( gmdate( 'm', $in ) === gmdate( 'm', $out ) ) {
		/* translators: 1: "Aug 26", 2: day of the month, 3: year */
		$format = __( '%1$s — %2$s, %3$s', 'roova' );
		return sprintf( $format, date_i18n( 'M j', $in ), date_i18n( 'j', $out ), date_i18n( 'Y', $in ) );
	}

	/* translators: 1: "Jun 12", 2: "Jul 3", 3: year */
	$format = __( '%1$s — %2$s, %3$s', 'roova' );
	return sprintf( $format, date_i18n( 'M j', $in ), date_i18n( 'M j', $out ), date_i18n( 'Y', $in ) );
}

/**
 * "2 adults · 1 child" for a stay.
 *
 * @param array $stay Stay row.
 * @return string
 */
function roova_account_guest_label( $stay ) {
	$parts = array();

	if ( $stay['adults'] > 0 ) {
		$parts[] = sprintf(
			/* translators: %s: number of adults */
			esc_html( _n( '%s adult', '%s adults', $stay['adults'], 'roova' ) ),
			number_format_i18n( $stay['adults'] )
		);
	}

	if ( $stay['children'] > 0 ) {
		$parts[] = sprintf(
			/* translators: %s: number of children */
			esc_html( _n( '%s child', '%s children', $stay['children'], 'roova' ) ),
			number_format_i18n( $stay['children'] )
		);
	}

	return implode( ' · ', $parts );
}

/* -------------------------------------------------------------------------
 * The member
 * ---------------------------------------------------------------------- */

/**
 * The name at the top of the page.
 *
 * @param WP_User $user User.
 * @return string '' when there is no name on file.
 */
function roova_account_full_name( $user ) {
	$name = trim( $user->first_name . ' ' . $user->last_name );

	return $name ? $name : '';
}

/**
 * The letter in the avatar circle.
 *
 * @param WP_User $user User.
 * @return string
 */
function roova_account_initial( $user ) {
	$name = roova_account_full_name( $user );
	if ( ! $name ) {
		$name = $user->display_name;
	}

	$initial = mb_substr( trim( (string) $name ), 0, 1 );

	return $initial ? mb_strtoupper( $initial ) : 'R';
}

/**
 * "Member since 2024 · you@example.com".
 *
 * @param WP_User $user User.
 * @return string
 */
function roova_account_since_line( $user ) {
	$registered = $user->user_registered ? strtotime( $user->user_registered . ' UTC' ) : 0;

	if ( ! $registered ) {
		return $user->user_email;
	}

	return sprintf(
		/* translators: 1: year the member joined, 2: email address */
		__( 'Member since %1$s · %2$s', 'roova' ),
		date_i18n( 'Y', $registered ),
		$user->user_email
	);
}

/**
 * The avatar circle: the member's picture, or their initial on teal.
 *
 * @param WP_User $user User.
 * @param int     $size Pixel size.
 */
function roova_account_avatar( $user, $size = 74 ) {
	/*
	 * The initial is always drawn, and the avatar is laid over it — asked for
	 * with `blank` as its default, so a member with no Gravatar gets a
	 * transparent image and the teal circle underneath is what shows. Nothing
	 * else can tell the two apart: get_avatar_data() reports found_avatar for
	 * every address, real picture or not, so a plain get_avatar() would put
	 * Gravatar's grey mystery figure where the design draws a letter.
	 */
	$avatar = get_avatar( $user->ID, $size * 2, 'blank', '', array( 'class' => 'roova-account__avatar-img' ) );
	?>
	<span class="roova-account__avatar" style="--roova-avatar-size:<?php echo absint( $size ); ?>px" aria-hidden="true">
		<span class="roova-account__avatar-initial"><?php echo esc_html( roova_account_initial( $user ) ); ?></span>
		<?php if ( $avatar ) : ?>
			<?php echo wp_kses_post( $avatar ); ?>
		<?php endif; ?>
	</span>
	<?php
}

/**
 * Is the account's email address a confirmed one?
 *
 * For an account created since email confirmation shipped this is a fact: the
 * member opened a link sent to that address (see inc/verification.php). For one
 * that predates it, the address is still their sign-in ID and the only way they
 * could be reading this page — so it counts, and the filter is there for a site
 * that wants to hold an older account to the newer standard.
 *
 * @param WP_User $user User.
 * @return bool
 */
function roova_account_email_verified( $user ) {
	$verified = function_exists( 'roova_user_is_verified' ) ? roova_user_is_verified( $user->ID ) : true;

	/**
	 * Filter whether the account email shows as verified.
	 *
	 * @param bool    $verified Whether the address has been confirmed.
	 * @param WP_User $user     User.
	 */
	return (bool) apply_filters( 'roova_account_email_verified', $verified, $user );
}

/**
 * When the password was last set, as "4 months ago".
 *
 * The meta only exists once a password has been changed from this page, so the
 * account's own creation date stands in — which is when the password really was
 * set, for every account that has never changed it.
 *
 * @param WP_User $user User.
 * @return string '' when neither date is known.
 */
function roova_account_password_age( $user ) {
	$changed = (int) get_user_meta( $user->ID, 'roova_password_changed', true );

	if ( ! $changed && $user->user_registered ) {
		$changed = strtotime( $user->user_registered . ' UTC' );
	}

	if ( ! $changed ) {
		return '';
	}

	return sprintf(
		/* translators: %s: human-readable time difference, e.g. "4 months" */
		__( 'Last changed %s ago.', 'roova' ),
		human_time_diff( $changed, time() )
	);
}

/**
 * Where "Sign out" goes.
 *
 * @return string
 */
function roova_account_logout_url() {
	$redirect = roova_signin_url();

	if ( function_exists( 'wc_logout_url' ) ) {
		return wc_logout_url( $redirect );
	}

	return wp_logout_url( $redirect );
}

/* -------------------------------------------------------------------------
 * Forms
 *
 * The profile form and the review form both post back to the account page and
 * are handled before it renders. Errors and typed values go into the same
 * static the sign-in and sign-up pages use — the field shell is shared, so the
 * state behind it is too, and neither survives the redirect a success ends in.
 * ---------------------------------------------------------------------- */

/**
 * Run whichever account form was posted.
 */
function roova_account_handle_forms() {
	if ( ! roova_is_account_dashboard() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the action only selects a handler; each verifies its own nonce.
	$action = isset( $_POST['roova_account_action'] ) ? sanitize_key( wp_unslash( $_POST['roova_account_action'] ) ) : '';

	if ( 'profile' === $action ) {
		roova_account_save_profile();
	} elseif ( 'review' === $action ) {
		roova_account_save_review();
	}
}
add_action( 'template_redirect', 'roova_account_handle_forms', 5 );

/**
 * Was a form just saved? Reads the flag the redirect carries back.
 *
 * @param string $which 'profile' or 'review'.
 * @return bool
 */
function roova_account_just_saved( $which ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a display flag on a GET after a redirect; it changes nothing.
	return isset( $_GET['roova_saved'] ) && sanitize_key( wp_unslash( $_GET['roova_saved'] ) ) === $which;
}

/**
 * Send the member back to a tab with a "saved" flag on it.
 *
 * Post/redirect/get: a reload must never repost a password change or a review.
 *
 * @param string $tab   Tab key.
 * @param string $saved Flag value, or '' for none.
 */
function roova_account_redirect( $tab, $saved = '' ) {
	$url = roova_account_tab_url( $tab );

	if ( $saved ) {
		$url = add_query_arg( 'roova_saved', $saved, $url );
	}

	wp_safe_redirect( $url );
	exit;
}

/**
 * Save the profile: names, phone, and the password panel when it is open.
 */
function roova_account_save_profile() {
	if ( ! isset( $_POST['roova_profile_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['roova_profile_nonce'] ) ), 'roova_account_profile' ) ) {
		roova_auth_add_error( 'form', __( 'That form had expired. Please try again.', 'roova' ) );
		return;
	}

	$user = wp_get_current_user();

	$first = isset( $_POST['roova_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['roova_first_name'] ) ) : '';
	$last  = isset( $_POST['roova_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['roova_last_name'] ) ) : '';
	$phone = isset( $_POST['roova_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['roova_phone'] ) ) : '';

	roova_auth_set_value( 'first_name', $first );
	roova_auth_set_value( 'last_name', $last );
	roova_auth_set_value( 'phone', $phone );

	if ( mb_strlen( trim( $first ) ) < 2 ) {
		roova_auth_add_error( 'first_name', __( 'Enter your first name.', 'roova' ) );
	}

	if ( mb_strlen( trim( $last ) ) < 2 ) {
		roova_auth_add_error( 'last_name', __( 'Enter your last name.', 'roova' ) );
	}

	// The phone number is the only way the hotel reaches a guest on the day, but
	// an account that never had one should not be blocked from fixing a name.
	if ( '' !== trim( $phone ) && ! roova_auth_valid_phone( $phone ) ) {
		roova_auth_add_error( 'phone', __( 'Enter a valid phone number.', 'roova' ) );
	}

	$current = isset( $_POST['roova_current_password'] ) ? (string) wp_unslash( $_POST['roova_current_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised -- checked against the stored hash, never stored or printed.
	$new     = isset( $_POST['roova_new_password'] ) ? (string) wp_unslash( $_POST['roova_new_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised -- hashed by WordPress, never printed.
	$confirm = isset( $_POST['roova_confirm_password'] ) ? (string) wp_unslash( $_POST['roova_confirm_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised -- compared only.

	$changing = ( '' !== $current || '' !== $new || '' !== $confirm );

	if ( $changing ) {
		// Remembered so the panel reopens on the redraw with its error showing.
		roova_auth_set_value( 'password_panel', '1' );

		if ( '' === $current ) {
			roova_auth_add_error( 'current_password', __( 'Enter your current password.', 'roova' ) );
		} elseif ( ! wp_check_password( $current, $user->user_pass, $user->ID ) ) {
			roova_auth_add_error( 'current_password', __( 'That is not your current password.', 'roova' ) );
		}

		if ( ! roova_auth_valid_password( $new ) ) {
			roova_auth_add_error( 'new_password', __( 'New password needs 8+ characters with a letter and a number.', 'roova' ) );
		}

		if ( '' === $confirm || $confirm !== $new ) {
			roova_auth_add_error( 'confirm_password', __( 'New passwords don\'t match.', 'roova' ) );
		}
	}

	if ( roova_auth_get_errors()->has_errors() ) {
		return;
	}

	wp_update_user(
		array(
			'ID'         => $user->ID,
			'first_name' => $first,
			'last_name'  => $last,
		)
	);

	// The same keys checkout prefills itself from — see inc/checkout.php.
	update_user_meta( $user->ID, 'billing_first_name', $first );
	update_user_meta( $user->ID, 'billing_last_name', $last );
	update_user_meta( $user->ID, 'billing_phone', $phone );

	if ( $changing ) {
		wp_set_password( $new, $user->ID );
		update_user_meta( $user->ID, 'roova_password_changed', time() );

		/*
		 * wp_set_password() destroys every session, this one included. Signing
		 * the member back in is the difference between "saved" and being thrown
		 * out of the page they were standing on.
		 */
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );
	}

	/**
	 * Fires after a member saves their profile.
	 *
	 * @param int   $user_id User ID.
	 * @param array $data    first_name, last_name, phone.
	 */
	do_action( 'roova_account_profile_saved', $user->ID, array(
		'first_name' => $first,
		'last_name'  => $last,
		'phone'      => $phone,
	) );

	roova_account_redirect( 'profile', 'profile' );
}

/**
 * Store a review posted from the Reviews tab.
 */
function roova_account_save_review() {
	if ( ! isset( $_POST['roova_review_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['roova_review_nonce'] ) ), 'roova_account_review' ) ) {
		roova_auth_add_error( 'form', __( 'That form had expired. Please try again.', 'roova' ) );
		return;
	}

	$hotel_id = isset( $_POST['roova_hotel_id'] ) ? absint( $_POST['roova_hotel_id'] ) : 0;

	// `rating` rather than `roova_rating`: WooCommerce reads that exact key when
	// it decides whether a review may be posted. See inc/reviews.php.
	$rating = isset( $_POST['rating'] ) ? absint( $_POST['rating'] ) : 0;
	$body   = isset( $_POST['roova_review_body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['roova_review_body'] ) ) : '';

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised -- each value is cast to an int below.
	$posted_scores = isset( $_POST['roova_subscore'] ) && is_array( $_POST['roova_subscore'] ) ? wp_unslash( $_POST['roova_subscore'] ) : array();

	$subscores = array();
	foreach ( roova_review_subscores() as $key => $label ) {
		$subscores[ $key ] = isset( $posted_scores[ $key ] ) ? absint( $posted_scores[ $key ] ) : 0;
	}

	roova_auth_set_value( 'review_hotel', (string) $hotel_id );
	roova_auth_set_value( 'review_body', $body );
	roova_auth_set_value( 'review_rating', (string) $rating );

	$result = roova_submit_review(
		array(
			'hotel_id'  => $hotel_id,
			'rating'    => $rating,
			'body'      => $body,
			'subscores' => $subscores,
		)
	);

	if ( is_wp_error( $result ) ) {
		$code = $result->get_error_code();
		$field = in_array( $code, array( 'rating', 'body' ), true ) ? 'review_' . $code : 'form';

		roova_auth_add_error( $field, $result->get_error_message() );
		return;
	}

	roova_account_redirect( 'reviews', 'review' );
}
