<?php
/**
 * Hotel details page.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

get_header();

the_post();

$roova_hotel_id = get_the_ID();
$roova_product  = wc_get_product( $roova_hotel_id );
$roova_details  = roova_get_hotel_details( $roova_hotel_id );
$roova_criteria = roova_get_criteria();
$roova_rooms    = Roova_Availability::get_bookable_rooms( $roova_hotel_id, $roova_criteria );
$roova_lowest   = Roova_Availability::hotel_lowest_rate( $roova_hotel_id, $roova_criteria );

// Gallery images: featured first, then the product gallery.
$roova_image_ids = $roova_product ? $roova_product->get_gallery_image_ids() : array();
if ( $roova_product && $roova_product->get_image_id() ) {
	array_unshift( $roova_image_ids, $roova_product->get_image_id() );
}
$roova_image_ids = array_values( array_unique( array_filter( array_map( 'absint', $roova_image_ids ) ) ) );
?>

<div class="roova-breadcrumb">
	<div class="wrap wrap--wide">
		<a href="<?php echo esc_url( roova_search_url() ); ?>"><?php esc_html_e( 'Our hotels', 'roova' ); ?></a>
		<span aria-hidden="true">/</span>
		<?php
		$roova_crumb_destinations = roova_get_hotel_destinations( $roova_hotel_id );
		if ( $roova_crumb_destinations ) :
			?>
			<a href="<?php echo esc_url( roova_destination_url( $roova_crumb_destinations[0] ) ); ?>">
				<?php echo esc_html( $roova_crumb_destinations[0]->name ); ?>
			</a>
			<span aria-hidden="true">/</span>
		<?php endif; ?>
		<span><?php echo esc_html( get_the_title() ); ?></span>
	</div>
</div>

<div class="wrap wrap--wide">
	<?php if ( function_exists( 'woocommerce_output_all_notices' ) ) : ?>
		<div class="roova-notices"><?php woocommerce_output_all_notices(); ?></div>
	<?php endif; ?>

	<header class="roova-hotel-head">
		<?php if ( (int) $roova_details['stars'] ) : ?>
			<div class="roova-hotel-head__stars"><?php echo roova_stars( (int) $roova_details['stars'], 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<?php endif; ?>

		<h1><?php the_title(); ?></h1>

		<?php
		if ( function_exists( 'roova_like_button' ) ) {
			roova_like_button( $roova_hotel_id, array(
				'class' => 'roova-like--head',
				'size'  => 18,
			) );
		}
		?>

		<div class="roova-hotel-head__loc">
			<?php roova_the_icon( 'pin', 15 ); ?>

			<?php
			/*
			 * The street address is what a guest wants under the name. The
			 * destination stays a link in the breadcrumb above, and stands in
			 * here only when no address has been entered. Addresses come from a
			 * textarea, so flatten them onto one line.
			 */
			$roova_head_address = trim( preg_replace( '/\s+/', ' ', (string) $roova_details['address'] ) );

			if ( $roova_head_address ) :
				?>
				<span class="roova-hotel-head__address"><?php echo esc_html( $roova_head_address ); ?></span>
			<?php else : ?>
				<?php roova_hotel_destination_links( $roova_hotel_id ); ?>
			<?php endif; ?>

			<?php if ( $roova_details['address'] || ( $roova_details['lat'] && $roova_details['lng'] ) ) : ?>
				<?php
				$roova_query = ( $roova_details['lat'] && $roova_details['lng'] )
					? $roova_details['lat'] . ',' . $roova_details['lng']
					: $roova_details['address'];
				?>
				<a href="<?php echo esc_url( 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $roova_query ) ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'View on map', 'roova' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</header>

	<?php if ( $roova_image_ids ) : ?>
		<div class="roova-gallery" data-roova-gallery>
			<div class="roova-gallery__main">
				<?php foreach ( $roova_image_ids as $roova_i => $roova_image_id ) : ?>
					<?php $roova_blur = wp_get_attachment_image_url( $roova_image_id, 'roova-hotel-card' ); ?>
					<figure class="roova-gallery__slide <?php echo 0 === $roova_i ? 'is-active' : ''; ?>" data-roova-gallery-slide="<?php echo esc_attr( $roova_i ); ?>">
						<?php if ( $roova_blur ) : ?>
							<span class="roova-gallery__blur" style="background-image:url('<?php echo esc_url( $roova_blur ); ?>')" aria-hidden="true"></span>
						<?php endif; ?>
						<?php
						/*
						 * The full size, not roova-hotel-hero: that size is a hard
						 * crop, so a "whole" photo would really be WordPress's
						 * centre cut of it. srcset still lets the browser pick a
						 * sensible file for the width it needs.
						 */
						echo wp_get_attachment_image(
							$roova_image_id,
							'full',
							false,
							array(
								'loading' => 0 === $roova_i ? 'eager' : 'lazy',
								'sizes'   => '(max-width: 1400px) 100vw, 1400px',
							)
						);
						?>
					</figure>
				<?php endforeach; ?>

				<?php if ( count( $roova_image_ids ) > 1 ) : ?>
					<button type="button" class="roova-gallery__arrow roova-gallery__arrow--prev" data-roova-gallery-step="-1" aria-label="<?php esc_attr_e( 'Previous photo', 'roova' ); ?>">
						<?php roova_the_icon( 'chevron-left', 16 ); ?>
					</button>
					<button type="button" class="roova-gallery__arrow roova-gallery__arrow--next" data-roova-gallery-step="1" aria-label="<?php esc_attr_e( 'Next photo', 'roova' ); ?>">
						<?php roova_the_icon( 'chevron-right', 16 ); ?>
					</button>
					<span class="roova-gallery__count"><span data-roova-gallery-index>1</span> / <?php echo esc_html( count( $roova_image_ids ) ); ?></span>
				<?php endif; ?>
			</div>

			<?php if ( count( $roova_image_ids ) > 1 ) : ?>
				<div class="roova-gallery__thumbs" role="tablist" aria-label="<?php esc_attr_e( 'Hotel photos', 'roova' ); ?>">
					<?php foreach ( $roova_image_ids as $roova_i => $roova_thumb_id ) : ?>
						<button type="button"
							class="roova-gallery__thumb <?php echo 0 === $roova_i ? 'is-active' : ''; ?>"
							data-roova-gallery-go="<?php echo esc_attr( $roova_i ); ?>"
							role="tab"
							aria-selected="<?php echo 0 === $roova_i ? 'true' : 'false'; ?>"
							aria-label="<?php echo esc_attr( sprintf( /* translators: 1: photo number, 2: total photos */ __( 'Photo %1$d of %2$d', 'roova' ), $roova_i + 1, count( $roova_image_ids ) ) ); ?>">
							<?php echo wp_get_attachment_image( $roova_thumb_id, 'roova-room-thumb', false, array( 'loading' => 'lazy' ) ); ?>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="roova-layout">
		<div class="roova-layout__main">

			<?php if ( get_the_content() ) : ?>
				<section class="roova-card">
					<h2 class="roova-card__title"><?php esc_html_e( 'Description', 'roova' ); ?></h2>
					<div class="roova-prose roova-clamp" data-roova-clamp>
						<?php the_content(); ?>
					</div>
					<button type="button" class="roova-link-btn" data-roova-clamp-toggle
						data-more="<?php esc_attr_e( 'Read more', 'roova' ); ?>"
						data-less="<?php esc_attr_e( 'Read less', 'roova' ); ?>">
						<?php esc_html_e( 'Read more', 'roova' ); ?>
					</button>
				</section>
			<?php endif; ?>

			<?php if ( roova_get_facilities( $roova_hotel_id ) ) : ?>
				<section class="roova-card" id="facilities">
					<h2 class="roova-card__title"><?php esc_html_e( 'Facilities', 'roova' ); ?></h2>
					<?php roova_facilities_grid( $roova_hotel_id ); ?>
				</section>
			<?php endif; ?>

			<section class="roova-card" id="rooms">
				<h2 class="roova-card__title"><?php esc_html_e( 'Select your room', 'roova' ); ?></h2>

				<p class="roova-stay-summary">
					<?php roova_the_icon( 'calendar', 14 ); ?>
					<?php
					$roova_nights = roova_nights( $roova_criteria['check_in'], $roova_criteria['check_out'] );
					printf(
						/* translators: 1: check-in date, 2: check-out date, 3: nights phrase, 4: guests phrase */
						esc_html__( '%1$s — %2$s · %3$s · %4$s', 'roova' ),
						esc_html( roova_format_date( $roova_criteria['check_in'] ) ),
						esc_html( roova_format_date( $roova_criteria['check_out'] ) ),
						esc_html( sprintf( /* translators: %d: nights */ _n( '%d night', '%d nights', $roova_nights, 'roova' ), $roova_nights ) ),
						esc_html( Roova_Cart::guests_label( $roova_criteria['adults'], $roova_criteria['children'] ) )
					);
					?>
				</p>

				<?php if ( $roova_rooms ) : ?>
					<?php foreach ( $roova_rooms as $roova_room_id => $roova_room ) : ?>
						<?php roova_room_card( $roova_room_id, $roova_room, $roova_criteria ); ?>
					<?php endforeach; ?>
				<?php else : ?>
					<p class="roova-empty"><?php esc_html_e( 'No rooms have been added to this hotel yet.', 'roova' ); ?></p>
				<?php endif; ?>
			</section>

			<?php if ( roova_get_amenities( $roova_hotel_id ) ) : ?>
				<section class="roova-card">
					<h2 class="roova-card__title"><?php esc_html_e( 'Amenities', 'roova' ); ?></h2>
					<?php roova_amenities_grid( $roova_hotel_id ); ?>
				</section>
			<?php endif; ?>

			<?php
			$roova_has_landmarks = roova_parse_landmarks( $roova_details['landmarks_popular'] ) || roova_parse_landmarks( $roova_details['landmarks_nearby'] );
			if ( $roova_has_landmarks ) :
				?>
				<section class="roova-card">
					<h2 class="roova-card__title"><?php esc_html_e( "What's nearby", 'roova' ); ?></h2>
					<?php roova_landmarks( $roova_hotel_id ); ?>
				</section>
			<?php endif; ?>
		</div>

		<aside class="roova-layout__side">
			<?php roova_review_box( $roova_hotel_id ); ?>
			<?php roova_booking_box( $roova_hotel_id, $roova_criteria, $roova_lowest['rate'] ); ?>
			<?php roova_hotel_map( $roova_hotel_id ); ?>
		</aside>
	</div>
</div>

<?php
get_footer();
