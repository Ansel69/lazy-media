<?php
/**
 * Design tokens exposed to PHP.
 *
 * The same values live in style.css as custom properties. Anything a template
 * needs in PHP — chiefly the per-section accent colour, which is also written
 * into an inline style attribute — reads from here so the two never drift.
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Accent colour for a homepage block.
 *
 * The neon divider under each section heading is the site's signature, so the
 * colour is per-section rather than global.
 *
 * @param string $section Section slug: hero, new-articles, games, hardware,
 *                        entertainment, latest.
 * @return string Hex colour.
 */
function lazymedia_section_accent( $section ) {
	$accents = array(
		'hero'          => '#FF7E36',
		'new-articles'  => '#FF7E36',
		'games'         => '#C203C6',
		'hardware'      => '#36FFF3',
		'entertainment' => '#BFFA17',
		'latest'        => '#FF36EB',
	);

	return isset( $accents[ $section ] ) ? $accents[ $section ] : '#FF7E36';
}

/**
 * Human label for a section slug.
 *
 * @param string $section Section slug.
 * @return string Display label.
 */
function lazymedia_section_label( $section ) {
	$labels = array(
		'hero'          => 'Pilihan Redaksi',
		'new-articles'  => 'New Articles',
		'games'         => 'Games',
		'hardware'      => 'Hardware',
		'entertainment' => 'Entertainment',
		'latest'        => 'Latest Articles',
	);

	return isset( $labels[ $section ] ) ? $labels[ $section ] : ucfirst( $section );
}

/**
 * Estimated reading time in minutes for a post.
 *
 * @param int $post_id Post ID.
 * @return int Minutes, minimum 1.
 */
function lazymedia_read_time( $post_id ) {
	$words   = str_word_count( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ) );
	$minutes = (int) ceil( $words / 200 );

	return max( 1, $minutes );
}

/**
 * The page that holds the contact details.
 *
 * The slug is a content decision, so it is resolved rather than hardcoded: a
 * hardcoded path silently 404s the moment an editor renames the page. Both the
 * Indonesian and English slugs are accepted.
 *
 * @return WP_Post|null
 */
function lazymedia_contact_page() {
	foreach ( array( 'contact', 'kontak' ) as $slug ) {
		$page = get_page_by_path( $slug );

		if ( $page ) {
			return $page;
		}
	}

	return null;
}

/**
 * URL of the contact page, falling back to the home page.
 *
 * @return string
 */
function lazymedia_contact_url() {
	$page = lazymedia_contact_page();

	return $page ? get_permalink( $page ) : home_url( '/' );
}

/**
 * Whether the page being rendered is the contact page.
 *
 * @return bool
 */
function lazymedia_is_contact_page() {
	$page = lazymedia_contact_page();

	return $page && is_page( $page->ID );
}

/**
 * Social profiles shown in the footer.
 *
 * @return array<int, array{label:string,url:string,handle:string}> Profile list.
 */
function lazymedia_social_links() {
	$networks = array(
		array(
			'label'  => 'Instagram',
			'handle' => '@lazymedia',
			'url'    => 'https://instagram.com/lazymedia',
		),
		array(
			'label'  => 'YouTube',
			'handle' => '@lazymedia',
			'url'    => 'https://youtube.com/@lazymedia',
		),
		array(
			'label'  => 'X',
			'handle' => '@lazymedia',
			'url'    => 'https://x.com/lazymedia',
		),
		array(
			'label'  => 'TikTok',
			'handle' => '@lazymedia',
			'url'    => 'https://tiktok.com/@lazymedia',
		),
	);

	/**
	 * Filter the footer social profiles.
	 *
	 * @param array $networks Profile definitions.
	 */
	return apply_filters( 'lazymedia_social_links', $networks );
}