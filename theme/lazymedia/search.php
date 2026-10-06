<?php
/**
 * Search results.
 *
 * The static export has no PHP, so the server-rendered result list below is
 * only a fallback for local browsing. assets/js/search.js rebuilds the same
 * markup in the same container from search-index.json, which is what runs on
 * the deployed site.
 *
 * There is no pagination here on purpose. The deployed site has a single search
 * page whose results come from one JSON index, so a "page 2" link would have
 * nowhere to go.
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

get_header();

$lm_term = get_search_query();
?>

<main class="site-main" id="lm-content">
	<section class="lm-archive-hero lm-archive-hero--search" style="--lm-section-accent:<?php echo esc_attr( lazymedia_section_accent( 'new-articles' ) ); ?>">
		<div class="lm-container">
			<h1 class="lm-archive-hero__title">
				<?php
				if ( $lm_term ) {
					printf(
						/* translators: %s: search query. */
						esc_html__( 'Hasil pencarian: %s', 'lazymedia' ),
						esc_html( $lm_term )
					);
				} else {
					esc_html_e( 'Cari artikel', 'lazymedia' );
				}
				?>
			</h1>

			<div class="lm-search-form-lg">
				<?php get_search_form(); ?>
			</div>

			<p class="lm-archive-hero__count" data-lm-search-status>
				<?php if ( $lm_term ) : ?>
					<?php
					$lm_found = $GLOBALS['wp_query']->found_posts;
					printf(
						/* translators: %s: number of results. */
						esc_html( _n( '%s artikel ditemukan', '%s artikel ditemukan', $lm_found, 'lazymedia' ) ),
						esc_html( number_format_i18n( $lm_found ) )
					);
					?>
				<?php endif; ?>
			</p>
		</div>
	</section>

	<div class="lm-container">
		<div class="lm-grid lm-grid--3" data-lm-search-results aria-live="polite">
			<?php if ( have_posts() ) : ?>
				<?php
				while ( have_posts() ) :
					the_post();
					lazymedia_card(
						array(
							'variant' => 'default',
							'size'    => 'lm-card-md',
						)
					);
				endwhile;
				?>
			<?php elseif ( $lm_term ) : ?>
				<p class="lm-empty"><?php echo esc_html( lazymedia_empty_message( 'search' ) ); ?></p>
			<?php endif; ?>
		</div>

		</div>
</main>

<?php
get_footer();