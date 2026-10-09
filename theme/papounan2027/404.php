<?php
/**
 * 404 template.
 *
 * @package papounan2027
 */

get_header();
?>

<section class="error-404 not-found">
	<header class="page-header">
		<h1 class="page-title"><?php esc_html_e( 'Page not found', 'papounan2027' ); ?></h1>
	</header>

	<div class="page-content">
		<p><?php esc_html_e( 'The page may have moved or no longer exists. Try searching the site.', 'papounan2027' ); ?></p>
		<?php get_search_form(); ?>
	</div>
</section>

<?php
get_footer();
