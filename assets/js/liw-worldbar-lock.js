/**
 * Liebherr World – gemeinsamer „Weltleisten-Sperr"-Helfer (ADR-LIW-MYL-002 §5.2).
 *
 * EINE Quelle für das Verhalten „Vollbild-Overlay über der Plattform, aber die ‚Liebherr World'-Leiste
 * (.liw-switcher) und der Sprachumschalter bleiben oben sichtbar/bedienbar". Genutzt vom Intro-Gate
 * (liebherr-frontend.js) und von der Plattformzeit-Sperre (liw-ptime-lock.js) – keine Redundanz.
 *
 * engage(): setzt html.liw-intro-lock (die CSS lässt die Leiste oben schweben) und hängt den
 * Sprachumschalter (.liw-header__lang, Wrapper um das Core-Widget) in die Leiste (.liw-switcher__inner),
 * weil er sonst im Header-Stacking-Kontext gefangen bleibt. release(): macht beides rückgängig.
 * Beide Aufrufe sind idempotent.
 */
( function ( w, d ) {
	'use strict';

	var moved = null; // { node, parent, next }

	function engage() {
		d.documentElement.classList.add( 'liw-intro-lock' );
		if ( moved ) { return; }
		var bar  = d.querySelector( '.liw-switcher__inner' );
		var lang = d.querySelector( '.liw-header__lang' );
		if ( ! bar || ! lang || bar.contains( lang ) ) { return; }
		moved = { node: lang, parent: lang.parentNode, next: lang.nextSibling };
		bar.appendChild( lang );
	}

	function release() {
		d.documentElement.classList.remove( 'liw-intro-lock' );
		if ( moved && moved.parent ) {
			if ( moved.next && moved.next.parentNode === moved.parent ) {
				moved.parent.insertBefore( moved.node, moved.next );
			} else {
				moved.parent.appendChild( moved.node );
			}
		}
		moved = null;
	}

	w.LiwWorldbarLock = { engage: engage, release: release };
} )( window, document );
