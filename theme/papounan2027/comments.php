<?php
/**
 * Comments template.
 *
 * @package papounan2027
 */

if ( post_password_required() ) {
	return;
}
?>

<section id="comments" class="comments-area">
	<?php if ( have_comments() ) : ?>
		<h2 class="comments-title">
			<?php
			$papounan2027_comment_count = get_comments_number();
			printf(
				/* translators: 1: Number of comments, 2: Post title. */
				esc_html( _n( '%1$s comment on “%2$s”', '%1$s comments on “%2$s”', $papounan2027_comment_count, 'papounan2027' ) ),
				esc_html( number_format_i18n( $papounan2027_comment_count ) ),
				esc_html( get_the_title() )
			);
			?>
		</h2>

		<ol class="comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'short_ping'  => true,
					'avatar_size' => 48,
				)
			);
			?>
		</ol>

		<?php the_comments_navigation(); ?>
	<?php endif; ?>

	<?php if ( ! comments_open() && get_comments_number() ) : ?>
		<p class="no-comments"><?php esc_html_e( 'Comments are closed.', 'papounan2027' ); ?></p>
	<?php endif; ?>

	<?php comment_form(); ?>
</section>
