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
