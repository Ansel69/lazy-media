<?php
/**
 * Single post: hero image, byline, body, tags, share, related posts.
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main class="site-main" id="lm-content">
	<?php
	while ( have_posts() ) :
		the_post();

		$lm_terms    = get_the_category();
		$lm_primary  = $lm_terms ? $lm_terms[0] : null;
		$lm_is_video = (bool) get_post_meta( get_the_ID(), '_lm_featured_video', true );

		// Render the body up front: the table of contents in the side column
		// needs the headings, and they need ids before the body is printed.
		$lm_body = lazymedia_toc_extract( apply_filters( 'the_content', get_the_content() ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals
		?>

		<div class="lm-progress" data-lm-progress aria-hidden="true"><span class="lm-progress__bar"></span></div>

		<article class="lm-single" <?php post_class(); ?> data-lm-article>
			<div class="lm-container">
				<header class="lm-single__header">
					<div class="lm-single__taxonomy">
						<?php if ( $lm_primary ) : ?>
							<a class="lm-chip lm-chip--accent" href="<?php echo esc_url( get_category_link( $lm_primary ) ); ?>">
								<?php echo esc_html( $lm_primary->name ); ?>
							</a>
						<?php endif; ?>
						<?php if ( $lm_is_video ) : ?>
							<span class="lm-chip lm-chip--video">
								<svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true" focusable="false"><path d="M8 5.5v13l11-6.5-11-6.5Z" fill="currentColor"/></svg>
								<?php esc_html_e( 'Video', 'lazymedia' ); ?>
							</span>
						<?php endif; ?>
					</div>

					<h1 class="lm-single__title"><?php the_title(); ?></h1>

					<?php if ( has_excerpt() ) : ?>
						<p class="lm-single__dek"><?php echo esc_html( get_the_excerpt() ); ?></p>
					<?php endif; ?>

					<div class="lm-meta">
						<span class="lm-meta__item">
							<span class="lm-meta__label"><?php esc_html_e( 'Ditulis oleh', 'lazymedia' ); ?></span>
							<span class="lm-meta__value"><?php echo esc_html( get_the_author() ); ?></span>
						</span>
						<span class="lm-meta__sep" aria-hidden="true"></span>
						<time class="lm-meta__item" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
							<span class="lm-meta__label"><?php esc_html_e( 'Terbit', 'lazymedia' ); ?></span>
							<span class="lm-meta__value"><?php echo esc_html( get_the_date( 'j F Y' ) ); ?></span>
						</time>
						<span class="lm-meta__sep" aria-hidden="true"></span>
						<span class="lm-meta__item">
							<span class="lm-meta__label"><?php esc_html_e( 'Baca', 'lazymedia' ); ?></span>
							<span class="lm-meta__value"><?php echo esc_html( lazymedia_read_time( get_the_ID() ) ); ?> <?php esc_html_e( 'menit', 'lazymedia' ); ?></span>
						</span>
					</div>
				</header>

				<?php if ( has_post_thumbnail() ) : ?>
					<figure class="lm-single__figure">
						<?php the_post_thumbnail( 'lm-hero', array( 'fetchpriority' => 'high' ) ); ?>
						<?php if ( wp_get_attachment_caption( get_post_thumbnail_id() ) ) : ?>
							<figcaption class="lm-single__caption">
								<?php echo esc_html( wp_get_attachment_caption( get_post_thumbnail_id() ) ); ?>
							</figcaption>
						<?php endif; ?>
					</figure>
				<?php endif; ?>
			</div>

			<div class="lm-container lm-single__layout">
				<div class="lm-single__aside">
					<?php lazymedia_toc( $lm_body['items'] ); ?>

					<div class="lm-share">
						<h2 class="lm-share__title"><?php esc_html_e( 'Bagikan', 'lazymedia' ); ?></h2>
						<ul class="lm-share__list">
							<li>
								<a class="lm-share__link" href="https://twitter.com/intent/tweet?text=<?php echo rawurlencode( get_the_title() ); ?>&url=<?php echo rawurlencode( get_permalink() ); ?>"
									target="_blank" rel="noopener noreferrer nofollow">X</a>
							</li>
							<li>
								<a class="lm-share__link" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode( get_permalink() ); ?>"
									target="_blank" rel="noopener noreferrer nofollow">Facebook</a>
							</li>
							<li>
								<a class="lm-share__link" href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo rawurlencode( get_permalink() ); ?>"
									target="_blank" rel="noopener noreferrer nofollow">LinkedIn</a>
							</li>
							<li>
								<button type="button" class="lm-share__link" data-lm-copy-link="<?php echo esc_url( get_permalink() ); ?>">
									<?php esc_html_e( 'Salin link', 'lazymedia' ); ?>
								</button>
							</li>
						</ul>
					</div>
				</div>

				<div class="lm-single__body" data-lm-article-body>
					<?php
					echo $lm_body['html']; // phpcs:ignore WordPress.Security.EscapeOutput -- filtered post content.

					wp_link_pages(
						array(
							'before' => '<nav class="lm-single__pages">',
							'after'  => '</nav>',
						)
					);
					?>
				</div>
			</div>

			<div class="lm-container">
				<?php if ( has_tag() ) : ?>
					<footer class="lm-single__footer">
						<h2 class="lm-single__footer-title"><?php esc_html_e( 'Tag', 'lazymedia' ); ?></h2>
						<ul class="lm-tags">
							<?php
							foreach ( get_the_tags() as $lm_tag ) :
								?>
								<li><a class="lm-chip" href="<?php echo esc_url( get_tag_link( $lm_tag ) ); ?>"><?php echo esc_html( $lm_tag->name ); ?></a></li>
								<?php
							endforeach;
							?>
						</ul>
					</footer>
				<?php endif; ?>

				<?php
				$lm_credit = lazymedia_photo_credit( (int) get_post_thumbnail_id() );

				if ( $lm_credit ) :
					?>
					<p class="lm-photo-credit">
						<?php esc_html_e( 'Foto:', 'lazymedia' ); ?>
						<?php if ( $lm_credit['creator'] ) : ?>
							<a href="<?php echo esc_url( $lm_credit['source'] ); ?>" target="_blank" rel="noopener noreferrer nofollow">
								<?php echo esc_html( $lm_credit['creator'] ); ?>
							</a>
							<?php esc_html_e( 'melalui', 'lazymedia' ); ?>
						<?php endif; ?>
						<a href="<?php echo esc_url( $lm_credit['license_url'] ); ?>" target="_blank" rel="noopener noreferrer nofollow">
							<?php echo esc_html( $lm_credit['license'] ); ?>
						</a>
						<?php esc_html_e( 'lewat Openverse.', 'lazymedia' ); ?>
					</p>
					<?php
				endif;
				?>
			</div>
		</article>

		<?php
		$lm_related = lazymedia_query_related( get_the_ID(), 3 );

		if ( $lm_related ) :
			?>
			<section class="lm-block lm-block--related" style="--lm-section-accent:<?php echo esc_attr( lazymedia_section_accent( 'latest' ) ); ?>">
				<div class="lm-container">
					<div class="lm-block__head">
						<h2 class="lm-block__title"><?php esc_html_e( 'Artikel Terkait', 'lazymedia' ); ?></h2>
					</div>

					<div class="lm-grid lm-grid--3 lm-related">
						<?php
						while ( $lm_related->have_posts() ) :
							$lm_related->the_post();
							lazymedia_card(
								array(
									'variant' => 'default',
									'size'    => 'lm-card-md',
								)
							);
						endwhile;
						wp_reset_postdata();
						?>
					</div>
				</div>
			</section>
			<?php
		endif;

	endwhile;
	?>
</main>

<?php
get_footer();