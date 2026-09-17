# Liebherr Interface Solutions — Landingpage-Konzept

Beantwortet die Frage „Haben wir ein Layout-Konzept für die Landingpage?" (Joseph White,
18.09.2026): Ja – als verbindlicher Content-Bauplan im Pflichtenheft
(`Pflichtenheft_Liebherr_Interface_Solutions_Landingpage.md`, §8), 14 Abschnitte LP-01 bis
LP-14. Was bisher fehlte, war die Zuordnung zu bereits gebauten Boards/Shortcodes bzw. zu
neuen Grafik-Assets – das leistet dieses Dokument.

Stand: 18.09.2026 (0.1.0-alpha.12).

## Die 14 Abschnitte (Pflichtenheft §8) und ihr Umsetzungsstand

| # | Abschnitt | Kurzinhalt (Pflichtenheft) | Status |
|---|---|---|---|
| LP-01 | Hero | Baumaschinenmotiv + Netzwerkebene, H1 „One structure. Connected worldwide.", CTAs Start Integration / Explore the Simulation | Offen (Content Board §19 nötig) |
| LP-02 | Ausgangslage | Heutige Systemvielfalt, Risiken (Dubletten, Medienbrüche, Zeitverlust) | Offen |
| LP-03 | Zielbild | Zentral-System → Interface LogiQ → lokale Systeme | Offen |
| LP-04 | Magic Cube | Sandbox, Schnittstellentests, Verifizierung, Validierung | **Datenpflege gebaut** (Simulation Board, alpha.4) – Frontend-Darstellung offen |
| LP-05 | Interface LogiQ | Produktive Vermittlungs-/Prüf-/Übersetzungsschicht | Offen |
| LP-06 | World Connections | Netzwerkdarstellung Regionen/Händler, keine realen Standorte ohne Freigabe | **Gebaut** (Datenpflege alpha.5, Frontend-Shortcode `[liw_world_connections_map]` alpha.8) |
| LP-07 | Data Model | Objektgruppen Kunde, Kontakt, Händler, Maschine, Konfiguration, Angebot, Auftrag, Bedarfsfall, Lieferung, Rechnung, Zahlung | **Grafik geliefert** (`assets/img/liw-data-model.svg`, alpha.12) – Platzierung wartet auf Content Board |
| LP-08 | Process Worlds | Karten Sales, Configuration, Order, Goods, Finance, Service, Warranty | **Grafik geliefert** (`assets/img/liw-process-worlds.svg`, alpha.12) – Platzierung wartet auf Content Board |
| LP-09 | Goods and Finance | Waren-/Finanzströme, messbarer Nutzen | Offen |
| LP-10 | Security | Zero-Trust-Darstellung, rollenbasierter Zugriff, Audit, Versionierung, Freigaben | Offen |
| LP-11 | Onboarding | 9-stufiger Händleranschluss, Bestandsaufnahme → überwachter Produktivbetrieb | **Formular gebaut** (`[liw_onboarding_form]`, alpha.6) – deckt Anfrage/Erfassung ab, nicht die vollen 9 Stufen als Darstellung |
| LP-12 | Roadmap | Contract Model → Magic Cube → Sandbox Validation → Pilot Dealer → Interface LogiQ → Global Rollout | Offen |
| LP-13 | Kontakt | Qualifiziertes Anfrageformular (Zentralbereich/Händler/Lieferant/Technologiepartner/sonstiges) | Offen (separat vom Onboarding-Formular, andere Zielgruppe laut Pflichtenheft) |
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
- **Content Board (§19)** – Admin-Funktion, um `liw_section`-Posts für LP-01…LP-14
  tatsächlich anzulegen und die beiden neuen Grafiken sowie die bestehenden Boards/
  Shortcodes an ihrer vorgesehenen Stelle einzubetten. S. `docs/LIW_TODO.md`.
- Die beiden neuen SVGs sind als **inline einzubettendes Markup** konzipiert (nicht als
  `<img src="...">`), damit die `var(--ary-*)`-Farbwerte aus der echten Seiten-CSS
  übernommen werden; die eingebetteten Hex-Werte sind nur der Fallback für den
  Stand 18.09.2026 (ARY-DP-1.0.0) und müssen bei einer Token-Änderung nicht zwingend
  angepasst werden, sollten aber gegengeprüft werden.
