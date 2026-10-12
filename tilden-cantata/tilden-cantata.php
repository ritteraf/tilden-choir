<?php
/**
 * Plugin Name: Choir Plugin (Tilden)
 * Description: Cantata fields, season logic, the home-page cantata stack, and members-only (Backstage) page protections for the Tilden Community Choir site.
 * Version:     2.4.0
 * Author:      Tilden Community Choir
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * License:     GPL-2.0-or-later
 * Text Domain: tilden-cantata
 */

defined( 'ABSPATH' ) || exit;

define( 'TCC_VERSION', '2.4.0' );
define( 'TCC_FILE', __FILE__ );
define( 'TCC_DIR', plugin_dir_path( __FILE__ ) );
define( 'TCC_URL', plugin_dir_url( __FILE__ ) );
define( 'TCC_CATEGORY', 'cantata' );

require_once TCC_DIR . 'includes/helpers.php';
require_once TCC_DIR . 'includes/meta.php';
require_once TCC_DIR . 'includes/state.php';
require_once TCC_DIR . 'includes/render.php';
require_once TCC_DIR . 'includes/blocks.php';
require_once TCC_DIR . 'includes/members.php';
require_once TCC_DIR . 'includes/admin.php';
require_once TCC_DIR . 'includes/cache.php';
require_once TCC_DIR . 'includes/rehearsals.php';
require_once TCC_DIR . 'includes/people.php';
require_once TCC_DIR . 'includes/contact.php';
require_once TCC_DIR . 'includes/tracks.php';
require_once TCC_DIR . 'includes/signin.php';
require_once TCC_DIR . 'includes/dashboard.php';
require_once TCC_DIR . 'includes/app.php';
require_once TCC_DIR . 'includes/score.php';

/**
 * Activation: make sure the Cantata category exists.
 */
register_activation_hook( __FILE__, function () {
	if ( ! term_exists( TCC_CATEGORY, 'category' ) ) {
		wp_insert_term( 'Cantata', 'category', [ 'slug' => TCC_CATEGORY ] );
	}
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
	wp_clear_scheduled_hook( 'tcc_daily_purge' );
} );

/**
 * Front-end assets. Loaded only where the stack or a cantata post is rendered.
 */
function tcc_enqueue_front_assets() {
	wp_enqueue_style( 'tcc-stack', TCC_URL . 'assets/stack.css', [], TCC_VERSION );
	wp_enqueue_script( 'tcc-stack', TCC_URL . 'assets/stack.js', [], TCC_VERSION, [ 'strategy' => 'defer' ] );
}
