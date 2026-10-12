<?php
defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
	register_block_type( 'tilden/cantata-stack', [
		'api_version'     => 3,
		'title'           => 'Cantata stack',
		'description'     => 'Every cantata, newest first, with the current one expanded.',
		'category'        => 'widgets',
		'icon'            => 'playlist-audio',
		'supports'        => [ 'html' => false, 'multiple' => false, 'align' => [ 'full' ] ],
		'attributes'      => [ 'wording' => [ 'type' => 'object', 'default' => [] ] ],
		'render_callback' => function ( array $attributes ) {
			tcc_wording_override( array_filter( (array) ( $attributes['wording'] ?? [] ), 'is_string' ) );
			tcc_enqueue_front_assets();
			return '<div ' . get_block_wrapper_attributes( [ 'class' => 'tcc-stack-wrap' ] ) . '>' . tcc_render_stack() . '</div>';
		},
	] );
	register_block_type( 'tilden/cantata-details', [
		'api_version'     => 3,
		'title'           => 'Cantata details',
		'description'     => 'The full entry for this cantata post: photo, cover, event details, and actions.',
		'category'        => 'widgets',
		'icon'            => 'tickets-alt',
		'supports'        => [ 'html' => false, 'multiple' => false, 'align' => [ 'full' ] ],
		'render_callback' => function () {
			$id = get_the_ID();
			if ( ! $id || ! has_category( TCC_CATEGORY, $id ) ) {
				return '';
			}
			tcc_enqueue_front_assets();
			$e = tcc_entry_data( $id );
			return '<div ' . get_block_wrapper_attributes( [ 'class' => 'tcc-stack-wrap' ] ) . '>' . sprintf( '<div class="tcc-stack tcc-stack--single"><article class="tcc-entry is-open state-%s%s" id="%s" style="--tcc-tint:%s;--tcc-wash:%s">%s</article></div>',
				esc_attr( $e['state'] ), $e['current'] ? ' is-current' : '', esc_attr( $e['year'] ), esc_attr( $e['tint'] ), esc_attr( $e['wash'] ), tcc_render_full( $e, 'single' ) ) . '</div>';
		},
	] );
} );

/** The publisher's recording of the work, for the singers page. Click to load the player. */
function tcc_render_listen( int $cantata_id = 0 ): string {
	$cantata_id = $cantata_id ?: tcc_current_id();
	$url = $cantata_id ? tcc_meta( $cantata_id, 'listen' ) : '';
	$id  = tcc_youtube_id( $url );
	if ( ! $id ) {
		return current_user_can( 'edit_posts' ) ? '<p class="tcc-empty">No listening video yet. Add one in the Cantata box on this year\'s post.</p>' : '';
	}
	$title = get_the_title( $cantata_id );
	$comp  = tcc_meta( $cantata_id, 'composers' );
	return '<figure class="tcc-listen" data-tcc-inline="' . esc_attr( $id ) . '" data-tcc-title="' . esc_attr( $title ) . '">'
		. '<button type="button" class="tcc-listen__play" aria-label="Play ' . esc_attr( $title ) . '">'
		. '<img src="https://i.ytimg.com/vi/' . esc_attr( $id ) . '/hqdefault.jpg" alt="" loading="lazy">'
		. '<span class="tcc-listen__badge">' . tcc_svg( 'play' ) . '<span>Listen to the whole work</span></span></button>'
		. '<figcaption><strong>' . esc_html( $title ) . '</strong>' . ( $comp ? ' · ' . esc_html( $comp ) : '' ) . ' · <a href="' . esc_url( tcc_youtube_watch_url( $url ) ) . '" target="_blank" rel="noopener">Open on YouTube</a></figcaption></figure>';
}

add_action( 'init', function () {
	register_block_type( 'tilden/cantata-listen', [
		'api_version' => 3, 'title' => 'Listening video', 'description' => 'The publisher\'s recording of this year\'s cantata, for singers.',
		'category' => 'widgets', 'icon' => 'video-alt3',
		'supports' => [ 'html' => false, 'multiple' => false, 'align' => [ 'wide' ] ],
		'attributes' => [ 'cantata' => [ 'type' => 'integer', 'default' => 0 ] ],
		'render_callback' => function ( array $attributes ) {
			tcc_enqueue_front_assets();
			return '<div ' . get_block_wrapper_attributes() . '>' . tcc_render_listen( (int) ( $attributes['cantata'] ?? 0 ) ) . '</div>';
		},
	] );
} );

/** Backstage hero: this year's cantata as a tinted band with its cover. */
function tcc_render_backstage_hero( int $cantata_id = 0 ): string {
	$cantata_id = $cantata_id ?: tcc_current_id();
	if ( ! $cantata_id ) {
		return '';
	}
	$e = tcc_entry_data( $cantata_id );
	$out  = '<section class="tcc-bhero" style="--tcc-tint:' . esc_attr( $e['tint'] ) . ';--tcc-wash:' . esc_attr( $e['wash'] ) . '">';
	$out .= '<div class="tcc-bhero__inner"><div class="tcc-bhero__text">';
	$out .= '<span class="tcc-tag tcc-tag--current">' . tcc_svg( 'star' ) . '<span>Backstage · ' . esc_html( $e['year'] ) . ' season</span></span>';
	$out .= '<h1 class="tcc-bhero__title">' . esc_html( $e['title'] ) . '</h1>';
	if ( $e['composers'] ) {
		$out .= '<p class="tcc-bhero__sub">' . esc_html( trim( $e['subtitle'] . ' ' . $e['composers'] ) ) . '</p>';
	}
	$out .= '<p class="tcc-bhero__lede">Everything for this year\'s cantata, for everyone who makes it happen. The password you just used works on every page here for the whole season.</p>';
	if ( $e['perf_long'] ) {
		$out .= '<p class="tcc-bhero__when">' . tcc_svg( 'bell' ) . '<span>Performance ' . esc_html( $e['perf_long'] ) . ( $e['perf_time'] ? ', ' . esc_html( $e['perf_time'] ) : '' ) . ( $e['days'] !== null && $e['days'] > 0 ? ' · ' . (int) $e['days'] . ' days away' : '' ) . '</span></p>';
	}
	$out .= '</div>';
	if ( $e['cover'] ) {
		$out .= '<div class="tcc-bhero__cover">' . tcc_cover_img( $e, 'tcc-cover tcc-cover--hero', 'medium_large' ) . '</div>';
	}
	return $out . '</div></section>';
}

add_action( 'init', function () {
	register_block_type( 'tilden/backstage-hero', [
		'api_version' => 3, 'title' => 'Backstage hero', 'description' => 'This year\'s cantata as a tinted band with its cover, for the top of the Backstage page.',
		'category' => 'widgets', 'icon' => 'cover-image',
		'supports' => [ 'html' => false, 'multiple' => false, 'align' => [ 'full' ] ],
		'attributes' => [ 'cantata' => [ 'type' => 'integer', 'default' => 0 ] ],
		'render_callback' => function ( array $attributes ) {
			tcc_enqueue_front_assets();
			return '<div ' . get_block_wrapper_attributes() . '>' . tcc_render_backstage_hero( (int) ( $attributes['cantata'] ?? 0 ) ) . '</div>';
		},
	] );
} );

/**
 * The header for a Backstage sub-page (Schedule, Soprano, …): a "← Backstage" link, the season
 * eyebrow with this year's cantata, and the page's own title. Pages using it should use the
 * "Backstage page" template so the theme doesn't print the title a second time.
 */
function tcc_render_page_head( int $cantata_id = 0, string $title = '', bool $back = true ): string {
	$cantata_id = $cantata_id ?: tcc_current_id();
	$title = trim( $title ) ?: get_the_title();
	$out  = '<header class="tcc-phead">';
	if ( $back ) {
		$out .= '<a class="tcc-phead__back" href="' . esc_url( home_url( '/backstage/' ) ) . '">' . tcc_svg( 'back' ) . '<span>Backstage</span></a>';
	}
	if ( $cantata_id ) {
		$out .= '<span class="tcc-tag tcc-tag--current tcc-phead__tag">' . tcc_svg( 'star' ) . '<span>' . esc_html( tcc_year( $cantata_id ) ) . ' season · ' . esc_html( get_the_title( $cantata_id ) ) . '</span></span>';
	}
	$out .= '<h1 class="tcc-phead__title">' . esc_html( $title ) . '</h1>';
	return $out . '</header>';
}

add_action( 'init', function () {
	register_block_type( 'tilden/page-head', [
		'api_version' => 3, 'title' => 'Backstage page header', 'description' => 'Back link, season eyebrow and the page title, for the top of every Backstage sub-page. Use with the "Backstage page" template.',
		'category' => 'widgets', 'icon' => 'heading',
		'supports' => [ 'html' => false, 'multiple' => false ],
		'attributes' => [ 'cantata' => [ 'type' => 'integer', 'default' => 0 ], 'title' => [ 'type' => 'string', 'default' => '' ], 'back' => [ 'type' => 'boolean', 'default' => true ] ],
		'render_callback' => function ( array $attributes ) {
			tcc_enqueue_front_assets();
			return '<div ' . get_block_wrapper_attributes() . '>' . tcc_render_page_head( (int) ( $attributes['cantata'] ?? 0 ), (string) ( $attributes['title'] ?? '' ), ! empty( $attributes['back'] ) ) . '</div>';
		},
	] );
} );

/** Documents for one group (or all groups) of this year's cantata, as icon cards. Nothing renders for an empty group. */
function tcc_render_docs( int $cantata_id = 0, string $group = 'all', bool $headings = true ): string {
	$cantata_id = $cantata_id ?: tcc_current_id();
	if ( ! $cantata_id || ! function_exists( 'tcc_attendee_types' ) ) {
		return '';
	}
	$docs  = array_filter( (array) get_post_meta( $cantata_id, '_tcc_docs', true ), 'is_array' );
	$types = [ 'everyone' => 'Everyone' ] + tcc_attendee_types();
	$by    = array_fill_keys( array_keys( $types ), [] );
	foreach ( $docs as $d ) {
		$who = (array) ( $d['who'] ?? [] );
		if ( ! $who || count( $who ) >= count( tcc_attendee_types() ) ) {
			$by['everyone'][] = $d;
		} else {
			foreach ( $who as $w ) {
				if ( isset( $by[ $w ] ) ) {
					$by[ $w ][] = $d;
				}
			}
		}
	}
	$out = '';
	foreach ( $by as $key => $list ) {
		if ( ! $list || ( $group !== 'all' && $group !== $key ) ) {
			continue;
		}
		if ( $headings ) {
			$out .= '<p class="tcc-eyebrow tcc-eyebrow--' . esc_attr( $key ) . '">' . esc_html( $types[ $key ] ) . '</p>';
		}
		$out .= '<div class="tcc-docs">';
		foreach ( $list as $d ) {
			$url  = ! empty( $d['file'] ) ? wp_get_attachment_url( (int) $d['file'] ) : ( $d['url'] ?? '' );
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
			$out .= '<a class="tcc-doc tcc-doc--' . $icon . '" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . tcc_svg( 'file-' . $icon )
				. '<span class="tcc-doc__title">' . esc_html( $d['title'] ) . '</span>'
				. ( ! empty( $d['note'] ) ? '<span class="tcc-doc__note">' . esc_html( $d['note'] ) . '</span>' : '' ) . '</a>';
		}
		$out .= '</div>';
	}
	return $out;
}

add_action( 'init', function () {
	register_block_type( 'tilden/backstage-docs', [
		'api_version' => 3, 'title' => 'Cantata documents', 'description' => 'Documents from this year\'s cantata post, as cards, for one group or all groups.',
		'category' => 'widgets', 'icon' => 'media-document',
		'supports' => [ 'html' => false, 'multiple' => true ],
		'attributes' => [ 'cantata' => [ 'type' => 'integer', 'default' => 0 ], 'group' => [ 'type' => 'string', 'default' => 'all' ], 'headings' => [ 'type' => 'boolean', 'default' => true ] ],
		'render_callback' => function ( array $attributes ) {
			tcc_enqueue_front_assets();
			$html = tcc_render_docs( (int) ( $attributes['cantata'] ?? 0 ), (string) ( $attributes['group'] ?? 'all' ), ! empty( $attributes['headings'] ) );
			if ( ! $html ) {
				return current_user_can( 'edit_posts' ) && is_admin() ? '<p class="tcc-empty">No documents for this group yet. Add them in the Cantata box on this year\'s post.</p>' : '';
			}
			return '<div ' . get_block_wrapper_attributes() . '>' . $html . '</div>';
		},
	] );
} );

/** Tiny editor script so both blocks show in the inserter and preview server-side. No build step. */
add_action( 'enqueue_block_editor_assets', function () {
	wp_register_script( 'tcc-blocks', TCC_URL . 'assets/blocks.js', [ 'wp-blocks', 'wp-element', 'wp-components', 'wp-server-side-render', 'wp-block-editor' ], TCC_VERSION, true );
	$fields = [];
	foreach ( tcc_wording() as $k => [ $label, $default, $group ] ) {
		$fields[] = [ 'key' => $k, 'label' => $label, 'default' => $default, 'group' => $group ];
	}
	$cantatas = [ [ 'value' => 0, 'label' => 'This year’s cantata (automatic)' ] ];
	foreach ( tcc_get_cantatas() as $c ) {
		$cantatas[] = [ 'value' => (int) $c->ID, 'label' => get_the_title( $c ) . ' (' . tcc_year( $c->ID ) . ')' ];
	}
	wp_add_inline_script( 'tcc-blocks', 'window.tccWording = ' . wp_json_encode( $fields ) . '; window.tccCantatas = ' . wp_json_encode( $cantatas ) . ';', 'before' );
	wp_enqueue_script( 'tcc-blocks' );
	wp_enqueue_style( 'tcc-stack', TCC_URL . 'assets/stack.css', [], TCC_VERSION );
} );

/**
 * The wording lives on the stack block in the front-page template. Whenever that template is saved in the
 * Site Editor, mirror the block's wording into an option so cantata pages (which have no stack block) match.
 */
add_action( 'save_post_wp_template', function ( int $post_id, WP_Post $post ) {
	if ( ! has_block( 'tilden/cantata-stack', $post ) ) {
		return;
	}
	$find = function ( array $blocks ) use ( &$find ) {
		foreach ( $blocks as $blk ) {
			if ( $blk['blockName'] === 'tilden/cantata-stack' ) {
				return (array) ( $blk['attrs']['wording'] ?? [] );
			}
			if ( ! empty( $blk['innerBlocks'] ) ) {
				$r = $find( $blk['innerBlocks'] );
				if ( $r !== null ) {
					return $r;
				}
			}
		}
		return null;
	};
	$wording = $find( parse_blocks( $post->post_content ) );
	if ( $wording !== null ) {
		update_option( 'tcc_wording', array_map( 'sanitize_text_field', array_filter( $wording, 'is_string' ) ), false );
		if ( function_exists( 'tcc_purge_home' ) ) {
			tcc_purge_home();
		}
	}
}, 10, 2 );
