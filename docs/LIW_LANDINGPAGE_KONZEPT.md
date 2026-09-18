# Liebherr Interface Solutions — Landingpage-Konzept

Beantwortet die Frage „Haben wir ein Layout-Konzept für die Landingpage?" (Joseph White,
18.09.2026): Ja – als verbindlicher Content-Bauplan im Pflichtenheft
(`Pflichtenheft_Liebherr_Interface_Solutions_Landingpage.md`, §8), 14 Abschnitte LP-01 bis
LP-14. Was bisher fehlte, war die Zuordnung zu bereits gebauten Boards/Shortcodes bzw. zu
neuen Grafik-Assets – das leistet dieses Dokument.

Stand: 18.09.2026 (0.1.0-alpha.22).

## Die 14 Abschnitte (Pflichtenheft §8) und ihr Umsetzungsstand

Seit alpha.18 gibt es die zusammengesetzte Seite: `[liw_landingpage]` (`Frontend\LandingpageView`)
rendert alle veröffentlichten Abschnitte in Reihenfolge; die Redaktion setzt den Shortcode in eine
normale WordPress-Seite. Seit alpha.17 sind alle 14 Abschnitte im Plugin als Bauplan hinterlegt (`Content\SectionBlueprint`,
Codes/Titel/Vorgaben wörtlich aus dem Pflichtenheft) und per Knopf im Content Board als Entwürfe
anlegbar (`Content\SectionSeeder`, idempotent über Post-Meta `_liw_lp_code`). Die Spalte „Status"
unten beschreibt den Stand der *inhaltlichen/funktionalen* Umsetzung je Abschnitt.

| # | Abschnitt | Kurzinhalt (Pflichtenheft) | Status |
|---|---|---|---|
| LP-01 | Hero | Baumaschinenmotiv + Netzwerkebene, H1 „One structure. Connected worldwide.", CTAs Start Integration / Explore the Simulation | Offen (Content Board §19 nötig) |
| LP-02 | Ausgangslage | Heutige Systemvielfalt, Risiken (Dubletten, Medienbrüche, Zeitverlust) | Offen |
| LP-03 | Zielbild | Zentral-System → Interface LogiQ → lokale Systeme | **Grafik geliefert** (`[liw_graphic name="target-model"]`, alpha.20) |
| LP-04 | Magic Cube | Sandbox, Schnittstellentests, Verifizierung, Validierung | **Datenpflege gebaut** (Simulation Board, alpha.4), **Grafik geliefert** (`[liw_graphic name="magic-cube"]`, alpha.20) |
| LP-05 | Interface LogiQ | Produktive Vermittlungs-/Prüf-/Übersetzungsschicht | Offen |
| LP-06 | World Connections | Netzwerkdarstellung Regionen/Händler, keine realen Standorte ohne Freigabe | **Gebaut** (Datenpflege alpha.5, Frontend-Shortcode `[liw_world_connections_map]` alpha.8) |
| LP-07 | Data Model | Objektgruppen Kunde, Kontakt, Händler, Maschine, Konfiguration, Angebot, Auftrag, Bedarfsfall, Lieferung, Rechnung, Zahlung | **Grafik geliefert** (`assets/img/liw-data-model.svg`, alpha.12), **einbettbar** per `[liw_graphic name="data-model"]` (alpha.15) – Abschnitt im Content Board anlegen |
| LP-08 | Process Worlds | Karten Sales, Configuration, Order, Goods, Finance, Service, Warranty | **Grafik geliefert** (`assets/img/liw-process-worlds.svg`, alpha.12), **einbettbar** per `[liw_graphic name="process-worlds"]` (alpha.15) – Abschnitt im Content Board anlegen |
| LP-09 | Goods and Finance | Waren-/Finanzströme, messbarer Nutzen | Offen |
| LP-10 | Security | Zero-Trust-Darstellung, rollenbasierter Zugriff, Audit, Versionierung, Freigaben | Offen |
| LP-11 | Onboarding | 9-stufiger Händleranschluss, Bestandsaufnahme → überwachter Produktivbetrieb | **Formular gebaut** (`[liw_onboarding_form]`, alpha.6) – deckt Anfrage/Erfassung ab, nicht die vollen 9 Stufen als Darstellung |
| LP-12 | Roadmap | Contract Model → Magic Cube → Sandbox Validation → Pilot Dealer → Interface LogiQ → Global Rollout | **Grafik geliefert** (`[liw_graphic name="roadmap"]`, alpha.20) |
| LP-13 | Kontakt | Qualifiziertes Anfrageformular (Zentralbereich/Händler/Lieferant/Technologiepartner/sonstiges) | **Gebaut** (`[liw_contact_form]`, Contact Board, alpha.19) – getrennt vom Onboarding-Formular |
| LP-14 | Footer | Rechtliches, Datenschutz, Barrierefreiheit, Sprachen, „Solution Provider: GoHeal" | Offen |

## Entscheidung: Umgang mit der internen Prozess-PDF (18.09.2026)

Joseph lieferte eine interne Prozessgrafik („Liebherr DSC – Schnittstellenprozess
Neugestaltung"): vollständiger BC/NAV-↔-Livision-Integrationsplan mit 32 nummerierten
Feldern, realen API-Endpunktnamen (`Create Customer`, `Update Contact`, `Livision URL for
Update`, `Lias Open Trans`, `Get Opportunity` …) und der kompletten Pfeil-Logik.

**Entscheidung (Joseph White):** Diese Grafik NICHT direkt bzw. in Auszügen auf der
öffentlichen Landingpage zeigen. Stattdessen: anonymisierte, generische Grafiken für
LP-07 (Data Model) und LP-08 (Process Worlds) – ohne reale System- oder API-Namen.

**Begründung:** Das Pflichtenheft selbst verlangt in §17 die strikte Trennung öffentlicher
Inhalte von technischen Schnittstellendaten (bereits im Code umgesetzt, s.
`InterfaceCatalogService::get_public_catalog()` – zeigt nie `doc_reference`/`created_by`).
Die PDF zu veröffentlichen widerspräche außerdem der von Joseph selbst formulierten
Anforderung, dass die Interface-Logik „absolut sicher gegenüber Angriffen von außen" sein
soll: reale Endpunktnamen und die genaue Systemarchitektur (welches ERP, welche
Middleware) sind für einen Angreifer verwertbare Aufklärungsinformationen.

**Umsetzung:** Die PDF-Struktur (Objektgruppen, Prozessreihenfolge Sales → Configuration
→ Order) deckt sich inhaltlich fast 1:1 mit den bereits im Pflichtenheft für LP-07/LP-08
vorgesehenen, ohnehin generischen Inhalten. Zwei neue SVG-Grafiken
(`assets/img/liw-data-model.svg`, `assets/img/liw-process-worlds.svg`) setzen das um,
gestaltet über die zentralen ARALIYA-Design-Tokens (`var(--ary-*, Fallback)`), analog zur
bestehenden Konvention der beiden Frontend-Shortcodes.

## Offene Punkte

- **Visuelles Gesamt-Layout/Wireframe** für alle 14 Abschnitte (Reihenfolge auf der Seite,
  Bildsprache für LP-01/02/03/09/10/12, Übergänge) – noch nicht erstellt.
- **Content Board (§19)** – Grundgerüst seit alpha.13 (Übersicht, Freigabeworkflow);
  Restpunkte s. `docs/LIW_TODO.md`. Die 14 `liw_section`-Entwürfe werden seit alpha.17 per Knopf
  angelegt; die redaktionelle Ausformulierung (Marketingtexte, Bildsprache LP-01/02/03/09/10/12)
  bleibt Aufgabe der Redaktion – das Plugin liefert bewusst nur die Pflichtenheft-Vorgabe als
  Hinweis („nicht erfinden").
- **Einbettung der Grafiken – eingelöst (alpha.15):** Shortcode `[liw_graphic name="data-model"]`
  bzw. `[liw_graphic name="process-worlds"]` (`Frontend\SectionGraphicView`, optional
  `caption="…"`) bettet die SVGs **inline** ein (nicht als `<img src="...">`), damit die
  `var(--ary-*)`-Farbwerte aus der echten Seiten-CSS übernommen werden. Feste Whitelist,
  keine Dateipfade per Attribut (Sicherheitsanforderung Joseph 18.09.2026). Die eingebetteten
  Hex-Werte in den SVGs sind nur der Fallback für den Stand 18.09.2026 (ARY-DP-1.0.0) und
  sollten bei einer Token-Änderung gegengeprüft werden.
