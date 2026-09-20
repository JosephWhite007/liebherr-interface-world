/**
 * Liebherr World – Plattformzeit: Standby-Sperr-Overlay-Steuerung (ADR-LIW-MYL-002 §7).
 *
 * Das Overlay wird nur ausgegeben, wenn der Server „gesperrt" (Standby) meldet. Dieses Skript holt die
 * Rechenaufgabe cache-sicher über die REST-Route `platform-time/challenge`, prüft die Antwort per
 * `platform-time/resume` und lädt bei Erfolg neu (der Server rendert dann kein Overlay mehr). Die
 * „Liebherr World"-Leiste + Sprachumschalter bleiben über den gemeinsamen Helfer sichtbar. Vanilla-JS.
 */
( function () {
	'use strict';

	var cfg = window.liwPtimeLock;
	var root = document.querySelector( '[data-liw-ptlock]' );
	if ( ! cfg || ! cfg.root || ! root ) { return; }

	// Weltleiste oben sichtbar halten (Leiste schwebt, Sprachumschalter wandert hinein).
	if ( window.LiwWorldbarLock ) {
		window.LiwWorldbarLock.engage();
	} else {
		document.documentElement.classList.add( 'liw-intro-lock' );
	}

	var elQ     = root.querySelector( '[data-liw-ptlock-q]' );
	var elTok   = root.querySelector( '[data-liw-ptlock-token]' );
	var elAns   = root.querySelector( '[data-liw-ptlock-answer]' );
	var elForm  = root.querySelector( '[data-liw-ptlock-form]' );
	var elEnter = root.querySelector( '[data-liw-ptlock-enter]' );
	var elHint  = root.querySelector( '[data-liw-ptlock-hint]' );
	var i18n    = cfg.i18n || {};
	var busy    = false;

	function api( path, method, body ) {
		return fetch( cfg.root + path, {
			method: method,
			headers: { 'X-WP-Nonce': cfg.nonce, 'Content-Type': 'application/json', 'Accept': 'application/json' },
			credentials: 'same-origin',
			body: body ? JSON.stringify( body ) : undefined
		} ).then( function ( r ) { return r.json().catch( function () { return null; } ); } ).catch( function () { return null; } );
	}

	function refresh() { if ( elEnter ) { elEnter.disabled = busy || '' === elAns.value.replace( /\s/g, '' ); } }

	function loadChallenge() {
		if ( elHint ) { elHint.textContent = ''; }
		return api( 'challenge', 'GET' ).then( function ( d ) {
			if ( d && d.ok && d.token ) {
				if ( elQ ) { elQ.textContent = d.question; }
				if ( elTok ) { elTok.value = d.token; }
			}
			if ( elAns ) { elAns.value = ''; }
			refresh();
			if ( elAns ) { try { elAns.focus(); } catch ( e ) {} }
		} );
	}

	if ( elAns ) { elAns.addEventListener( 'input', refresh ); }

	if ( elForm ) {
		elForm.addEventListener( 'submit', function ( ev ) {
			ev.preventDefault();
			if ( busy ) { return; }
			var ans = parseInt( elAns.value.replace( /\s/g, '' ), 10 );
			if ( isNaN( ans ) ) { return; }
			busy = true; refresh();
			api( 'resume', 'POST', { token: elTok ? elTok.value : '', answer: ans } ).then( function ( d ) {
				if ( d && d.ok ) {
					if ( window.LiwWorldbarLock ) { window.LiwWorldbarLock.release(); }
					window.location.reload();
					return;
				}
				busy = false;
				var reason = d && d.reason ? d.reason : 'error';
				if ( elHint ) { elHint.textContent = 'expired' === reason ? ( i18n.expired || '' ) : ( 'wrong' === reason ? ( i18n.wrong || '' ) : ( i18n.error || '' ) ); }
				// Bei verbrauchter/abgelaufener Aufgabe eine frische holen.
				if ( 'wrong' !== reason ) { loadChallenge(); } else { refresh(); }
			} );
		} );
	}

	loadChallenge();
} )();
