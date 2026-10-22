<?php
/**
 * Click tracking: Matomo events from `data-trk` attributes.
 *
 * In CrocoBuilder a trackable element only carries `data-trk="<placement>"`
 * (and optionally `data-trk-action="<action>"`); click-tracking.js, loaded on
 * every front-end page, turns clicks on it into
 *   _paq.push( [ 'trackEvent', 'Navigation', <action>, <placement> · <room slug> ] )
 * It also turns the availability calendar's `bac:book` event into
 * `calendrier · <room slug>`. No list of buttons here, no JS in the builder.
 *
 * The room slug is output by PHP on single room pages as the body class
 * `papounan-room-<slug>`: static per page (safe with Cloudflare's HTML cache),
 * the same in every language, and the same value as the booking engine's
 * Matomo events (`$post->post_name`, papounan2027-booking AcfRoomContentRepository).
 *
 * @package PapounanSite
 */

defined( 'ABSPATH' ) || exit;

const PAPOUNAN_TRK_ROOM_CLASS = 'papounan-room-';

/**
 * Register the script.
 */
function papounan_trk_init(): void {
	wp_register_script(
		'papounan-click-tracking',
		PAPOUNAN_SITE_URL . 'modules/click-tracking/click-tracking.js',
		array(),
		PAPOUNAN_SITE_VERSION,
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}
add_action( 'init', 'papounan_trk_init' );

/**
 * Enqueue on every front-end page (any element may carry `data-trk`).
 */
function papounan_trk_enqueue(): void {
	wp_enqueue_script( 'papounan-click-tracking' );
}
add_action( 'wp_enqueue_scripts', 'papounan_trk_enqueue' );

/**
 * Room slug as a body class on single room pages.
 *
 * @param string[] $classes Body classes.
 * @return string[]
 */
function papounan_trk_body_class( $classes ) {
	if ( ! is_singular( 'chambre' ) ) {
		return $classes;
	}
	// Inside CrocoBuilder templates get_the_ID() is not always the room: use the queried object.
	$slug = (string) get_post_field( 'post_name', get_queried_object_id(), 'raw' );
	if ( preg_match( '/^[a-z0-9-]+$/', $slug ) ) {
		$classes[] = PAPOUNAN_TRK_ROOM_CLASS . $slug;
	}
	return $classes;
}
add_filter( 'body_class', 'papounan_trk_body_class' );
