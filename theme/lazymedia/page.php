<?php
/**
 * Static page template: About, Contact, and any other page.
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

get_header();

$lm_accent = lazymedia_section_accent( 'hardware' );
?>

<main class="site-main" id="lm-content">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article class="lm-page" <?php post_class(); ?>>
			<header class="lm-archive-hero" style="--lm-section-accent:<?php echo esc_attr( $lm_accent ); ?>">
				<div class="lm-container">
					<nav class="lm-breadcrumb" aria-label="<?php esc_attr_e( 'Remah roti', 'lazymedia' ); ?>">
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Beranda', 'lazymedia' ); ?></a>
						<span aria-hidden="true">/</span>
						<span aria-current="page"><?php the_title(); ?></span>
					</nav>
					<h1 class="lm-archive-hero__title"><?php the_title(); ?></h1>
				</div>
			</header>

			<div class="lm-container">
				<div class="lm-page__body">
					<?php
					the_content();

					wp_link_pages(
						array(
							'before' => '<nav class="lm-single__pages">',
							'after'  => '</nav>',
						)
					);
					?>
				</div>

				<?php if ( lazymedia_is_contact_page() ) : ?>
					<div class="lm-contact">
						<h2 class="lm-contact__title"><?php esc_html_e( 'Hubungi redaksi', 'lazymedia' ); ?></h2>
						<ul class="lm-contact__list">
							<?php foreach ( lazymedia_social_links() as $lm_social ) : ?>
								<li>
									<a href="<?php echo esc_url( $lm_social['url'] ); ?>" target="_blank" rel="noopener noreferrer nofollow">
										<strong><?php echo esc_html( $lm_social['label'] ); ?></strong>
										<span><?php echo esc_html( $lm_social['handle'] ); ?></span>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			</div>
		</article>
		<?php
	endwhile;
	?>
</main>

<?php
get_footer();