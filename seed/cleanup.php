<?php
/**
 * Cleanup: remove default WordPress content and add the About page.
 *
 * Run with:
 *   docker compose run --rm wpcli wp eval-file /seed/cleanup.php --path=/var/www/html --allow-root
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

// Delete the bundled sample post and pages.
foreach ( array( 'hello-world', 'sample-page', 'privacy-policy' ) as $slug ) {
	foreach ( array( 'post', 'page' ) as $type ) {
		$found = get_page_by_path( $slug, OBJECT, $type );
		while ( $found ) {
			wp_delete_post( $found->ID, true );
			echo "deleted {$type}: {$slug}\n";
			$found = get_page_by_path( $slug, OBJECT, $type );
		}
	}
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

// Reading settings.
update_option( 'posts_per_page', 8 );
update_option( 'show_on_front', 'page' );

$front = get_page_by_path( 'home', OBJECT, 'page' );
if ( $front ) {
	update_option( 'page_on_front', $front->ID );
	echo "front page: home (ID {$front->ID})\n";
}

echo 'cleanup done' . "\n";