/**
 * Liebherr Adventures – Insel-Interaktion (§5/§7, MVP, alpha.51).
 * Filter → Stream neu laden; „Standort ermitteln" (Geolocation) → Drei-Wörter-Ort via REST;
 * Create (Entwurf/Einreichen) → REST. Serverseitige Wahrheit/Autorisierung; Client zeigt/aktualisiert nur.
 * Fortschreitende Verbesserung: Hero + initialer Stream funktionieren ohne JS.
 */
( function () {
	'use strict';
	var cfg = window.liwAdv || null;
	if ( ! cfg ) { return; }

	function $( s, r ) { return ( r || document ).querySelector( s ); }
	function el( tag, cls ) { var e = document.createElement( tag ); if ( cls ) { e.className = cls; } return e; }

	function api( path, opts ) {
		opts = opts || {};
		var headers = { 'Content-Type': 'application/json' };
		if ( opts.auth ) { headers['X-WP-Nonce'] = cfg.nonce; }
		return fetch( cfg.rest + path, {
			method: opts.method || 'GET',
			headers: headers,
			credentials: 'same-origin',
			body: opts.body ? JSON.stringify( opts.body ) : undefined
		} ).then( function ( r ) { return r.json(); } );
	}

	function buildCard( a ) {
		var li = el( 'li', 'liw-adv__card' );
		var media = el( 'span', 'liw-adv__card-media' + ( a.image ? '' : ' liw-adv__card-media--empty' ) );
		if ( a.image ) { media.style.backgroundImage = 'url(' + String( a.image ).replace( /"/g, '' ) + ')'; }
		li.appendChild( media );
		var body = el( 'span', 'liw-adv__card-body' );
		var badges = el( 'span', 'liw-adv__badges' );
		var bt = el( 'span', 'liw-adv__badge liw-adv__badge--type' ); bt.textContent = a.type_label || ''; badges.appendChild( bt );
		var bu = el( 'span', 'liw-adv__badge liw-adv__badge--urg liw-adv__badge--' + ( a.urgency || '' ) ); bu.textContent = a.urgency_label || ''; badges.appendChild( bu );
		body.appendChild( badges );
		var title = el( 'span', 'liw-adv__card-title' ); title.textContent = a.title || ''; body.appendChild( title );
		if ( a.words ) { var w = el( 'span', 'liw-adv__card-words' ); w.textContent = '/// ' + a.words; body.appendChild( w ); }
		var meta = el( 'span', 'liw-adv__card-meta' ); meta.textContent = [ a.region, a.date ].filter( Boolean ).join( ' · ' ); body.appendChild( meta );
		if ( a.story ) { var st = el( 'span', 'liw-adv__card-story' ); st.textContent = a.story; body.appendChild( st ); }
		li.appendChild( body );
		return li;
	}

	function refreshStream( root ) {
		var stream = $( '[data-liw-adv-stream]', root );
		if ( ! stream ) { return; }
		var type = ( $( '[data-liw-adv-filter="type"]', root ) || {} ).value || '';
		var urg = ( $( '[data-liw-adv-filter="urgency"]', root ) || {} ).value || '';
		var q = 'stream?limit=24' + ( type ? '&type=' + encodeURIComponent( type ) : '' ) + ( urg ? '&urgency=' + encodeURIComponent( urg ) : '' );
		api( q ).then( function ( res ) {
			stream.innerHTML = '';
			if ( ! res || ! res.ok || ! res.items || ! res.items.length ) {
				var empty = el( 'li', 'liw-adv__empty' ); empty.textContent = cfg.i18n.empty; stream.appendChild( empty ); return;
			}
			res.items.forEach( function ( a ) { stream.appendChild( buildCard( a ) ); } );
		} ).catch( function () {} );
	}

	function initCreate( root ) {
		var panel = $( '[data-liw-adv-create]', root );
		if ( ! panel ) { return; }
		var latEl = $( '[data-liw-adv-lat]', root ), lngEl = $( '[data-liw-adv-lng]', root ), wordsEl = $( '[data-liw-adv-words]', root ), msg = $( '[data-liw-adv-msg]', root );

		var locateBtn = $( '[data-liw-adv-locate]', root );
		function locate( lat, lng ) {
			api( 'locate', { method: 'POST', body: { lat: lat, lng: lng } } ).then( function ( res ) {
				if ( res && res.ok ) { wordsEl.textContent = '/// ' + res.words + ' · ' + res.region; latEl.value = res.lat; lngEl.value = res.lng; }
			} );
		}
		if ( locateBtn ) {
			locateBtn.addEventListener( 'click', function () {
				wordsEl.textContent = cfg.i18n.locating;
				if ( navigator.geolocation ) {
					navigator.geolocation.getCurrentPosition(
						function ( pos ) { locate( pos.coords.latitude, pos.coords.longitude ); },
						function () { wordsEl.textContent = cfg.i18n.geoErr; }
					);
				} else { wordsEl.textContent = cfg.i18n.geoErr; }
			} );
		}
		[ latEl, lngEl ].forEach( function ( inp ) {
			inp.addEventListener( 'change', function () {
				if ( latEl.value !== '' && lngEl.value !== '' ) { locate( parseFloat( latEl.value ), parseFloat( lngEl.value ) ); }
			} );
		} );

		[].forEach.call( root.querySelectorAll( '[data-liw-adv-submit]' ), function ( btn ) {
			btn.addEventListener( 'click', function () {
				var title = ( $( '[data-liw-adv-title]', root ) || {} ).value || '';
				if ( ! title.trim() ) { msg.textContent = '⚠ Titel erforderlich.'; return; }
				msg.textContent = cfg.i18n.saving;
				api( 'create', { method: 'POST', auth: true, body: {
					title: title,
					story: ( $( '[data-liw-adv-story]', root ) || {} ).value || '',
					image_url: ( $( '[data-liw-adv-image]', root ) || {} ).value || '',
					type: ( $( '[data-liw-adv-type]', root ) || {} ).value || '',
					urgency: ( $( '[data-liw-adv-urgency]', root ) || {} ).value || '',
					visibility: ( $( '[data-liw-adv-visibility]', root ) || {} ).value || '',
					protection: ( $( '[data-liw-adv-protection]', root ) || {} ).value || 'region',
					intent: btn.getAttribute( 'data-liw-adv-submit' ),
					lat: latEl.value !== '' ? parseFloat( latEl.value ) : null,
					lng: lngEl.value !== '' ? parseFloat( lngEl.value ) : null
				} } ).then( function ( res ) {
					msg.textContent = res && res.message ? res.message : ( res && res.ok ? 'OK' : ( res && res.error ) || 'Fehler' );
					if ( res && res.ok ) { refreshStream( root ); }
				} ).catch( function () { msg.textContent = 'Fehler.'; } );
			} );
		} );
	}

	function init( root ) {
		[].forEach.call( root.querySelectorAll( '[data-liw-adv-filter]' ), function ( sel ) {
			sel.addEventListener( 'change', function () { refreshStream( root ); } );
		} );
		initCreate( root );
	}

	function boot() {
		[].forEach.call( document.querySelectorAll( '[data-liw-adv]' ), init );
	}
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', boot ); } else { boot(); }
}() );
