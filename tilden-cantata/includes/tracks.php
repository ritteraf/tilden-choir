<?php
defined( 'ABSPATH' ) || exit;

/* =====================================================================
 * Practice tracks.
 *
 *  - Each cantata post holds its tracks: _tcc_tracks = [ part => [ [ id, song, title ], … ] ]
 *    where id is an audio attachment and song is the index into the song list (-1 = none).
 *  - The director's timestamp notes ("cues"): _tcc_cues = [ [ id, song, t, text, parts ], … ].
 *  - Singers identify themselves once per device (a signed cookie holding a People id);
 *    plays, flags and personal notes go in two small tables.
 *  - The front end is the tilden/part-tracks block: a purpose-built player.
 * ===================================================================== */

define( 'TCC_PRACTICE_DB', '2' );

/** The playlists a cantata can have. */
function tcc_track_parts(): array {
	return apply_filters( 'tcc_track_parts', [
		'soprano'       => 'Soprano',
		'alto'          => 'Alto',
		'tenor'         => 'Tenor',
		'bass'          => 'Bass',
		'accompaniment' => 'Accompaniment (performance)',
		'sections'      => 'Accompaniment, sectioned (rehearsal)',
		'listen'        => 'All voices (listening)',
	] );
}

/** Guess the part from a title such as "Bass Practice Tracks" or "Advent Dreams (Alto)". */
function tcc_guess_part( string $title ): string {
	$t = strtolower( $title );
	foreach ( [ 'soprano' => 'soprano', 'alto' => 'alto', 'tenor' => 'tenor', 'bass' => 'bass', 'section' => 'sections', 'all voices' => 'listen', 'full mix' => 'listen', 'listen' => 'listen', 'demo' => 'listen', 'accomp' => 'accompaniment', 'piano' => 'accompaniment', 'orchestra' => 'accompaniment' ] as $needle => $part ) {
		if ( str_contains( $t, $needle ) ) {
			return $part;
		}
	}
	return '';
}

/** The cantata whose year matches a date. */
function tcc_guess_cantata( string $date ): int {
	$year = (int) substr( $date, 0, 4 );
	foreach ( tcc_get_cantatas() as $c ) {
		if ( (int) tcc_year( $c->ID ) === $year ) {
			return $c->ID;
		}
	}
	return 0;
}

/** A song title stripped of track numbers and part suffixes, for matching and file names. */
function tcc_clean_song_title( string $title ): string {
	$s = preg_replace( '/^\s*\d+\s*[-.–]?\s*/', '', $title );
	$s = preg_replace( '/\s*[-–]?\s*\((soprano|alto|tenor|bass|accomp[a-z]*|piano|all voices|sop|alt|ten|bas)\)\s*$/i', '', $s );
	$s = preg_replace( '/\s+(SOP|ALT|TEN|BAS|ACC|ACCOMP\.?|Soprano|Alto|Tenor|Bass)\s*$/', '', $s );
	$s = preg_replace( '/\s*[-–]\s*(accomp\.?|all voices|men only|women only)\s*$/i', '', $s );
	return trim( $s );
}

/** Index of the song in the cantata's list that best matches a track title, or -1. */
function tcc_match_song( string $title, array $songs ): int {
	$clean = strtolower( tcc_clean_song_title( $title ) );
	if ( $clean === '' ) {
		return -1;
	}
	$best = -1; $best_score = 0.0;
	foreach ( $songs as $i => $song ) {
		$s = strtolower( trim( (string) $song ) );
		if ( $s === $clean ) {
			return $i;
		}
		similar_text( $clean, $s, $pct );
		if ( str_starts_with( $s, $clean ) || str_starts_with( $clean, $s ) ) {
			$pct = max( $pct, 85 );
		}
		if ( $pct > $best_score ) {
			$best_score = $pct; $best = $i;
		}
	}
	return $best_score >= 60 ? $best : -1;
}

/* ---------- tracks on the cantata ---------- */

/** [ part => [ [ 'id', 'song', 'title' ], … ] ] with dead attachments dropped. */
function tcc_tracks( int $cantata_id ): array {
	$raw = (array) get_post_meta( $cantata_id, '_tcc_tracks', true );
	$out = [];
	foreach ( tcc_track_parts() as $part => $label ) {
		foreach ( (array) ( $raw[ $part ] ?? [] ) as $t ) {
			$id = (int) ( $t['id'] ?? 0 );
			if ( $id && get_post_type( $id ) === 'attachment' ) {
				$out[ $part ][] = [ 'id' => $id, 'song' => (int) ( $t['song'] ?? -1 ), 'title' => (string) ( $t['title'] ?? get_the_title( $id ) ) ];
			}
		}
	}
	return $out;
}

function tcc_sanitize_tracks( $v ): array {
	$out = [];
	foreach ( (array) $v as $part => $list ) {
		if ( ! isset( tcc_track_parts()[ $part ] ) ) {
			continue;
		}
		foreach ( (array) $list as $t ) {
			$id = (int) ( $t['id'] ?? 0 );
			if ( $id ) {
				$out[ $part ][] = [ 'id' => $id, 'song' => (int) ( $t['song'] ?? -1 ), 'title' => sanitize_text_field( (string) ( $t['title'] ?? '' ) ) ];
			}
		}
	}
	return $out;
}

/**
 * Unmatched tracks borrow their song from another part's track with the same ID3 track number
 * ("Track 04" in the Alto set → whatever track 4 in the Soprano set was matched to). Part CDs from
 * the same publisher almost always line up; the odd one out can be fixed by hand.
 */
function tcc_match_by_track_number( array $tracks ): array {
	$num = function ( int $id ): int {
		$m = wp_get_attachment_metadata( $id ) ?: [];
		$n = (int) ( $m['track_number'] ?? 0 );
		if ( ! $n && preg_match( '/(?:track|^)\s*0*(\d{1,2})\b/i', get_the_title( $id ), $mm ) ) {
			$n = (int) $mm[1];
		}
		return $n;
	};
	$known = []; // track number => song, from matched tracks
	foreach ( $tracks as $part => $list ) {
		foreach ( $list as $t ) {
			if ( $t['song'] >= 0 && ( $n = $num( $t['id'] ) ) ) {
				$known[ $n ] = $known[ $n ] ?? $t['song'];
			}
		}
	}
	if ( ! $known ) {
		return $tracks;
	}
	foreach ( $tracks as $part => &$list ) {
		foreach ( $list as &$t ) {
			if ( $t['song'] < 0 && ( $n = $num( $t['id'] ) ) && isset( $known[ $n ] ) ) {
				$t['song'] = $known[ $n ];
			}
		}
		usort( $list, fn( $a, $b ) => ( $a['song'] < 0 ? 999 : $a['song'] ) <=> ( $b['song'] < 0 ? 999 : $b['song'] ) );
	}
	unset( $list, $t );
	return $tracks;
}

/**
 * Gist icons for director notes: one glance tells a singer what the note is about
 * (a turtle for slowing down, a speaker with waves for getting louder…). Key => [label, inner SVG].
 */
function tcc_cue_icons(): array {
	$raw = [
		'slower' => [ 'Slower', '<path d="M3 16a7.5 7.5 0 0 1 15 0z"/><path d="M18 14.2c.6-1.6 1.6-2.7 2.8-2.7a1.8 1.8 0 0 1 0 3.6H18"/><path d="M6 16v2.5M15 16v2.5"/><path d="M8 9.8 9.6 16M13 9.8 11.4 16"/>' ],
		'faster' => [ 'Faster', '<path d="M15 19.5H8.5a5.2 5.2 0 1 1 3.6-9"/><circle cx="15.8" cy="10.8" r="3.2"/><path d="M14.3 7.9c-1.3-2.4-1.4-5.2-.1-5.3 1.3-.1 2 2.6 1.8 5"/><path d="M17 7.7c.6-2.6 1.9-5 3-4.6 1.1.5.2 3.2-1.3 5.2"/><path d="M15 19.5c-.6-1.4-.5-2.6.4-3.5"/><circle cx="2.9" cy="13.6" r="1" fill="currentColor" stroke="none"/><circle cx="16.9" cy="10.4" r=".7" fill="currentColor" stroke="none"/>' ],
		'louder' => [ 'Louder', '<path d="M11 5 6 9H2v6h4l5 4V5z"/><path d="M15 9.5a3.5 3.5 0 0 1 0 5"/><path d="M17.8 7a7 7 0 0 1 0 10"/><path d="M20.6 4.5a10.5 10.5 0 0 1 0 15"/>' ],
		'softer' => [ 'Softer', '<path d="M11 5 6 9H2v6h4l5 4V5z"/><path d="M15 10a2.8 2.8 0 0 1 0 4"/>' ],
		'breathe' => [ 'Breathe', '<path d="M3 8h10a3 3 0 1 0-3-3"/><path d="M3 12h15a3 3 0 1 1-3 3"/><path d="M3 16h6"/>' ],
		'hold' => [ 'Hold (fermata)', '<path d="M3.5 16.5a8.5 8.5 0 0 1 17 0"/><circle cx="12" cy="15" r="1.7" fill="currentColor" stroke="none"/>' ],
		'cutoff' => [ 'Cut-off', '<circle cx="6" cy="6.5" r="3"/><circle cx="6" cy="17.5" r="3"/><path d="M20 4.5 8.4 15.6M14.6 14.4 20 19.5M8.4 8.4l3.4 3.2"/>' ],
		'entrance' => [ 'Entrance', '<path d="M3 12h11M10 7.5l4.5 4.5-4.5 4.5"/><path d="M19.5 4.5v15"/>' ],
		'watch' => [ 'Watch me', '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>' ],
		'listen' => [ 'Listen & blend', '<path d="M6.5 9a5.5 5.5 0 1 1 11 0c0 3.2-2.7 4-2.7 6.8a3.3 3.3 0 0 1-6.3 1.4"/><path d="M9.6 9.2a2.4 2.4 0 1 1 4.8 0c0 1.3-1.2 1.8-1.4 3"/>' ],
		'higher' => [ 'Higher / stay up', '<path d="M12 19.5V5M5.5 11.5 12 5l6.5 6.5"/>' ],
		'lower' => [ 'Lower', '<path d="M12 4.5V19M5.5 12.5 12 19l6.5-6.5"/>' ],
		'short' => [ 'Short & crisp', '<circle cx="5.5" cy="12" r="1.8" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.8" fill="currentColor" stroke="none"/><circle cx="18.5" cy="12" r="1.8" fill="currentColor" stroke="none"/>' ],
		'smooth' => [ 'Smooth & connected', '<path d="M2.5 13.5c3.2-5.5 6.3-5.5 9.5 0s6.3 5.5 9.5 0"/>' ],
		'count' => [ 'Count / tempo', '<path d="M6.5 20.5 10 3.5h4l3.5 17z"/><path d="M6 20.5h12"/><path d="M12 15.5l5.5-8"/><circle cx="12" cy="15.5" r=".9" fill="currentColor" stroke="none"/>' ],
		'smile' => [ 'Smile / expression', '<circle cx="12" cy="12" r="9.5"/><path d="M8 14.2a4.8 4.8 0 0 0 8 0"/><circle cx="9" cy="9.6" r=".9" fill="currentColor" stroke="none"/><circle cx="15" cy="9.6" r=".9" fill="currentColor" stroke="none"/>' ],
		'words' => [ 'Words & diction', '<path d="M21 11.5a8.5 8 0 0 1-12.2 7.2L3 20.5l1.9-5A8.5 8 0 1 1 21 11.5z"/><path d="M8.5 10h7M8.5 13.5h4.5"/>' ],
		'star' => [ 'Important', '<path d="M12 3.2l2.7 5.5 6 .9-4.35 4.25 1 6L12 17l-5.35 2.85 1-6L3.3 9.6l6-.9z"/>' ],
	];
	$out = [];
	foreach ( $raw as $k => [ $label, $inner ] ) {
		$out[ $k ] = [ 'label' => $label, 'svg' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>' ];
	}
	return $out;
}

/** A measure reference as the director typed it: "42", "42–44", "12a". Short, plain characters only. */
function tcc_cue_measure( $v ): string {
	$v = trim( wp_strip_all_tags( (string) $v ) );
	$v = preg_replace( '/^(?:measures?|meas|bars?|mm?|ms)\.?\s*/i', '', $v ); // the app adds "m." itself
	$v = preg_replace( '/[^0-9A-Za-z ,\-–]/u', '', $v );
	$v = trim( preg_replace( '/\s+/', ' ', str_replace( '-', '–', $v ) ) );
	return mb_substr( $v, 0, 12 );
}

/** How long a note stays lit, in seconds; 0 means the app's default (8 s). */
function tcc_cue_dur( $v ): float {
	$v = round( (float) $v, 1 );
	return $v <= 0 ? 0.0 : min( 120.0, max( 2.0, $v ) );
}

/** An icon key, if it is one of ours. */
function tcc_cue_icon( $v ): string {
	$v = (string) $v;
	return isset( tcc_cue_icons()[ $v ] ) ? $v : '';
}

/** Director's timestamp notes. */
function tcc_cues( int $cantata_id ): array {
	$out = [];
	foreach ( (array) get_post_meta( $cantata_id, '_tcc_cues', true ) as $c ) {
		if ( is_array( $c ) && isset( $c['t'], $c['text'] ) ) {
			$out[] = [ 'id' => (string) ( $c['id'] ?? '' ), 'song' => (int) ( $c['song'] ?? -1 ), 't' => (float) $c['t'], 'text' => (string) $c['text'], 'parts' => array_values( (array) ( $c['parts'] ?? [] ) ), 'm' => (string) ( $c['m'] ?? '' ), 'icon' => (string) ( $c['icon'] ?? '' ), 'd' => (float) ( $c['d'] ?? 0 ) ];
		}
	}
	usort( $out, fn( $a, $b ) => [ $a['song'], $a['t'] ] <=> [ $b['song'], $b['t'] ] );
	return $out;
}

/* ---------- tables ---------- */

function tcc_practice_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'tcc_practice';
}
function tcc_pnotes_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'tcc_pnotes';
}
/** A dated log of practice: listening sessions, feedback changes, loops and rewinds. It is what lets the director see each week between rehearsals, and where in a song people go back. */
function tcc_events_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'tcc_events';
}
function tcc_log_event( int $who, int $cantata, int $song, string $part, string $kind, float $a = 0, float $b = 0, string $v = '' ): void {
	global $wpdb;
	$wpdb->insert( tcc_events_table(), [ 'person_id' => $who, 'cantata_id' => $cantata, 'song' => $song, 'part' => substr( $part, 0, 20 ), 'kind' => $kind, 'a' => $a, 'b' => $b, 'v' => substr( $v, 0, 12 ), 'created' => current_time( 'mysql' ) ] );
}

add_action( 'plugins_loaded', function () {
	if ( get_option( 'tcc_practice_db' ) === TCC_PRACTICE_DB ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	dbDelta( 'CREATE TABLE ' . tcc_practice_table() . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		person_id bigint(20) unsigned NOT NULL,
		cantata_id bigint(20) unsigned NOT NULL,
		song int(11) NOT NULL DEFAULT -1,
		part varchar(20) NOT NULL DEFAULT '',
		plays int(11) NOT NULL DEFAULT 0,
		seconds int(11) NOT NULL DEFAULT 0,
		flag varchar(12) NOT NULL DEFAULT '',
		last_played datetime NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY who (person_id, cantata_id, song, part),
		KEY cantata (cantata_id, song)
	) $charset;" );
	dbDelta( 'CREATE TABLE ' . tcc_pnotes_table() . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		person_id bigint(20) unsigned NOT NULL,
		cantata_id bigint(20) unsigned NOT NULL,
		song int(11) NOT NULL DEFAULT -1,
		t float NOT NULL DEFAULT 0,
		text text NOT NULL,
		created datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY who (person_id, cantata_id, song)
	) $charset;" );
	dbDelta( 'CREATE TABLE ' . tcc_events_table() . " (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		person_id bigint(20) unsigned NOT NULL DEFAULT 0,
		cantata_id bigint(20) unsigned NOT NULL,
		song int(11) NOT NULL DEFAULT -1,
		part varchar(20) NOT NULL DEFAULT '',
		kind varchar(10) NOT NULL DEFAULT '',
		a float NOT NULL DEFAULT 0,
		b float NOT NULL DEFAULT 0,
		v varchar(12) NOT NULL DEFAULT '',
		created datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY cw (cantata_id, created),
		KEY cs (cantata_id, song, kind)
	) $charset;" );
	update_option( 'tcc_practice_db', TCC_PRACTICE_DB );
} );

/* ---------- identity: a signed cookie with a People id ---------- */

function tcc_who_sign( int $id ): string {
	return $id . '.' . substr( hash_hmac( 'sha256', 'who|' . $id, wp_salt( 'auth' ) ), 0, 24 );
}
function tcc_who(): int {
	if ( function_exists( 'tcc_is_director' ) && tcc_is_director() ) {
		return 0; // the director isn't one of the singers
	}
	$c = (string) ( $_COOKIE['tcc_who'] ?? '' );
	if ( ! preg_match( '/^(\d+)\.([a-f0-9]{24})$/', $c, $m ) ) {
		return 0;
	}
	return hash_equals( tcc_who_sign( (int) $m[1] ), $c ) ? (int) $m[1] : 0;
}
/** The token the protected page hands the player; the API accepts writes only with it. */
function tcc_practice_token( int $cantata_id ): string {
	return substr( hash_hmac( 'sha256', 'practice|' . $cantata_id, wp_salt( 'auth' ) ), 0, 32 );
}
function tcc_rest_cantata( WP_REST_Request $r ): int {
	$id = (int) $r->get_param( 'cantata' );
	if ( ! $id || get_post_type( $id ) !== 'post' ) {
		return 0;
	}
	$tok = (string) ( $r->get_header( 'x-tcc-token' ) ?: $r->get_param( 'token' ) );
	return hash_equals( tcc_practice_token( $id ), $tok ) ? $id : 0;
}

/* ---------- REST ---------- */

add_action( 'rest_api_init', function () {
	$ns   = 'tcc/v1';
	$open = fn( WP_REST_Request $r ) => (bool) tcc_rest_cantata( $r );
	$edit = fn() => current_user_can( 'edit_posts' );

	register_rest_route( $ns, '/roster', [ 'methods' => 'GET', 'permission_callback' => $open, 'callback' => function ( WP_REST_Request $r ) {
		$cantata = tcc_rest_cantata( $r );
		$parts   = function_exists( 'tcc_voice_parts' ) ? tcc_voice_parts() : [];
		$groups  = [];
		$ids = get_posts( [ 'post_type' => 'tcc_person', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'title', 'order' => 'ASC',
			'meta_query' => [ [ 'key' => '_tcc_seasons', 'value' => sprintf( 'i:%d;', $cantata ), 'compare' => 'LIKE' ] ] ] );
		foreach ( $ids as $id ) {
			$part = get_post_meta( $id, '_tcc_part', true );
			$label = $parts[ $part ] ?? 'Everyone else';
			$label = trim( preg_replace( '/\s*\(.*$/', '', $label ) );
			$groups[ $label ][] = [ 'id' => $id, 'name' => get_the_title( $id ) ];
		}
		return [ 'groups' => $groups ];
	} ] );

	register_rest_route( $ns, '/who', [ 'methods' => 'POST', 'permission_callback' => $open, 'callback' => function ( WP_REST_Request $r ) {
		$id = (int) $r->get_param( 'person' );
		if ( $id && get_post_type( $id ) !== 'tcc_person' ) {
			return new WP_Error( 'bad', 'Unknown person', [ 'status' => 400 ] );
		}
		$secure = is_ssl();
		if ( $id ) {
			setcookie( 'tcc_who', tcc_who_sign( $id ), [ 'expires' => time() + YEAR_IN_SECONDS, 'path' => '/', 'secure' => $secure, 'httponly' => false, 'samesite' => 'Lax' ] );
			return [ 'id' => $id, 'name' => get_the_title( $id ) ];
		}
		setcookie( 'tcc_who', '', [ 'expires' => time() - 3600, 'path' => '/', 'secure' => $secure, 'samesite' => 'Lax' ] );
		return [ 'id' => 0 ];
	} ] );

	register_rest_route( $ns, '/state', [ 'methods' => 'GET', 'permission_callback' => $open, 'callback' => function ( WP_REST_Request $r ) {
		global $wpdb;
		$cantata = tcc_rest_cantata( $r );
		$who = tcc_who();
		if ( ! $who ) {
			return [ 'who' => null ];
		}
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT song, part, plays, seconds, flag, last_played FROM ' . tcc_practice_table() . ' WHERE person_id = %d AND cantata_id = %d', $who, $cantata ), ARRAY_A );
		$notes = $wpdb->get_results( $wpdb->prepare( 'SELECT id, song, t, text FROM ' . tcc_pnotes_table() . ' WHERE person_id = %d AND cantata_id = %d ORDER BY song, t', $who, $cantata ), ARRAY_A );
		return [ 'who' => [ 'id' => $who, 'name' => get_the_title( $who ) ], 'rows' => $rows, 'notes' => $notes ];
	} ] );

	register_rest_route( $ns, '/play', [ 'methods' => 'POST', 'permission_callback' => $open, 'callback' => function ( WP_REST_Request $r ) {
		global $wpdb;
		$cantata = tcc_rest_cantata( $r ); $who = tcc_who();
		if ( ! $who ) {
			return [ 'ok' => false ];
		}
		$song = (int) $r->get_param( 'song' ); $part = sanitize_key( $r->get_param( 'part' ) );
		$sec  = min( 3600, max( 0, (int) $r->get_param( 'seconds' ) ) ); $done = (int) (bool) $r->get_param( 'complete' );
		$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . tcc_practice_table() . ' (person_id, cantata_id, song, part, plays, seconds, last_played) VALUES (%d, %d, %d, %s, %d, %d, %s)
			ON DUPLICATE KEY UPDATE plays = plays + VALUES(plays), seconds = seconds + VALUES(seconds), last_played = VALUES(last_played)', $who, $cantata, $song, $part, $done, $sec, current_time( 'mysql' ) ) );
		if ( $sec > 0 || $done ) { tcc_log_event( $who, $cantata, $song, $part, 'listen', 0, $sec, $done ? 'done' : '' ); }
		return [ 'ok' => true ];
	} ] );

	register_rest_route( $ns, '/flag', [ 'methods' => 'POST', 'permission_callback' => $open, 'callback' => function ( WP_REST_Request $r ) {
		global $wpdb;
		$cantata = tcc_rest_cantata( $r ); $who = tcc_who();
		if ( ! $who ) {
			return [ 'ok' => false ];
		}
		$song = (int) $r->get_param( 'song' ); $part = sanitize_key( $r->get_param( 'part' ) );
		$flag = in_array( $r->get_param( 'flag' ), [ 'ok', 'working', 'help', '' ], true ) ? $r->get_param( 'flag' ) : '';
		$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . tcc_practice_table() . ' (person_id, cantata_id, song, part, flag) VALUES (%d, %d, %d, %s, %s) ON DUPLICATE KEY UPDATE flag = VALUES(flag)', $who, $cantata, $song, $part, $flag ) );
		tcc_log_event( $who, $cantata, $song, $part, 'flag', 0, 0, $flag );
		return [ 'ok' => true, 'flag' => $flag ];
	} ] );

	/* where people go back: loops (each time round), rewinds and backward scrubs, sent in small batches */
	register_rest_route( $ns, '/heat', [ 'methods' => 'POST', 'permission_callback' => $open, 'callback' => function ( WP_REST_Request $r ) {
		$cantata = tcc_rest_cantata( $r ); $who = (int) tcc_who();
		$items = $r->get_param( 'items' );
		if ( is_string( $items ) ) { $items = json_decode( $items, true ); }
		$n = 0;
		foreach ( array_slice( (array) $items, 0, 80 ) as $it ) {
			if ( ! is_array( $it ) || ! in_array( $it['kind'] ?? '', [ 'loop', 'back', 'seek' ], true ) ) { continue; }
			$a = round( max( 0, min( 3600, (float) ( $it['a'] ?? 0 ) ) ), 1 ); $b = round( max( 0, min( 3600, (float) ( $it['b'] ?? 0 ) ) ), 1 );
			if ( $b <= $a || $b - $a > 300 ) { continue; }
			tcc_log_event( $who, $cantata, (int) ( $it['song'] ?? -1 ), sanitize_key( $it['part'] ?? '' ), $it['kind'], $a, $b );
			$n++;
		}
		return [ 'ok' => true, 'n' => $n ];
	} ] );

	register_rest_route( $ns, '/note', [
		[ 'methods' => 'POST', 'permission_callback' => $open, 'callback' => function ( WP_REST_Request $r ) {
			global $wpdb;
			$cantata = tcc_rest_cantata( $r ); $who = tcc_who();
			$text = sanitize_text_field( (string) $r->get_param( 'text' ) );
			if ( ! $who || $text === '' ) {
				return [ 'ok' => false ];
			}
			$id = (int) $r->get_param( 'id' );
			if ( $id ) { // an edit: only ever the person's own note
				$wpdb->update( tcc_pnotes_table(), [ 't' => max( 0, (float) $r->get_param( 't' ) ), 'text' => $text ], [ 'id' => $id, 'person_id' => $who ] );
				return [ 'ok' => true, 'id' => $id ];
			}
			$wpdb->insert( tcc_pnotes_table(), [ 'person_id' => $who, 'cantata_id' => $cantata, 'song' => (int) $r->get_param( 'song' ), 't' => (float) $r->get_param( 't' ), 'text' => $text, 'created' => current_time( 'mysql' ) ] );
			return [ 'ok' => true, 'id' => (int) $wpdb->insert_id ];
		} ],
		[ 'methods' => 'DELETE', 'permission_callback' => $open, 'callback' => function ( WP_REST_Request $r ) {
			global $wpdb;
			$who = tcc_who();
			if ( $who ) {
				$wpdb->delete( tcc_pnotes_table(), [ 'id' => (int) $r->get_param( 'id' ), 'person_id' => $who ] );
			}
			return [ 'ok' => (bool) $who ];
		} ],
	] );

	// Director's cues: ordinary logged-in REST (cookie + nonce), editors only.
	register_rest_route( $ns, '/cue', [
		[ 'methods' => 'POST', 'permission_callback' => $edit, 'callback' => function ( WP_REST_Request $r ) {
			$cantata = (int) $r->get_param( 'cantata' );
			$text = sanitize_text_field( (string) $r->get_param( 'text' ) );
			if ( ! $cantata || $text === '' ) {
				return new WP_Error( 'bad', 'Missing text', [ 'status' => 400 ] );
			}
			$cues = tcc_cues( $cantata );
			$parts = tcc_cue_parts( $r->get_param( 'parts' ) );
			$id = (string) $r->get_param( 'id' );
			if ( $id !== '' ) { // an edit: same id and song; new time, words and parts
				foreach ( $cues as &$c ) {
					if ( $c['id'] === $id ) {
						$c['t'] = round( max( 0, (float) $r->get_param( 't' ) ), 1 );
						$c['text'] = $text;
						$c['parts'] = $parts;
						$c['m'] = tcc_cue_measure( $r->get_param( 'm' ) );
						$c['icon'] = tcc_cue_icon( $r->get_param( 'icon' ) );
						$c['d'] = tcc_cue_dur( $r->get_param( 'd' ) );
						$cue = $c;
					}
				}
				unset( $c );
				if ( empty( $cue ) ) {
					return new WP_Error( 'gone', 'That note no longer exists', [ 'status' => 404 ] );
				}
				update_post_meta( $cantata, '_tcc_cues', $cues );
				return [ 'ok' => true, 'cue' => $cue ];
			}
			$cue = [ 'id' => wp_generate_password( 8, false ), 'song' => (int) $r->get_param( 'song' ), 't' => round( (float) $r->get_param( 't' ), 1 ), 'text' => $text, 'parts' => $parts, 'm' => tcc_cue_measure( $r->get_param( 'm' ) ), 'icon' => tcc_cue_icon( $r->get_param( 'icon' ) ), 'd' => tcc_cue_dur( $r->get_param( 'd' ) ) ];
			$cues[] = $cue;
			update_post_meta( $cantata, '_tcc_cues', $cues );
			return [ 'ok' => true, 'cue' => $cue ];
		} ],
		[ 'methods' => 'DELETE', 'permission_callback' => $edit, 'callback' => function ( WP_REST_Request $r ) {
			$cantata = (int) $r->get_param( 'cantata' ); $id = (string) $r->get_param( 'id' );
			$cues = array_values( array_filter( tcc_cues( $cantata ), fn( $c ) => $c['id'] !== $id ) );
			update_post_meta( $cantata, '_tcc_cues', $cues );
			return [ 'ok' => true ];
		} ],
	] );
} );

/** Who a director note is for: voice parts and/or teams ("team:<slug>"); none = everyone. */
function tcc_cue_parts( $in ): array {
	$ok = array_keys( tcc_track_parts() );
	foreach ( array_keys( function_exists( 'tcc_teams' ) ? tcc_teams() : [] ) as $slug ) {
		$ok[] = 'team:' . $slug;
	}
	return array_values( array_intersect( $ok, array_map( 'strval', (array) $in ) ) );
}

/* ---------- what's on at the next rehearsal ---------- */

/** 0-based song indices the next rehearsal covers, with its date; null if none. */
function tcc_next_rehearsal_songs( int $cantata_id ): ?array {
	$next = tcc_next_rehearsal( $cantata_id );
	if ( ! $next ) {
		return null;
	}
	$dt = tcc_rehearsal_dt( $next->ID );
	$count = count( tcc_song_titles( $cantata_id ) );
	if ( tcc_rehearsal_is_full( $next->ID ) ) {
		return [ 'date' => $dt ? $dt->format( 'l, F j' ) : '', 'songs' => range( 0, max( 0, $count - 1 ) ), 'focus' => tcc_song_indices( (string) get_post_meta( $next->ID, '_tcc_focus', true ), $count ), 'full' => true, 'title' => get_the_title( $next ) ];
	}
	$idx = tcc_song_indices( (string) get_post_meta( $next->ID, '_tcc_songs', true ), $count );
	return [ 'date' => $dt ? $dt->format( 'l, F j' ) : '', 'songs' => $idx, 'focus' => tcc_song_indices( (string) get_post_meta( $next->ID, '_tcc_focus', true ), $count ), 'full' => false, 'title' => get_the_title( $next ) ];
}

/* ---------- the player block ---------- */

function tcc_part_for_page( string $attr ): string {
	$parts = tcc_track_parts();
	if ( $attr && $attr !== 'auto' && isset( $parts[ $attr ] ) ) {
		return $attr;
	}
	return tcc_guess_part( get_the_title() ) ?: 'soprano';
}

/** Track list for the player: resolved URLs and durations. */
function tcc_player_list( array $tracks, array $songs ): array {
	$out = [];
	foreach ( $tracks as $t ) {
		$meta = wp_get_attachment_metadata( $t['id'] ) ?: [];
		$out[] = [
			'id'    => $t['id'],
			'song'  => $t['song'],
			'title' => $t['song'] >= 0 && isset( $songs[ $t['song'] ] ) ? $songs[ $t['song'] ] : tcc_clean_song_title( $t['title'] ),
			'url'   => wp_get_attachment_url( $t['id'] ),
			'dur'   => (int) ( $meta['length'] ?? 0 ),
			/* the CD track number — for the sectioned accompaniment it matches the circled number in the score */
			'cd'    => (int) ( $meta['track_number'] ?? 0 ) ?: ( preg_match( '/(?:track|^)\s*0*(\d{1,3})\b/i', get_the_title( $t['id'] ), $mm ) ? (int) $mm[1] : 0 ),
		];
	}
	return $out;
}

function tcc_render_player( string $attr = 'auto', bool $past = true ): string {
	$page    = $attr === 'page'; // the whole-page practice player: every part, the person's own first
	$part    = $page ? '' : tcc_part_for_page( $attr );
	$label   = $part ? trim( preg_replace( '/\s*\(.*$/', '', tcc_track_parts()[ $part ] ?? ucfirst( $part ) ) ) : '';
	$current = tcc_current_id();
	if ( ! $current ) {
		return '';
	}
	$songs  = tcc_song_titles( $current );
	$tracks = tcc_tracks( $current );
	$lists  = [];
	foreach ( $page ? array_diff( array_keys( tcc_track_parts() ), [ 'sections' ] ) : [ $part, 'listen', 'accompaniment' ] as $p ) {
		if ( ! empty( $tracks[ $p ] ) ) {
			$lists[ $p ] = tcc_player_list( $tracks[ $p ], $songs );
		}
	}
	$cues = $page ? tcc_cues( $current ) : array_values( array_filter( tcc_cues( $current ), fn( $c ) => ! $c['parts'] || in_array( $part, $c['parts'], true ) ) );
	$old  = [];
	if ( $past ) {
		foreach ( tcc_get_cantatas() as $c ) {
			if ( $c->ID === $current ) {
				continue;
			}
			$t = tcc_tracks( $c->ID );
			if ( $page ) {
				$by = [];
				foreach ( [ 'soprano', 'alto', 'tenor', 'bass', 'accompaniment', 'sections', 'listen' ] as $p ) {
					if ( ! empty( $t[ $p ] ) ) {
						$by[ $p ] = tcc_player_list( $t[ $p ], tcc_song_titles( $c->ID ) );
					}
				}
				if ( $by ) {
					$old[] = [ 'id' => $c->ID, 'year' => tcc_year( $c->ID ), 'title' => $c->post_title, 'parts' => $by, 'token' => tcc_practice_token( $c->ID ) ];
				}
			} elseif ( ! empty( $t[ $part ] ) ) {
				$old[] = [ 'id' => $c->ID, 'year' => tcc_year( $c->ID ), 'title' => $c->post_title, 'tracks' => tcc_player_list( $t[ $part ], tcc_song_titles( $c->ID ) ), 'token' => tcc_practice_token( $c->ID ) ];
			}
		}
	}
	$who   = tcc_who();
	$voice = [];
	foreach ( function_exists( 'tcc_voice_parts' ) ? tcc_voice_parts() : [] as $k => $l ) {
		$voice[ $k ] = [ 'label' => trim( preg_replace( '/\s*\(.*$/', '', $l ) ), 'tracks' => explode( '-', $k ) ];
	}
	$data = [
		'mode'      => $page ? 'page' : 'part',
		'rest'      => esc_url_raw( rest_url( 'tcc/v1/' ) ),
		'token'     => tcc_practice_token( $current ),
		'nonce'     => current_user_can( 'edit_posts' ) ? wp_create_nonce( 'wp_rest' ) : '',
		'canEdit'   => current_user_can( 'edit_posts' ),
		'director'  => function_exists( 'tcc_is_director' ) && tcc_is_director(),
		'cantata'   => [ 'id' => $current, 'year' => tcc_year( $current ), 'title' => get_the_title( $current ) ],
		'part'      => $part,
		'partLabel' => $label,
		'voice'     => $voice,
		'hub'       => home_url( '/backstage/' ),
		'partLabels'=> array_map( fn( $l ) => trim( preg_replace( '/\s*\(.*$/', '', $l ) ), tcc_track_parts() ),
		'songs'     => array_values( $songs ),
		'lists'     => $lists,
		'teams'     => (object) ( function_exists( 'tcc_teams' ) ? array_map( fn( $t ) => $t['label'], tcc_teams() ) : [] ),
		'cdmarks'   => (object) ( function_exists( 'tcc_cd_marks' ) ? tcc_cd_marks( $current ) : [] ),
		'cues'      => $cues,
		'cueIcons'  => tcc_cue_icons(),
		'next'      => tcc_next_rehearsal_songs( $current ),
		'past'      => $old,
		'who'       => $who ? [ 'id' => $who, 'name' => get_the_title( $who ) ] : null,
		'emblem'    => function_exists( 'tcc_emblem_url' ) ? tcc_emblem_url() : '',
		'empty'     => ! $page && empty( $lists[ $part ] ) ? ( current_user_can( 'edit_posts' )
			? sprintf( 'No %s tracks on %s yet. This page\'s title is "%s", so the player looks for the %s list on this year\'s cantata post (Practice tracks box). Add them there, or set the part on this block in the editor. Only editors see this note.', $label, get_the_title( $current ), get_the_title(), $label )
			: $label . ' tracks for this year aren\'t up yet. Check back soon.' ) : '',
	];
	$data['signin'] = function_exists( 'tcc_signin_config' ) ? tcc_signin_config( $current ) : null;
	if ( $page ) {
		// The practice page is the same HTML for everyone; the browser asks /me who it belongs to.
		$data['who'] = null;
		if ( $data['signin'] ) {
			$data['signin']['who'] = null;
		}
	}
	if ( function_exists( 'tcc_enqueue_signin_assets' ) ) {
		tcc_enqueue_signin_assets();
	}
	if ( $page ) {
		$data['score'] = function_exists( 'tcc_score_data' ) ? tcc_score_data( $current, $data['token'] ) : null;
		// The full-screen practice app.
		wp_enqueue_style( 'tcc-app', TCC_URL . 'assets/app.css', [ 'tcc-signin' ], TCC_VERSION );
		wp_enqueue_script( 'tcc-app', TCC_URL . 'assets/app.js', [ 'tcc-signin' ], TCC_VERSION, [ 'strategy' => 'defer' ] );
		return '<div class="tcc-app-root" data-tcc-app="' . esc_attr( wp_json_encode( $data ) ) . '"><noscript><p>The practice app needs JavaScript turned on.</p></noscript></div>';
	}
	wp_enqueue_style( 'tcc-practice', TCC_URL . 'assets/practice.css', [], TCC_VERSION );
	wp_enqueue_script( 'tcc-practice', TCC_URL . 'assets/practice.js', [ 'tcc-signin' ], TCC_VERSION, [ 'strategy' => 'defer' ] );
	$empty = $data['empty'] ? '<p class="tcc-practice__empty">' . esc_html( $data['empty'] ) . '</p>' : '';
	return '<div class="tcc-practice' . ( $page ? ' tcc-practice--page' : '' ) . '" data-tcc-practice="' . esc_attr( wp_json_encode( $data ) ) . '">' . $empty . '<noscript><p>The practice player needs JavaScript.</p></noscript></div>';
}

add_action( 'init', function () {
	register_block_type( 'tilden/part-tracks', [
		'api_version' => 3, 'title' => 'Practice tracks (this part)', 'description' => 'The practice player for the page\'s voice part: this year\'s tracks with the director\'s notes, slow-down, looping and per-singer practice tracking; earlier years folded underneath.',
		'category' => 'widgets', 'icon' => 'playlist-audio',
		'supports' => [ 'html' => false, 'multiple' => false, 'align' => [ 'wide' ] ],
		'attributes' => [ 'part' => [ 'type' => 'string', 'default' => 'auto' ], 'past' => [ 'type' => 'boolean', 'default' => true ] ],
		'render_callback' => function ( array $attributes ) {
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) { // the editor preview: the real player only runs on the page
				$part = tcc_part_for_page( (string) ( $attributes['part'] ?? 'auto' ) );
				return '<div ' . get_block_wrapper_attributes() . '><div style="padding:22px 24px;border:1.5px dashed #c8c8c4;border-radius:8px;font-family:sans-serif"><strong style="font-size:16px">Practice player — ' . esc_html( trim( preg_replace( '/\s*\(.*$/', '', tcc_track_parts()[ $part ] ?? $part ) ) ) . '</strong><br><span style="color:#6b6b6b">This year\'s tracks with the director\'s notes, slow-down, looping and practice tracking. Tracks are added on each cantata post.</span></div></div>';
			}
			tcc_enqueue_front_assets();
			return '<div ' . get_block_wrapper_attributes() . '>' . tcc_render_player( (string) ( $attributes['part'] ?? 'auto' ), ! empty( $attributes['past'] ) ) . '</div>';
		},
	] );
} );

/* ---------- the cantata post: Practice tracks box ---------- */

add_action( 'add_meta_boxes_post', function ( WP_Post $post ) {
	if ( has_category( TCC_CATEGORY, $post ) || $post->post_status === 'auto-draft' ) {
		add_meta_box( 'tcc_tracks', 'Practice tracks', 'tcc_render_tracks_box', 'post', 'normal', 'default' );
	}
} );

function tcc_render_tracks_box( WP_Post $post ) {
	wp_nonce_field( 'tcc_tracks_save', 'tcc_tracks_nonce' );
	$songs  = tcc_song_titles( $post->ID );
	$tracks = tcc_tracks( $post->ID );
	$cues   = tcc_cues( $post->ID );
	$data   = [ 'songs' => array_values( $songs ), 'parts' => tcc_track_parts(), 'tracks' => $tracks, 'cues' => $cues, 'icons' => array_map( fn( $i ) => $i['label'], tcc_cue_icons() ) ];
	foreach ( $data['tracks'] as $part => &$list ) {
		foreach ( $list as &$t ) {
			$t['file'] = basename( (string) get_attached_file( $t['id'] ) );
		}
	}
	unset( $list, $t );
	$data['tracks'] = (object) $data['tracks']; // an empty PHP array would reach the browser as [] and be treated as a list
	echo '<p class="description">One list per part. <strong>Add tracks</strong> opens the media library (upload the ripped files there, select them all at once); each track is matched to a song in the Song list above from its title — check the dropdowns. Files still called "Track 04" are matched on Update from whichever part already has track 4 matched, so set one part by hand and the rest follow. Order follows the song list. Timestamp notes are easiest to add from the practice page itself while logged in; they\'re listed here so you can edit or remove them.</p>';
	if ( ! $songs ) {
		echo '<p><strong>Add the song list first</strong> (above) so tracks can be matched to songs.</p>';
	}
	echo '<div id="tcc-tracks-app" data-post="' . (int) $post->ID . '" data-nonce="' . esc_attr( wp_create_nonce( 'tcc_tracks_save' ) ) . '" data-tcc-tracks="' . esc_attr( wp_json_encode( $data ) ) . '"></div>';
	echo '<p style="margin:12px 0 0;display:flex;align-items:center;gap:12px"><button type="button" class="button button-primary" id="tcc-tracks-save">Save tracks</button><span id="tcc-tracks-msg" class="description"></span></p>';
	echo '<input type="hidden" name="_tcc_tracks_json" id="tcc_tracks_json" value="">';
	echo '<input type="hidden" name="_tcc_cues_json" id="tcc_cues_json" value="">';
}

/** Store a tracks list and cue list on a cantata. Returns [ parts => count ]. */
function tcc_store_tracks( int $post_id, $tracks_json, $cues_json ): array {
	$counts = [];
	$tracks = is_string( $tracks_json ) && $tracks_json !== '' ? json_decode( $tracks_json, true ) : null;
	if ( is_array( $tracks ) ) {
		$clean = tcc_match_by_track_number( tcc_sanitize_tracks( $tracks ) );
		if ( $clean ) {
			update_post_meta( $post_id, '_tcc_tracks', $clean );
			foreach ( $clean as $part => $list ) {
				$counts[ $part ] = count( $list );
				foreach ( $list as $t ) {
					update_post_meta( $t['id'], '_tcc_cantata', $post_id );
					update_post_meta( $t['id'], '_tcc_part', $part );
					update_post_meta( $t['id'], '_tcc_song', $t['song'] );
				}
			}
		} else {
			delete_post_meta( $post_id, '_tcc_tracks' );
		}
	}
	$cues = is_string( $cues_json ) && $cues_json !== '' ? json_decode( $cues_json, true ) : null;
	if ( is_array( $cues ) ) {
		$clean = [];
		foreach ( $cues as $c ) {
			$text = sanitize_text_field( (string) ( $c['text'] ?? '' ) );
			if ( $text !== '' ) {
				$clean[] = [ 'id' => preg_replace( '/[^a-zA-Z0-9]/', '', (string) ( $c['id'] ?? '' ) ) ?: wp_generate_password( 8, false ), 'song' => (int) ( $c['song'] ?? -1 ), 't' => round( (float) ( $c['t'] ?? 0 ), 1 ), 'text' => $text, 'parts' => tcc_cue_parts( $c['parts'] ?? [] ), 'm' => tcc_cue_measure( $c['m'] ?? '' ), 'icon' => tcc_cue_icon( $c['icon'] ?? '' ), 'd' => tcc_cue_dur( $c['d'] ?? 0 ) ];
			}
		}
		update_post_meta( $post_id, '_tcc_cues', $clean );
	}
	update_option( 'tcc_tracks_debug', [ 'when' => current_time( 'mysql' ), 'post' => $post_id, 'via' => wp_doing_ajax() ? 'button' : 'post save', 'json_len' => is_string( $tracks_json ) ? strlen( $tracks_json ) : -1, 'decoded' => is_array( $tracks ), 'counts' => $counts ], false );
	return $counts;
}

add_action( 'save_post_post', function ( int $post_id ) {
	if ( ! isset( $_POST['tcc_tracks_nonce'] ) || ! wp_verify_nonce( $_POST['tcc_tracks_nonce'], 'tcc_tracks_save' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		if ( isset( $_POST['_tcc_tracks_json'] ) ) {
			update_option( 'tcc_tracks_debug', [ 'when' => current_time( 'mysql' ), 'post' => $post_id, 'via' => 'post save', 'rejected' => isset( $_POST['tcc_tracks_nonce'] ) ? 'nonce or capability' : 'no nonce field', 'json_len' => strlen( (string) $_POST['_tcc_tracks_json'] ) ], false );
		}
		return;
	}
	tcc_store_tracks( $post_id, isset( $_POST['_tcc_tracks_json'] ) ? wp_unslash( $_POST['_tcc_tracks_json'] ) : '', isset( $_POST['_tcc_cues_json'] ) ? wp_unslash( $_POST['_tcc_cues_json'] ) : '' );
}, 20 );

/** The box's own Save button: stores straight away, independent of the editor's save. */
add_action( 'wp_ajax_tcc_save_tracks', function () {
	$post_id = (int) ( $_POST['post'] ?? 0 );
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) || ! check_ajax_referer( 'tcc_tracks_save', 'nonce', false ) ) {
		wp_send_json_error( 'Not allowed.' );
	}
	$counts = tcc_store_tracks( $post_id, wp_unslash( $_POST['tracks'] ?? '' ), wp_unslash( $_POST['cues'] ?? '' ) );
	$bits = [];
	foreach ( $counts as $part => $n ) {
		$bits[] = $n . ' ' . trim( preg_replace( '/\s*\(.*$/', '', tcc_track_parts()[ $part ] ?? $part ) );
	}
	if ( function_exists( 'tcc_purge_home' ) ) {
		tcc_purge_home();
	}
	wp_send_json_success( [ 'message' => $bits ? 'Saved: ' . implode( ', ', $bits ) . '.' : 'Saved (no tracks).' ] );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
		return;
	}
	$post = get_post();
	if ( ! $post || $post->post_type !== 'post' ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_script( 'tcc-tracks-admin', TCC_URL . 'assets/tracks-admin.js', [ 'jquery' ], TCC_VERSION, true );
} );

/* ---------- Cantata → Practice tracks: overview, what the choir is practicing, import ---------- */

add_action( 'admin_menu', function () {
	add_submenu_page( 'tcc-menu', 'Practice tracks', 'Practice tracks', 'manage_options', 'tcc-tracks', 'tcc_render_tracks_page' );
}, 11 );

function tcc_render_tracks_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $wpdb;
	$cantatas = tcc_get_cantatas();
	$parts    = tcc_track_parts();
	$job      = get_option( 'tcc_import_job' );
	$notice   = sanitize_key( $_GET['tcc_notice'] ?? '' );
	$view     = (int) ( $_GET['cantata'] ?? 0 ) ?: tcc_current_id();
	?>
	<div class="wrap">
		<h1>Practice tracks</h1>
		<?php if ( $notice === 'badfile' ) : ?><div class="notice notice-error is-dismissible"><p>That file isn't a playlist export.</p></div><?php endif; ?>

		<?php $dbg = get_option( 'tcc_tracks_debug' ); if ( $dbg ) : ?>
			<p class="description">Last tracks save: <?php echo esc_html( $dbg['when'] ); ?> · post <?php echo (int) $dbg['post']; ?> · via <?php echo esc_html( $dbg['via'] ); ?><?php if ( ! empty( $dbg['rejected'] ) ) : ?> · <strong style="color:#9c2b1a">rejected: <?php echo esc_html( $dbg['rejected'] ); ?></strong><?php else : ?> · data <?php echo (int) $dbg['json_len']; ?> chars, <?php echo ! empty( $dbg['decoded'] ) ? 'decoded' : 'not decoded'; ?><?php foreach ( (array) ( $dbg['counts'] ?? [] ) as $pp => $n ) : ?> · <?php echo esc_html( $pp . ' ' . $n ); ?><?php endforeach; ?><?php endif; ?></p>
		<?php endif; ?>
		<h2>What's loaded</h2>
		<table class="widefat striped" style="max-width:1100px"><thead><tr><th>Cantata</th><?php foreach ( $parts as $l ) : ?><th><?php echo esc_html( trim( preg_replace( '/\s*\(.*$/', '', $l ) ) ); ?></th><?php endforeach; ?><th></th></tr></thead><tbody>
		<?php foreach ( $cantatas as $c ) :
			$t = tcc_tracks( $c->ID ); $songs = count( tcc_song_titles( $c->ID ) ); ?>
			<tr><td><strong><?php echo esc_html( tcc_year( $c->ID ) ); ?></strong> <?php echo esc_html( $c->post_title ); ?><br><span class="description"><?php echo $songs; ?> songs in the list</span></td>
			<?php foreach ( $parts as $k => $l ) : $n = count( $t[ $k ] ?? [] ); $un = count( array_filter( $t[ $k ] ?? [], fn( $x ) => $x['song'] < 0 ) ); ?>
				<td><?php echo $n ? $n . ' tracks' . ( $un ? ' <span style="color:#9c2b1a">(' . $un . ' unmatched)</span>' : '' ) : '<span style="color:#a7aaad">—</span>'; ?></td>
			<?php endforeach; ?>
			<td><a href="<?php echo esc_url( get_edit_post_link( $c->ID ) ); ?>#tcc_tracks">Edit</a></td></tr>
		<?php endforeach; ?>
		</tbody></table>

		<?php if ( $view ) :
			$songs = tcc_song_titles( $view );
			$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT song, part, SUM(plays) plays, COUNT(DISTINCT person_id) people, SUM(flag = %s) help, SUM(flag = %s) working, SUM(flag = %s) ok FROM ' . tcc_practice_table() . ' WHERE cantata_id = %d GROUP BY song, part', 'help', 'working', 'ok', $view ), ARRAY_A );
			$by = [];
			foreach ( $rows as $r ) { $by[ (int) $r['song'] ][ $r['part'] ] = $r; }
			$groups = function_exists( 'tcc_people_for_cantata' ) ? array_values( tcc_people_for_cantata( $view ) ) : [];
			$people_total = $groups ? count( array_unique( array_merge( ...$groups ) ) ) : 0;
			$active = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(DISTINCT person_id) FROM ' . tcc_practice_table() . ' WHERE cantata_id = %d AND plays > 0', $view ) );
			$helpers = $wpdb->get_results( $wpdb->prepare( 'SELECT person_id, song, part FROM ' . tcc_practice_table() . ' WHERE cantata_id = %d AND flag = %s', $view, 'help' ), ARRAY_A );
		?>
		<h2 style="margin-top:32px">What the choir is practicing
			<form method="get" style="display:inline;margin-left:12px;font-weight:normal"><input type="hidden" name="page" value="tcc-tracks"><select name="cantata" onchange="this.form.submit()"><?php foreach ( $cantatas as $c ) : ?><option value="<?php echo $c->ID; ?>"<?php selected( $view, $c->ID ); ?>><?php echo esc_html( tcc_year( $c->ID ) . ' · ' . $c->post_title ); ?></option><?php endforeach; ?></select></form>
		</h2>
		<p><strong><?php echo $active; ?></strong> of <?php echo $people_total; ?> people on this season's roster have practiced on the site. Marks are what singers set themselves; <em>tricky</em> means they marked the song “Tricky spots”. The Director page in Backstage has the full report.</p>
		<table class="widefat striped" style="max-width:1100px"><thead><tr><th>Song</th><th>Plays</th><th>People</th><th style="color:#9c2b1a">Tricky spots</th><th>Getting there</th><th>Feeling good</th><th>By part</th></tr></thead><tbody>
		<?php foreach ( $songs as $i => $title ) :
			$plays = 0; $people = 0; $help = 0; $working = 0; $ok = 0; $bits = [];
			foreach ( $by[ $i ] ?? [] as $part => $r ) { $plays += $r['plays']; $people = max( $people, (int) $r['people'] ); $help += $r['help']; $working += $r['working']; $ok += $r['ok']; if ( $r['plays'] ) { $bits[] = esc_html( trim( preg_replace( '/\s*\(.*$/', '', $parts[ $part ] ?? $part ) ) ) . ' ' . (int) $r['plays'] . ( $r['help'] ? ' <span style="color:#9c2b1a">(' . (int) $r['help'] . ' help)</span>' : '' ); } } ?>
			<tr><td><?php echo ( $i + 1 ) . '. ' . esc_html( $title ); ?></td><td><?php echo $plays; ?></td><td><?php echo $people; ?></td><td><?php echo $help ? '<strong style="color:#9c2b1a">' . $help . '</strong>' : '—'; ?></td><td><?php echo $working ?: '—'; ?></td><td><?php echo $ok ?: '—'; ?></td><td><?php echo implode( ' · ', $bits ) ?: '—'; ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<?php if ( $helpers ) : ?>
			<p class="description" style="margin-top:8px">Marked tricky: <?php
				$names = [];
				foreach ( $helpers as $h ) { $names[ $h['person_id'] ][] = ( $songs[ $h['song'] ] ?? 'Song ' . ( $h['song'] + 1 ) ); }
				$lines = [];
				foreach ( $names as $pid => $ss ) { $lines[] = '<strong>' . esc_html( get_the_title( $pid ) ) . '</strong> — ' . esc_html( implode( ', ', array_unique( $ss ) ) ); }
				echo implode( ' &nbsp;·&nbsp; ', $lines );
			?></p>
		<?php endif; ?>
		<?php endif; ?>

		<hr style="margin-top:32px">
		<h2>Import playlists from another site</h2>
		<?php if ( $job && ! empty( $job['playlists'] ) && empty( $job['confirmed'] ) ) : ?>
			<p>Found <strong><?php echo count( $job['playlists'] ); ?></strong> playlists from <?php echo esc_html( $job['site'] ); ?>. Check the cantata and part for each, untick any you don't want, then start.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'tcc_import_confirm', 'tcc_import_nonce' ); ?>
				<input type="hidden" name="action" value="tcc_import_confirm">
				<table class="widefat striped" style="max-width:1100px"><thead><tr><th style="width:30px"></th><th>Playlist on the other site</th><th>Published</th><th>Tracks</th><th>Cantata here</th><th>Part</th></tr></thead><tbody>
				<?php foreach ( $job['playlists'] as $i => $pl ) : ?>
					<tr>
						<td><input type="checkbox" name="use[<?php echo $i; ?>]" value="1"<?php checked( $pl['cantata'] && $pl['part'] ); ?>></td>
						<td><strong><?php echo esc_html( $pl['title'] ); ?></strong><br><span class="description"><?php echo esc_html( $pl['tracks'][0]['title'] ?? '' ); ?><?php echo count( $pl['tracks'] ) > 1 ? ', …' : ''; ?></span></td>
						<td><?php echo esc_html( substr( $pl['date'], 0, 10 ) ); ?></td>
						<td><?php echo count( $pl['tracks'] ); ?></td>
						<td><select name="cantata[<?php echo $i; ?>]"><option value="">—</option><?php foreach ( $cantatas as $c ) : ?><option value="<?php echo $c->ID; ?>"<?php selected( $pl['cantata'], $c->ID ); ?>><?php echo esc_html( tcc_year( $c->ID ) . ' · ' . $c->post_title ); ?></option><?php endforeach; ?></select></td>
						<td><select name="part[<?php echo $i; ?>]"><option value="">—</option><?php foreach ( $parts as $k => $l ) : ?><option value="<?php echo esc_attr( $k ); ?>"<?php selected( $pl['part'], $k ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?></select></td>
					</tr>
				<?php endforeach; ?>
				</tbody></table>
				<p class="description">Each audio file is copied from the other site, saved here with a proper name (year, cantata, part, song), matched to the song list and added to the cantata. Files already imported are reused, so running again is safe.</p>
				<p><?php submit_button( 'Start import', 'primary', 'submit', false ); ?> <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tcc_import_cancel' ), 'tcc_import_cancel' ) ); ?>">Cancel</a></p>
			</form>
		<?php elseif ( $job && ! empty( $job['confirmed'] ) ) :
			$total = 0; $done = 0; $failed = 0;
			foreach ( $job['playlists'] as $pl ) {
				if ( empty( $pl['use'] ) ) { continue; }
				foreach ( $pl['tracks'] as $t ) { $total++; if ( ( $t['status'] ?? '' ) === 'done' ) { $done++; } elseif ( ( $t['status'] ?? '' ) === 'failed' ) { $failed++; } }
			} ?>
			<div id="tcc-import" data-nonce="<?php echo esc_attr( wp_create_nonce( 'tcc_import_step' ) ); ?>" data-total="<?php echo $total; ?>" data-done="<?php echo $done; ?>" data-failed="<?php echo $failed; ?>">
				<p><strong id="tcc-import-status">Importing…</strong></p>
				<div style="max-width:700px;height:14px;background:#dcdcde;border-radius:7px;overflow:hidden"><div id="tcc-import-bar" style="height:100%;width:0;background:#355635;transition:width .2s"></div></div>
				<p id="tcc-import-line" class="description" style="margin-top:8px"></p>
				<ul id="tcc-import-log" style="max-height:260px;overflow:auto;font-size:12px;color:#50575e;margin-top:10px"></ul>
				<p><button type="button" class="button" id="tcc-import-pause">Pause</button> <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tcc_import_cancel' ), 'tcc_import_cancel' ) ); ?>">Stop and clear</a></p>
			</div>
			<script>
			(function(){
				var box=document.getElementById('tcc-import'),bar=document.getElementById('tcc-import-bar'),st=document.getElementById('tcc-import-status'),line=document.getElementById('tcc-import-line'),log=document.getElementById('tcc-import-log');
				var total=+box.dataset.total,done=+box.dataset.done,failed=+box.dataset.failed,paused=false;
				function paint(){var p=total?Math.round((done+failed)/total*100):100;bar.style.width=p+'%';st.textContent=(done+failed>=total)?'Finished':'Importing… '+p+'%';line.textContent=done+' of '+total+' copied'+(failed?', '+failed+' failed':'')+'.';}
				function step(){
					if(paused)return;
					if(done+failed>=total){paint();st.textContent='Finished. Reload to see the totals above.';return;}
					var fd=new FormData();fd.append('action','tcc_import_step');fd.append('_wpnonce',box.dataset.nonce);
					fetch(ajaxurl,{method:'POST',credentials:'same-origin',body:fd}).then(function(r){return r.json();}).then(function(j){
						if(!j||!j.success){throw new Error((j&&j.data)||'Server error');}
						var d=j.data;done=d.done;failed=d.failed;total=d.total;
						if(d.message){var li=document.createElement('li');li.textContent=d.message;if(d.error){li.style.color='#9c2b1a';}log.prepend(li);}
						paint();
						if(!d.finished){setTimeout(step,150);}else{st.textContent='Finished. Reload to see the totals above.';}
					}).catch(function(e){st.textContent='Stopped: '+e.message+' (click Pause twice to retry)';});
				}
				document.getElementById('tcc-import-pause').addEventListener('click',function(){paused=!paused;this.textContent=paused?'Resume':'Pause';if(!paused)step();});
				paint();step();
			})();
			</script>
		<?php else : ?>
			<p>Upload a <code>cue-playlists.json</code> export. Tracks are copied here, named properly, matched to the song list and added to the cantata.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( 'tcc_import_upload', 'tcc_import_nonce' ); ?>
				<input type="hidden" name="action" value="tcc_import_upload">
				<p><input type="file" name="tcc_json" accept="application/json,.json" required> <?php submit_button( 'Read the export', 'secondary', 'submit', false ); ?></p>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

/* ---------- import ---------- */

add_action( 'admin_post_tcc_import_upload', function () {
	if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['tcc_import_nonce'] ?? '', 'tcc_import_upload' ) ) {
		wp_die( 'Not allowed.' );
	}
	$raw  = ! empty( $_FILES['tcc_json']['tmp_name'] ) ? file_get_contents( $_FILES['tcc_json']['tmp_name'] ) : '';
	$data = $raw ? json_decode( $raw, true ) : null;
	if ( ! is_array( $data ) || ( $data['format'] ?? '' ) !== 'tcc-cue-export/1' || empty( $data['playlists'] ) ) {
		wp_safe_redirect( admin_url( 'admin.php?page=tcc-tracks&tcc_notice=badfile' ) );
		exit;
	}
	$job = [ 'site' => (string) ( $data['site'] ?? '' ), 'confirmed' => false, 'playlists' => [] ];
	foreach ( $data['playlists'] as $pl ) {
		$tracks = [];
		foreach ( (array) ( $pl['tracks'] ?? [] ) as $t ) {
			if ( empty( $t['audioUrl'] ) ) {
				continue;
			}
			$tracks[] = [ 'title' => sanitize_text_field( (string) ( $t['title'] ?? '' ) ), 'url' => esc_url_raw( (string) $t['audioUrl'] ), 'order' => (int) ( $t['order'] ?? count( $tracks ) ), 'status' => 'pending' ];
		}
		if ( ! $tracks ) {
			continue;
		}
		$job['playlists'][] = [ 'title' => sanitize_text_field( (string) ( $pl['title'] ?? '' ) ), 'date' => sanitize_text_field( (string) ( $pl['date'] ?? '' ) ), 'cantata' => tcc_guess_cantata( (string) ( $pl['date'] ?? '' ) ), 'part' => tcc_guess_part( (string) ( $pl['title'] ?? '' ) ), 'use' => false, 'linked' => false, 'tracks' => $tracks ];
	}
	update_option( 'tcc_import_job', $job, false );
	wp_safe_redirect( admin_url( 'admin.php?page=tcc-tracks' ) );
	exit;
} );

add_action( 'admin_post_tcc_import_confirm', function () {
	if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_POST['tcc_import_nonce'] ?? '', 'tcc_import_confirm' ) ) {
		wp_die( 'Not allowed.' );
	}
	$job = get_option( 'tcc_import_job' );
	if ( ! $job ) {
		wp_safe_redirect( admin_url( 'admin.php?page=tcc-tracks' ) );
		exit;
	}
	$parts = tcc_track_parts();
	foreach ( $job['playlists'] as $i => &$pl ) {
		$pl['cantata'] = (int) ( $_POST['cantata'][ $i ] ?? 0 );
		$pl['part']    = isset( $parts[ $_POST['part'][ $i ] ?? '' ] ) ? $_POST['part'][ $i ] : '';
		$pl['use']     = ! empty( $_POST['use'][ $i ] ) && $pl['cantata'] && $pl['part'];
	}
	unset( $pl );
	$job['confirmed'] = true;
	update_option( 'tcc_import_job', $job, false );
	wp_safe_redirect( admin_url( 'admin.php?page=tcc-tracks' ) );
	exit;
} );

add_action( 'admin_post_tcc_import_cancel', function () {
	if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'tcc_import_cancel' ) ) {
		wp_die( 'Not allowed.' );
	}
	delete_option( 'tcc_import_job' );
	wp_safe_redirect( admin_url( 'admin.php?page=tcc-tracks' ) );
	exit;
} );

function tcc_find_imported( string $url ): int {
	$ids = get_posts( [ 'post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => '_tcc_source_url', 'meta_value' => $url ] );
	return $ids ? (int) $ids[0] : 0;
}

/** Copy one audio file across, named for what it is. Returns the attachment id or a WP_Error. */
function tcc_import_track( array $track, array $pl, int $n ) {
	$existing = tcc_find_imported( $track['url'] );
	if ( $existing ) {
		return $existing;
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = download_url( $track['url'], 300 );
	if ( is_wp_error( $tmp ) ) {
		return $tmp;
	}
	$ext  = strtolower( pathinfo( (string) wp_parse_url( $track['url'], PHP_URL_PATH ), PATHINFO_EXTENSION ) ) ?: 'mp3';
	$song = tcc_clean_song_title( $track['title'] ) ?: 'track';
	$name = sprintf( '%s-%s-%s-%02d-%s.%s', tcc_year( $pl['cantata'] ), sanitize_title( get_the_title( $pl['cantata'] ) ), $pl['part'], $n, sanitize_title( $song ), $ext );
	$id   = media_handle_sideload( [ 'name' => $name, 'tmp_name' => $tmp ], $pl['cantata'], $track['title'] );
	if ( is_wp_error( $id ) ) {
		@unlink( $tmp );
		return $id;
	}
	update_post_meta( $id, '_tcc_source_url', $track['url'] );
	update_post_meta( $id, '_tcc_cantata', $pl['cantata'] );
	update_post_meta( $id, '_tcc_part', $pl['part'] );
	update_post_meta( $id, '_tcc_order', $n );
	return $id;
}

/** Once every track of a row is in, add the list to the cantata, matched to its songs. */
function tcc_import_link( array &$pl ): void {
	if ( $pl['linked'] ) {
		return;
	}
	$songs = tcc_song_titles( $pl['cantata'] );
	$list  = [];
	foreach ( $pl['tracks'] as $t ) {
		if ( ( $t['status'] ?? '' ) === 'done' && ! empty( $t['attachment'] ) ) {
			$list[] = [ 'id' => (int) $t['attachment'], 'song' => tcc_match_song( $t['title'], $songs ), 'title' => $t['title'] ];
		}
	}
	if ( ! $list ) {
		return;
	}
	$all = (array) get_post_meta( $pl['cantata'], '_tcc_tracks', true );
	$all[ $pl['part'] ] = $list;
	update_post_meta( $pl['cantata'], '_tcc_tracks', tcc_sanitize_tracks( $all ) );
	foreach ( $list as $t ) {
		update_post_meta( $t['id'], '_tcc_song', $t['song'] );
	}
	$pl['linked'] = true;
}

add_action( 'wp_ajax_tcc_import_step', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'tcc_import_step', '_wpnonce', false ) ) {
		wp_send_json_error( 'Not allowed.' );
	}
	$job = get_option( 'tcc_import_job' );
	if ( ! $job || empty( $job['confirmed'] ) ) {
		wp_send_json_error( 'No import in progress.' );
	}
	set_time_limit( 330 );
	$message = ''; $error = false; $total = 0; $done = 0; $failed = 0; $worked = false;
	foreach ( $job['playlists'] as &$pl ) {
		if ( empty( $pl['use'] ) ) {
			continue;
		}
		$n = 0;
		foreach ( $pl['tracks'] as &$t ) {
			$n++; $total++;
			if ( ( $t['status'] ?? '' ) === 'done' ) { $done++; continue; }
			if ( ( $t['status'] ?? '' ) === 'failed' ) { $failed++; continue; }
			if ( $worked ) { continue; }
			$worked = true;
			$res = tcc_import_track( $t, $pl, $n );
			if ( is_wp_error( $res ) ) {
				$t['status'] = 'failed'; $t['error'] = $res->get_error_message(); $failed++;
				$message = '✗ ' . $pl['title'] . ' · ' . $t['title'] . ' — ' . $res->get_error_message(); $error = true;
			} else {
				$t['status'] = 'done'; $t['attachment'] = (int) $res; $done++;
				$message = '✓ ' . $pl['title'] . ' · ' . $t['title'];
			}
		}
		unset( $t );
		if ( ! array_filter( $pl['tracks'], fn( $t ) => ( $t['status'] ?? '' ) === 'pending' ) ) {
			tcc_import_link( $pl );
		}
	}
	unset( $pl );
	update_option( 'tcc_import_job', $job, false );
	$finished = ( $done + $failed ) >= $total;
	if ( $finished && function_exists( 'tcc_purge_home' ) ) {
		tcc_purge_home();
	}
	wp_send_json_success( [ 'total' => $total, 'done' => $done, 'failed' => $failed, 'finished' => $finished, 'message' => $message, 'error' => $error ] );
} );

/* ---------- rehearsal editor: help-flag counts beside the song checkboxes ---------- */

function tcc_help_counts( int $cantata_id ): array {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT song, COUNT(*) n FROM ' . tcc_practice_table() . ' WHERE cantata_id = %d AND flag = %s GROUP BY song', $cantata_id, 'help' ), ARRAY_A );
	$out = [];
	foreach ( $rows as $r ) {
		$out[ (int) $r['song'] ] = (int) $r['n'];
	}
	return $out;
}
