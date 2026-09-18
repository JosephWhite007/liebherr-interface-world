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
use Liebherr\InterfaceWorld\Admin\Pages\MediaBoardPage;
use Liebherr\InterfaceWorld\Admin\Pages\OnboardingBoardPage;
use Liebherr\InterfaceWorld\Admin\Pages\PartnerDocumentBoardPage;
use Liebherr\InterfaceWorld\Admin\Pages\ProgrammingLogPage;
use Liebherr\InterfaceWorld\Admin\Pages\SimulationBoardPage;
use Liebherr\InterfaceWorld\Admin\Pages\TodoBoardPage;
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

		// Frontpage-Ansicht: Direktlink zur öffentlichen Landingpage (erster Unterpunkt – kein Umschalten nötig).
		$front_url = self::front_url();
		if ( '' !== $front_url ) {
			add_submenu_page(
				'liw-interface-board',
				__( 'Frontpage-Ansicht', 'liebherr-interface-world' ),
				__( '🌐 Frontpage-Ansicht', 'liebherr-interface-world' ),
				RoleBridge::CAP_MANAGE_CONTENT,
				$front_url
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
			__( 'Simulation Board', 'liebherr-interface-world' ),
			__( 'Simulation Board', 'liebherr-interface-world' ),
			RoleBridge::CAP_MANAGE_INTERFACES,
			SimulationBoardPage::MENU_SLUG,
			[ SimulationBoardPage::class, 'render' ]
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

		if ( '' !== $front_url ) {
			self::move_first( 'liw-interface-board', $front_url );
		}
	}

	/** URL der öffentlichen Landingpage (Trägerseite mit [liw_landingpage]); leer, wenn keine existiert. */
	private static function front_url(): string {
		$page = get_page_by_path( 'interface-world' );
		if ( $page instanceof \WP_Post && 'publish' === $page->post_status ) {
			return (string) get_permalink( $page );
		}
		$ids = get_posts( [
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			's'              => '[liw_landingpage',
		] );
		if ( ! empty( $ids ) ) {
			return (string) get_permalink( (int) $ids[0] );
		}
		return '';
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
