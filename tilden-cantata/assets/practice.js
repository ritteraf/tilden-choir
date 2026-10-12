/* Tilden Cantata — the practice player.
   One instance per page. Big controls, nothing to explain: pick a track, press play.
   Keeps per-singer practice counts, flags and notes through the plugin's REST API. */
( function () {
	'use strict';
	var root = document.querySelector( '[data-tcc-practice]' );
	if ( ! root ) { return; }
	var D = JSON.parse( root.getAttribute( 'data-tcc-practice' ) );
	root.removeAttribute( 'data-tcc-practice' );

	/* ---------- helpers ---------- */
	function h( tag, attrs, children ) {
		var el = document.createElement( tag );
		if ( attrs ) { Object.keys( attrs ).forEach( function ( k ) {
			if ( k === 'class' ) { el.className = attrs[ k ]; }
			else if ( k === 'html' ) { el.innerHTML = attrs[ k ]; }
			else if ( k.indexOf( 'on' ) === 0 ) { el.addEventListener( k.slice( 2 ), attrs[ k ] ); }
			else if ( attrs[ k ] !== null && attrs[ k ] !== undefined ) { el.setAttribute( k, attrs[ k ] ); }
		} ); }
		( children || [] ).forEach( function ( c ) { if ( c === null || c === undefined || c === false ) { return; } el.appendChild( typeof c === 'string' ? document.createTextNode( c ) : c ); } );
		return el;
	}
	function fmt( t ) { t = Math.max( 0, Math.floor( t || 0 ) ); return Math.floor( t / 60 ) + ':' + ( '0' + ( t % 60 ) ).slice( -2 ); }
	function ago( s ) {
		if ( ! s ) { return ''; }
		var d = new Date( s.replace( ' ', 'T' ) ), now = new Date(), days = Math.floor( ( now - d ) / 864e5 );
		if ( isNaN( d ) ) { return ''; }
		if ( days <= 0 ) { return 'today'; }
		if ( days === 1 ) { return 'yesterday'; }
		if ( days < 7 ) { return d.toLocaleDateString( undefined, { weekday: 'long' } ); }
		return d.toLocaleDateString( undefined, { month: 'short', day: 'numeric' } );
	}
	var SVG = {
		play: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 5v14l12-7z"/></svg>',
		pause: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>',
		back: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7"/><polyline points="3 3 3 9 9 9"/><text x="8" y="16" font-size="8" fill="currentColor" stroke="none" font-family="sans-serif" font-weight="700">5</text></svg>',
		next: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 5v14l9-7zM17 5h2v14h-2z"/></svg>',
		prev: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18 5v14l-9-7zM5 5h2v14H5z"/></svg>',
		note: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
		people: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
		pen: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>',
		star: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 1 L13.4 10.6 L23 12 L13.4 13.4 L12 23 L10.6 13.4 L1 12 L10.6 10.6 Z"/></svg>',
		check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>',
		x: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'
	};
	var FLAGS = { '': 'How’s it going?', ok: 'Got it', working: 'Working on it', help: 'I need help' };

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
		return fetch( url, { method: method, headers: headers, credentials: 'same-origin', body: method === 'GET' ? undefined : JSON.stringify( body ) } ).then( function ( r ) { return r.json(); } );
	}

	/* ---------- state ---------- */
	var PAGE = D.mode === 'page';
	var VOICE = [ 'soprano', 'alto', 'tenor', 'bass' ];
	var S = {
		who: D.signin && D.signin.who ? D.signin.who : D.who,
		playPart: '',  // the part a play counts toward (the part shown when the track was started)
		rows: {},     // 'song|part' -> { plays, seconds, flag, last_played }
		notes: [],    // personal notes
		list: null,   // the list currently playing: { key, cantata: {id,token,title,year}, part, tracks }
		idx: -1,      // index into list.tracks
		alt: null,    // when "hear everyone" is on: the alternative part key
		loopA: null, loopB: null,
		rate: 1,
		listened: 0, counted: false, lastT: 0,
		ownKey: 'now:' + D.part
	};
	var audio = new Audio();
	audio.preload = 'metadata';
	try { audio.preservesPitch = true; audio.mozPreservesPitch = true; audio.webkitPreservesPitch = true; } catch ( e ) {}

	/* lists we can play: this year's own part, companions, and past years */
	var LISTS = {};
	Object.keys( D.lists || {} ).forEach( function ( p ) { LISTS[ 'now:' + p ] = { key: 'now:' + p, cantata: { id: D.cantata.id, token: D.token, title: D.cantata.title, year: D.cantata.year }, part: p, tracks: D.lists[ p ] }; } );
	( D.past || [] ).forEach( function ( c ) {
		if ( PAGE ) { Object.keys( c.parts || {} ).forEach( function ( p ) { LISTS[ 'past:' + c.id + ':' + p ] = { key: 'past:' + c.id + ':' + p, cantata: { id: c.id, token: c.token, title: c.title, year: c.year }, part: p, tracks: c.parts[ p ] }; } ); }
		else { LISTS[ 'past:' + c.id ] = { key: 'past:' + c.id, cantata: { id: c.id, token: c.token, title: c.title, year: c.year }, part: D.part, tracks: c.tracks }; }
	} );
	function pastKeysFor( part ) { return Object.keys( LISTS ).filter( function ( k ) { return k.indexOf( 'past:' ) === 0 && ( ! PAGE || k.slice( k.lastIndexOf( ':' ) + 1 ) === part ); } ); }
	function partLabel( p ) { return ( D.partLabels && D.partLabels[ p ] ) || p; }
	/* page mode: which part to show first */
	function urlPart() { var m = location.search.match( /[?&]part=([a-z]+)/ ); return m && LISTS[ 'now:' + m[ 1 ] ] ? m[ 1 ] : ''; }
	function urlSong() { var m = location.hash.match( /#song-(\d+)/ ); return m ? parseInt( m[ 1 ], 10 ) - 1 : -1; }
	function partForVoice( voice ) {
		var v = D.voice && D.voice[ voice ];
		if ( ! v ) { return ''; }
		for ( var i = 0; i < v.tracks.length; i++ ) { if ( LISTS[ 'now:' + v.tracks[ i ] ] ) { return v.tracks[ i ]; } }
		return '';
	}
	function setPart( p, silent ) {
		if ( ! p || ! D.partLabels[ p ] ) { return; }
		D.part = p; D.partLabel = partLabel( p ); S.ownKey = 'now:' + p;
		if ( ! silent ) { try { localStorage.setItem( 'tcc_part', p ); } catch ( e ) {} renderLists(); renderSwitch(); }
	}
	if ( PAGE ) {
		var saved = ''; try { saved = localStorage.getItem( 'tcc_part' ) || ''; } catch ( e ) {}
		var pick = urlPart() || ( LISTS[ 'now:' + saved ] ? saved : '' ) || VOICE.concat( [ 'accompaniment', 'sections', 'listen' ] ).filter( function ( p ) { return LISTS[ 'now:' + p ]; } )[ 0 ] || 'soprano';
		setPart( pick, true );
	}

	function rowKey( song, part ) { return song + '|' + part; }
	function stat( song ) { return S.rows[ rowKey( song, D.part ) ] || {}; }
	function countPart() { return S.playPart || D.part; }

	/* ---------- build the page ---------- */
	var ui = {};
	function build() {
		if ( PAGE ) { buildPage(); return; }
		root.innerHTML = '';
		ui.who = h( 'div', { class: 'tcc-pr__who' } );
		root.appendChild( ui.who );
		if ( D.next && D.next.songs && D.next.songs.length && LISTS[ S.ownKey ] ) {
			var nextTracks = LISTS[ S.ownKey ].tracks.filter( function ( t ) { return D.next.songs.indexOf( t.song ) !== -1; } );
			if ( nextTracks.length ) {
				root.appendChild( h( 'div', { class: 'tcc-pr__next' }, [
					h( 'div', { class: 'tcc-pr__next-text' }, [
						h( 'span', { class: 'tcc-pr__eyebrow' }, [ 'On ' + ( D.next.date || 'Wednesday' ) ] ),
						h( 'span', {}, [ D.next.full ? 'Full run-through — the whole work.' : 'We’re working on ' + D.next.songs.map( function ( i ) { return ( i + 1 ) + '. ' + ( D.songs[ i ] || '' ); } ).join( ', ' ) + '.' ] )
					] ),
					h( 'button', { type: 'button', class: 'tcc-pr__btn tcc-pr__btn--primary', onclick: function () { playList( S.ownKey, LISTS[ S.ownKey ].tracks.indexOf( nextTracks[ 0 ] ), D.next.songs ); } }, [ h( 'span', { html: SVG.play } ), 'Practice these' ] )
				] ) );
			}
		}
		if ( ! LISTS[ S.ownKey ] && D.empty ) {
			root.appendChild( h( 'p', { class: 'tcc-practice__empty' }, [ D.empty ] ) );
		}
		if ( LISTS[ S.ownKey ] ) {
			root.appendChild( h( 'div', { class: 'tcc-pr__head' }, [ h( 'span', { class: 'tcc-pr__eyebrow' }, [ D.cantata.year + ' · ' + D.cantata.title ] ), h( 'h2', { class: 'tcc-pr__title' }, [ D.partLabel + ' tracks' ] ) ] ) );
			root.appendChild( renderList( S.ownKey, true ) );
		}
		var pastKeys = Object.keys( LISTS ).filter( function ( k ) { return k.indexOf( 'past:' ) === 0; } );
		if ( pastKeys.length ) {
			var past = h( 'div', { class: 'tcc-pr__past' }, [ h( 'span', { class: 'tcc-pr__eyebrow tcc-pr__eyebrow--line' }, [ 'Earlier years' ] ) ] );
			pastKeys.forEach( function ( k ) {
				var L = LISTS[ k ];
				past.appendChild( h( 'details', { class: 'tcc-pr__year' }, [ h( 'summary', {}, [ L.cantata.year + ' · ' + L.cantata.title ] ), renderList( k, false ) ] ) );
			} );
			root.appendChild( past );
		}
		ui.bar = buildBar();
		root.appendChild( ui.bar );
		ui.picker = null;
		renderWho();
		document.body.classList.add( 'tcc-has-player' );
	}

	/* ---------- page mode: the whole-page practice layout ---------- */
	function buildPage() {
		root.innerHTML = '';
		ui.who = h( 'div', { class: 'tcc-pr__who' } );
		ui.switch = h( 'div', { class: 'tcc-pp__switch', role: 'tablist', 'aria-label': 'Part' } );
		ui.lists = h( 'div', { class: 'tcc-pp__lists' } );
		ui.notes = h( 'div', { class: 'tcc-pp__notes' } );
		ui.bar = buildBar();
		ui.bar.classList.add( 'tcc-pr__bar--panel' );
		ui.bar.hidden = false;
		ui.picker = null;
		root.appendChild( h( 'div', { class: 'tcc-pp' }, [
			h( 'div', { class: 'tcc-pp__top' }, [ ui.switch, ui.who ] ),
			h( 'div', { class: 'tcc-pp__main' }, [ ui.bar, ui.notes ] ),
			h( 'div', { class: 'tcc-pp__side' }, [ ui.lists ] )
		] ) );
		renderSwitch(); renderLists(); paintNotes(); // who-line waits for /me
		document.body.classList.add( 'tcc-has-page-player' );
	}
	function renderSwitch() {
		ui.switch.innerHTML = '';
		var mine = S.who && S.who.part ? ( D.voice[ S.who.part ] || { tracks: [] } ).tracks : [];
		VOICE.concat( [ 'accompaniment', 'sections', 'listen' ] ).forEach( function ( p ) {
			if ( ! LISTS[ 'now:' + p ] ) { return; }
			var own = mine.indexOf( p ) !== -1;
			ui.switch.appendChild( h( 'button', { type: 'button', role: 'tab', 'aria-selected': p === D.part ? 'true' : 'false', class: 'tcc-pp__tab' + ( p === D.part ? ' is-on' : '' ) + ( own ? ' is-mine' : '' ) + ( VOICE.indexOf( p ) === -1 ? ' tcc-pp__tab--other' : '' ), onclick: function () { setPart( p ); } }, [ partLabel( p ), own ? h( 'small', {}, [ 'your part' ] ) : null ] ) );
		} );
	}
	function renderLists() {
		ui.lists.innerHTML = '';
		var L = LISTS[ S.ownKey ];
		if ( D.next && D.next.songs && D.next.songs.length && L ) {
			var nextTracks = L.tracks.filter( function ( t ) { return D.next.songs.indexOf( t.song ) !== -1; } );
			if ( nextTracks.length ) {
				ui.lists.appendChild( h( 'div', { class: 'tcc-pr__next tcc-pr__next--slim' }, [
					h( 'div', { class: 'tcc-pr__next-text' }, [
						h( 'span', { class: 'tcc-pr__eyebrow' }, [ 'On ' + ( D.next.date || 'Wednesday' ) ] ),
						h( 'span', {}, [ D.next.full ? 'Full run-through — the whole work.' : 'We’re working on ' + D.next.songs.map( function ( i ) { return ( i + 1 ) + ( ( D.next.focus || [] ).indexOf( i ) !== -1 ? ' ★' : '' ); } ).join( ', ' ) + '.' ] )
					] ),
					h( 'button', { type: 'button', class: 'tcc-pr__btn tcc-pr__btn--primary', onclick: function () { playList( S.ownKey, L.tracks.indexOf( nextTracks[ 0 ] ), D.next.songs ); } }, [ h( 'span', { html: SVG.play } ), 'Practice these' ] )
				] ) );
			}
		}
		ui.lists.appendChild( h( 'div', { class: 'tcc-pr__head' }, [ h( 'span', { class: 'tcc-pr__eyebrow' }, [ D.cantata.year + ' · ' + D.cantata.title ] ), h( 'h2', { class: 'tcc-pr__title' }, [ D.partLabel + ( VOICE.indexOf( D.part ) !== -1 ? ' tracks' : '' ) ] ) ] ) );
		if ( L ) { ui.lists.appendChild( renderList( S.ownKey, true ) ); }
		else { ui.lists.appendChild( h( 'p', { class: 'tcc-practice__empty' }, [ 'No ' + D.partLabel + ' tracks for this year yet.' ] ) ); }
		var pastKeys = pastKeysFor( D.part );
		if ( pastKeys.length ) {
			var past = h( 'div', { class: 'tcc-pr__past' }, [ h( 'span', { class: 'tcc-pr__eyebrow tcc-pr__eyebrow--line' }, [ 'Earlier years' ] ) ] );
			pastKeys.forEach( function ( k ) { var PL = LISTS[ k ]; past.appendChild( h( 'details', { class: 'tcc-pr__year' }, [ h( 'summary', {}, [ PL.cantata.year + ' · ' + PL.cantata.title ] ), renderList( k, false ) ] ) ); } );
			ui.lists.appendChild( past );
		}
		markCurrent();
	}
	/* the notes timeline under the panel: every director note and personal note on the current song, in order */
	function paintNotes() {
		if ( ! PAGE || ! ui.notes ) { return; }
		ui.notes.innerHTML = '';
		var t = current();
		if ( ! t ) {
			ui.notes.appendChild( h( 'p', { class: 'tcc-pp__notes-empty' }, [ 'Pick a song ' + ( window.innerWidth > 900 ? 'on the right' : 'below' ) + ' to begin. Notes from the director, and your own, show up here as it plays.' ] ) );
			return;
		}
		var list = cuesForCurrent();
		ui.notes.appendChild( h( 'div', { class: 'tcc-pp__notes-head' }, [
			h( 'span', { class: 'tcc-pr__eyebrow' }, [ 'Notes on ' + t.title ] ),
			h( 'span', { class: 'tcc-pp__notes-count' }, [ list.length ? list.length + ( list.length === 1 ? ' note' : ' notes' ) : '' ] )
		] ) );
		if ( ! list.length ) {
			ui.notes.appendChild( h( 'p', { class: 'tcc-pp__notes-empty' }, [ S.who ? 'No notes on this one yet. Press “My note” while it plays to drop one at that moment.' : 'No notes on this one yet.' ] ) );
		} else {
			var ol = h( 'ol', { class: 'tcc-pp__cues' } );
			list.forEach( function ( c, i ) {
				ol.appendChild( h( 'li', { class: 'tcc-pp__cue tcc-pp__cue--' + c.kind, 'data-t': c.t, 'data-end': i + 1 < list.length ? list[ i + 1 ].t : 1e9 }, [
					h( 'button', { type: 'button', class: 'tcc-pp__cue-t', onclick: function () { audio.currentTime = Math.max( 0, c.t - 1 ); if ( audio.paused ) { audio.play().catch( function () {} ); } } }, [ fmt( c.t ) ] ),
					h( 'span', { class: 'tcc-pp__cue-k' }, [ c.kind === 'cue' ? 'Director' : 'Me' ] ),
					h( 'span', { class: 'tcc-pp__cue-text' }, [ c.text ] ),
					( ( c.kind === 'mine' && S.who ) || ( c.kind === 'cue' && D.canEdit ) ) ? h( 'button', { type: 'button', class: 'tcc-pp__cue-x', 'aria-label': 'Remove this note', html: SVG.x, onclick: function () { removeNote( c ); } } ) : null
				] ) );
			} );
			ui.notes.appendChild( ol );
		}
		tickNoteRows();
	}
	function tickNoteRows() {
		if ( ! PAGE || ! ui.notes ) { return; }
		var now = audio.currentTime || 0;
		ui.notes.querySelectorAll( '.tcc-pp__cue' ).forEach( function ( li ) { li.classList.toggle( 'is-on', now >= +li.getAttribute( 'data-t' ) - 1 && now < +li.getAttribute( 'data-end' ) - 1 ); } );
	}

	function onSeason() { return ! D.signin || ! S.who || S.who.onSeason !== false; }
	function renderWho() {
		ui.who.innerHTML = '';
		if ( S.who && ! onSeason() && ! localStorage.getItem( 'tcc_who_skip' ) ) {
			ui.who.appendChild( h( 'div', { class: 'tcc-pr__who-card' }, [
				h( 'div', {}, [ h( 'b', {}, [ 'Welcome back, ' + S.who.name.split( ' ' )[ 0 ] + '!' ] ), h( 'p', {}, [ 'Singing with us for ' + D.cantata.title + '? Take a moment to confirm your details — it replaces the paper sign-up sheet.' ] ) ] ),
				h( 'div', { class: 'tcc-pr__who-actions' }, [
					h( 'button', { type: 'button', class: 'tcc-pr__btn tcc-pr__btn--primary', onclick: openPicker }, [ 'Yes, I’m in' ] ),
					h( 'button', { type: 'button', class: 'tcc-pr__link', onclick: function () { try { localStorage.setItem( 'tcc_who_skip', '1' ); } catch ( e ) {} renderWho(); } }, [ 'Not now' ] )
				] )
			] ) );
			return;
		}
		if ( S.who ) {
			ui.who.appendChild( h( 'div', { class: 'tcc-pr__who-line' }, [ h( 'span', { html: SVG.people } ), 'Practicing as ', h( 'b', {}, [ S.who.name ] ), h( 'button', { type: 'button', class: 'tcc-pr__link', onclick: openPicker }, [ 'not you?' ] ) ] ) );
		} else if ( ! localStorage.getItem( 'tcc_who_skip' ) ) {
			ui.who.appendChild( h( 'div', { class: 'tcc-pr__who-card' }, [
				h( 'div', {}, [ h( 'b', {}, [ 'Who’s practicing?' ] ), h( 'p', {}, [ D.signin ? 'Sign in once on this device — it puts you on this year’s list, replaces the paper sign-up sheet, and lets the site keep track of what you’ve practiced. You can still play everything without it.' : 'Choose your name once and this device will remember what you’ve practiced and let you mark songs you want help with. You can still play everything without it.' ] ) ] ),
				h( 'div', { class: 'tcc-pr__who-actions' }, [
					h( 'button', { type: 'button', class: 'tcc-pr__btn tcc-pr__btn--primary', onclick: openPicker }, [ D.signin ? 'Sign in' : 'Choose my name' ] ),
					h( 'button', { type: 'button', class: 'tcc-pr__link', onclick: function () { try { localStorage.setItem( 'tcc_who_skip', '1' ); } catch ( e ) {} renderWho(); } }, [ 'Not now' ] )
				] )
			] ) );
		} else {
			ui.who.appendChild( h( 'div', { class: 'tcc-pr__who-line tcc-pr__who-line--muted' }, [ h( 'button', { type: 'button', class: 'tcc-pr__link', onclick: openPicker }, [ 'Choose your name to keep track of your practice' ] ) ] ) );
		}
	}

	function openPicker() {
		if ( D.signin && window.tccSignin ) {
			D.signin.who = S.who ? { id: S.who.id, name: S.who.name, onSeason: onSeason() } : null;
			window.tccSignin.open( D.signin, function ( who ) {
				S.who = who; try { localStorage.removeItem( 'tcc_who_skip' ); } catch ( e ) {}
				renderWho(); loadState( true );
			} );
			return;
		}
		if ( ui.picker ) { ui.picker.remove(); }
		var panel = h( 'div', { class: 'tcc-pr__picker', role: 'dialog', 'aria-label': 'Choose your name' }, [
			h( 'div', { class: 'tcc-pr__picker-box' }, [
				h( 'div', { class: 'tcc-pr__picker-head' }, [ h( 'b', {}, [ 'Who’s practicing?' ] ), h( 'button', { type: 'button', class: 'tcc-pr__icon', 'aria-label': 'Close', html: SVG.x, onclick: function () { panel.remove(); ui.picker = null; } } ) ] ),
				h( 'p', { class: 'tcc-pr__picker-loading' }, [ 'Loading the roster…' ] )
			] )
		] );
		ui.picker = panel;
		document.body.appendChild( panel );
		api( 'roster', 'GET', { cantata: D.cantata.id } ).then( function ( r ) {
			var box = panel.querySelector( '.tcc-pr__picker-box' );
			box.querySelector( '.tcc-pr__picker-loading' ).remove();
			var groups = r.groups || {};
			var order = [ 'Soprano', 'Mezzo', 'Alto', 'Tenor', 'Baritone', 'Bass' ];
			Object.keys( groups ).sort( function ( a, b ) { var x = order.indexOf( a ), y = order.indexOf( b ); return ( x === -1 ? 99 : x ) - ( y === -1 ? 99 : y ); } ).forEach( function ( g ) {
				var grp = h( 'div', { class: 'tcc-pr__picker-group' }, [ h( 'span', { class: 'tcc-pr__eyebrow' }, [ g ] ) ] );
				var grid = h( 'div', { class: 'tcc-pr__picker-grid' } );
				groups[ g ].forEach( function ( p ) {
					grid.appendChild( h( 'button', { type: 'button', class: 'tcc-pr__name' + ( S.who && S.who.id === p.id ? ' is-me' : '' ), onclick: function () { choose( p ); } }, [ p.name ] ) );
				} );
				grp.appendChild( grid ); box.appendChild( grp );
			} );
			box.appendChild( h( 'p', { class: 'tcc-pr__picker-foot' }, [ 'Not on the list? Tell the director and carry on — everything still plays.', S.who ? h( 'button', { type: 'button', class: 'tcc-pr__link', onclick: function () { choose( null ); } }, [ 'Forget me on this device' ] ) : null ] ) );
		} ).catch( function () { panel.querySelector( '.tcc-pr__picker-loading' ).textContent = 'Couldn’t load the roster. Try again in a moment.'; } );
		function choose( p ) {
			api( 'who', 'POST', { cantata: D.cantata.id, person: p ? p.id : 0 } ).then( function ( r ) {
				S.who = r && r.id ? { id: r.id, name: r.name } : null;
				try { localStorage.removeItem( 'tcc_who_skip' ); } catch ( e ) {}
				panel.remove(); ui.picker = null;
				renderWho(); loadState();
			} );
		}
	}

	function renderList( key, own ) {
		var L = LISTS[ key ];
		var ol = h( 'ol', { class: 'tcc-pr__list', 'data-key': key } );
		L.tracks.forEach( function ( t, i ) {
			var st = own ? stat( t.song ) : {};
			var meta = [ fmt( t.dur ) ];
			if ( own && st.plays ) { meta.push( 'practiced ' + st.plays + '×' + ( st.last_played ? ', last ' + ago( st.last_played ) : '' ) ); }
			var li = h( 'li', { class: 'tcc-pr__row' + ( own && D.next && D.next.songs.indexOf( t.song ) !== -1 ? ' is-next' : '' ), 'data-i': i }, [
				h( 'button', { type: 'button', class: 'tcc-pr__play', 'aria-label': 'Play ' + t.title, html: SVG.play, onclick: function () { if ( S.list && S.list.key === key && S.idx === i ) { toggle(); } else { playList( key, i ); } } } ),
				h( 'div', { class: 'tcc-pr__row-text', onclick: function () { if ( S.list && S.list.key === key && S.idx === i ) { toggle(); } else { playList( key, i ); } } }, [
					h( 'b', {}, [ ( t.song >= 0 ? ( t.song + 1 ) + '. ' : '' ) + t.title, own && D.next && ( D.next.focus || [] ).indexOf( t.song ) !== -1 ? h( 'span', { class: 'tcc-focus', title: 'The director says: focus on this one' }, [ ' ★' ] ) : null ] ),
					h( 'span', { class: 'tcc-pr__row-meta' }, [ meta.join( ' · ' ) ] )
				] ),
				own && S.who ? flagChip( t.song, st.flag || '' ) : null
			] );
			ol.appendChild( li );
		} );
		return ol;
	}

	function flagChip( song, flag ) {
		var chip = h( 'button', { type: 'button', class: 'tcc-pr__flag tcc-pr__flag--' + ( flag || 'none' ), 'aria-haspopup': 'true' }, [ FLAGS[ flag ] ] );
		chip.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			closeMenus();
			var menu = h( 'div', { class: 'tcc-pr__menu' } );
			[ 'ok', 'working', 'help', '' ].forEach( function ( f ) {
				if ( f === '' && ! flag ) { return; }
				menu.appendChild( h( 'button', { type: 'button', class: 'tcc-pr__menu-item tcc-pr__flag--' + ( f || 'none' ), onclick: function () {
					var fp = D.part;
					api( 'flag', 'POST', { cantata: D.cantata.id, song: song, part: fp, flag: f } ).then( function () {
						var k = rowKey( song, fp ); S.rows[ k ] = S.rows[ k ] || {}; S.rows[ k ].flag = f;
						refreshLists();
					} );
					menu.remove();
				} }, [ f ? FLAGS[ f ] : 'Clear' ] ) );
			} );
			chip.parentNode.appendChild( menu );
			setTimeout( function () { document.addEventListener( 'click', closeMenus, { once: true } ); }, 0 );
		} );
		return chip;
	}
	function closeMenus() { root.querySelectorAll( '.tcc-pr__menu' ).forEach( function ( m ) { m.remove(); } ); }

	function refreshLists() {
		if ( PAGE ) { renderLists(); return; }
		root.querySelectorAll( '.tcc-pr__list' ).forEach( function ( ol ) {
			var key = ol.getAttribute( 'data-key' );
			var fresh = renderList( key, key === S.ownKey );
			ol.replaceWith( fresh );
		} );
		markCurrent();
	}

	/* ---------- the bar ---------- */
	function buildBar() {
		ui.title = h( 'div', { class: 'tcc-pr__bar-title' }, [ 'Choose a track to begin' ] );
		ui.sub = h( 'div', { class: 'tcc-pr__bar-sub' } );
		ui.cue = h( 'div', { class: 'tcc-pr__cue', 'aria-live': 'polite' } );
		ui.scrub = h( 'div', { class: 'tcc-pr__scrub', role: 'slider', tabindex: '0', 'aria-label': 'Position' } );
		ui.fill = h( 'div', { class: 'tcc-pr__scrub-fill' } );
		ui.marks = h( 'div', { class: 'tcc-pr__scrub-marks' } );
		ui.loopBand = h( 'div', { class: 'tcc-pr__scrub-loop' } );
		ui.scrub.appendChild( ui.loopBand ); ui.scrub.appendChild( ui.fill ); ui.scrub.appendChild( ui.marks );
		ui.tcur = h( 'span', {}, [ '0:00' ] ); ui.tdur = h( 'span', {}, [ '0:00' ] );
		ui.playBtn = h( 'button', { type: 'button', class: 'tcc-pr__big', 'aria-label': 'Play', html: SVG.play, onclick: toggle } );
		ui.rateBtn = h( 'button', { type: 'button', class: 'tcc-pr__tool', onclick: cycleRate }, [ h( 'b', {}, [ '1×' ] ), h( 'span', {}, [ 'Speed' ] ) ] );
		ui.loopBtn = h( 'button', { type: 'button', class: 'tcc-pr__tool', onclick: loopTap }, [ h( 'b', {}, [ 'A–B' ] ), h( 'span', {}, [ 'Loop' ] ) ] );
		ui.altBtn = h( 'button', { type: 'button', class: 'tcc-pr__tool', onclick: toggleAlt }, [ h( 'b', { html: SVG.people } ), h( 'span', {}, [ 'Everyone' ] ) ] );
		ui.noteBtn = h( 'button', { type: 'button', class: 'tcc-pr__tool', onclick: function () { noteForm( 'mine' ); } }, [ h( 'b', { html: SVG.pen } ), h( 'span', {}, [ 'My note' ] ) ] );
		ui.cueBtn = D.canEdit ? h( 'button', { type: 'button', class: 'tcc-pr__tool tcc-pr__tool--director', onclick: function () { noteForm( 'cue' ); } }, [ h( 'b', { html: SVG.star } ), h( 'span', {}, [ 'Director note' ] ) ] ) : null;
		ui.form = h( 'div', { class: 'tcc-pr__form' } );
		var bar = h( 'div', { class: 'tcc-pr__bar', hidden: '' }, [
			h( 'div', { class: 'tcc-pr__bar-top' }, [ h( 'div', { class: 'tcc-pr__bar-text' }, [ ui.title, ui.sub ] ), ui.cue ] ),
			h( 'div', { class: 'tcc-pr__bar-scrub' }, [ ui.tcur, ui.scrub, ui.tdur ] ),
			h( 'div', { class: 'tcc-pr__bar-ctl' }, [
				h( 'button', { type: 'button', class: 'tcc-pr__icon', 'aria-label': 'Previous track', html: SVG.prev, onclick: function () { step( -1 ); } } ),
				h( 'button', { type: 'button', class: 'tcc-pr__icon', 'aria-label': 'Back five seconds', html: SVG.back, onclick: function () { audio.currentTime = Math.max( 0, audio.currentTime - 5 ); } } ),
				ui.playBtn,
				h( 'button', { type: 'button', class: 'tcc-pr__icon', 'aria-label': 'Next track', html: SVG.next, onclick: function () { step( 1 ); } } )
			] ),
			h( 'div', { class: 'tcc-pr__bar-tools' }, [ ui.rateBtn, ui.loopBtn, ui.altBtn, ui.noteBtn, ui.cueBtn ] ),
			ui.form
		] );
		ui.scrub.addEventListener( 'pointerdown', scrubStart );
		ui.scrub.addEventListener( 'keydown', function ( e ) { if ( e.key === 'ArrowLeft' ) { audio.currentTime -= 5; } if ( e.key === 'ArrowRight' ) { audio.currentTime += 5; } } );
		return bar;
	}
	function scrubStart( e ) {
		if ( ! audio.duration ) { return; }
		var rect = ui.scrub.getBoundingClientRect();
		function at( ev ) { var x = Math.min( 1, Math.max( 0, ( ev.clientX - rect.left ) / rect.width ) ); audio.currentTime = x * audio.duration; paint(); }
		at( e ); ui.scrub.setPointerCapture( e.pointerId );
		function move( ev ) { at( ev ); }
		function up() { ui.scrub.removeEventListener( 'pointermove', move ); ui.scrub.removeEventListener( 'pointerup', up ); }
		ui.scrub.addEventListener( 'pointermove', move ); ui.scrub.addEventListener( 'pointerup', up );
	}

	/* ---------- playback ---------- */
	var queue = null; // when "Practice these" is used: only these song indices
	function playList( key, i, onlySongs ) {
		flushPlay();
		queue = onlySongs || null;
		S.list = LISTS[ key ]; S.idx = i; S.alt = null; S.loopA = S.loopB = null;
		S.playPart = key.indexOf( 'now:' ) === 0 && VOICE.indexOf( LISTS[ key ].part ) === -1 ? D.part : LISTS[ key ].part;
		loadTrack( true );
	}
	function current() { return S.list ? S.list.tracks[ S.idx ] : null; }
	function altTrack() {
		var t = current(); if ( ! t || t.song < 0 || S.list.key.indexOf( 'now:' ) !== 0 ) { return null; }
		var pref = S.list.part === 'listen' ? [ 'now:accompaniment' ] : [ 'now:listen', 'now:accompaniment' ];
		for ( var k = 0; k < pref.length; k++ ) {
			var L = LISTS[ pref[ k ] ]; if ( ! L || pref[ k ] === S.list.key ) { continue; }
			for ( var j = 0; j < L.tracks.length; j++ ) { if ( L.tracks[ j ].song === t.song ) { return { key: pref[ k ], track: L.tracks[ j ] }; } }
		}
		return null;
	}
	function loadTrack( autoplay, keepTime ) {
		var t = current(); if ( ! t ) { return; }
		var src = t, altLabel = '';
		if ( S.alt ) { var a = altTrack(); if ( a ) { src = a.track; altLabel = D.partLabels[ LISTS[ a.key ].part ] || ''; } else { S.alt = null; } }
		var was = keepTime ? audio.currentTime : null;
		audio.src = src.url;
		audio.playbackRate = S.rate;
		S.listened = 0; S.counted = false; S.lastT = 0;
		var resume = 0;
		try { resume = parseFloat( localStorage.getItem( 'tcc_pos_' + src.id ) || '0' ); } catch ( e ) {}
		audio.addEventListener( 'loadedmetadata', function once() {
			audio.removeEventListener( 'loadedmetadata', once );
			if ( was !== null ) { audio.currentTime = Math.min( was, audio.duration - 1 ); }
			else if ( resume > 5 && resume < audio.duration - 10 ) { audio.currentTime = resume; }
			paint(); paintMarks();
		} );
		ui.title.textContent = ( t.song >= 0 ? ( t.song + 1 ) + '. ' : '' ) + t.title;
		ui.sub.textContent = ( altLabel ? altLabel : D.partLabels[ S.list.part ] || S.list.part ) + ' · ' + S.list.cantata.year + ' ' + S.list.cantata.title;
		ui.altBtn.classList.toggle( 'is-on', !! S.alt );
		ui.altBtn.hidden = ! altTrack() && ! S.alt;
		ui.altBtn.querySelector( 'span' ).textContent = S.alt ? 'Back to ' + partLabel( S.list.part ) : 'Everyone';
		ui.bar.hidden = false;
		ui.cue.classList.remove( 'is-on' ); clearTimeout( cueTimer );
		ui.noteBtn.hidden = ! S.who || S.list.key.indexOf( 'now:' ) !== 0;
		if ( ui.cueBtn ) { ui.cueBtn.hidden = S.list.key.indexOf( 'now:' ) !== 0; }
		ui.form.innerHTML = '';
		markCurrent(); paintMarks(); paintLoop(); paintNotes();
		if ( PAGE && autoplay && window.innerWidth <= 900 ) { ui.bar.scrollIntoView( { block: 'start', behavior: 'smooth' } ); }
		if ( 'mediaSession' in navigator ) {
			try {
				navigator.mediaSession.metadata = new MediaMetadata( { title: t.title, artist: ( D.partLabels[ S.list.part ] || '' ) + ' — ' + S.list.cantata.title, album: 'Tilden Community Choir', artwork: D.emblem ? [ { src: D.emblem, sizes: '512x512', type: 'image/png' } ] : [] } );
				navigator.mediaSession.setActionHandler( 'play', function () { audio.play(); } );
				navigator.mediaSession.setActionHandler( 'pause', function () { audio.pause(); } );
				navigator.mediaSession.setActionHandler( 'previoustrack', function () { step( -1 ); } );
				navigator.mediaSession.setActionHandler( 'nexttrack', function () { step( 1 ); } );
				navigator.mediaSession.setActionHandler( 'seekbackward', function () { audio.currentTime = Math.max( 0, audio.currentTime - 5 ); } );
			} catch ( e ) {}
		}
		if ( autoplay ) { audio.play().catch( function () {} ); }
	}
	function toggle() { if ( ! current() ) { var L = LISTS[ S.ownKey ]; if ( L ) { playList( S.ownKey, 0 ); } return; } if ( audio.paused ) { audio.play().catch( function () {} ); } else { audio.pause(); } }
	function step( dir ) {
		if ( ! S.list ) { return; }
		flushPlay();
		var n = S.idx;
		for ( var k = 0; k < S.list.tracks.length; k++ ) {
			n += dir;
			if ( n < 0 || n >= S.list.tracks.length ) { return; }
			if ( ! queue || queue.indexOf( S.list.tracks[ n ].song ) !== -1 ) { break; }
		}
		S.idx = n; S.loopA = S.loopB = null; loadTrack( true );
	}
	function markCurrent() {
		root.querySelectorAll( '.tcc-pr__row' ).forEach( function ( li ) {
			var on = S.list && li.parentNode.getAttribute( 'data-key' ) === S.list.key && +li.getAttribute( 'data-i' ) === S.idx;
			li.classList.toggle( 'is-current', on );
			li.querySelector( '.tcc-pr__play' ).innerHTML = on && ! audio.paused ? SVG.pause : SVG.play;
		} );
	}
	function cycleRate() {
		var rates = [ 1, 0.85, 0.75 ], i = rates.indexOf( S.rate ); S.rate = rates[ ( i + 1 ) % rates.length ];
		audio.playbackRate = S.rate; ui.rateBtn.querySelector( 'b' ).textContent = S.rate + '×'; ui.rateBtn.classList.toggle( 'is-on', S.rate !== 1 );
	}
	function loopTap() {
		if ( S.loopA === null ) { S.loopA = audio.currentTime; ui.loopBtn.querySelector( 'span' ).textContent = 'Set end'; ui.loopBtn.classList.add( 'is-on' ); }
		else if ( S.loopB === null ) { S.loopB = audio.currentTime; if ( S.loopB <= S.loopA + 1 ) { S.loopB = Math.min( audio.duration, S.loopA + 5 ); } ui.loopBtn.querySelector( 'span' ).textContent = fmt( S.loopA ) + '–' + fmt( S.loopB ); audio.currentTime = S.loopA; }
		else { S.loopA = S.loopB = null; ui.loopBtn.querySelector( 'span' ).textContent = 'Loop'; ui.loopBtn.classList.remove( 'is-on' ); }
		paintLoop();
	}
	function paintLoop() {
		if ( S.loopA !== null && S.loopB !== null && audio.duration ) { ui.loopBand.style.left = ( S.loopA / audio.duration * 100 ) + '%'; ui.loopBand.style.width = ( ( S.loopB - S.loopA ) / audio.duration * 100 ) + '%'; ui.loopBand.hidden = false; }
		else { ui.loopBand.hidden = true; }
	}
	function toggleAlt() { if ( ! current() ) { return; } S.alt = S.alt ? null : 'alt'; var playing = ! audio.paused; loadTrack( playing, true ); }

	/* ---------- cues and notes on the bar ---------- */
	function cuesForCurrent() {
		var t = current(); if ( ! t || t.song < 0 || S.list.key.indexOf( 'now:' ) !== 0 ) { return []; }
		var part = countPart();
		var out = D.cues.filter( function ( c ) { return c.song === t.song && ( ! c.parts || ! c.parts.length || c.parts.indexOf( part ) !== -1 ); } ).map( function ( c ) { return { t: c.t, text: c.text, kind: 'cue', id: c.id }; } );
		S.notes.filter( function ( n ) { return +n.song === t.song; } ).forEach( function ( n ) { out.push( { t: +n.t, text: n.text, kind: 'mine', id: n.id } ); } );
		return out.sort( function ( a, b ) { return a.t - b.t; } );
	}
	function paintMarks() {
		ui.marks.innerHTML = '';
		paintNotes();
		if ( ! audio.duration ) { return; }
		cuesForCurrent().forEach( function ( c ) {
			var m = h( 'button', { type: 'button', class: 'tcc-pr__mark tcc-pr__mark--' + c.kind, title: fmt( c.t ) + ' — ' + c.text, 'aria-label': 'Jump to ' + fmt( c.t ) + ': ' + c.text, onclick: function ( e ) { e.stopPropagation(); audio.currentTime = Math.max( 0, c.t - 1 ); showCue( c, true ); } } );
			m.addEventListener( 'pointerdown', function ( e ) { e.stopPropagation(); } );
			m.style.left = ( c.t / audio.duration * 100 ) + '%';
			ui.marks.appendChild( m );
		} );
	}
	var cueTimer = null, shownCue = null;
	function showCue( c, pinned ) {
		ui.cue.innerHTML = '';
		ui.cue.className = 'tcc-pr__cue is-on tcc-pr__cue--' + c.kind;
		ui.cue.appendChild( h( 'span', { class: 'tcc-pr__cue-k' }, [ ( c.kind === 'cue' ? 'Director' : 'Me' ) + ' · ' + fmt( c.t ) ] ) );
		ui.cue.appendChild( h( 'span', {}, [ c.text ] ) );
		if ( ( c.kind === 'mine' && S.who ) || ( c.kind === 'cue' && D.canEdit ) ) {
			ui.cue.appendChild( h( 'button', { type: 'button', class: 'tcc-pr__cue-x', 'aria-label': 'Remove this note', html: SVG.x, onclick: function () { removeNote( c ); } } ) );
		}
		shownCue = c;
		clearTimeout( cueTimer );
		cueTimer = setTimeout( function () { ui.cue.classList.remove( 'is-on' ); shownCue = null; }, pinned ? 12000 : 8000 );
	}
	function tickCues( prev, now ) {
		cuesForCurrent().forEach( function ( c ) { if ( c.t > prev && c.t <= now ) { showCue( c ); } } );
	}
	function noteForm( kind ) {
		if ( ! current() ) { return; }
		audio.pause();
		var at = audio.currentTime;
		ui.form.innerHTML = '';
		var input = h( 'input', { type: 'text', class: 'tcc-pr__input', placeholder: kind === 'cue' ? 'e.g. m. 42 — altos take the lower note here' : 'e.g. breathe before the high G', maxlength: '200', autocomplete: 'off' } );
		var partsBox = null;
		if ( kind === 'cue' ) {
			partsBox = h( 'div', { class: 'tcc-pr__form-parts' }, [ h( 'span', {}, [ 'Only for:' ] ) ] );
			VOICE.forEach( function ( p ) { partsBox.appendChild( h( 'label', {}, [ h( 'input', { type: 'checkbox', value: p, checked: p === countPart() ? '' : null } ), ' ' + ( D.partLabels[ p ] || p ) ] ) ); } );
			partsBox.appendChild( h( 'span', { class: 'tcc-pr__form-hint' }, [ '(none = everyone)' ] ) );
		}
		ui.form.appendChild( h( 'div', { class: 'tcc-pr__form-row' }, [
			h( 'span', { class: 'tcc-pr__form-k' }, [ ( kind === 'cue' ? 'Director note' : 'My note' ) + ' at ' + fmt( at ) ] ),
			input,
			h( 'button', { type: 'button', class: 'tcc-pr__btn tcc-pr__btn--primary', onclick: save }, [ 'Save' ] ),
			h( 'button', { type: 'button', class: 'tcc-pr__link', onclick: function () { ui.form.innerHTML = ''; } }, [ 'Cancel' ] )
		] ) );
		if ( partsBox ) { ui.form.appendChild( partsBox ); }
		input.focus();
		input.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Enter' ) { save(); } if ( e.key === 'Escape' ) { ui.form.innerHTML = ''; } } );
		function save() {
			var text = input.value.trim(); if ( ! text ) { input.focus(); return; }
			var t = current();
			if ( kind === 'cue' ) {
				var parts = partsBox ? Array.prototype.map.call( partsBox.querySelectorAll( 'input:checked' ), function ( c ) { return c.value; } ) : [];
				api( 'cue', 'POST', { cantata: D.cantata.id, song: t.song, t: at, text: text, parts: parts } ).then( function ( r ) { if ( r && r.cue ) { D.cues.push( r.cue ); } ui.form.innerHTML = ''; paintMarks(); showCue( { t: at, text: text, kind: 'cue', id: r && r.cue ? r.cue.id : '' }, true ); } );
			} else {
				api( 'note', 'POST', { cantata: D.cantata.id, song: t.song, t: at, text: text } ).then( function ( r ) { S.notes.push( { id: r && r.id, song: t.song, t: at, text: text } ); ui.form.innerHTML = ''; paintMarks(); showCue( { t: at, text: text, kind: 'mine', id: r && r.id }, true ); } );
			}
		}
	}
	function removeNote( c ) {
		if ( ! window.confirm( 'Remove this note?' ) ) { return; }
		if ( c.kind === 'cue' ) {
			api( 'cue', 'DELETE', { cantata: D.cantata.id, id: c.id } ).then( function () { D.cues = D.cues.filter( function ( x ) { return x.id !== c.id; } ); ui.cue.classList.remove( 'is-on' ); paintMarks(); } );
		} else {
			api( 'note', 'DELETE', { cantata: D.cantata.id, id: c.id } ).then( function () { S.notes = S.notes.filter( function ( x ) { return x.id !== c.id; } ); ui.cue.classList.remove( 'is-on' ); paintMarks(); } );
		}
	}

	/* ---------- counting practice ---------- */
	function flushPlay( complete ) {
		var t = current();
		if ( ! t || ! S.who || S.list.key.indexOf( 'now:' ) !== 0 || t.song < 0 ) { return; }
		var secs = Math.round( S.listened );
		if ( secs < 3 && ! complete ) { return; }
		var done = complete && ! S.counted ? 1 : 0;
		if ( done ) { S.counted = true; }
		var cp = countPart();
		var k = rowKey( t.song, cp ); S.rows[ k ] = S.rows[ k ] || { plays: 0, seconds: 0 };
		S.rows[ k ].plays = ( +S.rows[ k ].plays || 0 ) + done; S.rows[ k ].seconds = ( +S.rows[ k ].seconds || 0 ) + secs; if ( done ) { S.rows[ k ].last_played = new Date().toISOString().slice( 0, 19 ).replace( 'T', ' ' ); }
		api( 'play', 'POST', { cantata: D.cantata.id, song: t.song, part: cp, seconds: secs, complete: done }, S.list.cantata.token, true );
		S.listened = 0;
		if ( done ) { refreshLists(); }
	}
	audio.addEventListener( 'timeupdate', function () {
		var now = audio.currentTime, prev = S.lastT;
		if ( ! audio.paused && now > prev && now - prev < 2 ) { S.listened += now - prev; }
		S.lastT = now;
		if ( S.loopA !== null && S.loopB !== null && now >= S.loopB ) { audio.currentTime = S.loopA; }
		if ( ! S.counted && audio.duration && S.listened >= audio.duration * 0.6 ) { flushPlay( true ); }
		tickCues( prev, now ); tickNoteRows();
		var t = current(); if ( t ) { try { localStorage.setItem( 'tcc_pos_' + ( S.alt && altTrack() ? altTrack().track.id : t.id ), String( now ) ); } catch ( e ) {} }
		paint();
	} );
	audio.addEventListener( 'ended', function () {
		flushPlay( true );
		try { var t = current(); if ( t ) { localStorage.removeItem( 'tcc_pos_' + t.id ); } } catch ( e ) {}
		if ( S.loopA !== null ) { audio.currentTime = S.loopA; audio.play(); return; }
		step( 1 );
	} );
	audio.addEventListener( 'play', function () { ui.playBtn.innerHTML = SVG.pause; ui.playBtn.setAttribute( 'aria-label', 'Pause' ); markCurrent(); } );
	audio.addEventListener( 'pause', function () { ui.playBtn.innerHTML = SVG.play; ui.playBtn.setAttribute( 'aria-label', 'Play' ); markCurrent(); flushPlay(); } );
	audio.addEventListener( 'durationchange', function () { paint(); paintMarks(); paintLoop(); } );
	window.addEventListener( 'pagehide', function () { flushPlay(); } );
	function paint() {
		var d = audio.duration || 0, c = audio.currentTime || 0;
		ui.fill.style.width = d ? ( c / d * 100 ) + '%' : '0';
		ui.tcur.textContent = fmt( c ); ui.tdur.textContent = fmt( d );
		ui.scrub.setAttribute( 'aria-valuenow', Math.round( c ) ); ui.scrub.setAttribute( 'aria-valuemax', Math.round( d ) );
	}
	document.addEventListener( 'keydown', function ( e ) {
		if ( e.target.matches( 'input, textarea, select' ) || ! S.list ) { return; }
		if ( e.key === ' ' ) { e.preventDefault(); toggle(); }
	} );

	/* ---------- state ---------- */
	function loadState( switched ) {
		S.rows = {}; S.notes = [];
		if ( PAGE ) {
			api( 'me', 'GET', { cantata: D.cantata.id } ).then( function ( r ) {
				S.who = r && r.who ? { id: r.who.id, name: r.who.name, onSeason: r.who.onSeason, part: r.who.part } : null;
				var own = S.who ? partForVoice( S.who.part ) : '';
				if ( own && ( switched || ! urlPart() ) ) {
					var saved = ''; try { saved = localStorage.getItem( 'tcc_part' ) || ''; } catch ( e ) {}
					if ( switched || ! saved || ! LISTS[ 'now:' + saved ] ) { setPart( own, true ); }
				}
				renderWho(); renderSwitch(); loadRows();
			} ).catch( function () { renderWho(); refreshLists(); } );
			return;
		}
		loadRows();
	}
	function loadRows() {
		if ( ! S.who ) { refreshLists(); return; }
		api( 'state', 'GET', { cantata: D.cantata.id } ).then( function ( r ) {
			if ( ! r || ! r.who ) { S.who = null; renderWho(); refreshLists(); return; }
			( r.rows || [] ).forEach( function ( row ) { S.rows[ rowKey( +row.song, row.part ) ] = row; } );
			S.notes = r.notes || [];
			refreshLists(); paintMarks();
		} ).catch( function () { refreshLists(); } );
	}

	build();
	loadState();
	/* a deep link (#song-3) selects that song straight away, ready to play */
	if ( PAGE ) {
		var want = urlSong();
		if ( want >= 0 && LISTS[ S.ownKey ] ) {
			var at = -1; LISTS[ S.ownKey ].tracks.some( function ( t, i ) { if ( t.song === want ) { at = i; return true; } return false; } );
			if ( at >= 0 ) { S.list = LISTS[ S.ownKey ]; S.idx = at; S.playPart = D.part; loadTrack( false ); }
		}
	}
} )();
