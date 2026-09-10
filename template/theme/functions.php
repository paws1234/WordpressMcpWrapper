<?php
/**
 * Child theme bootstrap.
 *
 * Deliberately thin: it loads the parent stylesheet so Elementor's own styles keep
 * working, and nothing else. Site behaviour lives in the wp-agent-bridge plugin so it
 * survives a theme switch.
 *
 * @package __PROJECT_THEME__
 */

defined( 'ABSPATH' ) || exit;

/**
 * Load the parent theme stylesheet, then the child stylesheet after it.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		$theme  = wp_get_theme();
		$parent = $theme->parent();

		wp_enqueue_style(
			'hello-elementor-parent',
			get_template_directory_uri() . '/style.css',
			array(),
			$parent ? $parent->get( 'Version' ) : $theme->get( 'Version' )
		);

		wp_enqueue_style(
			'hello-elementor-child',
			get_stylesheet_uri(),
			array( 'hello-elementor-parent' ),
			$theme->get( 'Version' )
		);
	},
	5
);
