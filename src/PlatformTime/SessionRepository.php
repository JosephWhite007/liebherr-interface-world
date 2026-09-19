<?php
/**
 * Liebherr World – Plattformzeit: Session-Repository (Pflichtenheft My Liebherr §41, ADR-LIW-MYL-001 S9/S10).
 *
 * Persistiert den serverautoritären Zeitabschnitt (Tabelle {@see Schema::session_table()}) und schreibt die
 * aktive Zeit inkrementell über {@see SessionClock} fort (Heartbeat). start() reserviert den Abschnitt
 * (Eintritt), stop() bestätigt ihn (Verlassen) und erzeugt genau einen Abrechnungssatz über
 * {@see ChargeService}. Nur eigener Nutzer (Objektbezug §35/SEC 01); der Aufrufer stellt die Identität.
 *
 * @package Liebherr\InterfaceWorld\PlatformTime
 * @since   0.1.0-alpha.111
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\PlatformTime;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SessionRepository {

	/** Inaktivitäts-Timeout in Sekunden (administrierbar, Standard 5 Minuten). */
	public static function timeout(): int {
		$t = (int) get_option( 'liw_ptime_idle_timeout', 300 );
		return max( 1, (int) apply_filters( 'liw_ptime_idle_timeout', $t ) );
	}

	private static function now(): int {
		return time();
	}

	private static function dt( int $ts ): string {
		return gmdate( 'Y-m-d H:i:s', $ts );
	}

	private static function ts( ?string $dt ): int {
		return ( null === $dt || '' === $dt ) ? 0 : (int) strtotime( $dt . ' UTC' );
	}

	/** Offener (laufender) Abschnitt des Nutzers oder null. */
	public static function open_for( int $user_id ): ?array {
		if ( $user_id <= 0 ) {
			return null;
		}
		global $wpdb;
		$t   = Schema::session_table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE user_id = %d AND status = 'running' ORDER BY id DESC LIMIT 1", $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $row ) ? self::shape( $row ) : null;
	}

	/** Startet (oder setzt fort) den Zeitabschnitt = Reservierung beim Eintritt. */
	public static function start( int $user_id, ?int $now = null ): array {
		$now  = $now ?? self::now();
		$open = self::open_for( $user_id );
		if ( null !== $open ) {
			return $open;
		}
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB
			Schema::session_table(),
			[
				'user_id'        => $user_id,
				'status'         => 'running',
				'rule_version'   => TokenRule::current()->version,
				'active_seconds' => 0,
				'started_at'     => self::dt( $now ),
				'last_seen_at'   => self::dt( $now ),
			],
			[ '%d', '%s', '%s', '%d', '%s', '%s' ]
		);
		$open = self::open_for( $user_id );
		return null !== $open ? $open : self::shape( [ 'user_id' => $user_id, 'started_at' => self::dt( $now ), 'last_seen_at' => self::dt( $now ) ] );
	}

	/** Serverautoritärer Herzschlag: schreibt aktive Zeit fort und liefert den aktuellen Stand. */
	public static function heartbeat( int $user_id, ?int $now = null ): array {
		$now  = $now ?? self::now();
		$open = self::open_for( $user_id );
		if ( null === $open ) {
			$open = self::start( $user_id, $now );
		}
		$acc = SessionClock::accrue( (int) $open['active_seconds'], self::ts( $open['last_seen_at'] ), $now, self::timeout() );
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB
			Schema::session_table(),
			[ 'active_seconds' => (int) $acc['active'], 'last_seen_at' => self::dt( (int) $acc['last_seen'] ), 'updated_at' => current_time( 'mysql' ) ],
			[ 'id' => (int) $open['id'] ],
			[ '%d', '%s', '%s' ],
			[ '%d' ]
		);
		$open['active_seconds'] = (int) $acc['active'];
		$open['last_seen_at']   = self::dt( (int) $acc['last_seen'] );
		return self::state( $open, (bool) $acc['idle'] );
	}

	/** Aktueller Stand ohne Persistenz (Lesen) – rechnet den offenen Gap in-memory dazu. */
	public static function status( int $user_id, ?int $now = null ): array {
		$now  = $now ?? self::now();
		$open = self::open_for( $user_id );
		if ( null === $open ) {
			return [ 'active' => false, 'active_seconds' => 0, 'tokens' => 0, 'running' => false ];
		}
		$acc                    = SessionClock::accrue( (int) $open['active_seconds'], self::ts( $open['last_seen_at'] ), $now, self::timeout() );
		$open['active_seconds'] = (int) $acc['active'];
		return self::state( $open, (bool) $acc['idle'] ) + [ 'active' => true ];
	}

	/** Beendet den Abschnitt = Bestätigung beim Verlassen; erzeugt genau einen Abrechnungssatz. */
	public static function stop( int $user_id, ?int $now = null ): array {
		$now  = $now ?? self::now();
		$open = self::open_for( $user_id );
		if ( null === $open ) {
			return [ 'ok' => false, 'reason' => 'no_session' ];
		}
		$acc     = SessionClock::accrue( (int) $open['active_seconds'], self::ts( $open['last_seen_at'] ), $now, self::timeout() );
		$active  = (int) $acc['active'];
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB
			Schema::session_table(),
			[ 'active_seconds' => $active, 'status' => 'stopped', 'ended_at' => self::dt( $now ), 'last_seen_at' => self::dt( $now ), 'updated_at' => current_time( 'mysql' ) ],
			[ 'id' => (int) $open['id'] ],
			[ '%d', '%s', '%s', '%s', '%s' ],
			[ '%d' ]
		);
		$charge = ChargeService::record( (int) $open['id'], $user_id, $active );
		return [
			'ok'             => true,
			'session'        => (int) $open['id'],
			'active_seconds' => $active,
			'tokens'         => (int) $charge['token_amount'],
			'charge'         => $charge,
		];
	}

	/**
	 * Baut die Zustands-Antwort (aktive Sekunden + laufende Token nach aktueller Regel).
	 *
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	private static function state( array $row, bool $idle ): array {
		$seconds = (int) $row['active_seconds'];
		$tokens  = TokenRule::current()->tokens_for( $seconds );
		return [
			'session'        => (int) $row['id'],
			'active_seconds' => $seconds,
			'tokens'         => $tokens,
			'running'        => 'running' === (string) $row['status'],
			'idle'           => $idle,
		];
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	private static function shape( array $row ): array {
		return [
			'id'             => (int) ( $row['id'] ?? 0 ),
			'user_id'        => (int) ( $row['user_id'] ?? 0 ),
			'status'         => (string) ( $row['status'] ?? 'running' ),
			'rule_version'   => (string) ( $row['rule_version'] ?? '' ),
			'active_seconds' => (int) ( $row['active_seconds'] ?? 0 ),
			'started_at'     => (string) ( $row['started_at'] ?? '' ),
			'last_seen_at'   => (string) ( $row['last_seen_at'] ?? '' ),
			'ended_at'       => isset( $row['ended_at'] ) ? (string) $row['ended_at'] : null,
		];
	}
}
