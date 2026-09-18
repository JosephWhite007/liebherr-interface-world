<?php
/**
 * Liebherr Adventures – Mock-Drei-Wörter-Provider (Prototyp, §4.3).
 *
 * Deterministische, umkehrbare Beispiel-Umsetzung OHNE realen Anbieter: Koordinaten werden auf ein
 * grobes Raster (0,5°) quantisiert und in drei englische Wörter aus einer festen 64-Wort-Liste kodiert;
 * `decode()` rechnet exakt zum Rasterzentrum zurück (round-trip). Rein, ohne WP-DB (unit-testbar).
 * NUR Beispieldaten – vor Produktivbetrieb echten, lizenzierten Anbieter über den Provider-Vertrag anbinden.
 *
 * @package Liebherr\InterfaceWorld\Adventures\Location
 * @since   0.1.0-alpha.51
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Adventures\Location;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MockProvider implements ProviderInterface {

	private const STEP = 0.5;   // Grad; grobes Demo-Raster (~55 km).
	private const COLS = 721;   // Längengrad-Zellen 0..720 (−180..180 in 0,5°-Schritten).
	private const BASE = 64;    // Wortlisten-Länge.

	/** 64 einfache englische Wörter (feste Reihenfolge = Teil der „Providerversion"). */
	private const WORDS = [
		'apple', 'anchor', 'arrow', 'amber', 'bridge', 'basalt', 'birch', 'beacon',
		'copper', 'cedar', 'coral', 'canyon', 'delta', 'dune', 'drift', 'dawn',
		'ember', 'echo', 'elm', 'edge', 'forge', 'falcon', 'fern', 'frost',
		'granite', 'gale', 'grove', 'glacier', 'harbor', 'hazel', 'hollow', 'horizon',
		'iron', 'ivory', 'inlet', 'island', 'jade', 'juniper', 'jetty', 'journey',
		'kelp', 'kite', 'knoll', 'krypton', 'larch', 'lagoon', 'lumen', 'ledge',
		'maple', 'meadow', 'marble', 'mesa', 'north', 'nimbus', 'nectar', 'nova',
		'oak', 'onyx', 'orbit', 'ocean', 'pine', 'quartz', 'ridge', 'summit',
	];

	public function name(): string {
		return 'Mock Three-Word Provider (Demo)';
	}

	public function version(): string {
		return 'mock-1';
	}

	public function encode( float $lat, float $lng ): array {
		$lat = max( -90.0, min( 90.0, $lat ) );
		$lng = max( -180.0, min( 180.0, $lng ) );

		$cell_lat = (int) round( ( $lat + 90.0 ) / self::STEP );  // 0..360
		$cell_lng = (int) round( ( $lng + 180.0 ) / self::STEP ); // 0..720
		$key      = $cell_lat * self::COLS + $cell_lng;

		$i1 = $key % self::BASE;
		$i2 = intdiv( $key, self::BASE ) % self::BASE;
		$i3 = intdiv( $key, self::BASE * self::BASE ) % self::BASE;
		$words = self::WORDS[ $i1 ] . '.' . self::WORDS[ $i2 ] . '.' . self::WORDS[ $i3 ];

		return [
			'words'    => $words,
			'lat'      => $cell_lat * self::STEP - 90.0,
			'lng'      => $cell_lng * self::STEP - 180.0,
			'accuracy' => 'grid_' . self::STEP . 'deg',
		];
	}

	public function decode( string $words ): ?array {
		$parts = explode( '.', strtolower( trim( $words ) ) );
		if ( 3 !== count( $parts ) ) {
			return null;
		}
		$flip = array_flip( self::WORDS );
		$idx  = [];
		foreach ( $parts as $w ) {
			if ( ! isset( $flip[ $w ] ) ) {
				return null;
			}
			$idx[] = (int) $flip[ $w ];
		}
		$key      = $idx[0] + $idx[1] * self::BASE + $idx[2] * self::BASE * self::BASE;
		$cell_lat = intdiv( $key, self::COLS );
		$cell_lng = $key % self::COLS;

		return [
			'lat' => $cell_lat * self::STEP - 90.0,
			'lng' => $cell_lng * self::STEP - 180.0,
		];
	}
}
