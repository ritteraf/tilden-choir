/* Tilden Cantata — the Backstage dashboard.
   The page HTML is the same for everyone; this asks /me who the device belongs to and draws
   that person's page: their next rehearsal, the songs to work on, their tracks, documents and team.
   An unknown device gets three equal choices. Browsing without signing in shows the general page. */
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
			if ( k === 'class' ) { el.className = attrs[ k ]; }
			else if ( k === 'html' ) { el.innerHTML = attrs[ k ]; }
			else if ( k.indexOf( 'on' ) === 0 ) { el.addEventListener( k.slice( 2 ), attrs[ k ] ); }
			else if ( attrs[ k ] !== null && attrs[ k ] !== undefined && attrs[ k ] !== false ) { el.setAttribute( k, attrs[ k ] === true ? '' : attrs[ k ] ); }
		} ); }
		( children || [] ).forEach( function ( c ) { if ( c === null || c === undefined || c === false ) { return; } el.appendChild( typeof c === 'string' ? document.createTextNode( c ) : c ); } );
		return el;
	}
	function api( path, body ) {
		var url = W.rest + path + '?' + Object.keys( body ).map( function ( k ) { return k + '=' + encodeURIComponent( body[ k ] ); } ).join( '&' );
		return fetch( url, { headers: { 'X-TCC-Token': W.token }, credentials: 'same-origin', cache: 'no-store' } ).then( function ( r ) { return r.json(); } );
	}
	function ss( k, v ) { try { if ( v === undefined ) { return sessionStorage.getItem( k ); } if ( v === null ) { sessionStorage.removeItem( k ); } else { sessionStorage.setItem( k, v ); } } catch ( e ) { return null; } }
	function todayStr() { var d = new Date(); return d.getFullYear() + '-' + ( '0' + ( d.getMonth() + 1 ) ).slice( -2 ) + '-' + ( '0' + d.getDate() ).slice( -2 ); }
	function relDay( r ) {
		var t = todayStr();
		if ( r.date === t ) { return 'Today'; }
		var d = new Date(); d.setDate( d.getDate() + 1 );
		var tm = d.getFullYear() + '-' + ( '0' + ( d.getMonth() + 1 ) ).slice( -2 ) + '-' + ( '0' + d.getDate() ).slice( -2 );
		return r.date === tm ? 'Tomorrow' : '';
	}
	function pills( who ) {
		var box = h( 'span', { class: 'tcc-next__who' } );
		if ( ! who || ! who.length ) { box.appendChild( h( 'span', { class: 'tcc-who tcc-who--all' }, [ 'Everyone' ] ) ); return box; }
		who.forEach( function ( k ) {
			var role = k.indexOf( 'role:' ) === 0;
			var label = role ? ( ( W.roles[ k.slice( 5 ) ] || {} ).label || k.slice( 5 ) ) : ( W.types[ k ] || k );
			box.appendChild( h( 'span', { class: 'tcc-who tcc-who--' + ( role ? 'role' : k ) }, [ label ] ) );
		} );
		return box;
	}
	function practiceUrl( part, song ) {
		var u = W.urls.practice || ( W.urls.parts[ part ] || W.urls.hub );
		if ( part && W.urls.practice ) { u += ( u.indexOf( '?' ) === -1 ? '?' : '&' ) + 'part=' + part; }
		if ( song !== undefined && song !== null ) { u += '#song-' + ( song + 1 ); }
		return u;
	}
	function eyebrow( text, mod ) { return h( 'p', { class: 'tc-eyebrow' + ( mod ? ' tc-eyebrow--' + mod : '' ) }, [ text ] ); }

	/* ---------- state ---------- */
	var me = null, rows = {}, noteCounts = {};
	function rowKey( song, part ) { return song + '|' + part; }

	/* who-matching: does a "who" list include this person? empty = everyone */
	function forMe( who ) {
		if ( ! who || ! who.length ) { return true; }
		var p = me.who;
		return who.some( function ( k ) { return p.types.indexOf( k ) !== -1 || ( p.role && k === 'role:' + p.role ); } );
	}
	function requiredOfMe( must ) { return !! ( must && must.length && forMe( must ) ); }
	function myTrackParts() {
		var v = W.voice[ me.who.part ];
		return v ? v.tracks.filter( function ( p ) { return W.parts[ p ] && W.parts[ p ].count; } ) : [];
	}
	function isSinger() { return me.who.types.indexOf( 'singers' ) !== -1; }
	function statFor( song ) {
		var out = { plays: 0, flag: '', last: '' };
		myTrackParts().forEach( function ( p ) { var r = rows[ rowKey( song, p ) ]; if ( r ) { out.plays += r.plays || 0; if ( r.flag ) { out.flag = r.flag; } if ( r.last && r.last > out.last ) { out.last = r.last; } } } );
		return out;
	}
	function cuesFor( song ) {
		var parts = myTrackParts();
		return W.cues.filter( function ( c ) { return c.song === song && ( ! c.parts.length || c.parts.some( function ( p ) { return parts.indexOf( p ) !== -1; } ) ); } ).length;
	}

	/* ---------- the rehearsal card (same bones as the PHP one) ---------- */
	function rehearsalCard( r, personal ) {
		var req = personal ? requiredOfMe( r.must ) : ( r.must && r.must.length > 0 );
		var card = h( 'div', { class: 'tcc-next tcc-next--' + r.type + ( req ? ' tcc-next--required' : '' ) } );
		card.appendChild( h( 'div', { class: 'tcc-next__date' }, [ h( 'span', { class: 'tcc-next__dow' }, [ r.dow ] ), h( 'span', { class: 'tcc-next__day' }, [ r.day ] ), h( 'span', { class: 'tcc-next__mon' }, [ r.mon ] ) ] ) );
		var body = h( 'div', { class: 'tcc-next__body' } );
		var label = r.type === 'performance' ? 'Performance day' : 'Next rehearsal';
		if ( req ) { label += personal ? ' · required for you' : ' · attendance required'; }
		var rel = relDay( r );
		body.appendChild( h( 'span', { class: 'tcc-next__label' }, [ label ] ) );
		body.appendChild( h( 'span', { class: 'tcc-next__title' }, [ r.title ] ) );
		body.appendChild( h( 'span', { class: 'tcc-next__when' }, [ ( rel ? rel + ' · ' : '' ) + r.long + ( r.time ? ' · ' + r.time : '' ) ] ) );
		var facts = h( 'dl', { class: 'tcc-next__facts' } );
		facts.appendChild( h( 'div', {}, [ h( 'dt', {}, [ 'Who should attend' ] ), h( 'dd', {}, [ pills( r.who ) ] ) ] ) );
		if ( r.full ) {
			facts.appendChild( h( 'div', {}, [ h( 'dt', {}, [ 'What we’re working on' ] ), h( 'dd', { class: 'tcc-next__songs' }, [ h( 'span', { class: 'tcc-next__full', html: W.icons.note + 'Full run-through' } ), ' The whole work, start to finish.' ] ) ] ) );
		} else if ( r.songs.length ) {
			var ul = h( 'ul' );
			r.songs.forEach( function ( i ) { var f = r.focus.indexOf( i ) !== -1; ul.appendChild( h( 'li', { class: f ? 'is-focus' : null }, [ ( i + 1 ) + '. ' + W.songs[ i ], f ? h( 'span', { class: 'tcc-focus', title: 'Focus on this one' }, [ ' ★' ] ) : null ] ) ); } );
			facts.appendChild( h( 'div', {}, [ h( 'dt', {}, [ 'What we’re working on' ] ), h( 'dd', { class: 'tcc-next__songs' }, [ ul ] ) ] ) );
		}
		var after = r.after.filter( function ( a ) { return ! personal || forMe( a.who ); } );
		if ( after.length ) {
			var al = h( 'ul' );
			after.forEach( function ( a ) { al.appendChild( h( 'li', {}, [ a.time ? h( 'span', { class: 'tcc-next__after-time' }, [ a.time ] ) : null, ' ' + a.what + ' ', personal ? null : pills( a.who ) ] ) ); } );
			facts.appendChild( h( 'div', {}, [ h( 'dt', {}, [ personal ? 'Afterward, for you' : 'Afterward' ] ), h( 'dd', { class: 'tcc-next__after' }, [ al ] ) ] ) );
		}
		body.appendChild( facts );
		if ( r.note ) { body.appendChild( h( 'div', { class: 'tcc-next__notes', html: r.note } ) ); }
		if ( W.urls.schedule ) { body.appendChild( h( 'a', { class: 'tcc-btn tcc-btn--light tcc-next__btn', href: W.urls.schedule }, [ 'Full schedule' ] ) ); }
		card.appendChild( body );
		return card;
	}

	function docCards( groups ) {
		var out = [];
		groups.forEach( function ( g ) {
			var list = W.docs[ g ];
			if ( ! list || ! list.length ) { return; }
			out.push( eyebrow( g === 'everyone' ? 'Documents' : W.types[ g ] + ' documents', g === 'everyone' ? '' : g ) );
			var grid = h( 'div', { class: 'tcc-docs' } );
			list.forEach( function ( d ) { grid.appendChild( h( 'a', { class: 'tcc-doc tcc-doc--' + d.icon, href: d.url, target: '_blank', rel: 'noopener', html: W.icons[ d.icon ] + '<span class="tcc-doc__title"></span>' + ( d.note ? '<span class="tcc-doc__note"></span>' : '' ) } ) ); var a = grid.lastChild; a.querySelector( '.tcc-doc__title' ).textContent = d.title; if ( d.note ) { a.querySelector( '.tcc-doc__note' ).textContent = d.note; } } );
			out.push( grid );
		} );
		return out;
	}

	/* ---------- views ---------- */
	function show() { root.innerHTML = ''; for ( var i = 0; i < arguments.length; i++ ) { if ( arguments[ i ] ) { root.appendChild( arguments[ i ] ); } } }

	function signIn( start, who ) {
		var cfg = W.signin; cfg.who = who || null;
		window.tccSignin.open( cfg, function () { ss( 'tcc_browse', null ); load(); }, start );
	}

	/* an unknown device: three equal doors */
	function viewChooser() {
		show( h( 'section', { class: 'tcc-dash__choose' }, [
			h( 'span', { class: 'tcc-dash__eyebrow' }, [ W.cantata.year + ' · ' + W.cantata.title ] ),
			h( 'h2', { class: 'tcc-dash__h' }, [ 'Welcome backstage.' ] ),
			h( 'p', { class: 'tcc-dash__lede' }, [ 'Tell us who you are once on this device and Backstage becomes your page: your rehearsals, your part’s tracks, your notes. It also replaces the paper sign-up sheet.' ] ),
			h( 'div', { class: 'tcc-dash__doors' }, [
				h( 'button', { type: 'button', class: 'tcc-dash__door', onclick: function () { signIn( 'find' ); } }, [ h( 'b', {}, [ 'I’ve sung with the choir before' ] ), h( 'span', {}, [ 'Find my name on the list' ] ) ] ),
				h( 'button', { type: 'button', class: 'tcc-dash__door', onclick: function () { signIn( 'new' ); } }, [ h( 'b', {}, [ 'I’m new this year' ] ), h( 'span', {}, [ 'Sign me up — it takes a minute' ] ) ] ),
				h( 'button', { type: 'button', class: 'tcc-dash__door tcc-dash__door--quiet', onclick: function () { ss( 'tcc_browse', '1' ); viewBrowse(); } }, [ h( 'b', {}, [ 'Just browsing for now' ] ), h( 'span', {}, [ 'Have a look around; you can sign in any time' ] ) ] )
			] )
		] ) );
	}

	/* a known device whose person isn't on this season yet */
	function viewWelcomeBack( who ) {
		var first = who.first || who.name.split( ' ' )[ 0 ];
		var card = h( 'section', { class: 'tcc-dash__choose tcc-dash__choose--back' }, [
			h( 'span', { class: 'tcc-dash__eyebrow' }, [ W.cantata.year + ' · ' + W.cantata.title ] ),
			h( 'h2', { class: 'tcc-dash__h' }, [ 'Welcome back, ' + first + '!' ] ),
			h( 'p', { class: 'tcc-dash__lede' }, [ 'Taking part in ' + W.cantata.title + '? Confirm your details and this becomes your page. It replaces the paper sign-up sheet.' ] ),
			h( 'div', { class: 'tcc-dash__doors' }, [
				h( 'button', { type: 'button', class: 'tcc-dash__door tcc-dash__door--primary', onclick: function () { signIn( undefined, { id: who.id, name: who.name, onSeason: false } ); } }, [ h( 'b', {}, [ 'Yes, I’m in this year' ] ), h( 'span', {}, [ 'Check my details' ] ) ] ),
				h( 'button', { type: 'button', class: 'tcc-dash__door tcc-dash__door--quiet', onclick: function () { ss( 'tcc_browse', '1' ); viewBrowse(); } }, [ h( 'b', {}, [ 'Just browsing for now' ] ), h( 'span', {}, [ 'Have a look around first' ] ) ] )
			] ),
			h( 'p', { class: 'tcc-dash__foot' }, [ 'Not ' + first + '? ', h( 'button', { type: 'button', class: 'tcc-dash__link', onclick: function () { signIn( 'find' ); } }, [ 'Find your name' ] ), ' · ', h( 'button', { type: 'button', class: 'tcc-dash__link', onclick: function () { signIn( 'new' ); } }, [ 'I’m new' ] ) ] )
		] );
		show( card );
	}

	/* the general page, for anyone */
	function viewBrowse() {
		var parts = h( 'div', { class: 'tc-tiles tcc-dash__tiles' } );
		[ 'soprano', 'alto', 'tenor', 'bass' ].forEach( function ( p ) { if ( W.parts[ p ] && W.parts[ p ].count ) { parts.appendChild( h( 'p', { class: 'tc-tile' }, [ h( 'a', { href: practiceUrl( p ) }, [ h( 'b', {}, [ W.parts[ p ].label.charAt( 0 ) ] ), h( 'span', {}, [ W.parts[ p ].label ] ) ] ) ] ) ); } } );
		if ( W.parts.accompaniment && W.parts.accompaniment.count ) { parts.appendChild( h( 'p', { class: 'tc-tile tc-tile--acc' }, [ h( 'a', { href: practiceUrl( 'accompaniment' ) }, [ h( 'b', {}, [ 'Acc' ] ), h( 'span', {}, [ 'Accompaniment only' ] ) ] ) ] ) ); }
		var line = h( 'p', { class: 'tcc-dash__line' }, [ me && me.who ? 'Browsing as ' : 'Browsing without signing in. ', me && me.who ? h( 'b', {}, [ me.who.name ] ) : null, me && me.who ? '. ' : '', h( 'button', { type: 'button', class: 'tcc-dash__link', onclick: function () { ss( 'tcc_browse', null ); if ( me && me.who ) { signIn( undefined, { id: me.who.id, name: me.who.name, onSeason: me.who.onSeason } ); } else { viewChooser(); } } }, [ me && me.who ? ( me.who.onSeason ? 'Show my page' : 'Sign in for this year' ) : 'Sign in' ] ) ] );
		var next = W.rehearsals[ 0 ];
		show.apply( null, [ line, next ? rehearsalCard( next, false ) : null, eyebrow( 'Everyone' ),
			h( 'div', { class: 'tc-cards tcc-dash__cards' }, [
				W.urls.schedule ? h( 'p', { class: 'tc-card tc-card--calendar' }, [ h( 'a', { href: W.urls.schedule }, [ 'Full cantata schedule' ] ), h( 'span', {}, [ 'Every rehearsal, who it’s for, what we’ll cover, and which ones are required.' ] ) ] ) : null,
				W.urls.practice ? h( 'p', { class: 'tc-card tc-card--play' }, [ h( 'a', { href: W.urls.practice }, [ 'Practice tracks' ] ), h( 'span', {}, [ 'Every part, with the director’s notes. Sign in and it keeps track of what you’ve worked on.' ] ) ] ) : null
			] ) ].concat( docCards( [ 'everyone' ] ), [ parts.children.length ? eyebrow( 'Singers', 'singers' ) : null, parts.children.length ? parts : null ], docCards( [ 'singers', 'narrators', 'actors', 'tech' ] ) ) );
	}

	/* the person's own page */
	function viewDash() {
		var who = me.who;
		var chips = [];
		if ( isSinger() && W.voice[ who.part ] ) { chips.push( W.voice[ who.part ].label ); }
		who.types.forEach( function ( t ) { if ( t !== 'singers' && W.types[ t ] ) { chips.push( W.types[ t ] ); } } );
		if ( who.role && W.roles[ who.role ] ) { chips.push( W.roles[ who.role ].label + ' team' ); }
		var head = h( 'header', { class: 'tcc-dash__me' }, [
			h( 'div', {}, [
				h( 'span', { class: 'tcc-dash__eyebrow' }, [ W.cantata.year + ' · ' + W.cantata.title ] ),
				h( 'h2', { class: 'tcc-dash__name' }, [ who.name ] ),
				h( 'div', { class: 'tcc-dash__chips' }, chips.map( function ( c ) { return h( 'span', { class: 'tcc-dash__chip' }, [ c ] ); } ) )
			] ),
			h( 'div', { class: 'tcc-dash__me-actions' }, [
				h( 'button', { type: 'button', class: 'tcc-dash__link', onclick: function () { signIn( undefined, { id: who.id, name: who.name, onSeason: true } ); } }, [ 'Not you, or details changed?' ] )
			] )
		] );

		/* next rehearsal that is mine */
		var sections = [ head ];
		var mine = W.rehearsals.filter( function ( r ) { return forMe( r.who ); } );
		var first = W.rehearsals[ 0 ], next = mine[ 0 ];
		if ( next ) {
			if ( first && first.id !== next.id ) {
				sections.push( h( 'p', { class: 'tcc-dash__aside' }, [ first.long + ' is for ', pills( first.who ), ' — your next one is ' + next.long + '.' ] ) );
			}
			sections.push( rehearsalCard( next, true ) );
		} else {
			sections.push( h( 'p', { class: 'tcc-dash__aside' }, [ 'Nothing on the calendar for you right now.', W.urls.schedule ? ' ' : '', W.urls.schedule ? h( 'a', { href: W.urls.schedule }, [ 'See the full schedule' ] ) : null ] ) );
		}

		/* songs to work on */
		var parts = myTrackParts();
		if ( isSinger() ) {
			var primary = parts[ 0 ] || 'soprano';
			if ( parts.length ) {
				var pool = next && ! next.full && next.songs.length ? next.songs.slice() : W.songs.map( function ( _, i ) { return i; } );
				var focus = next ? next.focus : [];
				var full = ! next || next.full || ! next.songs.length;
				pool = pool.filter( function ( i ) { return parts.some( function ( p ) { return W.parts[ p ].songs.indexOf( i ) !== -1; } ); } );
				pool.sort( function ( a, b ) {
					var fa = focus.indexOf( a ) !== -1 ? 0 : 1, fb = focus.indexOf( b ) !== -1 ? 0 : 1;
					if ( fa !== fb ) { return fa - fb; }
					if ( full ) { var pa = statFor( a ).plays, pb = statFor( b ).plays; if ( pa !== pb ) { return pa - pb; } }
					return a - b;
				} );
				if ( full ) { pool = pool.slice( 0, 6 ); }
				var title = next ? ( full ? ( next.full ? 'Full run-through ' + next.long + ' — least practiced first' : 'Least practiced' ) : 'Songs for ' + next.long ) : 'Least practiced';
				var list = h( 'ol', { class: 'tcc-dash__songs' } );
				pool.forEach( function ( i ) {
					var st = statFor( i ), cues = cuesFor( i ), notes = noteCounts[ i ] || 0, isF = focus.indexOf( i ) !== -1;
					var meta = [];
					meta.push( st.plays ? 'practiced ' + st.plays + '×' : 'not yet practiced' );
					if ( cues ) { meta.push( cues + ' director note' + ( cues > 1 ? 's' : '' ) ); }
					if ( notes ) { meta.push( notes + ' of your notes' ); }
					list.appendChild( h( 'li', { class: 'tcc-dash__song' + ( isF ? ' is-focus' : '' ) + ( st.flag ? ' is-' + st.flag : '' ) }, [
						h( 'a', { class: 'tcc-dash__song-play', href: practiceUrl( primary, i ), 'aria-label': 'Practice ' + W.songs[ i ], html: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 5v14l12-7z"/></svg>' } ),
						h( 'div', { class: 'tcc-dash__song-text' }, [
							h( 'b', {}, [ ( i + 1 ) + '. ' + W.songs[ i ], isF ? h( 'span', { class: 'tcc-focus', title: 'The director says: focus on this one' }, [ ' ★' ] ) : null ] ),
							h( 'span', {}, [ meta.join( ' · ' ) ] )
						] ),
						st.flag ? h( 'span', { class: 'tcc-dash__flag tcc-dash__flag--' + st.flag }, [ { ok: 'Got it', working: 'Working on it', help: 'Asked for help' }[ st.flag ] ] ) : null
					] ) );
				} );
				sections.push( h( 'section', { class: 'tcc-dash__sec' }, [
					eyebrow( title ),
					list,
					h( 'div', { class: 'tcc-dash__cta' }, [
						h( 'a', { class: 'tcc-dash__start', href: practiceUrl( primary ) }, [ h( 'span', { html: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 5v14l12-7z"/></svg>' } ), 'Start practicing' ] ),
						h( 'span', { class: 'tcc-dash__cta-sub' }, [ parts.map( function ( p ) { return W.parts[ p ].label; } ).join( ' and ' ) + ' tracks' + ( parts.length > 1 ? '' : ', and every other part a tap away' ) + '.' ] )
					] )
				] ) );
			} else {
				sections.push( h( 'section', { class: 'tcc-dash__sec' }, [ eyebrow( 'Practice' ), h( 'p', { class: 'tcc-dash__aside' }, [ W.voice[ who.part ] ? 'No ' + W.voice[ who.part ].label + ' tracks are up yet. ' : 'We don’t have a voice part for you yet — talk to the director at the next rehearsal. ', W.urls.practice ? h( 'a', { href: W.urls.practice }, [ 'See what’s there' ] ) : null ] ) ] ) );
			}
		}

		/* documents: everyone + my groups */
		var groups = [ 'everyone' ].concat( who.types.filter( function ( t ) { return W.types[ t ]; } ) );
		var docs = docCards( groups );
		if ( docs.length ) { sections.push( h( 'section', { class: 'tcc-dash__sec' }, docs ) ); }

		/* team */
		if ( who.role && W.roles[ who.role ] ) {
			var role = W.roles[ who.role ], key = 'role:' + who.role;
			var meet = null;
			W.rehearsals.some( function ( r ) {
				var a = r.after.filter( function ( x ) { return x.who.indexOf( key ) !== -1; } )[ 0 ];
				if ( a ) { meet = { r: r, a: a }; return true; }
				if ( r.who.indexOf( key ) !== -1 ) { meet = { r: r, a: null }; return true; }
				return false;
			} );
			var members = ( W.teams[ who.role ] || [] ).filter( function ( n ) { return n !== who.name; } );
			sections.push( h( 'section', { class: 'tcc-dash__sec' }, [
				eyebrow( role.label + ' team' ),
				h( 'div', { class: 'tcc-dash__team' }, [
					role.desc ? h( 'p', { class: 'tcc-dash__team-desc' }, [ role.desc.charAt( 0 ).toUpperCase() + role.desc.slice( 1 ) + '.' ] ) : null,
					meet ? h( 'p', { class: 'tcc-dash__team-meet', html: W.icons.then } ) : null,
					h( 'p', { class: 'tcc-dash__team-who' }, [ members.length ? 'With ' + members.join( ', ' ) + '.' : 'You’re the first on this team — more will join as people sign in.' ] )
				] )
			] ) );
			if ( meet ) { var m = sections[ sections.length - 1 ].querySelector( '.tcc-dash__team-meet' ); m.appendChild( h( 'span', {}, [ meet.a ? 'Meets ' + meet.r.long + ( meet.a.time ? ', ' + meet.a.time : ', after rehearsal' ) + ' — ' + meet.a.what : 'Meets ' + meet.r.long + ( meet.r.time ? ', ' + meet.r.time : '' ) + ' — ' + meet.r.title ] ) ); }
		} else {
			sections.push( h( 'section', { class: 'tcc-dash__sec' }, [ eyebrow( 'Your team' ), h( 'p', { class: 'tcc-dash__aside' }, [ 'Alongside singing, everyone takes on one small job for the season. ', h( 'button', { type: 'button', class: 'tcc-dash__link', onclick: function () { signIn( undefined, { id: who.id, name: who.name, onSeason: true } ); } }, [ 'Pick a team' ] ) ] ) ] ) );
		}

		if ( W.canEdit && W.admin ) {
			sections.push( h( 'p', { class: 'tcc-dash__aside tcc-dash__aside--director' }, [ 'Director: ', h( 'a', { href: W.admin }, [ 'what the choir is practicing' ] ) ] ) );
		}
		show.apply( null, sections );
	}

	/* ---------- go ---------- */
	function load() {
		root.innerHTML = '';
		root.appendChild( h( 'p', { class: 'tcc-dash__loading' }, [ 'One moment…' ] ) );
		api( 'me', { cantata: W.cantata.id } ).then( function ( r ) {
			me = r || {};
			rows = {}; noteCounts = {};
			( r.rows || [] ).forEach( function ( row ) { rows[ rowKey( row.song, row.part ) ] = row; } );
			( r.notes || [] ).forEach( function ( n ) { noteCounts[ n.song ] = n.n; } );
			if ( ss( 'tcc_browse' ) ) { viewBrowse(); return; }
			if ( ! r.who ) { viewChooser(); return; }
			if ( ! r.who.onSeason ) { viewWelcomeBack( r.who ); return; }
			viewDash();
		} ).catch( function () { me = {}; viewBrowse(); } );
	}
	load();
} )();
