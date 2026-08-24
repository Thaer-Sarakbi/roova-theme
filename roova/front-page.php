<?php
/**
 * Homepage: hero and search, guarantees, a photo band, the hotel collection,
 * the destination mosaic, a second band and the coverage map.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

get_header();

roova_hero();
roova_guarantees_row();

// Defaults here have to match the ones registered in the Customizer:
// get_theme_mod() falls back to what the caller passes, not to the setting.
roova_image_band( array(
	'image_id'  => (int) roova_option( 'band_image', 0 ),
	'fallback'  => 'band-1.jpg',
	'eyebrow'   => roova_option( 'band_eyebrow', __( 'Klang Valley & Malacca', 'roova' ) ),
	'statement' => roova_option( 'band_statement', __( 'A short walk from wherever you\'re headed.', 'roova' ) ),
) );
?>

<?php if ( roova_has_woocommerce() ) : ?>

	<?php
	$roova_hotel_ids = roova_get_hotel_ids();
	if ( roova_option( 'show_hotels', true ) && $roova_hotel_ids ) :
		// The grid shows the first four; the link opens the rest.
		$roova_shown = array_slice( $roova_hotel_ids, 0, 4 );
		$roova_total = count( $roova_hotel_ids );
		?>
		<section class="roova-section roova-section--hotels" id="hotels">
			<div class="wrap">
				<div class="roova-section__head" data-roova-reveal>
					<div>
						<span class="roova-eyebrow"><?php echo esc_html( roova_option( 'hotels_eyebrow', __( 'The collection', 'roova' ) ) ); ?></span>
						<h2><?php echo esc_html( roova_option( 'hotels_title', __( 'Our hotels', 'roova' ) ) ); ?></h2>
					</div>

					<?php if ( $roova_total > count( $roova_shown ) ) : ?>
						<a class="roova-section__more" href="<?php echo esc_url( roova_criteria_url( roova_search_url() ) ); ?>">
							<?php
							printf(
								/* translators: %d: number of hotels */
								esc_html__( 'View all %d →', 'roova' ),
								(int) $roova_total
							);
							?>
						</a>
					<?php endif; ?>
				</div>

				<div class="roova-hotels-grid" data-roova-reveal data-roova-stagger>
					<?php foreach ( $roova_shown as $roova_hotel_id ) : ?>
						<?php roova_hotel_card( $roova_hotel_id ); ?>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php
	$roova_destinations = roova_get_destinations();
	if ( roova_option( 'show_destinations', true ) && $roova_destinations ) :
		?>
		<section class="roova-section roova-section--destinations" id="destinations">
			<div class="wrap">
				<div class="roova-section__head" data-roova-reveal>
					<div>
						<span class="roova-eyebrow"><?php echo esc_html( roova_option( 'destinations_eyebrow', __( 'Where we are', 'roova' ) ) ); ?></span>
						<h2><?php echo esc_html( roova_option( 'destinations_title', __( 'Explore our destinations', 'roova' ) ) ); ?></h2>
					</div>
				</div>

				<div class="roova-destinations" data-roova-reveal data-roova-stagger>
					<?php foreach ( $roova_destinations as $roova_index => $roova_destination ) : ?>
						<?php roova_destination_tile( $roova_destination, roova_destination_span_class( $roova_index ) ); ?>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php
	roova_image_band( array(
		'image_id' => (int) roova_option( 'band2_image', 0 ),
		'fallback' => 'band-2.jpg',
		'class'    => 'roova-band--tall',
	) );
	?>

	<?php if ( roova_show_coverage_map() ) : ?>
		<?php roova_coverage_map(); ?>
	<?php endif; ?>

<?php endif; ?>

<?php if ( have_posts() ) : ?>
	<?php
	// Anything the site owner puts in the homepage editor renders below.
	while ( have_posts() ) :
		the_post();
		$roova_content = get_the_content();
		if ( '' === trim( $roova_content ) ) {
			continue;
		}
		?>
		<section class="roova-section">
			<div class="wrap roova-prose">
				<?php the_content(); ?>
			</div>
		</section>
		<?php
	endwhile;
	?>
<?php endif; ?>

<?php
get_footer();
