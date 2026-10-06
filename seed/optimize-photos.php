<?php
/**
 * Seed: cap photo originals at a sane size and re-queue their derivatives.
 *
 * Openverse returns whatever the source repository hosts, which for Wikimedia
 * and museum scans means 5-13 MB files. The theme only ever renders a photo at
 * most 2000px wide, so originals larger than that are resampled down. This
 * keeps the static export small without changing what the site displays.
 *
 * Run with:
 *   docker compose run --rm wpcli wp eval-file /seed/optimize-photos.php --path=/var/www/html --allow-root
 *
 * Safe to re-run: it only rewrites files wider than the target.
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

const LM_MAX_WIDTH = 2000;
const LM_QUALITY   = 82;

require_once ABSPATH . 'wp-admin/includes/image.php';

$upload  = wp_upload_dir();
$basedir = $upload['basedir'];
$photodir = trailingslashit( $basedir ) . 'photos';

if ( ! is_dir( $photodir ) ) {
	echo "no photos directory yet\n";
	return;
}

/**
 * Largest edge length of an image file, without loading it into memory.
 *
 * @param string $path Absolute path.
 * @return array{0:int,1:int}
 */
function lm_image_size( $path ) {
	$size = getimagesize( $path );

	return $size ? array( (int) $size[0], (int) $size[1] ) : array( 0, 0 );
}

/**
 * Resample a JPEG down to LM_MAX_WIDTH wide.
 *
 * @param string $path Absolute path.
 * @param int    $max_width Cap width in pixels.
 * @param int    $quality   JPEG quality, 1-100.
 * @return bool True when the file was rewritten.
 */
function lm_downscale_jpeg( $path, $max_width, $quality ) {
	$image = wp_get_image_editor( $path );

	if ( is_wp_error( $image ) ) {
		return false;
	}

	$size = $image->get_size();

	if ( ! $size || $size['width'] <= $max_width ) {
		return false;
	}

	$image->resize( $max_width, null, false );
	$saved = $image->save( $path, 'image/jpeg', array( 'quality' => $quality ) );

	return ! is_wp_error( $saved );
}

$files = glob( trailingslashit( $photodir ) . '*.jpg' );

if ( ! $files ) {
	echo "no photos to optimize\n";
	return;
}

// Only bare originals; -scaled, -rotated, and -1024x768 derivatives are skipped.
$originals = array_values(
	array_filter(
		$files,
		function ( $file ) {
			$name = basename( $file );

			return ! preg_match( '/-(scaled|rotated|\d+x\d+)\.jpg$/', $name );
		}
	)
);

$shrank = 0;
$skipped = 0;
$saved   = 0;

foreach ( $originals as $file ) {
	list( $width, $height ) = lm_image_size( $file );

	if ( ! $width ) {
		echo "unreadable: " . basename( $file ) . "\n";
		++$skipped;
		continue;
	}

	$before = filesize( $file );

	if ( $width <= LM_MAX_WIDTH ) {
		++$skipped;
		continue;
	}

	if ( ! lm_downscale_jpeg( $file, LM_MAX_WIDTH, LM_QUALITY ) ) {
		echo "failed: " . basename( $file ) . "\n";
		++$skipped;
		continue;
	}

	$after = filesize( $file );
	$name  = basename( $file );

	echo sprintf(
		"shrunk: %-46s %5dpx -> %5dpx  %5.1fMB -> %4.1fMB\n",
		$name,
		$width,
		LM_MAX_WIDTH,
		$before / 1048576,
		$after / 1048576
	);
	++$shrank;

	// Derivatives were cut from the old, larger original and are now larger than
	// anything the theme serves, so remove the stale files by hand. The resized
	// original itself must survive, so the attachment row is updated in place
	// rather than deleted and recreated.
	$relative = 'photos/' . $name;
	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_wp_attached_file',
			'meta_value'     => $relative,
		)
	);

	if ( $existing ) {
		$attachment_id = (int) $existing[0];
		$old_meta      = wp_get_attachment_metadata( $attachment_id );

		if ( is_array( $old_meta ) && ! empty( $old_meta['sizes'] ) ) {
			foreach ( $old_meta['sizes'] as $size ) {
				if ( empty( $size['file'] ) ) {
					continue;
				}

				$stale = trailingslashit( $basedir ) . 'photos/' . $size['file'];

				if ( file_exists( $stale ) && $stale !== $file ) {
					wp_delete_file( $stale );
				}
			}
		}

		// Regenerating metadata leaves _wp_attachment_image_alt and the credit
		// meta alone; only the size list needs replacing.
		wp_update_attachment_metadata(
			$attachment_id,
			wp_generate_attachment_metadata( $attachment_id, $file )
		);

		++$saved;
		continue;
	}

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => pathinfo( $name, PATHINFO_FILENAME ),
			'post_status'    => 'inherit',
		),
		$file
	);

	if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
		echo "  could not re-register {$name}\n";
		continue;
	}

	update_post_meta( $attachment_id, '_wp_attached_file', $relative );
	wp_update_attachment_metadata(
		$attachment_id,
		wp_generate_attachment_metadata( $attachment_id, $file )
	);
	++$saved;
}

echo "\noptimize done: {$shrank} resized, {$saved} re-registered, {$skipped} left alone\n";