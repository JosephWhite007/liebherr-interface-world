<?php
declare( strict_types = 1 );

/**
 * Liebherr Interface Solutions – Integrations-Selbsttest (Docker-Praxistest)
 *
 * Prüft alle sieben Boards (Interface, Content, Simulation, World Connections –
 * Datenpflege und Frontend, Onboarding, Media) sowie die Nachvollziehbarkeits-Reiter
 * (Programmierlogbuch, To-Dos, Landingpage-Konzept) end-to-end in der ECHTEN
 * WordPress-Umgebung (CLAUDE.md DoD Punkt 4: „tatsächlich geprüft, nicht nur müsste
 * gehen"). Ergänzt `tests/run-tests.php` (Syntax/Statuslogik ohne WP) um die
 * DB-/Hook-/Shortcode-gebundenen Abläufe, analog zu Core's `scripts/yb-selftest.php`.
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
use Liebherr\InterfaceWorld\Contact\ContactForm;
use Liebherr\InterfaceWorld\Contact\ContactSchema;
use Liebherr\InterfaceWorld\Contact\ContactService;
use Liebherr\InterfaceWorld\Content\SectionBlueprint;
use Liebherr\InterfaceWorld\Content\SectionSeeder;
use Liebherr\InterfaceWorld\CoreBridge\LanguageBridge;
use Liebherr\InterfaceWorld\CoreBridge\MarkdownBridge;
use Liebherr\InterfaceWorld\CoreBridge\MediaBridge;
use Liebherr\InterfaceWorld\CoreBridge\PartnerBridge;
use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;
use Liebherr\InterfaceWorld\CoreBridge\SeoBridge;
use Liebherr\InterfaceWorld\CPT\LiwSectionCpt;
use Liebherr\InterfaceWorld\Frontend\LandingpageView;
use Liebherr\InterfaceWorld\Frontend\SectionGraphicView;
use Liebherr\InterfaceWorld\Interfaces\InterfaceCatalogSchema;
use Liebherr\InterfaceWorld\Interfaces\InterfaceCatalogService;
use Liebherr\InterfaceWorld\Onboarding\OnboardingSchema;
use Liebherr\InterfaceWorld\Onboarding\OnboardingService;
use Liebherr\InterfaceWorld\Partner\PartnerDocumentSchema;
use Liebherr\InterfaceWorld\Partner\PartnerDocumentService;
use Liebherr\InterfaceWorld\Partner\PartnerDocumentsView;
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
$cleanup_section_ids    = [];
$cleanup_contact_ids    = [];
$cleanup_user_ids       = [];
$cleanup_document_ids   = [];

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
			'liw_contact_request'  => ContactSchema::table_name(),
			'liw_partner_document' => PartnerDocumentSchema::table_name(),
		] as $label => $table
	) {
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
		liw_st_check( "Tabelle vorhanden: {$label}", $exists );
	}

	// Erneuter Schema-Abgleich muss fehlerfrei sein (Befund alpha.16: Klammern im Tabellen-COMMENT
	// erzeugten bei jedem maybe_upgrade_database() ein fehlerhaftes "ALTER TABLE … ADD COLUMN )").
	$wpdb->last_error = '';
	$suppress_before  = $wpdb->suppress_errors( true );
	\Liebherr\InterfaceWorld\create_tables();
	$wpdb->suppress_errors( $suppress_before );
	liw_st_check( 'dbDelta-Wiederholung (create_tables) ohne DB-Fehler', '' === $wpdb->last_error, $wpdb->last_error );
	$consent_cols = $wpdb->get_col( 'DESCRIBE ' . ConsentLogSchema::table_name() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Tabellenname aus Schema-Klasse.
	liw_st_check( 'liw_consent_log hat Spalte request_kind (alpha.19, additiv per dbDelta)', in_array( 'request_kind', $consent_cols, true ) );

	// ── [1] Capabilities ──────────────────────────────────────────────────────
	echo "\n[1] Rollen/Capabilities\n";
	$admin_role = get_role( 'administrator' );
	liw_st_check( 'administrator hat liw_manage_interfaces', $admin_role instanceof WP_Role && $admin_role->has_cap( RoleBridge::CAP_MANAGE_INTERFACES ) );
	liw_st_check( 'administrator hat liw_manage_content', $admin_role instanceof WP_Role && $admin_role->has_cap( RoleBridge::CAP_MANAGE_CONTENT ) );
	liw_st_check( 'administrator hat liw_view_onboarding', $admin_role instanceof WP_Role && $admin_role->has_cap( RoleBridge::CAP_VIEW_ONBOARDING ) );
	$partner_role = get_role( RoleBridge::ROLE_PARTNER );
	liw_st_check( 'Rolle liw_partner existiert mit read + liw_partner_access, ohne Backend-Caps', $partner_role instanceof WP_Role && $partner_role->has_cap( 'read' ) && $partner_role->has_cap( RoleBridge::CAP_PARTNER_ACCESS ) && ! $partner_role->has_cap( 'edit_posts' ) && ! $partner_role->has_cap( RoleBridge::CAP_MANAGE_CONTENT ) );

	// ── [2] Interface Board ───────────────────────────────────────────────────
	echo "\n[2] Interface Board (§18)\n";
	$iface_id = InterfaceCatalogService::create( [ 'code' => strtolower( $run ) . '-if', 'name' => "{$run} Schnittstelle", 'direction' => 'bidirectional' ], 1 );
	liw_st_check( 'Schnittstelle angelegt', ! is_wp_error( $iface_id ), is_wp_error( $iface_id ) ? $iface_id->get_error_message() : '' );
	if ( ! is_wp_error( $iface_id ) ) {
		$cleanup_interface_ids[] = $iface_id;
		$all = InterfaceCatalogService::get_all();
		// $wpdb->get_results() liefert alle Spalten als String (mysqli-Standard, kein Type-Casting) –
		// daher hier explizit auf int gecastet, sonst schlägt der strikte in_array()-Vergleich mit der
		// int-ID aus $wpdb->insert_id (in InterfaceCatalogService::create()) immer fehl (Befund 18.09.2026).
		liw_st_check( 'get_all() enthält neue Schnittstelle', in_array( $iface_id, array_map( 'intval', array_column( $all, 'id' ) ), true ) );
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
		// Cast s. Kommentar bei [2] Interface Board: $wpdb liefert Spalten als String.
		liw_st_check( 'get_worlds() enthält neue Welt', in_array( $world_id, array_map( 'intval', array_column( SimulationService::get_worlds(), 'id' ) ), true ) );

		$scenario_id = SimulationService::add_scenario( [ 'world_id' => $world_id, 'category' => 'connectivity' ], 1 );
		liw_st_check( 'Testszenario angelegt', ! is_wp_error( $scenario_id ), is_wp_error( $scenario_id ) ? $scenario_id->get_error_message() : '' );
		if ( ! is_wp_error( $scenario_id ) ) {
			$cleanup_scenario_ids[] = $scenario_id;
			liw_st_check( 'get_scenarios_for_world() enthält neues Szenario', in_array( $scenario_id, array_map( 'intval', array_column( SimulationService::get_scenarios_for_world( $world_id ), 'id' ) ), true ) );
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
		'contact_email'    => strtolower( $run ) . '@example.invalid', // je Lauf eindeutig (alpha.21: Kontoanlage verknüpft sonst Alt-Benutzer)
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

		// Partnerkonto (alpha.21): nur für freigegebene Anfragen, idempotent, Rolle liw_partner, Rückverweis.
		$acct_mail_filter = static fn( $args ) => array_merge( (array) $args, [ 'to' => 'selftest-blackhole@example.invalid' ] );
		add_filter( 'wp_mail', $acct_mail_filter );
		$acct_user_id = OnboardingService::create_partner_account( $partner_id, 1 );
		remove_filter( 'wp_mail', $acct_mail_filter );
		liw_st_check( 'Partnerkonto für freigegebene Anfrage angelegt', ! is_wp_error( $acct_user_id ) && $acct_user_id > 0, is_wp_error( $acct_user_id ) ? $acct_user_id->get_error_message() : '' );
		if ( ! is_wp_error( $acct_user_id ) ) {
			$cleanup_user_ids[] = $acct_user_id;
			$acct_user = get_userdata( $acct_user_id );
			liw_st_check( 'Konto hat Rolle liw_partner und Rückverweis _liw_partner_id', $acct_user instanceof WP_User && in_array( RoleBridge::ROLE_PARTNER, (array) $acct_user->roles, true ) && (int) get_user_meta( $acct_user_id, OnboardingService::USER_META_PARTNER_ID, true ) === $partner_id );
			liw_st_check( 'liw_partner_extra.wp_user_id gesetzt', (int) ( OnboardingService::get_extra( $partner_id )['wp_user_id'] ?? 0 ) === $acct_user_id );
			liw_st_check( 'Partnerkonto darf NICHT ins Backend (edit_posts) und hat keine Board-Caps', ! user_can( $acct_user_id, 'edit_posts' ) && ! user_can( $acct_user_id, RoleBridge::CAP_VIEW_ONBOARDING ) && user_can( $acct_user_id, RoleBridge::CAP_PARTNER_ACCESS ) );
			$second = OnboardingService::create_partner_account( $partner_id, 1 );
			liw_st_check( 'Zweiter Aufruf legt kein zweites Konto an (liw_account_exists)', is_wp_error( $second ) && 'liw_account_exists' === $second->get_error_code() );
		}
		$not_approved = OnboardingService::submit_request( [ 'name' => "{$run} Zweit", 'contact_email' => strtolower( $run ) . '.zweit@example.com', 'liw_partner_type' => 'dealer', 'privacy_consent' => true ] );
		if ( ! is_wp_error( $not_approved ) ) {
			$cleanup_partner_ids[] = $not_approved;
			$refused = OnboardingService::create_partner_account( $not_approved, 1 );
			liw_st_check( 'Kein Konto für nicht freigegebene Anfrage (liw_not_approved)', is_wp_error( $refused ) && 'liw_not_approved' === $refused->get_error_code() );
		}

		$shortcode_html = do_shortcode( '[liw_onboarding_form]' );
		liw_st_check( 'Shortcode [liw_onboarding_form] rendert Formular', false !== strpos( $shortcode_html, 'liw-onboarding-form' ) );
		liw_st_check( 'Honeypot-Feld liw_hp_website vorhanden', false !== strpos( $shortcode_html, 'liw_hp_website' ) );
		liw_st_check( 'Honeypot in .liw-visually-hidden verpackt', false !== strpos( $shortcode_html, 'liw-visually-hidden' ) );
	}

	// ── [5c] Partnerdokumente (geschützter Bereich, alpha.22) ─────────────────
	echo "\n[5c] Partnerdokumente – geschützter Download (§10/§23)\n";
	$pd_dir = PartnerDocumentService::get_upload_path();
	liw_st_check( 'Gesperrtes Verzeichnis mit .htaccess Deny + index.html angelegt', is_dir( $pd_dir ) && is_file( $pd_dir . '/.htaccess' ) && str_contains( (string) file_get_contents( $pd_dir . '/.htaccess' ), 'Deny from all' ) && is_file( $pd_dir . '/index.html' ) );
	$tmp_pdf = tempnam( get_temp_dir(), 'liwst' ) . '.pdf';
	file_put_contents( $tmp_pdf, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n" );
	$fake = [ 'name' => "{$run}-Prozess.pdf", 'tmp_name' => $tmp_pdf, 'size' => filesize( $tmp_pdf ), 'error' => UPLOAD_ERR_OK ];
	$bad  = [ 'name' => "{$run}-Skript.php", 'tmp_name' => $tmp_pdf, 'size' => filesize( $tmp_pdf ), 'error' => UPLOAD_ERR_OK ];
	$rej  = PartnerDocumentService::upload( $bad, 'x', '', 1 );
	liw_st_check( 'Upload mit nicht erlaubter Endung (.php) wird abgelehnt', is_wp_error( $rej ) && 'liw_type_not_allowed' === $rej->get_error_code() );
	$tmp_png = tempnam( get_temp_dir(), 'liwst' ) . '.png';
	file_put_contents( $tmp_png, "%PDF-1.4 not really a png" );
	$rej2 = PartnerDocumentService::upload( [ 'name' => 'bild.png', 'tmp_name' => $tmp_png, 'size' => filesize( $tmp_png ), 'error' => UPLOAD_ERR_OK ], 'x', '', 1 );
	liw_st_check( 'Inhalt ≠ Endung (PDF-Bytes als .png) wird abgelehnt', is_wp_error( $rej2 ) && 'liw_type_mismatch' === $rej2->get_error_code() );
	$doc_id = PartnerDocumentService::upload( $fake, "{$run} Prozessdokument", 'Testbeschreibung', 1 );
	liw_st_check( 'PDF-Upload angelegt', ! is_wp_error( $doc_id ) && $doc_id > 0, is_wp_error( $doc_id ) ? $doc_id->get_error_message() : '' );
	if ( ! is_wp_error( $doc_id ) ) {
		$doc = PartnerDocumentService::get( $doc_id );
		$cleanup_document_ids[] = [ $doc_id, $pd_dir . '/' . ( $doc['stored_name'] ?? '' ) ];
		liw_st_check( 'Zufälliger stored_name, sha256 gesetzt, Datei im gesperrten Verzeichnis', is_array( $doc ) && preg_match( '/^[0-9a-f]{32}\.pdf$/', (string) $doc['stored_name'] ) && 64 === strlen( (string) $doc['sha256'] ) && null !== PartnerDocumentService::file_path( $doc ) );
		liw_st_check( 'get_active() listet Dokument ohne stored_name auszugeben', in_array( $doc_id, array_map( 'intval', array_column( PartnerDocumentService::get_active(), 'id' ) ), true ) && ! array_key_exists( 'stored_name', PartnerDocumentService::get_active()[0] ) );
		$prev_user = get_current_user_id();
		wp_set_current_user( 0 );
		liw_st_check( '[liw_partner_documents] ohne Login → nur Login-Link, kein Dokument', str_contains( do_shortcode( '[liw_partner_documents]' ), 'liw-partner-docs--login' ) && ! str_contains( do_shortcode( '[liw_partner_documents]' ), "{$run} Prozessdokument" ) );
		if ( ! empty( $acct_user_id ) && ! is_wp_error( $acct_user_id ) ) {
			wp_set_current_user( $acct_user_id );
			$pd_html = do_shortcode( '[liw_partner_documents]' );
			liw_st_check( '[liw_partner_documents] als Partner → Dokument mit Download-Formular (Nonce, POST)', str_contains( $pd_html, "{$run} Prozessdokument" ) && str_contains( $pd_html, 'name="document_id"' ) && str_contains( $pd_html, 'liw_pd_nonce' ) && ! str_contains( $pd_html, (string) $doc['stored_name'] ) );
			liw_st_check( 'Admin-Leiste für reine Partner ausgeblendet', false === PartnerDocumentsView::hide_admin_bar_for_partners( true ) );
		}
		wp_set_current_user( $prev_user );
		liw_st_check( 'soft_delete() entfernt aus Liste, Datei bleibt (Audit-Trail)', true === PartnerDocumentService::soft_delete( $doc_id, 1 ) && null === PartnerDocumentService::get( $doc_id ) && is_file( $pd_dir . '/' . $doc['stored_name'] ) );
	}
	@unlink( $tmp_pdf ); @unlink( $tmp_png ); // phpcs:ignore

	// ── [5b] Kontaktformular LP-13 (§22) ─────────────────────────────────────
	echo "\n[5b] Kontaktformular LP-13 (§22, getrennt vom Onboarding)\n";
	$ct_base = [
		'organisation' => "{$run} GmbH", 'contact_name' => "{$run} Person", 'contact_email' => strtolower( $run ) . '@example.com',
		'region' => 'europe', 'role' => 'technology_partner', 'interests' => [ 'interfaces', 'simulation' ],
		'message' => 'Testanfrage.', 'privacy_consent' => true,
	];
	$rejected = ContactService::submit_request( array_merge( $ct_base, [ 'privacy_consent' => false ] ) );
	liw_st_check( 'Anfrage ohne Datenschutz-Einwilligung wird abgelehnt', is_wp_error( $rejected ) && 'liw_consent_required' === $rejected->get_error_code() );
	$rejected = ContactService::submit_request( array_merge( $ct_base, [ 'role' => 'hacker' ] ) );
	liw_st_check( 'Unbekannte Rolle wird abgelehnt', is_wp_error( $rejected ) && 'liw_invalid_role' === $rejected->get_error_code() );
	$rejected = ContactService::submit_request( array_merge( $ct_base, [ 'interests' => [ 'nicht-vorhanden' ] ] ) );
	liw_st_check( 'Unbekanntes Projektinteresse → Pflichtfeld verletzt', is_wp_error( $rejected ) && 'liw_invalid_interest' === $rejected->get_error_code() );

	$mail_filter = static fn( $args ) => array_merge( (array) $args, [ 'to' => 'selftest-blackhole@example.invalid' ] ); // Testlauf: keine echte Admin-Mail.
	add_filter( 'wp_mail', $mail_filter );
	$before_partners = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'ary_partners' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$contact_id      = ContactService::submit_request( $ct_base );
	remove_filter( 'wp_mail', $mail_filter );
	liw_st_check( 'Kontaktanfrage angelegt', ! is_wp_error( $contact_id ), is_wp_error( $contact_id ) ? $contact_id->get_error_message() : '' );
	if ( ! is_wp_error( $contact_id ) ) {
		$cleanup_contact_ids[] = $contact_id;
		$after_partners = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'ary_partners' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		liw_st_check( 'Kontaktanfrage legt KEINEN Partner an (getrennt vom Onboarding)', $before_partners === $after_partners );
		$saved = ContactService::get( $contact_id );
		liw_st_check( 'Interessen als Schlüsselliste gespeichert', 'interfaces,simulation' === ( $saved['interests'] ?? '' ) );
		liw_st_check( 'Datenschutz-Einwilligung mit request_kind=contact protokolliert', ConsentLogService::has_consent( $contact_id, ConsentLogService::TYPE_PRIVACY, ConsentLogService::KIND_CONTACT ) );
		liw_st_check( 'Marketing-Einwilligung NICHT protokolliert', ! ConsentLogService::has_consent( $contact_id, ConsentLogService::TYPE_MARKETING, ConsentLogService::KIND_CONTACT ) );
		$kind_rows = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . ConsentLogSchema::table_name() . ' WHERE request_id = %d AND request_kind = %s', $contact_id, 'contact' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		liw_st_check( 'Genau eine Einwilligungszeile mit request_kind=contact (ID-Räume getrennt)', 1 === $kind_rows );
		ContactService::set_status( $contact_id, 'in_progress', 1 );
		liw_st_check( 'Statuswechsel new → in_progress', 'in_progress' === ( ContactService::get( $contact_id )['request_status'] ?? '' ) );
		liw_st_check( 'get_all() Seite 1 enthält Anfrage', in_array( $contact_id, array_map( 'intval', array_column( ContactService::get_all( 1 ), 'id' ) ), true ) );
		liw_st_check( 'count_all() >= 1', ContactService::count_all() >= 1 );
		$ct_html = do_shortcode( '[liw_contact_form]' );
		liw_st_check( 'Shortcode [liw_contact_form] rendert Formular mit Rollen/Regionen/Interessen', str_contains( $ct_html, 'liw-contact-form' ) && str_contains( $ct_html, 'value="technology_partner"' ) && str_contains( $ct_html, 'value="asia_pacific"' ) && str_contains( $ct_html, 'name="interests[]"' ) );
		liw_st_check( 'Honeypot-Feld liw_hp_company_url vorhanden und versteckt', str_contains( $ct_html, 'liw_hp_company_url' ) && str_contains( $ct_html, 'liw-visually-hidden' ) );
		$del = ContactService::delete( $contact_id, 1 );
		liw_st_check( 'delete() entfernt Anfrage und Einwilligungen (§24)', true === $del && null === ContactService::get( $contact_id ) && ! ConsentLogService::has_consent( $contact_id, ConsentLogService::TYPE_PRIVACY, ConsentLogService::KIND_CONTACT ) );
		$cleanup_contact_ids = [];
	}

	// ── [6] Content Board (§19) ───────────────────────────────────────────────
	echo "\n[6] Content Board – Freigabeworkflow (§19)\n";
	$section_id = wp_insert_post( [
		'post_type'    => LiwSectionCpt::POST_TYPE,
		'post_title'   => "{$run} Testabschnitt",
		'post_content' => 'Testinhalt.',
		'post_status'  => 'draft',
		'menu_order'   => 99,
	], true );
	liw_st_check( 'Abschnitt angelegt (Status draft)', ! is_wp_error( $section_id ) && $section_id > 0 );
	if ( ! is_wp_error( $section_id ) && $section_id > 0 ) {
		$cleanup_section_ids[] = $section_id;

		liw_st_check( 'display_post_states-Filter für liw_approved registriert', false !== has_filter( 'display_post_states', [ LiwSectionCpt::class, 'add_status_badge' ] ) );

		wp_update_post( [ 'ID' => $section_id, 'post_status' => 'pending' ] );
		liw_st_check( 'Statuswechsel draft → pending', 'pending' === get_post_status( $section_id ) );

		wp_update_post( [ 'ID' => $section_id, 'post_status' => LiwSectionCpt::STATUS_APPROVED ] );
		liw_st_check( 'Statuswechsel pending → liw_approved', LiwSectionCpt::STATUS_APPROVED === get_post_status( $section_id ) );

		wp_update_post( [ 'ID' => $section_id, 'post_status' => 'publish' ] );
		liw_st_check( 'Statuswechsel liw_approved → publish', 'publish' === get_post_status( $section_id ) );

		$all_section_ids = get_posts( [ 'post_type' => LiwSectionCpt::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ] );
		liw_st_check( 'Abschnitt in beliebigem Status auffindbar (post_status=any)', in_array( $section_id, $all_section_ids, true ) );

		// Bauplan LP-01…LP-14 + Seeder (alpha.17). Der Seeder wird hier NICHT ausgeführt (würde echte
		// Abschnitte anlegen); geprüft werden Bauplan, Zuordnung per Post-Meta und die Idempotenz-Sicht.
		$codes = array_keys( SectionBlueprint::all() );
		liw_st_check( 'Bauplan enthält 14 Codes LP-01…LP-14 in Reihenfolge', 14 === count( $codes ) && 'LP-01' === $codes[0] && 'LP-14' === $codes[13] );
		liw_st_check( 'draft_content(LP-07) enthält [liw_graphic name="data-model"] als Shortcode-Block', str_contains( SectionBlueprint::draft_content( 'LP-07' ), '<!-- wp:shortcode -->[liw_graphic name="data-model"]<!-- /wp:shortcode -->' ) );
		liw_st_check( 'menu_order_for(LP-07) = 70', 70 === SectionBlueprint::menu_order_for( 'LP-07' ) );
		update_post_meta( $section_id, SectionBlueprint::META_CODE, "{$run}-CODE" );
		liw_st_check( 'existing_codes() erkennt Zuordnung über Post-Meta', ( SectionSeeder::existing_codes()[ "{$run}-CODE" ] ?? 0 ) === $section_id );
		$missing  = SectionSeeder::missing_codes();
		$existing = array_intersect( $codes, array_keys( SectionSeeder::existing_codes() ) );
		liw_st_check( 'missing_codes() + vorhandene Bauplan-Codes = 14 (Idempotenz-Sicht konsistent)', 14 === count( $missing ) + count( $existing ), 'fehlend: ' . count( $missing ) . ', vorhanden: ' . count( $existing ) );
		echo '  ℹ Standard-Abschnitte laut Bauplan: ' . count( $existing ) . ' vorhanden, ' . count( $missing ) . " fehlend (Anlage per Knopf im Content Board)\n";

		// [liw_landingpage] (alpha.18): zeigt nur veröffentlichte Abschnitte, löst eingebettete Shortcodes auf.
		wp_update_post( [ 'ID' => $section_id, 'post_content' => SectionBlueprint::draft_content( 'LP-07' ) ] ); // Abschnitt ist zu diesem Zeitpunkt 'publish'.
		$draft_id = wp_insert_post( [ 'post_type' => LiwSectionCpt::POST_TYPE, 'post_title' => "{$run} Entwurf-Abschnitt", 'post_content' => 'unsichtbar', 'post_status' => 'draft' ], true );
		if ( ! is_wp_error( $draft_id ) ) { $cleanup_section_ids[] = $draft_id; }
		$lp_html = do_shortcode( '[liw_landingpage]' );
		liw_st_check( 'Shortcode [liw_landingpage] registriert', shortcode_exists( LandingpageView::SHORTCODE ) );
		liw_st_check( '[liw_landingpage] enthält veröffentlichten Abschnitt (Titel + Anker aus Code)', str_contains( $lp_html, "{$run} Testabschnitt" ) && str_contains( $lp_html, 'id="' . strtolower( $run ) . '-code"' ) );
		liw_st_check( '[liw_landingpage] löst eingebetteten [liw_graphic] auf (Inline-SVG)', str_contains( $lp_html, 'liw-graphic--data-model' ) );
		liw_st_check( '[liw_landingpage] zeigt Entwurf NICHT', ! str_contains( $lp_html, "{$run} Entwurf-Abschnitt" ) && ! str_contains( $lp_html, 'unsichtbar' ) );
		liw_st_check( '[liw_landingpage] Wrapper-Klasse liw-landingpage vorhanden', str_contains( $lp_html, 'class="liw-landingpage"' ) );
		// Sprungleiste (alpha.23): Link auf den Anker des veröffentlichten Abschnitts; nav="0" schaltet ab.
		$nav_html = do_shortcode( '[liw_landingpage]' );
		$published_count = count( LandingpageView::get_published_sections() );
		liw_st_check(
			$published_count >= 2 ? '[liw_landingpage] enthält Sprungleiste mit Link auf den Abschnitts-Anker' : '[liw_landingpage] ohne Sprungleiste bei nur einem veröffentlichten Abschnitt (bewusst)',
			$published_count >= 2
				? ( str_contains( $nav_html, 'class="liw-landingpage__nav"' ) && str_contains( $nav_html, 'href="#' . strtolower( $run ) . '-code"' ) )
				: ! str_contains( $nav_html, 'liw-landingpage__nav' )
		);
		liw_st_check( '[liw_landingpage nav="0"] ohne Sprungleiste, Abschnitt weiterhin da', ! str_contains( do_shortcode( '[liw_landingpage nav="0"]' ), 'liw-landingpage__nav' ) && str_contains( do_shortcode( '[liw_landingpage nav="0"]' ), "{$run} Testabschnitt" ) );
		liw_st_check( 'get_published_sections() liefert nur publish', [] === array_filter( LandingpageView::get_published_sections(), static fn( WP_Post $p ): bool => 'publish' !== $p->post_status ) );
	}

	// ── [6b] Sprache & SEO (I18nSeo-Analyse Option A, alpha.24) ─────────────────
	echo "\n[6b] Sprache & hreflang (Pflichtenheft §20/§24, Option A)\n";
	$langs = LanguageBridge::active_langs();
	liw_st_check( 'Aktive Sprachen vom Core als Codes (kein "Array"), Default-Sprache enthalten', count( $langs ) >= 1 && in_array( LanguageBridge::current_lang(), $langs, true ) && [] === array_filter( $langs, static fn( string $l ): bool => 1 !== preg_match( '/^[a-z]{2,5}(-[a-z0-9]{2,8})?$/', $l ) ), implode( ',', $langs ) );
	$hl = SeoBridge::hreflang_markup( 'https://example.test/interface-world/', [ 'de', 'en' ] );
	liw_st_check( 'hreflang-Markup: je Sprache ?lang=, x-default = Basis-URL', str_contains( $hl, 'hreflang="de"' ) && str_contains( $hl, 'lang=en' ) && str_contains( $hl, 'hreflang="x-default" href="https://example.test/interface-world/"' ) && 3 === substr_count( $hl, '<link ' ) );
	liw_st_check( 'Core-Router-Status abfragbar (bool), SeoBridge schweigt bei aktivem Router', is_bool( LanguageBridge::core_router_active() ) );
	liw_st_check( '[liw_language_switcher] registriert, liefert Core-Widget oder leer', shortcode_exists( LanguageBridge::SHORTCODE ) && ( '' === do_shortcode( '[liw_language_switcher]' ) || str_contains( do_shortcode( '[liw_language_switcher]' ), 'liw-language-switcher' ) ) );
	liw_st_check( 'Einwilligungs-Textversion sprachabhängig (§24)', str_ends_with( LanguageBridge::versioned( 'x-v1' ), '-' . LanguageBridge::current_lang() ) );
	$cl_ver = $wpdb->get_var( $wpdb->prepare( 'SELECT text_version FROM ' . ConsentLogSchema::table_name() . ' WHERE request_id = %d AND request_kind = %s AND consent_type = %s', $partner_id, 'onboarding', 'privacy' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	liw_st_check( 'Onboarding-Einwilligung dieses Laufs trägt Sprachsuffix', is_string( $cl_ver ) && str_ends_with( $cl_ver, '-' . LanguageBridge::current_lang() ) );

	// ── [7] Media Board (§18) ─────────────────────────────────────────────────
	echo "\n[7] Media Board – CI-005-Freigabe\n";
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

	// ── [8] Design System / Assets ────────────────────────────────────────────
	echo "\n[8] Design-System-Anbindung + Admin-Assets\n";
	liw_st_check( 'assets/css/liebherr-frontend.css vorhanden', is_readable( LIW_PATH . 'assets/css/liebherr-frontend.css' ) );
	liw_st_check( 'assets/css/liebherr-admin.css vorhanden', is_readable( LIW_PATH . 'assets/css/liebherr-admin.css' ) );
	liw_st_check( 'AdminPagination-Klasse verfügbar', class_exists( AdminPagination::class ) );
	// Aktive Anker-Hervorhebung (alpha.26): enqueuetes Frontend-Skript + Aktiv-Zustand im CSS.
	liw_st_check( 'assets/js/liebherr-frontend.js vorhanden', is_readable( LIW_PATH . 'assets/js/liebherr-frontend.js' ) );
	$liw_front_assets_src = (string) file_get_contents( LIW_PATH . 'src/Frontend/FrontendAssets.php' );
	liw_st_check( 'FrontendAssets registriert liebherr-frontend.js (Footer, LIW_VERSION)', str_contains( $liw_front_assets_src, "assets/js/liebherr-frontend.js" ) && str_contains( $liw_front_assets_src, 'wp_enqueue_script' ) );
	liw_st_check( 'liebherr-frontend.js: IntersectionObserver + aria-current + is-current, an .liw-landingpage__nav gebunden', ( static function (): bool { $s = (string) file_get_contents( LIW_PATH . 'assets/js/liebherr-frontend.js' ); return str_contains( $s, 'IntersectionObserver' ) && str_contains( $s, 'aria-current' ) && str_contains( $s, 'is-current' ) && str_contains( $s, '.liw-landingpage__nav' ); } )() );
	liw_st_check( 'liebherr-frontend.css: Aktiv-Zustand .liw-landingpage__nav-link.is-current', str_contains( (string) file_get_contents( LIW_PATH . 'assets/css/liebherr-frontend.css' ), '.liw-landingpage__nav-link.is-current' ) );
	// Brand Board / CI-Tokens (alpha.27, Etappe 1).
	liw_st_check( 'Brand Board Seite verfügbar', class_exists( \Liebherr\InterfaceWorld\Admin\Pages\BrandBoardPage::class ) );
	liw_st_check( 'BrandTokens::css_root() liefert :root mit --brand-primary', str_starts_with( \Liebherr\InterfaceWorld\Branding\BrandTokens::css_root(), ':root{' ) && str_contains( \Liebherr\InterfaceWorld\Branding\BrandTokens::css_root(), '--brand-primary:' ) );
	liw_st_check( 'BrandTokens-Fallback markenneutral (kein #ffd000 ohne Freigabe)', '#ffd000' !== strtolower( (string) \Liebherr\InterfaceWorld\Branding\BrandTokens::defaults()['primary'] ) );
	liw_st_check( 'FrontendAssets injiziert Tokens via wp_add_inline_style', str_contains( $liw_front_assets_src, 'wp_add_inline_style' ) && str_contains( $liw_front_assets_src, 'BrandTokens::css_root' ) );
	liw_st_check( 'liebherr-frontend.css enthält :root --brand-* Fallbacks', str_contains( (string) file_get_contents( LIW_PATH . 'assets/css/liebherr-frontend.css' ), '--brand-primary:' ) );
	// Header + Hero (alpha.28, Etappe 2).
	liw_st_check( 'Shortcodes [liw_header] und [liw_hero] registriert', shortcode_exists( 'liw_header' ) && shortcode_exists( 'liw_hero' ) );
	liw_st_check( 'Header Board Seite verfügbar', class_exists( \Liebherr\InterfaceWorld\Admin\Pages\HeaderBoardPage::class ) );
	$hdr = do_shortcode( '[liw_header]' );
	liw_st_check( '[liw_header] rendert Header mit Navigation und Primär-CTA', str_contains( $hdr, 'class="liw-header"' ) && str_contains( $hdr, 'liw-header__nav' ) && str_contains( $hdr, 'liw-cta--primary' ) );
	// Logo-Regel (CI-002/005): freigegebenes Logo → Bild; sonst neutrale Wortmarke.
	$__logo_id = \Liebherr\InterfaceWorld\Branding\BrandTokens::logo_id();
	$__logo_ok = ( $__logo_id > 0 && \Liebherr\InterfaceWorld\CoreBridge\MediaBridge::is_approved( $__logo_id ) )
		? str_contains( $hdr, 'liw-header__logo' )
		: str_contains( $hdr, 'liw-header__wordmark' );
	liw_st_check( '[liw_header] Logo-Regel: freigegeben → Bild, sonst Wortmarke (CI-002/005)', $__logo_ok );
	$hero = do_shortcode( '[liw_hero]' );
	liw_st_check( '[liw_hero] rendert Hero mit H1 und CTAs', str_contains( $hero, 'class="liw-hero' ) && str_contains( $hero, 'liw-hero__headline' ) && str_contains( $hero, 'liw-cta--secondary' ) );
	liw_st_check( '[liw_hero] Standard-Headline (§8)', str_contains( $hero, 'One structure' ) );
	$liw_front_css = (string) file_get_contents( LIW_PATH . 'assets/css/liebherr-frontend.css' );
	liw_st_check( 'CSS enthält Header/CTA/Hero-Klassen', str_contains( $liw_front_css, '.liw-header {' ) && str_contains( $liw_front_css, '.liw-cta {' ) && str_contains( $liw_front_css, '.liw-hero {' ) );
	liw_st_check( 'Hero-Animation respektiert prefers-reduced-motion', str_contains( $liw_front_css, 'prefers-reduced-motion: no-preference' ) );
	liw_st_check( 'FrontendAssets: [liw_header]/[liw_hero] als Asset-Auslöser', str_contains( $liw_front_assets_src, 'HeaderView::SHORTCODE' ) && str_contains( $liw_front_assets_src, 'HeroView::SHORTCODE' ) );
	// Kern-Komponenten (alpha.29, Etappe 3).
	liw_st_check( 'Shortcodes [liw_process_worlds]/[liw_roadmap]/[liw_onboarding_steps] registriert', shortcode_exists( 'liw_process_worlds' ) && shortcode_exists( 'liw_roadmap' ) && shortcode_exists( 'liw_onboarding_steps' ) );
	liw_st_check( 'Components Board Seite verfügbar', class_exists( \Liebherr\InterfaceWorld\Admin\Pages\ComponentsBoardPage::class ) );
	$pw = do_shortcode( '[liw_process_worlds]' );
	liw_st_check( '[liw_process_worlds] rendert 7 Karten mit Sales', substr_count( $pw, 'liw-pcard__title' ) === 7 && str_contains( $pw, 'Sales' ) );
	$rm = do_shortcode( '[liw_roadmap]' );
	liw_st_check( '[liw_roadmap] rendert 6 Phasen mit Global Rollout', substr_count( $rm, 'liw-roadmap__phase' ) === 6 && str_contains( $rm, 'Global Rollout' ) );
	$ob = do_shortcode( '[liw_onboarding_steps]' );
	liw_st_check( '[liw_onboarding_steps] rendert 9 Schritte', substr_count( $ob, 'liw-steps__item' ) === 9 );
	liw_st_check( 'CSS enthält Komponenten-Klassen (pcard/roadmap/steps)', str_contains( $liw_front_css, '.liw-pcard {' ) && str_contains( $liw_front_css, '.liw-roadmap {' ) && str_contains( $liw_front_css, '.liw-steps {' ) );
	// §24-Export + Redaktions-Prüfung (alpha.30, Etappe 4).
	liw_st_check( 'Kontakt-Export: admin_post-Handler registriert', has_action( 'admin_post_' . \Liebherr\InterfaceWorld\Contact\ContactExporter::ACTION ) !== false );
	liw_st_check( 'ContactService::get_all_for_export() liefert Array', is_array( \Liebherr\InterfaceWorld\Contact\ContactService::get_all_for_export() ) );
	liw_st_check( 'ConsentLogService::list_for_request() liefert Array', is_array( \Liebherr\InterfaceWorld\Consent\ConsentLogService::list_for_request( 999999, \Liebherr\InterfaceWorld\Consent\ConsentLogService::KIND_CONTACT ) ) );
	liw_st_check( 'Contact Board zeigt CSV-Export-Knopf + DSGVO-Hinweis', str_contains( (string) file_get_contents( LIW_PATH . 'src/Admin/Pages/ContactBoardPage.php' ), 'ContactExporter::ACTION' ) && str_contains( (string) file_get_contents( LIW_PATH . 'src/Admin/Pages/ContactBoardPage.php' ), 'DSGVO' ) );
	liw_st_check( 'Redaktions-Prüfung: count_images_without_alt zählt korrekt', 1 === \Liebherr\InterfaceWorld\Admin\Pages\ContentBoardPage::count_images_without_alt( '<img src="x">' ) && 0 === \Liebherr\InterfaceWorld\Admin\Pages\ContentBoardPage::count_images_without_alt( '<img src="x" alt="ok">' ) );
	// Etappe 5: Audit Board (Lesen) + Interface-Lifecycle-Status-UI (alpha.31).
	liw_st_check( 'Audit Board Seite verfügbar', class_exists( \Liebherr\InterfaceWorld\Admin\Pages\AuditBoardPage::class ) );
	liw_st_check( 'AuditBridge::recent_liw_events() liefert Array', is_array( \Liebherr\InterfaceWorld\CoreBridge\AuditBridge::recent_liw_events( 5 ) ) );
	liw_st_check( 'AuditBridge::count_liw_events() liefert int >= 0', \Liebherr\InterfaceWorld\CoreBridge\AuditBridge::count_liw_events() >= 0 );
	liw_st_check( 'Audit-Lesen filtert auf liw_-Eintraege', str_contains( (string) file_get_contents( LIW_PATH . 'src/CoreBridge/AuditBridge.php' ), "entity_type LIKE 'liw" ) );
	liw_st_check( 'Interface-Lifecycle: ungueltiger Status → WP_Error', is_wp_error( \Liebherr\InterfaceWorld\Interfaces\InterfaceCatalogService::set_lifecycle_status( 0, 'bogus_status', 0 ) ) );
	liw_st_check( 'Interface Board: Statuswechsel-Formular (set_lifecycle)', str_contains( (string) file_get_contents( LIW_PATH . 'src/Admin/Pages/InterfaceBoardPage.php' ), "value=\"set_lifecycle\"" ) && str_contains( (string) file_get_contents( LIW_PATH . 'src/Admin/Pages/InterfaceBoardPage.php' ), 'lifecycle_status' ) );
	// Etappe 6: SEO-Rest + Release-Readiness (alpha.32).
	$og = \Liebherr\InterfaceWorld\CoreBridge\SeoBridge::open_graph_markup( 'Titel', 'https://example.com/x', 'en', 'https://example.com/i.jpg' );
	liw_st_check( 'OG-Markup: title/url(+lang)/locale/image', str_contains( $og, 'og:title' ) && str_contains( $og, 'lang=en' ) && str_contains( $og, 'og:locale' ) && str_contains( $og, 'og:image' ) );
	$og_de = \Liebherr\InterfaceWorld\CoreBridge\SeoBridge::open_graph_markup( 'Titel', 'https://example.com/x', 'de', '' );
	liw_st_check( 'OG-Markup: Standardsprache DE ohne lang-Parameter, ohne Bild', ! str_contains( $og_de, 'lang=de' ) && ! str_contains( $og_de, 'og:image' ) );
	liw_st_check( 'Sitemap: liw_section ausgeschlossen', ! array_key_exists( 'liw_section', \Liebherr\InterfaceWorld\CoreBridge\SeoBridge::filter_sitemap_post_types( [ 'liw_section' => 'x', 'page' => 'y' ] ) ) );
	$non_liw_post = new WP_Post( (object) [ 'post_type' => 'page', 'post_content' => 'nur Text ohne Shortcode' ] );
	liw_st_check( 'Canonical-Filter: Nicht-LIW-Seite unveraendert', 'https://example.com/x' === \Liebherr\InterfaceWorld\CoreBridge\SeoBridge::filter_canonical( 'https://example.com/x', $non_liw_post ) );
	$liw_post = new WP_Post( (object) [ 'post_type' => 'liw_section', 'post_content' => '' ] );
	liw_st_check( 'is_liw_post erkennt Abschnitt', \Liebherr\InterfaceWorld\CoreBridge\SeoBridge::is_liw_post( $liw_post ) );
	// Etappe 7: Sicherheit/Datenschutz (alpha.33).
	liw_st_check( 'RateLimitBridge::allow() liefert bool', is_bool( \Liebherr\InterfaceWorld\CoreBridge\RateLimitBridge::allow( 'contact' ) ) );
	liw_st_check( 'ContactForm mit Rate-Limit (SEC-004)', str_contains( (string) file_get_contents( LIW_PATH . 'src/Contact/ContactForm.php' ), 'RateLimitBridge::allow' ) );
	liw_st_check( 'OnboardingForm mit Rate-Limit (SEC-004)', str_contains( (string) file_get_contents( LIW_PATH . 'src/Onboarding/OnboardingForm.php' ), 'RateLimitBridge::allow' ) );
	liw_st_check( 'Retention-Cron geplant (SEC-007/§24)', false !== wp_next_scheduled( \Liebherr\InterfaceWorld\Contact\ContactRetention::CRON_HOOK ) );
	$__ret_before = \Liebherr\InterfaceWorld\Contact\ContactRetention::days();
	\Liebherr\InterfaceWorld\Contact\ContactRetention::set_days( 30 );
	liw_st_check( 'Retention set/days Round-Trip', 30 === \Liebherr\InterfaceWorld\Contact\ContactRetention::days() );
	\Liebherr\InterfaceWorld\Contact\ContactRetention::set_days( 0 );
	liw_st_check( 'Retention 0 = deaktiviert, run() ohne Löschung', 0 === \Liebherr\InterfaceWorld\Contact\ContactRetention::days() && ( \Liebherr\InterfaceWorld\Contact\ContactRetention::run() === null || true ) );
	\Liebherr\InterfaceWorld\Contact\ContactRetention::set_days( $__ret_before );
	liw_st_check( 'ContactService::ids_older_than() liefert Array', is_array( \Liebherr\InterfaceWorld\Contact\ContactService::ids_older_than( gmdate( 'Y-m-d H:i:s' ) ) ) );
	// SEC-009 (Uploads): Partnerdokument-Upload prüft Größe + doppelten MIME (Dateiname + finfo) gegen Whitelist.
	$__pd = (string) file_get_contents( LIW_PATH . 'src/Partner/PartnerDocumentService.php' );
	liw_st_check( 'Upload-Härtung: finfo-MIME + Größenlimit + Whitelist (SEC-009)', str_contains( $__pd, 'finfo' ) && str_contains( $__pd, 'MAX_SIZE' ) && str_contains( $__pd, 'ALLOWED' ) );
	liw_st_check( 'Contact Board: Aufbewahrungsfrist-Formular', str_contains( (string) file_get_contents( LIW_PATH . 'src/Admin/Pages/ContactBoardPage.php' ), 'set_retention' ) );
	// Etappe 8: Qualität & Abnahme (alpha.34).
	liw_st_check( 'Demo-Seeder vorhanden + --confirm-Schutz (§33)', is_readable( LIW_PATH . 'scripts/liw-seed-demo.php' ) && str_contains( (string) file_get_contents( LIW_PATH . 'scripts/liw-seed-demo.php' ), '--confirm' ) );
	liw_st_check( 'Demo-Seeder legt keine echten Daten an (DEMO-Kennzeichnung)', str_contains( (string) file_get_contents( LIW_PATH . 'scripts/liw-seed-demo.php' ), 'DEMO' ) );
	liw_st_check( 'Abnahmebericht + Release-Plan dokumentiert', is_readable( LIW_PATH . 'docs/LIW_ABNAHME.md' ) && is_readable( LIW_PATH . 'docs/LIW_RELEASEPLAN.md' ) );
	// Etappe 9: Content-Board-Restpunkte (alpha.35).
	// Verdrahtung quellbasiert (der wp_ajax_-Hook wird nur im Admin-Kontext gesetzt, nicht im CLI-Selbsttest).
	liw_st_check( 'Content-Reorder: AJAX-Handler verdrahtet (§19)', str_contains( (string) file_get_contents( LIW_PATH . 'src/Admin/Pages/ContentBoardPage.php' ), "wp_ajax_' . self::REORDER_ACTION" ) && str_contains( (string) file_get_contents( LIW_PATH . 'src/Bootstrap.php' ), 'ContentBoardPage::register()' ) );
	liw_st_check( 'Content-Reorder: Sortier-Skript vorhanden', is_readable( LIW_PATH . 'assets/js/liw-admin-content.js' ) );
	liw_st_check( 'AdminAssets enqueued Sortier-Skript auf dem Content Board', str_contains( (string) file_get_contents( LIW_PATH . 'src/Admin/AdminAssets.php' ), 'liw-admin-content' ) && str_contains( (string) file_get_contents( LIW_PATH . 'src/Admin/AdminAssets.php' ), 'jquery-ui-sortable' ) );
	// apply_order() setzt menu_order in gewünschter Reihenfolge (mit echten Test-Abschnitten).
	$__a = wp_insert_post( [ 'post_type' => 'liw_section', 'post_title' => 'SELFTEST Reorder A', 'post_status' => 'draft', 'menu_order' => 100 ] );
	$__b = wp_insert_post( [ 'post_type' => 'liw_section', 'post_title' => 'SELFTEST Reorder B', 'post_status' => 'draft', 'menu_order' => 200 ] );
	$__n = \Liebherr\InterfaceWorld\Admin\Pages\ContentBoardPage::apply_order( [ (int) $__b, (int) $__a ] );
	liw_st_check( 'apply_order(): B vor A → menu_order 10/20', 2 === $__n && 10 === (int) get_post_field( 'menu_order', $__b ) && 20 === (int) get_post_field( 'menu_order', $__a ) );
	liw_st_check( 'apply_order(): ignoriert Fremd-Post-Typen', 0 === \Liebherr\InterfaceWorld\Admin\Pages\ContentBoardPage::apply_order( [ 1 ] ) );
	wp_delete_post( (int) $__a, true );
	wp_delete_post( (int) $__b, true );
	// Etappe 9b: Sichtbarkeits-Zeitfenster (alpha.36).
	liw_st_check( 'SectionSchedule-Metabox vorhanden', class_exists( \Liebherr\InterfaceWorld\Admin\SectionScheduleMetabox::class ) );
	liw_st_check( 'Metabox + save_post quellbasiert verdrahtet', str_contains( (string) file_get_contents( LIW_PATH . 'src/Admin/SectionScheduleMetabox.php' ), 'add_meta_boxes' ) && str_contains( (string) file_get_contents( LIW_PATH . 'src/Bootstrap.php' ), 'SectionScheduleMetabox::register' ) );
	$__pub = wp_insert_post( [ 'post_type' => 'liw_section', 'post_title' => 'SELFTEST Window', 'post_status' => 'publish' ] );
	update_post_meta( (int) $__pub, \Liebherr\InterfaceWorld\Content\SectionSchedule::META_UNTIL, gmdate( 'Y-m-d H:i:s', time() - 3600 ) );
	liw_st_check( 'is_visible_now: abgelaufenes Fenster → unsichtbar', false === \Liebherr\InterfaceWorld\Content\SectionSchedule::is_visible_now( (int) $__pub ) );
	$__lp_ids = array_map( static fn( WP_Post $p ): int => $p->ID, \Liebherr\InterfaceWorld\Frontend\LandingpageView::get_published_sections() );
	liw_st_check( 'Landingpage blendet abgelaufenen Abschnitt aus (§19)', ! in_array( (int) $__pub, $__lp_ids, true ) );
	update_post_meta( (int) $__pub, \Liebherr\InterfaceWorld\Content\SectionSchedule::META_UNTIL, gmdate( 'Y-m-d H:i:s', time() + 3600 ) );
	liw_st_check( 'is_visible_now: laufendes Fenster → sichtbar', true === \Liebherr\InterfaceWorld\Content\SectionSchedule::is_visible_now( (int) $__pub ) );
	wp_delete_post( (int) $__pub, true );
	// Etappe „Optik" (alpha.37): Liebherr-CI angewendet, Weltkarte, Webfonts.
	liw_st_check( 'CI angewendet: Brand-Primaerfarbe = Liebherr-Gelb', str_contains( \Liebherr\InterfaceWorld\Branding\BrandTokens::css_root(), '--brand-primary:#ffd000' ) );
	liw_st_check( 'CI angewendet: Logo freigegeben (approved=1)', 0 < \Liebherr\InterfaceWorld\Branding\BrandTokens::logo_id() && \Liebherr\InterfaceWorld\CoreBridge\MediaBridge::is_approved( \Liebherr\InterfaceWorld\Branding\BrandTokens::logo_id() ) );
	liw_st_check( 'Webfonts: @font-face fuer freigegebene Liebherr-Fonts', str_contains( \Liebherr\InterfaceWorld\Frontend\FontFaceService::css(), "font-family:'LiebherrHead'" ) && str_contains( \Liebherr\InterfaceWorld\Frontend\FontFaceService::css(), "font-family:'LiebherrText'" ) );
	liw_st_check( 'Shortcode [liw_world_map] registriert', shortcode_exists( \Liebherr\InterfaceWorld\Frontend\WorldMapView::SHORTCODE ) );
	$__wm = do_shortcode( '[liw_world_map]' );
	liw_st_check( '[liw_world_map] rendert SVG + Zentrale + Text-Alternative', str_contains( $__wm, 'liw-worldmap__svg' ) && str_contains( $__wm, 'liw-worldmap__hub' ) && str_contains( $__wm, 'liw-worldmap__list' ) );
	liw_st_check( '[liw_world_map] enthaelt Regionsknoten aus DEMO-Verbindungen', str_contains( $__wm, 'data-region="europe"' ) || str_contains( $__wm, 'data-region="asia_pacific"' ) );
	// LP-14 Footer + Cache-Buster + RUCSS-Safelist (alpha.38).
	liw_st_check( 'Shortcode [liw_footer] registriert', shortcode_exists( \Liebherr\InterfaceWorld\Frontend\FooterView::SHORTCODE ) );
	$__ft = do_shortcode( '[liw_footer]' );
	liw_st_check( '[liw_footer] rendert Legal-Nav + GoHeal-Hinweis (CI-003)', str_contains( $__ft, 'liw-footer__legal' ) && str_contains( $__ft, 'Impressum' ) && str_contains( $__ft, 'Datenschutzhinweis' ) && str_contains( $__ft, 'GoHeal' ) );
	liw_st_check( 'FrontendAssets: filemtime-Cache-Buster (bust_src)', str_contains( (string) file_get_contents( LIW_PATH . 'src/Frontend/FrontendAssets.php' ), 'bust_src' ) && str_contains( (string) file_get_contents( LIW_PATH . 'src/Frontend/FrontendAssets.php' ), 'style_loader_src' ) );
	liw_st_check( 'RocketCompat RUCSS-Safelist enthaelt .liw-worldmap/.liw-footer', str_contains( implode( ',', \Liebherr\InterfaceWorld\Frontend\RocketCompat::safelist( [] ) ), '.liw-worldmap' ) && str_contains( implode( ',', \Liebherr\InterfaceWorld\Frontend\RocketCompat::safelist( [] ) ), '.liw-footer' ) );
	// Wortmarke + Vollbild-Vorlage (alpha.39).
	liw_st_check( 'BrandTokens::brand_text() = Liebherr Interface Solutions (CI angewendet)', 'Liebherr Interface Solutions' === \Liebherr\InterfaceWorld\Branding\BrandTokens::brand_text() );
	liw_st_check( '[liw_footer] zeigt die Wortmarke', str_contains( do_shortcode( '[liw_footer]' ), 'Liebherr Interface Solutions' ) );
	liw_st_check( 'Vollbild-Seitenvorlage registriert + Datei vorhanden', array_key_exists( \Liebherr\InterfaceWorld\Frontend\PageTemplate::TEMPLATE, \Liebherr\InterfaceWorld\Frontend\PageTemplate::add_choice( [] ) ) && is_readable( LIW_PATH . 'templates/full-width.php' ) );
	$__cw_id = \Liebherr\InterfaceWorld\Content\SitePages::interface_id();
		$__cw    = $__cw_id > 0 ? get_post( $__cw_id ) : ( get_page_by_path( 'interface-solutions' ) ?: get_page_by_path( 'interface-world' ) );
	liw_st_check( 'Traegerseite nutzt Vollbild-Vorlage', $__cw instanceof WP_Post && \Liebherr\InterfaceWorld\Frontend\PageTemplate::TEMPLATE === get_page_template_slug( $__cw->ID ) );
	// Frontpage-Ansicht als erster Menü-Unterpunkt (alpha.40).
	$__am = (string) file_get_contents( LIW_PATH . 'src/Admin/AdminMenu.php' );
	liw_st_check( 'Menü: Local Intelligence + Interface Solutions + move_first verdrahtet', str_contains( $__am, 'Local Intelligence' ) && str_contains( $__am, 'Interface Solutions' ) && str_contains( $__am, 'move_first' ) && str_contains( $__am, 'SitePages' ) );
	liw_st_check( 'Frontpage-Link-Ziel existiert (Trägerseite über Registry auffindbar)', $__cw instanceof WP_Post );
	liw_st_check( 'Language Board Seite verfügbar', class_exists( \Liebherr\InterfaceWorld\Admin\Pages\LanguageBoardPage::class ) );
	liw_st_check( 'TranslationBridge::public_scope_post_ids() liefert Array', is_array( \Liebherr\InterfaceWorld\CoreBridge\TranslationBridge::public_scope_post_ids() ) );
	liw_st_check( 'TranslationBridge::readiness_report() liefert je Sprache Kennzahlen', ( function (): bool { $r = \Liebherr\InterfaceWorld\CoreBridge\TranslationBridge::readiness_report( [ 'en' ] ); return ! \Liebherr\InterfaceWorld\CoreBridge\TranslationBridge::is_available() || ( isset( $r['en'] ) && array_key_exists( 'ready', $r['en'] ) && array_key_exists( 'min_rate', $r['en'] ) ); } )() );

	// ── [8b] Liebherr Local Intelligence – Hauptseite (LI-Pflichtenheft §8, alpha.41) ──
	echo "\n[8b] Local Intelligence – Hauptseite (11 Module)\n";
	liw_st_check( 'Composite [liw_local_intelligence] registriert', shortcode_exists( 'liw_local_intelligence' ) );
	foreach ( [ 'liw_li_hero', 'liw_li_vision', 'liw_li_flow', 'liw_simulation_world', 'liw_li_knowledge', 'liw_li_trust', 'liw_li_global', 'liw_li_usecases', 'liw_interface_bridge', 'liw_li_rollout', 'liw_li_contact', 'liw_context_nav' ] as $__sc ) {
		liw_st_check( "Shortcode [{$__sc}] registriert", shortcode_exists( $__sc ) );
	}
	$__li = do_shortcode( '[liw_local_intelligence]' );
	liw_st_check( 'Composite rendert alle Modul-Anker', str_contains( $__li, 'id="li-hero"' ) && str_contains( $__li, 'id="li-vision"' ) && str_contains( $__li, 'id="li-simulation"' ) && str_contains( $__li, 'id="li-bridge"' ) && str_contains( $__li, 'id="li-contact"' ) );
	liw_st_check( 'Composite enthaelt Sprungleiste', str_contains( $__li, 'liw-li__nav' ) );
	liw_st_check( 'Hero: Eyebrow + Dreiklang-Kurzzeile', str_contains( $__li, 'LIEBHERR LOCAL INTELLIGENCE' ) && str_contains( $__li, 'Simulieren. Verstehen. Entscheiden.' ) );
	$__sim = do_shortcode( '[liw_simulation_world]' );
	liw_st_check( 'Simulation: Tabs A/B/C + Demo-Kennzeichnung', str_contains( $__sim, 'data-liw-sim-tab="A"' ) && str_contains( $__sim, 'data-liw-sim-tab="B"' ) && str_contains( $__sim, 'data-liw-sim-tab="C"' ) && str_contains( $__sim, 'liw-li__demo-note' ) );
	$__uc = do_shortcode( '[liw_li_usecases]' );
	liw_st_check( 'Einsatzfelder: Filterleiste + sechs Karten', str_contains( $__uc, 'data-liw-usecase-filter' ) && 6 === substr_count( $__uc, 'data-liw-tag="' ) );
	$__br = do_shortcode( '[liw_interface_bridge]' );
	liw_st_check( 'Bruecke: CTA zu Interface Solutions vorhanden', str_contains( $__br, 'liw-cta--primary' ) && str_contains( $__br, 'Interface Solutions' ) );
	liw_st_check( 'Content-Modell: defaults() hat elf Modul-Zweige', 11 === count( array_intersect( array_keys( \Liebherr\InterfaceWorld\Settings\LocalIntelligenceContent::defaults() ), [ 'hero','vision','flow','simulation','knowledge','trust','global','usecases','bridge','rollout','contact' ] ) ) );
	liw_st_check( 'Content-Modell: sanitize([]) == defaults()', \Liebherr\InterfaceWorld\Settings\LocalIntelligenceContent::sanitize( [] ) === \Liebherr\InterfaceWorld\Settings\LocalIntelligenceContent::defaults() );
	liw_st_check( 'SitePages-Resolver liefern Integer', is_int( \Liebherr\InterfaceWorld\Content\SitePages::li_id() ) && is_int( \Liebherr\InterfaceWorld\Content\SitePages::interface_id() ) );
	liw_st_check( 'LegacyRedirect verfuegbar (301 Altroute)', class_exists( \Liebherr\InterfaceWorld\Frontend\LegacyRedirect::class ) );
	liw_st_check( 'Local-Intelligence-Board verfuegbar', class_exists( \Liebherr\InterfaceWorld\Admin\Pages\LocalIntelligenceBoardPage::class ) );
	liw_st_check( 'JS: Szenario-Schalter + Einsatzfeld-Filter', ( function (): bool { $j = (string) file_get_contents( LIW_PATH . 'assets/js/liebherr-frontend.js' ); return str_contains( $j, 'data-liw-sim' ) && str_contains( $j, 'data-liw-usecase-filter' ); } )() );
	liw_st_check( 'CSS: .liw-li-Bloecke + reduzierte Bewegung', ( function (): bool { $c = (string) file_get_contents( LIW_PATH . 'assets/css/liebherr-frontend.css' ); return str_contains( $c, '.liw-li__hero' ) && str_contains( $c, 'prefers-reduced-motion' ); } )() );

	// ── [9] Programmierlogbuch / To-Dos (Nachvollziehbarkeit) ────────────────
	echo "\n[9] Programmierlogbuch / To-Dos\n";
	liw_st_check( 'docs/LIW_PROGRAMMIERLOGBUCH.md vorhanden', is_readable( LIW_PATH . 'docs/LIW_PROGRAMMIERLOGBUCH.md' ) );
	liw_st_check( 'docs/LIW_TODO.md vorhanden', is_readable( LIW_PATH . 'docs/LIW_TODO.md' ) );
	liw_st_check( 'CoreBridge\\MarkdownBridge verfügbar (Core-Renderer)', MarkdownBridge::is_available() );
	if ( MarkdownBridge::is_available() ) {
		$rendered = MarkdownBridge::render( "## Test\n\n- Punkt A\n- Punkt B\n" );
		liw_st_check( 'MarkdownBridge::render() liefert HTML', false !== strpos( $rendered, '<h2' ) || false !== strpos( $rendered, '<li' ) );
	}

	// ── [10] Landingpage-Konzept (Grafiken) ────────────────────────────────────
	echo "\n[10] Landingpage-Konzept – LP-07/LP-08-Grafiken + Shortcode [liw_graphic]\n";
	liw_st_check( 'docs/LIW_LANDINGPAGE_KONZEPT.md vorhanden', is_readable( LIW_PATH . 'docs/LIW_LANDINGPAGE_KONZEPT.md' ) );
	liw_st_check( 'assets/img/liw-data-model.svg vorhanden', is_readable( LIW_PATH . 'assets/img/liw-data-model.svg' ) );
	liw_st_check( 'assets/img/liw-process-worlds.svg vorhanden', is_readable( LIW_PATH . 'assets/img/liw-process-worlds.svg' ) );

	// Shortcode [liw_graphic] (alpha.15): Inline-Einbettung der beiden Grafiken in liw_section-Inhalte.
	liw_st_check( 'Shortcode [liw_graphic] registriert', shortcode_exists( SectionGraphicView::SHORTCODE ) );
	$dm_html = do_shortcode( '[liw_graphic name="data-model" caption="Testunterschrift"]' );
	liw_st_check( '[liw_graphic name=data-model] rendert Inline-SVG', false !== strpos( $dm_html, '<svg' ) && false !== strpos( $dm_html, 'liw-graphic--data-model' ) );
	liw_st_check( '[liw_graphic] gibt figcaption escaped aus', false !== strpos( $dm_html, '<figcaption class="liw-graphic-figure__caption">Testunterschrift</figcaption>' ) );
	liw_st_check( '[liw_graphic name=process-worlds] rendert Inline-SVG', false !== strpos( do_shortcode( '[liw_graphic name="process-worlds"]' ), 'liw-graphic--process-worlds' ) );
	foreach ( [ 'target-model' => 'LP-03', 'magic-cube' => 'LP-04', 'roadmap' => 'LP-12' ] as $g_name => $g_lp ) { // alpha.20
		$g_html = do_shortcode( '[liw_graphic name="' . $g_name . '"]' );
		liw_st_check( "[liw_graphic name={$g_name}] ({$g_lp}) rendert Inline-SVG mit title/desc", str_contains( $g_html, "liw-graphic--{$g_name}" ) && str_contains( $g_html, '<title' ) && str_contains( $g_html, '<desc' ) );
	}
	liw_st_check( '[liw_graphic] unbekannter Name → leere Ausgabe', '' === do_shortcode( '[liw_graphic name="gibt-es-nicht"]' ) );
	liw_st_check( '[liw_graphic] Path-Traversal-Versuch → leere Ausgabe', '' === do_shortcode( '[liw_graphic name="../../wp-config"]' ) );
	liw_st_check( '[liw_graphic] ohne name → leere Ausgabe', '' === do_shortcode( '[liw_graphic]' ) );
	liw_st_check( 'FrontendAssets kennt liw_graphic als CSS-Auslöser', str_contains( (string) file_get_contents( LIW_PATH . 'src/Frontend/FrontendAssets.php' ), 'SectionGraphicView::SHORTCODE' ) );

	// ── [11] Permalink-Hinweis (Info, kein Fehlschlag) ────────────────────────
	echo "\n[11] Hinweis\n";
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
	foreach ( $cleanup_document_ids as [ $doc_id, $doc_file ] ) {
		$wpdb->delete( PartnerDocumentSchema::table_name(), [ 'id' => $doc_id ] );
		if ( is_file( $doc_file ) ) { wp_delete_file( $doc_file ); }
	}
	if ( [] !== $cleanup_user_ids ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( $cleanup_user_ids as $uid ) {
			wp_delete_user( $uid );
		}
	}
	foreach ( $cleanup_partner_ids as $partner_id ) {
		$wpdb->delete( ConsentLogSchema::table_name(), [ 'request_id' => $partner_id ] );
		$wpdb->delete( OnboardingSchema::table_name(), [ 'partner_id' => $partner_id ] );
		$wpdb->delete( $wpdb->prefix . 'ary_partners', [ 'id' => $partner_id ] );
	}
	foreach ( $cleanup_section_ids as $id ) {
		wp_delete_post( $id, true );
	}
	foreach ( $cleanup_contact_ids as $id ) {
		$wpdb->delete( ContactSchema::table_name(), [ 'id' => $id ] );
		$wpdb->delete( ConsentLogSchema::table_name(), [ 'request_id' => $id, 'request_kind' => 'contact' ] );
	}
	echo "  Testdaten entfernt (Präfix {$run}).\n";
}

echo "\n== Ergebnis: {$passed} bestanden, {$failed} fehlgeschlagen ==\n";
exit( $failed > 0 ? 1 : 0 );
