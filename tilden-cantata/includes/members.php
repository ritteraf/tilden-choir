<?php
defined( 'ABSPATH' ) || exit;

/** Is the current request a singers-only page (flagged, or simply password-protected)? */
function tcc_is_members_request(): bool {
	if ( ! is_singular() ) {
		return false;
	}
	$id = get_queried_object_id();
	return (bool) get_post_meta( $id, '_tcc_members', true ) || post_password_required( $id ) || (bool) get_post( $id )->post_password;
}

/** Remember the page password for a whole season instead of WordPress's 10 days. */
add_filter( 'post_password_expires', fn() => time() + 180 * DAY_IN_SECONDS );

/** Keep singers' pages out of search engines. */
add_filter( 'wp_robots', function ( array $robots ) {
	if ( tcc_is_members_request() ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
	}
	return $robots;
} );

/**
 * Keep singers' pages out of any page cache. This covers WordPress and most caching plugins;
 * SiteGround's Dynamic Cache additionally needs the page URL added to its exclusion list
 * (Site Tools → Speed → Caching → Dynamic Cache → Exclude URLs).
 */
add_action( 'template_redirect', function () {
	if ( tcc_is_members_request() ) {
		nocache_headers();
		header( 'Cache-Control: private, no-cache, no-store, must-revalidate' );
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
	}
}, 1 );

/** Ask SiteGround Optimizer (if present) to skip these URLs too. */
add_filter( 'sgo_dynamic_cache_exclude', function ( array $urls ) {
	$pages = get_posts( [
		'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
		'meta_key' => '_tcc_members', 'meta_value' => '1',
	] );
	foreach ( $pages as $id ) {
		$urls[] = get_permalink( $id );
	}
	return $urls;
} );
