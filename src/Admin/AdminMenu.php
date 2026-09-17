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

use Liebherr\InterfaceWorld\Admin\Pages\ConnectionBoardPage;
use Liebherr\InterfaceWorld\Admin\Pages\ContentBoardPage;
use Liebherr\InterfaceWorld\Admin\Pages\HandbookPage;
use Liebherr\InterfaceWorld\Admin\Pages\InterfaceBoardPage;
use Liebherr\InterfaceWorld\Admin\Pages\MediaBoardPage;
use Liebherr\InterfaceWorld\Admin\Pages\OnboardingBoardPage;
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
			__( 'Media Board', 'liebherr-interface-world' ),
			__( 'Media Board', 'liebherr-interface-world' ),
			RoleBridge::CAP_MANAGE_CONTENT,
			MediaBoardPage::MENU_SLUG,
			[ MediaBoardPage::class, 'render' ]
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
	}
}
