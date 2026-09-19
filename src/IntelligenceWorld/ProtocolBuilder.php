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
		$line_items = [];
		$modules_cost = 0;
		foreach ( $events as $row ) {
			if ( ! is_array( $row ) ) { continue; }
			$type = (string) ( $row['type'] ?? '' );
			$meta = $row['metadata'] ?? [];
			if ( is_string( $meta ) ) { $meta = (array) json_decode( $meta, true ); }
			if ( ! is_array( $meta ) ) { $meta = []; }
			$event_list[] = [
				'seq'         => (int) ( $row['seq'] ?? 0 ),
				'type'        => $type,
				'label'       => EventTypes::label( $type ),
				'occurred_at' => (string) ( $row['occurred_at'] ?? '' ),
				'module'      => (string) ( $row['module'] ?? '' ),
			];
			// Kostenpflichtige Module/Rechenlasten (§6.4/§8): Posten mit Kosten aus den Metadaten.
			$cost = isset( $meta['cost_minor'] ) ? (int) $meta['cost_minor'] : 0;
			if ( $cost > 0 ) {
				$line_items[] = [
					'seq'          => (int) ( $row['seq'] ?? 0 ),
					'action'       => (string) ( $meta['action'] ?? '' ),
					'label'        => (string) ( $meta['label'] ?? EventTypes::label( $type ) ),
					'units'        => (int) ( $meta['units'] ?? 1 ),
					'cost_minor'   => $cost,
					'cost_display' => Money::format( $cost, $currency ),
				];
				$modules_cost += $cost;
			}
		}
		$base_cost  = (int) ( $billing['base_cost_minor'] ?? 0 );
		$total_cost = $base_cost + $modules_cost;

		return [
			'session_code'   => (string) ( $session['session_code'] ?? '' ),
			'status'         => (string) ( $session['status'] ?? 'unknown' ),
			'started_at'     => (string) ( $session['started_at'] ?? '' ),
			'ended_at'       => (string) ( $session['ended_at'] ?? '' ),
			'active_seconds' => $active,
			'active_display' => self::hms( $active ),
			'billing'        => [
				'currency'           => $currency,
				'base_cost_minor'    => $base_cost,
				'base_cost_display'  => Money::format( $base_cost, $currency ),
				'modules_cost_minor' => $modules_cost,
				'modules_display'    => Money::format( $modules_cost, $currency ),
				'total_cost_minor'   => $total_cost,
				'total_display'      => Money::format( $total_cost, $currency ),
				'budget_minor'       => (int) ( $billing['budget_minor'] ?? 0 ),
				'budget_display'     => Money::format( (int) ( $billing['budget_minor'] ?? 0 ), $currency ),
				'budget_pct'         => (int) ( $billing['budget_pct'] ?? 0 ),
				'level'              => (string) ( $billing['level'] ?? 'ok' ),
			],
			'line_items'     => $line_items,
			'events'         => $event_list,
			'event_count'    => count( $event_list ),
			'integrity_ok'   => $integrity_ok,
			'generated_at'   => $generated_at,
			'prototype'      => true,
		];
	}

	/**
	 * Formatiert ein Protokoll (aus {@see self::build()}) in Textzeilen für den PDF-/Text-Export. Rein/testbar.
	 *
	 * @param array<string,mixed> $p
	 * @return array<int,string>
	 */
	public static function to_lines( array $p ): array {
		$b     = is_array( $p['billing'] ?? null ) ? $p['billing'] : [];
		$lines = [
			'Sitzung: ' . (string) ( $p['session_code'] ?? '' ),
			'Status: ' . (string) ( $p['status'] ?? '' ),
			'Start: ' . (string) ( $p['started_at'] ?? '' ),
			'Ende: ' . (string) ( $p['ended_at'] ?? '' ),
			'Aktive Zeit: ' . (string) ( $p['active_display'] ?? '' ),
			'Basiskosten (Zeit): ' . (string) ( $b['base_cost_display'] ?? '' ),
			'Zusatzkosten (Module): ' . (string) ( $b['modules_display'] ?? '' ),
			'Gesamtkosten: ' . (string) ( $b['total_display'] ?? ( $b['base_cost_display'] ?? '' ) ),
			'Budget: ' . (string) ( $b['budget_display'] ?? '' ) . ' (' . (int) ( $b['budget_pct'] ?? 0 ) . ' %)',
			'Integritaet: ' . ( ! empty( $p['integrity_ok'] ) ? 'unveraendert' : 'VERAENDERT' ),
			'Erstellt (UTC): ' . (string) ( $p['generated_at'] ?? '' ),
			'',
		];
		$items = is_array( $p['line_items'] ?? null ) ? $p['line_items'] : [];
		if ( [] !== $items ) {
			$lines[] = 'Kostenpflichtige Module:';
			foreach ( $items as $it ) {
				$lines[] = '- ' . (string) ( $it['label'] ?? '' ) . ' (x' . (int) ( $it['units'] ?? 1 ) . '): ' . (string) ( $it['cost_display'] ?? '' );
			}
			$lines[] = '';
		}
		$events = is_array( $p['events'] ?? null ) ? $p['events'] : [];
		$lines[] = 'Ereignisse (' . count( $events ) . '):';
		foreach ( $events as $e ) {
			$lines[] = (int) ( $e['seq'] ?? 0 ) . '. ' . (string) ( $e['occurred_at'] ?? '' ) . '  ' . (string) ( $e['label'] ?? '' );
		}
		$lines[] = '';
		$lines[] = 'Prototyp - Beispieldaten, keine echte Abrechnung.';
		return $lines;
	}

	/** hh:mm:ss (auch > 24 h). Rein. */
	private static function hms( int $seconds ): string {
		$seconds = max( 0, $seconds );
		return sprintf( '%02d:%02d:%02d', intdiv( $seconds, 3600 ), intdiv( $seconds % 3600, 60 ), $seconds % 60 );
	}
}
