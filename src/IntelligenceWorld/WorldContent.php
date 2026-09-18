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
				'base_price_minute_minor' => 250,   // 2,50 EUR/min (Beispiel)
				'session_budget_minor'    => 5000,  // 50,00 EUR Beispiel-Sitzungsbudget (für Warnschwellen)
				'storage_budget_mb'       => 5,     // lokales Speicher-Grundbudget (Anzeige, §14)
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
			foreach ( [ 'base_price_minute_minor', 'session_budget_minor', 'storage_budget_mb' ] as $k ) {
				if ( isset( $raw['pricing'][ $k ] ) ) { $out['pricing'][ $k ] = max( 0, (int) $raw['pricing'][ $k ] ); }
			}
			if ( isset( $raw['pricing']['access_code'] ) ) {
				$code = $scalar( $raw['pricing']['access_code'] );
				$out['pricing']['access_code'] = '' !== $code ? $code : $def['pricing']['access_code'];
			}
		}

		return $out;
	}

	public static function access_code(): string {
		return (string) self::get()['pricing']['access_code'];
	}
}
