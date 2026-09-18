<?php
/**
 * Liebherr Interface Solutions – Vollbild-Seitenvorlage (§6/§7, alpha.39).
 *
 * Stellt eine plugin-eigene Seitenvorlage „Interface World – Vollbild" bereit, die nur den
 * Seiteninhalt (unsere Shortcodes: Header/Hero/Landingpage/Footer) samt wp_head/wp_footer rendert –
 * ohne Theme-Kopf/-Fuß. So erscheint die Landingpage ohne doppelten Theme-Header/-Footer. Auswahl je
 * Seite über die Seitenattribute (oder per Demo-Seeder gesetzt). Kein Eingriff ins Theme.
 *
 * @package Liebherr\InterfaceWorld\Frontend
 * @since   0.1.0-alpha.39
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Frontend;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class PageTemplate {

	public const TEMPLATE = 'liw-full-width.php';

	public static function register(): void {
		add_filter( 'theme_page_templates', [ self::class, 'add_choice' ] );
		add_filter( 'template_include', [ self::class, 'maybe_use' ] );
	}

	/**
	 * @param array<string,string> $templates
	 * @return array<string,string>
	 */
	public static function add_choice( array $templates ): array {
		$templates[ self::TEMPLATE ] = __( 'Interface World – Vollbild', 'liebherr-interface-world' );
		return $templates;
	}

	public static function maybe_use( string $template ): string {
		if ( ! is_page() ) {
			return $template;
		}
		$selected = (string) get_page_template_slug( (int) get_queried_object_id() );
		if ( self::TEMPLATE !== $selected ) {
			return $template;
		}
		$file = LIW_PATH . 'templates/full-width.php';
		return is_readable( $file ) ? $file : $template;
	}
}
