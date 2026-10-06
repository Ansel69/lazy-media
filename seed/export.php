<?php
/**
 * Static exporter.
 *
 * Walks every public route, captures the rendered HTML, rewrites the local
 * origin away, copies the assets those pages reference, and writes the whole
 * site into /dist for Vercel to serve.
 *
 * Run through scripts/export.ps1, which empties /dist first.
 *
 * Output layout:
 *   dist/index.html                 home
 *   dist/<post-slug>/index.html     one directory per route, so no rewrite is
 *                                   needed to resolve relative links
 *   dist/404.html                   custom not-found page
 *   dist/theme/...                  theme CSS, JS, fonts, images
 *   dist/uploads/...                featured images and derivatives actually used
 *   dist/search-index.json          payload for client-side search
 *   dist/sitemap.xml, robots.txt
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

// The CLI container talks to the wordpress service over the internal network,
// and the public origin is not resolvable from inside the container.
define( 'WP_HTTP_BLOCK_EXTERNAL', false );
add_filter( 'redirect_canonical', '__return_false' );

const LM_DIST        = '/dist';
const LM_PER_PAGE    = 8;
const LM_THEME_PATH  = '/wp-content/themes/lazymedia/';
const LM_UPLOAD_PATH = '/wp-content/uploads/';

// Where those two prefixes land in the export. Keeping the theme and media in
// named directories instead of mirroring wp-content means nothing from another
// plugin can be dragged along by accident.
const LM_THEME_DEST  = '/theme/';
const LM_UPLOAD_DEST = '/uploads/';

/**
 * Site origin the running site answers on from inside the container.
 *
 * @return string
 */
function lm_export_origin() {
	return 'http://wordpress';
}

/**
 * The origin WordPress thinks it serves from, which is what appears in markup.
 *
 * @return string Origin without a trailing slash.
 */
function lm_export_public_origin() {
	return untrailingslashit( home_url( '/' ) );
}

/**
 * Production origin the export will be served from, e.g. https://lazy.media.
 *
 * Read from LM_PUBLIC_URL. Most links stay root-relative so the export works
 * on any host, but canonical, og:url, og:image, JSON-LD, share links and the
 * sitemap must be absolute: crawlers and link unfurlers ignore relative ones.
 *
 * @return string Origin without a trailing slash, or '' when not configured.
 */
function lm_export_site_origin() {
	$origin = trim( (string) getenv( 'LM_PUBLIC_URL' ) );

	return $origin ? untrailingslashit( $origin ) : '';
}

/**
 * GET a path on the running site.
 *
 * Uses cURL directly: WP-CLI forces WordPress HTTP requests onto the public host
 * port, which does not exist inside the container.
 *
 * @param string $path     Path relative to the site root.
 * @param int    $expected Status code the route must return.
 * @return string Response body.
 * @throws RuntimeException When the request fails or returns the wrong code.
 */
function lm_export_fetch( $path, $expected = 200 ) {
	$ch    = curl_init( lm_export_origin() . $path );
	$parts = wp_parse_url( lm_export_public_origin() );

	curl_setopt_array(
		$ch,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_TIMEOUT        => 40,
			CURLOPT_HTTPHEADER     => array( 'Host: ' . $parts['host'] . ':' . $parts['port'] ),
		)
	);

	$body = curl_exec( $ch );

	if ( false === $body ) {
		$error = curl_error( $ch );
		curl_close( $ch );
		throw new RuntimeException( $path . ' could not be requested: ' . $error );
	}

	$code = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
	curl_close( $ch );

	if ( $expected && $code !== $expected ) {
		throw new RuntimeException( $path . ' returned HTTP ' . $code . ', expected ' . $expected );
	}

	return $body;
}

/**
 * Turn an absolute site URL into a path relative to the site root.
 *
 * @param string $url Absolute URL.
 * @return string Path starting with a slash, or an empty string when the URL is
 *                not on this site.
 */
function lm_export_path( $url ) {
	$origin = lm_export_public_origin();

	if ( 0 !== strpos( $url, $origin ) ) {
		return '';
	}

	return '/' . ltrim( substr( $url, strlen( $origin ) ), '/' );
}

/**
 * Rewrite local absolute URLs into root-relative ones.
 *
 * The longest prefixes are replaced first so a theme or media URL never loses
 * its tail, then the bare origin is dropped so ordinary links such as the home
 * page become "/". Each replacement is also applied to the slash-escaped form
 * that appears inside JSON blobs such as the localized script data.
 *
 * @param string $html Rendered markup.
 * @return string Markup with no reference to the local origin.
 */
function lm_export_rewrite( $html ) {
	$origin  = lm_export_public_origin();
	$replace = array(
		$origin . LM_THEME_PATH  => LM_THEME_DEST,
		$origin . LM_UPLOAD_PATH => LM_UPLOAD_DEST,
		// Share buttons pass the permalink URL-encoded inside a query string,
		// where a relative path is useless; they get the production origin.
		rawurlencode( $origin )  => rawurlencode( lm_export_site_origin() ),
		$origin                  => '',
	);

	foreach ( $replace as $needle => $replacement ) {
		$escaped_needle     = str_replace( '/', '\\/', $needle );
		$escaped_replacement = str_replace( '/', '\\/', $replacement );

		$html = str_replace(
			array( $needle, $escaped_needle ),
			array( $replacement, $escaped_replacement ),
			$html
		);
	}

	return $html;
}

/**
 * Prefix a root-relative URL with the production origin.
 *
 * @param string $url URL as it appears after lm_export_rewrite().
 * @return string
 */
function lm_export_absolute( $url ) {
	$origin = lm_export_site_origin();

	if ( ! $origin || ! is_string( $url ) || '/' !== substr( $url, 0, 1 ) || '//' === substr( $url, 0, 2 ) ) {
		return $url;
	}

	return $origin . $url;
}

/**
 * Make the URLs that must be absolute absolute again.
 *
 * Runs after the rewrite and after asset collection. Only three places are
 * touched: meta tags whose content is a path (og:url, og:image, twitter:image),
 * the canonical link, and every path-valued string inside JSON-LD.
 *
 * @param string $html Rewritten markup.
 * @return string
 */
function lm_export_absolutize( $html ) {
	if ( ! lm_export_site_origin() ) {
		return $html;
	}

	$html = preg_replace_callback(
		'/<meta\b[^>]*\bcontent="(\/[^"\/][^"]*|\/)"[^>]*>/i',
		function ( $m ) {
			return str_replace( '"' . $m[1] . '"', '"' . lm_export_absolute( $m[1] ) . '"', $m[0] );
		},
		$html
	);

	$html = preg_replace_callback(
		'/<link\b[^>]*\brel="canonical"[^>]*>/i',
		function ( $m ) {
			return preg_replace_callback(
				'/\bhref="([^"]*)"/',
				function ( $h ) {
					return 'href="' . lm_export_absolute( $h[1] ) . '"';
				},
				$m[0]
			);
		},
		$html
	);

	return preg_replace_callback(
		'/(<script type="application\/ld\+json">)(.*?)(<\/script>)/s',
		function ( $m ) {
			$data = json_decode( $m[2], true );

			if ( null === $data ) {
				return $m[0];
			}

			array_walk_recursive(
				$data,
				function ( &$value ) {
					$value = lm_export_absolute( $value );
				}
			);

			return $m[1] . json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . $m[3];
		},
		$html
	);
}

/**
 * Every route the export must include.
 *
 * Archives are paginated: the largest category holds more posts than fit on one
 * page, and a static site cannot produce page two on request. Empty archives are
 * exported too, because the navigation links to categories that have no posts
 * yet — a nav link that 404s is worse than a nav link to an empty archive, which
 * the archive template renders with an explicit empty state.
 *
 * Each route carries an "index" flag: only routes with content belong in the
 * sitemap, so the empty archives stay reachable but stay out of search results.
 *
 * @return array<int, array{path:string,file:string,index:bool}>
 */
function lm_export_routes() {
	$routes   = array();
	$per_page = (int) get_option( 'posts_per_page' ) ? (int) get_option( 'posts_per_page' ) : LM_PER_PAGE;

	// Home.
	$routes[] = array(
		'path'  => '/',
		'file'  => 'index.html',
		'index' => true,
	);

	// Search landing page. Term routes are served by the same file.
	$routes[] = array(
		'path'  => '/search/',
		'file'  => 'search/index.html',
		'index' => false,
	);

	// Posts.
	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		)
	);

	foreach ( $posts as $post ) {
		$path = lm_export_path( get_permalink( $post ) );

		if ( '' === $path ) {
			continue;
		}

		$routes[] = array(
			'path'    => $path,
			'file'    => trim( $path, '/' ) . '/index.html',
			'index'   => true,
			'lastmod' => get_post_modified_time( 'c', true, $post ),
		);
	}

	// Pages.
	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		)
	);

	foreach ( $pages as $page ) {
		$path = lm_export_path( get_permalink( $page ) );

		if ( '' === $path ) {
			continue;
		}

		$routes[] = array(
			'path'    => $path,
			'file'    => trim( $path, '/' ) . '/index.html',
			'index'   => true,
			'lastmod' => get_post_modified_time( 'c', true, $page ),
		);
	}

	/*
	 * Category and tag archives, with every page of each.
	 *
	 * get_categories() hides empty terms by default, which would drop archives
	 * the navigation links to. The default category is excluded instead: it is
	 * WordPress's own bucket rather than a section anyone navigates to.
	 */
	$default_category = (int) get_option( 'default_category' );

	$taxonomies = array(
		'category' => get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'exclude'    => array( $default_category ),
			)
		),
		'post_tag' => get_terms(
			array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => false,
			)
		),
	);

	foreach ( $taxonomies as $taxonomy => $terms ) {
		foreach ( $terms as $term ) {
			$link = 'category' === $taxonomy ? get_category_link( $term ) : get_tag_link( $term );
			$path = lm_export_path( $link );

			if ( '' === $path ) {
				continue;
			}

			$routes[] = array(
				'path'  => $path,
				'file'  => trim( $path, '/' ) . '/index.html',
				'index' => (int) $term->count > 0,
			);

			$pages_of_term = (int) ceil( $term->count / $per_page );

			for ( $number = 2; $number <= $pages_of_term; $number++ ) {
				$routes[] = array(
					'path'  => $path . 'page/' . $number . '/',
					'file'  => trim( $path, '/' ) . '/page/' . $number . '/index.html',
					'index' => true,
				);
			}
		}
	}

	foreach ( get_users( array( 'who' => 'author' ) ) as $author ) {
		$path = lm_export_path( get_author_posts_url( $author->ID ) );

		if ( '' === $path || 0 === (int) $author->post_count ) {
			continue;
		}

		$routes[] = array(
			'path'  => $path,
			'file'  => trim( $path, '/' ) . '/index.html',
			'index' => true,
		);

		$pages_of_author = (int) ceil( $author->post_count / $per_page );

		for ( $number = 2; $number <= $pages_of_author; $number++ ) {
			$routes[] = array(
				'path'  => $path . 'page/' . $number . '/',
				'file'  => trim( $path, '/' ) . '/page/' . $number . '/index.html',
				'index' => true,
			);
		}
	}

	return $routes;
}

/**
 * Every value of an attribute that can carry a URL.
 *
 * Both quote styles have to be handled. WordPress prints its own enqueued
 * assets with single quotes — <link rel='stylesheet' href='...' /> — while theme
 * markup prints double quotes. Matching only double quotes silently drops every
 * stylesheet from the export while still reporting success.
 *
 * @param string $html Rewritten markup.
 * @return string[] Attribute values.
 */
function lm_export_attr_values( $html ) {
	preg_match_all(
		'/\b(?:src|href|data-src|data-srcset|srcset|poster|content)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i',
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
 * Asset URLs referenced by a page.
 *
 * Reads src, href, srcset and CSS url() so that a derivative named only in a
 * srcset is copied too. Query strings and fragments are dropped, and only the
 * theme and uploads trees are considered.
 *
 * @param string $html Rewritten markup.
 * @return string[] Root-relative asset paths.
 */
function lm_export_assets_in( $html ) {
	$found = array();

	// content= picks up og:image and twitter:image, which may name a size no
	// visible <img> uses.
	preg_match_all( '/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', $html, $css_matches );
	preg_match_all( '#"(/(?:theme|uploads)/[^"]+)"#', $html, $json_matches );

	$candidates = array_merge(
		lm_export_attr_values( $html ),
		$css_matches[1],
		$json_matches[1]
	);

	foreach ( $candidates as $candidate ) {
		// srcset packs several candidates into one attribute, comma separated.
		foreach ( explode( ',', $candidate ) as $url ) {
			$url = preg_replace( '/^\s+|\s+$/', '', $url );
			$url = preg_split( '/\s/', $url )[0];

			if ( ! $url ) {
				continue;
			}

			$url = preg_replace( '/[?#].*$/', '', $url );

			if ( 0 === strpos( $url, LM_THEME_DEST ) || 0 === strpos( $url, LM_UPLOAD_DEST ) ) {
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
 * @param string $relative Reference as written in the CSS, e.g. ../fonts/x.woff2.
 * @return string Root-relative asset path, or an empty string when it escapes the root.
 */
function lm_export_resolve_css_url( $css_url, $relative ) {
	// Absolute and protocol-relative references are not ours to rewrite.
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

	$path = '/' . implode( '/', $segments );

	// Refuse anything that climbed above the site root.
	if ( false !== strpos( $path, '..' ) ) {
		return '';
	}

	return $path;
}

/**
 * Copy a stylesheet's own dependencies into the export.
 *
 * A stylesheet refers to its fonts and images with paths relative to itself, so
 * nothing in the markup points at them. Without this pass the fonts are simply
 * absent and the page renders in a fallback face.
 *
 * @param string   $css_url  Root-relative URL of the copied stylesheet.
 * @param string[] $assets   Tracking array, by reference.
 * @param string[] $failures Failure log, by reference.
 * @return void
 */
function lm_export_copy_css_dependencies( $css_url, &$assets, &$failures ) {
	preg_match_all( '/url\(\s*[\'"]?([^\'")]+)[\'"]?\s*\)/i', (string) file_get_contents( LM_DIST . $css_url ), $matches );

	foreach ( $matches[1] as $reference ) {
		$resolved = lm_export_resolve_css_url( $css_url, $reference );

		if ( ! $resolved ) {
			continue;
		}

		lm_export_copy_asset( $resolved, $assets, $failures );
	}
}

/**
 * Copy every asset a page references.
 *
 * @param string[] $urls     Root-relative asset paths.
 * @param string[] $assets   Tracking array, by reference.
 * @param string[] $failures Failure log, by reference.
 * @return void
 */
function lm_export_copy_assets( $urls, &$assets, &$failures ) {
	foreach ( $urls as $url ) {
		if ( lm_export_copy_asset( $url, $assets, $failures ) && '.css' === strtolower( substr( $url, -4 ) ) ) {
			lm_export_copy_css_dependencies( $url, $assets, $failures );
		}
	}
}

/**
 * Copy one referenced asset into the export.
 *
 * A referenced file that cannot be copied is recorded rather than ignored: an
 * asset the page asks for and the build does not deliver is a broken page, and
 * it has to be visible in the export summary.
 *
 * @param string   $url      Root-relative asset path.
 * @param bool     $seen     Tracking array, by reference.
 * @param string[] $failures Failure log, by reference.
 * @return bool True when the file exists in the export afterwards.
 */
function lm_export_copy_asset( $url, &$seen, &$failures = array() ) {
	if ( isset( $seen[ $url ] ) ) {
		return true;
	}

	$upload_dir = wp_upload_dir();

	if ( 0 === strpos( $url, LM_THEME_DEST ) ) {
		$relative = substr( $url, strlen( LM_THEME_DEST ) );
		$source   = get_template_directory() . '/' . $relative;
	} elseif ( 0 === strpos( $url, LM_UPLOAD_DEST ) ) {
		$relative = substr( $url, strlen( LM_UPLOAD_DEST ) );
		$source   = $upload_dir['basedir'] . '/' . $relative;
	} else {
		return false;
	}

	// Refuse anything that climbs out of its tree.
	if ( false !== strpos( $relative, '../' ) ) {
		$failures[] = 'refused unsafe asset path ' . $url;
		return false;
	}

	if ( ! is_file( $source ) ) {
		$failures[] = 'missing source file for ' . $url;
		return false;
	}

	$target = LM_DIST . $url;

	if ( ! is_dir( dirname( $target ) ) && ! wp_mkdir_p( dirname( $target ) ) ) {
		$failures[] = 'could not create ' . dirname( $url );
		return false;
	}

	if ( ! copy( $source, $target ) ) {
		$failures[] = 'could not copy ' . $url;
		return false;
	}

	$seen[ $url ] = true;

	return true;
}

/**
 * Payload for client-side search.
 *
 * Field names match what assets/js/search.js reads. The thumbnail is the card
 * size rather than the full image, since results never render larger.
 *
 * @return array<int, array<string, mixed>>
 */
function lm_export_search_index() {
	$entries = array();

	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		)
	);

	foreach ( $posts as $post ) {
		$thumb_id  = (int) get_post_thumbnail_id( $post->ID );
		$thumbnail = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'lm-card-md' ) : '';

		$categories = get_the_category( $post->ID );
		$tags       = get_the_tags( $post->ID );
		$tag_names  = array();

		if ( $tags ) {
			foreach ( $tags as $tag ) {
				$tag_names[] = $tag->name;
			}
		}

		$entries[] = array(
			'title'       => get_the_title( $post ),
			'url'         => lm_export_path( get_permalink( $post ) ),
			'excerpt'     => wp_strip_all_tags( get_the_excerpt( $post ) ),
			'thumbnail'   => $thumbnail ? lm_export_rewrite( $thumbnail ) : '',
			'category'    => $categories ? $categories[0]->name : '',
			'tags'        => $tag_names,
			'date'        => get_the_date( 'c', $post ),
			'dateLabel'   => get_the_date( 'j F Y', $post ),
			'readTime'    => lazymedia_read_time( $post->ID ),
			'isVideo'     => (bool) get_post_meta( $post->ID, '_lm_featured_video', true ),
		);
	}

	return $entries;
}

/**
 * Make sure the export directory exists.
 *
 * The directory is emptied by scripts/export.ps1 on the host, not here: dist/ is
 * a bind mount on Windows, and inside the container a recursive delete can only
 * see the files written during the current run, so it leaves earlier output
 * behind. Host-side deletion sees everything.
 *
 * @return void
 */
function lm_export_prepare() {
	if ( ! is_dir( LM_DIST ) && ! wp_mkdir_p( LM_DIST ) ) {
		throw new RuntimeException( 'could not create ' . LM_DIST );
	}
}

/**
 * Write a file inside the export, creating directories as needed.
 *
 * @param string $relative Path relative to LM_DIST.
 * @param string $contents File contents.
 * @return void
 * @throws RuntimeException When the file cannot be written.
 */
function lm_export_write( $relative, $contents ) {
	$path = LM_DIST . '/' . ltrim( $relative, '/' );

	if ( ! is_dir( dirname( $path ) ) && ! wp_mkdir_p( dirname( $path ) ) ) {
		throw new RuntimeException( 'could not create ' . dirname( $path ) );
	}

	if ( false === file_put_contents( $path, $contents ) ) {
		throw new RuntimeException( 'could not write ' . $path );
	}
}

// --- Export ------------------------------------------------------------------

lm_export_prepare();

$routes    = lm_export_routes();
$assets    = array();
$written   = 0;
$failures  = array();

foreach ( $routes as $route ) {
	try {
		// Rewrite first: asset collection recognises the root-relative theme and
		// uploads paths that the rewrite produces.
		$html = lm_export_rewrite( lm_export_fetch( $route['path'] ) );

		lm_export_copy_assets( lm_export_assets_in( $html ), $assets, $failures );

		lm_export_write( $route['file'], lm_export_absolutize( $html ) );
		++$written;
	} catch ( Exception $e ) {
		$failures[] = $route['path'] . ' — ' . $e->getMessage();
	}
}

// 404: any unknown path renders the not-found template.
try {
	$html = lm_export_rewrite( lm_export_fetch( '/lm-export-not-a-real-page/', 404 ) );

	lm_export_copy_assets( lm_export_assets_in( $html ), $assets, $failures );

	lm_export_write( '404.html', $html );
	++$written;
} catch ( Exception $e ) {
	$failures[] = '404 — ' . $e->getMessage();
}

// Search index. Written unslashed because the client parses it as JSON.
$index = lm_export_search_index();
lm_export_write(
	'search-index.json',
	json_encode( $index, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
);

lm_export_copy_assets(
	lm_export_assets_in( (string) file_get_contents( LM_DIST . '/search-index.json' ) ),
	$assets,
	$failures
);

// Sitemap and robots. Routes flagged as not indexable — the search page and any
// empty archive — stay out so a crawler is never sent to a page with no posts.
$sitemap = array( '<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' );

foreach ( $routes as $route ) {
	if ( empty( $route['index'] ) ) {
		continue;
	}

	$sitemap[] = '  <url>';
	$sitemap[] = '    <loc>' . esc_xml( lm_export_absolute( $route['path'] ) ) . '</loc>';

	if ( ! empty( $route['lastmod'] ) ) {
		$sitemap[] = '    <lastmod>' . $route['lastmod'] . '</lastmod>';
	}

	$sitemap[] = '  </url>';
}

$sitemap[] = '</urlset>';

lm_export_write( 'sitemap.xml', implode( "\n", $sitemap ) . "\n" );

lm_export_write(
	'robots.txt',
	"User-agent: *\nAllow: /\n\nSitemap: " . lm_export_absolute( '/sitemap.xml' ) . "\n"
);

echo 'export: ' . $written . ' pages, ' . count( $assets ) . ' assets, ' . count( $index ) . " search entries\n";

if ( $failures ) {
	echo "\nfailed routes and assets:\n";

	foreach ( $failures as $failure ) {
		echo "  - {$failure}\n";
	}
}

echo 'output: ' . lm_export_public_origin() . ' -> /dist' . "\n";

if ( lm_export_site_origin() ) {
	echo 'public origin: ' . lm_export_site_origin() . "\n";
} else {
	echo "warning: LM_PUBLIC_URL is not set; canonical, Open Graph and sitemap URLs stay relative\n";
}

/*
 * A failed copy leaves the page pointing at something the build never delivered,
 * so the export is not a deliverable. Exit non-zero and let scripts/export.ps1
 * stop here rather than reporting a green build.
 */
if ( $failures ) {
	exit( 1 );
}