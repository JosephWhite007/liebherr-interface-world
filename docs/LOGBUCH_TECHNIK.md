# Logbuch für Techniker – Liebherr Interface Solutions

Eigenes Logbuch dieses Plugins, analog zur Konvention von `araliya-platform-core`
(`docs/LOGBUCH_TECHNIK.md`). Entscheidungen, die die **Ein-Plugin-Regel-Ausnahme** und die
**Variante-A-Integrationsentscheidung** selbst betreffen, stehen im Logbuch von
`araliya-platform-core` (Core-Governance). Hier stehen die Entscheidungen, die innerhalb dieses
Plugins gefallen sind.

## Teil II – Sitzungs-Logbuch (neueste zuerst)

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
