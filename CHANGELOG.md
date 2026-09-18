# Liebherr Interface Solutions — Changelog

## [0.1.0-alpha.33] – 2026-09-18 – Etappe 7: Sicherheit & Datenschutz (§23/§24)

### Hinzugefügt
- **Rate-Limiting öffentlicher Formulare** (SEC-004): Kontakt- und Onboarding-Formular drosseln
  Absendungen je IP über den Core-`RateLimiter` (neuer `RateLimitBridge`, 5/min, `liw_`-Präfix).
  Überschreitung → generische Fehlermeldung (keine internen Details, SEC-010). Ohne Core wird nicht
  blockiert (Graceful Degradation, §14).
- **Konfigurierbare Aufbewahrungsfrist** für Kontaktanfragen (SEC-007/§24, `ContactRetention`):
  täglicher WP-Cron löscht Anfragen älter als N Tage inkl. Einwilligungen (auditiert); 0 = deaktiviert
  (Standard). Einstellbar im Contact Board; Cron wird bei Deaktivierung entfernt.
  - Neu: `ContactService::ids_older_than()`.

### Geprüft/Dokumentiert (bereits erfüllt)
- **Upload-Härtung (SEC-009):** `PartnerDocumentService::upload()` prüft bereits Größe, doppelten
  MIME (Dateiname via `wp_check_filetype` **und** Inhalt via `finfo`) gegen eine Whitelist und legt in
  einem gegen Direktzugriff geschützten Verzeichnis ab – durch Selbsttest abgesichert.
- **Fehlermeldungs-Hygiene (SEC-010):** öffentliche Formulare geben nur generische Status
  (success/error) aus, keine internen Pfade/Details.
- **Security-Header (SEC-008):** bewusst Plattform-/Serveraufgabe (CSP/HSTS „nach Plattformstandard");
  im Modul nicht gesetzt, um Konflikte zu vermeiden (dokumentiert).

### Hinweise
- Verifikation: `php -l`; `tests/run-tests.php` 174/174, `scripts/liw-selftest.php` 177/177 im
  Docker-Container.

## [0.1.0-alpha.32] – 2026-09-18 – Etappe 6: SEO-Rest (§21) & Sprach-Release-Readiness (LANG-006)

### Hinzugefügt
- **SEO je Sprache** (SeoBridge, §21): Canonical je Locale über den WP-Filter `get_canonical_url`
  (hängt `?lang=xx` bei Nicht-Standardsprache an – kein doppeltes Canonical-Tag), Open-Graph-Tags
  (`og:type/title/url/locale`, optional `og:image` aus dem freigegebenen Hero-Bild), Sitemap-Ausschluss
  der Abschnitts-Fragmente `liw_section` (kanonisch ist die Landingpage). Ergänzt das bestehende
  hreflang (Option A); schweigt weiterhin bei aktivem Core-Router.
- **Language Board – Release-Readiness** ([LanguageBoardPage](src/Admin/Pages/LanguageBoardPage.php),
  „🌐 Language Board", LANG-006): je aktiver Sprache Vollständigkeit der öffentlichen Flächen
  (veröffentlichte Abschnitte + Trägerseite) aus der Core-Translation-Registry
  (`page_metrics()`/`is_complete()`): Flächen vollständig, Mindest-Übersetzungsgrad, Release-fähig.
  - Neu: `TranslationBridge::readiness_report()`, `public_scope_post_ids()`; `is_locale_release_ready()`
    nutzt jetzt echte Registry-Metriken statt des nie erfüllten Gate-Aufrufs.

### Hinweise
- UI-Chrome (Nav/CTA/Formular/Fehlermeldungen) läuft über Programmtexte (`__()`/Sprachdateien)/Slots
  und wird im Core Languages Hub gepflegt; das Board misst die redaktionellen Flächeninhalte. Das
  *harte* Sprach-Gate bleibt Betriebsentscheidung (Kategorie A, Core). I18n-Router Option B (Präfix-URLs)
  weiterhin offener Folgepunkt.
- Verifikation: `php -l`; `tests/run-tests.php` 169/169, `scripts/liw-selftest.php` 168/168 im
  Docker-Container.

## [0.1.0-alpha.31] – 2026-09-18 – Etappe 5: Audit Board & Interface-Lifecycle-Status (§18)

### Hinzugefügt
- **Audit Board** ([AuditBoardPage](src/Admin/Pages/AuditBoardPage.php), „🛡 Audit Board"):
  unveränderliche Lese-Ansicht der administrativen LIW-Ereignisse (Zeitpunkt, Aktion, Objekt,
  Objekt-ID, Akteur, Akteurstyp), paginiert. Quelle = zentrales Core-Audit-Log, gefiltert auf
  `entity_type LIKE 'liw_%'`. Capability `liw_manage_interfaces`. Kein eigenes Audit-Datenmodell.
  - Neu: `AuditBridge::recent_liw_events()` und `AuditBridge::count_liw_events()` (nur Lesen).
- **Interface-Lifecycle-Status-UI** im Interface Board: Statuswechsel je Schnittstelle
  (Entwurf → In Simulation → Verifiziert → Freigegeben → Stillgelegt) über
  `InterfaceCatalogService::set_lifecycle_status()`; jede Änderung wird auditiert.

### Hinweise
- **Release Board (§18):** bewusst nicht als eigenes Board gebaut – Staging/Produktion, Freigaben und
  Rollback laufen über den ARALIYA-Deployment-Manager des Core (Variante A). Das Sprach-Release-Gate
  folgt in Etappe 6 (LANG-006).
- Verifikation: `php -l`; `tests/run-tests.php` 167/167, `scripts/liw-selftest.php` 160/160 im
  Docker-Container.

## [0.1.0-alpha.30] – 2026-09-18 – Etappe 4: §24-Export & Redaktions-Prüfung (§19)

### Hinzugefügt
- **§24 CSV-Export der Kontaktanfragen** ([ContactExporter](src/Contact/ContactExporter.php)):
  Knopf „Als CSV exportieren (§24)" im Contact Board (admin-post, Capability `liw_view_onboarding`,
  Nonce, Audit-Eintrag `export`). Format UTF-8 mit BOM + Semikolon (Excel-DE); Spalten = §22-Felder
  (Region/Rolle/Interessen als Labels) + Status + Datenschutz-/Marketing-Einwilligung mit Textversion
  und Zeitstempel (getrennt, §24). Datenschutzhinweis am Knopf.
  - Neu: `ContactService::get_all_for_export()`, `ConsentLogService::list_for_request()`.
- **Redaktions-Prüfung** im Content Board (§19/§26): warnt bei veröffentlichten Abschnitten ohne
  Titel oder mit Bildern ohne Alt-Text (mit Direktlink zum Bearbeiten). Reine, testbare
  `ContentBoardPage::count_images_without_alt()`.
- **CTA-Ziel-Vorschläge** im Header Board (§19 „intern auswählen"): `<datalist>` der veröffentlichten
  Abschnitts-Anker an den CTA-Zielfeldern.

### Hinweise
- Übersetzungs-Vollständigkeit ist Teil des Sprach-Release-Gates (Etappe 6, LANG-006), nicht der
  Redaktions-Prüfung. Medien-Picker-Beschränkung auf freigegebene Bibliothek bleibt zurückgestellt
  (WP-`ajax_query_attachments`-Kontext unzuverlässig, s. To-Dos); die LIW-Auswahllisten
  (Logo/Hero-Bild) kennzeichnen bereits den Freigabestatus.
- Verifikation: `php -l`; `tests/run-tests.php` 165/165, `scripts/liw-selftest.php` 154/154 im
  Docker-Container.

## [0.1.0-alpha.29] – 2026-09-18 – Etappe 3: Datengetriebene Kern-Komponenten (LP-08/11/12)

### Hinzugefügt
- **`[liw_process_worlds]`** (LP-08): Karten-Grid Sales, Configuration, Order, Goods, Finance,
  Service, Warranty. **`[liw_roadmap]`** (LP-12): nummerierte Phasen-Zeitleiste (Contract Model …
  Global Rollout). **`[liw_onboarding_steps]`** (LP-11): neunstufige Schrittliste. Alle reiner
  Text/HTML → von sich aus barrierearm (§26 Textalternative), Gestaltung über `--brand-*` (§11).
- **`src/Settings/ComponentContent.php`** – administrierbarer Inhalt der drei Listen (Option
  `liw_components`), Standardinhalte je §8 via `__()`; leere Liste = Standard zurück.
- **`src/Admin/Pages/ComponentsBoardPage.php`** – „🧩 Components Board" (Eingabe „Titel | Text" je
  Zeile), Capability `liw_manage_content`, Nonce, Audit (SEC-005).
- CSS für Karten/Zeitleiste/Schritte über `--brand-*`. `[liw_*]` als Asset-Auslöser.

### Hinweise
- Kuratierte Abschnitte LP-02/03/04/05/07/09/10 bleiben redaktionell (Grafik/HTML im Abschnitt);
  interaktive Vertiefung ist Feature-Flag/Folgeetappe (AC-002).
- Labels wie in Etappe 2: Standard mehrsprachig via `__()`, Overrides literal (Verfeinerung, To-Dos).
- Verifikation: `php -l`; `tests/run-tests.php` 158/158, `scripts/liw-selftest.php` 149/149 im
  Docker-Container; die drei Komponenten visuell im Browser bestätigt.

## [0.1.0-alpha.28] – 2026-09-18 – Etappe 2: Header/Navigation (§7) + Hero (LP-01)

### Hinzugefügt
- **`[liw_header]`** ([HeaderView](src/Frontend/HeaderView.php)) – sticky Hauptnavigation (§7/§16):
  Logo (nur wenn im Media Board freigegeben, CI-005; sonst neutrale Text-Wortmarke, kein erfundenes
  Logo, CI-002), konfigurierbare Menüpunkte, Primär-CTA „Start Integration", Sekundär-CTA
  „Explore the Simulation", Core-Sprachumschalter und optionaler Portal-Login. Mobil als
  zugängliches Off-Canvas-Menü (`aria-expanded`, Escape/Link schließt).
- **`[liw_hero]`** ([HeroView](src/Frontend/HeroView.php)) – LP-01 Hero-Network (§8): H1/Subline
  (Attribute, über Abschnittstext mehrsprachig), dezente Netzwerk-Ebene (dekorativ, `aria-hidden`,
  `prefers-reduced-motion`-fest), beide CTAs. Bildmotiv nur wenn freigegeben (CI-005), sonst
  neutraler Verlauf.
- **`src/Settings/HeaderSettings.php`** – administrierbare Header-Konfiguration (Option `liw_header`):
  Menüpunkte, CTAs, Portal-Login, Hero-Bild; Standardwerte je §7/§8 mit `__()`. Reine, testbare
  `normalize_target()` (nur In-Page-Anker, relativer Pfad oder http(s)-URL; `javascript:` und
  protokollrelative Ziele verworfen).
- **`src/Admin/Pages/HeaderBoardPage.php`** – „🧭 Header Board" (Nav/CTAs/Portal/Hero-Bild),
  Capability `liw_manage_content`, Nonce, Audit (SEC-005).
- CSS (Header sticky + Off-Canvas, CTAs, Hero) über `--brand-*`-Tokens; JS-Menü-Umschalter in
  `assets/js/liebherr-frontend.js`. `[liw_header]`/`[liw_hero]` als Asset-Auslöser.

### Hinweise
- Nav-/CTA-Labels: Standard über `__()` (mehrsprachig); redaktionell überschriebene Labels sind
  literal – per-Sprache-Labels sind eine spätere Verfeinerung (To-Dos).
- Verifikation: `php -l`; `tests/run-tests.php` 147/147, `scripts/liw-selftest.php` 143/143 im
  Docker-Container; Header/Hero visuell in Desktop- und Mobilansicht geprüft (Browser-Vorschau).

## [0.1.0-alpha.27] – 2026-09-18 – Etappe 1: CI/Brand-Fundament (Design-Tokens §10–12)

### Hinzugefügt
- **`src/Branding/BrandTokens.php`** – zentraler Design-Token-Service (Pflichtenheft §11):
  neutrale, markenneutrale Fallbacks (kein erfundenes Liebherr-Branding, CI-002); Option
  `liw_brand_tokens` überschreibt sie. Reine, unit-testbare `sanitize()` (Farben nur `#rrggbb`,
  Schriftstacks auf sichere Zeichen begrenzt, Radius/Breite nur Zahl+Einheit, `logo_id` int) und
  `css_from()`/`css_root()` (Ausgabe `:root{ --brand-* }`, ausbruchsicher gegen `{}`/`;`/`<`/`>`).
- **`src/Admin/Pages/BrandBoardPage.php`** – Admin-Board „🎨 Brand Board (Design / CI)": pflegt
  Primär-/Sekundär-/Flächen-/Text-/Muted-/Rahmenfarbe, Headline-/Text-Schrift, Radius,
  Inhaltsbreite und Logo (Auswahl aus Media-Board-Assets der Rolle „logo"; nicht freigegebene
  Logos werden im Frontend nicht ausgegeben, CI-005). Capability `liw_manage_content`, Nonce,
  Audit (SEC-005), Live-Vorschau des ausgegebenen CSS-Blocks.
- Frontend: `FrontendAssets::maybe_enqueue()` injiziert die Tokens via `wp_add_inline_style()`
  (sanktionierter Weg für dynamische Tokens, kein hart codierter Markenwert in Komponenten, §11/§14).
- `assets/css/liebherr-frontend.css`: `:root`-Fallback-Block der `--brand-*`-Variablen.
- Selbsttests: `tests/run-tests.php` (Fallback-Validität, Markenneutralität, sanitize-Fälle,
  css_from-Ausbruchschutz), `scripts/liw-selftest.php` [8] (Board vorhanden, css_root, Injektion).

### Hinweise
- Bis zur dokumentierten Liebherr-Freigabe (§34) gelten die neutralen Fallbacks. Danach die in
  `docs/LIW_BRAND_TOKENS.md` erfassten Originalwerte im Brand Board eintragen und die Assets im
  Media Board freigeben. Erste Etappe des Release-Plans (`docs/LIW_RELEASEPLAN.md`).
- Verifikation: `php -l`; `tests/run-tests.php` 132/132 und `scripts/liw-selftest.php` 134/134 im Docker-Container.

## [0.1.0-alpha.26] – 2026-09-18 – Landingpage: aktive Hervorhebung des sichtbaren Abschnitts in der Sprungleiste

### Hinzugefügt
- **`assets/js/liebherr-frontend.js`** – erstes Frontend-Skript des Plugins: ein
  abhängigkeitsfreier Scrollspy, der in der Sprungleiste (`.liw-landingpage__nav`, seit alpha.23)
  den gerade sichtbaren Abschnitt markiert. Per `IntersectionObserver` (schmales Lesefenster ~42 %)
  erhält der zugehörige Link `aria-current="true"` und die Klasse `is-current`, alle anderen
  verlieren sie. Fortschreitende Verbesserung: Ohne JavaScript (oder ohne `IntersectionObserver`)
  bleibt die Sprungleiste voll funktionsfähig. Kein Inline-Code, keine Netzwerk-/Personendaten.
- CSS-Aktivzustand `.liw-landingpage__nav-link.is-current` (Akzentfarbe + Unterstrich wie beim
  Hover, zusätzlich fett) in `assets/css/liebherr-frontend.css`.
- Selbsttests: `tests/run-tests.php` (WP-frei) prüft Asset-Vorhandensein, Enqueue-Registrierung
  (Footer, `LIW_VERSION`), Bindung an `.liw-landingpage__nav`, `IntersectionObserver`/`aria-current`/
  `is-current` und den CSS-Aktivzustand; `scripts/liw-selftest.php` [8] ergänzt die gleichen Checks
  in der Docker-Umgebung.

### Geändert
- `src/Frontend/FrontendAssets.php` – `maybe_enqueue()` bindet zusätzlich zum Stylesheet das
  Frontend-Skript ein (gleicher Auslöser: eine Seite mit einem der `liw_*`-Shortcodes; Footer,
  `LIW_VERSION` als Cache-Buster).
- `src/Frontend/LandingpageView.php` – Kommentar von `render_nav()` an den neuen Stand angepasst
  (JS-Hervorhebung seit alpha.26; Markup unverändert, das Skript bindet an die Nav-Klasse).

### Umgesetzter To-Do-Punkt
- „Aktive Hervorhebung des sichtbaren Abschnitts in der Sprungleiste" (offen seit alpha.18,
  YAGNI-vertagt) – siehe `docs/LIW_TODO.md`.

### Hinweise
- Keine neuen sichtbaren Texte: `aria-current`/`is-current` sind Zustände, keine Inhalte; die
  Nav-Titel stammen unverändert aus den Abschnitts-Titeln (Core-Übersetzung). DoD Punkt 9 nicht berührt.
- Verifikation: `php -l` je geänderter Datei; `tests/run-tests.php` 119/119 im Docker-Container;
  JS-Parse und Scrollspy-Verhalten (genau ein aktiver Link, Dokumentreihenfolge gewinnt, letzte
  Markierung bleibt) real in der Browser-JS-Engine simuliert. Docker-`liw-selftest.php`-Lauf steht aus.

## [0.1.0-alpha.25] – 2026-09-18 – Bugfix: Sprachcodes aus dem Core („Array" statt `de`)

### Behoben
- **`LanguageBridge::active_langs()`** (und damit auch der seit alpha.1 bestehende Code in der alten
  `SeoBridge`) behandelte den Rückgabewert von Core `LanguageService::get_active_langs()` als
  String-Liste. Der Core liefert aber **Sprach-Datensätze** (`['code' => 'de', 'label' => …]`);
  der Cast erzeugte `"Array"` – im Frontend also `hreflang="Array"` statt `hreflang="de"`. Seit
  alpha.1 unbemerkt, weil hreflang bisher nur auf Einzelansichten und nie geprüft ausgegeben wurde;
  die neue Selbsttest-Prüfung [6b] aus alpha.24 hat es beim ersten Docker-Lauf aufgedeckt
  (`PHP Warning: Array to string conversion`, 124/125).
- Fix: `active_langs()` akzeptiert Datensätze (`code`-Feld), schlüssel-indizierte Listen und reine
  String-Listen, verwirft alles, was kein plausibler Sprachcode ist (`^[a-z]{2,5}(-[a-z0-9]{2,8})?$`),
  entfernt Dubletten und fällt bei leerem Ergebnis auf DE/EN/PL zurück.

### Geprüft
- Stub-Test ohne WP (6/6): Core-Datensätze, String-Liste, schlüssel-indiziert, leer, unbrauchbar,
  Dubletten/Großschreibung. `tests/run-tests.php`: 112/112. Selbsttest-Prüfung [6b] verschärft:
  jeder Code muss dem Muster entsprechen (hätte den Fehler auch ohne PHP-Warning gefangen).
  Docker-Lauf steht aus.

## [0.1.0-alpha.24] – 2026-09-18 – I18nSeo Option A: Sprache, hreflang, §24-Sprachsuffix

### Entscheidung (Analyse 18.09.2026, Joseph White „ja" zu Option A)
- Befund: Die Core-Sprachsteuerung (Cookie + `?lang=xx`, `LanguageService`) greift bereits auf
  allen Seiten inkl. Trägerseite und Abschnitten; der Core hat ein fertiges
  `LanguageSwitcherWidget`. Der Core-`I18nRouter` (Präfix-URLs `/en/…`) ist feature-geflaggt und
  deckt nur Startseite + `suite/apartment/treeroom` ab – **nicht** den Post-Type `page`, auf dem
  die Landingpage liegt; ein Ergänzen von `liw_section` allein hätte die Trägerseite nicht erfasst.
  Die bisherige `SeoBridge` zielte auf Einzelansichten statt auf die Trägerseite.
- Option A (kein Core-Eingriff) umgesetzt; Option B (Router um `page` + `liw_section` erweitern)
  als Folgepunkt, wenn der Router plattformweit aktiv ist.

### Hinzugefügt / Geändert
- **`CoreBridge\LanguageBridge`** (neu): einziger Kopplungspunkt zur Core-Sprache –
  `current_lang()`/`active_langs()` (Fallback DE/EN/PL, Pflichtenheft §20), `switcher_html()`
  (Core-Widget), `core_router_active()`, `versioned()`; Shortcode **`[liw_language_switcher]`**
  (Hülle `.liw-language-switcher` um das Core-Widget, kein eigener Umschalter).
- **`CoreBridge\SeoBridge`** neu ausgerichtet: hreflang (je Sprache `?lang=`, `x-default` =
  Basis-URL) auf der **Trägerseite** (Seite mit `[liw_landingpage]`) und auf Einzelansichten;
  **schweigt, sobald der Core-Router aktiv ist** (kein doppeltes hreflang). Markup-Erzeugung als
  testbare Funktion `hreflang_markup()`.
- **§24 „Datenschutztexte sprachabhängig versionieren":** Einwilligungen aus Onboarding und
  Kontaktformular tragen jetzt die Textversion mit Sprachsuffix (z. B. `2026-09-18-contact-v1-de`).
- Handbuch: Absatz Mehrsprachigkeit im Landingpage-Abschnitt; `docs/LIW_TODO.md`: I18nSeo-Punkt
  auf Option B umgestellt, §24-Punkt erledigt.

### Geprüft
- `tests/run-tests.php`: 112/112. `scripts/liw-selftest.php`: neuer Abschnitt [6b] mit sechs
  Prüfungen (aktive Sprachen, hreflang-Markup, Router-Status, Umschalter-Shortcode, Sprachsuffix
  in `versioned()` und in der realen Onboarding-Einwilligung des Laufs). Docker-Lauf steht aus.

## [0.1.0-alpha.23] – 2026-09-18 – Landingpage: klebende Sprungleiste (Ankernavigation)

### Hinzugefügt
- `[liw_landingpage]` rendert oben eine **Sprungleiste** (`<nav aria-label="Abschnitte der Seite">`)
  mit einem Link je veröffentlichtem Abschnitt auf dessen Anker (`#lp-07` …); `position: sticky`,
  horizontal scrollbar auf kleinen Bildschirmen, Hover/Fokus in Akzentfarbe, `scroll-margin-top`
  der Abschnitte auf 4rem erhöht, damit Überschriften nicht unter der Leiste verschwinden.
  Erscheint erst ab zwei Abschnitten; `nav="0"` schaltet sie ab. Reines HTML/CSS – keine aktive
  Hervorhebung per JS (kein Inline-JS; eigenes Skript wäre YAGNI, s. To-Dos).
- `LandingpageView::anchor_for()` als gemeinsame Quelle für Anker (Abschnitt und Leiste), kein
  doppelter Code.

### Geprüft
- `tests/run-tests.php`: 110/110. `scripts/liw-selftest.php` [6]: zwei Prüfungen (Leiste mit Link auf
  den Test-Anker bei ≥ 2 veröffentlichten Abschnitten, `nav="0"` ohne Leiste). Docker-Lauf steht aus.

## [0.1.0-alpha.22] – 2026-09-18 – Partnerbereich Stufe 2: geschützte Partnerdokumente

### Hinzugefügt
- **`Partner\PartnerDocumentService` / `PartnerDocumentSchema`** (Tabelle `liw_partner_document`,
  nur Metadaten): Sicherheitsmuster 1:1 vom Core `Modules\Documents` (ADR-074) übernommen, weil
  das Core-Modul gastgebunden ist – Upload-Verzeichnis `uploads/liw-partner-documents` mit 0750,
  `.htaccess Deny from all` + `Require all denied`, leerer `index.html`; zufälliger `stored_name`
  (nie ausgegeben, auch nicht in `get_active()`); doppelte Typprüfung Dateiname UND Inhalt (finfo)
  gegen Whitelist PDF/PNG/JPG/DOCX; 10 MB; SHA-256 je Datei; Soft-Delete (Datei bleibt für den
  Audit-Trail); Audit für Upload, Löschung und **jeden Download** (§10).
- **`[liw_partner_documents]`** (`Partner\PartnerDocumentsView`): ohne Login nur Login-Link
  (`wp_login_url()` mit Rücksprung), angemeldet ohne `liw_partner_access` ein Hinweis, Partner
  die Liste mit Download-Formular (POST + Nonce). Download über `admin_post_` (bewusst kein
  `nopriv`): eingeloggt → Capability → Nonce → aktives Dokument → realpath-Guard → Streaming mit
  `Content-Disposition`, `nosniff`, `nocache_headers()`. Kein Token nötig (Website-Login mit
  Cookie; der Core braucht Token wegen seiner cookielosen App-API). Admin-Leiste für reine
  Partner ausgeblendet.
- **Neuntes Board „Partnerdokumente"** (`Admin\Pages\PartnerDocumentBoardPage`, Capability
  `liw_manage_content`): Upload-Formular, Liste, Entfernen. Hinweis im Board: nur für Partner
  freigegebene Fassungen hochladen.
- `RoleBridge`: Administratoren erhalten `liw_partner_access` (Prüfung/Support des Frontends);
  Nachziehen bei Versionswechsel über `ensure_partner_role()`.
- CSS `.liw-partner-docs*` (Design-Tokens), `FrontendAssets`: sechster Auslöser. Handbuch:
  neuer Abschnitt 7, Nummerierung 8/9.

### Annahme
- **ANNAHME-LIW-12:** Alle Partner mit `liw_partner_access` sehen dieselben Dokumente (keine
  Zuordnung je Partner/Region); additiv per Join-Tabelle nachrüstbar.

### Geprüft
- `tests/run-tests.php`: 110/110 (neue Klassen; COMMENT-Regel für die neue Tabelle grün).
- Fachlogik ohne WP per Stub (14/14): gültiger Upload, zufälliger Name, Datei im gesperrten
  Verzeichnis mit `.htaccess`, SHA-256, realpath-Guard gegen Traversal, Ablehnung `.php`/`.exe`,
  Inhalt≠Endung, leerer Titel, Upload-Fehler, >10 MB, Soft-Delete lässt Datei stehen.
- `scripts/liw-selftest.php`: neuer Abschnitt [5c] mit 10 Prüfungen (Verzeichnisschutz,
  Ablehnungen, Upload, Listen ohne `stored_name`, Shortcode ohne Login/als Partner, Admin-Leiste,
  Soft-Delete) – Testuploads nur in `development` per CLI aus dem WP-Temp-Verzeichnis
  (`is_test_upload()`, im Produktivbetrieb bleibt `is_uploaded_file()` zwingend). Docker-Lauf
  steht aus.

## [0.1.0-alpha.21] – 2026-09-18 – Partnerbereich Stufe 1: Rolle `liw_partner` + Partnerkonto anlegen

### Hinzugefügt
- **Eigene WP-Rolle `liw_partner`** („Liebherr Interface Partner", `RoleBridge::ROLE_PARTNER`) mit
  genau zwei Capabilities: `read` (WP-Minimum für Login/Profil) und `liw_partner_access`
  (`RoleBridge::CAP_PARTNER_ACCESS`). Bewusst keine ARALIYA-Rolle: Core-Rollen tragen Hotel-/
  Gesundheits-Capabilities, die ein Liebherr-Partner nie haben darf (Least Privilege, §10/§23).
  Kein Backend-Zugriff über das eigene Profil hinaus. `RoleBridge::ensure_partner_role()` läuft
  idempotent bei Aktivierung und bei jedem Versionswechsel (`maybe_upgrade_database()`); die Rolle
  wird bei Deaktivierung bewusst nicht entfernt (Benutzer würden rollenlos).
- **`OnboardingService::create_partner_account()`**: legt für eine *freigegebene* Anfrage ein
  WP-Konto mit Partnerrolle an, verknüpft es (`liw_partner_extra.wp_user_id`, User-Meta
  `_liw_partner_id`), versendet den WP-Standardlink zum Passwort-Setzen (kein Passwort wird je
  erzeugt oder kommuniziert), Audit `account_create`. Idempotent (`liw_account_exists`); nicht
  freigegeben → `liw_not_approved`; vorhandener WP-Benutzer zur E-Mail wird verknüpft und erhält
  die Partnerrolle zusätzlich.
- **Onboarding Board:** neue Spalte „Partnerkonto" – Knopf „Partnerkonto anlegen" nur bei Status
  `approved`, sonst Link auf den Benutzer bzw. Hinweis „nach Freigabe".
- `liw_partner_extra`: additive Spalte `wp_user_id` + Index (dbDelta, kein Datenverlust).
- Handbuch: Absatz zur Kontoanlage im Onboarding-Abschnitt.

### Entscheidung / Analyse (geschützter Partner-Download, Joseph White „ja" zu Option A)
- Core-Bestandsaufnahme: `Modules\Documents` hat ein sehr gutes Sicherheitsmuster, ist aber
  gastgebunden; das Core-Partner-Portal ist eine Bearer-Token-API für eine App ohne Website-Login
  und ohne Händlerrolle; Medienbibliothek-Dateien sind immer per URL öffentlich (CI-005 schützt
  nur die Anzeige). Daher Option A (eigener Bereich im Plugin nach Core-Muster), Migrationspfad
  zu B (Core-Verallgemeinerung). Diese Auslieferung ist **Stufe 1** (Rolle + Konto); Stufe 2
  (Dokumentenbereich) folgt nach zwei Vorab-Entscheidungen (s. `docs/LIW_TODO.md`).
- **ANNAHME-LIW-11:** Konten werden nicht automatisch bei Freigabe angelegt, sondern per
  explizitem zweitem Schritt im Board (konservativer Default bis zur Entscheidung).

### Geprüft
- `tests/run-tests.php`: 101/101 (keine neuen Klassen; Syntax/strict_types/COMMENT-Regel grün).
- `scripts/liw-selftest.php`: [1] Rolle existiert mit genau den erwarteten Caps und ohne
  Backend-Caps; [5] Kontoanlage für die freigegebene Testanfrage (Rolle, Rückverweis,
  `wp_user_id`, keine Backend-Rechte), Idempotenz, Ablehnung für nicht freigegebene Anfrage;
  Mail im Testlauf umgeleitet; Testbenutzer werden wieder gelöscht. Docker-Lauf steht aus.

## [0.1.0-alpha.20] – 2026-09-18 – Drei weitere anonymisierte Grafiken (LP-03, LP-04, LP-12)

### Hinzugefügt
- `assets/img/liw-target-model.svg` (**LP-03 Zielbild**: Zentral-System → Interface LogiQ →
  lokale Händler-/Lieferanten-/Kundensysteme, Rollen Vermittlung/Prüfung/Übersetzung, Hinweis
  „nie direkt mit dem Zentral-System"), `assets/img/liw-magic-cube.svg` (**LP-04**: isometrischer
  Würfel mit den sieben Pflichtenheft-Bausteinen Sandbox, synthetische Daten, Schnittstellentests,
  Fehlerfälle, Lasttests, Verifizierung, Validierung), `assets/img/liw-roadmap.svg` (**LP-12**:
  sechs Phasen Contract Model → … → Global Rollout als Zeitstrahl ohne Termine). Alle Inhalte
  wörtlich aus Pflichtenheft §8; Farben ausschließlich über `var(--ary-*, Fallback)`;
  `role="img"` mit `<title>/<desc>`; visuell per Rendering geprüft.
- `SectionGraphicView::GRAPHICS`: Whitelist um `target-model`, `magic-cube`, `roadmap` erweitert.
  Bauplan: LP-03/LP-04/LP-12 betten die Grafiken ein (neu angelegte Abschnitte); Handbuch nennt
  alle fünf Grafiknamen und den Handgriff für bereits angelegte Abschnitte.

### Entscheidung (Joseph White, 18.09.2026 – Nutzung der internen Prozess-PDF)
- Öffentlich **nicht** (reale API-Endpunkt-/Systemnamen; §17; Sicherheitsziel „absolut sicher gegen
  Angriffe von außen"). Stattdessen „beides": anonymisierte Grafiken jetzt (diese Auslieferung),
  Analyse eines geschützten Partner-Downloads als nächste Scheibe (`docs/LIW_TODO.md`).
  Hinweis: Die PDF lag Claude in dieser Sitzung nicht mehr vor – die Grafiken beruhen daher auf
  dem Pflichtenheft-Wortlaut, nicht auf PDF-Details.

### Geprüft
- `tests/run-tests.php`: 101/101 – neu: alle Whitelist-Grafiken vorhanden, XML-wohlgeformt, mit
  passender Klasse; Bauplan-Grafiknamen alle in der Whitelist.
- `scripts/liw-selftest.php` [10]: drei Prüfungen (Rendering mit `<title>/<desc>`). Docker-Lauf steht aus.

## [0.1.0-alpha.19] – 2026-09-18 – LP-13 Kontaktformular + Kontaktanfragen-Board (§22/§24)

### Hinzugefügt
- **`[liw_contact_form]`** (`Contact\ContactForm`, LP-13): qualifiziertes Anfrageformular exakt nach
  Pflichtenheft §22 Feldliste – Organisation*, Kontaktperson*, geschäftliche E-Mail*, Telefon,
  Land/Region* (Auswahl), Rolle* (Zentrale/Händler/Lieferant/Technologiepartner/Sonstige), lokales
  ERP/CRM, Projektinteresse* (Mehrfachauswahl), Nachricht*, Datenschutzeinwilligung*, **separate**
  Marketingeinwilligung (§22: „Marketing- und Kontaktzweck dürfen nicht gekoppelt werden"). Gleiche
  Schutzmechanik wie das Onboarding-Formular (Nonce, Honeypot mit stillem Erfolg, admin-post für
  eingeloggte und anonyme Besucher), gleiche `liw-*`-Klassen, kein Inline-CSS/JS.
- **`Contact\ContactService`**: serverseitige Validierung (AC-008) mit klaren Fehlercodes,
  Speicherung, Einwilligungsprotokoll mit Textversion/Zeitstempel (§24), Benachrichtigung der
  Empfänger per `wp_mail()` (Klartext), Audit nur mit Klassifizierung (Rolle/Region/Interessen,
  keine personenbezogenen Freitexte – Datensparsamkeit), Statuspflege, `delete()` inkl.
  Einwilligungen (§24 Löschprozess).
- **`Contact\ContactSchema`**: neue Tabelle `liw_contact_request` (Kategorie B). Datenmodell-Prüfung:
  Eine Kontaktanfrage ist kein Partner – Rollen „Zentrale"/„Sonstige" dürfen keinen
  `ary_partners`-Datensatz erzeugen; `liw_partner_extra` passt daher nicht. Kein eigenes
  Einwilligungsmodell, sondern Wiederverwendung von `liw_consent_log`.
- **`liw_consent_log`: neue Spalte `request_kind`** (`onboarding` | `contact`, Default `onboarding`,
  additiv per dbDelta, Index `idx_kind_request`). Trennt die ID-Räume von `ary_partners.id` und
  `liw_contact_request.id` im selben Protokoll. `ConsentLogService::record()`/`has_consent()` mit
  optionalem 4. Parameter – alle bestehenden Aufrufe und Altdaten bleiben unverändert gültig;
  neu `delete_for_request()`.
- **Achtes Admin-Board „Kontaktanfragen"** (`Admin\Pages\ContactBoardPage`, Capability
  `liw_view_onboarding` – gleiche Zielgruppe wie Onboarding, keine neue Capability): seitenweise
  Liste (AdminPagination), Status Neu → In Bearbeitung → Abgeschlossen, Löschen.
- Bauplan: LP-13 bettet `[liw_contact_form]` ein. CSS: Formular-Regeln auf `.liw-contact-form`
  erweitert (Selektorlisten statt Kopie), Fieldset für die Mehrfachauswahl. `FrontendAssets`:
  fünfter Auslöser. Handbuch: neuer Abschnitt 6, Abschnitte 7/8 nachnummeriert, Hinweis für
  bereits vor alpha.19 angelegte LP-13-Entwürfe (Shortcode einmalig von Hand ergänzen – der
  Knopf überschreibt nie).

### Annahmen (Pflichtenheft nennt keine Wertelisten/Empfänger)
- **ANNAHME-LIW-8** Land/Region: Weltregionen-Liste, Filter `liw_contact_regions`.
- **ANNAHME-LIW-9** Projektinteresse: Optionen entlang der Landingpage-Bausteine, Filter
  `liw_contact_interests`.
- **ANNAHME-LIW-10** Empfänger: WP-Admin-E-Mail bis zur CRM-/Empfängerdefinition (Pflichtenheft
  §31 offener Punkt der Projektleitung), Filter `liw_contact_recipients`; keine CRM-Übergabe.

### Geprüft
- `tests/run-tests.php`: 99/99 grün (neue Klassen in Syntax-/strict_types-/COMMENT-Prüfung,
  `liw_contact_form` als bekannter Shortcode im Bauplan-Test).
- Fachlogik ohne WP mit Stubs geprüft (15/15): gültige Anfrage, Interessen-Whitelist, genau eine
  Consent-Zeile `kind=contact`, Marketing nur bei separater Zustimmung, Mail an Admin mit
  Rollen-Label und ohne HTML, alle sieben Ablehnungspfade mit korrektem Fehlercode,
  XSS-Tags aus Feldern entfernt.
- `scripts/liw-selftest.php`: Tabelle `liw_contact_request` und Spalte `request_kind` in [0];
  neuer Abschnitt [5b] mit 15 Prüfungen (Ablehnungen, Anlage ohne Partner-Datensatz,
  Consent-Trennung, Status, Liste, Shortcode/Honeypot, Löschprozess); Mail im Testlauf per
  `wp_mail`-Filter umgeleitet. Docker-Lauf durch Joseph steht aus.

### Bewusst nicht Teil dieser Auslieferung
- Export personenbezogener Anfragen (§24), CRM-Übergabe, sprachabhängige
  Datenschutztext-Versionierung, fachliche Wertelisten – s. `docs/LIW_TODO.md`.

## [0.1.0-alpha.18] – 2026-09-18 – Zusammengesetzte Landingpage (Shortcode `[liw_landingpage]`)

### Hinzugefügt
- Vierter öffentlicher Shortcode **`[liw_landingpage]`** (`Frontend\LandingpageView`): rendert alle
  Abschnitte mit Status `publish` in Reihenfolge (`menu_order`, dann Titel) als `<section>`-Blöcke
  mit `<h2>`-Titel und Inhalt. Der Inhalt läuft durch den regulären `the_content`-Filter – Blöcke,
  eingebettete Shortcodes (`[liw_graphic]`, `[liw_world_connections_map]`, `[liw_onboarding_form]`)
  und die Core-Übersetzung werden wie in Einzelansichten aufgelöst. Damit steuert der
  Freigabeworkflow des Content Boards direkt, was öffentlich sichtbar ist: nur „Veröffentlicht".
- Anker je Abschnitt aus dem Bauplan-Code (`#lp-07`), Fallback Post-Slug; `sanitize_html_class()`.
- Leerzustand: Besucher sehen nichts; angemeldete Redakteure (`liw_manage_content`) einen Hinweis.
- Rekursionsschutz, falls ein Abschnitt selbst `[liw_landingpage]` enthält; globaler `$post` wird
  nach dem Rendern wiederhergestellt.
- Kein eigenes Template/Page-Builder: Redaktion legt eine normale WP-Seite an und setzt den
  Shortcode hinein (WP-Bordmittel, CLAUDE.md Abschnitt 5). `FrontendAssets` lädt das Stylesheet
  auch auf der Trägerseite (deckt die nur in Abschnitten eingebetteten Shortcodes mit ab).
- `assets/css/liebherr-frontend.css`: `.liw-landingpage*` (Abschnittsabstände, Trennlinie,
  Titel in Akzentfarbe, `scroll-margin-top` für Anker, Leerzustand) – Design-Tokens, kein Inline-CSS.
- Handbuch: Abschnitt 7 umbenannt in „Landingpage im Frontend – Shortcodes & Design System",
  Anleitung zur Trägerseite. `docs/LIW_LANDINGPAGE_KONZEPT.md`, `docs/LIW_TODO.md` (neuer Punkt
  „Landingpage-Feinheiten": Ankernavigation, Sprache/SEO, Hero-Motiv).

### Geprüft
- `tests/run-tests.php`: 90/90 grün (neue Klasse in Syntax-/strict_types-Prüfung).
- `scripts/liw-selftest.php` [6] um sechs Prüfungen erweitert: Registrierung, veröffentlichter
  Abschnitt mit Titel und Code-Anker sichtbar, eingebetteter `[liw_graphic]` als Inline-SVG
  aufgelöst, Entwurf unsichtbar, Wrapper-Klasse, `get_published_sections()` nur `publish`.
  Docker-Lauf durch Joseph steht aus.

### Bewusst nicht Teil dieser Auslieferung
- Ankernavigation/Sprungleiste, Sprachumschaltung und SEO-Metadaten der Trägerseite (offene
  I18nSeo-Frage), Hero-Bildmotiv, Marketingtexte (Redaktion). S. `docs/LIW_TODO.md`.

## [0.1.0-alpha.17] – 2026-09-18 – Bauplan LP-01…LP-14 + Standard-Abschnitte per Knopf anlegen

### Hinzugefügt
- **`Content\SectionBlueprint`** – einzige Quelle der 14 Landingpage-Abschnitte laut Pflichtenheft §8:
  Code, Titel, Reihenfolge (10…140) und Redaktionsvorgabe (Kurzinhalt wörtlich aus der
  Pflichtenheft-Tabelle) sowie der bereits gebaute Baustein, wo vorhanden (LP-06
  `[liw_world_connections_map]`, LP-07/LP-08 `[liw_graphic]`, LP-11 `[liw_onboarding_form]`).
  Erzeugt Block-Editor-Markup (Absatz + Shortcode-Block), kein Classic-Block. Bewusst KEINE
  erfundenen Marketingtexte („nicht erfinden"): das Plugin liefert die Vorgabe als Hinweis, die
  Redaktion formuliert aus. Keine realen System-/API-/Standortnamen (§17).
- **`Content\SectionSeeder`** – legt fehlende Abschnitte als Entwürfe an. Idempotent über
  Post-Meta `_liw_lp_code` (Zuordnung per Code, nicht per Titel: Titel frei änderbar, nie
  Dubletten; auch Papierkorb zählt als vorhanden – bewusst Gelöschtes wird nicht wiederbelebt).
  Nutzt `wp_insert_post()`/Post-Meta/`menu_order`, kein eigenes Datenmodell; jede Anlage über
  `AuditBridge` protokolliert.
- **Content Board:** Knopf „N fehlende Standard-Abschnitte anlegen" (nur sichtbar, solange Codes
  fehlen; eigener Nonce), Erfolgs-/Fehlermeldung, neue Spalte „Code".
- Handbuch: Abschnitt zur Anlage der Standard-Abschnitte.
- `docs/LIW_LANDINGPAGE_KONZEPT.md`: Hinweis auf Bauplan/Seeder. `docs/LIW_TODO.md`: neuer
  offener Punkt **LP-13 Kontaktformular** (Pflichtenheft verlangt eigenes Formular, getrennt
  vom Onboarding – noch nicht gebaut); Core-Hinweis als erledigt verschoben (alpha.716/717).

### Geprüft
- `tests/run-tests.php`: 88/88 grün – sieben neue Bauplan-Prüfungen ohne WP (14 Codes lückenlos
  in Reihenfolge, Titel/Vorgabe vorhanden, Einbettungen nur auf existierende Shortcodes,
  `menu_order`, Block-Markup mit Escaping, unbekannter Code leer).
- `scripts/liw-selftest.php` [6] um sechs Prüfungen erweitert (Bauplan, Meta-Zuordnung,
  Idempotenz-Sicht `missing + vorhanden = 14`) plus Info-Zeile zum Bestand. Der Seeder selbst
  wird im Selbsttest bewusst nicht ausgeführt (würde echte Abschnitte anlegen) – Live-Prüfung
  durch Joseph per Knopf im Content Board.

## [0.1.0-alpha.16] – 2026-09-18 – Bugfix: dbDelta-Fehler beim Schema-Abgleich

### Behoben
- Bei jedem Versionswechsel (`maybe_upgrade_database()` → `dbDelta()`) erschienen sechs
  SQL-Syntaxfehler `ALTER TABLE {$p}liw_* ADD COLUMN ) DEFAULT CHARACTER SET …` im Log
  (Befund Docker-Praxistest alpha.15, erster Request nach dem Update). Ursache: `dbDelta()`
  extrahiert den Spaltenblock mit einem gierigen Regex bis zur **letzten** `)` der
  Anweisung; unsere Tabellen-`COMMENT`s enthielten Klammern (z. B. `… (Pflichtenheft §17
  liw_interface)`), sodass dbDelta die Zeile `) DEFAULT … COMMENT='…` für eine neue Spalte
  namens `)` hielt. Die Tabellen selbst waren nie betroffen (Fehlerquery schlug einfach fehl),
  aber sechs Fehler pro Update verstoßen gegen „Fehlervermeidung".
- Fix: Klammern in allen sechs Tabellen-`COMMENT`s durch Kommata ersetzt (Inhalt unverändert).
  Kein Datenmodell-Eingriff; bestehende Installationen behalten den alten COMMENT-Text in
  MySQL (dbDelta ändert Tabellen-Kommentare nicht), was fachlich irrelevant ist.

### Geprüft
- `tests/run-tests.php`: 77/77 grün – neue statische Prüfung „Tabellen-COMMENT ohne
  Klammern" je Schema-Datei (Regressionsschutz ohne WP).
- `scripts/liw-selftest.php` [0]: neue Live-Prüfung „dbDelta-Wiederholung (create_tables)
  ohne DB-Fehler" – wäre vor dem Fix rot gewesen. Docker-Lauf durch Joseph (`SELFTEST-J8NV1B6A`):
  68/68, keine `ALTER TABLE`-Fehler mehr – bestätigt.

### Hinweis an den Core (nicht umgesetzt, außerhalb dieses Plugins)
- Dasselbe Muster (Klammern im Tabellen-`COMMENT`) findet sich in mindestens fünf
  Core-Tabellen (`src/Core/VersionManager.php` ×3, `src/Language/TranslationRepository.php`,
  `src/Modules/Notification/NotificationSchema.php`). Dort dürfte bei jedem Core-Versionswechsel
  derselbe Log-Fehler auftreten. Dokumentiert in `docs/LOGBUCH_TECHNIK.md`, Entscheidung liegt
  beim Core (Refactoring nur auf Freigabe).

## [0.1.0-alpha.15] – 2026-09-18 – LP-07/LP-08-Grafiken einbettbar (Shortcode `[liw_graphic]`)

### Hinzugefügt
- Dritter öffentlicher Shortcode **`[liw_graphic name="data-model"]`** (LP-07 Data Model)
  bzw. **`name="process-worlds"`** (LP-08 Process Worlds), optional `caption="…"`
  (`Frontend\SectionGraphicView`). Bettet die mit alpha.12 gelieferten, anonymisierten SVGs
  **inline** in `liw_section`-Inhalte ein – bewusst kein `<img src>`, weil nur Inline-Markup
  die `var(--ary-*)`-Design-Tokens der Seiten-CSS übernimmt (Konzept-Doku „Offene Punkte",
  jetzt eingelöst). Erster sichtbarer Landingpage-Baustein, der über das Content Board
  (alpha.13) in einen Abschnitt gesetzt werden kann.
- Sicherheit (Anforderung Joseph 18.09.2026, „absolut sicher gegen Angriffe von außen"):
  `name` wird ausschließlich gegen eine feste Whitelist (`SectionGraphicView::GRAPHICS`)
  aufgelöst, kein Dateipfad-Parameter; zusätzlicher `realpath()`-Guard auf `assets/img/`.
  Unbekannte Namen, Traversal-Versuche und fehlendes `name` liefern eine leere Ausgabe.
  `caption` wird sanitisiert und escaped.
- `assets/css/liebherr-frontend.css`: Klassen `.liw-graphic-figure`, `.liw-graphic-figure__caption`
  (responsive, Design-Tokens, kein Inline-CSS). `FrontendAssets` lädt das Stylesheet jetzt
  auch auf Seiten mit `[liw_graphic]`.
- Handbuch: Anleitung zum Einbetten im Abschnitt „Content Board", Shortcode-Liste ergänzt.
- `docs/LIW_LANDINGPAGE_KONZEPT.md`: LP-07/LP-08 auf „einbettbar" gesetzt, Einbettungs-Punkt
  als eingelöst markiert.

### Geprüft
- `tests/run-tests.php`: 72/72 grün (neue Klasse in Syntax-/strict_types-Prüfung).
- Shortcode-Logik zusätzlich ohne WP mit Stubs geprüft (8/8: Rendering beider Grafiken,
  Caption-Escaping, leere Ausgabe bei unbekanntem Namen/Traversal/fehlendem Attribut).
- `scripts/liw-selftest.php` Abschnitt [10] um acht Prüfungen erweitert (Registrierung,
  Rendering, Escaping, Negativfälle, CSS-Auslöser) – Docker-Lauf durch Joseph steht aus.

### Annahme
- **ANNAHME-LIW-7:** Jede Grafik erscheint pro Seite höchstens einmal (LP-07/LP-08 sind je
  ein Abschnitt). Die SVGs tragen feste `id`s für `<title>/<desc>`; bei doppelter Einbettung
  wären diese ids mehrfach vorhanden. Additiv lösbar (id-Suffix), falls je nötig.

## [0.1.0-alpha.14] – 2026-09-18 – Bugfixes aus dem Docker-Praxistest

### Behoben
- **`CoreBridge\AuditBridge::log()`** übergab an `AuditService::log()` fälschlich den
  Plugin-Slug `'liebherr-interface-world'` (24 Zeichen) als 7. Parameter. Dieser Parameter
  ist jedoch `string $actor_type` (Core-Spalte `actor_type VARCHAR(20)` in
  `{$wpdb->prefix}ary_audit_log`) – kein Modulbezeichner. Dadurch scheiterte praktisch
  **jeder** Audit-Log-Eintrag dieses Plugins in echtem WordPress/MySQL mit „value too long
  or contains invalid data" (nicht fatal, aber Nachvollziehbarkeit ging verloren – SEC-005).
  Behoben durch `$actor_id > 0 ? 'admin' : 'system'`, analog zur bestehenden Konvention im
  Core selbst (`PlatformResetService`). Gefunden über den von Joseph in Docker ausgeführten
  Selbsttest `scripts/liw-selftest.php` (56/59 bestanden, s. Logbuch).
- **`scripts/liw-selftest.php`**: drei Prüfungen (`get_all()`/`get_worlds()`/
  `get_scenarios_for_world()` enthalten den neu angelegten Datensatz) verglichen eine
  `int`-ID strikt (`in_array(..., true)`) gegen `$wpdb->get_results()`-Ergebnisse, deren
  Spalten mysqli-bedingt als `string` zurückkommen – der strikte Vergleich schlug dadurch
  immer fehl, obwohl `InterfaceCatalogService::get_all()`/`SimulationService::get_worlds()`/
  `get_scenarios_for_world()` selbst korrekt arbeiten (kein Fehler im Anwendungscode).
  Behoben durch `array_map('intval', ...)` vor dem Vergleich. Reiner Testskript-Fehler,
  keine Auswirkung auf das Plugin im laufenden Betrieb.

### Geprüft
- `tests/run-tests.php`: 70/70 grün (unverändert, keine Signaturänderung nach außen).
- Docker-Praxistest `scripts/liw-selftest.php` erneut durch Joseph ausgeführt (18.09.2026,
  Lauf `SELFTEST-DDIUJH0J`): **59 bestanden, 0 fehlgeschlagen**, kein
  `AuditService: INSERT failed` mehr im Log – beide Fixes gegen die reale Umgebung bestätigt.

## [0.1.0-alpha.13] – 2026-09-18 – Content Board (§19, Grundgerüst)

### Hinzugefügt
- Sechstes/siebtes Admin-Board „Content Board" (`ContentBoardPage`, Capability
  `liw_manage_content`): Übersicht aller `liw_section`-Beiträge (LP-01…LP-14) mit
  Reihenfolge, Status und Aktionen (Bearbeiten, Vorschau, Statuswechsel).
  Freigabeworkflow Entwurf → Prüfung → freigegeben → veröffentlicht per Zeilen-Aktion
  (`wp_update_post()`, `AuditBridge::log()`), da der Block-Editor den seit alpha.1
  registrierten Custom-Status `liw_approved` nicht in seinem eigenen Status-Dropdown
  anbietet.
- `CPT\LiwSectionCpt::add_status_badge()`: zeigt „Freigegeben" als Status-Badge neben dem
  Titel in der nativen Listenansicht (WP zeigt das für Custom-Status nicht automatisch an).

### Geprüft vor Umsetzung / bewusst wiederverwendet (keine Doppelentwicklung)
- Titel, Inhalt, Reihenfolge (`page-attributes`) und **Revisionen vergleichen/
  wiederherstellen** funktionieren bereits nativ, da die CPT diese Features seit alpha.1
  unterstützt – dafür war kein zusätzlicher Code nötig.

### ANNAHME-LIW-6
- Reihenfolge wird über das native „Reihenfolge"-Feld im Beitrags-Editor gepflegt, nicht
  über Drag-and-Drop in diesem Board (YAGNI, additiv nachrüstbar ohne Datenmodelländerung).

### Bewusst nicht Teil dieser Auslieferung (Entscheidung Joseph White, 18.09.2026: Grundgerüst zuerst)
- Drag-and-Drop-Reihenfolge, Zeitsteuerte Veröffentlichung über den nativen `future`-Status
  hinaus, Vorschau je Sprache/Gerät, CTA-Ziel-Picker, Medien-Picker-Beschränkung auf
  freigegebene Bibliothek, Pflichtfeldprüfung/Übersetzungswarnungen. Details:
  `docs/LIW_TODO.md`.

### Selftest
- `tests/run-tests.php`: 70/70 Prüfungen grün (vorher 68/68). `scripts/liw-selftest.php`
  um Abschnitt [6] Content Board erweitert (Statuswechsel draft→pending→liw_approved→
  publish, Status-Badge-Filter-Registrierung, `post_status=any`-Sichtbarkeit).

## [0.1.0-alpha.12] – 2026-09-18 – Landingpage-Konzept + anonymisierte LP-07/LP-08-Grafiken

### Hinzugefügt
- `docs/LIW_LANDINGPAGE_KONZEPT.md`: beantwortet „Haben wir ein Layout-Konzept für die
  Landingpage?" – Übersichtstabelle aller 14 Pflichtenheft-Abschnitte (LP-01…LP-14, §8)
  mit Umsetzungsstand (gebaut/offen) und Zuordnung zu bestehenden Boards/Shortcodes.
- `assets/img/liw-data-model.svg` (LP-07 Data Model): schematische Darstellung Interface
  LogiQ als zentrale Vermittlungsschicht mit den elf Objektgruppen (Kunde, Kontakt,
  Händler, Maschine, Konfiguration, Angebot, Auftrag, Bedarfsfall, Lieferung, Rechnung,
  Zahlung) – als inline einzubettendes SVG, Farben über `var(--ary-*, Fallback)`.
- `assets/img/liw-process-worlds.svg` (LP-08 Process Worlds): sieben Prozesswelten
  Sales → Configuration → Order → Goods → Finance → Service → Warranty.

### Entscheidung (Joseph White, 18.09.2026)
- Eine hochgeladene interne Prozess-PDF („Liebherr DSC – Schnittstellenprozess
  Neugestaltung", vollständiger BC/NAV-↔-Livision-Integrationsplan mit realen
  API-Endpunktnamen) wird NICHT direkt bzw. in Auszügen auf der öffentlichen Landingpage
  gezeigt – Begründung: Pflichtenheft §17 (strikte Trennung öffentlich/technisch) und die
  Anforderung, dass die Interface-Logik sicher gegenüber externen Angriffen sein muss.
  Stattdessen anonymisierte Grafiken für LP-07/LP-08, deren fachliche Struktur (Objekte,
  Prozessreihenfolge) sich mit der PDF deckt, ohne reale System-/API-Namen zu zeigen.
  Details: `docs/LIW_LANDINGPAGE_KONZEPT.md`.

### Bewusst nicht Teil dieser Auslieferung
- Platzierung der beiden neuen Grafiken auf der tatsächlichen Landingpage – wartet auf
  das Content Board (§19, weiterhin offen, s. `docs/LIW_TODO.md`).
- Visuelles Gesamt-Layout/Wireframe für alle 14 Abschnitte.

## [0.1.0-alpha.11] – 2026-09-18 – Docker-Praxistest (Integrations-Selbsttest)

### Hinzugefügt
- `scripts/liw-selftest.php`: Integrations-Selbsttest analog zu Core's
  `scripts/yb-selftest.php` – läuft in der echten WordPress-/Docker-Umgebung (nicht nur
  `php -l`/Statuslogik wie `tests/run-tests.php`). Prüft end-to-end: DB-Schema-Version und
  alle sechs Tabellen vorhanden, Capabilities an `administrator` vergeben, Interface Board
  (Anlage → Lifecycle → öffentlicher Katalog), Simulation Board (Welt → Szenario),
  World Connections Map (Anlage → Freigabe → Statuspflege → Shortcode-Rendering → Löschung),
  Onboarding-Formular (Ablehnung ohne Einwilligung, Anlage, Core-Partner bleibt neutral
  `general`, Consent-Log, Freigabe spiegelt `ary_partners.status`, Pagination, Shortcode
  inkl. Honeypot), Media Board (CI-005-Freigabe-Meta, Hook-Registrierung), Design-System-
  Assets (beide Stylesheets vorhanden), Programmierlogbuch/To-Dos (Dateien vorhanden,
  `MarkdownBridge` liefert HTML). Testdaten sind `SELFTEST-`-präfigiert und werden am Ende
  vollständig entfernt (`finally`-Block, läuft auch bei fehlgeschlagenen Prüfungen).
  Nur in `WP_ENVIRONMENT_TYPE=development` ausführbar (Terminal-Regel).

### Geändert
- `tests/run-tests.php`: Kommentar aktualisiert – verweist jetzt auf das tatsächlich
  ausgelieferte `scripts/liw-selftest.php` statt auf eine „Folgeauslieferung".

### Ausführung (Terminal-Regel – bitte durch Joseph in der Docker-Dev-Umgebung)
```
docker exec araliya_wordpress php /var/www/html/wp-content/plugins/liebherr-interface-world/scripts/liw-selftest.php
```

## [0.1.0-alpha.10] – 2026-09-18 – Feinschliff an den Boards + Nachvollziehbarkeit

### Hinzugefügt
- **Pagination** für Media Board (`WP_Query` statt `get_posts()`, liefert `found_posts` mit)
  und Onboarding Board (`OnboardingService::get_all_requests()` jetzt mit `LIMIT`/`OFFSET`,
  neue Methode `count_all_requests()`). Beide Boards waren zuvor als „bewusst nicht Teil
  dieser Auslieferung" (Media Board, alpha.7) bzw. mit einem ungebremsten JOIN
  (Onboarding Board, alpha.6) dokumentiert. Gemeinsame Ansicht: `Admin\AdminPagination`
  (DRY statt Duplikat in beiden Boards).
- `Admin\AdminAssets` + `assets/css/liebherr-admin.css`: löst drei Inline-Style-
  Fundstellen ab (Media-, Connection- und Onboarding-Board), die gegen CLAUDE.md
  Abschnitt 5 „keine Inline-Styles" verstießen. Lädt nur auf den eigenen Board-Seiten.
- **Programmierlogbuch** (`docs/LIW_PROGRAMMIERLOGBUCH.md`, Reiter „🧾 Programmierlogbuch"):
  protokolliert ab sofort jede Quellcodeänderung auf Datei-/Klassenebene, ergänzend zu
  diesem Changelog (fachliche Sicht) und dem Core-Logbuch (Entscheidungs-Warum).
  Rückwirkend für alpha.1–alpha.9 aus diesem Changelog rekonstruiert.
- **To-Dos** (`docs/LIW_TODO.md`, Reiter „📋 To-Dos"): kuratierte Liste offener Punkte
  (Content Board §19, Simulation-Status-Übergänge, echte Karte für die World Connections
  Map, I18nSeo-Architekturfrage, Docker-Praxistest, Mehrsprachigkeits-Audit) – jeweils mit
  Quellenangabe, nichts neu erfunden.
- `CoreBridge\MarkdownBridge`: Wrapper um Core `Modules\Deployment\Admin\HandbookRenderer`
  (verifiziert generisch), für Programmierlogbuch und To-Dos genutzt statt einer zweiten
  Markdown-Implementierung.
- Handbuch vollständig überarbeitet: deckt jetzt alle sechs Boards, beide Frontend-
  Shortcodes sowie Programmierlogbuch/To-Dos ab (vorher: nur Interface-Board-Grundgerüst).

### Selftest
- `tests/run-tests.php`: 68/68 Prüfungen grün (vorher 58/58; +10 durch fünf neue Klassen).

## [0.1.0-alpha.9] – 2026-09-18 – Design-System-Anbindung (Frontend-Shortcodes)

### Hinzugefügt
- `assets/css/liebherr-frontend.css` – Styling für `[liw_onboarding_form]` und
  `[liw_world_connections_map]`, ausschließlich über die zentralen ARALIYA-Design-Tokens
  (`--ary-bg`, `--ary-card`, `--ary-text`, `--ary-accent`, `--ary-highlight` aus
  `araliya-platform-core/src/Frontend/DesignSystem.php::generate_css()`, ARY-DP-1.0.0,
  sowie `--ar-font-*`-Typografie-Variablen), mit Fallback-Werten. Keine eigenen,
  hartkodierten Markenfarben – folgt derselben Namenskonvention wie Core's eigene
  seitenspezifische Stylesheets (`.ary-apt-*`, `.ary-ev-*` → hier `.liw-onboarding-form`,
  `.liw-connections-map`).
- `Frontend\FrontendAssets` – lädt das Stylesheet nur auf Seiten, die tatsächlich einen
  der beiden Shortcodes enthalten (`has_shortcode()`-Prüfung), nicht global auf jeder
  Seite (Performance).
- `.liw-visually-hidden`-Utility-Klasse ergänzt – löst die in alpha.6 dokumentierte
  ANNAHME-LIW-5 (Honeypot-Feld im Onboarding-Formular) jetzt tatsächlich ein.

### Geprüft vor Umsetzung
- `araliya-platform-core` hat keine einzelne globale Komponentenbibliothek für
  Buttons/Cards; stattdessen definiert jede Frontend-Seite ihre eigenen, namensraum-
  gescopten Selektoren (`.ary-apt-*` in `apartment-page.css`, `.ary-ev-*` in
  `event-page.css` usw.), alle aufbauend auf den gemeinsamen Design-Tokens. Diese
  Konvention wurde hier übernommen, statt eine nicht existierende „globale
  Komponenten-CSS" zu suchen oder zu erfinden.

## [0.1.0-alpha.8] – 2026-09-18 – World Connections Map (Frontend-Visualisierung)

### Hinzugefügt
- Öffentlicher Shortcode `[liw_world_connections_map]`: zeigt ausschließlich freigegebene
  Verbindungen (`ConnectionService::get_public()`, `public_flag = 1`) gruppiert nach
  Region, mit Partnertyp (Händler/Lieferant/Kunde) und Anzeigestatus. Reines semantisches
  HTML, kein Inline-CSS/JS – Gestaltung über das zentrale Design System.

### Entscheidung (Joseph White, 18.09.2026)
- Darstellung als gruppiertes Regionen-Grid statt einer geografischen Karte:
  `liw_connection` speichert Region nur als Freitext, keine Koordinaten. Eine echte Karte
  hätte eine Datenmodelländerung (latitude/longitude, Kategorie B) plus Kartenbibliothek
  erfordert – bewusst nicht umgesetzt, bleibt als spätere Option offen, ohne dass die
  jetzt gelieferte Datenpflege (alpha.5) davon berührt wäre.

### Damit abgeschlossen
- Alle fünf ursprünglich in alpha.1 als „offene Punkte" benannten Bereiche sind jetzt
  ausgeliefert: Interface Board, Simulation Board, World Connections Map (Datenpflege +
  Frontend), Onboarding-Formular, Media Board.

## [0.1.0-alpha.7] – 2026-09-18 – Media Board (§18)

### Hinzugefügt
- Fünftes Admin-Board „Media Board" (Capability `liw_manage_content`, ANNAHME-LIW-3-
  Präzedenzfall): Bulk-Übersicht der Medienbibliothek mit einem Formular für alle
  Zeilen gleichzeitig (Copyright/Rechteinhaber, Asset-Quelle, CI-005-Freigabe-Checkbox
  je Medium, ein Speichern-Klick für alle Änderungen). Filter „Alle/Freigegeben/Nicht
  freigegeben" über die bestehenden `_liw_media_*`-Attachment-Metafelder (kein neues
  Datenmodell, kein eigenes Mediensystem).

### Bugfix (Fund bei dieser Gelegenheit)
- `CoreBridge\MediaBridge::add_fields()` zeigte den CI-005-Freigabestatus bisher als
  reinen Anzeigetext („Ja"/„Nein", `input => 'text'`) im nativen Anhang-Editor – ohne
  Checkbox-Semantik. `save_fields()` werte dieses Feld zudem gar nicht aus: der
  Freigabestatus war über den Standard-Medien-Dialog faktisch weder erkennbar noch
  änderbar, nur über direkten Datenbankzugriff. Fix: echte Checkbox (`input => 'html'`,
  dokumentiertes WP-Muster für boolesche Attachment-Felder) + Auswertung in
  `save_fields()`. Das neue Media Board ist der primäre, empfohlene Weg zur
  Bulk-Freigabe; der Einzel-Anhang-Dialog funktioniert jetzt zusätzlich korrekt.

### Bewusst nicht Teil dieser Auslieferung
- Paginierung (aktuell 50 neueste Medien) – ergänzen, sobald die Bibliothek in der
  Praxis größer wird.

## [0.1.0-alpha.6] – 2026-09-18 – Onboarding-Formular (§22)

### Hinzugefügt
- Öffentlicher Shortcode `[liw_onboarding_form]` (Frontend, kein Login nötig): Formular für
  Händler-/Lieferanten-/Kundenanfragen mit Pflicht-Datenschutz-Checkbox und optionaler
  Marketing-Einwilligung, verarbeitet über `admin-post.php` (funktioniert für eingeloggte
  UND anonyme Besucher). Honeypot-Feld gegen einfache Bots.
- `CoreBridge\PartnerBridge`: Wrapper um Core `PartnerService` (verifiziert generisch, keine
  Guest-Bindung) – jede Onboarding-Anfrage legt einen echten `ary_partners`-Eintrag an
  (Status `pending` bis zur Freigabe), statt eine Parallelstruktur zu bauen.
- Neue Tabelle `liw_partner_extra` (Liebherr-spezifische Zusatzfelder: Klassifizierung
  dealer/supplier/customer, gewünschte Schnittstellen, Freitext-Nachricht, Freigabestatus)
  – 1:1 zu `ary_partners.id`, keine Datenkopie der Core-Felder.
- Viertes Admin-Board „Onboarding" (Capability `liw_view_onboarding`, seit alpha.1
  vorbereitet, jetzt erstmals genutzt): Liste aller Anfragen inkl. Core-Partnerdaten
  (ein JOIN, keine N+1-Zugriffe) mit Statuspflege (new/in_review/approved/rejected) –
  spiegelt bei approved/rejected automatisch den `ary_partners.status`.
- Datenschutz-Einwilligung wird über die bestehende `ConsentLogService` (seit alpha.1)
  protokolliert – keine neue Consent-Infrastruktur.
- Selbstheilender DB-Schema-Abgleich (`maybe_upgrade_database()`): DB-Tabellen wurden bisher
  nur im Aktivierungshook angelegt; ein reines Datei-Update (wie bei diesem Release) hätte
  `liw_partner_extra` sonst nicht erzeugt, ohne das Plugin erneut zu deaktivieren/aktivieren
  (dieselbe Problemklasse wie der Capability-Bugfix in alpha.3). Läuft jetzt bei jedem
  Request einmal pro tatsächlicher Versionsänderung.

### Wichtiger Befund (vor Implementierung geprüft, nicht geraten)
- Core `PartnerService::VALID_TYPES` erlaubt nur `clinic/doctor/wellness/supplier/
  insurance/general` (Health-Domain-Vokabular) – `dealer` und `customer` sind dort keine
  gültigen Werte. Statt Core's Enum zu erweitern: Liebherr-Partner werden in
  `ary_partners.partner_type` immer als `'general'` angelegt, die eigentliche
  Liebherr-Klassifizierung lebt in `liw_partner_extra.liw_partner_type`. Details:
  `CoreBridge\PartnerBridge`-Klassenkommentar.

### ANNAHME-LIW-5
- Das Honeypot-Feld setzt eine Utility-Klasse `.liw-visually-hidden` im zentralen Design
  System voraus. Ohne sie ist das Feld sichtbar, aber die Formularfunktion selbst bleibt
  unbeeinträchtigt (kein Blocker, nur reduzierter Spam-Schutz).

## [0.1.0-alpha.5] – 2026-09-18 – World Connections Map (Datenpflege, Grundgerüst)

### Hinzugefügt
- Drittes Admin-Board „World Connections" (Untermenü unter „Interface World"):
  Anlage-Formular + Liste für `liw_connection` (Region, Partnertyp, Anzeigestatus
  planned/active/inactive, Öffentlichkeits-Flag) samt Statuswechsel, Freigabe/Rückzug
  und Löschung – Liebherr-Pflichtenheft §17/LP-06.
- `ConnectionService` um `get_all()`, `get()`, `set_display_status()`,
  `set_public_flag()`, `delete()` erweitert (bisher nur `create()`/`get_public()`).
  Jede Änderung über `AuditBridge` protokolliert.
- **ANNAHME-LIW-3** (dokumentiert im Seitenkopf von `ConnectionBoardPage.php`): Da das
  Pflichtenheft keine Rollenzuordnung für die World Connections Map nennt, wird dieselbe
  Capability wie beim Content Board verwendet (`liw_manage_content`) – redaktionelle
  Freigabeentscheidung, welche Region öffentlich erscheint. Bei Bedarf ohne
  Datenmodelländerung auf eine eigene Capability umstellbar.

### Bugfix (Fund bei dieser Gelegenheit)
- Plugin-Header nannte `Requires PHP: 7.4`, der Code verwendet aber bereits seit
  alpha.1 PHP-8.0-Unions (`int|\WP_Error` in `InterfaceCatalogService`,
  `ConnectionService` u. a.) und jetzt zusätzlich `match` (PHP 8.0+) in
  `ConnectionBoardPage`. Auf `Requires PHP: 8.0` korrigiert (Laufzeitumgebung ist
  ohnehin PHP 8.2, s. `docker-compose.yml`) – rein deklarativ, keine Verhaltensänderung.

### Bewusst nicht Teil dieser Auslieferung
- Frontend-Visualisierung der World Connections Map (Karte/Grafik auf der
  Landingpage) – Entscheidung JW 18.09.2026: erst Datenpflege, dann Visualisierung.

## [0.1.0-alpha.4] – 2026-09-18 – Simulation Board (Magic Cube, Grundgerüst)

### Hinzugefügt
- Zweites Admin-Board „Simulation Board" (Untermenü von „Interface World", Capability
  `liw_manage_interfaces` – dieselbe wie Interface Board, s. RoleBridge-Kommentar
  „Interface-/Simulationskatalog pflegen"): Anlage-Formular + Liste für Simulationswelten
  (`liw_simulation_world`), je ausgewählter Welt Anlage-Formular + Liste für deren
  Testszenarien (`liw_test_scenario`) – Liebherr-Pflichtenheft §17 „Magic Cube".
- Navigation Welt → Szenarien über `?world_id=` (reine Ansichtsnavigation, keine
  Datenänderung, daher ohne Nonce).

### Bewusst nicht Teil dieser Auslieferung
- Status-Übergänge (Welt validieren/verwerfen, Szenario als bestanden/fehlgeschlagen
  markieren) – dafür fehlt noch die eigentliche Simulations-Engine-Anbindung; YAGNI,
  bis diese ansteht. `InterfaceCatalogService::set_lifecycle_status()` ist ebenfalls
  noch ohne UI, aus demselben Grund.

## [0.1.0-alpha.3] – 2026-09-17 – Bugfix: Menü „Interface World" für Administrator unsichtbar

### Behoben
- **Kritisch.** `RoleBridge::grant_capabilities()` vergab die Liebherr-Capabilities
  (`liw_manage_interfaces`, `liw_manage_content`, `liw_view_onboarding`) nur an die
  Custom-Rollen `araliya_admin`/`araliya_marketing`, nicht aber an die native
  WordPress-Rolle `administrator`. Der Core folgt hier durchgängig dem Muster
  „administrator erhält zusätzlich zu araliya_admin jede Capability" (s.
  `araliya-platform-core/src/Core/RoleManager.php`). Dadurch blendete WordPress den
  kompletten Menüpunkt „Interface World" für den regulären Administrator-Account aus
  (`add_menu_page()`/`add_submenu_page()` verstecken sich selbst ohne passende Capability).
- Fix: `administrator` in `RoleBridge::FULL_ACCESS_ROLES` aufgenommen (analog Core-
  Konvention). `revoke_capabilities()` entfernt weiterhin bewusst **nicht** von
  `administrator` (Core-Konvention: "Does NOT remove 'administrator'" bei Deaktivierung).
- **Wichtig für bestehende Installationen**: Die Capability-Vergabe läuft nur beim
  Aktivierungshook. Nach diesem Fix muss das Plugin einmal deaktiviert und wieder
  aktiviert werden, damit `administrator` die Capabilities tatsächlich erhält.

## [0.1.0-alpha.2] – 2026-09-17 – Bugfix: Aktivierung schlug immer fehl

### Behoben
- **Kritisch.** `core_is_available()` prüfte mit `class_exists('Araliya\Platform\Core\Core\ModuleInterface')`.
  `ModuleInterface` ist im Core aber als `interface` deklariert, nicht als `class` –
  `class_exists()` matcht keine Interfaces und lieferte **immer** `false`, unabhängig vom
  tatsächlichen Aktivierungsstatus von Core. Dadurch schlug die Aktivierung von
  `liebherr-interface-world` reproduzierbar mit „ARALIYA Platform Core ist nicht aktiv"
  fehl, auch wenn Core nachweislich aktiv war (Diagnose anhand von Docker-Logs, Joseph
  White 17.09.2026).
- Fix: `core_is_available()` prüft jetzt primär mit WordPress' nativer `is_plugin_active(
  'araliya-platform-core/araliya-platform-core.php' )`, mit `interface_exists(
  ModuleInterface )` als Fallback. Konstante `CORE_DEPENDENCY_CLASS` aufgeteilt in
  `CORE_DEPENDENCY_PLUGIN_FILE` und `CORE_DEPENDENCY_INTERFACE`.

## [0.1.0-alpha.1] – 2026-09-17 – Plugin-Grundgerüst + CoreBridge

### Hinzugefügt
- Eigenständiges Plugin `liebherr-interface-world` (zweite Ausnahme von der Ein-Plugin-Regel,
  ADR-LIW-001), PSR-4-Autoloader `Liebherr\InterfaceWorld\`, Aktivierungsprüfung gegen
  `araliya-platform-core` mit Graceful-Degradation-Hinweis statt Fatal Error.
- `CoreBridge\TranslationBridge`, `RoleBridge`, `AuditBridge`, `SeoBridge`, `MediaBridge` –
  Integration gegen verifizierte, tatsächlich generische Core-Services (Details: ADR-LIW-001).
- CPT `liw_section` (14 Landingpage-Abschnitte, Liebherr-Pflichtenheft §8) inkl. eigenem
  Post-Status `liw_approved` für den Freigabeworkflow (Entwurf → Prüfung → freigegeben →
  veröffentlicht) – native WP-Mechanismen statt eigenem Content-Schema.
- DB-Schema für die vier fachlich neuen Tabellen: `liw_interface` (Schnittstellenkatalog),
  `liw_simulation_world` + `liw_test_scenario` (Magic Cube), `liw_connection` (World Connections
  Map) sowie `liw_consent_log` (begründete Ausnahme, s. ADR-LIW-001).
- Erstes Admin-Board „Interface Board" (Liste + Anlage-Formular für den Schnittstellenkatalog),
  eigenes Handbuch (letzter Menüreiter, Handbuch-Regel).
- Selftest `tests/run-tests.php` (Syntax, Coding Standard, Statuslogik) – 38/38 grün.

### Bekannte Einschränkungen / offene Punkte
- Content Board (Redaktionsfunktionen §19), Media Board (Bulk/Freigabeworkflow), World
  Connections Map (Frontend-Visualisierung), Onboarding-Formular (§22, Wiederverwendung von
  `ary_partners` + neue `liw_partner_extra`) sind **nicht** Teil dieser Auslieferung.
- I18nSeo-Anbindung ist eine eigenständige, schlanke Lösung statt Core-Wiederverwendung
  (Core-Router ist auf andere CPTs fest verdrahtet) – offener Punkt an JW (ADR-LIW-001).
- Kein Brand Kit/Liebherr-CI verfügbar – Design-Tokens/Templates sind nicht Teil dieser
  Auslieferung (Phase-3-Plan: erst Grundgerüst + CoreBridge).
- Kein Docker-Dev-Test durchgeführt (Terminal-Regel: Aktivierung/DB-Migration/Permalink-Flush
  muss Joseph in der Docker-Umgebung ausführen und bestätigen – s. Abschlussbericht).
