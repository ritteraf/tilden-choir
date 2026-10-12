<?php
defined( 'ABSPATH' ) || exit;

/**
 * The home page is assembled from every cantata post, so a change to any of them, or to the
 * settings, must clear the cached front page. SiteGround's Dynamic Cache does not do this on
 * its own; the SiteGround Optimizer plugin exposes purge functions when it is installed.
 */
function tcc_purge_home() {
	delete_transient( 'tcc_stack' );
	$home = home_url( '/' );
	if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
		sg_cachepress_purge_cache( $home );
	}
	if ( function_exists( 'sg_cachepress_purge_everything' ) ) {
		sg_cachepress_purge_everything();
	}
	do_action( 'tcc_purged_home', $home );
}

add_action( 'save_post_post', function ( int $post_id, WP_Post $post ) {
	if ( wp_is_post_revision( $post_id ) || $post->post_status === 'auto-draft' ) {
		return;
	}
	if ( has_category( TCC_CATEGORY, $post_id ) ) {
		tcc_purge_home();
	}
}, 20, 2 );

add_action( 'update_option_tcc_settings', 'tcc_purge_home' );
add_action( 'update_option_tcc_wording', 'tcc_purge_home' );
add_action( 'deleted_post', 'tcc_purge_home' );
add_action( 'trashed_post', 'tcc_purge_home' );

/** Once a day, clear the front page so the countdown and season state never go stale. */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'tcc_daily_purge' ) ) {
		wp_schedule_event( strtotime( 'tomorrow 00:05' ) ?: time() + DAY_IN_SECONDS, 'daily', 'tcc_daily_purge' );
	}
} );
add_action( 'tcc_daily_purge', 'tcc_purge_home' );
