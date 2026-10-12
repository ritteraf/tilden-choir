<?php
defined( 'ABSPATH' ) || exit;

/**
 * All published cantata posts, newest performance first.
 * Posts without a performance date sort by their publish date.
 */
function tcc_get_cantatas(): array {
	$posts = get_posts( [
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'category_name'  => TCC_CATEGORY,
		'no_found_rows'  => true,
	] );
	usort( $posts, function ( $a, $b ) {
		$da = tcc_performance_dt( $a->ID );
		$db = tcc_performance_dt( $b->ID );
		$ta = $da ? $da->getTimestamp() : strtotime( $a->post_date_gmt );
		$tb = $db ? $db->getTimestamp() : strtotime( $b->post_date_gmt );
		return $tb <=> $ta;
	} );
	return $posts;
}

/** The newest cantata: the only one that can be anything other than "past". */
function tcc_current_id(): int {
	$all = tcc_get_cantatas();
	return $all ? (int) $all[0]->ID : 0;
}

/**
 * Season state for a post, decided entirely by its two dates:
 *   recruiting  – up to and including the "Rehearsals start" date
 *   upcoming    – the day after that, through the day before the performance
 *   live        – the performance date, all day
 *   performed   – the 14 days after the performance
 *   past        – everything later, and every older cantata
 */
function tcc_state( int $post_id, ?int $current_id = null ): string {
	$current_id = $current_id ?? tcc_current_id();
	if ( $post_id !== $current_id ) {
		return 'past';
	}
	$override = tcc_meta( $post_id, 'override', 'auto' );
	if ( $override === 'past' ) {
		return 'past';
	}
	if ( $override === 'upcoming' ) {
		return 'upcoming';
	}
	$perf = tcc_performance_dt( $post_id );
	if ( ! $perf ) {
		return 'upcoming';
	}
	$today    = new DateTimeImmutable( 'today', wp_timezone() );
	$perf_day = $perf->setTime( 0, 0 );
	$reh      = tcc_rehearsals_dt( $post_id );

	if ( $today < $perf_day ) {
		if ( $reh && $today <= $reh->setTime( 0, 0 ) ) {
			return 'recruiting';
		}
		return 'upcoming';
	}
	if ( $today == $perf_day ) {
		return 'live';
	}
	$days_after = (int) $perf_day->diff( $today )->days;
	return $days_after <= 14 ? 'performed' : 'past';
}

/** Days from now until the performance (0 on the day). */
function tcc_days_until( int $post_id ): ?int {
	$perf = tcc_performance_dt( $post_id );
	if ( ! $perf ) {
		return null;
	}
	$now = new DateTimeImmutable( 'today', wp_timezone() );
	$diff = $now->diff( $perf->setTime( 0, 0 ) );
	return $diff->invert ? 0 : (int) $diff->days;
}

/** Google Calendar "add" link for the performance. */
function tcc_calendar_url( int $post_id ): string {
	$perf = tcc_performance_dt( $post_id );
	if ( ! $perf ) {
		return '';
	}
	$start = $perf->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Ymd\THis\Z' );
	$end   = $perf->modify( '+90 minutes' )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Ymd\THis\Z' );
	$venue = tcc_meta( $post_id, 'venue', tcc_option( 'venue' ) );
	$addr  = tcc_option( 'address' );
	return add_query_arg( [
		'action'   => 'TEMPLATE',
		'text'     => rawurlencode( get_the_title( $post_id ) . ' — ' . get_bloginfo( 'name' ) ),
		'dates'    => $start . '/' . $end,
		'location' => rawurlencode( trim( $venue . ', ' . $addr, ', ' ) ),
		'details'  => rawurlencode( get_permalink( $post_id ) ),
	], 'https://calendar.google.com/calendar/render' );
}

/**
 * /singers/ → this year's singers page.
 * No rewrite rule: if a page actually lives at /singers/ WordPress serves it, and we only redirect
 * when that page isn't the one set on this year's cantata (or when nothing lives there at all).
 */
add_action( 'template_redirect', function () {
	$path = trim( (string) parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	$base = trim( (string) parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	if ( $base !== '' && str_starts_with( $path, $base ) ) {
		$path = trim( substr( $path, strlen( $base ) ), '/' );
	}
	if ( ! in_array( $path, [ 'backstage', 'singers' ], true ) ) {
		return; // both spellings lead to the members page
	}
	$cur  = tcc_current_id();
	$page = $cur ? (int) tcc_meta( $cur, 'members' ) : 0;
	if ( ! $page || get_post_status( $page ) !== 'publish' ) {
		if ( is_404() ) {
			wp_safe_redirect( home_url( '/' ), 302 );
			exit;
		}
		return;
	}
	if ( is_page( $page ) ) {
		return; // the members page lives at this address itself
	}
	$target = get_permalink( $page );
	$target_path = trim( (string) parse_url( $target, PHP_URL_PATH ), '/' );
	if ( $base !== '' && str_starts_with( $target_path, $base ) ) {
		$target_path = trim( substr( $target_path, strlen( $base ) ), '/' );
	}
	if ( $target_path === $path ) {
		// We are already at the members page's address but WordPress didn't resolve it (usually a stale
		// rewrite rule). Serve the page directly rather than redirecting to ourselves.
		global $wp_query;
		$wp_query = new WP_Query( [ 'page_id' => $page ] );
		$wp_query->is_page = true;
		$wp_query->is_singular = true;
		$wp_query->is_404 = false;
		status_header( 200 );
		return;
	}
	wp_safe_redirect( $target, 302 );
	exit;
}, 1 );

/** Flush stored rewrite rules once per plugin version, so removed rules don't linger. */
add_action( 'init', function () {
	if ( get_option( 'tcc_rules_version' ) !== TCC_VERSION ) {
		flush_rewrite_rules( false );
		update_option( 'tcc_rules_version', TCC_VERSION, false );
	}
}, 99 );
