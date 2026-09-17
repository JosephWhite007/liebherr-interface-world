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
				'liebherr-interface-world'
			);
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( sprintf( '[liebherr-interface-world][audit-fallback] %s liw_%s#%d', $action, $entity_type, $entity_id ) );
	}
}
