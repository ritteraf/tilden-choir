<?php
defined( 'ABSPATH' ) || exit;

/* =====================================================================
 * Backstage as a home-screen app: a web app manifest, the icons, and the
 * head tags phones look for. "Add to Home Screen" then gives singers a
 * Choir icon that opens Backstage full screen, like an app.
 * ===================================================================== */

/** The manifest, served at /?tcc_manifest=1 so no rewrite rules are needed. */
add_action( 'init', function () {
	if ( ! isset( $_GET['tcc_manifest'] ) ) {
		return;
	}
	$cur = tcc_current_id();
	$hub = $cur ? (int) tcc_meta( $cur, 'members' ) : 0;
	$start = $hub ? wp_make_link_relative( get_permalink( $hub ) ) : '/backstage/';
	$icons = TCC_URL . 'assets/icons/';
	nocache_headers();
	header( 'Content-Type: application/manifest+json; charset=utf-8' );
	echo wp_json_encode( [
		'id'               => $start,
		'name'             => get_bloginfo( 'name' ) . ' — Backstage',
		'short_name'       => 'Tilden Community Choir',
		'description'      => 'Rehearsals, practice tracks and notes for this year\'s cantata.',
		'start_url'        => $start,
		'scope'            => '/',
		'display'          => 'standalone',
		'orientation'      => 'any',
		'background_color' => '#ffffff',
		'theme_color'      => '#1f1f1f',
		'icons'            => [
			[ 'src' => $icons . 'icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any' ],
			[ 'src' => $icons . 'icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any' ],
			[ 'src' => $icons . 'icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' ],
		],
	], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	exit;
}, 1 );

/** On Backstage pages: the manifest, the icon, full-screen hints, and a viewport that reaches the screen edges. */
add_action( 'wp', function () {
	if ( ! function_exists( 'tcc_is_members_request' ) || ! tcc_is_members_request() ) {
		return;
	}
	remove_action( 'wp_head', '_block_template_viewport_meta_tag', 0 );
	add_action( 'wp_head', function () {
		$icons = TCC_URL . 'assets/icons/';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">' . "\n";
		echo '<link rel="manifest" href="' . esc_url( home_url( '/?tcc_manifest=1' ) ) . '">' . "\n";
		echo '<link rel="apple-touch-icon" href="' . esc_url( $icons . 'apple-touch-icon.png' ) . '">' . "\n";
		echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="apple-mobile-web-app-title" content="Tilden Community Choir">' . "\n";
		echo '<meta name="apple-mobile-web-app-status-bar-style" content="black">' . "\n";
		echo '<meta name="theme-color" content="#1f1f1f">' . "\n";
	}, 0 );
} );
