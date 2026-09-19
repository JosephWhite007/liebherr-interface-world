/**
 * Liebherr Emergency – Hilfe-Koffer Overlay.
 *
 * Klick auf den winzigen Koffer-Punkt öffnet IMMER zuerst das Overlay mit der einstelligen Rechenaufgabe.
 * Erst nach richtiger Antwort zeigt das Overlay die Emergency-Area (kontextbezogener Hilfe-Hub, vom Server).
 * Die Lösung wird nie an den Client gesendet; verifiziert wird ausschließlich serverseitig.
 */
( function () {
	'use strict';

	var CFG = window.liwEmg || null;
	if ( ! CFG ) { return; }
	var I18N = CFG.i18n || {};
	var overlay = null;
	var lastFocus = null;

	function el( tag, cls, html ) {
		var n = document.createElement( tag );
		if ( cls ) { n.className = cls; }
		if ( html != null ) { n.innerHTML = html; }
		return n;
	}

	function esc( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function close() {
		if ( overlay ) {
			overlay.parentNode && overlay.parentNode.removeChild( overlay );
			overlay = null;
			document.removeEventListener( 'keydown', onKey );
		}
		if ( lastFocus && lastFocus.focus ) { try { lastFocus.focus(); } catch ( e ) {} }
	}

	function onKey( e ) {
		if ( e.key === 'Escape' ) { close(); }
	}

	function build() {
		close();
		overlay = el( 'div', 'liw-emg-overlay' );
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-modal', 'true' );
		overlay.setAttribute( 'aria-label', esc( I18N.title || 'Emergency' ) );
		var box = el( 'div', 'liw-emg-modal' );
		var btnClose = el( 'button', 'liw-emg-modal__close' );
		btnClose.type = 'button';
		btnClose.setAttribute( 'aria-label', esc( I18N.close || 'Close' ) );
		btnClose.innerHTML = '&times;';
		btnClose.addEventListener( 'click', close );
		var body = el( 'div', 'liw-emg-modal__body' );
		box.appendChild( btnClose );
		box.appendChild( body );
		overlay.appendChild( box );
		overlay.addEventListener( 'click', function ( e ) { if ( e.target === overlay ) { close(); } } );
		document.body.appendChild( overlay );
		document.addEventListener( 'keydown', onKey );
		return body;
	}

	function api( path, opts ) {
		opts = opts || {};
		opts.headers = opts.headers || {};
		opts.headers[ 'X-WP-Nonce' ] = CFG.nonce;
		if ( opts.body ) { opts.headers[ 'Content-Type' ] = 'application/json'; }
		return fetch( CFG.rest + path, opts ).then( function ( r ) { return r.json(); } );
	}

	function showLoading( body ) {
		body.innerHTML = '<p class="liw-emg-loading">' + esc( I18N.loading || 'Loading…' ) + '</p>';
	}

	function showChallenge( body, note ) {
		showLoading( body );
		api( 'challenge', { method: 'GET' } ).then( function ( c ) {
			if ( ! c || ! c.token ) { body.innerHTML = '<p>' + esc( I18N.error ) + '</p>'; return; }
			body.innerHTML = '';
			body.appendChild( el( 'h2', 'liw-emg-modal__title', esc( I18N.title || 'Emergency' ) ) );
			if ( note ) { body.appendChild( el( 'p', 'liw-emg-note', esc( note ) ) ); }
			body.appendChild( el( 'p', 'liw-emg-ask', esc( I18N.ask ) ) );
			var q = el( 'p', 'liw-emg-q', esc( c.question ) );
			body.appendChild( q );
			var form = el( 'form', 'liw-emg-form' );
			var lab = el( 'label', 'liw-emg-form__lab' );
			lab.setAttribute( 'for', 'liw-emg-answer' );
			lab.textContent = I18N.answer || 'Result';
			var inp = el( 'input', 'liw-emg-form__inp' );
			inp.type = 'number';
			inp.id = 'liw-emg-answer';
			inp.inputMode = 'numeric';
			inp.autocomplete = 'off';
			inp.required = true;
			var go = el( 'button', 'liw-emg-form__go' );
			go.type = 'submit';
			go.textContent = I18N.enter || 'Enter';
			form.appendChild( lab );
			form.appendChild( inp );
			form.appendChild( go );
			body.appendChild( form );
			inp.focus();
			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				var ans = parseInt( inp.value, 10 );
				if ( isNaN( ans ) ) { inp.focus(); return; }
				go.disabled = true;
				verify( body, c.token, ans );
			} );
		} ).catch( function () { body.innerHTML = '<p>' + esc( I18N.error ) + '</p>'; } );
	}

	function verify( body, token, answer ) {
		api( 'verify', {
			method: 'POST',
			body: JSON.stringify( {
				token: token,
				answer: answer,
				path: location.pathname,
				admin: !! CFG.isAdmin,
				page: CFG.adminPage || ''
			} )
		} ).then( function ( r ) {
			if ( r && r.ok ) {
				body.innerHTML = r.hub || '';
				var h = body.querySelector( 'h2' );
				if ( h ) { h.setAttribute( 'tabindex', '-1' ); h.focus(); }
				return;
			}
			var reason = r && r.reason;
			var note = reason === 'expired' ? I18N.expired : ( reason === 'wrong' ? I18N.wrong : I18N.error );
			showChallenge( body, note );
		} ).catch( function () { showChallenge( body, I18N.error ); } );
	}

	function openAssistant( trigger ) {
		lastFocus = trigger || document.activeElement;
		var body = build();
		showChallenge( body, '' );
	}

	document.addEventListener( 'click', function ( e ) {
		var t = e.target.closest ? e.target.closest( '[data-liw-emg]' ) : null;
		if ( t ) { e.preventDefault(); openAssistant( t ); }
	} );
}() );
