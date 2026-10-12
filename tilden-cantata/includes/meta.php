<?php
defined( 'ABSPATH' ) || exit;

/** Register every field as post meta so the REST API and the editor can read it. */
add_action( 'init', function () {
	foreach ( tcc_fields() as $key => $f ) {
		if ( $f[1] === 'songlist' ) {
			register_post_meta( 'post', '_tcc_songlist', [ 'type' => 'array', 'single' => true, 'show_in_rest' => false, 'auth_callback' => fn() => current_user_can( 'edit_posts' ) ] );
			continue;
		}
		if ( $f[1] === 'docs' ) {
			register_post_meta( 'post', '_tcc_docs', [ 'type' => 'array', 'single' => true, 'show_in_rest' => false, 'auth_callback' => fn() => current_user_can( 'edit_posts' ) ] );
			continue;
		}
		register_post_meta( 'post', '_tcc_' . $key, [
			'type'          => in_array( $f[1], [ 'image', 'file', 'page' ], true ) ? 'integer' : 'string',
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => fn() => current_user_can( 'edit_posts' ),
			'sanitize_callback' => fn( $v ) => tcc_sanitize_field( $key, $v ),
		] );
	}
	register_post_meta( 'page', '_tcc_members', [
		'type' => 'boolean', 'single' => true, 'show_in_rest' => true,
		'auth_callback' => fn() => current_user_can( 'edit_pages' ),
	] );
} );

function tcc_sanitize_field( string $key, $v ) {
	$type = tcc_fields()[ $key ][1] ?? 'text';
	switch ( $type ) {
		case 'image':
		case 'file':
		case 'page':
			return absint( $v );
		case 'url':
			return esc_url_raw( trim( (string) $v ) );
		case 'textarea':
			return sanitize_textarea_field( (string) $v );
		case 'songlist':
			return tcc_sanitize_songlist( $v );
		case 'docs':
			return tcc_sanitize_docs( $v );
		case 'color':
			$v = trim( (string) $v );
			return preg_match( '/^#[0-9a-fA-F]{6}$/', $v ) ? strtolower( $v ) : '';
		case 'select':
			return in_array( $v, [ 'auto', 'upcoming', 'past' ], true ) ? $v : 'auto';
		case 'datetime':
		case 'date':
			$v = trim( (string) $v );
			return preg_match( '/^\d{4}-\d{2}-\d{2}(T| )?(\d{2}:\d{2})?$/', $v ) ? str_replace( 'T', ' ', $v ) : '';
		default:
			return sanitize_text_field( (string) $v );
	}
}

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'tcc_cantata', 'Cantata', 'tcc_render_meta_box', 'post', 'normal', 'high' );
	add_meta_box( 'tcc_members', 'Members-only page', 'tcc_render_members_box', 'page', 'side' );
} );

function tcc_render_meta_box( WP_Post $post ) {
	wp_nonce_field( 'tcc_save', 'tcc_nonce' );
	echo '<p class="description">Fill these in for a post in the <strong>Cantata</strong> category. Everything on the home page comes from here, the title, the excerpt (one-sentence summary), the featured image (performance photo), and the post body (program notes and a photo gallery).</p>';
	echo '<table class="form-table tcc-fields"><tbody>';
	foreach ( tcc_fields() as $key => [ $label, $type, $desc ] ) {
		$name = '_tcc_' . $key;
		$val  = get_post_meta( $post->ID, $name, true );
		echo '<tr><th scope="row"><label for="' . esc_attr( $name ) . '">' . esc_html( $label ) . '</label></th><td>';
		switch ( $type ) {
			case 'datetime':
				printf( '<input type="datetime-local" id="%1$s" name="%1$s" value="%2$s">', esc_attr( $name ), esc_attr( str_replace( ' ', 'T', $val ) ) );
				break;
			case 'date':
				printf( '<input type="date" id="%1$s" name="%1$s" value="%2$s">', esc_attr( $name ), esc_attr( substr( (string) $val, 0, 10 ) ) );
				break;
			case 'songlist':
				tcc_render_songlist_control( $post->ID );
				break;
			case 'docs':
				tcc_render_docs_control( $post->ID );
				break;
			case 'textarea':
				printf( '<textarea id="%1$s" name="%1$s" rows="4" class="large-text">%2$s</textarea>', esc_attr( $name ), esc_textarea( $val ) );
				break;
			case 'image':
			case 'file':
				$id  = absint( $val );
				$src = $id ? ( $type === 'image' ? wp_get_attachment_image_url( $id, 'medium' ) : wp_get_attachment_url( $id ) ) : '';
				echo '<div class="tcc-media" data-type="' . esc_attr( $type ) . '">';
				printf( '<input type="hidden" id="%1$s" name="%1$s" value="%2$d">', esc_attr( $name ), $id );
				if ( $type === 'image' ) {
					echo '<img class="tcc-media__preview" src="' . esc_url( $src ) . '" style="max-width:120px;height:auto;display:' . ( $src ? 'block' : 'none' ) . ';margin-bottom:6px;">';
				} else {
					echo '<span class="tcc-media__name">' . esc_html( $src ? basename( $src ) : '' ) . '</span> ';
				}
				echo '<button type="button" class="button tcc-media__pick">Choose</button> <button type="button" class="button-link tcc-media__clear" style="' . ( $id ? '' : 'display:none' ) . '">Remove</button>';
				echo '</div>';
				break;
			case 'page':
				wp_dropdown_pages( [
					'name' => $name, 'id' => $name, 'selected' => absint( $val ),
					'show_option_none' => '— None —', 'option_none_value' => 0,
				] );
				break;
			case 'select':
				$opts = [ 'auto' => 'Auto (from the performance date)', 'upcoming' => 'Force upcoming', 'past' => 'Force past' ];
				echo '<select id="' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '">';
				foreach ( $opts as $k => $l ) {
					printf( '<option value="%s"%s>%s</option>', $k, selected( $val ?: 'auto', $k, false ), esc_html( $l ) );
				}
				echo '</select>';
				break;
			case 'color':
				printf( '<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text" placeholder="#264b7c" style="width:8em"> <span class="tcc-swatch" style="display:inline-block;width:1.4em;height:1.4em;vertical-align:middle;border-radius:3px;background:%2$s"></span>', esc_attr( $name ), esc_attr( $val ) );
				break;
			case 'url':
				printf( '<input type="url" id="%1$s" name="%1$s" value="%2$s" class="large-text">', esc_attr( $name ), esc_attr( $val ) );
				break;
			default:
				printf( '<input type="text" id="%1$s" name="%1$s" value="%2$s" class="large-text">', esc_attr( $name ), esc_attr( $val ) );
		}
		if ( $desc ) {
			echo '<p class="description">' . esc_html( $desc ) . '</p>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
}

function tcc_render_members_box( WP_Post $post ) {
	wp_nonce_field( 'tcc_save', 'tcc_nonce' );
	$on = (bool) get_post_meta( $post->ID, '_tcc_members', true );
	echo '<label><input type="checkbox" name="_tcc_members" value="1"' . checked( $on, true, false ) . '> This is a members-only (Backstage) page</label>';
	echo '<p class="description">Keeps it out of search engines and out of the page cache. Set a password under Visibility as well.</p>';
}

add_action( 'save_post', function ( int $post_id, WP_Post $post ) {
	if ( ! isset( $_POST['tcc_nonce'] ) || ! wp_verify_nonce( $_POST['tcc_nonce'], 'tcc_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( $post->post_type === 'page' ) {
		if ( current_user_can( 'edit_page', $post_id ) ) {
			update_post_meta( $post_id, '_tcc_members', ! empty( $_POST['_tcc_members'] ) );
		}
		return;
	}
	if ( $post->post_type !== 'post' || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( array_key_exists( '_tcc_songlist', $_POST ) ) {
		$list = tcc_sanitize_songlist( wp_unslash( $_POST['_tcc_songlist'] ) );
		if ( $list ) {
			update_post_meta( $post_id, '_tcc_songlist', $list );
			update_post_meta( $post_id, '_tcc_songs', implode( "\n", array_column( $list, 'title' ) ) ); // kept in step for anything reading the old text
		} else {
			delete_post_meta( $post_id, '_tcc_songlist' );
			delete_post_meta( $post_id, '_tcc_songs' );
		}
	}
	if ( array_key_exists( '_tcc_docs', $_POST ) ) {
		$docs = tcc_sanitize_docs( wp_unslash( $_POST['_tcc_docs'] ) );
		if ( $docs ) {
			update_post_meta( $post_id, '_tcc_docs', $docs );
		} else {
			delete_post_meta( $post_id, '_tcc_docs' );
		}
	}
	$old_cover = (int) get_post_meta( $post_id, '_tcc_cover', true );
	$old_tint  = (string) get_post_meta( $post_id, '_tcc_tint', true );
	foreach ( tcc_fields() as $key => $f ) {
		$name = '_tcc_' . $key;
		if ( $f[1] === 'songlist' || $f[1] === 'docs' || ! array_key_exists( $name, $_POST ) ) {
			continue;
		}
		$clean = tcc_sanitize_field( $key, wp_unslash( $_POST[ $name ] ) );
		if ( $clean === '' || $clean === 0 ) {
			delete_post_meta( $post_id, $name );
		} else {
			update_post_meta( $post_id, $name, $clean );
		}
	}
	// Derive the tint from the cover unless the editor typed one.
	$cover = (int) get_post_meta( $post_id, '_tcc_cover', true );
	$tint  = (string) get_post_meta( $post_id, '_tcc_tint', true );
	if ( $cover && ( ! $tint || ( $cover !== $old_cover && $tint === $old_tint ) ) ) {
		$auto = tcc_extract_tint( $cover );
		if ( $auto ) {
			update_post_meta( $post_id, '_tcc_tint', $auto );
		}
	}
	delete_transient( 'tcc_stack' );
}, 10, 2 );

/** Media pickers, no build step. */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
		return;
	}
	wp_enqueue_media();
	wp_add_inline_script( 'media-editor', file_get_contents( TCC_DIR . 'assets/picker.js' ) );
} );

/* ---------- the song list control: songs, each with solos / duets / ensembles and who sings them ---------- */

function tcc_people_options(): array {
	if ( ! function_exists( 'tcc_voice_parts' ) ) {
		return [];
	}
	$out = [];
	foreach ( get_posts( [ 'post_type' => TCC_PERSON, 'post_status' => 'publish', 'posts_per_page' => -1, 'orderby' => 'title', 'order' => 'ASC' ] ) as $p ) {
		$part = tcc_voice_parts()[ get_post_meta( $p->ID, '_tcc_part', true ) ] ?? '';
		$out[ $p->ID ] = $p->post_title . ( $part ? ' (' . $part . ')' : '' );
	}
	return $out;
}

function tcc_render_songlist_control( int $post_id ) {
	$songs  = tcc_songlist( $post_id );
	$people = tcc_people_options();
	$kinds  = tcc_part_kinds();
	$people_html = '';
	foreach ( $people as $id => $label ) {
		$people_html .= '<label><input type="checkbox" name="__PEOPLE__" value="' . $id . '"> ' . esc_html( $label ) . '</label>';
	}
	$kind_html = '';
	foreach ( $kinds as $k => $l ) {
		$kind_html .= '<option value="' . esc_attr( $k ) . '">' . esc_html( $l ) . '</option>';
	}
	if ( ! $people ) {
		echo '<p class="description">No people yet. Add the choir under <a href="' . esc_url( admin_url( 'edit.php?post_type=' . TCC_PERSON ) ) . '">Cantata → People</a> to assign solos.</p>';
	}
	echo '<style>
	.tcc-song{border:1px solid #c3c4c7;border-radius:4px;background:#fff;margin:0 0 10px}
	.tcc-song__head{display:flex;align-items:center;gap:8px;padding:8px 10px;background:#f6f7f7;border-bottom:1px solid #dcdcde}
	.tcc-song__n{font-weight:600;min-width:2em;text-align:right}
	.tcc-song__head input{flex:1}
	.tcc-parts{padding:6px 10px}
	.tcc-part{display:grid;grid-template-columns:130px 1fr 220px auto;gap:10px;align-items:start;padding:8px 0;border-bottom:1px dashed #dcdcde}
	.tcc-part:last-child{border-bottom:0}
	.tcc-people{max-height:112px;overflow:auto;border:1px solid #dcdcde;border-radius:3px;padding:4px 8px;columns:2;font-size:12px}
	.tcc-people label{display:block;break-inside:avoid;white-space:nowrap}
	.tcc-song__foot{padding:4px 10px 8px}
	</style>';
	echo '<div id="tcc-songlist">';
	foreach ( $songs as $i => $song ) {
		echo tcc_song_row_html( $i, $song, $people_html, $kind_html );
	}
	echo '</div>';
	echo '<p><button type="button" class="button" id="tcc-song-add">Add a song</button></p>';
	echo '<template id="tcc-song-tpl">' . tcc_song_row_html( '__S__', [ 'title' => '', 'parts' => [] ], $people_html, $kind_html ) . '</template>';
	echo '<template id="tcc-part-tpl">' . tcc_part_row_html( '__S__', '__P__', [ 'kind' => 'solo', 'people' => [], 'note' => '' ], $people_html, $kind_html ) . '</template>';
	echo <<<'JS'
<script>
(function(){
  var list=document.getElementById('tcc-songlist'), s=list.children.length+100;
  function renumber(){ Array.prototype.forEach.call(list.querySelectorAll('.tcc-song__n'),function(n,i){ n.textContent=(i+1)+'.'; }); }
  document.getElementById('tcc-song-add').addEventListener('click',function(){
    var html=document.getElementById('tcc-song-tpl').innerHTML.split('__S__').join(s++);
    list.insertAdjacentHTML('beforeend',html); renumber(); list.lastElementChild.querySelector('input[type=text]').focus();
  });
  list.addEventListener('click',function(e){
    var song=e.target.closest('.tcc-song'); if(!song) return;
    if(e.target.matches('.tcc-part-add')){
      var si=song.dataset.s, p=parseInt(song.dataset.p||'0',10);
      var html=document.getElementById('tcc-part-tpl').innerHTML.split('__S__').join(si).split('__P__').join(p);
      song.dataset.p=p+1; song.querySelector('.tcc-parts').insertAdjacentHTML('beforeend',html);
    } else if(e.target.matches('.tcc-part-remove')){ e.target.closest('.tcc-part').remove(); }
    else if(e.target.matches('.tcc-song-remove')){ if(confirm('Remove this song?')) { song.remove(); renumber(); } }
    else if(e.target.matches('.tcc-song-up')){ if(song.previousElementSibling) list.insertBefore(song,song.previousElementSibling); renumber(); }
    else if(e.target.matches('.tcc-song-down')){ if(song.nextElementSibling) list.insertBefore(song.nextElementSibling,song); renumber(); }
  });
})();
</script>
JS;
}

function tcc_song_row_html( $i, array $song, string $people_html, string $kind_html ): string {
	$out  = '<div class="tcc-song" data-s="' . esc_attr( $i ) . '" data-p="' . count( $song['parts'] ) . '">';
	$out .= '<div class="tcc-song__head"><span class="tcc-song__n"></span>';
	$out .= '<input type="text" name="_tcc_songlist[' . esc_attr( $i ) . '][title]" value="' . esc_attr( $song['title'] ) . '" placeholder="Song title">';
	$out .= '<button type="button" class="button-link tcc-song-up" title="Move up">▲</button><button type="button" class="button-link tcc-song-down" title="Move down">▼</button>';
	$out .= '<button type="button" class="button-link-delete tcc-song-remove">Remove</button></div>';
	$out .= '<div class="tcc-parts">';
	foreach ( $song['parts'] as $j => $part ) {
		$out .= tcc_part_row_html( $i, $j, $part, $people_html, $kind_html );
	}
	$out .= '</div><div class="tcc-song__foot"><button type="button" class="button-link tcc-part-add">+ Add a solo, duet or ensemble</button></div></div>';
	return $out;
}

function tcc_part_row_html( $i, $j, array $part, string $people_html, string $kind_html ): string {
	$base = '_tcc_songlist[' . esc_attr( $i ) . '][parts][' . esc_attr( $j ) . ']';
	$kinds = str_replace( 'value="' . esc_attr( $part['kind'] ) . '"', 'value="' . esc_attr( $part['kind'] ) . '" selected', $kind_html );
	$people = str_replace( '__PEOPLE__', $base . '[people][]', $people_html );
	foreach ( (array) $part['people'] as $pid ) {
		$people = str_replace( 'value="' . (int) $pid . '">', 'value="' . (int) $pid . '" checked>', $people );
	}
	return '<div class="tcc-part"><select name="' . $base . '[kind]">' . $kinds . '</select>'
		. '<div class="tcc-people">' . ( $people ?: '<span class="description">No people yet</span>' ) . '</div>'
		. '<input type="text" name="' . $base . '[note]" value="' . esc_attr( $part['note'] ?? '' ) . '" placeholder="Note (optional), e.g. verse 2">'
		. '<button type="button" class="button-link-delete tcc-part-remove">×</button></div>';
}

/* ---------- documents: title, file or link, who it's for ---------- */

function tcc_render_docs_control( int $post_id ) {
	$rows  = (array) get_post_meta( $post_id, '_tcc_docs', true );
	$rows  = array_values( array_filter( $rows, 'is_array' ) );
	$rows[] = [ 'title' => '', 'file' => 0, 'url' => '', 'who' => [], 'note' => '' ];
	$rows[] = [ 'title' => '', 'file' => 0, 'url' => '', 'who' => [], 'note' => '' ];
	$types = function_exists( 'tcc_attendee_types' ) ? tcc_attendee_types() : [];
	echo '<table class="widefat striped" style="max-width:980px"><thead><tr><th>Title</th><th style="width:230px">File</th><th style="width:220px">or link</th><th style="width:250px">For</th><th>Note</th></tr></thead><tbody>';
	foreach ( $rows as $i => $row ) {
		$base = '_tcc_docs[' . $i . ']';
		$fid  = absint( $row['file'] ?? 0 );
		$fname = $fid ? basename( (string) get_attached_file( $fid ) ) : '';
		echo '<tr>';
		printf( '<td><input type="text" name="%s[title]" value="%s" class="large-text" placeholder="e.g. Narration script"></td>', esc_attr( $base ), esc_attr( $row['title'] ?? '' ) );
		echo '<td><div class="tcc-media" data-type="file">';
		printf( '<input type="hidden" name="%s[file]" value="%d">', esc_attr( $base ), $fid );
		echo '<span class="tcc-media__name">' . esc_html( $fname ) . '</span> <button type="button" class="button button-small tcc-media__pick">Choose</button> <button type="button" class="button-link tcc-media__clear" style="' . ( $fid ? '' : 'display:none' ) . '">Remove</button></div></td>';
		printf( '<td><input type="url" name="%s[url]" value="%s" class="large-text" placeholder="https://docs.google.com/…"></td>', esc_attr( $base ), esc_attr( $row['url'] ?? '' ) );
		echo '<td>';
		foreach ( $types as $k => $l ) {
			printf( '<label style="margin-right:8px;white-space:nowrap"><input type="checkbox" name="%s[who][]" value="%s"%s> %s</label>', esc_attr( $base ), esc_attr( $k ), checked( in_array( $k, (array) ( $row['who'] ?? [] ), true ), true, false ), esc_html( $l ) );
		}
		echo '</td>';
		printf( '<td><input type="text" name="%s[note]" value="%s" class="large-text" placeholder="One line, optional"></td>', esc_attr( $base ), esc_attr( $row['note'] ?? '' ) );
		echo '</tr>';
	}
	echo '</tbody></table><p class="description">Nothing ticked under "For" means everyone. Rows without a title, or with neither a file nor a link, are ignored. Two blank rows are always offered; save for more.</p>';
}
