<?php
/**
 * Latest Articles block: the full-width feed.
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

lazymedia_block_open(
	array(
		'title'  => 'Latest Articles',
		'accent' => lazymedia_section_accent( 'latest' ),
		'class'  => 'lm-block--latest',
	)
);
?>

<div class="lm-grid lm-grid--3" data-lm-grid>
	<?php
	lazymedia_card_grid(
		lazymedia_query_latest( 6 ),
		array(
			'variant' => 'default',
			'size'    => 'lm-card-md',
		),
		lazymedia_empty_message( 'latest' )
	);
	?>
</div>

<?php lazymedia_block_close(); ?>