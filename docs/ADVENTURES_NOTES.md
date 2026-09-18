# Liebherr Adventures – Umsetzungsnotizen (Grundlagenkonzept)

Vierte Insel als **Modul im bestehenden Plugin** (`src/Adventures/`), deaktivierbar/erweiterbar; nutzt
CoreBridge/Rollen/Medien/Sprachen mit (keine Duplizierung). Entscheidung JW 19.09.2026: Modul-in-Plugin,
**sichtbares MVP zuerst**. Prototyp (§23.3): keine echten Diagnosen/Sicherheitsfreigaben/Bestellungen,
Demo-Daten gekennzeichnet.

## Etappe „Visible Adventures – Teil 1" (0.1.0-alpha.51)

**Umgesetzt:**
- Klassifikation `Taxonomy` (13 Inhaltstypen + 4 Dringlichkeitsstufen, **getrennte Achsen**, §3).
- Drei-Wörter-Ort: `Location\ProviderInterface` + `MockProvider` (invertierbar, 64-Wort-Liste, 0,5°-Raster)
  + `LocationService` (Resolver über Filter `liw_adv_three_word_provider`, Partner-Attribution „Location powered by …").
  **Kein hart verdrahteter Anbieter** (§4.1); Lizenz-/Freigabe-Gate offen (§4.3/§24.1).
- Datenmodell `AdventureCpt` (CPT `liw_adventure` + Meta: Typ, Dringlichkeit, Sichtbarkeit, Ort, UUID,
  Schutzstufe, Lösungsstatus, Medium). UUID statt fortlaufender ID (§10.2).
- `Policy` (serverseitig): `can_create` (Intelligence-Zugang = Login, Filter `liw_adv_can_create`),
  `effective_status` (Einreichen → pending; **Critical nie auto-öffentlich**, §22.5), `can_view`
  (Sichtbarkeit erzwungen, §9/§22.6/§22.11), `can_moderate`.
- `AdventureService` (create + sichtbarkeitsgefilterte query + View-Modell; Ortspräzision je Schutzstufe, §4.4).
- `Rest` (`liw-adv/v1`): stream (öffentlich), locate (öffentlich), create (nur Intelligence-Zugang + Nonce).
- `AdventuresView` (Shortcode `[liw_adventures]`): Hero (Kernbotschaft), Filter, Create-Panel (nur mit Zugang),
  Stream (serverseitig + JS-Refresh), Partner-Attribution. Assets `liw-adventures.css/js` (eigener Cache-Buster).
- Seite `/liebherr-adventures/` + Demo-Seeder `scripts/liw-seed-adventures.php` (6 öffentlich, 1 kritisch/pending).

**Abnahme (§22) – Stand:** erfüllt für den sichtbaren Kern: ohne Intelligence-Zugang kein Erstellen (1/22),
Entwurf/Einreichen (2), Drei-Wörter-Ort über Mock-Adapter (3), Typ+Dringlichkeit unabhängig (4), Critical nicht
ungeprüft öffentlich (5), Sichtbarkeit serverseitig (6), Stream/Filter/Detail (Stream+Filter da; Detailseite &
World Map folgen), Partner-Attribution administrierbar (9), Demo-Kennzeichnung (14), keine hart codierten Domains (15).

## Offen / nächste Etappen
- **World Map** (Kartenansicht mit Clustering + Schutz, §18.2) und **Adventure-Detailseite** (§7.4).
- **Medien-Upload/Transcoding** (§14) – MVP nutzt vorerst externe Bild-URL-Fallback (`M_MEDIA_URL`).
- Moderations-UI im Backoffice (§17), Audit-Events (§12.3), My Adventures, Get-Help-Assistent (Phase 3).
- Verbindliche Filter (Maschine/Bauteil/Fehlercode) + Suche (§18.1); Mehrsprachigkeit des Nutzerinhalts (§15).

## Offene Entscheidungen (§24)
- Drei-Wörter-Partner (Name/Lizenz) → Provider bleibt bis dahin Mock.
- Max. Videodauer/Dateigröße, führende Systeme (Maschinen/Teile/Bestand), Helpdesk-Schnittstelle,
  Reaktionszeiten Critical, Aufbewahrungsfristen je Typ, Markenfreigabe.
