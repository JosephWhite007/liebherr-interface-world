# Liebherr Interface Solutions — Changelog

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
