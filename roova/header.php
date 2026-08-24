<?php
/**
 * Site header — sticky, cream, translucent over whatever scrolls beneath it.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/*
 * The homepage opens with the full-bleed hero, so the header is laid over the
 * photograph instead of sitting in a bar of its own.
 */
$roova_over_hero = is_front_page() && ! is_paged();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>

<body <?php body_class( $roova_over_hero ? 'roova-has-hero' : '' ); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#roova-content"><?php esc_html_e( 'Skip to content', 'roova' ); ?></a>

<header class="roova-nav <?php echo $roova_over_hero ? 'roova-nav--over' : ''; ?>">
	<div class="roova-nav__inner">
		<div class="roova-nav__brand">
			<?php
			if ( has_custom_logo() ) {
				the_custom_logo();
			} else {
				printf(
					'<a class="roova-nav__logo" href="%s">%s</a>',
					esc_url( home_url( '/' ) ),
					roova_wordmark() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
				);
			}
			?>
		</div>

		<nav class="roova-nav__menu" aria-label="<?php esc_attr_e( 'Primary menu', 'roova' ); ?>">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu( array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'roova-menu',
					'depth'          => 2,
					'fallback_cb'    => false,
				) );
			}
			?>
		</nav>

		<div class="roova-nav__actions">
			<?php if ( function_exists( 'wc_get_cart_url' ) && WC()->cart && ! WC()->cart->is_empty() ) : ?>
				<a class="roova-nav__cart" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
					<?php
					printf(
						/* translators: %d: number of items */
						esc_html( _n( '%d room in cart', '%d rooms in cart', WC()->cart->get_cart_contents_count(), 'roova' ) ),
						(int) WC()->cart->get_cart_contents_count()
					);
					?>
				</a>
			<?php endif; ?>

			<?php
			$roova_support_url = roova_option( 'support_url', '' );
			if ( $roova_support_url ) :
				?>
				<a class="roova-nav__support" href="<?php echo esc_url( $roova_support_url ); ?>">
					<?php echo esc_html( roova_option( 'support_label', __( 'Support', 'roova' ) ) ); ?>
				</a>
			<?php endif; ?>

			<?php
			$roova_account_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '';
			if ( $roova_account_url ) :
				?>
				<a class="roova-btn roova-btn--nav" href="<?php echo esc_url( $roova_account_url ); ?>">
					<?php esc_html_e( 'Manage booking', 'roova' ); ?>
				</a>
			<?php endif; ?>

			<button class="roova-nav__toggle" type="button" data-roova-nav-toggle aria-expanded="false">
				<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'roova' ); ?></span>
				<span aria-hidden="true"></span>
			</button>
		</div>
	</div>
</header>

<main id="roova-content" class="roova-main">
