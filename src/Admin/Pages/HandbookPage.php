<?php
/**
 * Liebherr Interface Solutions – Handbuch (Admin-Seite)
 *
 * Handbuch-Regel (CLAUDE.md Abschnitt 1): letzter Reiter des Moduls, fortgeschrieben bei
 * jeder fachlichen Erweiterung. Deckt seit alpha.13 alle sieben Boards (inkl. Content
 * Board) sowie die beiden Frontend-Shortcodes und die Nachvollziehbarkeits-Reiter
 * (Programmierlogbuch, To-Dos) ab.
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
			<p><?php esc_html_e( 'Liebherr-Händler, -Lieferanten und -Kunden sind weltweit an unterschiedliche Systeme angebunden. Diese Landingpage macht sichtbar, welche technischen Verbindungen es gibt, in welchem Zustand sie sind, und öffnet zugleich einen öffentlichen Kanal für neue Partneranfragen (Onboarding) – ohne dass technisches Wissen nur in Köpfen statt in der Plattform existiert.', 'liebherr-interface-world' ); ?></p>

			<h2><?php esc_html_e( 'Die sieben Bereiche im Überblick', 'liebherr-interface-world' ); ?></h2>

			<h3><?php esc_html_e( '1. Interface Board (§18 – Schnittstellenkatalog)', 'liebherr-interface-world' ); ?></h3>
			<p><?php esc_html_e( 'Jede Schnittstelle wird mit Code, Name, Richtung und Protokoll angelegt und durchläuft den Lebenszyklus draft → in_simulation → verified → approved. Nur „approved"-Einträge erscheinen in einer öffentlichen Übersicht (§17: strikte Trennung öffentlicher Inhalte von technischen Schnittstellendaten). Capability: liw_manage_interfaces.', 'liebherr-interface-world' ); ?></p>

			<h3><?php esc_html_e( '2. Content Board (§19 – Landingpage-Abschnitte)', 'liebherr-interface-world' ); ?></h3>
			<p><?php esc_html_e( 'Übersicht und Freigabeworkflow für die 14 Landingpage-Abschnitte LP-01…LP-14 (liw_section, Pflichtenheft §8). Titel, Inhalt, Reihenfolge (Feld „Reihenfolge" im Beitrags-Editor) und Revisionen laufen über den nativen WordPress-Editor. Freigabeworkflow Entwurf → Prüfung → freigegeben → veröffentlicht per Statuswechsel-Aktion je Zeile. Grundgerüst (Entscheidung JW 18.09.2026): Drag-and-Drop-Reihenfolge, Zeitsteuerung über den Freigabestatus hinaus, Mehrsprachen-/Geräte-Vorschau, CTA-Ziel-Picker und Medien-Picker-Beschränkung auf freigegebene Bibliothek sind bewusst noch nicht Teil dieser Auslieferung (siehe To-Dos). Layout-Konzept aller 14 Abschnitte: docs/LIW_LANDINGPAGE_KONZEPT.md. Capability: liw_manage_content.', 'liebherr-interface-world' ); ?></p>

			<h3><?php esc_html_e( '3. Simulation Board (§17 – Magic Cube)', 'liebherr-interface-world' ); ?></h3>
			<p><?php esc_html_e( 'Verwaltet Simulationswelten (liw_simulation_world) und je Welt deren Testszenarien (liw_test_scenario) – Navigation über die URL, ohne Datenänderung. Status-Übergänge (Welt validieren, Szenario bestanden/fehlgeschlagen) sind bewusst noch nicht Teil dieses Boards, da die eigentliche Simulations-Engine fehlt (siehe To-Dos). Capability: liw_manage_interfaces.', 'liebherr-interface-world' ); ?></p>

			<h3><?php esc_html_e( '4. World Connections Map – Datenpflege (§17/LP-06)', 'liebherr-interface-world' ); ?></h3>
			<p><?php esc_html_e( 'Anlage, Freigabe (Öffentlichkeits-Flag) und Statuspflege (geplant/aktiv/inaktiv) von Regionen-Verbindungen für Händler, Lieferanten und Kunden. Nur öffentlich freigegebene Einträge erscheinen auf der Landingpage. Capability: liw_manage_content.', 'liebherr-interface-world' ); ?></p>

			<h3><?php esc_html_e( '5. Onboarding – Partneranfragen (§22)', 'liebherr-interface-world' ); ?></h3>
			<p><?php esc_html_e( 'Öffentliches Formular (Shortcode [liw_onboarding_form], kein Login nötig) für Händler-/Lieferanten-/Kundenanfragen mit Pflicht-Datenschutz-Einwilligung. Jede Anfrage legt einen Partner in der Plattform an (Status „pending"); Freigabe/Ablehnung im Board spiegelt automatisch den Partnerstatus. Die Liste blättert seit alpha.10 seitenweise (20 pro Seite) statt alle Anfragen ungebremst zu laden. Capability: liw_view_onboarding.', 'liebherr-interface-world' ); ?></p>

			<h3><?php esc_html_e( '6. Media Board (§18 – Assets & Freigabe)', 'liebherr-interface-world' ); ?></h3>
			<p><?php esc_html_e( 'Bulk-Übersicht der Medienbibliothek: Copyright/Rechteinhaber, Asset-Quelle und CI-005-Freigabe je Medium, ein Speichern-Klick für alle sichtbaren Zeilen. Filter „Alle/Freigegeben/Nicht freigegeben". Seit alpha.10 blätterbar (vorher: hart auf die 50 neuesten Medien begrenzt). Capability: liw_manage_content.', 'liebherr-interface-world' ); ?></p>

			<h3><?php esc_html_e( '7. World Connections Map – Frontend & Design System', 'liebherr-interface-world' ); ?></h3>
			<p><?php esc_html_e( 'Öffentlicher Shortcode [liw_world_connections_map] zeigt freigegebene Verbindungen gruppiert nach Region (Regionen-Grid, keine geografische Karte – siehe To-Dos). Beide öffentlichen Shortcodes ([liw_onboarding_form], [liw_world_connections_map]) werden ausschließlich über die zentralen ARALIYA-Design-Tokens gestaltet (assets/css/liebherr-frontend.css), keine eigenen Markenfarben. Das Stylesheet lädt nur auf Seiten, die einen der beiden Shortcodes tatsächlich enthalten.', 'liebherr-interface-world' ); ?></p>

			<h2><?php esc_html_e( 'Nachvollziehbarkeit: Programmierlogbuch und To-Dos', 'liebherr-interface-world' ); ?></h2>
			<p><?php esc_html_e( 'Der Reiter „🧾 Programmierlogbuch" listet jede Quellcodeänderung auf Datei-/Klassenebene (was wurde wann geändert). Der Reiter „📋 To-Dos" listet offene bzw. bewusst zurückgestellte Punkte mit Quellenangabe (z. B. Content Board §19, echte Karte für die World Connections Map, Docker-Praxistest). Beide werden bei jeder Aufgabe gepflegt, in der sich etwas ändert.', 'liebherr-interface-world' ); ?></p>

			<h2><?php esc_html_e( 'FAQ', 'liebherr-interface-world' ); ?></h2>
			<p><strong><?php esc_html_e( 'Warum sehe ich in einem Board keine Einträge?', 'liebherr-interface-world' ); ?></strong><br />
			<?php esc_html_e( 'Entweder wurden noch keine Datensätze angelegt, oder Ihrer Rolle fehlt die jeweilige Capability (liw_manage_interfaces, liw_manage_content oder liw_view_onboarding – siehe Abschnitt oben).', 'liebherr-interface-world' ); ?></p>
			<p><strong><?php esc_html_e( 'Warum sind manche Onboarding-Anfragen bzw. Medien plötzlich auf Seite 2?', 'liebherr-interface-world' ); ?></strong><br />
			<?php esc_html_e( 'Seit alpha.10 blättern beide Listen (20 bzw. 50 Einträge pro Seite), statt alle Datensätze auf einmal zu laden – Performance-Schutz bei wachsender Nutzung.', 'liebherr-interface-world' ); ?></p>

			<h2><?php esc_html_e( 'Technische Details', 'liebherr-interface-world' ); ?></h2>
			<p><?php esc_html_e( 'Tabellen: liw_interface, liw_simulation_world, liw_test_scenario, liw_connection, liw_consent_log, liw_partner_extra. Das Plugin setzt araliya-platform-core voraus und nutzt dessen Übersetzungs-, Rollen-, Audit-, SEO-, Medien-, Partner- und Markdown-Renderer-Services ausschließlich über den CoreBridge-Adapter (src/CoreBridge/) – nie direkt. Details siehe docs/ADR-LIW-001 und docs/LIW_PROGRAMMIERLOGBUCH.md.', 'liebherr-interface-world' ); ?></p>

			<p><em><?php esc_html_e( 'Dieses Handbuch wird mit jeder fachlichen Erweiterung des Moduls fortgeschrieben.', 'liebherr-interface-world' ); ?></em></p>
		</div>
		<?php
	}
}
