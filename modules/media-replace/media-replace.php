<?php
/**
 * Media replace: replace an image attachment's file in place, keeping its id,
 * its file name and its URL.
 *
 *   POST /wp-json/papounan/v1/media/<id>/replace   multipart `file`, `dry_run` (default true), `optimize` (default true)
 *   POST /wp-json/papounan/v1/media/<id>/revert    `dry_run` (default true), `optimize` (default false)
 *
 * Who: a user with upload_files who can edit the attachment (application
 * password). Only JPEG, PNG and WebP, at most PAPOUNAN_MEDIA_REPLACE_MAX_BYTES,
 * and only of the same type as the current file: the file is never renamed, so
 * _wp_attached_file and the URL never change. alt, title, caption and
 * description are not touched.
 *
 * Replace:
 *   1. Same bytes as the current file (sha256) → nothing is done.
 *   2. Backup of the main file, the unscaled original (`original_image`) and
 *      every sub-size, with the metadata, in
 *      uploads/papounan-replaced/<id>/<Ymd-His>/ (manifest.json).
 *   3. `wp_handle_replace` (the Enable Media Replace action): ShortPixel drops
 *      its data for the image (its own backup, .webp/.avif copies, queue entry)
 *      and deletes the image files, which are in our backup.
 *   4. The new bytes are written to the main path (and to the unscaled original,
 *      if the attachment has one), sub-sizes are made with the image editor
 *      (same names as core: <original>-<w>x<h>.<ext>), the metadata is updated
 *      and the old sub-sizes that no longer exist are deleted. If a step fails,
 *      the backup is put back.
 *   5. ShortPixel: the `shortpixel/optimize-media` ability enqueues the image
 *      (not for PNG: ShortPixel's PNG → JPG conversion would rename the file).
 *
 * Sub-sizes are not made with wp_generate_attachment_metadata() or
 * wp_update_image_subsizes(): both apply the `wp_generate_attachment_metadata`
 * filter, where ShortPixel converts PNG to JPG and runs its AI SEO (alt text,
 * file name). The big-image threshold and EXIF rotation do not apply either:
 * both would save a -scaled / -rotated copy and change the URL.
 *
 * Revert: puts back the latest replace backup not yet reverted (a stack: each
 * revert undoes one replace), after a backup of the current state
 * ("pre-revert"). Nothing is deleted from uploads/papounan-replaced/.
 *
 * @package PapounanSite
 */

defined( 'ABSPATH' ) || exit;

/** Largest accepted upload, in bytes (20 MB). PHP's upload_max_filesize may be lower. */
const PAPOUNAN_MEDIA_REPLACE_MAX_BYTES = 20971520;

/** Accepted types. SVG, GIF and AVIF are refused. */
const PAPOUNAN_MEDIA_REPLACE_MIMES = array( 'image/jpeg', 'image/png', 'image/webp' );

/** Backup folder, in the uploads folder. */
const PAPOUNAN_MEDIA_REPLACE_DIR = 'papounan-replaced';

/**
 * Register the routes.
 */
function papounan_media_replace_routes(): void {
	register_rest_route(
		'papounan/v1',
		'/media/(?P<id>\d+)/replace',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'papounan_media_replace_handle_replace',
			'permission_callback' => 'papounan_media_replace_permission',
			'args'                => array(
				'dry_run'  => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'optimize' => array(
					'type'    => 'boolean',
					'default' => true,
				),
			),
		)
	);
	register_rest_route(
		'papounan/v1',
		'/media/(?P<id>\d+)/revert',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'papounan_media_replace_handle_revert',
			'permission_callback' => 'papounan_media_replace_permission',
			'args'                => array(
				'dry_run'  => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'optimize' => array(
					'type'    => 'boolean',
					'default' => false,
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'papounan_media_replace_routes' );

/**
 * Upload rights, then an existing image attachment the user can edit.
 *
 * @param WP_REST_Request $request Request.
 * @return true|WP_Error
 */
function papounan_media_replace_permission( WP_REST_Request $request ) {
	if ( ! current_user_can( 'upload_files' ) ) {
		return new WP_Error( 'rest_forbidden', 'Sorry, you are not allowed to replace media.', array( 'status' => rest_authorization_required_code() ) );
	}
	$id = (int) $request['id'];
	if ( 'attachment' !== get_post_type( $id ) || ! wp_attachment_is_image( $id ) ) {
		return new WP_Error( 'rest_post_invalid_id', 'No image attachment with this id.', array( 'status' => 404 ) );
	}
	if ( ! current_user_can( 'edit_post', $id ) ) {
		return new WP_Error( 'rest_forbidden', 'Sorry, you are not allowed to edit this attachment.', array( 'status' => rest_authorization_required_code() ) );
	}
	return true;
}

/**
 * POST …/replace.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function papounan_media_replace_handle_replace( WP_REST_Request $request ) {
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$id      = (int) $request['id'];
	$dry_run = (bool) $request['dry_run'];

	$current = papounan_media_replace_current( $id );
	if ( is_wp_error( $current ) ) {
		return $current;
	}
	$upload = papounan_media_replace_upload( $request );
	if ( is_wp_error( $upload ) ) {
		return $upload;
	}
	if ( $upload['mime'] !== $current['mime'] ) {
		return new WP_Error(
			'papounan_media_replace_type_mismatch',
			sprintf( 'The new file is %1$s, the current file is %2$s. A replace never changes the type (it would change the file name and the URL).', $upload['mime'], $current['mime'] ),
			array( 'status' => 409 )
		);
	}

	$old      = papounan_media_replace_state( $current );
	$response = array(
		'id'             => $id,
		'dry_run'        => $dry_run,
		'identical'      => $old['sha256'] === $upload['sha256'],
		'url'            => wp_get_attachment_url( $id ),
		'file'           => $current['attached_file'],
		'mime'           => $current['mime'],
		'old'            => $old,
		'new'            => null,
		'original_image' => '' !== $current['original'] ? $current['original'] : null,
		'sub_sizes'      => array(
			'regenerated' => array(),
			'removed'     => array(),
		),
		'backup'         => null,
		'shortpixel'     => null,
		'cache_hint'     => null,
		'warnings'       => array(),
	);
	if ( $response['identical'] ) {
		return rest_ensure_response( $response );
	}

	if ( $dry_run ) {
		$editor = papounan_media_replace_editor( $upload['tmp'], $upload['mime'] );
		if ( is_wp_error( $editor ) ) {
			return $editor;
		}
		$size      = $editor->get_size();
		$predicted = papounan_media_replace_predict( $id, $current, $size );

		$response['new']                      = array(
			'width'    => (int) $size['width'],
			'height'   => (int) $size['height'],
			'filesize' => $upload['size'],
			'sha256'   => $upload['sha256'],
		);
		$response['sub_sizes']['regenerated'] = $predicted;
		$response['sub_sizes']['removed']     = papounan_media_replace_stale( $current, $predicted );
		$response['cache_hint']               = papounan_media_replace_cache_hint( $id, $current, $predicted );
		$response['warnings']                 = papounan_media_replace_warnings( $old, $response['new'], $response['sub_sizes']['removed'] );
		return rest_ensure_response( $response );
	}

	if ( ! papounan_media_replace_lock( $id ) ) {
		return new WP_Error( 'papounan_media_replace_locked', 'Another replace or revert of this attachment is running.', array( 'status' => 409 ) );
	}
	try {
		$backup = papounan_media_replace_backup( $id, $current, 'replace', $upload['sha256'] );
		if ( is_wp_error( $backup ) ) {
			return $backup;
		}

		papounan_media_replace_signal_replace( $id );

		$made = papounan_media_replace_apply( $id, $current, $upload['tmp'] );
		if ( is_wp_error( $made ) ) {
			$restored = papounan_media_replace_restore( $id, $current, $backup );
			papounan_media_replace_log( 'replace', $id, $made->get_error_code() );
			$made->add_data(
				array(
					'status'   => 500,
					'restored' => ! is_wp_error( $restored ),
					'backup'   => papounan_media_replace_relative( $backup['dir'] ),
				)
			);
			return $made;
		}
		$response['sub_sizes']['removed'] = papounan_media_replace_delete_stale( $current, $made['files'] );
	} finally {
		papounan_media_replace_unlock( $id );
	}

	$response['new']                      = array(
		'width'    => $made['width'],
		'height'   => $made['height'],
		'filesize' => $upload['size'],
		'sha256'   => $upload['sha256'],
	);
	$response['sub_sizes']['regenerated'] = $made['files'];
	$response['backup']                   = papounan_media_replace_relative( $backup['dir'] );
	$response['shortpixel']               = $request['optimize']
		? papounan_media_replace_shortpixel( $id, $current['mime'] )
		: array(
			'status'  => 'skipped',
			'message' => 'optimize=false',
		);
	$response['cache_hint']               = papounan_media_replace_cache_hint( $id, $current, $made['files'] );
	$response['warnings']                 = papounan_media_replace_warnings( $old, $response['new'], $response['sub_sizes']['removed'] );
	return rest_ensure_response( $response );
}

/**
 * POST …/revert.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function papounan_media_replace_handle_revert( WP_REST_Request $request ) {
	$id      = (int) $request['id'];
	$dry_run = (bool) $request['dry_run'];

	$current = papounan_media_replace_current( $id );
	if ( is_wp_error( $current ) ) {
		return $current;
	}
	$backup = papounan_media_replace_latest_backup( $id );
	if ( is_wp_error( $backup ) ) {
		return $backup;
	}
	$manifest = $backup['manifest'];
	if ( $manifest['attached_file'] !== $current['attached_file'] ) {
		return new WP_Error( 'papounan_media_replace_moved', 'The attachment file changed since the backup; revert by hand.', array( 'status' => 409 ) );
	}
	foreach ( $manifest['files'] as $file => $sha256 ) {
		$path = $backup['dir'] . '/' . $file;
		if ( ! is_file( $path ) || hash_file( 'sha256', $path ) !== $sha256 ) {
			return new WP_Error( 'papounan_media_replace_backup_corrupt', sprintf( 'Backup file %s is missing or changed.', $file ), array( 'status' => 500 ) );
		}
	}

	$restored_files = array_keys( $manifest['files'] );
	$old            = papounan_media_replace_state( $current );
	$response       = array(
		'id'         => $id,
		'dry_run'    => $dry_run,
		'url'        => wp_get_attachment_url( $id ),
		'file'       => $current['attached_file'],
		'backup'     => papounan_media_replace_relative( $backup['dir'] ),
		'backup_of'  => $manifest['created'],
		'old'        => $old,
		'new'        => array(
			'width'    => (int) ( $manifest['metadata']['width'] ?? 0 ),
			'height'   => (int) ( $manifest['metadata']['height'] ?? 0 ),
			'filesize' => (int) filesize( $backup['dir'] . '/' . basename( $current['path'] ) ),
			'sha256'   => $manifest['files'][ basename( $current['path'] ) ] ?? '',
		),
		'restored'   => $restored_files,
		'removed'    => papounan_media_replace_stale( $current, $restored_files ),
		'pre_revert' => null,
		'shortpixel' => null,
		'cache_hint' => papounan_media_replace_cache_hint( $id, $current, $restored_files ),
		'warnings'   => array(),
	);
	if ( $old['sha256'] !== $manifest['replaced_by'] ) {
		// ShortPixel's optimization changes the bytes too, so this is information, not an error.
		$response['warnings'][] = 'The current file is not the one this backup was replaced by (optimized since, or replaced again by other means).';
	}
	if ( $dry_run ) {
		return rest_ensure_response( $response );
	}

	if ( ! papounan_media_replace_lock( $id ) ) {
		return new WP_Error( 'papounan_media_replace_locked', 'Another replace or revert of this attachment is running.', array( 'status' => 409 ) );
	}
	try {
		$pre = papounan_media_replace_backup( $id, $current, 'pre-revert', '' );
		if ( is_wp_error( $pre ) ) {
			return $pre;
		}

		papounan_media_replace_signal_replace( $id );

		$restored = papounan_media_replace_restore( $id, $current, $backup );
		if ( is_wp_error( $restored ) ) {
			// Put back what was there before the revert.
			papounan_media_replace_restore( $id, $current, $pre );
			papounan_media_replace_log( 'revert', $id, $restored->get_error_code() );
			$restored->add_data( array( 'status' => 500 ) );
			return $restored;
		}
		$response['removed'] = papounan_media_replace_delete_stale( $current, $restored_files );

		$manifest['reverted'] = gmdate( 'c' );
		papounan_media_replace_write_manifest( $backup['dir'], $manifest );
	} finally {
		papounan_media_replace_unlock( $id );
	}

	$response['pre_revert'] = papounan_media_replace_relative( $pre['dir'] );
	$response['shortpixel'] = $request['optimize']
		? papounan_media_replace_shortpixel( $id, $current['mime'] )
		: array(
			'status'  => 'skipped',
			'message' => 'optimize=false',
		);
	return rest_ensure_response( $response );
}

/**
 * The attachment's current file and metadata.
 *
 * @param int $id Attachment id.
 * @return array<string, mixed>|WP_Error {path, dir, attached_file, mime, meta, original, sub_sizes}
 */
function papounan_media_replace_current( int $id ) {
	$path = get_attached_file( $id, true );
	if ( ! $path || ! is_file( $path ) ) {
		return new WP_Error( 'papounan_media_replace_missing_file', 'The attachment file is missing on disk.', array( 'status' => 409 ) );
	}

	// Only files inside the uploads folder (not offloaded or external files).
	$uploads = wp_get_upload_dir();
	$real    = realpath( $path );
	$base    = realpath( $uploads['basedir'] );
	if ( false === $real || false === $base || 0 !== strpos( $real, trailingslashit( $base ) ) ) {
		return new WP_Error( 'papounan_media_replace_outside_uploads', 'The attachment file is not in the uploads folder.', array( 'status' => 409 ) );
	}

	// As stored (unfiltered): this is what the backup keeps and a revert writes back.
	$meta = get_post_meta( $id, '_wp_attachment_metadata', true );
	$meta = is_array( $meta ) ? $meta : array();
	$dir  = dirname( $real );

	// Sub-size files (several sizes can share one file).
	$sub_sizes = array();
	foreach ( (array) ( $meta['sizes'] ?? array() ) as $size ) {
		$file = is_array( $size ) ? (string) ( $size['file'] ?? '' ) : '';
		if ( '' !== $file && basename( $file ) === $file ) {
			$sub_sizes[] = $file;
		}
	}

	// Unscaled original of a -scaled attachment, when it exists. It is replaced too,
	// so it must have the same type (core keeps it so; a converted HEIC would not).
	$original = (string) ( $meta['original_image'] ?? '' );
	$type     = wp_check_filetype( $real );
	if ( '' !== $original && ( basename( $original ) !== $original || ! is_file( $dir . '/' . $original ) ) ) {
		$original = '';
	}
	if ( '' !== $original && wp_check_filetype( $original )['type'] !== $type['type'] ) {
		return new WP_Error( 'papounan_media_replace_original_type', 'The unscaled original has another type than the main file; replace it by hand.', array( 'status' => 409 ) );
	}

	return array(
		'path'          => $real,
		'dir'           => $dir,
		'attached_file' => (string) get_post_meta( $id, '_wp_attached_file', true ),
		'mime'          => (string) $type['type'],
		'meta'          => $meta,
		'original'      => $original,
		'sub_sizes'     => array_values( array_unique( $sub_sizes ) ),
	);
}

/**
 * Size, dimensions and hash of the current main file.
 *
 * @param array<string, mixed> $current From papounan_media_replace_current().
 * @return array{width: int, height: int, filesize: int, sha256: string}
 */
function papounan_media_replace_state( array $current ): array {
	return array(
		'width'    => (int) ( $current['meta']['width'] ?? 0 ),
		'height'   => (int) ( $current['meta']['height'] ?? 0 ),
		'filesize' => (int) filesize( $current['path'] ),
		'sha256'   => (string) hash_file( 'sha256', $current['path'] ),
	);
}

/**
 * The uploaded file, validated.
 *
 * @param WP_REST_Request $request Request.
 * @return array{tmp: string, mime: string, size: int, sha256: string}|WP_Error
 */
function papounan_media_replace_upload( WP_REST_Request $request ) {
	$files = $request->get_file_params();
	$file  = $files['file'] ?? null;
	if ( ! is_array( $file ) || ! isset( $file['tmp_name'], $file['name'], $file['error'] ) ) {
		return new WP_Error( 'papounan_media_replace_no_file', 'Send the new image as the multipart field "file".', array( 'status' => 400 ) );
	}
	if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
		return new WP_Error( 'papounan_media_replace_upload_error', sprintf( 'Upload failed (PHP upload error %d).', (int) $file['error'] ), array( 'status' => 400 ) );
	}
	$tmp = (string) $file['tmp_name'];
	if ( ! is_uploaded_file( $tmp ) ) {
		return new WP_Error( 'papounan_media_replace_no_file', 'Not an uploaded file.', array( 'status' => 400 ) );
	}

	$size = (int) filesize( $tmp );
	if ( $size <= 0 || $size > PAPOUNAN_MEDIA_REPLACE_MAX_BYTES ) {
		return new WP_Error( 'papounan_media_replace_too_large', sprintf( 'The file must be between 1 byte and %d bytes.', PAPOUNAN_MEDIA_REPLACE_MAX_BYTES ), array( 'status' => 413 ) );
	}

	// Real type from the content (wp_check_filetype_and_ext() reads image files), not the name.
	$check = wp_check_filetype_and_ext( $tmp, sanitize_file_name( (string) $file['name'] ) );
	$mime  = (string) $check['type'];
	if ( ! in_array( $mime, PAPOUNAN_MEDIA_REPLACE_MIMES, true ) ) {
		return new WP_Error( 'papounan_media_replace_bad_type', 'Only JPEG, PNG and WebP images are accepted.', array( 'status' => 415 ) );
	}
	$info = wp_getimagesize( $tmp );
	if ( ! is_array( $info ) || ( $info['mime'] ?? '' ) !== $mime || empty( $info[0] ) || empty( $info[1] ) ) {
		return new WP_Error( 'papounan_media_replace_not_image', 'The file is not a readable image.', array( 'status' => 415 ) );
	}

	return array(
		'tmp'    => $tmp,
		'mime'   => $mime,
		'size'   => $size,
		'sha256' => (string) hash_file( 'sha256', $tmp ),
	);
}

/**
 * Image editor on a file, with the EXIF orientation applied in memory, so the
 * sub-sizes are upright. The file itself is not rotated (that would save a
 * -rotated copy and change the URL); browsers apply the orientation.
 *
 * @param string $path File.
 * @param string $mime Its type.
 * @return WP_Image_Editor|WP_Error
 */
function papounan_media_replace_editor( string $path, string $mime ) {
	wp_raise_memory_limit( 'image' );
	$editor = wp_get_image_editor( $path, array( 'mime_type' => $mime ) );
	if ( is_wp_error( $editor ) ) {
		return $editor;
	}
	$rotated = $editor->maybe_exif_rotate();
	if ( is_wp_error( $rotated ) ) {
		return $rotated;
	}
	return $editor;
}

/**
 * Registered sub-sizes, through the same filter core applies before making them.
 *
 * @param int                  $id   Attachment id.
 * @param array<string, mixed> $meta Attachment metadata.
 * @return array<string, array<string, mixed>>
 */
function papounan_media_replace_sizes( int $id, array $meta ): array {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter, applied as core does.
	return (array) apply_filters( 'intermediate_image_sizes_advanced', wp_get_registered_image_subsizes(), $meta, $id );
}

/**
 * Sub-size file names a replace would make, for a dry run (core's naming:
 * <source name>-<w>x<h>.<ext>). A filter that changes the output format
 * (image_editor_output_format) is not taken into account.
 *
 * @param int                  $id      Attachment id.
 * @param array<string, mixed> $current From papounan_media_replace_current().
 * @param array<string, int>   $size    New image width and height.
 * @return array<string, string> Size name => file.
 */
function papounan_media_replace_predict( int $id, array $current, array $size ): array {
	$source = '' !== $current['original'] ? $current['original'] : basename( $current['path'] );
	$name   = pathinfo( $source, PATHINFO_FILENAME );
	$ext    = pathinfo( $source, PATHINFO_EXTENSION );
	$files  = array();
	foreach ( papounan_media_replace_sizes( $id, $current['meta'] ) as $size_name => $sub ) {
		$dims = image_resize_dimensions( (int) $size['width'], (int) $size['height'], (int) ( $sub['width'] ?? 0 ), (int) ( $sub['height'] ?? 0 ), $sub['crop'] ?? false );
		if ( is_array( $dims ) ) {
			$files[ (string) $size_name ] = sprintf( '%s-%dx%d.%s', $name, $dims[4], $dims[5], $ext );
		}
	}
	return $files;
}

/**
 * Write the new bytes, make the sub-sizes, save the metadata.
 *
 * @param int                  $id      Attachment id.
 * @param array<string, mixed> $current From papounan_media_replace_current().
 * @param string               $tmp     Uploaded file.
 * @return array{width: int, height: int, files: array<string, string>}|WP_Error
 */
function papounan_media_replace_apply( int $id, array $current, string $tmp ) {
	$written = papounan_media_replace_write( $tmp, $current['path'] );
	if ( is_wp_error( $written ) ) {
		return $written;
	}
	// A -scaled attachment's sub-sizes are made from its unscaled original, as core does.
	$source = $current['path'];
	if ( '' !== $current['original'] ) {
		$source  = $current['dir'] . '/' . $current['original'];
		$written = papounan_media_replace_write( $tmp, $source );
		if ( is_wp_error( $written ) ) {
			return $written;
		}
	}

	$editor = papounan_media_replace_editor( $source, $current['mime'] );
	if ( is_wp_error( $editor ) ) {
		return $editor;
	}
	$size = $editor->get_size();
	$made = $editor->multi_resize( papounan_media_replace_sizes( $id, $current['meta'] ) );

	$meta             = $current['meta'];
	$meta['width']    = (int) $size['width'];
	$meta['height']   = (int) $size['height'];
	$meta['filesize'] = wp_filesize( $current['path'] );
	$meta['sizes']    = $made;
	$image_meta       = wp_read_image_metadata( $current['path'] );
	if ( is_array( $image_meta ) ) {
		$meta['image_meta'] = $image_meta;
	}
	wp_update_attachment_metadata( $id, $meta );
	clean_attachment_cache( $id );

	$files = array();
	foreach ( $made as $size_name => $sub ) {
		$files[ (string) $size_name ] = (string) $sub['file'];
	}
	return array(
		'width'  => (int) $size['width'],
		'height' => (int) $size['height'],
		'files'  => $files,
	);
}

/**
 * Copy a file over another through a temporary file in the same folder, so the
 * target is never half-written. Permissions as core gives uploads.
 *
 * @param string $from Source.
 * @param string $to   Target.
 * @return true|WP_Error
 */
function papounan_media_replace_write( string $from, string $to ) {
	$tmp = $to . '.papounan-tmp';
	if ( ! copy( $from, $tmp ) ) {
		return new WP_Error( 'papounan_media_replace_write_failed', sprintf( 'Could not write %s.', basename( $to ) ) );
	}
	$stat = stat( dirname( $to ) );
	if ( is_array( $stat ) ) {
		chmod( $tmp, $stat['mode'] & 0000666 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- as core's _wp_handle_upload().
	}
	if ( ! rename( $tmp, $to ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- atomic replace in the same folder.
		wp_delete_file( $tmp );
		return new WP_Error( 'papounan_media_replace_write_failed', sprintf( 'Could not write %s.', basename( $to ) ) );
	}
	clearstatcache( true, $to );
	return true;
}

/**
 * Old sub-size files that are not in the new set (never the main file or the
 * unscaled original).
 *
 * @param array<string, mixed> $current From papounan_media_replace_current().
 * @param array<string|int, string> $keep Files of the new set.
 * @return string[]
 */
function papounan_media_replace_stale( array $current, array $keep ): array {
	$keep[] = basename( $current['path'] );
	$keep[] = $current['original'];
	return array_values( array_diff( $current['sub_sizes'], $keep ) );
}

/**
 * Delete the stale sub-size files (they are in the backup).
 *
 * @param array<string, mixed>      $current From papounan_media_replace_current().
 * @param array<string|int, string> $keep    Files of the new set.
 * @return string[] The stale files, now gone.
 */
function papounan_media_replace_delete_stale( array $current, array $keep ): array {
	$stale = papounan_media_replace_stale( $current, $keep );
	foreach ( $stale as $file ) {
		$path = $current['dir'] . '/' . $file;
		if ( is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}
	return $stale;
}

/**
 * Tell other plugins the attachment's file is being replaced, with the action
 * Enable Media Replace fires. ShortPixel (6.6.1, handleReplaceHook) then drops
 * its optimization data, its backup and its .webp/.avif copies for the image,
 * and deletes the image files (main, sub-sizes, unscaled original), which our
 * backup holds and the replace writes again.
 *
 * @param int $id Attachment id.
 */
function papounan_media_replace_signal_replace( int $id ): void {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Enable Media Replace's action, listened to by ShortPixel.
	do_action( 'wp_handle_replace', array( 'post_id' => $id ) );
}

/**
 * Enqueue the image in ShortPixel through its `shortpixel/optimize-media`
 * ability (Abilities API, WordPress 6.9). Enqueue only: ShortPixel processes
 * its queue later (or the caller runs `shortpixel/run-queue`). The ability
 * checks its own permission (edit_others_posts in ShortPixel 6.6.1).
 *
 * @param int    $id   Attachment id.
 * @param string $mime Image type.
 * @return array{status: string, message: string}
 */
function papounan_media_replace_shortpixel( int $id, string $mime ): array {
	if ( 'image/png' === $mime ) {
		return array(
			'status'  => 'skipped',
			'message' => 'PNG: ShortPixel can convert PNG to JPG, which would rename the file.',
		);
	}
	$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'shortpixel/optimize-media' ) : null;
	if ( null === $ability ) {
		return array(
			'status'  => 'unavailable',
			'message' => 'The shortpixel/optimize-media ability is not registered.',
		);
	}
	$result = $ability->execute(
		array(
			'id'      => $id,
			'process' => false,
		)
	);
	if ( is_wp_error( $result ) ) {
		return array(
			'status'  => 'error',
			'message' => $result->get_error_message(),
		);
	}
	if ( is_array( $result ) && ! empty( $result['error'] ) ) {
		return array(
			'status'  => 'error',
			'message' => (string) ( $result['message'] ?? '' ),
		);
	}
	return array(
		'status'  => 'queued',
		'message' => is_array( $result ) ? (string) ( $result['enqueue_message'] ?? '' ) : '',
	);
}

/**
 * Back up the attachment's files and metadata.
 *
 * @param int                  $id          Attachment id.
 * @param array<string, mixed> $current     From papounan_media_replace_current().
 * @param string               $kind        "replace" or "pre-revert".
 * @param string               $replaced_by sha256 of the new main file (replace).
 * @return array{dir: string, manifest: array<string, mixed>}|WP_Error
 */
function papounan_media_replace_backup( int $id, array $current, string $kind, string $replaced_by ) {
	$root  = papounan_media_replace_root();
	$stamp = gmdate( 'Ymd-His' );
	$dir   = $root . '/' . $id . '/' . $stamp;
	$n     = 2;
	while ( file_exists( $dir ) ) {
		$dir = $root . '/' . $id . '/' . $stamp . '-' . ( $n++ );
	}
	if ( ! wp_mkdir_p( $dir ) ) {
		return new WP_Error( 'papounan_media_replace_backup_failed', 'Could not create the backup folder.', array( 'status' => 500 ) );
	}
	if ( ! is_file( $root . '/index.php' ) ) {
		// No folder listing where the server would list it.
		file_put_contents( $root . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- uploads folder.
	}

	$files = array_merge( array( basename( $current['path'] ), $current['original'] ), $current['sub_sizes'] );
	$saved = array();
	foreach ( array_unique( array_filter( $files ) ) as $file ) {
		$from = $current['dir'] . '/' . $file;
		if ( ! is_file( $from ) ) {
			continue;
		}
		if ( ! copy( $from, $dir . '/' . $file ) ) {
			return new WP_Error( 'papounan_media_replace_backup_failed', sprintf( 'Could not back up %s.', $file ), array( 'status' => 500 ) );
		}
		$saved[ $file ] = (string) hash_file( 'sha256', $from );
	}

	$manifest = array(
		'kind'          => $kind,
		'id'            => $id,
		'created'       => gmdate( 'c' ),
		'user'          => get_current_user_id(),
		'attached_file' => $current['attached_file'],
		'files'         => $saved,
		'metadata'      => $current['meta'],
		'replaced_by'   => $replaced_by,
		'reverted'      => null,
	);
	if ( ! papounan_media_replace_write_manifest( $dir, $manifest ) ) {
		return new WP_Error( 'papounan_media_replace_backup_failed', 'Could not write the backup manifest.', array( 'status' => 500 ) );
	}
	return array(
		'dir'      => $dir,
		'manifest' => $manifest,
	);
}

/**
 * Write manifest.json in a backup folder.
 *
 * @param string               $dir      Backup folder.
 * @param array<string, mixed> $manifest Manifest.
 */
function papounan_media_replace_write_manifest( string $dir, array $manifest ): bool {
	$json = wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- uploads folder, like core's media code.
	return false !== $json && false !== file_put_contents( $dir . '/manifest.json', $json );
}

/**
 * Latest replace backup of an attachment that was not reverted yet.
 *
 * @param int $id Attachment id.
 * @return array{dir: string, manifest: array<string, mixed>}|WP_Error
 */
function papounan_media_replace_latest_backup( int $id ) {
	$manifests = glob( papounan_media_replace_root() . '/' . $id . '/*/manifest.json' );
	$manifests = is_array( $manifests ) ? $manifests : array();
	rsort( $manifests, SORT_STRING ); // Folder names are Ymd-His[-n]: newest first.
	foreach ( $manifests as $path ) {
		$manifest = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
		if ( is_array( $manifest ) && 'replace' === ( $manifest['kind'] ?? '' ) && empty( $manifest['reverted'] )
			&& is_array( $manifest['files'] ?? null ) && is_array( $manifest['metadata'] ?? null ) ) {
			return array(
				'dir'      => dirname( $path ),
				'manifest' => $manifest,
			);
		}
	}
	return new WP_Error( 'papounan_media_replace_no_backup', 'No replace of this attachment to revert.', array( 'status' => 404 ) );
}

/**
 * Put a backup's files and metadata back.
 *
 * @param int                                                $id      Attachment id.
 * @param array<string, mixed>                               $current From papounan_media_replace_current().
 * @param array{dir: string, manifest: array<string, mixed>} $backup  From papounan_media_replace_backup().
 * @return true|WP_Error
 */
function papounan_media_replace_restore( int $id, array $current, array $backup ) {
	foreach ( array_keys( $backup['manifest']['files'] ) as $file ) {
		if ( basename( $file ) !== $file ) {
			continue;
		}
		$written = papounan_media_replace_write( $backup['dir'] . '/' . $file, $current['dir'] . '/' . $file );
		if ( is_wp_error( $written ) ) {
			return $written;
		}
	}
	wp_update_attachment_metadata( $id, $backup['manifest']['metadata'] );
	clean_attachment_cache( $id );
	return true;
}

/**
 * URLs to purge from the page/CDN cache: the main file, the unscaled original
 * and every sub-size, old and new. Nothing is purged here.
 *
 * @param int                       $id      Attachment id.
 * @param array<string, mixed>      $current From papounan_media_replace_current().
 * @param array<string|int, string> $files   New sub-size files.
 * @return array{url: string, urls: string[]}
 */
function papounan_media_replace_cache_hint( int $id, array $current, array $files ): array {
	$url  = (string) wp_get_attachment_url( $id );
	$base = trailingslashit( dirname( $url ) );
	$all  = array_unique( array_filter( array_merge( array( $current['original'] ), $current['sub_sizes'], array_values( $files ) ) ) );
	$urls = array( $url );
	foreach ( $all as $file ) {
		$urls[] = $base . rawurlencode( $file );
	}
	return array(
		'url'  => $url,
		'urls' => $urls,
	);
}

/**
 * Things the caller should know.
 *
 * @param array<string, mixed> $before  Old state.
 * @param array<string, mixed> $after   New state.
 * @param string[]             $removed Stale sub-size files.
 * @return string[]
 */
function papounan_media_replace_warnings( array $before, array $after, array $removed ): array {
	$warnings = array();
	if ( $removed ) {
		$warnings[] = 'The dimensions change, so some sub-size file names change: a page that hard-codes an old sub-size URL (rather than the attachment id) will show a broken image.';
	}
	if ( $before['width'] && $after['width']
		&& abs( $before['width'] / max( 1, $before['height'] ) - $after['width'] / max( 1, $after['height'] ) ) > 0.01 ) {
		$warnings[] = 'The aspect ratio changes.';
	}
	return $warnings;
}

/**
 * One replace or revert at a time per attachment. mkdir is atomic; a lock
 * older than ten minutes is from a request that died and is taken over.
 *
 * @param int $id Attachment id.
 */
function papounan_media_replace_lock( int $id ): bool {
	$lock = papounan_media_replace_root() . '/' . $id . '/.lock';
	wp_mkdir_p( dirname( $lock ) );
	if ( @mkdir( $lock ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_mkdir -- atomic test-and-set; failure is the answer.
		return true;
	}
	$since = filemtime( $lock );
	if ( false !== $since && time() - $since > 600 ) {
		touch( $lock ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch -- refresh a stale lock.
		return true;
	}
	return false;
}

/**
 * Release the lock.
 *
 * @param int $id Attachment id.
 */
function papounan_media_replace_unlock( int $id ): void {
	rmdir( papounan_media_replace_root() . '/' . $id . '/.lock' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- our own lock folder.
}

/**
 * Backup root: uploads/papounan-replaced.
 */
function papounan_media_replace_root(): string {
	return wp_get_upload_dir()['basedir'] . '/' . PAPOUNAN_MEDIA_REPLACE_DIR;
}

/**
 * A path relative to the uploads folder, for responses.
 *
 * @param string $path Absolute path.
 */
function papounan_media_replace_relative( string $path ): string {
	return _wp_relative_upload_path( $path );
}

/**
 * Log a failure: action, attachment id and error code only.
 *
 * @param string $action "replace" or "revert".
 * @param int    $id     Attachment id.
 * @param string $code   Error code.
 */
function papounan_media_replace_log( string $action, int $id, string $code ): void {
	error_log( sprintf( 'papounan-site media-replace: %s of attachment %d failed (%s); backup restored', $action, $id, $code ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- failures only, no file contents.
}
