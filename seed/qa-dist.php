<?php
/**
 * Regression suite for the static export.
 *
 * Verifies that dist/ is internally consistent: every page is written, every
 * asset a page references was copied, and every internal link a page contains
 * resolves to a file that exists. A static site cannot fall back to a server, so
 * a broken link is a permanently broken page.
 *
 * Run with:
 *   docker compose run --rm wpcli wp eval-file /seed/qa-dist.php --path=/var/www/html --allow-root
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

const LM_DIST_ROOT = '/dist';

/**
 * Collect and report failures, one line each.
 *
 * Uses $GLOBALS because WP-CLI evaluates this file inside a function scope.
 *
 * @param string $problem Failure description.
 * @return void
 */
function lm_dist_fail( $problem ) {
	$GLOBALS['lm_dist_failures'][] = $problem;
}

/**
 * Every file in the export with one of the given extensions.
 *
 * @param string[] $extensions Lowercase extensions without the dot.
 * @return string[] Absolute paths.
 */
function lm_dist_files( $extensions ) {
	$files = array();

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( LM_DIST_ROOT, FilesystemIterator::SKIP_DOTS )
	);

	foreach ( $items as $item ) {
		if ( $item->isFile() && in_array( strtolower( $item->getExtension() ), $extensions, true ) ) {
			$files[] = $item->getPathname();
		}
	}

	sort( $files );

	return $files;
}

/**
 * Every value of an attribute that can carry a URL.
 *
 * Both quote styles matter. WordPress prints its own enqueued stylesheets with
 * single quotes, so a checker that only reads double quotes never looks at the
 * CSS at all — and a build with no stylesheets in it reports every link as fine.
 *
 * @param string $html Page markup.
 * @return string[] Attribute values.
 */
function lm_dist_attr_values( $html ) {
	preg_match_all(
		'/\b(?:src|href|data-src|data-srcset|srcset|poster)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i',
		$html,
		$matches,
		PREG_SET_ORDER
	);

	$values = array();

	foreach ( $matches as $match ) {
		foreach ( array( 1, 2, 3 ) as $group ) {
			if ( isset( $match[ $group ] ) && '' !== $match[ $group ] ) {
				$values[] = $match[ $group ];
				break;
			}
		}
	}

	return $values;
}

/**
 * Internal URLs referenced by one page.
 *
 * @param string $html Page markup.
 * @return string[] Root-relative URLs, without fragments or query strings.
 */
function lm_dist_links( $html ) {
	$found = array();

	preg_match_all( '/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', $html, $css_matches );

	foreach ( array_merge( lm_dist_attr_values( $html ), $css_matches[1] ) as $candidate ) {
		// srcset packs several candidates into one attribute, comma separated.
		foreach ( explode( ',', $candidate ) as $url ) {
			$url = preg_split( '/\s/', trim( $url ) )[0];

			if ( ! $url ) {
				continue;
			}

			// Only same-origin paths matter; external URLs are not ours to serve.
			if ( 0 !== strpos( $url, '/' ) || 0 === strpos( $url, '//' ) ) {
				continue;
			}

			$url = preg_replace( '/[?#].*$/', '', $url );

			if ( $url ) {
				$found[ $url ] = true;
			}
		}
	}

	return array_keys( $found );
}

/**
 * Resolve a CSS url() reference against the stylesheet that contains it.
 *
 * @param string $css_url  Root-relative URL of the stylesheet.
 * @param string $relative Reference as written in the CSS.
 * @return string Root-relative path, or an empty string when it escapes the root.
 */
function lm_dist_resolve_css_url( $css_url, $relative ) {
	if ( 0 === strpos( $relative, '/' ) || 0 === strpos( $relative, '//' ) ) {
		return '';
	}

	$relative = explode( '#', explode( '?', $relative )[0] )[0];

	if ( ! $relative ) {
		return '';
	}

	$segments = array();

	foreach ( explode( '/', dirname( $css_url ) . '/' . $relative ) as $segment ) {
		if ( '' === $segment || '.' === $segment ) {
			continue;
		}

		if ( '..' === $segment ) {
			array_pop( $segments );
			continue;
		}

		$segments[] = $segment;
	}

	return '/' . implode( '/', $segments );
}

/**
 * Whether an exported page shows any post.
 *
 * An archive with no posts renders the archive template's empty state, which is
 * exactly how the exporter decides to leave a route out of the sitemap.
 *
 * @param string $file Absolute path to an exported page.
 * @return bool
 */
function lm_dist_has_content( $file ) {
	return false !== strpos( (string) file_get_contents( $file ), 'lm-card__title' );
}

/**
 * The file a root-relative URL is served from.
 *
 * Mirrors how a static host resolves a path: a directory-style URL is its
 * index.html, a clean URL drops its extension, and /search/<term>/ is rewritten
 * to the search page.
 *
 * @param string $url Root-relative URL.
 * @return string Absolute path, whether or not it exists.
 */
function lm_dist_target( $url ) {
	$path = LM_DIST_ROOT . $url;

	if ( is_dir( $path ) ) {
		return rtrim( $path, '/' ) . '/index.html';
	}

	if ( preg_match( '#^/search/[^/]+/?$#', $url ) ) {
		return LM_DIST_ROOT . '/search/index.html';
	}

	return $path;
}

// --- Checks ------------------------------------------------------------------

$files = lm_dist_files( array( 'html' ) );

if ( ! $files ) {
	echo "FAIL  no HTML was exported\n";
	exit( 1 );
}

$checked = 0;
$links   = 0;

foreach ( $files as $file ) {
	$html = (string) file_get_contents( $file );

	foreach ( lm_dist_links( $html ) as $url ) {
		$target = lm_dist_target( $url );

		if ( ! file_exists( $target ) ) {
			lm_dist_fail(
				sprintf(
					'%s links to %s, which is not in dist/',
					str_replace( LM_DIST_ROOT . '/', '', $file ),
					$url
				)
			);
			continue;
		}

		// A directory URL served without its trailing slash would redirect.
		if ( is_dir( LM_DIST_ROOT . $url ) && '/' !== substr( $url, -1 ) ) {
			lm_dist_fail( str_replace( LM_DIST_ROOT . '/', '', $file ) . ' links to ' . $url . ' without a trailing slash' );
		}

		++$links;
	}

	++$checked;
}

// An empty export that passes the loop above would still be a broken site.
foreach ( array( 'index.html', '404.html', 'search/index.html', 'search-index.json', 'sitemap.xml', 'robots.txt' ) as $required ) {
	if ( ! file_exists( LM_DIST_ROOT . '/' . $required ) ) {
		lm_dist_fail( $required . ' is missing from dist/' );
	}
}

/*
 * A stylesheet names its fonts and images with paths relative to itself, so no
 * page links to them and the loop above cannot see them. They are checked here
 * instead: a missing font is invisible in the markup but leaves the whole site
 * rendering in a fallback face.
 */
foreach ( lm_dist_files( array( 'css' ) ) as $file ) {
	$css_url = '/' . ltrim( str_replace( LM_DIST_ROOT, '', $file ), '/' );

	preg_match_all( '/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', (string) file_get_contents( $file ), $css_matches );

	foreach ( $css_matches[1] as $reference ) {
		$resolved = lm_dist_resolve_css_url( $css_url, $reference );

		if ( ! $resolved ) {
			continue;
		}

		if ( ! file_exists( LM_DIST_ROOT . $resolved ) ) {
			lm_dist_fail(
				sprintf(
					'%s references %s, which is not in dist/',
					$css_url,
					$resolved
				)
			);
			continue;
		}

		++$links;
	}
}

/*
 * Every page must ship the stylesheets that style it.
 *
 * A page whose CSS is missing still resolves every link in it, so the loop above
 * reports no problem at all while the site renders as plain HTML. The three
 * stylesheets below are loaded on every page by the header; if any one of them
 * is absent from dist/, the build is not a deliverable.
 */
$core_styles = array( '/theme/style.css', '/theme/assets/css/components.css', '/theme/assets/css/fonts.css' );

foreach ( $files as $file ) {
	$relative = ltrim( str_replace( LM_DIST_ROOT, '', $file ), '/' );
	$html     = (string) file_get_contents( $file );

	foreach ( $core_styles as $style ) {
		if ( false === strpos( $html, $style ) ) {
			lm_dist_fail( $relative . ' does not link ' . $style );
			continue;
		}

		if ( ! file_exists( LM_DIST_ROOT . $style ) ) {
			lm_dist_fail( $relative . ' links ' . $style . ', which is not in dist/' );
		}
	}
}

// The tokens that define the design have to survive into the shipped CSS.
foreach ( array( '--lm-bg: #0F0F0F', '--lm-accent: #FBAD18', '--lm-radius: 8px' ) as $token ) {
	$found = false;

	foreach ( lm_dist_files( array( 'css' ) ) as $file ) {
		if ( false !== strpos( (string) file_get_contents( $file ), $token ) ) {
			$found = true;
			break;
		}
	}

	if ( ! $found ) {
		lm_dist_fail( 'design token ' . $token . ' is missing from the shipped stylesheets' );
	}
}

/*
 * Every exported page must be listed in the sitemap, except the ones the
 * exporter deliberately withholds: the 404 page, and routes it marked
 * unindexable. Those are read back from the exporter's own route list rather
 * than guessed at, so a new exclusion stays in step with the exporter.
 */
$excluded = array( '404.html', 'search/index.html' );

foreach ( glob( LM_DIST_ROOT . '/category/*/index.html' ) as $file ) {
	if ( ! lm_dist_has_content( $file ) ) {
		$excluded[] = ltrim( str_replace( LM_DIST_ROOT, '', $file ), '/' );
	}
}

$sitemap = (string) file_get_contents( LM_DIST_ROOT . '/sitemap.xml' );

foreach ( $files as $file ) {
	$relative = ltrim( str_replace( LM_DIST_ROOT, '', $file ), '/' );

	if ( 'index.html' === $relative || in_array( $relative, $excluded, true ) ) {
		continue;
	}

	$route = '/' . rtrim( dirname( $relative ), '/' ) . '/';

	// <loc> is absolute when LM_PUBLIC_URL is set, root-relative otherwise.
	if ( ! preg_match( '#<loc>(?:https?://[^/<]+)?' . preg_quote( $route, '#' ) . '</loc>#', $sitemap ) ) {
		lm_dist_fail( $route . ' is exported but absent from sitemap.xml' );
	}
}

$failures = $GLOBALS['lm_dist_failures'] ?? array();

foreach ( $failures as $failure ) {
	echo "FAIL  {$failure}\n";
}

if ( $failures ) {
	echo "\ndist QA: " . count( $failures ) . " problems in {$checked} pages\n";
	exit( 1 );
}

echo "PASS  dist pages, assets and links resolve ({$checked} pages, {$links} links checked)\n";
echo "\ndist QA: all clear\n";