<?php
defined( 'ABSPATH' ) || exit;

/* ---------- small building blocks ---------- */

function tcc_svg( string $name ): string {
	static $icons = [
		'star'  => '<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 1 L13.4 10.6 L23 12 L13.4 13.4 L12 23 L10.6 13.4 L1 12 L10.6 10.6 Z" fill="var(--tcc-gold)"/></svg>',
		'play'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 5v14l12-7z"/></svg>',
		'bell'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>',
		'chev'  => '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>',
		'note'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
		'file-doc'   => '<svg class="tcc-doc__icon" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 3h8l5 5v13H6z"/><path d="M14 3v5h5M9 13h7M9 17h7"/></svg>',
		'file-image' => '<svg class="tcc-doc__icon" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 17l-5-5-8 8"/></svg>',
		'file-play'  => '<svg class="tcc-doc__icon" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M10 8.5v7l6-3.5z" fill="currentColor"/></svg>',
		'file-link'  => '<svg class="tcc-doc__icon" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/></svg>',
		'mail'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><polyline points="3 7 12 13 21 7"/></svg>',
		'back'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>',
		'then'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 10 20 15 15 20"/><path d="M4 4v7a4 4 0 0 0 4 4h12"/></svg>',
		'gift'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 12v10H4V12"/><path d="M2 7h20v5H2z"/><path d="M12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>',
	];
	return $icons[ $name ] ?? '';
}

function tcc_emblem_url(): string {
	$id = (int) tcc_option( 'emblem' );
	if ( $id ) {
		return (string) wp_get_attachment_image_url( $id, 'large' );
	}
	$file = get_theme_file_path( 'assets/images/emblem.png' );
	return file_exists( $file ) ? get_theme_file_uri( 'assets/images/emblem.png' ) : '';
}

function tcc_directions_url(): string {
	$u = tcc_option( 'directions_url' );
	if ( $u ) {
		return $u;
	}
	$addr = tcc_option( 'address' );
	return $addr ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( tcc_option( 'venue' ) . ' ' . $addr ) : '';
}

/** Everything a template needs about one cantata, computed once. */
function tcc_entry_data( int $post_id, ?int $current_id = null ): array {
	$state   = tcc_state( $post_id, $current_id );
	$current = $state !== 'past';
	$perf    = tcc_performance_dt( $post_id );
	$year    = tcc_year( $post_id );
	$tint    = tcc_meta( $post_id, 'tint', '#35446e' );
	$yt      = tcc_meta( $post_id, 'youtube' );
	$ytid    = tcc_youtube_id( $yt );
	$perf_long = $perf ? $perf->format( 'l, F j, Y' ) : '';
	$perf_ord  = $perf ? tcc_date_ordinal( $perf ) : '';
	$reh       = tcc_rehearsals_dt( $post_id );

	switch ( $state ) {
		case 'recruiting':
			$tag   = tcc_text( 'recruiting_label', [ 'year' => $year, 'rehearsals' => $reh ? tcc_date_ordinal( $reh ) : 'soon' ] );
			$watch = $perf_ord ? tcc_text( 'watch_live_prefix', [ 'date' => $perf_ord ] ) : 'Watch live';
			break;
		case 'upcoming':
			$tag   = tcc_text( 'this_christmas', [ 'year' => $year ] );
			$watch = $perf_ord ? tcc_text( 'watch_live_prefix', [ 'date' => $perf_ord ] ) : 'Watch live';
			break;
		case 'live':
			$tag   = tcc_text( 'live_now' );
			$watch = 'Watch live';
			break;
		case 'performed':
			$tag   = tcc_text( 'performed_label', [ 'date' => $perf ? $perf->format( 'F j' ) : '' ] );
			$watch = tcc_text( 'watch_recording' );
			break;
		default:
			$tag   = tcc_text( 'past_year', [ 'year' => $year ] );
			$watch = tcc_text( 'watch_recording' );
	}
	$band_label = $tag;
	if ( $state === 'upcoming' && $perf ) {
		$band_label = tcc_text( 'this_christmas', [ 'year' => $year ] ) . ' · ' . $perf->format( 'l, F j' );
	}

	return [
		'id'         => $post_id,
		'state'      => $state,
		'current'    => $current,
		'year'       => $year,
		'title'      => get_the_title( $post_id ),
		'permalink'  => get_permalink( $post_id ),
		'subtitle'   => tcc_meta( $post_id, 'subtitle', 'A cantata by' ),
		'composers'  => tcc_meta( $post_id, 'composers' ),
		'summary'    => ( $state === 'recruiting' && tcc_meta( $post_id, 'invitation' ) ) ? tcc_meta( $post_id, 'invitation' ) : get_the_excerpt( $post_id ),
		'venue'      => tcc_meta( $post_id, 'venue', tcc_option( 'venue' ) ),
		'address'    => tcc_option( 'address' ),
		'runtime'    => tcc_meta( $post_id, 'runtime', 'About an hour' ),
		'performers' => array_filter( array_map( 'trim', explode( "\n", tcc_meta( $post_id, 'performers' ) ) ) ),
		'songs'      => tcc_song_titles( $post_id ),
		'songlist'   => tcc_songlist( $post_id ),
		'publisher'  => tcc_meta( $post_id, 'publisher' ),
		'publisher_url' => tcc_meta( $post_id, 'publisher_url' ),
		'program'    => (int) tcc_meta( $post_id, 'program' ) ? wp_get_attachment_url( (int) tcc_meta( $post_id, 'program' ) ) : '',
		'offering_to'   => tcc_meta( $post_id, 'offering_to' ),
		'offering_note' => tcc_meta( $post_id, 'offering_note' ),
		'cover'      => (int) tcc_meta( $post_id, 'cover' ),
		'photo'      => (int) get_post_thumbnail_id( $post_id ),
		'gallery'    => tcc_gallery_ids( $post_id ),
		'tint'       => $tint,
		'wash'       => tcc_lighten_rgb( $tint ),
		'youtube'    => $yt,
		'ytid'       => $ytid,
		'watch_url'  => $yt ? tcc_youtube_watch_url( $yt ) : '',
		'perf'       => $perf,
		'perf_long'  => $perf_long,
		'perf_time'  => $perf ? $perf->format( 'g:i A' ) : '',
		'days'       => tcc_days_until( $post_id ),
		'tag'        => $tag,
		'band_label' => $band_label,
		'watch_label'=> $watch,
		'calendar'   => tcc_calendar_url( $post_id ),
		'directions' => tcc_directions_url(),
		'join_url'   => tcc_option( 'join_url', home_url( '/join/' ) ),
		'reh'        => $reh,
	];
}

/* ---------- pieces ---------- */

/** The entry's main button. While recruiting it is "Join the choir"; the rest of the year it is the stream or recording. */
function tcc_primary_link( array $e, string $class ): string {
	if ( $e['state'] === 'recruiting' ) {
		return sprintf( '<a class="%s" href="%s">%s<span>%s</span></a>', esc_attr( $class ), esc_url( $e['join_url'] ), tcc_svg( 'note' ), esc_html( tcc_text( 'join_button' ) ) );
	}
	return tcc_watch_link( $e, $class );
}

function tcc_watch_link( array $e, string $class, bool $band = false ): string {
	if ( ! $e['watch_url'] ) {
		return '';
	}
	$inline = in_array( $e['state'], [ 'performed', 'past' ], true ) && $e['ytid'];
	$icon   = in_array( $e['state'], [ 'recruiting', 'upcoming' ], true ) ? 'bell' : 'play';
	$attrs  = $inline ? ' data-tcc-play="' . esc_attr( $e['ytid'] ) . '" data-tcc-entry="' . esc_attr( $e['year'] ) . '"' : ' target="_blank" rel="noopener"';
	return sprintf( '<a class="%s" href="%s"%s>%s<span>%s</span></a>', esc_attr( $class ), esc_url( $e['watch_url'] ), $attrs, tcc_svg( $icon ), esc_html( $e['watch_label'] ) );
}

function tcc_cover_img( array $e, string $class, string $size = 'medium_large' ): string {
	if ( ! $e['cover'] ) {
		return '';
	}
	return wp_get_attachment_image( $e['cover'], $size, false, [ 'class' => $class, 'alt' => 'Cover of ' . $e['title'] ] );
}

function tcc_hero( array $e ): string {
	if ( ! $e['photo'] ) {
		return '';
	}
	$img  = wp_get_attachment_image( $e['photo'], 'full', false, [ 'class' => 'tcc-hero__img', 'loading' => 'lazy' ] );
	$play = '';
	if ( in_array( $e['state'], [ 'performed', 'past' ], true ) && $e['ytid'] ) {
		$play = sprintf( '<a class="tcc-hero__play" href="%s" data-tcc-play="%s" data-tcc-entry="%s"><span class="tcc-hero__dot">%s</span>%s</a>',
			esc_url( $e['watch_url'] ), esc_attr( $e['ytid'] ), esc_attr( $e['year'] ), tcc_svg( 'play' ), esc_html( $e['watch_label'] ) );
	}
	return '<figure class="tcc-hero" data-tcc-player>' . $img . $play . '</figure>';
}

function tcc_meta_block( array $e ): string {
	if ( $e['current'] ) {
		$where = '<span class="tcc-meta__big">' . esc_html( $e['venue'] ) . '</span>'
			. '<span>' . esc_html( $e['address'] ) . ( $e['directions'] ? ' <a href="' . esc_url( $e['directions'] ) . '" target="_blank" rel="noopener">Directions</a>' : '' ) . '</span>';
		$when = '<span class="tcc-meta__big">' . esc_html( $e['perf_long'] ?: 'Date to be announced' ) . '</span>'
			. ( $e['perf_time'] ? '<span>' . esc_html( $e['perf_time'] ) . '. ' . esc_html( $e['runtime'] ) . '.</span>' : '' );
		$how = '<span class="tcc-meta__big">' . esc_html( tcc_text( 'watch_big' ) ) . '</span>'
			. '<span>' . esc_html( tcc_text( 'watch_line' ) ) . ( $e['watch_url'] ? ' Streamed live on <a href="' . esc_url( $e['watch_url'] ) . '" target="_blank" rel="noopener">YouTube</a>.' : '' ) . '</span>';
		$third = '<div><span class="tcc-meta__k">' . esc_html( tcc_text( 'label_how' ) ) . '</span>' . $how . '</div>';
		if ( $e['state'] === 'recruiting' ) {
			$third = '<div><span class="tcc-meta__k">' . esc_html( tcc_text( 'label_sing' ) ) . '</span>'
				. '<span class="tcc-meta__big">Rehearsals begin ' . esc_html( $e['reh'] ? $e['reh']->format( 'F j' ) : 'soon' ) . '</span>'
				. '<span>' . esc_html( tcc_text( 'recruit_column' ) ) . ' <a href="' . esc_url( $e['join_url'] ) . '">' . esc_html( tcc_text( 'recruit_link' ) ) . '</a></span></div>';
		}
		return '<div class="tcc-meta tcc-meta--event">'
			. '<div><span class="tcc-meta__k">' . esc_html( tcc_text( 'label_when' ) ) . '</span>' . $when . '</div>'
			. '<div><span class="tcc-meta__k">' . esc_html( tcc_text( 'label_where' ) ) . '</span>' . $where . '</div>'
			. $third
			. '</div>';
	}
	$perf = '<span>' . esc_html( $e['perf'] ? $e['perf']->format( 'F j, Y' ) : 'December ' . $e['year'] ) . '</span><span>' . esc_html( $e['venue'] ) . '</span>';
	$out  = '<div class="tcc-meta tcc-meta--past"><div><span class="tcc-meta__k">' . esc_html( tcc_text( 'label_performed' ) ) . '</span>' . $perf . '</div>';
	// "Featuring": read from the song assignments, then anything typed in "Other featured performers" (narration etc.).
	$featuring = '';
	if ( function_exists( 'tcc_featured_lines' ) ) {
		foreach ( tcc_featured_lines( $e['songlist'], $e['id'] ) as [ $label, $names ] ) {
			$featuring .= '<span><span class="tcc-meta__sub">' . esc_html( $label ) . '</span> ' . esc_html( implode( ', ', $names ) ) . '</span>';
		}
	}
	$featuring .= implode( '', array_map( fn( $l ) => '<span>' . esc_html( $l ) . '</span>', $e['performers'] ) );
	if ( $featuring ) {
		$out .= '<div><span class="tcc-meta__k">' . esc_html( tcc_text( 'label_featuring' ) ) . '</span>' . $featuring . '</div>';
	}
	if ( $e['runtime'] ) {
		$out .= '<div><span class="tcc-meta__k">Run time</span><span>' . esc_html( $e['runtime'] ) . '</span></div>';
	}
	return $out . '</div>';
}

function tcc_actions( array $e, string $context ): string {
	$out = '';
	$empty = '<div class="tcc-actions"></div>';
	switch ( $e['state'] ) {
		case 'recruiting':
			$out .= '<p class="tcc-lede"><strong>Rehearsals start ' . esc_html( $e['reh'] ? $e['reh']->format( 'F j' ) : 'soon' ) . '.</strong> ' . esc_html( tcc_text( 'recruit_lede', [ 'rehearsals' => $e['reh'] ? $e['reh']->format( 'F j' ) : 'soon' ] ) ) . '</p>';
			$out .= '<div class="tcc-actions">' . tcc_primary_link( $e, 'tcc-btn tcc-btn--primary' )
				. ( $e['calendar'] ? tcc_action_link( $e['calendar'], 'Add to calendar', 'tcc-btn tcc-btn--outline', true ) : '' )
				. tcc_watch_link( $e, 'tcc-link' ) . '</div>';
			break;
		case 'upcoming':
			if ( $e['days'] !== null ) {
				$out .= '<div class="tcc-count"><span class="tcc-count__n">' . (int) $e['days'] . '</span><span>' . ( $e['days'] === 1 ? 'day ' : 'days ' ) . esc_html( tcc_text( 'countdown' ) ) . '</span></div>';
			}
			$out .= '<div class="tcc-actions">' . tcc_watch_link( $e, 'tcc-btn tcc-btn--primary' )
				. ( $e['calendar'] ? tcc_action_link( $e['calendar'], 'Add to calendar', 'tcc-btn tcc-btn--outline', true ) : '' ) . '</div>';
			break;
		case 'live':
			break;
		case 'performed':
			$out .= '<p class="tcc-lede">' . esc_html( tcc_text( 'performed_lede' ) ) . '</p>';
			$out .= '<div class="tcc-actions">' . tcc_watch_link( $e, 'tcc-btn tcc-btn--primary' )
				. ( $e['gallery'] ? tcc_action_link( $e['permalink'] . '#photos', 'See the photos', 'tcc-btn tcc-btn--outline' ) : '' ) . '</div>';
			break;
		default:
			$out .= '<div class="tcc-actions">' . tcc_watch_link( $e, 'tcc-btn tcc-btn--dark' )
				. ( $e['program'] ? tcc_action_link( $e['program'], tcc_text( 'program_link' ), 'tcc-link', true ) : ( $e['songs'] && $context !== 'single' ? tcc_action_link( $e['permalink'], tcc_text( 'program_link' ), 'tcc-link' ) : '' ) )
				. ( $e['gallery'] && $context !== 'single' ? tcc_action_link( $e['permalink'] . '#photos', 'Photos', 'tcc-link' ) : '' ) . '</div>';
	}
	return str_replace( $empty, '', $out );
}

function tcc_action_link( string $url, string $label, string $class, bool $blank = false ): string {
	if ( ! $url ) {
		return '';
	}
	return sprintf( '<a class="%s" href="%s"%s>%s</a>', esc_attr( $class ), esc_url( $url ), $blank ? ' target="_blank" rel="noopener"' : '', esc_html( $label ) );
}

function tcc_offering( array $e ): string {
	if ( ! $e['current'] || ! $e['offering_to'] ) {
		return '';
	}
	return '<div class="tcc-offering">' . tcc_svg( 'gift' ) . '<p><strong>' . esc_html( tcc_text( 'offering_lead' ) ) . '</strong> ' . esc_html( tcc_text( 'offering_intro' ) ) . ' This year’s offering supports ' . esc_html( $e['offering_to'] ) . '.' . ( $e['offering_note'] ? ' ' . esc_html( $e['offering_note'] ) : '' ) . '</p></div>';
}

function tcc_gallery_strip( array $e ): string {
	if ( ! $e['gallery'] ) {
		return '';
	}
	$out  = '<div class="tcc-gallery">';
	$show = array_slice( $e['gallery'], 0, 3 );
	foreach ( $show as $id ) {
		$out .= '<a href="' . esc_url( $e['permalink'] . '#photos' ) . '">' . wp_get_attachment_image( $id, 'medium', false, [ 'loading' => 'lazy' ] ) . '</a>';
	}
	$more = count( $e['gallery'] ) - count( $show );
	if ( $more > 0 ) {
		$out .= '<a class="tcc-gallery__more" href="' . esc_url( $e['permalink'] . '#photos' ) . '">+' . $more . ' more</a>';
	}
	return $out . '</div>';
}

/** The featured singers, laid out like the soloist page of a program: portrait, name, what they sing, a line about them. */
function tcc_featured_parts( array $e ): string {
	if ( ! function_exists( 'tcc_featured_people' ) ) {
		return '';
	}
	$people = tcc_featured_people( $e['songlist'] );
	if ( ! $people ) {
		return '';
	}
	$out = '';
	foreach ( $people as $id => $p ) {
		$out .= tcc_program_entry( $id, $p );
	}
	return '<div class="tcc-parts"><h3 class="tcc-parts__h">' . esc_html( tcc_text( 'featured_heading' ) ) . '</h3><div class="tcc-parts__grid">' . $out . '</div></div>';
}

/* ---------- the expanded entry ---------- */

function tcc_render_full( array $e, string $context = 'stack' ): string {
	if ( $e['state'] === 'live' ) {
		$player = $e['ytid']
			? '<iframe class="tcc-live__frame" src="https://www.youtube-nocookie.com/embed/' . esc_attr( $e['ytid'] ) . '?rel=0" title="' . esc_attr( $e['title'] ) . ' live stream" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen loading="lazy"></iframe>'
			: '<div class="tcc-live__frame tcc-live__frame--empty">Live stream</div>';
		return '<section class="tcc-full tcc-full--live"><div class="tcc-live__player">' . $player . '</div>'
			. '<div class="tcc-live__text"><span class="tcc-live__tag"><span class="tcc-live__dot"></span>Live now</span>'
			. '<h2 class="tcc-title">' . esc_html( $e['title'] ) . '</h2>'
			. '<p class="tcc-composer">' . esc_html( $e['composers'] ) . '</p>'
			. '<p>' . esc_html( tcc_text( 'live_line', [ 'venue' => $e['venue'], 'runtime' => $e['runtime'] ] ) ) . '</p>'
			. ( $e['watch_url'] ? tcc_action_link( $e['watch_url'], 'Open on YouTube', 'tcc-btn tcc-btn--light', true ) : '' )
			. '</div></section>';
	}

	$emblem = tcc_emblem_url();
	$title  = $context === 'single' ? '<h1 class="tcc-title">' . esc_html( $e['title'] ) . '</h1>' : '<h2 class="tcc-title"><a href="' . esc_url( $e['permalink'] ) . '">' . esc_html( $e['title'] ) . '</a></h2>';

	$out  = '<section class="tcc-full' . ( $e['photo'] ? ' has-photo' : ' no-photo' ) . '">';
	if ( ! $e['photo'] && $emblem ) {
		$out .= '<img class="tcc-emblem" src="' . esc_url( $emblem ) . '" alt="" aria-hidden="true">';
	}
	$out .= tcc_hero( $e );
	$out .= '<div class="tcc-full__row"><div class="tcc-full__text">';
	$out .= '<div class="tcc-tag' . ( $e['current'] ? ' tcc-tag--current' : '' ) . '">' . tcc_svg( 'star' ) . '<span>' . esc_html( $e['tag'] ) . '</span></div>';
	$out .= $title;
	if ( $e['composers'] ) {
		$out .= '<p class="tcc-composer">' . esc_html( trim( $e['subtitle'] . ' ' . $e['composers'] ) ) . '</p>';
	}
	if ( $e['summary'] ) {
		$out .= '<p class="tcc-summary">' . esc_html( $e['summary'] ) . '</p>';
	}
	$out .= tcc_meta_block( $e );
	$out .= tcc_offering( $e );
	$out .= tcc_actions( $e, $context );
	if ( $context === 'single' && $e['songlist'] ) {
		$kinds = tcc_part_kinds();
		$out .= '<div class="tcc-program"><h2>The program</h2><ol>';
		foreach ( $e['songlist'] as $song ) {
			$out .= '<li><span>' . esc_html( $song['title'] ) . '</span>';
			foreach ( (array) ( $song['parts'] ?? [] ) as $part ) {
				$chips = function_exists( 'tcc_person_chip' ) ? implode( '', array_map( 'tcc_person_chip', (array) $part['people'] ) ) : '';
				if ( $chips || ! empty( $part['note'] ) ) {
					$out .= '<div class="tcc-parts__part"><span class="tcc-parts__kind">' . esc_html( $kinds[ $part['kind'] ] ?? $part['kind'] ) . ( ! empty( $part['note'] ) ? ' <em>' . esc_html( $part['note'] ) . '</em>' : '' ) . '</span><span class="tcc-parts__people">' . $chips . '</span></div>';
				}
			}
			$out .= '</li>';
		}
		$out .= '</ol></div>';
	}
	$out .= tcc_featured_parts( $e );
	if ( function_exists( 'tcc_roster_section' ) ) {
		$out .= tcc_roster_section( $e['id'], $e['state'] );
	}
	if ( $context !== 'single' ) {
		$out .= tcc_gallery_strip( $e );
	}
	$out .= '</div>';
	if ( $e['cover'] ) {
		$out .= '<div class="tcc-full__cover' . ( $e['photo'] ? ' is-lifted' : '' ) . '">' . tcc_cover_img( $e, 'tcc-cover tcc-cover--large' );
		if ( $context === 'single' && $e['publisher'] ) {
			$out .= '<span class="tcc-publisher">' . ( $e['publisher_url'] ? '<a href="' . esc_url( $e['publisher_url'] ) . '" target="_blank" rel="noopener">' . esc_html( $e['publisher'] ) . '</a>' : esc_html( $e['publisher'] ) ) . '</span>';
		}
		$out .= '</div>';
	}
	$out .= '</div></section>';
	return $out;
}

/* ---------- the collapsed band ---------- */

function tcc_render_band( array $e ): string {
	$emblem = tcc_emblem_url();
	$out  = '<section class="tcc-band' . ( $e['photo'] ? ' has-photo' : ' no-photo' ) . '">';
	if ( $e['photo'] ) {
		$out .= wp_get_attachment_image( $e['photo'], 'large', false, [ 'class' => 'tcc-band__photo', 'loading' => 'lazy', 'alt' => '' ] );
	} elseif ( $emblem ) {
		$out .= '<img class="tcc-emblem tcc-emblem--band" src="' . esc_url( $emblem ) . '" alt="" aria-hidden="true">';
	}
	$out .= '<div class="tcc-band__wash"></div><div class="tcc-band__row">';
	$out .= '<div class="tcc-band__cover">' . tcc_cover_img( $e, 'tcc-cover tcc-cover--small', 'medium' ) . '</div>';
	$out .= '<div class="tcc-band__text">';
	$out .= $e['current']
		? '<span class="tcc-band__label tcc-band__label--current">' . tcc_svg( 'star' ) . esc_html( $e['band_label'] ) . '</span>'
		: '<span class="tcc-band__label">' . esc_html( $e['band_label'] ) . '</span>';
	$out .= '<h2 class="tcc-band__title"><a href="' . esc_url( $e['permalink'] ) . '" data-tcc-open="' . esc_attr( $e['year'] ) . '">' . esc_html( $e['title'] ) . tcc_svg( 'chev' ) . '</a></h2>';
	if ( $e['composers'] ) {
		$out .= '<p class="tcc-composer">' . esc_html( trim( $e['subtitle'] . ' ' . $e['composers'] ) ) . '</p>';
	}
	if ( $e['summary'] ) {
		$out .= '<p class="tcc-summary tcc-summary--band">' . esc_html( $e['summary'] ) . '</p>';
	}
	$out .= '</div><div class="tcc-band__actions">';
	$out .= tcc_primary_link( $e, 'tcc-btn ' . ( $e['current'] ? 'tcc-btn--primary' : 'tcc-btn--dark' ) );
	$out .= '<a class="tcc-btn tcc-btn--outline" href="' . esc_url( $e['permalink'] ) . '" data-tcc-open="' . esc_attr( $e['year'] ) . '">' . esc_html( tcc_text( 'full_details' ) ) . '</a>';
	$out .= '</div></div></section>';
	return $out;
}

/* ---------- the stack ---------- */

function tcc_render_stack(): string {
	$posts = tcc_get_cantatas();
	if ( ! $posts ) {
		if ( current_user_can( 'edit_posts' ) ) {
			return '<p class="tcc-empty">No cantatas yet. Add a post to the <strong>Cantata</strong> category and fill in its Cantata fields.</p>';
		}
		return '';
	}
	$current = (int) $posts[0]->ID;
	$out = '<div class="tcc-stack" data-tcc-stack>';
	foreach ( $posts as $i => $p ) {
		$e = tcc_entry_data( $p->ID, $current );
		$classes = 'tcc-entry state-' . $e['state'] . ( $e['current'] ? ' is-current' : '' ) . ( $i === 0 ? ' is-open' : '' );
		$out .= sprintf( '<article class="%s" id="%s" style="--tcc-tint:%s;--tcc-wash:%s" data-tcc-year="%s">',
			esc_attr( $classes ), esc_attr( $e['year'] ), esc_attr( $e['tint'] ), esc_attr( $e['wash'] ), esc_attr( $e['year'] ) );
		$out .= tcc_render_full( $e ) . tcc_render_band( $e ) . '</article>';
		if ( $i === 0 ) {
			$out .= tcc_render_interlude();
		}
	}
	return $out . '</div>';
}

/** The editable band under the current cantata (a template part named cantata-interlude). */
function tcc_render_interlude(): string {
	if ( ! function_exists( 'block_template_part' ) || ! wp_is_block_theme() ) {
		return '';
	}
	ob_start();
	block_template_part( 'cantata-interlude' );
	$html = trim( ob_get_clean() );
	return $html ? '<div class="tcc-interlude">' . $html . '</div>' : '';
}
