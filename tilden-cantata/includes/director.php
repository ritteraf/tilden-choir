<?php
defined( 'ABSPATH' ) || exit;

/* =====================================================================
 * The director's report: how the cantata is coming, one practice week at a time.
 * A week runs from one rehearsal to the next. Built from the practice event log
 * (listening, feedback marks, loops and rewinds), with the per-person totals table
 * filling in for anything marked before the log existed.
 * ===================================================================== */

const TCC_SECTIONS = [ 'soprano', 'alto', 'tenor', 'bass' ];
const TCC_HEAT_BIN = 2; // seconds per heat-map cell

/** The singers on this season, by the track parts they'd practice (a Mezzo counts for Soprano and Alto). */
function tcc_director_roster( int $cantata_id ): array {
	$groups  = function_exists( 'tcc_people_for_cantata' ) ? tcc_people_for_cantata( $cantata_id ) : [];
	$singers = $groups['singers'] ?? [];
	$voices  = tcc_voice_parts();
	$by_voice = array_fill_keys( array_keys( $voices ), [] );
	$by_voice['unknown'] = [];
	$section = array_fill_keys( TCC_SECTIONS, [] );
	foreach ( $singers as $id ) {
		$v = (string) get_post_meta( $id, '_tcc_part', true );
		$by_voice[ isset( $voices[ $v ] ) ? $v : 'unknown' ][] = $id;
		foreach ( $v ? explode( '-', $v ) : [] as $p ) {
			if ( isset( $section[ $p ] ) ) { $section[ $p ][] = $id; }
		}
	}
	$names = [];
	foreach ( $singers as $id ) { $names[ $id ] = get_the_title( $id ); }
	return [ 'singers' => $singers, 'byVoice' => $by_voice, 'section' => $section, 'names' => $names ];
}

/** Practice weeks: before the first rehearsal, then rehearsal to rehearsal, up to now. Newest first. */
function tcc_director_weeks( int $cantata_id ): array {
	$tz  = wp_timezone();
	$now = new DateTimeImmutable( 'now', $tz );
	$dts = [];
	foreach ( tcc_get_rehearsals( $cantata_id ) as $r ) {
		$dt = tcc_rehearsal_dt( $r->ID );
		if ( $dt ) { $dts[] = [ 'dt' => $dt, 'title' => get_the_title( $r ) ]; }
	}
	$weeks = [];
	$start = null;
	foreach ( $dts as $i => $d ) {
		if ( $d['dt'] > $now ) {
			$weeks[] = [ 'from' => $start, 'to' => $d['dt'], 'current' => true, 'next' => $d ];
			break;
		}
		$weeks[] = [ 'from' => $start, 'to' => $d['dt'], 'current' => false, 'next' => $d ];
		$start = $d['dt'];
	}
	if ( $start && ( ! $weeks || ! end( $weeks )['current'] ) ) {
		$weeks[] = [ 'from' => $start, 'to' => null, 'current' => true, 'next' => null ];
	}
	if ( ! $weeks ) {
		$weeks[] = [ 'from' => null, 'to' => null, 'current' => true, 'next' => null ];
	}
	$out = [];
	foreach ( $weeks as $i => $w ) {
		$f = $w['from']; $t = $w['to'];
		if ( ! $f ) { $label = $t ? 'Before ' . $t->format( 'M j' ) : 'So far'; }
		elseif ( $w['current'] ) { $label = 'This week · since ' . $f->format( 'M j' ); }
		else { $label = $f->format( 'M j' ) . ' – ' . ( $t ? $t->format( 'M j' ) : 'now' ); }
		$out[] = [
			'key'     => 'w' . $i,
			'label'   => $label,
			'short'   => ! $f ? ( $t ? 'Before ' . $t->format( 'M j' ) : 'So far' ) : ( $w['current'] ? 'This week' : $f->format( 'M j' ) . ' – ' . ( $t ? $t->format( 'M j' ) : 'now' ) ),
			'from'    => $f ? $f->format( 'Y-m-d H:i:s' ) : '1970-01-01 00:00:00',
			'to'      => $t && ! $w['current'] ? $t->format( 'Y-m-d H:i:s' ) : $now->format( 'Y-m-d H:i:s' ),
			'current' => $w['current'],
			'nextOn'  => $w['next'] ? $w['next']['dt']->format( 'l, M j' ) : '',
		];
	}
	return array_reverse( $out );
}

/** Song lengths in seconds, from the voice-part tracks. */
function tcc_director_lengths( int $cantata_id, int $count ): array {
	$len = array_fill( 0, $count, 0 );
	foreach ( tcc_tracks( $cantata_id ) as $part => $list ) {
		if ( ! in_array( $part, TCC_SECTIONS, true ) ) { continue; }
		foreach ( $list as $t ) {
			if ( $t['song'] < 0 || $t['song'] >= $count ) { continue; }
			$m = wp_get_attachment_metadata( $t['id'] ) ?: [];
			$len[ $t['song'] ] = max( $len[ $t['song'] ], (int) ( $m['length'] ?? 0 ) );
		}
	}
	return $len;
}

/** Everything the page shows, for one week (or the whole season). */
function tcc_director_report( int $cantata_id, array $week ): array {
	global $wpdb;
	$E = tcc_events_table();
	$from = $week['from']; $to = $week['to'];
	$songs = tcc_song_titles( $cantata_id );
	$count = count( $songs );

	/* listening: who practiced which song in which part, and for how long */
	$listen = $wpdb->get_results( $wpdb->prepare( "SELECT person_id, song, part, SUM(b) secs, SUM(v = 'done') done FROM $E WHERE cantata_id = %d AND kind = 'listen' AND created >= %s AND created < %s GROUP BY person_id, song, part", $cantata_id, $from, $to ), ARRAY_A );
	$grid = []; $active = []; $minutes = 0;
	foreach ( $listen as $r ) {
		$s = (int) $r['song']; $p = $r['part']; $pid = (int) $r['person_id'];
		if ( $s < 0 ) { continue; }
		$grid[ $s ][ $p ]['people'][ $pid ] = true;
		$grid[ $s ][ $p ]['secs'] = ( $grid[ $s ][ $p ]['secs'] ?? 0 ) + (float) $r['secs'];
		$grid[ $s ][ $p ]['plays'] = ( $grid[ $s ][ $p ]['plays'] ?? 0 ) + (int) $r['done'];
		if ( $pid ) { $active[ $pid ] = true; }
		$minutes += (float) $r['secs'] / 60;
	}

	/* marks as they stood at the end of the week: the latest mark per person, song and part */
	$marks = [];
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT e.person_id, e.song, e.part, e.v, e.created FROM $E e JOIN ( SELECT person_id, song, part, MAX(id) mid FROM $E WHERE cantata_id = %d AND kind = 'flag' AND created < %s GROUP BY person_id, song, part ) m ON e.id = m.mid", $cantata_id, $to ), ARRAY_A );
	foreach ( $rows as $r ) { $marks[ $r['person_id'] . ':' . $r['song'] . ':' . $r['part'] ] = [ 'flag' => $r['v'], 'at' => $r['created'] ]; }
	/* marks made before the log existed live only in the totals table */
	$legacy = $wpdb->get_results( $wpdb->prepare( 'SELECT person_id, song, part, flag, last_played FROM ' . tcc_practice_table() . " WHERE cantata_id = %d AND flag <> ''", $cantata_id ), ARRAY_A );
	foreach ( $legacy as $r ) {
		$k = $r['person_id'] . ':' . $r['song'] . ':' . $r['part'];
		if ( ! isset( $marks[ $k ] ) && ( ! $r['last_played'] || $r['last_played'] < $to ) ) { $marks[ $k ] = [ 'flag' => $r['flag'], 'at' => $r['last_played'] ?: '' ]; }
	}
	$done = (array) get_post_meta( $cantata_id, '_tcc_help_done', true );
	$feel = []; $help = [];
	foreach ( $marks as $k => $m ) {
		[ $pid, $s, $p ] = explode( ':', $k ) + [ 0, 0, '' ];
		$s = (int) $s;
		if ( ! $m['flag'] || $s < 0 ) { continue; }
		$feel[ $s ][ $p ][ $m['flag'] ] = ( $feel[ $s ][ $p ][ $m['flag'] ] ?? 0 ) + 1;
		if ( $m['flag'] === 'help' ) {
			$handled = isset( $done[ $k ] ) && ( ! $m['at'] || $done[ $k ] >= $m['at'] );
			$help[] = [ 'key' => $k, 'name' => get_the_title( (int) $pid ), 'song' => $s, 'part' => $p, 'since' => $m['at'] ? mysql2date( 'M j', $m['at'] ) : '', 'handled' => $handled ];
		}
	}
	usort( $help, fn( $a, $b ) => [ $a['handled'], $a['song'], $a['name'] ] <=> [ $b['handled'], $b['song'], $b['name'] ] );

	/* where people went back: loops, rewinds, backward scrubs, as counts per 2-second cell */
	$len = tcc_director_lengths( $cantata_id, $count );
	$heat = [];
	$ev = $wpdb->get_results( $wpdb->prepare( "SELECT song, kind, a, b FROM $E WHERE cantata_id = %d AND kind IN ('loop','back','seek') AND created >= %s AND created < %s", $cantata_id, $from, $to ), ARRAY_A );
	foreach ( $ev as $r ) {
		$s = (int) $r['song'];
		if ( $s < 0 || $s >= $count ) { continue; }
		$n = max( 1, (int) ceil( max( $len[ $s ], (float) $r['b'] ) / TCC_HEAT_BIN ) );
		if ( ! isset( $heat[ $s ] ) ) { $heat[ $s ] = [ 'bins' => array_fill( 0, $n, 0 ), 'loop' => 0, 'back' => 0, 'seek' => 0 ]; }
		if ( count( $heat[ $s ]['bins'] ) < $n ) { $heat[ $s ]['bins'] = array_pad( $heat[ $s ]['bins'], $n, 0 ); }
		$heat[ $s ][ $r['kind'] ]++;
		for ( $i = (int) floor( $r['a'] / TCC_HEAT_BIN ), $j = (int) floor( max( $r['a'], $r['b'] - 0.01 ) / TCC_HEAT_BIN ); $i <= $j && $i < $n; $i++ ) { $heat[ $s ]['bins'][ $i ]++; }
	}

	/* shape it for the page */
	$cells = [];
	foreach ( range( 0, max( 0, $count - 1 ) ) as $s ) {
		foreach ( TCC_SECTIONS as $p ) {
			$g = $grid[ $s ][ $p ] ?? [];
			$cells[ $s ][ $p ] = [ 'people' => count( $g['people'] ?? [] ), 'mins' => round( ( $g['secs'] ?? 0 ) / 60 ), 'feel' => $feel[ $s ][ $p ] ?? (object) [] ];
		}
		$other = 0;
		foreach ( $grid[ $s ] ?? [] as $p => $g ) { if ( ! in_array( $p, TCC_SECTIONS, true ) ) { $other += $g['secs'] ?? 0; } }
		$cells[ $s ]['otherMins'] = round( $other / 60 );
	}
	return [
		'active'  => count( $active ),
		'minutes' => (int) round( $minutes ),
		'cells'   => $cells,
		'help'    => $help,
		'heat'    => (object) $heat,
	];
}

/** The data the director page is drawn from. */
function tcc_director_data( int $cantata_id ): array {
	$weeks  = tcc_director_weeks( $cantata_id );
	$season = [ 'key' => 'all', 'label' => 'All season', 'short' => 'All season', 'from' => '1970-01-01 00:00:00', 'to' => current_time( 'mysql' ), 'current' => false, 'nextOn' => '' ];
	$reports = [];
	foreach ( array_merge( $weeks, [ $season ] ) as $w ) { $reports[ $w['key'] ] = tcc_director_report( $cantata_id, $w ); }
	$songs = tcc_song_titles( $cantata_id );
	$score = function_exists( 'tcc_score' ) ? tcc_score( $cantata_id ) : [];
	$roster = tcc_director_roster( $cantata_id );
	global $wpdb;
	$since = $wpdb->get_var( $wpdb->prepare( 'SELECT MIN(created) FROM ' . tcc_events_table() . ' WHERE cantata_id = %d', $cantata_id ) );
	return [
		'cantata'  => [ 'id' => $cantata_id, 'title' => get_the_title( $cantata_id ), 'year' => tcc_year( $cantata_id ) ],
		'songs'    => array_values( $songs ),
		'lengths'  => tcc_director_lengths( $cantata_id, count( $songs ) ),
		'bin'      => TCC_HEAT_BIN,
		'sections' => array_map( fn( $p ) => [ 'id' => $p, 'label' => tcc_track_parts()[ $p ], 'size' => count( $roster['section'][ $p ] ) ], TCC_SECTIONS ),
		'voices'   => array_map( fn( $v, $ids ) => [ 'id' => $v, 'label' => $v === 'unknown' ? 'Part not given' : tcc_voice_parts()[ $v ], 'names' => array_values( array_map( fn( $id ) => $roster['names'][ $id ], tcc_sort_by_surname( $ids ) ) ) ], array_keys( $roster['byVoice'] ), $roster['byVoice'] ),
		'singers'  => count( $roster['singers'] ),
		'weeks'    => array_merge( $weeks, [ $season ] ),
		'reports'  => $reports,
		'next'     => function_exists( 'tcc_next_rehearsal_songs' ) ? tcc_next_rehearsal_songs( $cantata_id ) : null,
		'turns'    => (object) ( function_exists( 'tcc_turns' ) ? tcc_turns( $cantata_id ) : [] ),
		'pages'    => (object) ( function_exists( 'tcc_score_songs' ) ? tcc_score_songs( $cantata_id ) : [] ),
		'offset'   => (int) ( $score['offset'] ?? 0 ),
		'since'    => $since ? mysql2date( 'M j', $since ) : '',
		'rest'     => esc_url_raw( rest_url( 'tcc/v1/' ) ),
		'nonce'    => wp_create_nonce( 'wp_rest' ),
		'practice' => ( $p = get_page_by_path( 'practice' ) ) ? get_permalink( $p ) : '',
	];
}

/* the director can tick a help request off once it's dealt with; a new ask from the same singer brings it back */
add_action( 'rest_api_init', function () {
	register_rest_route( 'tcc/v1', '/helpdone', [ 'methods' => 'POST', 'permission_callback' => fn() => current_user_can( 'edit_posts' ), 'callback' => function ( WP_REST_Request $r ) {
		$cantata = (int) $r->get_param( 'cantata' );
		$key = (string) $r->get_param( 'key' );
		if ( ! $cantata || ! preg_match( '/^\d+:-?\d+:[a-z-]+$/', $key ) ) {
			return new WP_Error( 'bad', 'Bad request', [ 'status' => 400 ] );
		}
		$done = (array) get_post_meta( $cantata, '_tcc_help_done', true );
		if ( $r->get_param( 'done' ) ) { $done[ $key ] = current_time( 'mysql' ); } else { unset( $done[ $key ] ); }
		update_post_meta( $cantata, '_tcc_help_done', $done );
		return [ 'ok' => true ];
	} ] );
} );

/* the block: drawn only for people who can edit (the director and helpers signed in to WordPress) */
add_action( 'init', function () {
	register_block_type( 'tilden/director', [
		'api_version'     => 2,
		'title'           => 'Director report',
		'category'        => 'widgets',
		'icon'            => 'chart-bar',
		'render_callback' => function () {
			if ( ! current_user_can( 'edit_posts' ) ) {
				return '<div class="tcc-dir tcc-dir--locked"><p>This page is for the director. <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Sign in to WordPress</a> to see it.</p></div>';
			}
			$cantata = tcc_current_id();
			if ( ! $cantata ) {
				return '<div class="tcc-dir"><p>No current cantata yet.</p></div>';
			}
			wp_enqueue_style( 'tcc-director', TCC_URL . 'assets/director.css', [], TCC_VERSION );
			wp_enqueue_script( 'tcc-director', TCC_URL . 'assets/director.js', [], TCC_VERSION, true );
			return '<div class="tcc-dir" data-tcc-dir="' . esc_attr( wp_json_encode( tcc_director_data( $cantata ) ) ) . '"></div>';
		},
	] );
} );

/** The few numbers for the director's widget on the Backstage home: this practice week at a glance. */
function tcc_director_summary( int $cantata_id ): array {
	$weeks = tcc_director_weeks( $cantata_id );
	$w     = $weeks[0];
	$R     = tcc_director_report( $cantata_id, $w );
	$roster = tcc_director_roster( $cantata_id );
	$silent = 0;
	foreach ( $R['cells'] as $c ) {
		$any = $c['otherMins'] > 0;
		foreach ( TCC_SECTIONS as $p ) { if ( $c[ $p ]['people'] ) { $any = true; } }
		if ( ! $any ) { $silent++; }
	}
	/* per song: how many practiced it this week, all sections together */
	$per = [];
	foreach ( $R['cells'] as $c ) {
		$n = 0;
		foreach ( TCC_SECTIONS as $p ) { $n += $c[ $p ]['people']; }
		$per[] = $n;
	}
	$p = get_page_by_path( 'director' );
	return [
		'url'      => $p && $p->post_status === 'publish' ? get_permalink( $p ) : '',
		'week'     => $w['short'],
		'active'   => $R['active'],
		'singers'  => count( $roster['singers'] ),
		'minutes'  => $R['minutes'],
		'tricky'   => count( array_filter( $R['help'], fn( $x ) => ! $x['handled'] ) ),
		'silent'   => $silent,
		'songs'    => $per,
	];
}
