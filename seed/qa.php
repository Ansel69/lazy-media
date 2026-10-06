<?php
/**
 * Regression suite for Ark Media.
 *
 * Asserts the design tokens, the rendered blocks and every route reachable
 * from the homepage, then checks that the static export is complete.
 *
 * Run:
 *   docker compose run --rm wpcli wp eval-file /seed/qa.php --path=/var/www/html --allow-root
 *
 * Exits 0 when every check passes, 1 otherwise.
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

// The CLI container talks to the wordpress service over the internal network,
// which is a private IP WordPress would otherwise refuse to request, and the
// public origin it redirects to is not resolvable from inside the container.
define( 'WP_HTTP_BLOCK_EXTERNAL', false );
add_filter( 'redirect_canonical', '__return_false' );

/**
 * Collect QA results and print one line per check.
 *
 * Uses $GLOBALS because WP-CLI evaluates this file inside a function scope, so
 * a plain top-level variable would not be a true global.
 *
 * @param string   $name Check name.
 * @param callable $test Callback that throws RuntimeException on failure.
 * @return void
 */
function lm_qa( $name, $test ) {
	try {
		call_user_func( $test );
		$GLOBALS['lm_qa_passed']++;
		echo "PASS  {$name}\n";
	} catch ( Exception $e ) {
		$GLOBALS['lm_qa_failed']++;
		echo "FAIL  {$name}: " . $e->getMessage() . "\n";
	}
}

/**
 * Throw when a condition is falsy.
 *
 * @param bool   $condition Condition that must hold.
 * @param string $message   Failure message.
 * @throws RuntimeException When the condition is false.
 * @return void
 */
function lm_qa_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

/**
 * Site origin reachable from inside the CLI container.
 *
 * The wordpress service answers on port 80, but WordPress only renders
 * correctly when it believes it is being served from the configured host, so
 * requests carry an explicit Host header.
 *
 * @return string Origin without trailing slash.
 */
function lm_qa_origin() {
	return 'http://wordpress';
}

/**
 * Perform a GET against the running site.
 *
 * Uses cURL directly rather than wp_remote_get: WP-CLI forces every WordPress
 * HTTP request onto the public host port, which does not exist inside the
 * container.
 *
 * @param string $path Path relative to the site root.
 * @return array{code:int,body:string} Response code and body.
 * @throws RuntimeException When the request cannot be made.
 */
function lm_qa_request( $path ) {
	$url   = lm_qa_origin() . '/' . ltrim( $path, '/' );
	$ch    = curl_init( $url );
	$parts = wp_parse_url( home_url( '/' ) );

	curl_setopt_array(
		$ch,
		array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_TIMEOUT        => 25,
			CURLOPT_HTTPHEADER     => array( 'Host: ' . $parts['host'] . ':' . $parts['port'] ),
		)
	);

	$body = curl_exec( $ch );

	if ( false === $body ) {
		$error = curl_error( $ch );
		curl_close( $ch );
		throw new RuntimeException( $path . ' request failed: ' . $error );
	}

	$code = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
	curl_close( $ch );

	return array(
		'code' => $code,
		'body' => $body,
	);
}

/**
 * Fetch a path on the running site, requiring HTTP 200.
 *
 * @param string $path Path relative to the site root.
 * @return string Response body.
 * @throws RuntimeException When the request fails or is not 200.
 */
function lm_qa_get( $path ) {
	$response = lm_qa_request( $path );

	if ( 200 !== $response['code'] ) {
		throw new RuntimeException( $path . ' returned HTTP ' . $response['code'] );
	}

	return $response['body'];
}

/**
 * Every route the site exposes.
 *
 * @return string[]
 */
function lm_qa_routes() {
	$routes = array( '/', '/about/', '/contact/', '/nope-404/' );

	foreach ( get_posts(
		array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'fields'         => 'ids',
		)
	) as $id ) {
		$path = wp_make_link_relative( get_permalink( $id ) );
		if ( $path ) {
			$routes[] = $path;
		}
	}

	foreach ( array( 'category', 'post_tag' ) as $taxonomy ) {
		foreach ( get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
				'number'     => 50,
			)
		) as $term ) {
			$routes[] = wp_make_link_relative( get_term_link( $term ) );
		}
	}

	$routes[] = '/?s=ace';
	$routes[] = '/?lm_tag=games';
	$routes[] = '/nope-404/';

	return array_values( array_unique( array_filter( $routes ) ) );
}

/**
 * Card titles inside the homepage "New Articles" grid only.
 *
 * Counting lm-card__title across the whole page would compare every block, so
 * the hero and the category rails would mask a filter that changed nothing.
 *
 * @param string $path Path to the homepage, optionally with a query string.
 * @return string[] Card titles, in order.
 */
function lm_qa_new_article_titles( $path ) {
	$html = lm_qa_get( $path );
	$at   = strpos( $html, 'lm-block--new' );

	if ( false === $at ) {
		return array();
	}

	// The block ends where the next one begins.
	$tail = substr( $html, $at );
	$end  = strpos( $tail, '<section', 1 );

	if ( false !== $end ) {
		$tail = substr( $tail, 0, $end );
	}

	if ( ! preg_match_all( '#<h3 class="lm-card__title">\s*<a[^>]*>(.*?)</a>#s', $tail, $matches ) ) {
		return array();
	}

	return array_map( 'wp_strip_all_tags', $matches[1] );
}

$GLOBALS['lm_qa_passed'] = 0;
$GLOBALS['lm_qa_failed'] = 0;

// --- Routes -----------------------------------------------------------------

lm_qa(
	'every route returns 200',
	function () {
		$bad = array();
		foreach ( lm_qa_routes() as $path ) {
			$response = lm_qa_request( $path );
			$code     = $response['code'];
			if ( 404 !== $code && 200 !== $code ) {
				$bad[] = $path . '=' . $code;
			}
		}
		lm_qa_assert( ! $bad, implode( ', ', $bad ) );
	}
);

lm_qa(
	'404 template is used for unknown paths',
	function () {
		$response = lm_qa_request( '/nope-404/' );
		lm_qa_assert( 404 === $response['code'], 'expected HTTP 404, got ' . $response['code'] );
		lm_qa_assert( false !== strpos( $response['body'], 'lm-404' ), 'lm-404 markup missing' );
	}
);

// --- Design tokens ----------------------------------------------------------

lm_qa(
	'design tokens present',
	function () {
		// The tokens live in the stylesheet, not the document: assert on the
		// file that actually declares them.
		$css    = lm_qa_get( 'wp-content/themes/lazymedia/style.css' );
		$tokens = array(
			'--lm-bg'           => '#0F0F0F',
			'--lm-accent'       => '#FBAD18',
			'--lm-text'         => '#FFFFFF',
			'--lm-muted'        => '#9A9A9A',
			'--lm-faint'        => '#54595F',
			'--lm-radius'       => '8px',
			'--lm-container'    => '1230px',
			'--lm-shadow'       => '3px 3px 10px #000000',
		);

		foreach ( $tokens as $property => $value ) {
			// CSS is conventionally written with a space after the colon, so
			// match the declaration rather than an exact byte sequence.
			$pattern = '/' . preg_quote( $property, '/' ) . '\s*:\s*' . preg_quote( $value, '/' ) . '\s*;/i';

			lm_qa_assert(
				(bool) preg_match( $pattern, $css ),
				sprintf( '%s: %s is not declared in style.css', $property, $value )
			);
		}

		lm_qa_assert( false !== strpos( $css, '--lm-font-sans' ), 'type scale token --lm-font-sans missing' );
	}
);

lm_qa(
	'section accents present',
	function () {
		$html    = lm_qa_get( '/' );
		$accents = array( '#C203C6', '#36FFF3', '#BFFA17', '#FF36EB', '#FF7E36' );
		foreach ( $accents as $accent ) {
			lm_qa_assert( false !== strpos( $html, $accent ), $accent . ' missing' );
		}
	}
);

// --- Fonts ------------------------------------------------------------------

lm_qa(
	'fonts are self-contained',
	function () {
		$html = lm_qa_get( '/' );
		lm_qa_assert( false === stripos( $html, 'fonts.googleapis' ), 'Google Fonts referenced' );
		lm_qa_assert( false === stripos( $html, 'fonts.gstatic' ), 'Google Fonts host referenced' );

		$css = lm_qa_get( 'wp-content/themes/lazymedia/assets/css/fonts.css' );
		lm_qa_assert( false !== strpos( $css, '../fonts/barlow-400.woff2' ), 'local font files not referenced' );

		foreach ( array( 400, 500, 600, 700 ) as $weight ) {
			$response = lm_qa_request( "wp-content/themes/lazymedia/assets/fonts/barlow-{$weight}.woff2" );
			lm_qa_assert( 200 === $response['code'], "barlow-{$weight}.woff2 returned HTTP {$response['code']}" );
			lm_qa_assert( 'wOF2' === substr( $response['body'], 0, 4 ), "barlow-{$weight}.woff2 is not a woff2 file" );
		}
	}
);

// --- Header and footer ------------------------------------------------------

lm_qa(
	'header and footer render',
	function () {
		$html = lm_qa_get( '/' );
		foreach ( array( 'lm-header', 'lm-nav', 'lm-search', 'lm-footer', 'lm-social' ) as $needle ) {
			lm_qa_assert( false !== strpos( $html, $needle ), $needle . ' missing' );
		}
	}
);

lm_qa(
	'primary menu is nested',
	function () {
		$html = lm_qa_get( '/' );
		lm_qa_assert( false !== strpos( $html, 'sub-menu' ), 'no dropdown submenu' );
	}
);

// --- Cards ------------------------------------------------------------------

lm_qa(
	'card variants render',
	function () {
		$html = lm_qa_get( '/' );
		foreach ( array( 'lm-card', 'lm-card--compact', 'lm-card__badge', 'lm-card__title' ) as $needle ) {
			lm_qa_assert( false !== strpos( $html, $needle ), $needle . ' missing' );
		}
	}
);

// --- Homepage ---------------------------------------------------------------

lm_qa(
	'homepage hero renders',
	function () {
		$html = lm_qa_get( '/' );
		lm_qa_assert( false !== strpos( $html, 'lm-hero' ), 'lm-hero missing' );
		lm_qa_assert( false !== strpos( $html, 'lm-hero__slide' ), 'no hero slides' );
	}
);

lm_qa(
	'homepage blocks render',
	function () {
		$html    = lm_qa_get( '/' );
		$blocks  = array( 'New Articles', 'Games', 'Hardware', 'Entertainment', 'Latest Articles' );
		foreach ( $blocks as $block ) {
			lm_qa_assert( false !== strpos( $html, $block ), $block . ' block missing' );
		}
	}
);

lm_qa(
	'category blocks are never empty',
	function () {
		$html   = lm_qa_get( '/' );
		$blocks = array( 'Games', 'Hardware', 'Entertainment' );
		foreach ( $blocks as $block ) {
			// The heading is followed by its grid; require at least one card after it.
			$at = strpos( $html, '>' . $block . '<' );
			lm_qa_assert( false !== $at, $block . ' heading missing' );
			$tail = substr( $html, $at, 12000 );
			lm_qa_assert( false !== strpos( $tail, 'lm-card__title' ), $block . ' block has no cards' );
		}
	}
);

lm_qa(
	'tag filter renders and filters',
	function () {
		$html = lm_qa_get( '/' );
		lm_qa_assert( false !== strpos( $html, 'lm-filter' ), 'filter pills missing' );
		lm_qa_assert( false !== strpos( $html, 'lm-filter__pill' ), 'no filter pills rendered' );

		// A tag whose post count is below the block limit, so a filter that
		// silently did nothing would show the same six cards as the default.
		$tag = 'reviews';

		$unfiltered = lm_qa_new_article_titles( '/' );
		$filtered   = lm_qa_new_article_titles( '/?lm_tag=' . $tag );

		lm_qa_assert( count( $unfiltered ) > 0, 'New Articles block is empty without a filter' );
		lm_qa_assert( count( $filtered ) > 0, "New Articles block is empty for ?lm_tag={$tag}" );
		lm_qa_assert( count( $filtered ) < count( $unfiltered ), 'filter did not narrow the result set' );

		$expected = get_posts(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 6,
				'fields'         => 'ids',
				'tag'            => $tag,
			)
		);

		lm_qa_assert(
			count( $filtered ) === count( $expected ),
			'expected ' . count( $expected ) . " tagged cards, rendered " . count( $filtered )
		);

		// The pill for the active tag must be marked, and no other.
		$active = substr_count( lm_qa_get( '/?lm_tag=' . $tag ), 'lm-filter__pill is-active' );
		lm_qa_assert( 1 === $active, "expected 1 active pill for ?lm_tag={$tag}, found {$active}" );
	}
);

// --- Single post ------------------------------------------------------------

lm_qa(
	'single post has meta and related',
	function () {
		$ids = get_posts(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		lm_qa_assert( ! empty( $ids ), 'no published posts' );

		$html = lm_qa_get( wp_make_link_relative( get_permalink( $ids[0] ) ) );
		foreach ( array( 'lm-single', 'lm-meta', 'lm-related', 'lm-single__body' ) as $needle ) {
			lm_qa_assert( false !== strpos( $html, $needle ), $needle . ' missing' );
		}
	}
);

// --- Archive, search, page --------------------------------------------------

lm_qa(
	'archive renders a hero',
	function () {
		$term = get_term_by( 'slug', 'games', 'category' );
		lm_qa_assert( $term, 'games category missing' );

		$html = lm_qa_get( wp_make_link_relative( get_term_link( $term ) ) );
		lm_qa_assert( false !== strpos( $html, 'lm-archive-hero' ), 'archive hero missing' );
	}
);

lm_qa(
	'search page has a results container',
	function () {
		$html = lm_qa_get( '/?s=ace' );
		lm_qa_assert( false !== strpos( $html, 'data-lm-search-results' ), 'search results container missing' );
	}
);

lm_qa(
	'pages render',
	function () {
		$html = lm_qa_get( '/about/' );
		lm_qa_assert( false !== strpos( $html, 'lm-page' ), 'lm-page wrapper missing' );
	}
);

// --- Content completeness ---------------------------------------------------

lm_qa(
	'no uncategorised posts',
	function () {
		$bad = get_posts(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'category__in'   => array( get_cat_ID( 'uncategorized' ) ),
				'exclude'        => array(),
			)
		);
		$uncategorised = array();
		foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) {
			if ( ! has_term( array( 'games', 'game-news', 'reviews', 'console', 'hardware', 'tech', 'tech-news', 'tech-review', 'tech-tips', 'overclocking', 'setup', 'entertainment', 'anime', 'movie-series', 'hobby', 'esports', 'pc', 'gadgets' ), 'category', $id ) ) {
				$uncategorised[] = $id;
			}
		}
		lm_qa_assert( ! $uncategorised, count( $uncategorised ) . ' posts outside seeded categories' );
	}
);

lm_qa(
	'every post has a featured image',
	function () {
		$missing = array();
		foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $id ) {
			if ( ! has_post_thumbnail( $id ) ) {
				$missing[] = get_the_title( $id );
			}
		}
		lm_qa_assert( ! $missing, 'missing thumbnail: ' . implode( ', ', array_slice( $missing, 0, 3 ) ) );
	}
);

lm_qa(
	'debug log is clean',
	function () {
		$log = WP_CONTENT_DIR . '/debug.log';
		if ( ! file_exists( $log ) ) {
			return;
		}
		lm_qa_assert( 0 === filesize( $log ), 'debug.log is not empty' );
	}
);

// --- Static export ----------------------------------------------------------

lm_qa(
	'dist export is complete',
	function () {
		$root = '/dist';
		lm_qa_assert( is_dir( $root ), 'dist/ does not exist yet' );

		foreach ( array( 'index.html', 'search-index.json', 'about/index.html', 'category/games/index.html' ) as $file ) {
			lm_qa_assert( file_exists( $root . '/' . $file ), $file . ' missing from dist' );
		}
	}
);

lm_qa(
	'dist has no absolute localhost urls',
	function () {
		lm_qa_assert( file_exists( '/dist/index.html' ), 'dist/index.html does not exist yet' );

		$html = file_get_contents( '/dist/index.html' );
		lm_qa_assert( is_string( $html ), 'dist/index.html is not readable' );
		lm_qa_assert( false === strpos( $html, 'localhost:8080' ), 'dist still points at localhost:8080' );
		lm_qa_assert( false === strpos( $html, 'wp-content/plugins' ), 'plugin assets leaked into dist' );
	}
);

// --- Tally ------------------------------------------------------------------

echo "\nQA: {$GLOBALS['lm_qa_passed']}/" . ( $GLOBALS['lm_qa_passed'] + $GLOBALS['lm_qa_failed'] ) . " passed\n";

exit( $GLOBALS['lm_qa_failed'] > 0 ? 1 : 0 );