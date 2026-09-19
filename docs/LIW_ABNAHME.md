# Liebherr Interface Solutions — Abnahmebericht (Stufe 1)

**Stand:** 18.09.2026 · **Version:** 0.1.0-alpha.34 · **Grundlage:** Pflichtenheft
`Pflichtenheft_Liebherr_Interface_Solutions_Landingpage.md` (v1.0), Release-Plan `docs/LIW_RELEASEPLAN.md`.
**Umgebung:** Docker-Dev (araliya_wordpress). **Staging/Produktion:** gesperrt bis ausdrückliche Freigabe (§30, AC-016).

Dieser Bericht dokumentiert den Abnahmestand der Stufe 1 (Etappen 1–8, alpha.27–34). „Teilweise"
bedeutet: bewusst als Stufe-1-Umfang so festgelegt (AC-002 erlaubt Feature-Flag/Vorbereitung) bzw.
durch einen externen §34-Input blockiert.

## 1. Funktionale Abnahmekriterien (§32)

| ID | Kriterium | Status | Anmerkung |
|----|-----------|--------|-----------|
| AC-001 | Landingpage unter administrierbarer, umgebungsabhängiger Route | ✅ | `[liw_landingpage]` auf frei wählbarer WP-Seite; Route `/interface-world` als Fallback, Domain/Route je Umgebung = §34-Input |
| AC-002 | Alle 14 Abschnitte umgesetzt oder per Feature-Flag vorbereitet | ✅ | LP-01/06/08/11/12/13/14 datengetrieben/gebaut; LP-02/03/04/05/07/09/10 kuratiert (redaktionell), tiefe Interaktivität feature-geflaggt |
| AC-003 | Inhalte/Bilder/CTAs/Sichtbarkeit ohne Code pflegbar | ✅ | Content Board, Components Board, Header Board, Brand Board, Media Board |
| AC-004 | DE/EN/PL vollständig, weitere Sprachen strukturell vorbereitet | ⚠️ | Struktur + Sprachumschalter + Readiness-Board vorhanden; 100-%-Kuratierung der Texte = Redaktion (Languages Hub), Messung im Language Board |
| AC-005 | Sprachwechsel erhält Seite, korrekte Locale-Routen | ✅ | Core-Sprachsteuerung (Cookie/`?lang=`), hreflang/Canonical je Sprache |
| AC-006 | Liebherr primär, GoHeal nur klein als Solution Provider | ✅ | GoHeal nur Footer/Impressum (CI-003); seit alpha.37 Liebherr-Logo/-CI **vorläufig** angewendet (autorisiert JW), Logo/Hero freigegeben |
| AC-007 | CI ausschließlich aus freigegebenen/konfigurierbaren Brand Tokens | ✅ | Brand Board `--brand-*` (inkl. `--brand-on-primary`); seit alpha.37 mit echten Liebherr-Werten belegt (vorläufig bis endgültige Freigabe, §34) |
| AC-008 | Kontakt-/Onboarding serverseitig validiert, sicher gespeichert/übergeben, bestätigt | ✅ | Validierung, Nonce, Honeypot, Rate-Limit, Einwilligungsprotokoll, Bestätigung; Empfänger/CRM = §34 (bis dahin Admin-Mail) |
| AC-009 | Adminrechte serverseitig erzwungen, Änderungen auditiert | ✅ | Capabilities je Board, AuditBridge + Audit Board |
| AC-010 | Keine produktiven Schnittstellen/Daten unbeabsichtigt angesprochen | ✅ | Keine externen Adapter aktiv; DEMO-Seed klar gekennzeichnet (§4) |
| AC-011 | Responsive 360–1920 px | ✅ | Header Off-Canvas, fluide Hero/Grids; Desktop/Mobil im Browser geprüft |
| AC-012 | WCAG 2.2 AA Kernanforderungen | ⚠️ | Semantik/Fokus/Tastatur/`aria-current`/`prefers-reduced-motion`/Textalternativen umgesetzt; formale A11y-Vollprüfung (Screenreader/Kontrast mit echten CI-Farben) nach Markenfreigabe |
| AC-013 | Performanceziele auf Staging gemessen | ⛔ | Offen bis Staging (Lighthouse/Web-Vitals); Budgets §25 beachtet (Lazy-Load, scoped CSS, ein kleines JS) |
| AC-014 | SEO-Metadaten, Canonical, hreflang, Sitemap | ✅ | hreflang + Canonical je Locale + OG-Tags; `liw_section` aus Sitemap ausgeschlossen |
| AC-015 | Tests, Build, Deployment, Rollback dokumentiert | ✅ | Unit + Docker-Selbsttest; Rollback s. Abschnitt 4; Deploy über Core-Deployment-Manager |
| AC-016 | Staging fachlich/visuell freigegeben, Produktion gesperrt | ⛔ | Ausstehend – Staging-Abnahme + Produktionsfreigabe durch JW/Liebherr |

## 2. Qualität / Tests (§31)

- **Unit (WP-frei):** `php tests/run-tests.php` – 174 Prüfungen grün (Mapper/Validatoren/Fallbacks/
  Statuslogik, u. a. Ziel-Normalisierung, Alt-Text-Zählung, Brand-Token-Sanitize, OG/Canonical-Logik).
- **Integration (Docker):** `docker exec araliya_wordpress php scripts/liw-selftest.php` – 177+ Prüfungen
  grün (Shortcodes, Boards, Audit-Lesen, Lifecycle, SEO, Readiness, Rate-Limit, Retention, Upload-Härtung).
- **Visual/Responsive:** Header/Hero/Komponenten/Map in Desktop (1280) und Mobil im Browser bestätigt.
- **E2E / Visual-Regression / automatisierte A11y/Lighthouse:** bewusst nicht eingeführt (keine neue
  Kategorie-A-Toolchain, B-8) – manuelle Stichproben + Staging-Messung.

## 3. Liefergegenstände (§33)

Modul-Quellcode; DB-Migrationen (dbDelta, idempotent) + **Demo-Seed** `scripts/liw-seed-demo.php`
(keine echten Daten); Marken-Asset-Import `scripts/liw-import-brand-assets.php` (CI-005-Kandidaten);
Token-Referenz `docs/LIW_BRAND_TOKENS.md`; Handbuch (Admin-Reiter, 14 Bereiche); Technik-/Architektur-
Doku `docs/ADR-LIW-001`, `docs/LIW_PROGRAMMIERLOGBUCH.md`, `docs/LOGBUCH_TECHNIK.md`; To-Dos
`docs/LIW_TODO.md`; dieser Abnahmebericht.

## 4. Rollback-Verfahren

- **Code:** je Etappe ein Git-Commit (alpha.27–34); Rücknahme per `git revert <commit>` bzw.
  Auslieferung der Vorversion über den Core-Deployment-Manager (Variante A).
- **DB:** additive, idempotente `dbDelta`-Migrationen – kein destruktives Schema; ein Rückschritt der
  Plugin-Version lässt bestehende Tabellen unberührt. Optionen (`liw_brand_tokens`, `liw_header`,
  `liw_components`, `liw_contact_retention_days`) sind unkritisch und können geleert werden.
- **Cron:** `liw_contact_retention_cron` wird bei Deaktivierung entfernt.
- **Assets/DEMO:** Media-Kandidaten (`approved=0`) und DEMO-Seed sind gekennzeichnet und löschbar,
  ohne echte Daten zu berühren.

## 5. Offene Inputs / Launch-Blocker (§34)

| Input | Verantwortlich | blockiert |
|-------|----------------|-----------|
| Freigegebenes Liebherr-Logo + Varianten | Liebherr | finales Header/Footer-Branding (Kandidat im Media Board, `approved=0`) |
| Brand Manual (Farben/Typo/Schutzräume) | Liebherr | finale Design Tokens (Werte in `docs/LIW_BRAND_TOKENS.md` bereit, Freigabe fehlt) |
| Freigegebenes Bild-/Videomaterial | Liebherr | finale Hero-/Maschinenmotive (Kandidaten `approved=0`) |
| Freigegebene Marken-/Produkttexte | Liebherr/PL | finale öffentliche Aussagen |
| Datenschutz-/Impressumsangaben | Recht/Betreiber | produktiver Launch |
| Zielroute + Domain je Umgebung | Plattformteam | Deployment-Konfiguration |
| CRM-/Empfängerdefinition | Projektleitung | produktive Formularübergabe (bis dahin Admin-Mail) |
| Rollen + Freigabeverantwortliche | Projektleitung | Freigabeworkflow |
| Schrift-Lizenz LiebherrHead/-Text | Liebherr | Einbindung der Marken-Webfonts (bis dahin Fallback-Stack) |

## 6. Bewusst zurückgestellt (Kategorie-A-/YAGNI-Folgepunkte)

Geo-Weltkarte (statt Regionen-Grid), I18n-Router Option B (Präfix-URLs), Simulations-Status-Automat,
Observability-Dashboard (§28), echte externe Adapter, tiefe Interaktivität der kuratierten Abschnitte,
Drag-&-Drop-Reihenfolge / zeitgesteuerte Veröffentlichung / Mehrgeräte-Vorschau im Content Board,
per-Sprache-Overrides der Chrome-Labels, formaler `trx-scan/-audit`-Lauf. Details: `docs/LIW_TODO.md`.

## 7. Definition of Done (§35)

MUSS-Funktionen implementiert, getestet (Unit + Docker) und dokumentiert; DE strukturell vollständig,
EN/PL vorbereitet; keine kritischen Sicherheits-/Darstellungsfehler bekannt. **Ausstehend für „fertig":**
Staging-Abnahme + Performance-/A11y-Vollmessung, dokumentierte Liebherr-Markenfreigabe, ausdrückliche
Produktionsfreigabe. Bis dahin bleibt die Produktivschaltung gesperrt.

## 8. Liebherr Local Intelligence (Pflichtenheft LI, alpha.41/42)

Umsetzung des Pflichtenhefts „Liebherr Local Intelligence Landingpage": neue übergeordnete Hauptseite;
die bisherige Interface-World-Seite bleibt erhalten und wird zur technischen Unterseite.

### 8.1 Vorher-Nachher – Routen (§16 AK4/AK15)

| Vorher | Nachher |
|---|---|
| `/interface-world/` (eigenständige Seite) | `/interface-world/` → **301** auf die Unterseite |
| — | `/liebherr-local-intelligence/` (**neue Hauptseite**) |
| — | `/liebherr-local-intelligence/interface-solutions/` (Interface-Seite, verschachtelt) |

Bestehende interne Links/Bookmarks/Kampagnen laufen über den 301 weiter (kein toter Link, §3.2).

### 8.2 Geänderte/neue Dateien und Komponenten (§17.8)

- **Neu:** `Settings/LocalIntelligenceContent` (Content-Modell), `Frontend/LocalIntelligenceView`
  (11 Modul-Shortcodes + Composite `[liw_local_intelligence]` + `[liw_context_nav]`),
  `Frontend/LegacyRedirect` (301), `Content/SitePages` (Seiten-Registry),
  `Admin/Pages/LocalIntelligenceBoardPage` (Pflege), `scripts/liw-seed-local-intelligence.php`.
- **Geändert:** `Bootstrap`, `Frontend/FrontendAssets`, `Frontend/RocketCompat`, `Admin/AdminMenu`,
  `CoreBridge/SeoBridge` (Composite als Träger erkannt + noindex/Sitemap-Prototyp-Guard),
  `assets/js/liebherr-frontend.js` (Szenario-Schalter + Filter), `assets/css/liebherr-frontend.css`
  (`.liw-li*`), `scripts/liw-seed-demo-landing.php`, `scripts/liw-selftest.php`, `tests/run-tests.php`.

### 8.3 Abnahmekriterien LI (§16)

Erfüllt: eigenständige, vollständig erreichbare Hauptseite (AK1); Nutzen/Thema in Sekunden (AK2);
Interface-Seite besteht als Unterseite fort (AK3); Routen/301 (AK4); Erzählbogen Simulieren–Verstehen–
Entscheiden (AK5); Simulation/Wissen/Datenqualität/weltweit konkret (AK6); Interface Solutions als
Befähigungsebene eingeordnet (AK7); Inhalte administrierbar (AK8); DE vollständig, EN/weitere vorbereitet
(AK9); responsiv/Tastatur/reduzierte Bewegung (AK10); keine hartcodierten Domains/Formularziele (AK11);
keine unfreigegebenen Versprechen (AK12); Tests grün (AK13/AK14); diese Vorher-Nachher-Liste (AK15).

### 8.4 Prototyp-Status (§9.2/§12.7)

Bis zur dokumentierten Liebherr-Freigabe sind Haupt- und Unterseite **noindex** und aus der XML-Sitemap
ausgeschlossen (Guard in `SeoBridge`, Option `liw_public_release` / Filter `liw_allow_indexing`, Standard
gesperrt). CI weiterhin **vorläufig** (siehe §5). Freischaltung: `liw_public_release` setzen **und**
Markenfreigabe dokumentieren.

### 8.5 Offen (LI)

Vollständige EN-Fassung + weitere Sprachen (§12.4), Analytik-Events (§14), Szenario-Editor im Board,
Performance-/A11y-Vollmessung auf Staging, redaktionelle + markenrechtliche Freigabe.

## 9. My Liebherr – R1-Durchstich (Pflichtenheft My Liebherr, ADR-LIW-MYL-001)

**Stand:** 19.09.2026 · **Version:** 0.1.0-alpha.116 · alles hinter Flags `liw_myl_enabled`/`liw_ptime_enabled` (Default AUS).
Verifikation: `tests/run-tests.php` 576/0, `scripts/liw-selftest.php` 408/0 (echtes WP).

| ID | Szenario | Status | Nachweis |
|----|----------|--------|----------|
| MYL 001 | Persönliche My-Liebherr-Startseite mit korrektem Kontext | ✅ (Durchstich) | `[liw_my_liebherr]` rendert für angemeldeten Nutzer mit `liw_myl_access`; Context::for_user |
| MYL 002 | Berechtigte Widgets anordnen/aus- und einblenden, bleibt erhalten | ✅ | Dashboard S3: WidgetCatalog+DashboardService+`ary_liw_myl_dashboard_layout`, REST GET/PUT, Selftest-Round-Trip |
| MYL 003 | Mehrfachrollen/Organisationen, Kontextwechsel | ⚠️ Teil | Kontext + Mitgliedschaften lesbar, aktive Org/Rolle im Profil setzbar (PATCH /me); Org-Pflege/Onboarding folgt R1-Breite |
| MYL 004 | Wallet zeigt Salden aus dem Ledger | ✅ (read-only) | `[liw_my_wallet]` über `WalletBridge`→Core `get_summary`; verfügbar/reserviert/gesperrt-Buckets folgen mit Wallet-Pflichtenheft |
| MYL 006 | Erlöse/Buchungen je Beleg nachvollziehbar | ✅ (read-only) | Buchungsliste mit Datum/Art/Betrag ±/Status aus `get_transactions` |
| MYL 013 | Sechs Plattformreiter, Reihenfolge/Rollen/Direktlink | ✅ | Reihenfolge fix (Pos. 1–6); Nav rollenabhängig; CVF-Board-Simulation der neuen Karten (Einstieg→my_liebherr/pocket, First-Entry, Return-Route) via `wire_module_card`; Direktlink-Schutz durch self-gating der persönlichen Seiten (Login + `liw_myl_access`) |
| MYL 014 | My Dreams: private Maschinenfavoriten anlegen/sortieren/entfernen | ✅ | `[liw_my_dreams]`, Tabelle `dream_item`, REST + Round-Trip-Selftest |
| MYL 015 | Drei Galeriesichten mit getrennten Grants | ✅ (Kollegen aktiv / World Review offen) | `[liw_my_gallery]` privat + `share_grant` Kollegen/World + `[liw_shared_colleagues]`/`[liw_shared_world]` |
| MYL 016 | Own Adventures nach Status/Maschine filtern | ✅ | `[liw_my_adventures]` (Insel-Wiederverwendung), Status-Filter, Maschine/Bauteil/Token |
| MYL 017 | Drei-Wort-Name genau drei normalisierte Begriffe | ✅ | `ThreeWordLabel` (Tabelle three_word_label): Maschine·Problem·Handlung normiert, Vorschlag→Bestätigung, Synonyme + Agentensuche |
| MYL 018 | Kontaktanfrage: ohne Zustimmung kein Direktkontakt/Wallet-Verbindung | ✅ | `[liw_my_contacts]`: Erstkontakt nur als Anfrage; Connection entsteht erst bei accept |
| MYL 019 | Gemeinsame Leistung erzeugt genau eine korrekte Buchung | ✅ (Naht) | Service proposed→confirmed; Buchung über Hook `liw_myl_service_charge` (deferred bis Wallet-Pflichtenheft) |
| MYL 020 | Verbindung beenden: neue Leistungen verhindert, Historie bleibt | ✅ | Connection ended (Zustandsautomat), Service nur bei accepted/active vorschlagbar |
| MYL 021 | Pocket-Feed personenbezogen, priorisiert, auf Quelle rückführbar | ✅ | `[liw_pocket]`/`pocket/v1/feed`, Alerts zuerst, Rücksprung-URL |
| MYL 022 | Pocket-Pflichtinfo: Anzeige und bewusste Quittierung getrennt | ✅ | `requires_ack` + `pocket/v1/items/{id}/ack`, `acknowledged_at` protokolliert |
| MYL 023 | CVF-Viererflow bleibt intakt, kontrolliert auf 6 erweitert | ✅ | Board-Seed ergänzt my_liebherr/pocket_information additiv + inaktiv; Bestandsflows unverändert |
| MYL 09 | My Machines: zugeordnete Maschinen verwalten | ✅ | `[liw_my_machines]`, Tabelle machine, CRUD |
| MYL 009 | Meldungen sperren Inhalte + lösen Erstattung aus | ✅ (Naht) | Report→`ModerationService`: suspend setzt Galerie-Objekt `suspended`; refund via Hook `liw_myl_refund` (deferred); World-Review pending→published/blocked durch Prüfer (`liw_myl_moderate`) |
| MYL 012 | Mobil + Tastatur bedienbar | ✅ (Durchstich) | responsive CSS, Buttons/Formfelder tastaturbedienbar, `prefers-reduced-motion` (Uhr) |
| MYL 025 | Session-Uhr jederzeit ein-/ausblendbar, Zeit+Token | ✅ | ClockWidget unten links, Toggle, `platform-time/status` |
| MYL 026 | Serverautoritäre Zeit (Idle/Abbruch pausiert) | ✅ | SessionClock (Gap > Timeout zählt nicht), Heartbeat; Unit+Selftest |
| MYL 027 | Genau eine Abrechnung je Abschnitt (Idempotenz) | ✅ | ChargeService UNIQUE `ptime-<session>`; zweiter Stop = no_session |
| MYL 028 | Wallet-Naht (deaktiviert → nur ausstehend protokolliert) | ✅ | `liw_ptime_charge_live` Default AUS → Satz `pending`; Hook `liw_ptime_charge` |
| SEC 01 | Autorisierung serverseitig, nur eigenes Objekt | ✅ | EntitlementService (fremdes Objekt/Org nur mit Administer); Negativtest Subscriber |

**Offen (Breite R1 ff.):** S5-Profil-Datenschutz-Export als echte Funktion, Org-/Onboarding-Pflege (MYL 003 voll),
R2 Wallet-UI + Mehrwährung/Token-Buchung (Core-Kategorie A / Wallet-Pflichtenheft), Staging-Perf/A11y-Vollmessung.
**Staging/Live:** Seeder `liw-seed-my-liebherr.php`, Optionen/Flags neu setzen, Permalinks speichern.
