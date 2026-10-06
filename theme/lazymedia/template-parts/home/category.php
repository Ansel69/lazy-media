<?php
/**
 * Category block: three cards from one category.
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

$lm_category = isset( $args['category'] ) ? $args['category'] : '';

lazymedia_block_open(
	array(
		'title'  => isset( $args['title'] ) ? $args['title'] : lazymedia_section_label( $lm_category ),
		'accent' => isset( $args['accent'] ) ? $args['accent'] : lazymedia_section_accent( $lm_category ),
		'class'  => isset( $args['class'] ) ? $args['class'] : '',
		'more'   => $lm_category,
	)
);
?>

<div class="lm-grid lm-grid--3" data-lm-grid>
	<?php
	lazymedia_card_grid(
		lazymedia_query_category( $lm_category, 3 ),
		array(
			'variant' => 'default',
			'size'    => 'lm-card-md',
		),
		lazymedia_empty_message( $lm_category )
	);
	?>
</div>

<?php lazymedia_block_close(); ?>