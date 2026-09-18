<?php
/**
 * Liebherr Interface Solutions – Handbuch (Admin-Seite)
 *
 * Handbuch-Regel (CLAUDE.md Abschnitt 1): letzter Reiter des Moduls, fortgeschrieben bei
 * jeder fachlichen Erweiterung. Deckt seit alpha.19 alle acht Boards (inkl. Content
 * Board und Kontaktanfragen) sowie die fünf Frontend-Shortcodes und die Nachvollziehbarkeits-Reiter
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

			<h2><?php esc_html_e( 'Die acht Bereiche im Überblick', 'liebherr-interface-world' ); ?></h2>

			<h3><?php esc_html_e( '1. Interface Board (§18 – Schnittstellenkatalog)', 'liebherr-interface-world' ); ?></h3>
			<p><?php esc_html_e( 'Jede Schnittstelle wird mit Code, Name, Richtung und Protokoll angelegt und durchläuft den Lebenszyklus draft → in_simulation → verified → approved. Nur „approved"-Einträge erscheinen in einer öffentlichen Übersicht (§17: strikte Trennung öffentlicher Inhalte von technischen Schnittstellendaten). Capability: liw_manage_interfaces.', 'liebherr-interface-world' ); ?></p>

			<h3><?php esc_html_e( '2. Content Board (§19 – Landingpage-Abschnitte)', 'liebherr-interface-world' ); ?></h3>
			<p><?php esc_html_e( 'Übersicht und Freigabeworkflow für die 14 Landingpage-Abschnitte LP-01…LP-14 (liw_section, Pflichtenheft §8). Titel, Inhalt, Reihenfolge (Feld „Reihenfolge" im Beitrags-Editor) und Revisionen laufen über den nativen WordPress-Editor. Freigabeworkflow Entwurf → Prüfung → freigegeben → veröffentlicht per Statuswechsel-Aktion je Zeile. Grundgerüst (Entscheidung JW 18.09.2026): Drag-and-Drop-Reihenfolge, Zeitsteuerung über den Freigabestatus hinaus, Mehrsprachen-/Geräte-Vorschau, CTA-Ziel-Picker und Medien-Picker-Beschränkung auf freigegebene Bibliothek sind bewusst noch nicht Teil dieser Auslieferung (siehe To-Dos). Layout-Konzept aller 14 Abschnitte: docs/LIW_LANDINGPAGE_KONZEPT.md. Capability: liw_manage_content.', 'liebherr-interface-world' ); ?></p>
			<p><?php esc_html_e( 'Standard-Abschnitte anlegen (seit alpha.17): Solange Abschnitte aus dem Pflichtenheft-Bauplan LP-01…LP-14 fehlen, zeigt das Board oben den Knopf „… fehlende Standard-Abschnitte anlegen". Er legt die fehlenden Abschnitte als Entwürfe an – Titel und Reihenfolge (10, 20, … 140) aus dem Pflichtenheft §8, im Inhalt die Redaktionsvorgabe als erster Absatz und bei LP-03/LP-04/LP-06/LP-07/LP-08/LP-11/LP-12/LP-13 der bereits gebaute Baustein als Shortcode-Block. Wurden die Abschnitte bereits vor alpha.19/alpha.20 angelegt, die Shortcode-Blöcke [liw_contact_form] (LP-13), [liw_graphic name="target-model"] (LP-03), [liw_graphic name="magic-cube"] (LP-04) und [liw_graphic name="roadmap"] (LP-12) einmalig von Hand ergänzen (der Knopf legt nur fehlende Abschnitte an, er überschreibt nie). Die Zuordnung läuft über den Code (Spalte „Code"), nicht über den Titel: Titel dürfen frei geändert werden, ein erneuter Klick legt nie Dubletten an und stellt bewusst gelöschte Abschnitte nicht wieder her.', 'liebherr-interface-world' ); ?></p>
			<p><?php esc_html_e( 'Grafiken einbetten (seit alpha.15, erweitert alpha.20): im Abschnitts-Editor einen Shortcode-Block mit [liw_graphic name="…"] einfügen – verfügbar: target-model (Zielbild, LP-03), magic-cube (Magic Cube, LP-04), data-model (Datenmodell, LP-07), process-worlds (Prozesswelten, LP-08), roadmap (Roadmap, LP-12); optional caption="…" für eine Bildunterschrift. Die Grafiken werden inline eingebettet und übernehmen die Farben des zentralen Design Systems. Andere Werte für name werden aus Sicherheitsgründen ignoriert (feste Whitelist, keine Dateipfade).', 'liebherr-interface-world' ); ?></p>

			<h3><?php esc_html_e( '3. Simulation Board (§17 – Magic Cube)', 'liebherr-interface-world' ); ?></h3>
			<p><?php esc_html_e( 'Verwaltet Simulationswelten (liw_simulation_world) und je Welt deren Testszenarien (liw_test_scenario) – Navigation über die URL, ohne Datenänderung. Status-Übergänge (Welt validieren, Szenario bestanden/fehlgeschlagen) sind bewusst noch nicht Teil dieses Boards, da die eigentliche Simulations-Engine fehlt (siehe To-Dos). Capability: liw_manage_interfaces.', 'liebherr-interface-world' ); ?></p>

			<h3><?php esc_html_e( '4. World Connections Map – Datenpflege (§17/LP-06)', 'liebherr-interface-world' ); ?></h3>
			<p><?php esc_html_e( 'Anlage, Freigabe (Öffentlichkeits-Flag) und Statuspflege (geplant/aktiv/inaktiv) von Regionen-Verbindungen für Händler, Lieferanten und Kunden. Nur öffentlich freigegebene Einträge erscheinen auf der Landingpage. Capability: liw_manage_content.', 'liebherr-interface-world' ); ?></p>

			<h3><?php esc_html_e( '5. Onboarding – Partneranfragen (§22)', 'liebherr-interface-world' ); ?></h3>
			<p><?php esc_html_e( 'Öffentliches Formular (Shortcode [liw_onboarding_form], kein Login nötig) für Händler-/Lieferanten-/Kundenanfragen mit Pflicht-Datenschutz-Einwilligung. Jede Anfrage legt einen Partner in der Plattform an (Status „pending"); Freigabe/Ablehnung im Board spiegelt automatisch den Partnerstatus. Die Liste blättert seit alpha.10 seitenweise (20 pro Seite) statt alle Anfragen ungebremst zu laden. Capability: liw_view_onboarding.', 'liebherr-interface-world' ); ?></p>

			<h3><?php esc_html_e( '6. Kontaktanfragen (LP-13 / §22)', 'liebherr-interface-world' ); ?></h3>
			<p><?php esc_html_e( 'Öffentliches Kontaktformular (Shortcode [liw_contact_form], im Abschnitt LP-13 bereits eingebettet) für Projektanfragen von Zentrale, Händlern, Lieferanten, Technologiepartnern und sonstigen Kontakten – bewusst getrennt vom Partner-Onboarding: eine Kontaktanfrage legt keinen Partner an. Felder nach Pflichtenheft §22 (Organisation, Kontaktperson, geschäftliche E-Mail, Telefon, Land/Region, Rolle, lokales ERP/CRM, Projektinteresse als Mehrfachauswahl, Nachricht, getrennte Datenschutz- und Marketing-Einwilligung). Jede Anfrage wird serverseitig validiert, gespeichert, im Einwilligungsprotokoll mit Textversion und Zeitstempel erfasst und per E-Mail an die WordPress-Admin-Adresse gemeldet (bis eine CRM-/Empfängerdefinition vorliegt; per Filter liw_contact_recipients änderbar). Dieses Board zeigt die Anfragen seitenweise mit Status Neu → In Bearbeitung → Abgeschlossen; „Löschen" entfernt Anfrage und Einwilligungen zusammen (§24 Löschprozess). Capability: liw_view_onboarding.', 'liebherr-interface-world' ); ?></p>

			<h3><?php esc_html_e( '7. Media Board (§18 – Assets & Freigabe)', 'liebherr-interface-world' ); ?></h3>
			<p><?php esc_html_e( 'Bulk-Übersicht der Medienbibliothek: Copyright/Rechteinhaber, Asset-Quelle und CI-005-Freigabe je Medium, ein Speichern-Klick für alle sichtbaren Zeilen. Filter „Alle/Freigegeben/Nicht freigegeben". Seit alpha.10 blätterbar (vorher: hart auf die 50 neuesten Medien begrenzt). Capability: liw_manage_content.', 'liebherr-interface-world' ); ?></p>

			<h3><?php esc_html_e( '8. Landingpage im Frontend – Shortcodes & Design System', 'liebherr-interface-world' ); ?></h3>
			<p><?php esc_html_e( 'Die zusammengesetzte Landingpage (seit alpha.18): eine normale WordPress-Seite anlegen (z. B. „Interface World"), einen Shortcode-Block mit [liw_landingpage] einfügen und veröffentlichen. Der Shortcode zeigt alle Abschnitte mit Status „Veröffentlicht" in ihrer Reihenfolge untereinander – Entwürfe, Abschnitte in Prüfung und freigegebene, aber noch nicht veröffentlichte Abschnitte bleiben unsichtbar. Der Freigabeworkflow im Content Board steuert also direkt, was öffentlich erscheint. In den Abschnitten eingebettete Bausteine (Grafiken, World Connections Map, Onboarding-Formular) werden dabei aufgelöst. Jeder Abschnitt erhält einen Anker aus seinem Code (z. B. #lp-07), nutzbar für Sprungmarken. Solange nichts veröffentlicht ist, sehen nur angemeldete Redakteure einen Hinweis, Besucher sehen nichts.', 'liebherr-interface-world' ); ?></p>
			<p><?php esc_html_e( 'Öffentlicher Shortcode [liw_world_connections_map] zeigt freigegebene Verbindungen gruppiert nach Region (Regionen-Grid, keine geografische Karte – siehe To-Dos). Alle fünf öffentlichen Shortcodes ([liw_landingpage], [liw_onboarding_form], [liw_contact_form], [liw_world_connections_map], [liw_graphic]) werden ausschließlich über die zentralen ARALIYA-Design-Tokens gestaltet (assets/css/liebherr-frontend.css), keine eigenen Markenfarben. Das Stylesheet lädt nur auf Seiten, die einen dieser Shortcodes tatsächlich enthalten.', 'liebherr-interface-world' ); ?></p>

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
