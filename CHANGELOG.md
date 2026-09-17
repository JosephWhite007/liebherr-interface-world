# Liebherr Interface Solutions — Changelog

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
