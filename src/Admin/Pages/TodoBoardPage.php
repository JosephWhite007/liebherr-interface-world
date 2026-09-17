<?php
/**
 * Liebherr Interface Solutions – To-Dos (Admin-Seite)
 *
 * Zeigt `docs/LIW_TODO.md` im Backend an: kuratierte Liste offener bzw. für später
 * geplanter Punkte (Content Board §19, Simulation-Status-Übergänge, echte Karte für die
 * World Connections Map, I18nSeo-Architekturfrage, Docker-Praxistest, Mehrsprachigkeits-
 * Audit). Gleiches Rendering-Prinzip wie `ProgrammingLogPage` – Quelle ist die Markdown-
 * Datei im Repository, kein Duplikat des Inhalts im Code.
 *
 * @package Liebherr\InterfaceWorld\Admin\Pages
 * @since   0.1.0-alpha.10
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin\Pages;

use Liebherr\InterfaceWorld\CoreBridge\MarkdownBridge;
use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class TodoBoardPage {

	private const DOC_RELATIVE_PATH = 'docs/LIW_TODO.md';

	public static function render(): void {
		if ( ! current_user_can( RoleBridge::CAP_MANAGE_INTERFACES ) ) {
			wp_die( esc_html__( 'Keine Berechtigung für die To-Do-Liste.', 'liebherr-interface-world' ) );
		}

		$file = LIW_PATH . self::DOC_RELATIVE_PATH;

		echo '<div class="wrap"><h1>📋 ' . esc_html__( 'To-Dos', 'liebherr-interface-world' ) . '</h1>';

		if ( ! is_readable( $file ) ) {
			echo '<p class="description">' . esc_html(
				sprintf(
					/* translators: %s: Dateipfad. */
					__( 'To-Do-Datei nicht gefunden: %s', 'liebherr-interface-world' ),
					self::DOC_RELATIVE_PATH
				)
			) . '</p></div>';
			return;
		}

		printf(
			'<p class="description">%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: Dateipfad. */
					__( 'Quelle: %s (versioniert mit dem Plugin, bei jeder erledigten/neuen Aufgabe gepflegt)', 'liebherr-interface-world' ),
					self::DOC_RELATIVE_PATH
				)
			)
		);

		echo MarkdownBridge::render( (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- MarkdownBridge liefert bereits escaptes HTML.
		echo '</div>';
	}
}
