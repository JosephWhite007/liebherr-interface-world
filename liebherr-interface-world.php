<?php
/**
 * Plugin Name:  Liebherr Interface Solutions – Interface World Connections
 * Plugin URI:   https://araliya.info
 * Description:  Administrierbare, mehrsprachige Landingpage "Interface World Connections" für Liebherr-Händler-,
 *               Lieferanten- und Kundenanbindung (Magic Cube, Interface LogiQ). Solution Provider: GoHeal.
 * Version:      0.1.0-alpha.100
 * Author:       GoHeal
 * Author URI:   https://araliya.info
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Requires Plugins: araliya-platform-core
 * Text Domain:  liebherr-interface-world
 *
 * Eigenständiges Plugin (Ausnahme von der Ein-Plugin-Regel, zweite Ausnahme neben `araliya-installer`;
 * Entscheidung Joseph White, 17.09.2026 – siehe araliya-platform-core/docs/LOGBUCH_TECHNIK.md).
 * Nutzt araliya-platform-core als Laufzeit-Abhängigkeit (Variante A, Entscheidung JW 17.09.2026):
 * Übersetzung, Rollen, Audit, SEO/Locale-Routing und Media laufen über den CoreBridge-Adapter (src/CoreBridge/)
 * gegen die bestehenden Core-Services, statt sie nachzubauen.
 *
 * @package Liebherr\InterfaceWorld
 * @since   0.1.0-alpha.1
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld;

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ── Konstanten ────────────────────────────────────────────────────────────────
define( 'LIW_VERSION', '0.1.0-alpha.100' );
define( 'LIW_PATH', plugin_dir_path( __FILE__ ) );
define( 'LIW_URL', plugin_dir_url( __FILE__ ) );
define( 'LIW_BASENAME', plugin_basename( __FILE__ ) );

/** Plugin-Datei (Slug) des Core-Plugins, dessen Aktivierung Voraussetzung ist (Variante A). */
const CORE_DEPENDENCY_PLUGIN_FILE = 'araliya-platform-core/araliya-platform-core.php';

/** Marker-Interface im Core, das nur bei geladenem Core existiert (Fallback-Check). */
const CORE_DEPENDENCY_INTERFACE = 'Araliya\\Platform\\Core\\Core\\ModuleInterface';

// ── PSR-4 Autoloader (Namespace: Liebherr\InterfaceWorld\ → src/) ────────────
spl_autoload_register( static function ( string $class ): void {
	$prefix   = __NAMESPACE__ . '\\';
	$base_dir = LIW_PATH . 'src/';
	if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
		return;
	}
	$relative = substr( $class, strlen( $prefix ) );
	$file     = $base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative ) . '.php';
	if ( file_exists( $file ) ) {
		require $file;
	}
} );

/**
 * Prüft, ob araliya-platform-core aktiv und verfügbar ist.
 * Terminal-/Nichtziel-Vorgabe: Graceful Degradation statt Fatal Error, wenn Core fehlt.
 *
 * Bugfix (2026-09-17): Der ursprüngliche Check `class_exists(ModuleInterface)` lieferte
 * IMMER `false`, weil `ModuleInterface` im Core als `interface`, nicht als `class`
 * deklariert ist – `class_exists()` matcht keine Interfaces (`interface_exists()` wäre
 * nötig gewesen). Dadurch schlug die Aktivierung reproduzierbar fehl, obwohl Core aktiv
 * war (siehe docs/LOGBUCH_TECHNIK.md). Robuster Fix: WordPress' native
 * `is_plugin_active()` gegen den Plugin-Slug prüfen, mit `interface_exists()` als
 * zusätzlichem Fallback (z. B. falls der Slug künftig abweicht).
 */
function core_is_available(): bool {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( is_plugin_active( CORE_DEPENDENCY_PLUGIN_FILE ) ) {
		return true;
	}

	// Fallback, z. B. während der eigenen Aktivierung von Core selbst (Multisite-Netzwerkaktivierung o. Ä.).
	return interface_exists( CORE_DEPENDENCY_INTERFACE );
}

/** Admin-Hinweis, falls Core fehlt oder deaktiviert ist. */
function core_missing_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>';
	echo esc_html__(
		'Liebherr Interface Solutions benötigt das aktive Plugin "ARALIYA Platform Core" (Übersetzung, Rollen, Audit, SEO). Das Plugin bleibt inaktiv, solange der Core fehlt.',
		'liebherr-interface-world'
	);
	echo '</p></div>';
}

/**
 * Legt/aktualisiert alle DB-Tabellen des Plugins an. `dbDelta()` ist idempotent – sicher
 * sowohl bei Erstaktivierung als auch bei jedem `maybe_upgrade_database()`-Aufruf.
 */
function create_tables(): void {
	Interfaces\InterfaceCatalogSchema::create_table();
	Simulation\SimulationSchema::create_tables();
	Connection\ConnectionSchema::create_table();
	Consent\ConsentLogSchema::create_table();
	Onboarding\OnboardingSchema::create_table();
	Contact\ContactSchema::create_table();
	Partner\PartnerDocumentSchema::create_table();
	IntelligenceWorld\Schema::create_tables(); // Intelligence World – Sitzungen + Ereignis-Ledger (Pflichtenheft-2 §12).
	Adventures\TokenSchema::create_table();    // Adventures – Token-/Registrierungs-Ledger (Basislogik §5/§6/§9).
	Cvf\Schema::create_tables();               // Customer View Flow – Workflow-Version + Sitzung + Execution-Log (ADR-LIW-CVF-001).
	Cvf\BoardSchema::create_tables();          // CAPDB – Board/Plugin-Tabellen (ADR-LIW-CVF-002, §30).
}

/**
 * Selbstheilender Schema-Abgleich (Bugfix 2026-09-18): DB-Tabellen wurden bisher nur im
 * Aktivierungshook angelegt. Neue Tabellen aus einem späteren Update (z. B. `liw_partner_extra`
 * in alpha.6) blieben dadurch fehlend, bis das Plugin einmal deaktiviert und reaktiviert wurde
 * – dieselbe Klasse von Problem wie beim Capability-Bugfix in alpha.3 (Docker-Dev-Umgebungen
 * mit OPcache sind hierfür besonders anfällig, ein reiner Datei-Update reicht dort nicht).
 * Läuft bei jedem Request einmal pro tatsächlicher Versionsänderung (Options-Vergleich, kein
 * Overhead im Normalbetrieb).
 */
function maybe_upgrade_database(): void {
	if ( get_option( 'liw_installed_version' ) === LIW_VERSION ) {
		return;
	}

	create_tables();
	CoreBridge\RoleBridge::ensure_partner_role(); // Rollen/Caps-Abgleich analog zum Schema (alpha.21).
	Cvf\Roles::grant();                           // CVF-Caps an bestehende ARALIYA-Rollen (selbstheilend, alpha.85).
	Cvf\PluginRegistry::sync();                   // CAPDB – Plugin-Typen registrieren (idempotent, alpha.90).
	update_option( 'liw_installed_version', LIW_VERSION );
}

/** Aktivierung: Core-Voraussetzung prüfen, DB-Tabellen anlegen, CPT-Rewrite-Regeln vormerken. */
function activate(): void {
	if ( ! core_is_available() ) {
		deactivate_plugins( LIW_BASENAME );
		wp_die(
			esc_html__( 'Liebherr Interface Solutions konnte nicht aktiviert werden: ARALIYA Platform Core ist nicht aktiv.', 'liebherr-interface-world' ),
			esc_html__( 'Aktivierung fehlgeschlagen', 'liebherr-interface-world' ),
			[ 'back_link' => true ]
		);
	}

	create_tables();
	update_option( 'liw_installed_version', LIW_VERSION );

	CPT\LiwSectionCpt::register();
	CoreBridge\RoleBridge::grant_capabilities();
	Cvf\Roles::grant(); // CVF-Rollen-Mapping (JW-Entscheid §9.1) auf bestehende ARALIYA-Rollen.
	Cvf\PluginRegistry::sync(); // CAPDB – Plugin-Typen registrieren.

	// Permalink-Hinweis (CLAUDE.md DoD Punkt 7): CPT-Rewrite-Regeln erfordern Flush.
	update_option( 'liw_flush_rewrite_needed', '1' );
}

/** Deaktivierung: Rollen-Capabilities entfernen, Rewrite-Regeln zurücksetzen. Keine Datenlöschung (Kategorie A). */
function deactivate(): void {
	CoreBridge\RoleBridge::revoke_capabilities();
	Cvf\Roles::revoke();
	Contact\ContactRetention::unschedule();
	flush_rewrite_rules();
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );

add_action( 'plugins_loaded', static function (): void {
	load_plugin_textdomain( 'liebherr-interface-world', false, dirname( LIW_BASENAME ) . '/languages' );

	if ( ! core_is_available() ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\\core_missing_notice' );
		return;
	}

	maybe_upgrade_database();
	Bootstrap::init();
}, 20 ); // Prio 20: nach dem Bootstrap von araliya-platform-core.
