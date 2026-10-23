<?php
/**
 * Liebherr World – Sales Interface: Unterseite anlegen (ADR-LIW-SALES-001, Stufe 1a).
 *
 * Legt die Seite `…/interface-solutions/sales/` mit [liw_header][liw_sales_interface][liw_footer] als Kind der
 * Interface-Solutions-Seite an (Vollbild-Vorlage), setzt die Core-Regel »mit Code« (Zugangsschutz), merkt die ID in
 * `liw_sales_page_id` und trägt – falls das Components Board bereits eigene Inhalte speichert – das Ziel der Karte
 * „Sales“ nach. Idempotent, keine Dubletten, keine Löschungen (Terminal-Regel).
 *
 *   docker exec araliya_wordpress php \
 *     /var/www/html/wp-content/plugins/liebherr-interface-world/scripts/liw-seed-sales-interface.php --confirm
 *
 * @package Liebherr\InterfaceWorld
 * @since   0.1.0-alpha.146
 */

declare( strict_types = 1 );

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "Nur für die Kommandozeile.\n" );
	exit( 2 );
}
if ( ! in_array( '--confirm', $argv, true ) ) {
	fwrite( STDOUT, "Trockenlauf-Schutz: mit --confirm ausführen. Legt die Sales-Interface-Seite an, schützt sie mit dem Core-Zugangscode und verlinkt die Karte Sales.\n" );
	exit( 0 );
}

require_once dirname( __DIR__, 4 ) . '/wp-load.php';

use Liebherr\InterfaceWorld\Content\SitePages;
use Liebherr\InterfaceWorld\CoreBridge\GateBridge;
use Liebherr\InterfaceWorld\Frontend\PageTemplate;
use Liebherr\InterfaceWorld\SalesInterface\SalesInterfaceView;
use Liebherr\InterfaceWorld\Settings\ComponentContent;

if ( ! class_exists( SalesInterfaceView::class ) ) {
	fwrite( STDERR, "Plugin liebherr-interface-world ist nicht aktiv.\n" );
	exit( 1 );
}

echo "== Sales Interface: Unterseite anlegen ==\n";

$parent_id = SitePages::interface_id();
if ( $parent_id <= 0 ) {
	fwrite( STDERR, "Interface-Solutions-Seite nicht gefunden – bitte zuerst die Landingpage anlegen.\n" );
	exit( 1 );
}
echo "  [0] Elternseite Interface Solutions: #{$parent_id}\n";

$slug    = 'sales';
$content = "<!-- wp:shortcode -->[liw_header]<!-- /wp:shortcode -->\n\n<!-- wp:shortcode -->[" . SalesInterfaceView::SHORTCODE . "]<!-- /wp:shortcode -->\n\n<!-- wp:shortcode -->[liw_footer]<!-- /wp:shortcode -->";

$existing = get_page_by_path( ComponentContent::SALES_TARGET );
$args     = [
	'post_title'   => 'Sales Interface',
	'post_name'    => $slug,
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_parent'  => $parent_id,
	'post_content' => $content,
];
if ( $existing instanceof WP_Post ) {
	$args['ID'] = $existing->ID;
	wp_update_post( $args );
	$page_id = (int) $existing->ID;
	echo "  [1] Seite aktualisiert (#{$page_id}).\n";
} else {
	$page_id = (int) wp_insert_post( $args );
	echo "  [1] Seite angelegt (#{$page_id}).\n";
}
if ( $page_id <= 0 ) {
	fwrite( STDERR, "Seite konnte nicht angelegt werden.\n" );
	exit( 1 );
}

update_post_meta( $page_id, '_wp_page_template', PageTemplate::TEMPLATE );
update_option( SalesInterfaceView::OPTION_PAGE, $page_id );
echo "  [2] Vollbild-Vorlage zugewiesen, ID in " . SalesInterfaceView::OPTION_PAGE . " gemerkt.\n";

if ( GateBridge::protect( $page_id ) ) {
	echo "  [3] Zugangsschutz: Core-Regel »mit Code« gesetzt (Code: Einstellung ARALIYA-Zugangsschutz).\n";
} else {
	echo "  [3] WARNUNG: Core-Zugangsschutz nicht verfügbar – Seite ist NICHT geschützt.\n";
}

$stored = get_option( ComponentContent::OPTION, [] );
if ( is_array( $stored ) && isset( $stored['process'] ) && is_array( $stored['process'] ) ) {
	$changed = false;
	foreach ( $stored['process'] as $i => $item ) {
		if ( is_array( $item ) && 0 === strcasecmp( (string) ( $item['title'] ?? '' ), 'Sales' ) && empty( $item['target'] ) ) {
			$stored['process'][ $i ]['target'] = ComponentContent::SALES_TARGET;
			$changed                           = true;
		}
	}
	if ( $changed ) {
		update_option( ComponentContent::OPTION, $stored );
		echo "  [4] Karte „Sales“ im Components Board verlinkt.\n";
	} else {
		echo "  [4] Karte „Sales“ bereits verlinkt oder nicht vorhanden – nichts zu tun.\n";
	}
} else {
	echo "  [4] Components Board nutzt Standardwerte – Karte „Sales“ ist dort bereits verlinkt.\n";
}

flush_rewrite_rules( false );
echo "\nFertig. Seite: " . get_permalink( $page_id ) . "\n";
echo "Hinweis: Einstellungen → Permalinks → Speichern, falls die Seite 404 liefert.\n";
echo "Hinweis: Auf Dev ist der Core-Zugangsschutz standardmäßig AUS (Entwicklungsumgebung) – Maske nur bei ary_site_gate_enabled=1.\n";
