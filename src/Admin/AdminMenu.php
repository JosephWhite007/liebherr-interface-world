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
use Liebherr\InterfaceWorld\Admin\Pages\HandbookPage;
use Liebherr\InterfaceWorld\Admin\Pages\HeaderBoardPage;
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

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AdminMenu {

	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'add_menu' ] );
	}

	public static function add_menu(): void {
		add_menu_page(
			__( 'Liebherr Interface World', 'liebherr-interface-world' ),
			__( 'Interface World', 'liebherr-interface-world' ),
			RoleBridge::CAP_MANAGE_CONTENT,
			'liw-interface-board',
			[ InterfaceBoardPage::class, 'render' ],
			'dashicons-networking',
			58 // hinter dem ARALIYA-Kernmenü (Konvention: eigenständige Plugins ordnen sich dahinter ein).
		);

		// Frontpage-Ansichten: Direktlinks zu den öffentlichen Seiten (kein Umschalten nötig).
		// Hauptseite = Local Intelligence, darunter die technische Unterseite Interface Solutions.
		$li_url        = SitePages::li_url();
		$interface_url = SitePages::interface_url();
		$iw_url        = self::iw_url();
		if ( '' !== $iw_url ) {
			add_submenu_page(
				'liw-interface-board',
				__( 'Intelligence World (Landingpage)', 'liebherr-interface-world' ),
				__( '🪐 Intelligence World', 'liebherr-interface-world' ),
				RoleBridge::CAP_MANAGE_CONTENT,
				$iw_url
			);
		}
		if ( '' !== $li_url ) {
			add_submenu_page(
				'liw-interface-board',
				__( 'Local Intelligence (Hauptseite)', 'liebherr-interface-world' ),
				__( '🌍 Local Intelligence', 'liebherr-interface-world' ),
				RoleBridge::CAP_MANAGE_CONTENT,
				$li_url
			);
		}
		if ( '' !== $interface_url ) {
			add_submenu_page(
				'liw-interface-board',
				__( 'Interface Solutions (Frontpage)', 'liebherr-interface-world' ),
				__( '🌐 Interface Solutions', 'liebherr-interface-world' ),
				RoleBridge::CAP_MANAGE_CONTENT,
				$interface_url
			);
		}
		$adv_url = self::adv_url();
		if ( '' !== $adv_url ) {
			add_submenu_page(
				'liw-interface-board',
				__( 'Adventures (Insel)', 'liebherr-interface-world' ),
				__( '📸 Adventures', 'liebherr-interface-world' ),
				RoleBridge::CAP_MANAGE_CONTENT,
				$adv_url
			);
		}

		add_submenu_page(
			'liw-interface-board',
			__( 'Interface Board', 'liebherr-interface-world' ),
			__( 'Interface Board', 'liebherr-interface-world' ),
			RoleBridge::CAP_MANAGE_INTERFACES,
			'liw-interface-board',
			[ InterfaceBoardPage::class, 'render' ]
		);

		add_submenu_page(
			'liw-interface-board',
			__( 'Content Board', 'liebherr-interface-world' ),
			__( 'Content Board', 'liebherr-interface-world' ),
			RoleBridge::CAP_MANAGE_CONTENT,
			ContentBoardPage::MENU_SLUG,
			[ ContentBoardPage::class, 'render' ]
		);

		add_submenu_page(
			'liw-interface-board',
			__( 'Local Intelligence – Inhalte', 'liebherr-interface-world' ),
			__( '🧠 Local Intelligence', 'liebherr-interface-world' ),
			RoleBridge::CAP_MANAGE_CONTENT,
			LocalIntelligenceBoardPage::MENU_SLUG,
			[ LocalIntelligenceBoardPage::class, 'render' ]
		);

		add_submenu_page(
			'liw-interface-board',
			__( 'Simulation Board', 'liebherr-interface-world' ),
			__( 'Simulation Board', 'liebherr-interface-world' ),
			RoleBridge::CAP_MANAGE_INTERFACES,
			SimulationBoardPage::MENU_SLUG,
			[ SimulationBoardPage::class, 'render' ]
		);

		add_submenu_page(
			'liw-interface-board',
			__( 'Adventures – Board & Registrierung', 'liebherr-interface-world' ),
			__( '🗺 Adventures', 'liebherr-interface-world' ),
			RoleBridge::CAP_MANAGE_CONTENT,
			AdventureBoardPage::MENU_SLUG,
			[ AdventureBoardPage::class, 'render' ]
		);

		add_submenu_page(
			'liw-interface-board',
			__( 'World Connections Map', 'liebherr-interface-world' ),
			__( 'World Connections', 'liebherr-interface-world' ),
			RoleBridge::CAP_MANAGE_CONTENT,
			ConnectionBoardPage::MENU_SLUG,
			[ ConnectionBoardPage::class, 'render' ]
		);

		add_submenu_page(
			'liw-interface-board',
			__( 'Onboarding – Partneranfragen', 'liebherr-interface-world' ),
			__( 'Onboarding', 'liebherr-interface-world' ),
			RoleBridge::CAP_VIEW_ONBOARDING,
			OnboardingBoardPage::MENU_SLUG,
			[ OnboardingBoardPage::class, 'render' ]
		);

		add_submenu_page(
			'liw-interface-board',
			__( 'Kontaktanfragen (LP-13)', 'liebherr-interface-world' ),
			__( 'Kontaktanfragen', 'liebherr-interface-world' ),
			RoleBridge::CAP_VIEW_ONBOARDING,
			ContactBoardPage::MENU_SLUG,
			[ ContactBoardPage::class, 'render' ]
		);

		add_submenu_page(
			'liw-interface-board',
			__( 'Partnerdokumente (geschützter Bereich)', 'liebherr-interface-world' ),
			__( 'Partnerdokumente', 'liebherr-interface-world' ),
			RoleBridge::CAP_MANAGE_CONTENT,
			PartnerDocumentBoardPage::MENU_SLUG,
			[ PartnerDocumentBoardPage::class, 'render' ]
		);

		add_submenu_page(
			'liw-interface-board',
			__( 'Media Board', 'liebherr-interface-world' ),
			__( 'Media Board', 'liebherr-interface-world' ),
			RoleBridge::CAP_MANAGE_CONTENT,
			MediaBoardPage::MENU_SLUG,
			[ MediaBoardPage::class, 'render' ]
		);

		add_submenu_page(
			'liw-interface-board',
			__( 'Audit Board', 'liebherr-interface-world' ),
			__( '🛡 Audit Board', 'liebherr-interface-world' ),
			RoleBridge::CAP_MANAGE_INTERFACES,
			AuditBoardPage::MENU_SLUG,
			[ AuditBoardPage::class, 'render' ]
		);

		add_submenu_page(
			'liw-interface-board',
			__( 'Brand Board (Design / CI)', 'liebherr-interface-world' ),
			__( '🎨 Brand Board', 'liebherr-interface-world' ),
			RoleBridge::CAP_MANAGE_CONTENT,
			BrandBoardPage::MENU_SLUG,
			[ BrandBoardPage::class, 'render' ]
		);

		add_submenu_page(
			'liw-interface-board',
			__( 'Header Board (Navigation &amp; CTAs)', 'liebherr-interface-world' ),
			__( '🧭 Header Board', 'liebherr-interface-world' ),
			RoleBridge::CAP_MANAGE_CONTENT,
			HeaderBoardPage::MENU_SLUG,
			[ HeaderBoardPage::class, 'render' ]
		);

		add_submenu_page(
			'liw-interface-board',
			__( 'Components Board (Kern-Komponenten)', 'liebherr-interface-world' ),
			__( '🧩 Components Board', 'liebherr-interface-world' ),
			RoleBridge::CAP_MANAGE_CONTENT,
			ComponentsBoardPage::MENU_SLUG,
			[ ComponentsBoardPage::class, 'render' ]
		);

		add_submenu_page(
			'liw-interface-board',
			__( 'Language Board (Release-Readiness)', 'liebherr-interface-world' ),
			__( '🌐 Language Board', 'liebherr-interface-world' ),
			RoleBridge::CAP_MANAGE_CONTENT,
			LanguageBoardPage::MENU_SLUG,
			[ LanguageBoardPage::class, 'render' ]
		);

		add_submenu_page(
			'liw-interface-board',
			__( 'To-Dos', 'liebherr-interface-world' ),
			__( '📋 To-Dos', 'liebherr-interface-world' ),
			RoleBridge::CAP_MANAGE_INTERFACES,
			'liw-todo',
			[ TodoBoardPage::class, 'render' ]
		);

		add_submenu_page(
			'liw-interface-board',
			__( 'Programmierlogbuch', 'liebherr-interface-world' ),
			__( '🧾 Programmierlogbuch', 'liebherr-interface-world' ),
			RoleBridge::CAP_MANAGE_INTERFACES,
			'liw-programming-log',
			[ ProgrammingLogPage::class, 'render' ]
		);

		// Handbuch-Regel: letzter Reiter des Moduls (CLAUDE.md Abschnitt 1).
		add_submenu_page(
			'liw-interface-board',
			__( 'Handbuch', 'liebherr-interface-world' ),
			__( '📖 Handbuch', 'liebherr-interface-world' ),
			RoleBridge::CAP_MANAGE_CONTENT,
			'liw-handbook',
			[ HandbookPage::class, 'render' ]
		);

		// Reihenfolge oben: Intelligence World · Local Intelligence · Interface Solutions · Adventures.
		// move_first schiebt an Index 0 → in umgekehrter Zielreihenfolge aufrufen.
		if ( '' !== $adv_url ) {
			self::move_first( 'liw-interface-board', $adv_url );
		}
		if ( '' !== $interface_url ) {
			self::move_first( 'liw-interface-board', $interface_url );
		}
		if ( '' !== $li_url ) {
			self::move_first( 'liw-interface-board', $li_url );
		}
		if ( '' !== $iw_url ) {
			self::move_first( 'liw-interface-board', $iw_url );
		}
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
