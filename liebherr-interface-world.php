<?php
/**
 * Plugin Name:  Liebherr Interface Solutions – Interface World Connections
 * Plugin URI:   https://araliya.info
 * Description:  Administrierbare, mehrsprachige Landingpage "Interface World Connections" für Liebherr-Händler-,
 *               Lieferanten- und Kundenanbindung (Magic Cube, Interface LogiQ). Solution Provider: GoHeal.
 * Version:      0.1.0-alpha.1
 * Author:       GoHeal
 * Author URI:   https://araliya.info
 * Requires at least: 6.0
 * Requires PHP: 7.4
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
define( 'LIW_VERSION', '0.1.0-alpha.1' );
define( 'LIW_PATH', plugin_dir_path( __FILE__ ) );
define( 'LIW_URL', plugin_dir_url( __FILE__ ) );
define( 'LIW_BASENAME', plugin_basename( __FILE__ ) );

/** Namespace-Präfix des Core-Plugins, dessen Anwesenheit Voraussetzung ist (Variante A). */
const CORE_DEPENDENCY_CLASS = 'Araliya\\Platform\\Core\\Core\\ModuleInterface';

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
 */
function core_is_available(): bool {
	return class_exists( CORE_DEPENDENCY_CLASS );
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

	Interfaces\InterfaceCatalogSchema::create_table();
	Simulation\SimulationSchema::create_tables();
	Connection\ConnectionSchema::create_table();
	Consent\ConsentLogSchema::create_table();

	CPT\LiwSectionCpt::register();
	CoreBridge\RoleBridge::grant_capabilities();

	// Permalink-Hinweis (CLAUDE.md DoD Punkt 7): CPT-Rewrite-Regeln erfordern Flush.
	update_option( 'liw_flush_rewrite_needed', '1' );
}

/** Deaktivierung: Rollen-Capabilities entfernen, Rewrite-Regeln zurücksetzen. Keine Datenlöschung (Kategorie A). */
function deactivate(): void {
	CoreBridge\RoleBridge::revoke_capabilities();
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

	Bootstrap::init();
}, 20 ); // Prio 20: nach dem Bootstrap von araliya-platform-core.
