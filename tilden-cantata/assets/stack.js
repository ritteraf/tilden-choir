( function () {
	var stack = document.querySelector( '[data-tcc-stack]' );

	function open( year, scroll ) {
		if ( ! stack ) return;
		var target = stack.querySelector( '.tcc-entry[data-tcc-year="' + year + '"]' );
		if ( ! target ) return false;
		stack.querySelectorAll( '.tcc-entry.is-open' ).forEach( function ( a ) { a.classList.remove( 'is-open' ); } );
		target.classList.add( 'is-open' );
		if ( scroll ) {
			var top = target.getBoundingClientRect().top + window.pageYOffset - 12;
			window.scrollTo( { top: top, behavior: 'smooth' } );
		}
		return true;
	}

	// Expand in place instead of leaving the page.
	document.addEventListener( 'click', function ( e ) {
		var a = e.target.closest( '[data-tcc-open]' );
		if ( a && stack ) {
			if ( open( a.getAttribute( 'data-tcc-open' ), true ) ) {
				e.preventDefault();
				history.replaceState( null, '', '#' + a.getAttribute( 'data-tcc-open' ) );
			}
			return;
		}
		// Open the recording in a lightbox over the page.
		var p = e.target.closest( '[data-tcc-play]' );
		if ( p ) {
			e.preventDefault();
			openPlayer( p.getAttribute( 'data-tcc-play' ), p.getAttribute( 'aria-label' ) || p.textContent.trim(), p );
		}
	} );

	// Inline player (singers page): swap the thumbnail for the video in place.
	document.addEventListener( 'click', function ( e ) {
		var f = e.target.closest( '[data-tcc-inline]' );
		if ( ! f || f.classList.contains( 'is-playing' ) ) return;
		var btn = e.target.closest( '.tcc-listen__play' );
		if ( ! btn ) return;
		e.preventDefault();
		var id = f.getAttribute( 'data-tcc-inline' ), title = f.getAttribute( 'data-tcc-title' ) || 'Recording';
		btn.outerHTML = '<iframe class="tcc-listen__frame" src="https://www.youtube-nocookie.com/embed/' + encodeURIComponent( id ) + '?autoplay=1&rel=0" title="' + title.replace( /"/g, '' ) + '" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>';
		f.classList.add( 'is-playing' );
	} );

	var modal = null, lastFocus = null;
	function openPlayer( id, title, opener ) {
		closePlayer();
		lastFocus = opener;
		modal = document.createElement( 'div' );
		modal.className = 'tcc-modal';
		modal.setAttribute( 'role', 'dialog' );
		modal.setAttribute( 'aria-modal', 'true' );
		modal.setAttribute( 'aria-label', title || 'Recording' );
		modal.innerHTML = '<div class="tcc-modal__box">'
			+ '<button type="button" class="tcc-modal__close" aria-label="Close">'
			+ '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>'
			+ '<iframe class="tcc-modal__frame" src="https://www.youtube-nocookie.com/embed/' + encodeURIComponent( id ) + '?autoplay=1&rel=0" title="' + ( title || 'Recording' ).replace( /"/g, '' ) + '" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>'
			+ '</div>';
		document.body.appendChild( modal );
		document.body.classList.add( 'tcc-modal-open' );
		modal.querySelector( '.tcc-modal__close' ).focus();
		modal.addEventListener( 'click', function ( ev ) {
			if ( ev.target === modal || ev.target.closest( '.tcc-modal__close' ) ) closePlayer();
		} );
	}
	function closePlayer() {
		if ( ! modal ) return;
		modal.remove();
		modal = null;
		document.body.classList.remove( 'tcc-modal-open' );
		if ( lastFocus && lastFocus.focus ) lastFocus.focus();
	}
	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key === 'Escape' && modal ) closePlayer();
	} );

	// Deep links: /#2024 opens that year.
	function fromHash() {
		var h = ( location.hash || '' ).replace( '#', '' );
		if ( /^\d{4}$/.test( h ) ) open( h, false );
	}
	fromHash();
	window.addEventListener( 'hashchange', fromHash );
} )();
