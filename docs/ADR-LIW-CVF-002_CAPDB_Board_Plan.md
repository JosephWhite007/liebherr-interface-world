# ADR-LIW-CVF-002 — CAPDB / Workflow Administration Board: Umsetzungsplan

**Pflichtenheft:** `Pflichtenheft_Liebherr_World_Customer_View_Flow.md` §6, §24–§31 (v1.1)
**Baut auf:** [ADR-LIW-CVF-001](ADR-LIW-CVF-001_CustomerViewFlow_Integrationsplan.md) + CVF Phase 2 (alpha.81–88)
**Status:** Entwurf zur Freigabe — Planungs-Gate (§19/§29). **Kein Code bis Freigabe.**
**Erstellt:** 19.09.2026 · Solution Provider GoHeal

> Das CAPDB (Customer Action Priority Definition Board) ist die volle Workflow-Editor-Oberfläche: die
> Customer-View-Experience als **editierbare Timeline** aus vier Bereichen mit administrierbaren Übergängen,
> Plugins per Drag-and-Drop, sekundengenauer Zeitsteuerung, Simulation und versioniertem Publish/Rollback.
> Dieser Plan erweitert das vorhandene `liw_cvf_*`-Modell — er ersetzt nichts.

---

## 1 Grundsatz

1. **Erweitern, nicht neu bauen.** Der vertikale Durchstich (Phase 2) bleibt: `WorkflowVersion` (unveränderlich
   + Prüfsumme), `Runtime`, `ChallengeService`, `AccessService`, `Flags`, `Roles`, Session/Execution-Log,
   `FlowView`/`Rest`, `CvfBoardPage`. Das CAPDB legt das reichere Board-/Plugin-Modell **darüber**.
2. **Tabellenansicht ist Pflicht (§6) und kommt zuerst** — ein vollständiger, nicht-visueller Bearbeitungs-/
   Publish-Weg, bevor das visuelle Timeline-Board gebaut wird. Kein Feature hängt allein an Drag-and-Drop.
3. **Kein frei ausführbarer Code im Board (§26).** Plugins sind registrierte Funktionstypen mit Manifest +
   validiertem Parameterschema + Capability-Klasse; kein JS/SQL-Eingabefeld.
4. **Serverseitig kontrolliert (§27/§31.1).** Sicherheitsrelevante Freigaben, Timer und Zustandswechsel prüft
   der Server; Browser-Timer sind nie allein maßgeblich. Simulation führt NIE echte Grants/Aktionen aus (§27.1).
5. **Nur veröffentlichte, validierte Versionen sind wirksam.** Entwurf ändert nie die Customer-View (A35);
   Rollback stellt die vorherige Board-+Plugin-Konfiguration vollständig wieder her (A36).
6. **Alles hinter Feature-Flags** (`liw_cvf_enabled` Runtime, `liw_cvf_board_enabled` CAPDB, je Plugin-Flag),
   Default AUS. Keine Demo-Codes im Client-Bundle (§11/A22).

## 2 Ist → Soll

| Soll (§24–§30) | Ist (Phase 2) | Aktion |
|---|---|---|
| Vier Bereiche als Timeline-Blöcke (§24.1) | 4 Inseln via `WorldSwitcher` + flache `stages`-Config | `board_area` je Bereich, aus WorldSwitcher geseedet |
| Administrierbare Übergänge/Kanten (§28) | `Runtime` linear (begin→…→in_module) | `board_edge` (from/to, trigger, condition, priority) + Runtime-Ausbau |
| Modulbaukasten + Plugin-Typen (§25/§26) | einzelne Bausteine (Challenge/Access/Intro/GetHelp/Nav) | `plugin_type`-Registry (Manifest/Schema/Scope/Capability) über bestehende Bausteine |
| Plugin-Instanzen auf Seiten/Übergängen (§24.2 C/D) | — | `plugin_instance` (hostType page/edge, hostId, config, priority, status) |
| Zeit-/Timersteuerung (§27) | — | `plugin_schedule` (timeOrigin/openAt/closeAt/duration/timeout/repeat/resumePolicy) |
| Plugin-Zustände (§26.2, 11) | — | reine `PluginState`-Zustandsmaschine (wie `VisitorState`) |
| Ausführungsprotokoll (§30) | `liw_cvf_execution_log` (Zustandsübergänge) | `plugin_execution` (state/openedAt/closedAt/result/safeErrorCode) ergänzen |
| Board-Layout/Positionen (§30) | — | `board_layout` (viewport, nodePositionsJson, zoom) |
| Version unveränderlich + Prüfsumme + Publish/Rollback (§29) | `WorkflowRepository` (publish/checksum/publish_guarded) | um board-Entitäten + Rollback erweitern |
| Tabellenansicht + Board + Eigenschaften + Simulation + Publish (§24.2 A–G) | `CvfBoardPage` (Flags/Code/Difficulty/Log) | Tabellenansicht zuerst; visuelles Board + Simulation danach |
| Validierung Struktur/Security/A11y/Konflikt (§29.7) | `WorkflowVersion::validate` (Stufen) | Validierungssuite ausbauen (Zonen/Scope/Timer/Konflikte) |

## 3 Datenmodell (§30, Präfix `liw_cvf_`)

Neue Tabellen, alle an `workflow_version.id` gebunden (versioniert, unveränderlich nach Publish):

- `liw_cvf_board_area` (id, version_id, module_id, position, route_id, status, validity_json)
- `liw_cvf_board_edge` (id, version_id, from_area_id, to_area_id, trigger_type, condition_json, priority)
- `liw_cvf_plugin_type` (id, key, manifest_version, allowed_scopes, parameter_schema_json, capability_class) — **zentral gepflegt, nicht versioniert**
- `liw_cvf_plugin_instance` (id, version_id, plugin_type_id, host_type[page|edge], host_id, status, priority, config_json)
- `liw_cvf_plugin_schedule` (id, instance_id, time_origin, open_at_ms, close_at_ms, duration_ms, timeout_ms, resume_policy, repeat_policy)
- `liw_cvf_plugin_execution` (id, session_id, instance_id, state, opened_at, closed_at, result, safe_error_code) — append-only
- `liw_cvf_board_layout` (id, version_id, viewport, node_positions_json, zoom, updated_by)

COMMENT ohne Klammern (Falle alpha.16). JSON-Felder mit versioniertem Schema; Referenzen nur per ID.

## 4 Plugin-Modell

- **Zustände (§26.2):** reine `PluginState`-Klasse — unassigned→configured→scheduled→opening→open→closing→
  closed→completed, plus disabled/failed/cancelled mit den erlaubten Folgeübergängen.
- **Scopes:** `page`, `edge` (+ „nur serverseitig autorisierte Übergänge" für Status-Plugins).
- **Kategorien der Startversion (§26.1)** → auf vorhandene Bausteine gemappt (keine Redundanz):
  | Kategorie | Startversion-Typ(en) | Baustein |
  |---|---|---|
  | Zugang/Sicherheit | Codeprüfung, zweistellige Addition, Rate-Limit-Hinweis | `AccessService`, `ChallengeService` |
  | Information | First-Entry-Text, Hilfe, Datenschutz, Medienfenster | `IntroOverlay`, `GetHelpAssistant` |
  | Navigation | Weiter/zurück, Modul öffnen, Modulauswahl | `WorldSwitcher`, `Runtime`-Events |
  | Interaktion | Bestätigung, Auswahl, Formular, Feedback | neu (dünn) |
  | Zeitsteuerung | Countdown, verzögertes Öffnen, Auto-Schließen | `plugin_schedule` |
  | Status | Grant/Sperre setzen, Zustand prüfen | `SessionRepository` (serverseitig) |
  | Messung | datensparsame Ablaufmessung + sichere Fehlerklasse | neu (dünn, §17) |
- **Manifest + Parameterschema:** je Typ im PHP registriert (kein User-Code); Ablegen validiert gegen Schema +
  `allowed_scopes`; unzulässige Zielzone wird abgewiesen (A26).

## 5 Zeit-/Timersteuerung (§27) & Simulation (§27.1)

- `plugin_schedule` je Instanz; `timeOrigin` = registriertes Event; `openAt/closeAt/duration/minimumOpen/
  timeout/repeat/resumePolicy/cancelOn`. Widersprüche (closeAt + duration) sind Validierungsfehler.
- Sicherheitsrelevante Zeitpunkte serverseitig geprüft; Resume-Policy bei Seitenwechsel/Inaktivität/Netzverlust.
- **Simulation** = Abspielkopf + Zeitlineal + chronologisches Protokoll; rein lesend, **führt nie echte Grants/
  Nachrichten/externe Aktionen aus** (deterministische Vorschau über die reine Runtime).

## 6 Board-Zonen (§24.2)

A Modulbaukasten · B Customer-Timeline (horizontal/zoombar) · C Übergangszonen · D Seiten-Plugin-Zonen ·
E Eigenschaften (rechts) · F Simulation (Abspielkopf) · G Veröffentlichung (Validierung/Diff/Freigabe/Rollback).
**Tastaturbedienung gleichwertig zu Drag-and-Drop (§25).**

## 7 Baureihenfolge (Etappen)

| Etappe | Lieferumfang | Gate |
|---|---|---|
| **1 (dieses Dokument)** | Plan + Datenmodell-Entwurf + Entscheidungen | **Freigabe Joseph** |
| 2 | Tabellen `liw_cvf_board_*`/`plugin_*` + Enums (`PluginState/Scope/Category`) + Repositories + Startkonfig-Seeder (4 Bereiche + Übergänge §24.1/§28) | Schema-/Migrationstests |
| 3 | Plugin-Registry: die 7 Kategorien als registrierte Typen (Manifest/Schema/Scope/Capability), Schema-Validierung | Registry-/Validierungstests |
| 4 | **Tabellenansicht (Pflicht §6):** Bereiche/Kanten/Plugin-Instanzen lesen+bearbeiten, Entwurf→Validierung→Publish/Rollback (Vier-Augen optional) | Admin-E2E (A25/A35/A36) |
| 5 | Runtime-Ausbau: Kanten/Transitions/Conditions/Priority/Locks + Plugin-Scheduling (serverautoritativ) + `plugin_execution`; veröffentlichte Board-Config speist `FlowView`/`Rest` | Engine-Tests (A27–A34) |
| 6 | Visuelles Board: Timeline + Modulbaukasten + DnD (+ Tastatur) + Eigenschaften-Panel + Plugin-Zonen | Board-E2E + A11y (A26) |
| 7 | Simulation: Abspielkopf/Zeitlineal/Protokoll (ohne echte Aktionen) | A29–A33 |
| 8 | Validierung/Diff/Vier-Augen/Publish/Rollback + Feature-Flags, Abnahme A25–A36 | Staging-Abnahme |
| 9 | Härtung: ≥50 Stufen (§21), Last, Security, A11y, Recovery | Produktionsfreigabe |

## 8 Nicht-Verhandelbares (Checkliste)

Tabellenansicht Pflicht · kein User-JS/SQL im Board · Simulation ohne echte Aktionen · serverautoritative
Security/Timer/Grants · nur veröffentlichte Versionen wirksam · Entwurf ändert Customer-View nie · Rollback
vollständig · Feature-Flags Default AUS · kein Demo-Code im Client-Bundle · ≥50 Stufen bedienbar · DnD und
Tastatur gleichwertig · unzulässige Plugin-Zielzonen abgewiesen · vollständig auditierbar.

## 9 Offene Entscheidungen (vor Etappe 2)

1. **Reihenfolge:** Tabellenansicht (§6) zuerst, visuelles Board danach — bestätigen? (Empfehlung: ja.)
2. **DnD-Technik:** eigenes Vanilla-JS-Drag-and-Drop (kein Build-Step, Host hat kein node) vs. CDN-Bibliothek.
   (Empfehlung: Vanilla — passt zur Umgebung, keine Abhängigkeit.)
3. **Plugin-Startumfang:** welche konkreten Typen zuerst? (Empfehlung: Codeprüfung, zweistellige Addition,
   First-Entry-Text, Weiter/Modul öffnen, Grant setzen, Countdown, Messung — je einer pro Kategorie.)
4. **Versions-Anker:** `workflow_version` bleibt der unveränderliche Anker, alle `board_*`/`plugin_instance`
   referenzieren `version_id`; `plugin_type` bleibt zentral/unversioniert — bestätigen?
5. **Vier-Augen:** die in alpha.87 gebaute leichtgewichtige Prüfung übernehmen (Empfehlung) oder echte
   Submit→Approve-Queue?
6. **Rollback-Modell:** Rollback = neue Version als Kopie der Zielversion veröffentlichen (Verlauf bleibt),
   nicht „hartes Zurücksetzen" — bestätigen? (Empfehlung: ja, konsistent mit Unveränderlichkeit.)

---

*Nach Freigabe dieses Plans beginnt Etappe 2 (Datenmodell + Startkonfig-Seeder). Bis dahin kein Code, keine
Schemaänderung; der vertikale Durchstich (Phase 2) bleibt unverändert.*

---

## 10 Umsetzungsstand (freigegeben, „arbeite bis zum Ende durch")

Etappe 2–9 umgesetzt (alpha.89–alpha.96), jede mit grünen Tests:

- **alpha.89 (E2):** Datenmodell (7 Tabellen `liw_cvf_board_*`/`plugin_*`), `PluginState` (11 Zustände),
  `PluginTaxonomy`, `BoardRepository` (Entwurf/Publish/Rollback + CRUD + Startkonfig-Seeder), `BoardValidator`.
- **alpha.90 (E3):** `PluginRegistry` (8 Typen, alle 7 Kategorien; Manifest/Schema/Scope/Capability; `sync()`).
- **alpha.91 (E4):** Tabellenansicht/Editor `CvfBoardEditorPage` (Pflicht §6; Lifecycle + CRUD + Publish/Rollback).
- **alpha.92 (E5):** `BoardRuntime` (Auflösung/Prioritäten/Zeitfenster), `BoardSnapshot`, `ExecutionLog`
  (`plugin_execution`), REST `GET /board`; Flow-Challenge protokolliert serverseitig.
- **alpha.93 (E6):** visuelles Timeline-Board (Modulbaukasten, DnD + Tastatur, Zonen, Zoom; admin-ajax).
- **alpha.94 (E7):** Simulation (Abspielkopf/Zeitlineal/Protokoll) — reine Vorschau, keine echten Aktionen.
- **alpha.95 (E8):** `BoardDiff`, `Flags::board_enabled`, Diff-Sektion; Abnahme A26/A34/A35.
- **alpha.96 (E9):** Härtung ≥50 Stufen (§21) + Test-Isolation.

**Offen/optional:** Board als LIVE-Runtime scharfschalten (heute treibt der flache Phase-2-Durchstich die
Customer-View; das Board ist Editier-/Simulations-/Audit-Ebene hinter Flags) — Umschaltung ist eine bewusste
Folgeentscheidung. Feinschliff Eigenschaften-Panel (E-Zone) im visuellen Board; volle A11y-/Last-Messung auf Staging.
