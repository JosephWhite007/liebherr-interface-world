/**
 * Liebherr – My Liebherr: Dashboard-Anpassung (S3) + Profil-Formular (S5).
 *
 * Dashboard: Widgets ordnen / aus- und einblenden / zurücksetzen (serverseitig via PUT /dashboard, §5).
 * Profil: erlaubte Profilfelder über PATCH /me speichern (§18). Vanilla-JS, defensiv, ohne Abhängigkeiten.
 */
( function () {
	'use strict';
	var cfg = window.liwMyl;
	if ( ! cfg || ! cfg.root ) { return; }

	function api( path, method, body ) {
		return fetch( cfg.root + path, {
			method: method,
			headers: { 'X-WP-Nonce': cfg.nonce, 'Content-Type': 'application/json' },
			credentials: 'same-origin',
			body: body ? JSON.stringify( body ) : undefined
		} ).then( function ( r ) { return r.json(); } ).catch( function () { return null; } );
	}

	// ── Dashboard ─────────────────────────────────────────────────────────────
	var board = document.querySelector( '[data-liw-dashboard]' );
	if ( board ) {
		var currentLayout = function () {
			var out = [];
			board.querySelectorAll( '.liw-myl__grid [data-liw-widget]' ).forEach( function ( el ) {
				out.push( { key: el.getAttribute( 'data-liw-widget' ), visible: true } );
			} );
			board.querySelectorAll( '.liw-myl__hidden-list [data-liw-widget]' ).forEach( function ( el ) {
				out.push( { key: el.getAttribute( 'data-liw-widget' ), visible: false } );
			} );
			return out;
		};
		var save = function ( layout ) {
			api( 'dashboard', 'PUT', { layout: layout } ).then( function ( d ) { if ( d && d.ok ) { location.reload(); } } );
		};
		board.addEventListener( 'click', function ( ev ) {
			var btn = ev.target.closest( 'button' );
			if ( ! btn ) { return; }
			var tile = btn.closest( '[data-liw-widget]' );
			var key = tile ? tile.getAttribute( 'data-liw-widget' ) : '';

			if ( btn.hasAttribute( 'data-liw-reset' ) ) {
				api( 'dashboard', 'PUT', { reset: true } ).then( function ( d ) { if ( d && d.ok ) { location.reload(); } } );
				return;
			}
			var layout = currentLayout();
			var idx = layout.findIndex( function ( e ) { return e.key === key; } );
			if ( idx < 0 ) { return; }

			if ( btn.hasAttribute( 'data-liw-hide' ) ) { layout[ idx ].visible = false; save( layout ); return; }
			if ( btn.hasAttribute( 'data-liw-show' ) ) { layout[ idx ].visible = true; save( layout ); return; }
			if ( btn.hasAttribute( 'data-liw-move' ) ) {
				var dir = btn.getAttribute( 'data-liw-move' );
				// nur unter sichtbaren Widgets sortieren
				var vis = layout.filter( function ( e ) { return e.visible; } );
				var vIdx = vis.findIndex( function ( e ) { return e.key === key; } );
				var swap = 'up' === dir ? vIdx - 1 : vIdx + 1;
				if ( vIdx < 0 || swap < 0 || swap >= vis.length ) { return; }
				var tmp = vis[ vIdx ]; vis[ vIdx ] = vis[ swap ]; vis[ swap ] = tmp;
				save( vis.concat( layout.filter( function ( e ) { return ! e.visible; } ) ) );
			}
		} );
	}

	// ── Generische Content-Aktionen (Dreams/Gallery/Shares) ─────────────────────
	// Formulare mit data-liw-post="<pfad>" senden ihre Felder als JSON-POST; danach neu laden.
	document.addEventListener( 'submit', function ( ev ) {
		var f = ev.target.closest ? ev.target.closest( '[data-liw-post]' ) : null;
		if ( ! f ) { return; }
		ev.preventDefault();
		var path = f.getAttribute( 'data-liw-post' );
		var body = {};
		f.querySelectorAll( '[name]' ).forEach( function ( el ) {
			body[ el.name ] = ( 'checkbox' === el.type ) ? ( el.checked ? 1 : 0 ) : el.value;
		} );
		api( path, 'POST', body ).then( function ( d ) { if ( d && false !== d.ok ) { location.reload(); } } );
	} );
	// Buttons mit data-liw-act="<pfad>" (+ optional data-liw-method) lösen eine Aktion aus; danach neu laden.
	document.addEventListener( 'click', function ( ev ) {
		var b = ev.target.closest ? ev.target.closest( '[data-liw-act]' ) : null;
		if ( ! b ) { return; }
		ev.preventDefault();
		api( b.getAttribute( 'data-liw-act' ), b.getAttribute( 'data-liw-method' ) || 'POST' ).then( function ( d ) {
			if ( d && false !== d.ok ) { location.reload(); }
		} );
	} );

	// ── Profil-Formular ─────────────────────────────────────────────────────────
	var form = document.querySelector( '[data-liw-profile-form]' );
	if ( form ) {
		var status = form.querySelector( '[data-liw-profile-status]' );
		form.addEventListener( 'submit', function ( ev ) {
			ev.preventDefault();
			var body = {
				persona: ( form.querySelector( '[name=persona]' ) || {} ).value || '',
				locale: ( form.querySelector( '[name=locale]' ) || {} ).value || '',
				timezone: ( form.querySelector( '[name=timezone]' ) || {} ).value || '',
				active_org_id: parseInt( ( form.querySelector( '[name=active_org_id]' ) || {} ).value || '0', 10 ) || 0
			};
			if ( status ) { status.textContent = '…'; }
			api( 'me', 'PATCH', body ).then( function ( d ) {
				if ( status ) { status.textContent = ( d && d.ok ) ? ( form.getAttribute( 'data-saved' ) || 'OK' ) : ( form.getAttribute( 'data-error' ) || 'Fehler' ); }
			} );
		} );
	}
} )();
