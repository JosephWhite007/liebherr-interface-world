<?php
/**
 * Liebherr Intelligence World – Simulation Builder: reine Forecast-Engine (Pflichtenheft-2 §6.4).
 *
 * Deterministische, ganzzahlige Beispiel-Prognose: aus Basiswert, Wachstum (Promille je Periode),
 * Periodenzahl und Szenario (konservativ/basis/ambitioniert) wird eine Reihe projizierter Werte samt
 * Auswertung berechnet. Rein und ohne WordPress-Laufzeit unit-testbar; dieselbe Formel spiegelt das
 * Frontend in `assets/js/liw-iw-simulation.js` (progressive Enhancement, keine Server-Roundtrips je Eingabe).
 *
 * PROTOTYP (§21): Beispieldaten/-modell – keine echte Prognose, keine Produktivkennzahlen.
 *
 * @package Liebherr\InterfaceWorld\IntelligenceWorld
 * @since   0.1.0-alpha.56
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\IntelligenceWorld;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SimulationModel {

	/** Erlaubte Periodenzahlen (Auswahl im Frontend). */
	public const HORIZONS = [ 6, 12, 24 ];

	/**
	 * Szenarien: Prozentanteil, mit dem das Basiswachstum skaliert wird (A/B/C).
	 *
	 * @return array<string,array{key:string,pct:int}>
	 */
	public static function scenarios(): array {
		return [
			'conservative' => [ 'key' => 'conservative', 'pct' => 70 ],
			'base'         => [ 'key' => 'base',         'pct' => 100 ],
			'ambitious'    => [ 'key' => 'ambitious',    'pct' => 130 ],
		];
	}

	/** Menschliche Bezeichnung eines Szenarios (A/B/C). */
	public static function scenario_label( string $scenario ): string {
		switch ( $scenario ) {
			case 'conservative': return __( 'Konservativ (A)', 'liebherr-interface-world' );
			case 'ambitious':    return __( 'Ambitioniert (C)', 'liebherr-interface-world' );
			case 'base':
			default:             return __( 'Basis (B)', 'liebherr-interface-world' );
		}
	}

	/**
	 * Deterministische Beispiel-Ausgangslage je Produktsegment (reproduzierbar aus dem Schlüssel).
	 * Basiswert 1.000–9.999, Wachstum 10–99 ‰ (1,0–9,9 % je Periode).
	 *
	 * @return array{base:int,growth_permille:int}
	 */
	public static function sample_baseline( string $segment_key ): array {
		$h = crc32( $segment_key !== '' ? $segment_key : 'default' );
		return [
			'base'            => 1000 + (int) ( $h % 9000 ),
			'growth_permille' => 10 + (int) ( ( $h >> 8 ) % 90 ),
		];
	}

	/**
	 * Berechnet die Prognosereihe. Werte je Periode = round( base × (1 + g/1000)^i ), ganzzahlig.
	 *
	 * @param int    $base            Ausgangswert (>= 0).
	 * @param int    $growth_permille Basiswachstum je Periode in Promille (0..1000).
	 * @param int    $periods         Zahl der Perioden (1..60, sonst geklemmt).
	 * @param string $scenario        conservative|base|ambitious.
	 * @return array{
	 *   scenario:string, scenario_pct:int, periods:int, base:int,
	 *   growth_permille:int, effective_permille:int,
	 *   values:array<int,int>, end:int, total:int, delta_permille:int
	 * }
	 */
	public static function forecast( int $base, int $growth_permille, int $periods, string $scenario ): array {
		$base     = max( 0, $base );
		$growth   = min( 1000, max( 0, $growth_permille ) );
		$periods  = min( 60, max( 1, $periods ) );
		$scen     = self::scenarios();
		$scenario = isset( $scen[ $scenario ] ) ? $scenario : 'base';
		$pct      = $scen[ $scenario ]['pct'];

		$eff_permille = intdiv( $growth * $pct, 100 ); // effektives Wachstum ‰
		$factor       = 1.0 + ( $eff_permille / 1000 );

		$values = [];
		for ( $i = 1; $i <= $periods; $i++ ) {
			$values[] = (int) round( $base * ( $factor ** $i ) );
		}

		$end            = $values[ $periods - 1 ];
		$total          = array_sum( $values );
		$delta_permille = $base > 0 ? (int) round( ( $end - $base ) * 1000 / $base ) : 0;

		return [
			'scenario'           => $scenario,
			'scenario_pct'       => $pct,
			'periods'            => $periods,
			'base'               => $base,
			'growth_permille'    => $growth,
			'effective_permille' => $eff_permille,
			'values'             => $values,
			'end'                => $end,
			'total'              => $total,
			'delta_permille'     => $delta_permille,
		];
	}
}
