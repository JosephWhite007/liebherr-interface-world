<?php
/**
 * Liebherr Adventures – Demo-Adventures + Insel-Seite (Prototyp, §22.14). Idempotent (--confirm).
 *
 * Legt klar gekennzeichnete Beispiel-Adventures an (Meta _liw_adv_demo=1; bei erneutem Lauf ersetzt) und
 * eine Seite `/liebherr-adventures/` mit [liw_adventures]. Ein kritisches Beispiel bleibt bewusst „pending"
 * (nicht öffentlich, §22.5). Keine echten personenbezogenen/vertraulichen Daten (§23.3).
 *
 *   docker exec araliya_wordpress php \
 *     /var/www/html/wp-content/plugins/liebherr-interface-world/scripts/liw-seed-adventures.php --confirm
 *
 * @package Liebherr\InterfaceWorld
 * @since   0.1.0-alpha.51
 */

declare( strict_types = 1 );

if ( 'cli' !== PHP_SAPI ) { fwrite( STDERR, "Nur CLI.\n" ); exit( 2 ); }
if ( ! in_array( '--confirm', $argv, true ) ) { fwrite( STDOUT, "Trockenlauf: mit --confirm ausführen.\n" ); exit( 0 ); }

require_once dirname( __DIR__, 4 ) . '/wp-load.php';

use Liebherr\InterfaceWorld\Adventures\AdventureCpt;
use Liebherr\InterfaceWorld\Adventures\AdventureService;
use Liebherr\InterfaceWorld\Frontend\PageTemplate;

if ( ! class_exists( AdventureService::class ) ) { fwrite( STDERR, "Plugin nicht aktiv.\n" ); exit( 1 ); }

echo "== Liebherr Adventures: Demo ==\n";

// 1. Alte Demo-Adventures entfernen (idempotent).
$old = get_posts( [ 'post_type' => AdventureCpt::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_liw_adv_demo' ] );
foreach ( $old as $id ) { wp_delete_post( (int) $id, true ); }
echo '  [1] ' . count( $old ) . " alte Demo-Adventures entfernt.\n";

// 2. Neue Demo-Adventures.
$author = 1;
$demo = [
	[ 'title' => 'Neue Radlader-Generation im Einsatz', 'story' => 'Beeindruckende Übergabe an eine Niederlassung – Beispiel.', 'type' => 'field_experience', 'urgency' => 'funny', 'lat' => 48.10, 'lng' => 9.79, 'vis' => 'public_approved' ],
	[ 'title' => 'Werkzeugtipp: Drehmoment am Bolzen', 'story' => 'Sichere Reihenfolge beim Anziehen – Beispiel-Arbeitshinweis.', 'type' => 'tool_advice', 'urgency' => 'informative', 'lat' => 41.85, 'lng' => -87.65, 'vis' => 'public_approved' ],
	[ 'title' => 'Hydraulik-Auffälligkeit dokumentiert', 'story' => 'Leichte Verfärbung beobachtet – Beispiel-Beobachtung.', 'type' => 'fault_observation', 'urgency' => 'serious', 'lat' => -23.55, 'lng' => -46.63, 'vis' => 'public_approved' ],
	[ 'title' => 'Explosionszeichnung Getriebe', 'story' => 'Detailansicht einer Baugruppe – Beispiel.', 'type' => 'technical_detail', 'urgency' => 'informative', 'lat' => 25.20, 'lng' => 55.27, 'vis' => 'public_approved' ],
	[ 'title' => 'Come Together am Standort', 'story' => 'Gemeinsamer Termin des Teams – Beispiel.', 'type' => 'come_together', 'urgency' => 'funny', 'lat' => -33.87, 'lng' => 151.21, 'vis' => 'public_approved' ],
	[ 'title' => 'Foundation-Projekt Wasser', 'story' => 'Fortschritt eines Hilfsprojekts – Beispiel.', 'type' => 'liebherr_foundation', 'urgency' => 'informative', 'lat' => -26.20, 'lng' => 28.04, 'vis' => 'public_approved' ],
	// Kritisches Beispiel: bleibt „pending" (nicht öffentlich, §22.5).
	[ 'title' => 'Mögliche Leckage – kritisch', 'story' => 'Beispiel für kritischen Beitrag: nicht öffentlich, zur Prüfung.', 'type' => 'service_help', 'urgency' => 'critical', 'lat' => 45.07, 'lng' => 7.69, 'vis' => 'organization', 'keep_pending' => true ],
];

$pub = 0; $pending = 0;
foreach ( $demo as $d ) {
	$res = AdventureService::create( [
		'title' => $d['title'], 'story' => $d['story'], 'type' => $d['type'], 'urgency' => $d['urgency'],
		'visibility' => $d['vis'], 'intent' => 'submit', 'protection' => 'region',
		'lat' => $d['lat'], 'lng' => $d['lng'], 'author_id' => $author,
	] );
	if ( 0 === $res['id'] ) { continue; }
	update_post_meta( $res['id'], '_liw_adv_demo', 1 );
	if ( empty( $d['keep_pending'] ) ) {
		wp_update_post( [ 'ID' => $res['id'], 'post_status' => 'publish' ] );
		$pub++;
	} else {
		$pending++;
	}
}
echo "  [2] {$pub} veröffentlicht, {$pending} als kritisch/pending (nicht öffentlich).\n";

// 3. Insel-Seite.
$slug = 'liebherr-adventures';
$existing = get_page_by_path( $slug );
$content = "<!-- wp:shortcode -->[liw_header]<!-- /wp:shortcode -->\n\n<!-- wp:shortcode -->[liw_adventures]<!-- /wp:shortcode -->\n\n<!-- wp:shortcode -->[liw_footer]<!-- /wp:shortcode -->";
$args = [ 'post_title' => 'Liebherr Adventures', 'post_name' => $slug, 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => $content ];
if ( $existing instanceof WP_Post ) { $args['ID'] = $existing->ID; wp_update_post( $args ); $pid = (int) $existing->ID; echo "  [3] Seite aktualisiert (#{$pid}).\n"; }
else { $pid = (int) wp_insert_post( $args ); echo "  [3] Seite angelegt (#{$pid}).\n"; }
if ( $pid > 0 ) { update_post_meta( $pid, '_wp_page_template', PageTemplate::TEMPLATE ); update_option( 'liw_adventures_page_id', $pid ); }

flush_rewrite_rules( false );
echo "\nFertig. Seite: " . get_permalink( $pid ) . "\n";
