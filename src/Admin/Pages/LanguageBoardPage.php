<?php
/**
 * Liebherr Interface Solutions – Language Board / Release-Readiness (§20/§21, LANG-006).
 *
 * Zeigt je aktiver Sprache die Übersetzungs-Vollständigkeit der öffentlichen LIW-Flächen
 * (veröffentlichte Abschnitte + Trägerseite) auf Basis der Core-Translation-Registry. LANG-006:
 * eine Sprache ist erst release-fähig, wenn alle Pflichtsegmente übersetzt, nichts veraltet und
 * kritische Segmente freigegeben sind. Das *harte* Gate je Sprache bleibt eine Betriebsentscheidung
 * (Kategorie A, Core); hier ist die Lese-/Statusansicht. Kuratierung/Freigabe erfolgt im Core
 * Languages Hub.
 *
 * @package Liebherr\InterfaceWorld\Admin\Pages
 * @since   0.1.0-alpha.32
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin\Pages;

use Liebherr\InterfaceWorld\CoreBridge\LanguageBridge;
use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;
use Liebherr\InterfaceWorld\CoreBridge\TranslationBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class LanguageBoardPage {

	public const MENU_SLUG = 'liw-language-board';

	public static function render(): void {
		if ( ! current_user_can( RoleBridge::CAP_MANAGE_CONTENT ) ) {
			wp_die( esc_html__( 'Keine Berechtigung für das Language Board.', 'liebherr-interface-world' ) );
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Language Board – Release-Readiness (LANG-006)', 'liebherr-interface-world' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Übersetzungs-Vollständigkeit der öffentlichen Flächen (veröffentlichte Abschnitte + Trägerseite) je Sprache. Eine Sprache ist release-fähig, wenn alle Pflichtsegmente übersetzt, nichts veraltet und kritische Segmente freigegeben sind. Kuratierung und Freigabe der Texte erfolgen im ARALIYA Languages Hub; das harte Sprach-Gate ist eine Betriebsentscheidung.', 'liebherr-interface-world' ) . '</p>';

		if ( ! TranslationBridge::is_available() ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Das Übersetzungssystem des Core ist nicht verfügbar (araliya-platform-core inaktiv).', 'liebherr-interface-world' ) . '</p></div></div>';
			return;
		}

		$langs   = LanguageBridge::active_langs();
		$report  = TranslationBridge::readiness_report( $langs );
		$scopes  = count( TranslationBridge::public_scope_post_ids() );
		$current = LanguageBridge::current_lang();

		/* translators: %d: Anzahl geprüfter Flächen. */
		echo '<p>' . esc_html( sprintf( _n( 'Geprüfte Fläche: %d (Abschnitte + Trägerseite).', 'Geprüfte Flächen: %d (Abschnitte + Trägerseite).', $scopes, 'liebherr-interface-world' ), $scopes ) ) . '</p>';

		echo '<table class="widefat striped"><thead><tr>';
		foreach ( [ 'Sprache', 'Flächen vollständig', 'Mindest-Übersetzungsgrad', 'Release-fähig (LANG-006)' ] as $column ) {
			echo '<th>' . esc_html( $column ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $langs as $lang ) {
			$r = $report[ $lang ] ?? [ 'pages' => 0, 'complete' => 0, 'min_rate' => 0.0, 'ready' => false ];
			$is_source = 'de' === $lang; // Quellsprache gilt als vollständig (Pflichtenheft §20 Startsprache DE).
			$ready     = $is_source ? true : (bool) $r['ready'];

			echo '<tr>';
			echo '<td><strong>' . esc_html( strtoupper( $lang ) ) . '</strong>' . ( $lang === $current ? ' <span class="description">' . esc_html__( '(aktuell)', 'liebherr-interface-world' ) . '</span>' : '' ) . ( $is_source ? ' <span class="description">' . esc_html__( '(Quellsprache)', 'liebherr-interface-world' ) . '</span>' : '' ) . '</td>';
			echo '<td>' . esc_html( $is_source ? '—' : ( (int) $r['complete'] . ' / ' . (int) $r['pages'] ) ) . '</td>';
			echo '<td>' . esc_html( $is_source ? '100 %' : ( (float) $r['min_rate'] . ' %' ) ) . '</td>';
			printf(
				'<td><span style="display:inline-block;padding:2px 8px;border-radius:2px;color:#fff;background:%1$s">%2$s</span></td>',
				esc_attr( $ready ? '#327e0d' : '#b0542e' ),
				esc_html( $ready ? __( 'ja', 'liebherr-interface-world' ) : __( 'nein', 'liebherr-interface-world' ) )
			);
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<p class="description">' . esc_html__( 'Hinweis: UI-Texte des Chromes (Navigation, CTAs, Formular-Labels, Fehlermeldungen) laufen über Programmtexte (__()/Sprachdateien) bzw. Slots und werden im Languages Hub gepflegt; diese Tabelle misst die redaktionellen Inhalte der Flächen.', 'liebherr-interface-world' ) . '</p>';
		echo '</div>';
	}
}
