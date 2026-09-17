<?php
/**
 * Liebherr Interface Solutions – Markdown Bridge
 *
 * Dünner Wrapper um `Araliya\Platform\Core\Modules\Deployment\Admin\HandbookRenderer::render()`
 * (verifiziert: generischer, in sich geschlossener Markdown→HTML-Renderer für genau die im
 * Core-Handbuch verwendete Teilmenge – Überschriften, Absätze, Listen, Tabellen, Codeblöcke,
 * Inline-Code, Fett/Kursiv, Trennlinien; escaped den gesamten Dateiinhalt vor der Umwandlung).
 * Wird hier für das Programmierlogbuch (`ProgrammingLogPage`) genutzt, statt eine zweite
 * Markdown-Implementierung zu bauen (CLAUDE.md Abschnitt 5: keine Doppelentwicklungen).
 *
 * @package Liebherr\InterfaceWorld\CoreBridge
 * @since   0.1.0-alpha.10
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\CoreBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MarkdownBridge {

	private const RENDERER_CLASS = 'Araliya\\Platform\\Core\\Modules\\Deployment\\Admin\\HandbookRenderer';

	public static function is_available(): bool {
		return class_exists( self::RENDERER_CLASS );
	}

	/**
	 * Wandelt Markdown in sicheres, bereits escapetes HTML um. Ohne Core-Renderer (z. B. bei
	 * inkompatibler Core-Version) wird der Text als einfacher, escapeter Absatz ausgegeben –
	 * Graceful Degradation statt Fatal Error.
	 */
	public static function render( string $markdown ): string {
		if ( self::is_available() ) {
			return (string) call_user_func( [ self::RENDERER_CLASS, 'render' ], $markdown );
		}

		return '<pre>' . esc_html( $markdown ) . '</pre>';
	}
}
