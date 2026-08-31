<?php
/**
 * Template Name: Sign in
 *
 * Its own document rather than header.php + footer.php: the design is a split
 * screen with the wordmark as its whole header, and nothing on the page should
 * lead anywhere except into the account or the sign-up page.
 *
 * The page content is ignored — everything here is the form. The post is only
 * there to give the page a URL the client can rename and link to.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

$roova_redirect = roova_auth_redirect_target();
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
			<div class="roova-auth__inner roova-auth__inner--signin">
				<p class="roova-auth__eyebrow">
					<span class="roova-auth__rule" aria-hidden="true"></span>
					<?php esc_html_e( 'Welcome back', 'roova' ); ?>
				</p>

				<h1 class="roova-auth__title"><?php esc_html_e( 'Sign in', 'roova' ); ?></h1>

				<p class="roova-auth__sub">
					<?php esc_html_e( 'Access your bookings, saved stays and member rates.', 'roova' ); ?>
				</p>

				<?php roova_auth_form_error(); ?>

				<form class="roova-auth__form" method="post" action="<?php echo esc_url( get_permalink() ); ?>" novalidate data-roova-auth-form="signin">
					<?php wp_nonce_field( 'roova_signin', 'roova_signin_nonce' ); ?>
					<input type="hidden" name="roova_auth_action" value="signin" />
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( $roova_redirect ); ?>" />

					<div class="roova-auth__fields">
						<?php
						roova_auth_field( array(
							'name'         => 'email',
							'label'        => __( 'Email address', 'roova' ),
							'type'         => 'email',
							'placeholder'  => 'you@example.com',
							'required'     => false,
							'autocomplete' => 'email',
							'rule'         => 'email',
						) );

						roova_auth_field( array(
							'name'         => 'password',
							'label'        => __( 'Password', 'roova' ),
							'type'         => 'password',
							'placeholder'  => __( 'Your password', 'roova' ),
							'required'     => false,
							'autocomplete' => 'current-password',
							'rule'         => 'required-password',
							'toggle'       => true,
							'group'        => 'signin',
						) );
						?>
					</div>

					<div class="roova-auth__options">
						<label class="roova-check">
							<input type="checkbox" name="roova_remember" value="1" <?php checked( '1', roova_auth_value( 'remember', '1' ) ); ?> />
							<span><?php esc_html_e( 'Keep me signed in', 'roova' ); ?></span>
						</label>

						<a class="roova-auth__forgot" href="<?php echo esc_url( roova_lost_password_url() ); ?>">
							<?php esc_html_e( 'Forgot password?', 'roova' ); ?>
						</a>
					</div>

					<button class="roova-auth__submit" type="submit">
						<span><?php esc_html_e( 'Sign in', 'roova' ); ?></span>
						<?php roova_the_icon( 'arrow-right', 17 ); ?>
					</button>
				</form>

				<p class="roova-auth__foot">
					<?php
					printf(
						/* translators: %s: site name */
						esc_html__( 'New to %s?', 'roova' ),
						esc_html( get_bloginfo( 'name' ) )
					);
					?>
					<a href="<?php echo esc_url( roova_signup_url( $roova_redirect ) ); ?>"><?php esc_html_e( 'Create an account', 'roova' ); ?></a>
				</p>
			</div>
		</main>
	</div>

	<?php roova_auth_panel( 'signin', roova_option( 'auth_signin_headline', __( 'Your next stay is two taps away. Member rates apply the moment you sign in.', 'roova' ) ) ); ?>
</div>

<?php wp_footer(); ?>
</body>
</html>
