<?php
defined( 'ABSPATH' ) || exit;

/**
 * The fields stored on each cantata post. Key => [label, type, description].
 * Every one is a plain post-meta entry prefixed with _tcc_.
 */
function tcc_fields(): array {
	return [
		'performance' => [ 'Performance date and time', 'datetime', 'Drives the season state, the countdown, and the order of the stack.' ],
		'rehearsals'  => [ 'Rehearsals start', 'date', 'Optional. Before this date the entry shows the recruiting message.' ],
		'venue'       => [ 'Venue', 'text', 'Leave blank to use the default venue from Settings → Cantata.' ],
		'composers'   => [ 'Composers', 'text', 'As you want them shown, e.g. "Joseph M. Martin and Heather Sorenson".' ],
		'subtitle'    => [ 'Subtitle or kind', 'text', 'Shown before the composers, e.g. "A cantata by" or "A Celtic Christmas celebration by".' ],
		'publisher'   => [ 'Publisher', 'text', '' ],
		'publisher_url' => [ 'Publisher link', 'url', 'Link to the work on the publisher\'s site.' ],
		'runtime'     => [ 'Run time', 'text', 'e.g. "56 minutes" or "About an hour".' ],
		'songs'       => [ 'Songs', 'songlist', 'In performance order. Add solos, duets and ensembles to a song and pick who sings them from Cantata → People.' ],
		'performers'  => [ 'Other featured performers', 'textarea', 'Soloists come from the song assignments above; narrators and actors from People. Use this only for anything else, one line each, e.g. "Piano: Tom Jones".' ],
		'invitation'  => [ 'Recruiting invitation', 'textarea', 'Shown in place of the excerpt while recruiting (before "Rehearsals start"). Leave blank to show the excerpt all season.' ],
		'youtube'     => [ 'YouTube URL', 'url', 'The scheduled live stream. After the concert the same link is the recording.' ],
		'listen'      => [ 'Listening video (YouTube)', 'url', 'The publisher\'s recording of the whole work, for singers to learn from. Shown on the singers page.' ],
		'cover'       => [ 'Cover art', 'image', 'The published cover of the work.' ],
		'program'     => [ 'Program PDF', 'file', 'Optional. Linked as "Program and song list".' ],
		'members'     => [ 'Backstage page', 'page', 'The password-protected members page for this cantata: singers, narrators, actors and tech crew. The header\'s Backstage button goes here.' ],
		'offering_to' => [ 'Offering supports', 'text', 'Shown on the upcoming entry only.' ],
		'offering_note' => [ 'Offering note', 'textarea', 'One or two sentences on who they are and why.' ],
		'tint'        => [ 'Tint', 'color', 'Filled automatically from the cover art. Set a hex value to override.' ],
		'override'    => [ 'Status override', 'select', 'Leave on Auto unless a date is wrong and you need the home page fixed now.' ],
	];
}

/**
 * Every sentence the plugin writes on the page, with its default. Editable under Settings → Cantata → Wording.
 * {venue}, {runtime}, {date}, {rehearsals} are filled in where they make sense.
 */
function tcc_wording(): array {
	// key => [ label, default, group ]
	return [
		// Recruiting: performance in the future, today before "Rehearsals start"
		'recruiting_label' => [ 'Label ("{year}", "{rehearsals}")', '{year} – Rehearsals begin {rehearsals}!', 'Recruiting (before rehearsals start)' ],
		'recruit_lede'     => [ 'Pitch, after "Rehearsals start {rehearsals}."', 'The only requirement is that you love to sing. Practice tracks let you learn at home, and only two rehearsals are required.', 'Recruiting (before rehearsals start)' ],
		'label_sing'       => [ '"Sing with us" column heading', 'Sing with us', 'Recruiting (before rehearsals start)' ],
		'recruit_column'   => [ '"Sing with us" column, second line', 'All ages and skill levels, no audition.', 'Recruiting (before rehearsals start)' ],
		'recruit_link'     => [ '"Sing with us" column, link text', 'How to join', 'Recruiting (before rehearsals start)' ],
		'join_button'      => [ 'Main button', 'Join the choir', 'Recruiting (before rehearsals start)' ],
		// Upcoming: performance in the future, rehearsals under way
		'this_christmas'   => [ 'Label ("{year}")', 'This Christmas', 'Upcoming (rehearsals started)' ],
		'label_how'        => [ '"How to watch" column heading', 'How to watch', 'Upcoming (rehearsals started)' ],
		'watch_big'        => [ '"How to watch" first line', 'In person or live online', 'Upcoming (rehearsals started)' ],
		'watch_line'       => [ '"How to watch" second line (the YouTube link follows it)', 'Free, no ticket.', 'Upcoming (rehearsals started)' ],
		'countdown'        => [ 'After the number of days', 'until the concert. In person or streamed live.', 'Upcoming (rehearsals started)' ],
		'watch_live_prefix'=> [ 'Main button ("{date}" becomes e.g. December 18th)', 'Watch Live – {date}', 'Upcoming (rehearsals started)' ],
		'roster_label_current' => [ 'Cast list: the expandable heading (also used while live)', 'Cast and Crew', 'Upcoming (rehearsals started)' ],
		// Live
		'live_now'         => [ 'Label', 'Live now', 'Live' ],
		'live_line'        => [ 'Sentence under the stream ("{venue}", "{runtime}")', 'Performing tonight at {venue}. {runtime}.', 'Live' ],
		// Just performed: within 30 days after
		'performed_label'  => [ 'Label ("{date}")', 'Performed {date}', 'Just performed' ],
		'performed_lede'   => [ 'Thank-you line', 'Thank you to everyone who came and watched. The full recording is ready.', 'Just performed' ],
		// Past
		'past_year'        => [ 'Label ("{year}")', 'Christmas {year}', 'Past years' ],
		'label_performed'  => [ '"Performed" heading', 'Performed', 'Past years' ],
		'label_featuring'  => [ '"Featuring" heading (names come from the song assignments)', 'Featuring', 'Past years' ],
		'featured_heading' => [ 'Heading over the program-style soloist entries', 'Solos and ensembles', 'Everywhere' ],
		'roster_label'     => [ 'Cast list: the expandable heading (also used just after the concert)', 'Cast and Crew', 'Past years' ],
		// Everywhere
		'label_when'       => [ '"When" column heading', 'When', 'Everywhere' ],
		'label_where'      => [ '"Where" column heading', 'Where', 'Everywhere' ],
		'watch_recording'  => [ 'Recording button', 'Watch the recording', 'Everywhere' ],
		'full_details'     => [ 'Open-a-year button', 'Full details', 'Everywhere' ],
		'program_link'     => [ 'Program link', 'Program and song list', 'Everywhere' ],
		'offering_lead'    => [ 'Offering: bold lead-in', 'Free-will offering.', 'Everywhere' ],
		'offering_intro'   => [ 'Offering: sentence before "This year’s offering supports …"', 'Each year the cantata takes up a collection for someone in our community.', 'Everywhere' ],

	];
}

/** Wording chosen on the Cantata stack block in the Site Editor, for this request. */
function tcc_wording_override( ?array $set = null ): array {
	static $override = [];
	if ( $set !== null ) {
		$override = $set;
	}
	return $override;
}

function tcc_text( string $key, array $vars = [] ): string {
	$defaults = tcc_wording();
	$saved    = tcc_wording_override() ?: get_option( 'tcc_wording', [] );
	$t = ( isset( $saved[ $key ] ) && $saved[ $key ] !== '' ) ? $saved[ $key ] : ( $defaults[ $key ][1] ?? '' );
	foreach ( $vars as $k => $v ) {
		$t = str_replace( '{' . $k . '}', $v, $t );
	}
	return $t;
}

function tcc_meta( int $post_id, string $key, $default = '' ) {
	$v = get_post_meta( $post_id, '_tcc_' . $key, true );
	return ( $v === '' || $v === null ) ? $default : $v;
}

function tcc_option( string $key, $default = '' ) {
	$opts = get_option( 'tcc_settings', [] );
	return ( isset( $opts[ $key ] ) && $opts[ $key ] !== '' ) ? $opts[ $key ] : $default;
}

/** Performance time as a DateTimeImmutable in the site timezone, or null. */
function tcc_performance_dt( int $post_id ): ?DateTimeImmutable {
	$raw = tcc_meta( $post_id, 'performance' );
	if ( ! $raw ) {
		return null;
	}
	try {
		return new DateTimeImmutable( $raw, wp_timezone() );
	} catch ( Exception $e ) {
		return null;
	}
}

function tcc_rehearsals_dt( int $post_id ): ?DateTimeImmutable {
	$raw = tcc_meta( $post_id, 'rehearsals' );
	if ( ! $raw ) {
		return null;
	}
	try {
		return new DateTimeImmutable( $raw, wp_timezone() );
	} catch ( Exception $e ) {
		return null;
	}
}

/** "December 18th" */
function tcc_date_ordinal( DateTimeInterface $dt ): string {
	return $dt->format( 'F jS' );
}

/** Year the cantata belongs to (performance year, falling back to the post date). */
function tcc_year( int $post_id ): string {
	$dt = tcc_performance_dt( $post_id );
	return $dt ? $dt->format( 'Y' ) : get_the_date( 'Y', $post_id );
}

/** YouTube video id from any of the usual URL shapes. */
function tcc_youtube_id( string $url ): string {
	if ( ! $url ) {
		return '';
	}
	if ( preg_match( '~(?:youtu\.be/|v=|/live/|/embed/|/shorts/)([A-Za-z0-9_-]{11})~', $url, $m ) ) {
		return $m[1];
	}
	return '';
}

function tcc_youtube_watch_url( string $url ): string {
	$id = tcc_youtube_id( $url );
	return $id ? 'https://www.youtube.com/watch?v=' . $id : $url;
}

/**
 * Dominant "tint" of an image: the most present saturated mid-tone, normalised to a
 * consistent darkness so every year's wash and button sit at the same weight.
 */
function tcc_extract_tint( int $attachment_id ): string {
	if ( ! function_exists( 'imagecreatefromstring' ) ) {
		return '';
	}
	$file = get_attached_file( $attachment_id );
	if ( ! $file || ! file_exists( $file ) ) {
		return '';
	}
	$src = @imagecreatefromstring( file_get_contents( $file ) );
	if ( ! $src ) {
		return '';
	}
	$w = 48;
	$h = 72;
	$img = imagecreatetruecolor( $w, $h );
	imagecopyresampled( $img, $src, 0, 0, 0, 0, $w, $h, imagesx( $src ), imagesy( $src ) );
	imagedestroy( $src );

	$buckets = [];
	for ( $y = 0; $y < $h; $y++ ) {
		for ( $x = 0; $x < $w; $x++ ) {
			$rgb = imagecolorat( $img, $x, $y );
			$r = ( $rgb >> 16 ) & 255;
			$g = ( $rgb >> 8 ) & 255;
			$b = $rgb & 255;
			// quantise to 32 steps so near-identical colours pool together
			$k = ( $r >> 5 ) . ',' . ( $g >> 5 ) . ',' . ( $b >> 5 );
			if ( ! isset( $buckets[ $k ] ) ) {
				$buckets[ $k ] = [ 0, 0, 0, 0 ];
			}
			$buckets[ $k ][0] += $r;
			$buckets[ $k ][1] += $g;
			$buckets[ $k ][2] += $b;
			$buckets[ $k ][3]++;
		}
	}
	imagedestroy( $img );

	$best = null;
	$best_score = -1;
	foreach ( $buckets as $bk ) {
		$n = $bk[3];
		$r = $bk[0] / $n;
		$g = $bk[1] / $n;
		$b = $bk[2] / $n;
		[ $hh, $ll, $ss ] = tcc_rgb_to_hls( $r, $g, $b );
		$score = $n * ( $ss + 0.15 ) * ( 1 - abs( $ll - 0.35 ) );
		if ( $score > $best_score ) {
			$best_score = $score;
			$best = [ $hh, $ll, $ss ];
		}
	}
	if ( ! $best ) {
		return '';
	}
	[ $r, $g, $b ] = tcc_hls_to_rgb( $best[0], 0.32, max( $best[2], 0.35 ) );
	return sprintf( '#%02x%02x%02x', (int) round( $r ), (int) round( $g ), (int) round( $b ) );
}

function tcc_rgb_to_hls( float $r, float $g, float $b ): array {
	$r /= 255; $g /= 255; $b /= 255;
	$max = max( $r, $g, $b ); $min = min( $r, $g, $b );
	$l = ( $max + $min ) / 2;
	if ( $max === $min ) {
		return [ 0.0, $l, 0.0 ];
	}
	$d = $max - $min;
	$s = $l > 0.5 ? $d / ( 2 - $max - $min ) : $d / ( $max + $min );
	if ( $max === $r ) {
		$h = ( $g - $b ) / $d + ( $g < $b ? 6 : 0 );
	} elseif ( $max === $g ) {
		$h = ( $b - $r ) / $d + 2;
	} else {
		$h = ( $r - $g ) / $d + 4;
	}
	return [ $h / 6, $l, $s ];
}

function tcc_hls_to_rgb( float $h, float $l, float $s ): array {
	if ( $s == 0 ) {
		$v = $l * 255;
		return [ $v, $v, $v ];
	}
	$q = $l < 0.5 ? $l * ( 1 + $s ) : $l + $s - $l * $s;
	$p = 2 * $l - $q;
	$f = function ( $t ) use ( $p, $q ) {
		if ( $t < 0 ) $t += 1;
		if ( $t > 1 ) $t -= 1;
		if ( $t < 1 / 6 ) return $p + ( $q - $p ) * 6 * $t;
		if ( $t < 1 / 2 ) return $q;
		if ( $t < 2 / 3 ) return $p + ( $q - $p ) * ( 2 / 3 - $t ) * 6;
		return $p;
	};
	return [ $f( $h + 1 / 3 ) * 255, $f( $h ) * 255, $f( $h - 1 / 3 ) * 255 ];
}

/** Hex -> "r,g,b" of the colour mixed 92% toward white (the pale wash / band background). */
function tcc_lighten_rgb( string $hex, float $amount = 0.92 ): string {
	$hex = ltrim( $hex, '#' );
	if ( strlen( $hex ) !== 6 ) {
		return '255,255,255';
	}
	$n = hexdec( $hex );
	$f = fn( $v ) => (int) round( $v + ( 255 - $v ) * $amount );
	return $f( ( $n >> 16 ) & 255 ) . ',' . $f( ( $n >> 8 ) & 255 ) . ',' . $f( $n & 255 );
}

/** Kinds of special part a song can have. */
function tcc_part_kinds(): array {
	return apply_filters( 'tcc_part_kinds', [ 'solo' => 'Solo', 'duet' => 'Duet', 'trio' => 'Trio', 'quartet' => 'Quartet', 'ensemble' => 'Small ensemble' ] );
}

/**
 * The cantata's songs as [ [ 'title' => …, 'parts' => [ [ 'kind', 'people' => [ids], 'note' ] ] ], … ].
 * Falls back to the older one-title-per-line text so nothing already entered is lost.
 */
function tcc_songlist( int $post_id ): array {
	$list = get_post_meta( $post_id, '_tcc_songlist', true );
	if ( is_array( $list ) && $list ) {
		return $list;
	}
	$text = (string) get_post_meta( $post_id, '_tcc_songs', true );
	$out  = [];
	foreach ( array_filter( array_map( 'trim', explode( "\n", $text ) ) ) as $t ) {
		$out[] = [ 'title' => $t, 'parts' => [] ];
	}
	return $out;
}

function tcc_song_titles( int $post_id ): array {
	return array_map( fn( $s ) => (string) $s['title'], tcc_songlist( $post_id ) );
}

function tcc_sanitize_songlist( $v ): array {
	$out = [];
	foreach ( (array) $v as $song ) {
		if ( ! is_array( $song ) ) {
			continue;
		}
		$title = sanitize_text_field( (string) ( $song['title'] ?? '' ) );
		if ( $title === '' ) {
			continue;
		}
		$parts = [];
		foreach ( (array) ( $song['parts'] ?? [] ) as $p ) {
			if ( ! is_array( $p ) ) {
				continue;
			}
			$kind   = array_key_exists( (string) ( $p['kind'] ?? '' ), tcc_part_kinds() ) ? $p['kind'] : 'solo';
			$people = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $p['people'] ?? [] ) ) ) ) );
			$note   = sanitize_text_field( (string) ( $p['note'] ?? '' ) );
			if ( ! $people && $note === '' ) {
				continue;
			}
			$parts[] = [ 'kind' => $kind, 'people' => $people, 'note' => $note ];
		}
		$out[] = [ 'title' => $title, 'parts' => $parts ];
	}
	return $out;
}

function tcc_sanitize_docs( $rows ): array {
	$out = [];
	foreach ( (array) $rows as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$title = sanitize_text_field( (string) ( $row['title'] ?? '' ) );
		$file  = absint( $row['file'] ?? 0 );
		$url   = esc_url_raw( trim( (string) ( $row['url'] ?? '' ) ) );
		if ( $title === '' || ( ! $file && ! $url ) ) {
			continue;
		}
		$who = array_values( array_intersect( array_map( fn( $k ) => preg_replace( '/[^a-z0-9:_-]/', '', strtolower( (string) $k ) ), (array) ( $row['who'] ?? [] ) ), array_keys( function_exists( 'tcc_doc_audiences' ) ? tcc_doc_audiences() : tcc_attendee_types() ) ) );
		$out[] = [ 'title' => $title, 'file' => $file, 'url' => $url, 'who' => $who, 'note' => sanitize_text_field( (string) ( $row['note'] ?? '' ) ) ];
	}
	return $out;
}

/** Image ids of the first core/gallery block in a post's content. */
function tcc_gallery_ids( int $post_id ): array {
	$post = get_post( $post_id );
	if ( ! $post || ! has_blocks( $post->post_content ) ) {
		return [];
	}
	$find = function ( array $blocks ) use ( &$find ) {
		foreach ( $blocks as $b ) {
			if ( $b['blockName'] === 'core/gallery' ) {
				$ids = [];
				foreach ( $b['innerBlocks'] as $img ) {
					if ( ! empty( $img['attrs']['id'] ) ) {
						$ids[] = (int) $img['attrs']['id'];
					}
				}
				if ( $ids ) {
					return $ids;
				}
			}
			if ( ! empty( $b['innerBlocks'] ) ) {
				$r = $find( $b['innerBlocks'] );
				if ( $r ) {
					return $r;
				}
			}
		}
		return [];
	};
	return $find( parse_blocks( $post->post_content ) );
}

/* ---------- the director ---------- */

/** A WordPress administrator is the director: no "who are you" on Backstage, conductor mode in the player. */
function tcc_is_director(): bool {
	return is_user_logged_in() && current_user_can( 'manage_options' );
}

/* the director already proved who they are by logging in: no page password for them */
add_filter( 'post_password_required', function ( $required ) {
	return $required && tcc_is_director() ? false : $required;
}, 10, 1 );

/* and they aren't one of the people: forget any "who's practicing" this device remembered for them */
add_action( 'template_redirect', function () {
	if ( isset( $_COOKIE['tcc_who'] ) && tcc_is_director() ) {
		setcookie( 'tcc_who', '', [ 'expires' => time() - 3600, 'path' => '/', 'secure' => is_ssl(), 'samesite' => 'Lax' ] );
		unset( $_COOKIE['tcc_who'] );
	}
} );

