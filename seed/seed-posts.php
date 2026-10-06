<?php
/**
 * Seed: ~20 dummy articles with locally generated placeholder thumbnails.
 *
 * Run with:
 *   docker compose run --rm wpcli wp eval-file /seed/seed-posts.php --path=/var/www/html --allow-root
 *
 * Placeholder images are drawn with GD into wp-content/uploads/seed so nothing
 * is fetched from the network. Re-running updates nothing: posts are matched by
 * slug.
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Build a gradient placeholder image and register it as an attachment.
 *
 * @param string $slug    Post slug, also used to name the file.
 * @param string $label   Short text drawn on the image.
 * @param array  $palette [start hex, end hex] for the gradient.
 * @return int Attachment ID, or 0 on failure.
 */
function lm_seed_image( $slug, $label, $palette ) {
	$upload = wp_upload_dir();
	if ( ! empty( $upload['error'] ) ) {
		echo "upload dir error: {$upload['error']}\n";
		return 0;
	}

	$dir = trailingslashit( $upload['basedir'] ) . 'seed';
	if ( ! wp_mkdir_p( $dir ) ) {
		echo "cannot create {$dir}\n";
		return 0;
	}

	$filename = "{$slug}.jpg";
	$path     = trailingslashit( $dir ) . $filename;
	$relative = 'seed/' . $filename;

	$width  = 1280;
	$height = 720;

	if ( ! file_exists( $path ) ) {
		$image = imagecreatetruecolor( $width, $height );

		list( $r1, $g1, $b1 ) = sscanf( $palette[0], '#%02x%02x%02x' );
		list( $r2, $g2, $b2 ) = sscanf( $palette[1], '#%02x%02x%02x' );

		// Diagonal gradient.
		for ( $y = 0; $y < $height; $y++ ) {
			for ( $x = 0; $x < $width; $x += 8 ) {
				$t = ( ( $x / $width ) + ( $y / $height ) ) / 2;
				$color = imagecolorallocate(
					$image,
					(int) ( $r1 + ( $r2 - $r1 ) * $t ),
					(int) ( $g1 + ( $g2 - $g1 ) * $t ),
					(int) ( $b1 + ( $b2 - $b1 ) * $t )
				);
				imageline( $image, $x, $y, min( $x + 8, $width ), $y, $color );
			}
		}

		// Subtle grid so cards do not look flat.
		$grid = imagecolorallocatealpha( $image, 255, 255, 255, 108 );
		for ( $x = 0; $x < $width; $x += 80 ) {
			imageline( $image, $x, 0, $x, $height, $grid );
		}
		for ( $y = 0; $y < $height; $y += 80 ) {
			imageline( $image, 0, $y, $width, $y, $grid );
		}

		// Accent dot.
		$accent = imagecolorallocate( $image, 255, 122, 26 );
		imagefilledellipse( $image, 96, 96, 40, 40, $accent );

		// Label text, uppercase first 18 characters.
		$white = imagecolorallocate( $image, 240, 243, 248 );
		$text  = strtoupper( mb_substr( $label, 0, 18 ) );
		imagestring( $image, 5, 40, $height - 70, $text, $white );

		imagejpeg( $image, $path, 82 );
		imagedestroy( $image );
	}

	// Reuse the attachment if it already exists for this file.
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
		return (int) $existing[0];
	}

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => $label,
			'post_content'   => '',
			'post_status'    => 'inherit',
		),
		$path
	);

	if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
		echo "attachment error for {$slug}\n";
		return 0;
	}

	update_post_meta( $attachment_id, '_wp_attached_file', $relative );
	update_post_meta( $attachment_id, '_wp_attachment_image_alt', $label );

	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata(
		$attachment_id,
		wp_generate_attachment_metadata( $attachment_id, $path )
	);

	return (int) $attachment_id;
}

$palettes = array(
	array( '#1d222d', '#3a2a1f' ),
	array( '#151922', '#20323a' ),
	array( '#1a1524', '#2f1f33' ),
	array( '#12241c', '#2b2412' ),
);

// 'cat' is the primary category. 'sections' are the broad homepage blocks the
// article should also appear in (games / hardware / entertainment), so the
// category blocks are never starved of posts.
$articles = array(
	array( 'slug' => 'preview-monster-hunter-outlanders', 'title' => 'Preview Monster Hunter Outlanders - Game Mobile yang Jauh Lebih Menantang dari Perkiraan', 'cat' => 'games', 'sections' => array( 'games' ), 'tags' => array( 'games' ), 'video' => true ),
	array( 'slug' => 'keliling-indonesia-comic-con-2026', 'title' => 'Keliling Indonesia Comic Con 2026, dari Cosplay sampai Borong Komik!', 'cat' => 'entertainment', 'sections' => array( 'entertainment' ), 'tags' => array( 'entertainment' ) ),
	array( 'slug' => 'delapan-detail-terbaru-gta-vi', 'title' => 'Delapan Detail Terbaru GTA VI dari Game Informer', 'cat' => 'game-news', 'sections' => array( 'games' ), 'tags' => array( 'games' ) ),
	array( 'slug' => 'discord-game-mode-irit-cpu-gpu', 'title' => 'Discord Bikin Game Mode Biar Irit CPU dan GPU!', 'cat' => 'tech-tips', 'sections' => array( 'hardware' ), 'tags' => array( 'tech' ) ),
	array( 'slug' => 'review-ace-combat-8-wings-of-theve', 'title' => 'Review Ace Combat 8: Wings of Theve - Penantian PANJANG YANG SANGAT WORTH IT!', 'cat' => 'reviews', 'sections' => array( 'games' ), 'tags' => array( 'reviews' ), 'video' => true ),
	array( 'slug' => 'xbox-project-helix-40-persen', 'title' => 'XBOX Project Helix 40% Lebih Cepat dari PS6?', 'cat' => 'console', 'sections' => array( 'games', 'hardware' ), 'tags' => array( 'games' ) ),
	array( 'slug' => 'cuffed-public-playtest-steam', 'title' => 'CUFFED Buka Public Playtest di Steam', 'cat' => 'game-news', 'sections' => array( 'games' ), 'tags' => array( 'games' ) ),
	array( 'slug' => 'dune-awakening-ps5-xbox', 'title' => 'Dune: Awakening Resmi Mendarat di PS5 dan Xbox, Bawa DLC dan Diskon Besar', 'cat' => 'game-news', 'sections' => array( 'games' ), 'tags' => array( 'games' ) ),
	array( 'slug' => 'sepuluh-tahun-indonesia-comic-con', 'title' => '10 Tahun Indonesia Comic Con: Venue Baru, Kreator Global, dan Kehadiran Tamu Kehormatan', 'cat' => 'entertainment', 'sections' => array( 'entertainment' ), 'tags' => array( 'entertainment' ) ),
	array( 'slug' => 'tales-of-eternia-remastered', 'title' => 'Tales of Eternia Remastered: Mengembalikan Kejayaan JRPG Klasik untuk Gamer Modern', 'cat' => 'reviews', 'sections' => array( 'games' ), 'tags' => array( 'reviews' ) ),
	array( 'slug' => 'harga-gpu-micron-bkin-mahal', 'title' => 'Harga GPU Kurang Mahal? Micron Siap Perburuk Keadaan!', 'cat' => 'hardware', 'sections' => array( 'hardware' ), 'tags' => array( 'tech' ) ),
	array( 'slug' => 'laptop-gigabyte-kembali-indonesia', 'title' => 'Laptop GIGABYTE Hadir Kembali di Indonesia!', 'cat' => 'hardware', 'sections' => array( 'hardware' ), 'tags' => array( 'tech' ) ),
	array( 'slug' => 'setup-rgb-anggun-murah', 'title' => 'Setup RGB Keren di Bawah Dua Juta, Cocok untuk Meja Kerja', 'cat' => 'setup', 'sections' => array( 'hardware', 'hobby' ), 'tags' => array( 'tech' ) ),
	array( 'slug' => 'overclocking-ram-ddr5-egin', 'title' => 'Overclocking RAM DDR5 Explained: Rahasia Frekuensi dan Timing', 'cat' => 'overclocking', 'sections' => array( 'hardware' ), 'tags' => array( 'tech' ) ),
	array( 'slug' => 'review-keyboard-mekanik-entry-level', 'title' => 'Review Keyboard Mekanik Entry-Level yang Layak Dilirik 2026', 'cat' => 'tech-review', 'sections' => array( 'hardware' ), 'tags' => array( 'reviews', 'tech' ) ),
	array( 'slug' => 'gadget-lipa-untuk-travel', 'title' => 'Lima Gadget Lupa yang Literally Selalu Dibawa Saat Travel', 'cat' => 'gadgets', 'sections' => array( 'hardware' ), 'tags' => array( 'tech' ) ),
	array( 'slug' => 'manga-terbaru-2026', 'title' => 'Lima Manga Terbaru 2026 yang Wajib Masuk Koleksi', 'cat' => 'anime', 'sections' => array( 'entertainment' ), 'tags' => array( 'entertainment' ) ),
	array( 'slug' => 'film-seri-musim-ini', 'title' => 'Film dan Seri Musim Ini yang Layak Dicatat', 'cat' => 'movie-series', 'sections' => array( 'entertainment' ), 'tags' => array( 'entertainment' ) ),
	array( 'slug' => 'esports-indonesia-naik-kelas', 'title' => 'Esports Indonesia Naik Kelas di Panggung Asia Tenggara', 'cat' => 'esports', 'sections' => array( 'games' ), 'tags' => array( 'esports' ) ),
	array( 'slug' => 'hobby-koleksi-action-figure', 'title' => 'Mulai Koleksi Action Figure? Baca Dulu Ini', 'cat' => 'hobby', 'sections' => array( 'hobby' ), 'tags' => array( 'hobby' ) ),
	array( 'slug' => 'pc-budget-10-juta-2026', 'title' => 'Rekomendasi Build PC Budget 10 Juta untuk Gaming di 1440p', 'cat' => 'pc', 'sections' => array( 'hardware' ), 'tags' => array( 'tech', 'games' ) ),
);

$excerpts = array(
	'Jakarta, 5 Oktober 2026 - Banyak gamer berasumsi bahwa adaptasi game konsol atau PC ke platform mobile hanya sekadar pemotongan konten. Developers ternyata menemukan cara memindahkan kompleksitas yang selama ini hanya terasa di perangkat besar.',
	'Event tahunan kali ini datang dengan konsep venue baru yang lebih lapang, plus deretan kreator internasional yang akhirnya bisa ditemui langsung oleh pengunjung Indonesia.',
	'Detail terbaru soal game yang paling ditunggu ini kembali terungkap lewat tulisan eksklusif salah satu media gaming terbesar, dan daftar cirinya makin panjang.',
	'Salah satu aplikasi komunikasi yang paling user-friendly namun ternyata cukup boros resource. Kabar baiknya, ada fitur baru yang membuat pemakaian CPU dan GPU jauh lebih ringan.',
	'Setelah bertahun-tahun dinantikan, judul ini akhirnya mendarat. Review ini membahas prologue, kualitas audio, dan apakah performanya layak untuk sesi panjang.',
	'Render bocoran dari chip generasi berikutnya menunjukkan lonjakan performa yang jauh melampaui ekspektasi analis di awal tahun.',
	'Developer indie asal Amerika Serikat ini resmi membuka akses versi alpha untuk semua pemain di seluruh dunia, tanpa waiting list.',
	'Developer sekaligus publisher mengonfirmasi judul sandbox survival mereka akan mendarat di PS5 dan Xbox dengan skenario yang tidak ada di rilis PC.',
	'Sepuluh tahun menjadi ruang pertemuan serius bagi Maca, komik, animasi, dan gim lokal. Tahun ini, formatnya berubah total.',
);

$made  = 0;
$index = 0;

foreach ( $articles as $article ) {
	$palette = $palettes[ $index % count( $palettes ) ];
	++$index;

	$existing = get_page_by_path( $article['slug'], OBJECT, 'post' );

	if ( $existing ) {
		$post_id = (int) $existing->ID;
	} else {
		$post_id = wp_insert_post(
			array(
				'post_title'   => $article['title'],
				'post_name'    => $article['slug'],
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'post_author'  => 1,
				'post_content' => "<p>Paragraf pembuka artikel. Konten ini placeholder untuk melihat ritme homepage, bukan artikel asli.</p>\n\n<p>Paragraf kedua agar tampilan konten terasa penuh saat dibaca.</p>",
				'post_excerpt' => $excerpts[ ( $index - 1 ) % count( $excerpts ) ],
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			echo "post error ({$article['slug']})\n";
			continue;
		}

		// Spread publication dates backwards so the "date" columns differ.
		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( "-{$index} days" ) ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', strtotime( "-{$index} days" ) ),
			)
		);

		++$made;
		echo "post: {$article['slug']}\n";
	}

	$term_ids = array();
	foreach ( array_merge( array( $article['cat'] ), $article['sections'] ) as $cat_slug ) {
		$term = get_category_by_slug( $cat_slug );
		if ( $term && ! in_array( $term->term_id, $term_ids, true ) ) {
			$term_ids[] = $term->term_id;
		}
	}

	if ( $term_ids ) {
		// Primary category stays first so cards label correctly.
		wp_set_post_categories( $post_id, $term_ids );
	}

	foreach ( $article['tags'] as $tag_slug ) {
		$tag = get_term_by( 'slug', $tag_slug, 'post_tag' );
		if ( $tag ) {
			wp_set_post_terms( $post_id, array( $tag->term_id ), 'post_tag' );
		}
	}

	$thumb_id = lm_seed_image( $article['slug'], $article['title'], $palette );
	if ( $thumb_id ) {
		set_post_thumbnail( $post_id, $thumb_id );
	}

	if ( ! empty( $article['video'] ) ) {
		update_post_meta( $post_id, '_lm_featured_video', '1' );
	}
}

echo "posts seed done, created {$made}\n";