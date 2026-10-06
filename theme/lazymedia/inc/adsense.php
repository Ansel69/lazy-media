<?php
/**
 * Google AdSense loader.
 *
 * The publisher id lives here and nowhere else: inc/adsense.php is the single
 * source of truth for both the <head> snippet and dist/ads.txt, which the
 * exporter reads from this same constant.
 *
 * Replace the placeholder with the real ca-pub-... id, then re-run
 * .\scripts\export.ps1 so both the snippet and ads.txt pick it up.
 *
 * @package ArkMedia
 */

defined( 'ABSPATH' ) || exit;

// Placeholder: swap for the real publisher id before applying to AdSense.
define( 'LM_ADSENSE_PUB', 'ca-pub-0000000000000000' );

/**
 * Print the AdSense loader in <head>.
 *
 * Nothing renders while the placeholder is in place, so a site exported
 * without a real id ships no dead script.
 *
 * @return void
 */
function lm_adsense_head() {
	if ( 'ca-pub-0000000000000000' === LM_ADSENSE_PUB ) {
		return;
	}

	printf(
		"%s\n",
		'<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' . esc_attr( LM_ADSENSE_PUB ) . '" crossorigin="anonymous"></script>'
	);
}
add_action( 'wp_head', 'lm_adsense_head', 5 );

/**
 * ads.txt line for Google AdSense.
 *
 * The exporter calls this to write dist/ads.txt.
 *
 * @return string Line without trailing newline, empty when unconfigured.
 */
function lm_adsense_ads_txt() {
	// ca-pub-1234... -> pub-1234... (ads.txt wants the pub- form).
	return 'google.com, ' . preg_replace( '/^ca-/', '', LM_ADSENSE_PUB ) . ', DIRECT, f08c47fec0942fa0';
}
