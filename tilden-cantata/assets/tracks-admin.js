/* Practice tracks box on a cantata post: pick audio files per part, match each to a song, manage cues. */
( function ( $ ) {
	var app = document.getElementById( 'tcc-tracks-app' );
	if ( ! app ) { return; }
	var data = JSON.parse( app.getAttribute( 'data-tcc-tracks' ) || '{}' );
	var songs = data.songs || [], parts = data.parts || {}, tracks = ( data.tracks && ! Array.isArray( data.tracks ) ) ? data.tracks : {}, cues = Array.isArray( data.cues ) ? data.cues : [], icons = data.icons && ! Array.isArray( data.icons ) ? data.icons : {};
	var jsonTracks = document.getElementById( 'tcc_tracks_json' ), jsonCues = document.getElementById( 'tcc_cues_json' );

	function esc( s ) { return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ]; } ); }
	function clean( t ) {
		return String( t || '' ).replace( /^\s*\d+\s*[-.–]?\s*/, '' ).replace( /\s*[-–]?\s*\((soprano|alto|tenor|bass|accomp[a-z]*|piano|all voices|sop|alt|ten|bas)\)\s*$/i, '' ).replace( /\s+(SOP|ALT|TEN|BAS|ACC|ACCOMP\.?|Soprano|Alto|Tenor|Bass)\s*$/, '' ).replace( /\s*[-–]\s*(accomp\.?|all voices)\s*$/i, '' ).trim();
	}
	function similarity( a, b ) {
		a = a.toLowerCase(); b = b.toLowerCase();
		if ( a === b ) { return 1; }
		if ( a.indexOf( b ) === 0 || b.indexOf( a ) === 0 ) { return 0.85; }
		var aw = a.split( /\W+/ ).filter( Boolean ), bw = b.split( /\W+/ ).filter( Boolean ), hit = 0;
		aw.forEach( function ( w ) { if ( bw.indexOf( w ) !== -1 ) { hit++; } } );
		return aw.length ? hit / Math.max( aw.length, bw.length ) : 0;
	}
	function matchSong( title ) {
		var c = clean( title ), best = -1, score = 0;
		songs.forEach( function ( s, i ) { var v = similarity( c, s ); if ( v > score ) { score = v; best = i; } } );
		return score >= 0.5 ? best : -1;
	}
	function sortList( list ) {
		list.sort( function ( a, b ) { var x = a.song < 0 ? 999 : a.song, y = b.song < 0 ? 999 : b.song; return x - y; } );
	}
	var dirty = false;
	function sync( changed ) {
		jsonTracks.value = JSON.stringify( tracks );
		jsonCues.value = JSON.stringify( cues );
		if ( changed ) {
			dirty = true;
			// Tell the block editor the box changed, so its Update button wakes up.
			[ 'input', 'change' ].forEach( function ( ev ) { jsonTracks.dispatchEvent( new Event( ev, { bubbles: true } ) ); } );
			try { if ( window.wp && wp.data && wp.data.dispatch( 'core/edit-post' ) && wp.data.dispatch( 'core/edit-post' ).metaBoxUpdatesDirty ) { /* older API, harmless */ } } catch ( e ) {}
			var note = document.getElementById( 'tcc-tracks-unsaved' );
			if ( ! note ) { note = document.createElement( 'p' ); note.id = 'tcc-tracks-unsaved'; note.style.cssText = 'margin:8px 0 0;color:#9c2b1a;font-weight:600'; note.textContent = 'Not saved yet — click Save tracks below (or Update at the top of the page).'; app.parentNode.insertBefore( note, app.nextSibling ); }
		}
	}
	function fmt( t ) { t = Math.max( 0, Math.round( t ) ); return Math.floor( t / 60 ) + ':' + ( '0' + ( t % 60 ) ).slice( -2 ); }
	function parseTime( s ) { var m = String( s ).trim().match( /^(\d+):(\d{1,2})(?:\.(\d))?$/ ); if ( m ) { return +m[1] * 60 + +m[2] + ( m[3] ? +m[3] / 10 : 0 ); } var n = parseFloat( s ); return isNaN( n ) ? 0 : n; }

	function render( changed ) {
		var html = '';
		Object.keys( parts ).forEach( function ( part ) {
			var list = tracks[ part ] || [];
			html += '<div class="tcc-tr-part" data-part="' + esc( part ) + '" style="margin:14px 0;padding:12px 14px;border:1px solid #dcdcde;border-radius:4px;background:#fff">';
			html += '<div style="display:flex;align-items:center;gap:10px;margin-bottom:' + ( list.length ? '8px' : '0' ) + '"><strong style="font-size:14px">' + esc( parts[ part ] ) + '</strong><span class="description">' + ( list.length ? list.length + ' tracks' : 'none yet' ) + '</span><button type="button" class="button tcc-tr-add" style="margin-left:auto">Add tracks</button>' + ( list.length ? '<button type="button" class="button-link tcc-tr-rematch">Re-match songs</button>' : '' ) + '</div>';
			if ( list.length ) {
				html += '<table class="widefat striped"><thead><tr><th style="width:36px">#</th><th>File</th><th style="width:40%">Song</th><th style="width:60px"></th></tr></thead><tbody>';
				list.forEach( function ( t, i ) {
					html += '<tr data-i="' + i + '"><td>' + ( i + 1 ) + '</td><td><strong>' + esc( t.title ) + '</strong><br><span class="description">' + esc( t.file || '' ) + '</span></td><td><select class="tcc-tr-song" style="width:100%"><option value="-1"' + ( t.song < 0 ? ' selected' : '' ) + '>— not matched —</option>';
					songs.forEach( function ( s, j ) { html += '<option value="' + j + '"' + ( t.song === j ? ' selected' : '' ) + '>' + ( j + 1 ) + '. ' + esc( s ) + '</option>'; } );
					html += '</select></td><td><button type="button" class="button-link-delete tcc-tr-remove">Remove</button></td></tr>';
				} );
				html += '</tbody></table>';
			}
			html += '</div>';
		} );
		// cues
		html += '<div style="margin:18px 0 6px;padding:12px 14px;border:1px solid #dcdcde;border-radius:4px;background:#fff"><div style="display:flex;align-items:center;gap:10px;margin-bottom:8px"><strong style="font-size:14px">Timestamp notes</strong><span class="description">Shown to singers at that moment in the track. Add them from the practice page while logged in, or here.</span><button type="button" class="button tcc-cue-add" style="margin-left:auto">Add a note</button></div>';
		if ( cues.length ) {
			html += '<table class="widefat striped"><thead><tr><th style="width:40%">Song</th><th style="width:80px">At</th><th style="width:70px">Measure</th><th>Note</th><th style="width:150px">Icon</th><th style="width:220px">Only for</th><th style="width:60px"></th></tr></thead><tbody>';
			cues.forEach( function ( c, i ) {
				html += '<tr data-i="' + i + '"><td><select class="tcc-cue-song" style="width:100%">';
				songs.forEach( function ( s, j ) { html += '<option value="' + j + '"' + ( c.song === j ? ' selected' : '' ) + '>' + ( j + 1 ) + '. ' + esc( s ) + '</option>'; } );
				html += '</select></td><td><input type="text" class="tcc-cue-t" value="' + esc( fmt( c.t ) ) + '" style="width:70px"></td><td><input type="text" class="tcc-cue-m" value="' + esc( c.m || '' ) + '" placeholder="42" maxlength="12" style="width:60px"></td><td><input type="text" class="tcc-cue-text regular-text" value="' + esc( c.text ) + '" style="width:100%"></td><td><select class="tcc-cue-icon" style="width:100%"><option value="">—</option>';
				Object.keys( icons ).forEach( function ( k ) { html += '<option value="' + esc( k ) + '"' + ( c.icon === k ? ' selected' : '' ) + '>' + esc( icons[ k ] ) + '</option>'; } );
				html += '</select></td><td>';
				[ 'soprano', 'alto', 'tenor', 'bass' ].forEach( function ( p ) { html += '<label style="margin-right:8px;white-space:nowrap"><input type="checkbox" class="tcc-cue-part" value="' + p + '"' + ( ( c.parts || [] ).indexOf( p ) !== -1 ? ' checked' : '' ) + '> ' + esc( parts[ p ] || p ) + '</label>'; } );
				html += '<br><span class="description">None ticked = everyone</span></td><td><button type="button" class="button-link-delete tcc-cue-remove">Remove</button></td></tr>';
			} );
			html += '</tbody></table>';
		}
		html += '</div>';
		app.innerHTML = html;
		sync( changed !== false );
	}

	app.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( 'button' );
		if ( ! btn ) { return; }
		var partBox = btn.closest( '.tcc-tr-part' ), part = partBox ? partBox.getAttribute( 'data-part' ) : null, row = btn.closest( 'tr' ), i = row ? +row.getAttribute( 'data-i' ) : -1;
		if ( btn.classList.contains( 'tcc-tr-add' ) ) {
			var frame = wp.media( { title: 'Choose ' + ( parts[ part ] || part ) + ' tracks', button: { text: 'Add to ' + ( parts[ part ] || part ) }, multiple: 'add', library: { type: 'audio' } } );
			frame.on( 'select', function () {
				var list = tracks[ part ] = tracks[ part ] || [];
				frame.state().get( 'selection' ).each( function ( att ) {
					var a = att.toJSON();
					if ( list.some( function ( t ) { return t.id === a.id; } ) ) { return; }
					list.push( { id: a.id, song: matchSong( a.title || a.filename ), title: a.title || a.filename, file: a.filename } );
				} );
				sortList( list ); render();
			} );
			frame.open();
		} else if ( btn.classList.contains( 'tcc-tr-rematch' ) ) {
			( tracks[ part ] || [] ).forEach( function ( t ) { t.song = matchSong( t.title ); } ); sortList( tracks[ part ] ); render();
		} else if ( btn.classList.contains( 'tcc-tr-remove' ) ) {
			tracks[ part ].splice( i, 1 ); render();
		} else if ( btn.classList.contains( 'tcc-cue-add' ) ) {
			cues.push( { id: Math.random().toString( 36 ).slice( 2, 10 ), song: 0, t: 0, text: '', parts: [], m: '', icon: '' } ); render();
			var last = app.querySelector( '.tcc-cue-text:last-of-type' ); if ( last ) { last.focus(); }
		} else if ( btn.classList.contains( 'tcc-cue-remove' ) ) {
			cues.splice( i, 1 ); render();
		}
	} );
	app.addEventListener( 'change', function ( e ) {
		var el = e.target, row = el.closest( 'tr' ), i = row ? +row.getAttribute( 'data-i' ) : -1;
		if ( el.classList.contains( 'tcc-tr-song' ) ) {
			var part = el.closest( '.tcc-tr-part' ).getAttribute( 'data-part' );
			tracks[ part ][ i ].song = parseInt( el.value, 10 ); sortList( tracks[ part ] ); render();
		} else if ( el.classList.contains( 'tcc-cue-song' ) ) { cues[ i ].song = parseInt( el.value, 10 ); sync( true ); }
		else if ( el.classList.contains( 'tcc-cue-t' ) ) { cues[ i ].t = parseTime( el.value ); el.value = fmt( cues[ i ].t ); sync( true ); }
		else if ( el.classList.contains( 'tcc-cue-text' ) ) { cues[ i ].text = el.value; sync( true ); }
		else if ( el.classList.contains( 'tcc-cue-m' ) ) { cues[ i ].m = el.value.trim(); sync( true ); }
		else if ( el.classList.contains( 'tcc-cue-icon' ) ) { cues[ i ].icon = el.value; sync( true ); }
		else if ( el.classList.contains( 'tcc-cue-part' ) ) {
			cues[ i ].parts = Array.prototype.map.call( row.querySelectorAll( '.tcc-cue-part:checked' ), function ( c ) { return c.value; } ); sync( true );
		}
	} );
	app.addEventListener( 'input', function ( e ) { if ( e.target.classList.contains( 'tcc-cue-text' ) ) { var row = e.target.closest( 'tr' ); cues[ +row.getAttribute( 'data-i' ) ].text = e.target.value; sync( true ); } } );

	var saveBtn = document.getElementById( 'tcc-tracks-save' ), msg = document.getElementById( 'tcc-tracks-msg' );
	if ( saveBtn ) {
		saveBtn.addEventListener( 'click', function () {
			saveBtn.disabled = true; msg.textContent = 'Saving…';
			var fd = new FormData();
			fd.append( 'action', 'tcc_save_tracks' ); fd.append( 'post', app.getAttribute( 'data-post' ) ); fd.append( 'nonce', app.getAttribute( 'data-nonce' ) );
			fd.append( 'tracks', JSON.stringify( tracks ) ); fd.append( 'cues', JSON.stringify( cues ) );
			fetch( window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: fd } ).then( function ( r ) { return r.json(); } ).then( function ( j ) {
				saveBtn.disabled = false;
				if ( j && j.success ) { msg.textContent = j.data.message; dirty = false; var note = document.getElementById( 'tcc-tracks-unsaved' ); if ( note ) { note.remove(); } }
				else { msg.textContent = 'Could not save: ' + ( j && j.data ? j.data : 'server error' ); }
			} ).catch( function ( e ) { saveBtn.disabled = false; msg.textContent = 'Could not save: ' + e.message; } );
		} );
	}
	Object.keys( tracks ).forEach( function ( p ) { sortList( tracks[ p ] ); } );
	render( false );
} )( window.jQuery );
