<?php
/**
 * Archive template: category, tag, date, and author listings.
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

get_header();

$lm_kind      = is_category() ? 'category' : ( is_tag() ? 'tag' : 'archive' );
$lm_heading   = wp_strip_all_tags( get_the_archive_title() );
$lm_blurb    = get_the_archive_description();
$lm_accent    = lazymedia_section_accent( 'category' );
$lm_subcats   = array();

if ( is_category() ) {
	$lm_term    = get_queried_object();
	$lm_subcats = get_categories(
		array(
			'parent'     => $lm_term->term_id,
			'hide_empty' => true,
			'number'     => 8,
		)
	);
}
?>

<main class="site-main" id="lm-content">
	<section class="lm-archive-hero" style="--lm-section-accent:<?php echo esc_attr( $lm_accent ); ?>">
		<div class="lm-container">
			<nav class="lm-breadcrumb" aria-label="<?php esc_attr_e( 'Remah roti', 'lazymedia' ); ?>">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Beranda', 'lazymedia' ); ?></a>
				<span aria-hidden="true">/</span>
				<span aria-current="page"><?php echo esc_html( $lm_heading ); ?></span>
			</nav>

			<h1 class="lm-archive-hero__title"><?php echo esc_html( $lm_heading ); ?></h1>

			<?php if ( $lm_blurb ) : ?>
				<div class="lm-archive-hero__blurb"><?php echo wp_kses_post( $lm_blurb ); ?></div>
			<?php endif; ?>

			<p class="lm-archive-hero__count">
				<?php
				$lm_found = $GLOBALS['wp_query']->found_posts;
				printf(
					/* translators: %s: number of articles. */
					esc_html( _n( '%s artikel', '%s artikel', $lm_found, 'lazymedia' ) ),
					esc_html( number_format_i18n( $lm_found ) )
				);
				?>
			</p>
		</div>
	</section>

	<div class="lm-container">
		<?php if ( $lm_subcats ) : ?>
			<nav class="lm-filter lm-filter--archive" aria-label="<?php esc_attr_e( 'Subkategori', 'lazymedia' ); ?>">
				<?php foreach ( $lm_subcats as $lm_sub ) : ?>
					<a class="lm-filter__pill" href="<?php echo esc_url( get_category_link( $lm_sub ) ); ?>">
						<?php echo esc_html( $lm_sub->name ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>

		<?php if ( have_posts() ) : ?>
			<div class="lm-grid lm-grid--3" data-lm-grid>
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
			</div>

			<?php
			the_posts_pagination(
				array(
					'mid_size'           => 1,
					'prev_text'          => esc_html__( 'Sebelumnya', 'lazymedia' ),
					'next_text'          => esc_html__( 'Berikutnya', 'lazymedia' ),
					'screen_reader_text' => esc_html__( 'Navigasi arsip', 'lazymedia' ),
					'class'              => 'lm-pagination',
				)
			);
			?>
		<?php else : ?>
			<p class="lm-empty"><?php echo esc_html( lazymedia_empty_message( 'archive' ) ); ?></p>
		<?php endif; ?>
	</div>
</main>

<?php
get_footer();