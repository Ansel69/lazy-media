<?php
/**
 * Front page: the homepage block registry, rendered in order.
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main class="site-main" id="lm-content">
	<?php lazymedia_render_home_blocks(); ?>
</main>

<?php
get_footer();