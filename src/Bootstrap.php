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
		add_action( 'init', [ Adventures\AdventureCpt::class, 'register' ] ); // Liebherr Adventures – vierte Insel (CPT).
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
		IntelligenceWorld\NavigationView::register(); // Intelligence World – Navigation & Hotels (13 Segmente + Lösungswelt + 6 Hotels).
		IntelligenceWorld\SimulationView::register(); // Intelligence World – Simulation Builder (geführte Szenarien/Forecasts, §6.4).
		Adventures\Rest::register();             // Liebherr Adventures REST (Stream/Locate/Create).
		Adventures\AdventuresView::register();   // Liebherr Adventures Insel-Frontend.
		Adventures\DetailView::register();       // Liebherr Adventures – Detailseite eines Beitrags (Backlog A3).
		Adventures\GetHelpAssistant::register(); // Liebherr Adventures – Get-Help-Assistent (Backlog A7).
		LegacyRedirect::register();        // 301 Altroute /interface-world/ → Unterseite (LI §3.2).
		PartnerDocumentsView::register();
		FrontendAssets::register();
		Frontend\FaviconService::register(); // Website-Icon „goldener Planet" im Browser-Tab (site-weit).
			Emergency\EmergencyController::register(); // Hilfe-Koffer + Emergency-Area (plattformweit, Front + Admin).
			Cvf\Rest::register();      // Customer View Flow REST (Durchstich; self-gating ueber liw_cvf_enabled).
			Cvf\FlowView::register();  // Customer View Flow Frontend [liw_cvf_flow] (self-gating).
			MyLiebherr\Rest::register(); // My Liebherr REST (GET/PATCH me; self-gating ueber liw_myl_enabled, ADR-LIW-MYL-001 S1).
			MyLiebherr\OverviewView::register(); // My Overview Startseite [liw_my_liebherr] (ADR-LIW-MYL-001 S4/S3).
			MyLiebherr\ProfileView::register();  // My Profile & Rollen [liw_my_profile] (ADR-LIW-MYL-001 S5).
			MyLiebherr\WalletView::register();   // My Wallet (read-only Salden/Buchungen) [liw_my_wallet] (ADR-LIW-MYL-001 S8).
			MyLiebherr\ContentRest::register();  // Dreams/Gallery/Shares REST (ADR-LIW-MYL-001 R3, §31).
			MyLiebherr\DreamsView::register();   // My Dreams [liw_my_dreams] (§31).
			MyLiebherr\GalleryView::register();  // Own Gallery [liw_my_gallery] (§31).
			MyLiebherr\SharedView::register();   // Shared with Colleagues/World [liw_shared_colleagues|liw_shared_world] (§31).
			MyLiebherr\OwnAdventuresView::register(); // Own Adventures [liw_my_adventures] (§32, Wiederverwendung Insel).
			PlatformTime\Rest::register();       // Plattformzeit REST (start/heartbeat/status/stop; self-gating ueber liw_ptime_enabled, §41).
			PlatformTime\ClockWidget::register(); // Schwebende Session-Uhr / Schachuhr (S11, §41.6).
		Frontend\WorldSwitcher::register();  // Plattform-Umschalter der vier Inseln (Cross-Navigation).
		Frontend\SimulatorView::register();  // Liebherr Simulation World – Startbildschirm [liw_simulator].
		Frontend\RocketCompat::register(); // WP-Rocket-RUCSS-Safelist für .liw-Selektoren.
		Frontend\PageTemplate::register(); // Vollbild-Seitenvorlage (ohne Theme-Kopf/-Fuß).

		if ( is_admin() ) {
			AdminMenu::register();
			AdminAssets::register();
			Admin\Pages\ContentBoardPage::register(); // AJAX-Reorder (§19).
			Admin\Pages\AdventureBoardPage::register(); // Adventures-Board & Registrierung (Basislogik §7/§8).
			Admin\Pages\IntelligenceWorldBoardPage::register(); // IW-Pflege-Board: Tarife + Navigation/Hotels (§19).
			Admin\Pages\CvfBoardPage::register(); // Customer View Flow – Backoffice-Board (Flags/Code/Version/Log, ADR-LIW-CVF-001).
			Admin\Pages\CvfBoardEditorPage::register(); // CAPDB Tabellenansicht/Editor (Pflicht §6).
			Admin\ApprovedMediaFilter::register(); // Medien-Picker auf freigegebene Bibliothek beschränken (CI-005, A11).
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
