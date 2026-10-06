<?php
/**
 * Search and social metadata: description, canonical, Open Graph, Twitter
 * cards, and schema.org JSON-LD.
 *
 * Every URL here is printed absolute against home_url(). The static exporter
 * rewrites the local origin away and then re-prefixes these specific URLs with
 * the public origin (LM_PUBLIC_URL), because crawlers and link unfurlers reject
 * relative canonical, og:url and og:image values.
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Width and height of the fallback share image.
 */
const LAZYMEDIA_OG_FALLBACK_SIZE = array( 1200, 630 );

/**
 * Language the articles are written in, as a BCP 47 tag.
 *
 * Independent of the WordPress locale: the admin may run in English while the
 * content is Indonesian, and crawlers must be told the content language.
 *
 * @return string
 */
function lazymedia_content_language() {
	return (string) apply_filters( 'lazymedia_content_language', 'id-ID' );
}

/**
 * Put the content language on the <html> element.
 *
 * @param string $output Attributes WordPress built.
 * @return string
 */
function lazymedia_language_attributes( $output ) {
	return preg_replace( '/\blang="[^"]*"/', 'lang="' . esc_attr( lazymedia_content_language() ) . '"', $output );
}
add_filter( 'language_attributes', 'lazymedia_language_attributes' );

/**
 * Turn arbitrary markup into a single-line description of at most $limit chars.
 *
 * Cuts on a word boundary and appends an ellipsis only when something was cut.
 *
 * @param string $text  Raw text or HTML.
 * @param int    $limit Maximum length in characters.
 * @return string
 */
function lazymedia_seo_clean_text( $text, $limit = 160 ) {
	$text = wp_strip_all_tags( strip_shortcodes( (string) $text ), true );
	$text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) );

	if ( mb_strlen( $text ) <= $limit ) {
		return $text;
	}

	$cut   = mb_substr( $text, 0, $limit - 1 );
	$space = mb_strrpos( $cut, ' ' );

	if ( false !== $space && $space > $limit * 0.6 ) {
		$cut = mb_substr( $cut, 0, $space );
	}

	return rtrim( $cut, " ,.;:-–—" ) . '…';
}

/**
 * Share image for an attachment, sized for link previews.
 *
 * @param int $attachment_id Attachment ID.
 * @return array{url:string,width:int,height:int,alt:string}|null
 */
function lazymedia_seo_attachment_image( $attachment_id ) {
	if ( ! $attachment_id ) {
		return null;
	}

	// lm-card is 1200x675: the size social networks render at full width.
	$src = wp_get_attachment_image_src( $attachment_id, 'lm-card' );

	if ( ! $src ) {
		return null;
	}

	$alt = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

	return array(
		'url'    => $src[0],
		'width'  => (int) $src[1],
		'height' => (int) $src[2],
		'alt'    => $alt,
	);
}

/**
 * Share image used when a page has no photo of its own.
 *
 * @return array{url:string,width:int,height:int,alt:string}
 */
function lazymedia_seo_fallback_image() {
	return array(
		'url'    => get_template_directory_uri() . '/assets/img/og-default.jpg',
		'width'  => LAZYMEDIA_OG_FALLBACK_SIZE[0],
		'height' => LAZYMEDIA_OG_FALLBACK_SIZE[1],
		'alt'    => get_bloginfo( 'name' ),
	);
}

/**
 * Image of the newest post matching a query, for listing pages.
 *
 * A category page previewed with its own lead photo is far more clickable than
 * a generic brand card.
 *
 * @param array $args Extra WP_Query arguments.
 * @return array{url:string,width:int,height:int,alt:string}|null
 */
function lazymedia_seo_latest_image( $args = array() ) {
	$posts = get_posts(
		array_merge(
			array(
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'meta_key'       => '_thumbnail_id', // phpcs:ignore WordPress.DB.SlowDBQuery
				'fields'         => 'ids',
				'no_found_rows'  => true,
			),
			$args
		)
	);

	return $posts ? lazymedia_seo_attachment_image( (int) get_post_thumbnail_id( $posts[0] ) ) : null;
}

/**
 * Everything the head needs to describe the current request.
 *
 * Computed once per request and shared by the meta tags and the JSON-LD.
 *
 * @return array{
 *   title:string,
 *   description:string,
 *   url:string,
 *   type:string,
 *   image:array{url:string,width:int,height:int,alt:string},
 *   article:array<string,mixed>|null,
 *   crumbs:array<int,array{name:string,url:string}>
 * }|null Null when the page should carry no social metadata (404).
 */
function lazymedia_seo_context() {
	static $context = false;

	if ( false !== $context ) {
		return $context;
	}

	$context = null;

	if ( is_404() ) {
		return $context;
	}

	$site    = get_bloginfo( 'name' );
	$home    = home_url( '/' );
	$tagline = get_bloginfo( 'description' );
	$paged   = max( 1, (int) get_query_var( 'paged' ) );
	$image   = null;
	$article = null;
	$crumbs  = array(
		array(
			'name' => __( 'Beranda', 'lazymedia' ),
			'url'  => $home,
		),
	);

	$title       = wp_get_document_title();
	$description = $tagline;
	$url         = $home;
	$type        = 'website';

	if ( is_singular() ) {
		$post        = get_queried_object();
		$url         = get_permalink( $post );
		$type        = is_singular( 'post' ) ? 'article' : 'website';
		$title       = get_the_title( $post );
		$description = has_excerpt( $post )
			? lazymedia_seo_clean_text( $post->post_excerpt )
			: lazymedia_seo_clean_text( $post->post_content );
		$image       = lazymedia_seo_attachment_image( (int) get_post_thumbnail_id( $post ) );

		if ( is_singular( 'post' ) ) {
			$categories = get_the_category( $post->ID );
			$tags       = get_the_tags( $post->ID );
			$primary    = $categories ? $categories[0] : null;

			if ( $primary ) {
				$crumbs[] = array(
					'name' => $primary->name,
					'url'  => get_category_link( $primary ),
				);
			}

			$article = array(
				'published' => get_post_time( 'c', true, $post ),
				'modified'  => get_post_modified_time( 'c', true, $post ),
				'author'    => get_the_author_meta( 'display_name', $post->post_author ),
				'authorUrl' => get_author_posts_url( $post->post_author ),
				'section'   => $primary ? $primary->name : '',
				'tags'      => $tags ? wp_list_pluck( $tags, 'name' ) : array(),
				'words'     => str_word_count( wp_strip_all_tags( $post->post_content ) ),
			);
		}

		$crumbs[] = array(
			'name' => $title,
			'url'  => $url,
		);
	} elseif ( is_category() || is_tag() ) {
		$term        = get_queried_object();
		$url         = get_term_link( $term );
		$title       = single_term_title( '', false );
		$description = term_description( $term )
			? lazymedia_seo_clean_text( term_description( $term ) )
			/* translators: 1: term name, 2: site name. */
			: sprintf( __( 'Kumpulan artikel %1$s terbaru di %2$s.', 'lazymedia' ), $title, $site );
		$image       = lazymedia_seo_latest_image(
			array(
				'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'taxonomy' => $term->taxonomy,
						'terms'    => $term->term_id,
					),
				),
			)
		);

		$crumbs[] = array(
			'name' => $title,
			'url'  => $url,
		);
	} elseif ( is_author() ) {
		$author      = get_queried_object();
		$url         = get_author_posts_url( $author->ID );
		$title       = $author->display_name;
		$description = get_the_author_meta( 'description', $author->ID )
			? lazymedia_seo_clean_text( get_the_author_meta( 'description', $author->ID ) )
			/* translators: 1: author name, 2: site name. */
			: sprintf( __( 'Semua tulisan %1$s di %2$s.', 'lazymedia' ), $title, $site );
		$image       = lazymedia_seo_latest_image( array( 'author' => $author->ID ) );

		$crumbs[] = array(
			'name' => $title,
			'url'  => $url,
		);
	} elseif ( is_search() ) {
		$url         = home_url( '/search/' );
		/* translators: %s: site name. */
		$description = sprintf( __( 'Cari artikel, review, dan berita di %s.', 'lazymedia' ), $site );
	} elseif ( is_front_page() || is_home() ) {
		$title = $site;
		$image = lazymedia_seo_latest_image();
	}

	if ( $paged > 1 && ! is_singular() && ! is_search() ) {
		$url = trailingslashit( $url ) . 'page/' . $paged . '/';
	}

	$context = array(
		'title'       => $title,
		'description' => $description ? $description : $tagline,
		'url'         => $url,
		'type'        => $type,
		'image'       => $image ? $image : lazymedia_seo_fallback_image(),
		'article'     => $article,
		'crumbs'      => $crumbs,
	);

	return $context;
}

/**
 * Print one meta tag, skipping empty values.
 *
 * @param string $attr    Either "name" or "property".
 * @param string $key     Meta key.
 * @param string $content Value.
 * @return void
 */
function lazymedia_seo_meta( $attr, $key, $content ) {
	if ( '' === (string) $content ) {
		return;
	}

	printf( '<meta %s="%s" content="%s">' . "\n", esc_attr( $attr ), esc_attr( $key ), esc_attr( $content ) );
}

/**
 * Description, canonical, Open Graph and Twitter card tags.
 *
 * @return void
 */
function lazymedia_seo_head() {
	$seo = lazymedia_seo_context();

	if ( ! $seo ) {
		return;
	}

	$site = get_bloginfo( 'name' );

	echo "\n<!-- Lazy Media SEO -->\n";
	lazymedia_seo_meta( 'name', 'description', $seo['description'] );
	printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $seo['url'] ) );

	lazymedia_seo_meta( 'property', 'og:locale', str_replace( '-', '_', lazymedia_content_language() ) );
	lazymedia_seo_meta( 'property', 'og:site_name', $site );
	lazymedia_seo_meta( 'property', 'og:type', $seo['type'] );
	lazymedia_seo_meta( 'property', 'og:title', $seo['title'] );
	lazymedia_seo_meta( 'property', 'og:description', $seo['description'] );
	lazymedia_seo_meta( 'property', 'og:url', $seo['url'] );
	lazymedia_seo_meta( 'property', 'og:image', $seo['image']['url'] );
	lazymedia_seo_meta( 'property', 'og:image:width', (string) $seo['image']['width'] );
	lazymedia_seo_meta( 'property', 'og:image:height', (string) $seo['image']['height'] );
	lazymedia_seo_meta( 'property', 'og:image:alt', $seo['image']['alt'] );

	if ( $seo['article'] ) {
		lazymedia_seo_meta( 'property', 'article:published_time', $seo['article']['published'] );
		lazymedia_seo_meta( 'property', 'article:modified_time', $seo['article']['modified'] );
		lazymedia_seo_meta( 'property', 'article:section', $seo['article']['section'] );

		foreach ( $seo['article']['tags'] as $tag ) {
			lazymedia_seo_meta( 'property', 'article:tag', $tag );
		}
	}

	lazymedia_seo_meta( 'name', 'twitter:card', 'summary_large_image' );
	lazymedia_seo_meta( 'name', 'twitter:title', $seo['title'] );
	lazymedia_seo_meta( 'name', 'twitter:description', $seo['description'] );
	lazymedia_seo_meta( 'name', 'twitter:image', $seo['image']['url'] );
	lazymedia_seo_meta( 'name', 'twitter:image:alt', $seo['image']['alt'] );

	if ( $seo['article'] ) {
		lazymedia_seo_meta( 'name', 'twitter:label1', __( 'Ditulis oleh', 'lazymedia' ) );
		lazymedia_seo_meta( 'name', 'twitter:data1', $seo['article']['author'] );
		lazymedia_seo_meta( 'name', 'twitter:label2', __( 'Waktu baca', 'lazymedia' ) );
		lazymedia_seo_meta( 'name', 'twitter:data2', sprintf( '%d menit', lazymedia_read_time( get_queried_object_id() ) ) );
	}
}
add_action( 'wp_head', 'lazymedia_seo_head', 2 );

/**
 * Schema.org graph: organisation, website with search, breadcrumbs, article.
 *
 * Emitted as one @graph so entities reference each other by @id instead of
 * repeating themselves.
 *
 * @return void
 */
function lazymedia_seo_jsonld() {
	$seo = lazymedia_seo_context();

	if ( ! $seo ) {
		return;
	}

	$home     = home_url( '/' );
	$site     = get_bloginfo( 'name' );
	$language = lazymedia_content_language();
	$org_id   = $home . '#organization';
	$site_id  = $home . '#website';
	$page_id  = $seo['url'] . '#webpage';

	$graph = array(
		array(
			'@type' => 'NewsMediaOrganization',
			'@id'   => $org_id,
			'name'  => $site,
			'url'   => $home,
			'logo'  => array(
				'@type'  => 'ImageObject',
				'url'    => get_template_directory_uri() . '/assets/img/og-default.jpg',
				'width'  => LAZYMEDIA_OG_FALLBACK_SIZE[0],
				'height' => LAZYMEDIA_OG_FALLBACK_SIZE[1],
			),
		),
		array(
			'@type'           => 'WebSite',
			'@id'             => $site_id,
			'url'             => $home,
			'name'            => $site,
			'description'     => get_bloginfo( 'description' ),
			'inLanguage'      => $language,
			'publisher'       => array( '@id' => $org_id ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/search/' ) . '{search_term_string}/',
				),
				'query-input' => 'required name=search_term_string',
			),
		),
	);

	$webpage = array(
		'@type'      => is_singular() ? 'WebPage' : ( is_search() ? 'SearchResultsPage' : 'CollectionPage' ),
		'@id'        => $page_id,
		'url'        => $seo['url'],
		'name'       => $seo['title'],
		'isPartOf'   => array( '@id' => $site_id ),
		'inLanguage' => $language,
	);

	if ( count( $seo['crumbs'] ) > 1 ) {
		$items = array();

		foreach ( $seo['crumbs'] as $position => $crumb ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $position + 1,
				'name'     => $crumb['name'],
				'item'     => $crumb['url'],
			);
		}

		$graph[] = array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $page_id . '-breadcrumb',
			'itemListElement' => $items,
		);

		$webpage['breadcrumb'] = array( '@id' => $page_id . '-breadcrumb' );
	}

	$graph[] = $webpage;

	if ( $seo['article'] ) {
		$graph[] = array(
			'@type'            => 'NewsArticle',
			'@id'              => $seo['url'] . '#article',
			'headline'         => $seo['title'],
			'description'      => $seo['description'],
			'image'            => array(
				'@type'  => 'ImageObject',
				'url'    => $seo['image']['url'],
				'width'  => $seo['image']['width'],
				'height' => $seo['image']['height'],
			),
			'datePublished'    => $seo['article']['published'],
			'dateModified'     => $seo['article']['modified'],
			'author'           => array(
				'@type' => 'Person',
				'name'  => $seo['article']['author'],
				'url'   => $seo['article']['authorUrl'],
			),
			'publisher'        => array( '@id' => $org_id ),
			'mainEntityOfPage' => array( '@id' => $page_id ),
			'isPartOf'         => array( '@id' => $site_id ),
			'articleSection'   => $seo['article']['section'],
			'keywords'         => implode( ', ', $seo['article']['tags'] ),
			'wordCount'        => $seo['article']['words'],
			'inLanguage'       => $language,
		);
	}

	$payload = array(
		'@context' => 'https://schema.org',
		'@graph'   => $graph,
	);

	// Slashes stay unescaped so the exporter's origin rewrite matches them.
	echo '<script type="application/ld+json">'
		. wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG )
		. "</script>\n";
}
add_action( 'wp_head', 'lazymedia_seo_jsonld', 3 );

/**
 * Keep pages with nothing to rank out of the index.
 *
 * Empty archives exist so navigation never 404s, but an indexed empty page is
 * thin content. The 404 template gets the same treatment; WordPress already
 * marks search results noindex.
 *
 * @param array $robots Robots directives.
 * @return array
 */
function lazymedia_seo_robots( $robots ) {
	$empty_term = ( is_category() || is_tag() ) && 0 === (int) get_queried_object()->count;

	if ( is_404() || $empty_term ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
	} elseif ( ! is_search() ) {
		// Let previews use the full-width photo instead of a thumbnail.
		$robots['max-image-preview'] = 'large';
	}

	return $robots;
}
add_filter( 'wp_robots', 'lazymedia_seo_robots' );

// The theme prints its own canonical on every page type, not only singular.
remove_action( 'wp_head', 'rel_canonical' );
