/**
 * Liebherr Interface Solutions – Frontend-Verhalten
 *
 * Aktive Hervorhebung des sichtbaren Abschnitts in der Sprungleiste der Landingpage
 * (alpha.26, To-Do-Punkt seit alpha.18). Fortschreitende Verbesserung: Die Sprungleiste
 * (`.liw-landingpage__nav`, position: sticky) funktioniert ohne JavaScript vollständig;
 * dieses Skript ergänzt lediglich die Markierung des gerade gelesenen Abschnitts.
 *
 * Ein IntersectionObserver mit schmalem Lesefenster (~42 %) bestimmt den aktuellen
 * Abschnitt; der zugehörige Link (`a.liw-landingpage__nav-link[href="#…"]`) erhält
 * `aria-current="true"` und die Klasse `is-current`, alle anderen verlieren sie.
 * Kein Inline-Code, keine Abhängigkeit, keine Netzwerk-/Personendaten. Ohne
 * IntersectionObserver bleibt die Sprungleiste unverändert.
 *
 * @package Liebherr\InterfaceWorld\Frontend
 * @since   0.1.0-alpha.26
 */
( function () {
	'use strict';

	function initNav( nav ) {
		var links = nav.querySelectorAll( 'a.liw-landingpage__nav-link[href^="#"]' );
		if ( ! links.length ) {
			return;
		}

		var byId    = {}; // Anker-ID → Link
		var targets = []; // Zielabschnitte in Dokumentreihenfolge
		var visible = {}; // Anker-ID → aktuell im Lesefenster?

		for ( var i = 0; i < links.length; i++ ) {
			var href = links[ i ].getAttribute( 'href' ) || '';
			var id   = href.charAt( 0 ) === '#' ? href.slice( 1 ) : '';
			if ( ! id ) {
				continue;
			}
			var section = document.getElementById( id );
			if ( ! section ) {
				continue;
			}
			byId[ id ] = links[ i ];
			targets.push( section );
		}
		if ( ! targets.length ) {
			return;
		}

		var currentId = null;
		function setActive( id ) {
			if ( id === currentId ) {
				return;
			}
			currentId = id;
			for ( var key in byId ) {
				if ( ! Object.prototype.hasOwnProperty.call( byId, key ) ) {
					continue;
				}
				var link = byId[ key ];
				if ( key === id ) {
					link.classList.add( 'is-current' );
					link.setAttribute( 'aria-current', 'true' );
				} else {
					link.classList.remove( 'is-current' );
					link.removeAttribute( 'aria-current' );
				}
			}
		}

		function update() {
			// Obersten im Lesefenster liegenden Abschnitt (Dokumentreihenfolge) markieren;
			// liegt keiner im Fenster, bleibt die letzte Markierung bestehen.
			for ( var i = 0; i < targets.length; i++ ) {
				if ( visible[ targets[ i ].id ] ) {
					setActive( targets[ i ].id );
					return;
				}
			}
		}

		var observer = new IntersectionObserver( function ( entries ) {
			for ( var i = 0; i < entries.length; i++ ) {
				visible[ entries[ i ].target.id ] = entries[ i ].isIntersecting;
			}
			update();
		}, { rootMargin: '-42% 0px -53% 0px', threshold: 0 } );

		for ( var t = 0; t < targets.length; t++ ) {
			observer.observe( targets[ t ] );
		}
	}

	function init() {
		if ( ! ( 'IntersectionObserver' in window ) ) {
			return;
		}
		var navs = document.querySelectorAll( '.liw-landingpage__nav' );
		for ( var i = 0; i < navs.length; i++ ) {
			initNav( navs[ i ] );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
