# Liebherr Interface Solutions — Programmierlogbuch

Protokolliert **jede Änderung am Quellcode** dieses Plugins auf Datei-/Klassenebene – ergänzend
zu `CHANGELOG.md` (fachliche Sicht: *was* wurde geliefert) und dem projektweiten
`docs/LOGBUCH_TECHNIK.md` im Core (*warum* wurde so entschieden). Dieses Dokument beantwortet
die dritte Frage: **welche Datei wurde wann und wie geändert**. Neue Einträge werden oben
ergänzt (neueste zuerst, analog zur Logbuch-Regel), bestehende Einträge werden nicht
rückwirkend verändert. Sichtbar im Backend unter „Interface World → 🧾 Programmierlogbuch".

Begonnen: 18.09.2026 (Entscheidung Joseph White – ab sofort wird jede Quellcodeänderung hier
mitgeschrieben, zusätzlich zum bereits bestehenden Handbuch und Entscheidungs-Logbuch).

---

## 0.1.0-alpha.88 – Redundanz-Abbau: Intro-Gate auf zentralen ChallengeService

**Geändert:** `src/Frontend/IntroOverlay.php` – das Rechen-Gate des LI-Intro-Overlays nutzt jetzt den
zentralen `Cvf\ChallengeService` (ADR-LIW-CVF-001 §5) statt eigener `wp_rand`-Logik. **Cache-sicher**: die
Aufgabe wird NICHT mehr ins (WP-Rocket-gecachte) HTML eingebettet (`data-liw-sum` entfernt), sondern per
REST geholt — neue Routen `GET liw-intro/v1/challenge` + `POST liw-intro/v1/verify` (öffentlich, kein Nonce),
Secret = `AccessService::secret()`. Die Summe verlässt den Server nie; Prüfung serverseitig (signiert/TTL).
`assets/js/liebherr-frontend.js` – Gate lädt die Aufgabe (`data-liw-eq` gefüllt), prüft per POST; Soft-Gate
bleibt (Netzwerkfehler blockiert nicht), bei „expired" neue Aufgabe. `scripts/liw-selftest.php` – zwei
`data-liw-sum`-Prüfungen ersetzt durch cache-sichere Checks + REST-Round-Trip. Live verifiziert (20+44 → frei).
Damit ist eins der zwei doppelten Rechen-Gates aus §5 abgelöst (Download-Consent-Plugin = separates Repo,
spätere Etappe). Tests WP-frei 476 / Docker 375. Bump alpha.87 -> alpha.88.

## 0.1.0-alpha.87 – CVF Phase 2: Backoffice-Board (Administration)

**Neu:** `src/Admin/Pages/CvfBoardPage.php` – Menüpunkt „🧭 Customer View Flow" (Untermenü, Zugriff über
`Cvf\Roles::CAP_ADMINISTER`): Flag an/aus + Vier-Augen-Schalter, Zugangscode setzen (nur Hash), veröffentlichte
Version + Prüfsumme ansehen, Challenge-Schwierigkeit (ein-/zweistellig) ändern und als NEUE unveränderliche
Version veröffentlichen (leichtgewichtige Vier-Augen-Prüfung), Versionshistorie- und Execution-Log-Tabelle.
Drei admin-post-Handler (je Nonce + Capability). **Geändert:** `src/Cvf/WorkflowVersion.php`
(`set_challenge_difficulty()` rein), `src/Cvf/WorkflowRepository.php` (`history()/last_published_by()/
publish_guarded()` – Vier-Augen: Freigebender ≠ letzter Veröffentlicher), `src/Cvf/SessionRepository.php`
(`recent_log()`), `src/Admin/AdminMenu.php` (Submenu + use), `src/Bootstrap.php` (Registrierung),
`tests/run-tests.php` (+1 set_challenge_difficulty), `scripts/liw-selftest.php` (+2: Board-Klasse/Slug,
Vier-Augen-Guard mit Cleanup). Board headless als Admin gerendert (alle Sektionen + Formularfelder da).
Tests WP-frei 476 / Docker 374. Bump alpha.86 -> alpha.87.

## 0.1.0-alpha.86 – CVF Phase 2: begehbarer Durchstich (Frontend-Wiring)

**Neu (`src/Cvf/`):** `Steps` (rein: Zustand → Schritt-Typ/Deskriptor), `Rest` (REST `liw-cvf/v1`:
`begin/code/challenge/module/first-entry`, öffentlich + cache-sicher, **self-gating** über `Flags::enabled()`
→ sonst reason=disabled; verbindet SessionRepository + AccessService [Lockout] + ChallengeService [einmalig]
+ Runtime; Module = die vier Inseln via WorldSwitcher, keine Redundanz), `FlowView` (Shortcode
`[liw_cvf_flow]`; nur mit Flag aktiv, sonst leer bzw. Admin-Hinweis). `AccessService::ensure_configured()`
seedet den Prototyp-Zugangscode. **Assets:** `assets/js/liw-cvf-flow.js` (treibt Eingang→Challenge→Modulwahl
→First-Entry→Modul über REST; anonyme Besucher-ID in sessionStorage), `assets/css/liw-cvf-flow.css`.
**Geändert:** `src/Bootstrap.php` (`Cvf\Rest`/`Cvf\FlowView` registriert), `tests/run-tests.php` (+2 Steps),
`scripts/liw-selftest.php` (+2: kompletter REST-Durchstich end-to-end via rest_do_request; ohne Flag
disabled). Default AUS → bestehender IW-Eintritt unverändert. Tests WP-frei 473 / Docker 372. Bump alpha.85 -> alpha.86.

## 0.1.0-alpha.85 – CVF Phase 2: Rollen-Mapping auf die ARALIYA-Rollen

**Neu:** `src/Cvf/Roles.php` (JW-Entscheid §9.1) – KEINE eigenen CVF-Rollen: fünf feingranulare
`liw_cvf_`-Capabilities (edit_content/edit_workflow/publish/audit/administer) werden über eine reine
Mapping-Tabelle `map()` auf bestehende ARALIYA-Rollen abgebildet (Content-Editor→araliya_marketing,
Workflow-Editor/Publisher→araliya_ops, Auditor→araliya_finance/reception, Voll = administrator/araliya_admin).
`role_caps()` aggregiert Slug→Caps (rein/testbar); `grant()/revoke()` vergeben/entfernen die Caps nur an
VORHANDENE Rollen (robust gegen abweichende Core-Rollensätze). **Geändert:** `liebherr-interface-world.php`
– `Roles::grant()` in `activate()` UND selbstheilend in `maybe_upgrade_database()`, `Roles::revoke()` in
`deactivate()` (administrator bleibt unberührt). `tests/run-tests.php` (+2 reine Mapping-Asserts),
`scripts/liw-selftest.php` (+1: administrator hat nach Upgrade alle CVF-Caps). Tests WP-frei 465 / Docker 370.
Bump alpha.84 -> alpha.85.

## 0.1.0-alpha.84 – CVF Phase 2: Feature-Flags + gehärteter Zugangscode

**Neu (`src/Cvf/`):** `Flags` – `enabled()` (Option/Filter `liw_cvf_enabled`, Default AUS → CVF-Runtime
bleibt hinter Flag) + `four_eyes()` (Option/Filter `liw_cvf_four_eyes`, Default AUS, JW-Entscheid §9.4).
`AccessService` (JW-Entscheid §9.2) – Zugangscode als HMAC-SHA256 gegen Secret (Option `liw_cvf_secret`),
NIE Klartext gespeichert; reine Kernlogik `normalize()/hash_code()/verify()` (zeitkonstant, WP-frei
testbar); Verwaltung `secret()/set_code()/get_hash()/is_configured()`; Rate-Limit/Lockout pro Drossel-
schlüssel über Transients (`attempt()`, MAX_ATTEMPTS 5 / LOCK_TTL 300, `is_locked()/clear()`).
**Geändert:** `tests/run-tests.php` (+3 Asserts reine Access-Kernlogik), `scripts/liw-selftest.php` (+3:
Flag Default AUS, richtiger Code → ok + Hash gespeichert, Lockout nach Fehlversuchen). Aktiv erst mit
`liw_cvf_enabled`; der bestehende IW-Prototyp-Eintritt bleibt bis dahin unverändert. Tests WP-frei 461 /
Docker 369. Bump alpha.83 -> alpha.84.

## 0.1.0-alpha.83 – CVF Phase 2: Persistenz (Version + Sitzung + Execution-Log)

**Neu (`src/Cvf/`):** `Schema` – drei Tabellen `liw_cvf_workflow_version` (veröffentlichte Version
UNVERÄNDERLICH + Prüfsumme), `liw_cvf_visitor_session` (anonyme Sitzung, an Version gebunden),
`liw_cvf_execution_log` (append-only Übergangsprotokoll); COMMENT ohne Klammern (Falle alpha.16).
`WorkflowRepository` (`publish()` validiert + speichert unveränderlich mit SHA-256-Prüfsumme,
`get_active()/get()/ensure_active()/next_version()`), `SessionRepository` (`start()` bindet an aktive
Version + Zustand new→at_entry, `advance()` schreibt Zustand über die reine Runtime fort UND protokolliert
JEDEN Übergang append-only, `get()/get_by_visitor()/log_count()`). **Geändert:** `liebherr-interface-world.php`
`create_tables()` → `Cvf\Schema::create_tables()` (läuft via maybe_upgrade_database). `scripts/liw-selftest.php`
(+2: aktive Version/Prüfsumme, Sitzungsdurchlauf new→in_module mit protokollierten Übergängen, inkl. Cleanup).
Tabellen auf Docker verifiziert (ary_liw_cvf_*). Tests WP-frei 454 / Docker 366. Bump alpha.82 -> alpha.83.

## 0.1.0-alpha.82 – CVF Phase 2: Durchstich-Runtime (Config + Zustandsmaschine)

**Neu (rein/testbar, `src/Cvf/`):** `StageType` (entry/challenge/module_select/first_entry),
`VisitorState` (new→at_entry→at_challenge→at_module_select→at_first_entry→in_module, plus blocked;
`is_admitted()`), `WorkflowVersion` (`default_config()` = 4-Stufen-Durchstich, `stages()`, `validate()`
mit Problemliste, `canonical()` = rekursiv sortierte Serialisierung, `checksum()` = SHA-256), `Runtime`
(deterministisch: `start()`/`next(state,event,config)` → {state,action,reason,params}; `block` aus jedem
Zustand; Challenge-Schwierigkeit aus der Config gelesen). Grundlage des vertikalen Durchstichs (JW-Entscheid
§9). **Geändert:** `tests/run-tests.php` (+~8 Asserts: Config gültig/Prüfsumme/Validierung + Happy-Path +
invalider Übergang + block), `scripts/liw-selftest.php` (+1), `docs/ADR-LIW-CVF-001…md` (§9 Freigabe +
5 Entscheidungen dokumentiert). Persistenz/AccessService/Frontend folgen. Tests WP-frei 447 / Docker 364.
Bump alpha.81 -> alpha.82.

## 0.1.0-alpha.81 – CVF Phase 2 (Start): einheitlicher ChallengeService

**Neu:** `src/Cvf/ChallengeService.php` (ADR-LIW-CVF-001 §5) – EINE reine, testbare Quelle für die
„Human Verification"-Rechenaufgabe der Plattform: HMAC-signiert, TTL, Nonce-Einmalgebrauch, Summe bleibt
serverseitig; Schwierigkeit konfigurierbar (`single` = 1–9, `double` = 10–99); `create()/question()/
make_token()/verify()/parse()/bounds()/normalize()`. **Geändert:** `src/Emergency/EmergencyChallenge.php`
ist jetzt ein dünner Adapter darauf (Schwierigkeit `single`), Signier-/TTL-/Nonce-Logik nicht mehr
dupliziert (öffentliche API unverändert → Koffer-Tests grün). `tests/run-tests.php` (+3 Asserts, require
vor EmergencyChallenge), `scripts/liw-selftest.php` (+1). Nächste CVF-Schritte: die zwei Alt-Rechen-Gates
(LI-IntroOverlay, Download-Consent) auf den Service umstellen (danach Altpfad entfernen) + Domänenmodell
`liw_cvf_*`. Tests WP-frei 432 / Docker 363. Bump alpha.80 -> alpha.81.

## 0.1.0-alpha.80 – Kopfzeile: Doppelglobus entfernt + voll-breite World-Leiste

**Geändert:** `src/Frontend/FaviconService.php` – Filter `wp_admin_bar_show_site_icons` → `__return_false`:
da unser `get_site_icon_url`-Filter `has_site_icon()`=wahr macht, hängte Core zusätzlich ein
`img.site-icon` neben den Seitennamen der Adminleiste (zweiter Globus); der Globus bleibt dort als
ersetztes WP-Logo. `src/Frontend/WorldSwitcher.php` – `render()` bekommt einen `.liw-switcher__inner`
(Inhalt zentriert), `assets/css/liebherr-frontend.css` – `.liw-switcher` jetzt `width:100%` mit dunklem
Hintergrund über die volle Breite, `.liw-switcher__inner` trägt `max-width`/`margin:auto`/`padding`
(vorher lag der dunkle Hintergrund nur auf dem zentrierten Element → weiße Ränder links/rechts auf breiten
Screens). `scripts/liw-selftest.php` (+2). Frontend voll-breit im Browser verifiziert. Bump alpha.79 -> alpha.80.

## 0.1.0-alpha.79 – Hilfe-Koffer: JS-freier Fallback-Pfad (Barrierefreiheit)

**Geändert:** `src/Emergency/EmergencyController.php` – Koffer-Button ist jetzt ein `<a>` auf den Fallback
(`?liw_help=1&from=<Ort>`); das Overlay-JS fängt den Klick weiterhin ab (progressive Enhancement). Neuer
Handler `maybe_render_fallback()` an `template_redirect` (Priorität 1, VOR `redirect_canonical`) rendert
ohne JS eine eigenständige Emergency-Seite: serverseitiges Aufgaben-Formular (POST) → bei richtiger Antwort
die Emergency-Area (`fallback_page_html()`, gleiche Engines wie REST/Overlay, keine Logik-Duplizierung;
`noindex`, `nocache_headers`). `scripts/liw-selftest.php` (+3 Checks). Live per curl verifiziert (GET-Formular
→ POST → Area). FALLE: ohne Prio 1 fing `redirect_canonical` das GET ab (302 → leerer Body). Bump alpha.78 -> alpha.79.

## 0.1.0-alpha.78 – Hilfe-Koffer & Emergency-Area (plattformweiter Hilfeassistent)

**Neu:** `src/Emergency/EmergencyChallenge.php` (reine, signierte Rechenaufgabe mit ZWEI EINSTELLIGEN
Zahlen 1–9; HMAC + TTL + Nonce; `create()/question()/make_token()/parse()/verify()`),
`src/Emergency/HelpTopicCatalog.php` (reiner kontextbezogener Themenkatalog; `detect()/topic()/topics()`;
allgemeine Schritte referenzieren `GetHelpAssistant` als SSOT – keine Duplikate),
`src/Emergency/EmergencyController.php` (winziger Koffer-Punkt site-weit via `wp_footer`+`admin_footer`
und Shortcode `[liw_emergency_suitcase]`; REST `liw-emg/v1` `GET /challenge` + `POST /verify` mit
Einmalgebrauch via Transient; `render_hub()` = Emergency-Area; Secret in Option `liw_emg_secret`),
`assets/js/liw-emergency.js` (Overlay: Aufgabe holen → prüfen → Emergency-Area inline anzeigen),
`assets/css/liw-emergency.css` (16px oranger Punkt, bei Hover/Focus Koffer sichtbar; Modal-Overlay),
`assets/img/liw-emergency-suitcase.svg` (oranger Hilfe-Koffer mit weißem Kreuz).
**Geändert:** `src/Bootstrap.php` (`Emergency\EmergencyController::register()`),
`tests/run-tests.php` (14 Asserts: Challenge-Roundtrip/expired/invalid/wrong, Katalog-Detect/Fallback,
render_hub), `scripts/liw-selftest.php` (6 Checks inkl. REST-Namespace + einstellig). Live per Browser +
REST-Round-Trip verifiziert (8+4 → Emergency-Area). Bump alpha.77 -> alpha.78.

## 0.1.0-alpha.77 – Globus-Favicon nicht mehr doppelt (eine Quelle: Core)

**Geändert:** `src/Frontend/FaviconService.php` – da `filter_site_icon_url()` `has_site_icon()` „wahr"
macht, gibt WordPress-Core `wp_site_icon()` die Icon-`<link>`s in `wp_head`/`login_head` bereits selbst
aus; unser eigener `<link rel="icon">` in `output()` entfiel (war Dublette), `output()` liefert nur noch
das Marken-CSS. Für den Admin (Core hängt `wp_site_icon` nicht an `admin_head`) hängen wir dieselbe
Core-Funktion an `admin_head`. `scripts/liw-selftest.php` angepasst (+1). Bump alpha.76 -> alpha.77.

## 0.1.0-alpha.76 – Browser-Tab-Favicon zeigt Globus statt WP-„W"

**Geändert:** `src/Frontend/FaviconService.php` – neuer Filter `get_site_icon_url`
(`filter_site_icon_url()`): `/favicon.ico` leitet ohne gesetztes Website-Icon per Core auf das graue
WP-Logo (`w-logo-gray-white-bg.png`) um; der Filter liefert stattdessen den Globus (echtes Customizer-
Website-Icon behält Vorrang; PNG bevorzugt, sonst SVG). `scripts/liw-selftest.php` (+1). Bump alpha.75 -> alpha.76.

## 0.1.0-alpha.75 – Goldener Globus statt WordPress-Logo (Adminleiste + Login)

**Geändert:** `src/Frontend/FaviconService.php` – `brand_logo_css()` (Globus-Hintergrund für
`#wp-admin-bar-wp-logo .ab-icon:before` und `body.login h1 a`), in `output()` (wp_head/admin_head/login_head)
ausgegeben; Filter `login_headerurl` -> home, `login_headertext` -> Seitenname. Reuse `liw-planet-icon.svg`.
Selftest ergänzt. Bump alpha.74 -> alpha.75.

## 0.1.0-alpha.74 – Simulation Builder: austauschbare Engine-Naht

**Neu:** `src/IntelligenceWorld/SimulationEngineInterface.php` (Vertrag `forecast()`/`id()`),
`SimulationMockEngine.php` (Standard, delegiert an `SimulationModel`), `SimulationEngine.php`
(`resolve()`/`is_mock()`, Filter `liw_iw_simulation_engine`). `Rest::simulate` + Route `POST /simulate`.
**Geändert:** `SimulationView` (Default-Render über Engine; `wp_localize_script('liwIwSim', {rest,useServer,engine})`).
`assets/js/liw-iw-simulation.js` (recompute: bei `useServer` REST `simulate`, sonst lokaler Mock-Spiegel).
`tests/run-tests.php` (Stub `apply_filters`). Tests/Selftest. Bump alpha.73 -> alpha.74.

## 0.1.0-alpha.73 – Cockpit -> Simulation Builder verbunden

**Geändert:** `src/Frontend/SimulatorView.php` – `target()`-Default = IW-Seite + `#liw-iw-simulation`.
`assets/js/liw-intelligence-world.js` – nach Access-Gate-Eintritt Auto-Scroll zum Simulation Builder, wenn
der Hash `#liw-iw-simulation` gesetzt ist (instant, verzögert). Selftest ergänzt. Bump alpha.72 -> alpha.73.
Klärt die Frage „Cockpit <-> Simulations-Engine": navigatorische Verbindung gebaut; echte Engine bleibt
Gruppe-B (Naht = `SimulationModel::forecast`). 

## 0.1.0-alpha.72 – Content Board: Medien-Picker auf freigegebene Bibliothek [Backlog A11]

**Neu:** `src/Admin/ApprovedMediaFilter.php` (`restrict()` rein/testbar; `maybe_restrict()` honoriert Flag
`liw_approved_only`; `enqueue()` setzt das Flag via `wp.media.query`-Override nur auf LIW-Board-Seiten
[`is_liw_admin_page`], Hook `ajax_query_attachments_args`). **Geändert:** `src/Bootstrap.php`
(`ApprovedMediaFilter::register()` im Admin-Zweig). Tests/Selftest. Bump alpha.71 → alpha.72.
Kontext-Raten am `post_id` bewusst vermieden (To-Do-Hinweis); stattdessen seitengebundenes Flag.
**Backlog Gruppe A (A1–A11) vollständig abgearbeitet.**

## 0.1.0-alpha.71 – Intelligence World: serverseitige Protokoll-PDF [Backlog A10]

**Neu:** `src/IntelligenceWorld/PdfDocument.php` (reiner PDF-Generator `from_lines`, A4/Helvetica/xref,
Translit). `ProtocolBuilder::to_lines()` (Protokoll → Textzeilen, rein). `Rest::protocol_pdf` + Route
`GET /session/protocol-pdf` (streamt PDF). **Geändert:** `assets/js/liw-intelligence-world.js` (Protokoll-Aktion
„PDF herunterladen (Server)"), `WorldView` i18n `proto_pdf`. Tests/Selftest. Bump alpha.70 → alpha.71.
**Nächster Bau:** A11 – Content Board: Medien-Picker auf freigegebene Bibliothek beschränken.

## 0.1.0-alpha.70 – Simulation Builder: Szenarien speichern & vergleichen [Backlog A9]

**Geändert:** `src/IntelligenceWorld/SimulationView.php` (Button „Szenario speichern" + Container
`data-liw-sim-saved`). `assets/js/liw-iw-simulation.js` (localStorage-Store `liwIwSimSaved`, `renderSaved`/
`renderCompare`; `bind` merkt letzte Prognose, Save/Compare/Clear). `assets/css/liw-intelligence-world.css`
(`.liw-iw__sim-saved*`/`.liw-iw__sim-cmptable`). Selftest. Bump alpha.69 → alpha.70.
**Nächster Bau:** A10 – Nutzungs-/Kostenprotokoll: echte serverseitige PDF-Erzeugung.

## 0.1.0-alpha.69 – Local Intelligence: Szenario-Editor im Board [Backlog A8]

**Geändert:** `src/Admin/Pages/LocalIntelligenceBoardPage.php` – `scenarios_area()` (Render des A/B/C-Editors
als Strukturfeld), `parse_scenarios()` (public, rein/testbar), im Save `$raw['simulation']['scenarios']`
eingehängt (Content::sanitize bereinigt). Kein Datenmodell-Change (Szenarien in `liw_local_intelligence`
existieren, waren nur nicht editierbar). Tests/Selftest. Bump alpha.68 → alpha.69.
**Nächster Bau:** A9 – Simulation Builder: Szenarien speichern/vergleichen.

## 0.1.0-alpha.68 – Adventures: Get-Help-Assistent (Phase 3) [Backlog A7]

**Neu:** `src/Adventures/GetHelpAssistant.php` (`steps()` rein/testbar [5 Schritte], `render()` barrierefreie
`<details>`, Shortcode `[liw_adventures_help]`). **Geändert:** `AdventuresView` (Hero-CTA „Get Help" + Assistent
inline vor dem Stream), `src/Bootstrap.php` (`GetHelpAssistant::register()`), `assets/css/liw-adventures.css`
(`.liw-adv__help*`). Tests/Selftest. Bump alpha.67 → alpha.68. **Nächster Bau:** A8 – Local-Intelligence-Szenario-Editor im Board.

## 0.1.0-alpha.67 – Adventures: Medien-Upload [Backlog A6]

**Neu:** `src/Adventures/UploadService.php` (`allowed_exts`/`is_allowed_ext` [rein], `handle()` via
`media_handle_upload`, mimes-Override, nur Bilder). `Rest::upload` + Route `POST /upload` (Policy::can_create,
`get_file_params()`). **Geändert:** `SubmissionForm` (Datei-Feld + Status + Hidden `media_id`).
`assets/js/liw-adventures.js` (FormData-Upload → `media_id`; Submit sendet `media_id`). i18n uploading/uploaded/
uploadErr. `AdventureService::create` nutzt media_id bereits (Beitragsbild). Tests/Selftest. Bump → alpha.67.
Video/Transcoding bewusst später (§14). **Nächster Bau:** A7 – Get-Help-Assistent (Phase 3).

## 0.1.0-alpha.66 – Adventures: echtes Tokenbudget-Konto [Backlog A5]

**Neu:** `src/Adventures/TokenAccount.php` (User-Meta `_liw_adv_token_balance`; `default_balance`/`balance`/
`charge`/`grant`). **Geändert:** `Rest::accept` bucht das Konto ab (nur reason `budget_ok`), liefert `balance`;
`Rest::budget_for` → `TokenAccount::balance`. `RegistrationService::record_access` gibt `reason`+`is_author`
zurück; `access_preview` liefert `balance`. `assets/js/liw-adventures.js` – Dialog zeigt/aktualisiert Guthaben.
`Admin\Pages\AdventureBoardPage` – „Tokenkonto aufladen" (admin_post `liw_adv_token_grant`). Tests/Selftest.
Bump alpha.65 → alpha.66. **Nächster Bau:** A6 – Medien-Upload/Transcoding (§14).

## 0.1.0-alpha.65 – Adventures: Suche & Filter nach Maschine/Bauteil [Backlog A4]

**Geändert:** `AdventureCpt` (Meta `M_MACHINE`/`M_COMPONENT`). `AdventureService::create` speichert Maschine/
Bauteil; `query()` um `search` (WP-`s`) + `machine`/`component` (`LIKE`) erweitert; `to_view` trägt beide.
`SubmissionForm` (Maschine-/Bauteil-Felder). `AdventuresView` (Filterleiste: Suche + Maschine + Bauteil).
`assets/js/liw-adventures.js` (`refreshStream` liest alle Filter, Debounce für Textfelder; Submit sendet
machine/component). `Adventures\Rest` (`stream` + `create` reichen die Parameter durch). `DetailView` (Meta
Maschine/Bauteil). Tests/Selftest. Bump alpha.64 → alpha.65.

**Nächster Bau:** A5 – Adventures echtes Tokenbudget-Konto.

## 0.1.0-alpha.64 – Adventures: Detailseite eines Beitrags [Backlog A3]

**Neu:** `src/Adventures/DetailView.php` (Shortcode `[liw_adventure_detail]`, `?adv=<ID>`; `render(id)` =
Kopf [Badges/Titel/Ort/Status/Tokenwert/Artikelbook/Medium] + Inhalts-Gate; `current_id()`/`url()`). Inhalt
bei kostenpflichtig+nicht-Autor+kein-Zugriff verborgen (nicht im DOM); nach Dialog-Bestätigung → `reload`,
Server zeigt Inhalt (has_access). `TokenLedger::has_access(contrib,user,version)`.

**Geändert:** `src/Adventures/AdventuresView.php` – Detail bei `?adv` vorangestellt; Karten-Button `open_button()`
→ Link auf `?adv=ID` („Details ansehen"); Enqueue auch für Detail-Shortcode; i18n detail/detailFor.
`assets/js/liw-adventures.js` – `buildCard` Karten-Link statt Modal-Button; `confirmAccess` löst Event
`liw-adv-accepted` aus; `boot()` lauscht → Detailseite lädt nach Zugriff neu. `src/Bootstrap.php` –
`DetailView::register()`. `assets/css/liw-adventures.css` – `.liw-advdetail*`. Tests/Selftest. Bump → alpha.64.

**Ergebnis:** Backlog-A3 erledigt. Nächster Bau laut Reihenfolge: **A4 – Adventures Suche/Filter nach Maschine/Bauteil (§18).**

## 0.1.0-alpha.63 – Intelligence World: Compute-Metering + kostenpflichtige Module [Backlog A2]

**Neu:** `src/IntelligenceWorld/ModuleCatalog.php` (reine 5-Aktionen-Katalog: `actions/is_valid/get/label/
cost_minor/public_list`; Preis über `PriceRule`). `Rest::use_module()` + Route `POST session/use` (Ereignis mit
Kosten ins Ledger bei aktiver Sitzung). `assets/js/liw-intelligence-world.js` – Modul-Buttons (`data-liw-iw-use`)
→ `session/use`, laufende Zusatzkosten (`data-liw-iw-modtotal`); `renderProtocol` um Posten-Tabelle + Basis/
Module/Gesamt erweitert. `assets/css/liw-intelligence-world.css` – `.liw-iw__mod-*`.

**Geändert:** `src/IntelligenceWorld/ProtocolBuilder.php` – Metadaten je Ereignis dekodiert; `line_items` +
`billing.modules_cost_minor`/`modules_display`/`total_cost_minor`/`total_display`. `src/IntelligenceWorld/WorldView.php`
– Modul-Panel im Weltraum, `modules` (ModuleCatalog::public_list) + i18n in localize. Tests/Selftest ergänzt.
Bump alpha.62 → alpha.63.

**Ergebnis:** Backlog-A2 erledigt. Nächster Bau laut Reihenfolge: **A3 – Adventures-Detailseite (Einzelansicht).**

## 0.1.0-alpha.62 – Intelligence World: Backoffice-Pflege-Board (Tarife + Navigation/Hotels) [Backlog A1]

**Neu:** `src/Admin/Pages/IntelligenceWorldBoardPage.php` (MENU_SLUG `liw-iw-board`; `render()` = Formular für
Tarife/Budgets + Eintrittstexte + Katalog-Editor [Segmente/Lösungswelt/Hotels]; `handle_save()` admin-post mit
Nonce+Cap; `save_from_request(array)` getrennt/testbar → WorldContent::save + CatalogContent::save; Feld-Helfer
text/number/checkbox/select/node_editor, alles escaped). Checkbox `storage_is_minimum` wird auf 0/1 normalisiert.

**Geändert:** `src/Admin/AdminMenu.php` (Submenü „🪐 Intelligence World" + use-Import),
`src/Bootstrap.php` (`IntelligenceWorldBoardPage::register()` für admin_post). Tests/Selftest ergänzt.
Bump alpha.61 → alpha.62.

**Ergebnis:** Backlog-A1 erledigt (Navigation & Hotels + Tarife jetzt im Backoffice pflegbar statt nur Optionen).
Nächster Bau laut Reihenfolge: **A2 – Compute-Metering (Mock) + kostenpflichtige Module als Ledger-Ereignisse**.

## Dokumentationsstand 19.09.2026 (nach alpha.61, Push origin/master fd4d464..0353085)

Konsolidierungslauf, kein Quellcode geändert. Geprüft: alle drei Bücher sind auf aktuellem Stand –
Programmierlogbuch lückenlos alpha.1→alpha.61 (neueste oben), Handbuch mit Funktionalitäts-Absätzen bis
alpha.61, CHANGELOG bis alpha.61. `docs/LIW_TODO.md` erhielt eine geordnete **Abarbeitungsreihenfolge** aller
zurückgestellten Jobs (Gruppe A = intern baubar in Reihenfolge, Gruppe B = extern/blockiert) und einen
aktualisierten Kopf-Stand (alpha.41 → alpha.61). Nächster Bau laut Reihenfolge: **A1 – Intelligence-World-
Pflege-Board (Navigation & Hotels + Tarife) im Backoffice.**

## 0.1.0-alpha.61 – Adventures: Tokenakzeptanz-Dialog beim Zugriff

**Geändert:** `assets/js/liw-adventures.js` – Zugriff-Button in `buildCard()`; Modal-Dialog (`getModal`/
`openDialog`/`confirmAccess`): GET `access?post_id` → Vorschau (Tokenwert/Umfang/Version/Bedingungen), Akzeptanz-
Checkbox aktiviert „bestätigen" → POST `accept` → Ergebnis (Belastung + Transaktions-ID); Delegated-Click in
`boot()`. `src/Adventures/AdventuresView.php` – Server-Karten-Button `open_button()` + Dialog-i18n in localize.
`src/Adventures/AdventureService.php` – `to_view()` um `token_value` + `reg_status` erweitert.
`src/Adventures/RegistrationService.php` – `access_preview()` liefert `usage_label`/`status_label`; neue
`usage_label()`. `assets/css/liw-adventures.css` – `.liw-advmodal*` + `.liw-adv__open` (Modal, Dark-Mode-fest).
Selftest ergänzt (Rest::access/accept mit Nicht-Ersteller-Kontext). Bump alpha.60 → alpha.61.

**Verifikation:** WP-frei 372 / Docker 318; Dialog im Browser gerendert (geladener Zustand + Belastung headless,
da `access`/`accept` Login verlangen). §6 erfüllt: Tokenwert + Bedingungen sichtbar VOR der Bestätigung.

## 0.1.0-alpha.60 – Adventures: Workboard-Optik-Eingabemaske (Frontend + Backend)

**Neu:** `src/Adventures/SubmissionForm.php` (gemeinsamer Masken-Renderer `render('frontend'|'admin')` in
Workboard-Optik: Kategorie/Dringlichkeit/Titel/Beschreibung + Token-Block [Tokenwert `data-liw-adv-token`,
Nutzungsumfang `data-liw-adv-usage`, Rechte `data-liw-adv-rights`] + Aktionen draft/register).
`src/Admin/Pages/AdventureBoardPage.php` (Backend-Board: MENU_SLUG `liw-adventure-board`, `render()` = Maske
[admin] + Board-Tabelle über alle Beiträge mit Status/Token/Version/Artikelbook + Moderations-Buttons →
REST `moderate`; eigenes Enqueue von liw-adventures css/js + Inline-Moderations-JS).

**Geändert:** `src/Adventures/AdventuresView.php` (Create-Panel → `SubmissionForm::render('frontend')`; i18n
needTitle/needRights/articlebook). `assets/js/liw-adventures.js` (Submit sendet token_value/usage_scope/
rights_confirmed; Aktion „register" = intent submit + Rechte-Pflicht; zeigt Artikelbook-Ref). `src/Admin/AdminMenu.php`
(Submenü „🗺 Adventures" + use-Import). `src/Bootstrap.php` (`AdventureBoardPage::register()`).
`assets/css/liw-adventures.css` (`.liw-wb*`-Stile). Tests/Selftest ergänzt. Bump alpha.59 → alpha.60.

**Verifikation:** WP-frei 372 / Docker 315; Admin-Board + Frontend-Maske headless gerendert (Browser-Sicht
erfordert Login). Hinweis: Frontend-Maske nur mit Intelligence-Zugang (Policy::can_create), sonst Zugangs-Notiz.

## 0.1.0-alpha.59 – Adventures: Basislogik Registrierung, Tokenwert & Artikelbook (Fundament + REST)

**Neu:** `src/Adventures/RegistrationStatus.php` (reiner 9-Status-Automat + Übergänge + Veröffentlichungsstufen),
`src/Adventures/TokenPolicy.php` (reine Tokenregeln: `resolve_access`, `sanitize_value`, `next_version`),
`src/Adventures/TokenSchema.php` (Tabelle `liw_adv_ledger`, COMMENT ohne Klammern),
`src/Adventures/TokenLedger.php` (append-only Hash-Kette: reine `canonical`/`hash` + `append`/`chain_for`/
`verify_chain`), `src/Adventures/RegistrationService.php` (Workflow-Orchestrator: register/request_validation/
set_validation_result/publish_world/block/archive/set_token_value/access_preview/record_access),
`src/CoreBridge/ArticlebookBridge.php` (Filter `liw_articlebook_register`/`liw_articlebook_url` + lokaler Fallback).

**Geändert:** `src/Adventures/AdventureCpt.php` (Meta M_TOKEN_VALUE/M_REG_STATUS/M_REG_ID/M_VERSION/
M_ARTICLEBOOK_REF/M_ARTICLEBOOK_URL/M_RIGHTS_OK/M_USAGE_SCOPE). `src/Adventures/Rest.php` (Routen register/
request-validation/access/accept/moderate; `create()` registriert direkt bei Tokenwert+Rechte-Zusicherung).
`liebherr-interface-world.php` (`Adventures\TokenSchema::create_table()` in create_tables). Tests/Selftest ergänzt.
Bump alpha.58 → alpha.59.

**Entscheidung:** Core-Workboard nicht wiederverwendbar (Analyse: keine Hooks/REST/Shortcodes, feste Rollen-/
`ary_change_requests`-Bindung, feste Preis-Pauschale) → im Satelliten nachgebaut, frei wählbarer Tokenwert.
Eingabemaske in Workboard-Optik (Frontend+Backend) folgt alpha.60. Ledger-Muster gespiegelt von IntelligenceWorld\EventLog.

## 0.1.0-alpha.58 – Intelligence World: Nutzungs-/Kostenprotokoll

**Neu:** `src/IntelligenceWorld/ProtocolBuilder.php` (reine `build()`: Sitzung + Events + vorab berechnetes
Billing → strukturiertes Protokoll; privater `hms()`). `assets/js/liw-intelligence-world.js` – `get()`-Helper
(REST-GET) + `escHtml()` + `renderProtocol()` (baut Protokoll-HTML, JSON-Blob-Download, `window.print()`);
End-Handler ruft nach `session/end` `session/protocol` und rendert. `assets/css/liw-intelligence-world.css` –
Protokoll-Stile + `@media print` (nur `.liw-iw__protocol` sichtbar, hell).

**Geändert:** `src/IntelligenceWorld/EventTypes.php` – `label()` für alle 23 Typen (additiv).
`src/IntelligenceWorld/Rest.php` – Route `GET /session/protocol`, Handler `protocol()`, `build_protocol()`
(nutzt `SessionService::get` + `EventLog::chain_for`/`verify_chain` + `billing_status`). `WorldView.php` –
i18n-Strings fürs Protokoll in `wp_localize_script`. Tests/Selftest ergänzt. Bump alpha.57 → alpha.58.

**Layering:** Kostenrechnung bleibt in `Rest::billing_status` (SSOT); ProtocolBuilder bekommt das Ergebnis
hereingereicht (keine Recompute-Duplizierung, keine Domain→Transport-Abhängigkeit).

## 0.1.0-alpha.57 – Simulation World: Startseite + Bild-Slots gefüllt

**Neu:** `scripts/liw-seed-simulator.php` (idempotenter Seeder: Seite `/liebherr-simulator/` mit
`[liw_simulator]`, Vollbild-Vorlage `PageTemplate::TEMPLATE`, ID in Option `liw_simulator_page_id`).
`scripts/liw-selftest.php` – Prüfung ergänzt (bei gesetzter `liw_simulator_page_id`: Seite publish +
Vollbild-Vorlage + enthält `[liw_simulator]`). Bump alpha.56 → alpha.57.

**Konfiguration (Dev-DB, KEINE Code-/Repo-Änderung):** Optionen gesetzt – `liw_world_connections_image_id`
= Attachment `Liebherr_Solutions_ON-BOARD-VIEW_001`, `liw_simulator_image_id` = `Liebherr_Cockpit_001`; beide
Attachments per `_liw_media_approved=1` freigegeben (sonst greifen `WorldMapView::connections_image_url()` /
`SimulatorView::image_url()` nicht). Diese Werte müssen auf Staging/Live neu gesetzt werden (Medienimport).

## 0.1.0-alpha.56 – Intelligence World: Simulation Builder

**Neu:** `src/IntelligenceWorld/SimulationModel.php` (reine Engine: `forecast()`, `scenarios()`,
`scenario_label()`, `sample_baseline()`, Konstante `HORIZONS`; ganzzahlig, deterministisch).
`src/IntelligenceWorld/SimulationView.php` (Shortcode `[liw_iw_simulation]` + `render_section()`/
`render_forecast()`/`bars_svg()`; Formular + serverseitiger Default-Forecast, eigener JS-Cache-Buster
`bust()` für die Simulations-JS). `assets/js/liw-iw-simulation.js` (spiegelt `SimulationModel::forecast()`
1:1, rechnet live, baut Ausgabe im DOM neu). `assets/css/liw-intelligence-world.css` – Simulations-Stile
ergänzt (`.liw-iw__sim*`).

**Geändert:** `src/IntelligenceWorld/WorldView.php` – Hub-Kachel „Simulation Builder" live (Anker
`#liw-iw-simulation`, `enabled=true`); `SimulationView::render_section()` nach der Navigation eingebettet;
Demo-Hinweistext aktualisiert. `src/Bootstrap.php` – `IntelligenceWorld\SimulationView::register()`.
`tests/run-tests.php` + `scripts/liw-selftest.php` – Prüfungen ergänzt. Bump alpha.55 → alpha.56.

**Design:** `<details>` für die Wertetabelle (barrierefrei); SVG-Balken rein dekorativ, Tabelle ist die
zugängliche Alternative. Formel bewusst in PHP (SSOT, testbar) und JS gespiegelt – bei Änderungen BEIDE anpassen.

## 0.1.0-alpha.55 – Intelligence World: Preismodell (Sekundentakt/Monat/TB)

**Geändert:** `src/IntelligenceWorld/WorldContent.php` – Pricing-Defaults auf `price_unit=second`,
`base_price_second_minor=9`, `budget_period=month`, `session_budget_minor=500000`, `storage_budget_mb=1048576`,
`storage_is_minimum=true`; `sanitize()` um die neuen Felder erweitert (Whitelists für unit/period, bool für
is_minimum); neue reine Helfer `unit_label()`, `period_label()`, `storage_label()` (MB→GB→TB, 1024er).
`src/IntelligenceWorld/Rest.php` – `billing_status($active, $price_second_minor, $budget)` rechnet pro Sekunde
(`base = active × price`); `status_for()` nutzt `base_price_second_minor` + `budget_display` mit Periode;
`public_config()` liefert `price_second`, `price_display` „… / Sek.", `budget_display` „… / Monat",
`storage_display`. `src/IntelligenceWorld/WorldView.php` – Anzeige-Strings (Preis/Budget/Speicher) über die
neuen Helfer; localize-`config` gibt `price_second`. `assets/js/liw-intelligence-world.js` – `priceSec` statt
`priceMin`, `base = active * priceSec` (kein `/60` mehr). Tests/Selftest angepasst. Bump alpha.54 → alpha.55.

**Hintergrund:** Auftraggeber-Vorgabe „0,09 EUR/Sek. · 5.000,00 EUR/Monat · 1 TB min." für die IW-Eröffnungsseite.

## 0.1.0-alpha.54 – Intelligence World: Navigation & Hotels

**Neu:** `src/IntelligenceWorld/CatalogContent.php` (Option `liw_iw_catalog`; `defaults()` = 13 Produktsegmente
+ Lösungswelt + 6 Hotels, je mit Drei-Wörter-Ort; `sanitize()`/`sanitize_nodes()`/`sanitize_three_words()`;
Zugriffshelfer `segments()/solution_world()/hotels()`). `src/IntelligenceWorld/NavigationView.php` (Shortcode
`[liw_iw_navigation]` + `render_sections()`; Knoten als `<li><details>` – kein JS, barrierefrei; `node_li()`
escaped alle dynamischen Werte).

**Geändert:** `src/IntelligenceWorld/WorldView.php` – Hub-Kacheln „Produktsegmente & Lösungswelt" und
„Hotelwelt" von Platzhalter (`enabled=false`) auf Anker (`#liw-iw-segments` / `#liw-iw-hotels`, `enabled=true`)
umgestellt; nach der Hub-Liste `NavigationView::render_sections()` eingebettet; Demo-Hinweistext angepasst.
`src/Bootstrap.php` – `IntelligenceWorld\NavigationView::register()` ergänzt. `assets/css/liw-intelligence-world.css`
– Navigations-/Knoten-Stile ergänzt (`.liw-iw__nav*`, `.liw-iw__grid`, `.liw-iw__node*`); **Blink-Falle
behoben:** `<summary>` bleibt `display:block` (Flex-Layout in innerem `.liw-iw__node-sum-row`), sonst kippt das
native `<details>`-Toggle. `tests/run-tests.php` + `scripts/liw-selftest.php` – Prüfungen ergänzt.
Versions-Bump alpha.53 → alpha.54 (`liebherr-interface-world.php`).

**Falle (dokumentiert):** Ein `<summary>` mit `display:flex` verliert in Blink das Auf-/Zuklappen; die
Substring-Zählung `class="liw-iw__node` trifft auch Kindklassen → im Test auf `<li class="liw-iw__node`
eingegrenzt.

## 0.1.0-alpha.53 – Simulation-World-Startbildschirm + what3words-Provider

**Neu:** `src/Frontend/SimulatorView.php` (Shortcode `[liw_simulator]`: Cockpit-Startbild + „Start your
journey"-CTA; Bild droppable/Media-Board, Ziel via Option/Filter). `src/Adventures/Location/What3WordsProvider.php`
(echter w3w-Provider hinter ProviderInterface: convert-to-3wa/convert-to-coordinates, `language=en` fix,
API-Key aus Konstante LIW_W3W_API_KEY/Option `liw_w3w_api_key`, kein Key im Repo).
**Geändert:** `src/Adventures/Location/LocationService.php` (Provider = what3words wenn konfiguriert, sonst
Mock; encode/decode mit try/catch-Fallback auf Mock), `src/Bootstrap.php` (SimulatorView register),
`src/Frontend/FrontendAssets.php` (+`liw_simulator`/`liw_world_switcher` → CSS-Enqueue),
`src/Frontend/RocketCompat.php` (`.liw-sim`), `assets/css/liebherr-frontend.css` (`.liw-sim*`),
`scripts/liw-selftest.php` (+SIM/W3W), `CHANGELOG.md`, `docs/ADVENTURES_NOTES.md`, Version alpha.53.
**Grund:** Nutzervorgabe – Cockpit als Startbild „Start your journey"; Ortsdienst = what3words (Englisch,
keine Umschaltung), bestehende Drei-Wörter-Logik beibehalten.
**Sicherheit:** kein API-Key im Repo (§22.15); ohne Key/bei Fehler Mock-Fallback (Erfassung blockiert nie).
**Prüfung:** `php -l`; run-tests 311/311, liw-selftest 285/285.

## 0.1.0-alpha.52 – Cross-Navigation der vier Plattformen (World-Switcher)

**Neu:** `src/Frontend/WorldSwitcher.php` – Shortcode `[liw_world_switcher]` + `the_content`-Auto-Einfügung
(nur Hauptabfrage der vier Insel-Seiten); `worlds()` löst Ziele über SitePages + Optionen (liw_iw_page_id/
liw_adventures_page_id) auf, `current_key()` markiert die aktuelle. `assets/css/liebherr-frontend.css` –
`.liw-switcher*`-Leiste.
**Geändert:** `src/Bootstrap.php` (register), `src/Frontend/RocketCompat.php` (`.liw-switcher`),
`src/IntelligenceWorld/WorldView.php` (Hub: Adventures als Live-Kachel), `src/Admin/AdminMenu.php`
(Frontpage-Link „📸 Adventures" + `adv_url()` + Reihenfolge), `scripts/liw-selftest.php` (+2 Cross-Nav),
`CHANGELOG.md`, Version alpha.52.
**Grund:** Nutzerwunsch – die vier Logik-Plattformen im Menü gegenseitig verlinken.
**Prüfung:** `php -l`; run-tests 307/307, liw-selftest 279/279; Browser: Switcher-Leiste + Markierung ok.

## 0.1.0-alpha.51 – Liebherr Adventures (vierte Insel): Visible-Adventures-MVP

**Neu (`src/Adventures/`):** `Taxonomy.php` (13 Inhaltstypen + 4 Dringlichkeiten, rein), `Location/ProviderInterface.php`,
`Location/MockProvider.php` (invertierbar, 64 Wörter, 0,5°-Raster), `Location/LocationService.php` (Resolver +
Partner-Attribution), `AdventureCpt.php` (CPT `liw_adventure` + Meta), `Policy.php` (can_create/effective_status/
can_view/can_moderate; Critical nie auto-öffentlich §22.5), `AdventureService.php` (create/query/to_view,
Ortspräzision je Schutzstufe), `Rest.php` (`liw-adv/v1` stream/locate/create), `AdventuresView.php` (Shortcode
`[liw_adventures]`, eigener Cache-Buster). `assets/css/liw-adventures.css`, `assets/js/liw-adventures.js`,
`scripts/liw-seed-adventures.php`, `docs/ADVENTURES_NOTES.md`.
**Geändert:** `src/Bootstrap.php` (CPT-init + Rest/View register), `src/Frontend/RocketCompat.php` (`.liw-adv`),
`src/Frontend/WorldMapView.php` + `assets/css/liebherr-frontend.css` (World-Connections-Foto-Visual, falls
Datei/Media-Bild vorhanden – Nebenfix zur Nutzeranfrage), `tests/run-tests.php` (+Adventures-Unit-Tests),
`scripts/liw-selftest.php` (+Block [8e]), `CHANGELOG.md`, Version alpha.51.
**Entscheidungen:** Modul-in-Plugin (keine Redundanz), sichtbares MVP zuerst; Drei-Wörter-Anbieter = Mock
(austauschbar, kein hart verdrahteter Name, §4.1). Prototyp §23.3.
**Prüfung:** `php -l`; run-tests 305/305, liw-selftest 277/277; Browser: Insel + 6 Demo-Adventures, kritischer
Beitrag ausgeblendet.

## 0.1.0-alpha.50 – Intelligence World: Funktions-Hub nach dem Eintritt

**Geändert:** `src/IntelligenceWorld/WorldView.php` – World-Body zeigt nach dem Eintritt einen Kachel-Hub
(neue `hub_tiles()`: Live-Kacheln Local Intelligence + Interface Solutions über `SitePages`, „in Vorbereitung"
für Produktsegmente/Hotels/Simulation Builder; Filter `liw_iw_hub_tiles`); zusätzlich filemtime-Cache-Buster
`bust()` für die eigenen IW-Assets (Umgebung strippt `?ver`). `assets/css/liw-intelligence-world.css` –
`.liw-iw__hub`/`.liw-iw__tile*`-Regeln. `scripts/liw-selftest.php` (+Hub-Prüfung), `CHANGELOG.md`,
`docs/LOGBUCH_TECHNIK.md`, `docs/LIW_TODO.md`, `src/Admin/Pages/HandbookPage.php`, Version alpha.50.
**Grund:** Nutzerwunsch – nach dem Eintritt in die Intelligence World auf die bereits gebauten
Funktionalitäten „umschalten".
**Falle:** IW-CSS ohne `?ver` → Hub-Kacheln erschienen als Aufzählung; Cache-Buster behob es (wie Haupt-CSS).
**Prüfung:** `php -l`; run-tests 276/276, liw-selftest 269/269; Browser: Hub gestylt, Live-Links ok.

## 0.1.0-alpha.49 – IW-Fixes: Code-Prüfung, Pflichtfeld-Sternchen, Favicon

**Geändert:** `src/IntelligenceWorld/Rest.php` – `routes()` nutzt `__return_true` statt Nonce-Zwang
(`check_nonce` entfällt als Gate; Grund: WP-Rocket-Full-Page-Cache backt Nonce ein → veraltet → Code galt
fälschlich als ungültig; Prototyp §21, Access-Code ist das Tor). `src/IntelligenceWorld/WorldView.php` –
Pflichtfeld-Legende „Mit * markierte Felder sind Pflichtfelder." + `.liw-iw__req`-Sternchen an Code und beiden
Einwilligungen (Consents `required`). `assets/css/liw-intelligence-world.css` – `.liw-iw__required-note`/`.liw-iw__req`.
**Neu:** `assets/img/liw-planet-icon.svg` (kontrastreiches Gold-Planet-Favicon), `src/Frontend/FaviconService.php`
(gibt Favicon-Links site-weit in wp_head/admin_head/login_head aus; SVG primär, optionale PNGs bevorzugt;
Filter `liw_favicon_enabled`), registriert in `src/Bootstrap.php`.
**Tests:** `scripts/liw-selftest.php` (+Access-Gate akzeptiert/weist ab, +Favicon-Link, +Pflichtfeld-Legende),
`tests/run-tests.php` (+Favicon-SVG wohlgeformt). `src/Admin/Pages/HandbookPage.php`, `CHANGELOG.md`,
`docs/LOGBUCH_TECHNIK.md`, `docs/LIW_TODO.md`, Version alpha.49.
**Prüfung:** `php -l`; run-tests 276/276, liw-selftest 268/268; Browser: Eintritt mit korrektem Code ok.

## 0.1.0-alpha.48 – Intelligence World: Eintritt & Welt (Blue Planet, Access Gate, Sitzungsleiste)

**Neu (`src/IntelligenceWorld/`):** `WorldContent.php` (Option liw_iw_world: Landing/Gate-Texte + Prototyp-
Tarife/Budgets + Demo-Code; defaults/sanitize/get/save rein), `Rest.php` (REST liw-iw/v1 start/heartbeat/
end/status, Nonce X-LIW-Nonce; reine billing_status()/format_duration()), `WorldView.php` (Shortcode
[liw_intelligence_world]: Planet-SVG-Hero + Eintrittsschleuse + Sitzungsleiste; Terms via Wiederverwendung
IntroOverlay::terms_html(LocalIntelligenceContent::default_terms())).
`assets/css/liw-intelligence-world.css`, `assets/js/liw-intelligence-world.js` (Gate→REST-Start→Ticker/
Heartbeat→Ende, Budget-Warnungen 50/80/100 %). `scripts/liw-seed-intelligence-world.php`.
**Geändert:** `src/Bootstrap.php` (Rest+WorldView register), `src/Frontend/RocketCompat.php` (.liw-iw),
`src/Admin/AdminMenu.php` (Frontpage-Link „🪐 Intelligence World" + iw_url()), `tests/run-tests.php` (+Phase-2),
`scripts/liw-selftest.php` (+Block [8d]), `docs/IMPLEMENTATION_NOTES.md`, `CHANGELOG.md`,
`docs/LOGBUCH_TECHNIK.md`, `docs/LIW_TODO.md`, `src/Admin/Pages/HandbookPage.php`, Version alpha.48.
**Entscheidungen (Prototyp-Defaults, administrierbar):** Access-Code = Demo-Code `LIEBHERR-DEMO` (kein echtes
Login, §21); Demo-Tarife 2,50 €/min, Sitzungsbudget 50 €, Speicher 5 MB.
**Bug beim Bau:** Seeder-Echo mit ASCII-`"` in dt. Anführungszeichen (Parse-Fehler) → auf „…“ korrigiert.
**Prüfung:** `php -l`; run-tests 273/273, liw-selftest 264/264; Browser end-to-end (Start/Ticker/Ende) ok.

## 0.1.0-alpha.47 – Intelligence World: Fundament (Datenmodell, Ereignis-Ledger, Session-Meter)

**Neu (`src/IntelligenceWorld/`):** `Schema.php` (Tabellen liw_iw_session + liw_iw_event, dbDelta,
COMMENT ohne Klammern), `EventTypes.php` (23 Typen §11), `Money.php` (Integer-Minor-Units, rein),
`PriceRule.php` (10 Tarifarten, cost_minor, select_active/Gültigkeit, rein), `SessionMeter.php`
(aktive Sekunden aus Heartbeats+Timeout, rein/konservativ), `EventLog.php` (append + Hash-Kette +
Idempotenz via dedupe_key + verify_chain; reine canonical()/hash()), `SessionService.php`
(Start/Heartbeat/Pause/Resume/Ende + sweep_timeouts, UTC, Option liw_iw_timeout).
`docs/IMPLEMENTATION_NOTES.md` (§22.8).
**Geändert:** `liebherr-interface-world.php` – `create_tables()` ruft `IntelligenceWorld\Schema::create_tables()`;
Version alpha.47. `tests/run-tests.php` (+Block „Intelligence World Fundament", wp_json_encode-Stub),
`scripts/liw-selftest.php` (+Block [8c] DB-Lebenszyklus), `CHANGELOG.md`, `docs/LOGBUCH_TECHNIK.md`.
**Grund:** Start Pflichtenheft-2, Entscheidung „Fundament zuerst": serverseitige Abrechnungswahrheit vor UI.
**Prüfung:** `php -l` (alle); run-tests 262/262, liw-selftest 257/257 (aktive Sekunden=100, Hash-Kette,
Idempotenz, Manipulation erkannt).

## 0.1.0-alpha.46 – Erweiterte Nutzungsbedingungen (5.1–5.8) im Intro-Fenster

**Geändert:** `src/Settings/LocalIntelligenceContent.php` – neue `default_terms()` (Abschnitte 5.1–5.8 als
schlichtes Markup, Single-Quote-Zeilen), `intro.terms_body` nutzt sie; `terms_heading` = „Erweiterte
Nutzungsbedingungen …". `src/Frontend/IntroOverlay.php` – neue `terms_html()` (Markup→HTML: `## `→h4,
`- `→ul/li, `\d+. `→ol/li, sonst p; jedes Segment `esc_html()`); Terms-Panel rendert darüber statt `wpautop`.
`assets/css/liebherr-frontend.css` – Styles für `.liw-intro__terms-section`/Listen, Panelhöhe 46vh.
`scripts/liw-selftest.php` (+2), `tests/run-tests.php` (+1), `CHANGELOG.md`, `liebherr-interface-world.php`
(alpha.46).
**Grund:** Vorgabe des vollständigen Nutzungsbedingungstextes (Prototyp/Vertraulichkeit, lokale Speicherung,
kostenpflichtige Nutzung, Gebühren, Kostenprotokoll, Verantwortlichkeit, Sicherheit, Freigabevorbehalt) im
Anfangs-Fenster. Markup statt HTML in der Option, damit `sanitize_textarea_field` nichts entfernt und keine
rohen Tags gespeichert werden (Sicherheit).
**Prüfung:** `php -l`; run-tests 230/230, liw-selftest 250/250; real geprüft (formatiert + scrollbar).

## 0.1.0-alpha.45 – Intro-Overlay „Sternenregen" + Eintritts-Fenster (Rechen-Gate)

**Neu:** `src/Frontend/IntroOverlay.php` – Shortcode `[liw_intro]`: Overlay (role=dialog, ohne JS `hidden`),
Sternenhimmel-Container, Eintritts-Fenster mit aufklappbaren Nutzungsbedingungen und Rechen-Gate
(zwei zweistellige Zahlen via `wp_rand`, Summe in `data-liw-sum`).
**Geändert:** `src/Settings/LocalIntelligenceContent.php` – neuer `intro`-Zweig (defaults + sanitize).
`src/Admin/Pages/LocalIntelligenceBoardPage.php` – Board-Gruppe „Intro-Overlay".
`src/Bootstrap.php` (Registrierung), `src/Frontend/FrontendAssets.php` (Shortcode als Enqueue-Auslöser),
`src/Frontend/RocketCompat.php` (`.liw-intro` Safelist).
`assets/js/liebherr-frontend.js` – Intro-IIFE: spawnt kreuz-und-quer fliegende Logos (zufällige Start-/
Zielpunkte in vw/vh + Skalierung `--s1`/`--s2`, manche schrumpfen sternenklein) **und** funkelnde Sterne;
Terms-Toggle, Rechen-Gate; mehrphasiger Soft-In in `dismiss()` (Phase 1 `is-revealing` = Dunkel aus/Fenster
weg/Scroll frei, Phase 2 `is-clearing` = Sterne aus nach ~2,6 s, Phase 3 Entfernen nach ~5,4 s);
sessionStorage `liwIntroDone`, Screenreader-Geschwister ausblenden, reduced-motion.
`assets/css/liebherr-frontend.css` – `.liw-intro*` (eigene `__backdrop`-Ebene für das Dunkel, `__sky` für
Sterne/Logos; Keyframes `liw-intro-fly` [Translate+Scale+Rotate] & `liw-intro-twinkle`; z-index über globalen
Widgets; `is-revealing`/`is-clearing`-Phasen mit weichen Opazitäts-Transitions; reduced-motion). `scripts/liw-seed-local-intelligence.php` – `[liw_intro]` an den
Seitenanfang. `scripts/liw-selftest.php` (+7), `tests/run-tests.php` (+1), `CHANGELOG.md`,
`liebherr-interface-world.php` (alpha.45).
**Grund:** Nutzerwunsch – cineastischer ~7-s-Einstieg (Sternenregen mit Liebherr-Logos, Aufblenden aus dem
Dunkel) + wegklickbares Fenster mit Nutzungsbedingungen und einer Rechenaufgabe („ohne Code") als Eintritt.
**Falle:** globaler Sprachumschalter lag über dem Overlay → `.liw-intro` z-index auf 2147483000 angehoben.
**Prüfung:** `php -l`; run-tests 229/229, liw-selftest 248/248; real end-to-end getestet (Gate akzeptiert
korrekte Summe, Seite blendet auf).

## 0.1.0-alpha.44 – Obere Menüleiste (LI) repariert + Logo-Fix

**Geändert:** `src/Frontend/HeaderView.php` – `render()` nimmt jetzt `nav`-Attribut; neue `li_nav_config()`
(LI-Menüsatz: `#li-*`-Anker + Interface-Solutions-URL + LI-CTAs); `brand_markup()` gibt SVG-Logos als
direkte `<img src>`-URL aus (kein `wp_get_attachment_image()` mehr → keine `width="1" height="1"`).
`assets/css/liebherr-frontend.css` – `.liw-header__logo` mit expliziter `height:32px` (SVG-viewBox skaliert
die Breite). `scripts/liw-seed-local-intelligence.php` – LI-Seite nutzt `[liw_header nav="li"]`.
`scripts/liw-selftest.php` (+3 Header-Prüfungen), `CHANGELOG.md`, `liebherr-interface-world.php` (alpha.44).
**Grund:** Nutzerbefund „Doppelmenüstruktur – obere Menüleiste funktioniert nicht": der geteilte Header hatte
nur den globalen `#lp-*`-Menüsatz (existiert nur auf der Interface-Solutions-Seite) → auf der LI-Hauptseite
tote Links. Zusätzlich Logo als 1×1 px (SVG ohne Maße).
**Prüfung:** `php -l`; run-tests 226/226, liw-selftest 241/241; reale Seite JS-geprüft (alle Header-Links
treffen echte Ziele, Logo 258×32).

## 0.1.0-alpha.43 – Hero-Visual (Knotennetz, Modul 1) + A11y-Prüfungen

**Geändert:** `src/Frontend/LocalIntelligenceView.php` – neue `hero_network_svg()` (dekoratives Inline-SVG:
Hub + 7 lokale Knoten + 2 Datenraum-Ringe + gestrichelte Verbindungen, `aria-hidden`), in `render_hero()`
in die `.liw-li__hero-bg`-Ebene eingehängt. `assets/css/liebherr-frontend.css` – `.liw-li__net*`-Regeln
(tokenbasiert, Puls nur bei `prefers-reduced-motion: no-preference`). `scripts/liw-selftest.php` (+2:
Hero-Visual vorhanden, Composite hat genau eine H1). `CHANGELOG.md`, `liebherr-interface-world.php` (alpha.43).
**Grund:** LI §8 Modul 1 verlangt eine abstrakte räumliche Darstellung (lokale Knoten + zentrale Wissensquelle);
bisher war der Hero nur ein Farbverlauf. Zugleich A11y-Struktur (eine H1, Landmarken) automatisiert abgesichert.
**Prüfung:** `php -l`; run-tests 226/226, liw-selftest 238/238; Hero visuell bestätigt.

## 0.1.0-alpha.42 – Prototyp-SEO (noindex bis Freigabe) + Abnahme-Lieferliste

**Geändert:** `src/CoreBridge/SeoBridge.php` – `CARRIER_SHORTCODES` (jetzt auch `liw_local_intelligence`),
`has_carrier_shortcode()`-Helfer; `render_robots()` + `indexing_allowed()` (Option `liw_public_release` /
Filter `liw_allow_indexing`) → `noindex,follow` auf LIW-Flächen bis Freigabe; `filter_sitemap_page_args()`
schließt Haupt-/Interface-Seite aus der `page`-Sitemap aus (`wp_sitemaps_posts_query_args`).
`is_liw_post()`/`is_liw_public_view()` nutzen den Träger-Helfer (OG/hreflang/Canonical greifen jetzt auf der
LI-Hauptseite). `scripts/liw-selftest.php` (+4 SEO-Prüfungen), `tests/run-tests.php` (+2 Wächter),
`docs/LIW_ABNAHME.md` (§8 Vorher-Nachher-Liste), `CHANGELOG.md`, `liebherr-interface-world.php` (alpha.42).
**Grund:** Die neue Hauptseite nutzt `[liw_local_intelligence]`, fiel damit aus der bestehenden SEO-Logik
(nur `[liw_landingpage]`) heraus; zudem verlangt das LI-Pflichtenheft (§9.2/§12.7), nicht freigegebene
Prototypen nicht zu indexieren.
**Prüfung:** `php -l`; `tests/run-tests.php` 226/226, `scripts/liw-selftest.php` 236/236; reale Seiten (noindex,
OG-Titel, Sitemap-Ausschluss) bestätigt.

## 0.1.0-alpha.41 – Hauptseite „Liebherr Local Intelligence" (11 Module) + Verschachtelung

**Neu:**
- `src/Settings/LocalIntelligenceContent.php` – administrierbares Content-Modell (Option `liw_local_intelligence`),
  `defaults()`/`get()`/`save()`/`sanitize()` (tiefe Bereinigung gegen Standard), Standardtexte via `__()`.
- `src/Frontend/LocalIntelligenceView.php` – Composite `[liw_local_intelligence]` + 11 Modul-Shortcodes
  (`render_hero/vision/flow/simulation/knowledge/trust/global/usecases/bridge/rollout/contact`) +
  `[liw_context_nav]` (Breadcrumb/Rücklink). Rekursionsschutz, `head()`/`interface_href()`-Helfer.
- `src/Frontend/LegacyRedirect.php` – 301 der Altroute `/interface-world/` auf die Unterseite (`template_redirect`).
- `src/Content/SitePages.php` – Seiten-Registry (Optionen `liw_li_page_id`/`liw_interface_page_id`,
  Resolver mit Slug-/Pfad-Fallback, `li_url()`/`interface_url()`).
- `src/Admin/Pages/LocalIntelligenceBoardPage.php` – Pflege-Board (Text/Textarea/Zeilenlisten, Nonce, Audit).
- `scripts/liw-seed-local-intelligence.php` – idempotenter Seeder (Hauptseite anlegen, Interface-Seite
  verschachteln + Slug `interface-solutions`, Kontextnavigation einhängen, `flush_rewrite_rules`).

**Geändert:**
- `src/Bootstrap.php` – `LocalIntelligenceView::register()` + `LegacyRedirect::register()`.
- `src/Frontend/FrontendAssets.php` – LI-Shortcodes in `SHORTCODES` (CSS/JS-Enqueue-Auslöser).
- `src/Frontend/RocketCompat.php` – `.liw-li` in die RUCSS-Safelist.
- `src/Admin/AdminMenu.php` – zwei Frontpage-Direktlinks (Local Intelligence + Interface Solutions) über
  `SitePages`, LI-Board-Untermenü, `move_first`-Ordnung; altes `front_url()` entfernt.
- `assets/js/liebherr-frontend.js` – zwei IIFEs: Szenario-Schalter (`[data-liw-sim]`, Tabs A/B/C, Pfeiltasten,
  **Init-Zustand `show(active)`**) + Einsatzfeld-Filter (`[data-liw-usecase-filter]`).
- `assets/css/liebherr-frontend.css` – `.liw-li*`-Block (mobile-first, `--brand-*`, reduzierte Bewegung,
  Sekundär-CTA hell auf dunklem Grund).
- `scripts/liw-seed-demo-landing.php` – verschachtelungssicher (Interface-Seite über Registry, Slug/Parent
  unangetastet), speichert Interface-Seiten-ID.
- `scripts/liw-selftest.php` (+27, Block [8b]), `tests/run-tests.php` (+12, LI-Content-Modell),
  `CHANGELOG.md`, `docs/LIW_TODO.md`, `docs/LOGBUCH_TECHNIK.md`, `src/Admin/Pages/HandbookPage.php`,
  `liebherr-interface-world.php` (Version alpha.41).

**Zwei Bugs beim Bau gefixt:** (1) Board-Name-Templates ohne schließende `]`; (2) Szenario-JS setzte die
`--enhanced`-Klasse, rief aber `show()` initial nie auf → alle Panels sichtbar (Init-Aufruf ergänzt).
**Falle:** WP Rocket cachte das gerenderte HTML (Reihenfolge/CI alt) → `rocket_clean_domain()` nötig.
**Prüfung:** `php -l` (alle); `tests/run-tests.php` 224/224, `scripts/liw-selftest.php` 232/232; echte Seiten
(Haupt 200, Alt→301, Unter 200) + visuell (Szenario-Schalter, Filter) bestätigt.

## 0.1.0-alpha.40 – Menü „Frontpage-Ansicht" + Landingpage-Layout (Gutter/CI)

**Geändert:** `src/Admin/AdminMenu.php` (`front_url()`, `move_first()`, Untermenü „🌐 Frontpage-Ansicht"
als erster Eintrag – Direktlink zur Trägerseite), `assets/css/liebherr-frontend.css` (Landingpage-
Abschnitte + Sprungleiste zentriert mit 4vw-Gutter; Titel/Nav/Border auf `--font-heading`/`--brand-*`),
`scripts/liw-selftest.php` (+2), `CHANGELOG.md`, `docs/LIW_TODO.md`, `docs/LOGBUCH_TECHNIK.md`,
`liebherr-interface-world.php` (Version alpha.40).
**Grund:** Der Text der Landingpage-Abschnitte klebte in der Vollbild-Vorlage am linken Rand (kein
Theme-Wrapper mehr) → eigener zentrierter Content-Bereich.
**Prüfung:** `php -l`; `tests/run-tests.php` 203/203, `scripts/liw-selftest.php` 206/206; echte Seite geprüft.

## 0.1.0-alpha.39 – Wortmarke-Token + Vollbild-Seitenvorlage

**Neu:** `src/Frontend/PageTemplate.php` + `templates/full-width.php` (Vorlage „Interface World –
Vollbild": nur Inhalt + wp_head/wp_footer, kein Theme-Kopf/-Fuß; via `theme_page_templates` +
`template_include`).
**Geändert:** `src/Branding/BrandTokens.php` (`brand_text`-Token + `brand_text()`),
`src/Frontend/HeaderView.php` (Wortmarke/Alt via brand_text), `src/Frontend/FooterView.php` (Wortmarke
via brand_text), `src/Admin/Pages/BrandBoardPage.php` (Feld Wortmarke), `src/Bootstrap.php`
(PageTemplate registriert), `scripts/liw-apply-liebherr-ci.php` (brand_text = „Liebherr Interface
Solutions"), `scripts/liw-seed-demo-landing.php` (Vollbild-Vorlage der Trägerseite zugewiesen),
`tests/run-tests.php` (+1, sanitize_text_field-Stub), `scripts/liw-selftest.php` (+4), `CHANGELOG.md`,
`docs/LIW_TODO.md`, `docs/LOGBUCH_TECHNIK.md`, `src/Admin/Pages/HandbookPage.php`,
`liebherr-interface-world.php` (Version alpha.39).
**Prüfung:** `php -l`; `tests/run-tests.php` 203/203, `scripts/liw-selftest.php` 204/204; echte Seite
`/interface-world/` bestätigt (kein Theme-Chrome mehr, Wortmarke „Liebherr Interface Solutions").

## 0.1.0-alpha.38 – Demo-Landingpage + LP-14 Footer + Cache-Buster

**Neu:** `src/Frontend/FooterView.php` (`[liw_footer]`, schwarze Legal-Leiste, Filter
`liw_footer_legal_links`, GoHeal-Hinweis), `src/Frontend/RocketCompat.php` (RUCSS-Safelist `.liw-`),
`scripts/liw-seed-demo-landing.php` (Abschnitte bestücken/veröffentlichen, Platzhalter auf Entwurf,
Trägerseite `/interface-world/`).
**Geändert:** `src/Frontend/FrontendAssets.php` (`bust_src`/`asset_version` filemtime-Cache-Buster,
FooterView als Auslöser), `src/Bootstrap.php` (FooterView + RocketCompat registriert),
`assets/css/liebherr-frontend.css` (Footer), `scripts/liw-selftest.php` (Footer/Cache/Safelist-Checks),
`CHANGELOG.md`, `docs/LIW_TODO.md`, `docs/LOGBUCH_TECHNIK.md`, `src/Admin/Pages/HandbookPage.php`,
`liebherr-interface-world.php` (Version alpha.38).
**Befund (WP-Rocket-Falle):** Weltkarte auf der echten Seite ungestylt – Ursache war NICHT RUCSS
(war aus), sondern eine browserseitig gecachte CSS ohne `?ver` (Umgebung entfernt Query-Strings).
Fix: filemtime-`?v=` spät im `style_loader_src`/`script_loader_src` anhängen. RUCSS-Safelist zusätzlich
als Schutz für Produktion.
**Prüfung:** `php -l`; `tests/run-tests.php` 200/200, `scripts/liw-selftest.php` 200/200; echte Seite
`/interface-world/` visuell bestätigt.

## 0.1.0-alpha.37 – Optik: Liebherr-CI angewendet + Connected-World-Weltkarte

**Neu:** `src/Frontend/WorldMapView.php` (`[liw_world_map]`, abstrahierte SVG-Weltkarte, reine
`canonical_region()`), `src/Frontend/FontFaceService.php` (@font-face freigegebener Fonts),
`scripts/liw-apply-liebherr-ci.php` (Freigabe + Brand-Tokens + Hero, autorisiert JW, idempotent).
**Geändert:** `src/Branding/BrandTokens.php` (Token `on_primary`/`--brand-on-primary`),
`src/Admin/Pages/BrandBoardPage.php` (Feld on_primary), `src/Frontend/FrontendAssets.php`
(FontFaceService enqueued, WorldMap als Auslöser), `src/Bootstrap.php` (WorldMapView registriert),
`assets/css/liebherr-frontend.css` (Weltkarte, Hero-Keyline, CTA/Marker via --brand-on-primary),
`assets/js/liebherr-frontend.js` (Weltkarten-Highlight), `tests/run-tests.php` (+7),
`scripts/liw-selftest.php` (Optik-Checks + Logo-Regel zustandsabhängig), `CHANGELOG.md`,
`docs/LIW_TODO.md`, `docs/LOGBUCH_TECHNIK.md`, `docs/LIW_BRAND_TOKENS.md`, `docs/LIW_ABNAHME.md`,
`src/Admin/Pages/HandbookPage.php`, `liebherr-interface-world.php` (Version).
**Datenoperation (Dev, autorisiert JW):** 11 Assets `approved=1`, Brand-Tokens = echte Liebherr-Werte,
Logo #2154, Hero #2149. Vorläufig bis endgültige Freigabe.
**Prüfung:** `php -l`; `tests/run-tests.php` 196/196, `scripts/liw-selftest.php` 196/196; Optik visuell
bestätigt. Falle: durch das freigegebene Logo zeigt der Header jetzt das Bild statt der Wortmarke →
Etappe-2-Selbsttest auf zustandsabhängige Logo-Regel umgestellt.

## 0.1.0-alpha.36 – Content-Board-Restpunkte: Sichtbarkeits-Zeitfenster (§19)

**Neu:** `src/Content/SectionSchedule.php` (Meta `_liw_valid_from`/`_liw_valid_until`, reine
`is_within_window()`, `is_visible_now()`, `has_window()`), `src/Admin/SectionScheduleMetabox.php`
(Editor-Felder „Sichtbar ab/bis", Nonce + `edit_post`, Website-TZ → UTC).
**Geändert:** `src/Frontend/LandingpageView.php` (`get_published_sections()` filtert nach
`is_visible_now()`), `src/Admin/Pages/ContentBoardPage.php` (🕒-Kennzeichnung + Import),
`src/Bootstrap.php` (Metabox-Registrierung im is_admin-Block), `tests/run-tests.php` (+7),
`scripts/liw-selftest.php` ([8] +5), `CHANGELOG.md`, `docs/LIW_TODO.md`, `docs/LOGBUCH_TECHNIK.md`,
`src/Admin/Pages/HandbookPage.php`, `liebherr-interface-world.php` (Version).
**Prüfung:** `php -l`; `tests/run-tests.php` 185/185, `scripts/liw-selftest.php` 190/190
(Landingpage blendet abgelaufenen Abschnitt aus). TZ-Umrechnung über `wp_timezone()`.

## 0.1.0-alpha.35 – Content-Board-Restpunkte: Drag-&-Drop-Reihenfolge & Sprachvorschau (§19)

**Neu:** `assets/js/liw-admin-content.js` (jQuery-UI-Sortable + AJAX-Speichern).
**Geändert:** `src/Admin/Pages/ContentBoardPage.php` (`REORDER_ACTION`/`REORDER_NONCE`, `register()`
für `wp_ajax_liw_reorder_sections`, `ajax_reorder()`, testbare `apply_order()`; Tabelle mit
`data-liw-reorder`/`data-liw-id`/Ziehgriff/`liw-order-num`; Vorschau-Links je Sprache),
`src/Admin/AdminAssets.php` (Enqueue Sortable + Localize nur auf dem Content Board),
`src/Bootstrap.php` (ContentBoardPage::register im is_admin-Block), `assets/css/liebherr-admin.css`
(Ziehgriff/Busy/Placeholder), `scripts/liw-selftest.php` ([8] +4), `CHANGELOG.md`, `docs/LIW_TODO.md`,
`docs/LOGBUCH_TECHNIK.md`, `src/Admin/Pages/HandbookPage.php`, `liebherr-interface-world.php` (Version).
**Prüfung:** `php -l`; `tests/run-tests.php` 174/174, `scripts/liw-selftest.php` 185/185
(`apply_order()` mit echten Test-Abschnitten verifiziert). Kontext-Falle: `wp_ajax_`-Hook wird nur im
Admin gesetzt → Selbsttest prüft die Verdrahtung quellbasiert.

## 0.1.0-alpha.34 – Etappe 8: Qualität & Abnahme (§25–35)

**Neu:** `scripts/liw-seed-demo.php` (idempotenter DEMO-Seeder, `--confirm`; Interfaces/Connections/
Simulationswelt+Szenarien über die Services, Code-Vergleich case-insensitiv), `docs/LIW_ABNAHME.md`
(Abnahmebericht AC-001…016, Rollback, §34-Launch-Blocker).
**Geändert:** `scripts/liw-selftest.php` ([8] +3), `CHANGELOG.md`, `docs/LIW_TODO.md`,
`docs/LOGBUCH_TECHNIK.md`, `liebherr-interface-world.php` (Version alpha.34).
**Befund/Fix beim Seeder:** Interface-Codes werden vom Service kleingeschrieben gespeichert; die
Idempotenz-Prüfung muss case-insensitiv vergleichen (sonst Duplicate-Key beim zweiten Lauf) – behoben,
zweiter Lauf 0 angelegt / 7 übersprungen.
**Prüfung:** `php -l`; `tests/run-tests.php` 174/174, `scripts/liw-selftest.php` 180/180; zusammengesetzte
Landingpage visuell (Desktop/Mobil) bestätigt.

## 0.1.0-alpha.33 – Etappe 7: Sicherheit & Datenschutz (§23/§24)

**Neu:** `src/CoreBridge/RateLimitBridge.php` (Wrapper um Core-`RateLimiter::check()`, Graceful
Degradation), `src/Contact/ContactRetention.php` (Option `liw_contact_retention_days`, Cron
`liw_contact_retention_cron`, `run()`/`days()`/`set_days()`/`unschedule()`).
**Geändert:** `src/Contact/ContactForm.php` + `src/Onboarding/OnboardingForm.php` (Rate-Limit nach
Honeypot), `src/Contact/ContactService.php` (`ids_older_than()`), `src/Admin/Pages/ContactBoardPage.php`
(Retention-Formular + `set_retention`-Handler), `src/Bootstrap.php` (ContactRetention::register),
`liebherr-interface-world.php` (deactivate → ContactRetention::unschedule, Version), `tests/run-tests.php`
(+1), `scripts/liw-selftest.php` ([8] +9), `CHANGELOG.md`, `docs/LIW_TODO.md`, `docs/LOGBUCH_TECHNIK.md`,
`src/Admin/Pages/HandbookPage.php`.
**Verifiziert (bereits erfüllt):** Upload-Härtung SEC-009 in `PartnerDocumentService::upload()`
(finfo+Whitelist+Größe), Fehlermeldungs-Hygiene SEC-010. Security-Header SEC-008 = Plattform (nicht
im Modul).
**Prüfung:** `php -l`; `tests/run-tests.php` 174/174, `scripts/liw-selftest.php` 177/177.

## 0.1.0-alpha.32 – Etappe 6: SEO-Rest & Sprach-Release-Readiness (LANG-006)

**Neu:** `src/Admin/Pages/LanguageBoardPage.php` (Readiness-Lese-Ansicht, Capability
`liw_manage_content`).
**Geändert:** `src/CoreBridge/SeoBridge.php` (`filter_canonical()` via `get_canonical_url`,
`render_open_graph()`/`open_graph_markup()`/`og_image_url()`, `filter_sitemap_post_types()`,
`is_liw_post()`), `src/CoreBridge/TranslationBridge.php` (`readiness_report()`,
`public_scope_post_ids()`; `is_locale_release_ready()` auf Registry-Metriken umgestellt –
nutzt `TranslationRegistry::page_metrics()` + `PageScope::post()`), `src/Admin/AdminMenu.php`
(Untermenü „🌐 Language Board", use-Import), `scripts/liw-selftest.php` ([8] +8), `CHANGELOG.md`,
`docs/LIW_TODO.md`, `docs/LOGBUCH_TECHNIK.md`, `src/Admin/Pages/HandbookPage.php`,
`liebherr-interface-world.php` (Version).
**Prüfung:** `php -l`; `tests/run-tests.php` 169/169, `scripts/liw-selftest.php` 168/168.

## 0.1.0-alpha.31 – Etappe 5: Audit Board & Interface-Lifecycle-Status (§18)

**Neu:** `src/Admin/Pages/AuditBoardPage.php` (Lese-Ansicht, paginiert, Capability
`liw_manage_interfaces`).
**Geändert:** `src/CoreBridge/AuditBridge.php` (`recent_liw_events()`, `count_liw_events()`,
`table()`; Filter `entity_type LIKE 'liw\_%'`), `src/Admin/Pages/InterfaceBoardPage.php`
(Statuswechsel-UI: Aktion `set_lifecycle`, `STATUS_LABELS`, `render_status_form()`,
maybe_handle_submit erweitert), `src/Admin/AdminMenu.php` (Untermenü „🛡 Audit Board", use-Import),
`scripts/liw-selftest.php` ([8] +6), `CHANGELOG.md`, `docs/LIW_TODO.md`, `docs/LOGBUCH_TECHNIK.md`,
`src/Admin/Pages/HandbookPage.php`, `liebherr-interface-world.php` (Version).
**Entscheidung:** Release Board nicht eigenständig – Core-Deployment-Manager (Variante A).
**Prüfung:** `php -l`; `tests/run-tests.php` 167/167, `scripts/liw-selftest.php` 160/160.

## 0.1.0-alpha.30 – Etappe 4: §24-Export & Redaktions-Prüfung (§19)

**Neu:** `src/Contact/ContactExporter.php` (`admin_post_liw_contact_export`, Capability/Nonce/Audit,
CSV mit BOM+Semikolon, Consent-Version/Zeit je Anfrage).
**Geändert:** `src/Contact/ContactService.php` (`get_all_for_export()`), `src/Consent/ConsentLogService.php`
(`list_for_request()`), `src/Admin/Pages/ContactBoardPage.php` (`render_export()` + DSGVO-Hinweis),
`src/Admin/Pages/ContentBoardPage.php` (`render_qa()` + reine `count_images_without_alt()`),
`src/Admin/Pages/HeaderBoardPage.php` (`render_target_datalist()`, `list="liw-targets"` an CTA-Zielen),
`src/Bootstrap.php` (ContactExporter registriert), `tests/run-tests.php` (+5, `esc_attr`-Stub),
`scripts/liw-selftest.php` ([8] +5), `CHANGELOG.md`, `docs/LIW_TODO.md`, `docs/LOGBUCH_TECHNIK.md`,
`src/Admin/Pages/HandbookPage.php`, `liebherr-interface-world.php` (Version).
**Prüfung:** `php -l`; `tests/run-tests.php` 165/165, `scripts/liw-selftest.php` 154/154.

## 0.1.0-alpha.29 – Etappe 3: Datengetriebene Kern-Komponenten (LP-08/11/12)

**Neu:** `src/Settings/ComponentContent.php` (Option `liw_components`; drei Listen process/roadmap/
onboarding, `defaults()` je §8, `get()/save()/sanitize()`), `src/Frontend/ComponentViews.php`
(Shortcodes `[liw_process_worlds]`, `[liw_roadmap]`, `[liw_onboarding_steps]`),
`src/Admin/Pages/ComponentsBoardPage.php` (Components Board, „Titel | Text" je Zeile, Capability/
Nonce/Audit, `parse_lines()`).
**Geändert:** `src/Bootstrap.php` (ComponentViews registriert), `src/Admin/AdminMenu.php` (Untermenü
„🧩 Components Board", use-Import), `src/Frontend/FrontendAssets.php` (drei Shortcodes als
Asset-Auslöser), `assets/css/liebherr-frontend.css` (`.liw-pcard`/`.liw-roadmap`/`.liw-steps` über
`--brand-*`), `tests/run-tests.php` (+5, `__()`-Stub ergänzt), `scripts/liw-selftest.php` ([8] +6),
`CHANGELOG.md`, `docs/LIW_TODO.md`, `docs/LOGBUCH_TECHNIK.md`, `src/Admin/Pages/HandbookPage.php`,
`liebherr-interface-world.php` (Version).
**Prüfung:** `php -l`; `tests/run-tests.php` 158/158, `scripts/liw-selftest.php` 149/149; Rendering
der drei Komponenten visuell bestätigt (Karten-Grid, Roadmap-Kreise, Schritt-Liste).

## 0.1.0-alpha.28 – Etappe 2: Header/Navigation + Hero (LP-01)

**Neu:** `src/Settings/HeaderSettings.php` (Option `liw_header`; `defaults()`/`get()/save()/sanitize()`,
reine `normalize_target()`), `src/Frontend/HeaderView.php` (`[liw_header]`: Logo/Wortmarke, Nav, CTAs,
`LanguageBridge::switcher_html()`, Portal-Login, sticky/Off-Canvas), `src/Frontend/HeroView.php`
(`[liw_hero]`: H1/Subline aus Attributen, Netzwerk-Ebene dekorativ, CTAs, Bild nur freigegeben),
`src/Admin/Pages/HeaderBoardPage.php` (Header Board, Capability/Nonce/Audit).
**Geändert:** `src/Bootstrap.php` (HeaderView/HeroView registriert), `src/Admin/AdminMenu.php`
(Untermenü „🧭 Header Board", use-Import), `src/Frontend/FrontendAssets.php` (Header/Hero als
Asset-Auslöser), `assets/css/liebherr-frontend.css` (Header/CTA/Hero-Blöcke über `--brand-*`,
Off-Canvas, `prefers-reduced-motion`), `assets/js/liebherr-frontend.js` (Header-Toggle-IIFE),
`tests/run-tests.php` (+7 normalize_target), `scripts/liw-selftest.php` ([8] +9),
`CHANGELOG.md`, `docs/LIW_TODO.md`, `docs/LOGBUCH_TECHNIK.md`, `src/Admin/Pages/HandbookPage.php`,
`liebherr-interface-world.php` (Version).
**Prüfung:** `php -l` je Datei; `tests/run-tests.php` 147/147, `scripts/liw-selftest.php` 143/143;
Header/Hero visuell (Desktop 1280 + Mobil) über Browser-Vorschau bestätigt. PSR-4: neuer Namespace
`Settings` → `src/Settings/`.

## 0.1.0-alpha.27 – Etappe 1: CI/Brand-Fundament (Design-Tokens)

**Neu:** `src/Branding/BrandTokens.php` (Token-Service: `defaults()` neutrale Fallbacks,
`get()/save()` über Option `liw_brand_tokens`, reine `sanitize()` + `css_from()/css_root()`,
`logo_id()`), `src/Admin/Pages/BrandBoardPage.php` (Admin-Board, Capability `liw_manage_content`,
Nonce `liw_brand_board_save`, AuditBridge::log('update','brand_tokens',…), Logo-Auswahl aus
Rolle `logo`, CSS-Vorschau).
**Geändert:** `src/Admin/AdminMenu.php` (Untermenü „🎨 Brand Board" vor To-Dos, use-Import),
`src/Frontend/FrontendAssets.php` (`wp_add_inline_style(HANDLE, BrandTokens::css_root())`),
`assets/css/liebherr-frontend.css` (`:root`-Fallbacks `--brand-*`), `tests/run-tests.php`
(Abschnitt Brand Tokens, +9), `scripts/liw-selftest.php` ([8] +5), `docs/LIW_TODO.md`,
`docs/LIW_RELEASEPLAN.md` (neu, Release-Plan), `CHANGELOG.md`, `docs/LOGBUCH_TECHNIK.md`,
`src/Admin/Pages/HandbookPage.php`, `liebherr-interface-world.php` (Version).
**Prüfung:** `php -l` je Datei; `tests/run-tests.php` 132/132, `scripts/liw-selftest.php` 134/134
im Docker-Container. PSR-4: neuer Namespace `Branding` → `src/Branding/` (Autoloader base_dir).

## 0.1.0-alpha.26 (Nachtrag 18.09.2026) – Marken-Assets als CI-005-Kandidaten importiert

**Neu:** `scripts/liw-import-brand-assets.php` (idempotenter CLI-Import, `--confirm`; lädt Logo,
Webfonts und Bildmotive server-seitig von liebherr.com in die Medienbibliothek, setzt
`_liw_media_copyright`, `_liw_media_source`, `_liw_media_approved = 0`, `_liw_media_role`, Alt-Text;
temporäres `upload_mimes` für SVG/woff2 nur im Lauf; Idempotenz über Quelle-URL in `_liw_media_source`),
`docs/LIW_BRAND_TOKENS.md` (erfasste Farb-/Typo-Token als Referenz, Freigabe ausstehend).
**Geändert:** `docs/LIW_TODO.md` (CI/Branding-Punkt), `docs/LOGBUCH_TECHNIK.md`.
**Datenoperation:** Lauf im Dev-Container am 18.09.2026 → 11 Kandidaten (#2149–#2159), alle
`approved = 0`. Keine Code-/Schema-Änderung am Plugin, kein Versionssprung. Verwendung erst nach
dokumentierter Liebherr-Freigabe (CI-002/CI-005).

## 0.1.0-alpha.26 – Landingpage: aktive Anker-Hervorhebung der Sprungleiste

**Neu:** `assets/js/liebherr-frontend.js` (erstes Frontend-Skript des Plugins; IIFE, ES5,
abhängigkeitsfrei; `initNav()` bildet `a.liw-landingpage__nav-link[href^="#"]` → `#anchor`-Abschnitte
ab, ein `IntersectionObserver` mit `rootMargin: -42% 0px -53%` setzt am gerade sichtbaren Abschnitt
`aria-current="true"` + Klasse `is-current`, sonst entfernt; obersten sichtbaren in Dokumentreihenfolge
gewählt, keiner sichtbar → letzte Markierung bleibt; Guard auf `IntersectionObserver`-Verfügbarkeit
und `DOMContentLoaded`).
**Geändert:** `src/Frontend/FrontendAssets.php` (`maybe_enqueue()` enqueued zusätzlich
`assets/js/liebherr-frontend.js` unter dem Handle `liw-frontend`, im Footer, `LIW_VERSION`),
`assets/css/liebherr-frontend.css` (Regel `.liw-landingpage__nav-link.is-current`),
`src/Frontend/LandingpageView.php` (nur Kommentar von `render_nav()` aktualisiert; kein Markup-Eingriff),
`tests/run-tests.php` (Abschnitt „Aktive Anker-Hervorhebung", +7 Checks), `scripts/liw-selftest.php`
([8] +4 Checks), `docs/LIW_TODO.md`, `CHANGELOG.md`, `docs/LOGBUCH_TECHNIK.md`,
`src/Admin/Pages/HandbookPage.php`, `liebherr-interface-world.php` (Version).
**Prüfung:** `php -l` je Datei; `tests/run-tests.php` 119/119 im Docker-Container; JS-Parse und
Scrollspy-Verhalten real in der Browser-JS-Engine simuliert. Docker-`liw-selftest.php`-Lauf steht aus.

## 0.1.0-alpha.25 – Bugfix Sprachcodes

**Geändert:** `src/CoreBridge/LanguageBridge.php` (`active_langs()` verarbeitet Core-Datensätze,
Plausibilitätsmuster, Dubletten), `scripts/liw-selftest.php` ([6b] Codemuster-Prüfung),
`liebherr-interface-world.php` (Version), `CHANGELOG.md`, `docs/LOGBUCH_TECHNIK.md`.
**Fund:** Docker-Lauf alpha.24 (`SELFTEST-DFEY1SCL`, 124/125): `Array to string conversion` in
`LanguageBridge.php:57` – Core `get_active_langs()` liefert Datensätze, keine Strings.

## 0.1.0-alpha.24 – I18nSeo Option A

**Neu:** `src/CoreBridge/LanguageBridge.php` (Sprache, Umschalter-Shortcode, Router-Status, `versioned()`).
**Geändert:** `src/CoreBridge/SeoBridge.php` (Ziel Trägerseite, Router-Guard, `hreflang_markup()`),
`src/Bootstrap.php` (LanguageBridge registriert), `src/Onboarding/OnboardingService.php` und
`src/Contact/ContactService.php` (Textversion mit Sprachsuffix), `assets/css/liebherr-frontend.css`
(`.liw-language-switcher`), `src/Admin/Pages/HandbookPage.php`, `scripts/liw-selftest.php` ([6b] neu),
`docs/LIW_TODO.md`, `CHANGELOG.md`, `docs/LOGBUCH_TECHNIK.md`, `liebherr-interface-world.php` (Version).

## 0.1.0-alpha.23 – Landingpage: Sprungleiste

**Geändert:** `src/Frontend/LandingpageView.php` (`render_shortcode()` mit Attribut `nav`,
`anchor_for()`, `render_nav()`), `assets/css/liebherr-frontend.css` (`.liw-landingpage__nav*`,
`scroll-margin-top`), `src/Admin/Pages/HandbookPage.php`, `scripts/liw-selftest.php` ([6] +2),
`docs/LIW_TODO.md`, `CHANGELOG.md`, `liebherr-interface-world.php` (Version).

## 0.1.0-alpha.22 – Partnerbereich Stufe 2: geschützte Partnerdokumente

**Neu:** `src/Partner/PartnerDocumentSchema.php`, `src/Partner/PartnerDocumentService.php`,
`src/Partner/PartnerDocumentsView.php`, `src/Admin/Pages/PartnerDocumentBoardPage.php`.
**Geändert:** `src/Admin/AdminMenu.php` (Submenü), `src/Bootstrap.php` (View registriert),
`liebherr-interface-world.php` (`create_tables()` + Version), `src/Frontend/FrontendAssets.php`
(Auslöser), `src/CoreBridge/RoleBridge.php` (Admin-Cap `liw_partner_access`),
`assets/css/liebherr-frontend.css` (`.liw-partner-docs*`), `src/Admin/Pages/HandbookPage.php`
(Abschnitt 7, Nummerierung), `scripts/liw-selftest.php` ([0] Tabelle, [5c] neu, Cleanup),
`docs/LIW_TODO.md`, `docs/LIW_LANDINGPAGE_KONZEPT.md`, `CHANGELOG.md`, `docs/LOGBUCH_TECHNIK.md`.

## 0.1.0-alpha.21 – Partnerbereich Stufe 1: Rolle `liw_partner` + Kontoanlage

**Geändert:**
- `src/CoreBridge/RoleBridge.php` – `ROLE_PARTNER`, `CAP_PARTNER_ACCESS`, `ensure_partner_role()`
  (auch aus `grant_capabilities()`), Doc zur Nicht-Entfernung bei Deaktivierung.
- `liebherr-interface-world.php` – `maybe_upgrade_database()` ruft `ensure_partner_role()`; Version.
- `src/Onboarding/OnboardingSchema.php` – Spalte `wp_user_id` + Index `idx_wp_user`.
- `src/Onboarding/OnboardingService.php` – `USER_META_PARTNER_ID`, `create_partner_account()`;
  `get_all_requests()` liefert `wp_user_id` mit.
- `src/Admin/Pages/OnboardingBoardPage.php` – Aktion `create_account`, Spalte „Partnerkonto".
- `src/Admin/Pages/HandbookPage.php` – Absatz Kontoanlage.
- `scripts/liw-selftest.php` – [1] Rollenprüfung, [5] sechs Konto-Prüfungen, Cleanup
  `wp_delete_user()`, Test-E-Mail je Lauf eindeutig.
- `docs/LIW_TODO.md`, `CHANGELOG.md`, `docs/LOGBUCH_TECHNIK.md`.

## 0.1.0-alpha.20 – Anonymisierte Grafiken LP-03/LP-04/LP-12

**Neu:** `assets/img/liw-target-model.svg`, `assets/img/liw-magic-cube.svg`, `assets/img/liw-roadmap.svg`.
**Geändert:** `src/Frontend/SectionGraphicView.php` (Whitelist +3, Doc), `src/Content/SectionBlueprint.php`
(LP-03/04/12 `embed`), `src/Admin/Pages/HandbookPage.php`, `tests/run-tests.php` (Grafik-Prüfungen),
`scripts/liw-selftest.php` ([10] +3), `docs/LIW_LANDINGPAGE_KONZEPT.md`, `docs/LIW_TODO.md`,
`liebherr-interface-world.php` (Version).

## 0.1.0-alpha.19 – LP-13 Kontaktformular + Kontaktanfragen-Board

**Neu:**
- `src/Contact/ContactSchema.php` – Tabelle `liw_contact_request`.
- `src/Contact/ContactService.php` – `submit_request()`, `set_status()`, `get()`, `get_all()`,
  `count_all()`, `delete()`, `recipients()`, Konstanten ROLES/REGIONS/INTERESTS/STATUSES.
- `src/Contact/ContactForm.php` – Shortcode `[liw_contact_form]`, admin-post-Handler, Honeypot.
- `src/Admin/Pages/ContactBoardPage.php` – achtes Board (Liste, Status, Löschen).

**Geändert:**
- `src/Consent/ConsentLogSchema.php` – Spalte `request_kind` + Index `idx_kind_request` (additiv).
- `src/Consent/ConsentLogService.php` – `KIND_*`-Konstanten, 4. Parameter in `record()`/
  `has_consent()` (Default `onboarding`), neu `delete_for_request()`.
- `src/Admin/AdminMenu.php` – Submenü „Kontaktanfragen" vor Media Board.
- `src/Bootstrap.php` – `ContactForm::register()`.
- `liebherr-interface-world.php` – `create_tables()` ruft `ContactSchema::create_table()`; Version.
- `src/Frontend/FrontendAssets.php` – `liw_contact_form` als Auslöser.
- `assets/css/liebherr-frontend.css` – Formularregeln auf `.liw-contact-form` erweitert,
  `.liw-contact-form__interests`/`__interest`.
- `src/Content/SectionBlueprint.php` – LP-13 mit `embed` `[liw_contact_form]`, Vorgabe ohne
  „noch nicht gebaut".
- `src/Admin/Pages/HandbookPage.php` – Abschnitt 6 neu, 7/8 nachnummeriert, „acht Bereiche".
- `tests/run-tests.php` – `liw_contact_form` in bekannten Shortcodes.
- `scripts/liw-selftest.php` – [0] Tabelle/Spalte, neuer Abschnitt [5b], Cleanup.
- `docs/LIW_LANDINGPAGE_KONZEPT.md`, `docs/LIW_TODO.md`.

## 0.1.0-alpha.18 – Zusammengesetzte Landingpage (`[liw_landingpage]`)

**Neu:**
- `src/Frontend/LandingpageView.php` – Shortcode, `get_published_sections()`, Rendering mit
  `the_content`-Filter, Code-Anker, Leerzustand, Rekursionsschutz.

**Geändert:**
- `src/Bootstrap.php` – `LandingpageView::register()`.
- `src/Frontend/FrontendAssets.php` – `liw_landingpage` als vierter CSS-Auslöser.
- `assets/css/liebherr-frontend.css` – `.liw-landingpage`, `__section`, `__title`, `__content`, `--empty`.
- `src/Admin/Pages/HandbookPage.php` – Abschnitt 7 umbenannt/erweitert, „drei" → „vier" Shortcodes.
- `scripts/liw-selftest.php` – Abschnitt [6] um sechs `[liw_landingpage]`-Prüfungen erweitert.
- `docs/LIW_LANDINGPAGE_KONZEPT.md`, `docs/LIW_TODO.md`, `liebherr-interface-world.php` (Version).

## 0.1.0-alpha.17 – Bauplan LP-01…LP-14 + Standard-Abschnitte per Knopf

**Neu:**
- `src/Content/SectionBlueprint.php` – Datenklasse: 14 Codes/Titel/Vorgaben/Einbettungen,
  `menu_order_for()`, `draft_content()` (Block-Markup).
- `src/Content/SectionSeeder.php` – `existing_codes()`, `missing_codes()`, `seed_missing()`,
  `create()`; idempotent über Post-Meta `_liw_lp_code`.

**Geändert:**
- `src/Admin/Pages/ContentBoardPage.php` – Seed-Formular (`render_seed_form()`, Aktion
  `seed_sections` mit eigenem Nonce), Spalte „Code", `maybe_handle_submit()` verzweigt jetzt
  nach Aktion.
- `src/Admin/Pages/HandbookPage.php` – Absatz „Standard-Abschnitte anlegen".
- `tests/run-tests.php` – Prüfgruppe „Landingpage-Bauplan" (7 Prüfungen, mit esc_html-Stub).
- `scripts/liw-selftest.php` – Abschnitt [6] um Bauplan-/Seeder-Prüfungen erweitert.
- `docs/LIW_LANDINGPAGE_KONZEPT.md`, `docs/LIW_TODO.md`, `liebherr-interface-world.php` (Version).

## 0.1.0-alpha.16 – Bugfix: dbDelta-Fehler beim Schema-Abgleich

**Geändert:**
- `src/Interfaces/InterfaceCatalogSchema.php`, `src/Simulation/SimulationSchema.php` (×2),
  `src/Connection/ConnectionSchema.php`, `src/Consent/ConsentLogSchema.php`,
  `src/Onboarding/OnboardingSchema.php` – Klammern in den Tabellen-`COMMENT`s durch Kommata
  ersetzt; Regel als Kommentar in `InterfaceCatalogSchema::create_table()` festgehalten.
- `tests/run-tests.php` – neue Prüfgruppe „dbDelta-Kompatibilität" (COMMENT ohne Klammern).
- `scripts/liw-selftest.php` – Abschnitt [0]: erneuter `create_tables()`-Lauf muss ohne
  `$wpdb->last_error` bleiben.
- `liebherr-interface-world.php` – Version auf `0.1.0-alpha.16`.

**Fund:** sechs `ALTER TABLE … ADD COLUMN )`-Fehler im Log des Docker-Selbsttests alpha.15
(erster Request nach Versionswechsel). Gleiches Muster im Core vorhanden – dort nur
dokumentiert, nicht angefasst.

## 0.1.0-alpha.15 – LP-07/LP-08-Grafiken einbettbar (Shortcode `[liw_graphic]`)

**Neu:**
- `src/Frontend/SectionGraphicView.php` – Shortcode `[liw_graphic name=… caption=…]`, feste
  Whitelist `GRAPHICS` (data-model, process-worlds), `realpath()`-Guard, Request-Cache.

**Geändert:**
- `src/Bootstrap.php` – `SectionGraphicView::register()` ergänzt.
- `src/Frontend/FrontendAssets.php` – `liw_graphic` als dritter CSS-Auslöser.
- `assets/css/liebherr-frontend.css` – `.liw-graphic-figure`, `.liw-graphic-figure__caption`.
- `src/Admin/Pages/HandbookPage.php` – Einbettungs-Anleitung im Content-Board-Abschnitt,
  „beide" → „alle drei" Shortcodes in Abschnitt 7.
- `scripts/liw-selftest.php` – Abschnitt [10] um acht `[liw_graphic]`-Prüfungen erweitert.
- `docs/LIW_LANDINGPAGE_KONZEPT.md` – LP-07/LP-08-Status, Einbettungs-Punkt eingelöst.
- `liebherr-interface-world.php` – Version auf `0.1.0-alpha.15`.

## 0.1.0-alpha.14 – Bugfixes aus dem Docker-Praxistest

**Geändert:**
- `src/CoreBridge/AuditBridge.php` – 7. Parameter an `AuditService::log()` von
  `'liebherr-interface-world'` (Plugin-Slug, falsch) auf `$actor_id > 0 ? 'admin' : 'system'`
  (`$actor_type`, korrekt) geändert. Ursache: `VARCHAR(20)`-Spalte `actor_type` in
  `{$wpdb->prefix}ary_audit_log`, Plugin-Slug war 24 Zeichen lang.
- `scripts/liw-selftest.php` – drei `in_array(..., true)`-Vergleiche (Abschnitte [2] und [3])
  casten die DB-Ergebnisspalte `id` jetzt vor dem Vergleich mit `array_map('intval', ...)`,
  da `$wpdb->get_results()` alle Spalten als String liefert (mysqli-Standard).
- `liebherr-interface-world.php` – Version auf `0.1.0-alpha.14`.

**Fund:** beide Punkte durch den von Joseph in Docker ausgeführten Selbsttest
(`scripts/liw-selftest.php`, alpha.11/alpha.13) aufgedeckt – 56 von 59 Prüfungen bestanden,
drei Fehlschläge plus ein wiederkehrender, nicht fataler `AuditService`-DB-Fehler im Log.

## 0.1.0-alpha.13 – Content Board (§19, Grundgerüst)

**Neu:**
- `src/Admin/Pages/ContentBoardPage.php` – Übersicht/Freigabeworkflow für `liw_section`.
- `CPT\LiwSectionCpt::add_status_badge()` – Status-Badge „Freigegeben" in der Listenansicht.

**Geändert:**
- `src/Admin/AdminMenu.php` – Submenü „Content Board" ergänzt.
- `src/Admin/Pages/HandbookPage.php` – Abschnitt zum Content Board ergänzt, „sechs" → „sieben Bereiche".
- `scripts/liw-selftest.php` – neuer Abschnitt [6] Content Board (Statuswechsel-Kette,
  Filter-Registrierung); nachfolgende Abschnitte umnummeriert (Media Board jetzt [7] usw.),
  neuer Abschnitt [10] prüft die alpha.12-Landingpage-Konzept-Dateien.
- `liebherr-interface-world.php` – Version auf `0.1.0-alpha.13`.

## 0.1.0-alpha.12 – Landingpage-Konzept + anonymisierte LP-07/LP-08-Grafiken

**Neu:**
- `docs/LIW_LANDINGPAGE_KONZEPT.md` – LP-01…LP-14-Übersicht mit Umsetzungsstand.
- `assets/img/liw-data-model.svg` (LP-07), `assets/img/liw-process-worlds.svg` (LP-08) –
  anonymisierte, inline einzubettende SVG-Grafiken auf Basis der von Joseph gelieferten
  internen Prozess-PDF, ohne reale System-/API-Namen (Sicherheitsentscheidung).

**Geändert:**
- `liebherr-interface-world.php` – Version auf `0.1.0-alpha.12`.
- `docs/LIW_TODO.md` – Content-Board-Punkt ergänzt um Hinweis auf die beiden neuen,
  noch zu platzierenden Grafiken.

## 0.1.0-alpha.11 – Docker-Praxistest (Integrations-Selbsttest)

**Neu:**
- `scripts/liw-selftest.php` – Integrations-Selbsttest in der echten Docker-Dev-Umgebung
  (analog Core `scripts/yb-selftest.php`): DB-Schema, Capabilities, alle sechs Boards,
  beide Frontend-Shortcodes, Design-System-Assets, Programmierlogbuch/To-Dos. Testdaten
  `SELFTEST-`-präfigiert, garantierte Aufräumung im `finally`-Block.

**Geändert:**
- `tests/run-tests.php` – Kommentar verweist jetzt auf das ausgelieferte Skript statt auf
  eine „Folgeauslieferung".
- `liebherr-interface-world.php` – Version auf `0.1.0-alpha.11`.

## 0.1.0-alpha.10 – Feinschliff an den Boards + Programmierlogbuch/To-Dos

**Neu:**
- `src/Admin/AdminPagination.php` – gemeinsame Pagination-Ansicht (`paginate_links()`), von
  Media Board und Onboarding Board genutzt (DRY statt Code-Duplikat).
- `src/Admin/AdminAssets.php` – lädt `assets/css/liebherr-admin.css` nur auf den eigenen
  Board-Seiten (Hook-Suffix-Prüfung, analog `Frontend\FrontendAssets`).
- `assets/css/liebherr-admin.css` – Utility-Klassen `.liw-clear`, `.liw-row-form--inline`,
  `.liw-pagination`, löst die bisherigen Inline-Styles ab.
- `src/CoreBridge/MarkdownBridge.php` – Wrapper um Core
  `Modules\Deployment\Admin\HandbookRenderer::render()` (verifiziert generisch), für die
  Darstellung dieses Logbuchs und der To-Do-Liste im Backend.
- `src/Admin/Pages/ProgrammingLogPage.php` – rendert dieses Dokument im Backend.
- `src/Admin/Pages/TodoBoardPage.php` – rendert `docs/LIW_TODO.md` im Backend.
- `docs/LIW_TODO.md` – kuratierte Liste offener/geplanter Punkte (siehe dort).

**Geändert:**
- `src/Onboarding/OnboardingService.php` – `get_all_requests()` von ungebremstem JOIN auf
  `LIMIT`/`OFFSET` umgestellt (neuer Parameter `$page`, Konstante `REQUESTS_PER_PAGE = 20`),
  neue Methode `count_all_requests()` für die Pagination-Anzeige.
- `src/Admin/Pages/OnboardingBoardPage.php` – nutzt `AdminPagination`, Inline-Style
  (`style="display:flex;..."`) durch Klasse `liw-row-form--inline` ersetzt.
- `src/Admin/Pages/MediaBoardPage.php` – `get_posts()` durch `WP_Query` ersetzt (liefert
  `found_posts` für Pagination mit), Inline-Style (`style="clear:both;"`) durch
  `liw-clear` ersetzt. Vorher hart auf die 50 neuesten Anhänge begrenzt, jetzt blätterbar.
- `src/Admin/Pages/ConnectionBoardPage.php` – Inline-Style durch `liw-row-form--inline`
  ersetzt.
- `src/Admin/Pages/HandbookPage.php` – vollständig überarbeitet: deckt jetzt alle sechs
  Boards (Interface, Simulation, World Connections, Onboarding, Media, Design System) sowie
  Programmierlogbuch und To-Dos ab (vorher: nur Interface-Board-Grundgerüst aus alpha.1).
- `src/Admin/AdminMenu.php` – zwei neue Untermenüpunkte (Programmierlogbuch, To-Dos) vor dem
  Handbuch eingefügt; Handbuch bleibt laut Handbuch-Regel letzter Reiter.
- `src/Bootstrap.php` – `AdminAssets::register()` ergänzt.
- `liebherr-interface-world.php` – Version auf `0.1.0-alpha.10`.

**Fund bei dieser Gelegenheit (kein neuer Bug, Bestandsaufnahme):** drei Inline-Style-
Fundstellen verstießen gegen CLAUDE.md Abschnitt 5 („keine Inline-Styles"); Media Board und
Onboarding Board luden ihre Listen ungebremst (s. CHANGELOG alpha.7 „Bewusst nicht Teil dieser
Auslieferung").

## 0.1.0-alpha.9 – Design-System-Anbindung (Frontend-Shortcodes)

**Neu:** `assets/css/liebherr-frontend.css`, `src/Frontend/FrontendAssets.php`.
**Geändert:** `src/Bootstrap.php` (FrontendAssets registriert), `liebherr-interface-world.php`
(Version), `CHANGELOG.md`, `docs/LOGBUCH_TECHNIK.md`.
Details: CHANGELOG.md alpha.9.

## 0.1.0-alpha.8 – World Connections Map (Frontend-Visualisierung)

**Neu:** `src/Connection/ConnectionMapView.php` (Shortcode `[liw_world_connections_map]`).
**Geändert:** `src/Bootstrap.php` (ConnectionMapView registriert), `liebherr-interface-world.php`,
`CHANGELOG.md`, `docs/LOGBUCH_TECHNIK.md`.
Details: CHANGELOG.md alpha.8.

## 0.1.0-alpha.7 – Media Board (§18) + MediaBridge-Bugfix

**Neu:** `src/Admin/Pages/MediaBoardPage.php`.
**Geändert:** `src/CoreBridge/MediaBridge.php` (Bugfix: CI-005-Checkbox in `add_fields()`/
`save_fields()` – vorher reiner Anzeigetext, nie gespeichert), `src/Admin/AdminMenu.php`
(Submenü), `liebherr-interface-world.php`, `CHANGELOG.md`, `docs/LOGBUCH_TECHNIK.md`.
Details: CHANGELOG.md alpha.7.

## 0.1.0-alpha.6 – Onboarding-Formular (§22)

**Neu:** `src/Onboarding/OnboardingSchema.php`, `src/Onboarding/OnboardingService.php`,
`src/Onboarding/OnboardingForm.php`, `src/Admin/Pages/OnboardingBoardPage.php`,
`src/CoreBridge/PartnerBridge.php`.
**Geändert:** `liebherr-interface-world.php` (`create_tables()`/`maybe_upgrade_database()`
eingeführt – selbstheilender Schema-Abgleich), `src/Admin/AdminMenu.php`, `src/Bootstrap.php`,
`CHANGELOG.md`, `docs/LOGBUCH_TECHNIK.md`.
Details: CHANGELOG.md alpha.6.

## 0.1.0-alpha.5 – World Connections Map (Datenpflege, Grundgerüst)

**Neu:** `src/Admin/Pages/ConnectionBoardPage.php`.
**Geändert:** `src/Connection/ConnectionService.php` (erweitert um `get_all()`, `get()`,
`set_display_status()`, `set_public_flag()`, `delete()`), `src/Admin/AdminMenu.php`,
`liebherr-interface-world.php` (`Requires PHP: 7.4` → `8.0`, Bugfix), `CHANGELOG.md`,
`docs/LOGBUCH_TECHNIK.md`.
Details: CHANGELOG.md alpha.5.

## 0.1.0-alpha.4 – Simulation Board (Magic Cube, Grundgerüst)

**Neu:** `src/Admin/Pages/SimulationBoardPage.php`.
**Geändert:** `src/Admin/AdminMenu.php` (Submenü), `CHANGELOG.md`.
Details: CHANGELOG.md alpha.4.

## 0.1.0-alpha.3 – Bugfix: Menü „Interface World" für Administrator unsichtbar

**Geändert:** `src/CoreBridge/RoleBridge.php` (`administrator` zu `FULL_ACCESS_ROLES`
ergänzt), `CHANGELOG.md`.
Details: CHANGELOG.md alpha.3.

## 0.1.0-alpha.2 – Bugfix: Aktivierung schlug immer fehl

**Geändert:** `liebherr-interface-world.php` (`core_is_available()`: `is_plugin_active()`
statt fehlerhaftem `class_exists()` auf ein Interface), `CHANGELOG.md`.
Details: CHANGELOG.md alpha.2.

## 0.1.0-alpha.1 – Plugin-Grundgerüst + CoreBridge

**Neu:** komplettes Plugin-Grundgerüst – `liebherr-interface-world.php`, PSR-4-Autoloader,
`src/CoreBridge/{TranslationBridge,RoleBridge,AuditBridge,SeoBridge,MediaBridge}.php`,
CPT `liw_section`, DB-Schema (`liw_interface`, `liw_simulation_world`, `liw_test_scenario`,
`liw_connection`, `liw_consent_log`), `src/Admin/Pages/InterfaceBoardPage.php`,
`src/Admin/Pages/HandbookPage.php`, `tests/run-tests.php` (38/38 grün).
Details: CHANGELOG.md alpha.1.
