/* The director's report: how the cantata is coming, one practice week at a time. */
( function () {
	'use strict';
	var root = document.querySelector( '[data-tcc-dir]' );
	if ( ! root ) { return; }
	var D = JSON.parse( root.getAttribute( 'data-tcc-dir' ) );
	var SECT = D.sections, SHORT = { soprano: 'S', alto: 'A', tenor: 'T', bass: 'B' };
	var week = D.weeks[ 0 ].key;

	function h( tag, attrs, kids ) {
		var el = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( k ) {
			var v = attrs[ k ];
			if ( v === null || v === undefined || v === false ) { return; }
			if ( k === 'class' ) { el.className = v; } else if ( k === 'html' ) { el.innerHTML = v; } else if ( k.indexOf( 'on' ) === 0 ) { el.addEventListener( k.slice( 2 ), v ); } else if ( k === 'style' ) { el.style.cssText = v; } else { el.setAttribute( k, v === true ? '' : v ); }
		} );
		( kids || [] ).forEach( function ( c ) { if ( c === null || c === undefined || c === false ) { return; } el.appendChild( typeof c === 'string' || typeof c === 'number' ? document.createTextNode( String( c ) ) : c ); } );
		return el;
	}
	function fmt( s ) { s = Math.max( 0, Math.round( s ) ); return Math.floor( s / 60 ) + ':' + ( '0' + ( s % 60 ) ).slice( -2 ); }
	function mins( m ) { if ( m < 60 ) { return m + ' min'; } var hh = Math.floor( m / 60 ), mm = m % 60; return hh + ' h' + ( mm ? ' ' + mm + ' min' : '' ); }
	function weekOf( k ) { return D.weeks.filter( function ( w ) { return w.key === k; } )[ 0 ]; }
	function sec( title, sub, kids, cls ) {
		return h( 'section', { class: 'tcc-dir__sec' + ( cls ? ' ' + cls : '' ) }, [ h( 'h3', { class: 'tcc-dir__h3' }, [ title ] ), sub ? h( 'p', { class: 'tcc-dir__sub' }, [ sub ] ) : null ].concat( kids ) );
	}
	/* page numbers along a song: the score's first page, then one more at each recorded page turn */
	function pageAt( song, t ) {
		var r = D.pages[ song ]; if ( ! r ) { return null; }
		var n = 0; ( D.turns[ song ] || [] ).forEach( function ( x ) { if ( x <= t ) { n++; } } );
		return Math.min( r[ 1 ], r[ 0 ] + n ) + D.offset;
	}
	function feelDots( f ) {
		f = f || {};
		var out = h( 'span', { class: 'tcc-dir__feel' } );
		[ [ 'ok', 'good', 'Feeling good' ], [ 'working', 'some', 'Getting there' ], [ 'help', 'help', 'Asked for help' ] ].forEach( function ( o ) {
			if ( f[ o[ 0 ] ] ) { out.appendChild( h( 'span', { class: 'tcc-dir__dot tcc-dir__dot--' + o[ 1 ], title: f[ o[ 0 ] ] + ' · ' + o[ 2 ] }, [ String( f[ o[ 0 ] ] ) ] ) ); }
		} );
		return out.children.length ? out : null;
	}
	function cell( c, size ) {
		var pct = size ? Math.min( 1, c.people / size ) : ( c.people ? 1 : 0 );
		return h( 'div', { class: 'tcc-dir__cell' + ( c.people ? '' : ' is-zero' ), style: '--fill:' + pct.toFixed( 2 ), title: c.people + ( size ? ' of ' + size : '' ) + ' practiced · ' + mins( c.mins ) }, [
			h( 'span', { class: 'tcc-dir__cell-n' }, [ size ? c.people + '/' + size : String( c.people ) ] ),
			h( 'span', { class: 'tcc-dir__cell-bar' }, [ h( 'i', { style: 'width:' + Math.round( pct * 100 ) + '%' } ) ] ),
			feelDots( c.feel )
		] );
	}

	function render() {
		var R = D.reports[ week ], W = weekOf( week );
		root.innerHTML = '';

		/* heading and week picker */
		root.appendChild( h( 'header', { class: 'tcc-dir__head' }, [
			h( 'span', { class: 'tcc-dir__eyebrow' }, [ 'Director · ' + D.cantata.year + ' · ' + D.cantata.title ] ),
			h( 'h2', { class: 'tcc-dir__h' }, [ 'How the cantata is coming' ] ),
			h( 'p', { class: 'tcc-dir__lede' }, [ 'From the practice app, one practice week at a time — rehearsal to rehearsal.' + ( D.since ? ' Practice has been recorded since ' + D.since + '; marks from before then still count.' : ' Nothing recorded yet — this fills in as people practice.' ) ] )
		] ) );
		var chips = h( 'div', { class: 'tcc-dir__weeks', role: 'tablist', 'aria-label': 'Practice week' } );
		D.weeks.forEach( function ( w ) {
			chips.appendChild( h( 'button', { type: 'button', role: 'tab', class: 'tcc-dir__week' + ( w.key === week ? ' is-on' : '' ), 'aria-selected': w.key === week ? 'true' : 'false', title: w.label, onclick: function () { week = w.key; render(); } }, [ w.short ] ) );
		} );
		root.appendChild( chips );
		root.appendChild( h( 'p', { class: 'tcc-dir__weeklabel' }, [ W.label ] ) );

		/* the four numbers */
		var silent = D.songs.filter( function ( t, i ) { var c = R.cells[ i ]; return c && ! SECT.some( function ( s ) { return c[ s.id ].people; } ) && ! c.otherMins; } );
		var openHelp = R.help.filter( function ( x ) { return ! x.handled; } ).length;
		root.appendChild( h( 'div', { class: 'tcc-dir__tiles' }, [
			tile( R.active + ( D.singers ? ' of ' + D.singers : '' ), 'singers practiced' ),
			tile( mins( R.minutes ), 'of listening' ),
			tile( String( openHelp ), openHelp === 1 ? 'request for help' : 'requests for help', openHelp ? 'is-alert' : '' ),
			tile( String( silent.length ), silent.length === 1 ? 'song nobody practiced' : 'songs nobody practiced', silent.length ? 'is-warn' : '' )
		] ) );

		/* getting ready for the next rehearsal */
		if ( W.current && D.next && D.next.songs && D.next.songs.length ) {
			var list = D.next.full ? D.songs.map( function ( t, i ) { return i; } ) : D.next.songs;
			var rows = h( 'div', { class: 'tcc-dir__ready' } );
			list.forEach( function ( i ) {
				var c = R.cells[ i ]; if ( ! c ) { return; }
				rows.appendChild( h( 'div', { class: 'tcc-dir__ready-row' }, [
					h( 'span', { class: 'tcc-dir__ready-t' }, [ h( 'b', {}, [ ( i + 1 ) + '. ' + D.songs[ i ] ] ), D.next.focus && D.next.focus.indexOf( i ) !== -1 ? h( 'span', { class: 'tcc-dir__star', title: 'Focus' }, [ '★ focus' ] ) : null ] ),
					h( 'div', { class: 'tcc-dir__ready-parts' }, SECT.map( function ( s ) { return h( 'div', { class: 'tcc-dir__ready-p' }, [ h( 'span', { class: 'tcc-dir__ready-k' }, [ s.label ] ), cell( c[ s.id ], s.size ) ] ); } ) )
				] ) );
			} );
			root.appendChild( sec( 'Getting ready for ' + ( D.next.date || 'the next rehearsal' ), D.next.full ? 'The whole work is planned.' : 'The songs planned for it, and how each section is doing on them this week.', [ rows ] ) );
		}

		/* help requests */
		var help = h( 'ul', { class: 'tcc-dir__help' } );
		R.help.forEach( function ( x ) {
			var box = h( 'input', { type: 'checkbox', checked: x.handled ? true : null, 'aria-label': 'Handled' } );
			box.addEventListener( 'change', function () {
				x.handled = box.checked; li.classList.toggle( 'is-done', x.handled );
				fetch( D.rest + 'helpdone', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': D.nonce }, body: JSON.stringify( { cantata: D.cantata.id, key: x.key, done: x.handled ? 1 : 0 } ) } );
				Object.keys( D.reports ).forEach( function ( k ) { D.reports[ k ].help.forEach( function ( y ) { if ( y.key === x.key ) { y.handled = x.handled; } } ); } );
			} );
			var li = h( 'li', { class: 'tcc-dir__help-i' + ( x.handled ? ' is-done' : '' ) }, [
				h( 'label', { class: 'tcc-dir__help-l' }, [ box,
					h( 'span', {}, [ h( 'b', {}, [ x.name ] ), ' · ' + ( x.song + 1 ) + '. ' + ( D.songs[ x.song ] || 'Song' ) + ' · ' + ( ( SECT.filter( function ( s ) { return s.id === x.part; } )[ 0 ] || {} ).label || x.part ) ] ),
					x.since ? h( 'span', { class: 'tcc-dir__help-when' }, [ 'since ' + x.since ] ) : null
				] )
			] );
			help.appendChild( li );
		} );
		root.appendChild( sec( 'Asking for help', R.help.length ? 'Singers who marked a song “I’d like help”. Tick one off once you’ve dealt with it; if they ask again, it comes back.' : null, [ R.help.length ? help : h( 'p', { class: 'tcc-dir__none' }, [ 'Nobody has asked for help.' ] ) ] ) );

		/* song by part */
		var grid = h( 'div', { class: 'tcc-dir__grid', role: 'table' } );
		grid.appendChild( h( 'div', { class: 'tcc-dir__grid-row tcc-dir__grid-head', role: 'row' }, [ h( 'span', { role: 'columnheader' }, [ 'Song' ] ) ].concat( SECT.map( function ( s ) { return h( 'span', { role: 'columnheader', title: s.size + ' signed up' }, [ h( 'b', { class: 'tcc-dir__long' }, [ s.label ] ), h( 'b', { class: 'tcc-dir__short' }, [ SHORT[ s.id ] ] ), h( 'small', {}, [ s.size + ' in section' ] ) ] ); } ) ) ) );
		D.songs.forEach( function ( title, i ) {
			var c = R.cells[ i ]; if ( ! c ) { return; }
			grid.appendChild( h( 'div', { class: 'tcc-dir__grid-row', role: 'row' }, [ h( 'span', { class: 'tcc-dir__grid-t', role: 'rowheader' }, [ h( 'b', {}, [ ( i + 1 ) + '. ' + title ] ), c.otherMins ? h( 'small', {}, [ mins( c.otherMins ) + ' with the accompaniment' ] ) : null ] ) ].concat( SECT.map( function ( s ) { return h( 'span', { role: 'cell' }, [ cell( c[ s.id ], s.size ) ] ); } ) ) ) );
		} );
		root.appendChild( sec( 'Every song, every section', 'How many in each section practiced the song (of those signed up for that part), and how they marked it: ', [
			h( 'p', { class: 'tcc-dir__legend' }, [ h( 'span', { class: 'tcc-dir__dot tcc-dir__dot--good' }, [ '3' ] ), ' feeling good ', h( 'span', { class: 'tcc-dir__dot tcc-dir__dot--some' }, [ '2' ] ), ' getting there ', h( 'span', { class: 'tcc-dir__dot tcc-dir__dot--help' }, [ '1' ] ), ' asked for help. Marks are as they stood at the end of the week.' ] ),
			h( 'div', { class: 'tcc-dir__scroll' }, [ grid ] )
		] ) );

		/* heat maps */
		/* one scale for every song this week, so a single rewind never looks like seventy loops */
		var heats = h( 'div', { class: 'tcc-dir__heats' } ), cold = [], light = [], top = 1;
		Object.keys( R.heat ).forEach( function ( k ) { top = Math.max( top, Math.max.apply( null, R.heat[ k ].bins ) ); } );
		D.songs.forEach( function ( title, i ) {
			var H = R.heat[ i ], n = H ? H.loop + H.back + H.seek : 0;
			if ( ! n ) { cold.push( ( i + 1 ) + '. ' + title ); return; }
			if ( n < 3 ) { light.push( ( i + 1 ) + '. ' + title ); return; }
			heats.appendChild( heatRow( i, title, H, top ) );
		} );
		root.appendChild( sec( 'Where singers go back', 'Every loop, 5-second rewind and backward drag of the slider adds heat at that spot. The brightest places are the passages people keep repeating.', [
			heats.children.length ? heats : h( 'p', { class: 'tcc-dir__none' }, [ light.length ? 'No song has been gone back to more than twice.' : 'No loops or rewinds yet.' ] ),
			light.length ? h( 'p', { class: 'tcc-dir__cold' }, [ 'Once or twice: ' + light.join( ', ' ) + '.' ] ) : null,
			cold.length && ( heats.children.length || light.length ) ? h( 'p', { class: 'tcc-dir__cold' }, [ 'Nobody went back in: ' + cold.join( ', ' ) + '.' ] ) : null
		] ) );

		/* section balance */
		var bal = h( 'div', { class: 'tcc-dir__balance' } );
		D.voices.forEach( function ( v ) {
			if ( ! v.names.length && v.id === 'unknown' ) { return; }
			bal.appendChild( h( 'details', { class: 'tcc-dir__voice' }, [ h( 'summary', {}, [ h( 'b', {}, [ String( v.names.length ) ] ), ' ' + v.label ] ), h( 'p', {}, [ v.names.join( ', ' ) || 'Nobody yet.' ] ) ] ) );
		} );
		root.appendChild( sec( 'Section balance', D.singers + ' singers signed up this season. Mezzos count toward Soprano and Alto above, Baritones toward Tenor and Bass.', [ bal ] ) );
	}

	function tile( big, small, cls ) {
		return h( 'div', { class: 'tcc-dir__tile' + ( cls ? ' ' + cls : '' ) }, [ h( 'b', {}, [ big ] ), h( 'span', {}, [ small ] ) ] );
	}

	function heatRow( i, title, H, top ) {
		var bins = H.bins, n = bins.length, max = top || 1, bin = D.bin;
		var len = Math.max( D.lengths[ i ] || 0, n * bin );
		var strip = h( 'div', { class: 'tcc-dir__strip', role: 'img' } );
		bins.forEach( function ( v, k ) {
			var a = v / max;
			strip.appendChild( h( 'i', { style: 'opacity:' + ( v ? ( 0.12 + 0.88 * Math.sqrt( a ) ).toFixed( 2 ) : 0 ), title: fmt( k * bin ) + '–' + fmt( ( k + 1 ) * bin ) + ' · ' + v } ) );
		} );
		/* the hottest stretch: the best 10-second window */
		var w = Math.max( 1, Math.round( 10 / bin ) ), best = 0, at = 0;
		for ( var k = 0; k + w <= n || ( k === 0 && n < w ); k++ ) {
			var sum = 0; for ( var j = k; j < Math.min( n, k + w ); j++ ) { sum += bins[ j ]; }
			if ( sum > best ) { best = sum; at = k; }
		}
		var t0 = at * bin, t1 = Math.min( len, ( at + w ) * bin ), p0 = pageAt( i, t0 + 1 );
		strip.setAttribute( 'aria-label', 'Most repeated around ' + fmt( t0 ) + '–' + fmt( t1 ) );
		/* page numbers under the strip, at each page turn */
		var ticks = h( 'div', { class: 'tcc-dir__ticks' } );
		if ( D.pages[ i ] ) {
			var turns = [ 0 ].concat( D.turns[ i ] || [] );
			var gap = 44 / Math.max( 200, root.clientWidth ), last = -1; // keep labels from running into each other on a phone
			turns.forEach( function ( t ) {
				if ( t > len ) { return; }
				var x = t / len, show = last < 0 || x - last >= gap;
				ticks.appendChild( h( 'span', { class: show ? '' : 'is-bare', style: 'left:' + ( x * 100 ).toFixed( 2 ) + '%' }, [ show ? 'p. ' + pageAt( i, t + 0.01 ) : '' ] ) );
				if ( show ) { last = x; }
			} );
		}
		var bits = [];
		if ( H.loop ) { bits.push( H.loop + ( H.loop === 1 ? ' loop' : ' loops' ) ); }
		if ( H.back ) { bits.push( H.back + ( H.back === 1 ? ' rewind' : ' rewinds' ) ); }
		if ( H.seek ) { bits.push( H.seek + ( H.seek === 1 ? ' drag back' : ' drags back' ) ); }
		return h( 'div', { class: 'tcc-dir__heat' }, [
			h( 'div', { class: 'tcc-dir__heat-top' }, [
				h( 'b', {}, [ ( i + 1 ) + '. ' + title ] ),
				h( 'span', {}, [ bits.join( ' · ' ) ] )
			] ),
			strip,
			ticks.children.length ? ticks : null,
			h( 'div', { class: 'tcc-dir__heat-axis' }, [ h( 'span', {}, [ '0:00' ] ), h( 'span', { class: 'tcc-dir__hot' }, [ 'Most repeated: ' + fmt( t0 ) + '–' + fmt( t1 ) + ( p0 ? ' · p. ' + p0 : '' ) ] ), h( 'span', {}, [ fmt( len ) ] ) ] )
		] );
	}

	render();
} )();
