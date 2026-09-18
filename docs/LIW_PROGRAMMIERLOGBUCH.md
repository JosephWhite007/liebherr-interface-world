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
