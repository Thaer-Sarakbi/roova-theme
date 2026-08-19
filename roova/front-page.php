<?php
/**
 * Homepage: hero, search, hotel collection and destinations.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

get_header();

$roova_hero_image = (int) roova_option( 'hero_image', 0 );
$roova_hero_url   = $roova_hero_image ? wp_get_attachment_image_url( $roova_hero_image, 'full' ) : '';
?>

<section class="roova-hero <?php echo $roova_hero_url ? 'roova-hero--image' : ''; ?>"
	<?php echo $roova_hero_url ? 'style="background-image:linear-gradient(180deg,rgba(10,46,69,.78),rgba(12,59,87,.65)),url(' . esc_url( $roova_hero_url ) . ')"' : ''; ?>>

	<div class="roova-hero__content">
		<?php
		$roova_eyebrow  = roova_option( 'hero_eyebrow', '' );
		$roova_title    = roova_option( 'hero_title', __( 'Stay close to everywhere that matters to you', 'roova' ) );
		$roova_subtitle = roova_option( 'hero_subtitle', '' );
		?>
		<?php if ( $roova_eyebrow ) : ?>
			<span class="roova-hero__eyebrow"><?php echo esc_html( $roova_eyebrow ); ?></span>
		<?php endif; ?>

		<h1><?php echo esc_html( $roova_title ); ?></h1>

		<?php if ( $roova_subtitle ) : ?>
			<p><?php echo esc_html( $roova_subtitle ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( roova_has_woocommerce() ) : ?>
		<div class="wrap roova-hero__search">
			<?php roova_search_form(); ?>
		</div>
	<?php endif; ?>

	<svg class="roova-hero__waves" viewBox="0 0 1440 90" preserveAspectRatio="none" aria-hidden="true" focusable="false">
		<path d="M0,40 C240,90 480,0 720,30 C960,60 1200,10 1440,40 L1440,90 L0,90 Z" fill="var(--roova-sand)"/>
	</svg>
</section>

<?php if ( roova_has_woocommerce() ) : ?>

	<?php
	$roova_hotel_ids = roova_get_hotel_ids();
	if ( roova_option( 'show_hotels', true ) && $roova_hotel_ids ) :
		?>
		<section class="roova-section roova-section--sand" id="hotels">
			<div class="wrap">
				<div class="roova-section__head">
					<div>
						<span class="roova-eyebrow"><?php echo esc_html( roova_option( 'hotels_eyebrow', __( 'The collection', 'roova' ) ) ); ?></span>
						<h2><?php echo esc_html( roova_option( 'hotels_title', __( 'Our hotels', 'roova' ) ) ); ?></h2>
					</div>

					<?php if ( count( $roova_hotel_ids ) > 3 ) : ?>
						<div class="roova-slider__nav">
							<button type="button" class="roova-slider__arrow" data-roova-slide="-1" aria-label="<?php esc_attr_e( 'Previous hotels', 'roova' ); ?>">
								<?php roova_the_icon( 'chevron-left', 16 ); ?>
							</button>
							<button type="button" class="roova-slider__arrow" data-roova-slide="1" aria-label="<?php esc_attr_e( 'Next hotels', 'roova' ); ?>">
								<?php roova_the_icon( 'chevron-right', 16 ); ?>
							</button>
						</div>
					<?php endif; ?>
				</div>

				<div class="roova-slider" data-roova-slider>
					<?php foreach ( $roova_hotel_ids as $roova_hotel_id ) : ?>
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
		<section class="roova-section" id="destinations">
			<div class="wrap">
				<div class="roova-section__head">
					<div>
						<span class="roova-eyebrow"><?php echo esc_html( roova_option( 'destinations_eyebrow', __( 'Where we are', 'roova' ) ) ); ?></span>
						<h2><?php echo esc_html( roova_option( 'destinations_title', __( 'Explore our destinations', 'roova' ) ) ); ?></h2>
					</div>
				</div>

				<div class="roova-destinations roova-destinations--<?php echo esc_attr( min( 6, count( $roova_destinations ) ) ); ?>">
					<?php foreach ( $roova_destinations as $roova_index => $roova_destination ) : ?>
						<?php roova_destination_tile( $roova_destination, 'roova-destination--' . ( $roova_index + 1 ) ); ?>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
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
