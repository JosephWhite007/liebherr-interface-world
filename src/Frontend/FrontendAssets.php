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
	private const SHORTCODES = [ 'liw_onboarding_form', 'liw_contact_form', 'liw_world_connections_map', SectionGraphicView::SHORTCODE, LandingpageView::SHORTCODE, PartnerDocumentsView::SHORTCODE, HeaderView::SHORTCODE, HeroView::SHORTCODE ];

	public static function register(): void {
		add_action( 'wp_enqueue_scripts', [ self::class, 'maybe_enqueue' ] );
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
