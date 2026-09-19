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

	function get( path, params ) {
		var qs = Object.keys( params || {} ).map( function ( k ) {
			return encodeURIComponent( k ) + '=' + encodeURIComponent( params[ k ] );
		} ).join( '&' );
		return fetch( cfg.rest + path + ( qs ? '?' + qs : '' ), {
			method: 'GET',
			headers: { 'X-LIW-Nonce': cfg.nonce }
		} ).then( function ( r ) { return r.json(); } );
	}

	function escHtml( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"]/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ c ];
		} );
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
				// Vom Cockpit „Go" (Anker #liw-iw-simulation): nach dem Eintritt direkt zum Simulation Builder.
				var deepTarget = ( '#liw-iw-simulation' === window.location.hash )
					? document.getElementById( 'liw-iw-simulation' ) : null;
				try {
					if ( deepTarget ) {
						// Etwas verzögert + instant (zuverlässiger, sobald die Weltansicht gelayoutet ist).
						window.setTimeout( function () { try { deepTarget.scrollIntoView( { block: 'start' } ); } catch ( e ) {} }, 250 );
					} else {
						world.scrollIntoView( { behavior: 'smooth', block: 'start' } );
					}
				} catch ( e ) {}
			} ).catch( function () { announce( cfg.i18n.invalid ); refreshConfirm(); } );
		} );

		function renderProtocol( p ) {
			if ( ! proto || ! p ) { return; }
			var t = cfg.i18n, b = p.billing || {};
			var rows = ( p.events || [] ).map( function ( e ) {
				return '<tr><td>' + ( e.seq | 0 ) + '</td><td>' + escHtml( e.occurred_at ) +
					'</td><td>' + escHtml( e.label ) + '</td></tr>';
			} ).join( '' );
			var integrity = p.integrity_ok
				? '<span class="liw-iw__proto-badge liw-iw__proto-badge--ok">' + escHtml( t.proto_intact ) + '</span>'
				: '<span class="liw-iw__proto-badge liw-iw__proto-badge--bad">' + escHtml( t.proto_broken ) + '</span>';
			var itemsHtml = '';
			if ( p.line_items && p.line_items.length ) {
				var lrows = p.line_items.map( function ( it ) {
					return '<tr><td>' + escHtml( it.label ) + '</td><td>' + ( it.units | 0 ) + '</td><td>' + escHtml( it.cost_display ) + '</td></tr>';
				} ).join( '' );
				itemsHtml = '<h4 class="liw-iw__proto-subtitle">' + escHtml( t.proto_items ) + ' (' + p.line_items.length + ')</h4>' +
					'<table class="liw-iw__proto-table"><thead><tr><th>' + escHtml( t.proto_event ) + '</th><th>' + escHtml( t.mod_used ) + '</th><th>' + escHtml( t.proto_cost ) + '</th></tr></thead><tbody>' + lrows + '</tbody></table>';
			}
			var modulesLine = b.modules_cost_minor ? ( '<dt>' + escHtml( t.mod_extra ) + '</dt><dd>' + escHtml( b.modules_display ) + '</dd>' ) : '';
			proto.innerHTML =
				'<div class="liw-iw__proto-doc">' +
				'<h3 class="liw-iw__proto-title">' + escHtml( t.proto_title ) + '</h3>' +
				'<p class="liw-iw__proto-intro">' + escHtml( t.proto_intro ) + ' ' + integrity + '</p>' +
				'<dl class="liw-iw__proto-meta">' +
				'<dt>' + escHtml( t.proto_session ) + '</dt><dd>' + escHtml( p.session_code ) + '</dd>' +
				'<dt>' + escHtml( t.proto_status ) + '</dt><dd>' + escHtml( p.status ) + '</dd>' +
				'<dt>' + escHtml( t.proto_start ) + '</dt><dd>' + escHtml( p.started_at ) + '</dd>' +
				'<dt>' + escHtml( t.proto_end ) + '</dt><dd>' + escHtml( p.ended_at ) + '</dd>' +
				'<dt>' + escHtml( t.proto_active ) + '</dt><dd>' + escHtml( p.active_display ) + '</dd>' +
				'<dt>' + escHtml( t.proto_base ) + '</dt><dd>' + escHtml( b.base_cost_display ) + '</dd>' +
				modulesLine +
				'<dt>' + escHtml( t.mod_total ) + '</dt><dd><strong>' + escHtml( b.total_display || b.base_cost_display ) + '</strong></dd>' +
				'<dt>' + escHtml( t.proto_budget ) + '</dt><dd>' + escHtml( b.budget_display ) + ' · ' + ( b.budget_pct | 0 ) + ' %</dd>' +
				'</dl>' +
				itemsHtml +
				'<h4 class="liw-iw__proto-subtitle">' + escHtml( t.proto_events ) + ' (' + ( p.event_count | 0 ) + ')</h4>' +
				'<table class="liw-iw__proto-table"><thead><tr><th>' + escHtml( t.proto_seq ) + '</th><th>' +
				escHtml( t.proto_time ) + '</th><th>' + escHtml( t.proto_event ) + '</th></tr></thead><tbody>' + rows + '</tbody></table>' +
				'<div class="liw-iw__proto-actions">' +
				'<button type="button" class="liw-cta liw-cta--secondary" data-liw-iw-proto-json>' + escHtml( t.proto_json ) + '</button> ' +
				'<button type="button" class="liw-cta liw-cta--secondary" data-liw-iw-proto-print>' + escHtml( t.proto_print ) + '</button> ' +
				'<a class="liw-cta liw-cta--secondary" href="' + escHtml( cfg.rest + 'session/protocol-pdf?session_code=' + encodeURIComponent( p.session_code ) ) + '">' + escHtml( t.proto_pdf || 'PDF (Server)' ) + '</a>' +
				'</div></div>';
			proto.removeAttribute( 'hidden' );

			var jsonBtn = $( '[data-liw-iw-proto-json]', proto );
			if ( jsonBtn ) {
				jsonBtn.addEventListener( 'click', function () {
					var blob = new Blob( [ JSON.stringify( p, null, 2 ) ], { type: 'application/json' } );
					var url = URL.createObjectURL( blob );
					var a = document.createElement( 'a' );
					a.href = url;
					a.download = 'liebherr-intelligence-world-protokoll-' + ( p.session_code || 'sitzung' ) + '.json';
					document.body.appendChild( a );
					a.click();
					document.body.removeChild( a );
					setTimeout( function () { URL.revokeObjectURL( url ); }, 0 );
				} );
			}
			var printBtn = $( '[data-liw-iw-proto-print]', proto );
			if ( printBtn ) {
				printBtn.addEventListener( 'click', function () {
					root.setAttribute( 'data-liw-print', 'protocol' );
					window.print();
				} );
			}
		}

		endBtn.addEventListener( 'click', function () {
			if ( ! sessionCode ) { return; }
			endBtn.disabled = true;
			stopTimers();
			var code = sessionCode;
			post( 'session/end', { session_code: code } ).then( function ( res ) {
				if ( res && res.ok && res.status ) { syncFromStatus( res.status ); }
				return get( 'session/protocol', { session_code: code } );
			} ).then( function ( res ) {
				if ( res && res.ok && res.protocol ) { renderProtocol( res.protocol ); }
			} ).catch( function () {} );
		} );

		// Kostenpflichtige Module/Rechenlast nutzen (§6.4/§8): Ereignis + Kosten, Zusatzkosten mitzählen.
		var modTotalEl = $( '[data-liw-iw-modtotal]', root );
		var modulesTotal = 0;
		[].forEach.call( root.querySelectorAll( '[data-liw-iw-use]' ), function ( btn ) {
			btn.addEventListener( 'click', function () {
				if ( ! sessionCode ) { return; }
				btn.disabled = true;
				post( 'session/use', { session_code: sessionCode, action: btn.getAttribute( 'data-liw-iw-use' ) } ).then( function ( res ) {
					if ( res && res.ok ) {
						modulesTotal += ( res.cost_minor | 0 );
						if ( modTotalEl ) { modTotalEl.textContent = money( modulesTotal, cur ); }
						if ( res.status ) { syncFromStatus( res.status ); }
					}
					btn.disabled = false;
				} ).catch( function () { btn.disabled = false; } );
			} );
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
