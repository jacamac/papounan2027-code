<?php
/**
 * Room gallery: [room_gallery] shortcode.
 *
 * Renders the ACF repeater `pictures` of a room (sub-fields `picture` image,
 * `col_span`, `row_span`) as a 12-column CSS grid, with a native <dialog>
 * lightbox. Ported from the hello-elementor-child theme (papounan-widgets repo):
 * no Elementor dependency, design-system tokens, assets loaded from the plugin.
 *
 * Usage:
 *   [room_gallery]                       Current room (Theme Builder single template)
 *   [room_gallery id="1035"]             Specific room
 *   [room_gallery image_size="large"]    Image size shown in the grid (default: large)
 *   [room_gallery lightbox="false"]      Disable the lightbox
 *
 * @package PapounanSite
 */

defined( 'ABSPATH' ) || exit;

const PAPOUNAN_GALLERY_COLUMNS = 12;

/**
 * Register assets and the shortcode.
 */
function papounan_gallery_init(): void {
	$base = PAPOUNAN_SITE_URL . 'modules/room-gallery/';

	wp_register_style( 'papounan-room-gallery', $base . 'room-gallery.css', array(), PAPOUNAN_SITE_VERSION );
	wp_register_script(
		'papounan-room-gallery',
		$base . 'room-gallery.js',
		array(),
		PAPOUNAN_SITE_VERSION,
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);

	add_shortcode( 'room_gallery', 'papounan_gallery_shortcode' );
}
add_action( 'init', 'papounan_gallery_init' );

/**
 * Enqueue the stylesheet in <head> on room pages, so the gallery is styled
 * from the first paint (the shortcode also enqueues it, as a fallback).
 */
function papounan_gallery_enqueue_on_rooms(): void {
	if ( is_singular( 'chambre' ) ) {
		wp_enqueue_style( 'papounan-room-gallery' );
	}
}
add_action( 'wp_enqueue_scripts', 'papounan_gallery_enqueue_on_rooms' );

/**
 * Shortcode callback.
 *
 * @param array<string, string>|string $atts Shortcode attributes.
 * @return string Gallery HTML, or an admin-only notice.
 */
function papounan_gallery_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'id'         => 0,
			'field'      => 'pictures',
			'image_size' => 'large',
			'full_size'  => 'full',
			'lightbox'   => 'true',
		),
		$atts,
		'room_gallery'
	);

	$post_id = absint( $atts['id'] );
	if ( ! $post_id ) {
		// Inside CrocoBuilder templates get_the_ID() is not always the room: prefer the queried object.
		$post_id = is_singular() ? get_queried_object_id() : get_the_ID();
	}
	if ( ! $post_id ) {
		return papounan_gallery_notice( __( 'No room found. Use [room_gallery id="123"] outside a room page.', 'papounan-site' ) );
	}

	if ( ! function_exists( 'get_field' ) ) {
		return papounan_gallery_notice( __( 'Advanced Custom Fields is required.', 'papounan-site' ) );
	}

	$rows = get_field( sanitize_key( $atts['field'] ), $post_id );
	if ( empty( $rows ) || ! is_array( $rows ) ) {
		return papounan_gallery_notice( __( 'This room has no pictures.', 'papounan-site' ) );
	}

	$lightbox = filter_var( $atts['lightbox'], FILTER_VALIDATE_BOOLEAN );

	wp_enqueue_style( 'papounan-room-gallery' );
	if ( $lightbox ) {
		wp_enqueue_script( 'papounan-room-gallery' );
	}

	return papounan_gallery_render( $rows, $post_id, $atts, $lightbox );
}

/**
 * Build the gallery markup.
 *
 * @param array<int, array<string, mixed>> $rows     ACF repeater rows.
 * @param int                              $post_id  Room ID.
 * @param array<string, string>            $atts     Sanitised shortcode attributes.
 * @param bool                             $lightbox Whether to add lightbox links and the dialog.
 * @return string HTML.
 */
function papounan_gallery_render( array $rows, $post_id, array $atts, $lightbox ) {
	$image_size = sanitize_key( $atts['image_size'] );
	$full_size  = sanitize_key( $atts['full_size'] );
	$title      = get_the_title( $post_id );
	$gallery_id = 'room-gallery-' . $post_id;
	$items      = '';
	$index      = 0;

	foreach ( $rows as $row ) {
		$image    = $row['picture'] ?? null;
		$image_id = is_array( $image ) ? absint( $image['ID'] ?? $image['id'] ?? 0 ) : absint( $image );
		if ( ! $image_id ) {
			continue;
		}

		$col_span = absint( $row['col_span'] ?? 0 );
		$col_span = $col_span > 0 ? min( $col_span, PAPOUNAN_GALLERY_COLUMNS ) : PAPOUNAN_GALLERY_COLUMNS;
		$row_span = max( 1, min( absint( $row['row_span'] ?? 1 ), 4 ) );

		$alt = trim( (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) );
		if ( '' === $alt ) {
			/* translators: 1: Room name, 2: Photo number. */
			$alt = sprintf( __( '%1$s – photo %2$d', 'papounan-site' ), $title, $index + 1 );
		}

		$img = wp_get_attachment_image(
			$image_id,
			$image_size,
			false,
			array(
				'class'    => 'room-gallery__image',
				'alt'      => $alt,
				'sizes'    => papounan_gallery_sizes( $col_span ),
				'loading'  => $index < 2 ? 'eager' : 'lazy',
				'decoding' => 'async',
			)
		);
		if ( ! $img ) {
			continue;
		}

		if ( $lightbox ) {
			$full = wp_get_attachment_image_url( $image_id, $full_size );
			$img  = sprintf(
				'<a class="room-gallery__link" href="%1$s" data-gallery-index="%2$d" aria-label="%3$s">%4$s</a>',
				esc_url( $full ),
				$index,
				/* translators: %s: Image description. */
				esc_attr( sprintf( __( 'Enlarge: %s', 'papounan-site' ), $alt ) ),
				$img
			);
		}

		$items .= sprintf(
			'<figure class="room-gallery__item" style="--col-span:%1$d;--row-span:%2$d">%3$s</figure>',
			$col_span,
			$row_span,
			$img
		);
		++$index;
	}

	if ( '' === $items ) {
		return papounan_gallery_notice( __( 'This room has no valid pictures.', 'papounan-site' ) );
	}

	/* translators: %s: Room name. */
	$label = sprintf( __( 'Photos – %s', 'papounan-site' ), $title );

	$html = sprintf(
		'<div id="%1$s" class="room-gallery" role="group" aria-label="%2$s">%3$s</div>',
		esc_attr( $gallery_id ),
		esc_attr( $label ),
		$items
	);

	if ( $lightbox ) {
		$html .= papounan_gallery_dialog( $gallery_id );
	}

	return $html;
}

/**
 * Lightbox dialog markup (one per gallery, filled by room-gallery.js).
 *
 * @param string $gallery_id Gallery element ID.
 * @return string HTML.
 */
function papounan_gallery_dialog( $gallery_id ) {
	return sprintf(
		'<dialog class="room-gallery__dialog" data-gallery="%1$s" aria-label="%2$s">
			<button type="button" class="room-gallery__close" data-action="close" aria-label="%3$s">&times;</button>
			<button type="button" class="room-gallery__nav room-gallery__nav--prev" data-action="prev" aria-label="%4$s">&#8249;</button>
			<figure class="room-gallery__stage">
				<img class="room-gallery__full" src="" alt="">
				<figcaption class="room-gallery__counter" aria-live="polite"></figcaption>
			</figure>
			<button type="button" class="room-gallery__nav room-gallery__nav--next" data-action="next" aria-label="%5$s">&#8250;</button>
		</dialog>',
		esc_attr( $gallery_id ),
		esc_attr__( 'Photo viewer', 'papounan-site' ),
		esc_attr__( 'Close', 'papounan-site' ),
		esc_attr__( 'Previous photo', 'papounan-site' ),
		esc_attr__( 'Next photo', 'papounan-site' )
	);
}

/**
 * Responsive `sizes` attribute for a tile spanning $col_span of 12 columns
 * inside the 920 px container. Mobile shows one photo per row.
 *
 * @param int $col_span Columns spanned.
 * @return string
 */
function papounan_gallery_sizes( $col_span ) {
	$share = $col_span / PAPOUNAN_GALLERY_COLUMNS;
	$px    = (int) ceil( 920 * $share );
	$vw    = (int) ceil( 100 * $share );
	return sprintf( '(max-width: 767px) 100vw, (max-width: 960px) %1$dvw, %2$dpx', $vw, $px );
}

/**
 * Notice shown to editors only; visitors get nothing.
 *
 * @param string $message Message.
 * @return string
 */
function papounan_gallery_notice( $message ) {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return '';
	}
	return '<p class="room-gallery-notice">' . esc_html( $message ) . '</p>';
}
