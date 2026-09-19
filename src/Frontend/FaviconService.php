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

	public static function output(): void {
		$out = '<link rel="icon" type="image/svg+xml" href="' . esc_url( LIW_URL . 'assets/img/liw-planet-icon.svg' ) . '" />' . "\n";

		// Optionale offizielle PNGs (falls hinterlegt) – decken Browser ohne SVG-Favicon + Home-Screen ab.
		foreach ( [ '32' => '32x32', '192' => '192x192' ] as $file => $sizes ) {
			$rel = 'assets/img/goheal-gold-planet-' . $file . '.png';
			if ( is_readable( LIW_PATH . $rel ) ) {
				$out .= '<link rel="icon" type="image/png" sizes="' . esc_attr( $sizes ) . '" href="' . esc_url( LIW_URL . $rel ) . '" />' . "\n";
			}
		}
		$apple = 'assets/img/goheal-gold-planet-180.png';
		if ( is_readable( LIW_PATH . $apple ) ) {
			$out .= '<link rel="apple-touch-icon" sizes="180x180" href="' . esc_url( LIW_URL . $apple ) . '" />' . "\n";
		}

		$out .= self::brand_logo_css();

		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- URLs mit esc_url() escaped, CSS statisch.
	}
}
