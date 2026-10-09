<?php
/**
 * Page template.
 *
 * @package papounan2027
 */

get_header();
?>

<?php while ( have_posts() ) : ?>
	<?php the_post(); ?>
	<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<div class="entry-content">
			<?php the_content(); ?>
			<?php
			wp_link_pages(
				array(
					'before' => '<nav class="page-links">' . esc_html__( 'Pages:', 'papounan2027' ),
					'after'  => '</nav>',
				)
			);
			?>
		</div>
	</article>

	<?php if ( comments_open() || get_comments_number() ) : ?>
		<?php comments_template(); ?>
	<?php endif; ?>
<?php endwhile; ?>

<?php
get_footer();
