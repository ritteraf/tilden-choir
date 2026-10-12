<?php
/**
 * Plugin Name: Cue Playlist Export (for the Tilden choir site)
 * Description: Adds Tools → Export Cue playlists, which downloads every Cue playlist (titles, track titles, audio URLs, order) as one JSON file for importing on the choir site. Changes nothing here. Deactivate and delete when done.
 * Version:     1.0.1
 * Author:      Tilden Community Choir
 * License:     GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
	add_management_page( 'Export Cue playlists', 'Export Cue playlists', 'manage_options', 'tcc-cue-export', 'tcc_cue_export_page' );
} );

/** Every playlist with its tracks, read straight from Cue's post meta. */
function tcc_cue_export_collect(): array {
	$posts = get_posts( [ 'post_type' => 'cue_playlist', 'post_status' => 'any', 'posts_per_page' => -1, 'orderby' => 'date', 'order' => 'ASC' ] );
	$out   = [];
	foreach ( $posts as $p ) {
		$tracks = get_post_meta( $p->ID, 'tracks', true );
		if ( ! is_array( $tracks ) ) {
			$tracks = get_post_meta( $p->ID, '_cue_tracks', true );
		}
		if ( ! is_array( $tracks ) && function_exists( 'get_cue_playlist_tracks' ) ) {
			$tracks = get_cue_playlist_tracks( $p, 'edit' );
		}
		$list = [];
		foreach ( (array) $tracks as $i => $t ) {
			$t = (array) $t;
			$audio_id  = (int) ( $t['audioId'] ?? 0 );
			$audio_url = (string) ( $t['audioUrl'] ?? '' );
			if ( $audio_id && ! $audio_url ) {
				$audio_url = (string) wp_get_attachment_url( $audio_id );
			}
			$file = $audio_id ? get_attached_file( $audio_id ) : '';
			$list[] = [
				'order'      => (int) ( $t['order'] ?? $i ),
				'title'      => (string) ( $t['title'] ?? '' ),
				'artist'     => (string) ( $t['artist'] ?? '' ),
				'length'     => (string) ( $t['length'] ?? '' ),
				'format'     => (string) ( $t['format'] ?? '' ),
				'audioId'    => $audio_id,
				'audioUrl'   => $audio_url,
				'fileName'   => $file ? basename( $file ) : basename( (string) wp_parse_url( $audio_url, PHP_URL_PATH ) ),
				'fileSize'   => $file && file_exists( $file ) ? filesize( $file ) : null,
				'mime'       => $audio_id ? get_post_mime_type( $audio_id ) : '',
				'artworkUrl' => (string) ( $t['artworkUrl'] ?? '' ),
			];
		}
		usort( $list, fn( $a, $b ) => $a['order'] <=> $b['order'] );
		$out[] = [
			'id'       => $p->ID,
			'title'    => $p->post_title,
			'status'   => $p->post_status,
			'date'     => $p->post_date,
			'modified' => $p->post_modified,
			'tracks'   => $list,
		];
	}
	return $out;
}

function tcc_cue_export_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$playlists = post_type_exists( 'cue_playlist' ) ? tcc_cue_export_collect() : null;
	$url = wp_nonce_url( admin_url( 'admin-post.php?action=tcc_cue_export' ), 'tcc_cue_export' );
	echo '<div class="wrap"><h1>Export Cue playlists</h1>';
	if ( $playlists === null ) {
		echo '<p>The Cue plugin isn\'t active here, so there are no playlists to export.</p></div>';
		return;
	}
	$n = array_sum( array_map( fn( $p ) => count( $p['tracks'] ), $playlists ) );
	echo '<p>' . count( $playlists ) . ' playlists, ' . $n . ' tracks. The file lists titles, order and the public URL of each audio file; the choir site then copies the files across itself. Nothing on this site is changed.</p>';
	echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '">Download cue-playlists.json</a></p>';
	echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>Playlist</th><th>Published</th><th>Tracks</th><th>First track</th></tr></thead><tbody>';
	foreach ( $playlists as $p ) {
		echo '<tr><td>' . esc_html( $p['title'] ) . ( $p['status'] !== 'publish' ? ' <em>(' . esc_html( $p['status'] ) . ')</em>' : '' ) . '</td><td>' . esc_html( mb_substr( $p['date'], 0, 10 ) ) . '</td><td>' . count( $p['tracks'] ) . '</td><td>' . esc_html( $p['tracks'][0]['title'] ?? '—' ) . '</td></tr>';
	}
	echo '</tbody></table></div>';
}

add_action( 'admin_post_tcc_cue_export', function () {
	if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'tcc_cue_export' ) ) {
		wp_die( 'Not allowed.' );
	}
	$data = [
		'format'    => 'tcc-cue-export/1',
		'site'      => home_url(),
		'exported'  => current_time( 'c' ),
		'playlists' => tcc_cue_export_collect(),
	];
	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="cue-playlists.json"' );
	echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	exit;
} );
