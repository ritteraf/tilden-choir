<?php
defined( 'ABSPATH' ) || exit;

/** Is the current request a singers-only page (flagged, or simply password-protected)? */
function tcc_is_members_request(): bool {
	if ( ! is_singular() ) {
		return false;
	}
	return tcc_is_members_page( get_queried_object_id() );
}

/** A page is members-only when it is flagged, password-protected, or uses the theme's "Backstage page" template. */
function tcc_is_members_page( int $id ): bool {
	if ( ! $id ) {
		return false;
	}
	return (bool) get_post_meta( $id, '_tcc_members', true ) || (bool) get_post( $id )->post_password || get_page_template_slug( $id ) === 'page-backstage';
}

/**
 * One password for all of Backstage. A members-only page with no password of its own (the practice
 * page, say) is served only once the hub's password has been entered on this device; otherwise the
 * visitor is sent to the hub, which asks for it.
 */
add_action( 'template_redirect', function () {
	if ( ! is_singular() ) {
		return;
	}
	$id = get_queried_object_id();
	if ( ! tcc_is_members_page( $id ) || get_post( $id )->post_password ) {
		return;
	}
	$cur = tcc_current_id();
	$hub = $cur ? (int) tcc_meta( $cur, 'members' ) : 0;
	if ( ! $hub || $hub === $id || ! get_post( $hub )->post_password ) {
		return;
	}
	if ( post_password_required( $hub ) ) {
		wp_safe_redirect( get_permalink( $hub ), 302 );
		exit;
	}
}, 2 );

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
