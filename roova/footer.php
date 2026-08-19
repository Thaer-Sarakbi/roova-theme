<?php
/**
 * Site footer.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;
?>
</main>

<footer class="roova-footer">
	<div class="wrap">
		<div class="roova-footer__top">
			<div class="roova-footer__brand">
				<strong><?php echo esc_html( get_bloginfo( 'name' ) ); ?></strong>
				<?php
				$roova_tagline = roova_option( 'footer_tagline', '' );
				if ( $roova_tagline ) :
					?>
					<p><?php echo esc_html( $roova_tagline ); ?></p>
				<?php endif; ?>
			</div>

			<div class="roova-footer__contact">
				<?php
				$roova_contacts = array(
					'contact_phone'   => 'phone',
					'contact_email'   => 'info',
					'contact_address' => 'pin',
				);
				foreach ( $roova_contacts as $roova_key => $roova_icon ) :
					$roova_value = roova_option( $roova_key, '' );
					if ( ! $roova_value ) {
						continue;
					}
					?>
					<span>
						<?php roova_the_icon( $roova_icon, 14 ); ?>
						<?php
						if ( 'contact_email' === $roova_key ) {
							printf( '<a href="%s">%s</a>', esc_url( 'mailto:' . $roova_value ), esc_html( $roova_value ) );
						} elseif ( 'contact_phone' === $roova_key ) {
							printf( '<a href="%s">%s</a>', esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $roova_value ) ), esc_html( $roova_value ) );
						} else {
							echo esc_html( $roova_value );
						}
						?>
					</span>
				<?php endforeach; ?>
			</div>

			<?php if ( has_nav_menu( 'footer' ) ) : ?>
				<nav class="roova-footer__menu" aria-label="<?php esc_attr_e( 'Footer menu', 'roova' ); ?>">
					<?php
					wp_nav_menu( array(
						'theme_location' => 'footer',
						'container'      => false,
						'menu_class'     => 'roova-menu',
						'depth'          => 1,
						'fallback_cb'    => false,
					) );
					?>
				</nav>
			<?php endif; ?>
		</div>

		<?php if ( is_active_sidebar( 'roova-footer' ) ) : ?>
			<div class="roova-footer__widgets">
				<?php dynamic_sidebar( 'roova-footer' ); ?>
			</div>
		<?php endif; ?>

		<div class="roova-footer__bottom">
			<?php
			printf(
				/* translators: 1: year, 2: site name */
				esc_html__( '© %1$s %2$s. All rights reserved.', 'roova' ),
				esc_html( date_i18n( 'Y' ) ),
				esc_html( get_bloginfo( 'name' ) )
			);
			?>
		</div>
	</div>
</footer>

<?php roova_room_modal(); ?>
<?php wp_footer(); ?>
</body>
</html>
