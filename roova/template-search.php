<?php
/**
 * Template Name: Hotel search results
 *
 * Lists hotels that can take the visitor's dates, party size and destination.
 *
 * Its own document rather than header.php + footer.php — the fifth page in the
 * theme to print one, for the same reason as the other four: the design puts
 * its own header here, a navy band carrying the wordmark, the account button
 * and the search bar the results answer to. Nothing else on the page should
 * compete with the search.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

$roova_criteria = roova_get_criteria();
$roova_results  = roova_has_woocommerce() ? roova_search_hotels( $roova_criteria ) : array();
$roova_results  = roova_has_woocommerce() ? roova_sort_search_results( $roova_results ) : $roova_results;
$roova_nights   = roova_nights( $roova_criteria['check_in'], $roova_criteria['check_out'] );
$roova_resolved = roova_has_woocommerce() ? roova_resolve_destination( $roova_criteria['destination'] ) : array( 'term' => null );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>

<body <?php body_class( 'roova-page-white roova-search-page' ); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#roova-content"><?php esc_html_e( 'Skip to results', 'roova' ); ?></a>

<?php roova_search_page_header(); ?>

<main id="roova-content" class="roova-sp">
	<div class="roova-sp__inner">

		<?php if ( function_exists( 'woocommerce_output_all_notices' ) ) : ?>
			<div class="roova-notices"><?php woocommerce_output_all_notices(); ?></div>
		<?php endif; ?>

		<header class="roova-sp__head">
			<div>
				<span class="roova-eyebrow">
					<span class="roova-sp__rule" aria-hidden="true"></span>
					<?php
					printf(
						/* translators: 1: check-in, 2: check-out, 3: nights, 4: guests */
						esc_html__( '%1$s — %2$s · %3$s · %4$s', 'roova' ),
						esc_html( roova_format_date( $roova_criteria['check_in'] ) ),
						esc_html( roova_format_date( $roova_criteria['check_out'] ) ),
						esc_html( sprintf( /* translators: %d: nights */ _n( '%d night', '%d nights', $roova_nights, 'roova' ), $roova_nights ) ),
						esc_html(
							sprintf(
								/* translators: %d: guests */
								_n( '%d guest', '%d guests', (int) $roova_criteria['adults'] + (int) $roova_criteria['children'], 'roova' ),
								(int) $roova_criteria['adults'] + (int) $roova_criteria['children']
							)
						)
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

			<?php if ( roova_has_woocommerce() ) : ?>
				<?php roova_search_sort_form( count( $roova_results ) ); ?>
			<?php endif; ?>
		</header>

		<?php if ( $roova_results ) : ?>
			<div class="roova-results">
				<?php foreach ( $roova_results as $roova_result ) : ?>
					<?php roova_search_result_card( $roova_result, $roova_criteria ); ?>
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
				<div class="roova-prose roova-sp__content"><?php the_content(); ?></div>
				<?php
			endif;
		endwhile;
		?>
	</div>
</main>

<footer class="roova-sp__foot">
	<p>
		<?php
		printf(
			/* translators: 1: year, 2: site name */
			esc_html__( '© %1$s %2$s', 'roova' ),
			esc_html( gmdate( 'Y' ) ),
			esc_html( get_bloginfo( 'name' ) )
		);
		?>
	</p>

	<?php
	if ( has_nav_menu( 'footer' ) ) {
		wp_nav_menu(
			array(
				'theme_location' => 'footer',
				'container'      => false,
				'depth'          => 1,
				'menu_class'     => 'roova-sp__foot-menu',
			)
		);
	}
	?>
</footer>

<?php wp_footer(); ?>
</body>
</html>
