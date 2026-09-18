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
 * ENTSCHEIDUNG 18.09.2026 (I18nSeo-Analyse, Option A, LOGBUCH_TECHNIK alpha.24): Die Core-
 * Sprachsteuerung (Cookie/`?lang=`) greift bereits überall; der Core-Router deckt Seiten (`page`)
 * ohnehin nicht ab, ein Ergänzen von `liw_section` allein hätte die Trägerseite nicht erfasst.
 * Daher bleibt diese schlanke Lösung, neu ausgerichtet auf die Trägerseite, und schweigt sobald
 * der Core-Router aktiv ist. Option B (Router um `page` + `liw_section` erweitern) bleibt als
 * Folgepunkt, wenn der Router plattformweit aktiv geschaltet wird (docs/LIW_TODO.md).
 *
 * @package Liebherr\InterfaceWorld\CoreBridge
 * @since   0.1.0-alpha.1
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\CoreBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SeoBridge {

	public static function register(): void {
		add_action( 'wp_head', [ self::class, 'render_hreflang' ], 5 );
	}

	/**
	 * Ziel seit alpha.24 (I18nSeo-Analyse, Option A): die öffentliche Fläche ist die Trägerseite mit
	 * `[liw_landingpage]` (plus Einzelansichten von `liw_section`). Schweigt, wenn der Core-I18nRouter
	 * aktiv ist – der liefert dann eigenes hreflang mit Sprach-Präfix-URLs (kein doppeltes Markup).
	 */
	public static function render_hreflang(): void {
		if ( LanguageBridge::core_router_active() || ! self::is_liw_public_view() ) {
			return;
		}

		$langs = LanguageBridge::active_langs();
		if ( count( $langs ) < 2 ) {
			return; // Nur ausgeben, wenn es tatsächlich mehr als eine Sprache gibt (kein Leerlärm).
		}

		echo self::hreflang_markup( (string) get_permalink(), $langs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in hreflang_markup() escaped.
	}

	/** Trägerseite (Seite mit Landingpage-Shortcode) oder Einzelansicht eines Abschnitts. */
	public static function is_liw_public_view(): bool {
		if ( is_singular( 'liw_section' ) ) {
			return true;
		}
		$post = get_post();
		return is_page() && $post instanceof \WP_Post && has_shortcode( (string) $post->post_content, 'liw_landingpage' );
	}

	/**
	 * Reine Markup-Erzeugung (testbar ohne Request): je Sprache `?lang=xx`, x-default = Basis-URL.
	 *
	 * @param string[] $langs
	 */
	public static function hreflang_markup( string $base_url, array $langs ): string {
		$out = '';
		foreach ( $langs as $lang ) {
			$out .= sprintf( '<link rel="alternate" hreflang="%s" href="%s" />' . "\n", esc_attr( $lang ), esc_url( add_query_arg( 'lang', $lang, $base_url ) ) );
		}
		$out .= sprintf( '<link rel="alternate" hreflang="x-default" href="%s" />' . "\n", esc_url( $base_url ) );
		return $out;
	}
}
