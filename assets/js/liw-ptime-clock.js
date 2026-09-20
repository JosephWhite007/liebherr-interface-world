/**
 * Liebherr – Plattformzeit: schwebende Session-Uhr (ADR-LIW-MYL-001 S11).
 *
 * Zeigt verstrichene aktive Zeit + laufende Token. Die Anzeige tickt lokal zwischen den Herzschlägen;
 * maßgeblich ist der serverautoritäre Heartbeat (§41.1). Vanilla-JS, ohne Abhängigkeiten, defensiv.
 */
( function () {
	'use strict';
	var cfg = window.liwPtime;
	if ( ! cfg || ! cfg.root ) { return; }
	var root = document.querySelector( '[data-liw-ptime]' );
	if ( ! root ) { return; }

	var elTime = root.querySelector( '[data-liw-ptime-time]' );
	var elTok  = root.querySelector( '[data-liw-ptime-tokens]' );
	var btnStop = root.querySelector( '[data-liw-ptime-stop]' );
	var btnTgl  = root.querySelector( '[data-liw-ptime-toggle]' );

	var baseActive = 0, baseAt = Date.now(), tokens = 0, running = false, stopped = false;

	function two( n ) { return ( n < 10 ? '0' : '' ) + n; }
	function fmt( s ) {
		s = Math.max( 0, s | 0 );
		var h = Math.floor( s / 3600 ), m = Math.floor( ( s % 3600 ) / 60 ), sec = s % 60;
		return two( h ) + ':' + two( m ) + ':' + two( sec );
	}
	function paint() {
		var extra = ( running && ! stopped ) ? Math.floor( ( Date.now() - baseAt ) / 1000 ) : 0;
		if ( elTime ) { elTime.textContent = fmt( baseActive + extra ); }
		if ( elTok ) { elTok.textContent = String( tokens ); }
	}
	function api( path, method ) {
		return fetch( cfg.root + path, {
			method: method,
			headers: { 'X-WP-Nonce': cfg.nonce, 'Content-Type': 'application/json' },
			credentials: 'same-origin'
		} ).then( function ( r ) { return r.json(); } ).catch( function () { return null; } );
	}
	function sync( d ) {
		if ( ! d || ! d.ok ) { return; }
		if ( typeof d.active_seconds === 'number' ) { baseActive = d.active_seconds; baseAt = Date.now(); }
		if ( typeof d.tokens === 'number' ) { tokens = d.tokens; }
		running = ( d.running !== false );
		paint();
	}

	root.hidden = false;
	// Standard eingeklappt (nur grüner Punkt + „Time"). Nur wenn der Nutzer zuletzt ausdrücklich
	// aufgeklappt hatte (localStorage '0'), starten wir wieder aufgeklappt.
	var startExpanded = false;
	try { startExpanded = localStorage.getItem( 'liwPtimeCollapsed' ) === '0'; } catch ( e ) {}
	if ( startExpanded ) {
		root.classList.remove( 'is-collapsed' );
		if ( btnTgl ) { btnTgl.setAttribute( 'aria-expanded', 'true' ); }
	} else {
		root.classList.add( 'is-collapsed' );
		if ( btnTgl ) { btnTgl.setAttribute( 'aria-expanded', 'false' ); }
	}

	api( 'start', 'POST' ).then( sync );
	var hb = setInterval( function () { if ( ! stopped ) { api( 'heartbeat', 'POST' ).then( sync ); } }, ( cfg.interval || 30 ) * 1000 );
	setInterval( paint, 1000 );

	if ( btnTgl ) {
		btnTgl.addEventListener( 'click', function () {
			var col = root.classList.toggle( 'is-collapsed' );
			btnTgl.setAttribute( 'aria-expanded', col ? 'false' : 'true' );
			try { localStorage.setItem( 'liwPtimeCollapsed', col ? '1' : '0' ); } catch ( e ) {}
		} );
	}
	if ( btnStop ) {
		btnStop.addEventListener( 'click', function () {
			btnStop.disabled = true;
			api( 'stop', 'POST' ).then( function ( d ) {
				stopped = true; running = false;
				clearInterval( hb );
				if ( d && typeof d.active_seconds === 'number' ) { baseActive = d.active_seconds; }
				if ( d && typeof d.tokens === 'number' ) { tokens = d.tokens; }
				baseAt = Date.now();
				paint();
				if ( cfg.i18n && cfg.i18n.stopped && elTime ) { elTime.setAttribute( 'title', cfg.i18n.stopped ); }
			} );
		} );
	}
} )();
