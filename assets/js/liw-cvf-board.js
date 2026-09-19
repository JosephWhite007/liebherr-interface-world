/**
 * Liebherr World – CAPDB visuelles Board (Pflichtenheft §24.2/§25).
 * Modulbaukasten + Timeline mit Plugin-Zonen. Drag-and-Drop UND gleichwertige Tastaturbedienung.
 * Alle Mutationen laufen über admin-ajax gegen dieselben Repository-Operationen wie die Tabellenansicht.
 */
( function () {
	'use strict';
	var CFG = window.liwCvfBoard || null;
	if ( ! CFG ) { return; }
	var I = CFG.i18n || {};
	var root = document.querySelector( '[data-liw-board]' );
	if ( ! root ) { return; }
	var zoom = 100;
	var lastData = null;

	function esc( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function call( op, extra ) {
		var body = new URLSearchParams();
		body.set( 'action', CFG.action );
		body.set( 'nonce', CFG.nonce );
		body.set( 'op', op );
		Object.keys( extra || {} ).forEach( function ( k ) { body.set( k, extra[ k ] ); } );
		return fetch( CFG.ajax, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() } )
			.then( function ( r ) { return r.json(); } );
	}

	function scopesOf( data, key ) {
		var t = ( data.types || [] ).filter( function ( x ) { return x.key === key; } )[ 0 ];
		return t ? String( t.scopes ).split( ',' ) : [];
	}

	function instancesFor( data, hostType, hostId ) {
		return ( data.instances || [] ).filter( function ( i ) { return i.host_type === hostType && Number( i.host_id ) === Number( hostId ); } );
	}

	function zoneHtml( data, label, hostType, hostId ) {
		var list = instancesFor( data, hostType, hostId ).map( function ( i ) {
			return '<li class="liw-board__inst" data-inst="' + i.id + '" data-key="' + esc( i.plugin_key ) + '" tabindex="0" title="' + esc( I.edit || 'Bearbeiten' ) + '"><span class="liw-board__inst-lbl">' + esc( i.plugin_key ) + '</span>' +
				' <button type="button" class="liw-board__rm" data-inst="' + i.id + '" title="' + esc( I.remove ) + '">×</button></li>';
		} ).join( '' );
		return '<div class="liw-board__zone" data-host-type="' + hostType + '" data-host-id="' + hostId + '">' +
			'<div class="liw-board__zone-h">' + esc( label ) + '</div>' +
			'<ul class="liw-board__zone-list">' + ( list || '<li class="liw-board__drop">' + esc( I.dropHere ) + '</li>' ) + '</ul>' +
			keyboardAdder( data, hostType, hostId ) +
			'</div>';
	}

	function keyboardAdder( data, hostType, hostId ) {
		var opts = ( data.types || [] ).filter( function ( t ) { return String( t.scopes ).split( ',' ).indexOf( hostType ) !== -1; } )
			.map( function ( t ) { return '<option value="' + esc( t.key ) + '">' + esc( t.label ) + '</option>'; } ).join( '' );
		if ( ! opts ) { return ''; }
		return '<div class="liw-board__kb"><select class="liw-board__kb-sel">' + opts + '</select>' +
			'<button type="button" class="liw-board__kb-add" data-host-type="' + hostType + '" data-host-id="' + hostId + '">' + esc( I.addKeyboard ) + '</button></div>';
	}

	function render( data ) {
		lastData = data;
		if ( ! data || ! data.areas || ! data.areas.length ) {
			root.innerHTML = '<p>' + esc( I.empty ) + '</p>';
			return;
		}
		var areas = data.areas.slice().sort( function ( a, b ) { return a.position - b.position; } );
		var chips = ( data.types || [] ).map( function ( t ) {
			return '<li class="liw-board__chip" draggable="true" data-key="' + esc( t.key ) + '" data-scopes="' + esc( t.scopes ) + '">' +
				esc( t.label ) + '<span class="liw-board__chip-cat">' + esc( t.category ) + '</span></li>';
		} ).join( '' );

		var areaCards = areas.map( function ( a ) {
			return '<div class="liw-board__area"><div class="liw-board__area-h">#' + a.position + ' ' + esc( a.module_id ) + '</div>' +
				zoneHtml( data, I.pageZone, 'page', a.id ) + '</div>';
		} ).join( '<div class="liw-board__arrow">→</div>' );

		var edgeCards = ( data.edges || [] ).map( function ( e ) {
			return '<div class="liw-board__edge"><div class="liw-board__edge-h">#' + e.from_area_id + ' → #' + e.to_area_id + ' (' + esc( e.trigger_type ) + ')</div>' +
				zoneHtml( data, I.edgeZone, 'edge', e.id ) + '</div>';
		} ).join( '' );

		root.innerHTML =
			'<div class="liw-board__bar"><button type="button" class="button liw-board__zi">+ ' + esc( I.zoomIn ) + '</button> ' +
			'<button type="button" class="button liw-board__zo">– ' + esc( I.zoomOut ) + '</button></div>' +
			'<div class="liw-board__wrap"><aside class="liw-board__baukasten"><h3>' + esc( I.baukasten ) + '</h3><ul>' + chips + '</ul></aside>' +
			'<div class="liw-board__canvas" style="zoom:' + zoom + '%">' +
			'<div class="liw-board__timeline">' + areaCards + '</div>' +
			'<div class="liw-board__edges">' + edgeCards + '</div>' +
			'</div></div>' +
			'<div class="liw-board__sim">' +
			'<h3>' + esc( I.simTitle || 'Simulation' ) + '</h3>' +
			'<div class="liw-board__sim-bar">' +
			'<button type="button" class="button liw-sim-play">▶ ' + esc( I.play || 'Start' ) + '</button> ' +
			'<button type="button" class="button liw-sim-pause">⏸ ' + esc( I.pause || 'Pause' ) + '</button> ' +
			'<button type="button" class="button liw-sim-step">⏭ ' + esc( I.step || 'Schritt' ) + '</button> ' +
			'<button type="button" class="button liw-sim-reset">⟲ ' + esc( I.reset || 'Zurücksetzen' ) + '</button> ' +
			'<label>×<select class="liw-sim-speed"><option>1</option><option>2</option><option>4</option><option>8</option></select></label> ' +
			'<span class="liw-sim-clock">0.0 s</span></div>' +
			'<div class="liw-sim-ruler"><div class="liw-sim-head"></div></div>' +
			'<ol class="liw-sim-log" role="log" aria-live="polite"></ol>' +
			'<p class="liw-board__note">' + esc( I.simNote || 'Reine Vorschau – es werden keine echten Freigaben, Nachrichten oder Aktionen ausgeführt.' ) + '</p>' +
			'</div>' +
			'<div class="liw-board__props" data-liw-props hidden></div>';
		bind( data );
		bindSim();
		bindProps();
	}

	// ── Eigenschaften-Panel (§24.2 Zone E): Instanz-Parameter + Zeitsteuerung inline bearbeiten. ──
	function typeByKey( key ) {
		return ( ( lastData && lastData.types ) || [] ).filter( function ( t ) { return t.key === key; } )[ 0 ] || null;
	}
	function instById( id ) {
		return ( ( lastData && lastData.instances ) || [] ).filter( function ( i ) { return Number( i.id ) === Number( id ); } )[ 0 ] || null;
	}
	function schedById( id ) {
		return ( lastData && lastData.schedules && lastData.schedules[ id ] ) ? lastData.schedules[ id ] : {};
	}
	function ms2s( v ) { return ( v == null || v === '' ) ? '' : ( Number( v ) / 1000 ); }

	function openProps( id ) {
		var panel = root.querySelector( '[data-liw-props]' );
		var ins = instById( id );
		if ( ! panel || ! ins ) { return; }
		var type = typeByKey( ins.plugin_key );
		var schema = ( type && type.schema ) ? type.schema : {};
		var cfg = ins.config || {};
		var sch = schedById( id );
		var fields = '';
		Object.keys( schema ).forEach( function ( f ) {
			var spec = schema[ f ] || {};
			var val = ( cfg[ f ] != null ) ? cfg[ f ] : ( spec['default'] != null ? spec['default'] : '' );
			var input;
			if ( spec.type === 'enum' ) {
				input = '<select data-cfg="' + esc( f ) + '">' + ( spec.values || [] ).map( function ( o ) {
					return '<option value="' + esc( o ) + '"' + ( String( o ) === String( val ) ? ' selected' : '' ) + '>' + esc( o ) + '</option>';
				} ).join( '' ) + '</select>';
			} else if ( spec.type === 'int' ) {
				input = '<input type="number" data-cfg="' + esc( f ) + '" value="' + esc( val ) + '" />';
			} else {
				input = '<input type="text" data-cfg="' + esc( f ) + '" value="' + esc( val ) + '" />';
			}
			fields += '<label class="liw-board__pf"><span>' + esc( f ) + '</span>' + input + '</label>';
		} );
		var repeat = sch.repeat_policy || 'once_per_version';
		var resume = sch.resume_policy || 'continue';
		panel.innerHTML =
			'<div class="liw-board__props-h">' + esc( I.propsTitle || 'Eigenschaften' ) + ': ' + esc( ins.plugin_key ) +
			' <button type="button" class="liw-board__props-x" title="' + esc( I.close || 'Schließen' ) + '">×</button></div>' +
			'<form data-inst="' + id + '"><fieldset><legend>' + esc( I.params || 'Parameter' ) + '</legend>' + ( fields || '<em>' + esc( I.noParams || 'Keine Parameter' ) + '</em>' ) + '</fieldset>' +
			'<fieldset><legend>' + esc( I.timing || 'Zeitsteuerung (Sek.)' ) + '</legend>' +
			'<label class="liw-board__pf"><span>open</span><input type="number" step="0.1" data-sch="open_at" value="' + esc( ms2s( sch.open_at_ms ) ) + '" /></label>' +
			'<label class="liw-board__pf"><span>close</span><input type="number" step="0.1" data-sch="close_at" value="' + esc( ms2s( sch.close_at_ms ) ) + '" /></label>' +
			'<label class="liw-board__pf"><span>duration</span><input type="number" step="0.1" data-sch="duration" value="' + esc( ms2s( sch.duration_ms ) ) + '" /></label>' +
			'<label class="liw-board__pf"><span>timeout</span><input type="number" step="0.1" data-sch="timeout" value="' + esc( ms2s( sch.timeout_ms ) ) + '" /></label>' +
			'<label class="liw-board__pf"><span>resume</span><select data-sch="resume_policy">' + [ 'continue', 'restart', 'cancel' ].map( function ( o ) { return '<option' + ( o === resume ? ' selected' : '' ) + '>' + o + '</option>'; } ).join( '' ) + '</select></label>' +
			'<label class="liw-board__pf"><span>repeat</span><select data-sch="repeat_policy">' + [ 'once_per_version', 'each_visit' ].map( function ( o ) { return '<option' + ( o === repeat ? ' selected' : '' ) + '>' + o + '</option>'; } ).join( '' ) + '</select></label>' +
			'</fieldset>' +
			'<p><button type="submit" class="button button-primary">' + esc( I.save || 'Speichern' ) + '</button></p></form>';
		panel.hidden = false;
		panel.scrollIntoView( { block: 'nearest' } );
		var x = panel.querySelector( '.liw-board__props-x' );
		if ( x ) { x.addEventListener( 'click', function () { panel.hidden = true; panel.innerHTML = ''; } ); }
		var form = panel.querySelector( 'form' );
		if ( form ) { form.addEventListener( 'submit', function ( e ) { e.preventDefault(); saveProps( form ); } ); }
	}

	function saveProps( form ) {
		var id = form.getAttribute( 'data-inst' );
		var cfg = {};
		form.querySelectorAll( '[data-cfg]' ).forEach( function ( el ) { cfg[ el.getAttribute( 'data-cfg' ) ] = el.value; } );
		var sched = { instance_id: id };
		form.querySelectorAll( '[data-sch]' ).forEach( function ( el ) { sched[ el.getAttribute( 'data-sch' ) ] = el.value; } );
		call( 'update_instance', { instance_id: id, config_json: JSON.stringify( cfg ) } ).then( function ( r ) {
			if ( ! r || ! r.success ) { window.alert( I.saveErr || 'Speichern fehlgeschlagen (Parameter?).' ); return; }
			call( 'set_schedule', sched ).then( function ( r2 ) {
				if ( r2 && r2.success ) { render( r2.data ); var pnl = root.querySelector( '[data-liw-props]' ); if ( pnl ) { pnl.hidden = false; } openProps( id ); }
			} );
		} );
	}

	function bindProps() {
		root.querySelectorAll( '.liw-board__inst' ).forEach( function ( li ) {
			var open = function ( e ) {
				if ( e.target.closest && e.target.closest( '.liw-board__rm' ) ) { return; }
				openProps( li.getAttribute( 'data-inst' ) );
			};
			li.addEventListener( 'click', open );
			li.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Enter' || e.key === ' ' ) { e.preventDefault(); open( e ); } } );
		} );
	}

	// ── Simulation (Abspielkopf, §27.1): rein clientseitige Vorschau, keine echten Aktionen. ──
	var simEvents = null, simClock = 0, simTimer = null, simMax = 0;
	function bindSim() {
		var play = root.querySelector( '.liw-sim-play' ), pause = root.querySelector( '.liw-sim-pause' ),
			step = root.querySelector( '.liw-sim-step' ), reset = root.querySelector( '.liw-sim-reset' ),
			speed = root.querySelector( '.liw-sim-speed' );
		if ( ! play ) { return; }
		function ensure( cb ) {
			if ( simEvents ) { cb(); return; }
			call( 'sim', {} ).then( function ( r ) {
				simEvents = ( r && r.success && r.data && r.data.events ) ? r.data.events : [];
				simMax = simEvents.reduce( function ( m, e ) { return Math.max( m, e.at_ms ); }, 0 ) + 2000;
				cb();
			} );
		}
		function tick() {
			var mult = parseInt( speed.value, 10 ) || 1;
			simClock += 200 * mult;
			paint();
			if ( simClock >= simMax ) { stop(); }
		}
		function start() { ensure( function () { if ( simTimer ) { return; } simTimer = window.setInterval( tick, 200 ); } ); }
		function stop() { if ( simTimer ) { window.clearInterval( simTimer ); simTimer = null; } }
		function doStep() { ensure( function () { simClock += 1000; paint(); } ); }
		function doReset() { stop(); simClock = 0; paint(); }
		function paint() {
			var clock = root.querySelector( '.liw-sim-clock' ); if ( clock ) { clock.textContent = ( simClock / 1000 ).toFixed( 1 ) + ' s'; }
			var head = root.querySelector( '.liw-sim-head' ); if ( head && simMax ) { head.style.left = Math.min( 100, ( simClock / simMax ) * 100 ) + '%'; }
			var log = root.querySelector( '.liw-sim-log' ); if ( ! log ) { return; }
			log.innerHTML = ( simEvents || [] ).filter( function ( e ) { return e.at_ms <= simClock; } ).map( function ( e ) {
				return '<li>' + ( e.at_ms / 1000 ).toFixed( 1 ) + ' s · ' + esc( e.type ) + ' · ' + esc( e.plugin_key ) + ' · ' + esc( e.host ) + '</li>';
			} ).join( '' );
		}
		play.addEventListener( 'click', start );
		pause.addEventListener( 'click', stop );
		step.addEventListener( 'click', doStep );
		reset.addEventListener( 'click', doReset );
	}

	function bind( data ) {
		// Drag-and-Drop.
		root.querySelectorAll( '.liw-board__chip' ).forEach( function ( chip ) {
			chip.addEventListener( 'dragstart', function ( e ) {
				e.dataTransfer.setData( 'text/plain', chip.getAttribute( 'data-key' ) );
				e.dataTransfer.setData( 'liw/scopes', chip.getAttribute( 'data-scopes' ) );
			} );
		} );
		root.querySelectorAll( '.liw-board__zone' ).forEach( function ( zone ) {
			zone.addEventListener( 'dragover', function ( e ) { e.preventDefault(); zone.classList.add( 'is-over' ); } );
			zone.addEventListener( 'dragleave', function () { zone.classList.remove( 'is-over' ); } );
			zone.addEventListener( 'drop', function ( e ) {
				e.preventDefault();
				zone.classList.remove( 'is-over' );
				var key = e.dataTransfer.getData( 'text/plain' );
				addInstance( key, zone.getAttribute( 'data-host-type' ), zone.getAttribute( 'data-host-id' ) );
			} );
		} );
		// Tastatur-Alternative.
		root.querySelectorAll( '.liw-board__kb-add' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var sel = btn.parentNode.querySelector( '.liw-board__kb-sel' );
				addInstance( sel.value, btn.getAttribute( 'data-host-type' ), btn.getAttribute( 'data-host-id' ) );
			} );
		} );
		// Entfernen.
		root.querySelectorAll( '.liw-board__rm' ).forEach( function ( b ) {
			b.addEventListener( 'click', function () { call( 'del_instance', { instance_id: b.getAttribute( 'data-inst' ) } ).then( okRender ); } );
		} );
		// Zoom.
		var zi = root.querySelector( '.liw-board__zi' ), zo = root.querySelector( '.liw-board__zo' );
		if ( zi ) { zi.addEventListener( 'click', function () { zoom = Math.min( 200, zoom + 10 ); render( data ); } ); }
		if ( zo ) { zo.addEventListener( 'click', function () { zoom = Math.max( 50, zoom - 10 ); render( data ); } ); }
	}

	function addInstance( key, hostType, hostId ) {
		call( 'add_instance', { plugin_key: key, host_type: hostType, host_id: hostId } ).then( function ( r ) {
			if ( r && r.success ) { render( r.data ); }
			else { window.alert( I.forbidden ); }
		} );
	}

	function okRender( r ) { if ( r && r.success ) { render( r.data ); } }

	call( 'snapshot', {} ).then( function ( r ) { if ( r && r.success ) { render( r.data ); } else { root.innerHTML = '<p>' + esc( I.empty ) + '</p>'; } } ).catch( function () {} );
}() );
