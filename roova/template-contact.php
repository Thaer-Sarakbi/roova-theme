<?php
/**
 * Template Name: Contact us
 *
 * The desk: the ways to reach the company, where its office is, and when it is
 * open.
 *
 * Its own document rather than header.php + footer.php — the eighth page in the
 * theme to print one, for the reason the search results page does: the design
 * gives it a navy band of its own. That band still carries the Primary menu,
 * the account button and the hamburger, so a guest who arrives here from a
 * search engine can reach the rest of the site.
 *
 * Everything on the page is a Customizer setting, under Roova hotel theme →
 * Contact page and → Social links. See inc/contact.php.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>

<body <?php body_class( 'roova-page-white roova-contact-page' ); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#roova-content"><?php esc_html_e( 'Skip to content', 'roova' ); ?></a>

<?php roova_contact_page_header(); ?>

<main id="roova-content">
	<?php roova_contact_hero(); ?>

	<div class="roova-cp__inner">
		<?php if ( function_exists( 'woocommerce_output_all_notices' ) ) : ?>
			<div class="roova-notices"><?php woocommerce_output_all_notices(); ?></div>
		<?php endif; ?>

		<?php roova_contact_channels_row(); ?>
		<?php roova_contact_office(); ?>

		<?php
		// Anything typed into the page editor renders between the office panel
		// and the social row — a legal notice, a company registration number,
		// a form from a plugin.
		while ( have_posts() ) :
			the_post();
			if ( '' !== trim( get_the_content() ) ) :
				?>
				<div class="roova-prose roova-cp__content"><?php the_content(); ?></div>
				<?php
			endif;
		endwhile;
		?>

		<?php roova_contact_follow(); ?>
	</div>
</main>

<footer class="roova-cp__foot">
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
				'menu_class'     => 'roova-cp__foot-menu',
			)
		);
	}
	?>
</footer>

<?php wp_footer(); ?>
</body>
</html>
