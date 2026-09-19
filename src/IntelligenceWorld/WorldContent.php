<?php
/**
 * Liebherr Intelligence World – Inhalt & Prototyp-Konfiguration der Landingpage (Pflichtenheft-2 §4/§9/§19).
 *
 * Option `liw_iw_world`: Texte der Blue-Planet-Landing, der Eintrittsschleuse (Access Gate) und die
 * administrierbaren Prototyp-Tarife/Budgets. Nichts fest im Frontend codiert (§9/§19). Standardtexte via
 * `__()`. `defaults()/get()/save()/sanitize()` sind rein und unit-testbar.
 *
 * PROTOTYP (§21): Der Access-Code ist ein niedrigschwelliger Demo-Code (kein echtes Login/keine echte
 * Authentifizierung); die Preise/Budgets sind Beispielwerte, es findet keine echte Abrechnung statt.
 *
 * @package Liebherr\InterfaceWorld\IntelligenceWorld
 * @since   0.1.0-alpha.48
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\IntelligenceWorld;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class WorldContent {

	public const OPTION = 'liw_iw_world';

	/** @return array<string,mixed> */
	public static function defaults(): array {
		return [
			'landing' => [
				'eyebrow'  => __( 'LIEBHERR INTELLIGENCE WORLD', 'liebherr-interface-world' ),
				'headline' => __( 'Liebherr Intelligence World', 'liebherr-interface-world' ),
				'subline'  => __( 'Globale Informationen. Vernetzte Simulationen. Fundierte Entscheidungen.', 'liebherr-interface-world' ),
				'cta'      => __( 'Intelligence World betreten', 'liebherr-interface-world' ),
			],
			'gate' => [
				'heading'          => __( 'Eintritt in die Intelligence World', 'liebherr-interface-world' ),
				'prototype_note'   => __( 'Prototyp / interne Demonstration. Dargestellte Inhalte und Zahlen sind Beispieldaten und vertraulich zu behandeln.', 'liebherr-interface-world' ),
				'code_label'       => __( 'Bestätigungscode', 'liebherr-interface-world' ),
				'price_info'       => __( 'Mit dem Eintritt beginnt die zeitbasierte Abrechnung. Zusätzlich genutzte kostenpflichtige Module und Rechenleistungen werden gesondert protokolliert und nach der jeweils angezeigten Preisregel berechnet.', 'liebherr-interface-world' ),
				'consent_terms'    => __( 'Ich habe die Prototyp- und Vertraulichkeitshinweise sowie die Nutzungsbedingungen gelesen und akzeptiere sie.', 'liebherr-interface-world' ),
				'consent_storage'  => __( 'Ich willige in die technisch notwendige lokale Speicherung innerhalb des angezeigten Speicher-Grundbudgets ein.', 'liebherr-interface-world' ),
				'confirm_label'    => __( 'Bestätigungscode prüfen und kostenpflichtige Sitzung starten', 'liebherr-interface-world' ),
				'terms_heading'    => __( 'Erweiterte Nutzungsbedingungen (Auszug)', 'liebherr-interface-world' ),
				'terms_button'     => __( 'Nutzungsbedingungen anzeigen', 'liebherr-interface-world' ),
			],
			// Prototyp-Tarife/Budgets (Beispieldaten, administrierbar). Geld in Minor-Units (Cent).
			'pricing' => [
				'currency'                => 'EUR',
				'price_unit'              => 'second',   // Abrechnungstakt der Basiskosten (Sekunde)
				'base_price_second_minor' => 9,          // 0,09 EUR/Sek. (Beispiel)
				'budget_period'           => 'month',    // Bezugszeitraum des Budgets (Monat)
				'session_budget_minor'    => 500000,     // 5.000,00 EUR Budget pro Monat (für Warnschwellen)
				'storage_budget_mb'       => 1048576,    // 1 TB lokaler Speicher (Mindestwert, Anzeige §14)
				'storage_is_minimum'      => true,       // „min." – der angezeigte Speicher ist ein Mindestwert
				'access_code'             => 'LIEBHERR-DEMO', // Demo-Code (kein echtes Login, §21)
			],
		];
	}

	/** @return array<string,mixed> */
	public static function get(): array {
		$stored = get_option( self::OPTION, [] );
		if ( ! is_array( $stored ) || [] === $stored ) {
			return self::defaults();
		}
		return self::sanitize( $stored );
	}

	/**
	 * @param array<string,mixed> $raw
	 * @return array<string,mixed>
	 */
	public static function save( array $raw ): array {
		$clean = self::sanitize( $raw );
		update_option( self::OPTION, $clean );
		return $clean;
	}

	/**
	 * @param array<string,mixed> $raw
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $raw ): array {
		$def = self::defaults();
		$out = $def;

		$scalar = static fn( $v ): string => sanitize_text_field( (string) $v );
		$multi  = static fn( $v ): string => sanitize_textarea_field( (string) $v );

		if ( isset( $raw['landing'] ) && is_array( $raw['landing'] ) ) {
			foreach ( [ 'eyebrow', 'headline', 'cta' ] as $k ) {
				if ( isset( $raw['landing'][ $k ] ) ) { $out['landing'][ $k ] = $scalar( $raw['landing'][ $k ] ); }
			}
			if ( isset( $raw['landing']['subline'] ) ) { $out['landing']['subline'] = $multi( $raw['landing']['subline'] ); }
		}

		if ( isset( $raw['gate'] ) && is_array( $raw['gate'] ) ) {
			foreach ( [ 'heading', 'code_label', 'confirm_label', 'terms_heading', 'terms_button' ] as $k ) {
				if ( isset( $raw['gate'][ $k ] ) ) { $out['gate'][ $k ] = $scalar( $raw['gate'][ $k ] ); }
			}
			foreach ( [ 'prototype_note', 'price_info', 'consent_terms', 'consent_storage' ] as $k ) {
				if ( isset( $raw['gate'][ $k ] ) ) { $out['gate'][ $k ] = $multi( $raw['gate'][ $k ] ); }
			}
		}

		if ( isset( $raw['pricing'] ) && is_array( $raw['pricing'] ) ) {
			if ( isset( $raw['pricing']['currency'] ) ) {
				$cur = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $raw['pricing']['currency'] ) ?? '' );
				$out['pricing']['currency'] = '' !== $cur ? substr( $cur, 0, 3 ) : $def['pricing']['currency'];
			}
			foreach ( [ 'base_price_second_minor', 'session_budget_minor', 'storage_budget_mb' ] as $k ) {
				if ( isset( $raw['pricing'][ $k ] ) ) { $out['pricing'][ $k ] = max( 0, (int) $raw['pricing'][ $k ] ); }
			}
			if ( isset( $raw['pricing']['price_unit'] ) ) {
				$u = sanitize_key( (string) $raw['pricing']['price_unit'] );
				$out['pricing']['price_unit'] = in_array( $u, [ 'second', 'minute', 'hour' ], true ) ? $u : $def['pricing']['price_unit'];
			}
			if ( isset( $raw['pricing']['budget_period'] ) ) {
				$p = sanitize_key( (string) $raw['pricing']['budget_period'] );
				$out['pricing']['budget_period'] = in_array( $p, [ 'session', 'day', 'month', 'year' ], true ) ? $p : $def['pricing']['budget_period'];
			}
			if ( isset( $raw['pricing']['storage_is_minimum'] ) ) {
				$out['pricing']['storage_is_minimum'] = (bool) $raw['pricing']['storage_is_minimum'];
			}
			if ( isset( $raw['pricing']['access_code'] ) ) {
				$code = $scalar( $raw['pricing']['access_code'] );
				$out['pricing']['access_code'] = '' !== $code ? $code : $def['pricing']['access_code'];
			}
		}

		return $out;
	}

	/** Menschliche Bezeichnung des Abrechnungstakts (für „… / <Einheit>"). */
	public static function unit_label( string $unit ): string {
		switch ( $unit ) {
			case 'second': return __( 'Sek.', 'liebherr-interface-world' );
			case 'minute': return __( 'min', 'liebherr-interface-world' );
			case 'hour':   return __( 'Std.', 'liebherr-interface-world' );
			default:       return $unit;
		}
	}

	/** Menschliche Bezeichnung des Budget-Zeitraums (für „… / <Zeitraum>"). */
	public static function period_label( string $period ): string {
		switch ( $period ) {
			case 'session': return __( 'Sitzung', 'liebherr-interface-world' );
			case 'day':     return __( 'Tag', 'liebherr-interface-world' );
			case 'month':   return __( 'Monat', 'liebherr-interface-world' );
			case 'year':    return __( 'Jahr', 'liebherr-interface-world' );
			default:        return $period;
		}
	}

	/**
	 * Formatiert ein Speicher-Grundbudget aus Megabyte in eine lesbare Einheit (MB/GB/TB, 1024er-Schritte).
	 * `$is_minimum` hängt „ (min.)" an. Rein und unit-testbar.
	 */
	public static function storage_label( int $mb, bool $is_minimum = false ): string {
		$mb   = max( 0, $mb );
		$fmt  = static function ( float $v ): string {
			return rtrim( rtrim( number_format( $v, 2, ',', '.' ), '0' ), ',' );
		};
		if ( $mb >= 1048576 ) {
			$out = $fmt( $mb / 1048576 ) . ' TB';
		} elseif ( $mb >= 1024 ) {
			$out = $fmt( $mb / 1024 ) . ' GB';
		} else {
			$out = $mb . ' MB';
		}
		return $is_minimum ? $out . ' ' . __( '(min.)', 'liebherr-interface-world' ) : $out;
	}

	public static function access_code(): string {
		return (string) self::get()['pricing']['access_code'];
	}
}
