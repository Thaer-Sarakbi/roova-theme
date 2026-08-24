<?php
/**
 * Site footer.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

/*
 * Three link columns, each a menu location so the client can edit them under
 * Appearance > Menus. A column with no menu assigned is simply left out.
 */
$roova_footer_columns = array(
	'footer'   => roova_option( 'footer_heading_1', __( 'Stay', 'roova' ) ),
	'footer-2' => roova_option( 'footer_heading_2', __( 'Guests', 'roova' ) ),
	'footer-3' => roova_option( 'footer_heading_3', __( 'Company', 'roova' ) ),
);
?>
</main>

<footer class="roova-footer">
	<div class="wrap">
		<div class="roova-footer__top">
			<div class="roova-footer__brand">
				<strong><?php echo roova_wordmark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?></strong>
				<?php
				$roova_tagline = roova_option( 'footer_tagline', __( 'Malaysian hotels, booked direct. Klang Valley and Malacca.', 'roova' ) );
				if ( $roova_tagline ) :
					?>
					<p><?php echo esc_html( $roova_tagline ); ?></p>
				<?php endif; ?>

				<?php
				$roova_contacts = array(
					'contact_phone'   => 'phone',
					'contact_email'   => 'info',
					'contact_address' => 'pin',
				);
				$roova_has_contact = false;
				foreach ( array_keys( $roova_contacts ) as $roova_key ) {
					if ( roova_option( $roova_key, '' ) ) {
						$roova_has_contact = true;
						break;
					}
				}

				if ( $roova_has_contact ) :
					?>
					<div class="roova-footer__contact">
						<?php
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
				<?php endif; ?>
			</div>

			<?php foreach ( $roova_footer_columns as $roova_location => $roova_heading ) : ?>
				<?php if ( has_nav_menu( $roova_location ) ) : ?>
					<div class="roova-footer__col">
						<span class="roova-footer__heading"><?php echo esc_html( $roova_heading ); ?></span>
						<nav aria-label="<?php echo esc_attr( $roova_heading ); ?>">
							<?php
							wp_nav_menu( array(
								'theme_location' => $roova_location,
								'container'      => false,
								'menu_class'     => 'roova-menu',
								'depth'          => 1,
								'fallback_cb'    => false,
							) );
							?>
						</nav>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>

		<?php if ( is_active_sidebar( 'roova-footer' ) ) : ?>
			<div class="roova-footer__widgets">
				<?php dynamic_sidebar( 'roova-footer' ); ?>
			</div>
		<?php endif; ?>

		<div class="roova-footer__bottom">
			<span>
				<?php
				printf(
					/* translators: 1: year, 2: site name */
					esc_html__( '© %1$s %2$s', 'roova' ),
					esc_html( date_i18n( 'Y' ) ),
					esc_html( get_bloginfo( 'name' ) )
				);
				?>
			</span>
			<?php
			$roova_footer_note = roova_option( 'footer_note', __( 'Made in Malaysia', 'roova' ) );
			if ( $roova_footer_note ) :
				?>
				<span><?php echo esc_html( $roova_footer_note ); ?></span>
			<?php endif; ?>
		</div>
	</div>
</footer>

<?php roova_room_modal(); ?>
<?php wp_footer(); ?>
</body>
</html>
