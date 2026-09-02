<?php
/**
 * Email confirmation for new accounts.
 *
 * Signing up creates the account but does **not** sign anyone in: the address
 * gets a link, and opening it is what proves the address is theirs. Until then
 * the account cannot be signed into at all — that is what "confirmed" has to
 * mean for it to be worth anything.
 *
 * Only accounts created through this flow are ever held back. An account that
 * predates it carries no pending flag and counts as confirmed, so switching
 * this on cannot lock out a site's existing members.
 *
 * The link carries a random token; only its hash is stored, with an expiry, and
 * it is spent the moment it is used. That is the shape of a password reset, and
 * for the same reason: here the inbox is the credential.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/**
 * When the address was confirmed (a timestamp), or absent.
 */
const ROOVA_VERIFY_META = 'roova_email_verified';

/**
 * Set while an account is waiting for its link to be opened.
 */
const ROOVA_VERIFY_PENDING = 'roova_verify_pending';

/**
 * The hash of the live token.
 */
const ROOVA_VERIFY_HASH = 'roova_verify_hash';

/**
 * When that token stops working.
 */
const ROOVA_VERIFY_EXPIRES = 'roova_verify_expires';

/* -------------------------------------------------------------------------
 * State
 * ---------------------------------------------------------------------- */

/**
 * Must a new account confirm its address before it can be used?
 *
 * @return bool
 */
function roova_verification_required() {
	/**
	 * Filter whether new accounts must confirm their email address.
	 *
	 * Off means sign-up signs the member straight in, as it did before.
	 *
	 * @param bool $required Default true.
	 */
	return (bool) apply_filters( 'roova_require_email_verification', true );
}

/**
 * How long a confirmation link works for, in seconds.
 *
 * @return int
 */
function roova_verification_lifetime() {
	/**
	 * Filter the life of a confirmation link.
	 *
	 * @param int $seconds Default two days.
	 */
	return max( HOUR_IN_SECONDS, (int) apply_filters( 'roova_verification_lifetime', 2 * DAY_IN_SECONDS ) );
}

/**
 * Has this account confirmed its address?
 *
 * True for every account that never went through this flow — an existing member
 * has already proved the address by using it.
 *
 * @param int $user_id User ID.
 * @return bool
 */
function roova_user_is_verified( $user_id ) {
	$user_id = absint( $user_id );
	if ( ! $user_id ) {
		return false;
	}

	if ( get_user_meta( $user_id, ROOVA_VERIFY_META, true ) ) {
		return true;
	}

	return ! get_user_meta( $user_id, ROOVA_VERIFY_PENDING, true );
}

/**
 * Is this account waiting on a link nobody has opened?
 *
 * @param int $user_id User ID.
 * @return bool
 */
function roova_user_needs_verification( $user_id ) {
	if ( ! roova_verification_required() ) {
		return false;
	}

	return ! roova_user_is_verified( $user_id );
}

/**
 * Mark an account as waiting for its address to be confirmed.
 *
 * @param int $user_id User ID.
 */
function roova_mark_unverified( $user_id ) {
	$user_id = absint( $user_id );

	delete_user_meta( $user_id, ROOVA_VERIFY_META );
	update_user_meta( $user_id, ROOVA_VERIFY_PENDING, 1 );
}

/**
 * Record that the address is confirmed, and drop the token with it.
 *
 * @param int $user_id User ID.
 */
function roova_mark_verified( $user_id ) {
	$user_id = absint( $user_id );

	update_user_meta( $user_id, ROOVA_VERIFY_META, time() );
	delete_user_meta( $user_id, ROOVA_VERIFY_PENDING );
	delete_user_meta( $user_id, ROOVA_VERIFY_HASH );
	delete_user_meta( $user_id, ROOVA_VERIFY_EXPIRES );

	/**
	 * Fires once a member has confirmed their email address.
	 *
	 * @param int $user_id User ID.
	 */
	do_action( 'roova_email_verified', $user_id );
}

/* -------------------------------------------------------------------------
 * The link
 * ---------------------------------------------------------------------- */

/**
 * Issue a fresh token, replacing whatever was outstanding.
 *
 * Only the hash is kept, so a leaked database row cannot be turned back into a
 * working link.
 *
 * @param int $user_id User ID.
 * @return string The raw token — for the email, and nowhere else.
 */
function roova_create_verification_token( $user_id ) {
	$user_id = absint( $user_id );
	$token   = wp_generate_password( 40, false, false );

	update_user_meta( $user_id, ROOVA_VERIFY_HASH, wp_hash( $token ) );
	update_user_meta( $user_id, ROOVA_VERIFY_EXPIRES, time() + roova_verification_lifetime() );

	return $token;
}

/**
 * The address the link points at.
 *
 * The sign-in page: it is the theme's own and always exists, and it is where
 * someone arriving with a spent link should end up anyway.
 *
 * @param int    $user_id User ID.
 * @param string $token   Raw token.
 * @return string
 */
function roova_verification_url( $user_id, $token ) {
	$url = add_query_arg(
		array(
			'roova_verify' => rawurlencode( $token ),
			'roova_uid'    => absint( $user_id ),
		),
		roova_signin_url()
	);

	/**
	 * Filter the confirmation link.
	 *
	 * @param string $url     The link.
	 * @param int    $user_id User ID.
	 */
	return apply_filters( 'roova_verification_url', $url, absint( $user_id ) );
}

/**
 * Check a token against the one on file.
 *
 * @param int    $user_id User ID.
 * @param string $token   Raw token from the link.
 * @return string 'ok', 'expired' or 'invalid'.
 */
function roova_check_verification_token( $user_id, $token ) {
	$user_id = absint( $user_id );
	$stored  = (string) get_user_meta( $user_id, ROOVA_VERIFY_HASH, true );

	if ( ! $user_id || '' === $stored || '' === $token ) {
		return 'invalid';
	}

	// hash_equals, not ===: a timing comparison against a secret is a leak.
	if ( ! hash_equals( $stored, wp_hash( $token ) ) ) {
		return 'invalid';
	}

	$expires = (int) get_user_meta( $user_id, ROOVA_VERIFY_EXPIRES, true );
	if ( $expires && $expires < time() ) {
		return 'expired';
	}

	return 'ok';
}

/* -------------------------------------------------------------------------
 * The email
 * ---------------------------------------------------------------------- */

/**
 * Send the confirmation link.
 *
 * Plain text on purpose: this is one sentence and one link, and a plain message
 * lands in an inbox rather than a promotions tab.
 *
 * @param int $user_id User ID.
 * @return bool Whether the mail was handed to the mailer.
 */
function roova_send_verification_email( $user_id ) {
	$user = get_userdata( absint( $user_id ) );
	if ( ! $user ) {
		return false;
	}

	$token = roova_create_verification_token( $user->ID );
	$url   = roova_verification_url( $user->ID, $token );
	$site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$first = $user->first_name ? $user->first_name : $user->display_name;
	$hours = (int) round( roova_verification_lifetime() / HOUR_IN_SECONDS );

	$subject = sprintf(
		/* translators: %s: site name */
		__( 'Confirm your email address for %s', 'roova' ),
		$site
	);

	$lines = array(
		sprintf(
			/* translators: %s: the member's first name */
			__( 'Hi %s,', 'roova' ),
			$first
		),
		'',
		sprintf(
			/* translators: %s: site name */
			__( 'Confirm this email address to finish setting up your %s account:', 'roova' ),
			$site
		),
		'',
		$url,
		'',
		sprintf(
			/* translators: %s: number of hours the link works for */
			_n( 'The link works for the next %s hour.', 'The link works for the next %s hours.', $hours, 'roova' ),
			number_format_i18n( $hours )
		),
		__( 'If you did not create an account, ignore this email and nothing will happen.', 'roova' ),
		'',
		$site,
	);

	/**
	 * Filter the confirmation email.
	 *
	 * @param array  $mail    to, subject, message, headers.
	 * @param int    $user_id User ID.
	 * @param string $url     The confirmation link.
	 */
	$mail = apply_filters(
		'roova_verification_email',
		array(
			'to'      => $user->user_email,
			'subject' => $subject,
			'message' => implode( "\n", $lines ),
			'headers' => array(),
		),
		$user->ID,
		$url
	);

	return (bool) wp_mail( $mail['to'], $mail['subject'], $mail['message'], $mail['headers'] );
}

/* -------------------------------------------------------------------------
 * Opening the link
 * ---------------------------------------------------------------------- */

/**
 * Handle a click on a confirmation link.
 *
 * Priority 4: before the auth form handler, and before the "you are already
 * signed in" redirect, so the link decides where the visitor lands.
 */
function roova_handle_verification() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the token in the link is the credential, and it is checked below.
	if ( ! isset( $_GET['roova_verify'], $_GET['roova_uid'] ) ) {
		return;
	}

	$token   = sanitize_text_field( wp_unslash( $_GET['roova_verify'] ) );
	$user_id = absint( $_GET['roova_uid'] );
	// phpcs:enable

	$result = roova_check_verification_token( $user_id, $token );

	if ( 'ok' !== $result ) {
		/*
		 * A confirmed account arriving at a spent link is someone who clicked
		 * twice, or opened it on a second device. That is a success as far as
		 * they are concerned, not an error to read.
		 */
		if ( 'invalid' === $result && ! roova_user_needs_verification( $user_id ) && get_userdata( $user_id ) ) {
			$result = 'done';
		}

		wp_safe_redirect( add_query_arg( 'roova_verify_result', $result, roova_signin_url() ) );
		exit;
	}

	roova_mark_verified( $user_id );

	/*
	 * Opening the link is the proof, so it signs them in: the whole point of
	 * confirming an address is that whoever holds the inbox is the member.
	 */
	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true, is_ssl() );

	$user = get_userdata( $user_id );
	if ( $user ) {
		do_action( 'wp_login', $user->user_login, $user );
	}

	wp_safe_redirect( add_query_arg( 'roova_welcome', '1', roova_account_url() ) );
	exit;
}
add_action( 'template_redirect', 'roova_handle_verification', 4 );

/**
 * Refuse a sign-in until the address is confirmed.
 *
 * On `wp_authenticate_user`, so the password is still checked but no cookie is
 * ever set — and wp-login.php is covered as well as the theme's own form.
 *
 * @param WP_User|WP_Error $user The authenticating user.
 * @return WP_User|WP_Error
 */
function roova_block_unverified_login( $user ) {
	if ( is_wp_error( $user ) || ! $user instanceof WP_User ) {
		return $user;
	}

	if ( ! roova_user_needs_verification( $user->ID ) ) {
		return $user;
	}

	return new WP_Error(
		'roova_unverified',
		__( 'Confirm your email address first — the link is in your inbox.', 'roova' )
	);
}
add_filter( 'wp_authenticate_user', 'roova_block_unverified_login', 20 );

/* -------------------------------------------------------------------------
 * Sending it again
 * ---------------------------------------------------------------------- */

/**
 * Keep a short-lived note behind the "check your inbox" screen.
 *
 * The address lives in a transient rather than the URL: the screen wants to say
 * which inbox to look in, and a query string is the one place that should not
 * be carrying someone's email address around.
 *
 * @param int $user_id User ID.
 * @return string The key that goes in the URL.
 */
function roova_store_pending_notice( $user_id ) {
	$key = wp_generate_password( 20, false, false );

	set_transient(
		'roova_signup_' . $key,
		array( 'user_id' => absint( $user_id ) ),
		30 * MINUTE_IN_SECONDS
	);

	return $key;
}

/**
 * Read that note back.
 *
 * @param string $key Key from the URL.
 * @return array|null user_id and email, or null.
 */
function roova_pending_notice( $key ) {
	$key = sanitize_text_field( $key );
	if ( '' === $key ) {
		return null;
	}

	$stored = get_transient( 'roova_signup_' . $key );
	if ( ! is_array( $stored ) || empty( $stored['user_id'] ) ) {
		return null;
	}

	$user = get_userdata( (int) $stored['user_id'] );
	if ( ! $user ) {
		return null;
	}

	return array(
		'user_id' => $user->ID,
		'email'   => $user->user_email,
	);
}

/**
 * Send the link again.
 *
 * Answers the same way whether or not there was anything to send: this form is
 * reachable by anyone, and a different message for a real address would turn it
 * into a way to find out which addresses have accounts.
 */
function roova_process_resend() {
	if ( ! roova_auth_verify_nonce( 'roova_resend_nonce', 'roova_resend' ) ) {
		roova_auth_add_error( 'form', __( 'That form had expired. Please try again.', 'roova' ) );
		return;
	}

	$email = isset( $_POST['roova_email'] ) ? sanitize_text_field( wp_unslash( $_POST['roova_email'] ) ) : '';
	$user  = is_email( $email ) ? get_user_by( 'email', $email ) : null;

	if ( $user && roova_user_needs_verification( $user->ID ) ) {
		roova_send_verification_email( $user->ID );
	}

	wp_safe_redirect( add_query_arg( 'roova_verify_result', 'resent', roova_signin_url() ) );
	exit;
}

/**
 * What the sign-in page says to someone arriving from a link.
 *
 * @return array|null array( type, text ), or null when there is nothing to say.
 */
function roova_verification_notice() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a display flag; it changes nothing.
	$result = isset( $_GET['roova_verify_result'] ) ? sanitize_key( wp_unslash( $_GET['roova_verify_result'] ) ) : '';

	switch ( $result ) {
		case 'expired':
			return array(
				'error',
				__( 'That link has expired. Sign in below and we will send you a new one.', 'roova' ),
			);

		case 'invalid':
			return array(
				'error',
				__( 'That link is no longer valid. Sign in below and we will send you a new one.', 'roova' ),
			);

		case 'done':
			return array(
				'ok',
				__( 'That address is already confirmed — sign in below.', 'roova' ),
			);

		case 'resent':
			return array(
				'ok',
				__( 'If that address has an account waiting to be confirmed, the link is on its way.', 'roova' ),
			);
	}

	return null;
}

/* -------------------------------------------------------------------------
 * Markup
 * ---------------------------------------------------------------------- */

/**
 * A form that asks for the link to be sent again.
 *
 * The address is carried in a hidden field wherever the page already knows it —
 * off the sign-up screen the member has just typed it, and on sign-in they have
 * just tried to use it. Nobody should have to type it a third time.
 *
 * @param string $email The address to send to.
 * @param string $label Button label.
 */
function roova_auth_resend_form( $email, $label = '' ) {
	if ( ! $email ) {
		return;
	}

	$label = $label ? $label : __( 'Send it again', 'roova' );
	?>
	<form class="roova-auth__resend" method="post" action="<?php echo esc_url( roova_signin_url() ); ?>">
		<?php roova_auth_nonce_field( 'roova_resend', 'roova_resend_nonce' ); ?>
		<input type="hidden" name="roova_auth_action" value="resend" />
		<input type="hidden" name="roova_email" value="<?php echo esc_attr( $email ); ?>" />

		<button type="submit"><?php echo esc_html( $label ); ?></button>
	</form>
	<?php
}

/**
 * The screen a new member lands on: which inbox to look in, and what to do if
 * nothing arrives.
 *
 * @param array $pending user_id and email, from roova_pending_notice().
 */
function roova_auth_sent_panel( $pending ) {
	$hours = (int) round( roova_verification_lifetime() / HOUR_IN_SECONDS );
	?>
	<div class="roova-auth__sent">
		<p class="roova-auth__sent-lead">
			<?php
			printf(
				/* translators: %s: the member's email address */
				esc_html__( 'We have sent a confirmation link to %s.', 'roova' ),
				'<strong>' . esc_html( $pending['email'] ) . '</strong>'
			);
			?>
		</p>

		<p class="roova-auth__sent-body">
			<?php esc_html_e( 'Open it and your account is ready — you will be signed in straight away.', 'roova' ); ?>
			<?php
			printf(
				/* translators: %s: number of hours the link works for */
				esc_html( _n( 'The link works for the next %s hour.', 'The link works for the next %s hours.', $hours, 'roova' ) ),
				esc_html( number_format_i18n( $hours ) )
			);
			?>
		</p>

		<div class="roova-auth__sent-actions">
			<?php roova_auth_resend_form( $pending['email'], __( 'Send it again', 'roova' ) ); ?>

			<a class="roova-auth__sent-link" href="<?php echo esc_url( roova_signup_url() ); ?>">
				<?php esc_html_e( 'Use a different address', 'roova' ); ?>
			</a>
		</div>

		<p class="roova-auth__sent-note">
			<?php esc_html_e( 'Nothing after a minute or two? Check the spam folder — it is usually there.', 'roova' ); ?>
		</p>
	</div>
	<?php
}

/**
 * The line at the top of the sign-in page after arriving from a link.
 */
function roova_verification_alert() {
	$notice = roova_verification_notice();
	if ( ! $notice ) {
		return;
	}

	list( $type, $text ) = $notice;
	?>
	<p class="roova-auth__alert roova-auth__alert--<?php echo esc_attr( $type ); ?>" role="alert">
		<?php echo esc_html( $text ); ?>
	</p>
	<?php
}