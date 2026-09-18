# Liebherr Interface Solutions — Release-Plan bis Pflichtenheft-DoD (Stufe 1)

**Stand:** 18.09.2026 · **Freigabe:** Joseph White (18.09.2026 „ok, wir können loslegen") ·
**Grundlage:** `Pflichtenheft/Pflichtenheft_Liebherr_Interface_Solutions_Landingpage.md` (v1.0),
Ist-Stand `0.1.0-alpha.26`.

Stufe-1-Umfang nach Ermessen (JW: B-1 „(b) nach Ermessen"): funktionales Gerüst + definierte
Komponenten voll ausgebaut, aufwändig-interaktive Teile per Feature-Flag/kuratierter Grafik
vorbereitet (AC-002). Jede Etappe = eigener Commit, Tests, CHANGELOG + drei Bücher + Logbuch;
Staging/Produktion nur nach ausdrücklicher Freigabe.

## Abschnitts-Split (Stufe 1)

- **Datengetrieben + administrierbar:** LP-01 Hero, LP-06 World Connections (Grid), LP-08 Process
  World Cards, LP-11 Onboarding-Schritte, LP-12 Roadmap-Timeline, LP-13 Kontakt (fertig), LP-14 Footer.
- **Kuratierte Grafik/HTML (tiefe Interaktivität feature-geflaggt):** LP-02 Risk Matrix, LP-03
  Zielbild, LP-04 Magic Cube, LP-05 Interface LogiQ, LP-07 Data Model, LP-09 Goods & Finance,
  LP-10 Security/Validation Ladder. Alle mit `prefers-reduced-motion` + Textalternative (WCAG).

## Etappen

| # | Version | Etappe | Inhalt | §/AC |
|---|---|---|---|---|
| 1 | alpha.27 | **CI/Brand-Fundament** | Brand-Token-Board (Admin, konfigurierbar), neutrale Fallbacks, `:root`-Injektion via `wp_add_inline_style`, Logo-Slot aus Media Board, GoHeal-Footer | §10–12, CI-001..005, AC-006/007 |
| 2 | alpha.28 | **Header + Hero** | `[liw_header]` (admin-Nav, Primär-/Sekundär-CTA, Sprachumschalter, optl. Portal-Login, sticky, mobil) + LP-01 Hero-Network | §7, §16, LP-01, AC-002 |
| 3 | alpha.29 | **Kern-Komponenten** | LP-08 Process Cards, LP-12 Roadmap, LP-11 Onboarding datengetrieben; LP-02/03/04/05/07/09/10 kuratiert + Textalternative | §8, §16, AC-002/012 |
| 4 | alpha.30 | **Redaktion + §24-Export** | CSV-Export, Pflichtfeld-/Übersetzungs-/Alt-Text-Warnung, CTA-Ziel-Picker, Medien-Picker-Beschränkung, Drag-&-Drop, Zeitsteuerung, Vorschau | §19, §24, AC-003 |
| 5 | alpha.31 | **Boards-Rest** | Audit Board (Lese-Ansicht Core-Audit), Interface-Lifecycle-Status-UI; Release über Core-Deploy | §18, AC-009 |
| 6 | alpha.32 | **Sprache/SEO-Rest + Release-Gate** | Canonical/OG je Sprache, Sitemap; LANG-006-Vollständigkeits-Gate | §20/21, LANG-006, AC-004/005/014 |
| 7 | alpha.33 | **Sicherheit/Datenschutz** | Rate-Limiting Formulare, Upload-Prüfung, konfigurierbare Löschfrist + Cron, Header dokumentiert, Fehlermeldungs-Hygiene | §23/24, AC-008 |
| 8 | alpha.34 | **Qualität/Abnahme** | Test-Ausbau, manuelle A11y/Perf/Browser-Stichproben, Seed-Daten, Abnahmebericht + §34-Launch-Blocker + Rollback | §25–35, AC-011..016 |

## Nicht in Stufe 1 (Kategorie-A-/YAGNI-Folgepunkte)

Geo-Weltkarte, I18n-Router Option B, Simulations-Status-Automat, Observability-Dashboard, echte
externe Adapter. Marken-/Rechte-/Domain-/CRM-Inputs = Launch-Blocker (§34); Liebherr-Assets liegen
als CI-005-Kandidaten im Media Board (`approved = 0`), Werte in `docs/LIW_BRAND_TOKENS.md`.
