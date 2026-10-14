<?php
defined( 'ABSPATH' ) || exit;

/* =====================================================================
 * Pencil marks on the score.
 *
 * A stroke is a line drawn on one page of the score PDF: [ 'id', 'pg' (PDF page, from 1),
 * 'w' (pen width as a share of the page width), 'pts' => [ [ x, y ], … ] (shares of the page's
 * width and height, so a mark sits on the same notehead on any screen or zoom) ].
 *
 * The director's strokes live on the cantata and carry 'parts' (who sees them, like notes:
 * none = everyone; voice parts and "team:<slug>"). A singer's own strokes live on their person
 * record, one list per cantata, and nobody else sees them.
 * ===================================================================== */

const TCC_MAX_STROKES = 4000;

/** One stroke from the browser, cleaned; null if it isn't one. */
function tcc_clean_stroke( $s, bool $director ): ?array {
	if ( ! is_array( $s ) ) {
		return null;
	}
	$id = preg_replace( '/[^a-zA-Z0-9]/', '', (string) ( $s['id'] ?? '' ) );
	$pg = (int) ( $s['pg'] ?? 0 );
	if ( $id === '' || strlen( $id ) > 16 || $pg < 1 || $pg > 2000 ) {
		return null;
	}
	$pts = [];
	foreach ( array_slice( (array) ( $s['pts'] ?? [] ), 0, 3000 ) as $p ) {
		if ( is_array( $p ) && count( $p ) >= 2 ) {
			$pts[] = [ round( min( 1.2, max( -0.2, (float) $p[0] ) ), 4 ), round( min( 1.2, max( -0.2, (float) $p[1] ) ), 4 ) ];
		}
	}
	if ( ! $pts ) {
		return null;
	}
	$out = [ 'id' => $id, 'pg' => $pg, 'w' => round( min( 0.05, max( 0.0005, (float) ( $s['w'] ?? 0.004 ) ) ), 5 ), 'pts' => $pts ];
	if ( $director ) {
		$out['parts'] = function_exists( 'tcc_cue_parts' ) ? tcc_cue_parts( $s['parts'] ?? [] ) : [];
	}
	return $out;
}

/** Apply a batch (new strokes, ids to remove) to a stroke list. */
function tcc_marks_apply( array $list, $add, $del, bool $director ): array {
	$del = array_flip( array_map( 'strval', (array) ( is_string( $del ) ? json_decode( $del, true ) : $del ) ) );
	$by  = [];
	foreach ( $list as $s ) {
		if ( is_array( $s ) && isset( $s['id'] ) && ! isset( $del[ $s['id'] ] ) ) {
			$by[ $s['id'] ] = $s;
		}
	}
	foreach ( (array) ( is_string( $add ) ? json_decode( $add, true ) : $add ) as $s ) {
		$c = tcc_clean_stroke( $s, $director );
		if ( $c && ! isset( $del[ $c['id'] ] ) ) {
			$by[ $c['id'] ] = $c;
		}
	}
	return array_slice( array_values( $by ), -TCC_MAX_STROKES );
}

/** The director's strokes for a cantata. */
function tcc_marks( int $cantata_id ): array {
	$m = get_post_meta( $cantata_id, '_tcc_marks', true );
	return is_array( $m ) ? array_values( $m ) : [];
}

/** One person's own strokes for a cantata. */
function tcc_my_marks( int $person_id, int $cantata_id ): array {
	$m = $person_id ? get_post_meta( $person_id, '_tcc_marks_' . $cantata_id, true ) : [];
	return is_array( $m ) ? array_values( $m ) : [];
}

add_action( 'rest_api_init', function () {
	/* the director's pencil */
	register_rest_route( 'tcc/v1', '/marks', [ 'methods' => 'POST', 'permission_callback' => fn() => current_user_can( 'edit_posts' ), 'callback' => function ( WP_REST_Request $r ) {
		$cantata = (int) $r->get_param( 'cantata' );
		if ( ! $cantata || get_post_type( $cantata ) !== 'post' ) {
			return new WP_Error( 'bad', 'Missing cantata', [ 'status' => 400 ] );
		}
		$list = tcc_marks_apply( tcc_marks( $cantata ), $r->get_param( 'add' ), $r->get_param( 'del' ), true );
		update_post_meta( $cantata, '_tcc_marks', $list );
		return [ 'ok' => true, 'n' => count( $list ) ];
	} ] );
	/* a singer's own pencil: only ever their own list */
	register_rest_route( 'tcc/v1', '/mymarks', [ 'methods' => 'POST', 'permission_callback' => fn( WP_REST_Request $r ) => (bool) tcc_rest_cantata( $r ), 'callback' => function ( WP_REST_Request $r ) {
		$cantata = tcc_rest_cantata( $r );
		$who     = tcc_who();
		if ( ! $who ) {
			return [ 'ok' => false ];
		}
		$list = tcc_marks_apply( tcc_my_marks( $who, $cantata ), $r->get_param( 'add' ), $r->get_param( 'del' ), false );
		update_post_meta( $who, '_tcc_marks_' . $cantata, $list );
		return [ 'ok' => true, 'n' => count( $list ) ];
	} ] );
} );
