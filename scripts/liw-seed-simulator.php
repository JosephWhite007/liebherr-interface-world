<?php
/**
 * Liebherr Simulation World – Startseite anlegen (alpha.57).
 *
 * Legt die Seite `/liebherr-simulator/` mit [liw_simulator] an (Cockpit-Startbild „Start your journey",
 * Vollbild-Vorlage). Der Start-CTA führt in die Intelligence World (SimulatorView::target() nutzt
 * `liw_simulator_target` bzw. Fallback `liw_iw_page_id`). Seiten-ID in Option `liw_simulator_page_id`
 * (z. B. für einen Menülink). Idempotent (--confirm).
 *
 *   docker exec araliya_wordpress php \
 *     /var/www/html/wp-content/plugins/liebherr-interface-world/scripts/liw-seed-simulator.php --confirm
 *
 * @package Liebherr\InterfaceWorld
 * @since   0.1.0-alpha.57
 */

declare( strict_types = 1 );

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "Nur für die Kommandozeile.\n" );
	exit( 2 );
}
if ( ! in_array( '--confirm', $argv, true ) ) {
	fwrite( STDOUT, "Trockenlauf-Schutz: mit --confirm ausführen. Legt die Simulator-Startseite an.\n" );
	exit( 0 );
}

require_once dirname( __DIR__, 4 ) . '/wp-load.php';

use Liebherr\InterfaceWorld\Frontend\PageTemplate;

if ( ! class_exists( \Liebherr\InterfaceWorld\Frontend\SimulatorView::class ) ) {
	fwrite( STDERR, "Plugin liebherr-interface-world ist nicht aktiv.\n" );
	exit( 1 );
}

echo "== Simulation World: Startseite anlegen ==\n";

$slug    = 'liebherr-simulator';
$content = "<!-- wp:shortcode -->[liw_simulator]<!-- /wp:shortcode -->";

$existing = get_page_by_path( $slug );
$args = [
	'post_title'   => 'Liebherr Simulation World',
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
	update_option( 'liw_simulator_page_id', $page_id );
	echo "  [1b] Vollbild-Vorlage zugewiesen, ID in liw_simulator_page_id gemerkt.\n";
}

flush_rewrite_rules( false );
echo "\nFertig. Seite: " . get_permalink( $page_id ) . "\n";
echo "Hinweis: Startbild aus Option liw_simulator_image_id (freigegebenes Cockpit-Bild) oder Datei\n";
echo "assets/img/liw-simulator-start.*; Start-CTA führt in die Intelligence World.\n";
echo "Ggf. Einstellungen → Permalinks → Speichern.\n";
