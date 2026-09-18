<?php
/**
 * Liebherr Intelligence World – Preisregel (Pflichtenheft-2 §13.2/§13.3).
 *
 * Immutables Wertobjekt: Abrechnungseinheit, Preis (Minor-Units), Währung, Versionskennung und
 * Gültigkeitsfenster (UTC). Reine Kostenberechnung in Integer-Minor-Units. `select_active()` wählt die zum
 * Zeitpunkt gültige Regelversion (keine rückwirkende Preisänderung, §13.3). Ohne WP-Abhängigkeit (testbar).
 *
 * @package Liebherr\InterfaceWorld\IntelligenceWorld
 * @since   0.1.0-alpha.47
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\IntelligenceWorld;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class PriceRule {

	// Unterstützte Tarifarten (§13.2).
	public const UNIT_MINUTE       = 'per_minute';
	public const UNIT_HOUR         = 'per_hour';
	public const UNIT_CALL         = 'per_call';
	public const UNIT_QUERY        = 'per_query';
	public const UNIT_DATASOURCE   = 'per_datasource';
	public const UNIT_SIMULATION   = 'per_simulation';
	public const UNIT_COMPUTE_UNIT = 'per_compute_unit';
	public const UNIT_FLAT_PROJECT = 'flat_project';
	public const UNIT_QUOTA        = 'included_quota';
	public const UNIT_FREE         = 'free';

	public string $unit;
	public int    $price_minor;
	public string $currency;
	public string $version;
	public int    $valid_from; // UTC-Timestamp
	public ?int   $valid_until; // UTC-Timestamp oder null (offen)

	public function __construct( string $unit, int $price_minor, string $currency, string $version, int $valid_from, ?int $valid_until = null ) {
		$this->unit        = $unit;
		$this->price_minor = $price_minor;
		$this->currency    = $currency;
		$this->version     = $version;
		$this->valid_from  = $valid_from;
		$this->valid_until = $valid_until;
	}

	public static function units(): array {
		return [
			self::UNIT_MINUTE, self::UNIT_HOUR, self::UNIT_CALL, self::UNIT_QUERY, self::UNIT_DATASOURCE,
			self::UNIT_SIMULATION, self::UNIT_COMPUTE_UNIT, self::UNIT_FLAT_PROJECT, self::UNIT_QUOTA, self::UNIT_FREE,
		];
	}

	public function is_valid_at( int $ts ): bool {
		if ( $ts < $this->valid_from ) {
			return false;
		}
		return null === $this->valid_until || $ts <= $this->valid_until;
	}

	/**
	 * Kosten in Minor-Units. `$quantity` = Anzahl Abrechnungseinheiten (Aufrufe, Abfragen, Recheneinheiten …).
	 * Für zeitbasierte Tarife ist `$quantity` die Anzahl Minuten bzw. Stunden (vom Aufrufer aus Sekunden
	 * gerundet). `free` = 0; `flat_project` = einmaliger Pauschalpreis unabhängig von der Menge.
	 */
	public function cost_minor( int $quantity ): int {
		if ( self::UNIT_FREE === $this->unit ) {
			return 0;
		}
		if ( self::UNIT_FLAT_PROJECT === $this->unit ) {
			return $this->price_minor;
		}
		return Money::multiply( $this->price_minor, $quantity );
	}

	/**
	 * Wählt aus mehreren Versionen derselben Einheit die zum Zeitpunkt gültige (jüngstes valid_from ≤ ts).
	 * So gilt für eine bereits begonnene Leistung die beim Start gültige Version (§13.3).
	 *
	 * @param PriceRule[] $rules
	 */
	public static function select_active( array $rules, int $ts ): ?self {
		$best = null;
		foreach ( $rules as $rule ) {
			if ( ! $rule instanceof self || ! $rule->is_valid_at( $ts ) ) {
				continue;
			}
			if ( null === $best || $rule->valid_from > $best->valid_from ) {
				$best = $rule;
			}
		}
		return $best;
	}
}
