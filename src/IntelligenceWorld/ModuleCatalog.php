<?php
/**
 * Liebherr Intelligence World – Katalog kostenpflichtiger Module/Rechenlasten (Pflichtenheft-2 §6.4/§8).
 *
 * Neben der zeitbasierten Basisabrechnung gibt es zusätzlich nutzungsabhängige Posten (Datenabfragen,
 * Datenquellen, Simulationen, Compute-Jobs, Exporte). Dieser Katalog definiert die abrechenbaren Aktionen
 * mit Ereignistyp, Preiseinheit und Mock-Preis (Minor-Units). Jede Nutzung wird als Ereignis ins Ledger
 * geschrieben und erscheint im Nutzungs-/Kostenprotokoll als eigener Posten.
 *
 * Rein und ohne WordPress-Laufzeit unit-testbar. PROTOTYP (§21): Beispielpreise, keine echte Abrechnung.
 *
 * @package Liebherr\InterfaceWorld\IntelligenceWorld
 * @since   0.1.0-alpha.63
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\IntelligenceWorld;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ModuleCatalog {

	/**
	 * Abrechenbare Aktionen: key => [ label, event (EventTypes), unit (PriceRule), price_minor, default_units ].
	 *
	 * @return array<string,array{label:string,event:string,unit:string,price_minor:int,default_units:int}>
	 */
	public static function actions(): array {
		return [
			'data_query'  => [ 'label' => __( 'Datenabfrage', 'liebherr-interface-world' ),        'event' => EventTypes::QUERY_EXECUTED,       'unit' => PriceRule::UNIT_QUERY,        'price_minor' => 15,  'default_units' => 1 ],
			'data_source' => [ 'label' => __( 'Datenquelle einbinden', 'liebherr-interface-world' ), 'event' => EventTypes::DATA_SOURCE_ACCESSED,  'unit' => PriceRule::UNIT_DATASOURCE,   'price_minor' => 50,  'default_units' => 1 ],
			'simulation'  => [ 'label' => __( 'Simulation ausführen', 'liebherr-interface-world' ),  'event' => EventTypes::COMPUTE_JOB_COMPLETED, 'unit' => PriceRule::UNIT_SIMULATION,   'price_minor' => 500, 'default_units' => 1 ],
			'compute'     => [ 'label' => __( 'Compute-Job (je Einheit)', 'liebherr-interface-world' ), 'event' => EventTypes::COMPUTE_JOB_COMPLETED, 'unit' => PriceRule::UNIT_COMPUTE_UNIT, 'price_minor' => 120, 'default_units' => 1 ],
			'export'      => [ 'label' => __( 'Ergebnis exportieren', 'liebherr-interface-world' ),  'event' => EventTypes::RESULT_EXPORTED,      'unit' => PriceRule::UNIT_CALL,         'price_minor' => 200, 'default_units' => 1 ],
		];
	}

	public static function is_valid( string $key ): bool {
		return array_key_exists( $key, self::actions() );
	}

	/** @return array{label:string,event:string,unit:string,price_minor:int,default_units:int}|null */
	public static function get( string $key ): ?array {
		$a = self::actions();
		return $a[ $key ] ?? null;
	}

	public static function label( string $key ): string {
		$a = self::get( $key );
		return null !== $a ? (string) $a['label'] : $key;
	}

	/** Kosten einer Aktion in Minor-Units (rein), über die passende PriceRule berechnet. */
	public static function cost_minor( string $key, int $units, string $currency = 'EUR', string $version = 'mock-1' ): int {
		$a = self::get( $key );
		if ( null === $a ) {
			return 0;
		}
		$units = max( 1, $units );
		$rule  = new PriceRule( (string) $a['unit'], (int) $a['price_minor'], $currency, $version, 0, null );
		return $rule->cost_minor( $units );
	}

	/**
	 * Katalog für das Frontend (Buttons): key, label, unit, price_minor, price_display, default_units.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function public_list( string $currency = 'EUR' ): array {
		$out = [];
		foreach ( self::actions() as $key => $a ) {
			$out[] = [
				'key'           => $key,
				'label'         => (string) $a['label'],
				'unit'          => (string) $a['unit'],
				'price_minor'   => (int) $a['price_minor'],
				'price_display' => Money::format( (int) $a['price_minor'], $currency ),
				'default_units' => (int) $a['default_units'],
			];
		}
		return $out;
	}
}
