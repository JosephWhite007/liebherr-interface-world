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

	/**
	 * Aktueller, noch nicht abgeschlossener Abschnitt (running, paused ODER ending) oder null. Bereits
	 * abgerechnete (`settled`) und historische (`stopped`, Alt-Modell vor P2) Abschnitte gelten als
	 * abgeschlossen und sperren nicht.
	 */
	public static function current_for( int $user_id ): ?array {
		if ( $user_id <= 0 ) {
			return null;
		}
		global $wpdb;
		$t   = Schema::session_table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE user_id = %d AND status IN ('running','paused','ending') ORDER BY id DESC LIMIT 1", $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $row ) ? self::shape( $row ) : null;
	}

	/**
	 * Sperrzustand des Nutzers (ADR-LIW-MYL-002 §4/§6): '' (frei), 'standby' (pausiert) oder 'settlement'
	 * (beendet, Abrechnung offen → Report/Buchung nötig).
	 */
	public static function lock_state( int $user_id ): string {
		$cur = self::current_for( $user_id );
		if ( null === $cur ) {
			return '';
		}
		if ( 'paused' === (string) $cur['status'] ) {
			return 'standby';
		}
		if ( 'ending' === (string) $cur['status'] ) {
			return 'settlement';
		}
		return '';
	}

	/** Startet (oder setzt fort) den Zeitabschnitt = Reservierung beim Eintritt. */
	public static function start( int $user_id, ?int $now = null ): array {
		$now  = $now ?? self::now();
		// Kein neuer Abschnitt, solange ein aktueller besteht – auch pausiert (Standby) oder gestoppt
		// (Abrechnung offen). Sonst würde ein Reload während der Sperre die Sperre umgehen (ADR-LIW-MYL-002 §5/§6).
		$cur = self::current_for( $user_id );
		if ( null !== $cur ) {
			return $cur;
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
			// Kein laufender Abschnitt: evtl. gesperrt (paused/stopped) → Sperrzustand melden, sonst neu starten.
			$cur = self::current_for( $user_id );
			if ( null !== $cur && 'running' !== (string) $cur['status'] ) {
				return self::state( $cur, false );
			}
			$open = self::start( $user_id, $now );
			if ( 'running' !== (string) $open['status'] ) {
				return self::state( $open, false );
			}
		}
		$acc = SessionClock::accrue( (int) $open['active_seconds'], self::ts( $open['last_seen_at'] ), $now, self::timeout() );
		// Auto-Standby (ADR-LIW-MYL-002 §4): Bei Inaktivität/Timeout wird der Abschnitt automatisch pausiert
		// (statt still weiterzulaufen) → die Sperre greift ohne Nutzeraktion.
		if ( (bool) $acc['idle'] ) {
			return self::standby( $user_id, $now );
		}
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

	/**
	 * Standby (ADR-LIW-MYL-002 §7): aktive Zeit bis jetzt festschreiben, dann Abschnitt einfrieren
	 * (`paused`). Es entsteht KEIN Abrechnungssatz. Idempotent: ein bereits pausierter Abschnitt bleibt
	 * pausiert. Nur der eigene laufende Abschnitt.
	 *
	 * @return array<string,mixed>
	 */
	public static function standby( int $user_id, ?int $now = null ): array {
		$now = $now ?? self::now();
		$cur = self::current_for( $user_id );
		if ( null === $cur ) {
			return [ 'active' => false, 'active_seconds' => 0, 'tokens' => 0, 'running' => false, 'state' => 'none', 'locked' => false ];
		}
		if ( 'paused' === (string) $cur['status'] ) {
			return self::state( $cur, false ) + [ 'active' => true ];
		}
		$acc    = SessionClock::accrue( (int) $cur['active_seconds'], self::ts( $cur['last_seen_at'] ), $now, self::timeout() );
		$active = (int) $acc['active'];
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB
			Schema::session_table(),
			[ 'active_seconds' => $active, 'status' => 'paused', 'paused_at' => self::dt( $now ), 'last_seen_at' => self::dt( $now ), 'updated_at' => current_time( 'mysql' ) ],
			[ 'id' => (int) $cur['id'] ],
			[ '%d', '%s', '%s', '%s', '%s' ],
			[ '%d' ]
		);
		$cur['active_seconds'] = $active;
		$cur['status']         = 'paused';
		return self::state( $cur, false ) + [ 'active' => true ];
	}

	/**
	 * Resume (ADR-LIW-MYL-002 §7): pausierten Abschnitt fortsetzen. Die Pausendauer wird auf
	 * `paused_seconds` addiert (Token bleiben eingefroren, Festlegung 1) und `last_seen_at` auf jetzt
	 * gesetzt, damit die Pause NICHT als aktive Zeit gezählt wird. Nur der eigene pausierte Abschnitt.
	 *
	 * @return array<string,mixed>
	 */
	public static function resume( int $user_id, ?int $now = null ): array {
		$now = $now ?? self::now();
		$cur = self::current_for( $user_id );
		if ( null === $cur ) {
			return [ 'ok' => false, 'reason' => 'no_session' ];
		}
		if ( 'running' === (string) $cur['status'] ) {
			return [ 'ok' => true ] + self::state( $cur, false );
		}
		$paused_gap = max( 0, $now - self::ts( $cur['paused_at'] ) );
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB
			Schema::session_table(),
			[ 'status' => 'running', 'paused_seconds' => (int) $cur['paused_seconds'] + $paused_gap, 'paused_at' => null, 'last_seen_at' => self::dt( $now ), 'updated_at' => current_time( 'mysql' ) ],
			[ 'id' => (int) $cur['id'] ],
			[ '%s', '%d', '%s', '%s', '%s' ],
			[ '%d' ]
		);
		$cur['status'] = 'running';
		return [ 'ok' => true ] + self::state( $cur, false );
	}

	/** Aktueller Stand ohne Persistenz (Lesen) – rechnet den offenen Gap in-memory dazu. */
	public static function status( int $user_id, ?int $now = null ): array {
		$now  = $now ?? self::now();
		$open = self::open_for( $user_id );
		if ( null === $open ) {
			// Kein laufender Abschnitt: evtl. pausiert (Standby) → Sperrzustand melden, sonst frei.
			$paused = self::current_for( $user_id );
			if ( null !== $paused && 'paused' === (string) $paused['status'] ) {
				return self::state( $paused, false ) + [ 'active' => true ];
			}
			return [ 'active' => false, 'active_seconds' => 0, 'tokens' => 0, 'running' => false, 'state' => 'none', 'locked' => false ];
		}
		$acc                    = SessionClock::accrue( (int) $open['active_seconds'], self::ts( $open['last_seen_at'] ), $now, self::timeout() );
		$open['active_seconds'] = (int) $acc['active'];
		return self::state( $open, (bool) $acc['idle'] ) + [ 'active' => true ];
	}

	/**
	 * Beenden = Bestätigung beim Verlassen (ADR-LIW-MYL-002 §6): aktive Zeit festschreiben, Abschnitt in
	 * `ending` (beendet, Abrechnung offen → gesperrt bis zur Bestätigung) überführen und GENAU EINEN
	 * Abrechnungssatz erzeugen ({@see ChargeService}, idempotent). Liefert die Report-Daten. Idempotent:
	 * ein bereits beendeter Abschnitt liefert denselben Report ohne Doppelbuchung.
	 *
	 * @return array<string,mixed>
	 */
	public static function stop( int $user_id, ?int $now = null ): array {
		$now = $now ?? self::now();
		$cur = self::current_for( $user_id );
		if ( null === $cur ) {
			return [ 'ok' => false, 'reason' => 'no_session' ];
		}
		if ( 'ending' === (string) $cur['status'] ) {
			// Schon beendet, Abrechnung offen → denselben (idempotenten) Report liefern, nicht erneut buchen.
			$charge = ChargeService::record( (int) $cur['id'], $user_id, (int) $cur['active_seconds'] );
			return self::report_of( $cur, $charge );
		}
		$acc    = SessionClock::accrue( (int) $cur['active_seconds'], self::ts( $cur['last_seen_at'] ), $now, self::timeout() );
		$active = (int) $acc['active'];
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB
			Schema::session_table(),
			[ 'active_seconds' => $active, 'status' => 'ending', 'ended_at' => self::dt( $now ), 'last_seen_at' => self::dt( $now ), 'updated_at' => current_time( 'mysql' ) ],
			[ 'id' => (int) $cur['id'] ],
			[ '%d', '%s', '%s', '%s', '%s' ],
			[ '%d' ]
		);
		$cur['active_seconds'] = $active;
		$charge                = ChargeService::record( (int) $cur['id'], $user_id, $active );
		return self::report_of( $cur, $charge );
	}

	/** Beenden – Alias von {@see stop()} mit sprechendem Namen (Pflichtenheft §41.8/Report). */
	public static function end( int $user_id, ?int $now = null ): array {
		return self::stop( $user_id, $now );
	}

	/**
	 * Abrechnungssatz bestätigen (ADR-LIW-MYL-002 §6): schaltet den beendeten Abschnitt frei. Solange die
	 * Wallet-Naht deaktiviert ist ({@see Flags::charge_live()} = AUS, Standard), bleibt der Satz `pending`
	 * (nur protokolliert, §41.1/MYL 028) und der Abschnitt wird auf `settled` gesetzt → Sperre fällt. Die
	 * echte Wallet-Buchung inkl. strenger Deckungsprüfung (Festlegung 2) kommt mit P3 (Wallet-Pflichtenheft).
	 *
	 * @return array<string,mixed>
	 */
	public static function settle( int $user_id, ?int $now = null ): array {
		$now = $now ?? self::now();
		$cur = self::current_for( $user_id );
		if ( null === $cur || 'ending' !== (string) $cur['status'] ) {
			return [ 'ok' => false, 'reason' => 'no_settlement' ];
		}
		$session_id = (int) $cur['id'];

		// Echte Token-Buchung (P3/W2), NUR bei scharfer Naht (liw_ptime_charge_live) und verfügbarer
		// Token-Wallet. Streng (Festlegung 2): reicht das Token-Guthaben nicht, bleibt der Abschnitt
		// `ending` (= gesperrt) und der Nutzer wird auf „Wallet aufladen" verwiesen. Idempotent über
		// den Schlüssel ptime-settle-<session> (auch im Core), daher kein Doppelabzug bei Wiederholung.
		if ( Flags::charge_live() && \Liebherr\InterfaceWorld\CoreBridge\WalletBridge::tokens_available() ) {
			$charge = ChargeService::get( $session_id );
			$tokens = null !== $charge ? (int) $charge['token_amount'] : 0;
			$rule   = null !== $charge ? (string) $charge['rule_version'] : '';
			if ( $tokens > 0 ) {
				$res = \Liebherr\InterfaceWorld\CoreBridge\WalletBridge::debit_tokens(
					$user_id,
					$tokens,
					'token_billing_debit',
					'ptime:' . $session_id,
					$rule,
					'ptime-settle-' . $session_id
				);
				if ( empty( $res['ok'] ) ) {
					$reason = ( 'insufficient_tokens' === ( $res['reason'] ?? '' ) ) ? 'insufficient' : ( $res['reason'] ?: 'charge_failed' );
					return [ 'ok' => false, 'reason' => $reason, 'state' => 'ending', 'locked' => true ];
				}
				ChargeService::mark_settled( $session_id, (string) $res['tx'] );
			}
		}

		// Naht AUS ODER erfolgreich gebucht ODER 0 Token → Abschnitt freigeben.
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB
			Schema::session_table(),
			[ 'status' => 'settled', 'updated_at' => current_time( 'mysql' ) ],
			[ 'id' => $session_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);
		do_action( 'liw_ptime_settled', $session_id, $user_id, Flags::charge_live() );
		return [ 'ok' => true, 'state' => 'settled', 'session' => $session_id ];
	}

	/**
	 * Report-Daten eines beendeten Abschnitts (verbrauchte Zeit + Token + Tarif + Abrechnungssatz).
	 *
	 * @param array<string,mixed> $row
	 * @param array<string,mixed> $charge
	 * @return array<string,mixed>
	 */
	private static function report_of( array $row, array $charge ): array {
		return [
			'ok'             => true,
			'session'        => (int) $row['id'],
			'active_seconds' => (int) $row['active_seconds'],
			'tokens'         => (int) $charge['token_amount'],
			'rule_version'   => (string) $charge['rule_version'],
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
		$status  = (string) $row['status'];
		return [
			'session'        => (int) $row['id'],
			'active_seconds' => $seconds,
			'tokens'         => $tokens,
			'running'        => 'running' === $status,
			'idle'           => $idle,
			'state'          => $status, // running | paused | ending | settled
			'locked'         => ( 'paused' === $status || 'ending' === $status ), // Standby ODER Abrechnung offen.
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
			'paused_seconds' => (int) ( $row['paused_seconds'] ?? 0 ),
			'started_at'     => (string) ( $row['started_at'] ?? '' ),
			'paused_at'      => isset( $row['paused_at'] ) ? (string) $row['paused_at'] : null,
			'last_seen_at'   => (string) ( $row['last_seen_at'] ?? '' ),
			'ended_at'       => isset( $row['ended_at'] ) ? (string) $row['ended_at'] : null,
		];
	}
}
