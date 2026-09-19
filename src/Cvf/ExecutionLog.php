<?php
/**
 * Liebherr World – CAPDB Plugin-Ausführungsprotokoll (Pflichtenheft §30, append-only).
 *
 * Serverseitig protokollierte Plugin-Ausführungen (state/opened/closed/result/safeErrorCode). Voll
 * auditierbar (§31.1). Zustandsübergänge werden gegen {@see PluginState} geprüft, bevor sie protokolliert
 * werden.
 *
 * @package Liebherr\InterfaceWorld\Cvf
 * @since   0.1.0-alpha.92
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Cvf;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ExecutionLog {

	/**
	 * Protokolliert eine Plugin-Ausführung append-only.
	 *
	 * @return int Datensatz-ID (0 bei ungültigem Zustand).
	 */
	public static function record( int $session_id, int $instance_id, string $state, ?string $result = null, ?string $safe_error = null ): int {
		if ( ! PluginState::is_valid( $state ) ) {
			return 0;
		}
		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s' );
		$wpdb->insert( BoardSchema::plugin_execution_table(), [ // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'session_id'      => $session_id,
			'instance_id'     => $instance_id,
			'state'           => $state,
			'opened_at'       => PluginState::OPEN === $state ? $now : null,
			'closed_at'       => in_array( $state, [ PluginState::CLOSED, PluginState::COMPLETED ], true ) ? $now : null,
			'result'          => $result,
			'safe_error_code' => $safe_error,
			'created_at'      => $now,
		], [ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ] );
		return (int) $wpdb->insert_id;
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent( int $limit = 50 ): array {
		global $wpdb;
		$t     = BoardSchema::plugin_execution_table();
		$limit = max( 1, min( 200, $limit ) );
		$r     = $wpdb->get_results( $wpdb->prepare( "SELECT session_id, instance_id, state, opened_at, closed_at, result, safe_error_code, created_at FROM {$t} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $r ) ? $r : [];
	}

	public static function count_for_session( int $session_id ): int {
		global $wpdb;
		$t = BoardSchema::plugin_execution_table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE session_id = %d", $session_id ) ); // phpcs:ignore WordPress.DB
	}
}
