# Logbuch für Techniker – Liebherr Interface Solutions

Eigenes Logbuch dieses Plugins, analog zur Konvention von `araliya-platform-core`
(`docs/LOGBUCH_TECHNIK.md`). Entscheidungen, die die **Ein-Plugin-Regel-Ausnahme** und die
**Variante-A-Integrationsentscheidung** selbst betreffen, stehen im Logbuch von
`araliya-platform-core` (Core-Governance). Hier stehen die Entscheidungen, die innerhalb dieses
Plugins gefallen sind.

## Teil II – Sitzungs-Logbuch (neueste zuerst)

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
