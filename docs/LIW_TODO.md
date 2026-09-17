# Liebherr Interface Solutions — To-Dos

Offene bzw. für später geplante Punkte. Diese Liste erfindet nichts Neues, sondern fasst
Punkte zusammen, die im Projektverlauf bereits als „bewusst nicht Teil dieser Auslieferung"
oder als offene Rückfrage an Joseph White dokumentiert wurden (Quelle jeweils angegeben).
Sichtbar im Backend unter „Interface World → 📋 To-Dos". Wird bei jeder Aufgabe, die einen
Punkt hier abschließt oder ergänzt, gepflegt.

Stand: 18.09.2026 (0.1.0-alpha.12).

---

## Fachlich offen (Pflichtenheft)

- **Content Board (§19 – Redaktionsfunktionen).** In alpha.1 als „nicht Teil dieser
  Auslieferung" benannt; bislang nicht nachgeliefert. Betrifft redaktionelle Pflege der
  14 Landingpage-Abschnitte (`liw_section`, CPT-Freigabeworkflow `liw_approved`).
  Seit alpha.12 liegen für LP-07/LP-08 bereits fertige, anonymisierte Grafiken bereit
  (`assets/img/liw-data-model.svg`, `assets/img/liw-process-worlds.svg`) – warten auf
  ihre Platzierung durch das Content Board. Details/Gesamtkonzept:
  `docs/LIW_LANDINGPAGE_KONZEPT.md`.
  *Quelle: CHANGELOG.md alpha.1, alpha.12.*

- **Visuelles Gesamt-Layout/Wireframe der Landingpage.** Der inhaltliche Bauplan
  (LP-01…LP-14) steht im Pflichtenheft und ist seit alpha.12 in
  `docs/LIW_LANDINGPAGE_KONZEPT.md` mit Umsetzungsstand zusammengefasst; ein visuelles
  Wireframe für Reihenfolge/Bildsprache/Übergänge der Gesamtseite fehlt noch.
  *Quelle: docs/LIW_LANDINGPAGE_KONZEPT.md, 18.09.2026.*

- **Simulation Board – Status-Übergänge.** Welt validieren/verwerfen, Szenario als
  bestanden/fehlgeschlagen markieren. Bewusst zurückgestellt, bis eine echte
  Simulations-Engine angebunden ist (YAGNI) – dieselbe Begründung gilt für
  `InterfaceCatalogService::set_lifecycle_status()`, das ebenfalls noch keine UI hat.
  *Quelle: CHANGELOG.md alpha.4.*

- **World Connections Map – echte geografische Karte.** Aktuell Regionen-Grid (Liste
  gruppiert nach Freitext-Region), keine Koordinaten. Eine echte Karte erfordert eine
  Datenmodelländerung (latitude/longitude, Kategorie B) sowie eine Kartenbibliothek
  (Kategorie A – neue externe Abhängigkeit, braucht Freigabe). Entscheidung Joseph White
  18.09.2026: bewusst zurückgestellt, Datenpflege (alpha.5) ist davon unabhängig nutzbar.
  *Quelle: CHANGELOG.md alpha.8; AskUserQuestion-Antwort 18.09.2026 „Liste/Grid nach Region".*

## Architektur – offene Rückfrage an Joseph White

- **I18nSeo-Anbindung.** Aktuell eine eigenständige, schlanke Lösung (`CoreBridge\SeoBridge`),
  da der Core-Router fest auf andere CPTs verdrahtet ist. Ob das langfristig so bleibt oder
  der Core-Router erweitert wird, ist eine offene Kategorie-A-Entscheidung.
  *Quelle: CHANGELOG.md alpha.1, ADR-LIW-001.*

## Betrieb / Qualitätssicherung

- **Mehrsprachigkeits-Audit (DoD Punkt 9).** Für `liebherr-interface-world` als
  eigenständiges Plugin bislang nicht gegen das Core-Übersetzungssystem geprüft
  (`trx-scan`/`trx-audit` laufen bisher nur für Core-Module). Zu klären, ob/wie die
  Mehrsprachigkeits-Regel für dieses Ausnahme-Plugin greift.
  *Quelle: CLAUDE.md Abschnitt 5/DoD Punkt 9 – nicht auf dieses Plugin angewendet, seit alpha.1.*

---

**Bereits erledigt (zur Nachvollziehbarkeit, nicht mehr offen):**
- Media Board Paginierung und Onboarding Board Paginierung (beide seit alpha.7/alpha.6 als
  offen vermerkt) wurden mit alpha.10 umgesetzt.
- Gemeinsamer Docker-Praxistest aller Bereiche (`scripts/liw-selftest.php`) wurde mit
  alpha.11 ausgeliefert – Ausführung durch Joseph in der Docker-Dev-Umgebung steht noch
  aus (`docker exec araliya_wordpress php .../scripts/liw-selftest.php`).

S. `LIW_PROGRAMMIERLOGBUCH.md` für Details.
