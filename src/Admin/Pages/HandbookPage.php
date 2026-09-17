<?php
/**
 * Liebherr Interface Solutions – Handbuch (Admin-Seite)
 *
 * Handbuch-Regel (CLAUDE.md Abschnitt 1): letzter Reiter des Moduls. Diese Auslieferung
 * deckt nur das Grundgerüst ab – das Handbuch wird bei jeder fachlichen Erweiterung
 * (Content Board, Simulation Board, Onboarding-Formular) fortgeschrieben.
 *
 * @package Liebherr\InterfaceWorld\Admin\Pages
 * @since   0.1.0-alpha.1
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin\Pages;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class HandbookPage {

	public static function render(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Liebherr Interface World – Handbuch', 'liebherr-interface-world' ); ?></h1>

			<h2><?php esc_html_e( 'Warum dieses Werkzeug entstanden ist', 'liebherr-interface-world' ); ?></h2>
			<p><?php esc_html_e( 'Liebherr-Händler, -Lieferanten und -Kunden sind weltweit an unterschiedliche Systeme angebunden. Der Schnittstellenkatalog macht sichtbar, welche technischen Verbindungen es gibt, in welchem Zustand sie sind (Entwurf, in Simulation, verifiziert, freigegeben) und verhindert, dass Wissen darüber nur in Köpfen statt in der Plattform existiert.', 'liebherr-interface-world' ); ?></p>

			<h2><?php esc_html_e( 'Wie es funktioniert', 'liebherr-interface-world' ); ?></h2>
			<p><?php esc_html_e( 'Jede Schnittstelle wird im Interface Board mit Code, Name, Richtung und Protokoll angelegt und durchläuft den Lebenszyklus draft → in_simulation → verified → approved. Nur "approved"-Einträge erscheinen in der öffentlichen Übersicht (§17: strikte Trennung öffentlicher Inhalte von technischen Schnittstellendaten).', 'liebherr-interface-world' ); ?></p>

			<h2><?php esc_html_e( 'FAQ', 'liebherr-interface-world' ); ?></h2>
			<p><strong><?php esc_html_e( 'Warum sehe ich keine Einträge?', 'liebherr-interface-world' ); ?></strong><br />
			<?php esc_html_e( 'Es wurden noch keine Schnittstellen angelegt, oder Ihrer Rolle fehlt die Berechtigung liw_manage_interfaces.', 'liebherr-interface-world' ); ?></p>

			<h2><?php esc_html_e( 'Technische Details', 'liebherr-interface-world' ); ?></h2>
			<p><?php esc_html_e( 'Tabellen: liw_interface, liw_simulation_world, liw_test_scenario, liw_connection, liw_consent_log. Das Plugin setzt araliya-platform-core voraus und nutzt dessen Übersetzungs-, Rollen- und Audit-Services über den CoreBridge-Adapter (src/CoreBridge/). Details siehe docs/ADR-LIW-001.', 'liebherr-interface-world' ); ?></p>

			<p><em><?php esc_html_e( 'Dieses Handbuch wird mit jeder fachlichen Erweiterung des Moduls fortgeschrieben (Content Board, Simulation Board, World Connections Map, Onboarding-Formular).', 'liebherr-interface-world' ); ?></em></p>
		</div>
		<?php
	}
}
