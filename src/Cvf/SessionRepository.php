<?php
/**
 * Liebherr World – Customer View Flow: Besucher-Sitzungen + Übergangsprotokoll (§9/§12/§17).
 *
 * Eine Sitzung ist an genau eine veröffentlichte Workflow-Version gebunden. Fortschritt läuft ausschließlich
 * über die reine {@see Runtime}: die Aufrufschicht prüft Ereignisse serverseitig (Zugangscode, Challenge) und
 * meldet nur das Ergebnis; jeder Übergang wird append-only protokolliert. Sichtbarkeit ≠ Autorisierung (§14).
 *
 * @package Liebherr\InterfaceWorld\Cvf
 * @since   0.1.0-alpha.83
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Cvf;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SessionRepository {

	public const DEFAULT_TTL = 3600;

	/**
	 * Startet (oder ersetzt) die Sitzung eines anonymen Besuchers: an die aktive Version gebunden, Zustand
	 * new → at_entry (Ereignis begin protokolliert).
	 *
	 * @return array{id:int,state:string,workflow_version_id:int}
	 */
	public static function start( string $anon_visitor_id, ?int $ttl = null ): array {
		global $wpdb;
		$active = WorkflowRepository::ensure_active();
		$ttl    = ( null === $ttl ) ? self::DEFAULT_TTL : max( 60, $ttl );
		$now    = time();

		// Bestehende Sitzung des Besuchers ersetzen (uniq_visitor).
		$existing = self::get_by_visitor( $anon_visitor_id );
		if ( null !== $existing ) {
			$wpdb->delete( Schema::session_table(), [ 'id' => $existing['id'] ], [ '%d' ] ); // phpcs:ignore WordPress.DB
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::session_table(),
			[
				'anon_visitor_id'     => $anon_visitor_id,
				'workflow_version_id' => $active['id'],
				'state'               => Runtime::start(),
				'issued_at'           => gmdate( 'Y-m-d H:i:s', $now ),
				'expires_at'          => gmdate( 'Y-m-d H:i:s', $now + $ttl ),
				'updated_at'          => gmdate( 'Y-m-d H:i:s', $now ),
			],
			[ '%s', '%d', '%s', '%s', '%s', '%s' ]
		);
		$id = (int) $wpdb->insert_id;
		self::advance( $id, Runtime::EV_BEGIN );
		$row = self::get( $id );
		return [
			'id'                  => $id,
			'state'               => $row ? $row['state'] : Runtime::start(),
			'workflow_version_id' => $active['id'],
		];
	}

	/**
	 * Wendet ein (bereits serverseitig geprüftes) Ereignis auf die Sitzung an und protokolliert den Übergang.
	 *
	 * @return array{ok:bool,state:string,action:string,reason:string}
	 */
	public static function advance( int $session_id, string $event ): array {
		global $wpdb;
		$session = self::get( $session_id );
		if ( null === $session ) {
			return [ 'ok' => false, 'state' => '', 'action' => 'none', 'reason' => 'unknown_session' ];
		}
		$version = WorkflowRepository::get( (int) $session['workflow_version_id'] );
		$config  = $version ? $version['config'] : WorkflowVersion::default_config();

		$from = (string) $session['state'];
		$res  = Runtime::next( $from, $event, $config );

		if ( $res['state'] !== $from ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				Schema::session_table(),
				[ 'state' => $res['state'], 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ],
				[ 'id' => $session_id ],
				[ '%s', '%s' ],
				[ '%d' ]
			);
		}
		// Append-only Protokoll (auch abgelehnte/ungültige Übergänge – Nachvollziehbarkeit).
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			Schema::log_table(),
			[
				'session_id'          => $session_id,
				'workflow_version_id' => (int) $session['workflow_version_id'],
				'event'               => $event,
				'from_state'          => $from,
				'to_state'            => (string) $res['state'],
				'action'              => (string) $res['action'],
				'reason'              => (string) $res['reason'],
				'created_at'          => gmdate( 'Y-m-d H:i:s' ),
			],
			[ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);
		return [ 'ok' => ( 'ok' === $res['reason'] ), 'state' => (string) $res['state'], 'action' => (string) $res['action'], 'reason' => (string) $res['reason'] ];
	}

	/** @return array<string,mixed>|null */
	public static function get( int $id ): ?array {
		global $wpdb;
		$t   = Schema::session_table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $row ?: null;
	}

	/** @return array<string,mixed>|null */
	public static function get_by_visitor( string $anon_visitor_id ): ?array {
		global $wpdb;
		$t   = Schema::session_table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE anon_visitor_id = %s", $anon_visitor_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $row ?: null;
	}

	public static function log_count( int $session_id ): int {
		global $wpdb;
		$t = Schema::log_table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE session_id = %d", $session_id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Jüngste Zustandsübergänge (neueste zuerst) für die Backoffice-Ansicht.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function recent_log( int $limit = 50 ): array {
		global $wpdb;
		$t     = Schema::log_table();
		$limit = max( 1, min( 200, $limit ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT session_id, event, from_state, to_state, action, reason, created_at FROM {$t} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $rows ) ? $rows : [];
	}
}
