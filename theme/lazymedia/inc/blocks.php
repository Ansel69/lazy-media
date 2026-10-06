<?php
/**
 * Homepage block registry and shared block markup.
 *
 * The homepage is a list of sections. Their order, titles, accents and which
 * template part draws them live here, so the page's composition can be read in
 * one place instead of being spread across template files.
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sections on the homepage, in display order.
 *
 * @return array[]
 */
function lazymedia_home_blocks() {
	$blocks = array(
		array(
			'key'     => 'hero',
			'title'   => '',
			'part'    => 'hero',
			'accent'  => '#FF7E36',
			'class'   => 'lm-block--hero',
			'more'    => '',
			'context' => 'hero',
		),
		array(
			'key'     => 'new-articles',
			'title'   => 'New Articles',
			'part'    => 'new-articles',
			'accent'  => '#FF7E36',
			'class'   => 'lm-block--new',
			'more'    => '',
			'context' => 'new-articles',
		),
		array(
			'key'      => 'cat-games',
			'title'    => 'Games',
			'part'     => 'category',
			'accent'   => '#C203C6',
			'class'    => 'lm-block--cat-games',
			'category' => 'games',
			'more'     => 'games',
			'context'  => 'games',
		),
		array(
			'key'      => 'cat-hardware',
			'title'    => 'Hardware',
			'part'     => 'category',
			'accent'   => '#36FFF3',
			'class'    => 'lm-block--cat-hardware',
			'category' => 'hardware',
			'more'     => 'hardware',
			'context'  => 'hardware',
		),
		array(
			'key'      => 'cat-entertainment',
			'title'    => 'Entertainment',
			'part'     => 'category',
			'accent'   => '#BFFA17',
			'class'    => 'lm-block--cat-entertainment',
			'category' => 'entertainment',
			'more'     => 'entertainment',
			'context'  => 'entertainment',
		),
		array(
			'key'     => 'latest',
			'title'   => 'Latest Articles',
			'part'    => 'latest',
			'accent'  => '#FF36EB',
			'class'   => 'lm-block--latest',
			'more'    => '',
			'context' => 'latest',
		),
	);

	/**
	 * Filter the homepage block registry.
	 *
	 * @param array $blocks Block definitions, in display order.
	 */
	return apply_filters( 'lazymedia_home_blocks', $blocks );
}

/**
 * Render every homepage block, in order.
 *
 * @return void
 */
function lazymedia_render_home_blocks() {
	foreach ( lazymedia_home_blocks() as $block ) {
		get_template_part( 'template-parts/home/' . $block['part'], null, $block );
	}
}

/**
 * Open a block: section wrapper, heading row, neon divider.
 *
 * The divider under each heading is the site's signature, so the accent colour
 * is written into an inline custom property that the stylesheet consumes.
 *
 * @param array $args {
 *     Block definition from lazymedia_home_blocks().
 *
 *     @type string $title   Section heading. Empty renders no heading.
 *     @type string $accent  Hex colour for the divider.
 *     @type string $class   Extra class on the section element.
 *     @type string $more    Category slug for a "see all" link, or empty.
 * }
 * @return void
 */
function lazymedia_block_open( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'title'  => '',
			'accent' => lazymedia_section_accent( 'new-articles' ),
			'class'  => '',
			'more'   => '',
		)
	);

	$classes = array( 'lm-block' );
	if ( $args['class'] ) {
		$classes[] = $args['class'];
	}
	?>
	<section class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" style="--lm-section-accent:<?php echo esc_attr( $args['accent'] ); ?>">
		<div class="lm-container">
	<?php

	if ( '' !== $args['title'] ) {
		?>
			<div class="lm-block__head">
				<h2 class="lm-block__title"><?php echo esc_html( $args['title'] ); ?></h2>
				<?php if ( $args['more'] ) : ?>
					<a class="lm-block__more" href="<?php echo esc_url( lazymedia_category_url( $args['more'] ) ); ?>">
						<?php esc_html_e( 'Lihat semua', 'lazymedia' ); ?>
						<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</a>
				<?php endif; ?>
			</div>
		<?php
	}
}

/**
 * Close a block.
 *
 * @return void
 */
function lazymedia_block_close() {
	echo '</div></section>';
}

/**
 * Section heading row, for templates outside the block registry.
 *
 * @param string $title  Heading text.
 * @param string $accent Hex colour for the divider.
 * @param string $more   Optional URL for a trailing link.
 * @return void
 */
function lazymedia_section_head( $title, $accent = '', $more = '' ) {
	$accent = $accent ? $accent : lazymedia_section_accent( 'latest' );
	?>
	<div class="lm-block__head">
		<h2 class="lm-block__title"><?php echo esc_html( $title ); ?></h2>
		<?php if ( $more ) : ?>
			<a class="lm-block__more" href="<?php echo esc_url( $more ); ?>">
				<?php esc_html_e( 'Lihat semua', 'lazymedia' ); ?>
				<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path d="M9 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</a>
		<?php endif; ?>
	</div>
	<?php
}