<?php
/**
 * Primary navigation walker.
 *
 * Adds a class to items that have children so CSS can show a dropdown, and
 * gives sub-menus their own id so the parent link can address them with
 * aria-controls.
 *
 * @package LazyMedia
 */

defined( 'ABSPATH' ) || exit;

/**
 * Walker for the primary menu.
 */
class Lazy_Media_Nav_Walker extends Walker_Nav_Menu {

	/**
	 * Open a sub-menu.
	 *
	 * @param string   $output Menu HTML, passed by reference.
	 * @param int      $depth  Current depth.
	 * @param stdClass $args   Menu arguments.
	 * @return void
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {
		$indent  = str_repeat( "\t", $depth );
		$output .= "\n$indent<ul class=\"sub-menu lm-sub-menu\">\n";
	}

	/**
	 * Close a sub-menu.
	 *
	 * @param string   $output Menu HTML, passed by reference.
	 * @param int      $depth  Current depth.
	 * @param stdClass $args   Menu arguments.
	 * @return void
	 */
	public function end_lvl( &$output, $depth = 0, $args = null ) {
		$output .= str_repeat( "\t", $depth ) . "</ul>\n";
	}

	/**
	 * Open a menu item.
	 *
	 * @param string   $output Menu HTML, passed by reference.
	 * @param WP_Post  $item   Menu item.
	 * @param int      $depth  Current depth.
	 * @param stdClass $args   Menu arguments.
	 * @param int      $id     Menu item ID.
	 * @return void
	 */
	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$classes   = empty( $item->classes ) ? array() : (array) $item->classes;
		$classes[] = 'menu-item-' . $item->ID;

		$has_children = in_array( 'menu-item-has-children', $classes, true );

		if ( $has_children ) {
			$classes[] = 'lm-has-children';
		}

		$class_names = implode( ' ', array_filter( apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args, $depth ) ) );

		$output .= '<li class="' . esc_attr( $class_names ) . '">';

		$atts = array(
			'title'  => ! empty( $item->attr_title ) ? $item->attr_title : '',
			'target' => ! empty( $item->target ) ? $item->target : '',
			'rel'    => ! empty( $item->xfn ) ? $item->xfn : '',
			'href'   => ! empty( $item->url ) ? $item->url : '',
		);

		if ( $has_children ) {
			$atts['aria-expanded'] = 'false';
		}

		if ( in_array( 'current-menu-item', $classes, true ) || in_array( 'current-menu-ancestor', $classes, true ) ) {
			$atts['aria-current'] = 'page';
		}

		$atts      = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args, $depth );
		$attributes = '';

		foreach ( $atts as $attr => $value ) {
			if ( '' !== $value && false !== $value ) {
				$value       = ( 'href' === $attr ) ? esc_url( $value ) : esc_attr( $value );
				$attributes .= ' ' . $attr . '="' . $value . '"';
			}
		}

		$title = apply_filters( 'the_title', $item->title, $item->ID );
		$output .= '<a class="lm-nav__link"' . $attributes . '><span>' . esc_html( $title ) . '</span>';

		if ( $has_children ) {
			$output .= '<svg class="lm-nav__caret" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		}

		$output .= '</a>';
	}
}
