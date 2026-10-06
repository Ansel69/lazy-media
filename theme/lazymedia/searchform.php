<?php
/**
 * Search form.
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

$lm_id = 'lm-search-' . wp_rand( 1000, 9999 );
?>
<form role="search" method="get" class="lm-searchform" action="<?php echo esc_url( home_url( '/search/' ) ); ?>" data-lm-searchform>
	<label class="lm-visually-hidden" for="<?php echo esc_attr( $lm_id ); ?>">
		<?php esc_html_e( 'Cari artikel', 'lazymedia' ); ?>
	</label>

	<div class="lm-searchform__row">
		<span class="lm-searchform__icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" width="18" height="18"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="M16.5 16.5L21 21" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
		</span>

		<input type="search" id="<?php echo esc_attr( $lm_id ); ?>" class="lm-searchform__input"
			name="s"
			placeholder="<?php esc_attr_e( 'Cari artikel, kategori, atau topik…', 'lazymedia' ); ?>"
			value="<?php echo esc_attr( get_search_query() ); ?>"
			autocomplete="off" spellcheck="false">

		<button type="submit" class="lm-searchform__submit">
			<?php esc_html_e( 'Cari', 'lazymedia' ); ?>
		</button>
	</div>
</form>