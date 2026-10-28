<?php
/**
 * Rating schema: the platform ratings (CPT `review_platform`) as JSON-LD.
 *
 * One AggregateRating node per platform whose ACF true/false
 * « Publier dans les données structurées » (`show_in_schema`, field group in
 * the ACF UI, default off) is on, printed in wp_head:
 *   itemReviewed  { "@id": PAPOUNAN_RATING_SCHEMA_ITEM_ID }   SEOPress PRO's BedAndBreakfast node
 *   author        Organization: post title, `profile_url` when set
 *   ratingValue   `rating`, bestRating `rating_scale`, ratingCount `review_count`
 * Values are the raw post meta, unchanged: each platform keeps its own scale
 * (Booking.com /10, Google /5), no conversion, no average, no rounding. A
 * platform with a missing or invalid value is skipped, never output in part.
 * The CPT has no separate review count, so there is no reviewCount.
 *
 * Separate nodes that point to SEOPress's node by @id: nothing is added to or
 * duplicated from SEOPress's own schema.
 *
 * Where: front page only (every language, TranslatePress serves the same page).
 * The badges (component « Badge note plateforme ») are also on the Témoignages
 * and Chambres pages and every room page, but they come from CrocoBuilder query
 * loops, some with CrocoBuilder's query cache on, and there is no verified way
 * to know server-side that one was rendered. Témoignages and Chambres also have
 * no #lodging node to point to.
 *
 * @package PapounanSite
 */

defined( 'ABSPATH' ) || exit;

/*
 * @id of the BedAndBreakfast node printed by SEOPress PRO (Local Business). It is
 * not read from SEOPress: no documented API exposes it. It is the same in every
 * language (/en/ prints this @id too). Update it if the node's @id changes.
 */
const PAPOUNAN_RATING_SCHEMA_ITEM_ID = 'https://chateaupapounan.fr/#lodging';

/**
 * AggregateRating nodes for the platforms switched on, in menu order.
 *
 * @return array<int, array<string, mixed>>
 */
function papounan_rating_schema_nodes(): array {
	$posts = get_posts(
		array(
			'post_type'      => 'review_platform',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'meta_key'       => 'show_in_schema', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- a handful of posts.
			'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value -- a handful of posts.
		)
	);

	$nodes = array();
	foreach ( $posts as $post ) {
		$node = papounan_rating_schema_node( $post );
		if ( null !== $node ) {
			$nodes[] = $node;
		}
	}
	return $nodes;
}

/**
 * One AggregateRating node, or null if a required value is missing or invalid.
 *
 * @param WP_Post $post Platform entry.
 * @return array<string, mixed>|null
 */
function papounan_rating_schema_node( WP_Post $post ): ?array {
	$name  = trim( wp_strip_all_tags( $post->post_title ) );
	$value = trim( (string) get_post_meta( $post->ID, 'rating', true ) );
	$scale = trim( (string) get_post_meta( $post->ID, 'rating_scale', true ) );
	$count = trim( (string) get_post_meta( $post->ID, 'review_count', true ) );
	$url   = trim( (string) get_post_meta( $post->ID, 'profile_url', true ) );

	if ( '' === $name
		|| ! is_numeric( $value ) || ! is_numeric( $scale )
		|| (float) $value <= 0 || (float) $value > (float) $scale
		|| ! ctype_digit( $count ) || 0 === (int) $count ) {
		return null;
	}

	$author = array(
		'@type' => 'Organization',
		'name'  => $name,
	);
	$url    = esc_url_raw( $url, array( 'http', 'https' ) );
	if ( '' !== $url ) {
		$author['url'] = $url;
	}

	// Strings, so the stored value is printed as is (no float formatting).
	return array(
		'@type'        => 'AggregateRating',
		'itemReviewed' => array( '@id' => PAPOUNAN_RATING_SCHEMA_ITEM_ID ),
		'author'       => $author,
		'ratingValue'  => $value,
		'bestRating'   => $scale,
		'ratingCount'  => $count,
	);
}

/**
 * Print the nodes on the front page; nothing when no platform is switched on.
 */
function papounan_rating_schema_print(): void {
	if ( ! is_front_page() ) {
		return;
	}
	$nodes = papounan_rating_schema_nodes();
	if ( ! $nodes ) {
		return;
	}
	$json = wp_json_encode(
		array(
			'@context' => 'https://schema.org',
			'@graph'   => $nodes,
		),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
	);
	if ( false === $json ) {
		return;
	}
	// JSON_HEX_TAG keeps "</script>" out of the JSON; the values are escaped as JSON, not HTML.
	echo '<script type="application/ld+json" id="papounan-rating-schema">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action( 'wp_head', 'papounan_rating_schema_print' );
