<?php
/**
 * Singular post and custom post type template.
 *
 * @package papounan2027
 */

get_header();
?>

<?php while ( have_posts() ) : ?>
	<?php the_post(); ?>
	<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<header class="entry-header">
			<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>

			<?php if ( 'post' === get_post_type() ) : ?>
				<p class="entry-meta">
					<time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
					<span class="byline">
						<?php
						printf(
							/* translators: %s: Author name. */
							esc_html__( 'by %s', 'papounan2027' ),
							'<a href="' . esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ) . '">' . esc_html( get_the_author() ) . '</a>'
						);
						?>
					</span>
				</p>
			<?php endif; ?>
		</header>

		<?php if ( has_post_thumbnail() ) : ?>
			<div class="post-thumbnail">
				<?php the_post_thumbnail(); ?>
			</div>
		<?php endif; ?>

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

		<?php if ( 'post' === get_post_type() ) : ?>
			<footer class="entry-footer">
				<?php the_category( ', ' ); ?>
				<?php the_tags( '<p class="tags-links">', ', ', '</p>' ); ?>
			</footer>
		<?php endif; ?>
	</article>

	<?php the_post_navigation(); ?>

	<?php if ( comments_open() || get_comments_number() ) : ?>
		<?php comments_template(); ?>
	<?php endif; ?>
<?php endwhile; ?>

<?php
get_footer();
