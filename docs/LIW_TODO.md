# Liebherr Interface Solutions — To-Dos

Offene bzw. für später geplante Punkte. Diese Liste erfindet nichts Neues, sondern fasst
Punkte zusammen, die im Projektverlauf bereits als „bewusst nicht Teil dieser Auslieferung"
oder als offene Rückfrage an Joseph White dokumentiert wurden (Quelle jeweils angegeben).
Sichtbar im Backend unter „Interface World → 📋 To-Dos". Wird bei jeder Aufgabe, die einen
Punkt hier abschließt oder ergänzt, gepflegt.

Stand: 18.09.2026 (0.1.0-alpha.23).

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
  Die 14 `liw_section`-Entwürfe sind seit alpha.17 per Knopf im Content Board anlegbar (mit
  Pflichtenheft-Vorgabe und eingebetteten Bausteinen); offen bleibt die **redaktionelle
  Ausformulierung** der Texte und Bildsprache durch die Redaktion. Details/Gesamtkonzept:
  `docs/LIW_LANDINGPAGE_KONZEPT.md`.
  *Quelle: CHANGELOG.md alpha.1, alpha.12, alpha.13, alpha.15.*

- **Kontaktanfragen – Folgepunkte (§22/§24).** Seit alpha.19 gebaut; offen bleiben:
  CRM-/Empfängerdefinition durch die Projektleitung (Pflichtenheft §31 – bis dahin Mail an
  WP-Admin-Adresse, Filter `liw_contact_recipients`), Export personenbezogener Anfragen (§24
  „Export- und Löschprozesse vorbereiten" – Löschen ist umgesetzt, Export nicht), fachliche
  Wertelisten für Land/Region und Projektinteresse (ANNAHME-LIW-8/-9, aktuell Filter
  `liw_contact_regions`/`liw_contact_interests`), sprachabhängige Datenschutztext-Versionierung
  (§24, hängt an der I18nSeo-Frage).
  *Quelle: CHANGELOG.md alpha.19; Pflichtenheft §22/§24/§31.*

- **Landingpage-Feinheiten nach dem ersten Gerüst (alpha.18).** Sprachumschaltung/SEO-Metadaten
  der Trägerseite (hängt an der offenen I18nSeo-Frage), Hero-Bildmotiv (freigegebenes
  Liebherr-Motiv, redaktionell), aktive Hervorhebung des sichtbaren Abschnitts in der Sprungleiste
  (bräuchte ein eigenes, enqueuetes Skript – YAGNI bis Bedarf). Ankernavigation selbst: alpha.23.
  *Quelle: CHANGELOG.md alpha.18, alpha.23.*

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

- **Geschützter Partnerbereich – Folgepunkte.** Stufe 1 (Rolle + Konto, alpha.21) und Stufe 2
  (Dokumentenbereich, alpha.22) sind umgesetzt. Offen: (1) Entscheidung Liebherr, welche Fassung
  der Prozessdokumentation an Partner geht (Empfehlung: ohne Endpunktnamen) – der Bereich ist
  inhaltsneutral, der Upload liegt bei der Redaktion; (2) Konten für alle freigegebenen Händler
  automatisch statt per Knopf (ANNAHME-LIW-11); (3) Dokumente je Partner/Region statt für alle
  (ANNAHME-LIW-12, additiv per Join-Tabelle); (4) Migrationspfad zu Core-Option B
  (`Modules\Documents` um `owner_type` verallgemeinern), falls der Core Partnerdokumente braucht;
  (5) Login-Seite im Design System statt WP-Standard-Login (aktuell `wp_login_url()`).
  *Quelle: docs/LOGBUCH_TECHNIK.md alpha.21/alpha.22.*

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
- Einbettung der LP-07/LP-08-Grafiken (Shortcode `[liw_graphic]`) mit alpha.15 umgesetzt.
- Bauplan LP-01…LP-14 + Anlage der Standard-Abschnitte per Knopf (alpha.17) umgesetzt.
- Zusammengesetzte Landingpage `[liw_landingpage]` (alpha.18) umgesetzt.
- LP-13 Kontaktformular + Contact Board (alpha.19) umgesetzt – Folgepunkte s. oben.
- Partnerbereich Stufe 1: Rolle `liw_partner` + Kontoanlage im Onboarding Board (alpha.21).
- Ankernavigation/Sprungleiste der Landingpage (alpha.23).
- Partnerbereich Stufe 2: geschützte Partnerdokumente – Board, Service, `[liw_partner_documents]` (alpha.22).
- Anonymisierte Grafiken LP-03/LP-04/LP-12 (alpha.20) geliefert – damit haben acht von 14
  Abschnitten einen gebauten Baustein; LP-01/02/05/09/10/14 sind reine Redaktion/Bildsprache.
- Hinweis an den Core (Klammern in Tabellen-COMMENTs, Befund alpha.16): von Joseph freigegeben und
  im Core umgesetzt – alpha.716 (41 Tabellen bereinigt) und alpha.717 (statischer Test
  `tests/Schema/run-tests.php`, Lauf 2/2 grün).

S. `LIW_PROGRAMMIERLOGBUCH.md` für Details.
