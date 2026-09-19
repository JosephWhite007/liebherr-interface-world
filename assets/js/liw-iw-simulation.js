/**
 * Liebherr Intelligence World – Simulation Builder (Frontend, progressive Enhancement).
 *
 * Spiegelt die reine PHP-Engine `IntelligenceWorld\SimulationModel::forecast()` 1:1 und rechnet bei jeder
 * Eingabe (Segment / Szenario / Zeithorizont) live nach – ohne Server-Roundtrip. Ohne dieses Skript bleibt
 * die serverseitig gerenderte Default-Prognose sichtbar. PROTOTYP: Beispieldaten/-modell (§21).
 */
( function () {
	'use strict';

	var SCEN_PCT = { conservative: 70, base: 100, ambitious: 130 };
	var SCEN_LABEL = { conservative: 'Konservativ (A)', base: 'Basis (B)', ambitious: 'Ambitioniert (C)' };

	function forecast( base, growthPermille, periods, scenario ) {
		base = Math.max( 0, base | 0 );
		var growth = Math.min( 1000, Math.max( 0, growthPermille | 0 ) );
		periods = Math.min( 60, Math.max( 1, periods | 0 ) );
		if ( ! SCEN_PCT.hasOwnProperty( scenario ) ) { scenario = 'base'; }
		var pct = SCEN_PCT[ scenario ];
		var eff = Math.floor( growth * pct / 100 );   // effektives Wachstum ‰ (wie intdiv in PHP)
		var factor = 1 + ( eff / 1000 );
		var values = [];
		for ( var i = 1; i <= periods; i++ ) {
			values.push( Math.round( base * Math.pow( factor, i ) ) );
		}
		var end = values[ periods - 1 ];
		var total = values.reduce( function ( a, b ) { return a + b; }, 0 );
		var delta = base > 0 ? Math.round( ( end - base ) * 1000 / base ) : 0;
		return { scenario: scenario, periods: periods, base: base, effective_permille: eff,
			values: values, end: end, total: total, delta_permille: delta };
	}

	function num( v ) {
		v = Math.round( v );
		return v.toLocaleString( 'de-DE', { maximumFractionDigits: 0 } );
	}
	function pct( permille ) {
		return ( permille / 10 ).toLocaleString( 'de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 1 } );
	}
	function esc( s ) {
		return String( s ).replace( /[&<>"]/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ c ];
		} );
	}
	function f( v ) { return ( Math.round( v * 100 ) / 100 ).toString(); }

	function barsSvg( values ) {
		var n = values.length;
		if ( 0 === n ) { return ''; }
		var max = Math.max.apply( null, values.concat( [ 1 ] ) );
		var w = 100, h = 40, gap = n > 1 ? 1.5 : 0;
		var bw = ( w - gap * ( n - 1 ) ) / n;
		var rects = '';
		for ( var i = 0; i < n; i++ ) {
			var bh = ( values[ i ] / max ) * ( h - 2 );
			var x = i * ( bw + gap );
			rects += '<rect class="liw-iw__sim-bar" x="' + f( x ) + '" y="' + f( h - bh ) +
				'" width="' + f( bw ) + '" height="' + f( Math.max( 0, bh ) ) + '" rx="0.4" />';
		}
		return '<svg class="liw-iw__sim-chart" viewBox="0 0 ' + w + ' ' + h +
			'" preserveAspectRatio="none" role="img" aria-label="Prognose-Balkendiagramm" focusable="false">' +
			rects + '</svg>';
	}

	function tableHtml( values ) {
		var rows = '';
		for ( var i = 0; i < values.length; i++ ) {
			rows += '<tr><td>' + ( i + 1 ) + '</td><td>' + esc( num( values[ i ] ) ) + '</td></tr>';
		}
		return '<details class="liw-iw__sim-tablewrap"><summary class="liw-iw__sim-tabletoggle">Werte anzeigen</summary>' +
			'<table class="liw-iw__sim-table"><thead><tr><th scope="col">Periode</th><th scope="col">Wert</th></tr></thead>' +
			'<tbody>' + rows + '</tbody></table></details>';
	}

	function outHtml( fc, segmentLabel ) {
		var sign = fc.delta_permille >= 0 ? '+' : '';
		var summary = '<p class="liw-iw__sim-summary">' +
			'<span class="liw-iw__sim-badge">' + esc( segmentLabel ) + '</span>' +
			'<span class="liw-iw__sim-badge liw-iw__sim-badge--scen">' + esc( SCEN_LABEL[ fc.scenario ] || 'Basis (B)' ) + '</span> ' +
			'Start ' + esc( num( fc.base ) ) + ' → Ende ' + esc( num( fc.end ) ) +
			' (' + esc( sign + pct( fc.delta_permille ) ) + ' % über den Zeitraum, effektives Wachstum ' +
			esc( pct( fc.effective_permille ) ) + ' % je Periode).</p>';
		return summary + barsSvg( fc.values ) + tableHtml( fc.values );
	}

	var STORE_KEY = 'liwIwSimSaved';
	function loadSaved() {
		try { var v = window.localStorage.getItem( STORE_KEY ); return v ? ( JSON.parse( v ) || [] ) : []; } catch ( e ) { return []; }
	}
	function storeSaved( arr ) {
		try { window.localStorage.setItem( STORE_KEY, JSON.stringify( arr.slice( 0, 6 ) ) ); } catch ( e ) {}
	}

	function renderSaved( savedEl ) {
		if ( ! savedEl ) { return; }
		var list = loadSaved();
		if ( ! list.length ) { savedEl.setAttribute( 'hidden', 'hidden' ); savedEl.innerHTML = ''; return; }
		var rows = list.map( function ( s, i ) {
			return '<label class="liw-iw__sim-saveditem"><input type="checkbox" data-liw-sim-cmp="' + i + '" checked> ' +
				esc( s.segmentLabel ) + ' · ' + esc( SCEN_LABEL[ s.scenario ] || s.scenario ) + ' · ' + ( s.periods | 0 ) + 'P ' +
				'(' + esc( num( s.end ) ) + ')</label>';
		} ).join( '' );
		savedEl.innerHTML =
			'<h4 class="liw-iw__proto-subtitle">Gespeicherte Szenarien (' + list.length + ')</h4>' +
			'<div class="liw-iw__sim-savedlist">' + rows + '</div>' +
			'<div class="liw-iw__sim-savedactions">' +
			'<button type="button" class="liw-cta liw-cta--secondary" data-liw-sim-compare>Vergleichen</button> ' +
			'<button type="button" class="liw-cta liw-cta--secondary" data-liw-sim-clear>Alle löschen</button></div>' +
			'<div class="liw-iw__sim-compare" data-liw-sim-compare-out></div>';
		savedEl.removeAttribute( 'hidden' );
	}

	function renderCompare( savedEl ) {
		var list = loadSaved();
		var sel = [];
		savedEl.querySelectorAll( '[data-liw-sim-cmp]:checked' ).forEach( function ( c ) {
			var idx = parseInt( c.getAttribute( 'data-liw-sim-cmp' ), 10 );
			if ( list[ idx ] ) { sel.push( list[ idx ] ); }
		} );
		var out = savedEl.querySelector( '[data-liw-sim-compare-out]' );
		if ( ! out ) { return; }
		if ( sel.length < 2 ) { out.innerHTML = '<p>Bitte mindestens zwei Szenarien auswählen.</p>'; return; }
		function row( label, vals ) { return '<tr><th scope="row">' + esc( label ) + '</th>' + vals.map( function ( v ) { return '<td>' + esc( v ) + '</td>'; } ).join( '' ) + '</tr>'; }
		var head = '<tr><th></th>' + sel.map( function ( s ) { return '<th>' + esc( s.segmentLabel ) + '</th>'; } ).join( '' ) + '</tr>';
		var body =
			row( 'Szenario', sel.map( function ( s ) { return SCEN_LABEL[ s.scenario ] || s.scenario; } ) ) +
			row( 'Horizont', sel.map( function ( s ) { return ( s.periods | 0 ) + ' Perioden'; } ) ) +
			row( 'Start', sel.map( function ( s ) { return num( s.base ); } ) ) +
			row( 'Ende', sel.map( function ( s ) { return num( s.end ); } ) ) +
			row( 'Δ gesamt', sel.map( function ( s ) { return ( s.delta_permille >= 0 ? '+' : '' ) + pct( s.delta_permille ) + ' %'; } ) );
		out.innerHTML = '<table class="liw-iw__sim-table liw-iw__sim-cmptable"><thead>' + head + '</thead><tbody>' + body + '</tbody></table>';
	}

	function bind( root ) {
		var segEl = root.querySelector( '[data-liw-sim-segment]' );
		var horEl = root.querySelector( '[data-liw-sim-horizon]' );
		var outEl = root.querySelector( '[data-liw-sim-out]' );
		if ( ! segEl || ! horEl || ! outEl ) { return; }
		var savedEl = root.querySelector( '[data-liw-sim-saved]' );
		var last = null;

		function recompute() {
			var opt = segEl.options[ segEl.selectedIndex ];
			var base = parseInt( opt.getAttribute( 'data-base' ), 10 ) || 0;
			var growth = parseInt( opt.getAttribute( 'data-growth' ), 10 ) || 0;
			var periods = parseInt( horEl.value, 10 ) || 12;
			var scenEl = root.querySelector( '[data-liw-sim-scenario]:checked' );
			var scenario = scenEl ? scenEl.value : 'base';
			var fc = forecast( base, growth, periods, scenario );
			outEl.innerHTML = outHtml( fc, opt.textContent || '' );
			last = { segmentLabel: opt.textContent || '', scenario: fc.scenario, periods: fc.periods, base: fc.base, end: fc.end, delta_permille: fc.delta_permille };
		}

		segEl.addEventListener( 'change', recompute );
		horEl.addEventListener( 'change', recompute );
		root.querySelectorAll( '[data-liw-sim-scenario]' ).forEach( function ( r ) {
			r.addEventListener( 'change', recompute );
		} );

		var saveBtn = root.querySelector( '[data-liw-sim-save]' );
		if ( saveBtn && savedEl ) {
			saveBtn.addEventListener( 'click', function () {
				if ( ! last ) { return; }
				var arr = loadSaved();
				arr.unshift( last );
				storeSaved( arr );
				renderSaved( savedEl );
			} );
			savedEl.addEventListener( 'click', function ( e ) {
				if ( e.target.closest( '[data-liw-sim-compare]' ) ) { renderCompare( savedEl ); }
				else if ( e.target.closest( '[data-liw-sim-clear]' ) ) { storeSaved( [] ); renderSaved( savedEl ); }
			} );
			renderSaved( savedEl );
		}

		recompute(); // einmal initial (ersetzt die Server-Default-Ausgabe durch die identische JS-Ausgabe)
	}

	function init() {
		var roots = document.querySelectorAll( '[data-liw-sim]' );
		Array.prototype.forEach.call( roots, bind );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
