<?php
/**
 * Liebherr Intelligence World – Geldbeträge als Integer-Minor-Units (Pflichtenheft-2 §9).
 *
 * Verbindlich: „alle Geldbeträge als Decimal/Integer-Minor-Units, niemals als Float". Diese Klasse rechnet
 * ausschließlich in Minor-Units (z. B. Cent) mit Integer-Arithmetik und formatiert nur für die Anzeige.
 * Rein, ohne WP-Abhängigkeit (unit-testbar).
 *
 * @package Liebherr\InterfaceWorld\IntelligenceWorld
 * @since   0.1.0-alpha.47
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\IntelligenceWorld;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Money {

	/** Betrag (Minor-Units) × Menge, Integer-genau. */
	public static function multiply( int $unit_price_minor, int $quantity ): int {
		return $unit_price_minor * max( 0, $quantity );
	}

	/** Summe einer Liste von Minor-Unit-Beträgen. @param int[] $amounts */
	public static function sum( array $amounts ): int {
		$total = 0;
		foreach ( $amounts as $a ) {
			$total += (int) $a;
		}
		return $total;
	}

	/**
	 * Anzeigeformat aus Minor-Units (2 Nachkommastellen). Reine Formatierung; keine Rundung von Beträgen,
	 * die bereits als Minor-Units vorliegen. Standard: deutsches Format „1.234,56 EUR".
	 */
	public static function format( int $amount_minor, string $currency = 'EUR', string $locale = 'de' ): string {
		$neg   = $amount_minor < 0;
		$abs   = abs( $amount_minor );
		$major = intdiv( $abs, 100 );
		$cents = $abs % 100;

		if ( 'de' === $locale ) {
			$major_str = number_format( $major, 0, ',', '.' );
			$value     = $major_str . ',' . str_pad( (string) $cents, 2, '0', STR_PAD_LEFT );
			return ( $neg ? '-' : '' ) . $value . ' ' . $currency;
		}

		$major_str = number_format( $major, 0, '.', ',' );
		$value     = $major_str . '.' . str_pad( (string) $cents, 2, '0', STR_PAD_LEFT );
		return ( $neg ? '-' : '' ) . $currency . ' ' . $value;
	}
}
