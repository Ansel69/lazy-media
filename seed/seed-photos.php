<?php
/**
 * Seed: replace generated placeholders with real, licensed photographs.
 *
 * Run with:
 *   docker compose run --rm wpcli wp eval-file /seed/seed-photos.php --path=/var/www/html --allow-root
 *
 * Source is the Openverse API, which needs no credentials. Only results under
 * a licence that permits commercial use are accepted, and every photo's
 * creator and licence are stored as post meta so single.php can credit them.
 *
 * Re-running is safe: a post that already has a sourced photo is skipped.
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

const LM_PHOTO_ENDPOINT = 'https://api.openverse.org/v1/images/';

/**
 * Licence identifiers that permit commercial reuse.
 *
 * by-nd is deliberately excluded: a no-derivatives image cannot be cropped to
 * the card aspect ratios the theme uses.
 */
const LM_ALLOWED_LICENSES = array( 'cc0', 'pdm', 'by', 'by-sa' );

/**
 * Human label and URL for a licence code.
 *
 * @param string $code Licence code.
 * @return array{label:string,url:string}
 */
function lm_license_info( $code ) {
	$licenses = array(
		'cc0'   => array(
			'label' => 'CC0 1.0',
			'url'   => 'https://creativecommons.org/publicdomain/zero/1.0/',
		),
		'pdm'   => array(
			'label' => 'Public Domain Mark',
			'url'   => 'https://creativecommons.org/publicdomain/mark/1.0/',
		),
		'by'    => array(
			'label' => 'CC BY',
			'url'   => 'https://creativecommons.org/licenses/by/4.0/',
		),
		'by-sa' => array(
			'label' => 'CC BY-SA',
			'url'   => 'https://creativecommons.org/licenses/by-sa/4.0/',
		),
	);

	return isset( $licenses[ $code ] ) ? $licenses[ $code ] : array(
		'label' => strtoupper( $code ),
		'url'   => 'https://creativecommons.org/licenses/',
	);
}

/**
 * Search Openverse for one photo matching a topic.
 *
 * @param string $topic   Search phrase.
 * @param string $exclude Photo ID already used, to avoid duplicates.
 * @return array|null Openverse result, or null when nothing usable is found.
 */
function lm_find_photos( $topic ) {
	// Assembled by hand rather than with add_query_arg(): the multi-word
	// search phrase must be percent-encoded or the URL breaks before the
	// request leaves the container.
	$url = LM_PHOTO_ENDPOINT
		. '?q=' . rawurlencode( $topic )
		. '&page_size=20&license_type=commercial&size=large&mature=false';

	$body = lm_http_get( $url );

	if ( ! $body ) {
		return array();
	}

	$data = json_decode( $body, true );

	if ( ! is_array( $data ) || empty( $data['results'] ) ) {
		return array();
	}

	$used = lm_used_photo_ids();
	$candidates = array();

	foreach ( $data['results'] as $result ) {
		if ( ! in_array( $result['license'], LM_ALLOWED_LICENSES, true ) ) {
			continue;
		}

		// Wide enough for the 2000px hero without upscaling.
		if ( (int) $result['width'] < 1400 || (int) $result['height'] < 800 ) {
			continue;
		}

		if ( in_array( $result['id'], $used, true ) ) {
			continue;
		}

		$candidates[] = $result;
	}

	return $candidates;
}

/**
 * Photo IDs already attached to a post, so no two articles share a photo.
 *
 * @return string[]
 */
function lm_used_photo_ids() {
	$ids = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_lm_photo_id',
		)
	);

	$used = array();

	foreach ( $ids as $id ) {
		$value = get_post_meta( $id, '_lm_photo_id', true );

		if ( $value ) {
			$used[] = $value;
		}
	}

	return $used;
}

/**
 * GET a URL and return the body.
 *
 * @param string $url Absolute URL.
 * @return string|false Response body, or false on failure.
 */
function lm_http_get( $url ) {
	$ch = curl_init( $url );

	curl_setopt_array(
		$ch,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_USERAGENT      => 'LazyMedia/2.0 (theme seed; contact redaksi@lazymedia.example)',
		)
	);

	$body = curl_exec( $ch );
	$code = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
	curl_close( $ch );

	if ( false === $body || $code >= 400 ) {
		return false;
	}

	return $body;
}

/**
 * Download a photo and register it as an attachment.
 *
 * @param string $slug     Post slug, used to name the file.
 * @param array  $photo    Openverse result.
 * @param string $alt_text Alt text for the image.
 * @return int Attachment ID, or 0 on failure.
 */
function lm_store_photo( $slug, $photo, $alt_text ) {
	$body = lm_http_get( $photo['url'] );

	if ( ! $body ) {
		// Some providers rate-limit or block the client; try the next candidate.
		$body = lm_http_get( $photo['thumbnail'] );
	}

	if ( ! $body ) {
		return 0;
	}

	$upload = wp_upload_dir();

	if ( ! empty( $upload['error'] ) ) {
		return 0;
	}

	$dir = trailingslashit( $upload['basedir'] ) . 'photos';

	if ( ! wp_mkdir_p( $dir ) ) {
		return 0;
	}

	$path     = trailingslashit( $dir ) . $slug . '.jpg';
	$relative = 'photos/' . $slug . '.jpg';

	if ( false === file_put_contents( $path, $body ) ) {
		return 0;
	}

	// A provider can return an HTML error page with a 200; only real image
	// bytes should become a featured image.
	$info = @getimagesize( $path );

	if ( ! $info || empty( $info['mime'] ) || 'image/jpeg' !== $info['mime'] ) {
		wp_delete_file( $path );
		return 0;
	}

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => $alt_text,
			'post_content'   => '',
			'post_excerpt'   => $photo['foreign_landing_url'],
			'post_status'    => 'inherit',
		),
		$path
	);

	if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
		return 0;
	}

	update_post_meta( $attachment_id, '_wp_attached_file', $relative );
	update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
	update_post_meta( $attachment_id, '_lm_photo_id', $photo['id'] );
	update_post_meta( $attachment_id, '_lm_photo_creator', $photo['creator'] );
	update_post_meta( $attachment_id, '_lm_photo_license', $photo['license'] );
	update_post_meta( $attachment_id, '_lm_photo_license_version', $photo['license_version'] );
	update_post_meta( $attachment_id, '_lm_photo_source', $photo['foreign_landing_url'] );

	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata(
		$attachment_id,
		wp_generate_attachment_metadata( $attachment_id, $path )
	);

	return (int) $attachment_id;
}

/**
 * Search phrases per article slug, matched to what the article is about.
 *
 * @var string[]
 */
$topics = array(
	'preview-monster-hunter-outlanders'     => 'action role playing game fantasy',
	'keliling-indonesia-comic-con-2026'     => 'comic convention cosplay crowd',
	'delapan-detail-terbaru-gta-vi'         => 'open world driving game road',
	'discord-game-mode-irit-cpu-gpu'        => 'computer processor circuit',
	'review-ace-combat-8-wings-of-theve'    => 'fighter jet aircraft sky',
	'xbox-project-helix-40-persen'          => 'game console controller',
	'cuffed-public-playtest-steam'          => 'programming source code computer',
	'dune-awakening-ps5-xbox'               => 'desert dunes sand landscape',
	'sepuluh-tahun-indonesia-comic-con'      => 'comic book shop shelves',
	'tales-of-eternia-remastered'           => 'retro arcade game console',
	'harga-gpu-micron-bkin-mahal'            => 'graphics card gaming pc',
	'laptop-gigabyte-kembali-indonesia'     => 'laptop computer desk',
	'setup-rgb-anggun-murah'                => 'gaming desk neon lights',
	'overclocking-ram-ddr5-egin'            => 'computer memory ram hardware',
	'review-keyboard-mekanik-entry-level'   => 'mechanical keyboard switches',
	'gadget-lipa-untuk-travel'              => 'travel gadgets accessories',
	'manga-terbaru-2026'                    => 'manga comic illustration',
	'film-seri-musim-ini'                   => 'cinema movie theater screen',
	'esports-indonesia-naik-kelas'          => 'stage concert lights',
	'hobby-koleksi-action-figure'            => 'action figure collectible toy',
	'pc-budget-10-juta-2026'                => 'desktop computer case build',
);

$sourced = 0;
$kept    = 0;

foreach ( $topics as $slug => $topic ) {
	$post = get_page_by_path( $slug, OBJECT, 'post' );

	if ( ! $post ) {
		echo "skip (no post): {$slug}\n";
		continue;
	}

	// A post is done once it holds a real photograph, not a generated
	// placeholder. The seed script writes placeholders; this one replaces them.
	$thumb_id = (int) get_post_thumbnail_id( $post->ID );

	if ( $thumb_id && get_post_meta( $thumb_id, '_lm_photo_id', true ) ) {
		++$kept;
		continue;
	}

	// A search can return several acceptable photos; some provider links are
	// dead, so try each in turn rather than giving up on the first failure.
	$candidates     = lm_find_photos( $topic );
	$attachment_id = 0;
	$photo          = null;

	foreach ( $candidates as $candidate ) {
		$attachment_id = lm_store_photo( $slug, $candidate, $post->post_title );

		if ( $attachment_id ) {
			$photo = $candidate;
			break;
		}

		// This candidate is unusable; do not leave its file behind.
		wp_delete_file( trailingslashit( wp_upload_dir()['basedir'] ) . 'photos/' . $slug . '.jpg' );
	}

	if ( ! $attachment_id || ! $photo ) {
		echo count( $candidates )
			? "all downloads failed: {$slug} ({$topic})\n"
			: "no photo found: {$slug} ({$topic})\n";
		continue;
	}

	set_post_thumbnail( $post->ID, $attachment_id );

	$license = lm_license_info( $photo['license'] );

	echo "ok: {$slug} <- {$photo['creator']} ({$license['label']})\n";
	++$sourced;
}

echo "\nphotos done: {$sourced} sourced, {$kept} already in place\n";