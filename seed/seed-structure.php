<?php
/**
 * Seed: categories, tags, and the primary/footer menus.
 *
 * Run with:
 *   docker compose run --rm wpcli wp eval-file /seed/seed-structure.php --path=/var/www/html --allow-root
 *
 * Safe to re-run: categories and tags are looked up by slug before creation.
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Create a category if its slug is missing, then return the term.
 *
 * @param string $name Category name.
 * @param string $slug Category slug.
 * @return WP_Term
 */
function lm_seed_category( $name, $slug ) {
	$existing = get_category_by_slug( $slug );
	if ( $existing ) {
		return $existing;
	}

	// wp_insert_term directly: wp_insert_category() needs cat_name and returns
	// 0 silently on a bad key, which hides the failure.
	$result = wp_insert_term(
		$name,
		'category',
		array(
			'slug'        => $slug,
			'description' => '',
		)
	);

	if ( is_wp_error( $result ) ) {
		echo "category error ({$slug}): " . $result->get_error_message() . "\n";
		return null;
	}

	echo "category: {$slug} (term {$result['term_id']})\n";
	return get_category_by_slug( $slug );
}

/**
 * Create a tag if its slug is missing, then return the term.
 *
 * @param string $name Tag name.
 * @param string $slug Tag slug.
 * @return WP_Term|null
 */
function lm_seed_tag( $name, $slug ) {
	$existing = get_term_by( 'slug', $slug, 'post_tag' );
	if ( $existing ) {
		return $existing;
	}

	$result = wp_insert_term( $name, 'post_tag', array( 'slug' => $slug ) );
	if ( is_wp_error( $result ) ) {
		return null;
	}

	echo "tag: {$slug}\n";
	return get_term_by( 'slug', $slug, 'post_tag' );
}

// Categories mirroring the reference site structure.
$categories = array(
	'games'         => 'Games',
	'game-news'     => 'Game News',
	'console'       => 'Console',
	'hardware'      => 'Hardware',
	'tech'          => 'Tech',
	'tech-news'     => 'Tech News',
	'tech-review'   => 'Tech Review',
	'tech-tips'     => 'Tech Tips',
	'overclocking'  => 'Overclocking',
	'setup'         => 'Setup',
	'reviews'       => 'Reviews',
	'esports'       => 'Esports',
	'entertainment' => 'Entertainment',
	'anime'         => 'Anime & Manga',
	'movie-series'  => 'Movie & Series',
	'hobby'         => 'Hobby',
	'pc'            => 'PC',
	'gadgets'       => 'Gadgets',
);

foreach ( $categories as $lm_slug => $lm_name ) {
	lm_seed_category( $lm_name, $lm_slug );
}

// Tags used by the "New Articles" filter row.
foreach ( array( 'Games', 'Tech', 'Reviews', 'Esports', 'Entertainment', 'Hobby' ) as $lm_tag_name ) {
	lm_seed_tag( $lm_tag_name, sanitize_title( $lm_tag_name ) );
}

/**
 * Create or replace a menu, then register it at a theme location.
 *
 * @param string $name    Menu name.
 * @param string $slug    Menu slug.
 * @param array  $items   Items: label => url or ['type' => 'category', 'slug' => ...].
 * @param string $location Theme location.
 */
function lm_seed_menu( $name, $slug, $items, $location ) {
	$existing = wp_get_nav_menu_object( $name );
	$menu_id  = $existing ? (int) $existing->term_id : wp_create_nav_menu( $name );

	if ( is_wp_error( $menu_id ) || ! $menu_id ) {
		echo "menu error: {$name}\n";
		return;
	}

	foreach ( wp_get_nav_menu_items( $menu_id ) as $stale ) {
		wp_delete_post( $stale->ID, true );
	}

	/**
	 * Add one menu item and return its post ID (0 on failure).
	 *
	 * @param int    $menu_id Menu term ID.
	 * @param array  $item    Item spec: array( 'url'|'category', slug, title ).
	 * @param int    $order   Position.
	 * @param int    $parent  Parent menu item post ID.
	 * @return int
	 */
	$add_item = function ( $menu_id, $item, $order, $parent = 0 ) use ( &$add_item ) {
		if ( 'category' === $item[0] ) {
			$term = get_category_by_slug( $item[1] );
			if ( ! $term ) {
				return 0;
			}

			return (int) wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'     => $item[2],
					'menu-item-object'    => 'category',
					'menu-item-object-id' => $term->term_id,
					'menu-item-type'      => 'taxonomy',
					'menu-item-status'    => 'publish',
					'menu-item-position'  => $order,
					'menu-item-parent-id' => $parent,
				)
			);
		}

		return (int) wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'     => $item[1],
				'menu-item-url'       => $item[2],
				'menu-item-type'      => 'custom',
				'menu-item-status'    => 'publish',
				'menu-item-position'  => $order,
				'menu-item-parent-id' => $parent,
			)
		);
	};

	$order = 0;
	foreach ( $items as $item ) {
		++$order;

		$parent_id = $add_item( $menu_id, array( $item[0], $item[1], $item[2] ), $order );

		if ( empty( $item[3] ) || ! is_array( $item[3] ) ) {
			continue;
		}

		// Children render as the dropdown panel, so they keep the parent's slot.
		foreach ( $item[3] as $child ) {
			$add_item( $menu_id, $child, $order, $parent_id );
		}
	}

	$locations              = get_theme_mod( 'nav_menu_locations', array() );
	$locations[ $location ] = $menu_id;
	set_theme_mod( 'nav_menu_locations', $locations );

	echo "menu: {$name} -> {$location}\n";
}

// Nested items: array( type, slug_or_url, title, children_array ).
lm_seed_menu(
	'Menu Utama',
	'menu-utama',
	array(
		array( 'url', home_url( '/' ), 'Home' ),
		array(
			'category',
			'games',
			'Games',
			array(
				array( 'category', 'game-news', 'News' ),
				array( 'category', 'reviews', 'Reviews' ),
				array( 'category', 'console', 'Console' ),
			),
		),
		array(
			'category',
			'tech',
			'Tech',
			array(
				array( 'category', 'overclocking', 'Overclocking' ),
				array( 'category', 'setup', 'Setup' ),
				array( 'category', 'tech-news', 'Tech News' ),
				array( 'category', 'tech-review', 'Tech Review' ),
				array( 'category', 'tech-tips', 'Tech Tips' ),
			),
		),
		array(
			'category',
			'entertainment',
			'Entertainment',
			array(
				array( 'category', 'movie-series', 'Movie & Series' ),
				array( 'category', 'anime', 'Anime & Manga' ),
			),
		),
		array( 'category', 'hobby', 'Hobby' ),
		array( 'url', home_url( '/about/' ), 'About' ),
	),
	'primary'
);

lm_seed_menu(
	'Menu Footer',
	'menu-footer',
	array(
		array( 'category', 'games', 'Games' ),
		array( 'category', 'tech', 'Tech' ),
		array( 'category', 'entertainment', 'Entertainment' ),
		array( 'category', 'hobby', 'Hobby' ),
	),
	'footer'
);

echo "structure seed done\n";