<?php
/**
 * Liebherr World – My Liebherr: Dashboard-Logik (Pflichtenheft My Liebherr §5, ADR-LIW-MYL-001 S3).
 *
 * Reine Zusammenführung von gespeichertem Layout und {@see WidgetCatalog}: nur berechtigte Widgets, gespeicherte
 * Reihenfolge/Sichtbarkeit gewinnen, unbekannte/nicht berechtigte Widgets fallen weg, neue berechtigte Widgets
 * werden hinten (Standard-Sichtbarkeit) ergänzt. `sanitize()` bereinigt eine Eingabe (PUT /dashboard),
 * `default_layout()` liefert die Rollenvorlage (Zurücksetzen). Ohne WordPress (testbar).
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.114
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class DashboardService {

	/**
	 * Rollenvorlage: alle berechtigten Widgets in Standard-Reihenfolge und -Sichtbarkeit.
	 *
	 * @param array<int,string> $caps
	 * @return array<int,array{key:string,visible:bool}>
	 */
	public static function default_layout( array $caps ): array {
		$out     = [];
		$catalog = WidgetCatalog::all();
		foreach ( WidgetCatalog::permitted_for( $caps ) as $key ) {
			$out[] = [ 'key' => $key, 'visible' => (bool) $catalog[ $key ]['default_visible'] ];
		}
		return $out;
	}

	/**
	 * Effektives Layout aus gespeichertem Zustand + Katalog (nur berechtigte Widgets).
	 *
	 * @param array<int,string>                         $caps
	 * @param array<int,array{key:string,visible:bool}>|null $stored
	 * @return array<int,array{key:string,visible:bool}>
	 */
	public static function resolve( array $caps, ?array $stored ): array {
		$permitted = WidgetCatalog::permitted_for( $caps );
		$catalog   = WidgetCatalog::all();
		$out       = [];
		$seen      = [];

		foreach ( (array) $stored as $entry ) {
			$key = is_array( $entry ) ? (string) ( $entry['key'] ?? '' ) : '';
			if ( '' === $key || isset( $seen[ $key ] ) || ! in_array( $key, $permitted, true ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = [ 'key' => $key, 'visible' => isset( $entry['visible'] ) ? (bool) $entry['visible'] : true ];
		}
		foreach ( $permitted as $key ) {
			if ( ! isset( $seen[ $key ] ) ) {
				$out[] = [ 'key' => $key, 'visible' => (bool) $catalog[ $key ]['default_visible'] ];
			}
		}
		return $out;
	}

	/**
	 * Bereinigt eine Layout-Eingabe (PUT) auf berechtigte, eindeutige Widgets; ergänzt fehlende hinten.
	 *
	 * @param array<int,string> $caps
	 * @param mixed             $input Erwartet Liste von {key, visible}.
	 * @return array<int,array{key:string,visible:bool}>
	 */
	public static function sanitize( array $caps, $input ): array {
		return self::resolve( $caps, is_array( $input ) ? $input : [] );
	}
}
