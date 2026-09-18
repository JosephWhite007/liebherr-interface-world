<?php
/**
 * Liebherr Interface Solutions – Demo-Landingpage zusammenstellen (Präsentation, alpha.38).
 *
 * Baut aus den vorhandenen Bausteinen eine vorzeigbare Landingpage:
 *   1. stellt die 14 Standard-Abschnitte sicher (SectionSeeder),
 *   2. bestückt ausgewählte Abschnitte mit den Frontend-Shortcodes und veröffentlicht sie,
 *   3. legt eine Trägerseite „Interface World" mit [liw_header] + [liw_hero] + [liw_landingpage] an.
 *
 * Nur DEMO-Aufbereitung (keine echten Geschäftsdaten, §4). Idempotent (mehrfach ausführbar).
 *
 *   docker exec araliya_wordpress php \
 *     /var/www/html/wp-content/plugins/liebherr-interface-world/scripts/liw-seed-demo-landing.php --confirm
 *
 * @package Liebherr\InterfaceWorld
 * @since   0.1.0-alpha.38
 */

declare( strict_types = 1 );

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "Nur für die Kommandozeile.\n" );
	exit( 2 );
}
if ( ! in_array( '--confirm', $argv, true ) ) {
	fwrite( STDOUT, "Trockenlauf-Schutz: mit --confirm ausführen. Stellt eine Demo-Landingpage zusammen.\n" );
	exit( 0 );
}

require_once dirname( __DIR__, 4 ) . '/wp-load.php';

use Liebherr\InterfaceWorld\Content\SectionBlueprint;
use Liebherr\InterfaceWorld\Content\SectionSeeder;
use Liebherr\InterfaceWorld\CPT\LiwSectionCpt;

if ( ! class_exists( SectionSeeder::class ) ) {
	fwrite( STDERR, "Plugin liebherr-interface-world ist nicht aktiv.\n" );
	exit( 1 );
}

echo "== Demo-Landingpage zusammenstellen ==\n";

// 1. Standard-Abschnitte sicherstellen.
$res = SectionSeeder::seed_missing( 0 );
echo '  [1] Abschnitte: ' . count( $res['created'] ) . ' neu, ' . count( $res['skipped'] ) . " vorhanden.\n";

// 2. Abschnitte mit Shortcodes bestücken + veröffentlichen.
$compose = [
	'LP-06' => '[liw_world_map]',
	'LP-08' => '[liw_process_worlds]',
	'LP-12' => '[liw_roadmap]',
	'LP-11' => "[liw_onboarding_steps]\n\n[liw_onboarding_form]",
	'LP-13' => '[liw_contact_form]',
];
$published = 0;
foreach ( $compose as $code => $shortcodes ) {
	$ids = get_posts( [
		'post_type'      => LiwSectionCpt::POST_TYPE,
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_query'     => [ [ 'key' => SectionBlueprint::META_CODE, 'value' => $code ] ],
	] );
	if ( empty( $ids ) ) {
		echo "  [!] Abschnitt {$code} nicht gefunden\n";
		continue;
	}
	$content = '';
	foreach ( explode( "\n\n", $shortcodes ) as $sc ) {
		$sc = trim( $sc );
		if ( '' !== $sc ) {
			$content .= '<!-- wp:shortcode -->' . $sc . '<!-- /wp:shortcode -->' . "\n\n";
		}
	}
	wp_update_post( [ 'ID' => (int) $ids[0], 'post_content' => trim( $content ), 'post_status' => 'publish' ] );
	echo "  [2] {$code} veröffentlicht mit {$shortcodes}\n";
	$published++;
}

// 2b. Noch nicht aufbereitete Abschnitte für den Demo-Eindruck ausblenden (auf Entwurf; reversibel).
$keep_published = array_keys( $compose ); // nur die bestückten Abschnitte zeigen (Hero kommt direkt via [liw_hero]).
$drafted = 0;
foreach ( SectionBlueprint::all() as $code => $_def ) {
	if ( in_array( $code, $keep_published, true ) ) {
		continue;
	}
	$ids = get_posts( [
		'post_type'      => LiwSectionCpt::POST_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query,WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_query'     => [ [ 'key' => SectionBlueprint::META_CODE, 'value' => $code ] ],
	] );
	if ( ! empty( $ids ) ) {
		wp_update_post( [ 'ID' => (int) $ids[0], 'post_status' => 'draft' ] );
		$drafted++;
	}
}
echo "  [2b] {$drafted} noch nicht aufbereitete Abschnitte auf Entwurf gesetzt (im Content Board reaktivierbar).\n";

// 3. Trägerseite „Interface World".
$slug     = 'interface-world';
$existing = get_page_by_path( $slug );
$page_content = "<!-- wp:shortcode -->[liw_header]<!-- /wp:shortcode -->\n\n"
	. "<!-- wp:shortcode -->[liw_hero]<!-- /wp:shortcode -->\n\n"
	. "<!-- wp:shortcode -->[liw_landingpage]<!-- /wp:shortcode -->\n\n"
	. "<!-- wp:shortcode -->[liw_footer]<!-- /wp:shortcode -->";
$page_args = [
	'post_title'   => 'Interface World Connections',
	'post_name'    => $slug,
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_content' => $page_content,
];
if ( $existing instanceof WP_Post ) {
	$page_args['ID'] = $existing->ID;
	wp_update_post( $page_args );
	$page_id = $existing->ID;
	echo "  [3] Trägerseite aktualisiert (#{$page_id}).\n";
} else {
	$page_id = (int) wp_insert_post( $page_args );
	echo "  [3] Trägerseite angelegt (#{$page_id}).\n";
}

// Vollbild-Vorlage (ohne Theme-Kopf/-Fuß) zuweisen.
if ( $page_id > 0 ) {
	update_post_meta( $page_id, '_wp_page_template', \Liebherr\InterfaceWorld\Frontend\PageTemplate::TEMPLATE );
	echo "  [3b] Vollbild-Vorlage zugewiesen.\n";
}

echo "\n" . 'Fertig. ' . $published . ' Abschnitte veröffentlicht. Seite: ' . get_permalink( $page_id ) . "\n";
echo 'Hinweis: ggf. Einstellungen → Permalinks → Speichern (CPT-Rewrite).' . "\n";
