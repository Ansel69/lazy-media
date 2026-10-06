<?php
/**
 * 404 template.
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main class="site-main" id="lm-content">
	<section class="lm-archive-hero" style="--lm-section-accent:<?php echo esc_attr( lazymedia_section_accent( 'new-articles' ) ); ?>">
		<div class="lm-container">
			<h1 class="lm-archive-hero__title"><?php esc_html_e( 'Halaman tidak ditemukan', 'lazymedia' ); ?></h1>
		</div>
	</section>

	<div class="lm-container">
		<div class="lm-404">
			<p class="lm-404__code">404</p>
			<p class="lm-404__text">
				<?php esc_html_e( 'Alamat yang kamu tuju sudah pindah atau tidak pernah ada. Coba kembali ke beranda.', 'lazymedia' ); ?>
			</p>

			<div class="lm-search-form-lg">
				<?php get_search_form(); ?>
			</div>

			<div class="lm-404__actions">
				<a class="lm-btn lm-btn--accent" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<?php esc_html_e( 'Kembali ke beranda', 'lazymedia' ); ?>
				</a>
			</div>

			<?php
			$lm_recent = lazymedia_query_latest( 3 );

			if ( $lm_recent ) :
				?>
				<div class="lm-404__recent">
					<h2 class="lm-404__recent-title"><?php esc_html_e( 'Mungkin kamu mencari ini', 'lazymedia' ); ?></h2>
					<div class="lm-grid lm-grid--3">
						<?php lazymedia_card_grid( $lm_recent, array( 'size' => 'lm-card-md' ) ); ?>
					</div>
				</div>
				<?php
			endif;
			?>
		</div>
	</div>
</main>

<?php
get_footer();