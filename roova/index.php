<?php
/**
 * Fallback template for archives and the blog.
 *
 * @package Roova
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<div class="wrap roova-archive">
	<?php if ( ! is_front_page() && ( is_archive() || is_search() || is_home() ) ) : ?>
		<header class="roova-section__head">
			<div>
				<h1>
					<?php
					if ( is_search() ) {
						printf(
							/* translators: %s: search query */
							esc_html__( 'Search results for “%s”', 'roova' ),
							esc_html( get_search_query() )
						);
					} elseif ( is_archive() ) {
						the_archive_title();
					} else {
						echo esc_html( get_the_title( (int) get_option( 'page_for_posts' ) ) );
					}
					?>
				</h1>
			</div>
		</header>
	<?php endif; ?>

	<?php if ( have_posts() ) : ?>
		<div class="roova-posts">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article <?php post_class( 'roova-post-card' ); ?>>
					<?php if ( has_post_thumbnail() ) : ?>
						<a class="roova-post-card__media" href="<?php the_permalink(); ?>">
							<?php the_post_thumbnail( 'roova-hotel-card', array( 'loading' => 'lazy' ) ); ?>
						</a>
					<?php endif; ?>
					<div class="roova-post-card__body">
						<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<div class="roova-post-card__excerpt"><?php the_excerpt(); ?></div>
					</div>
				</article>
				<?php
			endwhile;
			?>
		</div>

		<?php
		the_posts_pagination( array(
			'mid_size'  => 1,
			'prev_text' => esc_html__( 'Previous', 'roova' ),
			'next_text' => esc_html__( 'Next', 'roova' ),
		) );
		?>
	<?php else : ?>
		<p class="roova-empty"><?php esc_html_e( 'Nothing found.', 'roova' ); ?></p>
	<?php endif; ?>
</div>

<?php
get_footer();
