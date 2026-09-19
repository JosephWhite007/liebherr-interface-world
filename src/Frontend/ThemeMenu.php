<?php
/**
 * Liebherr World – My Liebherr/Pocket im Theme-Frontend-Navigationsmenü (Pflichtenheft My Liebherr §1/§29/§35).
 *
 * Hängt „My Liebherr" und „Pocket Information" additiv in das Theme-Menü ein (Standort per Filter
 * `liw_myl_theme_menu_location`, Standard `primary`). Nur für angemeldete Nutzer mit
 * {@see \Liebherr\InterfaceWorld\MyLiebherr\Roles::CAP_ACCESS}, nur bei aktivem Flag und veröffentlichter Seite
 * (persönlicher Bereich, §35). Greift NICHT in andere Menüs ein; für ausgeloggte Besucher unverändert.
 * Abschaltbar über `liw_myl_theme_menu` (Default AN).
 *
 * @package Liebherr\InterfaceWorld\Frontend
 * @since   0.1.0-alpha.134
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Frontend;

use Liebherr\InterfaceWorld\MyLiebherr\Flags as MylFlags;
use Liebherr\InterfaceWorld\MyLiebherr\Roles as MylRoles;
use Liebherr\InterfaceWorld\Pocket\Flags as PocketFlags;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ThemeMenu {

	public static function register(): void {
		add_filter( 'wp_nav_menu_items', [ self::class, 'append' ], 20, 2 );
	}

	/** Ziel-Menüstandort (Standard `primary`); über Filter anpassbar. */
	public static function target_location(): string {
		return (string) apply_filters( 'liw_myl_theme_menu_location', 'primary' );
	}

	/** Nur einhängen, wenn Feature an, angemeldet und berechtigt (persönlicher Bereich). */
	public static function should_show(): bool {
		if ( ! (bool) apply_filters( 'liw_myl_theme_menu', true ) || ! MylFlags::enabled() ) {
			return false;
		}
		return is_user_logged_in() && current_user_can( MylRoles::CAP_ACCESS );
	}

	/**
	 * Hängt die persönlichen Menüpunkte an das Ziel-Menü an (nur am konfigurierten Standort).
	 *
	 * @param string $items Bisheriges Menü-Markup (Liste von <li>).
	 * @param object $args  wp_nav_menu-Argumente (u. a. theme_location).
	 */
	public static function append( string $items, $args ): string {
		$location = is_object( $args ) && isset( $args->theme_location ) ? (string) $args->theme_location : '';
		if ( $location !== self::target_location() || ! self::should_show() ) {
			return $items;
		}
		return $items . self::items_html();
	}

	private static function items_html(): string {
		$out = '';
		$myl = self::page_url( 'liw_my_liebherr_page_id' );
		if ( '' !== $myl ) {
			$out .= self::item( $myl, __( 'My Liebherr', 'liebherr-interface-world' ), 'liw-menu-my-liebherr' );
		}
		$pocket = self::page_url( 'liw_pocket_page_id' );
		if ( '' !== $pocket && PocketFlags::enabled() ) {
			$out .= self::item( $pocket, __( 'Pocket Information', 'liebherr-interface-world' ), 'liw-menu-pocket' );
		}
		return $out;
	}

	private static function item( string $url, string $label, string $slug ): string {
		return '<li class="menu-item menu-item-type-custom ' . esc_attr( $slug ) . '"><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
	}

	private static function page_url( string $option ): string {
		$id = (int) get_option( $option, 0 );
		return ( $id > 0 && 'publish' === get_post_status( $id ) ) ? (string) get_permalink( $id ) : '';
	}
}
