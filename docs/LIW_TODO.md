# Liebherr Interface Solutions — To-Dos

Offene bzw. für später geplante Punkte. Diese Liste erfindet nichts Neues, sondern fasst
Punkte zusammen, die im Projektverlauf bereits als „bewusst nicht Teil dieser Auslieferung"
oder als offene Rückfrage an Joseph White dokumentiert wurden (Quelle jeweils angegeben).
Sichtbar im Backend unter „Interface World → 📋 To-Dos". Wird bei jeder Aufgabe, die einen
Punkt hier abschließt oder ergänzt, gepflegt.

Stand: 18.09.2026 (0.1.0-alpha.14).

---

## Fachlich offen (Pflichtenheft)

- **Content Board (§19) – über das Grundgerüst hinaus.** Seit alpha.13 gibt es Übersicht,
  Freigabeworkflow (Entwurf → Prüfung → freigegeben → veröffentlicht) und native
  Editor-/Revisions-Anbindung für `liw_section`. Bewusst noch nicht Teil der Auslieferung
  (Entscheidung Joseph White 18.09.2026: „Grundgerüst zuerst"):
  - Drag-and-Drop-Reihenfolge (aktuell: numerisches „Reihenfolge"-Feld im Editor, ANNAHME-LIW-6)
  - Zeitsteuerte Veröffentlichung über den nativen `future`-Status hinaus
  - Vorschau je Sprache, Gerät und Veröffentlichungsstatus
  - CTA-Ziele intern auswählen statt URLs manuell einzutragen
  - Medien-Picker auf freigegebene Bibliothek beschränken (CI-005) – bei Recherche
    festgestellt, dass WPs `ajax_query_attachments_args`/`post_id`-Kontext dafür nicht
    zuverlässig genug ist, um es ungeprüft auszuliefern; braucht eigene Prüfung in Docker
  - Pflichtfeldprüfung und Warnung bei fehlenden Übersetzungen/Alt-Texten
  Für LP-07/LP-08 liegen bereits fertige, anonymisierte Grafiken bereit
  (`assets/img/liw-data-model.svg`, `assets/img/liw-process-worlds.svg`) – warten auf ihre
  Platzierung in den jetzt anlegbaren `liw_section`-Beiträgen. Details/Gesamtkonzept:
  `docs/LIW_LANDINGPAGE_KONZEPT.md`.
  *Quelle: CHANGELOG.md alpha.1, alpha.12, alpha.13.*

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
  alpha.11 ausgeliefert und am 18.09.2026 von Joseph in der Docker-Dev-Umgebung ausgeführt.
  Erster Lauf: 56/59 (zwei Befunde, behoben in alpha.14); zweiter Lauf: 59/59, kein
  Audit-Fehler mehr.
- Content Board Grundgerüst (Übersicht, Freigabeworkflow, native Editor-/Revisions-
  Anbindung) mit alpha.13 umgesetzt – Restpunkte s. oben.

S. `LIW_PROGRAMMIERLOGBUCH.md` für Details.
