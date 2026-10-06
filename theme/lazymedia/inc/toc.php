<?php
/**
 * Table of contents for long articles.
 *
 * Built on the server so the static export ships it as plain HTML: the anchor
 * links work with JavaScript disabled, and nothing reflows after load.
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Minimum number of headings before a table of contents is worth showing.
 */
const LAZYMEDIA_TOC_MIN = 3;

/**
 * Give every h2/h3 in rendered content an id and collect them.
 *
 * Existing ids are kept, so an editor's hand-written anchor never changes.
 * Generated ids are slugs of the heading text, de-duplicated with a suffix.
 *
 * @param string $html Rendered post content.
 * @return array{html:string,items:array<int,array{id:string,text:string,level:int}>}
 */
function lazymedia_toc_extract( $html ) {
	$items = array();
	$used  = array();

	$html = preg_replace_callback(
		'#<h([23])(\s[^>]*)?>(.*?)</h\1>#is',
		function ( $m ) use ( &$items, &$used ) {
			$level = (int) $m[1];
			$attrs = isset( $m[2] ) ? $m[2] : '';
			$text  = trim( wp_strip_all_tags( $m[3] ) );

			if ( '' === $text ) {
				return $m[0];
			}

			if ( preg_match( '/\sid=(["\'])(.*?)\1/i', $attrs, $id_match ) ) {
				$id = $id_match[2];
			} else {
				$base = sanitize_title( $text );
				$base = $base ? $base : 'bagian';
				$id   = $base;
				$n    = 2;

				while ( isset( $used[ $id ] ) ) {
					$id = $base . '-' . $n++;
				}

				$attrs .= ' id="' . esc_attr( $id ) . '"';
			}

			$used[ $id ] = true;
			$items[]     = array(
				'id'    => $id,
				'text'  => $text,
				'level' => $level,
			);

			return '<h' . $level . $attrs . '>' . $m[3] . '</h' . $level . '>';
		},
		$html
	);

	return array(
		'html'  => $html,
		'items' => $items,
	);
}

/**
 * Print the table of contents, or nothing for short articles.
 *
 * @param array<int,array{id:string,text:string,level:int}> $items Headings.
 * @return void
 */
function lazymedia_toc( $items ) {
	if ( count( $items ) < LAZYMEDIA_TOC_MIN ) {
		return;
	}
	?>
	<nav class="lm-toc" aria-labelledby="lm-toc-title" data-lm-toc>
		<h2 class="lm-toc__title" id="lm-toc-title"><?php esc_html_e( 'Daftar Isi', 'lazymedia' ); ?></h2>
		<ol class="lm-toc__list">
			<?php foreach ( $items as $item ) : ?>
				<li class="lm-toc__item lm-toc__item--h<?php echo (int) $item['level']; ?>">
					<a class="lm-toc__link" href="#<?php echo esc_attr( $item['id'] ); ?>"><?php echo esc_html( $item['text'] ); ?></a>
				</li>
			<?php endforeach; ?>
		</ol>
	</nav>
	<?php
}
