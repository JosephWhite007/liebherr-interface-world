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

/**
 * Mobiles Off-Canvas-Menü des Headers ([liw_header], §7, alpha.28).
 * Schaltet `data-liw-open` am Header und `aria-expanded` am Umschalter; schließt bei Escape
 * und bei Klick auf einen Navigationslink. Fortschreitende Verbesserung: ohne JS ist die
 * Navigation via CSS weiterhin erreichbar (Menü über 900px sichtbar).
 */
( function () {
	'use strict';
	function initHeader( header ) {
		var toggle = header.querySelector( '.liw-header__toggle' );
		var nav    = header.querySelector( '.liw-header__nav' );
		if ( ! toggle || ! nav ) { return; }

		function setOpen( open ) {
			header.setAttribute( 'data-liw-open', open ? '1' : '0' );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}
		setOpen( false );

		toggle.addEventListener( 'click', function () {
			setOpen( header.getAttribute( 'data-liw-open' ) !== '1' );
		} );
		nav.addEventListener( 'click', function ( e ) {
			if ( e.target && e.target.closest && e.target.closest( '.liw-header__nav-link' ) ) { setOpen( false ); }
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) { setOpen( false ); }
		} );
	}

	function init() {
		var headers = document.querySelectorAll( '[data-liw-header]' );
		for ( var i = 0; i < headers.length; i++ ) { initHeader( headers[ i ] ); }
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );

/**
 * Connected-World-Weltkarte ([liw_world_map], §8/LP-06, alpha.37).
 * Verknüpft Regionen-Knoten (SVG) mit der Text-Alternative: Hover/Fokus/Klick hebt Knoten und
 * Listeneintrag gemeinsam hervor. Reine Anzeige-Interaktion; ohne JS bleiben Karte und Liste nutzbar.
 */
( function () {
	'use strict';
	function initMap( root ) {
		var nodes = root.querySelectorAll( '.liw-worldmap__node[data-region]' );
		var items = root.querySelectorAll( '.liw-worldmap__list li[data-region]' );
		if ( ! nodes.length ) { return; }

		function setHighlight( region, on ) {
			[].forEach.call( nodes, function ( n ) {
				if ( n.getAttribute( 'data-region' ) === region ) { n.classList.toggle( 'is-highlight', on ); }
			} );
			[].forEach.call( items, function ( li ) {
				if ( li.getAttribute( 'data-region' ) === region ) { li.classList.toggle( 'is-highlight', on ); }
			} );
		}

		function bind( el ) {
			var region = el.getAttribute( 'data-region' );
			el.addEventListener( 'mouseenter', function () { setHighlight( region, true ); } );
			el.addEventListener( 'mouseleave', function () { setHighlight( region, false ); } );
			el.addEventListener( 'focus', function () { setHighlight( region, true ); } );
			el.addEventListener( 'blur', function () { setHighlight( region, false ); } );
		}
		[].forEach.call( nodes, bind );
		[].forEach.call( items, bind );
	}

	function init() {
		var maps = document.querySelectorAll( '[data-liw-worldmap]' );
		for ( var i = 0; i < maps.length; i++ ) { initMap( maps[ i ] ); }
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );

/**
 * Simulation World – Szenario-Schalter ([liw_simulation_world], LI §8 Modul 4, alpha.41).
 * Umschaltbare Varianten A/B/C (Tabs/Panels). Fortschreitende Verbesserung: ohne JS sind alle
 * Panels über die Standardanzeige erreichbar (CSS zeigt sie gestapelt), die Tabs steuern nur die
 * Sichtbarkeit. Tastaturbedienbar (Pfeiltasten), ARIA-Zustände werden gepflegt. Nur Demo-Daten.
 */
( function () {
	'use strict';
	function initSim( root ) {
		var tabs   = [].slice.call( root.querySelectorAll( '[data-liw-sim-tab]' ) );
		var panels = [].slice.call( root.querySelectorAll( '[data-liw-sim-panel]' ) );
		if ( ! tabs.length || ! panels.length ) { return; }
		root.classList.add( 'liw-li__sim--enhanced' ); // CSS: erst mit JS die Panels als Tabs verstecken.

		function show( key ) {
			tabs.forEach( function ( t ) {
				var on = t.getAttribute( 'data-liw-sim-tab' ) === key;
				t.classList.toggle( 'is-active', on );
				t.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				t.tabIndex = on ? 0 : -1;
			} );
			panels.forEach( function ( p ) {
				var on = p.getAttribute( 'data-liw-sim-panel' ) === key;
				p.classList.toggle( 'is-active', on );
				if ( on ) { p.removeAttribute( 'hidden' ); } else { p.setAttribute( 'hidden', 'hidden' ); }
			} );
		}

		tabs.forEach( function ( t, i ) {
			t.addEventListener( 'click', function () { show( t.getAttribute( 'data-liw-sim-tab' ) ); } );
			t.addEventListener( 'keydown', function ( e ) {
				var next = null;
				if ( 'ArrowRight' === e.key || 'ArrowDown' === e.key ) { next = tabs[ ( i + 1 ) % tabs.length ]; }
				else if ( 'ArrowLeft' === e.key || 'ArrowUp' === e.key ) { next = tabs[ ( i - 1 + tabs.length ) % tabs.length ]; }
				if ( next ) { e.preventDefault(); next.focus(); show( next.getAttribute( 'data-liw-sim-tab' ) ); }
			} );
		} );

		// Ausgangszustand herstellen: aktiven Tab (oder ersten) einblenden, übrige einklappen.
		var initial = tabs.filter( function ( t ) { return t.classList.contains( 'is-active' ); } )[ 0 ] || tabs[ 0 ];
		show( initial.getAttribute( 'data-liw-sim-tab' ) );
	}
	function init() {
		var sims = document.querySelectorAll( '[data-liw-sim]' );
		for ( var i = 0; i < sims.length; i++ ) { initSim( sims[ i ] ); }
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );

/**
 * Einsatzfelder – Filter ([liw_li_usecases], LI §8 Modul 8, alpha.41).
 * Blendet Karten nach Tag ein/aus. Fortschreitende Verbesserung: ohne JS sind alle Karten sichtbar,
 * die Filterleiste ist rein additiv. aria-pressed wird gepflegt.
 */
( function () {
	'use strict';
	function initFilter( bar ) {
		var section = bar.closest( '.liw-li__section' ) || document;
		var buttons = [].slice.call( bar.querySelectorAll( '[data-liw-filter]' ) );
		var cards   = [].slice.call( section.querySelectorAll( '.liw-li__usecase[data-liw-tag]' ) );
		if ( ! buttons.length || ! cards.length ) { return; }

		function apply( tag ) {
			cards.forEach( function ( c ) {
				var show = '*' === tag || c.getAttribute( 'data-liw-tag' ) === tag;
				c.style.display = show ? '' : 'none';
			} );
			buttons.forEach( function ( b ) {
				var on = b.getAttribute( 'data-liw-filter' ) === tag;
				b.classList.toggle( 'is-active', on );
				b.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			} );
		}
		buttons.forEach( function ( b ) {
			b.addEventListener( 'click', function () { apply( b.getAttribute( 'data-liw-filter' ) ); } );
		} );
	}
	function init() {
		var bars = document.querySelectorAll( '[data-liw-usecase-filter]' );
		for ( var i = 0; i < bars.length; i++ ) { initFilter( bars[ i ] ); }
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
