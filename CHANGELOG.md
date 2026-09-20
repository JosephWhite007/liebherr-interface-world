# Liebherr Interface Solutions — Changelog

## [0.1.0-alpha.138] – 2026-09-20 – Plattformzeit als „Time"-Punkt neben den Koffer (unten rechts)

### Geändert
- Die schwebende **Plattformzeit** sitzt nicht mehr unten links, sondern **unten rechts direkt links neben dem
  Hilfe-Koffer** (`right:68px` = 16 Koffer-Rand + 44 Koffer-Breite + 8 Abstand; vertikal mittig zum 44px-Koffer).
- Sie startet **eingeklappt**: nur ein **blinkender grüner Punkt + „Time"** als kleine Pill. Erst ein **Klick**
  öffnet das vorhandene Fenster (Zeit, Token, „Sitzung beenden"); das Fenster klappt **nach oben** auf, die Pill
  bleibt am Koffer verankert. Label des Widgets von „Plattformzeit" → „Time" (Kurzlabel), `aria-label` bleibt
  „Plattformzeit anzeigen".
- Der grüne Punkt blinkt jetzt klar erkennbar (Opazitäts-Blink zusätzlich zum Live-Ring-Puls); bei
  `prefers-reduced-motion` beides aus.

### Verifikation
- Browser (My-Liebherr-Seite, echte Plugin-CSS): Pill eingeklappt unten rechts, 8px links vom Koffer, Punkt
  `rgb(61,220,132)` grün + Blink-Animation; Klick/Aufklappen zeigt das Fenster **oberhalb** der Pill, vollständig
  sichtbar. `tests/run-tests.php` **657/657**. `LIW_VERSION` .137→.138. (Nach Live-Deploy WP-Rocket-Cache leeren.)

## [0.1.0-alpha.137] – 2026-09-19 – Sprachumschalter sitzt bei aktivem Intro-Gate in der „Liebherr World"-Leiste

### Geändert
- Bei aktivem Intro-Gate (`html.liw-intro-lock`) sitzt der **Sprachumschalter (🇩🇪 DE)** jetzt rechtsbündig
  **in** der schwebenden „Liebherr World"-Leiste, vertikal zentriert auf **44 px** – die Kopfzone ist damit exakt
  so hoch wie auf den anderen Seiten (der Umschalter machte sie zuvor optisch höher).
- Ursache/Fix: Der Umschalter (`.liw-header__lang`, Wrapper um das **Core**-Widget) liegt im Header-
  Stacking-Kontext (`.liw-header` `position:sticky; z-index:20`) und konnte per z-index **nie** über die Leiste
  (`z:2147483601`) steigen. Statt gegen den Stacking-Kontext zu kämpfen, hängt `liebherr-frontend.js` den
  **DOM-Knoten** bei Gate-Start in `.liw-switcher__inner` um (und beim Schließen zurück). CSS richtet ihn dort
  als rechtsbündiges Flex-Kind aus (`margin-left:auto`). Kein Eingriff ins Core-Widget.
- Die schwebende Leiste wird beim Gate **nicht** mehr per `aria-hidden` versteckt (bleibt für AT bedienbar).

### Verifikation
- Browser (`/liebherr-local-intelligence/`): Umschalter `inBar:true`, `onTop:true`, `position:static`, rechts am
  Leistenrand, Höhe der Leiste = 44 px; Gate weiterhin aktiv. `tests/run-tests.php` **657/657**. `LIW_VERSION`
  .136→.137. (Nach Live-Deploy WP-Rocket-Cache leeren.)

## [0.1.0-alpha.136] – 2026-09-19 – Navigationsleiste bleibt über dem Intro-Gate sichtbar (Local Intelligence)

### Geändert
- Beim aktiven Intro-Gate (`IntroOverlay`, „geschützter Entscheidungsraum") bleibt die **„Liebherr World"-
  Navigationsleiste oben sichtbar und klickbar** – sie schwebt über dem Overlay (`html.liw-intro-lock .liw-switcher`
  → `position:fixed; top:0; z-index:2147483601` > Overlay 2147483000). Man kann jederzeit in einen anderen Bereich
  wechseln, statt nur „zurück" zu müssen; nur der abgesicherte Local-Intelligence-Inhalt bleibt bis zum Lösen der
  Rechenmaske gesperrt. Reiner CSS-Eingriff, keine Strukturänderung – die Leiste sitzt **bündig, gleiche Höhe (44 px)
  wie auf den anderen Seiten** (ohne Schlagschatten).

### Verifikation
- Browser (`/liebherr-local-intelligence/`): Leiste fix oben, `elementFromPoint` auf einem Reiter trifft den Nav-Link
  (über dem Overlay, klickbar); Gate weiterhin aktiv. `tests/run-tests.php` **657/657**. `LIW_VERSION` .135→.136.
  (Nach Live-Deploy WP-Rocket-Cache leeren.)

## [0.1.0-alpha.135] – 2026-09-19 – „Liebherr World"-Kopfleiste auf My Liebherr/Pocket + Wallet-Pflichtenheft

### Behoben
- Die gestaltete **„Liebherr World"-Kopfleiste** (WorldSwitcher, dunkle Bar mit Wortmarke + horizontalen Reitern)
  erscheint jetzt auch auf den Seiten **My Liebherr** und **Pocket Information** – wie auf der Intelligence World.
  Ursache: das Frontend-CSS (`liebherr-frontend.css` mit `.liw-switcher`) wurde dort nicht geladen; `[liw_my_liebherr]`
  und `[liw_pocket]` sind nun Auslöser in `FrontendAssets`. (Nach Auslieferung WP-Rocket-Cache leeren – Frontend-HTML-Cache.)

### Dokumentation
- **Wallet-Pflichtenheft** `…/Pflichtenheft/Programmierpflichtenheft_Wallet_Mehrwaehrung_Token.md` (v1.0) erstellt:
  Erweiterung der Plattform-Wallet (araliya-platform-core) um eine echte **Token-Währung** neben EUR (eine Saldenquelle,
  mehrwährungsfähig), Buckets, `rule_version`, Vier-Augen, Adapter-Vertrag, `TokenAccount`-Migration und die Aktivierung
  der Nähte `liw_ptime_charge_live`/`liw_myl_service_charge`/`liw_myl_refund`/`liw_myl_wallet_live`. Core-Kategorie A, kein Satelliten-Code.

### Verifikation
- Browser: dunkle Kopfleiste auf `/my-liebherr/` (Bar `rgb(32,35,38)`, horizontale Reiter). `tests/run-tests.php` **657/657**.
  `LIW_VERSION` .134→.135.

## [0.1.0-alpha.134] – 2026-09-19 – My Liebherr/Pocket im Theme-Frontend-Menü der Website

### Hinzugefügt
- `Frontend\ThemeMenu`: hängt **My Liebherr** und **Pocket Information** additiv in das Theme-Navigationsmenü ein
  (`wp_nav_menu_items`) – nur am Ziel-Standort (Filter `liw_myl_theme_menu_location`, Standard `primary`), nur für
  **angemeldete** Nutzer mit `liw_myl_access`, nur bei aktivem Flag + veröffentlichter Seite (Pocket zusätzlich
  `liw_pocket_enabled`). Ausgeloggte Besucher sehen die Punkte nicht; andere Menüs bleiben unberührt. Abschaltbar
  über Filter `liw_myl_theme_menu`.

### Verifikation
- `scripts/liw-selftest.php` **425/425**: am `primary`-Standort eingehängt, an anderem Standort nicht.
  `tests/run-tests.php` **657/657**. `LIW_VERSION` .133→.134.

## [0.1.0-alpha.133] – 2026-09-19 – Medien-Pipeline für Dreams/Gallery (§14/§16)

### Hinzugefügt
- `MyLiebherr\MediaPipeline`: prüft in Dreams/Gallery referenzierte Mediathek-Anhänge – **erlaubte MIME-Typen**
  (jpeg/png/webp/gif), **Größenlimit** (`liw_myl_media_max_bytes`, Standard 8 MB), **Virenscan-Naht** (Filter
  `liw_media_scan_passed`) und **Freigabestatus** (CI-005 via `CoreBridge\MediaBridge`). Zustände: none/invalid/
  quarantine/approved; reine Regeln (mime_allowed/size_ok/classify) testbar.
- **Quarantäne (§16):** nicht freigegebene/ungeprüfte Bilder werden nicht angezeigt (`thumb()` → „Bild in Prüfung"
  bzw. „ungültig"); Dreams/Gallery/Shared nutzen `thumb()`. Prüfer können in der Galerie „Bild freigeben"
  (REST `POST /moderation/media/{id}/approve`, `liw_myl_moderate`).
- **Validierung beim Anlegen/Ändern:** Gallery/Dreams-REST lehnt unzulässige Medien ab (`media_mime_not_allowed`/
  `media_too_large`/`media_not_attachment`).
- **Datenschutz-Hinweis (§14)** im Galerie-Formular (Gesichter/Kennzeichen/Kundendaten prüfen).

### Behoben
- Selbsttest „CAPDB Startkonfig" setzt den geteilten Board-Entwurf nun deterministisch zurück (clear + seed),
  robust gegen den opt-in-Seeder `liw-seed-cvf-6cards.php`.

### Verifikation
- `tests/run-tests.php` **655/655** (MIME/Größe/Klassifikation), `scripts/liw-selftest.php` **424/424** (PDF abgelehnt;
  Bild quarantine→Freigabe→approved). `LIW_VERSION` .132→.133.

## [0.1.0-alpha.132] – 2026-09-19 – CVF-Simulation der zwei neuen Karten (§36, MYL 013)

### Hinzugefügt
- `Cvf\BoardRepository::wire_module_card()`: schaltet eine (initial deaktivierte) Bereichskarte simulierbar –
  Bereich aktiv + Route (Seiten-URL = **Return-Route**), **Übergang vom Einstieg** (intelligence_world, `world_granted`)
  und **First-Entry-Text** auf der Seite. Idempotent.
- Seeder `scripts/liw-seed-cvf-6cards.php` (opt-in): verdrahtet **my_liebherr** + **pocket_information** im Board-
  Entwurf, sodass First Entry und Rücksprung beider Karten vollständig in der Board-Simulation laufen. Veröffentlichen
  bleibt bewusst ein separater Schritt (CVF Board Backoffice + `liw_cvf_board_enabled`).

### Hintergrund (Direktlink-Schutz, MYL 013)
- Reihenfolge der sechs Karten fix (Position 1–6); Sichtbarkeit rollenabhängig (WorldSwitcher/Grants). Die
  persönlichen Seiten sind self-gating (Login + `liw_myl_access`) → ein Direktlink ohne Berechtigung zeigt nur den
  Anmelde-/Zugangshinweis, keinen Inhalt.

### Verifikation
- `scripts/liw-selftest.php` **423/423**: Simulation erreicht vom Einstieg beide neuen Karten, First-Entry vorhanden,
  Return-Route gesetzt. `tests/run-tests.php` **650/650**. `LIW_VERSION` .131→.132. Schließt MYL 013 ab.

## [0.1.0-alpha.131] – 2026-09-19 – Moderation: World-Review, Meldungen, Sperren, Erstattung (§11/§13/§31)

### Hinzugefügt
- Prüfer-Capability `liw_myl_moderate` (Rolle „Prüfer"): Vollzugriffs-Rollen + **araliya_ops/araliya_reception**.
- `ReportRepository` + Tabelle `ary_liw_myl_report` (§11-Meldegründe: gefährlich/falsch/veraltet/Datenschutz/
  Rechteverletzung/Duplikat/irreführender Preis), Status open→reviewed/dismissed/actioned.
- `ModerationService` (reine Übergänge + Orchestrierung): **World-Review** von Galerie-Freigaben (pending→published/
  blocked), **Meldung auflösen** (verwerfen / **sperren** §11 QX / **Erstattung** anstoßen). Erstattung ist eine Naht
  (Hook `liw_myl_refund`, echte Wallet-Buchung erst mit dem Wallet-Pflichtenheft); Sperren setzt Galerie-Objekt auf
  `suspended` (+ Hook `liw_myl_moderation_suspend` für andere Objekttypen).
- `ShareRepository::set_status` (Prüfer) + `GalleryRepository::moderate_status` (eigentümerübergreifend, gesperrt).
- REST: `POST /reports` (jeder berechtigte Nutzer), `GET /moderation/queue`, `POST /moderation/shares/{id}/review`,
  `POST /moderation/reports/{id}/resolve` (nur Prüfer).
- Frontend: „Melden"-Formular an den World-Freigaben (`SharedView`); Prüfer-Queue `[liw_moderation]` (nur mit
  `liw_myl_moderate`) mit Freigeben/Ablehnen + Verwerfen/Sperren/Erstattung; auf der My-Liebherr-Seite eingebettet.

### Verifikation
- `tests/run-tests.php` **650/650** (Übergangsregeln + Prüfer-Rollenmapping), `scripts/liw-selftest.php` **422/422**
  (Report→Queue→approve=published + suspend sperrt Objekt). `LIW_VERSION` .130→.131.

## [0.1.0-alpha.130] – 2026-09-19 – Drei-Wort-Label-Vollsystem für Own Adventures (§32)

### Hinzugefügt
- `MyLiebherr\ThreeWordLabel` + Tabelle `ary_liw_myl_three_word_label` (§37): normierter Drei-Wort-Name
  **Maschine · Problem · Handlung** je Objekt/Locale, mit Synonymen und Taxonomie-Version. Reine Logik
  (normalize/from_words/valid/display/suggest/tokens) + Persistenz (set/get) + **Agentensuche** `search()`
  (Begriffe/Synonyme, kombiniert mit stabiler Objekt-ID).
- REST (ContentRest): `GET/PUT my-liebherr/v1/adventures/{id}/label` (Setzen nur durch den **Autor**/Administer) +
  `GET my-liebherr/v1/adventures/labels/search?q=`.
- `OwnAdventuresView`: neue Spalte „Drei-Wort-Name" mit **Vorschlag → Bestätigung** je Beitrag (aus Maschine/Bauteil/
  Titel vorbelegt), Synonym-Feld und einer **Label-Suche** (GET, serverseitig, kombiniert mit dem eigenen Bestand).
- Generischer JS-Formular-Handler unterstützt jetzt `data-liw-method` (PUT).

### Verifikation
- `tests/run-tests.php` **641/641** (Normalisierung/Validierung/Vorschlag/Tokens), `scripts/liw-selftest.php` **421/421**
  (set→get→Agentensuche über Synonym). Dev-Vorschau: „Raupe Hydraulik Entlüften", Synonym „Bagger" findet den Beitrag.
  `LIW_VERSION` .129→.130. Erfüllt MYL 017 vollständig.

## [0.1.0-alpha.129] – 2026-09-19 – My-Overview-Widgets mit echten Daten

### Geändert
- Neuer Aggregator `MyLiebherr\OverviewData` speist die drei bisherigen Platzhalter-Kacheln aus echten Quellen (§5/§30):
  - **Was ist neu** ← mit mir geteilte Bilder (`ShareRepository`) + hohe/kritische Pocket-Infos.
  - **Was muss ich tun** ← offene Kontaktanfragen + zu bestätigende Leistungen (`ContactRepository`) + unquittierte
    Pflicht-Pocket-Infos (`PocketRepository`).
  - **Gebuchte Leistung** ← letzte Wallet-Buchungen (`WalletBridge`).
  - Jede Kachel zeigt Anzahl-Badge + verlinkte Liste (gekürzt auf 4, „+N …"), sonst einen Leer-Hinweis. Rein lesend,
    cross-Modul class_exists-guarded; reine `truncate()`-Logik testbar.

### Verifikation
- `tests/run-tests.php` **634/634** (truncate), `scripts/liw-selftest.php` **420/420** (Tasks+Neu aus echten Daten).
  Dev-Vorschau: „neu" = 2 Alerts, „zu tun" = 2 Quittierungen. `LIW_VERSION` .128→.129.

## [0.1.0-alpha.128] – 2026-09-19 – Pocket Information regelbasiert (automatische, erklärbare Infos)

### Hinzugefügt
- `Pocket\PocketRules`: erzeugt aus den **eigenen** Daten abgeleitete, **erklärbare** Pocket-Infos (§34) – **My Briefing**
  (Zusammenfassung), **Pocket Tasks** (offene Kontaktanfragen + zu bestätigende Leistungen, hoch), **Machine Pocket**
  (je Maschine), **Adventure Pocket** (eigene veröffentlichte Beiträge). Nicht persistiert (keine zweite Wahrheitsquelle),
  je Item Rücksprungziel + Begründung („weshalb angezeigt"). Über `liw_pocket_rules_enabled` (Default AN) abschaltbar (§5).
- `Pocket\FeedService`: merged gespeicherte + abgeleitete Items **priorisiert** (critical→low; gespeicherte vor
  abgeleiteten) – Sicherheits-/Pflichtmeldungen werden nicht durch Empfehlungen verdrängt (§34). Reine `merge()`-Logik testbar.
- `Pocket\Rest::feed` und `PocketView` nutzen jetzt den `FeedService`; abgeleitete Items werden ohne Quittieren/Löschen,
  mit Badge „automatisch" + Begründung dargestellt.

### Verifikation
- `tests/run-tests.php` **630/630** (Feed-Merge-Reihenfolge + derived-Flag), `scripts/liw-selftest.php` **419/419**
  (Feed enthält abgeleitetes My Briefing + Machine Pocket). Dev-Vorschau: kritische Meldung bleibt oben. `LIW_VERSION` .127→.128.

## [0.1.0-alpha.127] – 2026-09-19 – Demo-Seeder My Liebherr + Pocket (Dev-Vorschau)

### Hinzugefügt
- `scripts/liw-seed-demo-my-liebherr.php` (nur `development`): schaltet in Dev die Flags `liw_myl_enabled`/
  `liw_pocket_enabled`/`liw_ptime_enabled` scharf und legt für den ersten Administrator DEMO-Inhalte an (3 Dreams,
  2 Machines, 1 Gallery, 3 Pocket-Items inkl. kritischem Alert mit Pflichtquittierung). Idempotent (Marker-Option),
  `--reset` entfernt Inhalte + schaltet Flags aus. Reine Vorschau/Testdaten, nicht für Produktion.

### Verifikation
- In Dev ausgeführt; alle Reiter rendern für den Admin gefüllt (Overview inkl. Wallet-Sektion, Dreams, Machines,
  Gallery, Pocket mit kritisch-zuerst + Quittieren). `LIW_VERSION` .126→.127.

## [0.1.0-alpha.126] – 2026-09-19 – Übersichtsseite „Liebherr Frontend": My Liebherr + Pocket in der Linkliste

### Geändert
- `Admin\AdminMenu::render_frontend()`: die Link-Liste auf der Übersichtsseite selbst (oben im Seiteninhalt) führt
  nun neben den vier Welten auch **👤 My Liebherr** und **🎒 Pocket Information** (Direktlink zur Live-Seite), passend
  zu den Menüpunkten aus alpha.125. Intro-Text auf „Welten und persönliche Reiter" erweitert.

### Verifikation
- Live geprüft: `render_frontend()` listet beide Links. `LIW_VERSION` .125→.126.

## [0.1.0-alpha.125] – 2026-09-19 – My Liebherr + Pocket im Menü „Liebherr Frontend" verankert

### Geändert
- `Admin\AdminMenu`: Das wp-admin-Menü **Liebherr Frontend** führt jetzt neben den vier Welten auch
  **👤 My Liebherr** und **🎒 Pocket Information** als Direktlinks zur Live-Seite (sobald die Seiten angelegt sind,
  Optionen `liw_my_liebherr_page_id` / `liw_pocket_page_id`). Reihenfolge: IW · LI · IF · Adventures · My Liebherr · Pocket.
  Neuer Helfer `AdminMenu::page_url()`.
- Hintergrund: Bis dahin waren die persönlichen Reiter nur in der frontseitigen **WorldSwitcher**-Leiste verankert
  (gated über `liw_myl_enabled` + Seite + `liw_myl_access`), nicht im Admin-Menü. Das Theme-Frontend-Navigationsmenü
  (`wp_nav_menu`) wird vom Plugin bewusst nicht automatisch verändert (kein `wp_nav_menu_items`-Eingriff).

### Verifikation
- Live geprüft: Untermenü `liw-frontend` listet My Liebherr (`/my-liebherr`) + Pocket (`/pocket-information`).
  `tests/run-tests.php` **625/625**, `scripts/liw-selftest.php` **418/418**. `LIW_VERSION` .124→.125.

## [0.1.0-alpha.124] – 2026-09-19 – My Liebherr bis Reiter 11: My Contacts, My Machines, Pocket Information

Damit sind alle funktionalen My-Liebherr-Reiter 01–11 umgesetzt (12 = Platzhalter). Alles hinter Flags (Default AUS).

### 08 My Contacts (§33, voller Lebenszyklus)
- `MyLiebherr\ContactState` (reine Übergangstabellen), `ContactRepository` (Anfragen, Connections, Service-Exchange),
  `ContactsRest`, `ContactsView` (`[liw_my_contacts]`): Kontaktanfrage → Zustimmung → **Contact Connection**
  (accepted/active/paused/ended/disputed/blocked) → **gemeinsame Leistung** (proposed→confirmed→settled). Erst die
  bestätigte Leistung erzeugt die **Buchungsnaht** (Hook `liw_myl_service_charge`, deferred bis Wallet-Pflichtenheft).
  Sicherheitsgrenze §33: eine Verbindung ist keine Kontovollmacht.
- Neue Tabellen `ary_liw_myl_contact_request` / `_connection` / `_service_exchange`.

### 09 My Machines (§29)
- `MyLiebherr\MachineRepository` + `MachinesView` (`[liw_my_machines]`): eigene Maschinen (Name/Seriennummer/Standort/
  Notiz/Dokument-Link), anlegen/entfernen. Tabelle `ary_liw_myl_machine`. REST in `ContactsRest`.

### 10 Pocket Information (§34, 6. Reiter)
- Neues Modul `src/Pocket/` (`Flags`/`Schema`/`PocketRepository`/`Rest`/`PocketView`): personenbezogener Feed
  (Alerts high/critical zuerst), **Pflichtquittierung** getrennt protokolliert, Rücksprungziel; Tabelle
  `ary_liw_pocket_item`, REST-Namespace `pocket/v1` (feed/items/ack). Shortcode `[liw_pocket]`, eigener Reiter über
  `WorldSwitcher` (aktiv bei `liw_pocket_enabled` + Seite), Seeder `liw-seed-pocket.php` (`/pocket-information/`).

### Gemeinsames
- Generische JS-Handler um `data-liw-root`-Override erweitert (Pocket nutzt `pocket/v1`). Seeder legt Contacts/Machines
  mit auf `/my-liebherr/`. CSS für Contacts/Machines/Pocket. `LIW_VERSION` .121→.124.

### Verifikation
- `tests/run-tests.php` **625/625** (ContactState-Übergänge), `scripts/liw-selftest.php` **418/418** (Contacts-Flow
  Anfrage→…→Leistung-bestätigt + Übergangs-Guard, Machines-CRUD, Pocket Feed+Ack + Flag-Gating, Nav-Pocket aktiv).
  Abnahme `docs/LIW_ABNAHME.md §9`.

## [0.1.0-alpha.121] – 2026-09-19 – My Liebherr R3: My Dreams, Own Gallery + Teilen, Own Adventures, CVF 4→6

Alles hinter `liw_myl_enabled` (Default AUS). Neue Tabellen `ary_liw_myl_dream_item`/`_gallery_item`/`_share_grant`.

### My Dreams (§31, voll)
- `MyLiebherr\DreamRepository` + `DreamsView` (`[liw_my_dreams]`): Maschinen-Bilderbuch mit Bild (Mediathek-ID) oder
  Maschinenbezug, **Drei-Wort-Titel**, Notiz, Tags, Sammlung, Cover, Wunschstatus (Idee/Wunsch/Favorit); hinzufügen/
  ändern/entfernen/ordnen. Privat.

### Own Gallery + Teilen (§31)
- `MyLiebherr\GalleryRepository` + `GalleryView` (`[liw_my_gallery]`): private Bilder (Mediathek-ID), Titel/Beschreibung/
  Tags/Album/Sichtbarkeit.
- `MyLiebherr\ShareRepository`: gezielte Freigaben je Objekt an **Kollegen** (Person/Team, Status `active`) oder an die
  **World** (Status `pending` – Review vorbehalten, keine automatische Weltveröffentlichung), inkl. Widerruf.
- `MyLiebherr\SharedView`: `[liw_shared_colleagues]` (mit mir geteilt) + `[liw_shared_world]` (World-Freigaben mit Status).

### Own Adventures (§32)
- `MyLiebherr\OwnAdventuresView` (`[liw_my_adventures]`): **Wiederverwendung der bestehenden Adventures-Insel** – eigene
  Beiträge (author-gebunden), Status-Filter, Maschine/Bauteil + Tokenwert (aus `Adventures\RegistrationService`). Keine zweite Datenhaltung.

### CVF 4→6 (§36)
- `Cvf\BoardRepository::seed_start_config` ergänzt additiv die Karten **my_liebherr** und **pocket_information**,
  initial **deaktiviert** (`status=inactive`) und ohne Übergänge → bestehende Viererflows/Runtime unverändert.

### Gemeinsames
- `MyLiebherr\ContentRest` (REST dreams/gallery/shares), `ContentRules` (reine Wertlisten/Drei-Wort-Normalisierung),
  generische JS-Handler (Formular-POST + Aktions-Buttons) in `liw-my-liebherr.js`, CSS für Grids/Shares/Filter.
  Seeder `liw-seed-my-liebherr.php` legt alle Bereiche auf `/my-liebherr/`. `LIW_VERSION` .117→.121.
- Fix: Dream-Spalte `rank` → `sort_rank` (MySQL-8-reserviertes Wort).

### Verifikation
- `tests/run-tests.php` **599/599**, `scripts/liw-selftest.php` **413/413** (Dreams-CRUD, Gallery+Shares-Round-Trip,
  CVF-6-Seed, Own-Adventures-Render; CAPDB-Bestandstests auf 6 Bereiche nachgezogen). Abnahme `docs/LIW_ABNAHME.md §9`.

## [0.1.0-alpha.117] – 2026-09-19 – My Liebherr R2: My Wallet (S8), read-only über die Plattform-Wallet

### Hinzugefügt
- **`MyLiebherr\WalletView`** (`[liw_my_wallet]`, auch in die My-Overview-Seite eingebettet): read-only Wallet-Ansicht
  auf die **Plattform Health Wallet** über `CoreBridge\WalletBridge` — Saldo, gutgeschrieben/belastet gesamt,
  Jahresbudget + die letzten 10 Buchungen als verständliche Belege (Datum, Art, Betrag ±, Status). **Keine zweite
  Saldenquelle** (§7); keine Schreibbuchungen (Kauf/Abrechnung laufen über die Fachprozesse).
- `CoreBridge\WalletBridge` um `source_label()`/`status_label()` (lesbare, übersetzbare Buchungs-/Statuslabels) erweitert.
- Schnellaktion „Wallet öffnen" verweist jetzt auf die Wallet-Sektion (`#liw-my-wallet`) statt in den Admin-Planner.

### Hinweis (S7)
- Der Wallet-Adapter (`CoreBridge\WalletBridge`) ist damit als lesende Fassade abgeschlossen. Schreibende Token-
  Buchung / Mehrwährung bleibt Core-Kategorie A (kommendes Wallet-Pflichtenheft); Buchungsnaht weiter Flag AUS.

### Verifikation
- `tests/run-tests.php` **579/579** (Label-Helfer), `scripts/liw-selftest.php` **409/409** (`[liw_my_wallet]`-Render).
  Abnahme `docs/LIW_ABNAHME.md §9` (MYL 004/006).

## [0.1.0-alpha.116] – 2026-09-19 – My Liebherr R1-Breite: Dashboard (S3), Profil (S5), R1-Abnahme (S6)

Alles hinter `liw_myl_enabled` (Default AUS). Abnahme in `docs/LIW_ABNAHME.md §9`.

### S3 – Dashboard
- `MyLiebherr\WidgetCatalog` (berechtigte Widgets je Cap), `DashboardService` (reine Merge-/Sanitize-/Reset-Logik:
  nur berechtigte Widgets, gespeicherte Reihenfolge/Sichtbarkeit gewinnen, Unbekanntes fällt weg), `DashboardRepository`
  + Tabelle `ary_liw_myl_dashboard_layout` (je Nutzer/Gerätetyp), REST `GET/PUT my-liebherr/v1/dashboard` (inkl. `reset`).
- My Overview rendert die Widgets in persönlicher Reihenfolge/Sichtbarkeit mit Bedienelementen (↑ ↓ ✕, „Ausgeblendet"-Tray,
  Zurücksetzen); JS `liw-my-liebherr.js` speichert serverseitig (§5).

### S5 – Profil & Rollen
- `MyLiebherr\ProfileView` (`[liw_my_profile]`, auch in die Overview eingebettet): Stammdaten/Rollen/Organisationen lesbar,
  Formular für Persona/Sprache/Zeitzone/aktive Organisation → PATCH /me (Feldfreigabe §18), Datenschutz-Hinweis (Einstieg §14).

### S6 – R1-Abnahme
- Negativtest mit echtem Subscriber: `/me` liefert nur den eigenen Nutzer; Subscriber hat `liw_myl_access` ohne
  `liw_myl_administer`; Zugriff auf fremdes Objekt verboten (SEC 01). Abnahmematrix MYL 001–003/012/025–028 + SEC 01.

### Verifikation
- `tests/run-tests.php` **576/576**, `scripts/liw-selftest.php` **408/408** (Dashboard-REST-Round-Trip + reset,
  Profil-Shortcode, Subscriber-Negativtest; selbst-bereinigt).

## [0.1.0-alpha.113] – 2026-09-19 – My Liebherr Durchstich S2–S11 (Navigation, Overview, Plattformzeit-Schachuhr)

Vertikaler Durchstich nach ADR-LIW-MYL-001, alles hinter Flags (`liw_myl_enabled`/`liw_ptime_enabled`, Default AUS).

### S2 – Sechs-Reiter-Navigation
- `Frontend\WorldSwitcher` um `platform_tabs()` erweitert: die vier Inseln plus – nur bei `liw_myl_enabled`,
  angemeldet und mit `liw_myl_access` – der Reiter **My Liebherr** (rollenabhängig, nur bei veröffentlichter Seite)
  sowie **Pocket Information** als sichtbarer, deaktivierter Platzhalter „in Vorbereitung" (JW-Entscheid).
  `worlds()` bleibt bewusst vierinselig (CVF-Modulauflösung unverändert, keine Redundanz).

### S4 – My Overview (Bankkonto-Startseite)
- `MyLiebherr\OverviewView` (`[liw_my_liebherr]`, self-gating, nur angemeldet + `liw_myl_access`): Begrüßung,
  vier Kacheln (Was besitze ich / neu / zu tun / gebucht), Schnellaktionen, Hinweis auf die Session-Uhr.
- **Read-only Wallet:** neuer `CoreBridge\WalletBridge` → Plattform Health Wallet (`WalletService`): Saldo/Summary/
  Transaktionen (EUR-Cent), guarded über `class_exists`; **keine zweite Saldenquelle** (§7).
- Seeder `scripts/liw-seed-my-liebherr.php` (Seite `/my-liebherr/`, Vollbild-Vorlage, Option `liw_my_liebherr_page_id`).

### S9–S11 – Plattformzeit / Session-Uhr / Token-Schachuhr (§41)
- `PlatformTime\Schema` → Tabellen `ary_liw_ptime_session` + `ary_liw_ptime_charge` (append-only, UNIQUE idempotency_key).
- `PlatformTime\TokenRule` (versioniert, **10 Token/Minute** Standard, konfigurierbar `liw_ptime_token_per_min`),
  `SessionClock` (serverautoritäre, konservative Zeitfortschreibung; Idle > Timeout zählt nicht), `SessionRepository`
  (Reservieren beim Eintritt → Bestätigen beim Verlassen), `ChargeService` (genau ein Abrechnungssatz je Abschnitt,
  Idempotenz; Wallet-Buchung als Naht hinter `liw_ptime_charge_live`, sonst *ausstehend* protokolliert + Hook `liw_ptime_charge`).
- `PlatformTime\Rest`: `my-liebherr/v1/platform-time/{start,heartbeat,status,stop}` (angemeldet + `liw_myl_access`, self-gating).
- `PlatformTime\ClockWidget` (S11): schwebende Session-Uhr **unten links** (kollidiert nicht mit dem Hilfe-Koffer),
  ein-/ausklappbar, Zeit + laufende Token; Anzeige tickt lokal, maßgeblich der serverautoritäre Heartbeat (~30 s).
  Assets `liw-ptime-clock.js/.css` (reduced-motion-fest).

### Verifikation
- `tests/run-tests.php` **564/564**, `scripts/liw-selftest.php` **404/404** (Schema, Rollen, Flag-Gating,
  Session-Round-Trip start→heartbeat→stop=60 s/10 Token pending + Idempotenz, Nav-Platzhalter, Overview-Shortcode,
  Plattformzeit-REST). Seeder in Dev verifiziert (Seite #4176). Optionen/Seiten-ID nur Dev-DB → auf Staging/Live erneut setzen.

## [0.1.0-alpha.108] – 2026-09-19 – My Liebherr Fundament S1 (MYL-CORE: Kontext, Entitlements, /me)

### Hinzugefügt
- **Modulbereich `src/MyLiebherr/`** (ADR-LIW-MYL-001, Stufe S1, hinter Flag `liw_myl_enabled`, Default AUS):
  - `Schema` – zwei Tabellen `ary_liw_myl_profile` (persönliches Profil je Nutzer) + `ary_liw_myl_membership`
    (Nutzer × Organisation × Rolle), additiv über `create_tables()`/`maybe_upgrade_database()`.
  - `Roles` – zwei Caps `liw_myl_access` (jede angemeldete Rolle) + `liw_myl_administer` (Vollzugriff), auf
    bestehende ARALIYA-/WP-Rollen gemappt (keine eigenen Rollen), selbstheilend bei Aktivierung/Upgrade.
  - `EntitlementService` – reine Schnittmengen-Logik (Cap × Objektbezug × Organisation; fremde Objekte nur
    mit Administer), SEC 01: Sichtbarkeit ≠ Berechtigung.
  - `Context` – persönliche Sicht (Profil, Rollen, Mitgliedschaften, aktive Org/Rolle, Caps) + Feld-Allowlist
    und `sanitize_patch()` (Feldfreigabe §18; unzulässige Persona/Felder werden verworfen).
  - `ProfileRepository` / `MembershipRepository` – Persistenz (ensure/get/update bzw. Lesen).
  - `Rest` – `GET/PATCH my-liebherr/v1/me` (angemeldet; nur eigener Nutzer, Objektfilter; self-gating → disabled ohne Flag).
- Verdrahtet in `Bootstrap::init()` (REST) sowie Aktivierung/Upgrade/Deaktivierung (Tabellen + Rollen-Grant/Revoke).

### Verifikation
- `tests/run-tests.php` **537/537** (Rollen-Mapping, Kontext-Feldfreigabe, Entitlement-Kernlogik),
  `scripts/liw-selftest.php` **398/398** (Tabellen, Rollen-Grant, Flag-Gating, Profil-Round-Trip, echter
  REST-`/me`-Durchlauf inkl. PATCH). Testdaten selbst-bereinigt.

## [0.1.0-alpha.107] – 2026-09-19 – My Liebherr: Plattformzeit integriert + kompletter Programm-Workflow

### Dokumentation
- **Pflichtenheft `Programmierpflichtenheft_My_Liebherr.md` (v2.0) um §41 erweitert:** „Plattformzeit,
  Session-Uhr und Token-Schachuhr" – serverautoritäre Zeitmessung (Eintritt→Austritt), versionierte
  Zeit-Tokenregel, append-only Abrechnung, Wallet-Naht (deaktivierbar bis zum kommenden Wallet-Pflichtenheft),
  schwebendes Schachuhr-Widget. Eingewoben in Wallet-Buchungsmodell (§7), Datenmodell/REST (§37), CVF-Board
  (§36) und Abnahme (neue **MYL 025–028**). §40 Gesamtfazit ergänzt.
- **Neuer Arbeits-Workflow `docs/ADR-LIW-MYL-001_MyLiebherr_Programm_Workflow.md`:** überführt alle
  Programmmodule (R0–R6, sechs Reiter inkl. Pocket Information, CVF-6-Karten, Plattformzeit) in 25 prüfbare
  Stufen S0–S24 mit Dateien/Tabellen/REST/Tests/Abnahme-IDs, Modul-Landkarte, Wiederverwendungsplan der
  bestehenden IW-/CVF-Bausteine und dem gebündelten §38-Startklärungsblock als Phase-0-Gate.

### Startklärung freigegeben + Wallet-Bestandsaufnahme
- Alle §38-Fragen freigegeben (Joseph, 19.09.). **Frage 1 nach Sichtung der echten Plattform-Wallet korrigiert:**
  Saldenquelle ist die **Plattform Health Wallet** (`Modules\Wallet\WalletService`, EUR-Cent, keyed by user_id,
  append-only Ledger + Idempotenz + Reservieren→Bestätigen, ARY-PH-HW) über einen **neuen `CoreBridge\WalletBridge`**;
  der Satelliten-`Adventures\TokenAccount` wird abgelöst (keine zweite Saldenquelle, §7).
- **Token = echte eigene Währung im selben Wallet-Subsystem** → Plattform-Wallet muss mehrwährungsfähig werden.
  Das ist **Core-Kategorie A und gehört ins kommende Wallet-Pflichtenheft** — nicht Teil dieses Programms; Buchungsnaht
  bleibt per Flag AUS, Plattformzeit (§41) protokolliert nur *ausstehende* Token-Abrechnungen. §41 entsprechend präzisiert.

### Hinweis
- Reine Dokumentation/Planung – kein Modul-Code. Umsetzung startet erst nach Freigabe (ADR-LIW-MYL-001 §4/§9).
  Version angehoben gemäß Commit-je-Auslieferung.

## [0.1.0-alpha.75] – 2026-09-19 – Goldener Globus statt WordPress-Logo (Adminleiste + Login)

### Geändert
- Der goldene Globus ersetzt jetzt auch das **WordPress-„W" oben links in der Admin-Leiste** (Frontend-Toolbar
  und wp-admin) und das **große WordPress-Logo auf der Login-Seite** – per CSS in `FaviconService`
  (Selektoren greifen nur im jeweiligen Kontext, kein Markup-Eingriff). Zusätzlich verlinkt das Login-Logo
  auf die Seite (statt wordpress.org) und trägt den Seitennamen als Text (statt „Powered by WordPress").
- Ergänzt den bereits vorhandenen Browser-Tab-Favicon (goldener Planet, alpha.49).

### Verifikation
- `tests/run-tests.php` **408/408**, `scripts/liw-selftest.php` **349/349** (Adminleisten- + Login-Logo-CSS,
  Login-Link/Text). Browser: Login-Seite zeigt den Globus, Link → Seite, Alt-Text = Seitenname.

## [0.1.0-alpha.74] – 2026-09-19 – Simulation Builder: austauschbare Engine-Naht

### Hinzugefügt
- **Engine-Vertrag** `SimulationEngineInterface` + Standard `SimulationMockEngine` (kapselt `SimulationModel`)
  + Resolver `SimulationEngine::resolve()`/`is_mock()` (Filter `liw_iw_simulation_engine`). Eine echte
  Simulations-Engine (Gruppe-B-Zulieferung) kann so ohne Umbau des Builders eingehängt werden.
- **REST** `POST liw-iw/v1/simulate` rechnet über die aktive Engine. Der Simulation Builder rendert die
  Default-Prognose über die Engine; ist eine echte Engine registriert, rechnet auch das Frontend serverseitig
  (Flag `liwIwSim.useServer`), sonst weiter lokal (Mock, ohne Roundtrips).

### Hintergrund
- Schließt die Architektur der Frage „Cockpit ↔ Simulations-Engine": Cockpit → Simulation Builder (alpha.73) →
  Engine-Naht (alpha.74). Die *echte* Engine bleibt externe Zulieferung (Gruppe B).

### Verifikation
- `tests/run-tests.php` **408/408** (Mock = Standard, forecast == SimulationModel), `scripts/liw-selftest.php`
  **347/347** (REST simulate mock-1; Filter-Override „real-x" greift; nach Entfernen wieder Mock).

## [0.1.0-alpha.73] – 2026-09-19 – Cockpit → Simulation Builder verbunden

### Geändert
- Das Cockpit („Start your journey / Go", `[liw_simulator]`) führt jetzt gezielt in den **Simulation Builder**:
  Standard-Ziel = Intelligence-World-Seite + Anker `#liw-iw-simulation` (weiterhin per Option/Filter
  `liw_simulator_target` überschreibbar). Nach dem Eintritt (Access Gate) scrollt die Weltansicht automatisch
  zum Simulation Builder. Damit ist die zuvor nur navigatorisch getrennte Kette Cockpit → Simulation Builder
  geschlossen (die Rechenlogik bleibt das Mock-`SimulationModel`; eine echte Engine ist die Gruppe-B-Zulieferung).

### Verifikation
- `tests/run-tests.php` **401/401**, `scripts/liw-selftest.php` **344/344** (Cockpit-„Go" verlinkt
  `#liw-iw-simulation`). Browser: Go-Link korrekt; Eintritt enthüllt die Welt (Auto-Scroll best-effort).

## [0.1.0-alpha.72] – 2026-09-19 – Content Board: Medien-Picker auf freigegebene Bibliothek [Job A11]

### Hinzugefügt
- **Approved-Media-Filter** `Admin\ApprovedMediaFilter`: beschränkt den WordPress-Medien-Modal auf freigegebene
  Anhänge (`_liw_media_approved = 1`), aber nur wenn die Abfrage das Flag `liw_approved_only` trägt. Dieses Flag
  setzt ein seitengebundenes Skript ausschließlich auf den Liebherr-Backoffice-Board-Seiten (Slug `liw-…`).
  Damit greift CI-005 zuverlässig in der LIW-Redaktion, ohne die globale Mediathek zu verändern und ohne das
  unzuverlässige Raten am `post_id`-Kontext (bewusst vermieden).

### Erledigt (Backlog Gruppe A, Punkt 11 – letzter Punkt)
- Damit ist die gesamte intern baubare Abarbeitungsliste (A1–A11) abgeschlossen.

### Verifikation
- `tests/run-tests.php` **401/401** (`restrict`/`maybe_restrict`), `scripts/liw-selftest.php` **343/343**.

## [0.1.0-alpha.71] – 2026-09-19 – Intelligence World: serverseitige Protokoll-PDF [Job A10]

### Hinzugefügt
- **Serverseitiger PDF-Generator** `IntelligenceWorld\PdfDocument` (ohne Fremdbibliothek, Core-Font Helvetica,
  A4, automatischer Seitenumbruch, Nicht-ASCII ASCII-nah transliteriert) + `ProtocolBuilder::to_lines()`.
- **REST** `GET liw-iw/v1/session/protocol-pdf` streamt das Nutzungs-/Kostenprotokoll als echtes PDF
  (Content-Disposition attachment). Im Protokoll am Sitzungsende gibt es zusätzlich zum Browser-Druck den
  Link „PDF herunterladen (Server)".

### Erledigt (Backlog Gruppe A, Punkt 10)

### Verifikation
- `tests/run-tests.php` **397/397** (`to_lines`; PDF `%PDF…%%EOF`, transliteriert), `scripts/liw-selftest.php`
  **342/342** (Route registriert; gültiges PDF aus Sitzungsprotokoll). `file` erkennt „PDF document, version 1.4".

## [0.1.0-alpha.70] – 2026-09-19 – Simulation Builder: Szenarien speichern & vergleichen [Job A9]

### Hinzugefügt
- Im Simulation Builder lassen sich berechnete Szenarien **speichern** („Szenario speichern") und **nebeneinander
  vergleichen**: eine Vergleichstabelle stellt Szenario, Zeithorizont, Start-/Endwert und Gesamt-Δ pro
  gespeichertem Szenario gegenüber; einzelne per Auswahl, „Alle löschen" leert die Liste. Ablage lokal im
  Browser (localStorage, try/catch, max. 6) – passend, da der Simulation Builder ohne WP-Login läuft.

### Erledigt (Backlog Gruppe A, Punkt 9)

### Verifikation
- `tests/run-tests.php` **393/393**, `scripts/liw-selftest.php` **340/340** (Speichern/Vergleichen-Steuerung
  gerendert). Browser: 2 Szenarien gespeichert → Vergleichstabelle (5 Zeilen × 2 Szenarien).

## [0.1.0-alpha.69] – 2026-09-19 – Local Intelligence: Szenario-Editor im Board [Job A8]

### Hinzugefügt
- **Szenario-Editor** im Local-Intelligence-Board (Modul 4 – Simulation World): die A/B/C-Varianten
  (Key, Label, Zusammenfassung + Kennzahlen-Zeilen „Label | Wert") sind jetzt redaktionell pflegbar –
  kompaktes Strukturfeld (Kopfzeile je Variante, darunter „- Label | Wert"). Vorher nur über Seeder/Defaults.
  Parser `parse_scenarios()`; Bereinigung/Ablage über die bestehende `LocalIntelligenceContent::sanitize()`.

### Erledigt (Backlog Gruppe A, Punkt 8)

### Verifikation
- `tests/run-tests.php` **393/393** (Parser: 2 Szenarien, Zeilen/Key/Summary), `scripts/liw-selftest.php`
  **339/339** (Board rendert den A/B/C-Editor).

## [0.1.0-alpha.68] – 2026-09-19 – Adventures: Get-Help-Assistent (Phase 3) [Job A7]

### Hinzugefügt
- **Get-Help-Assistent** `Adventures\GetHelpAssistant` (Shortcode `[liw_adventures_help]`, inline in der Insel
  + Hero-CTA „Get Help"): geführte Schritte für Service-/Notfallsituationen (zuerst sichern → Lage einschätzen →
  qualifizierte Hilfe kontaktieren → Beitrag als Service/Hilfe erfassen → nachverfolgen), barrierefrei als
  `<details>`, mit CTA zur Erfassung. Kritische Beiträge werden weiterhin nie automatisch veröffentlicht (§22.5).

### Erledigt (Backlog Gruppe A, Punkt 7)

### Verifikation
- `tests/run-tests.php` **392/392** (5 Schritte, Shortcode), `scripts/liw-selftest.php` **338/338** (Assistent in
  der Insel + Shortcode rendert 5 Schritte).

## [0.1.0-alpha.67] – 2026-09-19 – Adventures: Medien-Upload [Job A6]

### Hinzugefügt
- **Bild-Upload** `Adventures\UploadService` + REST `POST liw-adv/v1/upload` (nur mit Zugang): legt aus einem
  hochgeladenen Bild (JPG/PNG/WebP/GIF) einen WordPress-Anhang über die Core-Medienpipeline an und liefert
  Attachment-ID + URL. Die Eingabemaske erhält ein Datei-Feld (mit Status); das hochgeladene Bild wird als
  Beitragsbild gesetzt und im Stream/Detail angezeigt. Externe Bild-URL bleibt als Alternative.
- Typprüfung serverseitig (`is_allowed_ext`, `mimes`-Override) – nur Bildtypen im MVP.

### Erledigt (Backlog Gruppe A, Punkt 6)
- Bild-Upload umgesetzt. **Offen (bewusst, §14):** Video-Upload + Transcoding (spätere Etappe).

### Verifikation
- `tests/run-tests.php` **389/389** (`is_allowed_ext` akzeptiert Bilder, lehnt exe/pdf ab),
  `scripts/liw-selftest.php` **337/337** (Route `/upload` registriert; `handle()` lehnt fehlende Datei/falschen
  Typ ab). Der eigentliche Upload läuft über die WP-Core-Medienpipeline (Browser/HTTP).

## [0.1.0-alpha.66] – 2026-09-19 – Adventures: echtes Tokenbudget-Konto [Job A5]

### Hinzugefügt
- **Tokenkonto je Nutzer** `Adventures\TokenAccount` (User-Meta `_liw_adv_token_balance`): persistiertes
  Guthaben mit Start-Guthaben (Option/Filter `liw_adv_token_default`, Standard 1000), `balance()`, `charge()`
  (bucht nur bei Deckung, keine Überziehung), `grant()` (aufladen).
- **Belastung beim Zugriff:** `Rest::accept` prüft das Budget gegen das Kontoguthaben und **bucht den Tokenwert
  tatsächlich ab** (nur bei echter Budgetbelastung – nicht bei Autor/kostenlos/Berechtigung); Antwort enthält
  das neue Guthaben. Der Akzeptanz-Dialog zeigt „Ihr Tokenkonto" und aktualisiert es nach der Buchung.
- **Backoffice:** „🗺 Adventures"-Board bekommt „Tokenkonto aufladen" (Nutzer-ID + Tokens, admin-post).

### Erledigt (Backlog Gruppe A, Punkt 5)

### Verifikation
- `tests/run-tests.php` **386/386**, `scripts/liw-selftest.php` **335/335** (balance/charge/grant/Überziehung;
  `Rest::accept` bucht 40 ab → Guthaben 60; zu wenig Guthaben → abgelehnt).

## [0.1.0-alpha.65] – 2026-09-19 – Adventures: Suche & Filter nach Maschine/Bauteil [Job A4]

### Hinzugefügt
- **Freitextsuche** (Titel/Beschreibung) und **Facetten Maschine/Modell + Bauteil/Komponente** im Adventures-
  Stream (Filterleiste). Neue Meta-Felder `_liw_adv_machine`/`_liw_adv_component`; in der Eingabemaske
  (`SubmissionForm`) erfassbar, im View-Modell + auf der Detailseite ausgewiesen.
- `AdventureService::query()` unterstützt `search` (WP-`s`) + `machine`/`component` (Teilstring, `LIKE`);
  REST `liw-adv/v1/stream` reicht die Parameter durch; das Frontend filtert live (Debounce bei Texteingabe).

### Erledigt (Backlog Gruppe A, Punkt 4)

### Verifikation
- `tests/run-tests.php` **384/384**, `scripts/liw-selftest.php` **332/332** (Maschine „9200" trifft nur den
  Bagger, Bauteil „Getriebe" nur den Kran, Freitextsuche nach Titel, `to_view` trägt Maschine/Bauteil).

## [0.1.0-alpha.64] – 2026-09-19 – Adventures: Detailseite eines Beitrags [Job A3]

### Hinzugefügt
- **Detailseite** `Adventures\DetailView` (Shortcode `[liw_adventure_detail]`, aufrufbar über `?adv=<ID>` auf der
  Adventures-Seite): Titel, Klassifikation, Ort (three words) + Region, Registrierungsstatus, Tokenwert +
  Nutzungsumfang, Artikelbook-Referenz und Medium. Die Karten im Stream verlinken jetzt auf die Detailseite
  („Details ansehen"), statt direkt den Dialog zu öffnen.
- **Inhalts-Gate (§5):** Bei kostenpflichtigen Beiträgen ist die Story erst nach Tokenakzeptanz sichtbar. Der
  bezahlte Text wird **nicht** vorab ins DOM eingebettet – nach Bestätigung über den bestehenden Dialog
  (alpha.61) lädt die Seite neu und der Server zeigt den Inhalt, weil dann ein Zugriff protokolliert ist.
  Eigene und kostenlose Beiträge sowie bereits abgerufene werden direkt gezeigt.
- `TokenLedger::has_access()` – prüft, ob ein Nutzer den Beitrag (in der Version) bereits bestätigt abgerufen
  hat; verhindert doppelte Belastung bei erneutem Aufruf (§6).

### Erledigt (Backlog Gruppe A, Punkt 3)
- „Adventures – Detailseite" abgeschlossen (Grundlage für World-Map-/Detail-Verlinkung).

### Verifikation
- `tests/run-tests.php` **384/384**, `scripts/liw-selftest.php` **328/328** (Shortcode registriert; `has_access`;
  Autor sieht Inhalt, Nicht-Ersteller ohne Zugriff erhält das Token-Gate mit verborgenem Inhalt). Browser:
  `/liebherr-adventures/?adv=<ID>` zeigt Kopf + Gate „Zugriff bestätigen · 40 Tokens", Inhalt nicht im DOM.

## [0.1.0-alpha.63] – 2026-09-19 – Intelligence World: Compute-Metering + kostenpflichtige Module [Job A2]

### Hinzugefügt
- **Modul-/Compute-Katalog** `IntelligenceWorld\ModuleCatalog` (rein, testbar): 5 abrechenbare Aktionen
  (Datenabfrage 0,15 · Datenquelle 0,50 · Simulation 5,00 · Compute-Job 1,20/Einheit · Export 2,00) mit
  Ereignistyp, Preiseinheit (`PriceRule`) und Mock-Preis in Minor-Units.
- **Metering-REST** `POST liw-iw/v1/session/use` (`Rest::use_module`): schreibt bei aktiver Sitzung ein
  Ereignis mit Kosten ins Ereignis-Ledger (Metadaten: action/label/units/cost_minor).
- **Protokoll-Integration** (`ProtocolBuilder`): kostenpflichtige Ereignisse werden als **Posten** ausgewiesen
  (`line_items`), plus **Modulsumme**, **Basiskosten (Zeit)** und **Gesamtkosten** (`billing.modules_cost_minor`/
  `total_cost_minor`). Das Nutzungs-/Kostenprotokoll enthält damit die Zusatzkosten als eigene Tabelle.
- **Frontend**: Panel „Kostenpflichtige Module & Rechenlast (Demo)" im Funktions-Hub – Buttons je Aktion mit
  Preis; laufende **Zusatzkosten**-Anzeige; das Protokoll am Sitzungsende listet Posten + Gesamtsumme.

### Erledigt (Backlog Gruppe A, Punkt 2)
- „Compute-Metering (Mock) + kostenpflichtige Module/Rechenlast als Ledger-Ereignisse (§6.4/§8)" und
  „Modul-/Compute-Ereignisse ins Protokoll" abgeschlossen.

### Verifikation
- `tests/run-tests.php` **381/381** (Katalogpreise, Protokoll: Basis 900 + Module 530 = 1430),
  `scripts/liw-selftest.php` **324/324** (Route `session/use`; use_module belastet 0,15 + Ledger; Protokoll mit
  Posten + Gesamtsumme). Browser end-to-end: Simulation 5,00 + Datenabfrage 0,15 → Zusatzkosten 5,15;
  Protokoll Basis 1,17 + Module 5,15 = **Gesamt 6,32 EUR**.

## [0.1.0-alpha.62] – 2026-09-19 – Intelligence World: Backoffice-Pflege-Board (Tarife + Navigation/Hotels) [Job A1]

### Hinzugefügt
- **Pflege-Board** `Admin\Pages\IntelligenceWorldBoardPage` (Menü „🪐 Intelligence World"): administrierbare
  Bearbeitung von **Tarifen/Budgets** (Preis/Sek., Abrechnungstakt, Budget + Zeitraum, Speicher + „min."-Flag,
  Demo-Code) und **Eintrittstexten** (Option `liw_iw_world`) sowie des **Katalogs Navigation & Hotels** –
  13 Produktsegmente, Lösungswelt und 6 Hotels je mit Drei-Wörter-Ort (Option `liw_iw_catalog`); die zwei
  Platzhalter-Hotels sind damit kuratierbar. Speichern über admin-post (Nonce + `CAP_MANAGE_CONTENT`);
  Bereinigung über die bestehenden `sanitize()`/`save()` der Content-Klassen.
- `save_from_request()` von der HTTP-Schicht getrennt (reines Array) → unit-testbar; Checkboxen werden auf 0/1
  normalisiert (Abwählen greift), Beträge in Minor-Units mit Live-Klartext-Hinweis (z. B. „0,09 EUR / Sek.").

### Erledigt (Backlog Gruppe A, Punkt 1)
- Damit ist der zurückgestellte Punkt „Navigation & Hotels – Backoffice-Pflegemodul" **und** „Preismodell –
  Backoffice-Formular zur Pflege der Tarife/Budgets" abgeschlossen.

### Verifikation
- `tests/run-tests.php` **374/374**, `scripts/liw-selftest.php` **321/321** (Seite registriert; `save_from_request`
  Round-Trip Tarif + Katalog; Zurücksetzen auf Defaults). Headless zusätzlich: Preis/Budget/Segment/Hotel geändert
  und persistiert, `storage_is_minimum` abwählbar.

## [0.1.0-alpha.61] – 2026-09-19 – Adventures: Tokenakzeptanz-Dialog beim Zugriff (§5/§6)

### Hinzugefügt
- **Zugriff-Button** an jeder Adventure-Karte (Frontend, Server- und JS-Render): „Zugriff · N Tokens" bzw.
  „Ansehen (kostenfrei)".
- **Tokenakzeptanz-Dialog** (Modal, `liw-adventures.js`): zeigt **vor** der Bestätigung Tokenwert,
  Nutzungsumfang, Version und die Nutzungsbedingungen (§6: Vorschau + Tokenwert sichtbar vor Bestätigung).
  Zugriff erst nach Akzeptanz der Bedingungen (Checkbox) **und** ausdrücklicher Bestätigung der
  Tokenverwendung → REST `accept` → revisionssichere Protokollierung; Ergebnis zeigt Belastung +
  Transaktions-ID. Eigene Beiträge „kostenfrei", nicht registrierte Beiträge werden abgewiesen,
  unzureichendes Budget wird gemeldet.
- View-Modell (`AdventureService::to_view`) trägt jetzt `token_value` + `reg_status`;
  `RegistrationService::access_preview` liefert `usage_label` + `status_label` (+ `RegistrationService::usage_label()`).

### Verifikation
- `tests/run-tests.php` **372/372**, `scripts/liw-selftest.php` **318/318** (View-Modell mit Tokenwert;
  `Rest::access` zeigt Tokenwert + Nutzungsumfang-Label vor Bestätigung; `Rest::accept` belastet + Transaktions-ID).
  Browser: Zugriff-Button „· 30 Tokens", Dialog mit Tokenwert/Umfang/Version + Bedingungen + Bestätigung
  (der geladene Zustand erfordert Login; anonym greift korrekt das Login-Gate).

### Hinweis
- Prototyp (§21): Tokenbudget via Filter `liw_adv_token_budget` (Standard 1000); echtes Budget-Konto später.

## [0.1.0-alpha.60] – 2026-09-19 – Adventures: Workboard-Optik-Eingabemaske (Frontend + Backend) mit Token-Bewertung

### Hinzugefügt
- **Gemeinsame Eingabemaske** `Adventures\SubmissionForm` (Workboard-Optik) – EIN Renderer für **Frontend
  (`[liw_adventures]`) und Backend-Board**. Felder: Kategorie (Inhaltstyp) + Dringlichkeit, Titel/Beschreibung,
  Sichtbarkeit/Ortsschutz, **frei definierbarer Tokenwert + Nutzungsumfang** und **Rechte-Zusicherung**.
  Aktionen: „Als Entwurf sichern" und „Registrieren & im Artikelbook eintragen".
- **Backoffice-Board** `Admin\Pages\AdventureBoardPage` (Menü „🗺 Adventures"): dieselbe Maske plus ein Board
  über alle Beiträge (Status, Tokenwert, Version, Artikelbook-Referenz) mit Moderations-Aktionen (Validieren,
  Für World freigeben, Sperren, Archivieren) über REST `liw-adv/v1/moderate`.
- Frontend-Submit (`liw-adventures.js`) sendet Tokenwert/Nutzungsumfang/Rechte-Zusicherung; Aktion
  „register" registriert direkt (verlangt bestätigte Rechte) und zeigt die Artikelbook-Referenz.

### Verifikation
- `tests/run-tests.php` **372/372**, `scripts/liw-selftest.php` **315/315** (Maske Frontend+Backend mit
  Token/Nutzungsumfang/Rechte/Registrieren; Board-Seite registriert). Headless gerendert: Admin-Board 7,9 KB
  inkl. Maske + Board-Tabelle; Frontend-Maske mit erzwungenem Zugang. (Sichtprüfung im Browser erfordert Login;
  anonym erscheint korrekt die Zugangs-Notiz.)

### Hinweis
- Bewusster Nachbau statt Wiederverwendung des Core-Workboards (nicht cross-plugin-fähig); frei wählbarer
  Tokenwert statt fester Core-Pauschale. Prototyp (§21): Tokenbudget via Filter, Artikelbook-Verknüpfung als Naht.

## [0.1.0-alpha.59] – 2026-09-19 – Adventures: Basislogik Registrierung, Tokenwert & Artikelbook (Fundament + REST)

### Hinzugefügt (serverseitiges Fundament der „Adventure Area"-Basislogik §1–§9)
- **`Adventures\RegistrationStatus`** (rein): Status-Automat mit 9 Status (Entwurf → eingereicht → registriert →
  Validierung beantragt → validiert → freigegeben, plus Nachbesserung/Gesperrt/Archiviert), erlaubte Übergänge,
  Veröffentlichungsstufen (nur registriert = im Firmennetz nutzbar; nur freigegeben = im Liebherr-World-Netz).
- **`Adventures\TokenPolicy`** (rein): **frei definierbarer Tokenwert** vom Ersteller; Zugriffsauflösung –
  eigene Beiträge frei, sonst Budgetprüfung/Berechtigung; Preisänderung nur für zukünftige Zugriffe.
- **`Adventures\TokenSchema` + `TokenLedger`**: append-only **Hash-Ketten-Ledger** (`liw_adv_ledger`),
  revisionssicher – dokumentiert Registrierung, Validierung, Veröffentlichung, Preisänderung und jeden
  Tokenzugriff (Nutzer, Beitrag+Version, Zeitpunkt, akzeptierter Tokenwert, Umfang, Org-Einheit, Transaktions-ID).
- **`Adventures\RegistrationService`**: erzwingt den Ablauf serverseitig – `register()` (Rechte-Zusicherung
  Pflicht) → Artikelbook-Registrierung + Ledger; `request_validation()`, `set_validation_result()`,
  `publish_world()`, `block()`/`archive()`, `set_token_value()` (Versionierung), `access_preview()`, `record_access()`.
- **`CoreBridge\ArticlebookBridge`**: Naht zum Artikelbook – Filter `liw_articlebook_register` (+ `liw_articlebook_url`)
  mit lokalem Fallback-Ref; die echte Core-Anbindung bleibt einklinkbar (keine harte Kopplung).
- **REST `liw-adv/v1`**: `create` (registriert direkt bei Tokenwert + Rechte-Zusicherung), `register`,
  `request-validation`, `access` (Vorschau), `accept` (Tokenzugriff), `moderate` (validieren/freigeben/sperren).
- CPT-Meta ergänzt (Tokenwert, Status, Reg-ID, Version, Artikelbook-Ref/URL, Rechte, Nutzungsumfang);
  Ledger-Tabelle in `create_tables()`.

### Hintergrund (Architektur)
- Das Core-Workboard (`admin.php?page=araliya-workboard`) ist **nicht** cross-plugin wiederverwendbar (keine
  Hooks/REST/Shortcodes, feste Rollen-/Tabellenbindung, feste Preis-Pauschale). Daher – wie „Workboard-Optik"
  an anderen Stellen der Plattform – im Satelliten **nachgebaut**, mit **frei wählbarem** Tokenwert statt fester
  Pauschale. Die Workboard-Optik-Eingabemaske (Frontend + Backend) folgt in alpha.60.

### Verifikation
- `tests/run-tests.php` **368/368** (Status-Automat, Tokenregeln, Ledger-Hashkette),
  `scripts/liw-selftest.php` **311/311** (Registrierung → Artikelbook-Ref, fremder/eigener Zugriff,
  Validierung → Freigabe, Ledger-Kette unverändert, alle Routen registriert).

### Hinweis
- Prototyp (§21): kein echtes Tokenbudget-Konto (Filter `liw_adv_token_budget`); Artikelbook-Verknüpfung als Naht.

## [0.1.0-alpha.58] – 2026-09-19 – Intelligence World: Nutzungs-/Kostenprotokoll (JSON + Druck/PDF)

### Hinzugefügt
- **Protokoll-Builder** `IntelligenceWorld\ProtocolBuilder` (rein, testbar): baut aus Sitzung + Ereignis-
  Ledger + Abrechnungsstatus ein strukturiertes Nutzungs-/Kostenprotokoll (§5.5/§8) – Sitzungskopf, aktive
  Zeit, Basiskosten/Budget, Ereignisliste mit lesbaren Bezeichnungen, Integritätsflag (Hash-Kette),
  Erstellungszeit (UTC). Kostenrechnung nicht dupliziert (Single Source `Rest::billing_status`).
- **Lesbare Ereignis-Namen** `EventTypes::label()` für alle 23 Ereignistypen.
- **REST** `GET liw-iw/v1/session/protocol` (öffentlich, cache-sicher wie die übrigen Prototyp-Endpunkte) +
  `Rest::build_protocol()` (Sitzung + `EventLog::chain_for` + `verify_chain` + Abrechnung).
- **Frontend:** Nach „Sitzung beenden" erscheint das Protokoll im (bisher ungenutzten) Bereich
  `data-liw-iw-protocol` – Kopf/Abrechnung/Ereignistabelle plus **„Als JSON herunterladen"** (Blob-Download)
  und **„Drucken / als PDF speichern"** (Druckansicht via `@media print`, nur das Protokoll).

### Verifikation
- `tests/run-tests.php` **348/348** (ProtocolBuilder-Struktur/Kosten/Labels, `EventTypes::label`),
  `scripts/liw-selftest.php` **304/304** (Route registriert, `build_protocol` Ende-zu-Ende inkl. Integrität).
  Browser end-to-end: Eintritt → Sitzung beenden → Protokoll mit 7 Ereignissen, 0,36 EUR bei 4 s, JSON/Druck.

### Hinweis
- Prototyp (§21): Beispieldaten, keine echte Abrechnung. „PDF" = Browser-Druck der Protokollansicht
  (keine serverseitige PDF-Bibliothek im Prototyp).

## [0.1.0-alpha.57] – 2026-09-19 – Simulation World: eigene Startseite + Cockpit-/World-Connections-Bilder

### Hinzugefügt
- **Startseite `/liebherr-simulator/`** (Seeder `scripts/liw-seed-simulator.php`, idempotent): Seite mit
  `[liw_simulator]`, Vollbild-Vorlage; Seiten-ID in Option `liw_simulator_page_id`. Der „Go / Start your
  journey"-CTA führt in die Intelligence World. Damit ist das Cockpit-Startbild tatsächlich sichtbar.

### Geändert (Konfiguration/Assets – im Dev-System gesetzt, nicht im Repo)
- **World-Connections-Bild** auf das freigegebene Mediathek-Bild `Liebherr_Solutions_ON-BOARD-VIEW_001`
  gesetzt (Option `liw_world_connections_image_id`) → ersetzt die abstrakte SVG-Karte durch die reale
  Globus-Grafik mit Standort-Flaggen.
- **Simulator-Startbild** auf das freigegebene Mediathek-Bild `Liebherr_Cockpit_001` gesetzt
  (Option `liw_simulator_image_id`). Beide Bilder mussten in der Mediathek freigegeben werden
  (`_liw_media_approved`, MediaBridge CI-005).

### Verifikation
- `tests/run-tests.php` **342/342**, `scripts/liw-selftest.php` **302/302** (Startseite bei gesetzter
  `liw_simulator_page_id` veröffentlicht + Vollbild-Vorlage; Bild-/SVG-Zweige weiterhin zustandsunabhängig).
  Browser: `/liebherr-simulator/` zeigt das Cockpit-Bild + „Start your journey" → „Go" verlinkt die
  Intelligence World; World-Connections-Karte zeigt das reale Bild.

### Hinweis (Deployment)
- Die Bild-Zuordnungen sind DB-Optionen und wandern **nicht** über Git; auf Staging/Live müssen die Bilder
  erneut hochgeladen, freigegeben und die Optionen gesetzt werden (bzw. über den Deployment-Manager-Medienimport).

## [0.1.0-alpha.56] – 2026-09-19 – Intelligence World: Simulation Builder (geführte Szenarien/Forecasts)

### Hinzugefügt
- **Reine Forecast-Engine** `IntelligenceWorld\SimulationModel`: deterministische, ganzzahlige Beispiel-
  Prognose aus Basiswert, Wachstum (‰/Periode), Periodenzahl und Szenario (Konservativ A / Basis B /
  Ambitioniert C, skaliert das Wachstum ×0,7/1,0/1,3). `sample_baseline()` leitet je Produktsegment eine
  reproduzierbare Ausgangslage ab. Vollständig ohne WordPress unit-testbar.
- **Simulation Builder** `IntelligenceWorld\SimulationView` (Shortcode `[liw_iw_simulation]` +
  `render_section()`): geführtes Formular (Segment · Szenario · Zeithorizont 6/12/24) mit Auswertungszeile,
  Inline-SVG-Balkendiagramm und Wertetabelle. **Default-Prognose serverseitig gerendert** (funktioniert ohne
  JavaScript); mit Skript rechnet `assets/js/liw-iw-simulation.js` bei jeder Eingabe live nach – dieselbe
  Formel wie die PHP-Engine (kein Server-Roundtrip).
- **Funktions-Hub vollständig live:** Die letzte Platzhalter-Kachel „Simulation Builder" verlinkt jetzt (Anker
  `#liw-iw-simulation`); die Sektion ist inline im Weltraum eingebettet.

### Verifikation
- `tests/run-tests.php` **342/342** (Forecast-Reihe/Delta, Szenario-Skalierung, Klemmung, deterministische
  Baseline), `scripts/liw-selftest.php` **299/299** (Formular + Default-Forecast im Weltraum, Hub-Anker,
  eigenständiger Shortcode). Browser end-to-end: Live-Neuberechnung bei Segment-/Szenario-/Horizont-Wechsel.

### Hinweis
- Prototyp (§21): Beispieldaten/-modell, keine echte Prognose.

## [0.1.0-alpha.55] – 2026-09-19 – Intelligence World: Preismodell (Sekundentakt, Monatsbudget, 1 TB)

### Geändert
- **Preise/Vorstellungen der Eröffnungsseite** (auf Wunsch des Auftraggebers) umgestellt – Anzeige **und**
  Abrechnungslogik konsistent:
  - **Preis: 0,09 EUR / Sek.** (Sekundentakt statt bisher 2,50 EUR/min).
  - **Sitzungsbudget: 5.000,00 EUR / Monat** (Monatsbezug statt Sitzungsbezug; für die Warnschwellen 50/80/100 %).
  - **Lokaler Speicher: 1 TB (min.)** (Mindestwert; MB→GB→TB-Formatierung in 1024er-Schritten).
- `WorldContent` (Option `liw_iw_world`): neue, administrierbare Felder `price_unit` (second/minute/hour),
  `base_price_second_minor`, `budget_period` (session/day/month/year), `storage_is_minimum`; Speicherbudget
  jetzt in MB mit lesbarer Einheit. Neue reine Helfer `unit_label()`, `period_label()`, `storage_label()`.
- `Rest::billing_status()` rechnet nun im **Sekundentakt** (`base = aktive Sekunden × Preis/Sek.`, Integer-
  Minor-Units); `status`/`config` liefern periode-/einheiten-/speicherbewusste Anzeige-Strings.
- Frontend-Ticker (`assets/js/liw-intelligence-world.js`) rechnet pro Sekunde (Live-Check: 5 s → 0,45 EUR).

### Verifikation
- `tests/run-tests.php` **331/331** (Sekundentakt-Abrechnung + Warnstufen, Preis-/Budget-/Speicher-Defaults,
  `storage_label` MB/GB/TB, `unit_label`/`period_label`), `scripts/liw-selftest.php` **294/294**
  (Eröffnungsseite zeigt „0,09 EUR / Sek.", „5.000,00 EUR / Monat", „1 TB (min.)"). Browser end-to-end.

### Hinweis
- Weiterhin **Prototyp** (§21): Beispieltarife, keine echte Abrechnung. Werte sind über die Option
  administrierbar; ein Backoffice-Formular dafür folgt mit dem Pflegemodul.

## [0.1.0-alpha.54] – 2026-09-19 – Intelligence World: Navigation & Hotels (13 Segmente + Lösungswelt + 6 Hotels)

### Hinzugefügt
- **Katalog „Navigation & Hotels"** (`IntelligenceWorld\CatalogContent`, Option `liw_iw_catalog`):
  die **13 Liebherr-Produktsegmente**, die übergreifende **Lösungswelt** und **sechs Hotel-Knoten** als
  administrierbare Daten (nichts fest im Frontend codiert). Jeder Knoten trägt einen **Drei-Wörter-Ort**
  (englisch, `word.word.word`; `sanitize_three_words()` normalisiert/verwirft Ungültiges). `defaults()/get()/
  save()/sanitize()` rein und unit-testbar.
- **Begehbare Navigation** (`IntelligenceWorld\NavigationView`): Produktsegmente-Abschnitt
  (`#liw-iw-segments`) + Hotelwelt-Abschnitt (`#liw-iw-hotels`) mit je einer Knotenkarte, die per
  `<details>` ihre Kurzbeschreibung und den Drei-Wörter-Ort öffnet – **barrierefrei, tastaturbedienbar,
  ohne JavaScript** (funktioniert auch bei aktivem Seiten-Cache). Zwei Nutzungswege: inline im Funktions-Hub
  der Intelligence World **und** als eigenständiger Shortcode `[liw_iw_navigation]`.
- **Funktions-Hub live geschaltet:** Die bisherigen Platzhalter-Kacheln „Produktsegmente & Lösungswelt"
  und „Hotelwelt" verlinken jetzt (als Anker) auf die neuen begehbaren Abschnitte. Nur der Simulation
  Builder bleibt „in Vorbereitung".

### Verifikation
- `tests/run-tests.php` **324/324** (13 Segmente, 6 Hotels, Lösungswelt, Drei-Wörter-Normalisierung,
  sanitize([])==defaults, Knoten ohne Namen verworfen), `scripts/liw-selftest.php` **291/291**
  (20 begehbare Knoten im Weltraum, Lösungswelt + `///`-Ort, Hub-Anker, eigenständiger Shortcode).
  Browser end-to-end verifiziert (Eintritt → Segmente/Hotels → `<details>`-Aufklappen mit Ort).

### Freigabe/Manuell (Auftraggeber)
- Segment-/Hoteltexte und Drei-Wörter-Orte sind **Beispieldaten** und vor Produktivbetrieb redaktionell
  zu kuratieren (zwei Hotel-Knoten sind bewusst Platzhalter). Ein Backoffice-Pflegemodul folgt in einer
  weiteren Etappe.

## [0.1.0-alpha.53] – 2026-09-19 – Simulation-World-Startbildschirm + what3words-Ortsdienst

### Hinzugefügt
- **Startbildschirm** `[liw_simulator]` (`Frontend\SimulatorView`): vollflächiges Cockpit-Startbild mit
  „Start your journey"-CTA (Ziel konfigurierbar, Standard Intelligence World). Startbild bevorzugt aus
  freigegebenem Media-Board-Bild (`liw_simulator_image_id`) oder Datei `assets/img/liw-simulator-start.*`
  (Filter `liw_simulator_image`); ohne Bild dunkler Platzhalter mit Admin-Hinweis.
- **what3words als echter Ortsdienst** (`Adventures\Location\What3WordsProvider`) hinter dem vorhandenen
  Provider-Vertrag: Koordinaten ↔ drei **englische** Wörter, `language=en` fix (keine Umschaltung).
  API-Key **nicht im Repo** (Konstante `LIW_W3W_API_KEY` in wp-config oder Option `liw_w3w_api_key`, Filter
  `liw_w3w_api_key`). Ist ein Key gesetzt, nutzt `LocationService` automatisch what3words; sonst weiter den
  Mock. Bei Netzwerk-/API-Fehler robuster Fallback auf den Mock (Erfassung blockiert nie). Die bereits
  eingeführte Drei-Wörter-Logik bleibt unverändert bestehen.

### Verifikation
- `tests/run-tests.php` 311/311, `scripts/liw-selftest.php` **285/285** (Startbildschirm + CTA; w3w ohne Key →
  Mock; encode liefert drei Wörter). Startbild und produktiver what3words-Betrieb: Datei/Key durch Auftraggeber.

### Freigabe/Manuell (Auftraggeber)
- Startbild-Datei ablegen (`assets/img/liw-simulator-start.jpg`) oder im Media Board setzen.
- what3words-Lizenz + API-Key (§4.3): Key in `wp-config.php` als `define('LIW_W3W_API_KEY','…')` oder Option.

## [0.1.0-alpha.52] – 2026-09-19 – Cross-Navigation der vier Plattformen (World-Switcher)

### Hinzugefügt
- **Plattform-Umschalter** `Frontend\WorldSwitcher` (Shortcode `[liw_world_switcher]` + automatische Leiste
  oben auf allen vier Insel-Seiten): verlinkt **Intelligence World · Local Intelligence · Interface Solutions ·
  Adventures** untereinander; aktuelle Insel hervorgehoben (`aria-current`). Ziele über Seiten-Registry/Optionen
  aufgelöst (nicht hart codiert); nur vorhandene, veröffentlichte Seiten werden verlinkt.
- **Intelligence-World-Hub:** Adventures ist nun eine Live-Kachel (statt „in Vorbereitung").
- **Backoffice-Menü:** zusätzlicher Frontpage-Direktlink „📸 Adventures"; Reihenfolge oben:
  Intelligence World · Local Intelligence · Interface Solutions · Adventures.

### Verifikation
- `tests/run-tests.php` 307/307, `scripts/liw-selftest.php` **279/279** (Switcher verlinkt alle vier, aktuelle
  hervorgehoben). Browser: Leiste erscheint oben auf den Inseln, korrekte Markierung.

## [0.1.0-alpha.51] – 2026-09-19 – Liebherr Adventures (vierte Insel): Visible-Adventures-MVP

Neues Modul `src/Adventures/` (Grundlagenkonzept „Liebherr Adventures"), im bestehenden Plugin. Prototyp
(§23.3): keine echten Diagnosen/Freigaben/Bestellungen; Demo-Daten gekennzeichnet.

### Hinzugefügt
- **Klassifikation** (13 Inhaltstypen + 4 Dringlichkeitsstufen als getrennte Achsen, §3).
- **Drei-Wörter-Ort** über austauschbare Provider-Abstraktion + invertierbaren **Mock** (kein hart
  verdrahteter Anbieter, §4.1) + Partner-Attribution „Location powered by …".
- **Datenmodell** CPT `liw_adventure` (+ Meta: Typ, Dringlichkeit, Sichtbarkeit, Drei-Wörter-Ort, UUID,
  Schutzstufe, Lösungsstatus, Medium).
- **Policy** serverseitig: Intelligence-Zugang zum Erstellen, **Critical nie ungeprüft öffentlich** (§22.5),
  7 Sichtbarkeitsstufen erzwungen (§9), Ortspräzision je Schutzstufe (§4.4).
- **REST** `liw-adv/v1` (stream/locate/create) + **Insel-Frontend** `[liw_adventures]`: Hero
  („One place. Three words. One shared experience."), Filter (Typ/Dringlichkeit), Create-Panel (Geolocation
  → Drei-Wörter-Ort), Stream mit Karten. Seite `/liebherr-adventures/` + Demo-Seeder.

### Verifikation
- `tests/run-tests.php` **305/305** (Taxonomie, Mock-Provider Round-Trip, Policy), `scripts/liw-selftest.php`
  **277/277** (CPT, create/Critical/Sichtbarkeit, Render, REST). Browser: Insel mit 6 Demo-Adventures, Drei-
  Wörter-Orte, kritischer Beitrag korrekt ausgeblendet.

### Nebenfix
- `[liw_world_map]` (World Connections) zeigt nun ein hinterlegtes Foto-Visual, falls vorhanden
  (`assets/img/liw-world-connections.*` ODER freigegebenes Media-Board-Bild, Filter `liw_world_connections_image`);
  sonst unverändert die abstrakte SVG-Karte.

## [0.1.0-alpha.50] – 2026-09-19 – Intelligence World: Funktions-Hub nach dem Eintritt

### Hinzugefügt
- **Funktions-Hub nach dem Eintreten:** Nach dem Access Gate erscheint in der Welt ein Kachel-Hub, der auf
  die bereits gebauten Bereiche **umschaltet** – **Local Intelligence** und **Interface Solutions** als
  Live-Kacheln (URLs über die Seiten-Registry `SitePages`, nicht hart codiert). **Produktsegmente & Lösungswelt**,
  **Hotelwelt** und **Simulation Builder** werden als „in Vorbereitung" gezeigt (nächste Etappen). Erweiterbar
  über Filter `liw_iw_hub_tiles`. Die Sitzungs-/Kostenleiste läuft dabei weiter.

### Behoben
- **IW-CSS/JS wurde ohne Version ausgeliefert** (Umgebung strippt `?ver`) → geänderte Styles kamen im Browser
  nicht an (Hub-Kacheln erschienen als Aufzählung). filemtime-Cache-Buster (`?v=`) für `liw-intelligence-world.css/js`
  ergänzt (Muster wie beim Haupt-Stylesheet).

### Verifikation
- `tests/run-tests.php` **276/276**, `scripts/liw-selftest.php` **269/269** (Hub verlinkt Local Intelligence +
  Interface Solutions). Browser: nach Eintritt Hub-Kacheln korrekt gestylt, Live-Links auf die gebauten Seiten.

## [0.1.0-alpha.49] – 2026-09-19 – IW-Fixes: Code-Prüfung, Pflichtfeld-Sternchen, Favicon „goldener Planet"

### Behoben
- **Eintritts-Code wurde fälschlich abgewiesen:** Die REST-Endpunkte verlangten ein WP-Nonce, das der
  Full-Page-Cache (WP Rocket) in die gecachte Seite einbackt → nach Ablauf schlug die Prüfung fehl und der
  korrekte Code („LIEBHERR-DEMO") galt als ungültig. Für die Prototyp-Landing sind die Endpunkte jetzt
  **öffentlich ohne Nonce-Zwang** (Access-Code ist das Tor, keine echten Kosten/Daten, §21). Serverseitig
  verifiziert (korrekt → Sitzung; falsch → Ablehnung).
- **Sternchen ohne Erklärung:** In der Eintrittsschleuse fehlte die Legende zum `*`. Ergänzt:
  „Mit * markierte Felder sind Pflichtfelder."; Code **und** beide Einwilligungen konsistent als Pflicht markiert.

### Hinzugefügt
- **Website-Icon „goldener Planet"** statt WordPress-Standard im Browser-Tab: `Frontend\FaviconService`
  gibt site-weit (Frontend/Admin/Login) ein kontrastreiches Gold-Planet-SVG (`assets/img/liw-planet-icon.svg`)
  im `<head>` aus; optionale offizielle PNGs (`assets/img/goheal-gold-planet-32|192|180.png`) werden bevorzugt,
  sobald hinterlegt. Filter `liw_favicon_enabled`.

### Verifikation
- `tests/run-tests.php` **276/276**, `scripts/liw-selftest.php` **268/268** (Access-Gate akzeptiert/weist ab,
  Favicon-Link im `<head>`, Pflichtfeld-Legende). Browser: Eintritt mit „LIEBHERR-DEMO" + beiden Zustimmungen ok.

### Manuell (Auftraggeber)
- Für pixelgenaue Home-Screen-Icons und WP-weite Pflege: offizielle quadratische PNG (idealerweise 512×512)
  unter Design → Customizer → Website-Icon hochladen; danach Browser-/WP-Cache leeren.

## [0.1.0-alpha.48] – 2026-09-19 – Intelligence World: Eintritt & Welt (Blue Planet, Access Gate, Sitzungsleiste)

Phase 2 des Pflichtenhefts-2 (Prototyp §21, kein echtes Payment). Baut auf dem Fundament (alpha.47) auf.

### Hinzugefügt (`src/IntelligenceWorld/`)
- **WorldContent** – administrierbare Texte/Prototyp-Tarife (Option `liw_iw_world`): Landing, Eintrittsschleuse,
  Demo-Preis (2,50 €/min), Sitzungsbudget (50 €), Speicher-Grundbudget (5 MB), Demo-Code `LIEBHERR-DEMO`.
- **Rest** – REST `liw-iw/v1` (session/start · heartbeat · end · status), Nonce-geschützt; serverseitige
  Abrechnungswahrheit; reine `billing_status()`/`format_duration()` (§7/§8).
- **WorldView** – Shortcode `[liw_intelligence_world]`: Blue-Planet-Hero (dekoratives Planet-SVG mit Orbits/
  Knoten, reduced-motion-fest), Eintrittsschleuse (Code + Prototyp-/Preishinweis + Nutzungsbedingungen 5.1–5.8
  wiederverwendet + zwei Pflicht-Einwilligungen + Bestätigung erst bei Code & beiden Zustimmungen), sticky
  Sitzungs-/Kostenleiste (Zeit, Basiskosten, Budget-Balken mit Warnstufen 50/80/100 %, „Sitzung beenden").
- Eigene Assets `assets/css|js/liw-intelligence-world.*`; Seeder `scripts/liw-seed-intelligence-world.php`
  (Seite `/liebherr-intelligence-world/`, Vollbild-Vorlage, eigener Menülink „🪐 Intelligence World").

### Verifikation
- `tests/run-tests.php` **273/273** (WorldContent, billing_status, format_duration §8-Beispiel),
  `scripts/liw-selftest.php` **264/264** (Shortcode-Render, Schleuse, REST-Route, Demo-Code).
- Real end-to-end im Browser: Code + Einwilligungen → Sitzung startet (REST), Ticker läuft, Basiskosten
  korrekt (z. B. 25 s = 1,04 €), „Sitzung beenden" erzeugt Abschlussprotokoll; keine JS-Fehler.

### Prototyp-Hinweis (§21)
- Demo-Code (kein echtes Login), Beispieltarife, keine echte Abrechnung/kein Payment. Serverseitiges
  Ereignis-Ledger + Hash-Kette aus alpha.47 protokolliert Eintritt/Sitzung revisionsfähig.

## [0.1.0-alpha.47] – 2026-09-19 – Intelligence World: Fundament (Datenmodell, Ereignis-Ledger, Session-Meter)

Start des zweiten Pflichtenhefts „Liebherr Intelligence World" (globale Simulations-/Nutzungs-/
Abrechnungsplattform). Gebaut wird im selben Plugin als eigenständige Ebene neben Local Intelligence.
Diese Etappe legt das **serverseitige Fundament** (ohne UI, ohne echtes Payment – Prototyp §21).

### Hinzugefügt (`src/IntelligenceWorld/`)
- **Schema** `liw_iw_session` (Sitzungen) + `liw_iw_event` (append-only Ereignis-Ledger mit Hash-Kette),
  registriert in der Plugin-Aktivierung/Upgrade (§12).
- **EventTypes** – die 23 Ereignistypen aus §11.
- **Money** – Geld ausschließlich als Integer-Minor-Units (§9), niemals Float; Anzeigeformat de/en.
- **PriceRule** – 10 Tarifarten (§13.2), Kostenrechnung, Gültigkeitsfenster + Auswahl der zum Zeitpunkt
  gültigen Version (keine rückwirkende Preisänderung, §13.3).
- **SessionMeter** – reine, konservative Berechnung abrechenbarer aktiver Zeit aus Heartbeats +
  Inaktivitäts-Timeout (§13.1; nicht nutzbare Zeit wird nicht berechnet).
- **EventLog** – manipulationsgeschütztes Anhängen (SHA-256-Hash-Kette) + Idempotenz via `dedupe_key`
  (Schutz gegen Doppelbuchung/Replay, §16); `verify_chain()` erkennt nachträgliche Änderungen.
- **SessionService** – serverseitiger Lebenszyklus Start/Heartbeat/Pause/Resume/Ende + Timeout-Kehrlauf,
  UTC-Zeit, administrierbarer Timeout (`liw_iw_timeout`).
- `docs/IMPLEMENTATION_NOTES.md` (§22.8): Entscheidungen, Prototyp-Grenzen, Etappenstatus, offene Punkte.

### Verifikation
- `tests/run-tests.php` **262/262** (Money/PriceRule/SessionMeter/EventLog-Hash/EventTypes),
  `scripts/liw-selftest.php` **257/257** (DB-Lebenszyklus, aktive-Sekunden = 100 im Testszenario,
  Hash-Kette gültig, Idempotenz, Manipulation erkannt, Testdaten entfernt).

### Prototyp-Hinweis (§21)
- Kein echtes Rechnungswesen/Payment, keine Produktivdaten. Beträge werden nur korrekt geführt/berechnet,
  nicht eingezogen. Produktivschaltung erst nach rechtlicher Freigabe (Release-Gate).

## [0.1.0-alpha.46] – 2026-09-19 – Erweiterte Nutzungsbedingungen (5.1–5.8) im Intro-Fenster

### Hinzugefügt/Geändert
- **Vollständige Nutzungsbedingungen** (Abschnitte 5.1–5.8: Prototypstatus/Vertraulichkeit, lokale
  Speicherung, kostenpflichtige Nutzungszeit, Modul-/Daten-/Rechengebühren, Nutzungs-/Kostenprotokoll,
  Verantwortlichkeit für Simulationen, Zugang/Sicherheit, rechtlicher Freigabevorbehalt) als Standardtext
  im Intro-Fenster hinter „Nutzungsbedingungen anzeigen" (`LocalIntelligenceContent::default_terms()`).
- **Strukturierte Darstellung:** Der Text wird als schlichtes Markup gepflegt (`## Abschnitt`, `- Punkt`,
  `1. Punkt`) und in `IntroOverlay::terms_html()` **escaped** zu HTML gerendert (h4/p/ul/ol/li) – kein roher
  HTML-Durchlass aus der Option, `sanitize_textarea_field`-sicher, im Board editierbar.
- Panel-Höhe auf 46vh erhöht (scrollbar); Styling für Abschnittsüberschriften/Listen im dunklen Panel.

### Hinweis (LI §9.2/§5.8)
- Der hinterlegte Text ist die verbindliche Umsetzungsvorgabe, jedoch noch **keine rechtsgeprüfte AGB-Fassung**;
  Produktivbetrieb erst nach der in 5.8 genannten Freigabe (passt zum bestehenden `liw_public_release`-noindex-Gate).

### Verifikation
- `tests/run-tests.php` 230/230, `scripts/liw-selftest.php` **250/250** (u. a.: 5.1–5.8 als HTML gerendert,
  Renderer escaped `<script>`). Real geprüft: Nutzungsbedingungen erscheinen formatiert und scrollbar im Fenster.

## [0.1.0-alpha.45] – 2026-09-18 – Intro-Overlay „Sternenregen" + Eintritts-Fenster (Rechen-Gate)

### Hinzugefügt
- **Intro-Overlay** (`[liw_intro]`, `Frontend\IntroOverlay`) für die Local-Intelligence-Seite: cineastischer
  Einstieg aus dem Dunkel – Titel „Liebherr Local Intelligence"; kleine (freigegebene) Liebherr-Logos
  fliegen **kreuz und quer**, **wachsen und schrumpfen**, verschwinden aus dem Bild oder werden sternenklein;
  dazu **funkelnde Sterne**, die immer wieder aufblitzen. Nach dem Eintreten bleiben Sterne und Logos noch
  einige Sekunden sichtbar, während die Plattform **sehr weich** durchscheint (mehrphasiger Soft-In:
  Dunkel blendet aus → Sterne klingen aus → Overlay entfernt).
- **Wegklickbares Eintritts-Fenster** mit aufklappbaren **Nutzungsbedingungen** (Button) und – statt eines
  CAPTCHA-Codes – einer **Rechenaufgabe aus zwei zweistelligen Zahlen** als Bestätigung/„Kontrollkästchen".
  Erwartungswert serverseitig erzeugt (`wp_rand`), clientseitig geprüft; „Eintreten" erst bei korrekter Summe.
- Inhalte administrierbar über `Settings\LocalIntelligenceContent['intro']` + neue Board-Gruppe
  „Intro-Overlay" im Reiter „🧠 Local Intelligence".

### Barrierefreiheit / Robustheit
- Ohne JavaScript bleibt das Overlay `hidden` (kein Trap – Seite voll nutzbar). `prefers-reduced-motion`
  schaltet den Sternenregen ab (nur ruhiges Ein-/Ausblenden). `role="dialog"`/`aria-modal`, Fokus ins
  Fenster, Geschwister werden für Screenreader ausgeblendet; einmal pro Sitzung (sessionStorage).

### Verifikation
- `tests/run-tests.php` 229/229, `scripts/liw-selftest.php` **248/248** (u. a.: Summe passt zur angezeigten
  Gleichung, Overlay ohne JS hidden). Real geprüft: Intro erscheint, Rechen-Gate akzeptiert die korrekte
  Summe, Seite blendet danach auf; Logo-Sterne sichtbar.

## [0.1.0-alpha.44] – 2026-09-18 – Obere Menüleiste der LI-Seite repariert + Logo-Fix

### Behoben
- **Tote obere Menüleiste auf `/liebherr-local-intelligence/`:** Der geteilte Header (`[liw_header]`) nutzte
  ausschließlich den globalen Menüsatz mit den Ankern `#lp-02…#lp-13`, die nur auf der Interface-Solutions-
  Unterseite existieren – auf der Hauptseite liefen alle Links ins Leere. `[liw_header]` ist jetzt
  **kontextfähig**: `nav="li"` verwendet den Local-Intelligence-Menüsatz (Vision `#li-vision`,
  Simulation World `#li-simulation`, Einsatzfelder `#li-usecases`, Interface Solutions → Unterseiten-URL,
  Kontakt `#li-contact`) samt passender CTAs. Die Hauptseite (Seeder) verwendet `[liw_header nav="li"]`;
  die Interface-Solutions-Seite behält den Standard-Menüsatz. Beide Menüs (obere Leiste + Sprungleiste) funktionieren.
- **Logo als 1×1 px:** `wp_get_attachment_image()` gab für das SVG-Logo `width="1" height="1"` aus
  (SVG ohne intrinsische Maße). Ausgabe jetzt als direkte `<img src=…>`-URL; Größe rein über CSS
  (`.liw-header__logo { height: 32px; width: auto }`, Breite folgt dem viewBox-Verhältnis) → Logo sichtbar.

### Verifikation
- `tests/run-tests.php` 226/226, `scripts/liw-selftest.php` **241/241**; reale Seite: obere Menüleiste
  trifft alle LI-Anker (JS-geprüft), Logo 258×32 px, Standard-Header der Unterseite unverändert.

## [0.1.0-alpha.43] – 2026-09-18 – Hero-Visual (Modul 1: Knotennetz) + A11y-Struktur bestätigt

### Hinzugefügt
- **Hero-Visual (LI §8 Modul 1 / §9.3):** abstraktes, dekoratives Knotennetz als Hero-Ebene – zentrale
  freigegebene Wissensquelle (Hub), lokale Knoten und Datenraum-Ringe mit gestrichelten Verbindungen.
  Inline-SVG (`LocalIntelligenceView::hero_network_svg()`), `aria-hidden`, Farben aus `--brand-*`, sanfter
  Puls nur bei `prefers-reduced-motion: no-preference`. Keine Roboter/Gehirn/KI-Chip-Klischees, kein externes Asset.

### Bestätigt (Barrierefreiheit §12.6)
- Genau **eine H1** (Hero) auf der Seite; Landmarken vorhanden (`<main>` aus der Vollbild-Vorlage, `<header>`,
  `<footer>`, `<nav>` mit `aria-label`). Als automatisierte Prüfungen im Selbsttest verankert.

### Verifikation
- `tests/run-tests.php` 226/226, `scripts/liw-selftest.php` **238/238**; Hero-Visual real geprüft (subtil,
  Text bleibt lesbar).

## [0.1.0-alpha.42] – 2026-09-18 – Prototyp-SEO-Konformität (noindex bis Freigabe) + Abnahme-Lieferliste

### Hinzugefügt/Geändert
- **SeoBridge erkennt die neue LI-Hauptseite** (`[liw_local_intelligence]` als Träger-Shortcode neben
  `[liw_landingpage]`): Open-Graph-, hreflang- und Canonical-Ausgabe greifen nun auch auf `/liebherr-local-intelligence/`.
- **Prototyp-Konformität (LI §9.2/§12.7):** Solange keine offizielle Liebherr-Freigabe vorliegt, liefern
  Haupt- und Interface-Seite `<meta name="robots" content="noindex,follow">` und werden aus der XML-Sitemap
  ausgeschlossen. Steuerung über Option `liw_public_release` (Standard: gesperrt) bzw. Filter `liw_allow_indexing`.
- **Abnahmebericht** `docs/LIW_ABNAHME.md` §8: Vorher-Nachher-Liste (Routen/Dateien/Komponenten, §16 AK15/§17),
  LI-Abnahmekriterien-Abgleich und Freischalt-Anleitung.

### Verifikation
- `tests/run-tests.php` **226/226**, `scripts/liw-selftest.php` **236/236**; reale Seiten liefern `noindex`,
  OG-Titel erscheint auf der Hauptseite, Sitemap `wp-sitemap-posts-page-1.xml` enthält die Prototyp-Seiten nicht.

## [0.1.0-alpha.41] – 2026-09-18 – Neue Hauptseite „Liebherr Local Intelligence" (11 Module) + Interface Solutions als Unterseite

Umsetzung des Pflichtenhefts „Liebherr Local Intelligence Landingpage": die bisherige Interface-World-Seite
bleibt vollständig erhalten und wird zur technischen Unterseite unter der neuen übergeordneten Hauptseite.

### Hinzugefügt
- **Hauptseite „Liebherr Local Intelligence"** mit allen elf Modulen (LI-Pflichtenheft §8):
  Hero · Vision-Dreiklang · Datenbewegung · **Simulation World (interaktiver A/B/C-Szenario-Schalter)** ·
  Wissensassistenz (Frage→Antwort→Quelle/Status→nächste Aktion) · Datenqualität (4 Prinzipien) ·
  weltweite Nutzung · **Einsatzfelder (filterbar)** · Interface-Solutions-Brücke · Rollout · Kontakt.
  Ein Modul = ein Shortcode (`[liw_li_hero]`, `[liw_li_vision]`, `[liw_li_flow]`, `[liw_simulation_world]`,
  `[liw_li_knowledge]`, `[liw_li_trust]`, `[liw_li_global]`, `[liw_li_usecases]`, `[liw_interface_bridge]`,
  `[liw_li_rollout]`, `[liw_li_contact]`) plus Composite `[liw_local_intelligence]` mit Sprungleiste.
  Kontaktformular (Modul 11) verwendet das bestehende `[liw_contact_form]` wieder.
- **Administrierbares Content-Modell** `Settings\LocalIntelligenceContent` (Option `liw_local_intelligence`,
  Standardtexte via `__()`) + Pflege-Board „🧠 Local Intelligence" (`Admin\Pages\LocalIntelligenceBoardPage`).
- **Verschachtelung/Routing:** Interface-World-Seite wird Kind der Hauptseite, Slug → `interface-solutions`
  (URL `/liebherr-local-intelligence/interface-solutions/`); **301-Redirect** der Altroute `/interface-world/`
  (`Frontend\LegacyRedirect`, §3.2 – keine toten Links). Kontextnavigation/Breadcrumb + Rücklink auf der
  Unterseite (`[liw_context_nav]`, §3.3/§13). Zentrale Seiten-Registry `Content\SitePages` (IDs statt Slug-Pfad).
- **Menü:** zwei Frontpage-Direktlinks (🌍 Local Intelligence als erste Ansicht, 🌐 Interface Solutions).
- **Interaktion** (assets/js): Szenario-Schalter (Tabs A/B/C, tastaturbedienbar) + Einsatzfeld-Filter –
  beide fortschreitende Verbesserung; ohne JS bleiben alle Inhalte sichtbar (§10). `prefers-reduced-motion`
  respektiert. `.liw-li*`-CSS ausschließlich über `--brand-*`-Tokens, mobile-first.
- **Seeder** `scripts/liw-seed-local-intelligence.php` (idempotent, `--confirm`): Hauptseite anlegen,
  Interface-Seite verschachteln, Kontextnavigation einhängen, Rewrite-Regeln aktualisieren.

### Konformität (LI-Pflichtenheft §4/§7/§12.8)
- Nur Demonstrationsdaten, klar gekennzeichnet; keine echten Geschäftszahlen; keine unbelegten
  Leistungs-, Sicherheits- oder Echtzeitversprechen; Markenassets nur über freigegebene CI-Tokens.

### Verifikation
- `php -l` (alle Dateien); `tests/run-tests.php` **224/224**, `scripts/liw-selftest.php` **232/232**.
- Echte Seiten geprüft: `/liebherr-local-intelligence/` (HTTP 200, alle 11 Anker, Hero zuerst, beide CTAs
  sichtbar, Szenario-Schalter A/B/C funktioniert, Einsatzfeld-Filter), `/interface-world/` → **301** auf
  die Unterseite, Unterseite HTTP 200 mit Breadcrumb + Rücklink.
- Falle bestätigt: **WP Rocket** (Frontend-HTML-Cache) musste geleert werden, damit Reihenfolge/CI griffen.

## [0.1.0-alpha.40] – 2026-09-18 – Menü „Frontpage-Ansicht" + Landingpage-Layout (Gutter/CI)

### Hinzugefügt
- **Menü-Direktlink „🌐 Frontpage-Ansicht"** als erster Unterpunkt im Interface-World-Menü: öffnet die
  öffentliche Landingpage (dynamisch aufgelöste Trägerseiten-URL), damit man nicht manuell umschalten muss.
  `AdminMenu::front_url()` (Slug `interface-world` bzw. Seite mit `[liw_landingpage]`) + `move_first()`.

### Behoben/Geändert
- **Layout:** In der Vollbild-Vorlage klebten Abschnitts-Überschriften/Text am linken Rand. Die
  `[liw_landingpage]`-Abschnitte und die Sprungleiste haben nun einen zentrierten Content-Bereich
  (`--content-max`) mit seitlichem Gutter (4vw). Landingpage-Titel/Nav/Border an die Liebherr-CI-Tokens
  (`--font-heading`, `--brand-text`, `--brand-primary`, `--brand-border`) angeglichen.

### Verifikation
- `php -l`; `tests/run-tests.php` 203/203, `scripts/liw-selftest.php` 206/206; echte Seite
  `/interface-world/` bestätigt (Gutter korrekt, CI-konforme Überschriften).

## [0.1.0-alpha.39] – 2026-09-18 – Wortmarke „Liebherr Interface Solutions" + Vollbild-Seitenvorlage

### Hinzugefügt
- **Wortmarke als Token** `brand_text` (Brand Board): Text für Header-Fallback und Footer; leer =
  WP-Seitentitel. Über die CI-Anwendung auf „Liebherr Interface Solutions" gesetzt – Footer/Header
  zeigen damit die Marke statt des Dev-Seitentitels (kein Logo-Eingriff, CI-002).
- **Vollbild-Seitenvorlage** „Interface World – Vollbild" (`Frontend\PageTemplate` + `templates/full-width.php`):
  rendert nur den Seiteninhalt (unsere Shortcodes) + `wp_head`/`wp_footer`, **ohne Theme-Kopf/-Fuß** –
  behebt die doppelte Theme-Navigation/-Fußzeile auf der Trägerseite. Der Demo-Seeder weist sie der
  Seite `/interface-world/` automatisch zu.

### Verifikation
- Reale Seite `/interface-world/` per DOM-Prüfung: Header mit Liebherr-Logo, Footer-Wortmarke
  „Liebherr Interface Solutions" (schwarz), Legal-Nav + GoHeal-Hinweis, **kein Theme-Header/-Footer** mehr.
- `php -l`; `tests/run-tests.php` 203/203, `scripts/liw-selftest.php` 204/204.

## [0.1.0-alpha.38] – 2026-09-18 – Demo-Landingpage zusammengestellt + LP-14 Footer + Cache-Buster

### Hinzugefügt
- **LP-14 Footer** `[liw_footer]` (§8/§14): schwarze Meta-/Legal-Leiste im Liebherr-Stil (Vorbild
  liebherr.com-Startseite) – Wortmarke + rechtliche Navigation (Impressum, Datenschutzhinweis, Kontakt,
  Privacy Settings, Barrierefreiheitserklärung; über Filter `liw_footer_legal_links` anpassbar) + sehr
  kleine Kennzeichnung „Solution Provider: GoHeal" (CI-003) + Copyright.
- **Demo-Landingpage** `scripts/liw-seed-demo-landing.php` (idempotent): bestückt LP-06/08/11/12/13 mit
  den Frontend-Shortcodes, veröffentlicht sie, blendet noch nicht aufbereitete Abschnitte aus (Entwurf,
  reversibel) und legt die Trägerseite „Interface World" (`/interface-world/`) mit
  `[liw_header]` + `[liw_hero]` + `[liw_landingpage]` + `[liw_footer]` an.
- **Cache-Busting** der eigenen Frontend-Assets (`FrontendAssets::bust_src`, filemtime-`?v=`): behebt,
  dass geänderte CSS/JS im Browser hängen bleiben, wenn die Umgebung `?ver` von Assets entfernt.
- **WP-Rocket-Kompatibilität** `RocketCompat`: `.liw-`-Selektoren in die RUCSS-Safelist, damit die
  Inline-SVG-Weltkarte u. a. nicht als „unused CSS" gestrippt wird (bekannte Falle).

### Hinweise
- WP Rocket in dieser Umgebung: RUCSS/Minify/Async waren aus; die Weltkarte war ungestylt wegen
  browserseitig gecachter CSS ohne `?ver` → durch den filemtime-Cache-Buster gelöst.
- Der Footer-Wortlaut nutzt den WP-Seitentitel (Filter `liw_footer_legal_links` bzw. Seitentitel für
  „LIEBHERR"). Der separate Theme-Footer bleibt bestehen (Layout-Frage der Trägerseiten-Vorlage).
- Verifikation: `php -l`; `tests/run-tests.php` 200/200, `scripts/liw-selftest.php` 200/200; echte Seite
  `/interface-world/` geprüft (Hero, dunkle Weltkarte, Process, Roadmap, Onboarding, Kontakt, schwarzer Footer).

## [0.1.0-alpha.37] – 2026-09-18 – Optik: Liebherr-CI angewendet + „Connected World"-Weltkarte

> **Markenhinweis:** Liebherr-CI, -Logo, -Bilder und -Webfonts sind auf ausdrückliche Autorisierung
> (Joseph White) **vorläufig** angewendet; die endgültige, dokumentierte Liebherr-Freigabe bleibt
> Voraussetzung für den produktiven Launch. Rücknahme: Brand Board zurücksetzen + Media Board
> `approved=0` (`docs/LIW_ABNAHME.md` §5).

### Hinzugefügt
- **Interaktive Weltkarte** `[liw_world_map]` (LP-06, §8): eigene, abhängigkeitsfreie Inline-SVG
  („Connected World") mit zentraler Liebherr-Zentrale und Regionen-Knoten aus den freigegebenen
  Verbindungen; Knoten per Tastatur/Screenreader erreichbar, mit vollständiger Text-Alternative (§26).
  Hover/Fokus synchronisiert Knoten und Liste (`assets/js/liebherr-frontend.js`).
- **Webfont-Einbindung** `FontFaceService` (§11): `@font-face` für die freigegebenen Liebherr-Fonts
  (LiebherrHead/LiebherrText), sodass die Brand-Token-Schriften real rendern.
- **`--brand-on-primary`-Token** (BrandTokens + Brand Board): lesbarer Text auf der Primärfarbe
  (dunkel auf Liebherr-Gelb) – behebt den Kontrast bei hellen Primärfarben.
- CI-Anwendung `scripts/liw-apply-liebherr-ci.php` (idempotent, autorisiert): gibt die
  Media-Board-Kandidaten frei (`approved=1`), belegt die Brand-Tokens mit den echten Liebherr-Werten
  (Gelb #ffd000, Anthrazit #202326, Blau #2779c4, LiebherrHead/Text) und setzt Logo + Hero-Bild.

### Geändert
- `assets/css/liebherr-frontend.css`: Weltkarten- und Hero-Feinschliff, CTA-/Marker-Kontrast über
  `--brand-on-primary`. `src/Bootstrap.php`/`FrontendAssets.php`: WorldMapView + Webfonts registriert.

### Hinweise
- Die abstrahierte Weltkarte nutzt **keine** externe Kartenbibliothek und keine echten Koordinaten
  (kein Kategorie-A-Eingriff); eine echte geografische Karte bleibt optionaler Folgepunkt.
- Verifikation: `php -l`; `tests/run-tests.php` 196/196, `scripts/liw-selftest.php` 196/196; Optik in
  Desktop-Vorschau bestätigt (Logo, Hero, Weltkarte, gelbe CTAs mit dunklem Text).

## [0.1.0-alpha.36] – 2026-09-18 – Content-Board-Restpunkte: Sichtbarkeits-Zeitfenster (§19)

### Hinzugefügt
- **Sichtbarkeits-Zeitfenster je Abschnitt** (§19, über den nativen `future`-Status hinaus):
  optionale Felder „Sichtbar ab/bis" (Metabox `SectionScheduleMetabox` im `liw_section`-Editor),
  Eingabe in Website-Zeitzone, Speicherung als UTC. Abschnitte außerhalb ihres Fensters werden
  auf der Landingpage ausgeblendet (`LandingpageView::get_published_sections()` filtert zusätzlich
  zum Veröffentlichungsstatus). Content Board kennzeichnet gesetzte Fenster (🕒 im/außerhalb Fenster).
  - Neu: `Content\SectionSchedule` mit reiner, testbarer `is_within_window()` (leere/ungültige Grenzen
    = offen, damit ein Tippfehler nie unbeabsichtigt ausblendet).

### Abschluss
- Damit sind die Content-Board-Restpunkte §19 vollständig: Reihenfolge (alpha.35), Pflichtfeld-/
  Alt-Text-Prüfung + CTA-Picker (alpha.30), Sprachvorschau (alpha.35), Zeitfenster (alpha.36).
  Medien-Picker-Beschränkung bleibt bewusst zurückgestellt (WP-`ajax_query_attachments` unzuverlässig).
- Verifikation: `php -l`; `tests/run-tests.php` 185/185, `scripts/liw-selftest.php` 190/190 im
  Docker-Container (inkl. „Landingpage blendet abgelaufenen Abschnitt aus").

## [0.1.0-alpha.35] – 2026-09-18 – Content-Board-Restpunkte: Drag-&-Drop-Reihenfolge & Sprachvorschau (§19)

### Hinzugefügt
- **Drag-&-Drop-Reihenfolge** der Abschnitte im Content Board (§19): Zeilen per Ziehgriff sortieren,
  neue `menu_order` (10/20/…) wird sofort per AJAX gespeichert (`liw_reorder_sections`, Nonce +
  Capability `liw_manage_content`, auditiert). Testbare Kernlogik `ContentBoardPage::apply_order()`.
  JS `assets/js/liw-admin-content.js` (jQuery-UI-Sortable); ohne JS bleibt das „Reihenfolge"-Feld im
  Editor nutzbar (progressive Verbesserung).
- **Vorschau je Sprache** in der Abschnittsliste (§19): Vorschau-Links je aktiver Sprache (`?lang=xx`).

### Hinweise
- Zeitgesteuerte Veröffentlichung nutzt weiterhin den nativen WP-`future`-Status (Editor); ein
  eigenes Sichtbarkeits-Zeitfenster (valid_from/valid_until) bleibt bewusster Folgepunkt (Schema-/
  Frontend-Änderung, To-Dos). Geräte-Vorschau = responsive Ansicht im Browser.
- Verifikation: `php -l`; `tests/run-tests.php` 174/174, `scripts/liw-selftest.php` 185/185 (u. a.
  `apply_order()` mit echten Test-Abschnitten) im Docker-Container.

## [0.1.0-alpha.34] – 2026-09-18 – Etappe 8: Qualität & Abnahme (§25–35)

### Hinzugefügt
- **Demo-/Seed-Daten** (§33): `scripts/liw-seed-demo.php` (idempotent, `--confirm`) legt klar
  gekennzeichnete DEMO-Schnittstellen, -Verbindungen und eine -Simulationswelt mit Szenarien an –
  keine echten Geschäftsdaten (§4). Boards/Landingpage sind damit ohne Echtdaten vorführbar.
- **Abnahmebericht** `docs/LIW_ABNAHME.md` (§32/§35): AC-001…016 mit Status, Testergebnisse (§31),
  Liefergegenstände (§33), **Rollback-Verfahren**, §34-Launch-Blocker-Liste und bewusst
  zurückgestellte Folgepunkte.

### Qualität/Abnahme
- Unit (WP-frei) 174/174, Docker-Integration 180/180 grün. Zusammengesetzte Landingpage
  (Header + Hero + Komponenten + Map) in Desktop und Mobil im Browser visuell bestätigt.
- Manuelle A11y-Grundlagen erfüllt (Semantik, Fokus, Tastatur, `aria-current`,
  `prefers-reduced-motion`, Textalternativen); formale A11y-/Performance-Vollmessung auf Staging offen
  (AC-012/013). Produktion bleibt gesperrt bis ausdrückliche Freigabe (AC-016).

### Abschluss Stufe 1
- Release-Plan (`docs/LIW_RELEASEPLAN.md`) Etappen 1–8 (alpha.27–34) umgesetzt. Offene Punkte sind
  externe §34-Inputs und bewusste Kategorie-A-/YAGNI-Folgepunkte (siehe Abnahmebericht/To-Dos).

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
