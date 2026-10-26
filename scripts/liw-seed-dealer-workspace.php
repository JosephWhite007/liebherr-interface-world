<?php
/**
 * Liebherr World – Dealer Workspace: Seite anlegen und Händler mit dem Core-Mandanten verknüpfen (Phase 3).
 *
 * Legt `/dealer-workspace/` mit [liw_header][liw_dealer_workspace][liw_footer] an (Vollbild-Vorlage), merkt die ID in
 * `liw_dealer_workspace_page_id`, setzt die Core-Zugangsregel »mit Code« und trägt optional `--dealer=<kürzel>
 * --org=<id>` die Verknüpfung Händler ↔ Addressbook-Mandant ein. Idempotent (--confirm).
 *
 *   docker exec araliya_wordpress php \
 *     /var/www/html/wp-content/plugins/liebherr-interface-world/scripts/liw-seed-dealer-workspace.php --dealer=tradler --org=3 --confirm
 *
 * @package Liebherr\InterfaceWorld
 * @since   0.1.0-alpha.152
 */

declare( strict_types = 1 );

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "Nur für die Kommandozeile.\n" );
	exit( 2 );
}
if ( ! in_array( '--confirm', $argv, true ) ) {
	fwrite( STDOUT, "Trockenlauf-Schutz: mit --confirm ausführen. Legt die Dealer-Workspace-Seite an und verknüpft optional Händler ↔ Mandant.\n" );
	exit( 0 );
}

require_once dirname( __DIR__, 4 ) . '/wp-load.php';

use Liebherr\InterfaceWorld\CoreBridge\GateBridge;
use Liebherr\InterfaceWorld\DealerWorkspace\WorkspaceContext;
use Liebherr\InterfaceWorld\DealerWorkspace\WorkspaceView;
use Liebherr\InterfaceWorld\Frontend\PageTemplate;
use Liebherr\InterfaceWorld\SalesInterface\DealerRepository;
use Liebherr\InterfaceWorld\SalesInterface\DealerSchema;

$opts = [ 'dealer' => '', 'org' => '' ];
foreach ( $argv as $arg ) {
	if ( preg_match( '/^--([a-z]+)=(.*)$/', $arg, $m ) && array_key_exists( $m[1], $opts ) ) {
		$opts[ $m[1] ] = trim( $m[2] );
	}
}

echo "== Dealer Workspace: Seite anlegen ==\n";
DealerSchema::create_table(); // org_id-Spalte (additiv)

$slug     = 'dealer-workspace';
$existing = get_page_by_path( $slug );
$args     = [ 'post_title' => 'Dealer Workspace', 'post_name' => $slug, 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => "<!-- wp:shortcode -->[liw_header]<!-- /wp:shortcode -->\n\n<!-- wp:shortcode -->[" . WorkspaceView::SHORTCODE . "]<!-- /wp:shortcode -->\n\n<!-- wp:shortcode -->[liw_footer]<!-- /wp:shortcode -->" ];
if ( $existing instanceof WP_Post ) {
	$args['ID'] = $existing->ID;
	wp_update_post( $args );
	$page_id = (int) $existing->ID;
	echo "  [1] Seite aktualisiert (#{$page_id}).\n";
} else {
	$page_id = (int) wp_insert_post( $args );
	echo "  [1] Seite angelegt (#{$page_id}).\n";
}
update_post_meta( $page_id, '_wp_page_template', PageTemplate::TEMPLATE );
update_option( WorkspaceContext::OPTION_PAGE, $page_id );
echo "  [2] Vollbild-Vorlage, Option " . WorkspaceContext::OPTION_PAGE . " gesetzt.\n";
echo '  [3] Zugangsschutz: ' . ( GateBridge::protect( $page_id ) ? 'Core-Regel »mit Code« gesetzt' : 'Core-Gate nicht verfügbar' ) . "\n";

if ( '' !== $opts['dealer'] && ctype_digit( $opts['org'] ) ) {
	$d = DealerRepository::find( $opts['dealer'] );
	if ( null === $d ) {
		fwrite( STDERR, "  [4] Händler {$opts['dealer']} nicht gefunden.\n" );
	} else {
		DealerRepository::save( [ 'code' => $d['code'], 'name' => $d['name'], 'country' => $d['country'], 'environment' => $d['environment'], 'sales_account_number' => $d['sales_account_number'], 'user_email' => $d['user_email'], 'write_enabled' => $d['write_enabled'] ? 1 : 0, 'status' => $d['status'], 'notes' => $d['notes'], 'org_id' => (int) $opts['org'] ], 0 );
		echo "  [4] Händler {$d['code']} ↔ Addressbook-Mandant #{$opts['org']} verknüpft.\n";
	}
}
flush_rewrite_rules( false );
echo "\nFertig. Seite: " . get_permalink( $page_id ) . "\nGgf. Einstellungen → Permalinks → Speichern.\n";
