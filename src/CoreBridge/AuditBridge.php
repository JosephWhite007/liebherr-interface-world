<?php
/**
 * Liebherr Interface Solutions – Audit Bridge
 *
 * Dünner Wrapper um `Araliya\Platform\Core\Modules\Audit\AuditService::log()` (verifiziert:
 * generische Signatur, keine Guest-/Health-Bindung) – kein eigenes Audit-System
 * (Entscheidung JW 17.09.2026, Variante A; CLAUDE.md SEC-005/Abschnitt 5).
 *
 * @package Liebherr\InterfaceWorld\CoreBridge
 * @since   0.1.0-alpha.1
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\CoreBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AuditBridge {

	private const AUDIT_SERVICE_CLASS = 'Araliya\\Platform\\Core\\Modules\\Audit\\AuditService';

	public static function is_available(): bool {
		return class_exists( self::AUDIT_SERVICE_CLASS );
	}

	/**
	 * Protokolliert eine administrative Änderung. Fällt bei fehlendem Core auf `error_log`
	 * zurück (Fehlervermeidung: kein stiller Verlust der Nachvollziehbarkeit, SEC-005),
	 * bricht aber niemals den Hauptprozess ab (identisches Prinzip wie im Core-Original).
	 *
	 * Bugfix 18.09.2026 (Docker-Praxistest, siehe LOGBUCH_TECHNIK.md): der 7. Parameter von
	 * `AuditService::log()` ist `string $actor_type` (Spalte `actor_type` VARCHAR(20) in
	 * `{$wpdb->prefix}ary_audit_log`, s. `AuditSchema.php`) – kein Modul-/Plugin-Bezeichner.
	 * Der bisherige Aufruf übergab hier fälschlich den Plugin-Slug `'liebherr-interface-world'`
	 * (24 Zeichen), was bei jedem Aufruf am `VARCHAR(20)`-Limit scheiterte („value too long").
	 * Konvention aus dem Core selbst übernommen (s. `PlatformResetService::log()`-Aufruf):
	 * `'admin'` für angemeldete Board-Aktionen, `'system'` für anonyme/automatisierte Vorgänge
	 * (actor_id 0, z. B. das öffentliche Onboarding-Formular).
	 *
	 * @param array<string, mixed> $old_values
	 * @param array<string, mixed> $new_values
	 */
	public static function log(
		string $action,
		string $entity_type,
		int $entity_id,
		array $old_values = [],
		array $new_values = [],
		int $actor_id = 0
	): void {
		if ( self::is_available() ) {
			call_user_func(
				[ self::AUDIT_SERVICE_CLASS, 'log' ],
				$action,
				'liw_' . $entity_type,
				$entity_id,
				$old_values,
				$new_values,
				$actor_id,
				$actor_id > 0 ? 'admin' : 'system'
			);
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( sprintf( '[liebherr-interface-world][audit-fallback] %s liw_%s#%d', $action, $entity_type, $entity_id ) );
	}

	/** Tabellenname des Core-Audit-Logs. */
	private static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'ary_audit_log';
	}

	/** Anzahl der LIW-Audit-Ereignisse (entity_type mit Präfix `liw_`). */
	public static function count_liw_events(): int {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE entity_type LIKE 'liw\\_%'" );
	}

	/**
	 * Jüngste LIW-Audit-Ereignisse (Lese-Ansicht Audit Board). Nur eigene (`liw_`) Einträge.
	 *
	 * @return array<int,array<string,mixed>> Spalten: created_at, actor_id, actor_type, action, entity_type, entity_id
	 */
	public static function recent_liw_events( int $limit = 100, int $offset = 0 ): array {
		global $wpdb;
		$table = self::table();
		$limit  = max( 1, min( 500, $limit ) );
		$offset = max( 0, $offset );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT created_at, actor_id, actor_type, action, entity_type, entity_id FROM {$table} WHERE entity_type LIKE 'liw\\_%' ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", $limit, $offset ), ARRAY_A );
		return is_array( $rows ) ? $rows : [];
	}
}
