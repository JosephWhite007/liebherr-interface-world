<?php
/**
 * Liebherr Intelligence World – Session-Service (Pflichtenheft-2 §5.3/§10.3/§13.1).
 *
 * Serverseitiger Sitzungslebenszyklus als „Abrechnungswahrheit" (§9): Start, Heartbeat, Pause, Resume, Ende
 * und Timeout-Kehrlauf. Jeder Schritt schreibt ein Ereignis ins manipulationsgeschützte Ledger (EventLog);
 * die abrechenbare aktive Dauer wird aus den Aktivitäts-Pings über SessionMeter berechnet (Pausen/Ende
 * schließen ein aktives Segment). Zeit in UTC, Beträge später als Minor-Units. Prototyp (§21): kein Payment.
 *
 * @package Liebherr\InterfaceWorld\IntelligenceWorld
 * @since   0.1.0-alpha.47
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\IntelligenceWorld;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SessionService {

	public const DEFAULT_TIMEOUT = 120; // Sekunden; administrativ überschreibbar (Option liw_iw_timeout).

	public static function timeout(): int {
		$t = (int) get_option( 'liw_iw_timeout', self::DEFAULT_TIMEOUT );
		return $t > 0 ? $t : self::DEFAULT_TIMEOUT;
	}

	private static function now(): string {
		return gmdate( 'Y-m-d H:i:s' );
	}

	private static function to_unix( string $utc ): int {
		return (int) strtotime( $utc . ' UTC' );
	}

	private static function generate_code(): string {
		return 'LIW-' . gmdate( 'Y' ) . '-' . strtoupper( substr( wp_generate_password( 10, false ), 0, 6 ) );
	}

	/**
	 * Startet eine Sitzung (nach bestätigtem Code + Zustimmung; die Bestätigung selbst erfolgt im Access Gate).
	 *
	 * @param array<string,mixed> $ctx user_ref, mandant, price_rule_version, meta[]
	 * @return string session_code
	 */
	public static function start( array $ctx = [] ): string {
		global $wpdb;
		$table = Schema::session_table();
		$now   = self::now();
		$prv   = isset( $ctx['price_rule_version'] ) ? (string) $ctx['price_rule_version'] : '';

		$code = self::generate_code();
		for ( $try = 0; $try < 3; $try++ ) {
			$ok = $wpdb->insert( // phpcs:ignore WordPress.DB
				$table,
				[
					'session_code'       => $code,
					'user_ref'           => isset( $ctx['user_ref'] ) ? (int) $ctx['user_ref'] : null,
					'mandant'            => isset( $ctx['mandant'] ) ? (string) $ctx['mandant'] : null,
					'status'             => 'active',
					'price_rule_version' => '' !== $prv ? $prv : null,
					'started_at'         => $now,
					'last_heartbeat_at'  => $now,
					'active_seconds'     => 0,
					'meta'               => isset( $ctx['meta'] ) && is_array( $ctx['meta'] ) ? (string) wp_json_encode( $ctx['meta'] ) : null,
				],
				[ '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ]
			);
			if ( false !== $ok ) {
				break;
			}
			$code = self::generate_code(); // Kollision → neuer Code.
		}

		EventLog::append( $code, EventTypes::SESSION_STARTED, [ 'occurred_at' => $now, 'price_rule_version' => $prv ] );
		return $code;
	}

	/** Regelmäßiger Aktivitäts-Ping. Aktualisiert last_heartbeat + aktive Dauer. */
	public static function heartbeat( string $session_code ): void {
		$now = self::now();
		EventLog::append( $session_code, EventTypes::SESSION_HEARTBEAT, [ 'occurred_at' => $now ] );
		self::touch( $session_code, [ 'last_heartbeat_at' => $now ] );
		self::recompute( $session_code );
	}

	public static function pause( string $session_code ): void {
		EventLog::append( $session_code, EventTypes::SESSION_PAUSED, [ 'occurred_at' => self::now() ] );
		self::touch( $session_code, [ 'status' => 'paused' ] );
		self::recompute( $session_code );
	}

	public static function resume( string $session_code ): void {
		$now = self::now();
		EventLog::append( $session_code, EventTypes::SESSION_RESUMED, [ 'occurred_at' => $now ] );
		self::touch( $session_code, [ 'status' => 'active', 'last_heartbeat_at' => $now ] );
		self::recompute( $session_code );
	}

	/** Beendet die Sitzung regulär. */
	public static function end( string $session_code ): void {
		$now = self::now();
		EventLog::append( $session_code, EventTypes::SESSION_ENDED, [ 'occurred_at' => $now ] );
		self::touch( $session_code, [ 'status' => 'ended', 'ended_at' => $now ] );
		self::recompute( $session_code );
	}

	/**
	 * Kehrlauf: beendet aktive Sitzungen, deren letzter Heartbeat länger als der Timeout zurückliegt
	 * (Schutz gegen „Browser geschlossen ohne Abmeldung", §5.3). Setzt das Ende auf den letzten Heartbeat.
	 *
	 * @return int Anzahl beendeter Sitzungen.
	 */
	public static function sweep_timeouts(): int {
		global $wpdb;
		$table   = Schema::session_table();
		$timeout = self::timeout();
		$rows    = $wpdb->get_results( "SELECT session_code, last_heartbeat_at FROM {$table} WHERE status = 'active'", ARRAY_A ); // phpcs:ignore WordPress.DB
		$rows    = is_array( $rows ) ? $rows : [];
		$now     = time();
		$count   = 0;
		foreach ( $rows as $row ) {
			$last = self::to_unix( (string) $row['last_heartbeat_at'] );
			if ( SessionMeter::is_timed_out( $last, $timeout, $now ) ) {
				$code = (string) $row['session_code'];
				$end  = gmdate( 'Y-m-d H:i:s', $last );
				EventLog::append( $code, EventTypes::SESSION_ENDED, [ 'occurred_at' => $end, 'metadata' => [ 'reason' => 'inactivity_timeout' ] ] );
				self::touch( $code, [ 'status' => 'timeout', 'ended_at' => $end ] );
				self::recompute( $code );
				$count++;
			}
		}
		return $count;
	}

	/** @return array<string,mixed>|null Sitzungszeile. */
	public static function get( string $session_code ): ?array {
		global $wpdb;
		$table = Schema::session_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE session_code = %s", $session_code ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $row ) ? $row : null;
	}

	/** Berechnet die abrechenbare aktive Dauer neu und speichert sie (Segmente durch Pause/Ende getrennt). */
	public static function recompute( string $session_code ): void {
		$timeout = self::timeout();
		$active  = 0;
		$segment = [];

		$flush = static function ( ?int $boundary ) use ( &$segment, &$active, $timeout ): void {
			if ( [] !== $segment ) {
				$active += SessionMeter::active_seconds( $segment, $timeout, $boundary );
				$segment = [];
			}
		};

		foreach ( EventLog::chain_for( $session_code ) as $row ) {
			$type = (string) $row['type'];
			$ts   = self::to_unix( (string) $row['occurred_at'] );
			if ( EventTypes::SESSION_STARTED === $type || EventTypes::SESSION_HEARTBEAT === $type || EventTypes::SESSION_RESUMED === $type ) {
				$segment[] = $ts;
			} elseif ( EventTypes::SESSION_PAUSED === $type || EventTypes::SESSION_ENDED === $type ) {
				$flush( $ts );
			}
		}
		$flush( null ); // offenes Segment (noch aktiv) ohne Abschlussgrenze.

		self::touch( $session_code, [ 'active_seconds' => $active ] );
	}

	/** @param array<string,mixed> $fields */
	private static function touch( string $session_code, array $fields ): void {
		global $wpdb;
		if ( [] === $fields ) {
			return;
		}
		$formats = [];
		foreach ( $fields as $k => $v ) {
			$formats[] = 'active_seconds' === $k ? '%d' : '%s';
		}
		$wpdb->update( Schema::session_table(), $fields, [ 'session_code' => $session_code ], $formats, [ '%s' ] ); // phpcs:ignore WordPress.DB
	}
}
