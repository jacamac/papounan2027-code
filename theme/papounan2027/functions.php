<?php
/**
 * Papounan 2027 theme functions.
 *
 * Derived from CrocoBuilder Blank 1.0.0 (Crocoblock, GPLv2).
 *
 * @package papounan2027
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Configure theme defaults and register WordPress features.
 */
function papounan2027_setup() {
	load_theme_textdomain( 'papounan2027', get_template_directory() . '/languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'align-wide' );
	add_theme_support(
		'html5',
		array(
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'search-form',
			'style',
			'script',
			'navigation-widgets',
		)
	);
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 100,
			'width'       => 300,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	register_nav_menus(
		array(
			'primary' => __( 'Primary menu', 'papounan2027' ),
		)
	);
}
add_action( 'after_setup_theme', 'papounan2027_setup' );

/**
 * Set a conservative default content width for embeds and media.
 */
function papounan2027_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'papounan2027_content_width', 960 );
}
add_action( 'after_setup_theme', 'papounan2027_content_width', 0 );

/**
 * Enqueue the main stylesheet (browser reset only).
 */
function papounan2027_enqueue_assets() {
	$theme = wp_get_theme();

	wp_enqueue_style(
		'papounan2027-style',
		get_stylesheet_uri(),
		array(),
		$theme->get( 'Version' )
	);

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'papounan2027_enqueue_assets' );
