/* Tilden Cantata — the Backstage dashboard.
   The page HTML is the same for everyone; this asks /me who the device belongs to and draws that
   person's dashboard. Every piece of information appears once, where it is used:
     greeting + countdown · up next (when, where, what the director wants) · your songs (the whole work
     as tiles, gently showing how each is going, the next rehearsal's songs marked) · your calendar ·
     your team and your documents.
   No practice numbers anywhere a singer sees them. */
( function () {
	'use strict';
	var root = document.querySelector( '[data-tcc-dash]' );
	if ( ! root ) { return; }
	var W = JSON.parse( root.getAttribute( 'data-tcc-dash' ) );
	root.removeAttribute( 'data-tcc-dash' );

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
	function api( path, body ) {
		var url = W.rest + path + '?' + Object.keys( body ).map( function ( k ) { return k + '=' + encodeURIComponent( body[ k ] ); } ).join( '&' );
		return fetch( url, { headers: { 'X-TCC-Token': W.token }, credentials: 'same-origin', cache: 'no-store' } ).then( function ( r ) { return r.json(); } );
	}
	function store( kind, k, v ) { try { var s = kind === 'ss' ? sessionStorage : localStorage; if ( v === undefined ) { return s.getItem( k ); } if ( v === null ) { s.removeItem( k ); } else { s.setItem( k, v ); } } catch ( e ) {} return null; }
	function ymd( d ) { return d.getFullYear() + '-' + ( '0' + ( d.getMonth() + 1 ) ).slice( -2 ) + '-' + ( '0' + d.getDate() ).slice( -2 ); }
	function relDay( date ) {
		var d = new Date(); if ( date === ymd( d ) ) { return 'Tonight'; }
		d.setDate( d.getDate() + 1 ); if ( date === ymd( d ) ) { return 'Tomorrow'; }
		var then = new Date( date + 'T12:00:00' ), now = new Date(); now.setHours( 12, 0, 0, 0 );
		var days = Math.round( ( then - now ) / 864e5 );
		return days > 1 && days < 7 ? 'This ' + then.toLocaleDateString( undefined, { weekday: 'long' } ) : days >= 7 && days < 14 ? 'Next week' : '';
	}
	function greeting() { var hr = new Date().getHours(); return hr < 12 ? 'Good morning' : hr < 17 ? 'Good afternoon' : 'Good evening'; }
	var ICON = {
		play: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 4.5v15l12.5-7.5z"/></svg>',
		star: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 1 L13.4 10.6 L23 12 L13.4 13.4 L12 23 L10.6 13.4 L1 12 L10.6 10.6 Z"/></svg>',
		pin: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s-7-6.2-7-12a7 7 0 0 1 14 0c0 5.8-7 12-7 12z"/><circle cx="12" cy="10" r="2.5"/></svg>',
		arrow: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>',
		share: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>'
	};
	function ring( st ) {
		if ( st === 'good' ) { return '<svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7" fill="currentColor"/></svg>'; }
		if ( st === 'some' ) { return '<svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M10 3a7 7 0 0 1 0 14z" fill="currentColor"/></svg>'; }
		if ( st === 'help' ) { return '<svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7" fill="none" stroke="currentColor" stroke-width="1.6"/><text x="10" y="13.8" text-anchor="middle" font-size="10" font-weight="700" fill="currentColor" font-family="sans-serif">?</text></svg>'; }
		return '<svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>';
	}
	function pills( who ) {
		var box = h( 'span', { class: 'tcc-db__pills' } );
		if ( ! who || ! who.length ) { box.appendChild( h( 'span', { class: 'tcc-who tcc-who--all' }, [ 'Everyone' ] ) ); return box; }
		who.forEach( function ( k ) {
			var role = k.indexOf( 'role:' ) === 0;
			box.appendChild( h( 'span', { class: 'tcc-who tcc-who--' + ( role ? 'role' : k ) }, [ role ? ( ( W.roles[ k.slice( 5 ) ] || {} ).label || k.slice( 5 ) ) : ( W.types[ k ] || k ) ] ) );
		} );
		return box;
	}
	function practiceUrl( part, song ) {
		var u = W.urls.practice || W.urls.hub;
		if ( part ) { u += ( u.indexOf( '?' ) === -1 ? '?' : '&' ) + 'part=' + part; }
		if ( song !== undefined && song !== null && song >= 0 ) { u += '#song-' + ( song + 1 ); }
		return u;
	}
	function eyebrow( text, extra ) { return h( 'div', { class: 'tcc-db__eyebrow' }, [ h( 'span', {}, [ text ] ), extra || null ] ); }

	/* ---------- the person ---------- */
	var me = null, rows = {};
	function P() { return me.who; }
	function forMe( who ) {
		if ( ! who || ! who.length ) { return true; }
		return who.some( function ( k ) { return P().types.indexOf( k ) !== -1 || ( P().role && k === 'role:' + P().role ); } );
	}
	function requiredOfMe( r ) { return !! ( r.must && r.must.length && forMe( r.must ) ); }
	function myTrackParts() { var v = W.voice[ P().part ]; return v ? v.tracks.filter( function ( p ) { return W.parts[ p ] && W.parts[ p ].count; } ) : []; }
	function isSinger() { return P().types.indexOf( 'singers' ) !== -1; }
	function stateOf( song ) {
		var flag = '', plays = 0, secs = 0;
		myTrackParts().forEach( function ( p ) { var r = rows[ song + '|' + p ]; if ( r ) { plays += r.plays || 0; secs += r.seconds || 0; if ( r.flag ) { flag = r.flag; } } } );
		if ( flag === 'ok' ) { return 'good'; }
		if ( flag === 'help' ) { return 'help'; }
		if ( flag === 'working' ) { return 'some'; }
		if ( plays >= 3 ) { return 'good'; }
		if ( plays >= 1 || secs >= 45 ) { return 'some'; }
		return 'none';
	}

	/* ---------- views ---------- */
	function show( nodes ) { root.innerHTML = ''; nodes.forEach( function ( n ) { if ( n ) { root.appendChild( n ); } } ); }
	function signIn( start, who ) {
		var cfg = W.signin; cfg.who = who || null;
		window.tccSignin.open( cfg, function () { store( 'ss', 'tcc_browse', null ); load(); }, start );
	}

	/* an unknown device: three equal doors */
	function viewChooser() {
		show( [ h( 'section', { class: 'tcc-dash__choose' }, [
			h( 'span', { class: 'tcc-dash__eyebrow' }, [ W.cantata.year + ' · ' + W.cantata.title ] ),
			h( 'h2', { class: 'tcc-dash__h' }, [ 'Welcome backstage.' ] ),
			h( 'p', { class: 'tcc-dash__lede' }, [ 'Tell us who you are once on this device and Backstage becomes your page: your rehearsals, your part’s tracks, your notes. It also replaces the paper sign-up sheet.' ] ),
			h( 'div', { class: 'tcc-dash__doors' }, [
				h( 'button', { type: 'button', class: 'tcc-dash__door', onclick: function () { signIn( 'find' ); } }, [ h( 'b', {}, [ 'I’ve sung with the choir before' ] ), h( 'span', {}, [ 'Find my name on the list' ] ) ] ),
				h( 'button', { type: 'button', class: 'tcc-dash__door', onclick: function () { signIn( 'new' ); } }, [ h( 'b', {}, [ 'I’m new this year' ] ), h( 'span', {}, [ 'Sign me up — it takes a minute' ] ) ] ),
				h( 'button', { type: 'button', class: 'tcc-dash__door tcc-dash__door--quiet', onclick: function () { store( 'ss', 'tcc_browse', '1' ); viewBrowse(); } }, [ h( 'b', {}, [ 'Just browsing for now' ] ), h( 'span', {}, [ 'Have a look around; you can sign in any time' ] ) ] )
			] )
		] ) ] );
	}

	/* a known device whose person isn't on this season yet */
	function viewWelcomeBack( who ) {
		var first = who.first || who.name.split( ' ' )[ 0 ];
		show( [ h( 'section', { class: 'tcc-dash__choose tcc-dash__choose--back' }, [
			h( 'span', { class: 'tcc-dash__eyebrow' }, [ W.cantata.year + ' · ' + W.cantata.title ] ),
			h( 'h2', { class: 'tcc-dash__h' }, [ 'Welcome back, ' + first + '!' ] ),
			h( 'p', { class: 'tcc-dash__lede' }, [ 'Taking part in ' + W.cantata.title + '? Confirm your details and this becomes your page. It replaces the paper sign-up sheet.' ] ),
			h( 'div', { class: 'tcc-dash__doors' }, [
				h( 'button', { type: 'button', class: 'tcc-dash__door tcc-dash__door--primary', onclick: function () { signIn( undefined, { id: who.id, name: who.name, onSeason: false } ); } }, [ h( 'b', {}, [ 'Yes, I’m in this year' ] ), h( 'span', {}, [ 'Check my details' ] ) ] ),
				h( 'button', { type: 'button', class: 'tcc-dash__door tcc-dash__door--quiet', onclick: function () { store( 'ss', 'tcc_browse', '1' ); viewBrowse(); } }, [ h( 'b', {}, [ 'Just browsing for now' ] ), h( 'span', {}, [ 'Have a look around first' ] ) ] )
			] ),
			h( 'p', { class: 'tcc-dash__foot' }, [ 'Not ' + first + '? ', h( 'button', { type: 'button', class: 'tcc-dash__link', onclick: function () { signIn( 'find' ); } }, [ 'Find your name' ] ), ' · ', h( 'button', { type: 'button', class: 'tcc-dash__link', onclick: function () { signIn( 'new' ); } }, [ 'I’m new' ] ) ] )
		] ) ] );
	}

	/* the general page, for anyone browsing */
	function viewBrowse() {
		var who = me && me.who;
		var nodes = [ h( 'p', { class: 'tcc-dash__line' }, [ who ? 'Browsing as ' : 'Browsing without signing in. ', who ? h( 'b', {}, [ who.name ] ) : null, who ? '. ' : '',
			h( 'button', { type: 'button', class: 'tcc-dash__link', onclick: function () { store( 'ss', 'tcc_browse', null ); if ( who ) { if ( who.onSeason ) { viewDash(); } else { signIn( undefined, { id: who.id, name: who.name, onSeason: false } ); } } else { viewChooser(); } } }, [ who ? ( who.onSeason ? 'Show my page' : 'Sign in for this year' ) : 'Sign in' ] ) ] ) ];
		var next = W.rehearsals[ 0 ];
		if ( next ) { nodes.push( upNext( next, false ) ); }
		var parts = h( 'div', { class: 'tcc-db__partpick' } );
		[ 'soprano', 'alto', 'tenor', 'bass', 'accompaniment', 'sections', 'listen' ].forEach( function ( p ) {
			if ( W.parts[ p ] && W.parts[ p ].count ) { parts.appendChild( h( 'a', { class: 'tcc-db__part', href: practiceUrl( p ) }, [ h( 'span', { html: ICON.play } ), W.parts[ p ].label ] ) ); }
		} );
		if ( parts.children.length ) { nodes.push( h( 'section', { class: 'tcc-db__sec' }, [ eyebrow( 'Practice tracks' ), parts ] ) ); }
		var later = W.rehearsals.slice( 1, 6 );
		if ( later.length ) { nodes.push( calendar( later, false ) ); }
		var docs = docList( [ 'everyone', 'singers', 'narrators', 'actors', 'tech' ], true );
		if ( docs ) { nodes.push( h( 'section', { class: 'tcc-db__sec' }, [ eyebrow( 'Documents' ), docs ] ) ); }
		show( nodes );
	}

	/* ---------- pieces ---------- */

	/* up next: when, where you need to be, what the director wants — songs are not listed here (they live in Your songs) */
	function upNext( r, personal ) {
		var req = personal ? requiredOfMe( r ) : !! ( r.must && r.must.length );
		var rel = relDay( r.date );
		var bits = [];
		if ( r.type === 'performance' ) { bits.push( h( 'span', { class: 'tcc-db__flag tcc-db__flag--perf' }, [ 'Performance' ] ) ); }
		if ( req ) { bits.push( h( 'span', { class: 'tcc-db__flag tcc-db__flag--req' }, [ personal ? 'Required for you' : 'Attendance required' ] ) ); }
		if ( r.full ) { bits.push( h( 'span', { class: 'tcc-db__flag tcc-db__flag--full', html: W.icons.note + '<span>Full run-through</span>' } ) ); }
		var covers = null;
		if ( ! r.full && r.songs.length ) {
			var fc = r.focus.filter( function ( i ) { return r.songs.indexOf( i ) !== -1; } ).length;
			covers = personal && isSinger() && myTrackParts().length
				? h( 'a', { class: 'tcc-db__covers', href: '#tcc-songs' }, [ 'Covers ' + r.songs.length + ( r.songs.length === 1 ? ' song' : ' songs' ) + ( fc ? ', ' + fc + ' starred' : '' ) + ' — marked below', h( 'span', { html: ICON.arrow } ) ] )
				: h( 'p', { class: 'tcc-db__covers' }, [ 'Covers ' + r.songs.map( function ( i ) { return ( i + 1 ) + ( r.focus.indexOf( i ) !== -1 ? '★' : '' ); } ).join( ', ' ) ] );
		}
		var after = r.after.filter( function ( a ) { return ! personal || forMe( a.who ); } );
		return h( 'section', { class: 'tcc-db__next' + ( req ? ' is-req' : '' ) }, [
			h( 'div', { class: 'tcc-db__cal' }, [ h( 'span', {}, [ r.mon ] ), h( 'b', {}, [ r.day ] ), h( 'span', {}, [ r.dow ] ) ] ),
			h( 'div', { class: 'tcc-db__next-body' }, [
				h( 'span', { class: 'tcc-db__next-k' }, [ ( rel ? rel + ' · ' : '' ) + ( r.type === 'performance' ? 'Performance' : 'Up next' ) ] ),
				h( 'h3', { class: 'tcc-db__next-t' }, [ r.time ? r.time : r.long, r.time ? h( 'span', {}, [ ' · ' + r.title ] ) : null ] ),
				bits.length ? h( 'div', { class: 'tcc-db__flags' }, bits ) : null,
				personal ? null : h( 'div', { class: 'tcc-db__whofor' }, [ 'For ', pills( r.who ) ] ),
				covers,
				after.length ? h( 'ul', { class: 'tcc-db__after' }, after.map( function ( a ) { return h( 'li', {}, [ h( 'span', { class: 'tcc-db__after-i', html: W.icons.then } ), a.time ? h( 'b', {}, [ a.time ] ) : null, ' ' + a.what, personal ? null : pills( a.who ) ] ); } ) ) : null,
				r.note ? h( 'div', { class: 'tcc-db__note' }, [ h( 'span', { class: 'tcc-db__note-k' }, [ 'From the director' ] ), h( 'div', { html: r.note } ) ] ) : null
			] )
		] );
	}

	/* your songs: the whole work, one tile per song */
	function songs( next ) {
		var parts = myTrackParts();
		var primary = parts[ 0 ];
		var have = {}; parts.forEach( function ( p ) { W.parts[ p ].songs.forEach( function ( i ) { have[ i ] = true; } ); } );
		var nextSongs = next && ! next.full ? next.songs : [];
		var focus = next ? next.focus : [];
		var tag = next ? next.dow : '';
		var grid = h( 'ol', { class: 'tcc-db__tiles' } );
		W.songs.forEach( function ( title, i ) {
			var ok = have[ i ], st = ok ? stateOf( i ) : '', inNext = nextSongs.indexOf( i ) !== -1, isF = focus.indexOf( i ) !== -1;
			var inner = [
				h( 'span', { class: 'tcc-db__tile-top' }, [ h( 'span', { class: 'tcc-db__tile-n' }, [ String( i + 1 ) ] ), st ? h( 'span', { class: 'tcc-db__ring tcc-db__ring--' + st, title: { none: 'Not started', some: 'Getting there', good: 'Feeling good', help: 'You asked for help' }[ st ], html: ring( st ) } ) : null ] ),
				h( 'span', { class: 'tcc-db__tile-t' }, [ title ] ),
				( inNext || isF ) ? h( 'span', { class: 'tcc-db__tile-tags' }, [ inNext ? h( 'span', { class: 'tcc-db__tile-tag' }, [ tag ] ) : null, isF ? h( 'span', { class: 'tcc-db__tile-star', title: 'The director says: focus on this one' }, [ '★ Focus' ] ) : null ] ) : null
			];
			grid.appendChild( h( 'li', {}, [ ok
				? h( 'a', { class: 'tcc-db__tile' + ( inNext ? ' is-next' : '' ) + ( isF ? ' is-focus' : '' ), href: practiceUrl( primary, i ) }, inner )
				: h( 'span', { class: 'tcc-db__tile is-off', title: 'No track for your part yet' }, inner ) ] ) );
		} );
		/* the one big button: pick up where you left off, else the next rehearsal's first song */
		var last = null; try { last = JSON.parse( store( 'ls', 'tcc_last' ) || 'null' ); } catch ( e ) {}
		var go;
		if ( last && last.song >= 0 && W.songs[ last.song ] && W.parts[ last.part ] ) {
			go = h( 'a', { class: 'tcc-db__go', href: practiceUrl( last.part, last.song ) }, [ h( 'span', { class: 'tcc-db__go-i', html: ICON.play } ), h( 'span', { class: 'tcc-db__go-t' }, [ h( 'small', {}, [ 'Pick up where you left off' ] ), ( last.song + 1 ) + '. ' + W.songs[ last.song ] ] ) ] );
		} else {
			var first = nextSongs.filter( function ( i ) { return have[ i ]; } ).sort( function ( a, b ) { return ( focus.indexOf( b ) !== -1 ) - ( focus.indexOf( a ) !== -1 ) || a - b; } )[ 0 ];
			go = h( 'a', { class: 'tcc-db__go', href: practiceUrl( primary, first ) }, [ h( 'span', { class: 'tcc-db__go-i', html: ICON.play } ), h( 'span', { class: 'tcc-db__go-t' }, [ h( 'small', {}, [ first !== undefined ? 'Start with ' + tag + '’s songs' : 'Open the practice app' ] ), first !== undefined ? ( first + 1 ) + '. ' + W.songs[ first ] : W.voice[ P().part ].label + ' tracks' ] ) ] );
		}
		return h( 'section', { class: 'tcc-db__sec', id: 'tcc-songs' }, [
			eyebrow( 'Your songs', h( 'a', { class: 'tcc-db__eyebrow-link', href: practiceUrl( primary ) }, [ W.voice[ P().part ].label + ' tracks' ] ) ),
			go,
			grid,
			h( 'p', { class: 'tcc-db__legend' }, [
				h( 'span', { class: 'tcc-db__ring tcc-db__ring--none', html: ring( 'none' ) } ), 'Not started',
				h( 'span', { class: 'tcc-db__ring tcc-db__ring--some', html: ring( 'some' ) } ), 'Getting there',
				h( 'span', { class: 'tcc-db__ring tcc-db__ring--good', html: ring( 'good' ) } ), 'Feeling good',
				h( 'span', { class: 'tcc-db__legend-note' }, [ 'Just for you — mark how a song is going in the practice app.' ] )
			] )
		] );
	}

	/* the calendar: your dates after the next one */
	function calendar( list, personal ) {
		var ol = h( 'ol', { class: 'tcc-db__dates' } );
		list.forEach( function ( r ) {
			var req = personal ? requiredOfMe( r ) : !! ( r.must && r.must.length );
			var after = r.after.filter( function ( a ) { return personal && forMe( a.who ); } );
			var what = r.type === 'performance' ? 'Performance' : r.full ? 'Full run-through' : r.title;
			ol.appendChild( h( 'li', { class: 'tcc-db__date' + ( req ? ' is-req' : '' ) + ( r.type === 'performance' ? ' is-perf' : '' ) }, [
				h( 'span', { class: 'tcc-db__date-d' }, [ h( 'b', {}, [ r.day ] ), h( 'span', {}, [ r.mon ] ) ] ),
				h( 'span', { class: 'tcc-db__date-t' }, [
					h( 'b', {}, [ r.dow + ( r.time ? ' · ' + r.time : '' ) ] ),
					h( 'span', {}, [ what, after.length ? ' + ' + after.map( function ( a ) { return a.what; } ).join( ', ' ) : '' ] )
				] ),
				req ? h( 'span', { class: 'tcc-db__date-req' }, [ 'Required' ] ) : personal ? null : pills( r.who )
			] ) );
		} );
		return h( 'section', { class: 'tcc-db__sec' }, [ eyebrow( personal ? 'Your calendar' : 'Coming up', W.urls.schedule ? h( 'a', { class: 'tcc-db__eyebrow-link', href: W.urls.schedule }, [ 'Full schedule' ] ) : null ), ol ] );
	}

	function docList( groups, all ) {
		var seen = {}, items = [];
		groups.forEach( function ( g ) { ( W.docs[ g ] || [] ).forEach( function ( d ) { if ( ! seen[ d.url ] ) { seen[ d.url ] = 1; items.push( { d: d, g: g } ); } } ); } );
		if ( ! items.length ) { return null; }
		return h( 'ul', { class: 'tcc-db__docs' }, items.map( function ( x ) {
			return h( 'li', {}, [ h( 'a', { href: x.d.url, target: '_blank', rel: 'noopener', class: 'tcc-db__doc' }, [
				h( 'span', { class: 'tcc-db__doc-i', html: W.icons[ x.d.icon ] || W.icons.doc } ),
				h( 'span', { class: 'tcc-db__doc-t' }, [ h( 'b', {}, [ x.d.title ] ), x.d.note ? h( 'small', {}, [ x.d.note ] ) : ( all && x.g !== 'everyone' ? h( 'small', {}, [ W.types[ x.g ] ] ) : null ) ] )
			] ) ] );
		} ) );
	}

	function teamCard() {
		var who = P();
		if ( ! who.role || ! W.roles[ who.role ] ) {
			return h( 'section', { class: 'tcc-db__card' }, [ h( 'span', { class: 'tcc-db__card-k' }, [ 'Your team' ] ), h( 'p', {}, [ 'Alongside singing, everyone takes on one small job for the season.' ] ), h( 'button', { type: 'button', class: 'tcc-dash__link', onclick: function () { signIn( undefined, { id: who.id, name: who.name, onSeason: true } ); } }, [ 'Pick a team' ] ) ] );
		}
		var role = W.roles[ who.role ], key = 'role:' + who.role, meet = null;
		W.rehearsals.some( function ( r ) {
			var a = r.after.filter( function ( x ) { return x.who.indexOf( key ) !== -1; } )[ 0 ];
			if ( a ) { meet = r.dow + ' ' + r.mon + ' ' + r.day + ( a.time ? ', ' + a.time : ', after rehearsal' ) + ' — ' + a.what; return true; }
			if ( r.who.indexOf( key ) !== -1 ) { meet = r.dow + ' ' + r.mon + ' ' + r.day + ( r.time ? ', ' + r.time : '' ) + ' — ' + r.title; return true; }
			return false;
		} );
		var others = ( W.teams[ who.role ] || [] ).filter( function ( n ) { return n !== who.name; } );
		return h( 'section', { class: 'tcc-db__card' }, [
			h( 'span', { class: 'tcc-db__card-k' }, [ 'Your team' ] ),
			h( 'h4', { class: 'tcc-db__card-t' }, [ role.label ] ),
			role.desc ? h( 'p', {}, [ role.desc.charAt( 0 ).toUpperCase() + role.desc.slice( 1 ) + '.' ] ) : null,
			meet ? ( function () { var m = h( 'p', { class: 'tcc-db__card-meet', html: W.icons.then } ); m.appendChild( h( 'span', {}, [ 'Meets ' + meet ] ) ); return m; } )() : null,
			h( 'p', { class: 'tcc-db__card-fine' }, [ others.length ? 'With ' + others.join( ', ' ) + '.' : 'You’re the first on this team.' ] )
		] );
	}

	/* "Put Backstage on your home screen" */
	var installEvt = null;
	window.addEventListener( 'beforeinstallprompt', function ( e ) { e.preventDefault(); installEvt = e; } );
	function homeScreenCard() {
		var standalone = ( window.matchMedia && window.matchMedia( '(display-mode: standalone)' ).matches ) || window.navigator.standalone;
		var touch = window.matchMedia && window.matchMedia( '(pointer: coarse)' ).matches;
		if ( standalone || ! touch || store( 'ls', 'tcc_a2hs' ) ) { return null; }
		var ios = /iphone|ipad|ipod/i.test( navigator.userAgent ) || ( navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1 );
		var card = h( 'section', { class: 'tcc-db__a2hs' }, [
			h( 'img', { src: W.icon, alt: '', width: '52', height: '52' } ),
			h( 'div', {}, [
				h( 'b', {}, [ 'Put Backstage on your home screen' ] ),
				h( 'p', { html: ios ? 'In Safari, tap <span class="tcc-db__ios">' + ICON.share + '</span> <b>Share</b>, then <b>Add to Home Screen</b>. It opens like an app — you’ll enter the password once more there.' : 'Tap your browser’s <b>⋮</b> menu, then <b>Add to Home screen</b> (or <b>Install app</b>). It opens like an app.' } ),
				h( 'div', { class: 'tcc-db__a2hs-go' }, [
					! ios && installEvt ? h( 'button', { type: 'button', class: 'tcc-db__btn', onclick: function () { installEvt.prompt(); installEvt.userChoice.then( function () { store( 'ls', 'tcc_a2hs', '1' ); card.remove(); } ); } }, [ 'Add it now' ] ) : null,
					h( 'button', { type: 'button', class: 'tcc-dash__link', onclick: function () { store( 'ls', 'tcc_a2hs', '1' ); card.remove(); } }, [ ios || ! installEvt ? 'Got it' : 'Not now' ] )
				] )
			] )
		] );
		return card;
	}

	/* the person's own dashboard */
	function viewDash() {
		var who = P();
		var chips = [];
		if ( isSinger() && W.voice[ who.part ] ) { chips.push( W.voice[ who.part ].label ); }
		who.types.forEach( function ( t ) { if ( t !== 'singers' && W.types[ t ] ) { chips.push( W.types[ t ].replace( /s$/, '' ) ); } } );
		if ( who.role && W.roles[ who.role ] ) { chips.push( W.roles[ who.role ].label + ' team' ); }
		var days = W.cantata.days;
		var head = h( 'header', { class: 'tcc-db__hello' }, [
			h( 'div', {}, [
				h( 'span', { class: 'tcc-db__hello-k' }, [ greeting() + ',' ] ),
				h( 'h2', { class: 'tcc-db__hello-name' }, [ who.first || who.name.split( ' ' )[ 0 ] ] ),
				h( 'div', { class: 'tcc-db__chips' }, chips.map( function ( c, i ) { return h( 'span', { class: 'tcc-db__chip' + ( i === 0 ? ' is-main' : '' ) }, [ c ] ); } ) )
			] ),
			days !== null && days !== undefined && days >= 0 ? h( 'div', { class: 'tcc-db__count' }, [ h( 'b', {}, [ days === 0 ? 'Today' : String( days ) ] ), h( 'span', {}, [ days === 0 ? W.cantata.title + ' — tonight’s the night' : ( days === 1 ? 'day' : 'days' ) + ' until ' + W.cantata.title ] ) ] ) : null
		] );
		var nodes = [ head, homeScreenCard() ];

		var mine = W.rehearsals.filter( function ( r ) { return forMe( r.who ); } );
		var next = mine[ 0 ], first = W.rehearsals[ 0 ];
		if ( next ) {
			if ( first && first.id !== next.id ) { nodes.push( h( 'p', { class: 'tcc-db__skip' }, [ first.dow + ' ' + first.mon + ' ' + first.day + ' is for ', pills( first.who ), ' — you’re off that night.' ] ) ); }
			nodes.push( upNext( next, true ) );
		} else {
			nodes.push( h( 'p', { class: 'tcc-db__skip' }, [ 'Nothing on the calendar for you right now.' ] ) );
		}

		if ( isSinger() ) {
			if ( myTrackParts().length ) { nodes.push( songs( next ) ); }
			else { nodes.push( h( 'section', { class: 'tcc-db__sec' }, [ eyebrow( 'Your songs' ), h( 'p', { class: 'tcc-db__skip' }, [ W.voice[ who.part ] ? W.voice[ who.part ].label + ' tracks aren’t up yet — check back soon.' : 'We don’t have a voice part for you yet — the director will help you find it at the next rehearsal.', W.urls.practice ? ' ' : '', W.urls.practice ? h( 'a', { href: W.urls.practice }, [ 'Listen to what’s there' ] ) : null ] ) ] ) ); }
		}

		/* non-singers: their materials come straight after Up next */
		var groups = [ 'everyone' ].concat( who.types.filter( function ( t ) { return W.types[ t ]; } ) );
		var docs = docList( groups, false );
		if ( ! isSinger() && docs ) { nodes.push( h( 'section', { class: 'tcc-db__sec' }, [ eyebrow( 'Your materials' ), docs ] ) ); docs = null; }

		var later = mine.slice( 1, 7 );
		if ( later.length ) { nodes.push( calendar( later, true ) ); }

		var pair = h( 'div', { class: 'tcc-db__pair' }, [ teamCard(), docs ? h( 'section', { class: 'tcc-db__card' }, [ h( 'span', { class: 'tcc-db__card-k' }, [ 'Documents' ] ), docs ] ) : null ] );
		nodes.push( pair );
		if ( ! isSinger() && W.urls.practice ) { nodes.push( h( 'p', { class: 'tcc-db__skip' }, [ 'Want to hear the music? ', h( 'a', { href: practiceUrl( W.parts.listen && W.parts.listen.count ? 'listen' : '' ) }, [ 'Open the practice app' ] ) ] ) ); }

		nodes.push( h( 'p', { class: 'tcc-db__foot' }, [ 'Signed in as ' + who.name + '. ', h( 'button', { type: 'button', class: 'tcc-dash__link', onclick: function () { signIn( undefined, { id: who.id, name: who.name, onSeason: true } ); } }, [ 'Not you, or details changed?' ] ), W.canEdit && W.admin ? ' · ' : '', W.canEdit && W.admin ? h( 'a', { href: W.admin }, [ 'Director: what the choir is practicing' ] ) : null ] ) );
		show( nodes );
	}

	/* ---------- go ---------- */
	function load() {
		root.innerHTML = '';
		root.appendChild( h( 'p', { class: 'tcc-dash__loading' }, [ 'One moment…' ] ) );
		api( 'me', { cantata: W.cantata.id } ).then( function ( r ) {
			me = r || {}; rows = {};
			( r.rows || [] ).forEach( function ( row ) { rows[ row.song + '|' + row.part ] = row; } );
			if ( store( 'ss', 'tcc_browse' ) ) { viewBrowse(); return; }
			if ( ! r.who ) { viewChooser(); return; }
			if ( ! r.who.onSeason ) { viewWelcomeBack( r.who ); return; }
			viewDash();
		} ).catch( function () { me = {}; viewBrowse(); } );
	}
	load();
} )();
