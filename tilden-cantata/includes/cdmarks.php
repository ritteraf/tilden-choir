<?php
defined( 'ABSPATH' ) || exit;

/* =====================================================================
 * CD marks: where each circled CD number in the score starts, as a time in that song's
 * whole-song tracks (the accompaniment, all-voices and voice-part tracks are aligned, so
 * one set of times serves them all). The director taps them in, like page turns.
 * ===================================================================== */

/** [ song => [ [ 'n' => CD number, 't' => seconds into the song ], … ] ], soonest first. */
function tcc_cd_marks( int $cantata_id ): array {
	$stored = get_post_meta( $cantata_id, '_tcc_cdmarks', true );
	$out    = [];
	foreach ( is_array( $stored ) ? $stored : [] as $song => $list ) {
		$out[ (int) $song ] = array_values( array_filter( (array) $list, 'is_array' ) );
	}
	ksort( $out );
	return $out;
}

add_action( 'rest_api_init', function () {
	/* the director saves one song's marks */
	register_rest_route( 'tcc/v1', '/cdmarks', [ 'methods' => 'POST', 'permission_callback' => fn() => current_user_can( 'edit_posts' ), 'callback' => function ( WP_REST_Request $r ) {
		$cantata = (int) $r->get_param( 'cantata' );
		$song    = (int) $r->get_param( 'song' );
		if ( ! $cantata || get_post_type( $cantata ) !== 'post' || $song < 0 ) {
			return new WP_Error( 'bad', 'Missing cantata or song', [ 'status' => 400 ] );
		}
		$marks = [];
		foreach ( (array) $r->get_param( 'marks' ) as $m ) {
			$n = (int) ( $m['n'] ?? 0 );
			if ( $n > 0 && $n < 1000 ) {
				$marks[] = [ 'n' => $n, 't' => round( max( 0, (float) ( $m['t'] ?? 0 ) ), 1 ) ];
			}
		}
		usort( $marks, fn( $a, $b ) => $a['t'] <=> $b['t'] );
		$all = get_post_meta( $cantata, '_tcc_cdmarks', true );
		$all = is_array( $all ) ? $all : [];
		$all[ $song ] = $marks; // an empty list on purpose means "no CDs in this song"
		update_post_meta( $cantata, '_tcc_cdmarks', $all );
		return [ 'ok' => true, 'marks' => $marks ];
	} ] );
} );
