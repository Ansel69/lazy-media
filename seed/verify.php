<?php
/**
 * Verification helper: prints homepage block coverage.
 *
 * Run with:
 *   docker compose run --rm wpcli wp eval-file /seed/verify.php --path=/var/www/html --allow-root
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

require_once get_template_directory() . '/inc/blocks.php';
require_once get_template_directory() . '/inc/query.php';

$ids = get_posts(
	array(
		'post_type'      => 'post',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

$with_thumb = 0;
foreach ( $ids as $id ) {
	if ( has_post_thumbnail( $id ) ) {
		++$with_thumb;
	}
}

echo "posts:            " . count( $ids ) . "\n";
echo "with thumbnail:   {$with_thumb}\n";
echo "featured video:   " . count( get_posts( array( 'post_type' => 'post', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_lm_featured_video' ) ) ) . "\n";
echo "categories:       " . wp_list_categories( array( 'hide_empty' => false, 'echo' => false, 'style' => 'none' ) ) . "\n";
echo "primary menu:     " . ( has_nav_menu( 'primary' ) ? 'yes' : 'no' ) . "\n";
echo "footer menu:      " . ( has_nav_menu( 'footer' ) ? 'yes' : 'no' ) . "\n";

$checks = array(
	'Trending (10)'            => lazymedia_query_trending( 10 ),
	"Today's Video (1)"        => lazymedia_query_todays_video(),
	'New Articles (6)'         => lazymedia_query_new_articles( 6 ),
	'New Articles tag=games'   => lazymedia_query_new_articles( 6, 'games' ),
	'Category games (3)'       => lazymedia_query_category( 'games', 3 ),
	'Category hardware (3)'    => lazymedia_query_category( 'hardware', 3 ),
	'Category entertainment(3)' => lazymedia_query_category( 'entertainment', 3 ),
	'Latest Articles (8)'      => lazymedia_query_latest( 8 ),
);

echo "\nblock queries:\n";
foreach ( $checks as $label => $query ) {
	if ( $query instanceof WP_Query ) {
		printf( "  %-26s %d posts\n", $label, (int) $query->post_count );
	} else {
		printf( "  %-26s EMPTY\n", $label );
	}
}