<?php
/**
 * Liebherr Intelligence World – eigenständige Landingpage anlegen (Pflichtenheft-2, alpha.48).
 *
 * Legt die Seite `/liebherr-intelligence-world/` mit [liw_intelligence_world] an (Blue-Planet-Landing +
 * Eintrittsschleuse + Sitzungsleiste), Vollbild-Vorlage; ID in Option `liw_iw_page_id` (für den Menülink).
 * Eigenständige Ebene NEBEN Local Intelligence (JW-Entscheid 19.09.2026). Idempotent (--confirm).
 *
 *   docker exec araliya_wordpress php \
 *     /var/www/html/wp-content/plugins/liebherr-interface-world/scripts/liw-seed-intelligence-world.php --confirm
 *
 * @package Liebherr\InterfaceWorld
 * @since   0.1.0-alpha.48
 */

declare( strict_types = 1 );

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "Nur für die Kommandozeile.\n" );
	exit( 2 );
}
if ( ! in_array( '--confirm', $argv, true ) ) {
	fwrite( STDOUT, "Trockenlauf-Schutz: mit --confirm ausführen. Legt die Intelligence-World-Seite an.\n" );
	exit( 0 );
}

require_once dirname( __DIR__, 4 ) . '/wp-load.php';

use Liebherr\InterfaceWorld\Frontend\PageTemplate;

if ( ! class_exists( \Liebherr\InterfaceWorld\IntelligenceWorld\WorldView::class ) ) {
	fwrite( STDERR, "Plugin liebherr-interface-world ist nicht aktiv.\n" );
	exit( 1 );
}

echo "== Intelligence World: Landingpage anlegen ==\n";

$slug    = 'liebherr-intelligence-world';
$content = "<!-- wp:shortcode -->[liw_intelligence_world]<!-- /wp:shortcode -->\n\n"
	. "<!-- wp:shortcode -->[liw_footer]<!-- /wp:shortcode -->";

$existing = get_page_by_path( $slug );
$args = [
	'post_title'   => 'Liebherr Intelligence World',
	'post_name'    => $slug,
	'post_type'    => 'page',
	'post_status'  => 'publish',
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
if ( $page_id > 0 ) {
	update_post_meta( $page_id, '_wp_page_template', PageTemplate::TEMPLATE );
	update_option( 'liw_iw_page_id', $page_id );
	echo "  [1b] Vollbild-Vorlage zugewiesen, ID gemerkt.\n";
}

flush_rewrite_rules( false );
echo "\nFertig. Seite: " . get_permalink( $page_id ) . "\n";
echo "Hinweis: Demo-Code aus WorldContent (Standard „LIEBHERR-DEMO“); ggf. Einstellungen → Permalinks → Speichern.\n";
