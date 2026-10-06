<?php
/**
 * Query helpers.
 *
 * Every homepage block and template asks for posts through this file, so the
 * shape of a section's content is decided in one place. Each helper returns a
 * WP_Query, or null when there is nothing to show — blocks render an empty
 * state for null rather than an empty grid.
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Posts for the homepage hero.
 *
 * The hero is the newest run of non-video posts: it is the loudest element on
 * the page and video belongs in its own block below.
 *
 * @param int $limit Number of slides.
 * @return WP_Query|null
 */
function lazymedia_query_hero( $limit = 4 ) {
	$query = new WP_Query(
		array(
			'post_type'           => 'post',
			'posts_per_page'      => $limit,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'meta_query'          => array(
				array(
					'key'     => '_lm_featured_video',
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);

	return $query->have_posts() ? $query : null;
}

/**
 * Trending posts.
 *
 * Real view tracking does not exist yet, so trending is the most recent posts
 * that are not the featured video. Replace the body with a view-count meta
 * query once counts are collected.
 *
 * @param int $limit Number of posts.
 * @return WP_Query|null
 */
function lazymedia_query_trending( $limit = 5 ) {
	$query = new WP_Query(
		array(
			'post_type'           => 'post',
			'posts_per_page'      => $limit,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'meta_query'          => array(
				array(
					'key'     => '_lm_featured_video',
					'compare' => 'NOT EXISTS',
				),
			),
		)
	);

	return $query->have_posts() ? $query : null;
}

/**
 * Today's featured video: the newest post flagged as a video.
 *
 * @return WP_Query|null
 */
function lazymedia_query_todays_video() {
	$query = new WP_Query(
		array(
			'post_type'      => 'post',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'   => '_lm_featured_video',
					'value' => '1',
				),
			),
		)
	);

	return $query->have_posts() ? $query : null;
}

/**
 * New articles: the most recent posts, optionally narrowed to one tag.
 *
 * @param int    $limit Number of posts.
 * @param string $tag   Optional tag slug.
 * @return WP_Query|null
 */
function lazymedia_query_new_articles( $limit = 6, $tag = '' ) {
	$args = array(
		'post_type'           => 'post',
		'posts_per_page'      => $limit,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);

	if ( $tag ) {
		$args['tag'] = $tag;
	}

	$query = new WP_Query( $args );

	return $query->have_posts() ? $query : null;
}

/**
 * Posts in one category.
 *
 * @param string $category_slug Category slug.
 * @param int    $limit         Number of posts.
 * @return WP_Query|null
 */
function lazymedia_query_category( $category_slug, $limit = 3 ) {
	$query = new WP_Query(
		array(
			'post_type'           => 'post',
			'posts_per_page'      => $limit,
			'ignore_sticky_posts' => true,
			'category_name'       => $category_slug,
			'no_found_rows'       => true,
		)
	);

	return $query->have_posts() ? $query : null;
}

/**
 * Latest articles.
 *
 * @param int $limit Number of posts.
 * @return WP_Query|null
 */
function lazymedia_query_latest( $limit = 8 ) {
	$query = new WP_Query(
		array(
			'post_type'           => 'post',
			'posts_per_page'      => $limit,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);

	return $query->have_posts() ? $query : null;
}

/**
 * Posts related to the one being viewed.
 *
 * Falls back to recent posts when nothing shares a category, so the block is
 * never empty.
 *
 * @param int $post_id Current post.
 * @param int $limit   Number of posts.
 * @return WP_Query|null
 */
function lazymedia_query_related( $post_id, $limit = 4 ) {
	$categories = wp_get_post_categories( $post_id );

	$args = array(
		'post_type'           => 'post',
		'posts_per_page'      => $limit,
		'post__not_in'        => array( $post_id ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);

	if ( $categories ) {
		$args['category__in'] = $categories;
	}

	$query = new WP_Query( $args );

	if ( ! $query->have_posts() ) {
		unset( $args['category__in'] );

		$query = new WP_Query( $args );
	}

	return $query->have_posts() ? $query : null;
}

/**
 * Tag slugs offered in the "New Articles" filter row.
 *
 * Only tags that actually have posts are shown, so the row never offers an
 * empty result.
 *
 * @return string[]
 */
function lazymedia_filter_tags() {
	$preferred = array( 'games', 'tech', 'reviews', 'esports', 'entertainment', 'hobby' );
	$tags      = array();

	foreach ( $preferred as $slug ) {
		$term = get_term_by( 'slug', $slug, 'post_tag' );

		if ( $term && $term->count > 0 ) {
			$tags[] = $slug;
		}
	}

	return $tags;
}

/**
 * The tag currently filtering the New Articles block.
 *
 * Read from the query string so the state is shareable and survives the static
 * export, which has no server to hold it.
 *
 * @return string Tag slug, or an empty string when unfiltered.
 */
function lazymedia_current_tag() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display filter.
	$tag = isset( $_GET['lm_tag'] ) ? sanitize_key( wp_unslash( $_GET['lm_tag'] ) ) : '';

	if ( '' === $tag ) {
		return '';
	}

	return get_term_by( 'slug', $tag, 'post_tag' ) ? $tag : '';
}

/**
 * Category archive URL, safe when the category does not exist.
 *
 * @param string $slug Category slug.
 * @return string
 */
function lazymedia_category_url( $slug ) {
	$term = get_category_by_slug( $slug );

	return $term ? get_category_link( $term ) : home_url( '/' );
}

/**
 * Empty-state message used when a block has nothing to show.
 *
 * @param string $context Section slug, so each section can explain itself.
 * @return string
 */
function lazymedia_empty_message( $context = '' ) {
	$messages = array(
		'hero'          => 'Belum ada artikel pilihan.',
		'new-articles'  => 'Belum ada artikel di bagian ini.',
		'games'         => 'Belum ada artikel Games.',
		'hardware'      => 'Belum ada artikel Hardware.',
		'entertainment' => 'Belum ada artikel Entertainment.',
		'latest'        => 'Belum ada artikel.',
		'search'        => 'Tidak ada artikel yang cocok. Coba kata kunci lain.',
		'archive'       => 'Belum ada artikel pada arsip ini.',
	);

	return isset( $messages[ $context ] ) ? $messages[ $context ] : $messages['archive'];
}