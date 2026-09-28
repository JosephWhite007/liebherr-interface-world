# Liebherr Interface Solutions – Kopplung zum ARALIYA Platform Core (Isolationsnachweis)

**Stand:** 28.09.2026 · 0.1.0-alpha.145 (Freeze) · Core 2.0.0-alpha.718 · Ermittelt per Quelltext-Scan (`src/`, Core `src/`).
**Zweck:** Vor der Duplicator-Migration muss belegt sein, dass das Plugin isoliert werden kann: welche Core-Schnittstellen
es nutzt, was beim **Ausschalten** im System bleibt, und dass der Core **ohne** das Plugin unverändert läuft.

## 1. Kernaussage

- **Kopplung ist strikt einseitig: Plugin → Core.** Der Core (`araliya-platform-core/src`, Theme) enthält **keinen einzigen**
  Verweis auf `liw`/`liebherr`. Ausschalten des Plugins kann im Core nichts brechen.
- **Alle Core-Zugriffe laufen über `src/CoreBridge/*`** (eine Ausnahme: `Frontend/FrontendAssets.php` nennt
  `Core\Frontend\DesignSystem` nur im Docblock als Vorbild – kein Code-Zugriff). Jede Bridge prüft `class_exists`/
  `method_exists` (15 Guards) und hat einen Fallback → das Plugin läuft auch bei fehlender Core-Funktion degradiert,
  aber fehlerfrei. Aktivierung ohne Core wird verweigert (`activate()` → `deactivate_plugins` + Hinweis).

## 2. Genutzte Core-Schnittstellen (vollständig)

| Core-Klasse / Schnittstelle | Bridge | Zweck | Fallback ohne Core |
|---|---|---|---|
| `Core\RoleManager` | RoleBridge | ARALIYA-Rollen (`araliya_admin`) erhalten LIW-Caps | Caps nur auf `administrator` |
| `Core\RateLimiter` | RateLimitBridge | Drosselung Formulare/REST | keine Drosselung (nur Nonce/Honeypot) |
| `Modules\Audit\AuditService` + Tabelle `{prefix}ary_audit_log` (lesend, Audit Board) | AuditBridge | Audit-Einträge (`actor_type` admin/system) | kein Audit |
| `Modules\Partner\PartnerService` + Tabelle `{prefix}ary_partners` (1 JOIN im Onboarding-Board) | PartnerBridge / OnboardingService | Partnerkategorien clinic/doctor/supplier/wellness; Partnerdatensatz anlegen | Onboarding ohne Core-Partnersatz |
| `Modules\Wallet\WalletService` | WalletBridge | My Wallet (lesen), Plattformzeit-Token-Buchung (nur Flag `liw_ptime_charge_live`) | Wallet-Ansicht leer, Buchung „pending" |
| `Language\LanguageService`, `LanguageSwitcherWidget`, `Modules\I18nSeo\I18nRouter` | LanguageBridge | Sprache, Umschalter, hreflang-Koordination | DE/EN/PL statisch, kein Widget |
| `Modules\Translation\Registry\{TranslationRegistry,PageScope,TranslationGate}` + Filter `araliya_translatable_fields`, `araliya_translation_source_value` | TranslationBridge | Übersetzbare Felder der LIW-Inhalte im Core-Übersetzungsregister | Inhalte einsprachig |
| `Modules\Deployment\Admin\HandbookRenderer` | MarkdownBridge | Markdown-Rendering der Doku-Seiten | Klartext |
| Core-Design-Tokens (`--ary-*` CSS-Variablen) | – (CSS) | Farben/Typografie | Hex-Fallbacks in jedem `var()` |

Nicht genutzt: Core-Optionen (`ary_*`, `araliya_*`), Core-REST-Namespaces, Core-Cron, Core-CPTs, Core-Seitentemplates.

## 3. Was das Plugin im System anlegt (bleibt nach dem Ausschalten bestehen)

Ausschalten (= `deactivate()`) entfernt: LIW-Capabilities von `araliya_admin`/`araliya_marketing` (**nicht** von
`administrator` – Core-Konvention `RoleManager::deactivate()`, der native Admin verliert keine Caps; 12 `liw_*`-Caps
bleiben dort wirkungslos stehen), My-Liebherr- und CVF-Caps,
den Cron `daily` der Kontakt-Aufbewahrung, Rewrite-Regeln (Flush). **Bewusst NICHT entfernt** (Daten bleiben, kein
`uninstall.php`, Pflichtenheft-Vorgabe „keine Datenlöschung ohne Auftrag"):

| Art | Umfang | Verhalten bei ausgeschaltetem Plugin |
|---|---|---|
| Tabellen `{prefix}liw_*` | 37 (Interface/Simulation/Connection/Consent/Contact/Partner-Dokumente/CVF ×10/Intelligence World ×2/My Liebherr ×12/Pocket/Plattformzeit ×2/Adventures-Ledger) | inert; nur Speicherplatz |
| Optionen `liw_*` | ~30 (Seiten-IDs, Flags `liw_myl_enabled`/`liw_pocket_enabled`/`liw_cvf_*`/`liw_ptime_*`/`liw_emergency_enabled`/`liw_public_release`, `liw_installed_version`, Bild-IDs, `liw_w3w_api_key`) | inert |
| CPT-Inhalte | `liw_section` (14 Landingpage-Abschnitte), `liw_adventure` | Posts bleiben in `wp_posts`, sind ohne CPT-Registrierung unsichtbar (kein 404-Handling nötig, WP ignoriert unbekannte Typen im Admin) |
| Seiten (WP `page`) mit LIW-Shortcodes | Landingpage, `/my-liebherr/`, `/pocket-information/`, Intelligence World, Local Intelligence, Adventures (`liw_*_page_id`) | Seiten bleiben **veröffentlicht** und zeigen die Shortcode-Tags als Rohtext → **vor dem Ausschalten auf Entwurf setzen** (Schritt 4.2) |
| Rolle `liw_partner` | in `wp_user_roles` | bleibt (Benutzer behalten Rolle, nur `read`) – harmlos |
| User-Meta `_liw_partner_id` | Partnerkonten | inert |
| Uploads | Partner-Dokumente (Secure Storage mit `.htaccess Deny`), Medien mit `_liw_media_approved` | Dateien bleiben; `.htaccess` schützt weiter |
| Theme-Eingriffe | `wp_nav_menu_items` (Liebherr-Frontend-Menü), `theme_page_templates` (full-width), Login-Logo/Favicon-Filter | alle per Filter → nach Ausschalten sofort weg |
| Core-Daten, die LIW erzeugt hat | Audit-Einträge in `ary_audit_log`, Partnersätze in `ary_partners`, Übersetzungen im Core-Register, Wallet-Buchungen (nur wenn `charge_live` je aktiv war – in Dev: nein) | bleiben als reguläre Core-Daten |

## 4. Verfahren „Plugin ausschalten" (für die Duplicator-Migration)

Hinweis Docker-Dev: Das Image `wordpress:6.5-php8.2-apache` hat **kein WP-CLI**; `WP_DEBUG_LOG` ist nicht gesetzt (Fehler
stehen in `docker logs araliya_wordpress`). Alle Schritte laufen daher als `php -r` über `wp-load.php`.

1. Backup der Dev-DB: `docker exec araliya_mysql sh -c 'mysqldump --no-tablespaces -u araliya -p"$MYSQL_PASSWORD" araliya_dev' > ~/Desktop/dev-vor-liw-aus-$(date +%Y%m%d).sql`
   (`--no-tablespaces` nötig – MySQL 8 ohne PROCESS-Recht).
2. LIW-Trägerseiten auf **Entwurf** setzen (sonst Rohtext-Shortcodes öffentlich) und Plugin deaktivieren – **nicht** löschen
   (Ordner ist gemountet; im Duplicator-Paket per Ausschlussliste ausgeschlossen):
   `docker exec araliya_wordpress php -r 'require "/var/www/html/wp-load.php"; require_once ABSPATH."wp-admin/includes/plugin.php"; foreach(["liw_interface_page_id","liw_my_liebherr_page_id","liw_pocket_page_id","liw_iw_page_id","liw_li_page_id","liw_adventures_page_id"] as $o){ $id=(int)get_option($o); if($id){ wp_update_post(["ID"=>$id,"post_status"=>"draft"]); echo "$o=$id draft\n"; } } deactivate_plugins("liebherr-interface-world/liebherr-interface-world.php"); echo "LIW aktiv: ".(is_plugin_active("liebherr-interface-world/liebherr-interface-world.php")?"JA":"nein")."\n";'`
3. Nachweis Core-ohne-LIW (Deaktivierungstest):
   - `php -r '… StagingGate::run() …'` → `overall` nicht `critical`; `class_exists(StagingGate)` = ja
   - Startseite und `wp-login.php` → HTTP 200; Backend-Dashboard + eine CPT-Single im Browser fehlerfrei
   - `docker logs araliya_wordpress --since 3m 2>&1 | grep -i "fatal\|PHP Warning"` → leer
   - `administrator` behält die 12 `liw_*`-Caps (erwartet, s. o.); `araliya_admin` ohne `liw_*`
4. Golden-Master-Preflight erst **danach** ausführen (Runbook Phase 1.5).

**Ergebnis Deaktivierungstest: ✅ bestanden – 28.09.2026 14:26 (JW, Docker-Dev, Core alpha.718 `main`).**
Backup `dev-vor-liw-aus-20260928.sql` (19,6 MB). Trägerseiten 2093/4176/4275/2638/2483/2728 → draft. `LIW aktiv: nein`.
`Core aktiv: ja`, `StagingGate overall: warning` (Dev-üblich, kein critical), Startseite 200, wp-login 200, keine Fatals/
Warnings im Container-Log. `administrator`: 12 `liw_*`-Caps verbleiben (dokumentiertes Verhalten). Browser-Sichtprüfung: ☐ JW.

## 5. Wieder einschalten (nach der Migration, Local → Staging → Produktiv)

`wp plugin activate liebherr-interface-world` → `activate()`: `create_tables()` (dbDelta, idempotent), Caps, Partner-Rolle,
`liw_installed_version`, Rewrite-Flush. Trägerseiten wieder veröffentlichen. Daten aus Abschnitt 3 sind dann sofort
wieder sichtbar. Voraussetzung Core ≥ alpha.718 (Wallet W1) für Plattformzeit-Buchung; alle anderen Bridges tolerieren
ältere Cores per Guard.

## 6. Annahmen (ANNAHME-LIW-ISO-1…3)

1. Liebherr-Daten (Tabellen/Optionen/CPT-Posts) **bleiben in der Dev-DB** und wandern mit ins Paket (Plugin aus → inert).
   Alternative „vorher bereinigen" nur auf Auftrag (Löschskript wäre neu zu schreiben – kein `uninstall.php`).
2. Trägerseiten werden auf Entwurf gesetzt statt gelöscht (rückgängig machbar).
3. Rolle `liw_partner` bleibt bestehen (kein Benutzer verliert seinen Zugang; Rolle ohne Caps ist wirkungslos).
