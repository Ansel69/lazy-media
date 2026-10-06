<?php
/**
 * Card renderers.
 *
 * One markup shape, three size variants, so a card looks the same everywhere
 * and only the grid decides how much room it gets.
 *
 * Variants:
 *   lm-card--feature  one large card with an overlaid headline
 *   lm-card           the default 16:9 card
 *   lm-card--compact  a small row: thumbnail on the left, text on the right
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render one post as a card.
 *
 * @param array $args {
 *     Optional. Card configuration.
 *
 *     @type string $variant  feature|default|compact.
 *     @type string $size     Image size to request.
 *     @type bool   $excerpt  Show the excerpt. Ignored by the feature variant.
 *     @type bool   $badge    Show the category badge over the image.
 *     @type string $class    Extra classes on the article element.
 *     @type int    $post_id  Post to render. Defaults to the current post.
 * }
 * @return void
 */
function lazymedia_card( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'variant' => 'default',
			'size'    => '',
			'excerpt' => true,
			'badge'   => true,
			'class'   => '',
			'post_id' => 0,
		)
	);

	$post_id = $args['post_id'] ? (int) $args['post_id'] : get_the_ID();

	if ( ! $post_id ) {
		return;
	}

	$variant = in_array( $args['variant'], array( 'feature', 'compact' ), true ) ? $args['variant'] : 'default';
	$size    = $args['size'] ? $args['size'] : ( 'compact' === $variant ? 'lm-thumb' : 'lm-card' );
	$classes = array( 'lm-card', 'lm-card--' . $variant );

	if ( 'feature' !== $variant && $args['excerpt'] ) {
		$classes[] = 'lm-card--excerpt';
	}

	if ( $args['class'] ) {
		$classes = array_merge( $classes, preg_split( '/\s+/', trim( $args['class'] ) ) );
	}

	$permalink = get_permalink( $post_id );
	$title     = get_the_title( $post_id );
	$terms     = get_the_category( $post_id );
	$is_video  = (bool) get_post_meta( $post_id, '_lm_featured_video', true );
	?>
	<article class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-lm-card>
		<a class="lm-card__thumb" href="<?php echo esc_url( $permalink ); ?>" tabindex="-1" aria-hidden="true">
			<?php if ( has_post_thumbnail( $post_id ) ) : ?>
				<?php
				echo get_the_post_thumbnail(
					$post_id,
					$size,
					array(
						'loading'  => 'lazy',
						'decoding' => 'async',
						'alt'      => '',
					)
				);
				?>
			<?php endif; ?>

			<?php if ( $args['badge'] && $terms ) : ?>
				<span class="lm-card__badge"><?php echo esc_html( $terms[0]->name ); ?></span>
			<?php endif; ?>

			<?php if ( $is_video ) : ?>
				<span class="lm-card__play" aria-label="<?php esc_attr_e( 'Video', 'lazymedia' ); ?>">
					<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false">
						<path d="M8 5.5v13l11-6.5-11-6.5Z" fill="currentColor"/>
					</svg>
				</span>
			<?php endif; ?>
		</a>

		<div class="lm-card__body">
			<h3 class="lm-card__title">
				<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
			</h3>

			<?php if ( 'feature' !== $variant && $args['excerpt'] ) : ?>
				<p class="lm-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt( $post_id ), 20 ) ); ?></p>
			<?php endif; ?>

			<div class="lm-card__meta">
				<time datetime="<?php echo esc_attr( get_the_date( 'c', $post_id ) ); ?>"><?php echo esc_html( get_the_date( '', $post_id ) ); ?></time>
				<span class="lm-card__dot" aria-hidden="true"></span>
				<span class="lm-card__time"><?php echo esc_html( lazymedia_read_time( $post_id ) ); ?> <?php esc_html_e( 'menit baca', 'lazymedia' ); ?></span>
			</div>
		</div>
	</article>
	<?php
}

/**
 * Render a list of cards from a query.
 *
 * @param WP_Query|null $query   Query to loop, or null when there is nothing to show.
 * @param array         $args    Arguments forwarded to lazymedia_card().
 * @param string        $empty   Message shown when the query is empty.
 * @return void
 */
function lazymedia_card_grid( $query, $args = array(), $empty = '' ) {
	if ( ! $query instanceof WP_Query || ! $query->have_posts() ) {
		if ( $empty ) {
			echo '<p class="lm-empty">' . esc_html( $empty ) . '</p>';
		}
		return;
	}

	while ( $query->have_posts() ) {
		$query->the_post();
		lazymedia_card( $args );
	}

	wp_reset_postdata();
}