<?php
/**
 * Liebherr World – My Liebherr: Widget-Katalog (Pflichtenheft My Liebherr §5, ADR-LIW-MYL-001 S3).
 *
 * Definiert die für das persönliche Dashboard verfügbaren Widgets mit benötigter Capability, Standard-
 * Reihenfolge und Standard-Sichtbarkeit. Nur berechtigte Widgets werden angeboten (§5: „ausschließlich
 * berechtigte Widgets"). Reine Datenklasse ohne WordPress (testbar); die Cap-Prüfung erhält die effektiven
 * Caps als Parameter (kein direkter WP-Zugriff).
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.114
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class WidgetCatalog {

	/**
	 * Alle Widgets in Standard-Reihenfolge: key → { cap, default_visible }.
	 *
	 * @return array<string,array{cap:string,default_visible:bool}>
	 */
	public static function all(): array {
		return [
			'wallet'   => [ 'cap' => Roles::CAP_ACCESS, 'default_visible' => true ],
			'updates'  => [ 'cap' => Roles::CAP_ACCESS, 'default_visible' => true ],
			'tasks'    => [ 'cap' => Roles::CAP_ACCESS, 'default_visible' => true ],
			'bookings' => [ 'cap' => Roles::CAP_ACCESS, 'default_visible' => true ],
		];
	}

	/** @return array<int,string> Widget-Keys in Standard-Reihenfolge. */
	public static function keys(): array {
		return array_keys( self::all() );
	}

	/**
	 * Für die gegebenen Caps erlaubte Widget-Keys (in Standard-Reihenfolge).
	 *
	 * @param array<int,string> $caps
	 * @return array<int,string>
	 */
	public static function permitted_for( array $caps ): array {
		$out = [];
		foreach ( self::all() as $key => $def ) {
			if ( in_array( $def['cap'], $caps, true ) ) {
				$out[] = $key;
			}
		}
		return $out;
	}
}
