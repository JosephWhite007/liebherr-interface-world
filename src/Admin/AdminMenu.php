<?php
/**
 * Liebherr Interface Solutions – Admin Menu
 *
 * Eigener Top-Level-Menüpunkt (Plugin ist eigenständig, keine Ein-Plugin-Regel-Bindung an das
 * Core-Menü). Handbuch-Regel (CLAUDE.md Abschnitt 1): letzter Reiter des Moduls.
 *
 * @package Liebherr\InterfaceWorld\Admin
 * @since   0.1.0-alpha.1
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin;

use Liebherr\InterfaceWorld\Admin\Pages\AdventureBoardPage;
use Liebherr\InterfaceWorld\Admin\Pages\AuditBoardPage;
use Liebherr\InterfaceWorld\Admin\Pages\BrandBoardPage;
use Liebherr\InterfaceWorld\Admin\Pages\ComponentsBoardPage;
use Liebherr\InterfaceWorld\Admin\Pages\ConnectionBoardPage;
use Liebherr\InterfaceWorld\Admin\Pages\ContactBoardPage;
use Liebherr\InterfaceWorld\Admin\Pages\ContentBoardPage;
use Liebherr\InterfaceWorld\Admin\Pages\CvfBoardPage;
use Liebherr\InterfaceWorld\Admin\Pages\CvfBoardEditorPage;
use Liebherr\InterfaceWorld\Admin\Pages\HandbookPage;
use Liebherr\InterfaceWorld\Admin\Pages\HeaderBoardPage;
use Liebherr\InterfaceWorld\Admin\Pages\IntelligenceWorldBoardPage;
use Liebherr\InterfaceWorld\Admin\Pages\InterfaceBoardPage;
use Liebherr\InterfaceWorld\Admin\Pages\LanguageBoardPage;
use Liebherr\InterfaceWorld\Admin\Pages\LocalIntelligenceBoardPage;
use Liebherr\InterfaceWorld\Admin\Pages\MediaBoardPage;
use Liebherr\InterfaceWorld\Admin\Pages\OnboardingBoardPage;
use Liebherr\InterfaceWorld\Admin\Pages\PartnerDocumentBoardPage;
use Liebherr\InterfaceWorld\Admin\Pages\ProgrammingLogPage;
use Liebherr\InterfaceWorld\Admin\Pages\SimulationBoardPage;
use Liebherr\InterfaceWorld\Admin\Pages\TodoBoardPage;
use Liebherr\InterfaceWorld\Content\SitePages;
use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;
use Liebherr\InterfaceWorld\Cvf\Roles as CvfRoles;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AdminMenu {

	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'add_menu' ] );
	}

	public static function add_menu(): void {
		$li_url        = SitePages::li_url();
		$interface_url = SitePages::interface_url();
		$iw_url        = self::iw_url();
		$adv_url       = self::adv_url();
		$myl_url       = self::page_url( 'liw_my_liebherr_page_id' ); // My Liebherr (persönlicher Bereich).
		$pocket_url    = self::page_url( 'liw_pocket_page_id' );      // Pocket Information (6. Reiter).
		$cc            = RoleBridge::CAP_MANAGE_CONTENT;
		$ci            = RoleBridge::CAP_MANAGE_INTERFACES;

		// ═══ FRONTEND – die vier begehbaren Welten (Direktlinks zur Live-Seite) ═══
		add_menu_page(
			__( 'Liebherr Frontend', 'liebherr-interface-world' ),
			__( 'Liebherr Frontend', 'liebherr-interface-world' ),
			$cc,
			'liw-frontend',
			[ self::class, 'render_frontend' ],
			'dashicons-admin-site-alt3',
			58
		);
		add_submenu_page( 'liw-frontend', __( 'Frontend – Übersicht', 'liebherr-interface-world' ), __( 'Übersicht', 'liebherr-interface-world' ), $cc, 'liw-frontend', [ self::class, 'render_frontend' ] );
		if ( '' !== $iw_url ) {
			add_submenu_page( 'liw-frontend', __( 'Intelligence World', 'liebherr-interface-world' ), __( '🪐 Intelligence World', 'liebherr-interface-world' ), $cc, $iw_url );
		}
		if ( '' !== $li_url ) {
			add_submenu_page( 'liw-frontend', __( 'Local Intelligence', 'liebherr-interface-world' ), __( '🌍 Local Intelligence', 'liebherr-interface-world' ), $cc, $li_url );
		}
		if ( '' !== $interface_url ) {
			add_submenu_page( 'liw-frontend', __( 'Interface Solutions', 'liebherr-interface-world' ), __( '🌐 Interface Solutions', 'liebherr-interface-world' ), $cc, $interface_url );
		}
		if ( '' !== $adv_url ) {
			add_submenu_page( 'liw-frontend', __( 'Adventures', 'liebherr-interface-world' ), __( '📸 Adventures', 'liebherr-interface-world' ), $cc, $adv_url );
		}
		// Persönliche Reiter (My Liebherr §29 + Pocket Information §34) – Direktlinks zur Live-Seite, sobald angelegt.
		if ( '' !== $myl_url ) {
			add_submenu_page( 'liw-frontend', __( 'My Liebherr', 'liebherr-interface-world' ), __( '👤 My Liebherr', 'liebherr-interface-world' ), $cc, $myl_url );
		}
		if ( '' !== $pocket_url ) {
			add_submenu_page( 'liw-frontend', __( 'Pocket Information', 'liebherr-interface-world' ), __( '🎒 Pocket Information', 'liebherr-interface-world' ), $cc, $pocket_url );
		}
		// Reihenfolge der Welten zuerst: IW · LI · IF · ADV (My Liebherr · Pocket folgen dahinter).
		foreach ( [ $adv_url, $interface_url, $li_url, $iw_url ] as $__u ) {
			if ( '' !== $__u ) { self::move_first( 'liw-frontend', $__u ); }
		}

		// ═══ BACKOFFICE – Pflege-Boards + Administration Plattform ═══
		add_menu_page(
			__( 'Liebherr Backoffice', 'liebherr-interface-world' ),
			__( 'Liebherr Backoffice', 'liebherr-interface-world' ),
			$cc,
			'liw-interface-board',
			[ InterfaceBoardPage::class, 'render' ],
			'dashicons-networking',
			59
		);
		add_submenu_page( 'liw-interface-board', __( 'Interface Board (Schnittstellenkatalog)', 'liebherr-interface-world' ), __( 'Interface Board', 'liebherr-interface-world' ), $ci, 'liw-interface-board', [ InterfaceBoardPage::class, 'render' ] );
		add_submenu_page( 'liw-interface-board', __( 'Content Board', 'liebherr-interface-world' ), __( 'Content Board', 'liebherr-interface-world' ), $cc, ContentBoardPage::MENU_SLUG, [ ContentBoardPage::class, 'render' ] );
		add_submenu_page( 'liw-interface-board', __( 'Intelligence World – Pflege', 'liebherr-interface-world' ), __( '🪐 Intelligence World', 'liebherr-interface-world' ), $cc, IntelligenceWorldBoardPage::MENU_SLUG, [ IntelligenceWorldBoardPage::class, 'render' ] );
		add_submenu_page( 'liw-interface-board', __( 'Local Intelligence – Inhalte', 'liebherr-interface-world' ), __( '🧠 Local Intelligence', 'liebherr-interface-world' ), $cc, LocalIntelligenceBoardPage::MENU_SLUG, [ LocalIntelligenceBoardPage::class, 'render' ] );
		add_submenu_page( 'liw-interface-board', __( 'Adventures – Board & Registrierung', 'liebherr-interface-world' ), __( '🗺 Adventures', 'liebherr-interface-world' ), $cc, AdventureBoardPage::MENU_SLUG, [ AdventureBoardPage::class, 'render' ] );
		add_submenu_page( 'liw-interface-board', __( 'Simulation Board', 'liebherr-interface-world' ), __( 'Simulation Board', 'liebherr-interface-world' ), $ci, SimulationBoardPage::MENU_SLUG, [ SimulationBoardPage::class, 'render' ] );
		add_submenu_page( 'liw-interface-board', __( 'World Connections Map', 'liebherr-interface-world' ), __( '🌐 World Connections', 'liebherr-interface-world' ), $cc, ConnectionBoardPage::MENU_SLUG, [ ConnectionBoardPage::class, 'render' ] );
		add_submenu_page( 'liw-interface-board', __( 'Media Board', 'liebherr-interface-world' ), __( '🖼 Media Board', 'liebherr-interface-world' ), $cc, MediaBoardPage::MENU_SLUG, [ MediaBoardPage::class, 'render' ] );
		add_submenu_page( 'liw-interface-board', __( 'Brand Board (Design / CI)', 'liebherr-interface-world' ), __( '🎨 Brand Board', 'liebherr-interface-world' ), $cc, BrandBoardPage::MENU_SLUG, [ BrandBoardPage::class, 'render' ] );
		add_submenu_page( 'liw-interface-board', __( 'Header Board (Navigation & CTAs)', 'liebherr-interface-world' ), __( '🧭 Header Board', 'liebherr-interface-world' ), $cc, HeaderBoardPage::MENU_SLUG, [ HeaderBoardPage::class, 'render' ] );
		add_submenu_page( 'liw-interface-board', __( 'Components Board (Kern-Komponenten)', 'liebherr-interface-world' ), __( '🧩 Components Board', 'liebherr-interface-world' ), $cc, ComponentsBoardPage::MENU_SLUG, [ ComponentsBoardPage::class, 'render' ] );
		add_submenu_page( 'liw-interface-board', __( 'Language Board (Release-Readiness)', 'liebherr-interface-world' ), __( '🌐 Language Board', 'liebherr-interface-world' ), $cc, LanguageBoardPage::MENU_SLUG, [ LanguageBoardPage::class, 'render' ] );
		add_submenu_page( 'liw-interface-board', __( 'Onboarding – Partneranfragen', 'liebherr-interface-world' ), __( 'Onboarding', 'liebherr-interface-world' ), RoleBridge::CAP_VIEW_ONBOARDING, OnboardingBoardPage::MENU_SLUG, [ OnboardingBoardPage::class, 'render' ] );
		add_submenu_page( 'liw-interface-board', __( 'Kontaktanfragen (LP-13)', 'liebherr-interface-world' ), __( 'Kontaktanfragen', 'liebherr-interface-world' ), RoleBridge::CAP_VIEW_ONBOARDING, ContactBoardPage::MENU_SLUG, [ ContactBoardPage::class, 'render' ] );
		add_submenu_page( 'liw-interface-board', __( 'Partnerdokumente (geschützter Bereich)', 'liebherr-interface-world' ), __( 'Partnerdokumente', 'liebherr-interface-world' ), $cc, PartnerDocumentBoardPage::MENU_SLUG, [ PartnerDocumentBoardPage::class, 'render' ] );

		// ─── Administration Plattform (Verwaltungsbereich innerhalb des Backoffice) ───
		add_submenu_page( 'liw-interface-board', __( 'Administration Plattform', 'liebherr-interface-world' ), __( '⚙ Administration Plattform', 'liebherr-interface-world' ), CvfRoles::CAP_ADMINISTER, 'liw-admin-platform', [ self::class, 'render_admin_platform' ] );
		add_submenu_page( 'liw-interface-board', __( 'Customer View Flow', 'liebherr-interface-world' ), __( '🧭 Customer View Flow', 'liebherr-interface-world' ), CvfRoles::CAP_ADMINISTER, CvfBoardPage::MENU_SLUG, [ CvfBoardPage::class, 'render' ] );
		add_submenu_page( 'liw-interface-board', __( 'CVF Board (Tabellenansicht)', 'liebherr-interface-world' ), __( '🧭 CVF Board', 'liebherr-interface-world' ), CvfRoles::CAP_EDIT_WORKFLOW, CvfBoardEditorPage::MENU_SLUG, [ CvfBoardEditorPage::class, 'render' ] );
		add_submenu_page( 'liw-interface-board', __( 'Audit Board', 'liebherr-interface-world' ), __( '🛡 Audit Board', 'liebherr-interface-world' ), $ci, AuditBoardPage::MENU_SLUG, [ AuditBoardPage::class, 'render' ] );
		add_submenu_page( 'liw-interface-board', __( 'To-Dos', 'liebherr-interface-world' ), __( '📋 To-Dos', 'liebherr-interface-world' ), $ci, 'liw-todo', [ TodoBoardPage::class, 'render' ] );
		add_submenu_page( 'liw-interface-board', __( 'Programmierlogbuch', 'liebherr-interface-world' ), __( '🧾 Programmierlogbuch', 'liebherr-interface-world' ), $ci, 'liw-programming-log', [ ProgrammingLogPage::class, 'render' ] );
		// Handbuch-Regel: letzter Reiter des Moduls (CLAUDE.md Abschnitt 1).
		add_submenu_page( 'liw-interface-board', __( 'Handbuch', 'liebherr-interface-world' ), __( '📖 Handbuch', 'liebherr-interface-world' ), $cc, 'liw-handbook', [ HandbookPage::class, 'render' ] );
	}

	/** Landeseite des Frontend-Menüs: Links zu den vier begehbaren Welten. */
	public static function render_frontend(): void {
		$items = [
			[ self::iw_url(), '🪐 Intelligence World' ],
			[ SitePages::li_url(), '🌍 Local Intelligence' ],
			[ SitePages::interface_url(), '🌐 Interface Solutions' ],
			[ self::adv_url(), '📸 Adventures' ],
			[ self::page_url( 'liw_my_liebherr_page_id' ), '👤 My Liebherr' ],
			[ self::page_url( 'liw_pocket_page_id' ), '🎒 Pocket Information' ],
		];
		echo '<div class="wrap"><h1>' . esc_html__( 'Liebherr Frontend', 'liebherr-interface-world' ) . '</h1>';
		echo '<p>' . esc_html__( 'Die begehbaren Welten und die persönlichen Reiter der Plattform (öffnet die Live-Seite in neuem Tab).', 'liebherr-interface-world' ) . '</p><ul class="ul-disc">';
		foreach ( $items as $it ) {
			if ( '' !== $it[0] ) {
				echo '<li><a href="' . esc_url( (string) $it[0] ) . '" target="_blank" rel="noopener">' . esc_html( (string) $it[1] ) . '</a></li>';
			}
		}
		echo '</ul></div>';
	}

	/** Landeseite des Bereichs „Administration Plattform": Links zu Workflow- und System-Verwaltung. */
	public static function render_admin_platform(): void {
		$links = [
			[ CvfBoardPage::MENU_SLUG, '🧭 Customer View Flow' ],
			[ CvfBoardEditorPage::MENU_SLUG, '🧭 CVF Board (Tabellenansicht + visuelles Board)' ],
			[ AuditBoardPage::MENU_SLUG, '🛡 Audit Board' ],
			[ 'liw-programming-log', '🧾 Programmierlogbuch' ],
			[ 'liw-todo', '📋 To-Dos' ],
			[ 'liw-handbook', '📖 Handbuch' ],
		];
		echo '<div class="wrap"><h1>' . esc_html__( 'Administration Plattform', 'liebherr-interface-world' ) . '</h1>';
		echo '<p>' . esc_html__( 'Plattform-Steuerung: geführte Besucher-Workflows (Customer View Flow / CAPDB-Board), Audit und Nachvollziehbarkeit.', 'liebherr-interface-world' ) . '</p><ul class="ul-disc">';
		foreach ( $links as $l ) {
			echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=' . $l[0] ) ) . '">' . esc_html( (string) $l[1] ) . '</a></li>';
		}
		echo '</ul></div>';
	}

	/** URL der Adventures-Insel (Option/Slug); leer, wenn nicht vorhanden. */
	private static function adv_url(): string {
		$id = (int) get_option( 'liw_adventures_page_id', 0 );
		if ( $id > 0 && 'publish' === get_post_status( $id ) ) {
			return (string) get_permalink( $id );
		}
		$page = get_page_by_path( 'liebherr-adventures' );
		return $page instanceof \WP_Post ? (string) get_permalink( $page ) : '';
	}

	/** URL einer per Option hinterlegten, veröffentlichten Seite (My Liebherr / Pocket); sonst leer. */
	private static function page_url( string $option ): string {
		$id = (int) get_option( $option, 0 );
		return ( $id > 0 && 'publish' === get_post_status( $id ) ) ? (string) get_permalink( $id ) : '';
	}

	/** URL der eigenständigen Intelligence-World-Landingpage (Option/Slug); leer, wenn nicht vorhanden. */
	private static function iw_url(): string {
		$id = (int) get_option( 'liw_iw_page_id', 0 );
		if ( $id > 0 && 'publish' === get_post_status( $id ) ) {
			return (string) get_permalink( $id );
		}
		$page = get_page_by_path( 'liebherr-intelligence-world' );
		return $page instanceof \WP_Post ? (string) get_permalink( $page ) : '';
	}

	/** Verschiebt den Untermenü-Eintrag mit gegebenem Slug an die erste Position. */
	private static function move_first( string $parent, string $slug ): void {
		global $submenu;
		if ( empty( $submenu[ $parent ] ) || ! is_array( $submenu[ $parent ] ) ) {
			return;
		}
		foreach ( $submenu[ $parent ] as $index => $item ) {
			if ( isset( $item[2] ) && $item[2] === $slug ) {
				$entry = $item;
				unset( $submenu[ $parent ][ $index ] );
				array_unshift( $submenu[ $parent ], $entry );
				$submenu[ $parent ] = array_values( $submenu[ $parent ] );
				return;
			}
		}
	}
}
