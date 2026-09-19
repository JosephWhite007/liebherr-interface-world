<?php
/**
 * Liebherr World – Plattformzeit: Zeit-Tokenregel (Pflichtenheft My Liebherr §8/§41, ADR-LIW-MYL-001 S10).
 *
 * Immutables Wertobjekt analog zur IW-{@see \Liebherr\InterfaceWorld\IntelligenceWorld\PriceRule}: Tokensatz je
 * Minute, Versionskennung und optionale Min/Max-Grenzen. Reine, ganzzahlige Umrechnung Sekunden → Token
 * (konservativ: ganzzahlige Division, angebrochene Einheiten werden nicht aufgerundet). Die beim Abschnitt
 * gültige Regelversion wird dauerhaft gespeichert (keine rückwirkende Änderung). Ohne WP-Abhängigkeit (testbar).
 *
 * @package Liebherr\InterfaceWorld\PlatformTime
 * @since   0.1.0-alpha.111
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\PlatformTime;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class TokenRule {

	public int    $tokens_per_minute;
	public string $version;
	public int    $min_tokens;
	public int    $max_tokens; // 0 = unbegrenzt

	public function __construct( int $tokens_per_minute, string $version, int $min_tokens = 0, int $max_tokens = 0 ) {
		$this->tokens_per_minute = max( 0, $tokens_per_minute );
		$this->version           = $version;
		$this->min_tokens        = max( 0, $min_tokens );
		$this->max_tokens        = max( 0, $max_tokens );
	}

	/**
	 * Token für aktive Sekunden: floor( seconds * tokens_per_minute / 60 ), danach Min/Max-Clamp.
	 */
	public function tokens_for( int $active_seconds ): int {
		$seconds = max( 0, $active_seconds );
		$tokens  = intdiv( $seconds * $this->tokens_per_minute, 60 );
		if ( $tokens < $this->min_tokens ) {
			$tokens = $this->min_tokens;
		}
		if ( $this->max_tokens > 0 && $tokens > $this->max_tokens ) {
			$tokens = $this->max_tokens;
		}
		return $tokens;
	}

	/**
	 * Aktuelle Regel aus Optionen (Standard 10 Token/Minute, JW-Entscheid 19.09.2026). Der Satz ist
	 * administrierbar (`liw_ptime_token_per_min`); die Version wandert mit dem Satz (Nachvollziehbarkeit §8).
	 */
	public static function current(): self {
		$per_min = (int) get_option( 'liw_ptime_token_per_min', 10 );
		$per_min = max( 0, (int) apply_filters( 'liw_ptime_token_per_min', $per_min ) );
		$version = (string) get_option( 'liw_ptime_rule_version', 'ptime-1' );
		return new self( $per_min, $version, 0, 0 );
	}
}
