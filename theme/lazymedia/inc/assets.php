<?php
/**
 * Site icon, favicon, and browser chrome metadata.
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register a favicon at every size modern browsers ask for.
 *
 * Vectors only: an .ico fallback would be a binary in the theme, and every
 * current browser accepts SVG.
 *
 * @return void
 */
function lazymedia_site_icon() {
	echo '<link rel="icon" href="' . esc_url( get_template_directory_uri() . '/assets/img/icon.svg' ) . '" type="image/svg+xml">' . "\n";
	echo '<link rel="mask-icon" href="' . esc_url( get_template_directory_uri() . '/assets/img/icon.svg' ) . '" color="#FBAD18">' . "\n";
	echo '<link rel="apple-touch-icon" href="' . esc_url( get_template_directory_uri() . '/assets/img/icon.svg' ) . '">' . "\n";
	echo '<meta name="theme-color" content="#0F0F0F">' . "\n";
}
add_action( 'wp_head', 'lazymedia_site_icon', 5 );

/**
 * Preconnect hints are unnecessary: the site loads no third-party origins.
 *
 * @param array  $urls          URLs to print.
 * @param string $relation_type Relation type.
 * @return array
 */
function lazymedia_no_preconnect( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type || 'dns-prefetch' === $relation_type ) {
		return array();
	}

	return $urls;
}
add_filter( 'wp_resource_hints', 'lazymedia_no_preconnect', 10, 2 );

/**
 * Drop the WordPress generator tag: it advertises the version and nothing else.
 *
 * @return string
 */
function lazymedia_no_generator() {
	return '';
}
add_filter( 'the_generator', 'lazymedia_no_generator' );

/**
 * Remove the shortlink and adjacent post links.
 *
 * They are meaningless once the site is a static export and add bytes to every
 * page head.
 *
 * @return void
 */
function lazymedia_clean_head() {
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'wp_head', 'wp_oembed_add_host_js' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'feed_links', 2 );
	remove_action( 'wp_head', 'feed_links_extra', 3 );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );

	// The REST API does not exist once the site is a static export, so the
	// discovery links WordPress advertises for it would all point at nothing.
	remove_action( 'wp_head', 'rest_output_link_wp_head' );
	remove_action( 'template_redirect', 'rest_output_link_header', 11 );
}

/**
 * Turn off speculative loading.
 *
 * WordPress emits speculation rules that prefetch every link it can see. On a
 * static export that is counterproductive: each page is already a plain HTML
 * file, so prefetching only double-downloads pages a visitor is about to read.
 *
 * The filter must return null rather than an empty array: an array is
 * normalised back to the prefetch/conservative default instead of disabling it.
 *
 * @param array|null $config Speculation rule configuration.
 * @return null
 */
function lazymedia_no_speculation( $config ) {
	return null;
}
add_filter( 'wp_speculation_rules_configuration', 'lazymedia_no_speculation' );
add_action( 'init', 'lazymedia_clean_head' );