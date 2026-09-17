<?php
/**
 * Liebherr Interface Solutions – Programmierlogbuch (Admin-Seite)
 *
 * Zeigt `docs/LIW_PROGRAMMIERLOGBUCH.md` im Backend an – analog zu Core's
 * `Admin\Pages\TechLogbookPage` (rendert `docs/LOGBUCH_TECHNIK.md`). Quelle ist
 * ausschließlich die Markdown-Datei im Repository (versioniert mit dem Code); gerendert
 * über `CoreBridge\MarkdownBridge` (Wiederverwendung des Core-Renderers, keine zweite
 * Markdown-Implementierung).
 *
 * @package Liebherr\InterfaceWorld\Admin\Pages
 * @since   0.1.0-alpha.10
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin\Pages;

use Liebherr\InterfaceWorld\CoreBridge\MarkdownBridge;
use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ProgrammingLogPage {

	private const DOC_RELATIVE_PATH = 'docs/LIW_PROGRAMMIERLOGBUCH.md';

	public static function render(): void {
		if ( ! current_user_can( RoleBridge::CAP_MANAGE_INTERFACES ) ) {
			wp_die( esc_html__( 'Keine Berechtigung für das Programmierlogbuch.', 'liebherr-interface-world' ) );
		}

		$file = LIW_PATH . self::DOC_RELATIVE_PATH;

		echo '<div class="wrap"><h1>🧾 ' . esc_html__( 'Programmierlogbuch', 'liebherr-interface-world' ) . '</h1>';

		if ( ! is_readable( $file ) ) {
			echo '<p class="description">' . esc_html(
				sprintf(
					/* translators: %s: Dateipfad. */
					__( 'Logbuch-Datei nicht gefunden: %s', 'liebherr-interface-world' ),
					self::DOC_RELATIVE_PATH
				)
			) . '</p></div>';
			return;
		}

		printf(
			'<p class="description">%s · %s</p>',
			esc_html(
				sprintf(
					/* translators: %s: Dateipfad. */
					__( 'Quelle: %s (versioniert mit dem Plugin)', 'liebherr-interface-world' ),
					self::DOC_RELATIVE_PATH
				)
			),
			esc_html(
				sprintf(
					/* translators: %s: Datum/Uhrzeit. */
					__( 'Stand: %s', 'liebherr-interface-world' ),
					wp_date( 'd.m.Y H:i', (int) filemtime( $file ) )
				)
			)
		);

		echo MarkdownBridge::render( (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- MarkdownBridge liefert bereits escaptes HTML.
		echo '</div>';
	}
}
