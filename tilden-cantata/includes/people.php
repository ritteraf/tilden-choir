<?php
defined( 'ABSPATH' ) || exit;

/* =====================================================================
 * People: the choir roster. Admin-only; never rendered on the front end.
 * ===================================================================== */

define( 'TCC_PERSON', 'tcc_person' );

function tcc_voice_parts(): array {
	return [
		'soprano'      => 'Soprano',
		'soprano-alto' => 'Mezzo (Soprano or Alto)',
		'alto'         => 'Alto',
		'tenor'        => 'Tenor',
		'tenor-bass'   => 'Baritone (Tenor or Bass)',
		'bass'         => 'Bass',
	];
}

/** The default volunteer roles, one per line, "Name — what it involves". */
function tcc_default_roles(): string {
	return "Marketing — social media posts, the paper, posters and signs around town\nWelcoming team — hospitality and catering for the social after the performance\nStaging — brainstorm, design and build the stage elements of the work\nCostuming — brainstorm, design and make costumes for the characters";
}

/** Volunteer roles with their descriptions: [ slug => [ 'label', 'desc' ] ]. Editable under Cantata → Settings. */
function tcc_volunteer_role_info(): array {
	$raw = tcc_option( 'roles', tcc_default_roles() );
	$out = [];
	foreach ( array_filter( array_map( 'trim', explode( "\n", $raw ) ) ) as $line ) {
		$bits  = preg_split( '/\s+(?:—|–|-)\s+|:\s+/u', $line, 2 );
		$label = trim( $bits[0] );
		if ( $label !== '' ) {
			$out[ sanitize_title( $label ) ] = [ 'label' => $label, 'desc' => trim( $bits[1] ?? '' ) ];
		}
	}
	return $out;
}

/** Volunteer roles as [ slug => label ]. */
function tcc_volunteer_roles(): array {
	return array_map( fn( $r ) => $r['label'], tcc_volunteer_role_info() );
}

function tcc_person_fields(): array {
	return [
		'photo'   => [ 'Photo', 'image', 'Shown, program-style, when they have a solo, duet or ensemble on a cantata. A portrait (taller than wide) works best; it is cropped to 3:4.' ],
		'bio'     => [ 'About', 'textarea', 'Public. A sentence or two printed beside their photo when they have a featured part, like a program note: how long they have sung with the choir, what they do, a favourite carol. Leave blank to show nothing.' ],
		'types'   => [ 'Type', 'types', 'Tick every one that applies.' ],
		'part'    => [ 'Voice part', 'part', 'Singers only.' ],
		'solos'   => [ 'Interested in solos', 'check', 'Singers only.' ],
		'phone'   => [ 'Phone', 'tel', '' ],
		'email'   => [ 'Email', 'email', '' ],
		'role'    => [ 'Volunteer role', 'role', 'Their secondary job for the cantata. Edit the list under Cantata → Settings.' ],
		'seasons' => [ 'Seasons', 'seasons', 'Which cantatas they took part in.' ],
		'hidden'  => [ 'Keep off the site', 'check', 'Tick if this person would rather not be listed publicly. They still appear in the roster here.' ],
		'notes'   => [ 'Notes', 'textarea', 'Private. Never shown on the site.' ],
	];
}

add_action( 'init', function () {
	register_post_type( TCC_PERSON, [
		'labels' => [
			'name' => 'People', 'singular_name' => 'Person', 'add_new' => 'Add person', 'add_new_item' => 'Add person',
			'edit_item' => 'Edit person', 'all_items' => 'People', 'search_items' => 'Search people', 'not_found' => 'Nobody yet.', 'menu_name' => 'People',
		],
		'public'              => false,
		'exclude_from_search' => true,
		'publicly_queryable'  => false,
		'show_ui'             => true,
		'show_in_menu'        => 'tcc-menu',
		'show_in_rest'        => false,
		'supports'            => [ 'title', 'thumbnail' ],
		'capability_type'     => 'post',
		'map_meta_cap'        => true,
	] );
} );

add_action( 'after_setup_theme', function () {
	$types = get_theme_support( 'post-thumbnails' );
	if ( is_array( $types ) && isset( $types[0] ) && is_array( $types[0] ) ) {
		add_theme_support( 'post-thumbnails', array_unique( array_merge( $types[0], [ TCC_PERSON ] ) ) );
	} elseif ( ! $types ) {
		add_theme_support( 'post-thumbnails', [ TCC_PERSON ] );
	}
}, 11 );
add_action( 'add_meta_boxes_' . TCC_PERSON, function () {
	remove_meta_box( 'postimagediv', TCC_PERSON, 'side' ); // the Photo field in Details replaces it
}, 20 );

/** Only editors and up see the roster, even though it is a post type. */
add_filter( 'user_has_cap', function ( array $caps, array $cap, array $args ) {
	if ( isset( $args[0] ) && str_starts_with( (string) $args[0], 'edit_post' ) && isset( $args[2] ) && get_post_type( (int) $args[2] ) === TCC_PERSON && empty( $caps['edit_others_posts'] ) ) {
		$caps['edit_post'] = false;
	}
	return $caps;
}, 10, 3 );
add_action( 'admin_menu', function () {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		remove_submenu_page( 'tcc-menu', 'edit.php?post_type=' . TCC_PERSON );
	}
}, 99 );

function tcc_sanitize_person_field( string $key, $v ) {
	$type = tcc_person_fields()[ $key ][1] ?? 'text';
	$v = is_string( $v ) ? trim( $v ) : $v;
	return match ( $type ) {
		'image'    => absint( $v ),
		'types'    => array_values( array_intersect( array_map( 'sanitize_key', (array) $v ), array_keys( tcc_attendee_types() ) ) ),
		'part'     => array_key_exists( (string) $v, tcc_voice_parts() ) ? $v : '',
		'check'    => $v ? '1' : '',
		'tel'      => preg_replace( '/[^0-9+().\- ]/', '', (string) $v ),
		'email'    => sanitize_email( (string) $v ),
		'role'     => array_key_exists( (string) $v, tcc_volunteer_roles() ) ? $v : '',
		'seasons'  => array_values( array_filter( array_map( 'absint', (array) $v ) ) ),
		'textarea' => sanitize_textarea_field( (string) $v ),
		default    => sanitize_text_field( (string) $v ),
	};
}

/* ---------- meta box ---------- */

add_action( 'add_meta_boxes_' . TCC_PERSON, function () {
	add_meta_box( 'tcc_person', 'Details', 'tcc_render_person_box', TCC_PERSON, 'normal', 'high' );
} );

function tcc_render_person_box( WP_Post $post ) {
	wp_nonce_field( 'tcc_person_save', 'tcc_person_nonce' );
	echo '<p class="description">The title above is the person\'s name. This roster is private. On the site, a cantata\'s entry lists the names of everyone in that season\'s cast and crew; someone with a solo, duet or ensemble also gets their photo and the "About" line, program-style. Phone, email, role and notes never leave this screen.</p>';
	echo '<table class="form-table"><tbody>';
	foreach ( tcc_person_fields() as $key => [ $label, $type, $desc ] ) {
		$name = $key === 'photo' ? '_thumbnail_id' : '_tcc_' . $key;
		$val  = get_post_meta( $post->ID, $name, true );
		$row_attr = in_array( $key, [ 'part', 'solos' ], true ) ? ' class="tcc-singer-only"' : '';
		if ( $key === 'hidden' ) {
			$val = $val ? '1' : '';
		}
		echo '<tr' . $row_attr . '><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td>';
		switch ( $type ) {
			case 'image':
				$id  = absint( $val );
				$src = $id ? wp_get_attachment_image_url( $id, 'thumbnail' ) : '';
				echo '<div class="tcc-media" data-type="image">';
				printf( '<input type="hidden" id="%1$s" name="%1$s" value="%2$d">', esc_attr( $name ), $id );
				echo '<img class="tcc-media__preview" src="' . esc_url( $src ) . '" style="width:96px;height:96px;object-fit:cover;border-radius:50%;display:' . ( $src ? 'block' : 'none' ) . ';margin-bottom:6px;">';
				echo '<button type="button" class="button tcc-media__pick">Choose photo</button> <button type="button" class="button-link tcc-media__clear" style="' . ( $id ? '' : 'display:none' ) . '">Remove</button></div>';
				break;
			case 'types':
				foreach ( tcc_attendee_types() as $k => $l ) {
					printf( '<label style="margin-right:16px"><input type="checkbox" name="%s[]" value="%s" data-tcc-type="%s"%s> %s</label>', esc_attr( $name ), esc_attr( $k ), esc_attr( $k ), checked( in_array( $k, (array) $val, true ), true, false ), esc_html( $l ) );
				}
				break;
			case 'part':
				echo '<select id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '"><option value="">—</option>';
				foreach ( tcc_voice_parts() as $k => $l ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $val, $k, false ), esc_html( $l ) );
				}
				echo '</select>';
				break;
			case 'check':
				printf( '<label><input type="checkbox" id="%1$s" name="%1$s" value="1"%2$s> Yes</label>', esc_attr( $name ), checked( (string) $val, '1', false ) );
				break;
			case 'role':
				echo '<select id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '"><option value="">—</option>';
				foreach ( tcc_volunteer_roles() as $k => $l ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $val, $k, false ), esc_html( $l ) );
				}
				echo '</select>';
				break;
			case 'seasons':
				$val = (array) $val;
				$cur = tcc_current_id();
				$is_new = $post->post_status === 'auto-draft';
				foreach ( tcc_get_cantatas() as $c ) {
					$on = in_array( $c->ID, $val, true ) || ( $is_new && $c->ID === $cur );
					printf( '<label style="margin-right:16px;white-space:nowrap"><input type="checkbox" name="%s[]" value="%d"%s> %s (%s)</label>', esc_attr( $name ), $c->ID, checked( $on, true, false ), esc_html( $c->post_title ), esc_html( tcc_year( $c->ID ) ) );
				}
				break;
			case 'textarea':
				printf( '<textarea id="%1$s" name="%1$s" rows="3" class="large-text">%2$s</textarea>', esc_attr( $name ), esc_textarea( $val ) );
				break;
			case 'tel':
			case 'email':
				printf( '<input type="%3$s" id="%1$s" name="%1$s" value="%2$s" class="regular-text">', esc_attr( $name ), esc_attr( $val ), $type );
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
	echo '<script>(function(){function sync(){var s=document.querySelector("[data-tcc-type=singers]");document.querySelectorAll(".tcc-singer-only").forEach(function(r){r.style.display=(s&&s.checked)?"":"none";});}document.addEventListener("change",function(e){if(e.target.matches("[data-tcc-type]"))sync();});sync();})();</script>';
}

add_action( 'save_post_' . TCC_PERSON, function ( int $post_id, WP_Post $post ) {
	if ( ! isset( $_POST['tcc_person_nonce'] ) || ! wp_verify_nonce( $_POST['tcc_person_nonce'], 'tcc_person_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}
	foreach ( tcc_person_fields() as $key => $f ) {
		$name = $key === 'photo' ? '_thumbnail_id' : '_tcc_' . $key;
		if ( ! array_key_exists( $name, $_POST ) ) {
			if ( in_array( $f[1], [ 'types', 'check', 'seasons' ], true ) ) {
				delete_post_meta( $post_id, $name );
			}
			continue;
		}
		$clean = tcc_sanitize_person_field( $key, wp_unslash( $_POST[ $name ] ) );
		if ( $clean === '' || $clean === [] || $clean === 0 ) {
			delete_post_meta( $post_id, $name );
		} else {
			update_post_meta( $post_id, $name, $clean );
		}
	}
	if ( function_exists( 'tcc_purge_home' ) ) {
		tcc_purge_home(); // a name or photo may be on the home page
	}
}, 10, 2 );

/** A person as shown on the site: round photo (or initials) and name. */
function tcc_person_chip( int $id ): string {
	$post = get_post( $id );
	if ( ! $post || $post->post_type !== TCC_PERSON || $post->post_status !== 'publish' ) {
		return '';
	}
	$name = get_the_title( $post );
	$img  = get_the_post_thumbnail( $post, 'thumbnail', [ 'class' => 'tcc-chip__img', 'alt' => '', 'loading' => 'lazy' ] );
	if ( ! $img ) {
		$initials = '';
		foreach ( preg_split( '/\s+/', trim( $name ) ) as $w ) {
			$initials .= mb_strtoupper( mb_substr( $w, 0, 1 ) );
		}
		$img = '<span class="tcc-chip__img tcc-chip__img--initials" aria-hidden="true">' . esc_html( mb_substr( $initials, 0, 2 ) ) . '</span>';
	}
	return '<span class="tcc-chip">' . $img . '<span class="tcc-chip__name">' . esc_html( $name ) . '</span></span>';
}

/* ---------- list table ---------- */

add_filter( 'manage_' . TCC_PERSON . '_posts_columns', function ( array $cols ) {
	return [ 'cb' => $cols['cb'], 'title' => 'Name', 'tcc_types' => 'Type', 'tcc_part' => 'Part', 'tcc_solos' => 'Solos', 'tcc_contact' => 'Contact', 'tcc_role' => 'Volunteer role', 'tcc_seasons' => 'Seasons' ];
} );
add_action( 'manage_' . TCC_PERSON . '_posts_custom_column', function ( string $col, int $id ) {
	$types = tcc_attendee_types();
	switch ( $col ) {
		case 'tcc_types':
			echo esc_html( implode( ', ', array_map( fn( $k ) => $types[ $k ] ?? $k, (array) get_post_meta( $id, '_tcc_types', true ) ) ) );
			break;
		case 'tcc_part':
			echo esc_html( tcc_voice_parts()[ get_post_meta( $id, '_tcc_part', true ) ] ?? '' );
			break;
		case 'tcc_solos':
			echo get_post_meta( $id, '_tcc_solos', true ) ? '✓' : '';
			break;
		case 'tcc_contact':
			$p = get_post_meta( $id, '_tcc_phone', true );
			$e = get_post_meta( $id, '_tcc_email', true );
			echo $p ? '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $p ) ) . '">' . esc_html( $p ) . '</a>' : '';
			echo $p && $e ? '<br>' : '';
			echo $e ? '<a href="mailto:' . esc_attr( $e ) . '">' . esc_html( $e ) . '</a>' : '';
			break;
		case 'tcc_role':
			echo esc_html( tcc_volunteer_roles()[ get_post_meta( $id, '_tcc_role', true ) ] ?? '' );
			break;
		case 'tcc_seasons':
			echo esc_html( implode( ', ', array_map( fn( $c ) => tcc_year( (int) $c ), (array) get_post_meta( $id, '_tcc_seasons', true ) ) ) );
			break;
	}
}, 10, 2 );

/** Filters above the list: season, type, part, role. */
add_action( 'restrict_manage_posts', function ( string $post_type ) {
	if ( $post_type !== TCC_PERSON ) {
		return;
	}
	$sel = fn( $name, $opts, $blank ) => tcc_filter_select( $name, $opts, $blank );
	$seasons = [];
	foreach ( tcc_get_cantatas() as $c ) {
		$seasons[ $c->ID ] = tcc_year( $c->ID ) . ' · ' . $c->post_title;
	}
	echo $sel( 'tcc_f_season', $seasons, 'All seasons' );
	echo $sel( 'tcc_f_type', tcc_attendee_types(), 'All types' );
	echo $sel( 'tcc_f_part', tcc_voice_parts(), 'All parts' );
	echo $sel( 'tcc_f_role', tcc_volunteer_roles(), 'All roles' );
	echo '<a class="button" style="margin-left:6px" href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tcc_people_csv&' . http_build_query( array_intersect_key( $_GET, array_flip( [ 'tcc_f_season', 'tcc_f_type', 'tcc_f_part', 'tcc_f_role' ] ) ) ) ), 'tcc_people_csv' ) ) . '">Download CSV</a>';
} );
function tcc_filter_select( string $name, array $opts, string $blank ): string {
	$cur = sanitize_text_field( $_GET[ $name ] ?? '' );
	$out = '<select name="' . esc_attr( $name ) . '"><option value="">' . esc_html( $blank ) . '</option>';
	foreach ( $opts as $k => $l ) {
		$out .= '<option value="' . esc_attr( $k ) . '"' . selected( (string) $cur, (string) $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	return $out . '</select> ';
}
add_action( 'pre_get_posts', function ( WP_Query $q ) {
	if ( ! is_admin() || ! $q->is_main_query() || $q->get( 'post_type' ) !== TCC_PERSON ) {
		return;
	}
	$meta = [];
	if ( ! empty( $_GET['tcc_f_season'] ) ) {
		$meta[] = [ 'key' => '_tcc_seasons', 'value' => sprintf( 'i:%d;', absint( $_GET['tcc_f_season'] ) ), 'compare' => 'LIKE' ];
	}
	if ( ! empty( $_GET['tcc_f_type'] ) ) {
		$meta[] = [ 'key' => '_tcc_types', 'value' => '"' . sanitize_key( $_GET['tcc_f_type'] ) . '"', 'compare' => 'LIKE' ];
	}
	if ( ! empty( $_GET['tcc_f_part'] ) ) {
		$meta[] = [ 'key' => '_tcc_part', 'value' => sanitize_key( $_GET['tcc_f_part'] ) ];
	}
	if ( ! empty( $_GET['tcc_f_role'] ) ) {
		$meta[] = [ 'key' => '_tcc_role', 'value' => sanitize_key( $_GET['tcc_f_role'] ) ];
	}
	if ( $meta ) {
		$q->set( 'meta_query', $meta );
	}
	if ( ! $q->get( 'orderby' ) ) {
		$q->set( 'orderby', 'title' );
		$q->set( 'order', 'ASC' );
	}
} );

/** Counts by voice part for this season, above the list. */
add_action( 'admin_notices', function () {
	$s = get_current_screen();
	if ( ! $s || $s->id !== 'edit-' . TCC_PERSON ) {
		return;
	}
	$cur = tcc_current_id();
	if ( ! $cur ) {
		return;
	}
	$ids = get_posts( [ 'post_type' => TCC_PERSON, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_query' => [ [ 'key' => '_tcc_seasons', 'value' => sprintf( 'i:%d;', $cur ), 'compare' => 'LIKE' ] ] ] );
	$counts = array_fill_keys( array_keys( tcc_voice_parts() ), 0 );
	$other  = 0;
	foreach ( $ids as $id ) {
		$p = get_post_meta( $id, '_tcc_part', true );
		if ( isset( $counts[ $p ] ) ) {
			$counts[ $p ]++;
		} else {
			$other++;
		}
	}
	$bits = [];
	foreach ( tcc_voice_parts() as $k => $l ) {
		$bits[] = '<strong>' . $counts[ $k ] . '</strong> ' . $l;
	}
	echo '<div class="notice notice-info" style="padding:10px 14px"><strong>' . esc_html( get_the_title( $cur ) ) . ':</strong> ' . count( $ids ) . ' people · ' . implode( ' · ', $bits ) . ( $other ? ' · <strong>' . $other . '</strong> non-singing' : '' ) . '</div>';
} );

/* ---------- CSV export ---------- */

add_action( 'admin_post_tcc_people_csv', function () {
	if ( ! current_user_can( 'edit_others_posts' ) || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'tcc_people_csv' ) ) {
		wp_die( 'Not allowed.' );
	}
	$q = new WP_Query( [ 'post_type' => TCC_PERSON, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );
	// re-use the list filters
	$_GET['post_type'] = TCC_PERSON;
	$types = tcc_attendee_types();
	$roles = tcc_volunteer_roles();
	$parts = tcc_voice_parts();
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="choir-roster-' . wp_date( 'Y-m-d' ) . '.csv"' );
	$out = fopen( 'php://output', 'w' );
	fputs( $out, "\xEF\xBB\xBF" );
	fputcsv( $out, [ 'Name', 'Type', 'Voice part', 'Solos', 'Phone', 'Email', 'Volunteer role', 'Seasons', 'Notes' ] );
	$want_season = absint( $_GET['tcc_f_season'] ?? 0 );
	$want_type   = sanitize_key( $_GET['tcc_f_type'] ?? '' );
	$want_part   = sanitize_key( $_GET['tcc_f_part'] ?? '' );
	$want_role   = sanitize_key( $_GET['tcc_f_role'] ?? '' );
	foreach ( $q->posts as $p ) {
		$t = (array) get_post_meta( $p->ID, '_tcc_types', true );
		$s = array_map( 'intval', (array) get_post_meta( $p->ID, '_tcc_seasons', true ) );
		$part = get_post_meta( $p->ID, '_tcc_part', true );
		$role = get_post_meta( $p->ID, '_tcc_role', true );
		if ( ( $want_season && ! in_array( $want_season, $s, true ) ) || ( $want_type && ! in_array( $want_type, $t, true ) ) || ( $want_part && $part !== $want_part ) || ( $want_role && $role !== $want_role ) ) {
			continue;
		}
		fputcsv( $out, [
			$p->post_title,
			implode( ', ', array_map( fn( $k ) => $types[ $k ] ?? $k, $t ) ),
			$parts[ $part ] ?? '',
			get_post_meta( $p->ID, '_tcc_solos', true ) ? 'Yes' : '',
			get_post_meta( $p->ID, '_tcc_phone', true ),
			get_post_meta( $p->ID, '_tcc_email', true ),
			$roles[ $role ] ?? '',
			implode( ', ', array_map( fn( $c ) => tcc_year( $c ), $s ) ),
			get_post_meta( $p->ID, '_tcc_notes', true ),
		] );
	}
	fclose( $out );
	exit;
} );

/** Published people who took part in a cantata and haven't asked to stay off the site, grouped by type. */
function tcc_people_for_cantata( int $cantata_id ): array {
	$ids = get_posts( [
		'post_type' => TCC_PERSON, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'title', 'order' => 'ASC',
		'meta_query' => [ [ 'key' => '_tcc_seasons', 'value' => sprintf( 'i:%d;', $cantata_id ), 'compare' => 'LIKE' ] ],
	] );
	$groups = array_fill_keys( array_keys( tcc_attendee_types() ), [] );
	foreach ( $ids as $id ) {
		if ( get_post_meta( $id, '_tcc_hidden', true ) ) {
			continue;
		}
		foreach ( (array) get_post_meta( $id, '_tcc_types', true ) as $t ) {
			if ( isset( $groups[ $t ] ) ) {
				$groups[ $t ][] = $id;
			}
		}
	}
	return array_filter( $groups );
}

/** Sort people ids the way a printed program does: by surname, then by the whole name. */
function tcc_sort_by_surname( array $ids ): array {
	$key = [];
	foreach ( $ids as $id ) {
		$name  = trim( get_the_title( $id ) );
		$words = preg_split( '/\s+/', $name );
		$key[ $id ] = mb_strtolower( end( $words ) . ' ' . $name );
	}
	usort( $ids, fn( $a, $b ) => strcmp( $key[ $a ], $key[ $b ] ) );
	return $ids;
}

/** One row of the cast list: a label on the left, names lined up in columns on the right. */
function tcc_roster_row( string $label, array $ids ): string {
	$names = '';
	foreach ( tcc_sort_by_surname( $ids ) as $id ) {
		$names .= '<span class="tcc-roster__name">' . esc_html( get_the_title( $id ) ) . '</span>';
	}
	return '<div class="tcc-roster__row"><span class="tcc-roster__label">' . esc_html( $label ) . '</span><div class="tcc-roster__names">' . $names . '</div></div>';
}

/** The collapsible "everyone who took part" section for a cantata entry: a table of rows, names only. */
function tcc_roster_section( int $cantata_id, string $state = 'past' ): string {
	if ( $state === 'recruiting' ) {
		return ''; // the cast is still forming; the entry is asking people to join it
	}
	$groups = tcc_people_for_cantata( $cantata_id );
	if ( ! $groups ) {
		return '';
	}
	$label = in_array( $state, [ 'upcoming', 'live' ], true ) ? tcc_text( 'roster_label_current' ) : tcc_text( 'roster_label' );
	$types = tcc_attendee_types();
	$parts = tcc_voice_parts();
	$rows  = '';
	if ( ! empty( $groups['singers'] ) ) {
		$by = array_fill_keys( array_keys( $parts ), [] );
		$by[''] = [];
		foreach ( $groups['singers'] as $id ) {
			$by[ get_post_meta( $id, '_tcc_part', true ) ?: '' ][] = $id;
		}
		foreach ( $by as $part => $pids ) {
			if ( $pids ) {
				$rows .= tcc_roster_row( trim( preg_replace( '/\s*\(.*$/', '', $parts[ $part ] ?? 'Singers' ) ), $pids ); // "Mezzo", not "Mezzo (Soprano or Alto)"
			}
		}
		unset( $groups['singers'] );
	}
	foreach ( $groups as $type => $ids ) {
		$rows .= tcc_roster_row( $types[ $type ] ?? ucfirst( $type ), $ids );
	}
	return '<details class="tcc-roster"><summary class="tcc-roster__summary">' . esc_html( $label ) . '</summary><div class="tcc-roster__table">' . $rows . '</div></details>';
}

/**
 * Everyone with a featured part on a cantata, in program order (first song they appear in).
 * Returns [ person_id => [ 'name', 'parts' => [ [ 'kind', 'song', 'note' ], … ] ] ].
 */
function tcc_featured_people( array $songlist ): array {
	$people = [];
	foreach ( $songlist as $song ) {
		foreach ( (array) ( $song['parts'] ?? [] ) as $part ) {
			foreach ( (array) ( $part['people'] ?? [] ) as $id ) {
				$id   = (int) $id;
				$post = get_post( $id );
				if ( ! $post || $post->post_type !== TCC_PERSON || $post->post_status !== 'publish' ) {
					continue;
				}
				$people[ $id ] ??= [ 'name' => get_the_title( $post ), 'parts' => [] ];
				$people[ $id ]['parts'][] = [ 'kind' => $part['kind'] ?? 'solo', 'song' => $song['title'] ?? '', 'note' => $part['note'] ?? '' ];
			}
		}
	}
	return $people;
}

/**
 * The "Featuring" lines for the meta block, built from the song assignments:
 * one line per kind of part ("Solos · Jane Smith, John Doe"), people listed once each.
 */
function tcc_featured_lines( array $songlist, int $cantata_id = 0 ): array {
	$kinds = tcc_part_kinds();
	$by    = [];
	foreach ( tcc_featured_people( $songlist ) as $id => $p ) {
		$seen = [];
		foreach ( $p['parts'] as $part ) {
			if ( isset( $seen[ $part['kind'] ] ) ) {
				continue;
			}
			$seen[ $part['kind'] ] = true;
			$by[ $part['kind'] ][] = $p['name'];
		}
	}
	$lines = [];
	foreach ( $kinds as $kind => $label ) {
		if ( ! empty( $by[ $kind ] ) ) {
			$plural = count( $by[ $kind ] ) > 1 && $kind !== 'ensemble' ? $label . 's' : $label;
			$lines[] = [ $plural, $by[ $kind ] ];
		}
	}
	// Narrators and actors come from the season's roster rather than the songs.
	if ( $cantata_id ) {
		$groups = tcc_people_for_cantata( $cantata_id );
		$types  = tcc_attendee_types();
		foreach ( [ 'narrators', 'actors' ] as $type ) {
			if ( empty( $groups[ $type ] ) ) {
				continue;
			}
			$names = array_map( 'get_the_title', tcc_sort_by_surname( $groups[ $type ] ) );
			$label = $types[ $type ] ?? ucfirst( $type );
			if ( count( $names ) === 1 ) {
				$label = rtrim( $label, 's' );
			}
			$lines[] = [ $label, $names ];
		}
	}
	return $lines;
}

/** A program entry: portrait photo, name, what they sing, and their "About" line. */
function tcc_program_entry( int $id, array $p ): string {
	$kinds = tcc_part_kinds();
	$img   = get_the_post_thumbnail( $id, 'medium', [ 'class' => 'tcc-solo__img', 'alt' => '', 'loading' => 'lazy' ] );
	if ( ! $img ) {
		$initials = '';
		foreach ( preg_split( '/\s+/', trim( $p['name'] ) ) as $w ) {
			$initials .= mb_strtoupper( mb_substr( $w, 0, 1 ) );
		}
		$img = '<span class="tcc-solo__img tcc-solo__img--initials" aria-hidden="true">' . esc_html( mb_substr( $initials, 0, 2 ) ) . '</span>';
	}
	$lines = '';
	foreach ( $p['parts'] as $part ) {
		$lines .= '<li><span class="tcc-solo__kind">' . esc_html( $kinds[ $part['kind'] ] ?? $part['kind'] ) . '</span> <span class="tcc-solo__song">' . esc_html( $part['song'] ) . '</span>' . ( $part['note'] ? ' <em>' . esc_html( $part['note'] ) . '</em>' : '' ) . '</li>';
	}
	$bio = trim( (string) get_post_meta( $id, '_tcc_bio', true ) );
	return '<article class="tcc-solo"><span class="tcc-solo__photo">' . $img . '</span><div class="tcc-solo__text">'
		. '<h4 class="tcc-solo__name">' . esc_html( $p['name'] ) . '</h4>'
		. '<ul class="tcc-solo__parts">' . $lines . '</ul>'
		. ( $bio ? '<p class="tcc-solo__bio">' . esc_html( $bio ) . '</p>' : '' )
		. '</div></article>';
}
