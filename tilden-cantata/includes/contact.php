<?php
defined( 'ABSPATH' ) || exit;

/* =====================================================================
 * Contact form: a block visitors fill in, a private "Messages" list in
 * the admin, and an email to the director. No third-party service.
 * ===================================================================== */

define( 'TCC_MSG', 'tcc_message' );

/** What a visitor can say they're writing about. Filterable. */
function tcc_contact_interests(): array {
	return apply_filters( 'tcc_contact_interests', [
		'join'      => 'I\'d like to sing with the choir',
		'help'      => 'I\'d like to help without singing — narrating, acting, backstage, tech',
		'concert'   => 'A question about the concert — when, where, how to watch',
		'support'   => 'Supporting the choir or the free-will offering',
		'group'     => 'I represent a church, school or group',
		'other'     => 'Something else',
	] );
}

function tcc_contact_to(): string {
	return (string) tcc_option( 'contact_to', get_option( 'admin_email' ) );
}

function tcc_contact_from(): string {
	$host = wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'localhost';
	return (string) tcc_option( 'contact_from', 'wordpress@' . preg_replace( '/^www\./', '', $host ) );
}

/* ---------- the Messages post type ---------- */

add_action( 'init', function () {
	register_post_type( TCC_MSG, [
		'labels' => [
			'name' => 'Messages', 'singular_name' => 'Message', 'all_items' => 'Messages', 'edit_item' => 'Message',
			'search_items' => 'Search messages', 'not_found' => 'No messages yet.', 'menu_name' => 'Messages',
		],
		'public' => false, 'exclude_from_search' => true, 'publicly_queryable' => false,
		'show_ui' => true, 'show_in_menu' => 'tcc-menu', 'show_in_rest' => false,
		'supports' => [ 'title' ], 'capability_type' => 'post', 'map_meta_cap' => true,
		'capabilities' => [ 'create_posts' => 'do_not_allow' ], // only the form makes these
	] );
} );

/** Unread count beside "Messages" in the admin menu. */
add_action( 'admin_menu', function () {
	global $submenu;
	if ( empty( $submenu['tcc-menu'] ) ) {
		return;
	}
	$n = (int) ( new WP_Query( [ 'post_type' => TCC_MSG, 'post_status' => 'private', 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => false, 'meta_query' => [ [ 'key' => '_tcc_read', 'compare' => 'NOT EXISTS' ] ] ] ) )->found_posts;
	if ( ! $n ) {
		return;
	}
	foreach ( $submenu['tcc-menu'] as &$item ) {
		if ( $item[2] === 'edit.php?post_type=' . TCC_MSG ) {
			$item[0] .= ' <span class="awaiting-mod">' . $n . '</span>';
		}
	}
}, 100 );

/** Opening a message marks it read. */
add_action( 'load-post.php', function () {
	$id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	if ( $id && get_post_type( $id ) === TCC_MSG && current_user_can( 'edit_post', $id ) ) {
		update_post_meta( $id, '_tcc_read', current_time( 'mysql' ) );
	}
} );

add_action( 'add_meta_boxes_' . TCC_MSG, function () {
	remove_meta_box( 'submitdiv', TCC_MSG, 'side' );
	add_meta_box( 'tcc_message', 'Message', 'tcc_render_message_box', TCC_MSG, 'normal', 'high' );
} );

function tcc_render_message_box( WP_Post $post ) {
	$name  = get_post_meta( $post->ID, '_tcc_name', true );
	$email = get_post_meta( $post->ID, '_tcc_email', true );
	$phone = get_post_meta( $post->ID, '_tcc_phone', true );
	$int   = tcc_contact_interests()[ get_post_meta( $post->ID, '_tcc_interest', true ) ] ?? '';
	$when  = get_the_date( 'l, F j, Y \a\t g:i A', $post );
	$subject = 'Re: your message to the Tilden Community Choir';
	echo '<table class="form-table" style="margin-top:0"><tbody>';
	echo '<tr><th style="width:120px">From</th><td><strong>' . esc_html( $name ) . '</strong>' . ( $email ? ' &lt;<a href="mailto:' . esc_attr( $email ) . '?subject=' . rawurlencode( $subject ) . '">' . esc_html( $email ) . '</a>&gt;' : '' ) . '</td></tr>';
	if ( $phone ) {
		echo '<tr><th>Phone</th><td><a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a></td></tr>';
	}
	if ( $int ) {
		echo '<tr><th>About</th><td>' . esc_html( $int ) . '</td></tr>';
	}
	echo '<tr><th>Received</th><td>' . esc_html( $when ) . '</td></tr>';
	echo '</tbody></table>';
	echo '<div style="padding:16px 18px;border:1px solid #dcdcde;border-radius:4px;background:#fff;font-size:15px;line-height:1.6;white-space:pre-wrap">' . esc_html( $post->post_content ) . '</div>';
	if ( $email ) {
		echo '<p style="margin-top:14px"><a class="button button-primary" href="mailto:' . esc_attr( $email ) . '?subject=' . rawurlencode( $subject ) . '">Reply by email</a> <a class="button" href="' . esc_url( get_delete_post_link( $post->ID ) ) . '">Move to trash</a></p>';
	}
}

add_filter( 'manage_' . TCC_MSG . '_posts_columns', function ( array $cols ) {
	return [ 'cb' => $cols['cb'], 'title' => 'From', 'tcc_about' => 'About', 'tcc_contact' => 'Contact', 'tcc_excerpt' => 'Message', 'date' => 'Received' ];
} );
add_action( 'manage_' . TCC_MSG . '_posts_custom_column', function ( string $col, int $id ) {
	switch ( $col ) {
		case 'tcc_about':
			echo esc_html( tcc_contact_interests()[ get_post_meta( $id, '_tcc_interest', true ) ] ?? '' );
			break;
		case 'tcc_contact':
			$e = get_post_meta( $id, '_tcc_email', true ); $p = get_post_meta( $id, '_tcc_phone', true );
			echo $e ? '<a href="mailto:' . esc_attr( $e ) . '">' . esc_html( $e ) . '</a>' : '';
			echo $e && $p ? '<br>' : '';
			echo esc_html( $p );
			break;
		case 'tcc_excerpt':
			echo esc_html( wp_trim_words( get_post_field( 'post_content', $id ), 18 ) );
			break;
	}
}, 10, 2 );
add_filter( 'post_row_actions', function ( array $actions, WP_Post $post ) {
	if ( $post->post_type === TCC_MSG ) {
		unset( $actions['inline hide-if-no-js'], $actions['view'] );
		$actions['edit'] = '<a href="' . esc_url( get_edit_post_link( $post->ID ) ) . '">Read</a>';
	}
	return $actions;
}, 10, 2 );
add_filter( 'display_post_states', function ( array $states, WP_Post $post ) {
	if ( $post->post_type === TCC_MSG ) {
		$states = get_post_meta( $post->ID, '_tcc_read', true ) ? [] : [ 'tcc_new' => '<span style="color:#9c2b1a">New</span>' ];
	}
	return $states;
}, 10, 2 );

/* ---------- the form ---------- */

/** A cache-safe token: the time it was issued, signed. Replaces a nonce, which a cached page would serve stale. */
function tcc_contact_token(): string {
	$t = (string) time();
	return $t . '.' . substr( hash_hmac( 'sha256', $t, wp_salt( 'nonce' ) ), 0, 20 );
}
function tcc_contact_token_age( string $token ): ?int {
	if ( ! preg_match( '/^(\d{9,11})\.([a-f0-9]{20})$/', $token, $m ) ) {
		return null;
	}
	if ( ! hash_equals( substr( hash_hmac( 'sha256', $m[1], wp_salt( 'nonce' ) ), 0, 20 ), $m[2] ) ) {
		return null;
	}
	return time() - (int) $m[1];
}

function tcc_render_contact_form( string $button = 'Send message', string $thanks = '' ): string {
	$thanks = $thanks ?: 'Thanks — your message is on its way. I\'ll get back to you soon.';
	$sent   = isset( $_GET['tcc_sent'] ) ? sanitize_key( $_GET['tcc_sent'] ) : '';
	$errors = [
		'empty'  => 'Please fill in your name, your email and a message.',
		'email'  => 'That email address doesn\'t look right.',
		'rate'   => 'That\'s a few messages in a row — give it a little while and try again.',
		'spam'   => 'The form didn\'t go through. Please try again, or email the director directly.',
		'mail'   => 'The message was saved but the email didn\'t send. The director will still see it.',
	];
	$out = '<div class="tcc-form" id="contact">';
	if ( $sent === 'ok' ) {
		return $out . '<div class="tcc-form__done">' . tcc_svg( 'note' ) . '<p>' . esc_html( $thanks ) . '</p></div></div>';
	}
	if ( $sent && isset( $errors[ $sent ] ) ) {
		$out .= '<p class="tcc-form__error" role="alert">' . esc_html( $errors[ $sent ] ) . '</p>';
	}
	$v = fn( $k ) => isset( $_GET[ 'tcc_' . $k ] ) ? esc_attr( wp_unslash( $_GET[ 'tcc_' . $k ] ) ) : '';
	$out .= '<form class="tcc-form__form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" novalidate>';
	$out .= '<input type="hidden" name="action" value="tcc_contact">';
	$out .= '<input type="hidden" name="tcc_t" value="' . esc_attr( tcc_contact_token() ) . '">';
	$back = remove_query_arg( [ 'tcc_sent', 'tcc_name', 'tcc_email', 'tcc_phone', 'tcc_interest' ] );
	if ( ! preg_match( '#^https?://#', $back ) ) {
		$back = home_url( $back ); // remove_query_arg() gives a path; the handler needs the full address
	}
	$out .= '<input type="hidden" name="tcc_back" value="' . esc_url( $back ) . '">';
	// Honeypot: real people never see this; bots fill it in.
	$out .= '<div class="tcc-form__hp" aria-hidden="true"><label for="tcc_website">Website</label><input type="text" id="tcc_website" name="tcc_website" tabindex="-1" autocomplete="off"></div>';
	$out .= '<div class="tcc-form__row">';
	$out .= '<label class="tcc-form__field"><span class="tcc-form__label">Your name</span><input type="text" name="tcc_name" required autocomplete="name" value="' . $v( 'name' ) . '"></label>';
	$out .= '<label class="tcc-form__field"><span class="tcc-form__label">Email</span><input type="email" name="tcc_email" required autocomplete="email" value="' . $v( 'email' ) . '"></label>';
	$out .= '</div><div class="tcc-form__row">';
	$out .= '<label class="tcc-form__field"><span class="tcc-form__label">Phone <em>optional</em></span><input type="tel" name="tcc_phone" autocomplete="tel" value="' . $v( 'phone' ) . '"></label>';
	$out .= '<label class="tcc-form__field"><span class="tcc-form__label">I\'m writing about</span><select name="tcc_interest">';
	foreach ( tcc_contact_interests() as $k => $l ) {
		$out .= '<option value="' . esc_attr( $k ) . '"' . selected( $v( 'interest' ), $k, false ) . '>' . esc_html( $l ) . '</option>';
	}
	$out .= '</select></label></div>';
	$out .= '<label class="tcc-form__field"><span class="tcc-form__label">Message</span><textarea name="tcc_message" rows="6" required></textarea></label>';
	$out .= '<div class="tcc-form__actions"><button type="submit" class="tcc-btn tcc-btn--primary">' . tcc_svg( 'mail' ) . '<span>' . esc_html( $button ) . '</span></button></div>';
	$out .= '</form></div>';
	return $out;
}

/** Receives the form, logged in or not. */
function tcc_handle_contact() {
	$back = isset( $_POST['tcc_back'] ) ? esc_url_raw( wp_unslash( $_POST['tcc_back'] ) ) : '';
	if ( $back && ! wp_parse_url( $back, PHP_URL_HOST ) ) {
		$back = home_url( $back ); // a path on this site
	}
	if ( ! $back || wp_parse_url( $back, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
		$back = home_url( '/' );
	}
	$name     = sanitize_text_field( wp_unslash( $_POST['tcc_name'] ?? '' ) );
	$email    = sanitize_email( wp_unslash( $_POST['tcc_email'] ?? '' ) );
	$phone    = preg_replace( '/[^0-9+().\- ]/', '', (string) wp_unslash( $_POST['tcc_phone'] ?? '' ) );
	$interest = sanitize_key( $_POST['tcc_interest'] ?? 'other' );
	$message  = trim( sanitize_textarea_field( wp_unslash( $_POST['tcc_message'] ?? '' ) ) );
	$keep     = [ 'tcc_name' => $name, 'tcc_email' => $email, 'tcc_phone' => $phone, 'tcc_interest' => $interest ];
	$fail     = fn( string $why ) => wp_safe_redirect( add_query_arg( array_filter( $keep ) + [ 'tcc_sent' => $why ], $back ) . '#contact' );

	// Spam: honeypot filled, token missing or forged, or submitted faster than a person could type.
	$age = tcc_contact_token_age( (string) ( $_POST['tcc_t'] ?? '' ) );
	if ( ! empty( $_POST['tcc_website'] ) || $age === null || $age < 4 || $age > 7 * DAY_IN_SECONDS ) {
		$fail( 'spam' );
		exit;
	}
	if ( $name === '' || $email === '' || $message === '' ) {
		$fail( 'empty' );
		exit;
	}
	if ( ! is_email( $email ) ) {
		$fail( 'email' );
		exit;
	}
	$ip  = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
	$key = 'tcc_contact_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= 5 ) {
		$fail( 'rate' );
		exit;
	}
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );
	if ( ! array_key_exists( $interest, tcc_contact_interests() ) ) {
		$interest = 'other';
	}

	$id = wp_insert_post( [
		'post_type'    => TCC_MSG,
		'post_status'  => 'private',
		'post_title'   => $name,
		'post_content' => $message,
	], true );
	if ( ! is_wp_error( $id ) ) {
		update_post_meta( $id, '_tcc_name', $name );
		update_post_meta( $id, '_tcc_email', $email );
		update_post_meta( $id, '_tcc_phone', $phone );
		update_post_meta( $id, '_tcc_interest', $interest );
		update_post_meta( $id, '_tcc_ip', $ip );
	}

	$about   = tcc_contact_interests()[ $interest ];
	$subject = sprintf( '[%s] %s — %s', get_bloginfo( 'name' ), $name, $about );
	$body    = "From: {$name} <{$email}>\n" . ( $phone ? "Phone: {$phone}\n" : '' ) . "About: {$about}\n\n{$message}\n\n—\n"
		. ( ! is_wp_error( $id ) ? 'Saved under Cantata → Messages: ' . admin_url( 'post.php?post=' . $id . '&action=edit' ) : 'Could not be saved to the site; this email is the only copy.' );
	$headers = [
		'From: ' . get_bloginfo( 'name' ) . ' <' . tcc_contact_from() . '>',
		'Reply-To: ' . $name . ' <' . $email . '>',
		'Content-Type: text/plain; charset=UTF-8',
	];
	$ok = wp_mail( tcc_contact_to(), $subject, $body, $headers );

	wp_safe_redirect( add_query_arg( 'tcc_sent', $ok ? 'ok' : 'mail', $back ) . '#contact' );
	exit;
}
add_action( 'admin_post_tcc_contact', 'tcc_handle_contact' );
add_action( 'admin_post_nopriv_tcc_contact', 'tcc_handle_contact' );

/** Settings → "Send a test" button. */
add_action( 'admin_post_tcc_test_mail', function () {
	if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'tcc_test_mail' ) ) {
		wp_die( 'Not allowed.' );
	}
	$ok = wp_mail( tcc_contact_to(), '[' . get_bloginfo( 'name' ) . '] Test message from the website', "If you're reading this, the contact form can reach you.\n\nSent from " . home_url( '/' ) . ' as ' . tcc_contact_from() . '.', [
		'From: ' . get_bloginfo( 'name' ) . ' <' . tcc_contact_from() . '>', 'Content-Type: text/plain; charset=UTF-8',
	] );
	wp_safe_redirect( admin_url( 'admin.php?page=tcc-settings&tcc_test=' . ( $ok ? 'ok' : 'fail' ) ) );
	exit;
} );

/* ---------- block ---------- */

add_action( 'init', function () {
	register_block_type( 'tilden/contact-form', [
		'api_version' => 3, 'title' => 'Contact form', 'description' => 'Name, email, phone, what they\'re writing about, and a message. Emails the director and keeps a copy under Cantata → Messages.',
		'category' => 'widgets', 'icon' => 'email',
		'supports' => [ 'html' => false, 'multiple' => false ],
		'attributes' => [ 'button' => [ 'type' => 'string', 'default' => 'Send message' ], 'thanks' => [ 'type' => 'string', 'default' => '' ] ],
		'render_callback' => function ( array $attributes ) {
			tcc_enqueue_front_assets();
			if ( function_exists( 'nocache_headers' ) && ! headers_sent() && isset( $_GET['tcc_sent'] ) ) {
				nocache_headers(); // the thank-you / error view must never be cached
			}
			return '<div ' . get_block_wrapper_attributes() . '>' . tcc_render_contact_form( (string) ( $attributes['button'] ?? 'Send message' ), (string) ( $attributes['thanks'] ?? '' ) ) . '</div>';
		},
	] );
} );
