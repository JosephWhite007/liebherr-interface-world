<?php
/**
 * Liebherr Interface Solutions – Language Bridge
 *
 * Einziger Kopplungspunkt zur Sprachermittlung und zum Sprachumschalter des Core
 * (`Araliya\Platform\Core\Language\LanguageService`, `LanguageSwitcherWidget`). Ergebnis der
 * I18nSeo-Analyse 18.09.2026 (LOGBUCH_TECHNIK alpha.24, Option A): Die Core-Sprachsteuerung
 * (Cookie + `?lang=xx`) greift bereits auf allen Seiten inkl. Landingpage und Abschnitten –
 * hier wird nichts nachgebaut, nur gekapselt und mit Fallbacks versehen:
 *   - `current_lang()`: aktuelle Sprache (Core) oder `de` (Pflichtenheft §20 Startsprache).
 *   - `active_langs()`: aktive Sprachen (Core) oder DE/EN/PL (Pflichtenheft §20).
 *   - `switcher_html()`: das fertige Core-Widget (Flaggen-Dropdown, `?lang=`-Links) für den
 *     Shortcode `[liw_language_switcher]` – kein eigener Umschalter.
 *   - `core_router_active()`: true, wenn der Core-I18nRouter (Sprach-Präfixe `/en/…`) aktiv ist –
 *     dann schweigt die SeoBridge, um doppeltes hreflang zu vermeiden.
 *
 * @package Liebherr\InterfaceWorld\CoreBridge
 * @since   0.1.0-alpha.24
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\CoreBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class LanguageBridge {

	public const SHORTCODE = 'liw_language_switcher';

	private const LANGUAGE_SERVICE = 'Araliya\\Platform\\Core\\Language\\LanguageService';
	private const SWITCHER_WIDGET  = 'Araliya\\Platform\\Core\\Language\\LanguageSwitcherWidget';
	private const I18N_ROUTER      = 'Araliya\\Platform\\Core\\Modules\\I18nSeo\\I18nRouter';

	/** Pflichtenheft §20: Startsprachen DE/EN/PL, Default DE. */
	public const FALLBACK_LANGS = [ 'de', 'en', 'pl' ];

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'render_switcher_shortcode' ] );
	}

	public static function current_lang(): string {
		if ( class_exists( self::LANGUAGE_SERVICE ) && method_exists( self::LANGUAGE_SERVICE, 'get_current_lang' ) ) {
			$lang = sanitize_key( (string) call_user_func( [ self::LANGUAGE_SERVICE, 'get_current_lang' ] ) );
			if ( '' !== $lang ) {
				return $lang;
			}
		}
		return self::FALLBACK_LANGS[0];
	}

	/**
	 * @return string[] Sprachcodes. Core `get_active_langs()` liefert Sprach-Datensätze
	 *   (`['code' => 'de', 'label' => …]`), keine Strings – Befund Docker-Selbsttest alpha.24
	 *   (`hreflang="Array"`, im alten SeoBridge-Code seit alpha.1 unbemerkt). Beide Formen werden
	 *   akzeptiert; ungültige Einträge fallen weg, leere Liste → Fallback.
	 */
	public static function active_langs(): array {
		if ( class_exists( self::LANGUAGE_SERVICE ) && method_exists( self::LANGUAGE_SERVICE, 'get_active_langs' ) ) {
			$langs = call_user_func( [ self::LANGUAGE_SERVICE, 'get_active_langs' ] );
			if ( is_array( $langs ) ) {
				$codes = [];
				foreach ( $langs as $key => $entry ) {
					$raw = is_array( $entry ) ? ( $entry['code'] ?? ( is_string( $key ) ? $key : '' ) ) : ( is_object( $entry ) ? ( $entry->code ?? '' ) : $entry );
					$code = is_scalar( $raw ) ? sanitize_key( (string) $raw ) : '';
					if ( 1 === preg_match( '/^[a-z]{2,5}(-[a-z0-9]{2,8})?$/', $code ) && ! in_array( $code, $codes, true ) ) { // nur plausible Sprachcodes (ISO 639 ± Region)
						$codes[] = $code;
					}
				}
				if ( [] !== $codes ) {
					return $codes;
				}
			}
		}
		return self::FALLBACK_LANGS;
	}

	/** Core-Sprachumschalter (fertiges Widget) oder leer, wenn der Core ihn nicht anbietet. */
	public static function switcher_html(): string {
		if ( class_exists( self::SWITCHER_WIDGET ) && method_exists( self::SWITCHER_WIDGET, 'render' ) ) {
			return (string) call_user_func( [ self::SWITCHER_WIDGET, 'render' ] );
		}
		return '';
	}

	public static function render_switcher_shortcode(): string {
		$html = self::switcher_html();
		return '' === $html ? '' : '<div class="liw-language-switcher">' . $html . '</div>';
	}

	/** Core-I18nRouter aktiv (Sprach-Präfix-URLs + eigenes hreflang)? */
	public static function core_router_active(): bool {
		return class_exists( self::I18N_ROUTER ) && method_exists( self::I18N_ROUTER, 'is_enabled' ) && (bool) call_user_func( [ self::I18N_ROUTER, 'is_enabled' ] );
	}

	/**
	 * Sprachabhängige Textversion für Einwilligungen (§24: „Datenschutztexte sprachabhängig
	 * versionieren"): Basisversion + aktuelle Sprache, z. B. `2026-09-18-contact-v1-de`.
	 */
	public static function versioned( string $base_version ): string {
		return $base_version . '-' . self::current_lang();
	}
}
