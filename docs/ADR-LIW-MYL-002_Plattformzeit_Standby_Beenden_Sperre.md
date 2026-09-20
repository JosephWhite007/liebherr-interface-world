# ADR-LIW-MYL-002 — Plattformzeit: Standby / Beenden, plattformweite Sperre und Token-Abrechnung

**Status:** Entwurf zur Freigabe (Festlegungen 1–4 durch Joseph entschieden, 20.09.2026) · **Stand:** 20.09.2026 · **Plugin:** `liebherr-interface-world` (`0.1.0-alpha.138`)
**Grundlage:** `Pflichtenheft/Programmierpflichtenheft_My_Liebherr.md` **§41** (Plattformzeit/Session-Uhr/Token-Schachuhr), neu **§41.8** (Standby/Beenden/Sperre)
**Bezug:** ADR-LIW-MYL-001 (Programm-Workflow, Stufe S10/S11) · ADR-LIW-001 (Struktur/CoreBridge) · ADR-LIW-CVF-002 (CAPDB-Board) · `Programmierpflichtenheft_Wallet_Mehrwaehrung_Token.md` (Core-Kategorie A)
**Deploy-Regel (verbindlich):** lokal entwickeln + testen → committen **ohne Push** → Joseph gibt frei → dann Push. Jede Stufe = eigener Commit + Tests + CHANGELOG + drei Bücher + Logbuch. **Dieses Dokument ist Konzept — kein Code.**

---

## 1 Zweck und Abgrenzung

Beim Betätigen des **Beenden**-Knopfes der Session-Uhr (§41) soll (a) **genau ein Abrechnungsprotokoll** entstehen und (b) die **Gesamtfunktionalität der Plattform sofort gesperrt** werden — bis auf die obere „Liebherr World"-Leiste (Navigation) und den Sprachumschalter, die immer sichtbar/bedienbar bleiben. Zusätzlich wird die Session-Uhr um eine zweite Aktion **Standby** ergänzt (Raum verlassen ohne Abrechnung, Rückkehr über Rechenlogik).

Dieses ADR entscheidet **das Wie** (Sperrmechanik, zwei Aktionen, Report-Popup, Wiedereintritt, Auto-Standby). Die fachlichen Rahmenwerte (serverautoritative Zeit, versionierte Token-Regel, Idempotenz, Wallet-Naht, Datenmodell, Events) stehen bereits in §41 und werden **nicht** dupliziert, sondern referenziert. Die echte Token-**Buchung** (Mehrwährung im Wallet) bleibt **Core-Kategorie A** (eigenes Wallet-Pflichtenheft) — hier nur als Naht.

---

## 2 Ausgangslage — was schon existiert (Wiederverwendung, keine Redundanz)

| Baustein | Ort | Rolle in diesem ADR |
|---|---|---|
| Session mit Start/Heartbeat/Stop/Status | `src/PlatformTime/Rest.php`, `SessionRepository.php`, `SessionClock.php` | Zustand + Zeitmessung; Basis für `standby`/`end` |
| Abrechnungssatz (append-only, idempotent) | `src/PlatformTime/ChargeService.php`, `Schema::charge_table()` | **Das Protokoll** — existiert bereits (`token_amount`, `rule_version`, `status`, `idempotency_key = ptime-<session>`, `wallet_ref`) |
| Versionierte Zeit-Tokenregel | `src/PlatformTime/TokenRule.php` | Token-Menge + `rule_version` im Protokoll |
| Wallet-Buchungsnaht | Hook `liw_ptime_charge` + Flag `liw_ptime_charge_live` + `CoreBridge\WalletBridge` | Zubuchung aufs Health Wallet (Reservieren/Bestätigen §41.1) |
| Schwebendes Uhr-Widget | `src/PlatformTime/ClockWidget.php`, `assets/*/liw-ptime-clock.*` | Trägt künftig **zwei** Knöpfe (Standby/Beenden) + Anleitungs-Knopf |
| Sperr-Overlay mit sichtbarer Kopfleiste | `assets/css/liebherr-frontend.css` (`html.liw-intro-lock` + `.liw-switcher`/`.liw-header__lang`), `assets/js/liebherr-frontend.js` (Intro) | **Sperr-Optik**: Vollbild-Overlay, Kopfleiste + Sprachumschalter schweben darüber (alpha.136/137) |
| Server-autoritative Rechenlogik | `src/Cvf/ChallengeService.php`, `src/Emergency/EmergencyChallenge.php`, `src/Frontend/IntroOverlay.php` | **Standby-Wiedereintritt** — vorhandene Challenge, nicht neu bauen |

**Kernaussage:** Kein neuer Zeit-/Abrechnungs-/Challenge-Motor. Neu sind nur: **Sitzungs-Zustandsfeld-Nutzung für die Sperre**, **zwei Aktionen**, **Report-Popup**, **Server-Gate über alle Welten**, **Auto-Standby**.

---

## 3 Entscheidungen (Festlegungen Joseph, 20.09.2026)

1. **Token während Standby: einfrieren.** Im Zustand `paused` (Standby) akkumuliert die Uhr **keine** Zeit/Token (`active_seconds` eingefroren, Zuwachs zählt auf `paused_seconds`). Kein Standby-Tarif.
2. **Guthaben reicht bei Beenden nicht: streng.** Schlägt die Wallet-Buchung mangels Deckung fehl, bleibt die Plattform **gesperrt** (`stopped`, nicht `settled`); der Nutzer sieht „Wallet aufladen" (Deep-Link zum Wallet-Planer) und kann erst nach erfolgreicher Buchung weiter. Kein kulantes Durchlassen.
3. **Sperrumfang: alle Welten.** Die Sperre wirkt **plattformweit** — Intelligence World, Local Intelligence, Interface Solutions, Adventures, My Liebherr, Pocket. Ausgenommen sind ausschließlich die „Liebherr World"-Leiste und der Sprachumschalter.
4. **Auto-Standby: ja.** Läuft der bestehende Inaktivitäts-Timeout ab (statt still zu pausieren), geht der Abschnitt **automatisch** in `paused`/Standby und die Sperre greift; Rückkehr wie bei manuellem Standby über die Rechenlogik.

---

## 4 Zustandsautomat (Erweiterung von §41.5)

§41.5 definiert: `started → running ⇄ paused → stopped → settled; alternativ voided`. Dieses ADR bindet **Sperre und UX** an die Zustände:

| Zustand | Uhr | Plattform | Auslöser hinein | Ausgang |
|---|---|---|---|---|
| `running` | läuft, Token akkumulieren | **frei** | Start/Resume | Standby, Beenden, Timeout |
| `paused` (**Standby**) | **eingefroren** (Festlegung 1) | **gesperrt** (alle Welten) | Knopf Standby **oder** Auto-Standby bei Timeout (Festlegung 4) | Rechenlogik gelöst → `running` |
| `stopped` (**Beenden, Abrechnung offen**) | eingefroren | **gesperrt** | Knopf Beenden → `ChargeService::record()` (genau ein Satz, §41.1/MYL 027) | erfolgreiche Wallet-Buchung → `settled` |
| `settled` | beendet | **frei**; nächste Interaktion startet frische Sitzung `running` | Wallet-Buchung bestätigt | — |
| `voided` | beendet | frei | Storno/Gegensatz (Governance) | — |

Jeder Übergang: Berechtigung + Vorbedingung + atomare Änderung + Audit-Event (`platformtime.paused/resumed/stopped/settled`, §41.6). Nicht definierte Übergänge werden serverseitig abgelehnt (§19).

**Wichtig (Festlegung 2):** `stopped → settled` **nur** bei erfolgreicher Wallet-Buchung. Deckungsfehler ⇒ Verbleib in `stopped` ⇒ Plattform bleibt gesperrt.

---

## 5 Sperrmechanik: „Popup drüber" **oder** „Plugin sperrt"?

**Entscheidung: Hybrid — der Server ist die Wahrheit, das Overlay ist die Anzeige.** Ein reines Client-Popup ist per DevTools entfernbar und für eine abrechnungsrelevante Sperre zu schwach. Deshalb:

### 5.1 Server-Gate (die echte Sperre)
Ein zentraler Guard prüft den Sitzungszustand des angemeldeten Nutzers. Bei `state ∈ {paused, stopped}`:
- **REST:** alle gated Endpunkte (My Liebherr, Buchung, Dreams, Gallery, Pocket-ack, CVF-Runtime, Adventures/GetHelp usw.) antworten mit **`423 Locked`** + maschinenlesbarem Grund (`reason: standby|settlement`), statt Nutzdaten zu liefern. Ausgenommen: `platform-time/*` (Status/Resume/Settle) und der Challenge-Endpunkt.
- **Seiten (front + ggf. wp-admin-Front):** gated Templates rendern **nur** Kopfleiste + Sprachumschalter + Overlay; der eigentliche Weltinhalt wird nicht ausgegeben (Server-seitig, nicht nur per CSS versteckt).
- **Umfang (Festlegung 3):** wirkt in allen vier Welten + My Liebherr/Pocket. Umsetzung als **ein** plattformweiter Hook (`template_redirect`/Content-Guard + `rest_pre_dispatch`), damit die Regel an **einer** Stelle lebt (Grundregel „keine Redundanzen").

### 5.2 Overlay (die Anzeige)
Wiederverwendung des `liw-intro-lock`-Musters (alpha.136/137): Vollbild-Overlay mit hohem z-index; die `.liw-switcher`-Leiste und `.liw-header__lang` schweben darüber und bleiben klickbar. Inhalt des Overlays je Zustand:
- `paused` → **Standby-Maske** mit Rechenaufgabe (`ChallengeService`).
- `stopped` → **Report-Popup** (§6).

Das Overlay ist damit die UI der Server-Sperre, nicht die Sperre selbst.

---

## 6 Beenden → Abrechnungsprotokoll + Report-Popup

1. Knopf **Beenden** ruft `POST …/platform-time/end` (löst die vorhandene Stop-Logik aus: `active_seconds` einfrieren, `state=stopped`, `ChargeService::record()` erzeugt/liest **idempotent** den Protokollsatz).
2. Antwort trägt die **Report-Daten**: verbrauchte Zeit (`HH:MM:SS`), `token_amount`, `rule_version`, aktueller Wallet-Saldo (über `WalletBridge`).
3. Overlay zeigt den Report: *„Du hast **X Token** in **HH:MM:SS** verbraucht (Tarif `rule_version`)."* + Knopf **„Auf Wallet buchen & weiter"**.
4. Bestätigung ⇒ `POST …/platform-time/settle` ⇒ `do_action('liw_ptime_charge', …)` bucht über die Wallet-Naht (Bestätigen des beim Eintritt Reservierten, §41.1):
   - **Erfolg:** `status=settled`, `wallet_ref`/`ledger_tx` gesetzt, `state=settled`, Overlay verschwindet, Sperre fällt, nächste Interaktion startet frische Sitzung.
   - **Deckung fehlt (Festlegung 2):** Buchung wird abgelehnt, `state` bleibt `stopped`, Report bleibt mit Hinweis **„Wallet aufladen"** (Deep-Link `admin.php?page=araliya-lav-wallet-planner` bzw. Front-Wallet), Plattform bleibt gesperrt.
   - **Naht deaktiviert (`liw_ptime_charge_live` AUS, Standard):** Satz bleibt `pending`, revisionssicher protokolliert (§41.1/MYL 028). Für die Sperre gilt in diesem Übergangsbetrieb: Report bestätigen entsperrt (kein echter Saldo vorhanden) — die strenge Deckungsprüfung greift erst mit scharfer Naht.

Das Protokoll ist der bestehende `platform_time_charge`-Satz (append-only, Korrektur nur als Gegensatz — §41.3).

---

## 7 Standby → Sperre + Rechenlogik-Wiedereintritt

1. Knopf **Standby** ruft `POST …/platform-time/standby`: `active_seconds` einfrieren (Festlegung 1), Zuwachs auf `paused_seconds`, `state=paused`, **kein** Abrechnungssatz, Event `platformtime.paused`.
2. Server-Gate sperrt sofort (alle Welten). Overlay zeigt die **Rechenaufgabe** aus `ChallengeService` (server-autoritativ, Aufgabe nicht im gecachten HTML — Muster wie Intro/Emergency).
3. Richtig gelöst ⇒ `POST …/platform-time/resume` prüft die Challenge serverseitig ⇒ `state=running`, Event `platformtime.resumed`, Sperre fällt, Uhr läuft weiter.
4. **Auto-Standby (Festlegung 4):** Der bestehende Inaktivitäts-Timeout überführt den Abschnitt automatisch nach `paused` (statt still). Beim nächsten Aufruf sieht der Nutzer dieselbe Standby-Maske.

---

## 8 Plattformregeln, die mitgezogen werden

- **„Tool braucht Anweisung" (verbindlich):** Standby- und Report-Overlay tragen einen erreichbaren **Anleitungs-Knopf** (Ablauf + Reihenfolge: *Standby = Pause, Rückkehr über Rechenaufgabe, kein Abzug; Beenden = Abrechnung, Buchung aufs Wallet, dann frei*).
- **„Keine Redundanzen":** Rechenlogik nur aus `ChallengeService`; Sperr-Overlay nur aus `liw-intro-lock`; Abrechnung nur aus `ChargeService`; Sperre an **einer** Guard-Stelle.
- **Terminal-Regel:** Zustands-/Feldergänzungen ausschließlich über `Schema`/`maybe_upgrade_database`; keine Direktschreibzugriffe; Buchung idempotent.
- **Deploy-Regel:** Flags default AUS; committen ohne Push.
- **Governance (§41.1):** keine Personenüberwachung/kein Ranking über die Plattformzeit.

---

## 9 Umsetzung in Etappen (nach Freigabe)

| Stufe | Inhalt | Wallet nötig? | Abnahme |
|---|---|---|---|
| **P1 ✅ (alpha.139)** | Zustandsnutzung für Sperre; plattformweites Server-Gate (gated REST `423`, alle Welten) + Frontend-Overlay; Standby-Overlay mit `ChallengeService`; `standby`/`resume`/`challenge`-Routen; Auto-Standby am Timeout | nein | Sperre greift überall außer Leiste+Sprache; Rückkehr nur per gelöster Aufgabe; Token in Standby eingefroren |
| **P2 ✅ (alpha.140)** | `end`/`settle`/`report`-Routen + Report-Overlay; Protokoll finalisieren (Status `ending`→`settled`); Anleitung im Overlay; Reload-Schleife behoben | nein | Beenden erzeugt genau einen Satz (MYL 027); Report zeigt Zeit/Token/Tarif; Naht AUS ⇒ `pending` (MYL 028) |
| **P3** | `settle`-Route + Wallet-Buchung scharf (`liw_ptime_charge_live`), strenge Deckungsprüfung, „Wallet aufladen"-Weg | **ja** (Core-Kategorie A) | `settled` nur bei erfolgreicher Buchung; Deckungsfehler hält Sperre |

P3 hängt am **Wallet-Mehrwährungs-Pflichtenheft** (eigene Freigabe/Startklärung).

---

## 10 Neue/berührte Bausteine (Ausblick, kein Code)

- **Neu:** `PlatformTime\LockGuard` (plattformweites Gate, REST + Content), `PlatformTime\LockOverlay` (Standby- + Report-Ansicht auf `liw-intro-lock`-Basis), REST `standby`/`resume`/`end`/`settle`.
- **Erweitert:** `SessionRepository` (Übergänge paused/resume/settle + Auto-Standby), `ClockWidget` (zwei Knöpfe + Anleitung), `ChargeService` (settle/Deckungsprüfung an der Naht), `Flags` (ggf. `liw_ptime_lock_enabled`).
- **Wiederverwendet unverändert:** `ChallengeService`, `TokenRule`, `WalletBridge`, `liw-intro-lock`-CSS/JS.

---

## 11 Offene Punkte für die Feinspezifikation (P2/P3)

- Genauer Wortlaut/Übersetzung (DE/EN/PL) von Report, Standby-Maske und Anleitung.
- Verhalten bei parallelen Tabs (eine Sitzung, mehrere Fenster) — Gate ist serverseitig, Overlay muss auf `status` pollen.
- „Wallet aufladen"-Zielseite Front vs. wp-admin je Rolle.
- Ob `voided`/Storno hier schon eine UI braucht oder rein Governance/Backoffice bleibt.
