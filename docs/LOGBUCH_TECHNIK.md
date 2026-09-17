# Logbuch für Techniker – Liebherr Interface Solutions

Eigenes Logbuch dieses Plugins, analog zur Konvention von `araliya-platform-core`
(`docs/LOGBUCH_TECHNIK.md`). Entscheidungen, die die **Ein-Plugin-Regel-Ausnahme** und die
**Variante-A-Integrationsentscheidung** selbst betreffen, stehen im Logbuch von
`araliya-platform-core` (Core-Governance). Hier stehen die Entscheidungen, die innerhalb dieses
Plugins gefallen sind.

## Teil II – Sitzungs-Logbuch (neueste zuerst)

### 2026-09-17 · Content Board für liw_section bewusst zurückgestellt (Planungsentscheidung)

**Kontext.** Nach Behebung der beiden Aktivierungs-/Capability-Bugs wurde die Frage gestellt,
ob für die 14 Landingpage-Abschnitte (CPT `liw_section`) ein provisorischer Menüpunkt
(`add_submenu_page` → natives `edit.php?post_type=liw_section`) ergänzt werden soll, um sie
schon vor dem eigentlichen Content Board redaktionell nutzbar zu machen.

**Entscheidung (Joseph White, 17.09.2026).** Keine provisorische Zwischenlösung. Der CPT
bleibt wie in CHANGELOG 0.1.0-alpha.1 dokumentiert ohne Menüanbindung (`show_in_menu =>
false`, kein Submenu-Eintrag), bis das vollwertige Content Board (Freigabeworkflow-UI,
Mehrsprachigkeits-Ansicht) als eigene Auslieferung kommt.

**Auswirkung.** Keine Code-Änderung. Bestätigt den bereits in CHANGELOG.md unter „Bekannte
Einschränkungen / offene Punkte" dokumentierten Stand.

### 2026-09-17 · Bugfix: Menü „Interface World" für Administrator-Account unsichtbar

**Kontext.** Nach erfolgreicher Aktivierung meldete Joseph (Screenshot des ARALIYA-Menübaums),
dass unter „ARALIYA System" kein Eintrag für Interface World zu sehen ist. Der eingeloggte
Account nutzt die native WordPress-Rolle `administrator`.

**Ursache.** `RoleBridge::grant_capabilities()` vergab die drei Liebherr-Capabilities
(`liw_manage_interfaces`, `liw_manage_content`, `liw_view_onboarding`) nur an die
Custom-Rollen `araliya_admin`/`araliya_marketing` – nicht an `administrator`. Das weicht vom
etablierten Core-Muster ab: `araliya-platform-core/src/Core/RoleManager.php` vergibt
konsequent jede Capability zusätzlich an `administrator` (Kommentar dort: "Extends the
built-in 'administrator' — we add ARALIYA capabilities"). Ohne die Capability blendet
WordPress `add_menu_page()`/`add_submenu_page()` beim Seitenaufbau automatisch komplett aus
– kein Zugriffsfehler, das Menü existiert für diesen Account schlicht nicht.

**Fix.** `administrator` in eine neue Konstante `RoleBridge::FULL_ACCESS_ROLES` (zusammen mit
`araliya_admin`) aufgenommen, damit beide Rollen bei `grant_capabilities()` gleich behandelt
werden – konsistent mit der Core-Konvention. `revoke_capabilities()` entfernt weiterhin
bewusst nichts von `administrator` (ebenfalls Core-Konvention:
`RoleManager::deactivate()`-Kommentar "Does NOT remove 'administrator'").

**Wichtig.** Die Capability-Vergabe läuft nur im Aktivierungshook (`register_activation_hook`).
Der Fix wirkt daher erst nach erneutem Deaktivieren + Aktivieren des Plugins – ein einfaches
Datei-Update reicht nicht, da WordPress `add_cap()` nicht automatisch erneut aufruft.

**Auswirkung.** Kategorie B (Bugfix am neuen Plugin, keine Core-Änderung, keine
Datenmodell-Änderung). Version 0.1.0-alpha.2 → 0.1.0-alpha.3.

**Quelle.** Screenshot des ARALIYA-Menübaums (Joseph White, 17.09.2026);
`araliya-platform-core/src/Core/RoleManager.php` (Zeilen 143–155, 368–369, 436, 440, 593,
615, 635, 665: durchgängiges "administrator + araliya_admin"-Muster).

### 2026-09-17 · Bugfix: Aktivierung schlug immer fehl (class_exists auf Interface)

**Kontext.** Joseph meldete nach dem Deploy reproduzierbare Aktivierungsfehler
(„ARALIYA Platform Core ist nicht aktiv") trotz aktivem Core. Anhand der von Joseph
bereitgestellten Docker-Logs (Zeitstempel: Core aktiviert 21:34:41, danach nicht mehr
angefasst; Liebherr-Aktivierung 21:35:08 schlägt trotzdem fehl) wurde ein Umgebungsproblem
ausgeschlossen und stattdessen der Code selbst untersucht.

**Ursache.** `core_is_available()` prüfte mit `class_exists('Araliya\Platform\Core\Core\
ModuleInterface')`. `ModuleInterface` ist im Core als `interface` deklariert, nicht als
`class` – `class_exists()` liefert für Interfaces unabhängig vom Ladezustand **immer**
`false` (`interface_exists()` wäre der korrekte Check gewesen). Dadurch war die
Aktivierung strukturell nie möglich, unabhängig vom tatsächlichen Core-Status.

**Fix.** `core_is_available()` prüft jetzt primär mit der nativen WordPress-Funktion
`is_plugin_active('araliya-platform-core/araliya-platform-core.php')` (Slug gegen die
tatsächliche Hauptdatei im Core-Ordner verifiziert), mit `interface_exists(ModuleInterface)`
als Fallback für Randfälle (z. B. während einer Netzwerkaktivierung). Konstante
`CORE_DEPENDENCY_CLASS` in `CORE_DEPENDENCY_PLUGIN_FILE` + `CORE_DEPENDENCY_INTERFACE`
aufgeteilt. Zusätzlich den bereits vorbereiteten `Requires Plugins: araliya-platform-core`-
Header (WP 6.5+ native Dependency-Deklaration) im Plugin-Header ergänzt – das ist die
Erklärung für das separat gemeldete „Ausgrauen" von Core im Installer-Assistenten: das ist
korrektes natives WP-Verhalten (identisches Muster wie bei `araliya-installer`), kein Bug.
Version auf 0.1.0-alpha.2 angehoben.

**Auswirkung.** Kategorie B (Bugfix am neuen Plugin selbst, keine Core-Änderung). Betrifft
nur `liebherr-interface-world.php`. `tests/run-tests.php` erneut grün (Syntax-Check auf der
geänderten Datei).

**Quelle.** Von Joseph bereitgestellte Docker-/Apache-Logs (17.09.2026, 21:3x);
`araliya-platform-core/src/Core/ModuleInterface.php` (Deklaration als `interface`);
`araliya-platform-core/araliya-platform-core.php` (verifizierter Plugin-Slug).

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
