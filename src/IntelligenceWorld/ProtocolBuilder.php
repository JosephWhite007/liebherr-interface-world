<?php
/**
 * Liebherr Intelligence World – Nutzungs-/Kostenprotokoll (Pflichtenheft-2 §5.5/§8).
 *
 * Baut aus einer Sitzung (Session-Datensatz), ihrem Ereignis-Ledger und dem bereits berechneten
 * Abrechnungsstatus ({@see Rest::billing_status()}) ein strukturiertes, exportierbares Protokoll.
 * Rein und ohne WordPress-Laufzeit unit-testbar; die Kostenrechnung wird NICHT dupliziert, sondern als
 * `$billing` hereingereicht (Single Source of Truth).
 *
 * Ausgabeformate im Frontend: JSON-Download (dieses Array) + druckbare Ansicht (PDF via Browser-Druck).
 * PROTOTYP (§21): Beispieldaten, keine echte Abrechnung.
 *
 * @package Liebherr\InterfaceWorld\IntelligenceWorld
 * @since   0.1.0-alpha.58
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\IntelligenceWorld;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ProtocolBuilder {

	/**
	 * @param array<string,mixed>       $session      Session-Datensatz ({@see SessionService::get()}).
	 * @param array<int,array<string,mixed>> $events  Ereignis-Ledger ({@see EventLog::chain_for()}).
	 * @param array<string,mixed>       $billing      Ergebnis von {@see Rest::billing_status()}.
	 * @param string                    $currency     Währungscode (z. B. EUR).
	 * @param bool                      $integrity_ok Hash-Kette unverändert? ({@see EventLog::verify_chain()}).
	 * @param string                    $generated_at UTC-Zeitstempel der Protokollerstellung.
	 * @return array<string,mixed>
	 */
	public static function build( array $session, array $events, array $billing, string $currency, bool $integrity_ok, string $generated_at ): array {
		$active = (int) ( $billing['active_seconds'] ?? ( $session['active_seconds'] ?? 0 ) );

		$event_list = [];
		foreach ( $events as $row ) {
			if ( ! is_array( $row ) ) { continue; }
			$type = (string) ( $row['type'] ?? '' );
			$event_list[] = [
				'seq'         => (int) ( $row['seq'] ?? 0 ),
				'type'        => $type,
				'label'       => EventTypes::label( $type ),
				'occurred_at' => (string) ( $row['occurred_at'] ?? '' ),
				'module'      => (string) ( $row['module'] ?? '' ),
			];
		}

		return [
			'session_code'   => (string) ( $session['session_code'] ?? '' ),
			'status'         => (string) ( $session['status'] ?? 'unknown' ),
			'started_at'     => (string) ( $session['started_at'] ?? '' ),
			'ended_at'       => (string) ( $session['ended_at'] ?? '' ),
			'active_seconds' => $active,
			'active_display' => self::hms( $active ),
			'billing'        => [
				'currency'          => $currency,
				'base_cost_minor'   => (int) ( $billing['base_cost_minor'] ?? 0 ),
				'base_cost_display' => Money::format( (int) ( $billing['base_cost_minor'] ?? 0 ), $currency ),
				'budget_minor'      => (int) ( $billing['budget_minor'] ?? 0 ),
				'budget_display'    => Money::format( (int) ( $billing['budget_minor'] ?? 0 ), $currency ),
				'budget_pct'        => (int) ( $billing['budget_pct'] ?? 0 ),
				'level'             => (string) ( $billing['level'] ?? 'ok' ),
			],
			'events'         => $event_list,
			'event_count'    => count( $event_list ),
			'integrity_ok'   => $integrity_ok,
			'generated_at'   => $generated_at,
			'prototype'      => true,
		];
	}

	/** hh:mm:ss (auch > 24 h). Rein. */
	private static function hms( int $seconds ): string {
		$seconds = max( 0, $seconds );
		return sprintf( '%02d:%02d:%02d', intdiv( $seconds, 3600 ), intdiv( $seconds % 3600, 60 ), $seconds % 60 );
	}
}
