<?php
/**
 * Site footer: brand blurb, link columns, social row, credit line.
 *
 * @package LazyMedia
 */

$lazy_sections = get_categories(
	array(
		'orderby'    => 'count',
		'order'      => 'DESC',
		'number'     => 5,
		'hide_empty' => true,
		'exclude'    => array( get_cat_ID( 'uncategorized' ) ),
	)
);
?>

	<footer class="lm-footer" role="contentinfo">
		<div class="lm-container">
			<div class="lm-footer__grid">
				<div class="lm-footer__brand">
					<a class="lm-brand__link" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
						<img class="lm-footer__logo" src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/logo.svg' ); ?>"
							alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" width="176" height="40">
					</a>
					<p class="lm-footer__blurb">
						<?php echo esc_html( get_bloginfo( 'description', 'display' ) ); ?>
					</p>
					<p class="lm-footer__copy">
						<?php esc_html_e( 'Berita gaming, teknologi, dan hiburan dalam bahasa Indonesia, setiap hari.', 'lazymedia' ); ?>
					</p>
				</div>

				<nav class="lm-footer__col" aria-label="<?php esc_attr_e( 'Kategori', 'lazymedia' ); ?>">
					<h2 class="lm-footer__title"><?php esc_html_e( 'Kategori', 'lazymedia' ); ?></h2>
					<ul class="lm-footer__list">
						<?php if ( $lazy_sections ) : ?>
							<?php foreach ( $lazy_sections as $lazy_section ) : ?>
								<li>
									<a href="<?php echo esc_url( get_category_link( $lazy_section ) ); ?>">
										<?php echo esc_html( $lazy_section->name ); ?>
									</a>
								</li>
							<?php endforeach; ?>
						<?php else : ?>
							<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Semua kategori', 'lazymedia' ); ?></a></li>
						<?php endif; ?>
					</ul>
				</nav>

				<nav class="lm-footer__col" aria-label="<?php esc_attr_e( 'Informasi', 'lazymedia' ); ?>">
					<h2 class="lm-footer__title"><?php esc_html_e( 'Informasi', 'lazymedia' ); ?></h2>
					<?php
					if ( has_nav_menu( 'footer' ) ) {
						wp_nav_menu(
							array(
								'theme_location' => 'footer',
								'container'      => false,
								'menu_class'     => 'lm-footer__list',
								'depth'          => 1,
								'fallback_cb'    => false,
							)
						);
					} else {
						echo '<ul class="lm-footer__list">';
						echo '<li><a href="' . esc_url( home_url( '/about/' ) ) . '">About</a></li>';
						echo '</ul>';
					}
					?>
					<ul class="lm-footer__list">
						<li>
							<a href="<?php echo esc_url( lazymedia_contact_url() ); ?>">
								<?php esc_html_e( 'Kontak', 'lazymedia' ); ?>
							</a>
						</li>
					</ul>
				</nav>

				<div class="lm-footer__col">
					<h2 class="lm-footer__title"><?php esc_html_e( 'Ikuti kami', 'lazymedia' ); ?></h2>
					<ul class="lm-social">
						<?php foreach ( lazymedia_social_links() as $lazy_social ) : ?>
							<li>
								<a class="lm-social__link" href="<?php echo esc_url( $lazy_social['url'] ); ?>"
									target="_blank" rel="noopener noreferrer nofollow">
									<span class="lm-social__label"><?php echo esc_html( $lazy_social['label'] ); ?></span>
									<span class="lm-social__handle"><?php echo esc_html( $lazy_social['handle'] ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>

			<div class="lm-footer__bottom">
				<p class="lm-footer__copy">
					&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>.
					<?php esc_html_e( 'Semua hak cipta dilindungi.', 'lazymedia' ); ?>
				</p>
				<p class="lm-footer__meta"><?php echo esc_html( sprintf( __( 'Desain dan pengembangan oleh tim redaksi %s.', 'lazymedia' ), get_bloginfo( 'name' ) ) ); ?></p>
			</div>
		</div>
	</footer>
</div>

<?php wp_footer(); ?>
</body>
</html>