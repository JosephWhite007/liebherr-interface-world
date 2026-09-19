<?php
/**
 * Liebherr – Website-Icon / Favicon „goldener Planet" (Intelligence-World-Vorgabe).
 *
 * Ersetzt das WordPress-Standard-Icon im Browser-Tab durch den goldenen GoHeal-Globus. Primär als
 * kontrastreiches SVG (assets/img/liw-planet-icon.svg); sind zusätzlich vom Auftraggeber gelieferte PNGs
 * hinterlegt (assets/img/goheal-gold-planet-32|192|180.png), werden diese ergänzt (Vorrang in Browsern
 * ohne SVG-Favicon-Unterstützung sowie für Apple-Touch-Icon). Ausgabe im `<head>` – Frontend, Admin und
 * Login –, damit das Icon auf allen Seiten erscheint (inkl. Local Intelligence). Über Filter
 * `liw_favicon_enabled` abschaltbar; die offizielle, WP-weite Pflege bleibt zusätzlich über
 * Design → Customizer → Website-Icon möglich.
 *
 * @package Liebherr\InterfaceWorld\Frontend
 * @since   0.1.0-alpha.49
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Frontend;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FaviconService {

	public static function register(): void {
		if ( ! apply_filters( 'liw_favicon_enabled', true ) ) {
			return;
		}
		// Website-Icon als EINE Quelle: /favicon.ico leitet ohne gesetztes Website-Icon per Core auf
		// das graue WP-„W" (wp-includes/images/w-logo-gray-white-bg.png) um. Über get_site_icon_url()
		// zeigt der Browser-Tab stattdessen den goldenen Globus (echtes Customizer-Icon behält Vorrang).
		// Weil dieser Filter has_site_icon() „wahr" macht, gibt Core die Icon-<link>s in wp_head und
		// login_head bereits selbst aus – wir dürfen sie NICHT zusätzlich ausgeben (sonst doppelt).
		add_filter( 'get_site_icon_url', [ self::class, 'filter_site_icon_url' ], 10, 3 );
		// Core hängt wp_site_icon NICHT an admin_head an → dort dieselbe Core-Funktion ergänzen,
		// damit auch der wp-admin-Tab den Globus zeigt (weiterhin eine Quelle, keine Dubletten).
		add_action( 'admin_head', 'wp_site_icon', 99 );
		// Kein zweiter Globus in der Adminleiste: Da unser Filter has_site_icon()=wahr macht, würde Core
		// zusätzlich ein `img.site-icon` neben den Seitennamen hängen (Doppelglobus). Wir zeigen den Globus
		// dort bereits als ersetztes WP-Logo → das Seitennamen-Icon abschalten.
		add_filter( 'wp_admin_bar_show_site_icons', '__return_false' );
		// Marken-Logo-CSS (Globus statt WP-„W" in Toolbar + auf der Login-Seite) – von Core nicht
		// geliefert, daher in allen drei Kontexten. Icon-<link>s kommen ausschließlich von Core.
		add_action( 'wp_head', [ self::class, 'output' ], 99 );
		add_action( 'admin_head', [ self::class, 'output' ], 99 );
		add_action( 'login_head', [ self::class, 'output' ], 99 );
		// Login-Logo verlinkt auf die Seite (statt wordpress.org) + spricht die Seite an (statt „Powered by WordPress").
		add_filter( 'login_headerurl', static function () { return home_url( '/' ); } );
		add_filter( 'login_headertext', static function () { return get_bloginfo( 'name' ); } );
	}

	/**
	 * Ersetzt das WordPress-„W" in der Admin-Leiste (oben links) und das WordPress-Logo auf der Login-Seite
	 * durch den goldenen Globus – per CSS (die Selektoren greifen nur im jeweiligen Kontext).
	 */
	public static function brand_logo_css(): string {
		$svg = esc_url( LIW_URL . 'assets/img/liw-planet-icon.svg' );
		return '<style id="liw-brand-logo">'
			// Admin-Leiste „W" → Globus (Frontend-Toolbar + wp-admin).
			. '#wpadminbar #wp-admin-bar-wp-logo>.ab-item .ab-icon:before{content:"" !important;background:url(' . $svg . ') center center/16px 16px no-repeat;width:20px;height:100%;display:inline-block;}'
			// Login-Seite: großes WordPress-Logo → Globus.
			. 'body.login h1 a{background-image:url(' . $svg . ') !important;background-size:contain !important;width:120px;height:120px;}'
			. '</style>' . "\n";
	}

	/**
	 * Liefert die Globus-URL als Website-Icon, solange kein echtes (im Customizer gesetztes) vorhanden ist.
	 * Bevorzugt ein hinterlegtes PNG (breiteste Browser-/OS-Kompatibilität), sonst das SVG.
	 *
	 * @param string $url     Von Core ermittelte Icon-URL (bei fehlendem Website-Icon Cores eigener
	 *                        Fallback, z. B. das graue WP-„W" – daher NICHT als leer prüfbar).
	 * @param int    $size    Angeforderte Kantenlänge in px (ungenutzt – Globus skaliert).
	 * @param int    $blog_id Blog-ID im Multisite-Kontext (ungenutzt).
	 */
	public static function filter_site_icon_url( string $url, int $size = 512, int $blog_id = 0 ): string {
		if ( get_option( 'site_icon' ) ) {
			return $url; // Echtes, im Customizer gesetztes Website-Icon behält Vorrang.
		}
		$png = 'assets/img/goheal-gold-planet-192.png';
		if ( is_readable( LIW_PATH . $png ) ) {
			return LIW_URL . $png;
		}
		return LIW_URL . 'assets/img/liw-planet-icon.svg';
	}

	/**
	 * Gibt ausschließlich das Marken-Logo-CSS aus (Globus statt WP-„W" in Toolbar + Login).
	 * Die Website-Icon-<link>s (Browser-Tab) stammen allein von Core (wp_site_icon), gespeist über
	 * filter_site_icon_url() – hier bewusst KEINE eigenen <link rel="icon">, um Dubletten zu vermeiden.
	 */
	public static function output(): void {
		echo self::brand_logo_css(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- URLs mit esc_url() escaped, CSS statisch.
	}
}
