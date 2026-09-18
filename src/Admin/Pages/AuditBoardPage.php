<?php
/**
 * Liebherr Interface Solutions – Audit Board (§18, SEC-005).
 *
 * Reine Lese-Ansicht der administrativen LIW-Audit-Ereignisse aus dem Core-Audit-Log
 * (`ary_audit_log`, unveränderlich – kein UPDATE/DELETE). Zeigt nur eigene Einträge (entity_type
 * mit Präfix `liw_`). Kein eigenes Audit-Datenmodell (CoreBridge, Variante A).
 *
 * Capability: `liw_manage_interfaces` (Interface-/System-Administration).
 *
 * @package Liebherr\InterfaceWorld\Admin\Pages
 * @since   0.1.0-alpha.31
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin\Pages;

use Liebherr\InterfaceWorld\Admin\AdminPagination;
use Liebherr\InterfaceWorld\CoreBridge\AuditBridge;
use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AuditBoardPage {

	public const MENU_SLUG = 'liw-audit-board';

	private const ITEMS_PER_PAGE = 50;

	public static function render(): void {
		if ( ! current_user_can( RoleBridge::CAP_MANAGE_INTERFACES ) ) {
			wp_die( esc_html__( 'Keine Berechtigung für das Audit Board.', 'liebherr-interface-world' ) );
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Audit Board – administrative Ereignisse', 'liebherr-interface-world' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Unveränderliches Protokoll der administrativen Änderungen in Interface World (Anlegen, Statuswechsel, Freigaben, Exporte …). Quelle: zentrales ARALIYA-Audit-Log, gefiltert auf dieses Modul.', 'liebherr-interface-world' ) . '</p>';

		if ( ! AuditBridge::is_available() ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Das zentrale Audit-Log ist nicht verfügbar (araliya-platform-core inaktiv).', 'liebherr-interface-world' ) . '</p></div></div>';
			return;
		}

		$page  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reine Leseansicht.
		$total = AuditBridge::count_liw_events();
		$rows  = AuditBridge::recent_liw_events( self::ITEMS_PER_PAGE, ( $page - 1 ) * self::ITEMS_PER_PAGE );

		echo '<table class="widefat striped"><thead><tr>';
		foreach ( [ 'Zeitpunkt (UTC)', 'Aktion', 'Objekt', 'Objekt-ID', 'Akteur', 'Akteurstyp' ] as $column ) {
			echo '<th>' . esc_html( $column ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( [] === $rows ) {
			echo '<tr><td colspan="6">' . esc_html__( 'Noch keine Ereignisse protokolliert.', 'liebherr-interface-world' ) . '</td></tr>';
		}

		foreach ( $rows as $row ) {
			$actor_id = (int) ( $row['actor_id'] ?? 0 );
			$actor    = $actor_id > 0 ? self::actor_label( $actor_id ) : '—';
			// entity_type ist mit `liw_` präfixiert (AuditBridge::log) – für die Anzeige entfernen.
			$entity = (string) ( $row['entity_type'] ?? '' );
			$entity = 0 === strpos( $entity, 'liw_' ) ? substr( $entity, 4 ) : $entity;

			echo '<tr>';
			echo '<td>' . esc_html( (string) ( $row['created_at'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $row['action'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( $entity ) . '</td>';
			echo '<td>' . esc_html( (string) ( $row['entity_id'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( $actor ) . '</td>';
			echo '<td>' . esc_html( (string) ( $row['actor_type'] ?? '' ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		AdminPagination::render( $page, $total, self::ITEMS_PER_PAGE );
		echo '</div>';
	}

	/** Lesbarer Akteur (Login oder ID). */
	private static function actor_label( int $actor_id ): string {
		$user = get_userdata( $actor_id );
		return $user instanceof \WP_User ? $user->user_login : ( '#' . $actor_id );
	}
}
