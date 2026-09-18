<?php
/**
 * Liebherr Interface Solutions – Bootstrap
 *
 * Wird nur geladen, wenn araliya-platform-core aktiv ist (Guard im Hauptplugin). Registriert
 * CPT, CoreBridge-Adapter und Admin-Board. Analog zu Bootstrap.php im Core, aber bewusst schlank
 * gehalten (Grundgerüst dieser Auslieferung – Phase-3-Plan „Plugin-Grundgerüst + CoreBridge zuerst").
 *
 * @package Liebherr\InterfaceWorld
 * @since   0.1.0-alpha.1
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld;

use Liebherr\InterfaceWorld\Admin\AdminAssets;
use Liebherr\InterfaceWorld\Admin\AdminMenu;
use Liebherr\InterfaceWorld\Connection\ConnectionMapView;
use Liebherr\InterfaceWorld\Contact\ContactExporter;
use Liebherr\InterfaceWorld\Contact\ContactForm;
use Liebherr\InterfaceWorld\Contact\ContactRetention;
use Liebherr\InterfaceWorld\CoreBridge\LanguageBridge;
use Liebherr\InterfaceWorld\CoreBridge\MediaBridge;
use Liebherr\InterfaceWorld\CoreBridge\SeoBridge;
use Liebherr\InterfaceWorld\CoreBridge\TranslationBridge;
use Liebherr\InterfaceWorld\CPT\LiwSectionCpt;
use Liebherr\InterfaceWorld\Frontend\ComponentViews;
use Liebherr\InterfaceWorld\Frontend\FooterView;
use Liebherr\InterfaceWorld\Frontend\FrontendAssets;
use Liebherr\InterfaceWorld\Frontend\HeaderView;
use Liebherr\InterfaceWorld\Frontend\HeroView;
use Liebherr\InterfaceWorld\Frontend\IntroOverlay;
use Liebherr\InterfaceWorld\Frontend\LandingpageView;
use Liebherr\InterfaceWorld\Frontend\LegacyRedirect;
use Liebherr\InterfaceWorld\Frontend\LocalIntelligenceView;
use Liebherr\InterfaceWorld\Frontend\SectionGraphicView;
use Liebherr\InterfaceWorld\Frontend\WorldMapView;
use Liebherr\InterfaceWorld\Onboarding\OnboardingForm;
use Liebherr\InterfaceWorld\Partner\PartnerDocumentsView;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Bootstrap {

	public static function init(): void {
		add_action( 'init', [ LiwSectionCpt::class, 'register' ] );
		add_action( 'init', [ TranslationBridge::class, 'register' ], 21 ); // nach Translation-Modul (Core-Bootstrap-Reihenfolge).

		SeoBridge::register();
		LanguageBridge::register();
		MediaBridge::register();
		OnboardingForm::register();
		ContactForm::register();
		ContactExporter::register();
		ContactRetention::register();
		ConnectionMapView::register();
		SectionGraphicView::register();
		HeaderView::register();
		HeroView::register();
		FooterView::register();
		ComponentViews::register();
		WorldMapView::register();
		LandingpageView::register();
		IntroOverlay::register();          // Intro-Overlay (Sternenregen + Eintritts-Fenster).
		LocalIntelligenceView::register(); // Hauptseite Local Intelligence (11 Module, LI-Pflichtenheft §8).
		IntelligenceWorld\Rest::register();      // Intelligence World REST (Eintritt/Sitzung, Pflichtenheft-2 §4.2/§7).
		IntelligenceWorld\WorldView::register(); // Intelligence World Frontend (Blue Planet + Eintrittsschleuse).
		LegacyRedirect::register();        // 301 Altroute /interface-world/ → Unterseite (LI §3.2).
		PartnerDocumentsView::register();
		FrontendAssets::register();
		Frontend\FaviconService::register(); // Website-Icon „goldener Planet" im Browser-Tab (site-weit).
		Frontend\RocketCompat::register(); // WP-Rocket-RUCSS-Safelist für .liw-Selektoren.
		Frontend\PageTemplate::register(); // Vollbild-Seitenvorlage (ohne Theme-Kopf/-Fuß).

		if ( is_admin() ) {
			AdminMenu::register();
			AdminAssets::register();
			Admin\Pages\ContentBoardPage::register(); // AJAX-Reorder (§19).
			Admin\SectionScheduleMetabox::register();  // Sichtbarkeits-Zeitfenster (§19).
		}

		add_action( 'admin_notices', [ self::class, 'maybe_show_permalink_notice' ] );
		add_action( 'load-options-permalink.php', [ self::class, 'clear_permalink_notice_on_save' ] );
	}

	/** Löscht den Permalink-Hinweis, sobald Einstellungen → Permalinks tatsächlich gespeichert wurde. */
	public static function clear_permalink_notice_on_save(): void {
		if ( isset( $_POST['submit'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WP-eigene Permalink-Seite prüft die Nonce bereits selbst.
			delete_option( 'liw_flush_rewrite_needed' );
			flush_rewrite_rules();
		}
	}

	/** Permalink-Hinweis (CLAUDE.md DoD Punkt 7): CPT-Rewrite-Regeln erfordern einen Flush. */
	public static function maybe_show_permalink_notice(): void {
		if ( '1' !== get_option( 'liw_flush_rewrite_needed' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="notice notice-warning is-dismissible"><p>';
		echo esc_html__(
			'Liebherr Interface Solutions wurde aktiviert: Bitte einmalig Einstellungen → Permalinks → Speichern aufrufen, damit die Abschnitts-URLs funktionieren.',
			'liebherr-interface-world'
		);
		echo '</p></div>';
	}
}
