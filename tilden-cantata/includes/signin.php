<?php
defined( 'ABSPATH' ) || exit;

/* =====================================================================
 * Backstage sign-in: the paper sign-up sheet, done once on the site.
 * A device that reaches Backstage is asked who it belongs to. Returning
 * members pick their name and confirm their details (contact details are
 * never shown back, only hinted); new people fill in the same short form.
 * Either way they land on this season's roster and the device is remembered.
 * ===================================================================== */

/** Everything the sign-in panel needs, for the current cantata. */
function tcc_signin_config( int $cantata_id ): array {
	$who = tcc_who();
	$on  = $who && in_array( $cantata_id, array_map( 'intval', (array) get_post_meta( $who, '_tcc_seasons', true ) ), true );
	$parts = [];
	if ( function_exists( 'tcc_voice_parts' ) ) {
		foreach ( tcc_voice_parts() as $k => $l ) {
			$parts[] = [ 'id' => $k, 'label' => $l ];
		}
	}
	$roles = [];
	if ( function_exists( 'tcc_volunteer_role_info' ) ) {
		foreach ( tcc_volunteer_role_info() as $k => $r ) {
			$roles[] = [ 'id' => $k, 'label' => $r['label'], 'desc' => $r['desc'] ];
		}
	}
	return [
		'rest'    => esc_url_raw( rest_url( 'tcc/v1/' ) ),
		'token'   => tcc_practice_token( $cantata_id ),
		'cantata' => [ 'id' => $cantata_id, 'year' => tcc_year( $cantata_id ), 'title' => get_the_title( $cantata_id ) ],
		'who'     => $who ? [ 'id' => $who, 'name' => get_the_title( $who ), 'onSeason' => $on ] : null,
		'parts'   => $parts,
		'roles'   => $roles,
		'types'   => [ [ 'id' => 'singers', 'label' => 'Singing' ], [ 'id' => 'narrators', 'label' => 'Narrating' ], [ 'id' => 'actors', 'label' => 'Acting' ], [ 'id' => 'tech', 'label' => 'Tech crew — sound, lights, livestream' ] ],
	];
}

function tcc_enqueue_signin_assets(): void {
	wp_enqueue_style( 'tcc-signin', TCC_URL . 'assets/signin.css', [], TCC_VERSION );
	wp_enqueue_script( 'tcc-signin', TCC_URL . 'assets/signin.js', [], TCC_VERSION, [ 'strategy' => 'defer' ] );
}

/* ---------- block: the welcome card on the Backstage hub ---------- */

add_action( 'init', function () {
	register_block_type( 'tilden/backstage-signin', [
		'api_version' => 3, 'title' => 'Backstage sign-in', 'description' => 'Asks each new device who it belongs to: returning members confirm their details, new people fill in the sign-up form. Replaces the paper sheet and keeps this season\'s roster current.',
		'category' => 'widgets', 'icon' => 'id-alt',
		'supports' => [ 'html' => false, 'multiple' => false ],
		'attributes' => [],
		'render_callback' => function () {
			$current = tcc_current_id();
			if ( ! $current ) {
				return '';
			}
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				return '<div ' . get_block_wrapper_attributes() . '><div style="padding:22px 24px;border:1.5px dashed #c8c8c4;border-radius:8px;font-family:sans-serif"><strong style="font-size:16px">Backstage sign-in</strong><br><span style="color:#6b6b6b">“Have you sung with us before?” — returning members confirm their details, new people sign up. Shown until the device has signed in for this season.</span></div></div>';
			}
			tcc_enqueue_front_assets();
			tcc_enqueue_signin_assets();
			return '<div ' . get_block_wrapper_attributes() . '><div class="tcc-signin" data-tcc-signin="' . esc_attr( wp_json_encode( tcc_signin_config( $current ) ) ) . '"></div></div>';
		},
	] );
} );

/* ---------- helpers ---------- */

function tcc_mask_phone( string $p ): string {
	$d = preg_replace( '/\D/', '', $p );
	return strlen( $d ) >= 4 ? 'ends in ' . substr( $d, -4 ) : '';
}
function tcc_mask_email( string $e ): string {
	if ( ! str_contains( $e, '@' ) ) {
		return '';
	}
	[ $u, $d ] = explode( '@', $e, 2 );
	return mb_substr( $u, 0, 1 ) . '•••@' . $d;
}

/** Names close enough to be the same person. */
function tcc_similar_people( string $name ): array {
	$name = strtolower( trim( $name ) );
	$out  = [];
	foreach ( get_posts( [ 'post_type' => 'tcc_person', 'post_status' => 'publish', 'posts_per_page' => -1 ] ) as $p ) {
		$n = strtolower( trim( $p->post_title ) );
		similar_text( $name, $n, $pct );
		$last_a = substr( strrchr( ' ' . $name, ' ' ), 1 ); $last_b = substr( strrchr( ' ' . $n, ' ' ), 1 );
		if ( $pct >= 80 || ( $last_a && $last_a === $last_b && $pct >= 55 ) ) {
			$out[] = [ 'id' => $p->ID, 'name' => $p->post_title ];
		}
	}
	return $out;
}

/* ---------- REST ---------- */

add_action( 'rest_api_init', function () {
	$ns   = 'tcc/v1';
	$open = fn( WP_REST_Request $r ) => (bool) tcc_rest_cantata( $r );

	// Everyone who has ever been on a roster, for "find my name".
	register_rest_route( $ns, '/people', [ 'methods' => 'GET', 'permission_callback' => $open, 'callback' => function ( WP_REST_Request $r ) {
		$cantata = tcc_rest_cantata( $r );
		$parts   = function_exists( 'tcc_voice_parts' ) ? tcc_voice_parts() : [];
		$groups  = [];
		foreach ( get_posts( [ 'post_type' => 'tcc_person', 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] ) as $p ) {
			$part  = get_post_meta( $p->ID, '_tcc_part', true );
			$label = trim( preg_replace( '/\s*\(.*$/', '', $parts[ $part ] ?? 'Everyone else' ) );
			$groups[ $label ][] = [ 'id' => $p->ID, 'name' => $p->post_title, 'on' => in_array( $cantata, array_map( 'intval', (array) get_post_meta( $p->ID, '_tcc_seasons', true ) ), true ) ];
		}
		return [ 'groups' => $groups ];
	} ] );

	// What we have on one person, with contact details only hinted.
	register_rest_route( $ns, '/hints', [ 'methods' => 'GET', 'permission_callback' => $open, 'callback' => function ( WP_REST_Request $r ) {
		$id = (int) $r->get_param( 'person' );
		if ( ! $id || get_post_type( $id ) !== 'tcc_person' ) {
			return new WP_Error( 'bad', 'Unknown person', [ 'status' => 404 ] );
		}
		return [
			'id'     => $id,
			'name'   => get_the_title( $id ),
			'types'  => array_values( (array) get_post_meta( $id, '_tcc_types', true ) ),
			'part'   => (string) get_post_meta( $id, '_tcc_part', true ),
			'solos'  => (bool) get_post_meta( $id, '_tcc_solos', true ),
			'role'   => (string) get_post_meta( $id, '_tcc_role', true ),
			'public' => ! get_post_meta( $id, '_tcc_hidden', true ),
			'phone'  => tcc_mask_phone( (string) get_post_meta( $id, '_tcc_phone', true ) ),
			'email'  => tcc_mask_email( (string) get_post_meta( $id, '_tcc_email', true ) ),
		];
	} ] );

	// The sign-up itself: confirm a returning member or create a new one, put them on this season, remember the device.
	register_rest_route( $ns, '/signup', [ 'methods' => 'POST', 'permission_callback' => $open, 'callback' => function ( WP_REST_Request $r ) {
		$cantata = tcc_rest_cantata( $r );
		$id      = (int) $r->get_param( 'person' );
		$name    = sanitize_text_field( (string) $r->get_param( 'name' ) );
		$types   = array_values( array_intersect( [ 'singers', 'narrators', 'actors', 'tech' ], array_map( 'sanitize_key', (array) $r->get_param( 'types' ) ) ) );
		$part    = sanitize_key( (string) $r->get_param( 'part' ) );
		$solos   = (bool) $r->get_param( 'solos' );
		$role    = sanitize_key( (string) $r->get_param( 'role' ) );
		$phone   = preg_replace( '/[^0-9+().\- ]/', '', (string) $r->get_param( 'phone' ) );
		$email   = sanitize_email( (string) $r->get_param( 'email' ) );
		$public  = $r->get_param( 'public' ) === null ? true : (bool) $r->get_param( 'public' );
		if ( $email && ! is_email( $email ) ) {
			return new WP_Error( 'email', 'That email address doesn\'t look right.', [ 'status' => 400 ] );
		}
		$key = 'tcc_signup_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
		if ( (int) get_transient( $key ) >= 20 ) {
			return new WP_Error( 'rate', 'Too many sign-ups from here. Try again later.', [ 'status' => 429 ] );
		}
		set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );

		$is_new = ! $id;
		if ( $is_new ) {
			if ( mb_strlen( $name ) < 3 || ! str_contains( trim( $name ), ' ' ) ) {
				return new WP_Error( 'name', 'Please give your first and last name.', [ 'status' => 400 ] );
			}
			if ( $phone === '' || $email === '' ) {
				return new WP_Error( 'contact', 'Please give a phone number and an email — they are how the director sends updates.', [ 'status' => 400 ] );
			}
			if ( ! $r->get_param( 'force' ) ) {
				$dups = tcc_similar_people( $name );
				if ( $dups ) {
					return [ 'duplicate' => $dups ];
				}
			}
			$id = wp_insert_post( [ 'post_type' => 'tcc_person', 'post_status' => 'publish', 'post_title' => $name ], true );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
		} elseif ( get_post_type( $id ) !== 'tcc_person' ) {
			return new WP_Error( 'bad', 'Unknown person', [ 'status' => 404 ] );
		} elseif ( ( $phone === '' && ! get_post_meta( $id, '_tcc_phone', true ) ) || ( $email === '' && ! get_post_meta( $id, '_tcc_email', true ) ) ) {
			return new WP_Error( 'contact', 'We need a phone number and an email for you — they are how the director sends updates.', [ 'status' => 400 ] );
		}

		$changed = [];
		if ( $types ) { update_post_meta( $id, '_tcc_types', $types ); $changed[] = 'types'; }
		if ( $part !== '' && function_exists( 'tcc_voice_parts' ) && isset( tcc_voice_parts()[ $part ] ) ) { update_post_meta( $id, '_tcc_part', $part ); }
		elseif ( $part === 'unsure' ) { delete_post_meta( $id, '_tcc_part' ); }
		if ( $solos ) { update_post_meta( $id, '_tcc_solos', '1' ); } else { delete_post_meta( $id, '_tcc_solos' ); }
		if ( $role !== '' && function_exists( 'tcc_volunteer_roles' ) && isset( tcc_volunteer_roles()[ $role ] ) ) { update_post_meta( $id, '_tcc_role', $role ); }
		elseif ( $role === 'none' ) { delete_post_meta( $id, '_tcc_role' ); }
		if ( $phone !== '' ) { update_post_meta( $id, '_tcc_phone', $phone ); $changed[] = 'phone'; }
		if ( $email !== '' ) { update_post_meta( $id, '_tcc_email', $email ); $changed[] = 'email'; }
		if ( $public ) { delete_post_meta( $id, '_tcc_hidden' ); } else { update_post_meta( $id, '_tcc_hidden', '1' ); }
		$seasons = array_map( 'intval', (array) get_post_meta( $id, '_tcc_seasons', true ) );
		if ( ! in_array( $cantata, $seasons, true ) ) {
			$seasons[] = $cantata;
			update_post_meta( $id, '_tcc_seasons', array_values( array_filter( $seasons ) ) );
		}
		update_post_meta( $id, '_tcc_signed_up', current_time( 'mysql' ) );
		update_post_meta( $id, '_tcc_signed_up_season', $cantata );
		if ( $is_new ) {
			update_post_meta( $id, '_tcc_signup_new', '1' );
		}

		setcookie( 'tcc_who', tcc_who_sign( $id ), [ 'expires' => time() + YEAR_IN_SECONDS, 'path' => '/', 'secure' => is_ssl(), 'httponly' => false, 'samesite' => 'Lax' ] );

		// Tell the director.
		if ( function_exists( 'tcc_contact_to' ) ) {
			$parts = function_exists( 'tcc_voice_parts' ) ? tcc_voice_parts() : [];
			$roles = function_exists( 'tcc_volunteer_roles' ) ? tcc_volunteer_roles() : [];
			$lines = [
				( $is_new ? 'New sign-up' : 'Returning member confirmed' ) . ': ' . get_the_title( $id ),
				'Season: ' . tcc_year( $cantata ) . ' ' . get_the_title( $cantata ),
				'Taking part as: ' . ( $types ? implode( ', ', $types ) : '(unchanged)' ),
				'Voice part: ' . ( $parts[ (string) get_post_meta( $id, '_tcc_part', true ) ] ?? ( $part === 'unsure' ? 'not sure yet' : '—' ) ),
				'Interested in solos: ' . ( $solos ? 'yes' : 'no' ),
				'Volunteer team: ' . ( $roles[ (string) get_post_meta( $id, '_tcc_role', true ) ] ?? '—' ),
				'Phone: ' . ( (string) get_post_meta( $id, '_tcc_phone', true ) ?: '—' ),
				'Email: ' . ( (string) get_post_meta( $id, '_tcc_email', true ) ?: '—' ),
				'Public cast list: ' . ( $public ? 'yes' : 'no' ),
				'',
				'Edit: ' . admin_url( 'post.php?post=' . $id . '&action=edit' ),
			];
			wp_mail( tcc_contact_to(), '[' . get_bloginfo( 'name' ) . '] ' . ( $is_new ? 'New sign-up: ' : 'Signed in: ' ) . get_the_title( $id ), implode( "\n", $lines ), [ 'From: ' . get_bloginfo( 'name' ) . ' <' . tcc_contact_from() . '>', 'Content-Type: text/plain; charset=UTF-8' ] );
		}
		if ( function_exists( 'tcc_purge_home' ) ) {
			tcc_purge_home();
		}
		return [ 'id' => $id, 'name' => get_the_title( $id ), 'new' => $is_new ];
	} ] );
} );

/* ---------- admin: who signed up via the site ---------- */

add_filter( 'display_post_states', function ( array $states, WP_Post $post ) {
	if ( $post->post_type === 'tcc_person' && get_post_meta( $post->ID, '_tcc_signed_up', true ) ) {
		$cur = tcc_current_id();
		if ( (int) get_post_meta( $post->ID, '_tcc_signed_up_season', true ) === $cur ) {
			$states['tcc_signed'] = get_post_meta( $post->ID, '_tcc_signup_new', true ) ? '<span style="color:#9c2b1a">New this season (signed up on the site)</span>' : '<span style="color:#355635">Confirmed on the site</span>';
		}
	}
	return $states;
}, 10, 2 );
