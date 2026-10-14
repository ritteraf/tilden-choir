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
	function practiceUrl( part, song ) {
		var u = W.urls.practice || W.urls.hub;
		if ( part ) { u += ( u.indexOf( '?' ) === -1 ? '?' : '&' ) + 'part=' + part; }
		if ( song !== undefined && song !== null && song >= 0 ) { u += '#song-' + ( song + 1 ); }
		return u;
	}

	/* ---------- the person ---------- */
	var me = null, rows = {};
	function P() { return me && me.who; }
	function forMe( who ) {
		if ( ! who || ! who.length || ! P() ) { return true; }
		return who.some( function ( k ) { return P().types.indexOf( k ) !== -1 || ( P().role && k === 'role:' + P().role ); } );
	}
	function requiredOfMe( r ) { return !! ( P() && r.must && r.must.length && forMe( r.must ) ); }
	function myTrackParts() { var v = W.voice[ P().part ]; return v ? v.tracks.filter( function ( p ) { return W.parts[ p ] && W.parts[ p ].count; } ) : []; }
	function isSinger() { return P().types.indexOf( 'singers' ) !== -1; }
	/* the circle is the singer's own mark, nothing else */
	function markOf( song ) {
		var flag = '';
		myTrackParts().forEach( function ( p ) { var r = rows[ song + '|' + p ]; if ( r && r.flag ) { flag = r.flag; } } );
		return { ok: 'good', working: 'some', help: 'help' }[ flag ] || 'none';
	}
	/* what they've actually done: completed plays, and when they last played it */
	function playsOf( song ) {
		var n = 0, last = '';
		myTrackParts().forEach( function ( p ) { var r = rows[ song + '|' + p ]; if ( r ) { n += r.plays || 0; if ( r.last && r.last > last ) { last = r.last; } } } );
		return { n: n, last: last };
	}
	function whenShort( s ) {
		if ( ! s ) { return ''; }
		var d = new Date( s.replace( ' ', 'T' ) ); if ( isNaN( d ) ) { return ''; }
		var t = new Date(); t.setHours( 0, 0, 0, 0 ); var dd = new Date( d ); dd.setHours( 0, 0, 0, 0 );
		var days = Math.round( ( t - dd ) / 864e5 );
		if ( days <= 0 ) { return 'today'; }
		if ( days === 1 ) { return 'yesterday'; }
		if ( days < 7 ) { return d.toLocaleDateString( undefined, { weekday: 'short' } ); }
		return d.toLocaleDateString( undefined, { month: 'short', day: 'numeric' } );
	}

	/* ---------- views ---------- */
	function show( nodes ) { root.innerHTML = ''; nodes.forEach( function ( n ) { if ( n ) { root.appendChild( n ); } } ); }
	function signIn( start, who ) {
		var cfg = W.signin; cfg.who = who || null;
		window.tccSignin.open( cfg, function () { store( 'ss', 'tcc_browse', null ); load(); }, start );
	}

	/* ---------- the doors: for a device Backstage doesn't know yet ---------- */
	var DOOR = {
		find: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="7" r="4"/><path d="M2 21v-2a4 4 0 0 1 4-4h6"/><path d="m16 19 2 2 4-4"/></svg>',
		add: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="7" r="4"/><path d="M2 21v-2a4 4 0 0 1 4-4h6"/><path d="M19 14v6M16 17h6"/></svg>',
		look: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>',
		user: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></svg>'
	};
	function browse() { store( 'ss', 'tcc_browse', '1' ); route(); window.scrollTo( 0, 0 ); }
	function door( icon, title, sub, onclick, cls ) {
		return h( 'button', { type: 'button', class: 'tcc-hm__w tcc-hm__door' + ( cls ? ' ' + cls : '' ), onclick: onclick }, [
			h( 'span', { class: 'tcc-hm__door-i', html: DOOR[ icon ] } ),
			h( 'span', { class: 'tcc-hm__door-t' }, [ h( 'b', {}, [ title ] ), h( 'span', {}, [ sub ] ) ] ),
			h( 'span', { class: 'tcc-hm__door-go', html: SVG.right } )
		] );
	}
	/* the page head for someone Backstage doesn't know: the same greeting spot, with a way to say who you are */
	function guestHead( title, lede ) {
		return h( 'header', { class: 'tcc-hm__head tcc-hm__head--wrap' }, [
			h( 'div', { class: 'tcc-hm__head-t' }, [
				h( 'span', { class: 'tcc-hm__eyebrow' }, [ W.cantata.year + ' · ' + W.cantata.title ] ),
				h( 'h2', { class: 'tcc-hm__hello' }, [ title ] )
			] ),
			h( 'button', { type: 'button', class: 'tcc-hm__me tcc-hm__me--guest', 'aria-label': 'Sign in — who’s practicing?', onclick: openSwitch }, [ h( 'span', { class: 'tcc-hm__av tcc-hm__av--guest', html: DOOR.user } ), h( 'span', { class: 'tcc-hm__me-n' }, [ 'Sign in' ] ) ] ),
			lede ? h( 'p', { class: 'tcc-hm__lede' }, lede ) : null
		] );
	}

	/* an unknown device: three equal doors */
	function viewChooser() {
		var grid = h( 'div', { class: 'tcc-hm__grid tcc-hm__grid--doors' }, [
			door( 'find', 'I’ve sung with the choir before', 'Find my name on the list', function () { signIn( 'find' ); } ),
			door( 'add', 'I’m new this year', 'Sign me up — it takes a minute', function () { signIn( 'new' ); } ),
			door( 'look', 'Just browsing for now', 'Have a look around; you can sign in any time', browse, 'is-quiet' )
		] );
		show( [ guestHead( 'Welcome backstage.', [ 'Tell us who you are once on this device and Backstage becomes your page: your rehearsals, your part’s tracks, your notes. It also replaces the paper sign-up sheet.' ] ), grid ] );
	}

	/* a known device whose person isn't on this season yet */
	function viewWelcomeBack( who ) {
		var first = firstOf( who );
		var grid = h( 'div', { class: 'tcc-hm__grid tcc-hm__grid--doors' }, [
			door( 'find', 'Yes, I’m in this year', 'Check my details', function () { signIn( undefined, { id: who.id, name: who.name, onSeason: false } ); }, 'is-primary' ),
			door( 'look', 'Just browsing for now', 'Have a look around first', browse, 'is-quiet' )
		] );
		show( [ guestHead( 'Welcome back, ' + first + '!', [ 'Taking part in ', h( 'i', {}, [ W.cantata.title ] ), '? Confirm your details and this becomes your page. It replaces the paper sign-up sheet.' ] ), grid,
			h( 'footer', { class: 'tcc-hm__foot' }, [ 'Not ' + first + '? ', h( 'button', { type: 'button', class: 'tcc-hm__link', onclick: openSwitch }, [ 'Switch person' ] ) ] ) ] );
	}

	/* the general page, for anyone browsing — often someone a choir member sent the link to */
	var PART_ABOUT = {
		soprano: 'The highest voices — usually women.',
		alto: 'The lower women’s voices.',
		tenor: 'The higher men’s voices.',
		bass: 'The lowest men’s voices.',
		accompaniment: 'The music we perform with — full accompaniment, no voices.',
		sections: 'The accompaniment in sections, starting at the circled CD numbers in the score.',
		listen: 'Everyone together, the way it will sound.'
	};
	function perfLine() {
		var c = W.cantata, d = ( c.perf || '' ).replace( /,\s*\d{4}$/, '' ), t = ( c.perfTime || '' ).replace( ':00', '' );
		return d ? d + ( t ? ' at ' + t : '' ) : '';
	}
	/* thinking about joining: the pitch from the Join page, and one button */
	function joinWidget() {
		var tick = function ( b, rest ) { return h( 'li', {}, [ h( 'span', { class: 'tcc-hm__tick', html: SVG.check } ), h( 'span', {}, [ h( 'b', {}, [ b ] ), rest ] ) ] ); };
		return widget( 'tcc-hm__join', [
			h( 'div', { class: 'tcc-hm__join-a' }, [
				wk( 'Thinking about singing with us?', 'is-red' ),
				h( 'p', { class: 'tcc-hm__join-big' }, [ 'There’s only one requirement: you must love to sing.' ] ),
				h( 'div', { class: 'tcc-hm__join-go' }, [
					h( 'button', { type: 'button', class: 'tcc-hm__btn', onclick: function () { store( 'ss', 'tcc_browse', null ); signIn( 'new' ); } }, [ 'I’d like to join', h( 'span', { html: SVG.right } ) ] ),
					W.urls.join ? h( 'a', { class: 'tcc-hm__link', href: W.urls.join }, [ 'Read more about joining' ] ) : null
				] )
			] ),
			h( 'ul', { class: 'tcc-hm__ticks' }, [
				tick( 'No audition, no experience needed', ' — and you don’t need to read music. If you can carry a tune and you’re willing to show up, we’ll teach you the rest.' ),
				tick( 'No cost.', ' The music is provided.' ),
				tick( 'Can’t make every rehearsal?', ' That’s normal — most people miss one or two. The practice tracks help you catch up at home.' ),
				tick( 'Not a singer?', ' We’d love to have you anyway: narrators, actors, a tech crew, and helpers for staging, costumes and welcoming the audience.' )
			] )
		] );
	}
	/* the practice tracks, explained before anyone is asked to pick one */
	function tracksWidget() {
		var list = h( 'ul', { class: 'tcc-hm__songs tcc-hm__parts' } );
		[ 'soprano', 'alto', 'tenor', 'bass', 'accompaniment', 'listen' ].forEach( function ( p ) {
			if ( ! W.parts[ p ] || ! W.parts[ p ].count ) { return; }
			list.appendChild( h( 'li', {}, [ h( 'a', { class: 'tcc-hm__song', href: practiceUrl( p ) }, [
				h( 'span', { class: 'tcc-hm__song-t' }, [ h( 'b', {}, [ W.parts[ p ].label ] ), PART_ABOUT[ p ] ? h( 'span', {}, [ PART_ABOUT[ p ] ] ) : null ] ),
				h( 'span', { class: 'tcc-hm__play', html: SVG.play, 'aria-hidden': 'true' } )
			] ) ] ) );
		} );
		if ( ! list.children.length ) { return null; }
		return widget( 'tcc-hm__songsw tcc-hm__tracks', [
			h( 'div', { class: 'tcc-hm__songs-top' }, [
				h( 'div', { class: 'tcc-hm__row' }, [ wk( 'Practice tracks', 'is-green' ), h( 'span', { class: 'tcc-hm__fine' }, [ 'Pick a part to listen' ] ) ] ),
				h( 'p', { class: 'tcc-hm__body' }, [ 'Each song in the cantata, recorded with one voice part turned up louder than the rest — so you can learn your notes by ear, in the car or at the kitchen sink. The director’s notes appear as the music plays.' ] ),
				h( 'p', { class: 'tcc-hm__fine' }, [ 'Not sure which part is yours? Most women start on Alto or Soprano and most men on Bass or Tenor. If the high notes feel like a strain, try the lower part — the director will help you find your fit.' ] )
			] ),
			list
		] );
	}
	function viewBrowse() {
		var who = me && me.who;
		var leave = function () { store( 'ss', 'tcc_browse', null ); route(); window.scrollTo( 0, 0 ); };
		var head;
		if ( who ) {
			head = h( 'header', { class: 'tcc-hm__head' }, [
				h( 'div', { class: 'tcc-hm__head-t' }, [ h( 'span', { class: 'tcc-hm__eyebrow' }, [ W.cantata.year + ' · ' + W.cantata.title ] ), h( 'h2', { class: 'tcc-hm__hello' }, [ 'Have a look around' ] ),
					h( 'p', { class: 'tcc-hm__lede' }, [ 'Browsing as ', h( 'b', {}, [ who.name ] ), '. ', h( 'button', { type: 'button', class: 'tcc-hm__link', onclick: leave }, [ who.onSeason ? 'Show my page' : 'Sign in for this year' ] ) ] ) ] )
			] );
		} else {
			/* they have the password, so someone in the choir invited them: say so, and make joining easy */
			var when = perfLine();
			head = guestHead( 'Come on in — we’re glad you’re here.', [ 'Someone in the choir shared the password with you, so consider yourself invited. This is Backstage, where the Tilden Community Choir gets ready for ', h( 'i', {}, [ W.cantata.title ] ), when ? ' on ' + when + '.' : '.' ] );
		}
		var grid = h( 'div', { class: 'tcc-hm__grid' } );
		if ( ! who ) { grid.appendChild( joinWidget() ); }
		grid.appendChild( rehearsalWidget( W.rehearsals[ 0 ] ) );
		var cd = countdownWidget(), sw = scheduleWidget(), tw = tracksWidget();
		if ( cd ) { grid.appendChild( cd ); }
		if ( sw ) { grid.appendChild( sw ); }
		if ( tw ) { grid.appendChild( tw ); }
		var strip = homeStrip(); if ( strip ) { grid.appendChild( strip ); }
		show( [ head, grid, who ? null : h( 'footer', { class: 'tcc-hm__foot' }, [
			'Already in the choir? ', h( 'button', { type: 'button', class: 'tcc-hm__link', onclick: function () { store( 'ss', 'tcc_browse', null ); signIn( 'find' ); } }, [ 'Find your name' ] ),
			W.urls.contact ? h( 'br' ) : null, W.urls.contact ? 'Questions? ' : null, W.urls.contact ? h( 'a', { class: 'tcc-hm__link', href: W.urls.contact }, [ 'Ask the director' ] ) : null
		] ) ] );
	}

	/* ---------- pieces ---------- */


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


	/* "Put Backstage on your home screen" */
	var installEvt = null;
	window.addEventListener( 'beforeinstallprompt', function ( e ) { e.preventDefault(); installEvt = e; } );
	/* which phone and browser this is, and the exact taps to put Backstage on the home screen */
	function a2hsSteps() {
		var ua = navigator.userAgent || '';
		var ios = /iphone|ipad|ipod/i.test( ua ) || ( navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1 );
		var android = /android/i.test( ua );
		var share = '<span class="tcc-db__ios">' + ICON.share + '</span>';
		var inApp = /FBAN|FBAV|FB_IAB|Instagram|Messenger|Line\//i.test( ua );
		if ( inApp ) {
			return { app: true, html: 'You’re in ' + ( /Instagram/i.test( ua ) ? 'Instagram' : 'Facebook' ) + '’s built-in browser, which can’t do this. Tap its <b>⋯</b> menu, choose <b>Open in ' + ( ios ? 'Safari' : 'browser' ) + '</b>, and come back to this page there.' };
		}
		if ( ios ) {
			var where = /iPad/i.test( ua ) || ( navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1 ) ? 'at the top' : '';
			if ( /CriOS/i.test( ua ) ) { return { html: 'In Chrome, tap ' + share + ' <b>Share</b> in the address bar, then <b>Add to Home Screen</b>. It opens like an app — you’ll enter the password and pick your name once more there.' }; }
			if ( /FxiOS/i.test( ua ) ) { return { html: 'In Firefox, tap the <b>≡</b> menu, then ' + share + ' <b>Share</b>, then <b>Add to Home Screen</b>. It opens like an app — you’ll enter the password and pick your name once more there.' }; }
			if ( /EdgiOS/i.test( ua ) ) { return { html: 'In Edge, tap ' + share + ' <b>Share</b>, then <b>Add to Home Screen</b>. It opens like an app — you’ll enter the password and pick your name once more there.' }; }
			return { html: 'In Safari, tap ' + share + ' <b>Share</b>' + ( where ? ' ' + where : '' ) + ', then <b>Add to Home Screen</b>. It opens like an app — you’ll enter the password and pick your name once more there.' };
		}
		if ( android ) {
			if ( /SamsungBrowser/i.test( ua ) ) { return { html: 'Tap the <b>≡</b> menu at the bottom, then <b>Add page to</b>, then <b>Home screen</b>. It opens like an app.' }; }
			if ( /Firefox/i.test( ua ) ) { return { html: 'Tap the <b>⋮</b> menu, then <b>Install</b> (or <b>Add to Home screen</b>). It opens like an app.' }; }
			if ( /EdgA/i.test( ua ) ) { return { html: 'Tap the <b>⋯</b> menu at the bottom, then <b>Add to phone</b>. It opens like an app.' }; }
			return { html: 'Tap the <b>⋮</b> menu at the top right, then <b>Add to Home screen</b> and <b>Install</b> (some phones say <b>Install app</b>). It opens like an app.' };
		}
		return { html: 'Use your browser’s menu and look for <b>Add to Home Screen</b> or <b>Install</b>. It opens like an app.' };
	}
	function isStandalone() { return ( window.matchMedia && window.matchMedia( '(display-mode: standalone)' ).matches ) || window.navigator.standalone; }
	function isTouch() { return window.matchMedia && window.matchMedia( '(pointer: coarse)' ).matches; }
	function homeScreenCard( force ) {
		if ( isStandalone() || ! isTouch() || ( store( 'ls', 'tcc_a2hs' ) && ! force ) ) { return null; }
		var steps = a2hsSteps();
		var card = h( 'section', { class: 'tcc-db__a2hs' }, [
			h( 'img', { src: W.icon, alt: '', width: '52', height: '52' } ),
			h( 'div', {}, [
				h( 'b', {}, [ steps.app ? 'Open this in your browser first' : 'Put Backstage on your home screen' ] ),
				h( 'p', { html: steps.html } ),
				steps.app ? null : h( 'p', { class: 'tcc-db__a2hs-why' }, [ 'You get a Tilden Community Choir icon, and the music gets the whole screen — no address bar.' ] ),
				h( 'div', { class: 'tcc-db__a2hs-go' }, [
					installEvt && ! steps.app ? h( 'button', { type: 'button', class: 'tcc-db__btn', onclick: function () { installEvt.prompt(); installEvt.userChoice.then( function () { store( 'ls', 'tcc_a2hs', '1' ); card.remove(); } ); } }, [ 'Add it now' ] ) : null,
					h( 'button', { type: 'button', class: 'tcc-dash__link', onclick: function () { store( 'ls', 'tcc_a2hs', '1' ); card.remove(); paintA2hsLink(); } }, [ 'Got it' ] )
				] )
			] )
		] );
		return card;
	}
	/* once the card is dismissed, a quiet way to bring it back */
	function a2hsLink() {
		if ( isStandalone() || ! isTouch() || ! store( 'ls', 'tcc_a2hs' ) ) { return null; }
		return h( 'button', { type: 'button', class: 'tcc-dash__link tcc-db__a2hs-again', onclick: function () {
			var c = homeScreenCard( true ); if ( ! c ) { return; }
			var head = root.querySelector( '.tcc-db__hello' ); if ( head ) { head.after( c ); } else { root.prepend( c ); }
			c.scrollIntoView( { behavior: 'smooth', block: 'center' } );
		} }, [ 'Add Backstage to your home screen' ] );
	}
	function paintA2hsLink() {
		var foot = root.querySelector( '.tcc-db__foot' );
		if ( foot && ! foot.querySelector( '.tcc-db__a2hs-again' ) ) { var l = a2hsLink(); if ( l ) { foot.appendChild( document.createTextNode( ' · ' ) ); foot.appendChild( l ); } }
	}

	/* ---------- the home screen: widgets ---------- */
	var SVG = {
		cal: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
		calDots: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg>',
		music: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>',
		play: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5l12 7-12 7z"/></svg>',
		down: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>',
		right: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>',
		phone: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/></svg>',
		x: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>',
		check: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>',
		team: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>'
	};
	function fmtT( s ) { s = Math.max( 0, Math.round( s ) ); return Math.floor( s / 60 ) + ':' + ( '0' + ( s % 60 ) ).slice( -2 ); }
	function firstOf( who ) { return who.first || who.name.split( ' ' )[ 0 ]; }
	function initial( name ) { return ( name || '?' ).trim().charAt( 0 ).toUpperCase(); }
	var AVATAR = [ '#355635', '#9c2b1a', '#8a5a00', '#3c5a7a', '#6b4a7a', '#1f1f1f' ];
	function avatarColor( id ) { return AVATAR[ ( +id || 0 ) % AVATAR.length ]; }
	function avatar( p, size ) { return h( 'span', { class: 'tcc-hm__av' + ( size ? ' tcc-hm__av--' + size : '' ), style: 'background:' + avatarColor( p.id ), 'aria-hidden': 'true' }, [ initial( p.name ) ] ); }
	function plainText( html ) { var d = document.createElement( 'div' ); d.innerHTML = html || ''; return ( d.textContent || '' ).replace( /\s+/g, ' ' ).trim(); }
	function whoLabel( k ) { return k.indexOf( 'role:' ) === 0 ? ( ( W.roles[ k.slice( 5 ) ] || {} ).label || k.slice( 5 ) ) + ' team' : ( W.types[ k ] || k ); }

	/* the people who use this device, newest first — so a shared phone or iPad switches in one tap */
	function people() { try { return JSON.parse( store( 'ls', 'tcc_people' ) || '[]' ) || []; } catch ( e ) { return []; } }
	function savePeople( list ) { store( 'ls', 'tcc_people', JSON.stringify( list.slice( 0, 12 ) ) ); }
	function remember( who ) {
		if ( ! who || ! who.id ) { return; }
		var list = people().filter( function ( p ) { return +p.id !== +who.id; } );
		list.unshift( { id: who.id, name: who.name, part: who.part && W.voice[ who.part ] ? W.voice[ who.part ].label : '', at: Date.now() } );
		savePeople( list );
	}
	function ago( t ) {
		if ( ! t ) { return ''; }
		var d = Math.floor( ( Date.now() - t ) / 864e5 );
		return d <= 0 ? 'today' : d === 1 ? 'yesterday' : d < 7 ? d + ' days ago' : d < 14 ? 'last week' : 'a while ago';
	}
	function switchTo( id ) {
		return fetch( W.rest + 'who', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-TCC-Token': W.token }, body: JSON.stringify( { cantata: W.cantata.id, person: id } ) } ).then( function ( r ) { return r.json(); } );
	}
	/* "Who's practicing?" */
	var sheetEl = null;
	function closeSheet() { if ( ! sheetEl ) { return; } var s = sheetEl; sheetEl = null; s.classList.remove( 'is-open' ); setTimeout( function () { s.remove(); }, 200 ); document.removeEventListener( 'keydown', escClose ); }
	function escClose( e ) { if ( e.key === 'Escape' ) { closeSheet(); } }
	function openSheet( title, body, label ) {
		closeSheet();
		var box = h( 'section', { class: 'tcc-hm__sheet-box', role: 'dialog', 'aria-modal': 'true', 'aria-label': label || title }, [
			h( 'span', { class: 'tcc-hm__sheet-grab', 'aria-hidden': 'true' } ),
			h( 'div', { class: 'tcc-hm__sheet-head' }, [ h( 'h2', {}, [ title ] ), h( 'button', { type: 'button', class: 'tcc-hm__x', 'aria-label': 'Close', html: SVG.x, onclick: closeSheet } ) ] ),
			body
		] );
		sheetEl = h( 'div', { class: 'tcc-hm__sheet', onclick: function ( e ) { if ( e.target === sheetEl ) { closeSheet(); } } }, [ box ] );
		document.body.appendChild( sheetEl );
		requestAnimationFrame( function () { if ( sheetEl ) { sheetEl.classList.add( 'is-open' ); } } );
		document.addEventListener( 'keydown', escClose );
		var f = box.querySelector( 'button' ); if ( f ) { setTimeout( function () { f.focus(); }, 60 ); }
	}
	function openSwitch() {
		var cur = me && me.who ? me.who.id : 0, editing = false;
		var listBox = h( 'div', { class: 'tcc-hm__people' } );
		function paint() {
			listBox.innerHTML = '';
			var list = people();
			if ( cur && ! list.some( function ( p ) { return +p.id === +cur; } ) ) { list.unshift( { id: cur, name: me.who.name, part: W.voice[ me.who.part ] ? W.voice[ me.who.part ].label : '', at: Date.now() } ); }
			list.forEach( function ( p ) {
				var on = +p.id === +cur;
				var row = h( 'div', { class: 'tcc-hm__person-row' }, [
					h( 'button', { type: 'button', class: 'tcc-hm__person' + ( on ? ' is-on' : '' ), 'aria-current': on ? 'true' : null, onclick: function () {
						if ( on ) { closeSheet(); return; }
						row.classList.add( 'is-busy' );
						switchTo( p.id ).then( function () { closeSheet(); store( 'ss', 'tcc_browse', null ); load(); } ).catch( function () { row.classList.remove( 'is-busy' ); } );
					} }, [
						avatar( p ),
						h( 'span', { class: 'tcc-hm__person-t' }, [ h( 'b', {}, [ p.name ] ), h( 'span', {}, [ [ p.part, on ? 'now' : ago( p.at ) ].filter( Boolean ).join( ' · ' ) ] ) ] ),
						on ? h( 'span', { class: 'tcc-hm__person-on', html: SVG.check } ) : null
					] ),
					editing && ! on ? h( 'button', { type: 'button', class: 'tcc-hm__person-x', 'aria-label': 'Remove ' + p.name + ' from this device', html: SVG.x, onclick: function () { savePeople( people().filter( function ( q ) { return +q.id !== +p.id; } ) ); paint(); } } ) : null
				] );
				listBox.appendChild( row );
			} );
			if ( ! list.length ) { listBox.appendChild( h( 'p', { class: 'tcc-hm__fine' }, [ 'Nobody has signed in on this device yet.' ] ) ); }
		}
		paint();
		var editBtn = h( 'button', { type: 'button', class: 'tcc-hm__link', onclick: function () { editing = ! editing; editBtn.textContent = editing ? 'Done' : 'Remove a name from this device'; paint(); } }, [ 'Remove a name from this device' ] );
		openSheet( 'Who’s practicing?', h( 'div', { class: 'tcc-hm__switch' }, [
			h( 'p', { class: 'tcc-hm__fine' }, [ 'Everyone who has used this device. One tap switches — notes, marks and where you left off follow each person.' ] ),
			listBox,
			h( 'div', { class: 'tcc-hm__more2' }, [
				h( 'button', { type: 'button', class: 'tcc-hm__opt', onclick: function () { closeSheet(); signIn( 'find' ); } }, [ h( 'b', {}, [ 'Someone else' ] ), h( 'span', {}, [ 'Find your name' ] ) ] ),
				h( 'button', { type: 'button', class: 'tcc-hm__opt', onclick: function () { closeSheet(); signIn( 'new' ); } }, [ h( 'b', {}, [ 'New this year' ] ), h( 'span', {}, [ 'Sign up' ] ) ] )
			] ),
			people().length > 1 ? editBtn : null
		] ) );
	}

	function widget( cls, kids, tag ) { return h( tag || 'section', { class: 'tcc-hm__w ' + ( cls || '' ) }, kids ); }
	function wk( text, cls ) { return h( 'span', { class: 'tcc-hm__k' + ( cls ? ' ' + cls : '' ) }, [ text ] ); }

	/* one rehearsal as a calendar page for the day — the times, who comes, what's on, the director's word */
	function rehBody( r ) {
		/* each part of the evening on its own line, with who it's for right on that line:
		   7–8 PM  Rehearsal  [Singers] [Required]
		   8–8:45 PM  Staging/Costuming meeting  [Staging] [Costuming] */
		function chips( who, extra ) {
			var box = h( 'span', { class: 'tcc-hm__chips' } );
			( who && who.length ? who : [ 'everyone' ] ).forEach( function ( k ) {
				var team = k.indexOf( 'role:' ) === 0;
				box.appendChild( h( 'span', { class: 'tcc-hm__tag' + ( team ? ' is-team' : '' ) }, [ k === 'everyone' ? 'Everyone' : team ? ( ( W.roles[ k.slice( 5 ) ] || {} ).label || k.slice( 5 ) ) : whoLabel( k ) ] ) );
			} );
			( extra || [] ).forEach( function ( x ) { if ( x ) { box.appendChild( x ); } } );
			return box;
		}
		var must = requiredOfMe( r ) ? h( 'span', { class: 'tcc-hm__tag is-must' }, [ 'Required' ] )
			: ( ! P() && r.must && r.must.length ? h( 'span', { class: 'tcc-hm__tag is-must' }, [ 'Required for ' + r.must.map( whoLabel ).join( ', ' ).toLowerCase() ] ) : null );
		var times = h( 'div', { class: 'tcc-hm__times' }, [
			h( 'div', { class: 'tcc-hm__time' }, [ h( 'b', {}, [ r.time || 'All day' ] ), h( 'span', { class: 'tcc-hm__time-t' }, [ h( 'span', {}, [ r.type && r.type !== 'rehearsal' ? ( W.rtypes && W.rtypes[ r.type ] ) || r.type.charAt( 0 ).toUpperCase() + r.type.slice( 1 ) : 'Rehearsal' ] ), chips( r.who, [ must ] ) ] ) ] )
		].concat( ( r.after || [] ).map( function ( a ) {
			return h( 'div', { class: 'tcc-hm__time' + ( a.who && a.who.length && ! forMe( a.who ) ? ' is-other' : '' ) }, [ h( 'b', {}, [ a.time || 'After' ] ), h( 'span', { class: 'tcc-hm__time-t' }, [ h( 'span', {}, [ a.what ] ), chips( a.who ) ] ) ] );
		} ) ) );
		var what = null;
		if ( r.full ) {
			what = h( 'div', { class: 'tcc-hm__what' }, [ h( 'span', { html: SVG.music } ), h( 'span', {}, [ h( 'b', {}, [ 'Full run-through' ] ), ' · the whole work' ] ) ] );
		} else if ( r.songs && r.songs.length ) {
			what = h( 'ul', { class: 'tcc-hm__what tcc-hm__what--songs' }, r.songs.map( function ( i ) {
				var f = r.focus && r.focus.indexOf( i ) !== -1;
				return h( 'li', { class: f ? 'is-focus' : '' }, [ h( 'b', {}, [ String( i + 1 ) ] ), ' ' + ( W.songs[ i ] || '' ), f ? h( 'span', { class: 'tcc-hm__star' }, [ '★ focus' ] ) : null ] );
			} ) );
		}
		var note = plainText( r.note );
		return [
			h( 'div', { class: 'tcc-hm__cal' }, [
				h( 'div', { class: 'tcc-hm__page', 'aria-hidden': 'true' }, [ h( 'span', { class: 'tcc-hm__page-m' }, [ r.mon.toUpperCase() ] ), h( 'span', { class: 'tcc-hm__page-d' }, [ r.day ] ), h( 'span', { class: 'tcc-hm__page-w' }, [ r.dow.toUpperCase() ] ) ] ),
				h( 'div', { class: 'tcc-hm__cal-t' }, [ h( 'h3', {}, [ r.title ] ), h( 'span', { class: 'tcc-hm__sr' }, [ r.long ] ), times ] )
			] ),
			what,
			note ? h( 'p', { class: 'tcc-hm__note' }, [ h( 'b', {}, [ 'From the director: ' ] ), note ] ) : null
		];
	}
	function rehearsalWidget( r, skippedFirst ) {
		if ( ! r ) { return widget( 'tcc-hm__reh', [ wk( 'Next rehearsal', 'is-red' ), h( 'p', { class: 'tcc-hm__fine' }, [ 'Nothing on the calendar for you right now.' ] ) ] ); }
		var rel = relDay( r.date );
		return widget( 'tcc-hm__reh', [
			h( 'div', { class: 'tcc-hm__row' }, [ wk( 'Next rehearsal', 'is-red' ), rel ? h( 'span', { class: 'tcc-hm__chip' }, [ rel ] ) : null ] )
		].concat( rehBody( r ), [ skippedFirst ? h( 'p', { class: 'tcc-hm__fine' }, [ skippedFirst ] ) : null ] ) );
	}

	/* your own practice, day by day over the last two weeks */
	function practiceWidget() {
		var days = me && me.days; if ( ! days || ! days.length ) { return null; }
		var mins = function ( m ) { return m < 60 ? m + ' min' : Math.floor( m / 60 ) + 'h ' + ( m % 60 ) + 'm'; };
		var week = days.slice( -7 ).reduce( function ( a, x ) { return a + x.min; }, 0 );
		var on = days.filter( function ( x ) { return x.min > 0; } ).length;
		var top = Math.max( 10, Math.max.apply( null, days.map( function ( x ) { return x.min; } ) ) );
		var bars = h( 'span', { class: 'tcc-hm__dbars tcc-hm__pbars', 'aria-hidden': 'true' }, days.map( function ( x, i ) {
			var d = new Date( x.d + 'T12:00:00' ), last = i === days.length - 1;
			return h( 'span', { class: 'tcc-hm__dbar' + ( x.min ? '' : ' is-zero' ) + ( last ? ' is-today' : '' ), title: d.toLocaleDateString( undefined, { weekday: 'short', month: 'short', day: 'numeric' } ) + ' · ' + mins( x.min ) }, [
				h( 'i', { style: 'height:' + ( x.min ? Math.max( 8, Math.round( x.min / top * 100 ) ) : 0 ) + '%' } ),
				h( 'small', {}, [ d.toLocaleDateString( undefined, { weekday: 'narrow' } ) ] )
			] );
		} ) );
		var stat = function ( big, small ) { return h( 'span', { class: 'tcc-hm__dstat' }, [ h( 'b', {}, [ big ] ), h( 'span', {}, [ small ] ) ] ); };
		return widget( 'tcc-hm__dir tcc-hm__pract', [
			h( 'div', { class: 'tcc-hm__row' }, [ wk( 'Your practice', 'is-green' ), h( 'span', { class: 'tcc-hm__fine' }, [ 'Last 2 weeks' ] ) ] ),
			h( 'div', { class: 'tcc-hm__dstats tcc-hm__dstats--2' }, [ stat( mins( week ), 'last 7 days' ), stat( String( on ), on === 1 ? 'day practiced' : 'days practiced' ) ] ),
			bars
		] );
	}

	/* the director's glance at the week: a tap opens the full report */
	function directorWidget() {
		var d = W.director; if ( ! d || ! d.url ) { return null; }
		var mins = function ( m ) { return m < 60 ? m + ' min' : Math.floor( m / 60 ) + 'h ' + ( m % 60 ) + 'm'; };
		var stat = function ( big, small, cls ) { return h( 'span', { class: 'tcc-hm__dstat' + ( cls ? ' ' + cls : '' ) }, [ h( 'b', {}, [ big ] ), h( 'span', {}, [ small ] ) ] ); };
		var top = Math.max( 1, d.singers || 0, Math.max.apply( null, d.songs.concat( [ 1 ] ) ) );
		var bars = h( 'span', { class: 'tcc-hm__dbars', 'aria-hidden': 'true' }, d.songs.map( function ( n, i ) {
			return h( 'span', { class: 'tcc-hm__dbar' + ( n ? '' : ' is-zero' ), title: ( i + 1 ) + '. ' + ( W.songs[ i ] || '' ) + ' · ' + n + ' practiced' }, [ h( 'i', { style: 'height:' + ( n ? Math.max( 8, Math.round( n / top * 100 ) ) : 0 ) + '%' } ), h( 'small', {}, [ String( i + 1 ) ] ) ] );
		} ) );
		return h( 'a', { class: 'tcc-hm__w tcc-hm__dir', href: d.url }, [
			h( 'div', { class: 'tcc-hm__row' }, [ wk( 'Director', 'is-red' ), h( 'span', { class: 'tcc-hm__dir-go' }, [ d.week, h( 'span', { html: SVG.right } ) ] ) ] ),
			h( 'div', { class: 'tcc-hm__dstats' }, [
				stat( d.active + ( d.singers ? '/' + d.singers : '' ), 'singers practiced' ),
				stat( mins( d.minutes ), 'listening' ),
				stat( String( d.tricky ), 'tricky', d.tricky ? 'is-alert' : '' ),
				stat( String( d.silent ), 'not practiced', d.silent ? 'is-warn' : '' )
			] ),
			bars
		] );
	}

	/* continue practicing: where this person left off, at the spot they stopped */
	function continueWidget( next ) {
		var parts = myTrackParts(), primary = parts[ 0 ];
		var whoId = me && me.who ? me.who.id : 0, last = null;
		try { last = JSON.parse( store( 'ls', 'tcc_last_' + whoId ) || 'null' ); } catch ( e ) {}
		var song, part, at = 0, dur = 0, small;
		if ( last && last.song >= 0 && W.songs[ last.song ] && W.parts[ last.part ] && parts.indexOf( last.part ) !== -1 ) {
			song = last.song; part = last.part;
			at = parseFloat( store( 'ls', 'tcc_pos_' + whoId + '_' + last.id ) || '0' ) || 0;
			dur = ( W.parts[ part ].durs || {} )[ song ] || 0;
			small = W.voice[ P().part ].label + ( at > 5 ? ' · picks up at ' + fmtT( at ) : ' · from the top' );
		} else {
			var have = {}; parts.forEach( function ( p ) { W.parts[ p ].songs.forEach( function ( i ) { have[ i ] = true; } ); } );
			var ns = next && ! next.full ? next.songs : [];
			song = ns.filter( function ( i ) { return have[ i ]; } )[ 0 ];
			if ( song === undefined ) { song = W.songs.map( function ( t, i ) { return i; } ).filter( function ( i ) { return have[ i ]; } )[ 0 ]; }
			part = primary;
			small = W.voice[ P().part ].label + ' · start here';
		}
		if ( song === undefined ) { return null; }
		var pct = dur ? Math.min( 100, Math.round( at / dur * 100 ) ) : 0;
		return widget( 'tcc-hm__cont', [
			wk( last && last.song === song ? 'Continue practicing' : 'Start practicing' ),
			h( 'div', { class: 'tcc-hm__cont-row' }, [
				h( 'span', { class: 'tcc-hm__cont-n', 'aria-hidden': 'true' }, [ String( song + 1 ) ] ),
				h( 'div', { class: 'tcc-hm__cont-t' }, [ h( 'b', {}, [ W.songs[ song ] ] ), h( 'span', {}, [ small ] ) ] ),
				h( 'a', { class: 'tcc-hm__playbig', href: practiceUrl( part, song ), 'aria-label': 'Play ' + W.songs[ song ], html: SVG.play } )
			] ),
			dur ? h( 'div', { class: 'tcc-hm__bar', role: 'img', 'aria-label': pct + '% through' }, [ h( 'i', { style: 'width:' + pct + '%' } ) ] ) : null
		] );
	}

	function countdownWidget() {
		var c = W.cantata, days = +c.days;
		if ( ! c.perfDay || ! ( days >= 0 ) ) { return null; }
		var when = c.perfDay[ 0 ] + ', ' + c.perfDay[ 2 ] + ' ' + c.perfDay[ 1 ] + ( c.perfTime ? ' · ' + c.perfTime.replace( ':00', '' ) : '' );
		return widget( 'tcc-hm__count', [
			wk( 'Performance' ),
			h( 'div', { class: 'tcc-hm__count-b' }, days === 0
				? [ h( 'span', { class: 'tcc-hm__count-n tcc-hm__count-n--today' }, [ 'Today!' ] ), h( 'span', { class: 'tcc-hm__count-w' }, [ when ] ) ]
				: [ h( 'span', { class: 'tcc-hm__count-n' }, [ String( days ) ] ), h( 'span', { class: 'tcc-hm__count-d' }, [ days === 1 ? 'day to go' : 'days to go' ] ), h( 'span', { class: 'tcc-hm__count-w' }, [ when ] ) ] )
		] );
	}
	function scheduleWidget() {
		if ( ! W.urls.schedule ) { return null; }
		return h( 'a', { class: 'tcc-hm__w tcc-hm__sched', href: W.urls.schedule }, [
			h( 'span', { class: 'tcc-hm__sched-i', html: SVG.calDots } ),
			h( 'span', { class: 'tcc-hm__sched-t' }, [ h( 'b', {}, [ 'Full schedule' ] ), h( 'span', {}, [ 'Every rehearsal and what’s on' ] ) ] )
		] );
	}

	/* your songs: how each one is going at a glance, then the list — tap one to practice it */
	var MARK_LABEL = { none: 'Not marked yet', some: 'Getting there', good: 'Feeling good', help: 'Tricky spots', na: 'No track for your part' };
	function songsWidget( next ) {
		var parts = myTrackParts(), primary = parts[ 0 ];
		var have = {}; parts.forEach( function ( p ) { W.parts[ p ].songs.forEach( function ( i ) { have[ i ] = true; } ); } );
		var nextSongs = next && ! next.full ? next.songs : [], focus = next ? next.focus : [];
		var count = { good: 0, some: 0, help: 0, none: 0 };
		var dots = h( 'div', { class: 'tcc-hm__dots', style: 'grid-template-columns:repeat(' + W.songs.length + ',minmax(0,1fr))' } );
		var list = h( 'ol', { class: 'tcc-hm__songs' } );
		W.songs.forEach( function ( title, i ) {
			var ok = !! have[ i ], st = ok ? markOf( i ) : 'na';
			if ( ok ) { count[ st ]++; }
			dots.appendChild( h( 'span', { class: 'tcc-hm__dot' }, [ h( 'span', { class: 'tcc-hm__ring tcc-hm__ring--' + st, role: 'img', 'aria-label': ( i + 1 ) + ': ' + MARK_LABEL[ st ], html: st === 'na' ? '' : ring( st ) } ), h( 'small', {}, [ String( i + 1 ) ] ) ] ) );
			var pl = ok ? playsOf( i ) : null;
			var bits = [];
			if ( ! ok ) { bits.push( 'No track for your part' ); }
			else { bits.push( pl.n ? 'Played ' + pl.n + '×' + ( pl.last ? ' · ' + whenShort( pl.last ) : '' ) : 'Not played yet' ); }
			var inner = [
				h( 'span', { class: 'tcc-hm__song-n' }, [ String( i + 1 ) ] ),
				h( 'span', { class: 'tcc-hm__song-t' }, [ h( 'b', {}, [ title ] ), h( 'span', {}, [ bits.join( '' ),
					ok && nextSongs.indexOf( i ) !== -1 && next.dow ? h( 'em', { class: 'tcc-hm__song-tag' }, [ next.dow ] ) : null,
					ok && focus.indexOf( i ) !== -1 ? h( 'em', { class: 'tcc-hm__star' }, [ '★ focus' ] ) : null ] ) ] ),
				ok ? h( 'span', { class: 'tcc-hm__ring tcc-hm__ring--' + st, title: MARK_LABEL[ st ], html: ring( st ) } ) : null,
				h( 'span', { class: 'tcc-hm__play' + ( ok ? '' : ' is-off' ), html: SVG.play } )
			];
			list.appendChild( h( 'li', {}, [ ok ? h( 'a', { class: 'tcc-hm__song', href: practiceUrl( primary, i ), 'aria-label': 'Practice ' + title } , inner ) : h( 'span', { class: 'tcc-hm__song is-off' }, inner ) ] ) );
		} );
		var summary = h( 'div', { class: 'tcc-hm__sum' }, [
			h( 'span', {}, [ h( 'b', { class: 'is-good' }, [ String( count.good ) ] ), ' feeling good' ] ),
			h( 'span', {}, [ h( 'b', { class: 'is-some' }, [ String( count.some ) ] ), ' getting there' ] ),
			h( 'span', {}, [ h( 'b', { class: 'is-help' }, [ String( count.help ) ] ), ' tricky spots' ] ),
			h( 'span', {}, [ h( 'b', {}, [ String( count.none ) ] ), ' not marked' ] )
		] );
		return widget( 'tcc-hm__songsw', [
			h( 'div', { class: 'tcc-hm__songs-top' }, [
				wk( 'Your songs · ' + W.voice[ P().part ].label, 'is-green' ),
				dots, summary
			] ),
			list
		] );
	}

	/* add to home screen: one quiet strip; its steps open in a sheet */
	function homeStrip() {
		if ( isStandalone() || ! isTouch() || store( 'ls', 'tcc_a2hs' ) ) { return null; }
		var steps = a2hsSteps();
		var strip = h( 'div', { class: 'tcc-hm__w tcc-hm__a2hs' }, [
			h( 'button', { type: 'button', class: 'tcc-hm__a2hs-go', onclick: function () {
				openSheet( steps.app ? 'Open this in your browser first' : 'Add Backstage to your home screen', h( 'div', { class: 'tcc-hm__a2hs-steps' }, [
					h( 'img', { src: W.icon, alt: '', width: '56', height: '56' } ),
					h( 'p', { html: steps.html } ),
					steps.app ? null : h( 'p', { class: 'tcc-hm__fine' }, [ 'You get a Tilden Community Choir icon, and the music gets the whole screen — no address bar.' ] ),
					installEvt && ! steps.app ? h( 'button', { type: 'button', class: 'tcc-hm__btn', onclick: function () { installEvt.prompt(); installEvt.userChoice.then( function () { store( 'ls', 'tcc_a2hs', '1' ); strip.remove(); closeSheet(); } ); } }, [ 'Add it now' ] ) : null
				] ) );
			} }, [ h( 'span', { class: 'tcc-hm__a2hs-i', html: SVG.phone } ), h( 'span', { class: 'tcc-hm__a2hs-t' }, [ 'Add Backstage to your home screen' ] ), h( 'span', { class: 'tcc-hm__a2hs-how' }, [ 'How' ] ) ] ),
			h( 'button', { type: 'button', class: 'tcc-hm__x tcc-hm__x--sm', 'aria-label': 'Hide this', html: SVG.x, onclick: function () { store( 'ls', 'tcc_a2hs', '1' ); strip.remove(); } } )
		] );
		return strip;
	}

	function head( who ) {
		var meta = [];
		if ( isSinger() && W.voice[ who.part ] ) { meta.push( W.voice[ who.part ].label ); }
		who.types.forEach( function ( t ) { if ( t !== 'singers' && W.types[ t ] ) { meta.push( W.types[ t ].replace( /s$/, '' ) ); } } );
		if ( who.role && W.roles[ who.role ] ) { meta.push( W.roles[ who.role ].label + ' team' ); }
		return h( 'header', { class: 'tcc-hm__head' }, [
			h( 'div', { class: 'tcc-hm__head-t' }, [
				h( 'span', { class: 'tcc-hm__eyebrow' }, [ W.cantata.year + ' · ' + W.cantata.title ] ),
				h( 'h2', { class: 'tcc-hm__hello' }, [ greeting() + ', ' + firstOf( who ) ] ),
				meta.length ? h( 'span', { class: 'tcc-hm__meta' }, [ meta.join( ' · ' ) ] ) : null
			] ),
			h( 'button', { type: 'button', class: 'tcc-hm__me', 'aria-label': 'Switch person — now ' + who.name, onclick: openSwitch }, [ avatar( who ), h( 'span', { class: 'tcc-hm__me-n' }, [ firstOf( who ) ] ), h( 'span', { class: 'tcc-hm__me-c', html: SVG.down } ) ] )
		] );
	}

	/* the person's own home */
	function viewDash() {
		var who = P();
		var mine = W.rehearsals.filter( function ( r ) { return forMe( r.who ); } );
		var next = mine[ 0 ], first = W.rehearsals[ 0 ];
		var skipped = next && first && first.id !== next.id ? first.dow + ' ' + first.mon + ' ' + first.day + ' (' + first.title + ') is for ' + ( first.who || [] ).map( whoLabel ).join( ', ' ) + ' — you’re off that night.' : '';
		var grid = h( 'div', { class: 'tcc-hm__grid' } );
		grid.appendChild( rehearsalWidget( next, skipped ) );
		if ( isSinger() && myTrackParts().length ) { var cw = continueWidget( next ); if ( cw ) { grid.appendChild( cw ); } }
		var tw = taskWidget(); if ( tw ) { grid.appendChild( tw ); }
		var cd = countdownWidget(), sw = scheduleWidget();
		if ( cd ) { grid.appendChild( cd ); }
		if ( sw ) { grid.appendChild( sw ); }
		var dw = directorWidget(); if ( dw ) { grid.appendChild( dw ); }
		if ( isSinger() ) { var pw = practiceWidget(); if ( pw ) { grid.appendChild( pw ); } }
		if ( isSinger() ) {
			if ( myTrackParts().length ) { grid.appendChild( songsWidget( next ) ); }
			else { grid.appendChild( widget( 'tcc-hm__wide', [ wk( 'Your songs', 'is-green' ), h( 'p', { class: 'tcc-hm__fine' }, [ W.voice[ who.part ] ? W.voice[ who.part ].label + ' tracks aren’t up yet — check back soon.' : 'We don’t have a voice part for you yet — the director will help you find it at the next rehearsal.' ] ) ] ) ); }
		} else {
			/* actors, narrators, crew: their materials take the songs' place */
			var groups = [ 'everyone' ].concat( who.types.filter( function ( t ) { return W.types[ t ]; } ) );
			var docs = docList( groups, false );
			grid.appendChild( widget( 'tcc-hm__wide', [ wk( 'Your materials', 'is-green' ), docs || h( 'p', { class: 'tcc-hm__fine' }, [ 'Nothing posted for you yet.' ] ), W.urls.practice ? h( 'a', { class: 'tcc-hm__link', href: practiceUrl( W.parts.listen && W.parts.listen.count ? 'listen' : '' ) }, [ 'Hear the music in the practice app' ] ) : null ] ) );
		}
		var strip = homeStrip(); if ( strip ) { grid.appendChild( strip ); }
		show( [ head( who ), grid, h( 'footer', { class: 'tcc-hm__foot' }, [
			h( 'span', {}, [ who.name + ( W.voice[ who.part ] && isSinger() ? ' · ' + W.voice[ who.part ].label : '' ) + ' · ' ] ),
			h( 'button', { type: 'button', class: 'tcc-hm__link', onclick: function () { signIn( 'details', { id: who.id, name: who.name, onSeason: true } ); } }, [ 'Update my details' ] ),
			! W.director && W.canEdit && W.admin ? h( 'br' ) : null,
			! W.director && W.canEdit && W.admin ? h( 'a', { class: 'tcc-hm__link', href: W.admin }, [ 'Director: how the cantata is coming' ] ) : null
		] ) ] );
	}

	/* ---------- the Team and Docs tabs ---------- */
	function post( path, body ) {
		return fetch( W.rest + path, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-TCC-Token': W.token }, body: JSON.stringify( Object.assign( { cantata: W.cantata.id }, body ) ) } ).then( function ( r ) { if ( ! r.ok ) { throw new Error( r.status ); } return r.json(); } );
	}
	function teamName( k ) { var l = ( W.roles[ k ] || {} ).label || 'Your team'; return /team$/i.test( l ) ? l : l + ' team'; }
	function myTeam() { var who = me && me.who && me.who.onSeason ? me.who : null; return who && who.role && W.roles[ who.role ] ? who.role : ''; }
	function myTasks() { var t = myTeam(); return t ? ( W.tasks || [] ).filter( function ( x ) { return x.team === t; } ) : []; }
	function doneByMe( x ) { var id = me.who.id; return x.done.some( function ( d ) { return +d.id === +id; } ); }
	/* "Due Fri", "Due today", "Overdue · Oct 10", "Due Oct 20" */
	function dueLabel( due, finished ) {
		if ( ! due ) { return null; }
		var d = new Date( due + 'T12:00:00' ), t = new Date( ( W.today || ymd( new Date() ) ) + 'T12:00:00' );
		var days = Math.round( ( d - t ) / 864e5 ), md = d.toLocaleDateString( undefined, { month: 'short', day: 'numeric' } );
		if ( days < 0 ) { return finished ? [ md, '' ] : [ 'Overdue · ' + md, 'is-late' ]; }
		if ( days === 0 ) { return [ 'Due today', finished ? '' : 'is-soon' ]; }
		if ( days === 1 ) { return [ 'Due tomorrow', finished ? '' : 'is-soon' ]; }
		if ( days < 7 ) { return [ 'Due ' + d.toLocaleDateString( undefined, { weekday: 'short' } ), finished ? '' : 'is-soon' ]; }
		return [ 'Due ' + md, '' ];
	}
	function taskRow( x, redraw ) {
		var mine = doneByMe( x ), others = x.done.filter( function ( d ) { return +d.id !== +me.who.id; } );
		var due = dueLabel( x.due, mine );
		var box = h( 'input', { type: 'checkbox', checked: mine ? true : null, 'aria-label': 'Done: ' + x.text } );
		box.addEventListener( 'change', function () {
			var on = box.checked;
			if ( on ) { x.done.push( { id: me.who.id, name: me.who.name } ); } else { x.done = x.done.filter( function ( d ) { return +d.id !== +me.who.id; } ); }
			redraw();
			post( 'taskdone', { task: x.id, done: on ? 1 : 0 } ).catch( function () {
				if ( on ) { x.done = x.done.filter( function ( d ) { return +d.id !== +me.who.id; } ); } else { x.done.push( { id: me.who.id, name: me.who.name } ); }
				redraw();
			} );
		} );
		return h( 'li', { class: 'tcc-hm__task' + ( mine ? ' is-done' : '' ) }, [
			h( 'label', { class: 'tcc-hm__task-l' }, [ box, h( 'span', { class: 'tcc-hm__task-box', html: SVG.check, 'aria-hidden': 'true' } ),
				h( 'span', { class: 'tcc-hm__task-t' }, [
					h( 'b', {}, [ x.text ] ),
					x.details ? h( 'span', {}, [ x.details ] ) : null,
					others.length ? h( 'span', { class: 'tcc-hm__task-who' }, [ '✓ ' + others.map( function ( d ) { return d.name.split( ' ' )[ 0 ]; } ).join( ', ' ) ] ) : null
				] ),
				due ? h( 'span', { class: 'tcc-hm__chip tcc-hm__due ' + due[ 1 ] }, [ due[ 0 ] ] ) : null
			] )
		] );
	}
	function tasksList( list, redraw ) {
		/* still to do first, soonest due; my finished ones sink to the bottom */
		var sorted = list.slice().sort( function ( a, b ) { return ( doneByMe( a ) - doneByMe( b ) ) || ( ( a.due || '9999' ) < ( b.due || '9999' ) ? -1 : ( a.due || '9999' ) > ( b.due || '9999' ) ? 1 : 0 ); } );
		return h( 'ul', { class: 'tcc-hm__tasks' }, sorted.map( function ( x ) { return taskRow( x, redraw ); } ) );
	}
	/* Home: a nudge when one of my team's tasks is due within the week (or late) and I haven't ticked it */
	function taskWidget() {
		var soon = myTasks().filter( function ( x ) {
			if ( doneByMe( x ) || ! x.due ) { return false; }
			var days = Math.round( ( new Date( x.due + 'T12:00:00' ) - new Date( ( W.today || ymd( new Date() ) ) + 'T12:00:00' ) ) / 864e5 );
			return days < 7;
		} );
		if ( ! soon.length ) { return null; }
		var wrap = h( 'div' );
		function draw() {
			var left = soon.filter( function ( x ) { return ! doneByMe( x ); } );
			wrap.innerHTML = '';
			wrap.appendChild( tasksList( soon, draw ) );
			lbl.textContent = left.length ? W.roles[ myTeam() ].label + ' · ' + ( left.length === 1 ? '1 task due' : left.length + ' tasks due' ) : W.roles[ myTeam() ].label + ' · all done';
		}
		var lbl = h( 'span', { class: 'tcc-hm__fine' } );
		var w = widget( 'tcc-hm__wide tcc-hm__taskw', [ h( 'div', { class: 'tcc-hm__row' }, [ wk( 'Team tasks', 'is-red' ), h( 'a', { class: 'tcc-hm__dir-go', href: '#team' }, [ 'Team', h( 'span', { html: SVG.right } ) ] ) ] ), lbl, wrap ] );
		draw();
		return w;
	}
	function nextMeeting( key ) {
		var meet = null;
		W.rehearsals.some( function ( r ) {
			var a = ( r.after || [] ).filter( function ( x ) { return x.who.indexOf( key ) !== -1; } )[ 0 ];
			if ( a ) { meet = r.dow + ' ' + r.mon + ' ' + r.day + ( a.time ? ' · ' + a.time : '' ) + ' · ' + a.what; return true; }
			if ( r.who.indexOf( key ) !== -1 ) { meet = r.dow + ' ' + r.mon + ' ' + r.day + ( r.time ? ' · ' + r.time : '' ) + ' · ' + r.title; return true; }
			return false;
		} );
		return meet;
	}
	function viewTeam() {
		var who = me && me.who && me.who.onSeason ? me.who : null, team = myTeam();
		var role = team ? W.roles[ team ] : null;
		var nodes = [ personHead( role ? role.label : 'Teams', role && role.desc ? role.desc.charAt( 0 ).toUpperCase() + role.desc.slice( 1 ) : '' ) ];
		var grid = h( 'div', { class: 'tcc-hm__grid tcc-hm__grid--team' } );
		var pick = function () { signIn( 'details', { id: who.id, name: who.name, onSeason: true } ); };
		if ( role ) {
			var list = myTasks(), box = h( 'div' );
			var draw = function () { box.innerHTML = ''; box.appendChild( list.length ? tasksList( list, draw ) : h( 'p', { class: 'tcc-hm__fine' }, [ 'No tasks yet.' ] ) ); };
			draw();
			grid.appendChild( widget( 'tcc-hm__teamtasks', [ wk( 'Tasks', 'is-red' ), box ] ) );
			var others = ( W.teams[ team ] || [] ).filter( function ( n ) { return n !== who.name; } ), meet = nextMeeting( 'role:' + team );
			grid.appendChild( widget( 'tcc-hm__teaminfo', [
				wk( 'Your team' ),
				meet ? h( 'p', { class: 'tcc-hm__meet' }, [ h( 'span', { html: SVG.cal } ), h( 'span', {}, [ h( 'b', {}, [ 'Meets ' ] ), meet ] ) ] ) : null,
				h( 'p', { class: 'tcc-hm__body' }, [ others.length ? 'With ' + others.join( ', ' ) : 'You’re the first on this team.' ] ),
				h( 'button', { type: 'button', class: 'tcc-hm__link', onclick: pick }, [ 'Change team' ] )
			] ) );
		} else {
			grid.appendChild( widget( 'tcc-hm__wide', [ wk( 'Your team', 'is-red' ),
				h( 'p', { class: 'tcc-hm__body' }, [ 'Alongside singing, everyone takes on one small job for the season.' ] ),
				who ? h( 'button', { type: 'button', class: 'tcc-hm__btn', onclick: pick }, [ 'Pick a team', h( 'span', { html: SVG.right } ) ] )
					: h( 'button', { type: 'button', class: 'tcc-hm__btn', onclick: openSwitch }, [ 'Sign in', h( 'span', { html: SVG.right } ) ] )
			] ) );
		}
		nodes.push( grid );
		/* the teams to choose from, only while this person hasn't picked one */
		var rest = role ? [] : Object.keys( W.roles );
		if ( rest.length ) {
			nodes.push( h( 'h3', { class: 'tcc-hm__month' }, [ 'Teams' ] ) );
			nodes.push( h( 'div', { class: 'tcc-hm__grid tcc-hm__grid--list' }, rest.map( function ( k ) {
				var r = W.roles[ k ], names = W.teams[ k ] || [];
				return h( 'div', { class: 'tcc-hm__w tcc-hm__team' }, [
					h( 'b', {}, [ r.label ] ),
					r.desc ? h( 'span', { class: 'tcc-hm__fine' }, [ r.desc.charAt( 0 ).toUpperCase() + r.desc.slice( 1 ) ] ) : null,
					h( 'span', { class: 'tcc-hm__team-who' }, [ names.length ? names.join( ', ' ) : 'Nobody yet' ] )
				] );
			} ) ) );
		}
		show( nodes );
	}
	/* Docs: just what's for me — everyone's, my groups', my team's */
	function viewDocs() {
		var who = me && me.who && me.who.onSeason ? me.who : null, team = myTeam();
		var groups = [ 'everyone' ].concat( who ? who.types.filter( function ( t ) { return W.types[ t ]; } ) : [] ).concat( team ? [ 'role:' + team ] : [] );
		var grid = h( 'div', { class: 'tcc-hm__grid tcc-hm__grid--list tcc-hm__grid--docs' } ), any = false;
		groups.forEach( function ( g ) {
			var list = docList( [ g ], false ); if ( ! list ) { return; }
			any = true;
			var label = g === 'everyone' ? 'For everyone' : g.indexOf( 'role:' ) === 0 ? teamName( g.slice( 5 ) ) : 'For ' + ( W.types[ g ] || g ).toLowerCase();
			grid.appendChild( h( 'div', { class: 'tcc-hm__w tcc-hm__wide' }, [ wk( label, 'is-red' ), list ] ) );
		} );
		if ( ! any ) { grid.appendChild( h( 'div', { class: 'tcc-hm__w tcc-hm__wide' }, [ h( 'p', { class: 'tcc-hm__fine' }, [ 'Nothing posted yet.' ] ) ] ) ); }
		show( [ personHead( 'Documents' ), grid ] );
	}

	/* ---------- the Schedule tab: every rehearsal, month by month ---------- */
	function personHead( title, meta ) {
		var who = me && me.who && me.who.onSeason ? me.who : null;
		if ( ! who ) { return guestHead( title, meta ? [ meta ] : null ); }
		return h( 'header', { class: 'tcc-hm__head' }, [
			h( 'div', { class: 'tcc-hm__head-t' }, [ h( 'span', { class: 'tcc-hm__eyebrow' }, [ W.cantata.year + ' · ' + W.cantata.title ] ), h( 'h2', { class: 'tcc-hm__hello' }, [ title ] ), meta ? h( 'span', { class: 'tcc-hm__meta' }, [ meta ] ) : null ] ),
			h( 'button', { type: 'button', class: 'tcc-hm__me', 'aria-label': 'Switch person — now ' + who.name, onclick: openSwitch }, [ avatar( who ), h( 'span', { class: 'tcc-hm__me-n' }, [ firstOf( who ) ] ), h( 'span', { class: 'tcc-hm__me-c', html: SVG.down } ) ] )
		] );
	}
	function viewSchedule() {
		var who = me && me.who && me.who.onSeason ? me.who : null;
		var all = W.rehearsals || [];
		var past = all.filter( function ( r ) { return r.past; } ), coming = all.filter( function ( r ) { return ! r.past; } );
		var mineOnly = !! who && store( 'ls', 'tcc_sched_mine' ) === '1';
		var shown = coming.filter( function ( r ) { return ! mineOnly || forMe( r.who ); } );
		var nextMine = coming.filter( function ( r ) { return forMe( r.who ); } )[ 0 ];
		var left = coming.filter( function ( r ) { return r.type !== 'performance'; } ).length;
		var nodes = [ personHead( 'Schedule', left ? left + ( left === 1 ? ' rehearsal' : ' rehearsals' ) + ' to go' + ( W.cantata.perfDay ? ' · performance ' + W.cantata.perfDay[ 0 ] + ', ' + W.cantata.perfDay[ 2 ] + ' ' + W.cantata.perfDay[ 1 ] : '' ) : '' ) ];
		if ( who ) {
			var chip = function ( on, label, val ) { return h( 'button', { type: 'button', class: 'tcc-hm__seg' + ( on ? ' is-on' : '' ), 'aria-pressed': on ? 'true' : 'false', onclick: function () { store( 'ls', 'tcc_sched_mine', val ); viewSchedule(); } }, [ label ] ); };
			nodes.push( h( 'div', { class: 'tcc-hm__segs', role: 'group', 'aria-label': 'Which rehearsals' }, [ chip( ! mineOnly, 'Everything', null ), chip( mineOnly, 'Just mine', '1' ) ] ) );
		}
		/* the performance closes the season, unless it's already on the calendar as performance day */
		var c = W.cantata, perfListed = all.some( function ( r ) { return r.type === 'performance' || r.date === c.perfDate; } );
		var items = shown.slice();
		if ( c.perfDate && ! perfListed && +c.days >= 0 ) { items.push( { perf: true, date: c.perfDate } ); }
		var month = '', grid = null, list = h( 'div', { class: 'tcc-hm__sched-list' } );
		items.forEach( function ( r ) {
			var m = new Date( r.date + 'T12:00:00' ).toLocaleDateString( undefined, { month: 'long' } );
			if ( m !== month ) {
				month = m;
				list.appendChild( h( 'h3', { class: 'tcc-hm__month' }, [ m ] ) );
				grid = h( 'div', { class: 'tcc-hm__grid tcc-hm__grid--sched' } );
				list.appendChild( grid );
			}
			if ( r.perf ) {
				grid.appendChild( widget( 'tcc-hm__count tcc-hm__perf', [
					wk( 'Performance' ),
					h( 'div', { class: 'tcc-hm__perf-row' }, [
						h( 'div', { class: 'tcc-hm__page', 'aria-hidden': 'true' }, [ h( 'span', { class: 'tcc-hm__page-m' }, [ c.perfDay[ 2 ].toUpperCase() ] ), h( 'span', { class: 'tcc-hm__page-d' }, [ c.perfDay[ 1 ] ] ), h( 'span', { class: 'tcc-hm__page-w' }, [ c.perfDay[ 0 ].toUpperCase() ] ) ] ),
						h( 'div', {}, [ h( 'h3', {}, [ c.title ] ), h( 'span', {}, [ ( c.perfTime ? c.perfTime.replace( ':00', '' ) + ' · ' : '' ) + ( +c.days === 0 ? 'Today!' : c.days + ( +c.days === 1 ? ' day' : ' days' ) + ' to go' ) ] ) ] )
					] )
				] ) );
				return;
			}
			var isNext = nextMine && r.id === nextMine.id, rel = relDay( r.date ), other = who && ! forMe( r.who );
			grid.appendChild( widget( 'tcc-hm__rcard' + ( isNext ? ' is-next' : '' ) + ( other ? ' is-other' : '' ), [
				isNext || rel || other ? h( 'div', { class: 'tcc-hm__row' }, [ isNext ? wk( who ? 'Next for you' : 'Next rehearsal', 'is-red' ) : other ? wk( 'Not for you' ) : h( 'span' ), rel ? h( 'span', { class: 'tcc-hm__chip' }, [ rel ] ) : null ] ) : null
			].concat( rehBody( r ) ) ) );
		} );
		if ( ! items.length ) { list.appendChild( widget( '', [ h( 'p', { class: 'tcc-hm__fine' }, [ mineOnly ? 'Nothing else on the calendar for you this season.' : 'Nothing on the calendar right now.' ] ) ] ) ); }
		nodes.push( list );
		if ( past.length ) {
			nodes.push( h( 'details', { class: 'tcc-hm__past' }, [
				h( 'summary', {}, [ 'Earlier this season · ' + past.length ] ),
				h( 'ul', {}, past.slice().reverse().map( function ( r ) { return h( 'li', {}, [ h( 'b', {}, [ r.dow + ' ' + r.mon + ' ' + r.day ] ), h( 'span', {}, [ r.title + ( r.full ? ' · full run-through' : r.songs && r.songs.length ? ' · ' + r.songs.map( function ( i ) { return i + 1; } ).join( ', ' ) : '' ) ] ) ] ); } ) )
			] ) );
		}
		show( nodes );
	}

	/* ---------- go ---------- */
	function load() {
		root.innerHTML = '';
		root.appendChild( h( 'p', { class: 'tcc-dash__loading' }, [ 'One moment…' ] ) );
		api( 'me', { cantata: W.cantata.id } ).then( function ( r ) {
			me = r || {}; rows = {};
			( r.rows || [] ).forEach( function ( row ) { rows[ row.song + '|' + row.part ] = row; } );
			if ( r.who && r.who.onSeason ) { remember( r.who ); }
			route();
		} ).catch( function () { me = {}; route(); } );
	}
	/* on computers the person switch lives in the tab bar, as in an app's title bar */
	function navMe() {
		var slot = document.querySelector( '.tcc-tabs__me' ); if ( ! slot ) { return; }
		var who = me && me.who && me.who.onSeason ? me.who : null;
		slot.innerHTML = '';
		document.documentElement.classList.add( 'tcc-navme' );
		if ( ! who ) {
			slot.appendChild( h( 'button', { type: 'button', class: 'tcc-tabs__who', 'aria-label': 'Sign in — who’s practicing?', onclick: openSwitch }, [ h( 'span', { class: 'tcc-hm__av tcc-hm__av--guest', html: DOOR.user } ), h( 'span', {}, [ 'Sign in' ] ) ] ) );
			return;
		}
		var part = W.voice[ who.part ] && isSinger() ? ' · ' + W.voice[ who.part ].label : '';
		slot.appendChild( h( 'button', { type: 'button', class: 'tcc-tabs__who', 'aria-label': 'Switch person — now ' + who.name, onclick: openSwitch }, [ avatar( who ), h( 'span', {}, [ who.name + part ] ), h( 'span', { class: 'tcc-hm__me-c', html: SVG.down } ) ] ) );
	}
	/* the hub draws three tabs: Home (default), Team (#team) and Docs (#docs) */
	function route() {
		navMe();
		if ( W.view === 'schedule' ) { document.documentElement.setAttribute( 'data-tcc-tab', 'schedule' ); root.classList.add( 'tcc-dash--sched' ); viewSchedule(); return; }
		var tab = ( location.hash || '' ).replace( '#', '' );
		document.documentElement.setAttribute( 'data-tcc-tab', tab === 'team' || tab === 'docs' ? tab : 'home' );
		if ( window.tccTabs ) { window.tccTabs(); }
		if ( tab === 'team' ) { viewTeam(); return; }
		if ( tab === 'docs' ) { viewDocs(); return; }
		var r = me || {};
		if ( store( 'ss', 'tcc_browse' ) || ( ! r.who && ! me ) ) { viewBrowse(); return; }
		if ( ! r.who ) { viewChooser(); return; }
		if ( ! r.who.onSeason ) { viewWelcomeBack( r.who ); return; }
		viewDash();
	}
	window.addEventListener( 'hashchange', function () { if ( me && W.view !== 'schedule' ) { route(); window.scrollTo( 0, 0 ); } } );
	load();
} )();
