<?php
/**
 * 404 page.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="wrap roova-page roova-404">
	<h1><?php esc_html_e( 'We could not find that page', 'roova' ); ?></h1>
	<p><?php esc_html_e( 'It may have moved. Try searching for a room instead.', 'roova' ); ?></p>

	<?php if ( roova_has_woocommerce() ) : ?>
		<div class="roova-404__search"><?php roova_search_form(); ?></div>
	<?php endif; ?>

	<p><a class="roova-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to the homepage', 'roova' ); ?></a></p>
</div>

<?php
get_footer();
