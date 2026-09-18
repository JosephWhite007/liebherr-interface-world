<?php
/**
 * Liebherr Interface Solutions – Frontend Assets
 *
 * Bindet `assets/css/liebherr-frontend.css` nur dort ein, wo einer der öffentlichen
 * Shortcodes tatsächlich verwendet wird (Performance – kein unnötiges CSS auf jeder
 * Seite, CLAUDE.md Abschnitt „Performance"). Namensgebung/Ort analog zu Core's eigenem
 * `Araliya\Platform\Core\Frontend\DesignSystem` (dort: Tokens/Typografie global; hier:
 * ein einzelnes, klein-scopiges Stylesheet für die sechs Frontend-Shortcodes).
 * `[liw_landingpage]` als Auslöser deckt auch die in Abschnitten eingebetteten Shortcodes ab,
 * die auf der Trägerseite selbst nicht vorkommen (has_shortcode prüft nur deren Inhalt).
 *
 * @package Liebherr\InterfaceWorld\Frontend
 * @since   0.1.0-alpha.9
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Frontend;

use Liebherr\InterfaceWorld\Partner\PartnerDocumentsView;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FrontendAssets {

	private const HANDLE     = 'liw-frontend';
	private const SHORTCODES = [ 'liw_onboarding_form', 'liw_contact_form', 'liw_world_connections_map', SectionGraphicView::SHORTCODE, LandingpageView::SHORTCODE, PartnerDocumentsView::SHORTCODE, HeaderView::SHORTCODE, HeroView::SHORTCODE, ComponentViews::SC_PROCESS, ComponentViews::SC_ROADMAP, ComponentViews::SC_ONBOARDING, WorldMapView::SHORTCODE, FooterView::SHORTCODE ];

	public static function register(): void {
		add_action( 'wp_enqueue_scripts', [ self::class, 'maybe_enqueue' ] );
		// Cache-Busting sicherstellen: manche Umgebungen entfernen `?ver` von statischen Assets
		// (site-weites „remove query strings"), wodurch geänderte CSS/JS im Browser hängen bleiben.
		// Wir hängen für die eigenen Dateien spät eine filemtime-Version an (überlebt das Stripping).
		add_filter( 'style_loader_src', [ self::class, 'bust_src' ], 9999, 2 );
		add_filter( 'script_loader_src', [ self::class, 'bust_src' ], 9999, 2 );
	}

	/** Dateiversion (filemtime) für sicheres Cache-Busting; Fallback Plugin-Version. */
	private static function asset_version( string $relative ): string {
		$path = LIW_PATH . ltrim( $relative, '/' );
		$mtime = is_readable( $path ) ? (int) filemtime( $path ) : 0;
		return $mtime > 0 ? (string) $mtime : LIW_VERSION;
	}

	/** Hängt für die eigenen Frontend-Assets eine filemtime-`?v=` an, falls sie fehlt. */
	public static function bust_src( $src, $handle ) {
		if ( self::HANDLE !== $handle || ! is_string( $src ) || false !== strpos( $src, 'v=' ) ) {
			return $src;
		}
		if ( false !== strpos( $src, 'assets/css/liebherr-frontend.css' ) ) {
			return add_query_arg( 'v', self::asset_version( 'assets/css/liebherr-frontend.css' ), $src );
		}
		if ( false !== strpos( $src, 'assets/js/liebherr-frontend.js' ) ) {
			return add_query_arg( 'v', self::asset_version( 'assets/js/liebherr-frontend.js' ), $src );
		}
		return $src;
	}

	public static function maybe_enqueue(): void {
		if ( ! self::current_post_has_shortcode() ) {
			return;
		}

		wp_enqueue_style(
			self::HANDLE,
			LIW_URL . 'assets/css/liebherr-frontend.css',
			[],
			LIW_VERSION
		);

		// CI-Tokens (§10–12): zentrale --brand-*-Variablen aus dem Brand Board (neutrale Fallbacks,
		// bis Liebherr-Freigabe vorliegt). Sanktionierter Weg für dynamische Tokens, kein hart
		// codierter Markenwert in Komponenten (§11/§14).
		wp_add_inline_style( self::HANDLE, \Liebherr\InterfaceWorld\Branding\BrandTokens::css_root() );

		// Liebherr-Webfonts (@font-face) – nur freigegebene (CI-005). Erst danach greifen die --font-*-Tokens.
		$font_css = FontFaceService::css();
		if ( '' !== $font_css ) {
			wp_add_inline_style( self::HANDLE, $font_css );
		}

		// Aktive Hervorhebung des sichtbaren Abschnitts in der Sprungleiste (alpha.26):
		// fortschreitende Verbesserung, im Footer, ohne Abhängigkeit, kein Inline-Code.
		wp_enqueue_script(
			self::HANDLE,
			LIW_URL . 'assets/js/liebherr-frontend.js',
			[],
			LIW_VERSION,
			true
		);
	}

	private static function current_post_has_shortcode(): bool {
		$post = get_post();
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		foreach ( self::SHORTCODES as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				return true;
			}
		}

		return false;
	}
}
