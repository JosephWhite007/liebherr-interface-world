# ADR-LIW-SALES-001 — Sales Interface: Untermenü hinter der Process-World-Karte „Sales" + Anbindung der Liebherr Dealer Interface API (Stufe 1)

**Auftrag:** Joseph White, 23.10.2026 — Karte „Sales" (Process Worlds, Interface Solutions) bekommt Mouse-over-Effekt und
führt auf ein passwortgeschütztes Untermenü (Code 3268) mit der aktuellen Schnittstellenbeschreibung; Integrationskonzept für
die API-Schnittstellen in Stufe 1 (Sales); alles im Backend administrierbar, nach dem Muster des Liebherr-Plugins.
**Status:** Entwurf zur Freigabe — Fragen F1–F4 am 23.10.2026 beantwortet (siehe Abschnitt 10); **Stufe 1a freigegeben (JW 23.10.2026, „F5–F12 gelten") und in 0.1.0-alpha.146 umgesetzt**; Stufe 1c (lesender Client, Manifest, Probe) in alpha.147 umgesetzt – Echtlauf 24.10.2026 erfolgreich (tab-text, product-list); 1d in alpha.148 gebaut (Händler-Entität, Schreibpfad, Trockenlauf – Echtlauf wartet auf SalesAccountNumber); 1b in alpha.149 umgesetzt (Codelisten-Import + Suche; Sandbox-Simulation entfällt zugunsten der echten Testverbindung); Variante B offen (`docs/LIW_TODO.md`).
**Plugin:** `liebherr-interface-world` (0.1.0-alpha.145) · **Quellen:** `Tradler-Dealer-Interface-Guide.md` (Sales API v3),
`LIVISION_Auswahlliste.xlsx` (Codelisten), Bestandscode des Plugins (siehe Abschnitt 1).

> Diktat-Auslegung (bitte bestätigen, Frage F1): „Stellenbeschreibung" = **Schnittstellenbeschreibung** (= Dealer Interface Guide);
> „AP" = **API**; „weder mit der 3268" = **wieder mit der 3268**.

---

## 1 Ist-Stand (verifiziert im Code)

| Baustein | Fundort | Bedeutung für das Konzept |
|---|---|---|
| Process-World-Karten (Sales … Warranty) | `Settings/ComponentContent.php` (Option `liw_components`, Schlüssel `process`), Shortcode `[liw_process_worlds]` in `Frontend/ComponentViews.php`; Pflege im **Components Board** | Karten sind heute reiner Text (`title`, `text`), kein Link, kein Hover. Erweiterung um Link + Schutz-Kennzeichen. |
| Landingpage-Abschnitte | CPT `liw_section` (LP-01…LP-14) + `Frontend/LandingpageView.php`, Übersetzung über `CoreBridge\TranslationBridge` | Vorbild für redaktionelle, übersetzbare Inhalte. |
| Weitere „Welten" hinter Zugang | `IntelligenceWorld\*` (Gate/Hub), `Cvf\AccessService`/`ChallengeService` (serverseitiger Code, signierte Rechen-Challenge, Rate-Limit) | Vorbild für geschützte Unterbereiche mit eigenem Board. |
| Zugangsschutz der Plattform | Core `Modules/Access/SiteGateSettings` (Maske, Standardcode 3268 über Env `ARY_SITE_GATE_CODE`, Regel je Seite „öffentlich / mit Code") | Möglichkeit A für den Schutz (Frage F2). |
| Schnittstellen-Katalog | `Interfaces\InterfaceCatalog*` (Tabelle `liw_interface`, Status draft→approved), Interface Board, Connection Board, Simulation Board (`SimulationMockEngine`) | Die 17 Endpunkte werden hier **geführt**, nicht neu erfunden. |
| Sicherheits-/Plattform-Brücken | `CoreBridge\{Audit,RateLimit,Translation,Role,Markdown,Language,Seo,Media}Bridge` | Wiederverwendung statt Eigenbau. |
| Admin-Menü | ADR-LIW-ADMIN-001 (Frontend / Backoffice / Administration Plattform) | Neues Board kommt in **Backoffice**, Handbuch-Kapitel unter Administration Plattform. |

## 2 Zielbild in einem Satz

Klick auf die Karte **Sales** → Seite **„Sales Interface"** (Zugangscode-Maske, danach Untermenü mit Reitern:
Übersicht · Umgebungen & Zugang · Endpunkte · Datenmodell · Codelisten · Sandbox · Roadmap/Änderungen · **Anleitung (letzter Reiter)**).
Alle Texte, Endpunkt-Einträge und Status sind im Backend pflegbar; die API-Anbindung läuft Schritt für Schritt von „Beschreibung" über „Sandbox"
zu „Live gegen den Liebherr-Testserver".

## 3 Teil A — Karte „Sales" (Hover + Link)

1. **Datenmodell:** Jede Process-Card in `liw_components['process']` erhält optionale Felder `target` (Seiten-ID oder Slug), `protected` (bool, nur Anzeige-Kennzeichen „🔒"),
   `badge` (z. B. „Neu"). Fehlt `target`, bleibt die Karte wie heute (kein Hover, kein Link) — die übrigen sechs Karten ändern sich nicht.
2. **Darstellung:** Karte mit Ziel wird als `<a class="liw-pcard liw-pcard--link">` ausgegeben. Hover/Fokus: Anheben (Schatten), gelbe Kopfleiste (Brand-Token `--brand-*`) wird kräftiger,
   Pfeil „→" blendet ein, optional Schloss-Symbol. Tastatur-Fokus identisch (Barrierefreiheit §26); `prefers-reduced-motion` schaltet die Animation ab. Nur CSS in `liebherr-frontend.css`, kein Inline-CSS/-JS.
3. **Admin:** Components Board → Reiter „Process Worlds": je Karte zusätzlich Zielseite (Auswahlliste der Liebherr-Seiten) und Kennzeichen „geschützt". Mehrsprachig über Language Board.
4. **Prüfung:** Selbsttest `tests/run-tests.php` um Sanitizing (`target` nur interne Seite) erweitert.

## 4 Teil B — Untermenü „Sales Interface" (Seite + Schutz)

### 4.1 Seitenstruktur
- Neue WP-Seite `Interface Solutions › Sales` (Slug-Vorschlag `/interface-solutions/sales/`, Vorlage `templates/full-width.php`), Inhalt über Shortcode `[liw_sales_interface]` (analog `[liw_process_worlds]`).
  Header/Footer/Brand wie die übrigen Welten; Rücksprung „← Process Worlds"; Reiter-Navigation als Anker/Unterseiten (Entscheidung F7).
- Der WorldSwitcher/Header bleibt unverändert (Sales ist Unterseite von Interface Solutions, keine fünfte Welt).

### 4.2 Passwortschutz (Varianten, Empfehlung A)
| Variante | Beschreibung | Vor-/Nachteil |
|---|---|---|
| **A (Empfehlung)** | Core-Zugangsschutz: Seite erhält Regel „mit Code"; Standardcode 3268 (Env `ARY_SITE_GATE_CODE`, Hash, nie Klartext) | Minimal, bewährt, ein Code wie auf der übrigen Plattform; Code im Backend pro Seite änderbar. Schwach: ein gemeinsamer Code, keine Händler-Zuordnung. |
| B | Eigener Gate im Plugin (`Cvf\AccessService` + `ChallengeService`): gehashter Code, Rechen-Challenge, Rate-Limit/Lockout, Audit | Händlerindividuelle Codes, Zugriffsprotokoll; mehr Aufwand. |
| C | A jetzt, B später (Hook vorgesehen) | Schnellster Start, kein Verbauen. |

Unabhängig von der Variante gilt: Sichtbarkeit im Frontend ist keine Autorisierung — technische Inhalte werden **serverseitig** erst nach bestandener Prüfung ausgeliefert (kein „Verstecken" per CSS/JS);
Seite ist `noindex`; keine Aufnahme in Sitemap/Suche.

### 4.3 Inhalt der Schnittstellenbeschreibung (aus dem Dealer Interface Guide)
Die Beschreibung wird **nicht als Fließtext fest verdrahtet**, sondern aus strukturierten Datensätzen gerendert (damit pflegbar und übersetzbar):

| Reiter | Inhalt (Quelle) |
|---|---|
| Übersicht | Zweck, Zielgruppe, Ablauf Händler ⇄ Livision ⇄ ARALIYA Interface Solutions, Status je Stufe |
| Umgebungen & Zugang | Test `https://livision.azure-api.net/crm/v3`, Produktion `https://emt-api.liebherr.com/crm/v3`, Developer Portal; Authentifizierung `Ocp-Apim-Subscription-Key` (Header/Query) + Pflichtheader `UserEmail` (Ausnahme `/sales-orders`). **Keine Schlüsselwerte auf der Seite** — nur „wie beantragt/hinterlegt" |
| Endpunkte | 17 Endpunkte in 5 Gruppen: **Kunde & Kontakt** (POST/PUT `/customer`, POST/PUT `/contact`) · **Bedarfsfall/Opportunity** (GET/PUT/DELETE `/opportunity`, `/opportunity-id`, `/opportunity/{id}`, `…/configuration/{id}`) · **Angebot & Livision-Links** (`/livision-url`, `/livision-url-for-update`, `/lias-opentrans-document`, `/customer-quotation-list`) · **Aufträge** (`/sales-orders`) · **Stammdaten** (`/product-list`, `/tab-text`). Je Endpunkt: Methode, Pfad, Zweck, Parameter, Pflichtfelder, Beispiel, **Status** (Entwurf / in Simulation / verifiziert / freigegeben) |
| Datenmodell | Schemas Customer(+Update), Contact(+Update), Opportunity: Feldname, Typ, Pflicht, Codeliste, Beschreibung |
| Codelisten | 21 Codelisten aus der Auswahlliste (z. B. 1B Bundesland, 1L Landkreis, 3B Branche, LN Land, KL Klassifizierung, TF Realisierung, 5G Grund Verlustmeldung …), 8 Sprachen (de, fr, en, it, es, ru, nl, zh), Suche je Liste/Sprache |
| Sandbox | Testaufruf-Konsole gegen Simulation (Stufe 1b) bzw. Testserver (1c) — nur für Berechtigte |
| Roadmap/Änderungen | Stufenplan, Änderungsverlauf der Beschreibung (Version, Datum) |
| Anleitung (letzter Reiter) | Handbuch-Regel/Grundannahme §1.15: Zweck, Bedienung, Rollen, FAQ |

Auffälligkeit im Guide (wird im Board als „Klärungspunkt" geführt, nicht geraten): `PUT /opportunity` ohne definierten Body; `GET /sales-orders` Parameter `oderDateFrom` (Tippfehler im Original, ggf. real so im API);
Schlüsselwerte „werden im Meeting übergeben".

## 5 Teil C — Integrationskonzept der Dealer-Interface-API (Stufe 1: Sales)

### 5.1 Architektur (neues Modul im Plugin, keine neue Plugin-Ebene)
`src/SalesInterface/`
- `DealerApiClient` — einziger Ausgangskanal: WP-HTTP-API, Header `Ocp-Apim-Subscription-Key` + `UserEmail`, Timeout, Retry nur bei GET, neutrale Fehler, Umschaltung Test/Produktion über Einstellung (Standard: Test).
- `EndpointRegistry` — die 17 Endpunkte als Daten (Pfad, Methode, Parameter, Pflichtheader, Gruppe); Quelle für Seite, Sandbox und Interface Board (`liw_interface`-Einträge, Richtung `outbound`/`bidirectional`).
- `CodeListService` — Lesezugriff auf importierte Codelisten, Abgleich mit `GET /tab-text`.
- `SalesInterfaceView` — Shortcode/Reiter; `SalesBoardPage` — Backend.
- Wiederverwendung: `AuditBridge` (jeder Aufruf protokolliert: Endpunkt, Status, Dauer — **keine Kunden-/Kontaktdaten im Log**), `RateLimitBridge`, `TranslationBridge`, `MarkdownBridge`, `RoleBridge` (neue Cap `liw_sales_interface_manage` / `…_use`).

### 5.2 Zugangsdaten & Sicherheit
- Subscription-Keys und `UserEmail`-Absender ausschließlich über Umgebungsvariablen (`LIW_LIVISION_KEY_TEST`, `LIW_LIVISION_KEY_PROD`, `LIW_LIVISION_USER_EMAIL`), **nie** in DB/Code/Repo/Log/Seite (ADR-031-Prinzip). Deklaration im Environment-Manifest (Filter `araliya_environment_manifest`, `clone_policy` = neu eingeben) — bei Duplicator-Klonen werden sie nicht mitgenommen.
- Ausgehende Aufrufe nur, wenn `InstanceState::may_send('liebherr-livision')` erlaubt (Klon-/Staging-Schutz, ADR-134). **Produktionsserver wird in Stufe 1 nicht angesprochen** (Keys laut Guide erst „nach Entwicklung und Tests").
- Serverseitige Berechtigungsprüfung je Aktion; Schreib-Endpunkte (POST/PUT/DELETE) standardmäßig deaktiviert (Feature-Flag, Default AUS).

### 5.3 Datenhaltung (additiv, verlustfrei)
- `liw_codelist` (language, code_list, code, description, additional_value; Index nach Liste+Sprache) — Zweck: Lokalspiegel der Liebherr-Codelisten (≈ 49 100 Zeilen; davon Landkreise 36 400). Befüllung per **idempotentem CLI-Skript** `scripts/liw-import-livision-codelists.php --confirm` (Terminal-Regel), ausgeführt von Joseph; Rollback: Tabelle leeren/löschen.
- `liw_codelist_meta` oder Option für Listenname/Entität (Blatt „CodeDetails").
- Endpunkt-/Beschreibungstexte: Option `liw_sales_interface` (strukturiert, versioniert) + Texte über Translation Registry — keine zusätzliche Tabelle nötig.
- Interface-Katalog: Einträge in vorhandener `liw_interface` (keine neue Tabelle).

### 5.4 Umsetzungsstufen
| Stufe | Inhalt | Externer Aufruf |
|---|---|---|
| **1a** | Hover-Karte, Sales-Seite mit Schutz, Reiter mit Beschreibung (aus Guide), Sales Board im Backend, Handbuch-Kapitel, Interface-Board-Einträge | nein |
| **1b** | Codelisten-Import + Suche, Sandbox mit `SimulationMockEngine` (synthetische Antworten nach Schema) | nein |
| **1c** | `DealerApiClient` gegen **Testserver**, nur lesende GETs (`product-list`, `tab-text`, `opportunity` suchen/lesen, `customer-quotation-list`, `sales-orders`) | Test, nur mit Keys + Freigabe |
| **1d** | Schreibende Endpunkte (Customer/Contact/Opportunity), Lost-Opportunity, Livision-Deep-Links | Test, einzeln freigegeben |
| später | Produktion, weitere Process Worlds (Configuration, Order, Goods, Finance, Service, Warranty) nach demselben Muster | — |

Empfehlung: **jetzt nur 1a freigeben** (liefert sichtbares Ergebnis, kein Risiko, keine Keys nötig); 1b–1d nach Klärung der Fragen F3/F4.

## 6 Backend-Administration (Wunsch „gesamte Seite wieder im Backend administrieren")

Neues Board **„Sales Interface"** unter *Liebherr Backoffice* (Muster `ComponentsBoardPage`/`InterfaceBoardPage`):
1. **Seite & Schutz:** Titel, Intro, Zielseite, Schutzstatus (Verweis auf Core-Gate-Regel bzw. Code ändern), Sichtbarkeit je Reiter.
2. **Umgebungen:** Anzeigetexte, Base-URLs, aktive Umgebung, Status der Env-Keys („hinterlegt / fehlt" — nie Wert).
3. **Endpunkte:** Liste (Gruppe, Methode, Pfad, Zweck, Parameter, Beispiel, Status, Klärungspunkt) — bearbeiten, sortieren, aktivieren; Sync mit `liw_interface`.
4. **Datenmodell:** Schemas/Felder bearbeitbar; Import der Feldlisten aus dem Guide per Markdown (MarkdownBridge) möglich.
5. **Codelisten:** Import-Status, Suche, Zeilenzahl je Sprache.
6. **Texte/Übersetzung:** alle Texte über Language Board / Translation Registry (Quelle DE, Zielsprachen nach F8).
7. **Protokoll:** Audit-Ansicht der API-Aufrufe (Audit Board filterbar).
8. **Anleitung:** letzter Reiter im Board + Kapitel im Handbuch (Handbuch-Regel).

## 7 Qualitäts-/Prozesspunkte (Definition of Done für Stufe 1a)
`php -l` im Container · Tests `tests/run-tests.php` erweitert · Prüfung im Docker (Hover, Gate, Board speichern) · **Permalinks speichern** (neue Seite/Slug) ·
CHANGELOG, Programmierlogbuch, Handbuch, ADR-Status · Annahmenprotokoll · Commit-Message vorbereiten (eigenes Git des Plugins) · keine Inline-Styles/-Scripts · Texte über `__()` bzw. Registry.

## 8 Risiken
- Schlüssel-Handling und Datenschutz: Kundendaten (Name, Adresse, Kontakt) sind personenbezogen → nie in Logs, in Sandbox nur synthetisch.
- Guide ist teilweise unvollständig (PUT-Body) → Klärungspunkte, keine Annahmen im Code.
- Ein gemeinsamer Code 3268 ist kein Händlerzugang → Variante B/C vorsehen.
- Codelisten-Import ist groß (≈ 1,4 MB xlsx) → nur per CLI, nicht per Web-Request.

## 9 Fragen vor Freigabe (ein Block)

**Blockierend**
- **F1** Ist „Stellenbeschreibung" = *Schnittstellenbeschreibung* (Dealer Interface Guide + Codelisten)? Oder gibt es zusätzlich einen bereits vorhandenen Text im Plugin/auf der Seite, den ich übernehmen soll (dann: wo)?
- **F2** Passwortschutz: Variante A (Core-Zugangsschutz, Code 3268), B (eigener Gate mit Händlercodes) oder C (A jetzt, B später)?
- **F3** Zweck der Sales-Seite in Stufe 1: reine **Dokumentation/Präsentation** (nur 1a/1b) oder soll sie ab 1c **live gegen den Liebherr-Testserver** arbeiten? Wer nutzt sie (Tradler-Mitarbeiter, Liebherr-Prüfer, interne Demo)?
- **F4** Liegen bereits Test-Subscription-Keys und eine `UserEmail` vor? (Ohne Keys bleibt es bei 1a/1b.)

**Annahmen (gilt als akzeptiert, wenn nicht widersprochen)**
- **F5** Hover-Effekt wie in Abschnitt 3; nur Karten mit Zielseite sind klickbar, die anderen sechs bleiben unverändert.
- **F6** Slug `/interface-solutions/sales/`, `noindex`, nicht in Sitemap.
- **F7** Reiter als Anker auf **einer** Seite (nicht als sieben Unterseiten).
- **F8** Sprachen: Quelle Deutsch, Englisch als erste Zielsprache; Codelisten in allen 8 Sprachen der Auswahlliste.
- **F9** Endpunkte werden in vorhandener `liw_interface` geführt; neue Tabelle nur `liw_codelist` (additiv).
- **F10** Produktivserver wird in Stufe 1 nicht angesprochen; Schreib-Endpunkte bleiben per Flag aus.
- **F11** Gestaltung nach Liebherr-CI-Tokens (gelb/anthrazit wie auf dem Screenshot), kein Eigenstil.
- **F12** Umsetzung zunächst nur **Stufe 1a**; Version `alpha.146…`, eigener Commit im Plugin-Git.

## 10 Antworten Joseph White (23.10.2026)

| Frage | Antwort | Folge fürs Konzept |
|---|---|---|
| F1 | Gemeint ist die **Schnittstellenbeschreibung** | Inhalt = Dealer Interface Guide + Codelisten, wie in 4.3 |
| F2 | **Variante C: A jetzt, B später** | Stufe 1a mit Core-Zugangsschutz (Code 3268); Gate-Hook so bauen, dass Händlercodes (Variante B) später ohne Umbau folgen |
| F3 | Später **live gegen den Liebherr-Testserver**; Nutzer: **Tradler-Mitarbeiter und wir intern als Entwickler** | 1a/1b zuerst, 1c später; zwei Nutzergruppen → zwei Capabilities (`…_use` für Tradler, `…_manage` für Entwickler) |
| F4 | Test-Keys und `UserEmail` **liegen vor** | Keys gehören **nicht in den Chat und nicht ins Repo**: Eintrag nur in der Container-/Server-Umgebung (`LIW_LIVISION_KEY_TEST`, `LIW_LIVISION_USER_EMAIL`), Joseph setzt sie selbst |
| F5–F12 | nicht widersprochen → gelten als akzeptiert | — |
