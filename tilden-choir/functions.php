<?php
/**
 * Tilden Choir theme.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', function () {
	add_theme_support( 'editor-styles' );
	add_editor_style( 'style.css' );
	load_theme_textdomain( 'tilden-choir' );
} );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'tilden-choir', get_stylesheet_uri(), [], wp_get_theme()->get( 'Version' ) );
} );

add_action( 'init', function () {
	register_block_pattern_category( 'tilden-choir', [ 'label' => 'Tilden Choir' ] );
} );

/** A gentle nudge if the plugin that renders the stack isn't active. */
add_action( 'admin_notices', function () {
	if ( ! function_exists( 'tcc_render_stack' ) && current_user_can( 'activate_plugins' ) ) {
		echo '<div class="notice notice-warning"><p><strong>Cantata Theme:</strong> activate the <em>Choir Plugin</em> to get the cantata fields and the home-page stack.</p></div>';
	}
} );

/** Cantata posts get their own single template (the full entry, then the post body). */
add_filter( 'single_template_hierarchy', function ( array $templates ) {
	if ( is_singular( 'post' ) && has_category( 'cantata' ) ) {
		array_unshift( $templates, 'single-cantata' );
	}
	return $templates;
} );
