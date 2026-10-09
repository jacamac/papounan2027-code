<?php
/**
 * Site header.
 *
 * Content is wrapped in a <div>, not <main>: CrocoBuilder Theme Builder
 * templates output the page's single <main> landmark.
 *
 * @package papounan2027
 */
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="screen-reader-text" href="#primary"><?php esc_html_e( 'Skip to content', 'papounan2027' ); ?></a>

<header id="masthead" class="site-header">
	<div class="site-branding">
		<?php if ( has_custom_logo() ) : ?>
			<?php the_custom_logo(); ?>
		<?php endif; ?>

		<?php if ( is_front_page() && is_home() ) : ?>
			<h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></h1>
		<?php else : ?>
			<p class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></p>
		<?php endif; ?>

		<?php $papounan2027_description = get_bloginfo( 'description', 'display' ); ?>
		<?php if ( $papounan2027_description || is_customize_preview() ) : ?>
			<p class="site-description"><?php echo esc_html( $papounan2027_description ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( has_nav_menu( 'primary' ) ) : ?>
		<nav id="site-navigation" class="primary-navigation" aria-label="<?php esc_attr_e( 'Primary menu', 'papounan2027' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'menu_id'        => 'primary-menu',
					'container'      => false,
				)
			);
			?>
		</nav>
	<?php endif; ?>
</header>

<div id="primary" class="site-main">
