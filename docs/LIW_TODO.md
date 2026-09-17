# Liebherr Interface Solutions — To-Dos

Offene bzw. für später geplante Punkte. Diese Liste erfindet nichts Neues, sondern fasst
Punkte zusammen, die im Projektverlauf bereits als „bewusst nicht Teil dieser Auslieferung"
oder als offene Rückfrage an Joseph White dokumentiert wurden (Quelle jeweils angegeben).
Sichtbar im Backend unter „Interface World → 📋 To-Dos". Wird bei jeder Aufgabe, die einen
Punkt hier abschließt oder ergänzt, gepflegt.

Stand: 18.09.2026 (0.1.0-alpha.10).

---

## Fachlich offen (Pflichtenheft)

- **Content Board (§19 – Redaktionsfunktionen).** In alpha.1 als „nicht Teil dieser
  Auslieferung" benannt; bislang nicht nachgeliefert. Betrifft redaktionelle Pflege der
  14 Landingpage-Abschnitte (`liw_section`, CPT-Freigabeworkflow `liw_approved`).
  *Quelle: CHANGELOG.md alpha.1.*

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

- **Gemeinsamer Docker-Praxistest aller Bereiche.** Bislang wurde jede Auslieferung nur
  über `php -l` + `tests/run-tests.php` (Syntax/Statuslogik ohne WP) geprüft; ein
  End-to-End-Test aller sechs Boards und beider Frontend-Shortcodes in der laufenden
  Docker-Dev-Umgebung (CLAUDE.md DoD Punkt 4 „tatsächlich geprüft, nicht nur müsste gehen")
  steht noch aus. Wurde am 18.09.2026 als Option angeboten, aber nicht gewählt (Feinschliff
  hatte Vorrang).
  *Quelle: Sitzungsverlauf 18.09.2026.*

- **Mehrsprachigkeits-Audit (DoD Punkt 9).** Für `liebherr-interface-world` als
  eigenständiges Plugin bislang nicht gegen das Core-Übersetzungssystem geprüft
  (`trx-scan`/`trx-audit` laufen bisher nur für Core-Module). Zu klären, ob/wie die
  Mehrsprachigkeits-Regel für dieses Ausnahme-Plugin greift.
  *Quelle: CLAUDE.md Abschnitt 5/DoD Punkt 9 – nicht auf dieses Plugin angewendet, seit alpha.1.*

---

**Bereits erledigt (zur Nachvollziehbarkeit, nicht mehr offen):**
Media Board Paginierung und Onboarding Board Paginierung (beide seit alpha.7/alpha.6 als
offen vermerkt) wurden mit alpha.10 umgesetzt – s. `LIW_PROGRAMMIERLOGBUCH.md`.
