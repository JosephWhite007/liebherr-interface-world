<?php
declare( strict_types = 1 );

/**
 * Liebherr Interface Solutions – Integrations-Selbsttest (Docker-Praxistest)
 *
 * Prüft alle sechs Boards (Interface, Simulation, World Connections – Datenpflege und
 * Frontend, Onboarding, Media) sowie die Nachvollziehbarkeits-Reiter (Programmierlogbuch,
 * To-Dos) end-to-end in der ECHTEN WordPress-Umgebung (CLAUDE.md DoD Punkt 4: „tatsächlich
 * geprüft, nicht nur müsste gehen"). Ergänzt `tests/run-tests.php` (Syntax/Statuslogik ohne
 * WP) um die DB-/Hook-/Shortcode-gebundenen Abläufe, analog zu Core's
 * `scripts/yb-selftest.php`.
 *
 * Testdaten sind mit SELFTEST- präfigiert und werden am Ende vollständig entfernt
 * (Testdaten, keine echten Fachdaten – CLAUDE.md Kategorie B). Läuft nur in
 * WP_ENVIRONMENT_TYPE=development.
 *
 * Aufruf (Terminal-Regel):
 *   docker exec araliya_wordpress php /var/www/html/wp-content/plugins/liebherr-interface-world/scripts/liw-selftest.php
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( "Nur über CLI ausführbar.\n" );
}
require_once dirname( __DIR__, 4 ) . '/wp-load.php';

if ( 'development' !== wp_get_environment_type() ) {
	exit( "Abbruch: Selbsttest nur in WP_ENVIRONMENT_TYPE=development.\n" );
}

use Liebherr\InterfaceWorld\Admin\AdminPagination;
use Liebherr\InterfaceWorld\Connection\ConnectionSchema;
use Liebherr\InterfaceWorld\Connection\ConnectionService;
use Liebherr\InterfaceWorld\Consent\ConsentLogSchema;
use Liebherr\InterfaceWorld\Consent\ConsentLogService;
use Liebherr\InterfaceWorld\CoreBridge\MarkdownBridge;
use Liebherr\InterfaceWorld\CoreBridge\MediaBridge;
use Liebherr\InterfaceWorld\CoreBridge\PartnerBridge;
use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;
use Liebherr\InterfaceWorld\Interfaces\InterfaceCatalogSchema;
use Liebherr\InterfaceWorld\Interfaces\InterfaceCatalogService;
use Liebherr\InterfaceWorld\Onboarding\OnboardingSchema;
use Liebherr\InterfaceWorld\Onboarding\OnboardingService;
use Liebherr\InterfaceWorld\Simulation\SimulationSchema;
use Liebherr\InterfaceWorld\Simulation\SimulationService;

$run    = 'SELFTEST-' . strtoupper( substr( wp_generate_password( 12, false ), 0, 8 ) );
$passed = 0;
$failed = 0;

/** @param mixed $detail */
function liw_st_check( string $name, bool $condition, string $detail = '' ): void {
	global $passed, $failed;
	if ( $condition ) {
		$passed++;
		echo "  ✓ {$name}\n";
		return;
	}
	$failed++;
	echo "  ✗ {$name}" . ( '' !== $detail ? " – {$detail}" : '' ) . "\n";
}

echo "Liebherr Interface Solutions – Integrations-Selbsttest {$run}\n";
echo 'Start: ' . current_time( 'mysql' ) . "\n";

// Aufräum-Register: wird am Ende garantiert (auch bei Abbruch per Exception) abgearbeitet.
$cleanup_interface_ids  = [];
$cleanup_world_ids      = [];
$cleanup_scenario_ids   = [];
$cleanup_connection_ids = [];
$cleanup_partner_ids    = [];

try {
	global $wpdb;

	// ── [0] Vorbedingungen ────────────────────────────────────────────────────
	echo "\n[0] Vorbedingungen\n";
	liw_st_check( 'Plugin-Konstanten definiert (LIW_VERSION/LIW_PATH)', defined( 'LIW_VERSION' ) && defined( 'LIW_PATH' ) );
	liw_st_check(
		'DB-Schema auf aktueller Version (liw_installed_version = LIW_VERSION)',
		get_option( 'liw_installed_version' ) === LIW_VERSION,
		(string) get_option( 'liw_installed_version' ) . ' ≠ ' . ( defined( 'LIW_VERSION' ) ? LIW_VERSION : '?' )
	);
	foreach (
		[
			'liw_interface'        => InterfaceCatalogSchema::table_name(),
			'liw_simulation_world' => SimulationSchema::worlds_table(),
			'liw_test_scenario'    => SimulationSchema::scenarios_table(),
			'liw_connection'       => ConnectionSchema::table_name(),
			'liw_consent_log'      => ConsentLogSchema::table_name(),
			'liw_partner_extra'    => OnboardingSchema::table_name(),
		] as $label => $table
	) {
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
		liw_st_check( "Tabelle vorhanden: {$label}", $exists );
	}

	// ── [1] Capabilities ──────────────────────────────────────────────────────
	echo "\n[1] Rollen/Capabilities\n";
	$admin_role = get_role( 'administrator' );
	liw_st_check( 'administrator hat liw_manage_interfaces', $admin_role instanceof WP_Role && $admin_role->has_cap( RoleBridge::CAP_MANAGE_INTERFACES ) );
	liw_st_check( 'administrator hat liw_manage_content', $admin_role instanceof WP_Role && $admin_role->has_cap( RoleBridge::CAP_MANAGE_CONTENT ) );
	liw_st_check( 'administrator hat liw_view_onboarding', $admin_role instanceof WP_Role && $admin_role->has_cap( RoleBridge::CAP_VIEW_ONBOARDING ) );

	// ── [2] Interface Board ───────────────────────────────────────────────────
	echo "\n[2] Interface Board (§18)\n";
	$iface_id = InterfaceCatalogService::create( [ 'code' => strtolower( $run ) . '-if', 'name' => "{$run} Schnittstelle", 'direction' => 'bidirectional' ], 1 );
	liw_st_check( 'Schnittstelle angelegt', ! is_wp_error( $iface_id ), is_wp_error( $iface_id ) ? $iface_id->get_error_message() : '' );
	if ( ! is_wp_error( $iface_id ) ) {
		$cleanup_interface_ids[] = $iface_id;
		$all = InterfaceCatalogService::get_all();
		liw_st_check( 'get_all() enthält neue Schnittstelle', in_array( $iface_id, array_column( $all, 'id' ), true ) );
		liw_st_check( 'öffentlicher Katalog zeigt draft-Schnittstelle NICHT', ! in_array( strtolower( $run ) . '-if', array_column( InterfaceCatalogService::get_public_catalog(), 'code' ), true ) );
		InterfaceCatalogService::set_lifecycle_status( $iface_id, 'approved', 1 );
		liw_st_check( 'öffentlicher Katalog zeigt approved-Schnittstelle', in_array( strtolower( $run ) . '-if', array_column( InterfaceCatalogService::get_public_catalog(), 'code' ), true ) );
	}

	// ── [3] Simulation Board ──────────────────────────────────────────────────
	echo "\n[3] Simulation Board (Magic Cube, §17)\n";
	$world_id = SimulationService::create_world( [ 'name' => "{$run} Welt" ], 1 );
	liw_st_check( 'Simulationswelt angelegt', ! is_wp_error( $world_id ), is_wp_error( $world_id ) ? $world_id->get_error_message() : '' );
	if ( ! is_wp_error( $world_id ) ) {
		$cleanup_world_ids[] = $world_id;
		liw_st_check( 'get_worlds() enthält neue Welt', in_array( $world_id, array_column( SimulationService::get_worlds(), 'id' ), true ) );

		$scenario_id = SimulationService::add_scenario( [ 'world_id' => $world_id, 'category' => 'connectivity' ], 1 );
		liw_st_check( 'Testszenario angelegt', ! is_wp_error( $scenario_id ), is_wp_error( $scenario_id ) ? $scenario_id->get_error_message() : '' );
		if ( ! is_wp_error( $scenario_id ) ) {
			$cleanup_scenario_ids[] = $scenario_id;
			liw_st_check( 'get_scenarios_for_world() enthält neues Szenario', in_array( $scenario_id, array_column( SimulationService::get_scenarios_for_world( $world_id ), 'id' ), true ) );
		}
	}

	// ── [4] World Connections Map – Datenpflege + Frontend ───────────────────
	echo "\n[4] World Connections Map – Datenpflege (§17/LP-06) + Frontend\n";
	$region      = "{$run}-Region";
	$conn_id = ConnectionService::create( [ 'region' => $region, 'partner_type' => 'dealer', 'public_flag' => false ], 1 );
	liw_st_check( 'Verbindung angelegt', ! is_wp_error( $conn_id ), is_wp_error( $conn_id ) ? $conn_id->get_error_message() : '' );
	if ( ! is_wp_error( $conn_id ) ) {
		$cleanup_connection_ids[] = $conn_id;
		liw_st_check( 'nicht-öffentliche Verbindung fehlt in get_public()', ! in_array( $region, array_column( ConnectionService::get_public(), 'region' ), true ) );

		ConnectionService::set_public_flag( $conn_id, true, 1 );
		liw_st_check( 'freigegebene Verbindung erscheint in get_public()', in_array( $region, array_column( ConnectionService::get_public(), 'region' ), true ) );

		ConnectionService::set_display_status( $conn_id, 'active', 1 );
		liw_st_check( 'Anzeigestatus aktualisiert', 'active' === ( ConnectionService::get( $conn_id )['display_status'] ?? null ) );

		$shortcode_html = do_shortcode( '[liw_world_connections_map]' );
		liw_st_check( 'Shortcode [liw_world_connections_map] rendert Region', false !== strpos( $shortcode_html, esc_html( $region ) ) );
		liw_st_check( 'Shortcode-Wrapper-Klasse liw-connections-map vorhanden', false !== strpos( $shortcode_html, 'liw-connections-map' ) );

		ConnectionService::delete( $conn_id, 1 );
		liw_st_check( 'Verbindung nach delete() nicht mehr auffindbar', null === ConnectionService::get( $conn_id ) );
		$cleanup_connection_ids = array_diff( $cleanup_connection_ids, [ $conn_id ] ); // bereits gelöscht.
	}

	// ── [5] Onboarding-Formular (§22) ────────────────────────────────────────
	echo "\n[5] Onboarding-Formular (§22)\n";
	$rejected = OnboardingService::submit_request( [ 'name' => "{$run} GmbH", 'contact_email' => 'selftest@example.invalid', 'privacy_consent' => false ] );
	liw_st_check( 'Anfrage ohne Datenschutz-Einwilligung wird abgelehnt', is_wp_error( $rejected ) );

	$partner_id = OnboardingService::submit_request( [
		'name'             => "{$run} GmbH",
		'contact_email'    => 'selftest@example.invalid',
		'liw_partner_type' => 'supplier',
		'privacy_consent'  => true,
	] );
	liw_st_check( 'Onboarding-Anfrage angelegt', ! is_wp_error( $partner_id ), is_wp_error( $partner_id ) ? $partner_id->get_error_message() : '' );

	if ( ! is_wp_error( $partner_id ) ) {
		$cleanup_partner_ids[] = $partner_id;

		$core_partner = PartnerBridge::get( $partner_id );
		liw_st_check( 'ary_partners.partner_type bleibt neutral (general)', 'general' === ( $core_partner['partner_type'] ?? null ), (string) ( $core_partner['partner_type'] ?? 'null' ) );

		$extra = OnboardingService::get_extra( $partner_id );
		liw_st_check( 'liw_partner_extra.liw_partner_type = supplier', 'supplier' === ( $extra['liw_partner_type'] ?? null ) );

		liw_st_check( 'Datenschutz-Einwilligung protokolliert', ConsentLogService::has_consent( $partner_id, ConsentLogService::TYPE_PRIVACY ) );
		liw_st_check( 'Marketing-Einwilligung NICHT protokolliert (nicht angekreuzt)', ! ConsentLogService::has_consent( $partner_id, ConsentLogService::TYPE_MARKETING ) );

		OnboardingService::set_status( $partner_id, 'approved', 1 );
		$core_partner_after = PartnerBridge::get( $partner_id );
		liw_st_check( 'Freigabe spiegelt ary_partners.status auf active', 'active' === ( $core_partner_after['status'] ?? null ), (string) ( $core_partner_after['status'] ?? 'null' ) );

		liw_st_check( 'count_all_requests() >= 1', OnboardingService::count_all_requests() >= 1 );
		liw_st_check( 'get_all_requests() Seite 1 liefert Zeilen', [] !== OnboardingService::get_all_requests( 1 ) );

		$shortcode_html = do_shortcode( '[liw_onboarding_form]' );
		liw_st_check( 'Shortcode [liw_onboarding_form] rendert Formular', false !== strpos( $shortcode_html, 'liw-onboarding-form' ) );
		liw_st_check( 'Honeypot-Feld liw_hp_website vorhanden', false !== strpos( $shortcode_html, 'liw_hp_website' ) );
		liw_st_check( 'Honeypot in .liw-visually-hidden verpackt', false !== strpos( $shortcode_html, 'liw-visually-hidden' ) );
	}

	// ── [6] Media Board (§18) ─────────────────────────────────────────────────
	echo "\n[6] Media Board – CI-005-Freigabe\n";
	$attachment_id = wp_insert_post( [
		'post_type'   => 'attachment',
		'post_title'  => "{$run} Test-Medium",
		'post_status' => 'inherit',
	], true );
	liw_st_check( 'Test-Anhang angelegt', ! is_wp_error( $attachment_id ) && $attachment_id > 0 );
	if ( ! is_wp_error( $attachment_id ) && $attachment_id > 0 ) {
		update_post_meta( $attachment_id, MediaBridge::META_APPROVED, '0' );
		liw_st_check( 'is_approved() = false vor Freigabe', ! MediaBridge::is_approved( $attachment_id ) );
		update_post_meta( $attachment_id, MediaBridge::META_APPROVED, '1' );
		liw_st_check( 'is_approved() = true nach Freigabe', MediaBridge::is_approved( $attachment_id ) );
		wp_delete_post( $attachment_id, true );
	}
	liw_st_check( 'MediaBridge::add_fields() an attachment_fields_to_edit registriert', false !== has_filter( 'attachment_fields_to_edit', [ MediaBridge::class, 'add_fields' ] ) );
	liw_st_check( 'MediaBridge::save_fields() an attachment_fields_to_save registriert', false !== has_filter( 'attachment_fields_to_save', [ MediaBridge::class, 'save_fields' ] ) );

	// ── [7] Design System / Assets ────────────────────────────────────────────
	echo "\n[7] Design-System-Anbindung + Admin-Assets\n";
	liw_st_check( 'assets/css/liebherr-frontend.css vorhanden', is_readable( LIW_PATH . 'assets/css/liebherr-frontend.css' ) );
	liw_st_check( 'assets/css/liebherr-admin.css vorhanden', is_readable( LIW_PATH . 'assets/css/liebherr-admin.css' ) );
	liw_st_check( 'AdminPagination-Klasse verfügbar', class_exists( AdminPagination::class ) );

	// ── [8] Programmierlogbuch / To-Dos (Nachvollziehbarkeit) ────────────────
	echo "\n[8] Programmierlogbuch / To-Dos\n";
	liw_st_check( 'docs/LIW_PROGRAMMIERLOGBUCH.md vorhanden', is_readable( LIW_PATH . 'docs/LIW_PROGRAMMIERLOGBUCH.md' ) );
	liw_st_check( 'docs/LIW_TODO.md vorhanden', is_readable( LIW_PATH . 'docs/LIW_TODO.md' ) );
	liw_st_check( 'CoreBridge\\MarkdownBridge verfügbar (Core-Renderer)', MarkdownBridge::is_available() );
	if ( MarkdownBridge::is_available() ) {
		$rendered = MarkdownBridge::render( "## Test\n\n- Punkt A\n- Punkt B\n" );
		liw_st_check( 'MarkdownBridge::render() liefert HTML', false !== strpos( $rendered, '<h2' ) || false !== strpos( $rendered, '<li' ) );
	}

	// ── [9] Permalink-Hinweis (Info, kein Fehlschlag) ─────────────────────────
	echo "\n[9] Hinweis\n";
	echo '  ℹ Permalink-Flush-Flag (liw_flush_rewrite_needed): ' . ( get_option( 'liw_flush_rewrite_needed' ) ? 'gesetzt – bitte Einstellungen → Permalinks → Speichern prüfen' : 'nicht gesetzt' ) . "\n";

} finally {
	// ── Aufräumen: alle SELFTEST-Daten entfernen, unabhängig vom Testergebnis ──
	echo "\n[Aufräumen]\n";
	global $wpdb;

	foreach ( $cleanup_scenario_ids as $id ) {
		$wpdb->delete( SimulationSchema::scenarios_table(), [ 'id' => $id ] );
	}
	foreach ( $cleanup_world_ids as $id ) {
		$wpdb->delete( SimulationSchema::worlds_table(), [ 'id' => $id ] );
	}
	foreach ( $cleanup_interface_ids as $id ) {
		$wpdb->delete( InterfaceCatalogSchema::table_name(), [ 'id' => $id ] );
	}
	foreach ( $cleanup_connection_ids as $id ) {
		$wpdb->delete( ConnectionSchema::table_name(), [ 'id' => $id ] );
	}
	foreach ( $cleanup_partner_ids as $partner_id ) {
		$wpdb->delete( ConsentLogSchema::table_name(), [ 'request_id' => $partner_id ] );
		$wpdb->delete( OnboardingSchema::table_name(), [ 'partner_id' => $partner_id ] );
		$wpdb->delete( $wpdb->prefix . 'ary_partners', [ 'id' => $partner_id ] );
	}
	echo "  Testdaten entfernt (Präfix {$run}).\n";
}

echo "\n== Ergebnis: {$passed} bestanden, {$failed} fehlgeschlagen ==\n";
exit( $failed > 0 ? 1 : 0 );
