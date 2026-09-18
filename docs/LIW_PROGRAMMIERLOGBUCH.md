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
