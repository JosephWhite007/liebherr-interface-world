<?php
/**
 * Liebherr Interface Solutions – WP-Rocket-Kompatibilität (Frontend-CSS).
 *
 * WP Rocket „Remove Unused CSS" (RUCSS) entfernt Selektoren, die es auf einer Seite nicht als
 * „genutzt" erkennt. Dynamisch aufgebaute Komponenten – insbesondere die Inline-SVG-Weltkarte
 * (`[liw_world_map]`, Klassen an SVG-Elementen) – werden dabei fälschlich gestrippt, sodass sie
 * ungestylt erscheinen (bekannte Falle, s. Projekt-Memory „WP Rocket zuerst prüfen").
 *
 * Lösung: alle `.liw-`-Selektoren in die RUCSS-Safelist aufnehmen (partielle Übereinstimmung deckt
 * auch Kind-Selektoren wie `.liw-worldmap__land` ab). Nur aktiv, wenn WP Rocket den Filter bereitstellt;
 * ohne WP Rocket passiert nichts. Betrifft ausschließlich das eigene Namespaces-CSS.
 *
 * @package Liebherr\InterfaceWorld\Frontend
 * @since   0.1.0-alpha.38
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Frontend;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class RocketCompat {

	public static function register(): void {
		add_filter( 'rocket_rucss_safelist', [ self::class, 'safelist' ] );
		add_filter( 'rocket_delay_js_exclusions', [ self::class, 'delay_js_exclusions' ] );
	}

	/**
	 * Schwebende Plattform-Helfer NICHT per „Delay JavaScript" aufschieben – sonst würde die Session-Uhr
	 * erst nach einer Nutzerinteraktion ticken/starten und das Standby-/Report-Overlay die „Liebherr World"-
	 * Leiste erst verspätet einhängen. Nur aktiv, wenn WP Rocket den Filter bereitstellt.
	 *
	 * @param array<int,string> $excluded
	 * @return array<int,string>
	 */
	public static function delay_js_exclusions( $excluded ): array {
		$excluded = is_array( $excluded ) ? $excluded : [];
		$keep     = [
			'assets/js/liw-ptime-clock.js',
			'assets/js/liw-ptime-lock.js',
			'assets/js/liw-worldbar-lock.js',
			'liwPtime', // Inline-Konfiguration (wp_localize_script) für die Session-Uhr.
			'liwPtimeLock',
		];
		return array_values( array_unique( array_merge( $excluded, $keep ) ) );
	}

	/**
	 * @param array<int,string> $safelist
	 * @return array<int,string>
	 */
	public static function safelist( $safelist ): array {
		$safelist = is_array( $safelist ) ? $safelist : [];
		// Namespaces-Wurzeln; RUCSS behält Selektoren, die einen dieser Strings enthalten.
		$keep = [
			'.liw-header', '.liw-hero', '.liw-footer', '.liw-cta', '.liw-worldmap', '.liw-pworlds', '.liw-pcard',
			'.liw-roadmap', '.liw-steps', '.liw-landingpage', '.liw-connections', '.liw-graphic',
			'.liw-onboarding', '.liw-contact', '.liw-partner-docs', '.liw-visually-hidden', '.liw-li', '.liw-intro', '.liw-iw', '.liw-adv', '.liw-worldmap', '.liw-switcher', '.liw-sim',
			// Schwebende Plattform-Helfer: Session-Uhr/Time-Pille, Standby-/Report-Sperre, Hilfe-Koffer.
			'.liw-ptime', '.liw-ptlock', '.liw-emg', '.liw-header__lang',
		];
		return array_values( array_unique( array_merge( $safelist, $keep ) ) );
	}
}
