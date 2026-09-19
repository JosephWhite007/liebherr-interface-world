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
		if ( a.id ) {
			var open = el( 'a', 'liw-cta liw-cta--secondary liw-adv__open' );
			open.href = window.location.pathname + '?adv=' + encodeURIComponent( a.id );
			var tv = parseInt( a.token_value, 10 ) || 0;
			open.textContent = tv > 0
				? ( ( cfg.i18n.detailFor || 'Details ansehen' ) + ' · ' + tv + ' ' + ( cfg.i18n.tokens || 'Tokens' ) )
				: ( cfg.i18n.detail || 'Details ansehen' );
			body.appendChild( open );
		}
		li.appendChild( body );
		return li;
	}

	function refreshStream( root ) {
		var stream = $( '[data-liw-adv-stream]', root );
		if ( ! stream ) { return; }
		var q = 'stream?limit=24';
		[ 'type', 'urgency', 'search', 'machine', 'component' ].forEach( function ( key ) {
			var v = ( $( '[data-liw-adv-filter="' + key + '"]', root ) || {} ).value || '';
			if ( v ) { q += '&' + key + '=' + encodeURIComponent( v ); }
		} );
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

		var rightsEl = $( '[data-liw-adv-rights]', root );
		[].forEach.call( root.querySelectorAll( '[data-liw-adv-submit]' ), function ( btn ) {
			btn.addEventListener( 'click', function () {
				var action = btn.getAttribute( 'data-liw-adv-submit' ); // draft | submit | register
				var title = ( $( '[data-liw-adv-title]', root ) || {} ).value || '';
				if ( ! title.trim() ) { msg.textContent = '⚠ ' + ( cfg.i18n.needTitle || 'Titel erforderlich.' ); return; }
				var wantsRegister = 'register' === action;
				var rights = !! ( rightsEl && rightsEl.checked );
				if ( wantsRegister && ! rights ) { msg.textContent = '⚠ ' + ( cfg.i18n.needRights || 'Rechte-Zusicherung erforderlich.' ); return; }
				msg.textContent = cfg.i18n.saving;
				api( 'create', { method: 'POST', auth: true, body: {
					title: title,
					story: ( $( '[data-liw-adv-story]', root ) || {} ).value || '',
					image_url: ( $( '[data-liw-adv-image]', root ) || {} ).value || '',
					type: ( $( '[data-liw-adv-type]', root ) || {} ).value || '',
					urgency: ( $( '[data-liw-adv-urgency]', root ) || {} ).value || '',
					visibility: ( $( '[data-liw-adv-visibility]', root ) || {} ).value || '',
					protection: ( $( '[data-liw-adv-protection]', root ) || {} ).value || 'region',
					machine: ( $( '[data-liw-adv-machine]', root ) || {} ).value || '',
					component: ( $( '[data-liw-adv-component]', root ) || {} ).value || '',
					token_value: parseInt( ( $( '[data-liw-adv-token]', root ) || {} ).value, 10 ) || 0,
					usage_scope: ( $( '[data-liw-adv-usage]', root ) || {} ).value || '',
					rights_confirmed: wantsRegister && rights,
					intent: wantsRegister ? 'submit' : action,
					lat: latEl.value !== '' ? parseFloat( latEl.value ) : null,
					lng: lngEl.value !== '' ? parseFloat( lngEl.value ) : null
				} } ).then( function ( res ) {
					var extra = '';
					if ( res && res.registration && res.registration.ok && res.registration.articlebook_ref ) {
						extra = ' · ' + ( cfg.i18n.articlebook || 'Artikelbook' ) + ': ' + res.registration.articlebook_ref;
					}
					msg.textContent = ( res && res.message ? res.message : ( res && res.ok ? 'OK' : ( res && res.error ) || 'Fehler' ) ) + extra;
					if ( res && res.ok ) { refreshStream( root ); }
				} ).catch( function () { msg.textContent = 'Fehler.'; } );
			} );
		} );
	}

	function init( root ) {
		var deb = null;
		[].forEach.call( root.querySelectorAll( '[data-liw-adv-filter]' ), function ( sel ) {
			sel.addEventListener( 'change', function () { refreshStream( root ); } );
			if ( 'text' === sel.type || 'search' === sel.type ) {
				sel.addEventListener( 'input', function () { window.clearTimeout( deb ); deb = window.setTimeout( function () { refreshStream( root ); }, 350 ); } );
			}
		} );
		initCreate( root );
	}

	// ── Tokenakzeptanz-Dialog beim Zugriff (§5/§6) ──
	var modal = null, modalPost = 0, modalDone = false;

	function t( k, fb ) { return ( cfg.i18n && cfg.i18n[ k ] ) || fb; }

	function getModal() {
		if ( modal ) { return modal; }
		modal = el( 'div', 'liw-advmodal' );
		modal.setAttribute( 'hidden', 'hidden' );
		modal.innerHTML =
			'<div class="liw-advmodal__backdrop" data-am-close></div>' +
			'<div class="liw-advmodal__box" role="dialog" aria-modal="true" aria-labelledby="liw-advmodal-title">' +
			'<button type="button" class="liw-advmodal__x" data-am-close aria-label="' + escAttr( t( 'close', 'Schließen' ) ) + '">×</button>' +
			'<h3 id="liw-advmodal-title" data-am-title></h3>' +
			'<p class="liw-advmodal__status" data-am-status></p>' +
			'<dl class="liw-advmodal__meta">' +
			'<dt>' + esc( t( 'dlgToken', 'Tokenwert' ) ) + '</dt><dd data-am-token></dd>' +
			'<dt>' + esc( t( 'dlgUsage', 'Nutzungsumfang' ) ) + '</dt><dd data-am-usage></dd>' +
			'<dt>' + esc( t( 'dlgVersion', 'Version' ) ) + '</dt><dd data-am-version></dd>' +
			'</dl>' +
			'<div class="liw-advmodal__terms" data-am-terms></div>' +
			'<label class="liw-advmodal__accept"><input type="checkbox" data-am-agree> <span>' + esc( t( 'dlgAgree', 'Ich habe den Tokenwert und die Nutzungsbedingungen gesehen und akzeptiere sie.' ) ) + '</span></label>' +
			'<div class="liw-advmodal__actions"><button type="button" class="liw-cta liw-cta--primary" data-am-confirm disabled>' + esc( t( 'dlgConfirm', 'Tokenverwendung bestätigen' ) ) + '</button></div>' +
			'<p class="liw-advmodal__msg" role="status" data-am-msg></p>' +
			'</div>';
		document.body.appendChild( modal );
		[].forEach.call( modal.querySelectorAll( '[data-am-close]' ), function ( c ) { c.addEventListener( 'click', closeModal ); } );
		document.addEventListener( 'keydown', function ( e ) { if ( 'Escape' === e.key && ! modal.hasAttribute( 'hidden' ) ) { closeModal(); } } );
		var agree = modal.querySelector( '[data-am-agree]' ), confirmBtn = modal.querySelector( '[data-am-confirm]' );
		agree.addEventListener( 'change', function () { confirmBtn.disabled = ! agree.checked; } );
		confirmBtn.addEventListener( 'click', confirmAccess );
		return modal;
	}

	function escAttr( s ) { return String( s == null ? '' : s ).replace( /"/g, '&quot;' ); }
	function esc( s ) { return String( s == null ? '' : s ).replace( /[&<>]/g, function ( c ) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;' }[ c ]; } ); }

	function closeModal() { if ( modal ) { modal.setAttribute( 'hidden', 'hidden' ); } }

	function openDialog( postId ) {
		var m = getModal();
		modalPost = postId; modalDone = false;
		m.querySelector( '[data-am-msg]' ).textContent = '';
		var agree = m.querySelector( '[data-am-agree]' ), confirmBtn = m.querySelector( '[data-am-confirm]' );
		agree.checked = false; agree.disabled = false; confirmBtn.disabled = true;
		confirmBtn.textContent = t( 'dlgConfirm', 'Tokenverwendung bestätigen' );
		m.querySelector( '[data-am-title]' ).textContent = t( 'dlgLoading', 'Wird geladen …' );
		m.querySelector( '[data-am-token]' ).textContent = '';
		m.querySelector( '[data-am-usage]' ).textContent = '';
		m.querySelector( '[data-am-version]' ).textContent = '';
		m.querySelector( '[data-am-status]' ).textContent = '';
		m.querySelector( '[data-am-terms]' ).textContent = t( 'dlgTerms', 'Mit der Bestätigung akzeptieren Sie den vom Ersteller festgelegten Tokenwert als Gegenleistung für den Zugriff. Bereits bestätigte Nutzungen werden nicht nachträglich durch Preisänderungen verändert. Der Vorgang wird protokolliert.' );
		m.removeAttribute( 'hidden' );

		api( 'access?post_id=' + encodeURIComponent( postId ), { auth: true } ).then( function ( res ) {
			var p = res && res.preview ? res.preview : null;
			if ( ! p ) { m.querySelector( '[data-am-msg]' ).textContent = t( 'dlgErr', 'Zugriffsdaten nicht verfügbar.' ); return; }
			m.querySelector( '[data-am-title]' ).textContent = p.title || '';
			m.querySelector( '[data-am-token]' ).textContent = p.is_author
				? t( 'dlgFreeAuthor', 'kostenfrei (eigener Beitrag)' )
				: ( ( parseInt( p.token_value, 10 ) || 0 ) + ' ' + t( 'tokens', 'Tokens' ) );
			m.querySelector( '[data-am-usage]' ).textContent = p.usage_label || '—';
			m.querySelector( '[data-am-version]' ).textContent = p.version || 1;
			if ( ! p.usable ) {
				m.querySelector( '[data-am-status]' ).textContent = t( 'dlgNotUsable', 'Dieser Beitrag ist (noch) nicht registriert/freigegeben.' );
				agree.disabled = true; confirmBtn.disabled = true;
			}
		} ).catch( function () { m.querySelector( '[data-am-msg]' ).textContent = t( 'dlgErr', 'Zugriffsdaten nicht verfügbar.' ); } );
	}

	function confirmAccess() {
		var m = getModal(), confirmBtn = m.querySelector( '[data-am-confirm]' ), msg = m.querySelector( '[data-am-msg]' );
		if ( modalDone ) { closeModal(); return; }
		confirmBtn.disabled = true; msg.textContent = t( 'saving', 'Wird gespeichert …' );
		api( 'accept', { method: 'POST', auth: true, body: { post_id: modalPost } } ).then( function ( res ) {
			if ( res && res.ok ) {
				modalDone = true;
				try { document.dispatchEvent( new CustomEvent( 'liw-adv-accepted', { detail: { postId: modalPost } } ) ); } catch ( e ) {}
				var charge = parseInt( res.charge, 10 ) || 0;
				msg.textContent = t( 'dlgOk', 'Zugriff protokolliert.' ) + ' · ' +
					( charge > 0 ? ( t( 'dlgCharged', 'belastet' ) + ': ' + charge + ' ' + t( 'tokens', 'Tokens' ) ) : t( 'dlgFree', 'ohne Belastung' ) ) +
					( res.transaction_id ? ' · ' + res.transaction_id : '' );
				confirmBtn.textContent = t( 'close', 'Schließen' ); confirmBtn.disabled = false;
			} else {
				msg.textContent = res && 'insufficient_budget' === res.error
					? t( 'dlgNoBudget', 'Nicht genügend Tokenbudget.' )
					: ( ( res && res.error ) || t( 'dlgErr', 'Zugriff fehlgeschlagen.' ) );
				confirmBtn.disabled = false;
			}
		} ).catch( function () { msg.textContent = t( 'dlgErr', 'Zugriff fehlgeschlagen.' ); confirmBtn.disabled = false; } );
	}

	function boot() {
		[].forEach.call( document.querySelectorAll( '[data-liw-adv]' ), init );
		document.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( '[data-liw-adv-open]' );
			if ( b ) { e.preventDefault(); openDialog( parseInt( b.getAttribute( 'data-liw-adv-open' ), 10 ) ); }
		} );
		// Nach bestätigtem Zugriff auf der Detailseite den Inhalt zeigen (Seite neu laden → Server rendert ihn).
		document.addEventListener( 'liw-adv-accepted', function ( e ) {
			var gate = document.querySelector( '[data-liw-adv-detail-gate]' );
			if ( gate && e.detail && parseInt( gate.getAttribute( 'data-liw-adv-detail-gate' ), 10 ) === ( e.detail.postId | 0 ) ) {
				setTimeout( function () { window.location.reload(); }, 700 );
			}
		} );
	}
	if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', boot ); } else { boot(); }
}() );
