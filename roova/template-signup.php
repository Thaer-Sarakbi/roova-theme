<?php
/**
 * Template Name: Sign up
 *
 * The sign-in page's twin — same split screen, same field shell, one more
 * column of fields. See template-signin.php for why it prints its own document.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

$roova_redirect = roova_auth_redirect_target();
$roova_open     = roova_registration_open();
$roova_legal    = roova_auth_legal_urls();

// Set the moment an account is created: the form is done, and what is left
// to say is which inbox to go and look in. See inc/verification.php.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a key into a transient this page wrote itself; it grants nothing.
$roova_sent = isset( $_GET['roova_sent'] ) ? roova_pending_notice( sanitize_text_field( wp_unslash( $_GET['roova_sent'] ) ) ) : null;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>

<body <?php body_class( 'roova-page-white roova-auth-page' ); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#roova-content"><?php esc_html_e( 'Skip to content', 'roova' ); ?></a>

<div class="roova-auth">
	<div class="roova-auth__form-col">
		<?php roova_auth_wordmark(); ?>

		<main id="roova-content" class="roova-auth__body">
			<div class="roova-auth__inner">
				<p class="roova-auth__eyebrow">
					<span class="roova-auth__rule" aria-hidden="true"></span>
					<?php
					if ( $roova_sent ) {
						esc_html_e( 'Almost there', 'roova' );
					} else {
						printf(
							/* translators: %s: site name */
							esc_html__( 'Join %s', 'roova' ),
							esc_html( get_bloginfo( 'name' ) )
						);
					}
					?>
				</p>

				<h1 class="roova-auth__title">
					<?php echo esc_html( $roova_sent ? __( 'Check your email', 'roova' ) : __( 'Create your account', 'roova' ) ); ?>
				</h1>

				<p class="roova-auth__sub">
					<?php
					echo esc_html(
						$roova_sent
							? __( 'One link, one tap, and your account is ready.', 'roova' )
							: __( 'Member rates, saved travellers and one-tap checkout across every Malaysian stay.', 'roova' )
					);
					?>
				</p>

				<?php roova_auth_form_error(); ?>

				<?php if ( $roova_sent ) : ?>

					<?php roova_auth_sent_panel( $roova_sent ); ?>

				<?php elseif ( ! $roova_open ) : ?>

					<p class="roova-auth__alert">
						<?php esc_html_e( 'New accounts are closed at the moment. Please get in touch if you need one.', 'roova' ); ?>
						<?php if ( current_user_can( 'manage_options' ) ) : ?>
							<span class="roova-auth__alert-admin">
								<?php echo esc_html( roova_registration_closed_reason() ); ?>
							</span>
						<?php endif; ?>
					</p>

					<p class="roova-auth__foot">
						<?php esc_html_e( 'Already have an account?', 'roova' ); ?>
						<a href="<?php echo esc_url( roova_signin_url( $roova_redirect ) ); ?>"><?php esc_html_e( 'Sign in', 'roova' ); ?></a>
					</p>

				<?php else : ?>

					<form class="roova-auth__form" method="post" action="<?php echo esc_url( get_permalink() ); ?>" novalidate data-roova-auth-form="signup">
						<?php roova_auth_nonce_field( 'roova_signup', 'roova_signup_nonce' ); ?>
						<input type="hidden" name="roova_auth_action" value="signup" />
						<input type="hidden" name="redirect_to" value="<?php echo esc_url( $roova_redirect ); ?>" />

						<div class="roova-auth__fields">
							<div class="roova-auth__pair">
								<?php
								roova_auth_field( array(
									'name'         => 'first_name',
									'label'        => __( 'First name', 'roova' ),
									'placeholder'  => __( 'Thaer', 'roova' ),
									'autocomplete' => 'given-name',
									'rule'         => 'name',
								) );

								roova_auth_field( array(
									'name'         => 'last_name',
									'label'        => __( 'Last name', 'roova' ),
									'placeholder'  => __( 'Ahmad', 'roova' ),
									'autocomplete' => 'family-name',
									'rule'         => 'name',
								) );
								?>
							</div>

							<?php
							roova_auth_field( array(
								'name'         => 'email',
								'label'        => __( 'Email address', 'roova' ),
								'type'         => 'email',
								'placeholder'  => 'you@example.com',
								'autocomplete' => 'email',
								'rule'         => 'email',
							) );

							roova_auth_field( array(
								'name'         => 'phone',
								'label'        => __( 'Phone number', 'roova' ),
								'type'         => 'tel',
								'placeholder'  => '+60 12 345 6789',
								'autocomplete' => 'tel',
								'rule'         => 'phone',
							) );

							roova_auth_field( array(
								'name'         => 'password',
								'label'        => __( 'Password', 'roova' ),
								'type'         => 'password',
								'placeholder'  => __( 'At least 8 characters', 'roova' ),
								'autocomplete' => 'new-password',
								'rule'         => 'password',
								'toggle'       => true,
								'group'        => 'signup',
							) );

							roova_auth_strength_meter();

							roova_auth_field( array(
								'name'         => 'password_confirm',
								'label'        => __( 'Confirm password', 'roova' ),
								'type'         => 'password',
								'placeholder'  => __( 'Re-enter your password', 'roova' ),
								'autocomplete' => 'new-password',
								'rule'         => 'confirm-password',
								'group'        => 'signup',
								'match'        => true,
							) );
							?>
						</div>

						<div class="roova-auth__terms" data-roova-field="terms">
							<label class="roova-check roova-check--terms">
								<input type="checkbox" name="roova_terms" value="1" data-roova-rule="terms" <?php checked( '1', roova_auth_value( 'terms' ) ); ?> />
								<span>
									<?php
									$roova_terms_link = $roova_legal['terms']
										? '<a href="' . esc_url( $roova_legal['terms'] ) . '">' . esc_html__( 'terms of service', 'roova' ) . '</a>'
										: '<strong>' . esc_html__( 'terms of service', 'roova' ) . '</strong>';

									$roova_privacy_link = $roova_legal['privacy']
										? '<a href="' . esc_url( $roova_legal['privacy'] ) . '">' . esc_html__( 'privacy notice', 'roova' ) . '</a>'
										: '<strong>' . esc_html__( 'privacy notice', 'roova' ) . '</strong>';

									printf(
										/* translators: 1: terms of service link, 2: privacy notice link */
										esc_html__( 'I agree to the %1$s and %2$s.', 'roova' ),
										$roova_terms_link, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
										$roova_privacy_link // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
									);
									?>
								</span>
							</label>

							<?php $roova_terms_error = roova_auth_error( 'terms' ); ?>
							<p class="roova-field__error" data-roova-error aria-live="polite" <?php echo $roova_terms_error ? '' : 'hidden'; ?>>
								<?php echo esc_html( $roova_terms_error ); ?>
							</p>
						</div>

						<button class="roova-auth__submit" type="submit">
							<span><?php esc_html_e( 'Create account', 'roova' ); ?></span>
							<?php roova_the_icon( 'arrow-right', 17 ); ?>
						</button>
					</form>

					<p class="roova-auth__foot">
						<?php esc_html_e( 'Already have an account?', 'roova' ); ?>
						<a href="<?php echo esc_url( roova_signin_url( $roova_redirect ) ); ?>"><?php esc_html_e( 'Sign in', 'roova' ); ?></a>
					</p>

				<?php endif; ?>
			</div>
		</main>
	</div>

	<?php
	/*
	 * The handoff's line promises "a discount when you register", which is a
	 * claim only the client can make good on. The default says what an account
	 * actually does; the Customizer is where a store that does discount members
	 * says so.
	 */
	roova_auth_panel( 'signup', roova_option( 'auth_signup_headline', __( 'Book a room in Malaysia in under a minute, and keep every stay in one place.', 'roova' ) ) );
	?>
</div>

<?php wp_footer(); ?>
</body>
</html>
