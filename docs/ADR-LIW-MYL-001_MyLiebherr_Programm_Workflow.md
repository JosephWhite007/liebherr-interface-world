# ADR-LIW-MYL-001 — My Liebherr & Pocket Information: kompletter Programm-Workflow

**Status:** Entwurf zur Freigabe (Startklärung offen) · **Stand:** 19.09.2026 · **Plugin:** `liebherr-interface-world` (`0.1.0-alpha.107`)
**Grundlage:** `Pflichtenheft/Programmierpflichtenheft_My_Liebherr.md` (v2.0, 19.09.2026) inkl. **§41 Plattformzeit/Session-Uhr/Token-Schachuhr**
**Bezug:** ADR-LIW-001 (Struktur/CoreBridge) · ADR-LIW-CVF-001 (CVF-Integrationsplan) · ADR-LIW-CVF-002 (CAPDB-Board) · ADR-LIW-ADMIN-001 (Menü) · `LIW_RELEASEPLAN.md`
**Deploy-Regel (verbindlich):** lokal entwickeln + testen → committen **ohne Push** → Joseph gibt frei → dann Push (= Auto-Deploy). Jede Stufe = eigener Commit + Tests + CHANGELOG + drei Bücher + Logbuch.

---

## 1 Zweck und Geltung

Dieses Dokument überführt das My-Liebherr-Pflichtenheft (sechster Reiter Pocket Information eingeschlossen) in einen **durchgängigen, prüfbaren Arbeits-Workflow für sämtliche Programmmodule**. Es benennt je Modul die wiederverwendbaren Bausteine des bestehenden Plugins, eine stabile Modul-ID, Release-Zuordnung, Abhängigkeiten, Dateien/Tabellen/REST/Tests und die Abnahme-IDs. §38 des Pflichtenhefts verlangt **vor der ersten Codeänderung genau einen gebündelten Klärungsblock**; dieser steht in Abschnitt 4 und ist das Phase-0-Gate. Erst nach seiner Beantwortung beginnt Stufe S1.

My Liebherr wird gemäß §15 als **Modulbereich innerhalb dieses Plugins** umgesetzt (kein eigenes Plugin), Namespaces `Liebherr\InterfaceWorld\MyLiebherr\` und `Liebherr\InterfaceWorld\Pocket\`, angebunden an CoreBridge, Roles, Flags und MediaBridge wie die bestehenden Welten.

---

## 2 Ausgangslage — was schon existiert und wiederverwendet wird

| Vorhandener Baustein | Ort | Wiederverwendung für My Liebherr |
|---|---|---|
| Sitzungsdienst + Timeout | `src/IntelligenceWorld/SessionService.php` | Plattformzeit §41 (Start/Ende/Timeout) |
| Sitzungsmesser Zeit→Kosten | `src/IntelligenceWorld/SessionMeter.php` | Token-Zeitrechnung §41 |
| Geldwert/Token in kleinster Einheit | `src/IntelligenceWorld/Money.php` | Integer-Beträge Wallet §7/§17, §41 |
| Versionierte Preisregel | `src/IntelligenceWorld/PriceRule.php` | Zeit-Tokenregel §8/§41 |
| Manipulationsgeschütztes Ereignis-Ledger | `src/IntelligenceWorld/EventLog.php`, `EventTypes.php` | Audit §20, append-only Buchungen §7 |
| Belegerzeuger | `src/IntelligenceWorld/ProtocolBuilder.php`, `PdfDocument.php` | Belege §7/§30, Zeitabrechnung §41 |
| REST-Abrechnungsstatus (pro Sekunde) | `src/IntelligenceWorld/Rest.php` (`billing_status`) | Vorlage `platform-time/status` §41 |
| CAPDB-Board (Schema/Registry/Runtime/Diff/Snapshot/Board-UI) | `src/Cvf/*`, `src/Admin/Pages/CvfBoardPage.php`, `CvfBoardEditorPage.php` | CVF-6-Karten-Erweiterung §36, Plugin-Typ Session-Uhr §41.6 |
| Challenge*/Runtime/Access/Flags/Roles | `src/Cvf/ChallengeService.php`, `Runtime.php`, `AccessService.php`, `Flags.php`, `Roles.php` | First Entry / Human Challenge / Grants / Return Routes für neue Karten §36 |
| Rollen-Mapping (7 ARALIYA-Rollen) | `src/Cvf/Roles.php` | Rollen-/Grant-Schicht §35, Rollenmodell §3 |
| Feature-Flags (default OFF) | `src/Cvf/Flags.php` (`liw_cvf_*`) | neue Flags `liw_myl_*`, `liw_pocket_*`, `liw_ptime_*` |
| MediaBridge + Freigabe-Meta | CoreBridge / `_liw_media_approved` | Dreams/Own Gallery Medien §31 |
| Emergency-/Favicon-Service (schwebendes Symbol) | `src/Emergency/*`, `src/Frontend/FaviconService.php` | Muster für schwebendes Session-Uhr-Widget §41.6 |

**Grundsatz:** keine zweite Zeit- oder Saldenquelle, keine parallele Wallet. Der **Wallet-Adapter (§7)** ist die einzige Buchungs-/Saldenschnittstelle; die eigentliche Wallet-Fachlogik kommt als **eigenes nächstes Pflichtenheft** — bis dahin ist die Buchungsnaht per Flag deaktiviert und Vorgänge werden nur revisionssicher als *ausstehend* protokolliert (§41.1).

---

## 3 Modul-Landkarte (stabile IDs, Reihenfolge, Abhängigkeiten)

| Modul-ID | Modul / Reiter | §-Bezug | Release | Hängt ab von | Wiederverwendung |
|---|---|---|---|---|---|
| MYL-CORE | Identität, Kontext, Entitlements, Audit-Basis | §6, §15, §35 | R1 | — | Roles, CoreBridge, EventLog |
| MYL-NAV | 6-Reiter-Navigation + rollenabhängige Sichtbarkeit + Platzhalter | §1, §29, §35 | R1 | MYL-CORE | AdminMenu, CVF Roles/Grants |
| MYL-OVERVIEW (01) | My Overview (Bankkonto-Startseite) | §4, §5, §29, §30 | R1 | MYL-CORE, MYL-DASH | WorldView-Muster |
| MYL-DASH | Dashboard-Katalog + Layout-Persistenz | §5, §17 (dashboard_layout) | R1 | MYL-CORE | — |
| MYL-PROFILE (11) | My Profile / Rollen / Sicherheit / Datenschutz | §6, §14, §29 | R1 | MYL-CORE | Roles |
| MYL-WALLET-ADP | Wallet-Adapter = `CoreBridge\WalletBridge` → Plattform Health Wallet | §7, §17 | R2 | MYL-CORE | `WalletService` (Core), EventBus; **ersetzt** `Adventures\TokenAccount` |
| MYL-WALLET (02) | My Wallet UI (Salden, Buchungen, Belege) | §7, §18, §29 | R2 | MYL-WALLET-ADP | ProtocolBuilder/PdfDocument |
| MYL-PTIME | **Plattformzeit / Session-Uhr / Token-Schachuhr** | **§41** | R2 | MYL-WALLET-ADP | SessionService, SessionMeter, PriceRule, EventLog |
| MYL-ADV | Own Adventures + Lifecycle + Drei-Wort-Name | §9, §10, §11, §32 | R3 | MYL-WALLET-ADP | Adventures-Bestand |
| MYL-DREAMS (03) | My Dreams (Maschinen-Bilderbuch) | §31, §37 (dream_item) | R3 | MYL-CORE | MediaBridge |
| MYL-GALLERY (05) | Own Gallery (privat) | §31, §37 (gallery_item) | R3 | MYL-CORE | MediaBridge, Media-Pipeline |
| MYL-SHARE (06/07) | Shared with Colleagues / with the World | §31, §37 (share_grant) | R4 | MYL-GALLERY, MYL-ADV | Review-Muster |
| MYL-CONTACTS (08) | My Contacts + Wallet Connection + Service Exchange | §33, §37 | R4 | MYL-WALLET-ADP | EventLog, Belege |
| MYL-QUALITY | Meldungen, Sperren, Erstattung, Media-Pipeline, Notifications | §9, §11, §13 | R4 | MYL-ADV | Notification-Muster |
| MYL-MACHINES (09) | My Machines | §29, §37 | R5 | MYL-CORE | Local-Intelligence-Views |
| POCKET (10 / Reiter 6) | Pocket Information (Briefing/Alerts/Tasks/…) | §34, §37 (pocket_item) | R5 | MYL-CORE, MYL-NAV | Rules, Return Routes |
| CVF-6CARD | CVF-Board: 4→6 Karten, moduleIds `my_liebherr`, `pocket_information` | §36 | R3→R5 | CAPDB-Board (vorhanden) | `src/Cvf/*`, Board-UI |
| MYL-FUTURE (12) | Future Services (unsichtbarer Platzhalter) | §29 | Platzhalter | MYL-NAV | — |

---

## 4 Startklärung nach §38 (einmaliger gebündelter Block — Phase-0-Gate)

> **FREIGEGEBEN am 19.09.2026 (Joseph White).** Alle vier blockierenden Fragen entschieden,
> die acht Annahme-Punkte unwidersprochen gültig. Damit ist das Phase-0-Gate geschlossen.
> **Frage 1 wurde nach Sichtung der echten Plattform-Wallet (Admin-Seite `araliya-lav-wallet-planner`) korrigiert.**
>
> 1. **Wallet-Quelle = Plattform Health Wallet.** Einzige Saldenquelle ist `Araliya\Platform\Core\Modules\Wallet\WalletService`
>    (Tabellen `ary_wallet_accounts` + `ary_wallet_transactions`, keyed by WP `user_id`), angebunden über einen **neuen
>    `CoreBridge\WalletBridge`** (wie die 10 vorhandenen Bridges; das Wallet-Modul bietet keine Filter/Hooks → Bridge
>    ruft `WalletService` direkt, `class_exists`-guarded, und beobachtet den `EventBus`). Der bisherige Satelliten-
>    `Adventures\TokenAccount` (User-Meta-Guthaben) wird **abgelöst** — keine zweite Saldenquelle (§7, „keine Redundanzen").
> 2. **Einheit = echte, eigene Token-Währung im selben Wallet-Subsystem.** Token ist **kein** EUR-Alias und **keine**
>    zweite Wallet, sondern eine reale Währung **innerhalb der Plattform-Wallet**. Das erfordert **Mehrwährungsfähigkeit**
>    des Core-Wallet-Moduls (EUR **und** Token je Nutzer, eigener Token-Ledger-Zweig, gleiche Idempotenz +
>    Reservieren→Bestätigen + Audit). **Das ist Core-Kategorie A und fachlich Teil des kommenden Wallet-Pflichtenhefts →
>    wird hier spezifiziert, aber NICHT in diesem Programm gebaut.** Bis dahin bleibt die Buchungsnaht per Flag aus.
> 3. **Zeit-Tokenregel:** eigener versionierter **Token/Minute**-Satz, zentrale Min/Max je Rolle; der EUR-Preis der
>    Intelligence World bleibt getrennt.
> 4. **Geltungsbereich Session-Uhr:** **plattformweit** als schwebendes Widget, Anzeige je Rolle/Flag steuerbar.
> 5. **Buchungsfluss (§41):** **Reservieren→Bestätigen** — beim Eintritt `reserve()`, bei Austritt/Stop
>    `confirm_reservation()` (bzw. `release_reservation()` bei Abbruch); nutzt den vorhandenen WalletService-Fluss.
> 6. **Reihenfolge:** **vertikaler Durchstich zuerst**, danach in die Breite.
>
> **Verbindliche Durchstich-Reihenfolge (vor voller R-Breite):** S0 → S1 (MYL-CORE) → S2 (MYL-NAV) →
> S4 (MYL-OVERVIEW, Minimal) → S9 (MYL-PTIME Zeitmessung) → S10 (Abrechnung/Naht, Buchung hinter Flag AUS) → S11
> (Schachuhr-Widget). Erst danach S3/S5/S6 und die restlichen Releases. **S7 Wallet-Adapter = `CoreBridge\WalletBridge`
> gegen die Plattform Health Wallet** (Lese-/Reservier-/Bestätigen-Fassade); der Token-Währungszweig + die Ablösung von
> `TokenAccount` sind an das Wallet-Pflichtenheft gekoppelt (siehe Abschnitt 9). Bis dahin: Plattformzeit protokolliert
> nur *ausstehende* Token-Abrechnungen (MYL 028).

### Wallet-Bestandsaufnahme (S0-Ergebnis, gesichtet 19.09.2026)

| Aspekt | Ist-Zustand der Plattform Health Wallet |
|---|---|
| Fassade | `WalletService` (statisch): `get_balance` / `get_summary` / `get_transactions` / `credit` / `debit` / `reserve` / `confirm_reservation` / `release_reservation` |
| Tabellen | `ary_wallet_accounts` (gespeicherter `balance_cents` + Jahresbudget/Etat), `ary_wallet_transactions` (append-only, `status pending→confirmed→settled/…`, **UNIQUE `idempotency_key`**) |
| Modell | Hybrid: Saldo als Spalte, im selben DB-Transaktionsblock wie der Ledger-Insert; **kein** `rule_version`, **kein** Doppelbuchungs-Soll/Haben, single-signed `amount_cents` |
| Einheit / Identität | **EUR-Cent (Integer)**; keyed by **WP `user_id`** (`guest_id` = user_id, UNIQUE) |
| Erweiterung | **Keine Filter/Hooks im Modul** → direkt `WalletService` aufrufen + `EventBus` (`WalletDebited`/`WalletCredited`) beobachten; Audit/Analytics/Notifications hängen bereits an |
| Finanzierungsquellen | self/family/employer/sponsor/insurance/government/voucher als `source_type` (kein eigenes Feld) |
| Spec | ARY-PH-HW-1.0.0 (Health Wallet) |

**Lücken gegenüber My-Liebherr-Spec (§7/§8/§17), die das Wallet-Pflichtenheft schließen muss:** Mehrwährung (Token),
`rule_version` auf Buchungen, ausstehend/gesperrt-Buckets, vier-Augen-Korrektur. Bis dahin nutzt My Liebherr die Wallet
**lesend + EUR-Reservierung/Bestätigung** und hält Token-Abrechnungen als *ausstehend* protokolliert.

Die ursprünglichen Fragen bleiben zur Nachvollziehbarkeit dokumentiert; maßgeblich sind die Freigaben oben.

### 4.1 Blockierend (ohne Antwort keine korrekte Umsetzung)

1. **Wallet-Quelle.** Nutzen wir für R2 den im Plugin bereits erreichbaren Token-Bestand (CoreBridge/`TokenAccount`) als Ledger hinter dem Wallet-Adapter, oder wartet R2 vollständig auf das kommende Wallet-Pflichtenheft? (Empfehlung: Adapter jetzt bauen, gegen `TokenAccount` als Ledger, reale Wallet-Fachlogik hinter Flag `liw_myl_wallet_live=OFF`.)
2. **Token-Zeitregel (§41).** Rechnet die Session-Uhr in **Token/Minute** mit zentral gepflegten Min/Max je Rolle, oder wird der Satz aus der bestehenden IW-Preisregel abgeleitet? (Empfehlung: eigener versionierter Token/Minute-Satz, IW-EUR-Preis bleibt getrennt.)
3. **Geltungsbereich der Session-Uhr.** Plattformweit über alle sechs Reiter, oder zunächst nur My Liebherr + Intelligence World? (Empfehlung: plattformweit als schwebendes Widget, Anzeige per Rolle/Flag steuerbar.)
4. **Reihenfolge R vs. Durchstich.** Strikt R1→R6, oder zuerst ein vertikaler Durchstich (MYL-CORE + MYL-NAV + MYL-OVERVIEW + MYL-PTIME als sichtbares Minimalerlebnis) und danach in die Breite? (Empfehlung: vertikaler Durchstich zuerst, wie bei CVF Phase 2.)

### 4.2 Annahmen (Standard gilt ohne Widerspruch)

5. **Namespaces/Ablage:** `src/MyLiebherr/`, `src/Pocket/`, `src/PlatformTime/`; Tabellen-Präfix `ary_liw_myl_*`, `ary_liw_pocket_*`, `ary_liw_ptime_*`.
6. **Flags default OFF:** `liw_myl_enabled`, `liw_pocket_enabled`, `liw_ptime_enabled`, `liw_myl_wallet_live`, `liw_ptime_charge_live`.
7. **Rollen:** Mapping der acht Pflichtenheft-Rollen (§3) auf die 7 ARALIYA-Rollen über die bestehende `Cvf\Roles`-Logik; kein neues Rollensystem.
8. **Tests:** weiter zweigleisig — WP-freie Suite `tests/run-tests.php` (Lint + Unit) und Docker-Selftest `scripts/liw-selftest.php`; jede Stufe ergänzt beide.
9. **i18n:** alle sichtbaren Texte über `__()` (Textdomain `liebherr-interface-world`), DE/EN Primärsprachen; keine fest verdrahteten Texte.
10. **Medienpipeline:** Upload-Prüfung/Quarantäne (§16/§14) über bestehende MediaBridge-Freigabe; Virenscan-Schnittstelle als Naht (extern), default konservativ „nicht freigegeben".
11. **Platzhalter-Reiter (§29 Pos. 12 / §34 Future Pocket):** im Nav-Schema + CVF-Board vorgesehen, aber unsichtbar; keine leeren Seiten/Buttons.
12. **Kein Produktivgang** ohne die in Abschnitt 9 gelisteten Kategorie-A-Entscheidungen (Recht/Steuer/Vergütung, §14/§26).

---

## 5 Der Workflow — Stufen S0…S24

Jede Stufe: **Ziel · Dateien/Klassen · DB/Migration · REST · Tests · Abnahme · Flag · Exit**. Reihenfolge ist verbindlich; additive, verlustfreie Migrationen (Kategorie B), Datenoperationen mit echten Daten nur als idempotente CLI-Skripte (Terminal-Regel).

### R0 — Analyse & Verträge

- **S0 · Architekturprotokoll & Adaptervertrag.** Wallet-Bestand, CVF-Board, Rollen, Taxonomie, Medien lesend prüfen; Adaptervertrag Wallet + Zeit-Tokenregel fixieren; offene Rechtsfragen (§14/§26) listen. *Exit:* Startklärung (Abschnitt 4) beantwortet, Adaptervertrag freigegeben. *Liefergegenstand:* Update dieses ADR + `docs/IMPLEMENTATION_NOTES.md`.

### R1 — Fundament (MYL-CORE, MYL-NAV, Dashboard, Overview, Profile)

- **S1 · MYL-CORE Kontext & Entitlements.** `MyLiebherr\Context`, `EntitlementService` (Schnittmenge Konto/Org/Rolle/Objekt/Region/Produkt/Freigabe, §3/§35). *DB:* `ary_liw_myl_profile`, `ary_liw_myl_membership`. *REST:* `GET/PATCH /my-liebherr/v1/me`. *Abnahme:* MYL 001, MYL 003, SEC 01. *Flag:* `liw_myl_enabled`.
- **S2 · MYL-NAV Sechs-Reiter-Navigation.** Reiter Intelligence/Local/Interface/Adventures/My Liebherr/Pocket in verbindlicher Reihenfolge; rollenabhängige Sichtbarkeit; Platzhalter unsichtbar. *Datei:* `src/Admin/AdminMenu.php` + Frontend-Nav. *Abnahme:* MYL 013 (Teil), Direktlink-Schutz SEC 01.
- **S3 · MYL-DASH Dashboard.** Widget-Katalog (nur berechtigte Widgets), DnD, Größen, Persistenz je Gerätetyp. *DB:* `ary_liw_myl_dashboard_layout`. *REST:* `GET/PUT /dashboard` (ETag). *Abnahme:* MYL 002, MYL 012.
- **S4 · MYL-OVERVIEW.** Bankkonto-Startseite: Saldo/Aufgaben/Aktivität/Schnellaktionen (§30). *Abnahme:* MYL 001, A11y-Stichprobe.
- **S5 · MYL-PROFILE.** Stammdaten, Rollen/Org-Wechsel sichtbar, Sicherheit/Datenschutz, Export/Löschung-Einstieg. *REST:* `PATCH /me` (Feldfreigabe). *Abnahme:* MYL 011 (Einstieg), SEC 06.
- **S6 · R1-Abnahme.** Mandantensichere Navigation + API; Negativtests fremde Org/Rolle/Objekt. *Exit R1:* SEC 01 grün, MYL 001–003/012 grün.

### R2 — Wallet & Plattformzeit (MYL-WALLET-ADP, MYL-WALLET, MYL-PTIME)

- **S7 · MYL-WALLET-ADP Adapter = `CoreBridge\WalletBridge`.** **Keine eigene Ledger-Tabelle im Satelliten.** Neuer Bridge ruft `Araliya\Platform\Core\Modules\Wallet\WalletService` (`class_exists`-guarded): `get_balance`/`get_summary`/`get_transactions` (lesen), `reserve`/`confirm_reservation`/`release_reservation` (zwei-Phasen-Buchung, EUR), `debit`/`credit` (mit caller-eigenem `idempotency_key`); beobachtet `EventBus` `WalletDebited`/`WalletCredited`. **Token-Währungszweig + Ablösung `Adventures\TokenAccount` an das Wallet-Pflichtenheft gekoppelt (Core-Kategorie A, Abschnitt 9) — nicht in diesem Programm.** *Abnahme:* SEC 02, SEC 03 (über WalletService-Idempotenz/-Status), MYL 004 (Salden aus WalletService).
- **S8 · MYL-WALLET UI.** Salden verfügbar/reserviert/ausstehend/gesperrt; Transaktionen paginiert; Belege. *REST:* `GET /wallet`, `GET /wallet/transactions`. *Abnahme:* MYL 004, MYL 006 (Vorbereitung), Export-Limit.
- **S9 · MYL-PTIME Zeitmessung (serverautoritär).** `PlatformTime\SessionClock` auf Basis SessionService/SessionMeter; Heartbeat, Pause bei Inaktivität/Austritt. *DB:* `ary_liw_ptime_session`. *REST:* `GET status`, `POST heartbeat`, `POST stop`. *Abnahme:* **MYL 026**. *Flag:* `liw_ptime_enabled`.
- **S10 · MYL-PTIME Abrechnung & Naht (Reservieren→Bestätigen).** Zeit→Token via versionierter Zeit-Tokenregel; append-only `ary_liw_ptime_charge` (satellitenseitiger Nachweis, **kein** Saldo). Beim Eintritt `WalletBridge::reserve()`, bei Stop `confirm_reservation()`, bei Abbruch `release_reservation()` — **nur** bei `liw_ptime_charge_live`; sonst wird der Abschnitt lediglich als *ausstehende* Token-Abrechnung protokolliert (Token-Buchung wartet auf den Mehrwährungs-Wallet aus dem Wallet-Pflichtenheft). Zustandsautomat started→…→settled/voided. *Abnahme:* **MYL 027, MYL 028**, SEC 02/03.
- **S11 · MYL-PTIME Schachuhr-Widget.** Schwebendes, jederzeit ein-/ausblendbares Widget (Muster Emergency/Favicon) mit Zeit + laufenden Tokenkosten; `prefers-reduced-motion`; Tastatur. *Abnahme:* **MYL 025**, MYL 012.
- **S12 · R2-Abnahme.** Parallel-/Wiederholungstests: genau eine Belastung; Zeit-Doppelstopp ohne Doppelbuchung. *Exit R2:* SEC 02/03 grün, MYL 004/025–028 grün.

### R3 — Adventures, Dreams, Gallery + CVF-6-Karten (Teil 1)

- **S13 · MYL-ADV Own Adventures + Lifecycle.** Status draft→…→archived (§19), Kauf reservieren→liefern→buchen, Lizenz-Reopen ohne Neubuchung. *REST:* `POST /adventures`, `/submit`, `/purchase`, `?ownership=own`. *Abnahme:* MYL 005, MYL 007, MYL 008, MYL 016.
- **S14 · MYL-ADV Drei-Wort-Name.** Muster Maschine-Problem-Handlung, genau drei normalisierte Begriffe, Synonyme/Übersetzungen im Index. *DB:* `ary_liw_myl_three_word_label`. *Abnahme:* MYL 017.
- **S15 · MYL-DREAMS.** Maschinen-Bilderbuch, privat; Drei-Wort-Titel, Notiz, Tags, Reihenfolge, Cover, Wunschstatus. *DB:* `ary_liw_myl_dream_item`. *REST:* `GET/POST /dreams`. *Abnahme:* MYL 014.
- **S16 · MYL-GALLERY.** Private Galerie: Upload/Import/Alben/Metadaten/Rechteprüfung/Archiv. *DB:* `ary_liw_myl_gallery_item`. *REST:* `GET/POST /gallery`. *Abnahme:* MYL 015 (privat), Upload-Prüfung SEC 04.
- **S17 · CVF-6CARD Migration.** Board 4→6 Karten, neue stabile moduleIds `my_liebherr`, `pocket_information`, **initial deaktiviert**; bestehende Viererflows verlustfrei. *Datei:* `src/Cvf/Schema.php`, `BoardSchema.php`, Board-UI. *Abnahme:* MYL 023, CVF „Schema Migration".
- **S18 · R3-Abnahme.** Adventure End-to-End (Erstellung→Prüfung→Kauf→Gutschrift-Vorstufe). *Exit R3:* MYL 005/007/008/014/015/016/017/023 grün.

### R4 — Qualität, Freigaben, Kontakte, Sharing (MYL-QUALITY, MYL-SHARE, MYL-CONTACTS)

- **S19 · MYL-QUALITY.** Meldungen/Sperren/Erstattung, Media-Pipeline (Scan/Quarantäne/Transkript), Notifications (§13). *REST:* `POST /adventures/{id}/report`, `POST /reviews/{id}/decision`. *Abnahme:* MYL 006, MYL 009, SEC 04.
- **S20 · MYL-SHARE.** Shared with Colleagues / World mit getrennten, nachvollziehbaren Grants (Empfänger/Ablauf/Kommentar/Download/Widerruf; World zusätzlich Review). *DB:* `ary_liw_myl_share_grant`. *REST:* `POST/DELETE /gallery/{id}/shares`. *Abnahme:* MYL 015 (drei Sichten).
- **S21 · MYL-CONTACTS.** Kontaktanfrage → Zustimmung → Contact Connection (§33), Service Exchange erzeugt genau eine Buchung; Verbindung beenden. *DB:* `ary_liw_myl_contact_request`, `ary_liw_myl_wallet_connection`, `ary_liw_myl_service_exchange`. *REST:* `POST /contacts/requests`, `/decision`, `POST /connections/{id}/services`. *Abnahme:* MYL 018, MYL 019, MYL 020, Sicherheitsgrenze §33.
- **S22 · R4-Abnahme.** Sicherheits-/Missbrauchstests (Rate Limits SEC 08, Upload SEC 04, Autorisierung SEC 01). *Exit R4:* MYL 006/009/018/019/020 grün.

### R5 — Integration, Pocket, Maschinen, CVF-6-Karten (Teil 2)

- **S23 · POCKET (6. Reiter).** Briefing/Alerts/Tasks/Saved/Machine/Adventure Pocket; regelbasiert + erklärbar; Pflichtquittierung getrennt protokolliert; Return Routes. *DB:* `ary_liw_pocket_item`. *REST:* `GET /pocket/v1/feed`, `POST /pocket/v1/items/{id}/ack`. *Abnahme:* MYL 021, MYL 022. *Flag:* `liw_pocket_enabled`.
- **S24 · MYL-MACHINES + CVF-6CARD (Teil 2) + R5-Abnahme.** My Machines mit Local-Intelligence-Bezug; First Entry + Return Route beider neuer CVF-Karten laufen vollständig in der Simulation; Direktlink-Schutz; Publish/Diff/Rollback deterministisch. *Abnahme:* MYL 013 (vollständig), CVF „Board Darstellung/Simulation/Runtime/Versionierung". *Exit R5:* kontextübergreifende Abnahme grün.

### R6 — Pilot

- Begrenzte Nutzergruppe, Monitoring, Support, Feedback; Pilotkennzahlen; keine kritischen Fehler. Erst nach Freigabe der Kategorie-A-Entscheidungen (Abschnitt 9).

---

## 6 Querschnittsanforderungen (in jeder Stufe zu erfüllen)

- **Sicherheit:** serverseitige Autorisierung je Aufruf (SEC 01), atomare/idempotente Buchungen (SEC 02), unveränderliches Ledger (SEC 03), Medienprüfung (SEC 04), Sitzungswiderruf (SEC 05), Re-Auth bei sensiblen Aktionen (SEC 06), lückenloses Audit (SEC 07), Rate Limits (SEC 08).
- **Barrierefreiheit:** WCAG 2.2 AA-Ziel, Tastatur/Fokus/Kontrast/Untertitel, verständliche Fehler (§22).
- **i18n:** keine fest eingebauten Texte/Formate; Zeitzonen korrekt (§22).
- **Performance:** Dashboard p95 < 2 s (warm), Wallet-Buchung p95 < 1 s (§22).
- **Beobachtbarkeit/Backup:** strukturierte Logs + Korrelations-ID ohne unnötige personenbezogene Inhalte; getestete Wiederherstellung; Ledger konsistent (§22).
- **Flags:** neue Fläche startet OFF; Aktivierung je Umgebung dokumentiert.

---

## 7 Abnahmematrix (Verweis)

Alle Kriterien werden in `docs/LIW_ABNAHME.md` je Stufe fortgeschrieben: **MYL 001–024** (§21/§39) + **MYL 025–028** (§41.7) + **SEC 01–08** (§20) + CVF-Folgearbeit-Kriterien (§36). Jede Stufe schließt erst, wenn die zugeordneten IDs in beiden Testsuiten grün sind.

## 8 Definition of Done je Stufe (nach §27)

1. Plan der Stufe vollständig umgesetzt, Modul ohne Core-Änderung, keine fest codierten Umgebungswerte.
2. Migrationen versioniert, wiederholbar, mit Testdaten geprüft (additiv/verlustfrei).
3. Alle REST-Routen mit Schema, Permission-Callback, Fehlerformat, Tests.
4. Wallet-Adapter bleibt einzige Buchungsschnittstelle; Idempotenz + Gegenbuchung nachgewiesen.
5. WP-freie Suite + Docker-Selftest grün; A11y/Perf-Stichprobe.
6. CHANGELOG + drei Bücher (LIW_TODO, LIW_PROGRAMMIERLOGBUCH, HandbookPage) + Logbuch aktualisiert.
7. Commit vorbereitet (kein Push ohne Freigabe); Permalink-/CPT-Hinweis falls einschlägig.

## 9 Offene Kategorie-A-Entscheidungen vor Produktivbetrieb (§14/§26/§41)

- Tokencharakter, Preisrecht, Verteilungsregel, Wartefrist, Arbeitgeberwechsel, Anonymität, Bewertung, KI-Funktionen (§26).
- **§41:** Höhe/Version der Zeit-Tokenregel; Zeitpunkt der Aktivierung von `liw_ptime_charge_live`; Geltungsbereich der Uhr; Datenschutzfreigabe, dass Plattformzeit **nicht** zur Personalbewertung genutzt wird.
- **Wallet (Core-Kategorie A, gehört ins kommende Wallet-Pflichtenheft):** Mehrwährungsfähigkeit der Plattform Health Wallet (echte Token-Währung neben EUR je Nutzer, eigener Token-Ledger-Zweig, Idempotenz + Reservieren→Bestätigen + Audit); `rule_version` auf Buchungen; Buckets ausstehend/gesperrt; vier-Augen-Korrektur; Migration/Ablösung von `Adventures\TokenAccount` auf den Token-Zweig der Plattform-Wallet (verlustfrei, idempotentes CLI-Skript, Terminal-Regel). **Bis zur Freigabe dieses Pflichtenhefts bleibt `liw_myl_wallet_live`/`liw_ptime_charge_live` = OFF.**
- Recht/Steuer/Betriebsrat/Exportkontrolle/Produkthaftung vor R6 dokumentiert und freigegeben.

---

*Nächster Schritt: Beantwortung des Startklärungsblocks (Abschnitt 4). Danach startet S1 ohne weitere Routinefragen; unterbrochen wird nur bei echten Stop-Blockern (§38).*
