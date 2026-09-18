# Logbuch für Techniker – Liebherr Interface Solutions

Eigenes Logbuch dieses Plugins, analog zur Konvention von `araliya-platform-core`
(`docs/LOGBUCH_TECHNIK.md`). Entscheidungen, die die **Ein-Plugin-Regel-Ausnahme** und die
**Variante-A-Integrationsentscheidung** selbst betreffen, stehen im Logbuch von
`araliya-platform-core` (Core-Governance). Hier stehen die Entscheidungen, die innerhalb dieses
Plugins gefallen sind.

## Teil II – Sitzungs-Logbuch (neueste zuerst)

### 2026-09-18 · Etappe 4: §24-Export als admin-post-CSV, Medien-Restriktion vertagt (0.1.0-alpha.30)

**Frage/Kontext.** §24 verlangt einen Exportprozess für personenbezogene Anfragen; §19 Pflichtfeld-/
Alt-Text-/Übersetzungswarnungen, CTA-Ziel-Picker und Medien-Picker-Beschränkung.

**Entscheidungen (Claude, Kategorie B).**
- **Export:** eigener `admin_post`-Handler (`ContactExporter`) statt Ausgabe in der Board-Render-Methode –
  sauberer Download (Header + exit), Capability `liw_view_onboarding`, Nonce, Audit `export`. CSV mit
  UTF-8-BOM + Semikolon (Excel-DE); Einwilligungen mit Version/Zeit getrennt ausgewiesen (§24-Zweckbindung).
- **Redaktions-Prüfung:** konkret und ohne externe API – Titel-Pflicht + Bilder ohne Alt-Text
  (reine `count_images_without_alt()`). Übersetzungs-Vollständigkeit gehört ins Sprach-Release-Gate
  (Etappe 6/LANG-006), nicht in diese Prüfung (keine Doppellogik).
- **CTA-Ziele:** leichter `<datalist>` der Abschnitts-Anker statt schwerem JS-Picker („intern auswählen").
- **Medien-Picker-Beschränkung:** weiter vertagt – WP-`ajax_query_attachments`-Kontext unzuverlässig
  (bestehender To-Do); die LIW-eigenen Auswahllisten kennzeichnen den Freigabestatus bereits.

**Quelle/Version.** Release-Plan Etappe 4; Pflichtenheft §19/§22/§24/§26; 0.1.0-alpha.30.

### 2026-09-18 · Etappe 3: Kern-Komponenten datengetrieben, übrige Abschnitte kuratiert (0.1.0-alpha.29)

**Frage/Kontext.** §16 markiert mehrere LP-Komponenten als „administrierbar". Welche werden in Stufe 1
echt datengetrieben, welche bleiben kuratierte Grafik/HTML (AC-002 erlaubt Feature-Flag/Vorbereitung)?

**Entscheidung (Claude, Kategorie B; Release-Plan-Split).** Datengetrieben: LP-08 Process World Cards,
LP-12 Roadmap, LP-11 Onboarding-Schritte – reine Listen, hoher Nutzen, geringes Risiko, rein textbasiert
(damit §26-Textalternative inhärent). Umsetzung als Option `liw_components` + drei Shortcodes +
Components Board (Muster wie Header, „Titel | Text"-Zeilen). Kuratiert (redaktionell im Abschnitt,
teils vorhandene SVGs via `[liw_graphic]`): LP-02 Risk Matrix, LP-03 Zielbild, LP-04 Magic Cube,
LP-05 Interface LogiQ, LP-07 Data Model, LP-09 Goods&Finance, LP-10 Security/Validation Ladder –
interaktive Vertiefung bleibt Folgeetappe. Labels/Standards mehrsprachig via `__()` (wie Etappe 2).

**Quelle/Version.** Release-Plan Etappe 3; Pflichtenheft §8/§16; 0.1.0-alpha.29.

### 2026-09-18 · Etappe 2: Header/Navigation + Hero – Shortcodes + Option-Config (0.1.0-alpha.28)

**Frage/Kontext.** §7 verlangt eine administrierbare, sticky Hauptnavigation mit Logo, CTAs,
Sprachumschalter und optionalem Portal-Login; §8 LP-01 einen Hero mit Netzwerk-Ebene und den zwei
CTAs. Zwei Entscheidungen: (1) Wo liegt die Header-Konfiguration? (2) Wie werden Nav-/CTA-Labels
mehrsprachig, ohne die Translation-Registry (die auf Post-Feldern arbeitet) für Chrome aufzubohren?

**Optionen (1).** (a) Fester Header im Template; (b) Option-basierte Settings + kleines „Header
Board". **(2).** (a) Labels in die Registry aufnehmen (schwer für Nicht-Post-Chrome); (b) Standard-
Labels über `__()` (gettext/.po), redaktionelle Overrides literal.

**Entscheidung (Claude, Kategorie B).** (1b) + (2b). `HeaderSettings` (Option `liw_header`) +
`HeaderBoardPage`; `[liw_header]`/`[liw_hero]` als Shortcodes (nicht fest ins Theme, da die
Landingpage eine WP-Seite mit Shortcode ist). Ziel-Normalisierung rein/testbar und hart abgesichert
(nur `#anker`, relativer Pfad oder http(s); `javascript:`/protokollrelativ verworfen). Labels:
`__()`-Standards mehrsprachig; per-Sprache-Overrides als spätere Verfeinerung vermerkt (To-Dos).
CI-Konformität: Logo nur bei Media-Board-Freigabe (CI-005), sonst neutrale Text-Wortmarke (kein
erfundenes Logo, CI-002); Hero-Bild analog. Farben/Schriften ausschließlich über `--brand-*` (§11).

**Quelle/Version.** Release-Plan Etappe 2; Pflichtenheft §7/§8/§16; 0.1.0-alpha.28.

### 2026-09-18 · Etappe 1: CI-Tokens als Brand Board + Inline-Style, neutrale Fallbacks (0.1.0-alpha.27)

**Frage/Kontext.** Release-Plan freigegeben („ok, wir können loslegen"). Etappe 1 = CI-Fundament
(§10–12). Zwei Entscheidungen: (1) Wo/wie kommen die Marken-Tokens ins Frontend, ohne Inline-Style
und ohne hart codierte Markenwerte? (2) Womit füllen, solange kein Liebherr-Brand-Kit freigegeben ist?

**Optionen (1).** (a) Werte in `liebherr-frontend.css` hart codieren – verstößt gegen §11/§14
(keine Markenwerte im Code) und ist nicht administrierbar; (b) dynamische `:root`-Variablen aus einer
Option über `wp_add_inline_style()` am bestehenden Frontend-Handle. **(2).** (a) ARALIYA-Creme-Tokens
weiterverwenden; (b) neutrale, industrielle Fallbacks; (c) echtes Liebherr-Gelb schon eintragen.

**Entscheidung (Claude, Kategorie B).** (1b) + (2b). `BrandTokens` als Service (reine, testbare
`sanitize()`/`css_from()`), `BrandBoardPage` als administrierbares Board (Capability
`liw_manage_content`, Nonce, Audit). Ausgabe über `wp_add_inline_style()` – der von WordPress
sanktionierte Weg für dynamische Tokens, kein hand­geschriebenes Inline-CSS im Template, kein
Markenwert im Code (§11/§14). Fallbacks bewusst **markenneutral** (nicht Liebherr-Gelb): CI-002
verbietet erfundenes/vorweggenommenes Branding; das echte Gelb `#ffd000` steht in
`docs/LIW_BRAND_TOKENS.md` bereit und wird erst nach dokumentierter Freigabe im Board eingetragen.
Ausbruchschutz in `css_from()` (Entfernen von `{}`/`;`/`<`/`>`) gegen CSS-/`</style>`-Injektion.
Logo nur aus im Media Board freigegebenen Assets (CI-005).

**Quelle/Version.** Release-Plan `docs/LIW_RELEASEPLAN.md`; Pflichtenheft §10–12/§14; 0.1.0-alpha.27.

### 2026-09-18 · Marken-Assets als CI-005-Kandidaten statt Verzicht bis zur Freigabe

**Frage/Kontext.** §34 nennt Logo, Brand-Manual, Bildmaterial als „vor finaler Freigabe offen". JW:
„Die müssen wir noch erstellen – jetzt. Analysiere die Liebherr-Welt-Seite, sammle die nötigen Assets
und lege sie ins Media Board; Freigabe holen wir uns dann von Liebherr."

**Optionen.** (a) Nichts tun bis Liebherr liefert; (b) Assets von liebherr.com als **Kandidaten** in den
CI-005-Freigabeworkflow des Media Boards laden (Quelle+Copyright, `approved = 0`), Werte/Typo als
Referenz festhalten, Verwendung erst nach dokumentierter Freigabe.

**Entscheidung (JW-Auftrag, Umsetzung Claude).** (b). Genau dafür existiert der Media-Board-Workflow.
Idempotenter Import (`scripts/liw-import-brand-assets.php`): 11 Assets (Logo-SVG, 5 Webfonts, 5 Bilder),
alle `_liw_media_approved = 0`, Quelle+Copyright gesetzt. Werte **nicht erfunden**, sondern aus Liebherrs
eigenem Auftritt abgelesen (`docs/LIW_BRAND_TOKENS.md`). Kein erfundenes Logo/Farbschema (CI-002),
keine Freigabe-Behauptung (Markenschutz), keine öffentliche Verwendung vor `approved = 1`. Schriften sind
lizenziert → Einbindung erst nach Liebherr-Lizenz.

**Quelle/Version.** JW-Auftrag 18.09.2026; Pflichtenheft §10–12/§34, CI-002/005; Media-Board (MediaBridge).

### 2026-09-18 · Aktive Anker-Hervorhebung der Sprungleiste – enqueuetes Skript statt Verzicht (0.1.0-alpha.26)

**Frage/Kontext.** Fortsetzung aus dem Chat, gewählte „Scheibe" (JW „weiter mit 1"): die seit alpha.18
als YAGNI vertagte aktive Hervorhebung des sichtbaren Abschnitts in der Landingpage-Sprungleiste
(`.liw-landingpage__nav`, seit alpha.23 sticky). „Bedarf" ist jetzt gegeben, YAGNI greift nicht mehr.

**Optionen.** (a) Weiter verzichten; (b) kleines, abhängigkeitsfreies Frontend-Skript, an die
Nav-Klasse gebunden, das per IntersectionObserver `aria-current`/`is-current` setzt; (c) zusätzlich
die Kopf-Navigation sticky machen – entfällt hier, denn die Sprungleiste ist bereits `position: sticky`.

**Entscheidung (Claude, Kategorie B).** (b): erstes Frontend-Skript des Plugins
`assets/js/liebherr-frontend.js`, im selben `maybe_enqueue()`-Auslöser wie das Stylesheet (nur auf
Seiten mit einem `liw_*`-Shortcode), im Footer, `LIW_VERSION` als Cache-Buster. Kein Inline-Code
(CLAUDE.md Abschnitt 5), fortschreitende Verbesserung: ohne JS/ohne IntersectionObserver bleibt die
Sprungleiste voll funktionsfähig. Markup unverändert (Skript bindet an `.liw-landingpage__nav`).
Lesefenster `rootMargin: -42% 0px -53%` → genau ein aktiver Eintrag; bei Überlappung gewinnt der
oberste in Dokumentreihenfolge, bei keinem sichtbaren bleibt die letzte Markierung.

**Prozesshinweis.** Die Scheibe wurde zu Sitzungsbeginn versehentlich zuerst im Core-Plugin
(`araliya-platform-core`, SAV „Kleiner Zaubermeister") gebaut, weil beide Projekte eine
Landingpage mit Anker-Sprungleiste haben. Nach dem Hinweis „Interface World / drei Bücher" korrigiert:
hier korrekt umgesetzt; der Core-Fehlgriff wurde als (nicht gepushter) Auto-Commit alpha.718 erkannt
und Joseph zur Entscheidung übergeben (Rücknahme vs. behalten).

**Quelle/Version.** Chat-Fortsetzung; `docs/LIW_TODO.md` (Punkt seit alpha.18); 0.1.0-alpha.26.

### 2026-09-18 · Befund aus alpha.24-Lauf: Core-Sprachliste sind Datensätze, nicht Strings (0.1.0-alpha.25)

**Kontext.** Erster Docker-Lauf nach alpha.24: 124/125, eine PHP-Warning `Array to string conversion`
in `LanguageBridge::active_langs()`. Ursache: Core `LanguageService::get_active_langs()` liefert
`[['code' => 'de', 'label' => …], …]`; der `(string)`-Cast machte daraus `"Array"`. Derselbe Cast
stand seit alpha.1 in der alten `SeoBridge::active_languages()` – im Frontend also seit Beginn
`hreflang="Array"`, unbemerkt, weil nie geprüft.

**Lehre.** Eine Core-Signatur „verifiziert" heißt nicht nur Parameter, sondern auch die **Form des
Rückgabewerts** – bei alpha.1 wurde die Existenz der Methode geprüft, nicht ihr Ergebnis. Genau
dafür zahlt sich der Selbsttest aus: Die in alpha.24 ergänzte Prüfung hat den Altfehler beim ersten
Lauf sichtbar gemacht. Zusätzlich verschärft: Sprachcodes müssen jetzt einem Plausibilitätsmuster
entsprechen, damit ein ähnlicher Formfehler künftig auch ohne PHP-Warning rot wird.

**Quelle/Version.** Lauf `SELFTEST-DFEY1SCL` 18.09.2026; Core `Language/LanguageService.php`
(`get_available_langs()` mit `code`-Feld); 0.1.0-alpha.25.

### 2026-09-18 · I18nSeo: Option A – vorhandene Core-Sprachsteuerung nutzen, Router-Eingriff vertagen (0.1.0-alpha.24)

**Frage/Kontext.** Seit alpha.1 offene Kategorie-A-Frage (ADR-LIW-001): `liw_section` in den
Core-`I18nRouter` aufnehmen oder eigenständige `SeoBridge` behalten? Inzwischen hingen drei Punkte
daran (Sprache der Trägerseite, §24-Textversion, Login-Seite). Joseph gab die Analyse und mit „ja"
die Umsetzung der Empfehlung frei.

**Befund (Core per device_bash gelesen).** `Language\LanguageService`: Sprache aus Cookie oder
`?lang=`, Titel/Inhalt-Filter wirken auf alle Beiträge → Landingpage und Abschnitte sind bereits
mehrsprachig bedienbar. `LanguageSwitcherWidget::render()` existiert (Flaggen-Dropdown, `?lang=`).
`Modules\I18nSeo\I18nRouter`: feature-geflaggt (`ary_i18n_router_enabled`), Scope Startseite +
`suite/apartment/treeroom`, Rewrite nur für `post_type=…&name=…` – der Post-Type `page`, auf dem die
Landingpage liegt, ist nicht abgedeckt. Eigene `SeoBridge` gab hreflang nur auf Einzelansichten aus –
seit alpha.18 die falsche Fläche.

**Optionen.** (A) Vorhandenes nutzen, kein Core-Eingriff: SeoBridge auf Trägerseite ausrichten,
Core-Widget per Shortcode, §24-Sprachsuffix; (B) Core-Router um `page` + `liw_section` erweitern,
SeoBridge abschaffen (Kategorie A, Core-Release, nur sinnvoll bei aktivem Router); (C) eigenes Routing
im Plugin (Doppelentwicklung – abgelehnt).

**Entscheidung (Joseph White „ja" auf Empfehlung A).** A jetzt, B als Folgepunkt. Leitplanken: kein
eigener Umschalter (Core-Widget gekapselt), keine eigene Sprachermittlung (LanguageBridge kapselt nur),
SeoBridge schweigt bei aktivem Core-Router, damit B später ohne Doppel-hreflang eingeführt werden kann.
Login-Seite bleibt WP-Standard (kein Sprach- oder Design-Eingriff nötig, Core-Cookie gilt auch dort).

**Quelle/Version.** Core `Language/LanguageService.php`, `Language/LanguageSwitcherWidget.php`,
`Modules/I18nSeo/I18nRouter.php` (gelesen 18.09.2026); Pflichtenheft §20/§24; ADR-LIW-001;
0.1.0-alpha.24.

### 2026-09-18 · Partnerdokumente: Core-Muster kopiert statt Core-Modul gebogen (0.1.0-alpha.22)

**Frage/Kontext.** Joseph gab mit „ja" Stufe 2 frei. Die zwei Vorab-Fragen (PDF-Fassung, Konto-
Automatik) sind noch offen – der Dokumentenbereich ist inhaltsneutral, daher blockieren sie den
Bau nicht; konservative Defaults bleiben (manuelle Konten, Redaktion entscheidet den Upload).

**Entscheidungen im Detail.** (1) Speichermuster: Verzeichnis/Random-Name/Deny/finfo/Soft-Delete
wie Core `DocumentService` – bewusst kopiert statt das gastgebundene Core-Modul zu erweitern
(Kategorie A, später als Option B möglich). (2) Download ohne Einmal-Token: Der Core braucht Token,
weil seine App-API ohne Cookie arbeitet; hier liegt ein Website-Login vor, also reicht eingeloggt +
Capability + Nonce + `admin_post_` ohne `nopriv` – weniger Zustand, kein Transient. (3) Kein
`nopriv`-Hook: anonyme Anfragen erreichen den Handler nie. (4) Admins erhalten
`liw_partner_access`, damit Joseph den Bereich ohne zweites Konto prüfen kann. (5) Testuploads:
`is_uploaded_file()` schlägt im CLI-Selbsttest immer fehl; Ausnahme nur bei
`WP_ENVIRONMENT_TYPE=development` UND CLI UND Datei im WP-Temp-Verzeichnis – im Produktivbetrieb
unverändert streng. (6) **ANNAHME-LIW-12:** ein Dokumentenpool für alle Partner.

**Quelle/Version.** Core `Modules/Documents/DocumentService.php` (Muster); Pflichtenheft §10/§23/§24;
Joseph White 18.09.2026 „ja"; 0.1.0-alpha.22.

### 2026-09-18 · Geschützter Partnerbereich: Option A, Stufe 1 = eigene Rolle + Konto per Knopf (0.1.0-alpha.21)

**Frage/Kontext.** Nach der PDF-Entscheidung (alpha.20) bat Joseph um die Analyse eines
geschützten Downloads nur für angemeldete, freigegebene Partner und gab mit „ja" die Umsetzung
von Option A frei (Rolle + Konto zuerst, Dokumentenbereich danach).

**Befund (Core-Bestandsaufnahme per device_bash).** `Modules\Documents` (DocumentService): sehr gutes
Sicherheitsmuster – zufälliger `stored_name`, Upload-Verzeichnis 0750 mit `.htaccess Deny from all`,
Einmal-Download-Token (Transient, TTL 300 s), Rate-Limit, Soft-Delete – aber jede Signatur ist an
`guest_id` gebunden. `PartnerAuth`/`PartnerPortalController`: Bearer-Token-REST-API für eine App
(Therapeut/Standortleiter via `wp_authenticate` + Core-Caps), kein Cookie-Login für Website-Seiten,
keine Händlerrolle. `RoleManager`: nur ARALIYA-Rollen mit Hotel-/Gesundheits-Caps. Liebherr-Partner
aus dem Onboarding sind `ary_partners`-Zeilen ohne WP-Benutzer. Medienbibliothek: Dateien immer
per URL öffentlich – die CI-005-Freigabe steuert nur die Anzeige, nicht den Dateizugriff.

**Optionen.** (A) eigener schlanker Dokumentenbereich im Plugin nach dem Core-Muster, WP-Konten mit
eigener Rolle; (B) Core-Dokumentenmodul um `owner_type` verallgemeinern (Kategorie A, sicherheits-
kritisches Modul, eigenes Core-Release); (C) Core-Partner-Portal-API (App-Design, bräuchte JS-Frontend);
(D) passwortgeschützte WP-Seite (schützt Datei-URL nicht – abgelehnt).

**Entscheidung (Joseph White „ja" auf Empfehlung A).** A mit Migrationspfad zu B. Stufe 1 in dieser
Auslieferung: eigene Rolle `liw_partner` (nur `read` + `liw_partner_access`, kein Backend – Least
Privilege statt Wiederverwendung einer ARALIYA-Rolle), Kontoanlage als expliziter zweiter Schritt
nach der fachlichen Freigabe (**ANNAHME-LIW-11**, konservativer Default), Passwort ausschließlich über
den WP-Standard-Setzen-Link, additive Verknüpfung `liw_partner_extra.wp_user_id` + User-Meta.
Rolle wird bei Deaktivierung nicht entfernt (Benutzer würden rollenlos; WP-Konvention).

**Vorab-Entscheidungen für Stufe 2 (nicht bei Claude).** (1) Vollständige interne PDF an Partner
oder bereinigte Fassung ohne Endpunktnamen (Empfehlung: bereinigt, Freigabe durch Liebherr);
(2) Konten für alle freigegebenen Händler oder nur ausgewählte.

**Quelle/Version.** Core-Dateien `Modules/Documents/*`, `Modules/Partner/PartnerAuth.php`,
`PartnerPortalController.php`, `Core/RoleManager.php` (gelesen 18.09.2026); Pflichtenheft §10/§23/§24;
Joseph White 18.09.2026; 0.1.0-alpha.21.

### 2026-09-18 · Interne Prozess-PDF: nicht veröffentlichen, Grafiken aus dem Pflichtenheft (0.1.0-alpha.20)

**Frage/Kontext.** Joseph fragte, ob die interne Prozess-PDF (Integrationsplan mit realen
API-Endpunkt- und Systemnamen, s. Eintrag alpha.12) in Bildausschnitten auf die Landingpage und
zusätzlich als Download angeboten werden kann.

**Befund.** Jeder Ausschnitt trägt reale Endpunkt-/Systemnamen – für Angreifer die wertvollste
Aufklärungsinformation (welche Systeme, welche Schnittstellen, welche Reihenfolge). Das widerspricht
Pflichtenheft §17 (strikte Trennung öffentlicher Inhalte von technischen Schnittstellendaten) und
Josephs eigener Anforderung vom 18.09.2026. Die PDF lag Claude in dieser Sitzung zudem nicht mehr vor.

**Optionen (Claude, AskUserQuestion).** (1) weitere anonymisierte Grafiken; (2) Analyse eines
geschützten Downloads nur für angemeldete, freigegebene Partner (Pflichtenheft kennt „freigegebene
Dokumente" für Partner); (3) öffentlich veröffentlichen – abgeraten; (4) zurückstellen.

**Entscheidung (Joseph White).** „Beides: Grafiken jetzt, Download-Analyse danach."

**Umsetzung.** Drei Grafiken ausschließlich aus dem Pflichtenheft-Wortlaut §8 (LP-03 Zielbild, LP-04
Magic Cube, LP-12 Roadmap) – keine PDF-Details, nichts aus dem Gedächtnis rekonstruiert („nicht
erfinden"). Technik/Sicherheit wie alpha.15 (Whitelist, Design-Tokens, Inline-SVG). Der geschützte
Download ist als Analysepunkt in `docs/LIW_TODO.md` festgehalten (Kategorie-A-nahe Entscheidung:
Partner-Login/Rolle, Media Board CI-005, ggf. Core-Dokumentenmodul).

**Quelle/Version.** Rückfrage + AskUserQuestion-Antwort Joseph White 18.09.2026; Pflichtenheft §8/§17;
0.1.0-alpha.20.

### 2026-09-18 · LP-13 Kontaktformular: eigene Tabelle, geteiltes Einwilligungsprotokoll (0.1.0-alpha.19)

**Frage/Kontext.** Nach der ersten sichtbaren Landingpage (alpha.18) wählte Joseph LP-13 – den
letzten Pflichtenheft-Baustein ohne Umsetzung. §22 liefert die Feldliste, §24 die
Datenschutzpflichten (Einwilligung mit Version/Zeitstempel, Zweckbindung Kontakt ≠ Marketing,
Export/Löschung vorbereiten), §8 die Trennung vom Onboarding.

**Befund.** Das Onboarding-Formular (alpha.6) legt bewusst einen Core-Partner (`ary_partners`,
pending) an und hängt Zusatzfelder in `liw_partner_extra`. Für Kontaktanfragen passt das nicht:
Rollen wie „Zentrale" oder „Sonstiger Projektkontakt" sind keine Partner, und ein
Partner-Datensatz je Anfrage wäre eine Datenkopie mit falscher Semantik. Das
Einwilligungsprotokoll `liw_consent_log` referenziert bisher nur `request_id` ohne Herkunft –
Partner-IDs und Kontakt-IDs würden kollidieren.

**Optionen.** (a) Kontaktanfrage als Partner (Wiederverwendung, aber semantisch falsch, Core-Daten
verschmutzt); (b) eigene Tabelle `liw_contact_request` + eigenes Consent-Log (Doppelentwicklung);
(c) eigene Tabelle + bestehendes Consent-Log mit neuer Spalte `request_kind` (additiv, Default
`onboarding`); (d) Kontaktanfragen als CPT (WP-Bordmittel, aber personenbezogene Daten in
`wp_posts`/Revisionen/Suche – schwer §24-konform zu löschen).

**Entscheidung (Claude, im Rahmen der Freigabe „LP-13").** (c). Kleinste saubere Änderung:
eine schlanke Fachtabelle (Kategorie B, keine Kopie von Partnerdaten) und eine additive Spalte
im bestehenden Protokoll, sodass §24 weiterhin eine einzige Quelle für Einwilligungen hat.
Alle bestehenden Aufrufe bleiben durch den Default unverändert gültig; Altdaten sind korrekt
als `onboarding` klassifiziert. Formular- und Board-Mechanik 1:1 vom Onboarding übernommen
(Nonce, Honeypot, admin-post, AdminPagination, Capability `liw_view_onboarding` – gleiche
Zielgruppe, keine neue Capability). Audit protokolliert nur Klassifizierung, keine Freitexte
(Datensparsamkeit). Löschen im Board entfernt Anfrage und Einwilligungen gemeinsam.

**Annahmen (dokumentiert, per Filter änderbar).** ANNAHME-LIW-8 Regionenliste, ANNAHME-LIW-9
Projektinteressen, ANNAHME-LIW-10 Empfänger = WP-Admin-Mail bis zur CRM-/Empfängerdefinition
(Pflichtenheft §31, Projektleitung). Bewusst offen: Export (§24), CRM-Übergabe,
sprachabhängige Datenschutztext-Version.

**Quelle/Version.** Pflichtenheft §8 LP-13, §22, §24, §31 (per device_bash gelesen);
AskUserQuestion-Antwort Joseph White 18.09.2026; 0.1.0-alpha.19.

### 2026-09-18 · Zusammengesetzte Landingpage: Shortcode auf normaler WP-Seite statt eigenem Template (0.1.0-alpha.18)

**Frage/Kontext.** Joseph: „Wo sehen wir die Seite?" – nach alpha.17 existierten die Abschnitte
nur als Einzelbeiträge mit eigener URL, eine Gesamtseite fehlte.

**Optionen.** (a) Shortcode `[liw_landingpage]` auf einer normalen WP-Seite; (b) eigenes
Page-Template/Frontcontroller mit fester Route `/interface-world`; (c) CPT-Archivseite
(`has_archive`) als Landingpage; (d) Block-Theme-Template-Part.

**Entscheidung (Claude, im Rahmen der Freigabe „ja").** (a). Gleiche Mechanik wie die drei
bestehenden Shortcodes, keine Route/Rewrite-Änderung, kein Theme-Eingriff; Redaktion behält
Titel, Slug, SEO und Sprache der Trägerseite in WP-Bordmitteln. (b)/(d) wären Kategorie-A-nahe
Eingriffe ohne Mehrwert für das Gerüst; (c) würde Archiv-Semantik (Pagination, Reihenfolge nach
Datum) gegen den Strich bürsten. Sichtbarkeit strikt an `publish` gebunden, damit der
§19-Freigabeworkflow die einzige Steuerung bleibt. Inhalte über `the_content`-Filter, um
Blöcke/Shortcodes/Core-Übersetzung nicht nachzubauen.

**Bewusst offen.** Ankernavigation, Sprache/SEO der Trägerseite (I18nSeo-Frage), Hero-Motiv.

**Quelle/Version.** Rückfrage Joseph White 18.09.2026 („wo sehen wir die Seite", „was kommt
noch zuvor", „ja"); 0.1.0-alpha.18.

### 2026-09-18 · Die 14 Abschnitte: Bauplan im Code, Anlage per Knopf, Texte bei der Redaktion (0.1.0-alpha.17)

**Frage/Kontext.** Nach `[liw_graphic]` (alpha.15) wählte Joseph, die 14 Abschnitte LP-01…LP-14
anzulegen, damit die Landingpage erstmals als Ganzes im Content Board sichtbar wird.

**Befund.** Das Pflichtenheft §8 liefert Code, Titel und einen Kurzinhalt je Abschnitt – aber
keine fertigen Marketingtexte. Vier Abschnitte haben bereits gebaute Bausteine (LP-06 Map,
LP-07/08 Grafiken, LP-11 Onboarding-Formular); LP-13 verlangt ein eigenes Kontaktformular, das
noch nicht existiert.

**Optionen.** (a) CLI-Seed-Skript unter `scripts/` (Terminal-Regel: Joseph müsste `docker exec`
ausführen – für eine redaktionelle Aufgabe unpassend); (b) Knopf im Content Board mit
idempotentem Seeder; (c) Abschnitte automatisch bei Aktivierung/Upgrade anlegen (ungefragte
Inhaltsanlage in fremden Installationen, Kategorie-B-Grenze); (d) zusätzlich ausformulierte
Texte generieren (verstößt gegen „nicht erfinden" und die §17-Vorsicht bei öffentlichen
Inhalten).

**Entscheidung (Claude im Rahmen der Freigabe „Die 14 Abschnitte anlegen").** (b) mit
strikter Trennung Daten/Verhalten: `SectionBlueprint` (Pflichtenheft-Inhalte 1:1, wörtlich) und
`SectionSeeder` (idempotent über Post-Meta `_liw_lp_code`, nicht über den Titel – Redaktion darf
umbenennen; Papierkorb zählt als vorhanden). Inhalt der Entwürfe: Vorgabe als erster Absatz plus
Baustein als Shortcode-Block – die Ausformulierung bleibt Redaktionsaufgabe. (d) verworfen.
Der Seeder läuft im Selbsttest bewusst nicht (würde echte Inhalte anlegen); geprüft werden
Bauplan und Idempotenz-Sicht, die Anlage selbst prüft Joseph per Knopf.

**Folge.** Neuer offener Punkt LP-13 Kontaktformular in `docs/LIW_TODO.md`.

**Quelle/Version.** Pflichtenheft §8 Tabelle LP-01…LP-14 (per device_bash gelesen, nicht aus
dem Gedächtnis); AskUserQuestion-Antwort Joseph White 18.09.2026; 0.1.0-alpha.17.

### 2026-09-18 · dbDelta und Klammern im Tabellen-COMMENT (0.1.0-alpha.16)

**Frage/Kontext.** Der Docker-Selbsttest für alpha.15 lief 67/67 grün, zeigte aber am
Anfang sechs WordPress-DB-Fehler: `ALTER TABLE ary_liw_* ADD COLUMN ) DEFAULT CHARACTER SET
utf8mb4 … COMMENT='Liebherr …` – Stacktrace über `maybe_upgrade_database → create_tables →
*Schema::create_table → dbDelta`. Beim alpha.14-Lauf fehlten die Fehler, weil der
Versionswechsel dort bereits durch einen vorherigen Admin-Request verarbeitet war.

**Befund.** `dbDelta()` (wp-admin/includes/upgrade.php) ermittelt den Spaltenblock einer
`CREATE TABLE`-Anweisung mit `preg_match("|\((.*)\)|ms", …)` – gierig bis zur **letzten**
schließenden Klammer. Alle sechs Plugin-Tabellen hatten Klammern im Tabellen-`COMMENT`
(`… (Pflichtenheft §17 liw_interface)`), also endete der „Spaltenblock" erst im Kommentar.
dbDelta zerlegt den Block zeilenweise und hält die Zeile `) DEFAULT CHARACTER SET …` für
eine neue Spalte mit dem Namen `)` → genau der beobachtete `ADD COLUMN )`-Query, der an
MySQL scheitert. Folgen: Tabellen korrekt (die fehlerhafte Query tut nichts), aber bei jedem
Versionswechsel sechs Fehler im Log; bei Erstanlage kein Fehler, weil dbDelta dann das
`CREATE TABLE` unverändert ausführt. Beim Suchen der Ursache wurde dasselbe Muster in
mindestens fünf Core-Tabellen gefunden (`VersionManager.php` Zeilen ~1061/1217/1602,
`TranslationRepository.php`, `NotificationSchema.php`).

**Optionen.** (a) Klammern aus den Plugin-COMMENTs entfernen, Inhalt sonst gleich; (b)
Tabellen-COMMENTs ganz streichen (Dokumentationsverlust in der DB); (c) eigene
dbDelta-Umgehung (Architekturbruch, WP-API bevorzugen); (d) zusätzlich den Core anfassen.

**Entscheidung/Umsetzung.** (a) für dieses Plugin – kleinster Eingriff, kein Datenmodell-
Effekt. Regel als Kommentar in `InterfaceCatalogSchema` verankert und doppelt abgesichert:
statisch in `tests/run-tests.php` (kein Klammerzeichen in `COMMENT='…'`) und live in
`scripts/liw-selftest.php` [0] (zweiter `create_tables()`-Lauf ohne `$wpdb->last_error`).
(d) bewusst **nicht** umgesetzt: Core-Refactoring nur auf Freigabe (Globale Anweisung
„Kontinuierliche Qualitätsverbesserung – dokumentieren, nicht automatisch umsetzen").
Empfehlung an den Core: gleiche Ersetzung in den genannten fünf Stellen; Aufwand gering,
Risiko null (dbDelta ändert bestehende Tabellen-Kommentare nicht), Nutzen: saubere Logs bei
jedem Core-Versionswechsel.

**Quelle/Version.** Docker-Selbsttest-Ausführung Joseph White 18.09.2026 (Lauf
`SELFTEST-LAZTQ9OX`); Core-Fundstellen per device_bash grep verifiziert; 0.1.0-alpha.16.

### 2026-09-18 · LP-07/LP-08: Shortcode mit Whitelist statt Bild-URL oder Block (0.1.0-alpha.15)

**Frage/Kontext.** Nach dem grünen Docker-Praxistest wählte Joseph als nächste Scheibe, die
beiden anonymisierten Grafiken (alpha.12) in `liw_section`-Abschnitten platzierbar zu machen.

**Befund.** Es gab dafür noch keinen Weg. Die Konzept-Doku verlangt Inline-Einbettung, damit
die `var(--ary-*)`-Tokens greifen – ein `<img src>` auf die SVG-Datei würde die Farben nicht
aus der Seiten-CSS beziehen. Im Plugin existieren bereits zwei öffentliche Shortcodes
(`OnboardingForm`, `ConnectionMapView`) mit etabliertem Muster (Klasse pro Shortcode,
`register()`/`render_shortcode()`, `liw-*`-Klassen, CSS nur bei Verwendung).

**Optionen.** (a) Shortcode `[liw_graphic name=…]` mit fester Whitelist; (b) eigener
Gutenberg-Block (JS-Build, Block-Registrierung, neue Abhängigkeiten – Kategorie A);
(c) Grafik als Medienbibliothek-Upload und `<img>` (Farb-Tokens gehen verloren, außerdem
würden die SVGs zu Nutzer-Uploads mit eigenem Sanitizing-Bedarf); (d) Shortcode mit freiem
`src`/Pfad-Attribut (flexibel, aber ein Angriffsvektor für beliebiges Datei-Lesen).

**Entscheidung (Claude, im Rahmen der Freigabe „LP-07/LP-08 einbetten").** (a). Passt
1:1 zum bestehenden Muster, kein neuer Build-Schritt, kein neues Datenmodell. (d) wurde
wegen Josephs Sicherheitsanforderung („absolut sicher gegen Angriffe von außen") explizit
verworfen: `name` wird nur gegen `SectionGraphicView::GRAPHICS` aufgelöst, zusätzlich
`realpath()`-Guard auf `assets/img/`. Die SVGs sind versioniertes Plugin-Markup, kein
Upload – darum direkte Ausgabe (wp_kses kennt SVG nicht), `caption` aber escaped.

**Umsetzung.** `Frontend\SectionGraphicView`, CSS-Klassen, `FrontendAssets`-Auslöser,
Handbuch-Anleitung, Selftest-Erweiterung (Positiv- und Negativfälle inkl. Traversal).
**ANNAHME-LIW-7:** eine Grafik je Seite nur einmal (feste `id`s in title/desc).

**Quelle/Version.** AskUserQuestion-Antwort Joseph White 18.09.2026 „LP-07/LP-08 in
Abschnitte einbetten"; docs/LIW_LANDINGPAGE_KONZEPT.md „Offene Punkte"; 0.1.0-alpha.15.

### 2026-09-18 · Docker-Praxistest: zwei Befunde behoben (0.1.0-alpha.14)

**Frage/Kontext.** Joseph hat nach dem Commit von alpha.13 erstmals `scripts/liw-selftest.php`
(alpha.11) in der echten Docker-Dev-Umgebung ausgeführt und das vollständige Terminal-Ergebnis
eingefügt: 56 von 59 Prüfungen bestanden, drei Fehlschläge, dazu ein wiederkehrender,
nicht-fataler `AuditService`-Fehler im Log bei nahezu jedem `AuditBridge::log()`-Aufruf.

**Befund 1 – `actor_type` (echter Produktionsfehler).** `AuditBridge::log()` rief
`Araliya\Platform\Core\Modules\Audit\AuditService::log()` mit sieben Argumenten auf und
übergab als letztes `'liebherr-interface-world'`, in der Annahme, dies sei ein
Modul-/Herkunftsbezeichner. Prüfung der echten Core-Signatur
(`src/Modules/Audit/AuditService.php`) ergab: Parameter 7 ist `string $actor_type = 'system'`,
gespeichert in der Spalte `actor_type VARCHAR(20)` (`AuditSchema.php`). Der 24 Zeichen lange
Plugin-Slug sprengte diese Spalte bei **jedem** Aufruf – nicht fatal (der Fehler wird im Core
selbst nur geloggt, s. `if (false === $result) { error_log(...) }`), aber die
Audit-Nachvollziehbarkeit (SEC-005) ging für dieses Plugin faktisch durchgehend verloren.

**Befund 2 – strikter Typvergleich im Testskript (kein Produktionsfehler).** Die drei
gemeldeten Fehlschläge (`get_all()`/`get_worlds()`/`get_scenarios_for_world()` „enthalten
neuen Datensatz nicht") betrafen ausschließlich `scripts/liw-selftest.php`, nicht die
Anwendungslogik: `InterfaceCatalogService::create()`/`SimulationService::create_world()`/
`add_scenario()` meldeten Erfolg, die anschließenden Prüfungen verglichen die neue (int-)ID
aber mit `in_array(..., true)` (strikt) gegen `array_column($wpdb_results, 'id')` – und
`$wpdb->get_results()` liefert alle Spaltenwerte als `string` (mysqli-Standard ohne eigenes
Type-Casting). `in_array(5, ['5'], true)` ist in PHP `false`. Die eigentlichen Board-Methoden
funktionieren also korrekt; nur der Selbsttest verglich falsch.

**Optionen.** Bei Befund 1 keine Alternative – falscher Parameter musste korrigiert werden.
Bei Befund 2: (a) Testskript auf `intval()`-Cast vor dem Vergleich umstellen; (b) strikten
Vergleich (`true`) ersatzlos entfernen. Für (a) entschieden, da strikte Vergleiche mit
korrektem Typ dem Projektstandard (`strict_types=1`) eher entsprechen als ein pauschal
gelockerter Vergleich.

**Entscheidung/Umsetzung.** `AuditBridge::log()`: 7. Argument auf
`$actor_id > 0 ? 'admin' : 'system'` geändert – Konvention 1:1 aus dem Core selbst übernommen
(`PlatformResetService::log()` verwendet dieselbe Ternäre mit `'user'`/`'system'`; `'admin'`
gewählt, da alle bisherigen Aufrufe dieses Plugins aus Admin-Board-Aktionen stammen, mit
Ausnahme des öffentlichen Onboarding-Formulars, das bereits `actor_id = 0` übergibt).
`scripts/liw-selftest.php`: `array_map('intval', array_column(...))` vor den drei betroffenen
`in_array()`-Aufrufen ergänzt. Keine Datenmodell- oder Schema-Änderung nötig.

**Quelle/Version.** Docker-Selbsttest-Ausführung Joseph White 18.09.2026;
`AuditService.php`/`AuditSchema.php` (araliya-platform-core, verifiziert per device_bash);
0.1.0-alpha.14.

### 2026-09-18 · Content Board (§19): Grundgerüst statt volles CMS-Verhalten (0.1.0-alpha.13)

**Frage/Kontext.** Nach alpha.12 wählte Joseph als nächsten Schritt das Content Board
(§19) – bislang der letzte fehlende der ursprünglich in alpha.1 benannten offenen Punkte.

**Befund.** Das Pflichtenheft verlangt für das Content Board volles CMS-Verhalten:
Drag-and-Drop-Reihenfolge, Zeitsteuerung, Vorschau je Sprache/Gerät/Status, Revisionen
vergleichen/wiederherstellen, CTA-Ziel-Picker, Medien-Picker nur aus freigegebener
Bibliothek, Freigabeworkflow, Pflichtfeldprüfung. Das vollständig auf einmal zu bauen wäre
ein sehr großer, schlecht überprüfbarer Schritt gewesen.

**Optionen (Claude, AskUserQuestion).** (a) Grundgerüst zuerst – native WP-Bordmittel
wiederverwenden, Rest dokumentiert zurückstellen; (b) größerer Wurf mit mehr Funktionen
sofort.

**Entscheidung (Joseph White).** (a) Grundgerüst zuerst.

**Begründung/Umsetzung.** Die `liw_section`-CPT unterstützt bereits seit alpha.1 Titel,
Editor, Revisionen und `page-attributes` (Reihenfolge) – Revisionen vergleichen/
wiederherstellen funktioniert dadurch bereits nativ, ohne zusätzlichen Code (echter Fund:
ein Pflichtenheft-Punkt war de facto schon erfüllt). Neu: `ContentBoardPage` (Übersicht,
Freigabeworkflow Entwurf→Prüfung→freigegeben→veröffentlicht per Statuswechsel-Aktion, da
der Block-Editor den Custom-Status `liw_approved` nicht in seinem Dropdown zeigt) sowie
`LiwSectionCpt::add_status_badge()` (Status-Badge in der Listenansicht). **ANNAHME-LIW-6**:
Reihenfolge über das native Editor-Feld statt Drag-and-Drop (YAGNI). Bewusst nicht Teil
dieser Auslieferung: Drag-and-Drop, Zeitsteuerung über `future` hinaus, Mehrsprachen-/
Geräte-Vorschau, CTA-Ziel-Picker, Medien-Picker-Beschränkung auf freigegebene Bibliothek
(bei Recherche: WPs `ajax_query_attachments_args` liefert keinen zuverlässigen
Post-Type-Kontext für die Media-Modal-Einschränkung – ungeprüft ausliefern hätte gegen
„nicht raten/nicht simulieren" verstoßen), Pflichtfeldprüfung. Alle Restpunkte in
`docs/LIW_TODO.md` dokumentiert.

**Quelle/Version.** liebherr-interface-world 0.1.0-alpha.13; CHANGELOG.md alpha.13;
Pflichtenheft §19; Selftest 70/70 grün (`scripts/liw-selftest.php` Abschnitt [6]).

### 2026-09-18 · Landingpage-Layout-Konzept + Umgang mit interner Prozess-PDF (0.1.0-alpha.12)

**Frage/Kontext.** Joseph fragte, ob es ein Layout-Konzept für die Landingpage gibt, und
lieferte eine interne Prozess-PDF („Liebherr DSC – Schnittstellenprozess Neugestaltung")
als mögliche Grundlage für Auszüge auf der Webseite. Er beschrieb zugleich, dass die
Interface-Logik hausintern ausschließlich von Händlern genutzt wird und „absolut sicher
gegenüber Angriffen von außen" sein muss.

**Befund (vor Entscheidung geprüft).** Die PDF ist kein Marketing-Diagramm, sondern der
vollständige interne Integrationsplan BC/NAV ↔ Livision mit 32 nummerierten Feldern und
realen API-Endpunktnamen (`Create Customer`, `Update Contact`, `Livision URL for Update`,
`Lias Open Trans`, `Get Opportunity` …). Das Pflichtenheft (§8) enthält bereits einen
vollständigen Content-Bauplan für die Landingpage (LP-01…LP-14) – ein Layout-Konzept
existierte also bereits, war aber nicht mit dem Codestand abgeglichen.

**Optionen (Claude, AskUserQuestion).** (a) Anonymisierte Prozessgrafik ohne reale System-/
API-Namen, (b) PDF bleibt rein intern, (c) Auszüge der echten Grafik trotzdem
veröffentlichen.

**Entscheidung (Joseph White).** (a) Anonymisierte Prozessgrafik.

**Begründung.** Pflichtenheft §17 verlangt bereits die strikte Trennung öffentlicher
Inhalte von technischen Schnittstellendaten (im Code bereits umgesetzt, s.
`InterfaceCatalogService::get_public_catalog()`). Reale Endpunktnamen und die genaue
Systemarchitektur offenzulegen widerspräche außerdem der von Joseph selbst formulierten
Sicherheitsanforderung. Die PDF-Struktur (Objektgruppen, Prozessreihenfolge Sales →
Configuration → Order) deckt sich inhaltlich fast 1:1 mit den ohnehin generisch
vorgesehenen Inhalten von LP-07 (Data Model) und LP-08 (Process Worlds).

**Umsetzung.** `docs/LIW_LANDINGPAGE_KONZEPT.md` (LP-01…LP-14-Übersicht mit
Umsetzungsstand), `assets/img/liw-data-model.svg` (LP-07), `assets/img/liw-process-worlds.svg`
(LP-08) – beide als inline einzubettendes SVG-Markup, Farben über die zentralen
ARALIYA-Design-Tokens. Platzierung auf der tatsächlichen Seite wartet auf das Content
Board (§19, weiterhin offen).

**Quelle/Version.** liebherr-interface-world 0.1.0-alpha.12; CHANGELOG.md alpha.12;
Pflichtenheft_Liebherr_Interface_Solutions_Landingpage.md §8/§17.

### 2026-09-18 · Docker-Praxistest: Integrations-Selbsttest scripts/liw-selftest.php (0.1.0-alpha.11)

**Frage/Kontext.** Nach alpha.10 fragte Claude erneut, wie es weitergeht. Der Docker-
Praxistest aller Bereiche stand seit der letzten Rückfrage als Option offen und war zudem
frisch in `docs/LIW_TODO.md` als offener Punkt vermerkt.

**Entscheidung (Joseph White).** Docker-Praxistest jetzt umsetzen.

**Begründung/Umsetzung.** `tests/run-tests.php` prüft nur Syntax und Statuslogik ohne
WP-Bootstrap (kein DB-/Hook-/Shortcode-Test). CLAUDE.md DoD Punkt 4 verlangt „tatsächlich
in der Docker-Umgebung geprüft, nicht nur müsste gehen". Da Claude selbst keinen Zugriff
auf `docker exec` hat (Terminal-Regel), wurde – analog zum bewährten Core-Muster
`scripts/yb-selftest.php` – ein eigenständiges, idempotentes Selbsttest-Skript
`scripts/liw-selftest.php` geliefert: bootstrapt echtes WordPress, prüft DB-Schema,
Capabilities und alle sechs Boards end-to-end inkl. beider Frontend-Shortcodes, räumt
`SELFTEST-`-präfigierte Testdaten garantiert wieder auf (`finally`-Block), läuft nur in
`WP_ENVIRONMENT_TYPE=development`. Die eigentliche Ausführung bleibt bei Joseph
(Terminal-Regel): `docker exec araliya_wordpress php .../scripts/liw-selftest.php`.

**Quelle/Version.** liebherr-interface-world 0.1.0-alpha.11; CHANGELOG.md alpha.11;
docs/LIW_TODO.md (Punkt als erledigt markiert, Ausführung durch Joseph steht noch aus).

### 2026-09-18 · Feinschliff an den Boards + Programmierlogbuch/To-Dos (0.1.0-alpha.10)

**Frage/Kontext.** Nach Abschluss aller fünf Board-Slices (alpha.4–alpha.9) fragte Claude, wie
es weitergehen soll. Zusätzlich bat Joseph White mitten in der Umsetzung, das Handbuch um alle
bisherigen Änderungen zu ergänzen, ein Programmierlogbuch (jede Quellcodeänderung) sowie einen
To-Do-Reiter (offene/geplante Punkte) einzuführen.

**Optionen (Board-Feinschliff).** (a) Feinschliff an bestehenden Boards, (b) gemeinsamer
Docker-Praxistest aller Bereiche, (c) neuer Punkt aus dem Pflichtenheft.

**Entscheidung (Joseph White).** (a) Feinschliff – konkret alle drei bei der Analyse
gefundenen Punkte: Media-Board-Paginierung, Onboarding-Board-Paginierung, Entfernen von
Inline-Styles (3 Fundstellen). Zusätzlich: Handbuch-Update, neues Programmierlogbuch
(`docs/LIW_PROGRAMMIERLOGBUCH.md`) und neuer To-Do-Reiter (`docs/LIW_TODO.md`).

**Begründung.** Media Board (hart auf 50 neueste Medien begrenzt) und Onboarding Board
(ungebremster JOIN ohne LIMIT) waren bereits in früheren Changelog-Einträgen (alpha.7 bzw.
alpha.6) als offene Punkte vermerkt; die Inline-Styles verstießen gegen CLAUDE.md Abschnitt 5
(„keine Inline-Styles"). Programmierlogbuch und To-Dos verbessern die Nachvollziehbarkeit für
alle, die dem Projekt folgen, ohne den bestehenden Zweck von Handbuch (Wie), Changelog (Was)
und diesem Logbuch (Warum) zu vermischen – zwei klar abgegrenzte weitere Sichten: Code-Änderung
je Datei (Programmierlogbuch) bzw. offene Punkte mit Quellenangabe (To-Dos).

**Umsetzung.** `Admin\AdminPagination` (gemeinsame Pagination-Ansicht für Media- und
Onboarding-Board), `Admin\AdminAssets` + `assets/css/liebherr-admin.css` (Inline-Styles
abgelöst), `CoreBridge\MarkdownBridge` (Wrapper um Core
`Modules\Deployment\Admin\HandbookRenderer::render()` – verifiziert generisch, keine zweite
Markdown-Implementierung), `Admin\Pages\ProgrammingLogPage`, `Admin\Pages\TodoBoardPage`.
Handbuch vollständig überarbeitet (alle sechs Boards + beide Frontend-Shortcodes, vorher nur
Interface-Board-Grundgerüst aus alpha.1).

**Quelle/Version.** liebherr-interface-world 0.1.0-alpha.10; CHANGELOG.md alpha.10;
docs/LIW_PROGRAMMIERLOGBUCH.md; docs/LIW_TODO.md; Selftest 68/68 grün.

### 2026-09-17 · Plugin-Grundgerüst + CoreBridge: drei Bridges korrigiert nach Code-Prüfung

**Kontext.** Phase-3-Plan (Machbarkeitsprüfung) nahm sieben CoreBridge-Adapter an (Translation,
Role, Audit, Seo, Media, Consent, ChangeManagement). Vor der Implementierung wurde jeder
angenommene Core-Service tatsächlich gelesen statt die Annahme ungeprüft umzusetzen
(„nicht raten, nicht erfinden", CLAUDE.md Abschnitt 8).

**Ergebnis der Prüfung.**
- `I18nRouter` ist fest auf die CPTs `suite/apartment/treeroom` verdrahtet – keine generische
  Locale-Routing-Lösung. Nicht gebunden; eigene, schlanke hreflang-Ausgabe stattdessen.
- `ImageManager` ist ein kalenderbasierter Bildwechsel-Mechanismus mit Rollback, kein „Media
  Board". Nicht gebunden; native WP-Medienbibliothek + zwei Attachment-Meta-Felder stattdessen.
- `ConsentService` ist an `guest_id` gebunden (Health-Domain-Entität). Nicht gebunden; eigene,
  schlanke `liw_consent_log`-Tabelle stattdessen (kein neues System, nur dieselbe fachliche
  Semantik: Version + Zeitstempel, Zweckbindung).
- `ChangeRequestService` ist an `location_id` (physische Räume) gebunden. Nicht gebunden; der
  Freigabeworkflow läuft über den nativen WP-Post-Status-Mechanismus.

**Entscheidung.** Vier statt sieben Core-Bridges umgesetzt (Translation, Role, Audit tatsächlich
generisch – wiederverwendet; Seo/Media/Consent/ChangeManagement eigenständig, aber ohne
Doppelentwicklung eines „Systems", nur punktuell). Vollständig dokumentiert in
`docs/ADR-LIW-001_Plugin_Struktur_und_CoreBridge.md`.

**Auswirkung.** Reduziert das ursprünglich im Liebherr-Pflichtenheft vorgesehene Datenmodell
(§17, 11 Tabellen) auf vier fachlich neue Tabellen plus eine begründete Ausnahme
(`liw_consent_log`). Offener Punkt an JW: Soll `I18nRouter` nachträglich um `liw_section`
erweitert werden (Core-Änderung, Kategorie A)?

**Quelle.** `docs/ADR-LIW-001_Plugin_Struktur_und_CoreBridge.md`; araliya-platform-core
Quellcode (`src/Modules/I18nSeo/I18nRouter.php`, `src/Modules/ImageManager/ImageManagerModule.php`,
`src/Modules/Consent/ConsentService.php`, `src/Modules/ChangeManagement/ChangeRequestService.php`).
