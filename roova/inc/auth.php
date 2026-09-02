<?php
/**
 * Sign in and sign up: the theme's own account pages.
 *
 * WordPress and WooCommerce both ship login forms — wp-login.php and the
 * "My account" page — and neither can be laid out to the handoff without
 * fighting their markup. So the theme owns the two pages instead: a page each,
 * created on activation, rendered by `template-signin.php` / `template-signup.php`,
 * and posted back to itself. Authentication itself is still core's
 * (`wp_signon`, `wc_create_new_customer`), so nothing here reimplements a
 * password check.
 *
 * Everything a form needs to redraw itself after a failed submit — the errors
 * and what the guest typed — lives in `roova_auth_state()`, filled by the
 * handler on `template_redirect` and read by the template a moment later.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/* -------------------------------------------------------------------------
 * The pages
 * ---------------------------------------------------------------------- */

/**
 * The two pages the theme creates, keyed by the handle used everywhere else.
 *
 * @return array[] key => array( option, slug, title, template ).
 */
function roova_auth_pages() {
	return array(
		'signin' => array(
			'option'   => 'roova_signin_page_id',
			'slug'     => 'sign-in',
			'title'    => __( 'Sign in', 'roova' ),
			'template' => 'template-signin.php',
		),
		'signup' => array(
			'option'   => 'roova_signup_page_id',
			'slug'     => 'sign-up',
			'title'    => __( 'Sign up', 'roova' ),
			'template' => 'template-signup.php',
		),
	);
}

/**
 * Make sure both pages exist and carry their template.
 *
 * A page that is already there is adopted rather than replaced — its content is
 * never touched, because the template ignores it anyway.
 */
function roova_create_auth_pages() {
	foreach ( roova_auth_pages() as $page ) {
		$page_id = (int) get_option( $page['option'] );

		if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
			update_post_meta( $page_id, '_wp_page_template', $page['template'] );
			continue;
		}

		$existing = $page_id ? get_post( $page_id ) : null;
		if ( ! $existing ) {
			$existing = get_page_by_path( $page['slug'] );
		}

		if ( $existing && 'page' === $existing->post_type ) {
			if ( 'trash' === $existing->post_status ) {
				wp_untrash_post( $existing->ID );
			}

			if ( 'publish' !== get_post_status( $existing->ID ) ) {
				wp_update_post( array(
					'ID'          => $existing->ID,
					'post_status' => 'publish',
				) );
			}

			update_post_meta( $existing->ID, '_wp_page_template', $page['template'] );
			update_option( $page['option'], $existing->ID );
			continue;
		}

		$new_id = wp_insert_post( array(
			'post_title'     => $page['title'],
			'post_name'      => $page['slug'],
			'post_status'    => 'publish',
			'post_type'      => 'page',
			'post_content'   => '',
			'comment_status' => 'closed',
		) );

		if ( $new_id && ! is_wp_error( $new_id ) ) {
			update_post_meta( $new_id, '_wp_page_template', $page['template'] );
			update_option( $page['option'], $new_id );
		}
	}
}
add_action( 'after_switch_theme', 'roova_create_auth_pages' );

/**
 * Check again on admin load, once per release.
 *
 * `after_switch_theme` never fires for a theme updated in place, and a page can
 * be trashed long after activation — without one the header's "Sign in" button
 * would point at nothing.
 */
function roova_maybe_create_auth_pages() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( get_option( 'roova_auth_version' ) === ROOVA_VERSION ) {
		return;
	}

	roova_create_auth_pages();
	update_option( 'roova_auth_version', ROOVA_VERSION );
}
add_action( 'admin_init', 'roova_maybe_create_auth_pages' );

/**
 * The ID of one of the auth pages.
 *
 * @param string $which 'signin' or 'signup'.
 * @return int
 */
function roova_auth_page_id( $which ) {
	$pages = roova_auth_pages();
	if ( ! isset( $pages[ $which ] ) ) {
		return 0;
	}

	$page_id = (int) get_option( $pages[ $which ]['option'] );

	/*
	 * Only a page that is actually published counts. get_permalink() builds a
	 * URL for a trashed page just as happily as for a live one, and that URL is
	 * a 404 — which would be the address baked into every confirmation email
	 * the site sends. Callers fall back to WordPress's own login instead, which
	 * always exists. roova_create_auth_pages() reads the option directly, so it
	 * can still find and untrash the page this hides.
	 */
	if ( $page_id && 'publish' !== get_post_status( $page_id ) ) {
		return 0;
	}

	return $page_id;
}

/**
 * Link to one of the auth pages.
 *
 * @param string $which    'signin' or 'signup'.
 * @param string $redirect Where to send the guest afterwards.
 * @return string
 */
function roova_auth_url( $which, $redirect = '' ) {
	$page_id = roova_auth_page_id( $which );
	$url     = $page_id ? get_permalink( $page_id ) : '';

	if ( ! $url ) {
		// No page: fall back to whatever login the site does have.
		$url = 'signup' === $which ? wp_registration_url() : wp_login_url( $redirect );
		return $url;
	}

	if ( $redirect ) {
		$url = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $url );
	}

	return $url;
}

/**
 * The sign-in page.
 *
 * @param string $redirect Where to send the guest afterwards.
 * @return string
 */
function roova_signin_url( $redirect = '' ) {
	return roova_auth_url( 'signin', $redirect );
}

/**
 * The sign-up page.
 *
 * @param string $redirect Where to send the guest afterwards.
 * @return string
 */
function roova_signup_url( $redirect = '' ) {
	return roova_auth_url( 'signup', $redirect );
}

/**
 * Where a signed-in guest goes: WooCommerce's My account page.
 *
 * @return string
 */
function roova_account_url() {
	if ( function_exists( 'wc_get_page_id' ) ) {
		$page_id = (int) wc_get_page_id( 'myaccount' );

		/*
		 * Published, not merely pointed at. `wc_get_page_permalink()` builds a
		 * URL for a page that has been trashed or never published just as
		 * happily as for a live one — and that URL is a 404. It is the address
		 * the header button, the sign-in redirect and the email confirmation
		 * link all end at, so a 404 there is the last thing a member sees after
		 * confirming their address.
		 */
		if ( $page_id > 0 && 'publish' === get_post_status( $page_id ) ) {
			$url = get_permalink( $page_id );
			if ( $url ) {
				return $url;
			}
		}

		// The page is missing or broken — roova_ensure_account_page() puts it
		// back on the next admin load. Home is somewhere; a 404 is not.
		return home_url( '/' );
	}

	// No WooCommerce at all: the profile screen is the only account there is.
	return admin_url( 'profile.php' );
}

/**
 * The "forgot password" link, WooCommerce's when it has one.
 *
 * @return string
 */
function roova_lost_password_url() {
	if ( function_exists( 'wc_lostpassword_url' ) ) {
		$url = wc_lostpassword_url();
		if ( $url ) {
			return $url;
		}
	}

	return wp_lostpassword_url();
}

/**
 * Is this one of the theme's auth pages?
 *
 * Matched on the template rather than the option, so a page the client built by
 * hand and assigned the template still counts.
 *
 * @param string $which '', 'signin' or 'signup'.
 * @return bool
 */
function roova_is_auth_page( $which = '' ) {
	if ( ! is_page() ) {
		return false;
	}

	$pages = roova_auth_pages();

	if ( $which ) {
		return isset( $pages[ $which ] ) && is_page_template( $pages[ $which ]['template'] );
	}

	foreach ( $pages as $page ) {
		if ( is_page_template( $page['template'] ) ) {
			return true;
		}
	}

	return false;
}

/* -------------------------------------------------------------------------
 * Form state
 * ---------------------------------------------------------------------- */

/**
 * Errors and submitted values, shared between the handler and the template.
 *
 * The handler runs on `template_redirect` and the template renders moments
 * later in the same request, so a static is enough — nothing here survives a
 * redirect, and nothing should.
 *
 * @return array
 */
function &roova_auth_state() {
	static $state = null;

	if ( null === $state ) {
		$state = array(
			'errors' => new WP_Error(),
			'values' => array(),
		);
	}

	return $state;
}

/**
 * The errors from this request's submit, if there was one.
 *
 * @return WP_Error
 */
function roova_auth_get_errors() {
	$state = &roova_auth_state();
	return $state['errors'];
}

/**
 * The error message for one field, or ''.
 *
 * @param string $field Field name.
 * @return string
 */
function roova_auth_error( $field ) {
	$errors = roova_auth_get_errors();
	$message = $errors->get_error_message( $field );

	return is_string( $message ) ? $message : '';
}

/**
 * What the guest typed into one field, for redrawing the form.
 *
 * Passwords are never carried back.
 *
 * @param string $field   Field name.
 * @param string $default Value when nothing was submitted.
 * @return string
 */
function roova_auth_value( $field, $default = '' ) {
	$state = &roova_auth_state();

	return isset( $state['values'][ $field ] ) ? (string) $state['values'][ $field ] : $default;
}

/**
 * Record a field error.
 *
 * @param string $field   Field name.
 * @param string $message Message.
 */
function roova_auth_add_error( $field, $message ) {
	$state = &roova_auth_state();
	$state['errors']->add( $field, $message );
}

/**
 * Remember a submitted value.
 *
 * @param string $field Field name.
 * @param string $value Value.
 */
function roova_auth_set_value( $field, $value ) {
	$state = &roova_auth_state();
	$state['values'][ $field ] = $value;
}

/* -------------------------------------------------------------------------
 * Nonces
 *
 * Every form on these pages is posted by somebody who is signed out, and a
 * logged-out nonce is built against "user 0" — except that WooCommerce swaps
 * that for its session customer id, and other plugins filter it too. So the
 * moment a cart session begins or ends between a form being drawn and posted —
 * a room added in another tab is enough, and on older WooCommerce simply
 * touching the cart at all was — the nonce is checked against a different user
 * than the one it was made for, and a perfectly good form comes back as
 * "That form had expired."
 *
 * Pinning the value at both ends makes the nonce depend on the action and the
 * clock alone, which is what WordPress does for anonymous forms by default.
 * ---------------------------------------------------------------------- */

/**
 * Force the "user" behind a logged-out nonce to 0.
 *
 * @param int $uid Incoming user ID.
 * @return int
 */
function roova_auth_pinned_nonce_user( $uid ) {
	unset( $uid );
	return 0;
}

/**
 * Run a callback with the logged-out nonce user pinned.
 *
 * Priority 99, so it is the last word over WooCommerce's own filter at 10.
 *
 * @param callable $callback What to run.
 * @return mixed Whatever the callback returns.
 */
function roova_auth_with_pinned_nonce( $callback ) {
	// A signed-in member's nonce uses their real ID and never consults the
	// filter, so there is nothing to pin and nothing to undo.
	if ( is_user_logged_in() ) {
		return $callback();
	}

	add_filter( 'nonce_user_logged_out', 'roova_auth_pinned_nonce_user', 99 );
	$result = $callback();
	remove_filter( 'nonce_user_logged_out', 'roova_auth_pinned_nonce_user', 99 );

	return $result;
}

/**
 * Print a nonce field for one of the auth forms.
 *
 * @param string $action Nonce action.
 * @param string $name   Field name.
 */
function roova_auth_nonce_field( $action, $name ) {
	roova_auth_with_pinned_nonce(
		static function () use ( $action, $name ) {
			wp_nonce_field( $action, $name );
		}
	);
}

/**
 * Check the nonce one of the auth forms posted.
 *
 * @param string $name   Field name in $_POST.
 * @param string $action Nonce action.
 * @return bool
 */
function roova_auth_verify_nonce( $name, $action ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- this *is* the check.
	$posted = isset( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : '';

	if ( '' === $posted ) {
		return false;
	}

	return (bool) roova_auth_with_pinned_nonce(
		static function () use ( $posted, $action ) {
			return wp_verify_nonce( $posted, $action );
		}
	);
}
/* -------------------------------------------------------------------------
 * Validation rules
 *
 * The same rules the handoff gives the client-side script, so a guest never
 * sees one message in the browser and a different one after the round trip.
 * assets/js/auth.js mirrors them; this copy is the one that decides.
 * ---------------------------------------------------------------------- */

/**
 * Does this look like a phone number?
 *
 * @param string $phone Raw value.
 * @return bool
 */
function roova_auth_valid_phone( $phone ) {
	return (bool) preg_match( '/^[+\d][\d\s-]{7,}$/', trim( $phone ) );
}

/**
 * Is this password long enough, with a letter and a number in it?
 *
 * @param string $password Raw value.
 * @return bool
 */
function roova_auth_valid_password( $password ) {
	return strlen( $password ) >= 8 && preg_match( '/[A-Za-z]/', $password ) && preg_match( '/\d/', $password );
}

/**
 * Where a form sends the guest once it is done.
 *
 * `wp_validate_redirect()` keeps an off-site `redirect_to` from turning the
 * sign-in page into an open redirect.
 *
 * @return string
 */
function roova_auth_redirect_target() {
	$fallback = roova_account_url();

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is checked by the caller; this only reads a destination.
	$requested = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';

	if ( ! $requested ) {
		return $fallback;
	}

	return wp_validate_redirect( $requested, $fallback );
}

/* -------------------------------------------------------------------------
 * Handlers
 * ---------------------------------------------------------------------- */

/**
 * Run whichever form was posted.
 */
function roova_handle_auth_forms() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- the action only selects a handler; each verifies its own nonce.
	$action = isset( $_POST['roova_auth_action'] ) ? sanitize_key( wp_unslash( $_POST['roova_auth_action'] ) ) : '';

	if ( 'signin' === $action ) {
		roova_process_signin();
	} elseif ( 'signup' === $action ) {
		roova_process_signup();
	} elseif ( 'resend' === $action ) {
		roova_process_resend();
	}
}
add_action( 'template_redirect', 'roova_handle_auth_forms', 5 );

/**
 * Sign an existing guest in.
 */
function roova_process_signin() {
	if ( ! roova_auth_verify_nonce( 'roova_signin_nonce', 'roova_signin' ) ) {
		roova_auth_add_error( 'form', __( 'That form had expired. Please try again.', 'roova' ) );
		return;
	}

	$email    = isset( $_POST['roova_email'] ) ? sanitize_text_field( wp_unslash( $_POST['roova_email'] ) ) : '';
	$password = isset( $_POST['roova_password'] ) ? (string) wp_unslash( $_POST['roova_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised -- a password is checked, never stored or printed.
	$remember = ! empty( $_POST['roova_remember'] );

	roova_auth_set_value( 'email', $email );
	roova_auth_set_value( 'remember', $remember ? '1' : '' );

	if ( ! is_email( $email ) ) {
		roova_auth_add_error( 'email', __( 'Enter a valid email address.', 'roova' ) );
	}

	if ( '' === $password ) {
		roova_auth_add_error( 'password', __( 'Enter your password.', 'roova' ) );
	}

	if ( roova_auth_get_errors()->has_errors() ) {
		return;
	}

	$user = wp_signon(
		array(
			'user_login'    => $email,
			'user_password' => $password,
			'remember'      => $remember,
		),
		is_ssl()
	);

	if ( is_wp_error( $user ) ) {
		/*
		 * Core's messages are HTML, name the field that was wrong and link off
		 * to wp-login.php. One plain line covers every "those details are not a
		 * match" case; anything else — a plugin blocking the attempt, say — is
		 * passed through so the guest can act on it.
		 */
		$code = $user->get_error_code();

		if ( in_array( $code, array( 'invalid_username', 'invalid_email', 'incorrect_password', 'empty_password', 'empty_username' ), true ) ) {
			$message = __( 'That email and password do not match an account.', 'roova' );
		} else {
			$message = wp_strip_all_tags( $user->get_error_message() );
		}

		/*
		 * The password was right and the account is real — it is the address
		 * that has not been confirmed. Saying so at field level would bury it
		 * under the password box, and there is something to *do* about it, so
		 * it goes above the form with a button beside it.
		 */
		if ( 'roova_unverified' === $code ) {
			roova_auth_add_error( 'form', $message );
			roova_auth_set_value( 'resend_email', $email );
			return;
		}

		roova_auth_add_error( 'password', $message );
		return;
	}

	wp_safe_redirect( roova_auth_redirect_target() );
	exit;
}

/**
 * Create an account, then sign the new member straight in.
 */
function roova_process_signup() {
	if ( ! roova_auth_verify_nonce( 'roova_signup_nonce', 'roova_signup' ) ) {
		roova_auth_add_error( 'form', __( 'That form had expired. Please try again.', 'roova' ) );
		return;
	}

	if ( ! roova_registration_open() ) {
		roova_auth_add_error( 'form', __( 'New accounts are closed at the moment. Please contact us if you need one.', 'roova' ) );
		return;
	}

	$first   = isset( $_POST['roova_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['roova_first_name'] ) ) : '';
	$last    = isset( $_POST['roova_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['roova_last_name'] ) ) : '';
	$email   = isset( $_POST['roova_email'] ) ? sanitize_text_field( wp_unslash( $_POST['roova_email'] ) ) : '';
	$phone   = isset( $_POST['roova_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['roova_phone'] ) ) : '';
	$pass    = isset( $_POST['roova_password'] ) ? (string) wp_unslash( $_POST['roova_password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised -- hashed by WordPress, never printed.
	$pass2   = isset( $_POST['roova_password_confirm'] ) ? (string) wp_unslash( $_POST['roova_password_confirm'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised -- compared only.
	$terms   = ! empty( $_POST['roova_terms'] );

	foreach ( array(
		'first_name' => $first,
		'last_name'  => $last,
		'email'      => $email,
		'phone'      => $phone,
	) as $field => $value ) {
		roova_auth_set_value( $field, $value );
	}
	roova_auth_set_value( 'terms', $terms ? '1' : '' );

	if ( mb_strlen( trim( $first ) ) < 2 ) {
		roova_auth_add_error( 'first_name', __( 'Enter your first name.', 'roova' ) );
	}

	if ( mb_strlen( trim( $last ) ) < 2 ) {
		roova_auth_add_error( 'last_name', __( 'Enter your last name.', 'roova' ) );
	}

	if ( ! is_email( $email ) ) {
		roova_auth_add_error( 'email', __( 'Enter a valid email address.', 'roova' ) );
	} elseif ( email_exists( $email ) ) {
		roova_auth_add_error( 'email', __( 'An account already uses that email address. Sign in instead.', 'roova' ) );
	}

	if ( ! roova_auth_valid_phone( $phone ) ) {
		roova_auth_add_error( 'phone', __( 'Enter a valid phone number.', 'roova' ) );
	}

	if ( ! roova_auth_valid_password( $pass ) ) {
		roova_auth_add_error( 'password', __( 'Use at least 8 characters, with a letter and a number.', 'roova' ) );
	}

	if ( '' === $pass2 || $pass2 !== $pass ) {
		roova_auth_add_error( 'password_confirm', __( 'Passwords do not match.', 'roova' ) );
	}

	if ( ! $terms ) {
		roova_auth_add_error( 'terms', __( 'Please accept the terms to create your account.', 'roova' ) );
	}

	if ( roova_auth_get_errors()->has_errors() ) {
		return;
	}

	/*
	 * Both of these send a welcome email of their own, and the guest is about to
	 * get the confirmation one. Two emails a second apart, one of which says
	 * "your account is ready" when it is not yet, is worse than either — so the
	 * welcome is held back while confirmation is on. The confirmation email is
	 * the welcome.
	 */
	$roova_confirming = roova_verification_required();
	if ( $roova_confirming ) {
		add_filter( 'woocommerce_email_enabled_customer_new_account', '__return_false', 99 );
	}

	/*
	 * WooCommerce's helper is preferred: it applies the store's username and
	 * password settings and gives the account the customer role. Without
	 * WooCommerce, core's own insert does.
	 */
	if ( function_exists( 'wc_create_new_customer' ) ) {
		$user_id = wc_create_new_customer(
			$email,
			'',
			$pass,
			array(
				'first_name' => $first,
				'last_name'  => $last,
			)
		);
	} else {
		$user_id = wp_insert_user( array(
			'user_login' => roova_auth_username_from_email( $email ),
			'user_email' => $email,
			'user_pass'  => $pass,
			'first_name' => $first,
			'last_name'  => $last,
			'role'       => get_option( 'default_role', 'subscriber' ),
		) );

		if ( ! is_wp_error( $user_id ) && ! $roova_confirming ) {
			wp_new_user_notification( $user_id, null, 'both' );
		}
	}

	if ( $roova_confirming ) {
		remove_filter( 'woocommerce_email_enabled_customer_new_account', '__return_false', 99 );
	}

	if ( is_wp_error( $user_id ) ) {
		roova_auth_add_error( 'form', wp_strip_all_tags( $user_id->get_error_message() ) );
		return;
	}

	// The details checkout would otherwise ask for again.
	update_user_meta( $user_id, 'first_name', $first );
	update_user_meta( $user_id, 'last_name', $last );
	update_user_meta( $user_id, 'billing_first_name', $first );
	update_user_meta( $user_id, 'billing_last_name', $last );
	update_user_meta( $user_id, 'billing_email', $email );
	update_user_meta( $user_id, 'billing_phone', $phone );

	/**
	 * Fires once a member has been created from the theme's sign-up form.
	 *
	 * @param int   $user_id The new user.
	 * @param array $data    first_name, last_name, email, phone.
	 */
	do_action( 'roova_member_registered', $user_id, array(
		'first_name' => $first,
		'last_name'  => $last,
		'email'      => $email,
		'phone'      => $phone,
	) );

	/*
	 * Nobody is signed in yet. The address has to prove itself first — see
	 * inc/verification.php — and the account stays unusable until the link in
	 * it is opened. With confirmation filtered off, sign-up behaves as it did
	 * before and signs the new member straight in.
	 */
	if ( roova_verification_required() ) {
		roova_mark_unverified( $user_id );
		roova_send_verification_email( $user_id );

		wp_safe_redirect(
			add_query_arg( 'roova_sent', roova_store_pending_notice( $user_id ), roova_signup_url() )
		);
		exit;
	}

	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true, is_ssl() );
	do_action( 'wp_login', get_userdata( $user_id )->user_login, get_userdata( $user_id ) );

	wp_safe_redirect( roova_auth_redirect_target() );
	exit;
}

/**
 * Can anyone still sign up?
 *
 * This reads the theme's own switch and nothing else. It used to read
 * WooCommerce's "Allow customers to create an account", which was simply the
 * wrong gate: that setting decides whether WooCommerce prints a registration
 * form on *its* account page, and `wc_create_new_customer()` — which is what
 * this page actually calls — never consults it. Borrowing it meant the sign-up
 * page shipped closed on every store that had left it at WooCommerce's default,
 * for a reason that had nothing to do with the page.
 *
 * So the page is open unless this site says otherwise, and the theme no longer
 * writes to anyone else's settings to make that true.
 *
 * @return bool
 */
function roova_registration_open() {
	// The default is repeated in inc/customizer.php — get_theme_mod() falls back
	// to what the caller passes, not to the setting's registered default.
	$open = (bool) roova_option( 'registration_open', true );

	/**
	 * Filter whether the sign-up form will create accounts.
	 *
	 * @param bool $open Whether registration is open.
	 */
	return (bool) apply_filters( 'roova_registration_open', $open );
}

/**
 * Why sign-up is closed, for an admin looking at the closed page.
 *
 * Only ever shown to someone who can act on it. "Registration is off" with no
 * indication of what turned it off is what made the first version of this hard
 * to unstick.
 *
 * @return string
 */
function roova_registration_closed_reason() {
	if ( ! roova_option( 'registration_open', true ) ) {
		return __( 'Only you can see this: sign-up is switched off in Appearance → Customize → Roova hotel theme → Sign in and sign up.', 'roova' );
	}

	// The setting is on, so a filter is overriding it — a plugin, or a snippet
	// in a child theme.
	return __( 'Only you can see this: the setting is on, so something on this site is filtering "roova_registration_open" to false — usually a plugin or a code snippet.', 'roova' );
}

/**
 * A free username built from an email address.
 *
 * Only used when WooCommerce is not there to generate one.
 *
 * @param string $email Email address.
 * @return string
 */
function roova_auth_username_from_email( $email ) {
	$base = sanitize_user( current( explode( '@', $email ) ), true );
	if ( '' === $base ) {
		$base = 'member';
	}

	$username = $base;
	$suffix   = 1;
	while ( username_exists( $username ) ) {
		$suffix++;
		$username = $base . $suffix;
	}

	return $username;
}

/* -------------------------------------------------------------------------
 * Routing
 * ---------------------------------------------------------------------- */

/**
 * A signed-in member has no use for the sign-in page.
 */
function roova_redirect_signed_in_members() {
	if ( ! is_user_logged_in() || ! roova_is_auth_page() ) {
		return;
	}

	wp_safe_redirect( roova_auth_redirect_target() );
	exit;
}
add_action( 'template_redirect', 'roova_redirect_signed_in_members' );

/**
 * Send a signed-out visitor from My account to the theme's sign-in page.
 *
 * WooCommerce would otherwise print its own login form there, so the site would
 * have two — one designed, one not. Endpoints are left alone: lost-password and
 * reset-password have to work while signed out.
 */
function roova_redirect_account_to_signin() {
	if ( is_user_logged_in() || ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
		return;
	}

	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url() ) {
		return;
	}

	/**
	 * Filter whether My account redirects signed-out visitors to the sign-in page.
	 *
	 * @param bool $redirect Default true.
	 */
	if ( ! apply_filters( 'roova_redirect_account_to_signin', true ) ) {
		return;
	}

	if ( ! roova_auth_page_id( 'signin' ) ) {
		return;
	}

	wp_safe_redirect( roova_signin_url( roova_account_url() ) );
	exit;
}
add_action( 'template_redirect', 'roova_redirect_account_to_signin' );

/* -------------------------------------------------------------------------
 * Markup
 * ---------------------------------------------------------------------- */

/**
 * The header's account control: a profile icon, or a "Sign in" button.
 *
 * Replaces the old "Manage booking" button, which sent everyone to My account
 * whether they had an account or not.
 */
function roova_account_control() {
	$signed_in = is_user_logged_in();

	/*
	 * One button in both states, so the top right of the header never changes
	 * shape or position when a guest signs in — only what it says and where it
	 * goes.
	 */
	$url   = $signed_in ? roova_account_url() : roova_signin_url();
	$label = $signed_in ? __( 'Manage account', 'roova' ) : __( 'Sign in', 'roova' );
	?>
	<a class="roova-btn roova-btn--nav" href="<?php echo esc_url( $url ); ?>">
		<?php echo esc_html( $label ); ?>
	</a>
	<?php
}

/**
 * One field of the auth forms.
 *
 * The `<label>` is the visible box and the `<input>` inside it is borderless,
 * which is what makes the caption and the value read as one control.
 *
 * @param array $args {
 *     @type string $name         Field name, without the roova_ prefix.
 *     @type string $label        Caption above the value.
 *     @type string $type         Input type.
 *     @type string $placeholder  Placeholder.
 *     @type bool   $required     Show the gold asterisk.
 *     @type string $autocomplete autocomplete attribute.
 *     @type string $rule         Rule name for assets/js/auth.js.
 *     @type string $value        Value, when it is not the submitted one.
 *     @type bool   $toggle       This field owns the shared show/hide button.
 *     @type bool   $match        Show a tick when it matches the password.
 *     @type string $group        Password group name for the shared toggle.
 * }
 */
function roova_auth_field( $args ) {
	$args = wp_parse_args( $args, array(
		'name'         => '',
		'label'        => '',
		'type'         => 'text',
		'placeholder'  => '',
		'required'     => true,
		'autocomplete' => '',
		'rule'         => '',
		'value'        => null,
		'toggle'       => false,
		'match'        => false,
		'group'        => '',
	) );

	$is_password = 'password' === $args['type'];
	$value       = null === $args['value'] ? roova_auth_value( $args['name'] ) : $args['value'];
	$error       = roova_auth_error( $args['name'] );
	$id          = 'roova-' . str_replace( '_', '-', $args['name'] );

	$classes = array( 'roova-field' );
	if ( $is_password ) {
		$classes[] = 'roova-field--password';
	}
	// Anything with something beside the value — an eye, a tick — lays out as a
	// row, or the extra element wraps under the input and the box grows.
	if ( $args['match'] ) {
		$classes[] = 'roova-field--match';
	}
	if ( $error ) {
		$classes[] = 'is-invalid';
	}
	?>
	<div class="roova-field-row" data-roova-field="<?php echo esc_attr( $args['name'] ); ?>">
		<label class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" for="<?php echo esc_attr( $id ); ?>">
			<span class="roova-field__main">
				<span class="roova-field__caption">
					<?php echo esc_html( $args['label'] ); ?>
					<?php if ( $args['required'] ) : ?>
						<span class="roova-field__req" aria-hidden="true">*</span>
					<?php endif; ?>
				</span>

				<input
					class="roova-field__input"
					id="<?php echo esc_attr( $id ); ?>"
					type="<?php echo esc_attr( $args['type'] ); ?>"
					name="roova_<?php echo esc_attr( $args['name'] ); ?>"
					value="<?php echo esc_attr( $is_password ? '' : $value ); ?>"
					placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
					<?php echo $args['autocomplete'] ? 'autocomplete="' . esc_attr( $args['autocomplete'] ) . '"' : ''; ?>
					<?php echo $args['rule'] ? 'data-roova-rule="' . esc_attr( $args['rule'] ) . '"' : ''; ?>
					<?php echo $is_password && $args['group'] ? 'data-roova-password="' . esc_attr( $args['group'] ) . '"' : ''; ?>
					<?php echo $args['required'] ? 'required' : ''; ?>
					<?php echo $error ? 'aria-invalid="true"' : ''; ?>
					aria-describedby="<?php echo esc_attr( $id . '-error' ); ?>"
				/>
			</span>

			<?php if ( $args['toggle'] ) : ?>
				<button
					class="roova-field__eye"
					type="button"
					data-roova-eye="<?php echo esc_attr( $args['group'] ); ?>"
					aria-pressed="false"
					aria-label="<?php esc_attr_e( 'Show password', 'roova' ); ?>"
				>
					<span class="roova-field__eye-on"><?php roova_the_icon( 'eye', 17 ); ?></span>
					<span class="roova-field__eye-off"><?php roova_the_icon( 'eye-off', 17 ); ?></span>
				</button>
			<?php elseif ( $args['match'] ) : ?>
				<span class="roova-field__match" data-roova-match aria-hidden="true">
					<?php roova_the_icon( 'check', 17 ); ?>
				</span>
			<?php endif; ?>
		</label>

		<p class="roova-field__error" id="<?php echo esc_attr( $id . '-error' ); ?>" data-roova-error aria-live="polite" <?php echo $error ? '' : 'hidden'; ?>>
			<?php echo esc_html( $error ); ?>
		</p>
	</div>
	<?php
}

/**
 * The four-bar password strength meter under the password field.
 *
 * Empty until the script fills it in — the level is only ever a reading of what
 * has been typed, which the server never sees.
 */
function roova_auth_strength_meter() {
	?>
	<div class="roova-strength" data-roova-strength>
		<span class="roova-strength__bars" aria-hidden="true">
			<i></i><i></i><i></i><i></i>
		</span>
		<span class="roova-strength__label" data-roova-strength-label>
			<?php esc_html_e( 'Password strength', 'roova' ); ?>
		</span>
	</div>
	<?php
}

/**
 * The photo panel down the right-hand side of both pages.
 *
 * @param string $which    'signin' or 'signup' — picks the photo setting and its
 *                         bundled fallback.
 * @param string $headline The line over the photograph.
 */
function roova_auth_panel( $which, $headline ) {
	/*
	 * A photo each: the towers on sign in, Batu Caves on sign up. They are two
	 * settings rather than one because the pages are seen one after the other —
	 * the same picture twice reads as a page that failed to change.
	 */
	$image_id = (int) roova_option( 'auth_' . $which . '_image', 0 );
	$fallback = 'signup' === $which ? 'auth-signup.jpg' : 'auth-signin.jpg';
	$stats    = roova_auth_stats();
	?>
	<aside class="roova-auth__panel roova-auth__panel--<?php echo esc_attr( $which ); ?>">
		<div class="roova-auth__panel-media" aria-hidden="true">
			<?php roova_background_image( $image_id, $fallback, true ); ?>
		</div>
		<div class="roova-auth__panel-scrim" aria-hidden="true"></div>

		<div class="roova-auth__panel-inner">
			<p class="roova-auth__panel-headline"><?php echo esc_html( $headline ); ?></p>

			<?php if ( $stats ) : ?>
				<div class="roova-auth__stats">
					<?php foreach ( $stats as $stat ) : ?>
						<div class="roova-auth__stat">
							<span class="roova-auth__stat-figure"><?php echo esc_html( $stat[0] ); ?></span>
							<span class="roova-auth__stat-label"><?php echo esc_html( $stat[1] ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</aside>
	<?php
}

/**
 * The two figures under the panel headline.
 *
 * Both are Customizer settings, because a claim about how many stays a site has
 * is the client's to make — and an empty one drops its column rather than
 * printing a number nobody stands behind.
 *
 * @return array[] Each: figure, label.
 */
function roova_auth_stats() {
	$stats = array();

	$pairs = array(
		array( roova_option( 'auth_stat_1_figure', __( '1,240+', 'roova' ) ), roova_option( 'auth_stat_1_label', __( 'Stays across Malaysia', 'roova' ) ) ),
		array( roova_option( 'auth_stat_2_figure', __( 'Zero', 'roova' ) ), roova_option( 'auth_stat_2_label', __( 'Booking fees, always', 'roova' ) ) ),
	);

	foreach ( $pairs as $pair ) {
		if ( $pair[0] && $pair[1] ) {
			$stats[] = $pair;
		}
	}

	return $stats;
}

/**
 * The logo above the form, which is the whole header on these pages.
 *
 * Deliberately *not* `the_custom_logo()`: the header's logo is chosen to sit on
 * the hero photograph and is usually the reversed-out, light version of a mark —
 * which would be invisible here, where the form column is white. So this is its
 * own setting, falling back to the full-colour file the theme ships, and to the
 * site name as text if that is ever removed.
 */
function roova_auth_wordmark() {
	$logo_id  = (int) roova_option( 'auth_logo', 0 );
	$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'full' ) : '';

	if ( ! $logo_url && file_exists( ROOVA_DIR . 'assets/images/logo.png' ) ) {
		$logo_url = ROOVA_URI . 'assets/images/logo.png';
	}
	?>
	<a class="roova-auth__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
		<?php if ( $logo_url ) : ?>
			<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
		<?php else : ?>
			<?php echo roova_wordmark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?>
		<?php endif; ?>
	</a>
	<?php
}

/**
 * A form-level error, above the fields.
 */
function roova_auth_form_error() {
	$message = roova_auth_error( 'form' );
	if ( ! $message ) {
		return;
	}
	?>
	<p class="roova-auth__alert" role="alert"><?php echo esc_html( $message ); ?></p>
	<?php
}

/**
 * The links behind "terms of service" and "privacy notice".
 *
 * @return array terms => url, privacy => url. Either may be ''.
 */
function roova_auth_legal_urls() {
	$terms = '';
	if ( function_exists( 'wc_get_page_permalink' ) ) {
		$terms = wc_get_page_permalink( 'terms' );
	}
	if ( ! $terms ) {
		$terms = roova_option( 'terms_url', '' );
	}

	return array(
		'terms'   => $terms,
		'privacy' => get_privacy_policy_url(),
	);
}
