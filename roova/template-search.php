<?php
/**
 * Template Name: Hotel search results
 *
 * Lists hotels that can take the visitor's dates, party size and destination.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

get_header();

$roova_criteria = roova_get_criteria();
$roova_results  = roova_has_woocommerce() ? roova_search_hotels( $roova_criteria ) : array();
$roova_nights   = roova_nights( $roova_criteria['check_in'], $roova_criteria['check_out'] );
$roova_resolved = roova_has_woocommerce() ? roova_resolve_destination( $roova_criteria['destination'] ) : array( 'term' => null );
?>

<section class="roova-search-header">
	<div class="wrap">
		<?php if ( roova_has_woocommerce() ) : ?>
			<?php roova_search_form(); ?>
		<?php endif; ?>
	</div>
</section>

<div class="wrap roova-search-results">
	<?php if ( function_exists( 'woocommerce_output_all_notices' ) ) : ?>
		<div class="roova-notices"><?php woocommerce_output_all_notices(); ?></div>
	<?php endif; ?>

	<header class="roova-section__head">
		<div>
			<span class="roova-eyebrow">
				<?php
				printf(
					/* translators: 1: check-in, 2: check-out, 3: nights */
					esc_html__( '%1$s — %2$s · %3$s', 'roova' ),
					esc_html( roova_format_date( $roova_criteria['check_in'] ) ),
					esc_html( roova_format_date( $roova_criteria['check_out'] ) ),
					esc_html( sprintf( /* translators: %d: nights */ _n( '%d night', '%d nights', $roova_nights, 'roova' ), $roova_nights ) )
				);
				?>
			</span>
			<h1>
				<?php
				if ( ! empty( $roova_resolved['term'] ) ) {
					printf(
						/* translators: %s: destination name */
						esc_html__( 'Hotels in %s', 'roova' ),
						esc_html( $roova_resolved['term']->name )
					);
				} elseif ( $roova_criteria['destination'] ) {
					printf(
						/* translators: %s: search term */
						esc_html__( 'Results for “%s”', 'roova' ),
						esc_html( $roova_criteria['destination'] )
					);
				} else {
					esc_html_e( 'All our hotels', 'roova' );
				}
				?>
			</h1>
		</div>

		<p class="roova-search-results__count">
			<?php
			printf(
				/* translators: %d: number of hotels */
				esc_html( _n( '%d hotel', '%d hotels', count( $roova_results ), 'roova' ) ),
				count( $roova_results )
			);
			?>
		</p>
	</header>

	<?php if ( $roova_results ) : ?>
		<div class="roova-results">
			<?php foreach ( $roova_results as $roova_result ) : ?>
				<?php
				$roova_hotel_id = $roova_result['hotel_id'];
				$roova_details  = roova_get_hotel_details( $roova_hotel_id );
				$roova_url      = roova_criteria_url( get_permalink( $roova_hotel_id ) );
				?>
				<article class="roova-result <?php echo $roova_result['has_availability'] ? '' : 'roova-result--unavailable'; ?>">
					<a class="roova-result__media" href="<?php echo esc_url( $roova_url ); ?>">
						<?php
						if ( has_post_thumbnail( $roova_hotel_id ) ) {
							echo get_the_post_thumbnail( $roova_hotel_id, 'roova-hotel-card', array( 'loading' => 'lazy' ) );
						} else {
							echo '<span class="roova-hotel-card__placeholder" aria-hidden="true"></span>';
						}
						?>
					</a>

					<div class="roova-result__body">
						<?php if ( (int) $roova_details['stars'] ) : ?>
							<div class="roova-hotel-card__stars"><?php echo roova_stars( (int) $roova_details['stars'], 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						<?php endif; ?>

						<h2><a href="<?php echo esc_url( $roova_url ); ?>"><?php echo esc_html( get_the_title( $roova_hotel_id ) ); ?></a></h2>

						<p class="roova-result__loc">
							<?php roova_the_icon( 'pin', 14 ); ?>
							<?php echo esc_html( roova_hotel_location_label( $roova_hotel_id ) ); ?>
						</p>

						<?php
						$roova_amenities = array_slice( roova_get_amenities( $roova_hotel_id ), 0, 5 );
						if ( $roova_amenities ) :
							?>
							<div class="roova-result__amenities">
								<?php foreach ( $roova_amenities as $roova_term ) : ?>
									<span>
										<?php echo roova_amenity_icon( $roova_term, 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
										<?php echo esc_html( $roova_term->name ); ?>
									</span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>

					<div class="roova-result__aside">
						<?php if ( $roova_result['has_availability'] ) : ?>
							<?php if ( $roova_result['rooms_left'] > 0 && $roova_result['rooms_left'] <= 3 ) : ?>
								<span class="roova-room__scarcity">
									<?php
									printf(
										/* translators: %d: rooms left */
										esc_html( _n( 'Only %d room left', 'Only %d rooms left', $roova_result['rooms_left'], 'roova' ) ),
										(int) $roova_result['rooms_left']
									);
									?>
								</span>
							<?php endif; ?>

							<div class="roova-result__price">
								<span><?php esc_html_e( 'From', 'roova' ); ?></span>
								<strong><?php echo wp_kses_post( wc_price( $roova_result['rate'] ) ); ?></strong>
								<span><?php esc_html_e( '/ night', 'roova' ); ?></span>
							</div>

							<a class="roova-btn" href="<?php echo esc_url( $roova_url ); ?>#rooms">
								<?php esc_html_e( 'See rooms', 'roova' ); ?>
							</a>
						<?php else : ?>
							<p class="roova-unavailable"><?php esc_html_e( 'No rooms free for these dates', 'roova' ); ?></p>
							<a class="roova-btn roova-btn--ghost" href="<?php echo esc_url( get_permalink( $roova_hotel_id ) ); ?>">
								<?php esc_html_e( 'View hotel', 'roova' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<p class="roova-empty">
			<?php esc_html_e( 'No hotels matched that search. Try another destination or different dates.', 'roova' ); ?>
		</p>
	<?php endif; ?>

	<?php
	// Anything typed into the page editor renders under the results.
	while ( have_posts() ) :
		the_post();
		if ( '' !== trim( get_the_content() ) ) :
			?>
			<div class="roova-prose roova-search-results__content"><?php the_content(); ?></div>
			<?php
		endif;
	endwhile;
	?>
</div>

<?php
get_footer();
