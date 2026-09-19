<?php
/**
 * Liebherr World – CAPDB Plugin-Scopes & -Kategorien (Pflichtenheft §26.1, §24.2).
 *
 * Rein/ohne WordPress testbar. Scopes bestimmen die erlaubten Zielzonen (Seite/Übergang); Kategorien
 * gruppieren die registrierten Plugin-Typen der Startversion.
 *
 * @package Liebherr\InterfaceWorld\Cvf
 * @since   0.1.0-alpha.89
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Cvf;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class PluginTaxonomy {

	// Zielzonen (Host-Typen).
	public const SCOPE_PAGE = 'page';
	public const SCOPE_EDGE = 'edge';

	// Kategorien der Startversion (§26.1).
	public const CAT_SECURITY    = 'security';
	public const CAT_INFORMATION = 'information';
	public const CAT_NAVIGATION  = 'navigation';
	public const CAT_INTERACTION = 'interaction';
	public const CAT_TIMING      = 'timing';
	public const CAT_STATUS      = 'status';
	public const CAT_MEASUREMENT = 'measurement';

	/** @return array<int,string> */
	public static function scopes(): array {
		return [ self::SCOPE_PAGE, self::SCOPE_EDGE ];
	}

	public static function is_scope( string $scope ): bool {
		return in_array( $scope, self::scopes(), true );
	}

	/** @return array<int,string> */
	public static function categories(): array {
		return [
			self::CAT_SECURITY,
			self::CAT_INFORMATION,
			self::CAT_NAVIGATION,
			self::CAT_INTERACTION,
			self::CAT_TIMING,
			self::CAT_STATUS,
			self::CAT_MEASUREMENT,
		];
	}

	public static function is_category( string $cat ): bool {
		return in_array( $cat, self::categories(), true );
	}

	/**
	 * Zerlegt eine gespeicherte allowed_scopes-Angabe ("page,edge") in eine geprüfte Liste.
	 *
	 * @return array<int,string>
	 */
	public static function parse_scopes( string $raw ): array {
		$out = [];
		foreach ( explode( ',', $raw ) as $s ) {
			$s = trim( $s );
			if ( self::is_scope( $s ) && ! in_array( $s, $out, true ) ) {
				$out[] = $s;
			}
		}
		return $out;
	}

	/** Ist $scope in der (gespeicherten) allowed_scopes-Angabe enthalten? */
	public static function scope_allowed( string $scope, string $allowed_raw ): bool {
		return in_array( $scope, self::parse_scopes( $allowed_raw ), true );
	}
}
