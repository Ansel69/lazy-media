<?php
/**
 * Lazy Media theme setup.
 *
 * @package LazyMedia
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LAZYMEDIA_VERSION', '2.0.0' );

/**
 * Theme supports and registrations.
 *
 * @return void
 */
function lazymedia_setup() {
	load_theme_textdomain( 'lazymedia', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support(
		'html5',
		array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
	);

	// Card images are 16:9 everywhere: 1200x675 full, 600x338 card, 300x200 thumb.
	add_image_size( 'lm-card', 1200, 675, true );
	add_image_size( 'lm-card-md', 600, 338, true );
	add_image_size( 'lm-thumb', 300, 200, true );
	add_image_size( 'lm-hero', 2000, 1125, true );

	register_nav_menus(
		array(
			'primary' => __( 'Menu Utama', 'lazymedia' ),
			'footer'  => __( 'Menu Footer', 'lazymedia' ),
		)
	);
}
add_action( 'after_setup_theme', 'lazymedia_setup' );

/**
 * Give the bare /search/ path a route.
 *
 * WordPress already maps /search/<term>/ to the search template, but a search
 * page with no term — which is where every search form points — 404s. The
 * static export needs a real /search/index.html, and Vercel serves that same
 * file for /search/<term>/.
 *
 * @return void
 */
function lazymedia_search_rewrite() {
	add_rewrite_rule( '^search/?$', 'index.php?s=', 'top' );
}
add_action( 'init', 'lazymedia_search_rewrite' );

/**
 * Font faces registered as a separate stylesheet.
 *
 * Kept apart from style.css so a cache bust only invalidates the @font-face
 * rules, and so the font URLs are versioned with the theme.
 *
 * @return void
 */
function lazymedia_fonts() {
	wp_enqueue_style(
		'lazymedia-fonts',
		get_template_directory_uri() . '/assets/css/fonts.css',
		array(),
		LAZYMEDIA_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'lazymedia_fonts' );

/**
 * Enqueue theme assets.
 *
 * Component stylesheets are conditional so a page only downloads the CSS it
 * actually renders. In the static export that matters: every kilobyte is
 * paid on every page load.
 *
 * @return void
 */
function lazymedia_assets() {
	wp_enqueue_style( 'lazymedia', get_stylesheet_uri(), array( 'lazymedia-fonts' ), LAZYMEDIA_VERSION );

	wp_enqueue_style( 'lazymedia-components', get_template_directory_uri() . '/assets/css/components.css', array( 'lazymedia' ), LAZYMEDIA_VERSION );

	if ( is_front_page() ) {
		wp_enqueue_style( 'lazymedia-home', get_template_directory_uri() . '/assets/css/home.css', array( 'lazymedia-components' ), LAZYMEDIA_VERSION );
	}

	if ( is_singular( 'post' ) ) {
		wp_enqueue_style( 'lazymedia-single', get_template_directory_uri() . '/assets/css/single.css', array( 'lazymedia-components' ), LAZYMEDIA_VERSION );
	}

	if ( is_archive() ) {
		wp_enqueue_style( 'lazymedia-archive', get_template_directory_uri() . '/assets/css/archive.css', array( 'lazymedia-components' ), LAZYMEDIA_VERSION );
	}

	if ( is_search() ) {
		wp_enqueue_style( 'lazymedia-archive', get_template_directory_uri() . '/assets/css/archive.css', array( 'lazymedia-components' ), LAZYMEDIA_VERSION );
		wp_enqueue_script( 'lazymedia-search', get_template_directory_uri() . '/assets/js/search.js', array(), LAZYMEDIA_VERSION, true );
	}

	if ( is_page() ) {
		wp_enqueue_style( 'lazymedia-page', get_template_directory_uri() . '/assets/css/page.css', array( 'lazymedia-components' ), LAZYMEDIA_VERSION );
	}

	if ( is_404() ) {
		wp_enqueue_style( 'lazymedia-page', get_template_directory_uri() . '/assets/css/page.css', array( 'lazymedia-components' ), LAZYMEDIA_VERSION );
	}

	wp_enqueue_script( 'lazymedia', get_template_directory_uri() . '/assets/js/lazymedia.js', array(), LAZYMEDIA_VERSION, true );

	wp_localize_script(
		'lazymedia',
		'lazymediaData',
		array(
			'searchIndex' => home_url( '/search-index.json' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'lazymedia_assets' );

/**
 * Trim excerpts to the card length used across the site.
 *
 * @param int $length Default length.
 * @return int
 */
function lazymedia_excerpt_length( $length ) {
	return is_admin() ? $length : 22;
}
add_filter( 'excerpt_length', 'lazymedia_excerpt_length' );

/**
 * Replace the excerpt ellipsis.
 *
 * @return string
 */
function lazymedia_excerpt_more() {
	return '…';
}
add_filter( 'excerpt_more', 'lazymedia_excerpt_more' );

/**
 * Mark posts tagged as video so cards can show a play badge.
 *
 * @return void
 */
function lazymedia_flag_video_posts() {
	add_meta_box(
		'lazymedia-video',
		__( 'Video', 'lazymedia' ),
		'lazymedia_video_meta_box',
		'post',
		'side'
	);
}
add_action( 'add_meta_boxes', 'lazymedia_flag_video_posts' );

/**
 * Render the video meta box.
 *
 * @param WP_Post $post Current post.
 * @return void
 */
function lazymedia_video_meta_box( $post ) {
	wp_nonce_field( 'lazymedia_video', 'lazymedia_video_nonce' );
	$is_video = (bool) get_post_meta( $post->ID, '_lm_featured_video', true );
	?>
	<label>
		<input type="checkbox" name="lm_video" value="1" <?php checked( $is_video ); ?>>
		<?php esc_html_e( 'Tampilkan sebagai video', 'lazymedia' ); ?>
	</label>
	<?php
}

/**
 * Persist the video flag.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function lazymedia_save_video( $post_id ) {
	if ( ! isset( $_POST['lazymedia_video_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['lazymedia_video_nonce'] ) ), 'lazymedia_video' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$is_video = isset( $_POST['lm_video'] ) ? 1 : 0;

	if ( $is_video ) {
		update_post_meta( $post_id, '_lm_featured_video', 1 );
	} else {
		delete_post_meta( $post_id, '_lm_featured_video' );
	}
}
add_action( 'save_post', 'lazymedia_save_video' );

require_once get_template_directory() . '/inc/assets.php';
require_once get_template_directory() . '/inc/photo-credit.php';
require_once get_template_directory() . '/inc/design.php';
require_once get_template_directory() . '/inc/query.php';
require_once get_template_directory() . '/inc/cards.php';
require_once get_template_directory() . '/inc/nav-walker.php';
require_once get_template_directory() . '/inc/blocks.php';
require_once get_template_directory() . '/inc/seo.php';
require_once get_template_directory() . '/inc/toc.php';