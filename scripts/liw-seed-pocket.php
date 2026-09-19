<?php
/**
 * Liebherr World – Pocket Information: Startseite anlegen (6. Reiter, ADR-LIW-MYL-001 R5).
 *
 * Legt die Seite `/pocket-information/` mit [liw_pocket] an (Vollbild-Vorlage). Seiten-ID in Option
 * `liw_pocket_page_id` (Navigation/WorldSwitcher). Sichtbar erst mit Flag `liw_pocket_enabled` und Rolle mit
 * `liw_myl_access`. Idempotent (--confirm).
 *
 *   docker exec araliya_wordpress php \
 *     /var/www/html/wp-content/plugins/liebherr-interface-world/scripts/liw-seed-pocket.php --confirm
 *
 * @package Liebherr\InterfaceWorld
 * @since   0.1.0-alpha.124
 */

declare( strict_types = 1 );

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "Nur für die Kommandozeile.\n" );
	exit( 2 );
}
if ( ! in_array( '--confirm', $argv, true ) ) {
	fwrite( STDOUT, "Trockenlauf-Schutz: mit --confirm ausführen. Legt die Pocket-Information-Seite an.\n" );
	exit( 0 );
}

require_once dirname( __DIR__, 4 ) . '/wp-load.php';

use Liebherr\InterfaceWorld\Frontend\PageTemplate;

if ( ! class_exists( \Liebherr\InterfaceWorld\Pocket\PocketView::class ) ) {
	fwrite( STDERR, "Plugin liebherr-interface-world ist nicht aktiv.\n" );
	exit( 1 );
}

echo "== Pocket Information: Startseite anlegen ==\n";

$slug    = 'pocket-information';
$content = "<!-- wp:shortcode -->[liw_pocket]<!-- /wp:shortcode -->";

$existing = get_page_by_path( $slug );
$args = [
	'post_title'   => 'Pocket Information',
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
	update_option( 'liw_pocket_page_id', $page_id );
	echo "  [1b] Vollbild-Vorlage zugewiesen, ID in liw_pocket_page_id gemerkt.\n";
}

flush_rewrite_rules( false );
echo "\nFertig. Seite: " . get_permalink( $page_id ) . "\n";
echo "Hinweis: Reiter erst sichtbar mit Flags liw_myl_enabled=1 + liw_pocket_enabled=1 und Rolle mit liw_myl_access.\n";
echo "Ggf. Einstellungen → Permalinks → Speichern.\n";
