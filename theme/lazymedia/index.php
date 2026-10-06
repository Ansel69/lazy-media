<?php
/**
 * Fallback template for any query WordPress has no more specific file for.
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

get_header();

$lm_query = new WP_Query(
	array(
		'post_type'           => 'post',
		'posts_per_page'      => 9,
		'ignore_sticky_posts' => true,
	)
);
?>

<main class="site-main" id="lm-content">
	<section class="lm-archive-hero" style="--lm-section-accent:<?php echo esc_attr( lazymedia_section_accent( 'latest' ) ); ?>">
		<div class="lm-container">
			<nav class="lm-breadcrumb" aria-label="<?php esc_attr_e( 'Remah roti', 'lazymedia' ); ?>">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Beranda', 'lazymedia' ); ?></a>
				<span aria-hidden="true">/</span>
				<span aria-current="page"><?php esc_html_e( 'Artikel terbaru', 'lazymedia' ); ?></span>
			</nav>
			<h1 class="lm-archive-hero__title"><?php esc_html_e( 'Artikel Terbaru', 'lazymedia' ); ?></h1>
		</div>
	</section>

	<div class="lm-container">
		<?php if ( $lm_query->have_posts() ) : ?>
			<div class="lm-grid lm-grid--3" data-lm-grid>
				<?php lazymedia_card_grid( $lm_query, array( 'size' => 'lm-card-md' ) ); ?>
			</div>

			<?php
			the_posts_pagination(
				array(
					'mid_size'           => 1,
					'prev_text'          => esc_html__( 'Sebelumnya', 'lazymedia' ),
					'next_text'          => esc_html__( 'Berikutnya', 'lazymedia' ),
					'screen_reader_text' => esc_html__( 'Navigasi', 'lazymedia' ),
					'class'              => 'lm-pagination',
				)
			);
			?>
		<?php else : ?>
			<p class="lm-empty"><?php echo esc_html( lazymedia_empty_message( 'latest' ) ); ?></p>
		<?php endif; ?>
	</div>
</main>

<?php
get_footer();