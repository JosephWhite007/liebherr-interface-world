# ADR-LIW-001: Eigenständiges Plugin mit CoreBridge-Integration (Variante A)

**Status:** Angenommen
**Datum:** 2026-09-17
**Entscheider:** Joseph White (JW)
**Bezug:** araliya-platform-core/docs/LOGBUCH_TECHNIK.md, Einträge 2026-09-17
(„zweite Ausnahme von der Ein-Plugin-Regel" und „Integrationsvariante A")

## Kontext

Liebherr Interface Solutions – Interface World Connections ist eine administrierbare,
mehrsprachige Landingpage für Liebherr-Händler-, Lieferanten- und Kundenanbindung
(Pflichtenheft v1.0). Das Pflichtenheft sieht ein eigenständiges WordPress-Plugin vor. Das
widerspricht zunächst der Ein-Plugin-Regel von `araliya-platform-core` (CLAUDE.md Abschnitt 1).

## Entscheidung

1. **Eigenständiges Plugin** `liebherr-interface-world` – zweite Ausnahme von der
   Ein-Plugin-Regel neben `araliya-installer`, da fachlich außerhalb der
   Health-Relationship-Domäne der ARALIYA Constitution (Artikel 1, 23) und anderer
   Auftraggeber/Marke.
2. **Variante A (Integration statt Eigenbau):** `araliya-platform-core` ist Laufzeit-
   Abhängigkeit. Ein `CoreBridge`-Adapter-Namespace ist der einzige Kopplungspunkt zu
   Core-Services – analog zum bestehenden Muster von `araliya-installer`
   (`Core\Environment\EnvironmentManifest`/`InstanceState`).

## Verifizierte Reuse-Entscheidungen je Core-Service

| Service | Ergebnis der Prüfung | Bridge-Entscheidung |
|---|---|---|
| `Modules\Translation\Registry\TranslationRegistry` + Modulvertrag (`araliya_translatable_fields`/`araliya_translation_source_value`, ADR-128–133) | Generisch, post-type-unabhängig (ModuleSlotAdapter) | **Wiederverwendet** – `CoreBridge\TranslationBridge` meldet `liw_section`-CTA-Meta-Feld über den bestehenden Filter an. post_title/post_content deckt der vorhandene `PostFieldAdapter` ohnehin ab. |
| `Core\RoleManager` (native WP-Rollen) | Generisch | **Wiederverwendet** – `CoreBridge\RoleBridge` vergibt eigene Capabilities (`liw_*`) an bestehende ARALIYA-Rollen statt eigenes Rollensystem. |
| `Modules\Audit\AuditService::log()` | Generische Signatur (`entity_type`/`entity_id` als String/Int, keine Domänen-Bindung) | **Wiederverwendet** – `CoreBridge\AuditBridge` mit `error_log`-Fallback, falls Core inaktiv. |
| `Modules\I18nSeo\I18nRouter` | **Nicht generisch** – fest verdrahtet auf `POST_TYPES = ['suite','apartment','treeroom']` | **Nicht gebunden.** Core-Änderung wäre Kategorie A (Eingriff in Core-Komponente außerhalb des Liebherr-Moduls) und wurde nicht ohne Freigabe vorgenommen. Liebherr rendert hreflang/Canonical eigenständig über `wp_head` (liest aber `Language\LanguageService::get_active_langs()` mit). Offener Punkt an JW: Core-Router-Scope erweitern oder bei eigener Lösung bleiben. |
| `Modules\ImageManager` | **Falscher Zweck** – kalenderbasierter Bildwechsel mit Rollback (`araliya_image_schedule`), nicht „Media Board" | **Nicht gebunden.** `CoreBridge\MediaBridge` nutzt native WP-Medienbibliothek + zwei Attachment-Meta-Felder (Copyright, Quelle) statt Zweckentfremdung. |
| `Modules\Consent\ConsentService` | **Health-Domain-gebunden** (`guest_id`) | **Nicht gebunden.** Eigene, schlanke `Consent\ConsentLogService`/`ConsentLogSchema` (kein neues System, nur eine Tabelle nach demselben fachlichen Muster: Version + Zeitstempel, Zweckbindung §22/§24). |
| `Modules\ChangeManagement\ChangeRequestService` | **Location-gebunden** (`location_id`, physische Räume) | **Nicht gebunden.** Freigabeworkflow läuft über den nativen WP-Post-Status-Mechanismus (`register_post_status('liw_approved')`) statt eigenem Datenmodell. |
| `Modules\Partner` (`ary_partners`) | Passende Grundfelder (name, contact_*, partner_type, status) | **Vorgesehen zur Wiederverwendung** für das Onboarding-Formular (Folgeauslieferung) – Liebherr-spezifische Zusatzfelder (Region, ERP, Projektinteresse) in einer eigenen, schlanken Erweiterungstabelle `liw_partner_extra`, nicht durch Änderung der Core-Tabelle. |

## Konsequenz

Drei der ursprünglich sieben angenommenen Bridges wurden nach Prüfung des echten Codes
korrigiert (SEO, Media, Consent/ChangeManagement) – bewusste Abweichung von der ursprünglichen
Annahme im Phase-3-Plan, um keine falsche Kopplung an Health-/Standort-spezifische Contracts zu
erzwingen (Grundprinzip „nicht raten, nicht erfinden"). Nur vier von acht ursprünglich im
Pflichtenheft vorgesehenen `liw_*`-Tabellen sind tatsächlich neu (`liw_interface`,
`liw_simulation_world`, `liw_test_scenario`, `liw_connection`) plus die begründete Ausnahme
`liw_consent_log`.

## Betroffene Dateien

Siehe `CHANGELOG.md`. Kein Eingriff in `araliya-platform-core`-Dateien.
