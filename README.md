# Liebherr Interface Solutions – Interface World Connections

Administrierbare, mehrsprachige Landingpage für die weltweite technische Anbindung von
Liebherr-Händlern, Lieferanten und Kunden an zentrale Liebherr-Systeme sowie den Magic Cube
als Simulations-, Prüf- und Integrationsumgebung.

**Solution Provider:** GoHeal. **Technische Basis:** ARALIYA Platform Core (Laufzeit-Abhängigkeit).

## Voraussetzung

Das Plugin **benötigt** das aktive Plugin `araliya-platform-core` (Übersetzung, Rollen, Audit).
Ohne Core bleibt es inaktiv (Admin-Hinweis statt Fatal Error).

## Architektur

Siehe `docs/ADR-LIW-001_Plugin_Struktur_und_CoreBridge.md` für die vollständige Begründung,
welche Core-Services wiederverwendet werden (`src/CoreBridge/`) und welche bewusst nicht
(Health-/Standort-spezifische Contracts).

```
src/
├── Bootstrap.php          Verdrahtung (nur bei aktivem Core geladen)
├── CoreBridge/             Einziger Kopplungspunkt zu araliya-platform-core
├── CPT/                    liw_section (Landingpage-Abschnitte)
├── Interfaces/              Schnittstellenkatalog (liw_interface)
├── Simulation/             Magic Cube: Simulationswelten + Testszenarien
├── Connection/             World Connections Map
├── Consent/                Eigenständiges Einwilligungsprotokoll (begründete Ausnahme)
└── Admin/                  Admin-Boards + Handbuch
```

## Status

Grundgerüst + CoreBridge (Phase 3 der Machbarkeitsprüfung). Siehe `CHANGELOG.md` für
Liefergegenstand und offene Punkte.

## Tests

```
php tests/run-tests.php
```
