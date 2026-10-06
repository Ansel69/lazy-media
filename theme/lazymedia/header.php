<?php
/**
 * Site header: utility bar, brand, primary dropdown nav, search.
 *
 * @package LazyMedia
 */

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="lm-skip-link" href="#lm-content"><?php esc_html_e( 'Lompat ke konten', 'lazymedia' ); ?></a>

<div class="site-shell">

	<header class="lm-header" data-lm-header>
		<div class="lm-utility">
			<div class="lm-container lm-utility__inner">
				<span class="lm-utility__date"><?php echo esc_html( wp_date( 'l, j F Y' ) ); ?></span>

				<ul class="lm-utility__social">
					<?php foreach ( lazymedia_social_links() as $lm_social ) : ?>
						<li>
							<a href="<?php echo esc_url( $lm_social['url'] ); ?>" rel="noopener noreferrer nofollow" target="_blank">
								<?php echo esc_html( $lm_social['label'] ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>

		<div class="lm-header__bar">
			<div class="lm-container lm-header__inner">
				<div class="lm-brand">
					<a class="lm-brand__link" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
						<img class="lm-brand__logo" src="<?php echo esc_url( get_template_directory_uri() . '/assets/img/logo.svg' ); ?>"
							alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" width="176" height="40">
					</a>
					<p class="lm-brand__tagline"><?php bloginfo( 'description' ); ?></p>
				</div>

				<nav class="lm-nav" id="lm-nav" aria-label="<?php esc_attr_e( 'Menu utama', 'lazymedia' ); ?>">
					<?php
					if ( has_nav_menu( 'primary' ) ) {
						wp_nav_menu(
							array(
								'theme_location' => 'primary',
								'container'      => false,
								'menu_class'     => 'lm-nav__list',
								'depth'          => 3,
								'walker'         => new Lazy_Media_Nav_Walker(),
							)
						);
					} else {
						echo '<ul class="lm-nav__list"><li class="lm-nav__item"><a class="lm-nav__link" href="' . esc_url( home_url( '/' ) ) . '"><span>' . esc_html__( 'Beranda', 'lazymedia' ) . '</span></a></li></ul>';
					}
					?>
				</nav>

				<div class="lm-header__actions">
					<div class="lm-search" data-lm-search>
						<?php get_search_form(); ?>
					</div>

					<a class="lm-btn lm-btn--accent lm-header__cta" href="<?php echo esc_url( lazymedia_contact_url() ); ?>">
						<?php esc_html_e( 'Redaksi', 'lazymedia' ); ?>
					</a>

					<button type="button" class="lm-burger" data-lm-nav-toggle aria-controls="lm-nav" aria-expanded="false">
						<span class="lm-burger__box" aria-hidden="true"><span></span><span></span><span></span></span>
						<span class="lm-visually-hidden"><?php esc_html_e( 'Buka menu', 'lazymedia' ); ?></span>
					</button>
				</div>
			</div>
		</div>
	</header>