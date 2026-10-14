<?php
defined( 'ABSPATH' ) || exit;

/* =====================================================================
 * CD marks: where each circled CD number in the score starts, as a time in that song's
 * whole-song tracks (the accompaniment and the voice parts are aligned, so one set of
 * times serves them all). The director taps them in, like page turns. Until a song has
 * been tapped, its marks come from the old sectioned accompaniment files: each section's
 * CD number and length add up to where it starts in the song.
 * ===================================================================== */

/** [ song => [ [ 'n' => CD number, 't' => seconds into the song ], … ] ], soonest first. */
function tcc_cd_marks( int $cantata_id ): array {
	$stored = (array) get_post_meta( $cantata_id, '_tcc_cdmarks', true );
	$out    = [];
	foreach ( $stored as $song => $list ) {
		$out[ (int) $song ] = array_values( array_filter( (array) $list, 'is_array' ) );
	}
	$seeded = false;
	foreach ( tcc_cd_marks_from_sections( $cantata_id ) as $song => $list ) {
		if ( ! isset( $out[ $song ] ) ) {
			$out[ $song ] = $list;
			$seeded       = true;
		}
	}
	ksort( $out );
	/* keep the seeded marks, so the sectioned files can be deleted without losing them */
	if ( $seeded ) {
		update_post_meta( $cantata_id, '_tcc_cdmarks', $out );
	}
	return $out;
}

/** Starting marks worked out from the sectioned accompaniment files (number + length, in order). */
function tcc_cd_marks_from_sections( int $cantata_id ): array {
	$tracks = function_exists( 'tcc_tracks' ) ? ( tcc_tracks( $cantata_id )['sections'] ?? [] ) : [];
	$out = []; $at = [];
	foreach ( $tracks as $t ) {
		$song = (int) $t['song'];
		if ( $song < 0 ) {
			continue;
		}
		$meta = wp_get_attachment_metadata( $t['id'] ) ?: [];
		$n    = (int) ( $meta['track_number'] ?? 0 ) ?: ( preg_match( '/(?:track|^)\s*0*(\d{1,3})\b/i', get_the_title( $t['id'] ), $mm ) ? (int) $mm[1] : 0 );
		$len  = (float) ( $meta['length'] ?? 0 );
		$t0   = $at[ $song ] ?? 0.0;
		if ( $n > 0 ) {
			$out[ $song ][] = [ 'n' => $n, 't' => round( $t0, 1 ) ];
		}
		$at[ $song ] = $t0 + $len;
	}
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
		$all = (array) get_post_meta( $cantata, '_tcc_cdmarks', true );
		$all[ $song ] = $marks; // an empty list on purpose means "no CDs in this song"
		update_post_meta( $cantata, '_tcc_cdmarks', $all );
		return [ 'ok' => true, 'marks' => $marks ];
	} ] );
} );
