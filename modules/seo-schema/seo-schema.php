<?php
/**
 * SEO schema: fixes for SEOPress PRO automatic schemas.
 *
 * SEOPress PRO 10.3 automatic schema #4139 (HotelRoom, post type `chambre`)
 * uses `%%post_thumbnail_url%%` for `image`. SEOPress only resolves that tag
 * when its context has `is_single`, which the automatic-schema path never sets,
 * so `image` comes out empty on the room pages.
 *
 * This module fills the empty value from the featured image on singular views.
 * It never overrides a value SEOPress already resolved, and does nothing if
 * SEOPress is inactive (the filter simply never fires).
 *
 * Filter: `seopress_get_tag_post_thumbnail_url_value` ($value, $context),
 * see wp-seopress/src/Tags/PostThumbnailUrl.php.
 *
 * @package PapounanSite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fall back to the featured image URL when SEOPress left the tag empty.
 *
 * @param string $value Value resolved by SEOPress.
 * @return string Featured image URL (full size) if $value was empty on a singular view, else $value.
 */
function papounan_seo_schema_post_thumbnail_url( $value ) {
	if ( '' === $value && is_singular() ) {
		$url = get_the_post_thumbnail_url( get_queried_object_id(), 'full' );
		return $url ? $url : $value;
	}
	return $value;
}
add_filter( 'seopress_get_tag_post_thumbnail_url_value', 'papounan_seo_schema_post_thumbnail_url' );
