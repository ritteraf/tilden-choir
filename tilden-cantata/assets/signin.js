/* Backstage sign-in: "Have you sung with us before?" → find my name / I'm new → confirm details.
   Used by the welcome card on the hub and by the practice player's "Who's practicing?". */
( function () {
	'use strict';
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
	var X = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
	var STAR = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 1 L13.4 10.6 L23 12 L13.4 13.4 L12 23 L10.6 13.4 L1 12 L10.6 10.6 Z"/></svg>';

	function api( cfg, path, method, body ) {
		var url = cfg.rest + path, headers = { 'Content-Type': 'application/json', 'X-TCC-Token': cfg.token };
		if ( method === 'GET' ) { url += '?' + Object.keys( body ).map( function ( k ) { return k + '=' + encodeURIComponent( body[ k ] ); } ).join( '&' ); }
		return fetch( url, { method: method, headers: headers, credentials: 'same-origin', body: method === 'GET' ? undefined : JSON.stringify( body ) } ).then( function ( r ) { return r.json().then( function ( j ) { if ( ! r.ok ) { throw new Error( ( j && j.message ) || 'Something went wrong' ); } return j; } ); } );
	}

	/* ---------- the panel ---------- */
	function open( cfg, done, start ) {
		var old = document.querySelector( '.tcc-si' ); if ( old ) { old.remove(); }
		var panel = h( 'div', { class: 'tcc-si', role: 'dialog', 'aria-modal': 'true', 'aria-label': 'Sign in backstage' } );
		var box = h( 'div', { class: 'tcc-si__box' } );
		panel.appendChild( box );
		document.body.appendChild( panel );
		document.body.classList.add( 'tcc-si-open' );
		function close( who ) { panel.remove(); document.body.classList.remove( 'tcc-si-open' ); if ( who && done ) { done( who ); } }
		function head( title, back ) {
			return h( 'div', { class: 'tcc-si__head' }, [
				back ? h( 'button', { type: 'button', class: 'tcc-si__link', onclick: back }, [ '← Back' ] ) : h( 'span', {} ),
				h( 'b', {}, [ title ] ),
				h( 'button', { type: 'button', class: 'tcc-si__x', 'aria-label': 'Close', html: X, onclick: function () { close( null ); } } )
			] );
		}
		function show() { box.innerHTML = ''; for ( var i = 0; i < arguments.length; i++ ) { if ( arguments[ i ] ) { box.appendChild( arguments[ i ] ); } } box.scrollTop = 0; }

		/* step 0 */
		function stepAsk() {
			var known = cfg.who && cfg.who.id ? cfg.who : null;
			show(
				head( 'Welcome backstage' ),
				h( 'p', { class: 'tcc-si__lede' }, [ known ? 'Welcome back, ' + known.name.split( ' ' )[ 0 ] + '. Singing with us for ' + cfg.cantata.title + '? Take a moment to check your details.' : 'This is where the practice tracks, schedule and documents live. So we know who’s with us this year — and the site can keep track of your practice — tell us who you are once on this device.' ] ),
				h( 'div', { class: 'tcc-si__choices' }, [
					known
						? h( 'button', { type: 'button', class: 'tcc-si__choice tcc-si__choice--primary', onclick: function () { stepForm( known.id ); } }, [ h( 'b', {}, [ 'Yes, that’s me' ] ), h( 'span', {}, [ 'Confirm my details for this year' ] ) ] )
						: h( 'button', { type: 'button', class: 'tcc-si__choice tcc-si__choice--primary', onclick: stepFind }, [ h( 'b', {}, [ 'I’ve sung with the choir before' ] ), h( 'span', {}, [ 'Find my name' ] ) ] ),
					h( 'button', { type: 'button', class: 'tcc-si__choice', onclick: function () { stepForm( 0 ); } }, [ h( 'b', {}, [ 'I’m new this year' ] ), h( 'span', {}, [ 'Sign me up' ] ) ] ),
					known ? h( 'button', { type: 'button', class: 'tcc-si__link', onclick: stepFind }, [ 'Not ' + known.name + '? Find my name' ] ) : null
				] ),
				h( 'p', { class: 'tcc-si__foot' }, [ h( 'button', { type: 'button', class: 'tcc-si__link', onclick: function () { try { sessionStorage.setItem( 'tcc_si_skip', '1' ); } catch ( e ) {} close( null ); } }, [ 'Not now' ] ) ] )
			);
		}

		/* someone already on this year's list just switches the device to them — no form to fill in again */
		function pick( p ) {
			if ( ! p.on ) { stepForm( p.id ); return; }
			api( cfg, 'who', 'POST', { cantata: cfg.cantata.id, person: p.id } ).then( function ( r ) { close( { id: r.id, name: r.name, onSeason: true } ); } ).catch( function () { stepForm( p.id ); } );
		}

		/* step 1: find my name */
		function stepFind( title ) {
			var search = h( 'input', { type: 'search', class: 'tcc-si__search', placeholder: 'Start typing your name…', autocomplete: 'off' } );
			var list = h( 'div', { class: 'tcc-si__names' }, [ h( 'p', { class: 'tcc-si__muted' }, [ 'Loading…' ] ) ] );
			show( head( typeof title === 'string' ? title : 'Find your name', stepAsk ), search, list, h( 'p', { class: 'tcc-si__foot' }, [ 'Can’t find yourself? ', h( 'button', { type: 'button', class: 'tcc-si__link', onclick: function () { stepForm( 0 ); } }, [ 'Sign up as new' ] ) ] ) );
			search.focus();
			var groups = null;
			function paint() {
				list.innerHTML = '';
				var q = search.value.trim().toLowerCase(), any = false;
				var order = [ 'Soprano', 'Mezzo', 'Alto', 'Tenor', 'Baritone', 'Bass' ];
				Object.keys( groups ).sort( function ( a, b ) { var x = order.indexOf( a ), y = order.indexOf( b ); return ( x === -1 ? 99 : x ) - ( y === -1 ? 99 : y ); } ).forEach( function ( g ) {
					var people = groups[ g ].filter( function ( p ) { return ! q || p.name.toLowerCase().indexOf( q ) !== -1; } );
					if ( ! people.length ) { return; }
					any = true;
					var grp = h( 'div', { class: 'tcc-si__group' }, [ h( 'span', { class: 'tcc-si__eyebrow' }, [ g ] ) ] );
					var grid = h( 'div', { class: 'tcc-si__grid' } );
					people.forEach( function ( p ) { grid.appendChild( h( 'button', { type: 'button', class: 'tcc-si__name' + ( p.on ? ' is-on' : '' ), onclick: function () { pick( p ); } }, [ p.name, p.on ? h( 'small', {}, [ 'on this year’s list' ] ) : null ] ) ); } );
					grp.appendChild( grid ); list.appendChild( grp );
				} );
				if ( ! any ) { list.appendChild( h( 'p', { class: 'tcc-si__muted' }, [ 'Nobody by that name yet — use “Sign up as new” below.' ] ) ); }
			}
			api( cfg, 'people', 'GET', { cantata: cfg.cantata.id } ).then( function ( r ) { groups = r.groups || {}; paint(); } ).catch( function () { list.innerHTML = ''; list.appendChild( h( 'p', { class: 'tcc-si__muted' }, [ 'Couldn’t load the list. Try again in a moment.' ] ) ); } );
			search.addEventListener( 'input', function () { if ( groups ) { paint(); } } );
		}

		/* step 2: the form */
		function stepForm( personId, prefill ) {
			var isNew = ! personId;
			var hints = prefill || null;
			if ( ! isNew && ! hints ) {
				show( head( 'One moment…', stepAsk ), h( 'p', { class: 'tcc-si__muted' }, [ 'Loading your details…' ] ) );
				api( cfg, 'hints', 'GET', { cantata: cfg.cantata.id, person: personId } ).then( function ( r ) { stepForm( personId, r ); } ).catch( function () { stepForm( 0 ); } );
				return;
			}
			hints = hints || { types: [ 'singers' ], part: '', solos: false, role: '', public: true, phone: '', email: '' };
			var f = {};
			f.name = h( 'input', { type: 'text', class: 'tcc-si__input', value: hints.name || '', placeholder: 'First and last name', autocomplete: 'name', readonly: ! isNew } );
			var types = h( 'div', { class: 'tcc-si__checks' } );
			cfg.types.forEach( function ( t ) { types.appendChild( h( 'label', { class: 'tcc-si__check' }, [ h( 'input', { type: 'checkbox', name: 'types', value: t.id, checked: ( hints.types || [] ).indexOf( t.id ) !== -1 }), ' ' + t.label ] ) ); } );
			f.part = h( 'select', { class: 'tcc-si__input' }, [ h( 'option', { value: 'unsure' }, [ 'Not sure yet — we’ll help you find it' ] ) ].concat( cfg.parts.map( function ( p ) { return h( 'option', { value: p.id, selected: hints.part === p.id }, [ p.label ] ); } ) ) );
			f.solos = h( 'input', { type: 'checkbox', checked: !! hints.solos } );
			// Volunteer team: returning members only. New people are signing up to sing; the teams get explained at rehearsal.
			f.role = h( 'div', { class: 'tcc-si__roles' } );
			[ { id: 'none', label: 'I’ll decide later', desc: '' } ].concat( cfg.roles ).forEach( function ( r ) {
				f.role.appendChild( h( 'label', { class: 'tcc-si__role' }, [ h( 'input', { type: 'radio', name: 'tcc_role', value: r.id, checked: r.id === ( hints.role || 'none' ) } ), h( 'span', {}, [ h( 'b', {}, [ r.label ] ), r.desc ? h( 'small', {}, [ r.desc ] ) : null ] ) ] ) );
			} );
			f.phone = h( 'input', { type: 'tel', class: 'tcc-si__input', autocomplete: 'tel', required: ! hints.phone, placeholder: hints.phone ? 'Leave blank to keep' : '(402) 555-0100' } );
			f.email = h( 'input', { type: 'email', class: 'tcc-si__input', autocomplete: 'email', required: ! hints.email, placeholder: hints.email ? 'Leave blank to keep' : 'you@example.com' } );
			f.public = h( 'input', { type: 'checkbox', checked: hints.public !== false } );
			var partRow = h( 'label', { class: 'tcc-si__field' }, [ h( 'span', {}, [ 'Voice part' ] ), f.part ] );
			var soloRow = h( 'label', { class: 'tcc-si__check tcc-si__check--row' }, [ f.solos, ' I’d be interested in singing a solo or in a small group' ] );
			function syncSinger() { var singing = !! types.querySelector( 'input[value="singers"]:checked' ); partRow.hidden = ! singing; soloRow.hidden = ! singing; }
			types.addEventListener( 'change', syncSinger );
			var err = h( 'p', { class: 'tcc-si__error', hidden: true } );
			var save = h( 'button', { type: 'button', class: 'tcc-si__btn', onclick: submit }, [ isNew ? 'Sign me up' : 'That’s right — save' ] );
			show(
				head( isNew ? 'Tell us about you' : 'Is this still right?', isNew ? stepAsk : stepFind ),
				isNew ? null : h( 'p', { class: 'tcc-si__lede' }, [ ( hints.phone || hints.email ) ? 'We’ve got this from last time. Change anything that’s different; your phone and email aren’t shown here, so only type them if they’ve changed.' : 'We’ve got this from last time. Change anything that’s different — and we don’t have a phone or email for you yet, so please add those.' ] ),
				h( 'div', { class: 'tcc-si__form' }, [
					h( 'label', { class: 'tcc-si__field' }, [ h( 'span', {}, [ 'Your name' ] ), f.name ] ),
					h( 'div', { class: 'tcc-si__field' }, [ h( 'span', {}, [ 'This year I’ll be…' ] ), types ] ),
					partRow, soloRow,
					isNew ? null : h( 'div', { class: 'tcc-si__field' }, [ h( 'span', {}, [ 'Your volunteer team' ] ), h( 'small', { class: 'tcc-si__field-intro' }, [ 'Alongside singing, everyone takes on one small job for the season. Which would you enjoy?' ] ), f.role ] ),
					h( 'p', { class: 'tcc-si__note' }, [ 'Phone and email are how the director sends updates during the season, so we need both.' ] ),
					h( 'div', { class: 'tcc-si__row2' }, [
						h( 'label', { class: 'tcc-si__field' }, [ h( 'span', {}, [ 'Phone' + ( hints.phone ? '' : ' — required' ) ] ), f.phone, hints.phone ? h( 'small', {}, [ 'On file: ' + hints.phone ] ) : null ] ),
						h( 'label', { class: 'tcc-si__field' }, [ h( 'span', {}, [ 'Email' + ( hints.email ? '' : ' — required' ) ] ), f.email, hints.email ? h( 'small', {}, [ 'On file: ' + hints.email ] ) : null ] )
					] ),
					h( 'label', { class: 'tcc-si__check tcc-si__check--row' }, [ f.public, ' It’s fine to list my name on the public cast list' ] ),
					err,
					h( 'div', { class: 'tcc-si__actions' }, [ save, h( 'button', { type: 'button', class: 'tcc-si__link', onclick: function () { close( null ); } }, [ 'Cancel' ] ) ] )
				] )
			);
			syncSinger();
			if ( isNew ) { f.name.focus(); }
			function submit( force ) {
				err.hidden = true;
				var body = {
					cantata: cfg.cantata.id, person: personId || 0, name: f.name.value.trim(),
					types: Array.prototype.map.call( types.querySelectorAll( 'input:checked' ), function ( c ) { return c.value; } ),
					part: partRow.hidden ? '' : f.part.value, solos: ! soloRow.hidden && f.solos.checked, role: isNew ? '' : ( ( f.role.querySelector( 'input:checked' ) || {} ).value || 'none' ),
					phone: f.phone.value.trim(), email: f.email.value.trim(), public: f.public.checked, force: force === true
				};
				if ( isNew && ( body.name.length < 3 || body.name.indexOf( ' ' ) === -1 ) ) { err.textContent = 'Please give your first and last name.'; err.hidden = false; f.name.focus(); return; }
				if ( ! body.types.length ) { err.textContent = 'Tick at least one — singing, narrating, acting or tech.'; err.hidden = false; return; }
				if ( ! hints.phone && ! body.phone ) { err.textContent = 'Please add a phone number — it’s how the director sends updates.'; err.hidden = false; f.phone.focus(); return; }
				if ( ! hints.email && ! body.email ) { err.textContent = 'Please add an email address — it’s how the director sends updates.'; err.hidden = false; f.email.focus(); return; }
				save.disabled = true; save.textContent = 'Saving…';
				api( cfg, 'signup', 'POST', body ).then( function ( r ) {
					if ( r && r.duplicate ) {
						save.disabled = false; save.textContent = 'Sign me up';
						stepDuplicate( r.duplicate, body );
						return;
					}
					stepDone( r );
				} ).catch( function ( e ) { save.disabled = false; save.textContent = isNew ? 'Sign me up' : 'That’s right — save'; err.textContent = e.message; err.hidden = false; } );
			}
			function stepDuplicate( dups, body ) {
				show(
					head( 'Is this you?', function () { stepForm( 0 ); } ),
					h( 'p', { class: 'tcc-si__lede' }, [ 'We already have someone by a similar name. If that’s you, pick it and we’ll just confirm your details.' ] ),
					h( 'div', { class: 'tcc-si__grid' }, dups.map( function ( d ) { return h( 'button', { type: 'button', class: 'tcc-si__name', onclick: function () { stepForm( d.id ); } }, [ d.name ] ); } ) ),
					h( 'p', { class: 'tcc-si__foot' }, [ h( 'button', { type: 'button', class: 'tcc-si__btn tcc-si__btn--quiet', onclick: function () { body.force = true; api( cfg, 'signup', 'POST', body ).then( stepDone ).catch( function ( e ) { alert( e.message ); } ); } }, [ 'No, I’m a different ' + body.name.split( ' ' )[ 0 ] + ' — sign me up' ] ) ] )
				);
			}
		}

		/* step 3 */
		function stepDone( r ) {
			var first = ( r.name || '' ).split( ' ' )[ 0 ];
			show(
				head( 'You’re on the list' ),
				h( 'div', { class: 'tcc-si__done' }, [ h( 'span', { class: 'tcc-si__star', html: STAR } ), h( 'p', {}, [ ( r.new ? 'Welcome, ' : 'Thanks, ' ) + first + '. You’re signed up for ' + cfg.cantata.title + '. This device will remember you, and your practice will be kept track of from here on.' ] ) ] ),
				h( 'div', { class: 'tcc-si__actions' }, [ h( 'button', { type: 'button', class: 'tcc-si__btn', onclick: function () { close( { id: r.id, name: r.name, onSeason: true } ); } }, [ 'Carry on' ] ) ] )
			);
		}

		if ( start === 'details' && cfg.who ) { stepForm( cfg.who.id ); } else if ( start === 'switch' ) { stepFind( 'Who’s using this device?' ); } else if ( start === 'find' ) { stepFind(); } else if ( start === 'new' ) { stepForm( 0 ); } else { stepAsk(); }
		panel.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Escape' ) { close( null ); } } );
	}

	/* ---------- the welcome card on the hub ---------- */
	function card( el ) {
		var cfg = JSON.parse( el.getAttribute( 'data-tcc-signin' ) );
		el.removeAttribute( 'data-tcc-signin' );
		function paint() {
			el.innerHTML = '';
			var who = cfg.who;
			var skipped = false; try { skipped = !! sessionStorage.getItem( 'tcc_si_skip' ); } catch ( e ) {}
			if ( who && who.onSeason ) {
				el.appendChild( h( 'div', { class: 'tcc-si__line' }, [ 'Signed in as ', h( 'b', {}, [ who.name ] ), ' for ' + cfg.cantata.title + '. ', h( 'button', { type: 'button', class: 'tcc-si__link', onclick: launch }, [ 'Not you, or details changed?' ] ) ] ) );
				return;
			}
			if ( skipped ) {
				el.appendChild( h( 'div', { class: 'tcc-si__line tcc-si__line--muted' }, [ h( 'button', { type: 'button', class: 'tcc-si__link', onclick: launch }, [ 'Sign in for this year’s cantata' ] ) ] ) );
				return;
			}
			el.appendChild( h( 'div', { class: 'tcc-si__card' }, [
				h( 'div', { class: 'tcc-si__card-text' }, [
					h( 'span', { class: 'tcc-si__eyebrow' }, [ cfg.cantata.year + ' · ' + cfg.cantata.title ] ),
					h( 'b', {}, [ who ? 'Welcome back, ' + who.name.split( ' ' )[ 0 ] + '!' : 'Welcome backstage.' ] ),
					h( 'p', {}, [ who ? 'Singing with us this year? Take a moment to confirm your details — it replaces the paper sign-up sheet.' : 'Tell us who you are once on this device. It replaces the paper sign-up sheet, and the practice tracks can keep track of what you’ve worked on.' ] )
				] ),
				h( 'div', { class: 'tcc-si__card-actions' }, [
					h( 'button', { type: 'button', class: 'tcc-si__btn', onclick: launch }, [ who ? 'Yes, I’m in' : 'Sign in' ] ),
					h( 'button', { type: 'button', class: 'tcc-si__link', onclick: function () { try { sessionStorage.setItem( 'tcc_si_skip', '1' ); } catch ( e ) {} paint(); } }, [ 'Not now' ] )
				] )
			] ) );
		}
		function launch() { open( cfg, function ( who ) { cfg.who = who; paint(); } ); }
		paint();
	}

	window.tccSignin = { open: open };
	document.querySelectorAll( '[data-tcc-signin]' ).forEach( card );
} )();
