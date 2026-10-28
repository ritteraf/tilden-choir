<?php
defined( 'ABSPATH' ) || exit;

/* =====================================================================
 * Cantata → Email: write once, and the site emails everyone on this
 * season's cast (or some of them) one at a time — each person gets their
 * own copy addressed to them, nobody sees anyone else's address, and
 * replies come back to the director. A log of what went out is kept on
 * the cantata.
 * ===================================================================== */

function tcc_mail_cap(): string {
	return 'edit_others_posts';
}

add_action( 'admin_menu', function () {
	add_submenu_page( 'tcc-menu', 'Email the cast', 'Email', tcc_mail_cap(), 'tcc-email', 'tcc_render_email_page' );
}, 11 );

/** Who can be picked: [ key => label ] — everyone, the groups, the voice parts, the teams. */
function tcc_mail_groups(): array {
	$out = [ 'all' => 'Everyone' ];
	foreach ( tcc_attendee_types() as $k => $l ) {
		$out[ $k ] = $l;
	}
	foreach ( tcc_voice_parts() as $k => $l ) {
		$out[ 'part:' . $k ] = $l;
	}
	foreach ( tcc_teams() as $k => $t ) {
		$out[ 'role:' . $k ] = preg_match( '/team$/i', $t['label'] ) ? $t['label'] : $t['label'] . ' team';
	}
	return $out;
}

/** This season's people, with what the picker needs. */
function tcc_mail_people( int $cantata ): array {
	$ids = get_posts( [ 'post_type' => TCC_PERSON, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'title', 'order' => 'ASC',
		'meta_query' => [ [ 'key' => '_tcc_seasons', 'value' => sprintf( 'i:%d;', $cantata ), 'compare' => 'LIKE' ] ] ] );
	if ( function_exists( 'tcc_sort_by_surname' ) ) {
		$ids = tcc_sort_by_surname( $ids );
	}
	$out = [];
	foreach ( $ids as $id ) {
		$name  = get_the_title( $id );
		$email = sanitize_email( (string) get_post_meta( $id, '_tcc_email', true ) );
		$keys  = array_values( array_filter( (array) get_post_meta( $id, '_tcc_types', true ) ) );
		$part  = (string) get_post_meta( $id, '_tcc_part', true );
		$role  = (string) get_post_meta( $id, '_tcc_role', true );
		if ( $part !== '' ) {
			$keys[] = 'part:' . $part;
		}
		if ( $role !== '' ) {
			$keys[] = 'role:' . $role;
		}
		$out[ (int) $id ] = [ 'id' => (int) $id, 'name' => $name, 'first' => tcc_mail_first( $name ), 'email' => is_email( $email ) ? $email : '', 'keys' => $keys ];
	}
	return $out;
}

function tcc_mail_first( string $name ): string {
	$name = trim( preg_replace( '/\s+/', ' ', $name ) );
	return $name === '' ? '' : explode( ' ', $name )[0];
}

/** "Ann", "Bob and Sue", "Ann, Bob and Sue". */
function tcc_mail_join( array $names ): string {
	$names = array_values( array_unique( array_filter( $names ) ) );
	if ( count( $names ) < 2 ) {
		return (string) ( $names[0] ?? '' );
	}
	$last = array_pop( $names );
	return implode( ', ', $names ) . ' and ' . $last;
}

/** One email per address: people who share an address get one copy, greeted together. */
function tcc_mail_envelopes( array $people ): array {
	$by = [];
	foreach ( $people as $p ) {
		if ( $p['email'] === '' ) {
			continue;
		}
		$k = strtolower( $p['email'] );
		$by[ $k ] = $by[ $k ] ?? [ 'email' => $p['email'], 'people' => [] ];
		$by[ $k ]['people'][] = $p;
	}
	return array_values( $by );
}

function tcc_mail_reply_to(): string {
	return function_exists( 'tcc_contact_to' ) ? tcc_contact_to() : (string) get_option( 'admin_email' );
}
function tcc_mail_from(): string {
	return function_exists( 'tcc_contact_from' ) ? tcc_contact_from() : 'wordpress@' . preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
}
function tcc_mail_signer(): string {
	return function_exists( 'tcc_director_first_name' ) ? (string) tcc_director_first_name() : '';
}

/** The text one envelope gets. */
function tcc_mail_text( string $body, bool $hi, string $names, int $cantata ): string {
	$text = ( $hi && $names !== '' ? 'Hi ' . $names . ",\n\n" : '' ) . rtrim( $body );
	$who  = tcc_mail_signer();
	$text .= "\n\n—\nYou're getting this because you're part of the Tilden Community Choir's " . tcc_year( $cantata ) . ' cantata, ' . get_the_title( $cantata ) . '. '
		. 'Just reply to reach ' . ( $who !== '' ? $who : 'the director' ) . '.';
	return $text;
}

function tcc_mail_send_one( string $to_name, string $to_email, string $subject, string $text ): bool {
	$headers = [
		'From: ' . get_bloginfo( 'name' ) . ' <' . tcc_mail_from() . '>',
		'Reply-To: ' . tcc_mail_reply_to(),
		'Content-Type: text/plain; charset=UTF-8',
	];
	$to = $to_name !== '' ? '"' . str_replace( '"', '', $to_name ) . '" <' . $to_email . '>' : $to_email;
	return (bool) wp_mail( $to, $subject, $text, $headers );
}

/* ---------- the draft: kept per user, so a test send or a slip never loses the text ---------- */

function tcc_mail_draft(): array {
	$d = get_user_meta( get_current_user_id(), '_tcc_mail_draft', true );
	return wp_parse_args( is_array( $d ) ? $d : [], [ 'subject' => '', 'body' => '', 'hi' => 1, 'to' => [ 'all' ], 'skip' => [] ] );
}
function tcc_mail_draft_from_post(): array {
	$groups = tcc_mail_groups();
	$to     = array_values( array_intersect( array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['to'] ?? [] ) ), array_keys( $groups ) ) );
	return [
		'subject' => sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) ),
		'body'    => sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) ),
		'hi'      => empty( $_POST['hi'] ) ? 0 : 1,
		'to'      => $to,
		'skip'    => array_values( array_map( 'intval', (array) ( $_POST['skip'] ?? [] ) ) ),
	];
}

/** Who a draft goes to, from this season's people. */
function tcc_mail_pick( array $people, array $draft ): array {
	$to = (array) $draft['to'];
	$skip = array_map( 'intval', (array) $draft['skip'] );
	return array_filter( $people, function ( $p ) use ( $to, $skip ) {
		if ( in_array( $p['id'], $skip, true ) ) {
			return false;
		}
		return in_array( 'all', $to, true ) || array_intersect( $to, $p['keys'] );
	} );
}

/* ---------- the page ---------- */

function tcc_render_email_page() {
	$cantata = tcc_current_id();
	echo '<div class="wrap tcc-mail"><h1>Email the cast</h1>';
	if ( ! $cantata ) {
		echo '<p>Add a cantata first.</p></div>';
		return;
	}
	echo tcc_admin_cantata_line( $cantata ); // phpcs:ignore

	$done = sanitize_key( $_GET['tcc_mail'] ?? '' );
	if ( $done === 'test' ) {
		echo '<div class="notice notice-success is-dismissible"><p>Test sent to ' . esc_html( tcc_mail_reply_to() ) . '. Have a look, then send it for real below.</p></div>';
	} elseif ( $done === 'testfail' ) {
		echo '<div class="notice notice-error"><p>The test didn’t go out — the site couldn’t send mail just now. Try again in a minute.</p></div>';
	} elseif ( $done === 'sent' ) {
		$n = absint( $_GET['n'] ?? 0 );
		$f = absint( $_GET['f'] ?? 0 );
		echo '<div class="notice notice-' . ( $f ? 'warning' : 'success' ) . ' is-dismissible"><p><strong>Sent to ' . $n . ' ' . ( $n === 1 ? 'address' : 'addresses' ) . '.</strong>' . ( $f ? ' ' . $f . ' didn’t go out — see the log below.' : '' ) . '</p></div>';
	} elseif ( $done === 'empty' ) {
		echo '<div class="notice notice-error"><p>Add a subject and a message first.</p></div>';
	} elseif ( $done === 'none' ) {
		echo '<div class="notice notice-error"><p>Nobody picked has an email address.</p></div>';
	} elseif ( $done === 'again' ) {
		echo '<div class="notice notice-info"><p>That one had already gone out — it wasn’t sent twice.</p></div>';
	}

	$people = tcc_mail_people( $cantata );
	$groups = tcc_mail_groups();
	$draft  = tcc_mail_draft();
	/* arriving from the People list with a filter on: start with that group */
	if ( ! empty( $_GET['to'] ) && isset( $groups[ sanitize_text_field( wp_unslash( $_GET['to'] ) ) ] ) ) {
		$draft['to']   = [ sanitize_text_field( wp_unslash( $_GET['to'] ) ) ];
		$draft['skip'] = [];
	}
	/* only offer groups someone this season is actually in */
	$used = [ 'all' => true ];
	foreach ( $people as $p ) {
		foreach ( $p['keys'] as $k ) {
			$used[ $k ] = true;
		}
	}
	?>
	<style>
		.tcc-mail form { max-width: 980px; }
		.tcc-mail__grid { display: grid; grid-template-columns: minmax(0, 1fr) 300px; gap: 24px; align-items: start; }
		@media (max-width: 960px) { .tcc-mail__grid { grid-template-columns: 1fr; } }
		.tcc-mail__box { background: #fff; border: 1px solid #dcdcde; border-radius: 6px; padding: 16px 18px; }
		.tcc-mail__box h2 { font-size: 14px; margin: 0 0 10px; }
		.tcc-mail__pills { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 4px; }
		.tcc-mail__pill { position: relative; }
		.tcc-mail__pill input { position: absolute; opacity: 0; pointer-events: none; }
		.tcc-mail__pill span { display: inline-block; padding: 4px 11px; border: 1px solid #c3c4c7; border-radius: 999px; cursor: pointer; background: #f6f7f7; user-select: none; }
		.tcc-mail__pill input:checked + span { background: #2271b1; border-color: #2271b1; color: #fff; }
		.tcc-mail__pill input:focus-visible + span { outline: 2px solid #2271b1; outline-offset: 2px; }
		.tcc-mail__sub { font-size: 12px; color: #646970; margin: 10px 0 4px; }
		.tcc-mail input[type=text].tcc-mail__subject, .tcc-mail textarea { box-sizing: border-box; font-family: inherit; }
		.tcc-mail input[type=text].tcc-mail__subject { width: 100%; font-size: 15px; padding: 6px 10px; }
		.tcc-mail textarea { width: 100%; min-height: 260px; font-size: 15px; line-height: 1.55; padding: 10px 12px; }
		.tcc-mail__hi { display: block; margin: 10px 0 2px; }
		.tcc-mail__foot { font-size: 12px; color: #646970; white-space: pre-wrap; border-left: 3px solid #dcdcde; padding: 2px 0 2px 10px; margin: 10px 0 0; }
		.tcc-mail__who { max-height: 460px; overflow: auto; margin: 0 -4px; }
		.tcc-mail__who label { display: flex; gap: 8px; align-items: center; padding: 3px 4px; border-radius: 4px; }
		.tcc-mail__who label:hover { background: #f6f7f7; }
		.tcc-mail__who label.is-out { color: #a7aaad; }
		.tcc-mail__who label.is-noemail { color: #a7aaad; }
		.tcc-mail__who small { margin-left: auto; color: #8c8f94; font-size: 11px; }
		.tcc-mail__who a { margin-left: auto; font-size: 11px; }
		.tcc-mail__count { font-weight: 600; }
		.tcc-mail__send { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-top: 16px; }
		.tcc-mail__meta { font-size: 12px; color: #646970; margin: 8px 0 0; }
		.tcc-mail__log { max-width: 980px; margin-top: 34px; }
		.tcc-mail__log details { background: #fff; border: 1px solid #dcdcde; border-radius: 6px; padding: 10px 14px; margin-bottom: 8px; }
		.tcc-mail__log summary { cursor: pointer; display: flex; gap: 12px; align-items: baseline; }
		.tcc-mail__log summary b { flex: 1; }
		.tcc-mail__log summary span { color: #646970; font-size: 12px; white-space: nowrap; }
		.tcc-mail__log pre { white-space: pre-wrap; font: 14px/1.55 -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f6f7f7; padding: 10px 12px; border-radius: 4px; margin: 10px 0; }
		.tcc-mail__log .tcc-mail__fail { color: #b32d2e; }
	</style>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="tcc-mail-form">
		<input type="hidden" name="action" value="tcc_mail">
		<input type="hidden" name="once" value="<?php echo esc_attr( wp_generate_password( 16, false ) ); ?>">
		<?php wp_nonce_field( 'tcc_mail' ); ?>
		<div class="tcc-mail__grid">
			<div>
				<div class="tcc-mail__box">
					<h2>To</h2>
					<div class="tcc-mail__pills">
						<?php
						$sections = [ 'top' => [], 'part' => [], 'role' => [] ];
						foreach ( $groups as $k => $l ) {
							if ( empty( $used[ $k ] ) ) {
								continue;
							}
							$sections[ str_starts_with( $k, 'part:' ) ? 'part' : ( str_starts_with( $k, 'role:' ) ? 'role' : 'top' ) ][ $k ] = $l;
						}
						$pill = function ( $k, $l ) use ( $draft ) {
							return '<label class="tcc-mail__pill"><input type="checkbox" name="to[]" value="' . esc_attr( $k ) . '"' . checked( in_array( $k, (array) $draft['to'], true ), true, false ) . '><span>' . esc_html( $l ) . '</span></label>';
						};
						foreach ( $sections['top'] as $k => $l ) {
							echo $pill( $k, $l ); // phpcs:ignore
						}
						echo '</div>';
						if ( $sections['part'] ) {
							echo '<p class="tcc-mail__sub">Voice parts</p><div class="tcc-mail__pills">';
							foreach ( $sections['part'] as $k => $l ) {
								echo $pill( $k, $l ); // phpcs:ignore
							}
							echo '</div>';
						}
						if ( $sections['role'] ) {
							echo '<p class="tcc-mail__sub">Teams</p><div class="tcc-mail__pills">';
							foreach ( $sections['role'] as $k => $l ) {
								echo $pill( $k, $l ); // phpcs:ignore
							}
							echo '</div>';
						}
						?>
				</div>
				<div class="tcc-mail__box" style="margin-top:16px">
					<h2><label for="tcc-mail-subject">Subject</label></h2>
					<input type="text" class="tcc-mail__subject" id="tcc-mail-subject" name="subject" value="<?php echo esc_attr( $draft['subject'] ); ?>" placeholder="Rehearsal moved to Thursday" maxlength="160">
					<h2 style="margin-top:16px;display:flex;align-items:center;gap:10px"><label for="tcc-mail-body">Message</label>
						<button type="button" class="button button-small" id="tcc-mail-link" style="margin-left:auto">Add the Backstage link</button></h2>
					<textarea id="tcc-mail-body" name="body" placeholder="Write it like a normal email."><?php echo esc_textarea( $draft['body'] ); ?></textarea>
					<label class="tcc-mail__hi"><input type="checkbox" name="hi" value="1"<?php checked( ! empty( $draft['hi'] ) ); ?>> Start each email with “Hi <em>first name</em>,”</label>
					<p class="tcc-mail__foot">Each email ends with:
—
You're getting this because you're part of the Tilden Community Choir's <?php echo esc_html( tcc_year( $cantata ) . ' cantata, ' . get_the_title( $cantata ) ); ?>. Just reply to reach <?php echo esc_html( tcc_mail_signer() ?: 'the director' ); ?>.</p>
				</div>
				<div class="tcc-mail__send">
					<button type="submit" name="go" value="send" class="button button-primary button-large" id="tcc-mail-send">Send to <span class="tcc-mail__n">0</span> people</button>
					<button type="submit" name="go" value="test" class="button button-large">Send me a test</button>
				</div>
				<p class="tcc-mail__meta">From <?php echo esc_html( get_bloginfo( 'name' ) . ' <' . tcc_mail_from() . '>' ); ?> · replies go to <strong><?php echo esc_html( tcc_mail_reply_to() ); ?></strong> · each person gets their own copy.</p>
			</div>
			<div class="tcc-mail__box">
				<h2><span class="tcc-mail__count">0</span> of <?php echo count( $people ); ?> people</h2>
				<div class="tcc-mail__who">
					<?php
					foreach ( $people as $p ) {
						if ( $p['email'] === '' ) {
							echo '<label class="is-noemail" data-keys="' . esc_attr( implode( ' ', $p['keys'] ) ) . '"><input type="checkbox" disabled> ' . esc_html( $p['name'] ) . ' <a href="' . esc_url( get_edit_post_link( $p['id'] ) ) . '">add email</a></label>';
							continue;
						}
						echo '<label data-keys="' . esc_attr( implode( ' ', $p['keys'] ) ) . '"><input type="checkbox" class="tcc-mail__p" value="' . (int) $p['id'] . '"' . ( in_array( $p['id'], array_map( 'intval', (array) $draft['skip'] ), true ) ? ' data-skip="1"' : '' ) . '> ' . esc_html( $p['name'] ) . '</label>';
					}
					?>
				</div>
				<p class="tcc-mail__meta">Untick anyone to leave them out.</p>
			</div>
		</div>
		<div id="tcc-mail-skips"></div>
	</form>
	<script>
	( function () {
		var form = document.getElementById( 'tcc-mail-form' ), link = <?php echo wp_json_encode( home_url( '/backstage/' ) ); ?>;
		var pills = [].slice.call( form.querySelectorAll( 'input[name="to[]"]' ) );
		var rows = [].slice.call( form.querySelectorAll( '.tcc-mail__who label' ) );
		var skip = {};
		form.querySelectorAll( '.tcc-mail__p[data-skip]' ).forEach( function ( b ) { skip[ b.value ] = true; } );
		function picked() { return pills.filter( function ( p ) { return p.checked; } ).map( function ( p ) { return p.value; } ); }
		function inGroup( row, to ) {
			if ( to.indexOf( 'all' ) !== -1 ) { return true; }
			var keys = ( row.getAttribute( 'data-keys' ) || '' ).split( ' ' );
			return to.some( function ( k ) { return keys.indexOf( k ) !== -1; } );
		}
		function paint() {
			var to = picked(), n = 0;
			rows.forEach( function ( row ) {
				var box = row.querySelector( 'input' ), on = inGroup( row, to );
				row.hidden = ! on;
				if ( box.disabled ) { return; }
				box.checked = on && ! skip[ box.value ];
				row.classList.toggle( 'is-out', ! box.checked );
				if ( box.checked ) { n++; }
			} );
			form.querySelectorAll( '.tcc-mail__count, .tcc-mail__n' ).forEach( function ( el ) { el.textContent = n; } );
			document.getElementById( 'tcc-mail-send' ).disabled = ! n;
			document.getElementById( 'tcc-mail-send' ).lastChild.textContent = n === 1 ? ' person' : ' people';
			var sk = document.getElementById( 'tcc-mail-skips' ); sk.innerHTML = '';
			Object.keys( skip ).forEach( function ( id ) { var i = document.createElement( 'input' ); i.type = 'hidden'; i.name = 'skip[]'; i.value = id; sk.appendChild( i ); } );
		}
		pills.forEach( function ( p ) {
			p.addEventListener( 'change', function () {
				/* Everyone, or a pick of groups — not both */
				if ( p.value === 'all' && p.checked ) { pills.forEach( function ( q ) { if ( q !== p ) { q.checked = false; } } ); }
				if ( p.value !== 'all' && p.checked ) { pills.forEach( function ( q ) { if ( q.value === 'all' ) { q.checked = false; } } ); }
				if ( ! picked().length ) { pills[ 0 ].checked = true; }
				paint();
			} );
		} );
		rows.forEach( function ( row ) {
			var box = row.querySelector( 'input' );
			box.addEventListener( 'change', function () { if ( box.checked ) { delete skip[ box.value ]; } else { skip[ box.value ] = true; } paint(); } );
		} );
		document.getElementById( 'tcc-mail-link' ).addEventListener( 'click', function () {
			var ta = document.getElementById( 'tcc-mail-body' ), s = ta.selectionStart || ta.value.length, e = ta.selectionEnd || s;
			var bit = ( s && ! /\s$/.test( ta.value.slice( 0, s ) ) ? ' ' : '' ) + link + ' ';
			ta.value = ta.value.slice( 0, s ) + bit + ta.value.slice( e ); ta.focus(); ta.selectionStart = ta.selectionEnd = s + bit.length;
		} );
		form.addEventListener( 'submit', function ( ev ) {
			var go = ev.submitter ? ev.submitter.value : 'send';
			if ( go !== 'send' ) { return; }
			var n = +form.querySelector( '.tcc-mail__n' ).textContent;
			if ( ! window.confirm( 'Send this email to ' + n + ( n === 1 ? ' person' : ' people' ) + ' now?' ) ) { ev.preventDefault(); return; }
			form.querySelectorAll( 'button[type=submit]' ).forEach( function ( b ) { setTimeout( function () { b.disabled = true; }, 0 ); } );
			ev.submitter.lastChild.textContent = ' — sending…';
		} );
		paint();
	} )();
	</script>
	<?php
	tcc_render_mail_log( $cantata );
	echo '</div>';
}

function tcc_render_mail_log( int $cantata ) {
	$log = array_values( array_filter( (array) get_post_meta( $cantata, '_tcc_mail_log', true ), 'is_array' ) );
	echo '<div class="tcc-mail__log"><h2>Sent this season</h2>';
	if ( ! $log ) {
		echo '<p class="description">Nothing yet.</p></div>';
		return;
	}
	foreach ( $log as $m ) {
		$n = count( (array) $m['to'] );
		echo '<details><summary><b>' . esc_html( $m['subject'] ) . '</b><span>' . esc_html( $n . ' ' . ( $n === 1 ? 'person' : 'people' ) . ' · ' . wp_date( 'D M j, g:i A', (int) $m['t'] ) ) . '</span></summary>';
		echo '<pre>' . esc_html( $m['body'] ) . '</pre>';
		echo '<p class="tcc-mail__meta"><strong>To:</strong> ' . esc_html( implode( ', ', (array) $m['to'] ) ) . '</p>';
		if ( ! empty( $m['failed'] ) ) {
			echo '<p class="tcc-mail__meta tcc-mail__fail"><strong>Didn’t go out:</strong> ' . esc_html( implode( ', ', (array) $m['failed'] ) ) . '</p>';
		}
		echo '</details>';
	}
	echo '</div>';
}

/* ---------- sending ---------- */

add_action( 'admin_post_tcc_mail', function () {
	if ( ! current_user_can( tcc_mail_cap() ) || ! check_admin_referer( 'tcc_mail' ) ) {
		wp_die( 'Not allowed.' );
	}
	$cantata = tcc_current_id();
	$back    = admin_url( 'admin.php?page=tcc-email' );
	$draft   = tcc_mail_draft_from_post();
	update_user_meta( get_current_user_id(), '_tcc_mail_draft', $draft );
	if ( ! $cantata || $draft['subject'] === '' || trim( $draft['body'] ) === '' ) {
		wp_safe_redirect( add_query_arg( 'tcc_mail', 'empty', $back ) );
		exit;
	}
	$go = sanitize_key( $_POST['go'] ?? '' );

	if ( $go === 'test' ) {
		$me   = tcc_mail_signer();
		$text = tcc_mail_text( $draft['body'], (bool) $draft['hi'], $me, $cantata );
		$ok   = tcc_mail_send_one( $me, tcc_mail_reply_to(), '[Test] ' . $draft['subject'], $text );
		wp_safe_redirect( add_query_arg( 'tcc_mail', $ok ? 'test' : 'testfail', $back ) );
		exit;
	}

	/* a double-click or a resubmitted page never sends twice */
	$once = sanitize_key( $_POST['once'] ?? '' );
	if ( $once === '' || get_transient( 'tcc_mail_once_' . $once ) ) {
		wp_safe_redirect( add_query_arg( 'tcc_mail', 'again', $back ) );
		exit;
	}
	set_transient( 'tcc_mail_once_' . $once, 1, DAY_IN_SECONDS );

	$people = tcc_mail_pick( tcc_mail_people( $cantata ), $draft );
	$envs   = tcc_mail_envelopes( $people );
	if ( ! $envs ) {
		wp_safe_redirect( add_query_arg( 'tcc_mail', 'none', $back ) );
		exit;
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore
	}
	$sent = [];
	$fail = [];
	$good = 0;
	foreach ( $envs as $env ) {
		$names = array_map( fn( $p ) => $p['first'], $env['people'] );
		$full  = tcc_mail_join( array_map( fn( $p ) => $p['name'], $env['people'] ) );
		$ok    = tcc_mail_send_one( $full, $env['email'], $draft['subject'], tcc_mail_text( $draft['body'], (bool) $draft['hi'], tcc_mail_join( $names ), $cantata ) );
		$good += $ok ? 1 : 0;
		foreach ( $env['people'] as $p ) {
			if ( $ok ) {
				$sent[] = $p['name'];
			} else {
				$fail[] = $p['name'];
			}
		}
	}

	$log = array_values( array_filter( (array) get_post_meta( $cantata, '_tcc_mail_log', true ), 'is_array' ) );
	array_unshift( $log, [ 't' => time(), 'by' => get_current_user_id(), 'subject' => $draft['subject'], 'body' => $draft['body'], 'to' => $sent, 'failed' => $fail ] );
	update_post_meta( $cantata, '_tcc_mail_log', array_slice( $log, 0, 100 ) );

	/* sent: start the next one fresh, same people picked */
	update_user_meta( get_current_user_id(), '_tcc_mail_draft', [ 'subject' => '', 'body' => '', 'hi' => $draft['hi'], 'to' => $draft['to'], 'skip' => [] ] );
	wp_safe_redirect( add_query_arg( [ 'tcc_mail' => 'sent', 'n' => $good, 'f' => count( $envs ) - $good ], $back ) );
	exit;
} );

/* ---------- People list: "Email these people", carrying the filter across ---------- */

add_action( 'restrict_manage_posts', function ( string $post_type ) {
	if ( $post_type !== TCC_PERSON || ! current_user_can( tcc_mail_cap() ) ) {
		return;
	}
	$to = 'all';
	if ( ! empty( $_GET['tcc_f_part'] ) ) {
		$to = 'part:' . sanitize_key( $_GET['tcc_f_part'] );
	} elseif ( ! empty( $_GET['tcc_f_role'] ) ) {
		$to = 'role:' . sanitize_key( $_GET['tcc_f_role'] );
	} elseif ( ! empty( $_GET['tcc_f_type'] ) ) {
		$to = sanitize_key( $_GET['tcc_f_type'] );
	}
	echo '<a class="button" style="margin-left:6px" href="' . esc_url( admin_url( 'admin.php?page=tcc-email&to=' . rawurlencode( $to ) ) ) . '">Email ' . ( $to === 'all' ? 'this season' : 'these people' ) . '</a>';
}, 20 );
