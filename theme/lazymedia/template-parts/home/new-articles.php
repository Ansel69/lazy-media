<?php
/**
 * New Articles block: the filter row plus a six-card grid.
 *
 * The filter is plain links carrying ?lm_tag=, so it works without JavaScript
 * and survives the static export.
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

$lm_tags   = lazymedia_filter_tags();
$lm_active = lazymedia_current_tag();
$lm_label  = 'New Articles';

if ( $lm_active ) {
	$lm_term  = get_term_by( 'slug', $lm_active, 'post_tag' );
	$lm_label = $lm_term ? $lm_term->name : $lm_label;
}

lazymedia_block_open(
	array(
		'title'  => $lm_label,
		'accent' => lazymedia_section_accent( 'new-articles' ),
		'class'  => 'lm-block--new',
	)
);
?>

<?php if ( $lm_tags ) : ?>
	<nav class="lm-filter" aria-label="<?php esc_attr_e( 'Saring artikel', 'lazymedia' ); ?>">
		<a class="lm-filter__pill<?php echo '' === $lm_active ? ' is-active' : ''; ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php esc_html_e( 'Semua', 'lazymedia' ); ?>
		</a>
		<?php foreach ( $lm_tags as $lm_tag_slug ) : ?>
			<?php $lm_tag = get_term_by( 'slug', $lm_tag_slug, 'post_tag' ); ?>
			<?php if ( ! $lm_tag ) { continue; } ?>
			<a class="lm-filter__pill<?php echo $lm_active === $lm_tag_slug ? ' is-active' : ''; ?>"
				href="<?php echo esc_url( add_query_arg( 'lm_tag', $lm_tag_slug, home_url( '/' ) ) ); ?>">
				<?php echo esc_html( $lm_tag->name ); ?>
			</a>
		<?php endforeach; ?>
	</nav>
<?php endif; ?>

<div class="lm-grid lm-grid--3" data-lm-grid>
	<?php
	lazymedia_card_grid(
		lazymedia_query_new_articles( 6, $lm_active ),
		array(
			'variant' => 'default',
			'size'    => 'lm-card-md',
		),
		lazymedia_empty_message( 'new-articles' )
	);
	?>
</div>

<?php lazymedia_block_close(); ?>