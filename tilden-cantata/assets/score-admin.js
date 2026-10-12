/* Cantata post → Score box: upload the PDF, say where each song starts, check with thumbnails. */
( function () {
	'use strict';
	var box = document.getElementById( 'tcc-score-box' );
	if ( ! box ) { return; }
	var D = JSON.parse( box.getAttribute( 'data-tcc-score' ) );
	var map = {};
	Object.keys( D.map || {} ).forEach( function ( k ) { map[ k ] = D.map[ k ].slice(); } );
	var pdf = null, thumbs = {};

	function h( tag, attrs, kids ) {
		var el = document.createElement( tag );
		Object.keys( attrs || {} ).forEach( function ( k ) {
			var v = attrs[ k ]; if ( v === null || v === undefined || v === false ) { return; }
			if ( k === 'class' ) { el.className = v; } else if ( k === 'html' ) { el.innerHTML = v; } else if ( k.indexOf( 'on' ) === 0 ) { el.addEventListener( k.slice( 2 ), v ); } else { el.setAttribute( k, v === true ? '' : v ); }
		} );
		( kids || [] ).forEach( function ( c ) { if ( c !== null && c !== undefined && c !== false ) { el.appendChild( typeof c === 'string' ? document.createTextNode( c ) : c ); } } );
		return el;
	}
	function size( b ) { return b > 1048576 ? ( b / 1048576 ).toFixed( 1 ) + ' MB' : Math.round( b / 1024 ) + ' KB'; }
	function post( action, extra, file, onProgress ) {
		return new Promise( function ( resolve, reject ) {
			var fd = new FormData();
			fd.append( 'action', action ); fd.append( 'post', D.post ); fd.append( 'nonce', D.nonce );
			Object.keys( extra || {} ).forEach( function ( k ) { fd.append( k, extra[ k ] ); } );
			if ( file ) { fd.append( 'file', file ); }
			var x = new XMLHttpRequest();
			x.open( 'POST', D.ajax );
			if ( onProgress ) { x.upload.onprogress = function ( e ) { if ( e.lengthComputable ) { onProgress( e.loaded / e.total ); } }; }
			x.onload = function () { try { var r = JSON.parse( x.responseText ); if ( r.success ) { resolve( r.data ); } else { reject( new Error( r.data || 'Failed' ) ); } } catch ( e ) { reject( new Error( 'The server said: ' + x.status ) ); } };
			x.onerror = function () { reject( new Error( 'Network error' ) ); };
			x.send( fd );
		} );
	}
	function lib() {
		if ( window.pdfjsLib ) { return Promise.resolve( window.pdfjsLib ); }
		return new Promise( function ( res, rej ) { var s = document.createElement( 'script' ); s.src = D.lib; s.onload = function () { window.pdfjsLib.GlobalWorkerOptions.workerSrc = D.worker; res( window.pdfjsLib ); }; s.onerror = rej; document.head.appendChild( s ); } );
	}

	var off = function () { return D.score ? D.score.offset : 0; }; // printed = pdf page + offset
	function printed( p ) { return p + off(); }
	function pdfPage( n ) { return n - off(); }

	function render() {
		box.innerHTML = '';
		if ( ! D.score ) { box.appendChild( uploader( false ) ); return; }
		var S = D.score;
		var pagesIn = h( 'input', { type: 'number', min: '1', value: S.pages, style: 'width:70px' } );
		var offIn = h( 'input', { type: 'number', value: S.offset + 1, style: 'width:70px' } );
		var status = h( 'span', { class: 'description', style: 'margin-left:10px' } );
		offIn.addEventListener( 'input', function () { S.offset = ( parseInt( offIn.value, 10 ) || 1 ) - 1; paintTable(); } );
		pagesIn.addEventListener( 'input', function () { S.pages = Math.max( 1, parseInt( pagesIn.value, 10 ) || 1 ); } );
		box.appendChild( h( 'div', { style: 'display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:10px 12px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px' }, [
			h( 'span', { class: 'dashicons dashicons-media-document', style: 'color:#9c2b1a' } ),
			h( 'b', {}, [ S.name ] ), h( 'span', { class: 'description' }, [ size( S.size ) ] ),
			h( 'label', {}, [ 'Pages ', pagesIn ] ),
			h( 'label', { title: 'If the first page of the PDF is the cover and the music starts on printed page 2, put the cover\'s number here so page numbers match the book.' }, [ 'Printed number of the first PDF page ', offIn ] ),
			h( 'a', { href: S.url, target: '_blank', rel: 'noopener', class: 'button' }, [ 'Open' ] ),
			uploader( true ),
			h( 'button', { type: 'button', class: 'button-link button-link-delete', onclick: function () { if ( ! confirm( 'Remove the score file? Where songs start and the page turns are kept for a replacement scan.' ) ) { return; } post( 'tcc_score_remove' ).then( function () { D.score = null; pdf = null; render(); } ); } }, [ 'Remove' ] )
		] ) );
		var table = h( 'table', { class: 'widefat striped', style: 'margin-top:12px;max-width:820px' } );
		box.appendChild( table );
		box.appendChild( h( 'p', {}, [
			h( 'button', { type: 'button', class: 'button button-primary', onclick: save }, [ 'Save score settings' ] ), ' ',
			h( 'button', { type: 'button', class: 'button', onclick: fillLast }, [ 'Fill in last pages' ] ), status
		] ) );
		function paintTable() {
			table.innerHTML = '';
			table.appendChild( h( 'thead', {}, [ h( 'tr', {}, [ h( 'th', { style: 'width:40%' }, [ 'Song' ] ), h( 'th', {}, [ 'First page' ] ), h( 'th', {}, [ 'Last page' ] ), h( 'th', {}, [ 'Starts on' ] ), h( 'th', {}, [ 'Page turns' ] ) ] ) ] ) );
			var tb = h( 'tbody' );
			if ( ! D.songs.length ) { tb.appendChild( h( 'tr', {}, [ h( 'td', { colspan: '5' }, [ 'Add the song list to this cantata first.' ] ) ] ) ); }
			D.songs.forEach( function ( title, i ) {
				var r = map[ i ] || [ 0, 0 ];
				var a = h( 'input', { type: 'number', min: '1', value: r[ 0 ] ? printed( r[ 0 ] ) : '', style: 'width:70px', placeholder: 'p.' } );
				var b = h( 'input', { type: 'number', min: '1', value: r[ 1 ] ? printed( r[ 1 ] ) : '', style: 'width:70px', placeholder: 'p.' } );
				var th = h( 'span', { class: 'tcc-thumb', style: 'display:inline-block;width:56px;height:72px;background:#f0f0f1;border:1px solid #dcdcde;vertical-align:middle' } );
				function upd() {
					var x = parseInt( a.value, 10 ), y = parseInt( b.value, 10 );
					if ( x ) { map[ i ] = [ pdfPage( x ), y ? pdfPage( y ) : pdfPage( x ) ]; } else { delete map[ i ]; }
					thumb( th, map[ i ] ? map[ i ][ 0 ] : 0 );
				}
				a.addEventListener( 'change', upd ); b.addEventListener( 'change', upd );
				tb.appendChild( h( 'tr', {}, [ h( 'td', {}, [ ( i + 1 ) + '. ' + title ] ), h( 'td', {}, [ a ] ), h( 'td', {}, [ b ] ), h( 'td', {}, [ th ] ), h( 'td', {}, [ D.turns[ i ] ? D.turns[ i ] + ' set' : '—' ] ) ] ) );
				thumb( th, r[ 0 ] );
			} );
			table.appendChild( tb );
		}
		function fillLast() {
			var starts = Object.keys( map ).map( Number ).sort( function ( x, y ) { return map[ x ][ 0 ] - map[ y ][ 0 ]; } );
			starts.forEach( function ( song, k ) {
				var next = starts[ k + 1 ];
				map[ song ][ 1 ] = next !== undefined ? Math.max( map[ song ][ 0 ], map[ next ][ 0 ] ) : S.pages; // a song usually ends on the page the next one starts
			} );
			paintTable(); status.textContent = 'Filled in — check, then save.';
		}
		function save() {
			status.textContent = 'Saving…';
			post( 'tcc_score_save', { pages: S.pages, offset: S.offset, map: JSON.stringify( map ) } ).then( function ( r ) { map = {}; Object.keys( r.map ).forEach( function ( k ) { map[ k ] = r.map[ k ]; } ); paintTable(); status.textContent = 'Saved.'; } ).catch( function ( e ) { status.textContent = e.message; } );
		}
		paintTable();
		// open the PDF to count pages properly and draw the thumbnails
		lib().then( function ( L ) { return L.getDocument( { url: S.url, disableRange: true } ).promise; } ).then( function ( doc ) {
			pdf = doc;
			if ( doc.numPages !== S.pages ) { S.pages = doc.numPages; pagesIn.value = doc.numPages; status.textContent = 'Counted ' + doc.numPages + ' pages — save to keep that.'; }
			Object.keys( thumbs ).forEach( function ( k ) { thumbs[ k ].forEach( function ( fn ) { fn(); } ); } );
			thumbs = {};
			paintTable();
		} ).catch( function () { status.textContent = 'Couldn’t open the PDF here to check it.'; } );
	}
	function thumb( el, p ) {
		el.innerHTML = '';
		if ( ! p ) { return; }
		if ( ! pdf ) { ( thumbs[ p ] = thumbs[ p ] || [] ).push( function () {} ); return; }
		if ( p > pdf.numPages ) { el.textContent = '?'; return; }
		pdf.getPage( p ).then( function ( page ) {
			var vp = page.getViewport( { scale: 1 } ), sc = 56 / vp.width * ( window.devicePixelRatio || 1 );
			var v = page.getViewport( { scale: sc } );
			var c = document.createElement( 'canvas' ); c.width = v.width; c.height = v.height; c.style.width = '56px'; c.style.height = 'auto'; c.style.display = 'block';
			el.style.height = 'auto';
			page.render( { canvasContext: c.getContext( '2d' ), viewport: v } ).promise.then( function () { el.innerHTML = ''; el.appendChild( c ); } );
		} );
	}
	function uploader( replace ) {
		var input = h( 'input', { type: 'file', accept: 'application/pdf,.pdf', style: 'display:none' } );
		var bar = h( 'span', { class: 'description' } );
		var btn = h( 'button', { type: 'button', class: replace ? 'button' : 'button button-primary', onclick: function () { input.click(); } }, [ replace ? 'Replace…' : 'Upload the score PDF…' ] );
		input.addEventListener( 'change', function () {
			var f = input.files[ 0 ]; if ( ! f ) { return; }
			if ( f.size > D.max ) { bar.textContent = 'That file is ' + size( f.size ) + '; this server takes up to ' + size( D.max ) + '. Try scanning at 200 dpi in greyscale.'; return; }
			btn.disabled = true;
			post( 'tcc_score_upload', {}, f, function ( p ) { bar.textContent = 'Uploading… ' + Math.round( p * 100 ) + '%'; } ).then( function ( s ) { D.score = s; pdf = null; render(); } ).catch( function ( e ) { bar.textContent = e.message; btn.disabled = false; } );
		} );
		return h( 'span', {}, [ input, btn, ' ', bar, replace ? null : h( 'p', { class: 'description' }, [ 'The whole work as one PDF, in page order, as your singers\' copies are printed.' ] ) ] );
	}
	render();
} )();
