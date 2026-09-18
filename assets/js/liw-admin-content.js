/**
 * Liebherr Interface Solutions – Content Board: Drag-&-Drop-Reihenfolge (§19, alpha.35).
 *
 * Macht die Abschnitts-Tabelle per jQuery-UI-Sortable ziehbar und speichert die neue Reihenfolge
 * (menu_order) sofort per AJAX (Nonce + Capability serverseitig geprüft). Fortschreitende
 * Verbesserung: ohne JS bleibt die Tabelle nutzbar (Reihenfolge weiterhin über das „Reihenfolge"-Feld
 * im Beitrags-Editor pflegbar).
 *
 * @since 0.1.0-alpha.35
 */
( function ( $ ) {
	'use strict';
	$( function () {
		var $table = $( 'table[data-liw-reorder]' );
		var $tbody = $table.find( 'tbody' );
		var cfg    = window.liwContentReorder || {};
		if ( ! $table.length || ! $tbody.length || ! $.fn.sortable || ! cfg.ajaxUrl ) {
			return;
		}

		$tbody.sortable( {
			items: '> tr[data-liw-id]',
			handle: '.liw-drag-handle',
			axis: 'y',
			cursor: 'grabbing',
			helper: function ( event, tr ) {
				var $cells = tr.children();
				var $helper = tr.clone();
				$helper.children().each( function ( i ) {
					$( this ).width( $cells.eq( i ).width() );
				} );
				return $helper;
			},
			update: function () {
				var order = $tbody.find( '> tr[data-liw-id]' ).map( function () {
					return $( this ).data( 'liw-id' );
				} ).get();

				$table.addClass( 'liw-reorder-busy' );
				$.post( cfg.ajaxUrl, {
					action: 'liw_reorder_sections',
					_wpnonce: $table.data( 'liw-nonce' ),
					order: order
				} ).done( function ( resp ) {
					if ( resp && resp.success ) {
						$tbody.find( '> tr[data-liw-id] .liw-order-num' ).each( function ( i ) {
							$( this ).text( ( i + 1 ) * 10 );
						} );
					}
				} ).always( function () {
					$table.removeClass( 'liw-reorder-busy' );
				} );
			}
		} );
	} );
}( jQuery ) );
