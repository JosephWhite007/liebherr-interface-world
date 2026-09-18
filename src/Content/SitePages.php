<?php
/**
 * Liebherr Local Intelligence – Zentrale Seiten-Registry.
 *
 * Nach der Verschachtelung (Interface-World-Seite wird Kind der neuen Local-Intelligence-Hauptseite,
 * Slug → `interface-solutions`) ist ein Nachschlagen über einen festen Top-Level-Slug nicht mehr
 * verlässlich. Diese Klasse hält die IDs beider Trägerseiten in Optionen und bietet robuste
 * Resolver mit Slug-/Pfad-Fallback. So bleiben AdminMenu, Legacy-Redirect, Selbsttest und Seeder
 * frei von Slug-Annahmen (DRY, eine Wahrheit für „welche Seite ist welche").
 *
 * @package Liebherr\InterfaceWorld\Content
 * @since   0.1.0-alpha.41
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Content;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SitePages {

	public const OPTION_LI        = 'liw_li_page_id';        // Hauptseite Local Intelligence.
	public const OPTION_INTERFACE = 'liw_interface_page_id'; // Untergeordnete technische Seite.

	public const SLUG_LI        = 'liebherr-local-intelligence';
	public const SLUG_INTERFACE = 'interface-solutions';
	public const SLUG_LEGACY    = 'interface-world'; // Alte Top-Level-Route (301 → Unterseite).

	/** ID der Local-Intelligence-Hauptseite (0 = nicht vorhanden). */
	public static function li_id(): int {
		return self::resolve( self::OPTION_LI, [ self::SLUG_LI ] );
	}

	/** ID der Interface-Solutions-Unterseite (0 = nicht vorhanden). */
	public static function interface_id(): int {
		return self::resolve(
			self::OPTION_INTERFACE,
			[ self::SLUG_LI . '/' . self::SLUG_INTERFACE, self::SLUG_INTERFACE, self::SLUG_LEGACY ]
		);
	}

	public static function li_url(): string {
		$id = self::li_id();
		return $id > 0 ? (string) get_permalink( $id ) : '';
	}

	public static function interface_url(): string {
		$id = self::interface_id();
		return $id > 0 ? (string) get_permalink( $id ) : '';
	}

	public static function set_li_id( int $id ): void {
		update_option( self::OPTION_LI, $id );
	}

	public static function set_interface_id( int $id ): void {
		update_option( self::OPTION_INTERFACE, $id );
	}

	/**
	 * Option zuerst (nur wenn sie auf eine veröffentlichte Seite zeigt), sonst Pfad-Fallbacks.
	 *
	 * @param string[] $paths get_page_by_path-Kandidaten in Prioritätsreihenfolge.
	 */
	private static function resolve( string $option, array $paths ): int {
		$stored = (int) get_option( $option, 0 );
		if ( $stored > 0 ) {
			$post = get_post( $stored );
			if ( $post instanceof \WP_Post && 'page' === $post->post_type && 'trash' !== $post->post_status ) {
				return $stored;
			}
		}
		foreach ( $paths as $path ) {
			$page = get_page_by_path( $path );
			if ( $page instanceof \WP_Post ) {
				update_option( $option, $page->ID );
				return $page->ID;
			}
		}
		return 0;
	}
}
