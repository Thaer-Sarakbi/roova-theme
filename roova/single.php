<?php
/**
 * Single post.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="wrap roova-page">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article <?php post_class(); ?>>
			<header class="roova-page__head">
				<h1><?php the_title(); ?></h1>
				<p class="roova-post-meta"><?php echo esc_html( get_the_date() ); ?></p>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="roova-page__media"><?php the_post_thumbnail( 'roova-hotel-hero' ); ?></figure>
			<?php endif; ?>

			<div class="roova-prose"><?php the_content(); ?></div>
		</article>

		<?php
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}
		?>
		<?php
	endwhile;
	?>
</div>

<?php
get_footer();
