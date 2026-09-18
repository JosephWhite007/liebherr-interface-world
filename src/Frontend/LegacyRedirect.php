<?php
/**
 * Liebherr Local Intelligence – Erhalt der Altroute (Pflichtenheft LI §3.2/§13/§12.7).
 *
 * Die vormals eigenständige Seite `/interface-world/` wird als Unterseite unter
 * `/liebherr-local-intelligence/interface-solutions/` einsortiert. Damit Bookmarks, interne Links,
 * Kampagnen- und Suchmaschinenzugriffe nicht ins Leere laufen, leitet dieser Guard den alten Pfad
 * dauerhaft (301) auf die neue Unterseite um. Nur der exakte Alt-Slug wird behandelt; keine sonstige
 * Anfrage wird verändert. Greift ausschließlich, solange die Unterseite existiert.
 *
 * @package Liebherr\InterfaceWorld\Frontend
 * @since   0.1.0-alpha.41
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Frontend;

use Liebherr\InterfaceWorld\Content\SitePages;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class LegacyRedirect {

	public static function register(): void {
		add_action( 'template_redirect', [ self::class, 'maybe_redirect' ], 1 );
	}

	public static function maybe_redirect(): void {
		if ( is_admin() || ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$path = trim( (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ), '/' );
		if ( SitePages::SLUG_LEGACY !== $path ) {
			return;
		}
		// Nur umleiten, wenn die alte Route nicht (mehr) selbst eine echte Seite ist und ein Ziel existiert.
		if ( get_page_by_path( SitePages::SLUG_LEGACY ) instanceof \WP_Post ) {
			return; // Alt-Seite existiert noch als Top-Level – nichts zu tun (vor der Verschachtelung).
		}
		$target = SitePages::interface_url();
		if ( '' === $target ) {
			return;
		}
		wp_safe_redirect( $target, 301 );
		exit;
	}
}
