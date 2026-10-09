<?php
/**
 * Main fallback template.
 *
 * @package papounan2027
 */

get_header();
?>

<?php if ( have_posts() ) : ?>
	<?php if ( is_home() && ! is_front_page() ) : ?>
		<header class="page-header">
			<h1 class="page-title"><?php single_post_title(); ?></h1>
		</header>
	<?php elseif ( is_search() ) : ?>
		<header class="page-header">
			<h1 class="page-title">
				<?php
				printf(
					/* translators: %s: Search query. */
					esc_html__( 'Search results for: %s', 'papounan2027' ),
					'<span>' . esc_html( get_search_query() ) . '</span>'
				);
				?>
			</h1>
		</header>
	<?php elseif ( is_archive() ) : ?>
		<header class="page-header">
			<?php the_archive_title( '<h1 class="page-title">', '</h1>' ); ?>
			<?php the_archive_description( '<div class="archive-description">', '</div>' ); ?>
		</header>
	<?php endif; ?>

	<?php while ( have_posts() ) : ?>
		<?php the_post(); ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<h2 class="entry-title"><a href="<?php the_permalink(); ?>" rel="bookmark"><?php the_title(); ?></a></h2>
			</header>

			<?php if ( has_post_thumbnail() ) : ?>
				<a class="post-thumbnail" href="<?php the_permalink(); ?>" aria-hidden="true" tabindex="-1">
					<?php the_post_thumbnail(); ?>
				</a>
			<?php endif; ?>

			<div class="entry-summary">
				<?php the_excerpt(); ?>
			</div>
		</article>
	<?php endwhile; ?>

	<?php the_posts_pagination(); ?>
<?php else : ?>
	<section class="no-results not-found">
		<header class="page-header">
			<h1 class="page-title"><?php esc_html_e( 'Nothing found', 'papounan2027' ); ?></h1>
		</header>
		<p><?php esc_html_e( 'No content matched your request. Try a search instead.', 'papounan2027' ); ?></p>
		<?php get_search_form(); ?>
	</section>
<?php endif; ?>

<?php
get_footer();
