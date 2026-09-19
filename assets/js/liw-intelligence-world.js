/**
 * Liebherr Intelligence World – Eintrittsschleuse & Sitzungsleiste (Pflichtenheft-2 §4.2/§7, alpha.48).
 *
 * Steuert das Access Gate (Code + zwei Pflicht-Einwilligungen), startet über REST eine serverseitige Sitzung
 * und zeigt danach eine kompakte Sitzungs-/Kostenleiste (Ticker) mit Budget-Warnungen (50/80/100 %). Die
 * Abrechnungswahrheit liegt serverseitig; der Client extrapoliert nur zwischen den Heartbeats für die Anzeige.
 * Fortschreitende Verbesserung: ohne JS bleibt die Landing lesbar; die kostenpflichtige Sitzung braucht JS.
 */
( function () {
	'use strict';
	var cfg = window.liwIw || null;
	if ( ! cfg ) { return; }

	function $( sel, root ) { return ( root || document ).querySelector( sel ); }

	function post( path, body ) {
		return fetch( cfg.rest + path, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-LIW-Nonce': cfg.nonce },
			body: JSON.stringify( body || {} )
		} ).then( function ( r ) { return r.json(); } );
	}

	function money( minor, cur ) {
		var neg = minor < 0, a = Math.abs( minor ), s = ( a / 100 ).toFixed( 2 ).replace( '.', ',' );
		return ( neg ? '-' : '' ) + s + ' ' + ( cur || 'EUR' );
	}
	function dur( sec ) {
		sec = Math.max( 0, sec | 0 );
		function p( n ) { return ( n < 10 ? '0' : '' ) + n; }
		return p( ( sec / 3600 ) | 0 ) + ':' + p( ( ( sec % 3600 ) / 60 ) | 0 ) + ':' + p( sec % 60 );
	}

	function initWorld( root ) {
		var gate    = $( '[data-liw-iw-gate]', root );
		var world   = $( '[data-liw-iw-world]', root );
		var codeEl  = $( '[data-liw-iw-code]', root );
		var confirm = $( '[data-liw-iw-confirm]', root );
		var msg     = $( '[data-liw-iw-msg]', root );
		var consents = [].slice.call( root.querySelectorAll( '[data-liw-iw-consent]' ) );
		var termsToggle = $( '.liw-iw__terms-toggle', root );
		var terms   = $( '#liw-iw-terms', root );

		var timeEl  = $( '[data-liw-iw-time]', root );
		var baseEl  = $( '[data-liw-iw-base]', root );
		var pctEl   = $( '[data-liw-iw-budgetpct]', root );
		var fillEl  = $( '[data-liw-iw-budgetfill]', root );
		var endBtn  = $( '[data-liw-iw-end]', root );
		var proto   = $( '[data-liw-iw-protocol]', root );

		var sessionCode = null;
		var active = 0;          // Sekunden (lokal fortgeschrieben)
		var priceSec = cfg.config.price_second | 0;
		var budget = cfg.config.session_budget | 0;
		var cur = cfg.config.currency || 'EUR';
		var running = false;
		var lastWarn = 0;
		var tick = null, beat = null;

		if ( termsToggle && terms ) {
			termsToggle.addEventListener( 'click', function () {
				var open = terms.hasAttribute( 'hidden' );
				if ( open ) { terms.removeAttribute( 'hidden' ); } else { terms.setAttribute( 'hidden', 'hidden' ); }
				termsToggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			} );
		}

		function refreshConfirm() {
			var ok = codeEl.value.trim() !== '' && consents.every( function ( c ) { return c.checked; } );
			confirm.disabled = ! ok;
		}
		codeEl.addEventListener( 'input', refreshConfirm );
		consents.forEach( function ( c ) { c.addEventListener( 'change', refreshConfirm ); } );

		function paint() {
			var base = active * priceSec;
			var pct  = budget > 0 ? Math.floor( base * 100 / budget ) : 0;
			timeEl.textContent = dur( active );
			baseEl.textContent = money( base, cur );
			pctEl.textContent  = pct + ' %';
			if ( fillEl ) { fillEl.style.width = Math.min( 100, pct ) + '%'; }
			root.setAttribute( 'data-liw-level', pct >= 100 ? 'limit' : ( pct >= 80 ? 'high' : ( pct >= 50 ? 'mid' : 'ok' ) ) );
			var step = pct >= 100 ? 100 : ( pct >= 80 ? 80 : ( pct >= 50 ? 50 : 0 ) );
			if ( step > lastWarn ) {
				lastWarn = step;
				if ( step === 50 ) { announce( cfg.i18n.warn50 ); }
				else if ( step === 80 ) { announce( cfg.i18n.warn80 ); }
				else if ( step === 100 ) { announce( cfg.i18n.warn100 ); }
			}
		}
		function announce( text ) { if ( msg ) { msg.textContent = text; } }

		function syncFromStatus( st ) {
			if ( ! st ) { return; }
			active = st.active_seconds | 0;
			if ( st.base_cost_display ) { baseEl.textContent = st.base_cost_display; }
			paint();
		}

		function startTimers() {
			running = true;
			tick = window.setInterval( function () { if ( running ) { active++; paint(); } }, 1000 );
			beat = window.setInterval( function () {
				if ( ! running || ! sessionCode ) { return; }
				post( 'session/heartbeat', { session_code: sessionCode } ).then( function ( res ) {
					if ( res && res.ok ) { syncFromStatus( res.status ); }
				} ).catch( function () {} );
			}, 15000 );
		}
		function stopTimers() { running = false; if ( tick ) { clearInterval( tick ); } if ( beat ) { clearInterval( beat ); } }

		confirm.addEventListener( 'click', function () {
			confirm.disabled = true;
			announce( '' );
			post( 'session/start', {
				code: codeEl.value.trim(),
				consent_terms: consents.some( function ( c ) { return c.getAttribute( 'data-liw-iw-consent' ) === 'terms' && c.checked; } ),
				consent_storage: consents.some( function ( c ) { return c.getAttribute( 'data-liw-iw-consent' ) === 'storage' && c.checked; } )
			} ).then( function ( res ) {
				if ( ! res || ! res.ok ) {
					announce( ( res && res.error ) || cfg.i18n.invalid );
					refreshConfirm();
					return;
				}
				sessionCode = res.session_code;
				if ( res.config ) { priceSec = res.config.price_second | 0; budget = res.config.session_budget | 0; cur = res.config.currency || cur; }
				gate.setAttribute( 'hidden', 'hidden' );
				world.removeAttribute( 'hidden' );
				syncFromStatus( res.status );
				startTimers();
				try { world.scrollIntoView( { behavior: 'smooth', block: 'start' } ); } catch ( e ) {}
			} ).catch( function () { announce( cfg.i18n.invalid ); refreshConfirm(); } );
		} );

		endBtn.addEventListener( 'click', function () {
			if ( ! sessionCode ) { return; }
			endBtn.disabled = true;
			stopTimers();
			post( 'session/end', { session_code: sessionCode } ).then( function ( res ) {
				if ( res && res.ok && res.status ) {
					syncFromStatus( res.status );
					if ( proto ) {
						proto.removeAttribute( 'hidden' );
						proto.textContent = cfg.i18n.ended + ' ' + res.status.session_code + ' · ' +
							cfg.i18n.time + ': ' + res.status.active_display + ' · ' +
							cfg.i18n.base + ': ' + res.status.base_cost_display;
					}
				}
			} ).catch( function () {} );
		} );
	}

	function init() {
		var roots = document.querySelectorAll( '[data-liw-iw]' );
		for ( var i = 0; i < roots.length; i++ ) { initWorld( roots[ i ] ); }
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
