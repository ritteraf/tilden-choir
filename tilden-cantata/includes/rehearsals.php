<?php
defined( 'ABSPATH' ) || exit;

/* =====================================================================
 * Rehearsals: one record per rehearsal, attached to a cantata post.
 * ===================================================================== */

define( 'TCC_REH', 'tcc_rehearsal' );

function tcc_rehearsal_types(): array {
	return [
		'rehearsal'   => 'Rehearsal',
		'performance' => 'Performance day',
	];
}

/**
 * Everything a "who" checkbox row can offer: the people types, then the volunteer teams
 * (keyed role:<slug>, labels from Cantata → Settings). A person matches a team through their Volunteer role.
 */
function tcc_who_options(): array {
	$out = tcc_attendee_types();
	if ( function_exists( 'tcc_volunteer_roles' ) ) {
		foreach ( tcc_volunteer_roles() as $k => $label ) {
			$out[ 'role:' . $k ] = $label;
		}
	}
	return $out;
}

/** Who a rehearsal (or an after-rehearsal item) is for. */
function tcc_attendee_types(): array {
	return apply_filters( 'tcc_attendee_types', [
		'singers'   => 'Singers',
		'narrators' => 'Narrators',
		'actors'    => 'Actors',
		'tech'      => 'Tech crew',
	] );
}

function tcc_rehearsal_fields(): array {
	return [
		'date'    => [ 'Date', 'date', '' ],
		'start'   => [ 'Start time', 'time', '' ],
		'end'     => [ 'End time', 'time', '' ],
		'type'    => [ 'Type', 'select', '' ],
		'who'     => [ 'Who attends', 'who', 'Leave all unticked for everyone.' ],
		'must'    => [ 'Required to perform', 'who', 'Tick the groups who have to be at this rehearsal to take part in the performance. Leave all unticked if nobody is required.' ],
		'full'    => [ 'Full run-through', 'check', 'Tick when the rehearsal goes through the whole work start to finish. The song list below is then ignored and the schedule shows a run-through banner instead.' ],
		'songs'   => [ 'Songs', 'songs', 'Tick what this rehearsal covers. The list comes from the cantata\'s Song list field. The star marks a song to focus on before this rehearsal; singers see it as a hint on their dashboard and in the player.' ],
		'focus'   => [ 'Focus', 'focus', '' ],
		'after'   => [ 'Afterward', 'after', 'Separate items once rehearsal ends (solo tryouts, staging team meeting), each with its own time and attendees.' ],
		'cantata' => [ 'Cantata', 'cantata', 'Which year this belongs to. Defaults to this year\'s cantata.' ],
	];
}

add_action( 'init', function () {
	register_post_type( TCC_REH, [
		'labels' => [
			'name' => 'Rehearsals', 'singular_name' => 'Rehearsal', 'add_new' => 'Add rehearsal', 'add_new_item' => 'Add rehearsal',
			'edit_item' => 'Edit rehearsal', 'new_item' => 'New rehearsal', 'all_items' => 'Rehearsals', 'search_items' => 'Search rehearsals',
			'not_found' => 'No rehearsals yet.', 'menu_name' => 'Rehearsals',
		],
		'public'       => false,
		'show_ui'      => true,
		'show_in_menu' => 'tcc-menu',
		'show_in_rest' => true,
		'supports'     => [ 'title', 'editor' ],
		'menu_icon'    => 'dashicons-calendar-alt',
	] );
	foreach ( tcc_rehearsal_fields() as $key => $f ) {
		register_post_meta( TCC_REH, '_tcc_' . $key, [
			'type' => $f[1] === 'cantata' ? 'integer' : ( in_array( $f[1], [ 'who', 'after' ], true ) ? 'array' : 'string' ), 'single' => true,
			'show_in_rest' => $f[1] === 'who' ? [ 'schema' => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ] ] : ( $f[1] === 'after' ? [ 'schema' => [ 'type' => 'array', 'items' => [ 'type' => 'object', 'additionalProperties' => true ] ] ] : true ),
			'auth_callback' => fn() => current_user_can( 'edit_posts' ),
			'sanitize_callback' => fn( $v ) => tcc_sanitize_rehearsal_field( $key, $v ),
		] );
	}
} );

function tcc_sanitize_rehearsal_field( string $key, $v ) {
	$type = tcc_rehearsal_fields()[ $key ][1] ?? 'text';
	$v = is_string( $v ) ? trim( $v ) : $v;
	return match ( $type ) {
		'date'     => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $v ) ? $v : '',
		'time'     => preg_match( '/^\d{2}:\d{2}$/', (string) $v ) ? $v : '',
		'select'   => array_key_exists( (string) $v, tcc_rehearsal_types() ) ? $v : ( $v === 'required' ? 'required' : 'rehearsal' ),
		'who'      => tcc_sanitize_who( $v ),
		'songs', 'focus' => tcc_sanitize_songs( $v ),
		'check'    => $v ? '1' : '',
		'after'    => tcc_sanitize_after( $v ),
		'cantata'  => absint( $v ),
		'textarea' => sanitize_textarea_field( (string) $v ),
		default    => sanitize_text_field( (string) $v ),
	};
}

/** Keep only known types and volunteer teams. */
function tcc_sanitize_who( $v ): array {
	$keys = array_map( fn( $k ) => preg_replace( '/[^a-z0-9_:\-]/', '', strtolower( (string) $k ) ), (array) $v );
	return array_values( array_intersect( array_keys( tcc_who_options() ), $keys ) );
}

/** Checkbox array or typed "1-4, 6" → "1, 2, 3, 4, 6". */
function tcc_sanitize_songs( $v ): string {
	$nums = [];
	foreach ( is_array( $v ) ? $v : preg_split( '/[,\s]+/', (string) $v ) as $part ) {
		$part = trim( (string) $part );
		if ( preg_match( '/^(\d+)\s*(?:-|–)\s*(\d+)$/u', $part, $m ) ) {
			$nums = array_merge( $nums, range( (int) $m[1], (int) $m[2] ) );
		} elseif ( preg_match( '/^\d+$/', $part ) ) {
			$nums[] = (int) $part;
		}
	}
	$nums = array_values( array_unique( array_filter( $nums ) ) );
	sort( $nums );
	return implode( ', ', $nums );
}

function tcc_sanitize_after( $rows ): array {
	$out = [];
	foreach ( (array) $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$what = sanitize_text_field( (string) ( $row['what'] ?? '' ) );
		if ( $what === '' ) {
			continue;
		}
		$out[] = [
			'time' => tcc_sanitize_rehearsal_field( 'start', (string) ( $row['time'] ?? '' ) ),
			'end'  => tcc_sanitize_rehearsal_field( 'end', (string) ( $row['end'] ?? '' ) ),
			'what' => $what,
			'who'  => tcc_sanitize_who( $row['who'] ?? [] ),
		];
	}
	return $out;
}

/* ---------- admin menu: Cantata → Cantatas / Rehearsals / Settings ---------- */

add_action( 'admin_menu', function () {
	add_menu_page( 'Cantata', 'Cantata', 'edit_posts', 'tcc-menu', '', 'dashicons-playlist-audio', 5 );
	add_submenu_page( 'tcc-menu', 'Cantatas', 'Cantatas', 'edit_posts', 'edit.php?category_name=' . TCC_CATEGORY );
	// Rehearsals appear here via show_in_menu.
	add_submenu_page( 'tcc-menu', 'Add rehearsals in bulk', 'Add in bulk', 'edit_posts', 'tcc-season', 'tcc_render_season_page' );
	add_submenu_page( 'tcc-menu', 'Cantata settings', 'Settings', 'manage_options', 'tcc-settings', 'tcc_render_settings_page' );
	remove_submenu_page( 'tcc-menu', 'tcc-menu' );
}, 9 );

/* ---------- meta box ---------- */

add_action( 'add_meta_boxes_' . TCC_REH, function () {
	add_meta_box( 'tcc_rehearsal', 'Rehearsal details', 'tcc_render_rehearsal_box', TCC_REH, 'normal', 'high' );
} );

function tcc_render_rehearsal_box( WP_Post $post ) {
	wp_nonce_field( 'tcc_reh_save', 'tcc_reh_nonce' );
	echo '<table class="form-table"><tbody>';
	foreach ( tcc_rehearsal_fields() as $key => [ $label, $type, $desc ] ) {
		if ( $type === 'focus' ) {
			continue; // drawn inside the song list
		}
		$name = '_tcc_' . $key;
		$val  = get_post_meta( $post->ID, $name, true );
		echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td>';
		switch ( $type ) {
			case 'date':
				printf( '<input type="date" id="%1$s" name="%1$s" value="%2$s">', esc_attr( $name ), esc_attr( $val ) );
				break;
			case 'time':
				printf( '<input type="time" id="%1$s" name="%1$s" value="%2$s">', esc_attr( $name ), esc_attr( $val ) );
				break;
			case 'select':
				echo '<select id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '">';
				foreach ( tcc_rehearsal_types() as $k => $l ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $val ?: 'rehearsal', $k, false ), esc_html( $l ) );
				}
				echo '</select>';
				break;
			case 'cantata':
				$cur = tcc_current_id();
				echo '<select id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" data-tcc-cantata-select>';
				foreach ( tcc_get_cantatas() as $c ) {
					printf( '<option value="%d"%s>%s (%s)</option>', $c->ID, selected( (int) $val ?: $cur, $c->ID, false ), esc_html( $c->post_title ), esc_html( tcc_year( $c->ID ) ) );
				}
				echo '</select>';
				break;
			case 'check':
				printf( '<label><input type="checkbox" id="%1$s" name="%1$s" value="1"%2$s data-tcc-full> Yes</label>', esc_attr( $name ), checked( (string) $val, '1', false ) );
				break;
			case 'songs':
				$picked = array_map( 'intval', array_filter( preg_split( '/[,\s]+/', (string) $val ) ) );
				$focus  = array_map( 'intval', array_filter( preg_split( '/[,\s]+/', (string) get_post_meta( $post->ID, '_tcc_focus', true ) ) ) );
				$chosen = (int) get_post_meta( $post->ID, '_tcc_cantata', true ) ?: tcc_current_id();
				foreach ( tcc_get_cantatas() as $c ) {
					$songs = tcc_song_titles( $c->ID );
					printf( '<div class="tcc-songs" data-cantata="%d"%s>', $c->ID, $c->ID === $chosen ? '' : ' hidden' );
					if ( ! $songs ) {
						printf( '<p class="description">%s has no song list yet. Add one on the cantata post and come back.</p>', esc_html( $c->post_title ) );
					}
					$help = function_exists( 'tcc_help_counts' ) ? tcc_help_counts( $c->ID ) : [];
					foreach ( $songs as $i => $title ) {
						$n = $i + 1;
						printf( '<div style="display:flex;align-items:center;gap:6px;margin:2px 0"><label style="flex:1 1 auto"><input type="checkbox" name="%s[]" value="%d"%s> %d. %s%s</label><label title="Focus on this one" style="color:#8a6418;font-size:12px;white-space:nowrap"><input type="checkbox" name="_tcc_focus[]" value="%d"%s> ★ focus</label></div>', esc_attr( $name ), $n, checked( in_array( $n, $picked, true ), true, false ), $n, esc_html( $title ),
							! empty( $help[ $i ] ) ? ' <span style="color:#9c2b1a;font-size:12px">· ' . (int) $help[ $i ] . ' marked tricky</span>' : '', $n, checked( in_array( $n, $focus, true ), true, false ) );
					}
					echo '</div>';
				}
				break;
			case 'who':
				$val = $key === 'must' && ! $val ? tcc_rehearsal_required( $post->ID ) : (array) $val; // carries an old "Required rehearsal" over
				$val = (array) $val;
				echo tcc_who_checkboxes( $name, $val );
				break;
			case 'after':
				$rows = array_values( (array) $val );
				$rows[] = [ 'time' => '', 'end' => '', 'what' => '', 'who' => [] ];
				$rows[] = [ 'time' => '', 'end' => '', 'what' => '', 'who' => [] ];
				echo '<table class="widefat striped tcc-after" style="max-width:820px"><thead><tr><th style="width:110px">From</th><th style="width:110px">To</th><th>What</th><th style="width:260px">Who</th></tr></thead><tbody>';
				foreach ( $rows as $i => $row ) {
					echo '<tr>';
					printf( '<td><input type="time" name="%1$s[%2$d][time]" value="%3$s"></td><td><input type="time" name="%1$s[%2$d][end]" value="%4$s"></td>', esc_attr( $name ), $i, esc_attr( $row['time'] ?? '' ), esc_attr( $row['end'] ?? '' ) );
					printf( '<td><input type="text" name="%1$s[%2$d][what]" value="%3$s" class="large-text" placeholder="e.g. Solo and duet tryouts"></td>', esc_attr( $name ), $i, esc_attr( $row['what'] ?? '' ) );
					echo '<td>' . tcc_who_checkboxes( $name . '[' . $i . '][who]', (array) ( $row['who'] ?? [] ), true ) . '</td></tr>';
				}
				echo '</tbody></table><p class="description">Rows with an empty "What" are ignored. Two blank rows are always offered; save to get more. Volunteer teams (Staging, Costuming …) are the roles set on each person under Cantata → People.</p>';
				break;
			case 'textarea':
				printf( '<textarea id="%1$s" name="%1$s" rows="3" class="large-text">%2$s</textarea>', esc_attr( $name ), esc_textarea( $val ) );
				break;
			default:
				printf( '<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text">', esc_attr( $name ), esc_attr( $val ) );
		}
		if ( $desc ) {
			echo '<p class="description">' . esc_html( $desc ) . '</p>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<script>document.addEventListener("change",function(e){if(!e.target.matches("[data-tcc-cantata-select]"))return;document.querySelectorAll(".tcc-songs").forEach(function(d){d.hidden=d.dataset.cantata!==e.target.value;});});'
		. '(function(){function sync(){var f=document.querySelector("[data-tcc-full]");if(!f)return;document.querySelectorAll(".tcc-songs").forEach(function(d){d.style.opacity=f.checked?"0.4":"";d.style.pointerEvents=f.checked?"none":"";});}document.addEventListener("change",function(e){if(e.target.matches("[data-tcc-full]"))sync();});sync();})();</script>';
	echo '<p class="description">Anything else about this rehearsal (parking, who brings what, a video to watch first) goes in the editor above; it shows under the entry on the schedule.</p>';
}

add_action( 'save_post_' . TCC_REH, function ( int $post_id, WP_Post $post ) {
	if ( ! isset( $_POST['tcc_reh_nonce'] ) || ! wp_verify_nonce( $_POST['tcc_reh_nonce'], 'tcc_reh_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	foreach ( tcc_rehearsal_fields() as $key => $f ) {
		$name = '_tcc_' . $key;
		if ( ! array_key_exists( $name, $_POST ) ) {
			if ( in_array( $f[1], [ 'who', 'songs', 'focus', 'check' ], true ) ) {
				delete_post_meta( $post_id, $name ); // nothing ticked
			}
			continue;
		}
		$clean = tcc_sanitize_rehearsal_field( $key, wp_unslash( $_POST[ $name ] ) );
		if ( $clean === '' || $clean === 0 || $clean === [] ) {
			delete_post_meta( $post_id, $name );
		} else {
			update_post_meta( $post_id, $name, $clean );
		}
	}
	if ( function_exists( 'tcc_purge_home' ) ) {
		tcc_purge_home();
	}
}, 10, 2 );

/* ---------- who is expected: the roster filtered by this rehearsal's attendee types ---------- */

/** People (this cantata's season) expected at a rehearsal or after-item. $who empty = everyone. Returns [ type => [ids] ]. */
function tcc_expected_people( int $cantata_id, array $who ): array {
	if ( ! function_exists( 'tcc_voice_parts' ) || ! $cantata_id ) {
		return [];
	}
	$types = tcc_attendee_types();
	$who   = tcc_who_normal( $who ) ?: array_keys( $types );
	$ids   = get_posts( [
		'post_type' => TCC_PERSON, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'title', 'order' => 'ASC',
		'meta_query' => [ [ 'key' => '_tcc_seasons', 'value' => sprintf( 'i:%d;', $cantata_id ), 'compare' => 'LIKE' ] ],
	] );
	$groups = array_fill_keys( $who, [] );
	foreach ( $ids as $id ) {
		foreach ( (array) get_post_meta( $id, '_tcc_types', true ) as $t ) {
			if ( isset( $groups[ $t ] ) ) {
				$groups[ $t ][] = $id;
			}
		}
		$role = 'role:' . get_post_meta( $id, '_tcc_role', true );
		if ( isset( $groups[ $role ] ) ) {
			$groups[ $role ][] = $id;
		}
	}
	return array_filter( $groups );
}

add_action( 'add_meta_boxes_' . TCC_REH, function () {
	add_meta_box( 'tcc_expected', 'Who is expected (from the roster)', 'tcc_render_expected_box', TCC_REH, 'normal', 'default' );
} );

function tcc_render_expected_box( WP_Post $post ) {
	$cantata = (int) get_post_meta( $post->ID, '_tcc_cantata', true ) ?: tcc_current_id();
	if ( ! $cantata ) {
		echo '<p class="description">Pick a cantata and save to see who is expected.</p>';
		return;
	}
	if ( ! function_exists( 'tcc_voice_parts' ) ) {
		return;
	}
	if ( $post->post_status === 'auto-draft' ) {
		echo '<p class="description">Save the rehearsal first; this box then lists everyone on the roster whose type matches "Who attends".</p>';
		return;
	}
	echo '<p class="description" style="margin:0 0 10px">Nothing to set here. This lists the people that "Who attends" and each afterward item resolve to, with their contact details ready to copy.</p>';
	echo tcc_expected_html( 'This rehearsal', $cantata, (array) get_post_meta( $post->ID, '_tcc_who', true ) );
	$must = tcc_rehearsal_required( $post->ID );
	if ( $must ) {
		echo tcc_expected_html( 'Required to perform', $cantata, $must );
	}
	foreach ( (array) get_post_meta( $post->ID, '_tcc_after', true ) as $item ) {
		if ( ! empty( $item['what'] ) ) {
			echo tcc_expected_html( 'Afterward: ' . $item['what'], $cantata, (array) ( $item['who'] ?? [] ) );
		}
	}
	echo '<p class="description">Drawn from Cantata → People: everyone whose Seasons include this cantata and whose type matches. Add or fix people there.</p>';
}

function tcc_expected_html( string $heading, int $cantata, array $who ): string {
	$groups = tcc_expected_people( $cantata, $who );
	$types  = tcc_who_options();
	$parts  = tcc_voice_parts();
	$all    = $groups ? array_values( array_unique( array_merge( ...array_values( $groups ) ) ) ) : [];
	$out    = '<div style="margin:0 0 18px;padding:12px 14px;border:1px solid #dcdcde;border-radius:4px;background:#fff">';
	$out   .= '<p style="margin:0 0 8px"><strong>' . esc_html( $heading ) . '</strong> — ' . esc_html( tcc_who_label( $who ) ) . ' · <strong>' . count( $all ) . '</strong> people</p>';
	if ( ! $all ) {
		return $out . '<p class="description" style="margin:0">Nobody on the roster matches yet.</p></div>';
	}
	$emails = []; $phones = [];
	foreach ( $groups as $type => $ids ) {
		$names = [];
		foreach ( $ids as $id ) {
			$part = $type === 'singers' ? ( $parts[ get_post_meta( $id, '_tcc_part', true ) ] ?? '' ) : '';
			$names[] = '<a href="' . esc_url( get_edit_post_link( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a>' . ( $part ? ' <span style="color:#757575">(' . esc_html( $part ) . ')</span>' : '' );
		}
		$out .= '<p style="margin:4px 0"><strong>' . esc_html( $types[ $type ] ) . ' (' . count( $ids ) . ')</strong>: ' . implode( ', ', $names ) . '</p>';
	}
	foreach ( $all as $id ) {
		$e = get_post_meta( $id, '_tcc_email', true ); $p = get_post_meta( $id, '_tcc_phone', true );
		if ( $e ) { $emails[] = $e; }
		if ( $p ) { $phones[] = $p; }
	}
	$emails = array_unique( $emails ); $phones = array_unique( $phones );
	if ( $emails ) {
		$out .= '<p style="margin:10px 0 4px"><a class="button button-small" href="mailto:?bcc=' . esc_attr( rawurlencode( implode( ',', $emails ) ) ) . '">Email these ' . count( $emails ) . '</a> <span class="description">(opens your mail app with everyone in BCC)</span></p>';
		$out .= '<textarea readonly rows="2" class="large-text code" onclick="this.select()">' . esc_textarea( implode( ', ', $emails ) ) . '</textarea>';
	}
	if ( $phones ) {
		$out .= '<textarea readonly rows="2" class="large-text code" style="margin-top:6px" onclick="this.select()">' . esc_textarea( implode( ', ', $phones ) ) . '</textarea>';
	}
	return $out . '</div>';
}

/* ---------- list table: date, time, type columns; sort by date ---------- */

add_filter( 'manage_' . TCC_REH . '_posts_columns', function ( array $cols ) {
	return [ 'cb' => $cols['cb'], 'title' => 'Rehearsal', 'tcc_date' => 'When', 'tcc_type' => 'Type', 'tcc_who' => 'Who', 'tcc_cantata' => 'Cantata' ];
} );
add_action( 'manage_' . TCC_REH . '_posts_custom_column', function ( string $col, int $id ) {
	if ( $col === 'tcc_date' ) {
		$d = tcc_rehearsal_dt( $id );
		echo $d ? esc_html( $d->format( 'D, M j' ) . ' · ' . tcc_rehearsal_time_range( $id ) ) : '—';
	} elseif ( $col === 'tcc_type' ) {
		$req = tcc_required_label( $id );
		echo esc_html( tcc_rehearsal_types()[ get_post_meta( $id, '_tcc_type', true ) ?: 'rehearsal' ] ?? 'Rehearsal' ) . ( $req ? ' <span style="color:#9c2b1a">· ' . esc_html( lcfirst( $req ) ) . '</span>' : '' ) . ( tcc_rehearsal_is_full( $id ) ? ' <span style="color:#757575">· full run-through</span>' : '' );
	} elseif ( $col === 'tcc_who' ) {
		$who = (array) get_post_meta( $id, '_tcc_who', true );
		$cantata = (int) get_post_meta( $id, '_tcc_cantata', true );
		$g = tcc_expected_people( $cantata, $who );
		$n = $g ? count( array_unique( array_merge( ...array_values( $g ) ) ) ) : 0;
		echo esc_html( tcc_who_label( $who ) ) . ( $n ? ' <span style="color:#757575">(' . $n . ')</span>' : '' );
	} elseif ( $col === 'tcc_cantata' ) {
		$c = (int) get_post_meta( $id, '_tcc_cantata', true );
		echo $c ? esc_html( get_the_title( $c ) ) : '—';
	}
}, 10, 2 );
add_filter( 'manage_edit-' . TCC_REH . '_sortable_columns', fn( $c ) => $c + [ 'tcc_date' => 'tcc_date' ] );
add_action( 'pre_get_posts', function ( WP_Query $q ) {
	if ( ! is_admin() || ! $q->is_main_query() || $q->get( 'post_type' ) !== TCC_REH ) {
		return;
	}
	$orderby = $q->get( 'orderby' );
	if ( ! $orderby || $orderby === 'tcc_date' ) {
		$q->set( 'meta_key', '_tcc_date' );
		$q->set( 'orderby', 'meta_value' );
		if ( ! $orderby ) {
			$q->set( 'order', 'ASC' );
		}
	}
} );

/* ---------- read helpers ---------- */

function tcc_rehearsal_dt( int $id ): ?DateTimeImmutable {
	$d = get_post_meta( $id, '_tcc_date', true );
	if ( ! $d ) {
		return null;
	}
	$t = get_post_meta( $id, '_tcc_start', true ) ?: '00:00';
	try {
		return new DateTimeImmutable( $d . ' ' . $t, wp_timezone() );
	} catch ( Exception $e ) {
		return null;
	}
}

function tcc_fmt_time( string $hm ): string {
	if ( ! preg_match( '/^(\d{2}):(\d{2})$/', $hm, $m ) ) {
		return '';
	}
	$h = (int) $m[1]; $min = $m[2]; $ap = $h >= 12 ? 'PM' : 'AM'; $h12 = $h % 12 ?: 12;
	return $min === '00' ? "$h12 $ap" : "$h12:$min $ap";
}

function tcc_rehearsal_time_range( int $id ): string {
	$s = get_post_meta( $id, '_tcc_start', true );
	$e = get_post_meta( $id, '_tcc_end', true );
	if ( ! $s ) {
		return '';
	}
	if ( ! $e ) {
		return tcc_fmt_time( $s );
	}
	// "7–8 PM" when both share a meridiem and land on the hour, else "7:00 PM – 8:30 PM"
	$sa = (int) substr( $s, 0, 2 ) >= 12 ? 'PM' : 'AM';
	$ea = (int) substr( $e, 0, 2 ) >= 12 ? 'PM' : 'AM';
	if ( $sa === $ea ) {
		return str_replace( ' ' . $sa, '', tcc_fmt_time( $s ) ) . '–' . tcc_fmt_time( $e );
	}
	return tcc_fmt_time( $s ) . ' – ' . tcc_fmt_time( $e );
}

/** "Singers, Actors" / "Everyone". */
/** The people-type checkboxes followed by the volunteer-team checkboxes. */
function tcc_who_checkboxes( string $name, array $val, bool $compact = false ): string {
	$types = tcc_attendee_types();
	$style = $compact ? 'margin-right:10px;white-space:nowrap' : 'margin-right:16px;white-space:nowrap';
	$out = '';
	$first_role = array_key_first( array_diff_key( tcc_who_options(), $types ) );
	foreach ( tcc_who_options() as $k => $l ) {
		if ( $k === $first_role ) {
			$out .= $compact ? '<br>' : '<br><span class="description" style="display:inline-block;margin:6px 10px 2px 0">Volunteer teams:</span>';
		}
		$out .= sprintf( '<label style="%s"><input type="checkbox" name="%s[]" value="%s"%s> %s</label>', $style, esc_attr( $name ), esc_attr( $k ), checked( in_array( $k, $val, true ), true, false ), esc_html( $l ) );
	}
	return $out;
}

/**
 * Normalise a "who" list: known keys only, in option order. Empty (or every people type ticked) means everyone.
 * Volunteer teams never make it "everyone"; "Everyone" already includes them.
 */
function tcc_who_normal( array $who ): array {
	$opts  = tcc_who_options();
	$types = tcc_attendee_types();
	$who   = array_values( array_intersect( array_keys( $opts ), $who ) );
	$tk    = array_intersect( $who, array_keys( $types ) );
	if ( ! $who || count( $tk ) === count( $types ) ) {
		return [];
	}
	return $who;
}

function tcc_who_label( array $who ): string {
	$who = tcc_who_normal( $who );
	if ( ! $who ) {
		return 'Everyone';
	}
	$opts = tcc_who_options();
	return implode( ', ', array_map( fn( $k ) => $opts[ $k ], $who ) );
}

function tcc_who_pills( array $who, string $class = 'tcc-who' ): string {
	$who = tcc_who_normal( $who );
	if ( ! $who ) {
		return '<span class="' . esc_attr( $class ) . ' ' . esc_attr( $class ) . '--all">Everyone</span>';
	}
	$opts = tcc_who_options();
	return implode( '', array_map( fn( $k ) => '<span class="' . esc_attr( $class ) . ' ' . esc_attr( $class ) . '--' . ( str_starts_with( $k, 'role:' ) ? 'role' : $k ) . '">' . esc_html( $opts[ $k ] ) . '</span>', $who ) );
}

/** Rehearsals for a cantata, soonest first. */
function tcc_get_rehearsals( int $cantata_id ): array {
	$posts = get_posts( [
		'post_type' => TCC_REH, 'post_status' => 'publish', 'posts_per_page' => -1, 'no_found_rows' => true,
		'meta_key' => '_tcc_cantata', 'meta_value' => $cantata_id,
	] );
	usort( $posts, function ( $a, $b ) {
		$da = tcc_rehearsal_dt( $a->ID ); $db = tcc_rehearsal_dt( $b->ID );
		return ( $da ? $da->getTimestamp() : 0 ) <=> ( $db ? $db->getTimestamp() : 0 );
	} );
	return $posts;
}

/** The next rehearsal (today counts until its end time has passed), or null. */
function tcc_next_rehearsal( int $cantata_id ): ?WP_Post {
	$now = new DateTimeImmutable( 'now', wp_timezone() );
	foreach ( tcc_get_rehearsals( $cantata_id ) as $r ) {
		$dt = tcc_rehearsal_dt( $r->ID );
		if ( ! $dt ) {
			continue;
		}
		$end = get_post_meta( $r->ID, '_tcc_end', true );
		$ends = $end ? new DateTimeImmutable( $dt->format( 'Y-m-d' ) . ' ' . $end, wp_timezone() ) : $dt->setTime( 23, 59 );
		if ( $ends >= $now ) {
			return $r;
		}
	}
	return null;
}

/** "1-4" / "5, 6, 9" → titles from the cantata's song list. */
/**
 * Groups whose attendance at this rehearsal is required to perform. Empty = nobody.
 * A rehearsal saved as the old "Required rehearsal" type counts as required for everyone who attends.
 */
function tcc_rehearsal_required( int $reh_id ): array {
	$must = (array) get_post_meta( $reh_id, '_tcc_must', true );
	if ( ! $must && get_post_meta( $reh_id, '_tcc_type', true ) === 'required' ) {
		$who  = (array) get_post_meta( $reh_id, '_tcc_who', true );
		$must = $who ?: array_keys( tcc_attendee_types() );
	}
	return array_values( array_intersect( array_keys( tcc_who_options() ), $must ) );
}

/** "Required to perform" or "Required for Singers, Actors", depending on whether the required groups are everyone who attends. */
function tcc_required_label( int $reh_id ): string {
	$must = tcc_rehearsal_required( $reh_id );
	if ( ! $must ) {
		return '';
	}
	$who = tcc_who_normal( (array) get_post_meta( $reh_id, '_tcc_who', true ) ) ?: array_keys( tcc_attendee_types() );
	if ( ! array_diff( $who, $must ) ) {
		return 'Required to perform';
	}
	$opts = tcc_who_options();
	return 'Required for ' . implode( ', ', array_map( fn( $k ) => $opts[ $k ], $must ) );
}

/** True when the rehearsal is a full run-through of the work. */
function tcc_rehearsal_is_full( int $reh_id ): bool {
	return (bool) get_post_meta( $reh_id, '_tcc_full', true );
}

function tcc_rehearsal_song_titles( int $reh_id, int $cantata_id ): array {
	if ( tcc_rehearsal_is_full( $reh_id ) ) {
		return tcc_song_titles( $cantata_id );
	}
	$spec = get_post_meta( $reh_id, '_tcc_songs', true );
	if ( ! $spec ) {
		return [];
	}
	$songs = tcc_song_titles( $cantata_id );
	$nums = [];
	foreach ( preg_split( '/[,\s]+/', $spec ) as $part ) {
		if ( preg_match( '/^(\d+)\s*(?:-|–)\s*(\d+)$/u', $part, $m ) ) {
			$nums = array_merge( $nums, range( (int) $m[1], (int) $m[2] ) );
		} elseif ( preg_match( '/^\d+$/', $part ) ) {
			$nums[] = (int) $part;
		}
	}
	$out = [];
	foreach ( array_unique( $nums ) as $n ) {
		$out[] = isset( $songs[ $n - 1 ] ) ? $n . '. ' . $songs[ $n - 1 ] : 'Song ' . $n;
	}
	return $out;
}

/** 1-based song numbers the director starred as the ones to focus on before this rehearsal. */
function tcc_rehearsal_focus( int $reh_id ): array {
	return array_map( 'intval', array_filter( preg_split( '/[,\s]+/', (string) get_post_meta( $reh_id, '_tcc_focus', true ) ) ) );
}

/** A rehearsal's songs as list items, the starred ones marked. */
function tcc_song_items_html( int $reh_id, int $cantata_id ): string {
	$focus = tcc_rehearsal_focus( $reh_id );
	$out   = '';
	foreach ( tcc_rehearsal_song_titles( $reh_id, $cantata_id ) as $s ) {
		$n  = (int) $s;
		$is = $n && in_array( $n, $focus, true );
		$out .= '<li' . ( $is ? ' class="is-focus"' : '' ) . '>' . esc_html( $s ) . ( $is ? ' <span class="tcc-focus" title="Focus on this one">★</span>' : '' ) . '</li>';
	}
	return $out;
}

/* ---------- "Add a season": create a run of weekly drafts ---------- */

function tcc_render_season_page() {
	$done = isset( $_GET['tcc_made'] ) ? absint( $_GET['tcc_made'] ) : 0;
	?>
	<div class="wrap">
		<h1>Add rehearsals in bulk</h1>
		<?php if ( $done ) : ?><div class="notice notice-success"><p><?php echo $done; ?> rehearsals created as drafts. <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . TCC_REH . '&post_status=draft' ) ); ?>">Title and adjust them</a>, then publish.</p></div><?php endif; ?>
		<p>A shortcut for the usual weekly run. Creates one draft rehearsal per week, all attached to the cantata you pick; you then give each a title, tick the songs, tick who is required, and publish. Adding them one at a time under Rehearsals works just the same.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'tcc_season', 'tcc_season_nonce' ); ?>
			<input type="hidden" name="action" value="tcc_season">
			<table class="form-table"><tbody>
				<tr><th><label for="s_cantata">Cantata</label></th><td><select id="s_cantata" name="cantata"><?php foreach ( tcc_get_cantatas() as $c ) printf( '<option value="%d">%s (%s)</option>', $c->ID, esc_html( $c->post_title ), esc_html( tcc_year( $c->ID ) ) ); ?></select></td></tr>
				<tr><th><label for="s_first">First rehearsal</label></th><td><input type="date" id="s_first" name="first" required></td></tr>
				<tr><th><label for="s_count">How many weeks</label></th><td><input type="number" id="s_count" name="count" value="9" min="1" max="30"></td></tr>
				<tr><th><label for="s_start">Start time</label></th><td><input type="time" id="s_start" name="start" value="19:00"></td></tr>
				<tr><th><label for="s_end">End time</label></th><td><input type="time" id="s_end" name="end" value="20:00"></td></tr>
			</tbody></table>
			<?php submit_button( 'Create drafts' ); ?>
		</form>
	</div>
	<?php
}

add_action( 'admin_post_tcc_season', function () {
	if ( ! current_user_can( 'edit_posts' ) || ! wp_verify_nonce( $_POST['tcc_season_nonce'] ?? '', 'tcc_season' ) ) {
		wp_die( 'Not allowed.' );
	}
	$cantata = absint( $_POST['cantata'] ?? 0 );
	$first   = sanitize_text_field( $_POST['first'] ?? '' );
	$count   = min( 30, max( 1, absint( $_POST['count'] ?? 1 ) ) );
	$start   = tcc_sanitize_rehearsal_field( 'start', $_POST['start'] ?? '' );
	$end     = tcc_sanitize_rehearsal_field( 'end', $_POST['end'] ?? '' );
	$made = 0;
	try {
		$d = new DateTimeImmutable( $first, wp_timezone() );
	} catch ( Exception $e ) {
		wp_safe_redirect( admin_url( 'admin.php?page=tcc-season' ) );
		exit;
	}
	for ( $i = 0; $i < $count; $i++ ) {
		$id = wp_insert_post( [
			'post_type' => TCC_REH, 'post_status' => 'draft', 'post_title' => 'Rehearsal ' . ( $i + 1 ),
			'meta_input' => [ '_tcc_date' => $d->format( 'Y-m-d' ), '_tcc_start' => $start, '_tcc_end' => $end, '_tcc_type' => 'rehearsal', '_tcc_cantata' => $cantata ],
		] );
		if ( $id && ! is_wp_error( $id ) ) {
			$made++;
		}
		$d = $d->modify( '+1 week' );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=tcc-season&tcc_made=' . $made ) );
	exit;
} );

/* =====================================================================
 * Rendering: the schedule, and the "next rehearsal" card
 * ===================================================================== */

/** A rehearsal's "afterward" items with anything blank thrown out, whatever an older save left behind. */
function tcc_after_items( int $rehearsal_id ): array {
	$out = [];
	foreach ( (array) get_post_meta( $rehearsal_id, '_tcc_after', true ) as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$item['what'] = trim( (string) ( $item['what'] ?? '' ) );
		if ( $item['what'] === '' ) {
			continue;
		}
		$out[] = $item;
	}
	return $out;
}

function tcc_render_rehearsal( WP_Post $r, int $cantata_id, bool $is_next ): string {
	$dt   = tcc_rehearsal_dt( $r->ID );
	$type = get_post_meta( $r->ID, '_tcc_type', true ) ?: 'rehearsal';
	$now  = new DateTimeImmutable( 'now', wp_timezone() );
	$past = $dt && $dt->setTime( 23, 59 ) < $now && ! $is_next;
	$req  = tcc_required_label( $r->ID );
	$cls  = 'tcc-reh tcc-reh--' . $type . ( $req ? ' tcc-reh--required' : '' ) . ( $past ? ' is-past' : '' ) . ( $is_next ? ' is-next' : '' );
	$out  = '<li class="' . esc_attr( $cls ) . '">';
	$out .= '<div class="tcc-reh__date">';
	if ( $dt ) {
		$out .= '<span class="tcc-reh__dow">' . esc_html( $dt->format( 'D' ) ) . '</span><span class="tcc-reh__day">' . esc_html( $dt->format( 'j' ) ) . '</span>';
	}
	$out .= '</div><div class="tcc-reh__body">';
	$out .= '<div class="tcc-reh__top">';
	if ( $dt ) {
		$out .= '<span class="tcc-reh__when">' . esc_html( $dt->format( 'l, F j' ) ) . '</span>';
	}
	if ( $req ) {
		$out .= '<span class="tcc-reh__flag tcc-reh__flag--required">' . esc_html( $req ) . '</span>';
	}
	if ( $type === 'performance' ) {
		$out .= '<span class="tcc-reh__flag tcc-reh__flag--performance">Performance</span>';
	}
	if ( $is_next ) {
		$out .= '<span class="tcc-reh__flag tcc-reh__flag--next">Next</span>';
	}
	$out .= '</div>';
	$out .= '<h3 class="tcc-reh__title">' . esc_html( get_the_title( $r ) ) . '</h3>';
	// Time and attendees on one line, the same shape as an "afterward" row, so the entry reads top to bottom in time order.
	$range = tcc_rehearsal_time_range( $r->ID );
	$out .= '<div class="tcc-reh__who">'
		. ( $range ? '<span class="tcc-reh__k">When</span><span class="tcc-reh__after-time">' . esc_html( $range ) . '</span><span class="tcc-reh__sep" aria-hidden="true"></span>' : '' )
		. '<span class="tcc-reh__k">Who</span>' . tcc_who_pills( (array) get_post_meta( $r->ID, '_tcc_who', true ) ) . '</div>';
	if ( tcc_rehearsal_is_full( $r->ID ) ) {
		$out .= '<div class="tcc-reh__full">' . tcc_svg( 'note' ) . '<span class="tcc-reh__full-k">' . esc_html( apply_filters( 'tcc_full_label', 'Full run-through' ) ) . '</span><span class="tcc-reh__full-t">' . esc_html( apply_filters( 'tcc_full_line', 'The whole work, start to finish.' ) ) . '</span></div>';
	} else {
		$songs = tcc_rehearsal_song_titles( $r->ID, $cantata_id );
		if ( $songs ) {
			$out .= '<ul class="tcc-reh__songs">' . tcc_song_items_html( $r->ID, $cantata_id ) . '</ul>';
		}
	}
	$after = tcc_after_items( $r->ID );
	if ( $after ) {
		$out .= '<ul class="tcc-reh__after">';
		foreach ( $after as $item ) {
			$t = ! empty( $item['time'] ) ? tcc_fmt_time( $item['time'] ) . ( ! empty( $item['end'] ) ? '–' . tcc_fmt_time( $item['end'] ) : '' ) : 'Afterward';
			$out .= '<li><span class="tcc-reh__after-icon">' . tcc_svg( 'then' ) . '</span>'
				. '<span class="tcc-reh__k">When</span><span class="tcc-reh__after-time">' . esc_html( $t ) . '</span><span class="tcc-reh__sep" aria-hidden="true"></span>'
				. '<span class="tcc-reh__k">Who</span>' . tcc_who_pills( (array) ( $item['who'] ?? [] ) ) . '<span class="tcc-reh__sep" aria-hidden="true"></span>'
				. '<span class="tcc-reh__after-what">' . esc_html( $item['what'] ) . '</span></li>';
		}
		$out .= '</ul>';
	}
	if ( trim( $r->post_content ) ) {
		$out .= '<div class="tcc-reh__notes"><span class="tcc-reh__notes-k">' . esc_html( apply_filters( 'tcc_note_label', 'A note from the director' ) ) . '</span>' . apply_filters( 'the_content', $r->post_content ) . '</div>';
	}
	return $out . '</div></li>';
}

function tcc_render_schedule( int $cantata_id = 0 ): string {
	$cantata_id = $cantata_id ?: tcc_current_id();
	if ( ! $cantata_id ) {
		return '';
	}
	$rehs = tcc_get_rehearsals( $cantata_id );
	if ( ! $rehs ) {
		return current_user_can( 'edit_posts' ) ? '<p class="tcc-empty">No rehearsals yet for ' . esc_html( get_the_title( $cantata_id ) ) . '. Add them under Cantata → Rehearsals.</p>' : '';
	}
	$next = tcc_next_rehearsal( $cantata_id );
	$out  = '<div class="tcc-schedule">';
	$month = '';
	$open  = false;
	foreach ( $rehs as $r ) {
		$dt = tcc_rehearsal_dt( $r->ID );
		$m  = $dt ? $dt->format( 'F' ) : 'Undated';
		if ( $m !== $month ) {
			if ( $open ) {
				$out .= '</ol></section>';
			}
			$out .= '<section class="tcc-schedule__month"><h2 class="tcc-schedule__monthname">' . esc_html( $m ) . '</h2><ol class="tcc-schedule__list">';
			$month = $m;
			$open  = true;
		}
		$out .= tcc_render_rehearsal( $r, $cantata_id, $next && $next->ID === $r->ID );
	}
	if ( $open ) {
		$out .= '</ol></section>';
	}
	return $out . '</div>';
}

function tcc_render_next_rehearsal( int $cantata_id = 0, string $schedule_url = '' ): string {
	$cantata_id = $cantata_id ?: tcc_current_id();
	$next = $cantata_id ? tcc_next_rehearsal( $cantata_id ) : null;
	if ( ! $next ) {
		return '';
	}
	$dt   = tcc_rehearsal_dt( $next->ID );
	$type = get_post_meta( $next->ID, '_tcc_type', true ) ?: 'rehearsal';
	$req  = tcc_required_label( $next->ID );
	$out  = '<div class="tcc-next tcc-next--' . esc_attr( $type ) . ( $req ? ' tcc-next--required' : '' ) . '">';
	$out .= '<div class="tcc-next__date">' . ( $dt ? '<span class="tcc-next__dow">' . esc_html( $dt->format( 'D' ) ) . '</span><span class="tcc-next__day">' . esc_html( $dt->format( 'j' ) ) . '</span><span class="tcc-next__mon">' . esc_html( $dt->format( 'M' ) ) . '</span>' : '' ) . '</div>';
	$out .= '<div class="tcc-next__body">';
	$out .= '<span class="tcc-next__label">' . esc_html( $type === 'performance' ? 'Performance day' : ( $req ? 'Next rehearsal · ' . lcfirst( $req ) : 'Next rehearsal' ) ) . '</span>';
	$out .= '<span class="tcc-next__title">' . esc_html( get_the_title( $next ) ) . '</span>';
	$out .= '<span class="tcc-next__when">' . esc_html( $dt ? $dt->format( 'l, F j' ) : '' ) . ( tcc_rehearsal_time_range( $next->ID ) ? ' · ' . esc_html( tcc_rehearsal_time_range( $next->ID ) ) : '' ) . '</span>';

	$out .= '<dl class="tcc-next__facts">';
	$out .= '<div><dt>Who should attend</dt><dd class="tcc-next__who">' . tcc_who_pills( (array) get_post_meta( $next->ID, '_tcc_who', true ) ) . '</dd></div>';
	$songs = tcc_rehearsal_song_titles( $next->ID, $cantata_id );
	if ( tcc_rehearsal_is_full( $next->ID ) ) {
		$out .= '<div><dt>What we\'re working on</dt><dd class="tcc-next__songs"><span class="tcc-next__full">' . tcc_svg( 'note' ) . esc_html( apply_filters( 'tcc_full_label', 'Full run-through' ) ) . '</span> ' . esc_html( apply_filters( 'tcc_full_line', 'The whole work, start to finish.' ) ) . '</dd></div>';
	} elseif ( $songs ) {
		$all = count( $songs ) === count( tcc_song_titles( $cantata_id ) );
		$out .= '<div><dt>What we\'re working on</dt><dd class="tcc-next__songs">' . ( $all ? 'The whole work' : '<ul>' . tcc_song_items_html( $next->ID, $cantata_id ) . '</ul>' ) . '</dd></div>';
	}
	$after = tcc_after_items( $next->ID );
	if ( $after ) {
		$items = '';
		foreach ( $after as $item ) {
			$t = ! empty( $item['time'] ) ? tcc_fmt_time( $item['time'] ) . ( ! empty( $item['end'] ) ? '–' . tcc_fmt_time( $item['end'] ) : '' ) : '';
			$items .= '<li>' . ( $t ? '<span class="tcc-next__after-time">' . esc_html( $t ) . '</span> ' : '' ) . esc_html( $item['what'] ) . ' ' . tcc_who_pills( (array) ( $item['who'] ?? [] ) ) . '</li>';
		}
		if ( $items ) {
			$out .= '<div><dt>Afterward</dt><dd class="tcc-next__after"><ul>' . $items . '</ul></dd></div>';
		}
	}
	$out .= '</dl>';
	if ( trim( $next->post_content ) ) {
		$out .= '<div class="tcc-next__notes">' . apply_filters( 'the_content', $next->post_content ) . '</div>';
	}
	if ( $schedule_url ) {
		$out .= '<a class="tcc-btn tcc-btn--light tcc-next__btn" href="' . esc_url( $schedule_url ) . '">Full schedule</a>';
	}
	return $out . '</div></div>';
}

/* ---------- blocks ---------- */

add_action( 'init', function () {
	register_block_type( 'tilden/rehearsal-schedule', [
		'api_version' => 3, 'title' => 'Rehearsal schedule', 'description' => 'The season\'s rehearsals for a cantata, as a timeline.',
		'category' => 'widgets', 'icon' => 'calendar-alt',
		'supports' => [ 'html' => false, 'multiple' => false, 'align' => [ 'wide', 'full' ] ],
		'attributes' => [ 'cantata' => [ 'type' => 'integer', 'default' => 0 ] ],
		'render_callback' => function ( array $attributes ) {
			tcc_enqueue_front_assets();
			return '<div ' . get_block_wrapper_attributes() . '>' . tcc_render_schedule( (int) ( $attributes['cantata'] ?? 0 ) ) . '</div>';
		},
	] );
	register_block_type( 'tilden/next-rehearsal', [
		'api_version' => 3, 'title' => 'Next rehearsal', 'description' => 'A card for the next rehearsal, with a link to the full schedule.',
		'category' => 'widgets', 'icon' => 'clock',
		'supports' => [ 'html' => false, 'multiple' => false ],
		'attributes' => [ 'cantata' => [ 'type' => 'integer', 'default' => 0 ], 'scheduleUrl' => [ 'type' => 'string', 'default' => '' ] ],
		'render_callback' => function ( array $attributes ) {
			tcc_enqueue_front_assets();
			return '<div ' . get_block_wrapper_attributes() . '>' . tcc_render_next_rehearsal( (int) ( $attributes['cantata'] ?? 0 ), (string) ( $attributes['scheduleUrl'] ?? '' ) ) . '</div>';
		},
	] );
} );
