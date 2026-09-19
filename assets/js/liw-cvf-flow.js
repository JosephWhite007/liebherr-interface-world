/**
 * Liebherr World – Customer View Flow (Durchstich).
 * Treibt Eingang → Challenge → Modulauswahl → First-Entry → Modul über die liw-cvf/v1-REST-Endpunkte.
 * Serverseitige Prüfung von Code/Challenge; der Client hält nur eine anonyme Besucher-ID.
 */
( function () {
	'use strict';
	var CFG = window.liwCvf || null;
	if ( ! CFG ) { return; }
	var I = CFG.i18n || {};
	var root = document.querySelector( '[data-liw-cvf]' );
	if ( ! root ) { return; }

	var anon = getAnon();
	var currentModule = null;

	function getAnon() {
		var k = 'liwCvfVisitor', v = '';
		try { v = sessionStorage.getItem( k ) || ''; } catch ( e ) {}
		if ( ! v ) {
			v = 'v-' + Date.now().toString( 36 ) + '-' + Math.random().toString( 36 ).slice( 2, 10 );
			try { sessionStorage.setItem( k, v ); } catch ( e ) {}
		}
		return v;
	}

	function esc( s ) {
		return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function api( path, body ) {
		body = body || {};
		body.anon = anon;
		return fetch( CFG.rest + path, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify( body )
		} ).then( function ( r ) { return r.json(); } );
	}

	function loading() { root.innerHTML = '<p class="liw-cvf__loading">' + esc( I.loading ) + '</p>'; }

	function render( data, note ) {
		if ( ! data || ! data.step ) { root.innerHTML = '<p class="liw-cvf__err">' + esc( I.error ) + '</p>'; return; }
		var step = data.step, t = step.type, h = '';
		var noteHtml = note ? '<p class="liw-cvf__note">' + esc( note ) + '</p>' : '';

		if ( t === 'entry' ) {
			h = '<h2 class="liw-cvf__title">' + esc( I.entryTitle ) + '</h2><p>' + esc( I.entryLead ) + '</p>' + noteHtml +
				'<form class="liw-cvf__form" data-step="code"><label for="liw-cvf-code">' + esc( I.code ) + '</label>' +
				'<input id="liw-cvf-code" name="code" type="text" autocomplete="off" required />' +
				'<button type="submit">' + esc( I.continue ) + '</button></form>';
		} else if ( t === 'challenge' ) {
			var q = step.challenge ? step.challenge.question : '';
			var tok = step.challenge ? step.challenge.token : '';
			h = '<h2 class="liw-cvf__title">' + esc( I.entryTitle ) + '</h2><p>' + esc( I.challengeLead ) + '</p>' + noteHtml +
				'<p class="liw-cvf__q">' + esc( q ) + '</p>' +
				'<form class="liw-cvf__form" data-step="challenge" data-token="' + esc( tok ) + '"><label for="liw-cvf-ans">' + esc( I.answer ) + '</label>' +
				'<input id="liw-cvf-ans" name="answer" type="number" inputmode="numeric" autocomplete="off" required />' +
				'<button type="submit">' + esc( I.continue ) + '</button></form>';
		} else if ( t === 'module_select' ) {
			var mods = step.modules || [];
			h = '<h2 class="liw-cvf__title">' + esc( I.moduleTitle ) + '</h2><p>' + esc( I.moduleLead ) + '</p>' + noteHtml + '<ul class="liw-cvf__modules">';
			mods.forEach( function ( m ) {
				h += '<li><button type="button" class="liw-cvf__mod" data-key="' + esc( m.key ) + '">' + esc( m.label ) + '</button></li>';
			} );
			h += '</ul>';
		} else if ( t === 'first_entry' ) {
			currentModule = step.module || currentModule;
			var lbl = currentModule ? currentModule.label : '';
			var feTitle = ( step.first_entry && step.first_entry.title ) ? step.first_entry.title : '';
			var feBody = ( step.first_entry && step.first_entry.body ) ? step.first_entry.body : '';
			h = '<h2 class="liw-cvf__title">' + esc( feTitle || I.firstEntry ) + '</h2><p>' + esc( lbl ) + '</p>' +
				( feBody ? '<p class="liw-cvf__fe-body">' + esc( feBody ) + '</p>' : '' ) + noteHtml +
				'<button type="button" class="liw-cvf__enter" data-step="first">' + esc( I.enter ) + '</button>';
		} else if ( t === 'done' ) {
			var target = step.target || '';
			h = '<h2 class="liw-cvf__title">' + esc( I.done ) + '</h2>';
			if ( target ) { h += '<p><a class="liw-cvf__open" href="' + esc( target ) + '">' + esc( I.open ) + '</a></p>'; }
			root.innerHTML = h;
			if ( target ) { setTimeout( function () { location.href = target; }, 1200 ); }
			return;
		} else if ( t === 'blocked' ) {
			root.innerHTML = '<h2 class="liw-cvf__title">' + esc( I.blocked ) + '</h2>';
			return;
		}
		root.innerHTML = h;
	}

	function reasonNote( r ) {
		if ( r === 'wrong' ) { return I.wrongCode; }
		if ( r === 'locked' ) { return I.locked; }
		return I.error;
	}

	root.addEventListener( 'submit', function ( e ) {
		var f = e.target.closest( 'form.liw-cvf__form' );
		if ( ! f ) { return; }
		e.preventDefault();
		var btn = f.querySelector( 'button' ); if ( btn ) { btn.disabled = true; }
		if ( f.getAttribute( 'data-step' ) === 'code' ) {
			var code = f.querySelector( 'input[name=code]' ).value;
			loading();
			api( 'code', { code: code } ).then( function ( d ) {
				if ( d.ok ) { render( d ); } else { begin( reasonNote( d.reason ) ); }
			} ).catch( fail );
		} else if ( f.getAttribute( 'data-step' ) === 'challenge' ) {
			var token = f.getAttribute( 'data-token' );
			var ans = parseInt( f.querySelector( 'input[name=answer]' ).value, 10 );
			loading();
			api( 'challenge', { token: token, answer: isNaN( ans ) ? -999 : ans } ).then( function ( d ) {
				if ( d.ok ) { render( d ); } else { render( { step: d.step || { type: 'challenge' } }, I.wrongCalc ); }
			} ).catch( fail );
		}
	} );

	root.addEventListener( 'click', function ( e ) {
		var mod = e.target.closest( '.liw-cvf__mod' );
		if ( mod ) {
			loading();
			api( 'module', { module: mod.getAttribute( 'data-key' ) } ).then( function ( d ) {
				if ( d.step && d.step.module ) { currentModule = d.step.module; }
				render( d );
			} ).catch( fail );
			return;
		}
		var enter = e.target.closest( '.liw-cvf__enter' );
		if ( enter ) {
			enter.disabled = true;
			loading();
			api( 'first-entry', { module: currentModule ? currentModule.key : '' } ).then( render ).catch( fail );
		}
	} );

	function fail() { root.innerHTML = '<p class="liw-cvf__err">' + esc( I.error ) + '</p>'; }

	function begin( note ) {
		loading();
		api( 'begin', {} ).then( function ( d ) { render( d, note ); } ).catch( fail );
	}

	begin( '' );
}() );
