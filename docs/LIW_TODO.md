# Liebherr Interface Solutions — To-Dos

Offene bzw. für später geplante Punkte. Diese Liste erfindet nichts Neues, sondern fasst
Punkte zusammen, die im Projektverlauf bereits als „bewusst nicht Teil dieser Auslieferung"
oder als offene Rückfrage an Joseph White dokumentiert wurden (Quelle jeweils angegeben).
Sichtbar im Backend unter „Interface World → 📋 To-Dos". Wird bei jeder Aufgabe, die einen
Punkt hier abschließt oder ergänzt, gepflegt.

Stand: 18.09.2026 (0.1.0-alpha.41).

---

## Liebherr Adventures (vierte Insel, ab alpha.51)

**Geliefert (alpha.51) – Visible-Adventures-MVP:** Klassifikation (13 Typen + 4 Dringlichkeiten getrennt),
Drei-Wörter-Ort (austauschbarer Mock-Provider + Partner-Attribution), CPT `liw_adventure` + Meta, serverseitige
Policy (Intelligence-Zugang, Critical nie auto-öffentlich, 7 Sichtbarkeiten, Ortsschutz), REST `liw-adv/v1`,
Insel `[liw_adventures]` (Hero/Filter/Create/Stream), Seite `/liebherr-adventures/` + Demo-Seeder. Details:
`docs/ADVENTURES_NOTES.md`.

**Geliefert (alpha.59) – Basislogik „Adventure Area" (Fundament + REST):** Ersteller-bestimmter,
**frei definierbarer Tokenwert** (`TokenPolicy`), 9-Status-Registrierungs-/Veröffentlichungs-Automat
(`RegistrationStatus`), revisionssicheres Hash-Ketten-Ledger (`TokenSchema`/`TokenLedger`, Tabelle
`liw_adv_ledger`), Workflow-Orchestrator (`RegistrationService`: register/validate/publish/access), Artikelbook-
Naht (`CoreBridge\ArticlebookBridge`, Filter `liw_articlebook_register`), REST register/request-validation/
access/accept/moderate. Core-Workboard bewusst NICHT wiederverwendet (nicht cross-plugin-fähig) → Workboard-Optik
nachgebaut. **Offen (alpha.60):** gemeinsame Workboard-Optik-Eingabemaske (Frontend `[liw_adventures]` + Admin-
Board) mit Tokenwert + Nutzungsumfang + Rechte-Zusicherung; Tokenakzeptanz-UI beim Zugriff; Moderations-Board;
echte Artikelbook-Anbindung im Core über den Filter; echtes Tokenbudget-Konto (§21).

**Offen / nächste Etappen:** World Map + Detailseite, Medien-Upload/Transcoding (§14; MVP: externe Bild-URL),
Moderations-UI/Audit (§17/§12.3), Get-Help-Assistent (Phase 3), Suche/Filter Maschine/Bauteil (§18),
Mehrsprachigkeit der Nutzerinhalte (§15). **alpha.53:** what3words als echter Ortsdienst angebunden
(englisch, Key-gesteuert, Mock-Fallback) – offen nur noch Lizenz/Vertrag + Produktiv-Key (§4.3/§24.1).
Simulation-World-Startbildschirm `[liw_simulator]` („Start your journey") – Startbild-Datei/Key durch Auftraggeber.

## Intelligence World (Pflichtenheft-2, ab alpha.47)

**Geliefert (alpha.47) – Fundament, serverseitig, ohne UI:** Datenmodell (`liw_iw_session`/`liw_iw_event`),
23 Ereignistypen (§11), Geld als Integer-Minor-Units (`Money`), `PriceRule` (10 Tarifarten, Gültigkeit),
`SessionMeter` (aktive Zeit §13.1), `EventLog` (Hash-Kette + Idempotenz §16), `SessionService`
(Start/Heartbeat/Pause/Resume/Ende + Timeout). Details/Entscheidungen: `docs/IMPLEMENTATION_NOTES.md`.

**Geliefert (alpha.48) – Eintritt & Welt:** Blue-Planet-Landing (§4.1), Access Gate (§4.2: Demo-Code +
Consent + Preis + Storage-Budget), Sitzungs-/Kostenleiste (§7) via REST `liw-iw/v1`; Seite
`/liebherr-intelligence-world/` + Menüpunkt „🪐 Intelligence World". Demo-Defaults in `liw_iw_world`.

**Fixes (alpha.49):** Eintritts-Code-Prüfung repariert (öffentliche REST-Endpunkte statt gecachtem Nonce;
Standard-Demo-Code „LIEBHERR-DEMO"), Pflichtfeld-Sternchen mit Legende, Website-Icon „goldener Planet"
(SVG site-weit; offizielle PNG via Customizer → Website-Icon nachrüstbar). Härtung (frisches Token) vor Produktiv.

**Geliefert (alpha.50):** Funktions-Hub nach dem Eintritt (Kacheln zu Local Intelligence + Interface Solutions
über SitePages; kommende Bereiche „in Vorbereitung"; Filter `liw_iw_hub_tiles`). Cache-Buster für IW-Assets.

**Geliefert (alpha.54) – Navigation & Hotels:** Katalog `liw_iw_catalog` (`CatalogContent`) mit 13
Produktsegmenten + Lösungswelt + 6 Hotel-Knoten (je Drei-Wörter-Ort, englisch); begehbare Navigation
(`NavigationView`, `<details>` ohne JS) inline im Hub + Shortcode `[liw_iw_navigation]`; die beiden Hub-Kacheln
„Produktsegmente & Lösungswelt" / „Hotelwelt" sind live (Anker `#liw-iw-segments` / `#liw-iw-hotels`).

**Offen / nächste Etappen:**
- Navigation & Hotels – Ausbau: Backoffice-Pflegemodul für Segmente/Hotels/Drei-Wörter-Orte, Untermenüs +
  Master-Linkmodell (§19.1), echte Verortung der Hotels über what3words, zwei Platzhalter-Hotels kuratieren.
- Preismodell (alpha.55, **geliefert**): Sekundentakt 0,09 EUR/Sek., Monatsbudget 5.000,00 EUR, lokaler
  Speicher 1 TB (min.) – Anzeige, Ticker und `billing_status` konsistent umgestellt. Offen: Backoffice-Formular
  zur Pflege der Tarife/Budgets (derzeit nur Option `liw_iw_world`).
- Simulation Builder (alpha.56, **geliefert**): geführte Szenarien/Forecasts mit Beispieldaten
  (`SimulationModel` rein/testbar + `SimulationView` `[liw_iw_simulation]`, live per JS). Offen: echte
  Datenquellen/Compute-Metering statt Beispielmodell, Speichern/Vergleich von Szenarien.
- Simulator-Startseite (alpha.57, **geliefert**): Seite `/liebherr-simulator/` (Seeder liw-seed-simulator.php,
  Option `liw_simulator_page_id`), Cockpit-Startbild + „Go" → Intelligence World. Bild-Slots World-Connections
  (`liw_world_connections_image_id`) + Simulator (`liw_simulator_image_id`) aus der Mediathek gesetzt & freigegeben
  – **nur Dev-DB**, auf Staging/Live erneut hochladen/freigeben/zuweisen. GIF `Liebherr_Cockpit_Sprachzyklus`
  (#2838) liegt bereit, noch keinem Slot zugeordnet.
- Nutzungs-/Kostenprotokoll (alpha.58, **geliefert**): `ProtocolBuilder` (rein) + REST `session/protocol` +
  Frontend-Ansicht nach Sitzungsende (JSON-Download + Druck/PDF, Integritätsprüfung der Hash-Kette). Offen:
  echte serverseitige PDF-Erzeugung (Prototyp nutzt Browser-Druck), Modul-/Compute-Ereignisse ins Protokoll.
- Compute-Metering (Mock) + kostenpflichtige Module/Rechenlast als Ledger-Ereignisse (§6.4/§8).
- Pricing/Storage/Admin/Rollen/Audit (§14–§16, §19).
- **Prototyp-Grenzen (§21):** kein echtes Payment/Produktivdaten; Produktivschaltung erst nach Freigabe.

## Local Intelligence – Hauptseite (LI-Pflichtenheft, alpha.41)

**Geliefert (alpha.41):** übergeordnete Hauptseite „Liebherr Local Intelligence" mit allen elf Modulen
(Composite `[liw_local_intelligence]` + Modul-Shortcodes), interaktiver Simulation-World-Szenario-Schalter
(A/B/C) und filterbare Einsatzfelder, administrierbares Content-Modell + Pflege-Board, Verschachtelung der
Interface-Seite als Unterseite (`/liebherr-local-intelligence/interface-solutions/`) mit 301 der Altroute,
Breadcrumb/Rücklink, zwei Frontpage-Menüpunkte. Nur Demo-Inhalte (§4/§7/§12.8).

**Offen / bewusst später:**
- **Vollständige EN-Fassung + weitere Sprachen (§12.4).** DE ist redaktionelle Ausgangsfassung; die
  LI-Texte laufen wie ComponentContent über den bestehenden Sprach-Workflow (Standard via `__()`, Overrides
  literal). EN-Kuratierung + Freigabe im Language Board steht noch aus.
- **Szenario-Editor im Board.** Simulation-Szenarien A/B/C werden aktuell über Standardwerte/Seeder gepflegt;
  ein feingranularer UI-Editor (Zeilen je Variante) ist optional nachrüstbar.
- **Analytik-Events (§14).** Klick-/Formular-/Sprachwechsel-Events in der freigegebenen Analytik-
  Namenskonvention erfassen – erst mit freigegebenem Analytics-Setup.
- **Redaktion/Freigabe (§9.2/§16).** Bis zur dokumentierten Liebherr-Freigabe gilt die Seite als
  Konzept/Prototyp; CI weiterhin „vorläufig" (siehe Marken-Freigabe-Punkt unten). **alpha.42:** Haupt-
  und Interface-Seite sind bis dahin **noindex** + aus der Sitemap ausgeschlossen (`SeoBridge`, Option
  `liw_public_release`). *Freischalten bei Launch:* `liw_public_release` setzen **und** Markenfreigabe
  dokumentieren (Abnahme §8.4).

---

## Fachlich offen (Pflichtenheft)

- **CI/Branding & Marken-Freigabe (§10–12, §34, CI-002/005).** Die benötigten Liebherr-Assets
  (Logo-CI-SVG, Webfonts LiebherrHead/LiebherrText, 5 Baumaschinen-/Hero-Motive) wurden am
  18.09.2026 von liebherr.com als **CI-005-Kandidaten** ins Media Board importiert
  (`scripts/liw-import-brand-assets.php`, alle `_liw_media_approved = 0`). Erfasste Farb-/
  Typo-Token: `docs/LIW_BRAND_TOKENS.md`. Das **Brand Board** (Design-Tokens, Logo-Auswahl) ist mit
  alpha.27 gebaut. **alpha.37 (autorisiert JW):** CI **vorläufig angewendet** – Assets `approved=1`,
  echte Liebherr-Tokens (Gelb/Anthrazit/Blau, LiebherrHead/Text), Logo + Hero gesetzt, Webfonts
  eingebunden. **Launch-Blocker/offen:** endgültige, dokumentierte Liebherr-Freigabe + Schrift-Lizenz
  (bis dahin gilt die Anwendung als vorläufig; Rücknahmeweg in `docs/LIW_ABNAHME.md` §5).
  *Quelle: Pflichtenheft §10–12/§34; CHANGELOG alpha.26/alpha.27/alpha.37.*

- **Content Board (§19) – über das Grundgerüst hinaus.** Seit alpha.13 gibt es Übersicht,
  Freigabeworkflow (Entwurf → Prüfung → freigegeben → veröffentlicht) und native
  Editor-/Revisions-Anbindung für `liw_section`. Bewusst noch nicht Teil der Auslieferung
  (Entscheidung Joseph White 18.09.2026: „Grundgerüst zuerst"):
  - ~~Drag-and-Drop-Reihenfolge~~ – alpha.35: Zeilen im Content Board sortierbar (AJAX, menu_order)
  - ~~Zeitsteuerte Veröffentlichung über `future` hinaus~~ – alpha.36: Sichtbarkeits-Zeitfenster
    (valid_from/valid_until) per Metabox; Landingpage blendet Abschnitte außerhalb des Fensters aus
  - ~~Vorschau je Sprache~~ – alpha.35 (Links je aktiver Sprache); Vorschau je Gerät = responsive
    Ansicht im Browser; Vorschau je Veröffentlichungsstatus über den vorhandenen Vorschau-Link
  - ~~CTA-Ziele intern auswählen~~ – alpha.30: CTA-Zielfelder schlagen Abschnitts-Anker per `<datalist>` vor
  - Medien-Picker auf freigegebene Bibliothek beschränken (CI-005) – bei Recherche
    festgestellt, dass WPs `ajax_query_attachments_args`/`post_id`-Kontext dafür nicht
    zuverlässig genug ist, um es ungeprüft auszuliefern; braucht eigene Prüfung in Docker
    (LIW-eigene Logo-/Hero-Auswahllisten kennzeichnen den Freigabestatus bereits)
  - ~~Pflichtfeldprüfung/Alt-Text-Warnung~~ – alpha.30: Redaktions-Prüfung im Content Board
    (Titel + Bilder ohne Alt-Text). Fehlende Übersetzungen → Sprach-Release-Gate (Etappe 6/LANG-006)
  Die 14 `liw_section`-Entwürfe sind seit alpha.17 per Knopf im Content Board anlegbar (mit
  Pflichtenheft-Vorgabe und eingebetteten Bausteinen); offen bleibt die **redaktionelle
  Ausformulierung** der Texte und Bildsprache durch die Redaktion. Details/Gesamtkonzept:
  `docs/LIW_LANDINGPAGE_KONZEPT.md`.
  *Quelle: CHANGELOG.md alpha.1, alpha.12, alpha.13, alpha.15.*

- **Kontaktanfragen – Folgepunkte (§22/§24).** Seit alpha.19 gebaut; Löschen (alpha.19) und
  **CSV-Export (alpha.30)** umgesetzt (§24 Export-/Löschprozesse). Offen bleiben:
  CRM-/Empfängerdefinition durch die Projektleitung (Pflichtenheft §31 – bis dahin Mail an
  WP-Admin-Adresse, Filter `liw_contact_recipients`), fachliche Wertelisten für Land/Region und
  Projektinteresse (ANNAHME-LIW-8/-9, Filter `liw_contact_regions`/`liw_contact_interests`),
  sprachabhängige Datenschutztext-Versionierung (§24) – seit alpha.24 mit Sprachsuffix umgesetzt.
  *Quelle: CHANGELOG.md alpha.19/alpha.30; Pflichtenheft §22/§24/§31.*

- **Landingpage-Feinheiten nach dem ersten Gerüst (alpha.18).** Ankernavigation: alpha.23; aktive
  Hervorhebung: alpha.26; Header/Navigation (§7) + Hero LP-01 (§8): alpha.28 (`[liw_header]`,
  `[liw_hero]`, Header Board). **Offen:** Hero-Bildmotiv erst nach Liebherr-Freigabe des Assets
  (im Header Board wählbar, wird bis dahin als neutraler Verlauf ausgegeben); **per-Sprache-Labels
  für Nav/CTAs** (aktuell Standard via `__()` mehrsprachig, redaktionelle Overrides literal –
  Verfeinerung, ggf. Anbindung an die Translation-Registry).
  *Quelle: CHANGELOG.md alpha.18/alpha.23/alpha.26/alpha.28.*

- **Visuelles Gesamt-Layout/Wireframe der Landingpage.** Der inhaltliche Bauplan
  (LP-01…LP-14) steht im Pflichtenheft und ist seit alpha.12 in
  `docs/LIW_LANDINGPAGE_KONZEPT.md` mit Umsetzungsstand zusammengefasst; ein visuelles
  Wireframe für Reihenfolge/Bildsprache/Übergänge der Gesamtseite fehlt noch.
  *Quelle: docs/LIW_LANDINGPAGE_KONZEPT.md, 18.09.2026.*

- **Simulation Board – Status-Übergänge.** Welt validieren/verwerfen, Szenario als
  bestanden/fehlgeschlagen markieren. Bewusst zurückgestellt, bis eine echte
  Simulations-Engine angebunden ist (YAGNI). Die Interface-Lifecycle-UI
  (`set_lifecycle_status()`) ist dagegen seit **alpha.31** im Interface Board umgesetzt.
  *Quelle: CHANGELOG.md alpha.4/alpha.31.*

- **World Connections Map.** alpha.37: abstrakte, interaktive Inline-SVG-Weltkarte `[liw_world_map]`
  (Zentrale + Regionen-Knoten aus den Verbindungen, Text-Alternative) – ohne externe Bibliothek/
  Koordinaten. Zusätzlich weiterhin das Regionen-Grid `[liw_world_connections_map]`. **Offen (optional,
  Kategorie A):** echte geografische Karte mit Koordinaten (latitude/longitude) + Kartenbibliothek.
  *Quelle: CHANGELOG.md alpha.8/alpha.37; AskUserQuestion 18.09.2026 „interaktive Weltkarte".*

- **Geschützter Partnerbereich – Folgepunkte.** Stufe 1 (Rolle + Konto, alpha.21) und Stufe 2
  (Dokumentenbereich, alpha.22) sind umgesetzt. Offen: (1) Entscheidung Liebherr, welche Fassung
  der Prozessdokumentation an Partner geht (Empfehlung: ohne Endpunktnamen) – der Bereich ist
  inhaltsneutral, der Upload liegt bei der Redaktion; (2) Konten für alle freigegebenen Händler
  automatisch statt per Knopf (ANNAHME-LIW-11); (3) Dokumente je Partner/Region statt für alle
  (ANNAHME-LIW-12, additiv per Join-Tabelle); (4) Migrationspfad zu Core-Option B
  (`Modules\Documents` um `owner_type` verallgemeinern), falls der Core Partnerdokumente braucht;
  (5) Login-Seite im Design System statt WP-Standard-Login (aktuell `wp_login_url()`).
  *Quelle: docs/LOGBUCH_TECHNIK.md alpha.21/alpha.22.*

## Architektur – offene Rückfrage an Joseph White

- **I18nSeo – Option B (Core-Router erweitern).** Entschieden 18.09.2026 (Logbuch alpha.24):
  Option A umgesetzt – Core-Sprachsteuerung (Cookie/`?lang=`) genügt funktional, `SeoBridge` liefert
  hreflang, seit alpha.32 zusätzlich Canonical je Locale (`get_canonical_url`-Filter), Open-Graph-Tags
  und Sitemap-Ausschluss der Abschnitts-Fragmente; `[liw_language_switcher]` nutzt das Core-Widget,
  Einwilligungs-Textversionen sprachabhängig. Release-Readiness je Sprache (LANG-006) im Language Board
  (alpha.32). Offen bleibt Option B als Kategorie-A-Folgepunkt:
  Core-`I18nRouter` um `page` (Trägerseite) und `liw_section` erweitern, sobald der Router
  plattformweit aktiv geschaltet wird – dann saubere `/en/interface-world`-URLs; die `SeoBridge`
  schweigt in diesem Fall bereits automatisch.
  *Quelle: docs/LOGBUCH_TECHNIK.md alpha.24; ADR-LIW-001.*

## Betrieb / Qualitätssicherung

- **Mehrsprachigkeits-Audit (DoD Punkt 9).** Seit alpha.32 liest das Language Board die
  Vollständigkeit je Sprache direkt aus der Core-Registry (`page_metrics()`/`is_complete()`) für die
  LIW-Flächen. Offen bleibt der formale `trx-scan`/`trx-audit`-Lauf je Seite×Sprache (Core-CI-Gate)
  für dieses Ausnahme-Plugin sowie die Entscheidung zum harten Sprach-Gate (Kategorie A, Betrieb).
  *Quelle: CLAUDE.md DoD Punkt 9; CHANGELOG alpha.32.*

---

**Bereits erledigt (zur Nachvollziehbarkeit, nicht mehr offen):**
- Media Board Paginierung und Onboarding Board Paginierung (beide seit alpha.7/alpha.6 als
  offen vermerkt) wurden mit alpha.10 umgesetzt.
- Gemeinsamer Docker-Praxistest aller Bereiche (`scripts/liw-selftest.php`) wurde mit
  alpha.11 ausgeliefert und am 18.09.2026 von Joseph in der Docker-Dev-Umgebung ausgeführt.
  Erster Lauf: 56/59 (zwei Befunde, behoben in alpha.14); zweiter Lauf: 59/59, kein
  Audit-Fehler mehr.
- Content Board Grundgerüst (Übersicht, Freigabeworkflow, native Editor-/Revisions-
  Anbindung) mit alpha.13 umgesetzt – Restpunkte s. oben.
- Einbettung der LP-07/LP-08-Grafiken (Shortcode `[liw_graphic]`) mit alpha.15 umgesetzt.
- Bauplan LP-01…LP-14 + Anlage der Standard-Abschnitte per Knopf (alpha.17) umgesetzt.
- Zusammengesetzte Landingpage `[liw_landingpage]` (alpha.18) umgesetzt.
- LP-13 Kontaktformular + Contact Board (alpha.19) umgesetzt – Folgepunkte s. oben.
- Partnerbereich Stufe 1: Rolle `liw_partner` + Kontoanlage im Onboarding Board (alpha.21).
- Datengetriebene Kern-Komponenten LP-08 Process Worlds, LP-12 Roadmap, LP-11 Onboarding-Schritte
  (alpha.29): Shortcodes `[liw_process_worlds]`/`[liw_roadmap]`/`[liw_onboarding_steps]` + Components
  Board. **Bewusst kuratiert (redaktionell, keine Extra-Komponente):** LP-02/03/04/05/07/09/10 –
  interaktive Vertiefung ist Feature-Flag/Folgeetappe (AC-002).
- Audit Board (Lese-Ansicht Core-Audit, gefiltert auf liw_) + Interface-Lifecycle-Status-UI (alpha.31).
  Release Board bewusst nicht eigenständig → Core-Deployment-Manager (Variante A).
- Sicherheit/Datenschutz (alpha.33): Rate-Limiting der öffentlichen Formulare (SEC-004),
  konfigurierbare Aufbewahrungsfrist + Cron für Kontaktanfragen (SEC-007/§24). Upload-Härtung
  (SEC-009) war bereits erfüllt; Security-Header (SEC-008) = Plattform/Server (dokumentiert).
- Qualität & Abnahme (alpha.34): Demo-/Seed-Daten (`scripts/liw-seed-demo.php`, §33) und
  Abnahmebericht (`docs/LIW_ABNAHME.md`, §32/§35 inkl. Rollback + §34-Launch-Blocker). Offen laut
  Abnahmebericht: Staging-Performance-/A11y-Vollmessung (AC-013/012), Staging-/Produktionsfreigabe
  (AC-016) und die externen §34-Inputs.
- Optik/Demo (alpha.37/38): Liebherr-CI vorläufig angewendet, „Connected World"-Weltkarte
  `[liw_world_map]`, LP-14 Footer `[liw_footer]` nach Liebherr-Vorbild, zusammengestellte
  Demo-Landingpage `/interface-world/` (`scripts/liw-seed-demo-landing.php`). Cache-Buster für
  Frontend-Assets + WP-Rocket-RUCSS-Safelist. alpha.39: Wortmarke-Token `brand_text` = „Liebherr
  Interface Solutions" (Header/Footer), Vollbild-Seitenvorlage (kein Theme-Kopf/-Fuß mehr).
  **Offen (optional):** echte Geo-Karte (Kategorie A); Feinschliff einzelner Abschnittstexte/-bilder.
- Ankernavigation/Sprungleiste der Landingpage (alpha.23).
- Aktive Hervorhebung des sichtbaren Abschnitts in der Sprungleiste (alpha.26): enqueuetes
  Skript `assets/js/liebherr-frontend.js` (IntersectionObserver, `aria-current`/`is-current`),
  CSS-Aktivzustand, Selbsttests. Der zuvor als YAGNI vertagte Punkt aus alpha.18 ist damit erledigt.
- I18nSeo Option A: Sprachumschalter-Shortcode, hreflang auf Trägerseite, §24-Sprachsuffix (alpha.24).
- Partnerbereich Stufe 2: geschützte Partnerdokumente – Board, Service, `[liw_partner_documents]` (alpha.22).
- Anonymisierte Grafiken LP-03/LP-04/LP-12 (alpha.20) geliefert – damit haben acht von 14
  Abschnitten einen gebauten Baustein; LP-01/02/05/09/10/14 sind reine Redaktion/Bildsprache.
- Hinweis an den Core (Klammern in Tabellen-COMMENTs, Befund alpha.16): von Joseph freigegeben und
  im Core umgesetzt – alpha.716 (41 Tabellen bereinigt) und alpha.717 (statischer Test
  `tests/Schema/run-tests.php`, Lauf 2/2 grün).

S. `LIW_PROGRAMMIERLOGBUCH.md` für Details.
