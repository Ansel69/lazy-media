<?php
/**
 * Homepage hero: a sliding feature column beside a rail of smaller stories.
 *
 * The slides and the rail come from the same query, so the hero can never show
 * the same post twice.
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

$lm_hero = lazymedia_query_hero( 6 );

if ( $lm_hero ) {
	$lm_posts   = $lm_hero->posts;
	$lm_slides  = array_slice( $lm_posts, 0, 3 );
	$lm_rail    = array_slice( $lm_posts, 3, 3 );
	$lm_slide_0 = $lm_slides[0]->ID;
} else {
	$lm_slides = array();
	$lm_rail   = array();
}
?>
<section class="lm-block lm-block--hero" style="--lm-section-accent:<?php echo esc_attr( lazymedia_section_accent( 'hero' ) ); ?>" data-lm-hero>
	<div class="lm-container">
		<div class="lm-hero">
			<?php if ( $lm_slides ) : ?>
				<div class="lm-hero__main">
					<div class="lm-hero__track" data-lm-hero-track>
						<?php foreach ( $lm_slides as $lm_slide ) : ?>
							<div class="lm-hero__slide" role="group" aria-roledescription="slide">
								<?php
								lazymedia_card(
									array(
										'variant' => 'feature',
										'size'    => 'lm-hero',
										'excerpt' => false,
										'post_id' => $lm_slide->ID,
									)
								);
								?>
							</div>
						<?php endforeach; ?>
					</div>

					<?php if ( count( $lm_slides ) > 1 ) : ?>
						<div class="lm-hero__dots" role="tablist" aria-label="<?php esc_attr_e( 'Sorotan', 'lazymedia' ); ?>">
							<?php foreach ( $lm_slides as $lm_i => $lm_slide ) : ?>
								<button type="button" class="lm-hero__dot<?php echo 0 === $lm_i ? ' is-active' : ''; ?>"
									role="tab" aria-selected="<?php echo 0 === $lm_i ? 'true' : 'false'; ?>"
									aria-label="<?php echo esc_attr( sprintf( '%s: %s', __( 'Sorotan', 'lazymedia' ), $lm_i + 1 ) ); ?>"
									data-lm-hero-dot="<?php echo esc_attr( $lm_i ); ?>"></button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( $lm_rail ) : ?>
					<div class="lm-hero__rail">
						<?php foreach ( $lm_rail as $lm_rail_post ) : ?>
							<?php
							lazymedia_card(
								array(
									'variant' => 'compact',
									'size'    => 'lm-thumb',
									'excerpt' => false,
									'post_id' => $lm_rail_post->ID,
								)
							);
							?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			<?php else : ?>
				<p class="lm-empty"><?php echo esc_html( lazymedia_empty_message( 'hero' ) ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</section>