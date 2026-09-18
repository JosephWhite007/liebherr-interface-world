# IMPLEMENTATION_NOTES – Liebherr Intelligence World

Pflichtenheft-2 §22.8 verlangt die Dokumentation aller Abweichungen und offenen Entscheidungen.
Dieses Dokument wird je Etappe fortgeschrieben (neueste zuerst).

## Grundentscheidungen (Joseph White, 19.09.2026)

- **Hierarchie:** Intelligence World ist eine **eigenständige Landingpage neben** Local Intelligence
  (eigener Menüpunkt), nicht deren Oberseite. Gebaut im **selben Plugin** `liebherr-interface-world`
  als neues Modul-Namespace `IntelligenceWorld\` (keine Duplizierung der Bausteine).
- **Startetappe:** **Fundament zuerst** – Datenmodell, Ereignisschema und Session-Meter serverseitig,
  mit Tests; UI/Blue-Planet/Eintrittsschleuse folgen in späteren Etappen.
- **Reihenfolge:** Intelligence World vor dem Download-/Newsletter-Plugin.

## Prototyp-Grenzen (§21) – bewusst NICHT in dieser Etappe

- kein echtes Rechnungswesen/keine Zahlungsabwicklung (Beträge werden nur berechnet/geführt, nicht eingezogen);
- keine produktive Datenanbindung, keine echten Produktionsdaten;
- keine finale CI-/AGB-Freigabe. Produktivschaltung erst nach Freigabe (Release-Gate, vgl. `liw_public_release`).

## Etappe „Fundament" (0.1.0-alpha.47)

**Umgesetzt (serverseitig, `src/IntelligenceWorld/`):**

- `Schema` – Tabellen `liw_iw_session` (§12 Session) und `liw_iw_event` (append-only Ereignis-Ledger,
  §10.10/§11). COMMENT ohne Klammern (dbDelta-Falle). Registriert in `create_tables()`.
- `EventTypes` – die 23 Ereignistypen aus §11 als Konstanten + Validierung.
- `Money` – Geld ausschließlich als **Integer-Minor-Units** (§9), Integer-Arithmetik, Anzeigeformat de/en.
- `PriceRule` – 10 Tarifarten (§13.2), Kostenrechnung in Minor-Units, Gültigkeitsfenster +
  `select_active()` (keine rückwirkende Preisänderung, §13.3).
- `SessionMeter` – reine Berechnung abrechenbarer aktiver Sekunden aus Heartbeats + Inaktivitäts-Timeout
  (§13.1); **konservativ**: Lücken größer als der Timeout zählen gar nicht (nie zu viel berechnen, §5.3/§8).
- `EventLog` – Anhängen ins Ledger mit **Hash-Kette** (SHA-256 über prev_hash + kanonischen Kern) und
  **Idempotenz** via `dedupe_key` (Schutz gegen Doppelbuchung/Replay, §16); `verify_chain()` erkennt
  nachträgliche Manipulation. Reine `canonical()`/`hash()` sind unit-getestet.
- `SessionService` – DB-Lebenszyklus Start/Heartbeat/Pause/Resume/Ende + `sweep_timeouts()`
  (Inaktivitäts-Kehrlauf, §5.3); aktive Dauer über SessionMeter, Segmente durch Pause/Ende getrennt.
  Zeit in UTC; Timeout administrierbar (Option `liw_iw_timeout`, Standard 120 s).

**Grundsätze umgesetzt (§9):** serverseitige Abrechnungswahrheit, Trennung Präsentation/Messung/Abrechnung
(nur Messung/Abrechnung in dieser Etappe), UTC-Zeitstempel, keine Floats für Geld, idempotente Buchungen.

**Tests:** WP-frei (`tests/run-tests.php`) für Money/PriceRule/SessionMeter/EventLog(hash)/EventTypes;
Docker (`scripts/liw-selftest.php` Block [8c]) für DB-Lebenszyklus, aktive-Sekunden-Berechnung,
Hash-Ketten-Prüfung, Idempotenz und Manipulationserkennung.

## Etappe „Eintritt & Welt" (0.1.0-alpha.48)

**Umgesetzt:** Blue-Planet-Landing (§4.1, dekoratives Planet-SVG + Orbits/Knoten, reduced-motion-fest),
Eintrittsschleuse/Access Gate (§4.2: Code + Prototyp-/Preishinweis + Nutzungsbedingungen 5.1–5.8 + zwei
Pflicht-Einwilligungen + Bestätigung erst bei Code & beiden Zustimmungen), Sitzungs-/Kostenleiste (§7:
Zeit, Basiskosten, Budget-Balken mit Warnstufen 50/80/100 %, „Sitzung beenden"). Serverseitig über REST
`liw-iw/v1` (start/heartbeat/end/status), Nonce-geschützt; Abrechnung/Timing serverseitig, Client nur Anzeige.

**Entschieden (Prototyp-Defaults, administrierbar über Option `liw_iw_world`):**
- Access-Code = **Demo-Code `LIEBHERR-DEMO`** (kein echtes Login/keine echte Authentifizierung, §21).
- Demo-Tarife: **2,50 €/min** Basiszeit, **50 €** Sitzungsbudget (nur Warnschwellen), **5 MB** Speicher-Grundbudget.
- Terms: Wiederverwendung der vollständigen 5.1–5.8 aus `LocalIntelligenceContent::default_terms()`.

**Noch nicht in dieser Etappe:** Modul-/Compute-Metering-Kosten in der Leiste (nur Basiszeit), Storage-
Manager-Interaktion, echte Warn-Dialoge mit Hard-/Soft-Limit-Stop (aktuell Anzeige/Warntext).

## Offen / nächste Etappen (Vorschlag)

1. ~~**Eintritt & Welt:** Blue-Planet-Landing (§4.1), Access Gate (§4.2), Sitzungsticker/Kostenanzeige (§7)~~
   — **erledigt in alpha.48.**
2. **Navigation & Hotels:** administrierbare 14-Punkte-Taxonomie inkl. Untermenüs + Master-Linkmodell (§19.1),
   Hotels-Welt (6 Knoten, §3/§6.3).
3. **Simulation & Protokoll:** Simulation Builder (§6.4), Compute-Metering (Mock), Usage Ledger →
   Nutzungs-/Kostenprotokoll (Bildschirm/PDF/JSON, §5.5/§8).
4. **Pricing/Storage/Admin:** Pricing Engine (Kontingente/Rabatte/Währungen), Storage Manager (§14),
   Admin-Cockpit (§19), Rollen (§15), Audit/Security-Layer (§16).

## Offene Entscheidungen (an Joseph)

- Blue-Planet-Visual: eigenständige 3D-/SVG-Umsetzung vs. Wiederverwendung/Erweiterung des vorhandenen
  Hero-Visuals; Umfang der Animation.
- ~~Access-Gate-Code~~ (alpha.48: fester Demo-Code `LIEBHERR-DEMO`; per-Nutzer-Code/Login-Anbindung offen für Produktiv).
- ~~Tarifmodell Prototyp~~ (alpha.48: Demo 2,50 €/min + 50 € Budget + 5 MB; Pricing Engine mit Kontingenten später).
- Modul-/Compute-Gebühren in der Sitzungsleiste (aktuell nur Basiszeit) — nächste Etappen.
- Mehrsprachigkeit der neuen Inhalte: gleicher Workflow wie Local Intelligence (offen, s. LIW_TODO).
