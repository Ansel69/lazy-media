<?php
/**
 * Cleanup: remove default WordPress content and add the About, Contact, and
 * Privacy Policy pages.
 *
 * Run with:
 *   docker compose run --rm wpcli wp eval-file /seed/cleanup.php --path=/var/www/html --allow-root
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

// Delete the bundled sample post and pages. The bundled privacy stub ships as
// a draft; the published privacy page we create below must survive re-runs, so
// only drafts get removed.
foreach ( array( 'hello-world', 'sample-page' ) as $slug ) {
	foreach ( array( 'post', 'page' ) as $type ) {
		$found = get_page_by_path( $slug, OBJECT, $type );
		while ( $found ) {
			wp_delete_post( $found->ID, true );
			echo "deleted {$type}: {$slug}\n";
			$found = get_page_by_path( $slug, OBJECT, $type );
		}
	}
}

$privacy_stub = get_page_by_path( 'privacy-policy', OBJECT, 'page' );
if ( $privacy_stub && 'publish' !== $privacy_stub->post_status ) {
	wp_delete_post( $privacy_stub->ID, true );
	echo "deleted page: privacy-policy (bundled stub)\n";
}

// Uncategorized is left in place: WordPress requires it and dropping it would
// break any future post created from the admin without picking a category.

// About page referenced by the primary nav.
$about = get_page_by_path( 'about', OBJECT, 'page' );

if ( ! $about ) {
	$about_id = wp_insert_post(
		array(
			'post_title'   => 'About',
			'post_name'    => 'about',
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_content' => '<h2>Tentang Kami</h2><p>Tempat deskripsi singkat tentang media ini. Tulis profil, visi, dan misi di sini lewat WP Admin &rarr; Pages &rarr; About.</p>',
		)
	);
	echo "created page: about (ID {$about_id})\n";
} else {
	echo "page exists: about\n";
}

// Contact page so the footer link does not 404.
$contact = get_page_by_path( 'contact', OBJECT, 'page' );

if ( ! $contact ) {
	$contact_id = wp_insert_post(
		array(
			'post_title'   => 'Contact',
			'post_name'    => 'contact',
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_content' => '<h2>Kontak</h2><p>Tulis informasi kontak (email, sosial media, alamat redaksi) di sini.</p>',
		)
	);
	echo "created page: contact (ID {$contact_id})\n";
} else {
	echo "page exists: contact\n";
}

// Privacy policy: required for AdSense and linked from the footer menu.
$privacy = get_page_by_path( 'privacy-policy', OBJECT, 'page' );

if ( ! $privacy ) {
	$lm_privacy_content = sprintf(
		'<h2>Kebijakan Privasi %1$s</h2>
		<p>Kebijakan ini menjelaskan bagaimana %1$s mengumpulkan, menggunakan, dan melindungi informasi pengunjung situs ini. Dengan mengakses situs ini, Anda menyetujui praktik yang dijelaskan di sini.</p>
		<h3>Informasi yang dikumpulkan</h3>
		<p>Situs ini dapat mengumpulkan informasi non-pribadi seperti jenis browser, perangkat, halaman yang dikunjungi, dan waktu akses, baik langsung maupun melalui layanan analitik. Informasi tersebut dipakai secara agregat untuk meningkatkan kualitas konten.</p>
		<h3>Cookie</h3>
		<p>Situs ini dapat menggunakan cookie untuk mengingat preferensi pengunjung. Anda dapat menonaktifkan cookie melalui pengaturan browser Anda.</p>
		<h3>Iklan pihak ketiga</h3>
		<p>Situs ini menggunakan Google AdSense untuk menayangkan iklan. Google dan vendor pihak ketiga dapat menggunakan cookie untuk menayangkan iklan berdasarkan kunjungan Anda ke situs ini maupun situs lain di internet.</p>
		<ul>
			<li>Google memakai cookie DART agar iklan dapat ditayangkan berdasarkan kunjungan Anda ke situs ini dan situs lain.</li>
			<li>Anda dapat mengelola iklan yang ditayangkan Google lewat <a href="https://adssettings.google.com" target="_blank" rel="noopener noreferrer">Google Ads Settings</a>.</li>
			<li>Anda dapat memilih keluar dari cookie vendor pihak ketiga lewat <a href="https://optout.aboutads.info" target="_blank" rel="noopener noreferrer">AboutAds.info Opt-Out</a> dan <a href="https://optout.networkadvertising.org" target="_blank" rel="noopener noreferrer">Network Advertising Initiative Opt-Out</a>.</li>
			<li>Kebijakan privasi Google mengenai iklan tersedia di <a href="https://policies.google.com/technologies/ads" target="_blank" rel="noopener noreferrer">policies.google.com/technologies/ads</a>.</li>
		</ul>
		<h3>Tautan ke situs lain</h3>
		<p>Beberapa artikel memuat tautan ke situs eksternal. Kami tidak bertanggung jawab atas konten atau kebijakan privasi situs-situs tersebut.</p>
		<h3>Data anak</h3>
		<p>Situs ini ditujukan untuk umum dan tidak secara sengaja mengumpulkan informasi pribadi dari anak di bawah umur.</p>
		<h3>Perubahan kebijakan</h3>
		<p>Kebijakan ini dapat diperbarui sewaktu-waktu. Versi terbaru selalu dipublikasikan di halaman ini.</p>
		<h3>Kontak</h3>
		<p>Pertanyaan seputar privasi dapat disampaikan melalui halaman <a href="%2$s">Kontak</a>.</p>',
		get_bloginfo( 'name' ),
		esc_url( home_url( '/contact/' ) )
	);

	$privacy_id = wp_insert_post(
		array(
			'post_title'   => 'Privacy Policy',
			'post_name'    => 'privacy-policy',
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_content' => $lm_privacy_content,
		)
	);
	echo "created page: privacy-policy (ID {$privacy_id})\n";
} else {
	echo "page exists: privacy-policy\n";
}

// Reading settings. front-page.php composes the homepage; WordPress only
// picks that template for "/" while the front is the posts index, so the
// static "page" mode is used only when a real "home" page exists.
update_option( 'posts_per_page', 8 );

$front = get_page_by_path( 'home', OBJECT, 'page' );
if ( $front ) {
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $front->ID );
	echo "front page: home (ID {$front->ID})\n";
} else {
	update_option( 'show_on_front', 'posts' );
	echo "front: posts index (no home page)\n";
}

echo 'cleanup done' . "\n";