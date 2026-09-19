# ADR-LIW-CVF-001 — Customer View Flow & CAPDB: Integrationsplan (Phase 1)

**Pflichtenheft:** `Pflichtenheft_Liebherr_World_Customer_View_Flow.md` v1.1 (19.09.2026)
**Plugin:** `liebherr-interface-world` (Liebherr-World-Satellit)
**Status:** Entwurf zur Freigabe — Phase-1-Gate (§19 „Freigegebener Integrationsplan")
**Erstellt:** 19.09.2026 · Solution Provider GoHeal

> Zweck: Dieses Dokument ist das in §19 geforderte Phase-1-Ergebnis. Es dokumentiert den Ist-Stand,
> mappt ihn auf die Soll-Entitäten des Pflichtenhefts, legt die **Wiederverwendung statt Neubau** fest
> (Plattformregel „Keine Redundanzen") und beschreibt Datenmodell-Entwurf, Feature-Flags und die
> Umsetzungsreihenfolge. **Kein Code** — erst nach Freigabe folgen Phase 2 ff.

---

## 1 Grundsatzentscheidungen

1. **Ort:** Der Customer View Flow (CVF) und das CAPDB leben im bestehenden Plugin `liebherr-interface-world`
   (= die Liebherr-World-Ebene). Kein neues Plugin, kein Ersatzsystem.
2. **Wiederverwendung:** Vorhandene, bereits produktiv getestete Bausteine (Access Gate, Rechen-Challenge,
   Modul-Hub, Intro, Event-Ledger, Audit/Rollen/i18n über CoreBridge) werden **generalisiert und
   eingebunden**, nicht dupliziert. Wo heute zwei Varianten existieren (Rechen-Gate in LI-Intro **und** im
   Download-Plugin `Security`), wird eine **einzige** `ChallengeService`-Naht geschaffen.
3. **Autorisierung serverseitig:** Sichtbarkeit im Frontend ist nie Autorisierung (§14). Grants/Freigaben/
   Timer entstehen ausschließlich nach serverseitiger Prüfung (§9/§17).
4. **Keine Secrets/Domains/Texte hart codiert** (§19.1); Zugangscode als Hash/Secret serverseitig (§5.1);
   Texte als Content-Keys (§14.1); alle Referenzen über IDs (§12).
5. **Feature-Flags:** Customer Flow, CAPDB und jedes Modul hinter je einem Flag (§19.1) — Default AUS, damit
   der heutige (funktionierende) IW-Eintritt unverändert bleibt, bis die neue Runtime freigegeben ist.
6. **Prototyp-Grenzen bleiben (§1.2):** keine Benutzerverwaltung/SSO/Payment/CRM/Biometrie; nur
   Erweiterungspunkte dafür.

## 2 Ist-Stand (Analyse) → Soll-Mapping

| Pflichtenheft-Soll | Ist im Plugin | Bewertung / Aktion |
|---|---|---|
| §3 St. 1–4, §5.1, §13 `entry/verify` — Zugangscode serverseitig | `IntelligenceWorld\WorldContent` (Code, Prototyp `LIEBHERR-DEMO`), `WorldView` (Gate-UI), `Rest` (`session/start`) | **Wiederverwenden**; für §5.1 Hash/Secret + Rate-Limit/Lockout **härten** (heute Klartext-Option, Prototyp) → `AccessService` |
| §5.2, §13 `challenge`/`verify` — 2× zweistellige Addition, signiert, TTL, einmalig | `liebherr-download-consent\Security` (HMAC, 2×2-stellig, 1800 s) **und** LI-`IntroOverlay`-Rechen-Gate | **Konsolidieren** zu einer `ChallengeService`-Naht (signierte Challenge-ID, TTL 120 s, Einmalgebrauch, Lockout) — beide Altnutzungen später darauf umstellen |
| §3 St. 5/§4/§24.1 — Modulauswahl, 3 Module | IW-Funktions-Hub (`WorldView::hub_tiles`), `WorldSwitcher`, 4 Inseln | **Wiederverwenden**; Module als administrierbare `module`-Entität (Status/Sortierung/Route/introVersion) formalisieren |
| §4.1 — First Entry Flow je Modul | LI `IntroOverlay` (Sternenregen + Terms + Rechen-Gate) | **Verallgemeinern** zu modul-/versionsspezifischem First-Entry (Content-Keys `module.intro.*`) |
| §7 St. 11 / §6–§9 — Workflow-Runtime (Events/Conditions/Priority/Delay/Locks/Actions/Transitions) | — (nicht vorhanden) | **Neu:** deterministische, testbare Engine |
| §6/§24–§27 — CAPDB Admin-Board (Timeline, Drag-and-Drop, Plugin-Zonen, Simulation, Diff, Validierung) | Board-Muster vorhanden (z. B. `IntelligenceWorldBoardPage`, `AdventureBoardPage`), aber keine Timeline/DnD | **Neu** (größter Brocken); zuerst Tabellenansicht (§6 „alternative Tabellenansicht ist verpflichtend"), dann Timeline/DnD/Simulation |
| §25/§26 — Plugin-Registry/-Modell + Zustände | — | **Neu:** Registry mit Manifest + Parameter-Schema + Zustandsautomat |
| §12/§30 — Datenmodell + JSON-Schemas | Teilweise (Event-Ledger, Session) | **Neu/erweitern** (siehe §4) |
| §12/§17 — Execution-/Audit-Log, Metriken | `IntelligenceWorld\EventLog` + `Adventures\TokenLedger` (Hash-Ketten), `CoreBridge\AuditBridge` | **Wiederverwenden** (Muster) + `execution_log`/`audit_log` gemäß Feldliste |
| §10 — Versionierung/Publish/Rollback | Core-Deployment-Manager (Deploy-Ebene), Board-Publish-Muster | **Neu für Workflow-Versionen** (`workflow_version` unveränderlich + checksum); Deploy-Manager NICHT dafür zweckentfremden |
| §14.1 — Content-Keys | Board-Content-Modelle, `__()`/Overrides | **Wiederverwenden** über `CoreBridge\TranslationBridge` |
| §13 REST + §13.1 Grundregeln | REST-Muster `liw-iw/v1`, `liw-adv/v1` (versioniert, neutrale Fehler) | **Muster wiederverwenden**, neue Routen ergänzen |
| §17 Analytics getrennt vom Audit | AuditBridge vorhanden; Analytics separat | **Neu (dünn):** datensparsame `analytics.record`-Action mit Consent-Klasse |

## 3 Zielarchitektur (Komponenten, §18)

- **Access Service** — Codeprüfung (Hash/Secret), World-Grants, Modul-Grants, Session-Policy. Basis: heutiges
  IW-Gate; gehärtet.
- **Challenge Service** — Erzeugung/Signatur/Ablauf/Versuche/Prüfung der Additionsaufgabe. Basis: Download-
  Plugin-`Security`-Muster, in den Satelliten gehoben und wiederverwendbar.
- **Workflow Runtime** — Event-Matching → Conditions → Priority → Delay/Locks → Actions → Transitions (§9),
  deterministisch, gegen die **veröffentlichte** Version gebunden.
- **Workflow Admin UI (CAPDB)** — Tabellenansicht (Pflicht) + Timeline/DnD/Plugin-Zonen + Simulation + Diff +
  Validierung + Publish/Rollback.
- **Plugin Registry** — registrierte Funktionstypen (Manifest, Scopes, Parameter-Schema, Capability-Klasse),
  Instanzen mit Zustandsautomat (§26.2).
- **Content Service** — versionierte, lokalisierte Texte/Medien über CoreBridge.
- **Audit & Metrics** — sichere Ereignisse (Hash-Kette), Kennzahlen (§17), keine Secrets/Codes/Lösungen.
- **Persistence** — versionierte Workflows/Inhalte/Zustände/Grants/Protokolle (§12/§30) via eigener Tabellen
  (Muster wie `liw_iw_*`/`liw_adv_ledger`, COMMENT-ohne-Klammern-Falle beachten).

## 4 Datenmodell-Entwurf (§12/§30)

Neue Tabellen (Präfix `liw_cvf_`), JSON-Felder mit **versioniertem** Schema, Referenzen nur per ID:

- `liw_cvf_workflow` (id, key, name, scope, status, createdAt, createdBy)
- `liw_cvf_workflow_version` (id, workflowId, version, schemaVersion, state, publishedAt, publishedBy, checksum) — **unveränderlich** nach Publish
- `liw_cvf_stage` (id, versionId, number, name, status, scope, triggerType, priority, delayMs, conditionsJson, repeatPolicyJson, validityJson)
- `liw_cvf_stage_action` (id, stageId, sequence, actionType, parametersJson, lockKey, failureMode)
- `liw_cvf_stage_transition` (id, fromStageId, outcome, toStageId)
- `liw_cvf_module` (id, key, labelKey, routeId, status, sortOrder, introVersion, configJson)
- `liw_cvf_visitor_session` (id, anonymousVisitorId, workflowVersionId, state, issuedAt, expiresAt)
- `liw_cvf_visitor_grant` (id, sessionRef/userRef, scopeType, scopeId, grantVersion, issuedAt, expiresAt)
- `liw_cvf_challenge` (id, sessionId, moduleId, signedPayloadRef, expiresAt, attemptCount, consumedAt)
- `liw_cvf_execution_log` (id, timestamp, versionId, stageId, eventType, result, durationMs, safeErrorCode)
- `liw_cvf_audit_log` (id, actorId, action, entityType, entityId, beforeHash, afterHash, timestamp)
- Board/Plugin (§30): `liw_cvf_board_area`, `liw_cvf_board_edge`, `liw_cvf_plugin_type`,
  `liw_cvf_plugin_instance`, `liw_cvf_plugin_schedule`, `liw_cvf_plugin_execution`, `liw_cvf_board_layout`

Kern-Enums werden als reine, testbare PHP-Klassen geführt (Muster `RegistrationStatus`/`EventTypes`):
Visitor-State (§3.1), Event-Katalog (§7), Action-Katalog (§8), Plugin-Zustände (§26.2), Repeat-Policy (§6.2).

## 5 Konsolidierung „Human Verification" (keine Redundanz)

Heute existieren **zwei** Rechen-Gates (LI-`IntroOverlay`, Download-`Security`). Ziel: **ein**
`ChallengeService` (signierte Challenge, TTL 120 s, Einmalgebrauch, max. 5 Fehlversuche/Modul+Session,
barrierefreier Text, Lösung nie im Client — §5.2). Die beiden Altnutzungen werden nach Einführung des
Service darauf umgestellt; danach wird der jeweils alte Pfad entfernt (Plattformregel: alte Quelle löschen).

## 6 Feature-Flags & Sicherheits-Gates

- Flags: `liw_cvf_enabled` (Runtime/Customer-Flow), `liw_cvf_board_enabled` (CAPDB), je Modul `liw_cvf_module_<key>`.
- Build-/Security-Check: kein Demo-Code im Client-Bundle (§11 „Demo Code im Client Bundle = Fehler", A22).
- Session: Secure/HttpOnly/SameSite, ID-Rotation nach Grant (§5.3) — über WP-Session/Grant-Token.

## 7 Umsetzungsreihenfolge (folgt §19)

| Phase | Lieferumfang | Gate |
|---|---|---|
| **1 (dieses Dokument)** | Analyse + Integrationsplan | **Freigabe durch Joseph** |
| 2 | Domänenmodell: Tabellen `liw_cvf_*`, Enums, JSON-Schemas, Migrationen; `ChallengeService`-Naht | Schema-/Migrationstests |
| 3 | Access-Backend: Code-Härtung (Hash/Secret), World-Grants, Sessions, Rate-Limit/Lockout, neutrale Fehler | Security-/API-Tests |
| 4 | Challenge-Backend: signierte Aufgabe, Ablauf, Retry, Lockout, Replay-Schutz | Manipulations-/Replay-Tests |
| 5 | Customer-UI: Eingang, Auswahl, First Entry, Modullanding (auf Vorhandenem) | E2E Erstbesuch (A01–A10) |
| 6 | Runtime: Event-Engine, Conditions, Priority, Delay, Locks, Transitions | Deterministische Engine-Tests (A13/A14) |
| 7 | CAPDB: Tabellenansicht + Editor + Validierung, dann Timeline/DnD/Plugin-Zonen/Simulation | Admin-E2E + A11y (A25–A34) |
| 8 | Publish/Rollback/Audit/Metriken/Runbook | Staging-Abnahme (A17/A18/A35/A36) |
| 9 | Härtung: Last, Security, Browser, A11y, Recovery | Produktionsfreigabe |

## 8 Offene Punkte / Rückfragen an Joseph

1. **Rollen** (§2): Content-Editor/Workflow-Editor/Publisher/Auditor/Administrator auf die 7 ARALIYA-Rollen
   mappen — oder neue Caps im Satelliten? (Empfehlung: über `RoleBridge` auf bestehende Caps mappen.)
2. **Zugangscode-Härtung:** heute Prototyp-Klartext-Option (`liw_iw_world`). Für §5.1 auf Hash/Secret
   umstellen — betrifft den bestehenden IW-Eintritt. OK, gemeinsam mit Feature-Flag?
3. **Verhältnis zum heutigen IW-Eintritt:** Neue Runtime zunächst **parallel hinter Flag**; der bestehende
   Gate bleibt Default, bis die CVF-Version freigegeben ist. Einverstanden?
4. **Vier-Augen-Freigabe** (§10 Pkt 15/§29.8): erzwingen oder optional/mandantenabhängig?
5. **Umfang „Startversion":** Reicht als erste sichtbare Ausbaustufe der **vertikale Durchstich**
   (§22 Stufen 1–7 als versionierte Config über eine minimale Runtime), bevor das volle Timeline-Board kommt?

---

*Nach Freigabe dieses Plans beginnt Phase 2. Bis dahin: kein Code, keine Schemaänderung. Bestehende Funktionen
(Islands, Adventures-Basislogik, IW-Protokoll etc.) bleiben unverändert.*
