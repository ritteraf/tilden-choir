<?php
defined( 'ABSPATH' ) || exit;

/* =====================================================================
 * The Backstage dashboard and the practice page.
 *
 * The page HTML is the same for everyone (so any page cache is harmless):
 * the block ships a "world" payload — rehearsals, songs, which parts have
 * tracks, documents, teams — and the browser asks /me who this device
 * belongs to, then renders a dashboard for that person. An unknown device
 * gets three equal choices: sung before / new this year / just browsing.
 * ===================================================================== */

/** Song numbers "1, 3, 5" (1-based, as stored) → 0-based indices that exist in the song list. */
function tcc_song_indices( string $spec, int $count ): array {
	$nums = [];
	foreach ( preg_split( '/[,\s]+/', $spec ) as $p ) {
		if ( preg_match( '/^(\d+)\s*(?:-|–)\s*(\d+)$/u', $p, $m ) ) {
			$nums = array_merge( $nums, range( (int) $m[1], (int) $m[2] ) );
		} elseif ( preg_match( '/^\d+$/', $p ) ) {
			$nums[] = (int) $p;
		}
	}
	return array_values( array_filter( array_map( fn( $n ) => $n - 1, array_unique( $nums ) ), fn( $i ) => $i >= 0 && $i < $count ) );
}

/** Documents of a cantata grouped everyone / singers / narrators / actors / tech, as data. */
function tcc_docs_data( int $cantata_id ): array {
	if ( ! $cantata_id || ! function_exists( 'tcc_attendee_types' ) ) {
		return [];
	}
	$docs  = array_filter( (array) get_post_meta( $cantata_id, '_tcc_docs', true ), 'is_array' );
	$types = [ 'everyone' => 'Everyone' ] + tcc_attendee_types();
	$by    = array_fill_keys( array_keys( $types ), [] );
	foreach ( $docs as $d ) {
		$url = ! empty( $d['file'] ) ? wp_get_attachment_url( (int) $d['file'] ) : ( $d['url'] ?? '' );
		if ( ! $url ) {
			continue;
		}
		$mime = ! empty( $d['file'] ) ? (string) get_post_mime_type( (int) $d['file'] ) : '';
		$icon = 'doc';
		if ( str_starts_with( $mime, 'image/' ) ) {
			$icon = 'image';
		} elseif ( empty( $d['file'] ) && tcc_youtube_id( $url ) ) {
			$icon = 'play';
		} elseif ( empty( $d['file'] ) && ! preg_match( '~docs\.google\.com|drive\.google\.com|\.pdf($|\?)~i', $url ) ) {
			$icon = 'link';
		}
		$row = [ 'title' => (string) ( $d['title'] ?? '' ), 'url' => $url, 'note' => (string) ( $d['note'] ?? '' ), 'icon' => $icon ];
		$who    = (array) ( $d['who'] ?? [] );
		$groups = array_intersect( $who, array_keys( tcc_attendee_types() ) );
		if ( ! $who || count( $groups ) >= count( tcc_attendee_types() ) ) {
			$by['everyone'][] = $row;
		} else {
			foreach ( $who as $w ) {
				if ( isset( $by[ $w ] ) || str_starts_with( $w, 'role:' ) ) {
					$by[ $w ][] = $row;
				}
			}
		}
	}
	return array_filter( $by );
}

/** Upcoming rehearsals as plain data, soonest first. */
function tcc_rehearsals_data( int $cantata_id, bool $with_past = false ): array {
	$songs = tcc_song_titles( $cantata_id );
	$count = count( $songs );
	$now   = new DateTimeImmutable( 'now', wp_timezone() );
	$out   = [];
	foreach ( tcc_get_rehearsals( $cantata_id ) as $r ) {
		$dt = tcc_rehearsal_dt( $r->ID );
		if ( ! $dt ) {
			continue;
		}
		$end  = get_post_meta( $r->ID, '_tcc_end', true );
		$ends = $end ? new DateTimeImmutable( $dt->format( 'Y-m-d' ) . ' ' . $end, wp_timezone() ) : $dt->setTime( 23, 59 );
		$past = $ends < $now;
		if ( $past && ! $with_past ) {
			continue;
		}
		$full  = tcc_rehearsal_is_full( $r->ID );
		$after = [];
		foreach ( tcc_after_items( $r->ID ) as $item ) {
			$after[] = [
				'time' => ! empty( $item['time'] ) ? tcc_fmt_time( $item['time'] ) . ( ! empty( $item['end'] ) ? '–' . tcc_fmt_time( $item['end'] ) : '' ) : '',
				'what' => $item['what'],
				'who'  => tcc_who_normal( (array) ( $item['who'] ?? [] ) ),
			];
		}
		$out[] = [
			'id'    => $r->ID,
			'title' => get_the_title( $r ),
			'date'  => $dt->format( 'Y-m-d' ),
			'dow'   => $dt->format( 'D' ),
			'day'   => $dt->format( 'j' ),
			'mon'   => $dt->format( 'M' ),
			'long'  => $dt->format( 'l, F j' ),
			'time'  => tcc_rehearsal_time_range( $r->ID ),
			'type'  => get_post_meta( $r->ID, '_tcc_type', true ) ?: 'rehearsal',
			'who'   => tcc_who_normal( (array) get_post_meta( $r->ID, '_tcc_who', true ) ),
			'must'  => tcc_rehearsal_required( $r->ID ),
			'full'  => $full,
			'songs' => $full ? range( 0, max( 0, $count - 1 ) ) : tcc_song_indices( (string) get_post_meta( $r->ID, '_tcc_songs', true ), $count ),
			'focus' => tcc_song_indices( (string) get_post_meta( $r->ID, '_tcc_focus', true ), $count ),
			'after' => $after,
			'note'  => trim( $r->post_content ) ? apply_filters( 'the_content', $r->post_content ) : '',
			'past'  => $past,
		];
	}
	return $out;
}

/** Everything the dashboard needs that is the same for every visitor. */
function tcc_world( int $cantata_id ): array {
	$e      = tcc_entry_data( $cantata_id );
	$songs  = array_values( tcc_song_titles( $cantata_id ) );
	$tracks = tcc_tracks( $cantata_id );
	$parts  = [];
	foreach ( tcc_track_parts() as $k => $label ) {
		$have = []; $ids = []; $durs = [];
		foreach ( (array) ( $tracks[ $k ] ?? [] ) as $t ) {
			if ( (int) $t['song'] >= 0 ) {
				$have[] = (int) $t['song'];
				/* for "continue practicing": which file is which song, and how long it runs */
				$m = wp_get_attachment_metadata( $t['id'] ) ?: [];
				$ids[ (int) $t['song'] ] = (int) $t['id'];
				$durs[ (int) $t['song'] ] = (int) ( $m['length'] ?? 0 );
			}
		}
		$parts[ $k ] = [ 'label' => trim( preg_replace( '/\s*\(.*$/', '', $label ) ), 'count' => count( (array) ( $tracks[ $k ] ?? [] ) ), 'songs' => array_values( array_unique( $have ) ), 'ids' => (object) $ids, 'durs' => (object) $durs ];
	}
	$cues = [];
	foreach ( tcc_cues( $cantata_id ) as $c ) {
		$cues[] = [ 'song' => (int) $c['song'], 'parts' => (array) $c['parts'] ];
	}
	$teams = [];
	$roles = function_exists( 'tcc_volunteer_role_info' ) ? tcc_volunteer_role_info() : [];
	$ids   = get_posts( [ 'post_type' => 'tcc_person', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'title', 'order' => 'ASC',
		'meta_query' => [ [ 'key' => '_tcc_seasons', 'value' => sprintf( 'i:%d;', $cantata_id ), 'compare' => 'LIKE' ] ] ] );
	foreach ( $ids as $id ) {
		$role = (string) get_post_meta( $id, '_tcc_role', true );
		if ( $role && isset( $roles[ $role ] ) ) {
			$teams[ $role ][] = get_the_title( $id );
		}
	}
	$urls = [ 'hub' => home_url( '/backstage/' ), 'practice' => '', 'schedule' => '', 'parts' => [], 'join' => '', 'contact' => '' ];
	/* the public pages a visitor might want next: joining, and a word with the director */
	foreach ( [ 'join' => [ 'join-the-choir', 'join' ], 'contact' => [ 'contact-the-director', 'contact' ] ] as $k => $slugs ) {
		foreach ( $slugs as $slug ) {
			$p = get_page_by_path( $slug );
			if ( $p && $p->post_status === 'publish' ) { $urls[ $k ] = get_permalink( $p ); break; }
		}
	}
	$sched = tcc_backstage_schedule();
	$urls['schedule'] = $sched ? get_permalink( $sched ) : '';
	foreach ( [ 'practice', 'soprano', 'alto', 'tenor', 'bass', 'accompaniment' ] as $slug ) {
		$p = get_page_by_path( $slug );
		if ( $p && $p->post_status === 'publish' ) {
			if ( $slug === 'practice' ) {
				$urls[ $slug ] = get_permalink( $p );
			} else {
				$urls['parts'][ $slug ] = get_permalink( $p );
			}
		}
	}
	$voice = [];
	foreach ( function_exists( 'tcc_voice_parts' ) ? tcc_voice_parts() : [] as $k => $l ) {
		$voice[ $k ] = [ 'label' => trim( preg_replace( '/\s*\(.*$/', '', $l ) ), 'tracks' => explode( '-', $k ) ];
	}
	return [
		'cantata'    => [ 'id' => $cantata_id, 'year' => $e['year'], 'title' => $e['title'], 'perf' => $e['perf_long'], 'perfTime' => $e['perf_time'], 'days' => $e['days'], 'perfDate' => $e['perf'] ? $e['perf']->format( 'Y-m-d' ) : '', 'perfDay' => $e['perf'] ? [ $e['perf']->format( 'D' ), $e['perf']->format( 'j' ), $e['perf']->format( 'M' ) ] : null, 'cover' => $e['cover'] ? tcc_cover_img( $e, 'tcc-cover', 'medium' ) : '', 'tint' => $e['tint'], 'wash' => $e['wash'] ],
		'rehearsals' => tcc_rehearsals_data( $cantata_id ),
		'songs'      => $songs,
		'parts'      => $parts,
		'voice'      => $voice,
		'cues'       => $cues,
		'docs'       => tcc_docs_data( $cantata_id ),
		'types'      => tcc_attendee_types(),
		'rtypes'     => function_exists( 'tcc_rehearsal_types' ) ? tcc_rehearsal_types() : [],
		'roles'      => array_map( fn( $r ) => [ 'label' => $r['label'], 'desc' => $r['desc'] ], $roles ),
		'teams'      => $teams,
		'tasks'      => function_exists( 'tcc_tasks_data' ) ? tcc_tasks_data( $cantata_id ) : [],
		'today'      => current_time( 'Y-m-d' ),
		'urls'       => $urls,
		'signin'     => tcc_signin_config( $cantata_id ),
		'rest'       => esc_url_raw( rest_url( 'tcc/v1/' ) ),
		'token'      => tcc_practice_token( $cantata_id ),
		'icons'      => [ 'doc' => tcc_svg( 'file-doc' ), 'image' => tcc_svg( 'file-image' ), 'play' => tcc_svg( 'file-play' ), 'link' => tcc_svg( 'file-link' ), 'star' => tcc_svg( 'star' ), 'bell' => tcc_svg( 'bell' ), 'then' => tcc_svg( 'then' ), 'note' => tcc_svg( 'note' ) ],
		'icon'       => TCC_URL . 'assets/icons/icon-192.png',
		'canEdit'    => current_user_can( 'edit_posts' ),
		'admin'      => current_user_can( 'edit_posts' ) ? ( ( $dp = get_page_by_path( 'director' ) ) && $dp->post_status === 'publish' ? get_permalink( $dp ) : admin_url( 'admin.php?page=tcc-tracks' ) ) : '',
	];
}

/* ---------- REST: who am I, and how is my practice going ---------- */

add_action( 'rest_api_init', function () {
	$open = fn( WP_REST_Request $r ) => (bool) tcc_rest_cantata( $r );
	register_rest_route( 'tcc/v1', '/me', [ 'methods' => 'GET', 'permission_callback' => $open, 'callback' => function ( WP_REST_Request $r ) {
		global $wpdb;
		$cantata = tcc_rest_cantata( $r );
		$who = tcc_who();
		if ( ! $who || get_post_status( $who ) !== 'publish' ) {
			return [ 'who' => null ];
		}
		$seasons = array_map( 'intval', (array) get_post_meta( $who, '_tcc_seasons', true ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT song, part, plays, seconds, flag, last_played FROM ' . tcc_practice_table() . ' WHERE person_id = %d AND cantata_id = %d', $who, $cantata ), ARRAY_A );
		$notes = $wpdb->get_results( $wpdb->prepare( 'SELECT song, COUNT(*) AS n FROM ' . tcc_pnotes_table() . ' WHERE person_id = %d AND cantata_id = %d GROUP BY song', $who, $cantata ), ARRAY_A );
		$name  = get_the_title( $who );
		/* minutes listened per day over the last two weeks, for the "Your practice" card */
		$today = new DateTimeImmutable( 'today', wp_timezone() );
		$from  = $today->modify( '-13 days' );
		$by    = [];
		if ( function_exists( 'tcc_events_table' ) ) {
			foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT DATE(created) d, SUM(b) secs FROM ' . tcc_events_table() . " WHERE person_id = %d AND cantata_id = %d AND kind = 'listen' AND created >= %s GROUP BY DATE(created)", $who, $cantata, $from->format( 'Y-m-d 00:00:00' ) ), ARRAY_A ) as $x ) {
				$by[ $x['d'] ] = (float) $x['secs'];
			}
		}
		$days = [];
		for ( $d = $from; $d <= $today; $d = $d->modify( '+1 day' ) ) {
			$k = $d->format( 'Y-m-d' );
			$days[] = [ 'd' => $k, 'min' => (int) round( ( $by[ $k ] ?? 0 ) / 60 ) ];
		}
		return [
			'who' => [
				'id'       => $who,
				'name'     => $name,
				'first'    => explode( ' ', trim( $name ) )[0],
				'onSeason' => in_array( $cantata, $seasons, true ),
				'types'    => array_values( (array) get_post_meta( $who, '_tcc_types', true ) ),
				'part'     => (string) get_post_meta( $who, '_tcc_part', true ),
				'role'     => (string) get_post_meta( $who, '_tcc_role', true ),
				'solos'    => (bool) get_post_meta( $who, '_tcc_solos', true ),
			],
			'rows'  => array_map( fn( $x ) => [ 'song' => (int) $x['song'], 'part' => $x['part'], 'plays' => (int) $x['plays'], 'seconds' => (int) $x['seconds'], 'flag' => $x['flag'], 'last' => $x['last_played'] ], $rows ),
			'notes' => array_map( fn( $x ) => [ 'song' => (int) $x['song'], 'n' => (int) $x['n'] ], $notes ),
			'days'  => $days,
		];
	} ] );
} );

/* ---------- blocks ---------- */

add_action( 'init', function () {
	register_block_type( 'tilden/backstage-dashboard', [
		'api_version' => 3, 'title' => 'Backstage dashboard', 'description' => 'The whole Backstage hub: asks an unknown device who it belongs to, then shows each person their own next rehearsal, the songs to work on, their part\'s tracks, their documents and their team. Browsing without signing in shows the general page.',
		'category' => 'widgets', 'icon' => 'dashboard',
		'supports' => [ 'html' => false, 'multiple' => false, 'align' => [ 'wide' ] ],
		'attributes' => [ 'view' => [ 'type' => 'string', 'default' => 'home' ] ],
		'render_callback' => function ( array $attributes = [] ) {
			$view = ( $attributes['view'] ?? 'home' ) === 'schedule' ? 'schedule' : 'home';
			$current = tcc_current_id();
			if ( ! $current ) {
				return '';
			}
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				return '<div ' . get_block_wrapper_attributes() . '><div style="padding:22px 24px;border:1.5px dashed #c8c8c4;border-radius:8px;font-family:sans-serif"><strong style="font-size:16px">' . ( $view === 'schedule' ? 'Backstage schedule' : 'Backstage dashboard' ) . '</strong><br><span style="color:#6b6b6b">' . ( $view === 'schedule' ? 'Every rehearsal this season as cards, month by month, with a "Just mine" filter. It all comes from the rehearsals.' : 'Sign-in for new devices, then a personal page: next rehearsal, songs to work on, tracks, documents, team. Nothing to set here — it all comes from the cantata, the rehearsals and the people.' ) . '</span></div></div>';
			}
			tcc_enqueue_front_assets();
			tcc_enqueue_signin_assets();
			wp_enqueue_style( 'tcc-dash', TCC_URL . 'assets/dashboard.css', [ 'tcc-signin' ], TCC_VERSION );
			wp_enqueue_script( 'tcc-dash', TCC_URL . 'assets/dashboard.js', [ 'tcc-signin' ], TCC_VERSION, [ 'strategy' => 'defer' ] );
			$world = tcc_world( $current );
			if ( $view === 'home' && current_user_can( 'edit_posts' ) && function_exists( 'tcc_director_summary' ) ) {
				$world['director'] = tcc_director_summary( $current );
			}
			if ( $view === 'schedule' ) {
				/* the Schedule tab: the whole season, earlier rehearsals included */
				$world['view']       = 'schedule';
				$world['rehearsals'] = tcc_rehearsals_data( $current, true );
			}
			return '<div ' . get_block_wrapper_attributes() . '><div class="tcc-dash" data-tcc-dash="' . esc_attr( wp_json_encode( $world ) ) . '"><noscript><p>Backstage needs JavaScript turned on.</p></noscript></div></div>';
		},
	] );

	register_block_type( 'tilden/practice', [
		'api_version' => 3, 'title' => 'Practice page', 'description' => 'The full-page practice player: every part\'s tracks, the person\'s own part first, with the director\'s notes and their own notes laid out under the player. Put it on a page called Practice.',
		'category' => 'widgets', 'icon' => 'controls-play',
		'supports' => [ 'html' => false, 'multiple' => false, 'align' => [ 'wide', 'full' ] ],
		'attributes' => [],
		'render_callback' => function () {
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				return '<div ' . get_block_wrapper_attributes() . '><div style="padding:22px 24px;border:1.5px dashed #c8c8c4;border-radius:8px;font-family:sans-serif"><strong style="font-size:16px">Practice page</strong><br><span style="color:#6b6b6b">The whole-page player. Each person lands on their own part; the rest are a tap away.</span></div></div>';
			}
			tcc_enqueue_front_assets();
			return '<div ' . get_block_wrapper_attributes() . '>' . tcc_render_player( 'page', true ) . '</div>';
		},
	] );
} );
