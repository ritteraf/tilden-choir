<?php
defined( 'ABSPATH' ) || exit;

/* =====================================================================
 * The score: one PDF of the whole work, kept out of the media library,
 * where each song starts, and when its pages turn.
 *
 *   _tcc_score        [ file, name, size, pages, offset ]   offset = printed page number of PDF page 1, minus 1
 *   _tcc_score_songs  [ song index => [ first PDF page, last PDF page ] ]
 *   _tcc_turns        [ song index => [ seconds, … ] ]       same for every part: the tracks share one clock
 *
 * The file sits under uploads/tcc-scores/ with an unguessable name, a deny-all
 * .htaccess and an empty index; the app gets it through /tcc/v1/score, which
 * needs the Backstage page token.
 * ===================================================================== */

function tcc_score_dir(): string {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'tcc-scores';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( ! file_exists( $dir . '/.htaccess' ) ) {
		@file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" );
	}
	if ( ! file_exists( $dir . '/index.php' ) ) {
		@file_put_contents( $dir . '/index.php', "<?php // Silence.\n" );
	}
	return $dir;
}

function tcc_score( int $cantata_id ): array {
	$s = get_post_meta( $cantata_id, '_tcc_score', true );
	if ( ! is_array( $s ) || empty( $s['file'] ) || ! file_exists( tcc_score_dir() . '/' . basename( $s['file'] ) ) ) {
		return [];
	}
	return $s;
}
function tcc_score_songs( int $cantata_id ): array {
	$v = get_post_meta( $cantata_id, '_tcc_score_songs', true );
	return is_array( $v ) ? $v : [];
}
function tcc_turns( int $cantata_id ): array {
	$v = get_post_meta( $cantata_id, '_tcc_turns', true );
	return is_array( $v ) ? $v : [];
}

/** What the practice app needs, or null when there is no score. */
function tcc_score_data( int $cantata_id, string $token ): ?array {
	$s = tcc_score( $cantata_id );
	if ( ! $s ) {
		return null;
	}
	return [
		'url'    => add_query_arg( [ 'cantata' => $cantata_id, 'token' => $token, 'v' => substr( md5( $s['file'] ), 0, 8 ) ], rest_url( 'tcc/v1/score' ) ),
		'pages'  => (int) $s['pages'],
		'offset' => (int) $s['offset'],
		'songs'  => (object) array_map( fn( $r ) => [ (int) $r[0], (int) $r[1] ], tcc_score_songs( $cantata_id ) ),
		'turns'  => (object) array_map( fn( $t ) => array_values( array_map( 'floatval', (array) $t ) ), tcc_turns( $cantata_id ) ),
		'lib'    => TCC_URL . 'assets/vendor/pdfjs/pdf.min.js',
		'worker' => TCC_URL . 'assets/vendor/pdfjs/pdf.worker.min.js',
	];
}

/** Page count without loading the whole PDF into a parser: count page objects. Good enough for scans; the admin page double-checks with pdf.js. */
function tcc_pdf_page_count( string $path ): int {
	$data = (string) file_get_contents( $path );
	if ( preg_match_all( '/\/Type\s*\/Page(?![s\w])/', $data, $m ) ) {
		return count( $m[0] );
	}
	if ( preg_match_all( '/\/Count\s+(\d+)/', $data, $m ) ) {
		return max( array_map( 'intval', $m[1] ) );
	}
	return 0;
}

/* ---------- REST: the file, and the page turns ---------- */

add_action( 'rest_api_init', function () {
	register_rest_route( 'tcc/v1', '/score', [ 'methods' => 'GET', 'permission_callback' => fn( WP_REST_Request $r ) => (bool) tcc_rest_cantata( $r ), 'callback' => function ( WP_REST_Request $r ) {
		$cantata = tcc_rest_cantata( $r );
		$s = tcc_score( $cantata );
		if ( ! $s ) {
			return new WP_Error( 'none', 'No score', [ 'status' => 404 ] );
		}
		$path = tcc_score_dir() . '/' . basename( $s['file'] );
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		header( 'Content-Type: application/pdf' );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'Content-Disposition: inline; filename="score.pdf"' );
		header( 'Cache-Control: private, max-age=604800' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		readfile( $path );
		exit;
	} ] );

	register_rest_route( 'tcc/v1', '/turns', [ 'methods' => 'POST', 'permission_callback' => fn() => current_user_can( 'edit_posts' ), 'callback' => function ( WP_REST_Request $r ) {
		$cantata = (int) $r->get_param( 'cantata' );
		$song    = (int) $r->get_param( 'song' );
		if ( ! $cantata || get_post_type( $cantata ) !== 'post' || $song < 0 ) {
			return new WP_Error( 'bad', 'Missing cantata or song', [ 'status' => 400 ] );
		}
		$times = array_values( array_unique( array_map( fn( $t ) => round( max( 0, (float) $t ), 1 ), (array) $r->get_param( 'times' ) ) ) );
		sort( $times );
		$all = tcc_turns( $cantata );
		if ( $times ) {
			$all[ $song ] = $times;
		} else {
			unset( $all[ $song ] );
		}
		update_post_meta( $cantata, '_tcc_turns', $all );
		return [ 'ok' => true, 'times' => $times ];
	} ] );
} );

/* ---------- the cantata post: Score box ---------- */

add_action( 'add_meta_boxes_post', function ( WP_Post $post ) {
	if ( has_category( TCC_CATEGORY, $post ) || $post->post_status === 'auto-draft' ) {
		add_meta_box( 'tcc_score', 'Score (sheet music)', 'tcc_render_score_box', 'post', 'normal', 'default' );
	}
} );

function tcc_render_score_box( WP_Post $post ) {
	$s     = tcc_score( $post->ID );
	$songs = tcc_song_titles( $post->ID );
	$map   = tcc_score_songs( $post->ID );
	$turns = tcc_turns( $post->ID );
	$max   = wp_max_upload_size();
	$data  = [
		'post'   => $post->ID,
		'nonce'  => wp_create_nonce( 'tcc_score_' . $post->ID ),
		'ajax'   => admin_url( 'admin-ajax.php' ),
		'score'  => $s ? [ 'name' => $s['name'], 'size' => (int) $s['size'], 'pages' => (int) $s['pages'], 'offset' => (int) $s['offset'], 'url' => tcc_score_data( $post->ID, tcc_practice_token( $post->ID ) )['url'] ] : null,
		'songs'  => array_values( $songs ),
		'map'    => (object) $map,
		'turns'  => (object) array_map( 'count', $turns ),
		'max'    => $max,
		'lib'    => TCC_URL . 'assets/vendor/pdfjs/pdf.min.js',
		'worker' => TCC_URL . 'assets/vendor/pdfjs/pdf.worker.min.js',
	];
	echo '<div id="tcc-score-box" data-tcc-score="' . esc_attr( wp_json_encode( $data ) ) . '"><p class="description">Loading…</p></div>';
	echo '<p class="description" style="margin-top:12px">One PDF of the whole work. It is kept out of the media library and only shown inside the Backstage practice app. Page turns are set in the app: open a song, switch to <b>Music</b>, and tap <b>Set page turns</b>. Upload limit on this server: ' . esc_html( size_format( $max ) ) . '. Only upload a score you are licensed to share digitally.</p>';
}

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( in_array( $hook, [ 'post.php', 'post-new.php' ], true ) && get_post() && get_post()->post_type === 'post' ) {
		wp_enqueue_script( 'tcc-score-admin', TCC_URL . 'assets/score-admin.js', [], TCC_VERSION, true );
	}
} );

/** Upload (or replace) the score. */
add_action( 'wp_ajax_tcc_score_upload', function () {
	$post_id = (int) ( $_POST['post'] ?? 0 );
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) || ! wp_verify_nonce( $_POST['nonce'] ?? '', 'tcc_score_' . $post_id ) ) {
		wp_send_json_error( 'Not allowed' );
	}
	$f = $_FILES['file'] ?? null;
	if ( ! $f || ! empty( $f['error'] ) || ! is_uploaded_file( $f['tmp_name'] ) ) {
		wp_send_json_error( 'The upload did not arrive' . ( ! empty( $f['error'] ) ? ' (error ' . (int) $f['error'] . ')' : '' ) . '. It may be larger than the server allows.' );
	}
	$head = (string) file_get_contents( $f['tmp_name'], false, null, 0, 5 );
	if ( $head !== '%PDF-' ) {
		wp_send_json_error( 'That file is not a PDF.' );
	}
	$dir  = tcc_score_dir();
	$name = bin2hex( random_bytes( 16 ) ) . '.pdf';
	if ( ! move_uploaded_file( $f['tmp_name'], $dir . '/' . $name ) ) {
		wp_send_json_error( 'Could not store the file.' );
	}
	@chmod( $dir . '/' . $name, 0640 );
	$old = tcc_score( $post_id );
	if ( $old ) {
		@unlink( $dir . '/' . basename( $old['file'] ) ); // replaced, not kept
	}
	$score = [ 'file' => $name, 'name' => sanitize_file_name( $f['name'] ), 'size' => (int) $f['size'], 'pages' => tcc_pdf_page_count( $dir . '/' . $name ), 'offset' => $old ? (int) $old['offset'] : 0 ];
	update_post_meta( $post_id, '_tcc_score', $score );
	wp_send_json_success( [ 'name' => $score['name'], 'size' => $score['size'], 'pages' => $score['pages'], 'offset' => $score['offset'], 'url' => tcc_score_data( $post_id, tcc_practice_token( $post_id ) )['url'] ] );
} );

/** Save the page numbering and where each song sits. */
add_action( 'wp_ajax_tcc_score_save', function () {
	$post_id = (int) ( $_POST['post'] ?? 0 );
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) || ! wp_verify_nonce( $_POST['nonce'] ?? '', 'tcc_score_' . $post_id ) ) {
		wp_send_json_error( 'Not allowed' );
	}
	$s = tcc_score( $post_id );
	if ( ! $s ) {
		wp_send_json_error( 'Upload the score first.' );
	}
	$pages = max( 1, (int) ( $_POST['pages'] ?? $s['pages'] ) );
	$s['pages']  = $pages;
	$s['offset'] = (int) ( $_POST['offset'] ?? 0 );
	update_post_meta( $post_id, '_tcc_score', $s );
	$map = [];
	foreach ( (array) json_decode( wp_unslash( (string) ( $_POST['map'] ?? '{}' ) ), true ) as $song => $r ) {
		$a = max( 1, min( $pages, (int) ( $r[0] ?? 0 ) ) );
		$b = max( $a, min( $pages, (int) ( $r[1] ?? $a ) ) );
		if ( (int) ( $r[0] ?? 0 ) > 0 ) {
			$map[ (int) $song ] = [ $a, $b ];
		}
	}
	update_post_meta( $post_id, '_tcc_score_songs', $map );
	wp_send_json_success( [ 'map' => (object) $map, 'pages' => $pages, 'offset' => $s['offset'] ] );
} );

/** Remove the score file. Where songs sit and the page turns stay, for a replacement scan of the same edition. */
add_action( 'wp_ajax_tcc_score_remove', function () {
	$post_id = (int) ( $_POST['post'] ?? 0 );
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) || ! wp_verify_nonce( $_POST['nonce'] ?? '', 'tcc_score_' . $post_id ) ) {
		wp_send_json_error( 'Not allowed' );
	}
	$s = tcc_score( $post_id );
	if ( $s ) {
		@unlink( tcc_score_dir() . '/' . basename( $s['file'] ) );
	}
	delete_post_meta( $post_id, '_tcc_score' );
	wp_send_json_success();
} );
