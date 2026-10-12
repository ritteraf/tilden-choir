/* Tilden Cantata — the practice app.
   Takes over the whole screen. The middle is a stream of notes that scrolls itself as the music
   plays — the current note large in the centre, past ones fading up, the next one waiting below.
   Controls sit at the bottom like any music app. Songs and parts open as sheets.
   Practice is kept gently: no counts anywhere a singer sees, just "getting there" / "feeling good". */
( function () {
	'use strict';
	var root = document.querySelector( '[data-tcc-app]' );
	if ( ! root ) { return; }
	var D = JSON.parse( root.getAttribute( 'data-tcc-app' ) );
	root.removeAttribute( 'data-tcc-app' );

	/* ---------- helpers ---------- */
	function h( tag, attrs, children ) {
		var el = document.createElement( tag );
		if ( attrs ) { Object.keys( attrs ).forEach( function ( k ) {
			var v = attrs[ k ];
			if ( v === null || v === undefined || v === false ) { return; }
			if ( k === 'class' ) { el.className = v; }
			else if ( k === 'html' ) { el.innerHTML = v; }
			else if ( k.indexOf( 'on' ) === 0 ) { el.addEventListener( k.slice( 2 ), v ); }
			else { el.setAttribute( k, v === true ? '' : v ); }
		} ); }
		( children || [] ).forEach( function ( c ) { if ( c === null || c === undefined || c === false ) { return; } el.appendChild( typeof c === 'string' ? document.createTextNode( c ) : c ); } );
		return el;
	}
	function fmt( t ) { t = Math.max( 0, Math.floor( t || 0 ) ); return Math.floor( t / 60 ) + ':' + ( '0' + ( t % 60 ) ).slice( -2 ); }
	function ls( k, v ) { try { if ( v === undefined ) { return localStorage.getItem( k ); } if ( v === null ) { localStorage.removeItem( k ); } else { localStorage.setItem( k, v ); } } catch ( e ) { return null; } return null; }
	var reduceMotion = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var I = {
		play: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 4.5v15l12.5-7.5z"/></svg>',
		pause: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 4.5h4.2v15H6zM13.8 4.5H18v15h-4.2z"/></svg>',
		prev: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18 5v14l-10-7zM5 5h2.2v14H5z"/></svg>',
		next: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 5v14l10-7zM16.8 5H19v14h-2.2z"/></svg>',
		back5: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"/><polyline points="3 3 3 9 9 9"/></svg>',
		x: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
		down: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>',
		list: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><circle cx="3.5" cy="6" r="1" fill="currentColor"/><circle cx="3.5" cy="12" r="1" fill="currentColor"/><circle cx="3.5" cy="18" r="1" fill="currentColor"/></svg>',
		people: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
		pen: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>',
		loop: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>',
		star: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 1 L13.4 10.6 L23 12 L13.4 13.4 L12 23 L10.6 13.4 L1 12 L10.6 10.6 Z"/></svg>',
		score: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="2.5" width="16" height="19" rx="2"/><line x1="7" y1="7" x2="17" y2="7"/><line x1="7" y1="10" x2="17" y2="10"/><line x1="7" y1="14" x2="17" y2="14"/><line x1="7" y1="17" x2="17" y2="17"/><circle cx="10" cy="17" r="1.4" fill="currentColor"/><line x1="11.4" y1="17" x2="11.4" y2="12.5"/></svg>',
		notes: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>',
		left: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>',
		right: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>'
	};
	/* the gentle states — the only "progress" a singer ever sees */
	var STATE = { none: 'Not started', some: 'Getting there', good: 'Feeling good', help: 'Asked for help' };
	function ring( st ) {
		if ( st === 'good' ) { return '<svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7.5" fill="currentColor"/></svg>'; }
		if ( st === 'some' ) { return '<svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M10 2.5a7.5 7.5 0 0 1 0 15z" fill="currentColor"/></svg>'; }
		if ( st === 'help' ) { return '<svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7.5" fill="none" stroke="currentColor" stroke-width="1.6"/><text x="10" y="14" text-anchor="middle" font-size="11" font-weight="700" fill="currentColor" font-family="sans-serif">?</text></svg>'; }
		return '<svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7.5" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>';
	}

	/* ---------- API ---------- */
	function api( path, method, body, token, beacon ) {
		token = token || D.token;
		if ( beacon && navigator.sendBeacon ) {
			var fd = new FormData();
			Object.keys( body ).forEach( function ( k ) { fd.append( k, body[ k ] ); } );
			fd.append( 'token', token );
			return navigator.sendBeacon( D.rest + path, fd );
		}
		var headers = { 'Content-Type': 'application/json', 'X-TCC-Token': token };
		if ( D.nonce ) { headers[ 'X-WP-Nonce' ] = D.nonce; }
		var url = D.rest + path;
		if ( method === 'GET' ) { url += ( url.indexOf( '?' ) === -1 ? '?' : '&' ) + Object.keys( body ).map( function ( k ) { return k + '=' + encodeURIComponent( body[ k ] ); } ).join( '&' ); }
		return fetch( url, { method: method, headers: headers, credentials: 'same-origin', cache: 'no-store', body: method === 'GET' ? undefined : JSON.stringify( body ) } ).then( function ( r ) { return r.json(); } );
	}

	/* ---------- data ---------- */
	var VOICE = [ 'soprano', 'alto', 'tenor', 'bass' ];
	var ORDER = VOICE.concat( [ 'accompaniment', 'sections', 'listen' ] );
	var LISTS = {};
	Object.keys( D.lists || {} ).forEach( function ( p ) { LISTS[ 'now:' + p ] = { key: 'now:' + p, now: true, cantata: { id: D.cantata.id, token: D.token, title: D.cantata.title, year: D.cantata.year }, part: p, tracks: D.lists[ p ] }; } );
	( D.past || [] ).forEach( function ( c ) { Object.keys( c.parts || {} ).forEach( function ( p ) { LISTS[ 'past:' + c.id + ':' + p ] = { key: 'past:' + c.id + ':' + p, now: false, cantata: { id: c.id, token: c.token, title: c.title, year: c.year }, part: p, tracks: c.parts[ p ] }; } ); } );
	function label( p ) { return ( D.partLabels && D.partLabels[ p ] ) || p; }
	function shortLabel( p ) { return { accompaniment: 'Accompaniment', sections: 'Sectioned', listen: 'All voices' }[ p ] || label( p ); }
	var NEXT = D.next || null;
	function inNext( song ) { return !! ( NEXT && ! NEXT.full && NEXT.songs.indexOf( song ) !== -1 ); }
	function isFocus( song ) { return !! ( NEXT && ( NEXT.focus || [] ).indexOf( song ) !== -1 ); }
	var nextDow = NEXT && NEXT.date ? NEXT.date.split( ',' )[ 0 ].slice( 0, 3 ) : '';

	var S = {
		who: null, part: '', list: null, idx: -1, playPart: '',
		rows: {}, notes: [], alt: false, loopA: null, loopB: null, rate: 1,
		listened: 0, counted: false, lastT: 0, follow: true, active: -2,
		view: ls( 'tcc_view' ) === 'music' ? 'music' : 'notes'
	};
	function rowKey( song, part ) { return song + '|' + part; }
	function countPart() { return S.playPart || S.part; }
	function stateOf( song, part ) {
		var r = S.rows[ rowKey( song, part || countPart() ) ] || {};
		if ( r.flag === 'ok' ) { return 'good'; }
		if ( r.flag === 'help' ) { return 'help'; }
		if ( r.flag === 'working' ) { return 'some'; }
		if ( +r.plays >= 3 ) { return 'good'; }
		if ( +r.plays >= 1 || +r.seconds >= 45 ) { return 'some'; }
		return 'none';
	}
	function partForVoice( voice ) {
		var v = D.voice && D.voice[ voice ];
		if ( ! v ) { return ''; }
		for ( var i = 0; i < v.tracks.length; i++ ) { if ( LISTS[ 'now:' + v.tracks[ i ] ] ) { return v.tracks[ i ]; } }
		return '';
	}
	function myParts() { var v = S.who && D.voice[ S.who.part ]; return v ? v.tracks : []; }
	function urlPart() { var m = location.search.match( /[?&]part=([a-z]+)/ ); return m && LISTS[ 'now:' + m[ 1 ] ] ? m[ 1 ] : ''; }
	function urlSong() { var m = location.hash.match( /#song-(\d+)/ ); return m ? parseInt( m[ 1 ], 10 ) - 1 : -1; }

	var audio = new Audio();
	audio.preload = 'metadata';
	try { audio.preservesPitch = true; audio.mozPreservesPitch = true; audio.webkitPreservesPitch = true; } catch ( e ) {}

	/* ---------- the frame ---------- */
	var ui = {};
	function build() {
		document.body.classList.add( 'tcc-app-on' );
		root.innerHTML = '';
		ui.app = h( 'div', { class: 'ta' } );
		/* top */
		ui.close = h( 'a', { class: 'ta-icon', href: D.hub, 'aria-label': 'Back to Backstage', html: I.down, onclick: function () { flushPlay(); } } );
		ui.eyebrow = h( 'span', { class: 'ta-top__eyebrow' } );
		ui.ttl = h( 'span', { class: 'ta-top__title' }, [ 'Practice' ] );
		ui.partBtn = h( 'button', { type: 'button', class: 'ta-chip', onclick: openParts, 'aria-haspopup': 'dialog' } );
		ui.app.appendChild( h( 'header', { class: 'ta-top' }, [ ui.close, h( 'button', { type: 'button', class: 'ta-top__mid', onclick: openSongs, 'aria-haspopup': 'dialog' }, [ ui.eyebrow, ui.ttl ] ), ui.partBtn ] ) );
		/* stage */
		ui.stream = h( 'div', { class: 'ta-stream', tabindex: '-1' } );
		ui.followBtn = h( 'button', { type: 'button', class: 'ta-follow', hidden: true, onclick: function () { setFollow( true ); scrollToActive( true ); } }, [ 'Back to now' ] );
		ui.side = h( 'aside', { class: 'ta-side', 'aria-label': 'Songs' } );
		ui.music = h( 'div', { class: 'ta-music', hidden: true } );
		ui.app.appendChild( h( 'main', { class: 'ta-stage' }, [ h( 'div', { class: 'ta-streamwrap' }, [ ui.stream, ui.music, ui.followBtn ] ), ui.side ] ) );
		/* dock */
		ui.scrub = h( 'div', { class: 'ta-scrub', role: 'slider', tabindex: '0', 'aria-label': 'Position in the song' } );
		ui.rail = h( 'div', { class: 'ta-scrub__rail' } );
		ui.fill = h( 'div', { class: 'ta-scrub__fill' } );
		ui.loopBand = h( 'div', { class: 'ta-scrub__loop', hidden: true } );
		ui.marks = h( 'div', { class: 'ta-scrub__marks' } );
		ui.knob = h( 'div', { class: 'ta-scrub__knob' } );
		ui.scrub.appendChild( ui.rail ); ui.scrub.appendChild( ui.loopBand ); ui.scrub.appendChild( ui.fill ); ui.scrub.appendChild( ui.marks ); ui.scrub.appendChild( ui.knob );
		ui.tcur = h( 'span', {}, [ '0:00' ] ); ui.tdur = h( 'span', {}, [ '0:00' ] );
		ui.play = h( 'button', { type: 'button', class: 'ta-play', 'aria-label': 'Play', html: I.play, onclick: toggle } );
		ui.rateBtn = h( 'button', { type: 'button', class: 'ta-tool', onclick: cycleRate, 'aria-label': 'Speed' }, [ h( 'b', {}, [ '1×' ] ), h( 'span', {}, [ 'Speed' ] ) ] );
		ui.loopBtn = h( 'button', { type: 'button', class: 'ta-tool', onclick: loopTap, 'aria-label': 'Loop a section' }, [ h( 'b', { html: I.loop } ), h( 'span', {}, [ 'Loop' ] ) ] );
		ui.altBtn = h( 'button', { type: 'button', class: 'ta-tool', onclick: toggleAlt }, [ h( 'b', { html: I.people } ), h( 'span', {}, [ 'Everyone' ] ) ] );
		ui.noteBtn = h( 'button', { type: 'button', class: 'ta-tool', onclick: openNote }, [ h( 'b', { html: I.pen } ), h( 'span', {}, [ D.canEdit ? 'Note' : 'My note' ] ) ] );
		ui.songsBtn = h( 'button', { type: 'button', class: 'ta-tool ta-tool--songs', onclick: openSongs }, [ h( 'b', { html: I.list } ), h( 'span', {}, [ 'Songs' ] ) ] );
		ui.viewBtn = h( 'button', { type: 'button', class: 'ta-tool', onclick: function () { setView( S.view === 'music' ? 'notes' : 'music' ); } }, [ h( 'b', { html: I.score } ), h( 'span', {}, [ 'Music' ] ) ] );
		ui.strip = h( 'button', { type: 'button', class: 'ta-strip', hidden: true, onclick: function () { setView( 'notes' ); } } );
		ui.app.appendChild( h( 'footer', { class: 'ta-dock' }, [
			ui.strip,
			h( 'div', { class: 'ta-dock__scrub' }, [ ui.tcur, ui.scrub, ui.tdur ] ),
			h( 'div', { class: 'ta-dock__main' }, [
				h( 'button', { type: 'button', class: 'ta-icon ta-icon--dim', 'aria-label': 'Back five seconds', html: I.back5, onclick: function () { seek( audio.currentTime - 5 ); } } ),
				h( 'button', { type: 'button', class: 'ta-icon', 'aria-label': 'Previous song', html: I.prev, onclick: function () { step( -1 ); } } ),
				ui.play,
				h( 'button', { type: 'button', class: 'ta-icon', 'aria-label': 'Next song', html: I.next, onclick: function () { step( 1 ); } } ),
				h( 'span', { class: 'ta-icon ta-icon--spacer' } )
			] ),
			h( 'div', { class: 'ta-dock__tools' }, [ ui.viewBtn, ui.rateBtn, ui.loopBtn, ui.altBtn, ui.noteBtn, ui.songsBtn ] )
		] ) );
		ui.toast = h( 'div', { class: 'ta-toast', role: 'status', 'aria-live': 'polite' } );
		ui.app.appendChild( ui.toast );
		root.appendChild( ui.app );

		ui.scrub.addEventListener( 'pointerdown', scrubStart );
		ui.scrub.addEventListener( 'keydown', function ( e ) { if ( e.key === 'ArrowLeft' ) { seek( audio.currentTime - 5 ); } if ( e.key === 'ArrowRight' ) { seek( audio.currentTime + 5 ); } } );
		[ 'wheel', 'touchmove' ].forEach( function ( ev ) { ui.stream.addEventListener( ev, function () { if ( current() ) { setFollow( false ); } }, { passive: true } ); } );
		paintTop(); paintStream(); paintSide(); paintTools();
	}

	function toast( text ) {
		ui.toast.textContent = text; ui.toast.classList.add( 'is-on' );
		clearTimeout( toast.t ); toast.t = setTimeout( function () { ui.toast.classList.remove( 'is-on' ); }, 2600 );
	}

	/* ---------- top ---------- */
	function paintTop() {
		var t = current();
		ui.eyebrow.textContent = ( S.list ? ( S.list.now ? '' : S.list.cantata.year + ' · ' ) : '' ) + ( S.list ? S.list.cantata.title : D.cantata.title );
		ui.ttl.textContent = t ? ( t.song >= 0 ? ( t.song + 1 ) + '. ' : '' ) + t.title : 'Choose a song';
		ui.partBtn.innerHTML = '';
		ui.partBtn.appendChild( h( 'span', {}, [ S.alt && altTrack() ? 'Everyone' : shortLabel( S.list ? S.list.part : S.part ) ] ) );
		ui.partBtn.appendChild( h( 'span', { class: 'ta-chip__caret', html: I.down } ) );
		document.title = ( t ? t.title + ' · ' : '' ) + 'Practice';
	}

	/* ---------- the stream ---------- */
	function cuesForCurrent() {
		var t = current(); if ( ! t || t.song < 0 || ! S.list.now ) { return []; }
		var part = countPart();
		var out = ( D.cues || [] ).filter( function ( c ) { return c.song === t.song && ( ! c.parts || ! c.parts.length || c.parts.indexOf( part ) !== -1 ); } ).map( function ( c ) { return { t: +c.t, text: c.text, kind: 'cue', id: c.id, parts: c.parts || [] }; } );
		S.notes.filter( function ( n ) { return +n.song === t.song; } ).forEach( function ( n ) { out.push( { t: +n.t, text: n.text, kind: 'mine', id: n.id } ); } );
		return out.sort( function ( a, b ) { return a.t - b.t; } );
	}
	var CUES = [];
	function paintStream() {
		var s = ui.stream; s.innerHTML = '';
		var t = current();
		S.active = -2;
		if ( ! t ) {
			var L = LISTS[ 'now:' + S.part ];
			var nextTracks = L && NEXT ? L.tracks.filter( function ( x ) { return NEXT.full || NEXT.songs.indexOf( x.song ) !== -1; } ) : [];
			s.appendChild( h( 'div', { class: 'ta-hello' }, [
				h( 'span', { class: 'ta-hello__eyebrow' }, [ D.cantata.year + ' · ' + D.cantata.title ] ),
				h( 'h1', { class: 'ta-hello__h' }, [ S.who ? 'Ready when you are, ' + S.who.name.split( ' ' )[ 0 ] + '.' : 'Ready when you are.' ] ),
				h( 'p', { class: 'ta-hello__p' }, [ 'Pick a song and press play. Notes from the director scroll by here as the music reaches them.' ] ),
				h( 'div', { class: 'ta-hello__btns' }, [
					nextTracks.length ? h( 'button', { type: 'button', class: 'ta-btn ta-btn--gold', onclick: function () { playList( L.key, L.tracks.indexOf( nextTracks[ 0 ] ), NEXT.full ? null : NEXT.songs ); } }, [ h( 'span', { html: I.play } ), NEXT.full ? 'Play the whole work' : 'Play ' + ( nextDow || 'next rehearsal' ) + '’s songs' ] ) : null,
					L ? h( 'button', { type: 'button', class: 'ta-btn' + ( nextTracks.length ? '' : ' ta-btn--gold' ), onclick: openSongs }, [ 'Choose a song' ] ) : h( 'p', { class: 'ta-hello__p' }, [ 'No ' + label( S.part ) + ' tracks for this year yet.' ] )
				] )
			] ) );
			paintMarks();
			return;
		}
		CUES = cuesForCurrent();
		var st = S.list.now && t.song >= 0 ? stateOf( t.song ) : '';
		var tags = [];
		if ( S.list.now && isFocus( t.song ) ) { tags.push( h( 'span', { class: 'ta-tag ta-tag--focus' }, [ '★ The director says: focus on this one' ] ) ); }
		if ( S.list.now && inNext( t.song ) ) { tags.push( h( 'span', { class: 'ta-tag' }, [ 'On ' + NEXT.date ] ) ); }
		if ( ! S.list.now ) { tags.push( h( 'span', { class: 'ta-tag' }, [ 'From ' + S.list.cantata.year ] ) ); }
		var head = h( 'section', { class: 'ta-songhead' + ( CUES.length ? '' : ' is-alone' ) }, [
			h( 'span', { class: 'ta-songhead__num' }, [ t.song >= 0 ? String( t.song + 1 ) : '♪' ] ),
			h( 'h1', { class: 'ta-songhead__title' }, [ t.title ] ),
			h( 'p', { class: 'ta-songhead__sub' }, [ ( S.alt && altTrack() ? 'Everyone' : label( S.list.part ) ) + ' · ' + fmt( t.dur || audio.duration ) + ( CUES.length ? ' · ' + CUES.length + ( CUES.length === 1 ? ' note' : ' notes' ) : '' ) ] ),
			tags.length ? h( 'div', { class: 'ta-songhead__tags' }, tags ) : null,
			S.who && st !== '' ? feelRow( t.song, st ) : null
		] );
		s.appendChild( head );
		if ( ! CUES.length ) {
			s.appendChild( h( 'p', { class: 'ta-empty' }, [ D.canEdit ? 'No notes on this song yet. Tap “Note” while it plays to drop one at that moment.' : S.who ? 'No notes on this song yet. Tap “My note” while it plays to leave yourself one.' : 'No notes on this song yet.' ] ) );
		} else {
			var ol = h( 'ol', { class: 'ta-notes' } );
			CUES.forEach( function ( c, i ) {
				var canX = ( c.kind === 'mine' && S.who ) || ( c.kind === 'cue' && D.canEdit );
				ol.appendChild( h( 'li', { class: 'ta-note ta-note--' + c.kind, 'data-i': i }, [
					h( 'button', { type: 'button', class: 'ta-note__body', onclick: function () { seek( c.t - 1.5 ); if ( audio.paused ) { audio.play().catch( function () {} ); } setFollow( true ); } }, [
						h( 'span', { class: 'ta-note__meta' }, [ h( 'span', { class: 'ta-note__t' }, [ fmt( c.t ) ] ), h( 'span', { class: 'ta-note__who' }, [ c.kind === 'cue' ? ( c.parts.length && c.parts.length < 4 ? 'Director · ' + c.parts.map( shortLabel ).join( ', ' ) : 'Director' ) : 'Your note' ] ), h( 'span', { class: 'ta-note__in' } ) ] ),
						h( 'span', { class: 'ta-note__text' }, [ c.text ] )
					] ),
					canX ? h( 'button', { type: 'button', class: 'ta-note__x', 'aria-label': 'Remove this note', html: I.x, onclick: function () { removeNote( c ); } } ) : null
				] ) );
			} );
			s.appendChild( ol );
		}
		s.appendChild( h( 'div', { class: 'ta-tail' }, [ h( 'span', {}, [ 'End of ' + t.title ] ), nextInList() ? h( 'button', { type: 'button', class: 'ta-link', onclick: function () { step( 1 ); } }, [ 'Next: ' + nextInList().title ] ) : null ] ) );
		s.scrollTop = 0;
		paintMarks(); tickStream( true );
	}
	function feelRow( song, st ) {
		var row = h( 'div', { class: 'ta-feel', role: 'group', 'aria-label': 'How is this song going?' } );
		[ [ 'some', 'working', 'Getting there' ], [ 'good', 'ok', 'Feeling good' ], [ 'help', 'help', 'I’d like help' ] ].forEach( function ( o ) {
			var on = st === o[ 0 ];
			row.appendChild( h( 'button', { type: 'button', class: 'ta-feel__b ta-feel__b--' + o[ 0 ] + ( on ? ' is-on' : '' ), 'aria-pressed': on ? 'true' : 'false', onclick: function () { setFlag( song, on ? '' : o[ 1 ] ); } }, [ h( 'span', { html: ring( o[ 0 ] ) } ), o[ 2 ] ] ) );
		} );
		return row;
	}
	function setFlag( song, flag ) {
		var p = countPart();
		var k = rowKey( song, p ); S.rows[ k ] = S.rows[ k ] || {}; S.rows[ k ].flag = flag;
		var old = ui.stream.querySelector( '.ta-feel' );
		if ( old ) { old.replaceWith( feelRow( song, stateOf( song ) ) ); }
		paintSide();
		api( 'flag', 'POST', { cantata: D.cantata.id, song: song, part: p, flag: flag } );
		if ( flag === 'help' ) { toast( 'Noted — the director will see you asked.' ); }
	}
	function nextInList() { if ( ! S.list ) { return null; } return S.list.tracks[ S.idx + 1 ] || null; }

	/* which note is "now", and keep it centred */
	function tickStream( force ) {
		tickMusic( force );
		if ( ! CUES.length ) { paintStrip( -1 ); return; }
		var now = audio.currentTime || 0, a = -1;
		for ( var i = 0; i < CUES.length; i++ ) { if ( CUES[ i ].t <= now + 0.4 ) { a = i; } }
		var items = ui.stream.querySelectorAll( '.ta-note' );
		var upcoming = a + 1 < CUES.length ? a + 1 : -1;
		if ( upcoming >= 0 && items[ upcoming ] ) {
			var left = CUES[ upcoming ].t - now;
			items[ upcoming ].querySelector( '.ta-note__in' ).textContent = left > 0.5 && left < 600 ? 'in ' + fmt( Math.ceil( left ) ) : '';
		}
		if ( a === S.active && ! force ) { return; }
		S.active = a;
		paintStrip( a );
		items.forEach( function ( li, i ) {
			li.classList.toggle( 'is-past', i < a );
			li.classList.toggle( 'is-on', i === a );
			li.classList.toggle( 'is-next', i === upcoming );
			if ( i !== upcoming ) { li.querySelector( '.ta-note__in' ).textContent = ''; }
		} );
		if ( a >= 0 && navigator.vibrate && ! audio.paused && ! force && CUES[ a ].kind === 'cue' ) { /* a quiet nudge would go here; left off on purpose */ }
		scrollToActive();
	}
	function scrollToActive( smoothAnyway ) {
		if ( ! S.follow ) { return; }
		var s = ui.stream, target;
		if ( S.active < 0 ) { target = 0; }
		else {
			var el = s.querySelectorAll( '.ta-note' )[ S.active ];
			if ( ! el ) { return; }
			target = el.offsetTop - s.clientHeight * 0.38 + el.offsetHeight / 2;
		}
		s.scrollTo( { top: Math.max( 0, target ), behavior: reduceMotion && ! smoothAnyway ? 'auto' : 'smooth' } );
	}
	var followTimer = null;
	function setFollow( on ) {
		S.follow = on;
		ui.followBtn.hidden = on || ! CUES.length || audio.paused;
		clearTimeout( followTimer );
		if ( ! on ) { followTimer = setTimeout( function () { setFollow( true ); scrollToActive(); }, 7000 ); }
	}


	/* ---------- the music: score pages that turn with the track ---------- */
	var SC = D.score || null;
	var M = { pdf: null, loading: null, manual: null, shown: '', zoom: 1, rec: null, cache: {} };
	function scoreRange() {
		var t = current();
		if ( ! SC || ! t || t.song < 0 || ! S.list || ! S.list.now ) { return null; }
		var r = SC.songs[ t.song ];
		return r ? { a: r[ 0 ], b: r[ 1 ] } : null;
	}
	function turnsFor() { var t = current(); if ( M.rec ) { return M.rec.times; } return t && SC && SC.turns[ t.song ] ? SC.turns[ t.song ] : []; }
	function pageAt( time ) {
		var r = scoreRange(); if ( ! r ) { return 0; }
		var n = 0; turnsFor().forEach( function ( x ) { if ( x <= time ) { n++; } } );
		return Math.min( r.b, r.a + n );
	}
	function printedNo( p ) { return p + ( SC ? SC.offset : 0 ); }
	function setView( v ) {
		S.view = v; ls( 'tcc_view', v );
		if ( v === 'music' && scoreRange() ) { loadScore(); }
		paintTools(); paintMarks();
	}
	function applyView() {
		var music = S.view === 'music' && !! scoreRange();
		ui.music.hidden = ! music;
		ui.stream.hidden = music;
		ui.strip.hidden = ! music;
		ui.app.classList.toggle( 'is-music', music );
		if ( music ) { if ( M.pdf ) { tickMusic( true ); } else { loadScore(); } paintStrip( S.active ); }
	}
	function loadScore() {
		if ( M.pdf || M.loading ) { return M.loading; }
		ui.music.innerHTML = '';
		var msg = h( 'p', { class: 'ta-music__msg' }, [ 'Opening the score…' ] );
		ui.music.appendChild( msg );
		M.loading = new Promise( function ( res, rej ) {
			if ( window.pdfjsLib ) { res( window.pdfjsLib ); return; }
			var sc = document.createElement( 'script' ); sc.src = SC.lib;
			sc.onload = function () { window.pdfjsLib.GlobalWorkerOptions.workerSrc = SC.worker; res( window.pdfjsLib ); };
			sc.onerror = rej; document.head.appendChild( sc );
		} ).then( function ( L ) {
			var task = L.getDocument( { url: SC.url, disableRange: true, disableStream: false } );
			task.onProgress = function ( p ) { if ( p.total ) { msg.textContent = 'Opening the score… ' + Math.round( p.loaded / p.total * 100 ) + '%'; } };
			return task.promise;
		} ).then( function ( doc ) { M.pdf = doc; buildMusic(); tickMusic( true ); } ).catch( function () { msg.textContent = 'Couldn’t open the score. Check your connection and try again.'; M.loading = null; } );
		return M.loading;
	}
	function buildMusic() {
		ui.music.innerHTML = '';
		ui.pageInfo = h( 'span', { class: 'ta-music__info' } );
		ui.pageBack = h( 'button', { type: 'button', class: 'ta-icon ta-icon--sm', 'aria-label': 'Previous page', html: I.left, onclick: function () { manualPage( -1 ); } } );
		ui.pageNext = h( 'button', { type: 'button', class: 'ta-icon ta-icon--sm', 'aria-label': 'Next page', html: I.right, onclick: function () { manualPage( 1 ); } } );
		ui.pageNow = h( 'button', { type: 'button', class: 'ta-link ta-music__now', hidden: true, onclick: function () { M.manual = null; tickMusic( true ); } }, [ 'Back to now' ] );
		ui.recBtn = D.canEdit ? h( 'button', { type: 'button', class: 'ta-music__set', onclick: startRec }, [ 'Set page turns' ] ) : null;
		ui.pages = h( 'div', { class: 'ta-pages' } );
		ui.pagesIn = h( 'div', { class: 'ta-pages__in' } );
		ui.pages.appendChild( ui.pagesIn );
		ui.music.appendChild( h( 'div', { class: 'ta-music__bar' }, [ ui.pageBack, ui.pageInfo, ui.pageNext, ui.pageNow, h( 'span', { class: 'ta-music__sp' } ), ui.recBtn ] ) );
		ui.music.appendChild( ui.pages );
		ui.recBar = h( 'div', { class: 'ta-rec', hidden: true } );
		ui.music.appendChild( ui.recBar );
		gestures( ui.pages );
		window.addEventListener( 'resize', function () { M.shown = ''; tickMusic( true ); } );
	}
	function manualPage( d ) {
		var r = scoreRange(); if ( ! r ) { return; }
		var base = M.manual !== null ? M.manual : pageAt( audio.currentTime );
		M.manual = Math.max( 1, Math.min( SC.pages, base + d ) );
		tickMusic( true );
	}
	function spread() { var w = ui.pages.clientWidth, hh = ui.pages.clientHeight; return w >= 820 && w > hh * 1.2; }
	function tickMusic( force ) {
		if ( S.view !== 'music' || ! M.pdf || ui.music.hidden ) { return; }
		var r = scoreRange(); if ( ! r ) { return; }
		var now = audio.currentTime || 0;
		var live = pageAt( now );
		if ( M.manual !== null && M.manual === live ) { M.manual = null; }
		var p = M.manual !== null ? M.manual : live;
		var two = spread();
		var pages = two ? [ p, Math.min( M.pdf.numPages, p + 1 ) ] : [ p ];
		if ( two && pages[ 1 ] === pages[ 0 ] ) { pages = [ p ]; }
		/* the little line above the page: where we are and when it turns */
		var turns = turnsFor(), nextT = null;
		for ( var i = 0; i < turns.length; i++ ) { if ( turns[ i ] > now ) { nextT = turns[ i ]; break; } }
		var info = 'p. ' + printedNo( p ) + ( two && pages[ 1 ] ? '–' + printedNo( pages[ 1 ] ) : '' );
		if ( M.rec ) { info += ' · recording turns'; }
		else if ( M.manual !== null ) { info += ' · looking ahead'; }
		else if ( nextT !== null && pageAt( nextT ) !== p ) { info += ' · turns in ' + fmt( Math.ceil( nextT - now ) ); }
		else if ( ! turns.length && r.b > r.a ) { info += D.canEdit ? ' · page turns not set yet' : ''; }
		ui.pageInfo.textContent = info;
		ui.pageNow.hidden = M.manual === null;
		ui.pageBack.disabled = p <= 1; ui.pageNext.disabled = p >= M.pdf.numPages;
		var key = pages.join( ',' ) + '|' + ui.pages.clientWidth + 'x' + ui.pages.clientHeight + '|' + M.zoom;
		if ( key === M.shown ) { return; }
		var turned = M.shown && M.shown.split( '|' )[ 0 ] !== pages.join( ',' );
		M.shown = key;
		drawPages( pages, turned );
	}
	function drawPages( pages, turned ) {
		var W = ui.pages.clientWidth - 16, H = ui.pages.clientHeight - 16;
		var dpr = Math.min( 3, window.devicePixelRatio || 1 );
		Promise.all( pages.map( function ( n ) { return M.pdf.getPage( n ); } ) ).then( function ( list ) {
			var vp = list[ 0 ].getViewport( { scale: 1 } );
			var fit = Math.min( W / ( vp.width * list.length + ( list.length - 1 ) * 12 ), H / vp.height );
			if ( ! ( fit > 0 ) ) { return; }
			var scale = fit * M.zoom;
			var frag = document.createDocumentFragment();
			list.forEach( function ( page, k ) {
				var v = page.getViewport( { scale: scale * dpr } );
				var c = document.createElement( 'canvas' );
				c.width = Math.floor( v.width ); c.height = Math.floor( v.height );
				c.style.width = Math.floor( v.width / dpr ) + 'px'; c.style.height = Math.floor( v.height / dpr ) + 'px';
				c.className = 'ta-page' + ( k === 0 ? ' is-current' : '' );
				page.render( { canvasContext: c.getContext( '2d' ), viewport: v } );
				frag.appendChild( c );
			} );
			ui.pagesIn.innerHTML = '';
			ui.pagesIn.appendChild( frag );
			ui.pagesIn.classList.toggle( 'is-zoomed', M.zoom > 1 );
			ui.pages.classList.toggle( 'is-zoomed', M.zoom > 1 );
			if ( turned && ! reduceMotion ) { ui.pagesIn.classList.remove( 'is-turn' ); void ui.pagesIn.offsetWidth; ui.pagesIn.classList.add( 'is-turn' ); }
			if ( M.zoom === 1 ) { ui.pages.scrollTop = 0; ui.pages.scrollLeft = 0; }
			// get the next page ready so the turn is instant
			var after = pages[ pages.length - 1 ] + 1;
			if ( after <= M.pdf.numPages ) { M.pdf.getPage( after ); }
		} );
	}
	/* pinch to zoom, double-tap to zoom, swipe for pages */
	function gestures( el ) {
		var pts = {}, start = null, tap = 0, swipe = null;
		el.addEventListener( 'pointerdown', function ( e ) {
			pts[ e.pointerId ] = { x: e.clientX, y: e.clientY };
			var ids = Object.keys( pts );
			if ( ids.length === 2 ) { var a = pts[ ids[ 0 ] ], b = pts[ ids[ 1 ] ]; start = { d: Math.hypot( a.x - b.x, a.y - b.y ), z: M.zoom }; swipe = null; }
			else if ( ids.length === 1 ) { swipe = M.zoom === 1 ? { x: e.clientX, y: e.clientY, t: Date.now() } : null; }
		} );
		el.addEventListener( 'pointermove', function ( e ) {
			if ( ! pts[ e.pointerId ] ) { return; }
			pts[ e.pointerId ] = { x: e.clientX, y: e.clientY };
			var ids = Object.keys( pts );
			if ( ids.length === 2 && start ) {
				var a = pts[ ids[ 0 ] ], b = pts[ ids[ 1 ] ];
				var z = Math.max( 1, Math.min( 4, start.z * Math.hypot( a.x - b.x, a.y - b.y ) / start.d ) );
				ui.pagesIn.style.transform = 'scale(' + ( z / M.zoom ) + ')';
				start.next = z;
			}
		} );
		function end( e ) {
			var had = pts[ e.pointerId ]; delete pts[ e.pointerId ];
			if ( start && Object.keys( pts ).length < 2 ) {
				ui.pagesIn.style.transform = '';
				if ( start.next ) { zoomTo( start.next ); }
				start = null; return;
			}
			if ( swipe && had ) {
				var dx = had.x - swipe.x, dy = had.y - swipe.y, dt = Date.now() - swipe.t;
				if ( Math.abs( dx ) > 60 && Math.abs( dx ) > Math.abs( dy ) * 1.5 && dt < 600 ) { manualPage( dx < 0 ? 1 : -1 ); swipe = null; return; }
				if ( Math.abs( dx ) < 10 && Math.abs( dy ) < 10 ) {
					if ( Date.now() - tap < 320 ) { zoomTo( M.zoom > 1 ? 1 : 2.2 ); tap = 0; } else { tap = Date.now(); }
				}
			}
			swipe = null;
		}
		el.addEventListener( 'pointerup', end ); el.addEventListener( 'pointercancel', end );
	}
	function zoomTo( z ) {
		var el = ui.pages, cx = el.scrollLeft + el.clientWidth / 2, cy = el.scrollTop + el.clientHeight / 2, ratio = z / M.zoom;
		M.zoom = Math.round( z * 100 ) / 100;
		M.shown = '';
		tickMusic( true );
		setTimeout( function () { el.scrollLeft = cx * ratio - el.clientWidth / 2; el.scrollTop = cy * ratio - el.clientHeight / 2; }, 60 );
	}
	/* the current note, as one line above the controls */
	function paintStrip( a ) {
		if ( ! ui.strip || ui.strip.hidden ) { return; }
		ui.strip.innerHTML = '';
		var c = a >= 0 ? CUES[ a ] : null;
		var nx = CUES[ a + 1 ] || null;
		if ( c ) {
			ui.strip.className = 'ta-strip ta-strip--' + c.kind;
			ui.strip.appendChild( h( 'span', { class: 'ta-strip__k' }, [ fmt( c.t ) + ( c.kind === 'cue' ? ' · Director' : ' · You' ) ] ) );
			ui.strip.appendChild( h( 'span', { class: 'ta-strip__t' }, [ c.text ] ) );
		} else if ( nx ) {
			ui.strip.className = 'ta-strip ta-strip--quiet';
			ui.strip.appendChild( h( 'span', { class: 'ta-strip__k' }, [ 'First note at ' + fmt( nx.t ) ] ) );
			ui.strip.appendChild( h( 'span', { class: 'ta-strip__t' }, [ nx.text ] ) );
		} else {
			ui.strip.className = 'ta-strip ta-strip--quiet';
			ui.strip.appendChild( h( 'span', { class: 'ta-strip__t' }, [ CUES.length ? '' : 'No notes on this song yet.' ] ) );
		}
	}
	/* director: record the page turns by tapping along */
	function startRec() {
		var r = scoreRange(); if ( ! r ) { return; }
		M.rec = { times: [], old: ( SC.turns[ current().song ] || [] ).slice() };
		M.manual = null;
		seek( 0 ); audio.play().catch( function () {} );
		paintRec(); tickMusic( true ); paintMarks();
	}
	function paintRec() {
		var bar = ui.recBar; bar.innerHTML = '';
		if ( ! M.rec ) { bar.hidden = true; if ( ui.recBtn ) { ui.recBtn.hidden = false; } return; }
		bar.hidden = false; if ( ui.recBtn ) { ui.recBtn.hidden = true; }
		var r = scoreRange(), need = r ? r.b - r.a : 0;
		bar.appendChild( h( 'div', { class: 'ta-rec__msg' }, [ h( 'b', {}, [ 'Tap “Turn” each time the page should turn.' ] ), h( 'span', {}, [ M.rec.times.length + ' of ' + need + ' turns' + ( M.rec.times.length ? ' · last at ' + fmt( M.rec.times[ M.rec.times.length - 1 ] ) : '' ) ] ) ] ) );
		bar.appendChild( h( 'div', { class: 'ta-rec__go' }, [
			h( 'button', { type: 'button', class: 'ta-rec__turn', disabled: M.rec.times.length >= need ? true : null, onclick: function () { M.rec.times.push( Math.max( 0, Math.round( ( audio.currentTime - 0.3 ) * 10 ) / 10 ) ); paintRec(); tickMusic( true ); paintMarks(); } }, [ 'Turn', h( 'span', { html: I.right } ) ] ),
			h( 'button', { type: 'button', class: 'ta-btn', disabled: M.rec.times.length ? null : true, onclick: function () { M.rec.times.pop(); paintRec(); tickMusic( true ); paintMarks(); } }, [ 'Undo' ] ),
			h( 'button', { type: 'button', class: 'ta-btn ta-btn--gold', onclick: saveRec }, [ 'Save' ] ),
			h( 'button', { type: 'button', class: 'ta-link', onclick: function () { M.rec = null; paintRec(); tickMusic( true ); paintMarks(); } }, [ 'Cancel' ] )
		] ) );
	}
	function saveRec() {
		var t = current(), times = M.rec.times.slice();
		api( 'turns', 'POST', { cantata: D.cantata.id, song: t.song, times: times } ).then( function ( r ) {
			if ( r && r.ok ) { SC.turns[ t.song ] = r.times; M.rec = null; paintRec(); tickMusic( true ); paintMarks(); toast( 'Page turns saved for ' + t.title ); }
			else { toast( 'Couldn’t save — try again.' ); }
		} );
	}

	/* ---------- the side list (tablet) and the songs sheet (phone) ---------- */
	function songList( part, inSheet ) {
		var wrap = h( 'div', { class: 'ta-list' } );
		var L = LISTS[ 'now:' + part ];
		if ( ! S.who && D.signin && window.tccSignin ) {
			wrap.appendChild( h( 'div', { class: 'ta-signin' }, [ h( 'span', {}, [ 'Sign in to keep your notes and mark how songs are going.' ] ), h( 'button', { type: 'button', class: 'ta-link', onclick: signIn }, [ 'Sign in' ] ) ] ) );
		}
		if ( L && NEXT && ! NEXT.full && NEXT.songs.length ) {
			var nt = L.tracks.filter( function ( x ) { return NEXT.songs.indexOf( x.song ) !== -1; } );
			if ( nt.length ) {
				wrap.appendChild( h( 'button', { type: 'button', class: 'ta-queue', onclick: function () { playList( L.key, L.tracks.indexOf( nt[ 0 ] ), NEXT.songs ); closeSheet(); } }, [
					h( 'span', { class: 'ta-queue__k' }, [ 'For ' + NEXT.date ] ),
					h( 'span', { class: 'ta-queue__t' }, [ 'Play these ' + nt.length + ' in a row' ] ),
					h( 'span', { class: 'ta-queue__i', html: I.play } )
				] ) );
			}
		}
		if ( ! L ) { wrap.appendChild( h( 'p', { class: 'ta-empty' }, [ 'No ' + label( part ) + ' tracks for this year yet.' ] ) ); }
		else { wrap.appendChild( trackRows( L, true ) ); }
		Object.keys( LISTS ).filter( function ( k ) { return k.indexOf( 'past:' ) === 0 && LISTS[ k ].part === part; } ).forEach( function ( k ) {
			var PL = LISTS[ k ];
			wrap.appendChild( h( 'details', { class: 'ta-year', open: S.list && S.list.key === k ? true : null }, [ h( 'summary', {}, [ PL.cantata.year + ' · ' + PL.cantata.title ] ), trackRows( PL, false ) ] ) );
		} );
		if ( inSheet ) { wrap.appendChild( h( 'p', { class: 'ta-fine' }, [ S.who ? 'The circles are just for you — how each song is going. Only you see them.' : '' ] ) ); }
		return wrap;
	}
	function trackRows( L, now ) {
		var ol = h( 'ol', { class: 'ta-rows' } );
		L.tracks.forEach( function ( t, i ) {
			var on = S.list && S.list.key === L.key && S.idx === i;
			var st = now && S.who && t.song >= 0 ? stateOf( t.song, L.part ) : '';
			ol.appendChild( h( 'li', {}, [ h( 'button', { type: 'button', class: 'ta-row' + ( on ? ' is-on' : '' ) + ( now && inNext( t.song ) ? ' is-next' : '' ), onclick: function () { if ( on ) { toggle(); } else { playList( L.key, i ); } closeSheet(); } }, [
				h( 'span', { class: 'ta-row__n' }, [ t.song >= 0 ? String( t.song + 1 ) : '·' ] ),
				h( 'span', { class: 'ta-row__t' }, [
					h( 'b', {}, [ t.title ] ),
					h( 'small', {}, [ fmt( t.dur ), now && isFocus( t.song ) ? h( 'span', { class: 'ta-row__star', title: 'Focus on this one' }, [ '★' ] ) : null, now && inNext( t.song ) ? h( 'span', { class: 'ta-row__tag' }, [ nextDow || 'Next' ] ) : null ] )
				] ),
				st ? h( 'span', { class: 'ta-ring ta-ring--' + st, title: STATE[ st ], html: ring( st ) } ) : null,
				on ? h( 'span', { class: 'ta-row__eq', 'aria-hidden': 'true' }, [ h( 'i' ), h( 'i' ), h( 'i' ) ] ) : null
			] ) ] ) );
		} );
		return ol;
	}
	function paintSide() {
		ui.side.innerHTML = '';
		ui.side.appendChild( h( 'div', { class: 'ta-side__head' }, [ h( 'span', {}, [ label( S.part ) + ( VOICE.indexOf( S.part ) !== -1 ? ' tracks' : '' ) ] ), h( 'button', { type: 'button', class: 'ta-link', onclick: openParts }, [ 'Change part' ] ) ] ) );
		ui.side.appendChild( songList( S.part, false ) );
		if ( ui.sheetList ) { ui.sheetList.replaceWith( ui.sheetList = songList( S.part, true ) ); }
	}

	/* ---------- sheets ---------- */
	var sheet = null;
	function openSheet( title, body, cls ) {
		closeSheet();
		var box = h( 'div', { class: 'ta-sheet__box' }, [
			h( 'div', { class: 'ta-sheet__grab', 'aria-hidden': 'true' } ),
			h( 'div', { class: 'ta-sheet__head' }, [ h( 'b', {}, [ title ] ), h( 'button', { type: 'button', class: 'ta-icon ta-icon--sm', 'aria-label': 'Close', html: I.x, onclick: closeSheet } ) ] ),
			body
		] );
		sheet = h( 'div', { class: 'ta-sheet' + ( cls ? ' ' + cls : '' ), role: 'dialog', 'aria-modal': 'true', 'aria-label': title, onclick: function ( e ) { if ( e.target === sheet ) { closeSheet(); } } }, [ box ] );
		ui.app.appendChild( sheet );
		requestAnimationFrame( function () { sheet && sheet.classList.add( 'is-open' ); } );
		var f = box.querySelector( 'input, textarea' ); if ( f ) { setTimeout( function () { f.focus(); }, 60 ); }
	}
	function closeSheet() { if ( ! sheet ) { return; } var s = sheet; sheet = null; ui.sheetList = null; s.classList.remove( 'is-open' ); setTimeout( function () { s.remove(); }, 220 ); }
	function openSongs() {
		ui.sheetList = songList( S.part, true );
		openSheet( label( S.part ) + ( VOICE.indexOf( S.part ) !== -1 ? ' tracks' : '' ), ui.sheetList, 'ta-sheet--tall' );
		var on = sheet.querySelector( '.ta-row.is-on' ); if ( on ) { on.scrollIntoView( { block: 'center' } ); }
	}
	function openParts() {
		var mine = myParts();
		var box = h( 'div', { class: 'ta-parts' } );
		ORDER.forEach( function ( p ) {
			if ( ! LISTS[ 'now:' + p ] ) { return; }
			box.appendChild( h( 'button', { type: 'button', class: 'ta-part' + ( p === S.part ? ' is-on' : '' ), onclick: function () { setPart( p ); closeSheet(); } }, [
				h( 'b', {}, [ label( p ) ] ),
				mine.indexOf( p ) !== -1 ? h( 'small', {}, [ 'Your part' ] ) : null
			] ) );
		} );
		if ( S.who ) { box.appendChild( h( 'p', { class: 'ta-fine' }, [ 'Practicing as ' + S.who.name + '. ', h( 'button', { type: 'button', class: 'ta-link', onclick: function () { closeSheet(); signIn(); } }, [ 'Not you?' ] ) ] ) ); }
		openSheet( 'Which part?', box );
	}
	function setPart( p ) {
		if ( ! LISTS[ 'now:' + p ] ) { return; }
		S.part = p; ls( 'tcc_part', p );
		paintSide(); paintTop();
		if ( ! current() ) { paintStream(); return; }
		// keep the same song going in the new part, at the same moment
		var t = current(), L = LISTS[ 'now:' + p ];
		var j = -1; L.tracks.some( function ( x, i ) { if ( x.song === t.song && t.song >= 0 ) { j = i; return true; } return false; } );
		if ( j >= 0 && S.list.now ) { var playing = ! audio.paused; flushPlay(); S.list = L; S.idx = j; S.alt = false; S.playPart = p; loadTrack( playing, true ); toast( label( p ) ); }
	}
	function signIn() {
		if ( ! D.signin || ! window.tccSignin ) { return; }
		var cfg = D.signin; cfg.who = S.who ? { id: S.who.id, name: S.who.name, onSeason: S.who.onSeason } : null;
		window.tccSignin.open( cfg, function () { loadMe( true ); } );
	}

	/* ---------- notes: write one ---------- */
	function openNote() {
		var t = current();
		if ( ! t ) { toast( 'Play a song first.' ); return; }
		if ( ! S.list.now || t.song < 0 ) { toast( 'Notes are for this year’s songs.' ); return; }
		if ( ! D.canEdit && ! S.who ) { signIn(); return; }
		var at = Math.max( 0, audio.currentTime - ( audio.paused ? 0 : 0.8 ) ); // people tap a beat late
		var kind = D.canEdit ? 'cue' : 'mine';
		var when = h( 'b', { class: 'ta-at__t' }, [ fmt( at ) ] );
		function nudge( d ) { at = Math.max( 0, Math.min( audio.duration || at + 1, at + d ) ); when.textContent = fmt( at ); }
		var input = h( 'textarea', { class: 'ta-input', rows: '2', maxlength: '200', placeholder: kind === 'cue' ? 'e.g. m. 42 — altos take the lower note here' : 'e.g. breathe before the high G' } );
		var kindRow = null, partsRow = null;
		if ( D.canEdit ) {
			var asMine = false;
			kindRow = h( 'div', { class: 'ta-seg' }, [
				h( 'button', { type: 'button', class: 'ta-seg__b is-on', onclick: function ( e ) { asMine = false; seg( e.currentTarget ); partsRow.hidden = false; } }, [ 'For the singers' ] ),
				S.who ? h( 'button', { type: 'button', class: 'ta-seg__b', onclick: function ( e ) { asMine = true; seg( e.currentTarget ); partsRow.hidden = true; } }, [ 'Just for me' ] ) : null
			] );
			partsRow = h( 'div', { class: 'ta-checks' }, [ h( 'span', {}, [ 'Who sees it:' ] ) ] );
			VOICE.forEach( function ( p ) { partsRow.appendChild( h( 'label', {}, [ h( 'input', { type: 'checkbox', value: p, checked: p === countPart() ? true : null } ), ' ' + label( p ) ] ) ); } );
			partsRow.appendChild( h( 'small', {}, [ 'none ticked = everyone' ] ) );
			kind = function () { return asMine ? 'mine' : 'cue'; };
		}
		function seg( b ) { b.parentNode.querySelectorAll( '.ta-seg__b' ).forEach( function ( x ) { x.classList.toggle( 'is-on', x === b ); } ); }
		var body = h( 'div', { class: 'ta-noteform' }, [
			h( 'div', { class: 'ta-at' }, [ h( 'button', { type: 'button', class: 'ta-at__b', 'aria-label': 'One second earlier', onclick: function () { nudge( -1 ); } }, [ '−1s' ] ), h( 'span', {}, [ 'at ', when ] ), h( 'button', { type: 'button', class: 'ta-at__b', 'aria-label': 'One second later', onclick: function () { nudge( 1 ); } }, [ '+1s' ] ) ] ),
			kindRow, input, partsRow,
			h( 'div', { class: 'ta-noteform__go' }, [ h( 'button', { type: 'button', class: 'ta-btn ta-btn--gold', onclick: save }, [ 'Save note' ] ), h( 'span', { class: 'ta-fine' }, [ audio.paused ? '' : 'The music keeps playing.' ] ) ] )
		] );
		input.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Enter' && ! e.shiftKey ) { e.preventDefault(); save(); } } );
		openSheet( D.canEdit ? 'New note' : 'Note to self', body );
		function save() {
			var text = input.value.trim(); if ( ! text ) { input.focus(); return; }
			var k = typeof kind === 'function' ? kind() : kind;
			if ( k === 'cue' ) {
				var parts = Array.prototype.map.call( partsRow.querySelectorAll( 'input:checked' ), function ( c ) { return c.value; } );
				api( 'cue', 'POST', { cantata: D.cantata.id, song: t.song, t: at, text: text, parts: parts } ).then( function ( r ) { if ( r && r.cue ) { D.cues.push( r.cue ); paintStream(); toast( 'Note saved at ' + fmt( at ) ); } else { toast( 'Couldn’t save — try again.' ); } } );
			} else {
				api( 'note', 'POST', { cantata: D.cantata.id, song: t.song, t: at, text: text } ).then( function ( r ) { if ( r && r.ok ) { S.notes.push( { id: r.id, song: t.song, t: at, text: text } ); paintStream(); toast( 'Note saved at ' + fmt( at ) ); } else { toast( 'Couldn’t save — try again.' ); } } );
			}
			closeSheet();
		}
	}
	function removeNote( c ) {
		if ( ! window.confirm( 'Remove this note?' ) ) { return; }
		if ( c.kind === 'cue' ) { api( 'cue', 'DELETE', { cantata: D.cantata.id, id: c.id } ).then( function () { D.cues = D.cues.filter( function ( x ) { return x.id !== c.id; } ); paintStream(); } ); }
		else { api( 'note', 'DELETE', { cantata: D.cantata.id, id: c.id } ).then( function () { S.notes = S.notes.filter( function ( x ) { return x.id !== c.id; } ); paintStream(); } ); }
	}

	/* ---------- playback ---------- */
	var queue = null;
	function current() { return S.list ? S.list.tracks[ S.idx ] : null; }
	function playList( key, i, only ) {
		flushPlay();
		queue = only || null;
		S.list = LISTS[ key ]; S.idx = i; S.alt = false; S.loopA = S.loopB = null;
		S.playPart = S.list.part;
		loadTrack( true );
	}
	function altTrack() {
		var t = current(); if ( ! t || t.song < 0 || ! S.list.now ) { return null; }
		var pref = S.list.part === 'listen' ? [ 'now:accompaniment', 'now:sections' ] : [ 'now:listen', 'now:accompaniment', 'now:sections' ];
		for ( var k = 0; k < pref.length; k++ ) {
			var L = LISTS[ pref[ k ] ]; if ( ! L || pref[ k ] === S.list.key ) { continue; }
			for ( var j = 0; j < L.tracks.length; j++ ) { if ( L.tracks[ j ].song === t.song ) { return { key: pref[ k ], track: L.tracks[ j ] }; } }
		}
		return null;
	}
	function loadTrack( autoplay, keepTime ) {
		var t = current(); if ( ! t ) { return; }
		var src = t;
		if ( S.alt ) { var a = altTrack(); if ( a ) { src = a.track; } else { S.alt = false; } }
		var was = keepTime ? audio.currentTime : null;
		audio.src = src.url;
		audio.playbackRate = S.rate;
		S.listened = 0; S.counted = false; S.lastT = 0;
		var resume = parseFloat( ls( 'tcc_pos_' + src.id ) || '0' );
		audio.addEventListener( 'loadedmetadata', function once() {
			audio.removeEventListener( 'loadedmetadata', once );
			if ( was !== null ) { audio.currentTime = Math.min( was, audio.duration - 1 ); }
			else if ( resume > 5 && resume < audio.duration - 10 ) { audio.currentTime = resume; toast( 'Picking up at ' + fmt( resume ) ); }
			paintScrub(); paintMarks(); tickStream( true );
		} );
		if ( S.list.now && t.song >= 0 ) { ls( 'tcc_last', JSON.stringify( { part: S.list.part, song: t.song, id: src.id, title: t.title } ) ); }
		setFollow( true );
		M.manual = null;
		paintTop(); paintStream(); paintSide(); paintTools(); paintLoop();
		mediaSession( t );
		if ( autoplay ) { audio.play().catch( function () {} ); }
	}
	function toggle() {
		if ( ! current() ) { var L = LISTS[ 'now:' + S.part ]; if ( L ) { playList( L.key, 0 ); } return; }
		if ( audio.paused ) { audio.play().catch( function () {} ); } else { audio.pause(); }
	}
	function step( dir ) {
		if ( ! S.list ) { return; }
		flushPlay();
		var n = S.idx;
		for ( var k = 0; k < S.list.tracks.length; k++ ) {
			n += dir;
			if ( n < 0 || n >= S.list.tracks.length ) { if ( dir > 0 ) { toast( 'That’s the last one.' ); } return; }
			if ( ! queue || queue.indexOf( S.list.tracks[ n ].song ) !== -1 ) { break; }
		}
		S.idx = n; S.loopA = S.loopB = null; loadTrack( true );
	}
	function seek( t ) { if ( ! audio.duration ) { return; } audio.currentTime = Math.max( 0, Math.min( audio.duration - 0.2, t ) ); paintScrub(); tickStream( true ); }
	function cycleRate() {
		var rates = [ 1, 0.85, 0.75 ], i = rates.indexOf( S.rate ); S.rate = rates[ ( i + 1 ) % rates.length ];
		audio.playbackRate = S.rate; paintTools();
		toast( S.rate === 1 ? 'Normal speed' : 'Slower — ' + Math.round( S.rate * 100 ) + '%' );
	}
	function loopTap() {
		if ( ! current() ) { return; }
		if ( S.loopA === null ) { S.loopA = audio.currentTime; toast( 'Loop starts at ' + fmt( S.loopA ) + '. Tap again where it should end.' ); }
		else if ( S.loopB === null ) { S.loopB = audio.currentTime; if ( S.loopB <= S.loopA + 1 ) { S.loopB = Math.min( audio.duration, S.loopA + 5 ); } seek( S.loopA ); toast( 'Looping ' + fmt( S.loopA ) + '–' + fmt( S.loopB ) ); }
		else { S.loopA = S.loopB = null; toast( 'Loop off' ); }
		paintTools(); paintLoop();
	}
	function toggleAlt() { if ( ! current() || ! altTrack() && ! S.alt ) { return; } S.alt = ! S.alt; var playing = ! audio.paused; loadTrack( playing, true ); toast( S.alt ? 'Everyone — hear how your part fits' : 'Back to ' + label( S.list.part ) ); }
	function paintTools() {
		ui.rateBtn.querySelector( 'b' ).textContent = S.rate + '×';
		ui.rateBtn.classList.toggle( 'is-on', S.rate !== 1 );
		ui.loopBtn.classList.toggle( 'is-on', S.loopA !== null );
		ui.loopBtn.querySelector( 'span' ).textContent = S.loopA === null ? 'Loop' : S.loopB === null ? 'Set end' : fmt( S.loopA ) + '–' + fmt( S.loopB );
		ui.altBtn.hidden = ! ( current() && ( altTrack() || S.alt ) );
		ui.altBtn.classList.toggle( 'is-on', S.alt );
		ui.noteBtn.disabled = ! current();
		var hasScore = !! scoreRange();
		ui.viewBtn.hidden = ! hasScore;
		ui.viewBtn.classList.toggle( 'is-on', hasScore && S.view === 'music' );
		ui.viewBtn.querySelector( 'b' ).innerHTML = S.view === 'music' && hasScore ? I.notes : I.score;
		ui.viewBtn.querySelector( 'span' ).textContent = S.view === 'music' && hasScore ? 'Notes' : 'Music';
		applyView();
	}

	/* ---------- the scrubber ---------- */
	function paintScrub() {
		var d = audio.duration || 0, c = audio.currentTime || 0, pct = d ? c / d * 100 : 0;
		ui.fill.style.width = pct + '%'; ui.knob.style.left = pct + '%';
		ui.tcur.textContent = fmt( c ); ui.tdur.textContent = fmt( d || ( current() && current().dur ) );
		ui.scrub.setAttribute( 'aria-valuenow', Math.round( c ) ); ui.scrub.setAttribute( 'aria-valuemax', Math.round( d ) ); ui.scrub.setAttribute( 'aria-valuetext', fmt( c ) + ' of ' + fmt( d ) );
	}
	function paintMarks() {
		ui.marks.innerHTML = '';
		var d = audio.duration; if ( ! d ) { return; }
		CUES.forEach( function ( c ) { if ( c.t > d ) { return; } var m = h( 'span', { class: 'ta-mark ta-mark--' + c.kind } ); m.style.left = ( c.t / d * 100 ) + '%'; ui.marks.appendChild( m ); } );
		if ( S.view === 'music' ) { turnsFor().forEach( function ( t ) { if ( t > d ) { return; } var m = h( 'span', { class: 'ta-mark ta-mark--turn' } ); m.style.left = ( t / d * 100 ) + '%'; ui.marks.appendChild( m ); } ); }
	}
	function paintLoop() {
		var d = audio.duration;
		if ( S.loopA !== null && S.loopB !== null && d ) { ui.loopBand.style.left = ( S.loopA / d * 100 ) + '%'; ui.loopBand.style.width = ( ( S.loopB - S.loopA ) / d * 100 ) + '%'; ui.loopBand.hidden = false; }
		else { ui.loopBand.hidden = true; }
	}
	function scrubStart( e ) {
		if ( ! audio.duration ) { return; }
		var rect = ui.scrub.getBoundingClientRect();
		function at( ev ) { seek( Math.min( 1, Math.max( 0, ( ev.clientX - rect.left ) / rect.width ) ) * audio.duration ); }
		at( e ); ui.scrub.setPointerCapture( e.pointerId ); ui.scrub.classList.add( 'is-drag' );
		function move( ev ) { at( ev ); }
		function up() { ui.scrub.classList.remove( 'is-drag' ); ui.scrub.removeEventListener( 'pointermove', move ); ui.scrub.removeEventListener( 'pointerup', up ); setFollow( true ); scrollToActive(); }
		ui.scrub.addEventListener( 'pointermove', move ); ui.scrub.addEventListener( 'pointerup', up );
	}

	/* ---------- lock screen / headphones ---------- */
	function mediaSession( t ) {
		if ( ! ( 'mediaSession' in navigator ) ) { return; }
		try {
			navigator.mediaSession.metadata = new MediaMetadata( { title: t.title, artist: label( S.list.part ) + ' — ' + S.list.cantata.title, album: 'Tilden Community Choir', artwork: D.emblem ? [ { src: D.emblem, sizes: '512x512', type: 'image/png' } ] : [] } );
			navigator.mediaSession.setActionHandler( 'play', function () { audio.play(); } );
			navigator.mediaSession.setActionHandler( 'pause', function () { audio.pause(); } );
			navigator.mediaSession.setActionHandler( 'previoustrack', function () { step( -1 ); } );
			navigator.mediaSession.setActionHandler( 'nexttrack', function () { step( 1 ); } );
			navigator.mediaSession.setActionHandler( 'seekbackward', function () { seek( audio.currentTime - 5 ); } );
			navigator.mediaSession.setActionHandler( 'seekforward', function () { seek( audio.currentTime + 5 ); } );
		} catch ( e ) {}
	}

	/* ---------- keeping track, gently ---------- */
	function flushPlay( complete ) {
		var t = current();
		if ( ! t || ! S.who || ! S.list.now || t.song < 0 ) { return; }
		var secs = Math.round( S.listened );
		if ( secs < 3 && ! complete ) { return; }
		var done = complete && ! S.counted ? 1 : 0;
		if ( done ) { S.counted = true; }
		var cp = countPart(), k = rowKey( t.song, cp );
		S.rows[ k ] = S.rows[ k ] || { plays: 0, seconds: 0 };
		S.rows[ k ].plays = ( +S.rows[ k ].plays || 0 ) + done; S.rows[ k ].seconds = ( +S.rows[ k ].seconds || 0 ) + secs;
		api( 'play', 'POST', { cantata: D.cantata.id, song: t.song, part: cp, seconds: secs, complete: done }, S.list.cantata.token, true );
		S.listened = 0;
		if ( done ) { paintSide(); }
	}
	audio.addEventListener( 'timeupdate', function () {
		var now = audio.currentTime, prev = S.lastT;
		if ( ! audio.paused && now > prev && now - prev < 2 ) { S.listened += now - prev; }
		S.lastT = now;
		if ( S.loopA !== null && S.loopB !== null && now >= S.loopB ) { audio.currentTime = S.loopA; }
		if ( ! S.counted && audio.duration && S.listened >= audio.duration * 0.6 ) { flushPlay( true ); }
		var t = current(); if ( t ) { ls( 'tcc_pos_' + ( S.alt && altTrack() ? altTrack().track.id : t.id ), String( now ) ); }
		paintScrub(); tickStream();
	} );
	audio.addEventListener( 'ended', function () {
		flushPlay( true );
		var t = current(); if ( t ) { ls( 'tcc_pos_' + t.id, null ); }
		if ( S.loopA !== null ) { audio.currentTime = S.loopA; audio.play(); return; }
		step( 1 );
	} );
	audio.addEventListener( 'play', function () { ui.play.innerHTML = I.pause; ui.play.setAttribute( 'aria-label', 'Pause' ); ui.app.classList.add( 'is-playing' ); setFollow( S.follow ); } );
	audio.addEventListener( 'pause', function () { ui.play.innerHTML = I.play; ui.play.setAttribute( 'aria-label', 'Play' ); ui.app.classList.remove( 'is-playing' ); ui.followBtn.hidden = true; flushPlay(); } );
	audio.addEventListener( 'durationchange', function () { paintScrub(); paintMarks(); paintLoop(); } );
	window.addEventListener( 'pagehide', function () { flushPlay(); } );
	document.addEventListener( 'keydown', function ( e ) {
		if ( e.target.matches( 'input, textarea, select' ) ) { if ( e.key === 'Escape' ) { closeSheet(); } return; }
		if ( e.key === 'Escape' ) { closeSheet(); }
		if ( e.key === ' ' ) { e.preventDefault(); toggle(); }
		if ( S.view === 'music' && ( e.key === 'PageDown' || e.key === 'PageUp' ) ) { e.preventDefault(); manualPage( e.key === 'PageDown' ? 1 : -1 ); return; }
		if ( e.key === 'ArrowLeft' && ! sheet ) { seek( audio.currentTime - 5 ); }
		if ( e.key === 'ArrowRight' && ! sheet ) { seek( audio.currentTime + 5 ); }
	} );

	/* ---------- who, and where to start ---------- */
	function loadMe( switched ) {
		return api( 'me', 'GET', { cantata: D.cantata.id } ).then( function ( r ) {
			S.who = r && r.who ? r.who : null;
			S.rows = {};
			( r && r.rows || [] ).forEach( function ( row ) { S.rows[ rowKey( row.song, row.part ) ] = row; } );
			var own = S.who ? partForVoice( S.who.part ) : '';
			if ( switched && own && own !== S.part && ! current() ) { S.part = own; ls( 'tcc_part', own ); }
			if ( ! S.who ) { S.notes = []; done(); return; }
			return api( 'state', 'GET', { cantata: D.cantata.id } ).then( function ( st ) { S.notes = st && st.notes || []; done(); } );
		} ).catch( function () { done(); } );
		function done() { paintTop(); paintSide(); paintStream(); }
	}
	function start() {
		var saved = ls( 'tcc_part' ) || '';
		S.part = urlPart() || ( LISTS[ 'now:' + saved ] ? saved : '' ) || ORDER.filter( function ( p ) { return LISTS[ 'now:' + p ]; } )[ 0 ] || 'soprano';
		build();
		loadMe().then( function () {
			// first visit without ?part= : land on their own part
			if ( ! urlPart() && ! saved && S.who ) { var own = partForVoice( S.who.part ); if ( own && own !== S.part ) { S.part = own; paintTop(); paintSide(); paintStream(); } }
			var want = urlSong();
			if ( want >= 0 && ! current() ) {
				var L = LISTS[ 'now:' + S.part ];
				var at = -1; if ( L ) { L.tracks.some( function ( t, i ) { if ( t.song === want ) { at = i; return true; } return false; } ); }
				if ( at >= 0 ) { S.list = L; S.idx = at; S.playPart = S.part; loadTrack( false ); }
			}
		} );
	}
	start();
} )();
