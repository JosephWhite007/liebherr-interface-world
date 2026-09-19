# ADR-LIW-ADMIN-001 — Backoffice-Menü: Aufteilung in Frontend & Backoffice (+ Administration Plattform)

**Auftrag:** Joseph White, 19.09.2026 — „Interface World in zwei Bereiche aufteilen: **Frontend** (die vier
Seitenstrukturen Intelligence World, Local Intelligence, Interface Solutions, Adventures) und **Backoffice**;
im Backoffice zusätzlich ein Bereich **Administration Plattform**."
**Status:** Analyse / Vorschlag zur Freigabe — **kein Code umgesetzt** (Menü-Umbau erst nach Freigabe).
**Betrifft:** `src/Admin/AdminMenu.php` (nur Registrierungs-/Gruppierungslogik; Seiten-Klassen, Slugs,
Capabilities und Funktionen bleiben unverändert).

---

## 1 Ausgangslage

Heute gibt es **einen** Top-Level-Menüpunkt „Interface World" mit ~20 flach gelisteten Unterpunkten, die
drei sehr unterschiedliche Zwecke mischen: öffentliche Seiten-Direktlinks, Pflege-Boards und
Plattform-Administration. Das ist mit wachsendem Funktionsumfang unübersichtlich.

## 2 Zielstruktur (drei logische Bereiche)

| Bereich | Zweck | Inhalt |
|---|---|---|
| **Frontend** | Die vier begehbaren, öffentlichen Welten (Direktlinks zur Live-Seite) | 🪐 Intelligence World · 🌍 Local Intelligence · 🌐 Interface Solutions · 📸 Adventures |
| **Backoffice** | Inhalte, Daten und Beiträge pflegen | Content Board · 🪐 Intelligence World – Pflege · 🧠 Local Intelligence – Inhalte · 🗺 Adventures – Board · Simulation Board · Interface Board · 🌐 World Connections · 🎨 Brand Board · 🧭 Header Board · 🧩 Components Board · 🌐 Language Board · 🖼 Media Board · Onboarding · Kontaktanfragen · Partnerdokumente |
| **Backoffice ▸ Administration Plattform** | Plattform-Steuerung, Workflows, Nachvollziehbarkeit | 🧭 Customer View Flow · 🧭 CVF Board · 🛡 Audit Board · 🧾 Programmierlogbuch · 📋 To-Dos · 📖 Handbuch |

## 3 Vollständiges Mapping der heutigen Punkte

| Heutiger Menüpunkt | Neuer Bereich |
|---|---|
| 🪐 Intelligence World (Landingpage-Direktlink) | **Frontend** |
| 🌍 Local Intelligence (Hauptseite-Direktlink) | **Frontend** |
| 🌐 Interface Solutions (Frontpage-Direktlink) | **Frontend** |
| 📸 Adventures (Insel-Direktlink) | **Frontend** |
| Interface Board | Backoffice |
| Content Board | Backoffice |
| 🧠 Local Intelligence – Inhalte | Backoffice |
| Simulation Board | Backoffice |
| 🗺 Adventures – Board & Registrierung | Backoffice |
| 🪐 Intelligence World – Pflege | Backoffice |
| 🌐 World Connections Map | Backoffice |
| Onboarding – Partneranfragen | Backoffice |
| Kontaktanfragen (LP-13) | Backoffice |
| Partnerdokumente | Backoffice |
| 🖼 Media Board | Backoffice |
| 🎨 Brand Board · 🧭 Header Board · 🧩 Components Board · 🌐 Language Board | Backoffice |
| 🧭 Customer View Flow | Backoffice ▸ Administration Plattform |
| 🧭 CVF Board (Tabellenansicht) | Backoffice ▸ Administration Plattform |
| 🛡 Audit Board | Backoffice ▸ Administration Plattform |
| 🧾 Programmierlogbuch · 📋 To-Dos · 📖 Handbuch | Backoffice ▸ Administration Plattform |

## 4 WordPress-Randbedingung

WordPress-Adminmenüs kennen **nur eine** Untermenü-Ebene (Top-Level → Submenu). Eine echte dritte Ebene
(Backoffice ▸ Administration Plattform ▸ …) ist nativ nicht vorgesehen. Deshalb drei realistische Varianten:

- **Variante A (empfohlen): zwei Top-Level-Menüs** „Liebherr Frontend" und „Liebherr Backoffice". Die vier
  Welten hängen unter Frontend; alle Boards unter Backoffice. „Administration Plattform" wird im
  Backoffice-Menü durch eine **nicht anklickbare Abschnittsüberschrift** (Trenner-Submenu) sichtbar
  abgesetzt. Klarste 1:1-Abbildung des Wunsches, minimaler Umbau, gleiche Slugs/Rechte.
- **Variante B: ein Top-Level + Abschnittstrenner** „▸ Frontend" / „▸ Backoffice" / „▸ Administration
  Plattform" als deaktivierte Pseudo-Einträge. Weniger Klick-Wege, aber optisch gedrängt.
- **Variante C: Dashboard-Landeseite** mit gruppierten Karten (Frontend / Backoffice / Administration
  Plattform) als Menü-Ziel; Submenus bleiben zusätzlich. Schönste Übersicht, meiste Bauarbeit.

## 5 Empfehlung & Umsetzung (nach Freigabe)

**Variante A.** Umbau ausschließlich in `AdminMenu::add_menu()`:
1. Zwei `add_menu_page()`: „Liebherr Frontend" (dashicons-admin-site-alt3) und „Liebherr Backoffice"
   (dashicons-networking). Frontend-Position knapp vor Backoffice.
2. Die vier Frontpage-Direktlinks unter Frontend registrieren (bestehende `iw_url()/li_url()/…`).
3. Alle Pflege-Boards unter Backoffice; danach ein Trenner-Submenu „— Administration Plattform —"
   (Capability `liw_cvf_administer`; Ziel `#`, per kleinem CSS als Überschrift gestylt), gefolgt von
   Customer View Flow, CVF Board, Audit Board, Programmierlogbuch, To-Dos, Handbuch.
4. **Unverändert:** alle Seiten-Klassen, `MENU_SLUG`s, admin-post-/ajax-Handler, Capabilities. Nur die
   Zuordnung/Reihenfolge ändert sich → geringes Risiko, kein Datenmodell betroffen.
5. Bestehende Direkt-URLs (`admin.php?page=…`) bleiben gültig (Slugs unverändert); nur die Navigation ändert sich.

**Offene Rückfrage:** Sollen „Programmierlogbuch/To-Dos/Handbuch" wirklich unter „Administration Plattform"
liegen (Empfehlung) oder als eigener kleiner Bereich „System/Doku"? Und Variante A, B oder C?

---

*Nach Freigabe setze ich Variante A in `AdminMenu.php` um (reiner Menü-Umbau, mit Selbsttest, dass alle
Board-Slugs weiterhin registriert sind).*
