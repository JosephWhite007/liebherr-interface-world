<?php
/**
 * Liebherr Interface Solutions – SEO Bridge
 *
 * ABWEICHUNG von der ursprünglichen Annahme (Phase-3-Plan): `Modules\I18nSeo\I18nRouter`
 * wurde vor der Implementierung geprüft und ist NICHT generisch – der Router ist fest auf
 * `POST_TYPES = ['suite','apartment','treeroom']` verdrahtet (araliya-platform-core,
 * src/Modules/I18nSeo/I18nRouter.php). Eine Bindung an `liw_section` hätte eine Änderung an
 * dieser Kernkomponente des Core-Plugins erfordert – das ist außerhalb des Liebherr-Moduls und
 * laut CLAUDE.md Abschnitt 30 nur mit ausdrücklicher Begründung/Freigabe zulässig (Kategorie A:
 * Eingriff in eine öffentliche Core-Komponente). Es wurde NICHT eigenmächtig geändert.
 *
 * Stattdessen: eigene, minimale hreflang-/Canonical-Ausgabe für die Liebherr-Routen über
 * Standard-WP-Hooks (`wp_head`) – kein Nachbau eines Sprachsystems, nur Meta-Tag-Ausgabe auf
 * Basis der ohnehin über die Translation Registry gepflegten aktiven Sprachen.
 *
 * OFFENER PUNKT (an JW): Soll `liw_section` stattdessen nachträglich in den Router-Scope von
 * I18nRouter aufgenommen werden (Core-Änderung, Kategorie A)? Bis zur Freigabe bleibt die
 * hier beschriebene eigenständige, schlanke Lösung aktiv.
 *
 * @package Liebherr\InterfaceWorld\CoreBridge
 * @since   0.1.0-alpha.1
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\CoreBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SeoBridge {

	private const LANGUAGE_SERVICE_CLASS = 'Araliya\\Platform\\Core\\Language\\LanguageService';

	public static function register(): void {
		add_action( 'wp_head', [ self::class, 'render_hreflang' ], 5 );
	}

	public static function render_hreflang(): void {
		if ( ! is_singular( 'liw_section' ) ) {
			return;
		}

		$langs = self::active_languages();
		if ( count( $langs ) < 2 ) {
			return; // Nur ausgeben, wenn es tatsächlich mehr als eine Sprache gibt (kein Leerlärm).
		}

		global $post;
		foreach ( $langs as $lang ) {
			$url = add_query_arg( 'lang', $lang, get_permalink( $post ) );
			printf(
				'<link rel="alternate" hreflang="%s" href="%s" />' . "\n",
				esc_attr( $lang ),
				esc_url( $url )
			);
		}
		printf(
			'<link rel="alternate" hreflang="x-default" href="%s" />' . "\n",
			esc_url( get_permalink( $post ) )
		);
	}

	/** @return string[] */
	private static function active_languages(): array {
		if ( class_exists( self::LANGUAGE_SERVICE_CLASS ) && method_exists( self::LANGUAGE_SERVICE_CLASS, 'get_active_langs' ) ) {
			$langs = call_user_func( [ self::LANGUAGE_SERVICE_CLASS, 'get_active_langs' ] );
			if ( is_array( $langs ) && $langs !== [] ) {
				return array_map( 'strval', $langs );
			}
		}
		// Fallback laut Liebherr-Pflichtenheft §20: Startsprachen DE/EN/PL.
		return [ 'de', 'en', 'pl' ];
	}
}
