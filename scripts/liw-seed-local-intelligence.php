<?php
/**
 * Liebherr Local Intelligence – Hauptseite anlegen & Interface-Seite verschachteln (alpha.41).
 *
 * Baut die neue übergeordnete Hauptseite „Liebherr Local Intelligence" und ordnet die bestehende
 * Interface-Seite als Unterseite darunter ein (LI-Pflichtenheft §1/§3/§13):
 *   1. Hauptseite `/liebherr-local-intelligence/` mit [liw_header] + [liw_local_intelligence] + [liw_footer],
 *      Vollbild-Vorlage; ID in Option gespeichert.
 *   2. Interface-Seite: Elternseite = Hauptseite, Slug → `interface-solutions`
 *      (URL /liebherr-local-intelligence/interface-solutions/), Kontextnavigation + Rücklink ergänzt.
 *   3. Rewrite-Regeln aktualisieren (301 der Altroute /interface-world/ übernimmt LegacyRedirect).
 *
 * Nur DEMO-Aufbereitung, keine echten Geschäftsdaten (§4). Idempotent (mehrfach ausführbar).
 *
 *   docker exec araliya_wordpress php \
 *     /var/www/html/wp-content/plugins/liebherr-interface-world/scripts/liw-seed-local-intelligence.php --confirm
 *
 * @package Liebherr\InterfaceWorld
 * @since   0.1.0-alpha.41
 */

declare( strict_types = 1 );

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "Nur für die Kommandozeile.\n" );
	exit( 2 );
}
if ( ! in_array( '--confirm', $argv, true ) ) {
	fwrite( STDOUT, "Trockenlauf-Schutz: mit --confirm ausführen. Legt die Local-Intelligence-Hauptseite an und verschachtelt die Interface-Seite.\n" );
	exit( 0 );
}

require_once dirname( __DIR__, 4 ) . '/wp-load.php';

use Liebherr\InterfaceWorld\Content\SitePages;
use Liebherr\InterfaceWorld\Frontend\PageTemplate;

if ( ! class_exists( SitePages::class ) ) {
	fwrite( STDERR, "Plugin liebherr-interface-world ist nicht aktiv.\n" );
	exit( 1 );
}

echo "== Local Intelligence: Hauptseite + Verschachtelung ==\n";

// 1. Hauptseite Local Intelligence.
$li_content = "<!-- wp:shortcode -->[liw_header]<!-- /wp:shortcode -->\n\n"
	. "<!-- wp:shortcode -->[liw_local_intelligence]<!-- /wp:shortcode -->\n\n"
	. "<!-- wp:shortcode -->[liw_footer]<!-- /wp:shortcode -->";

$li_existing = get_page_by_path( SitePages::SLUG_LI );
$li_args = [
	'post_title'   => 'Liebherr Local Intelligence',
	'post_name'    => SitePages::SLUG_LI,
	'post_type'    => 'page',
	'post_status'  => 'publish',
	'post_content' => $li_content,
];
if ( $li_existing instanceof WP_Post ) {
	$li_args['ID'] = $li_existing->ID;
	wp_update_post( $li_args );
	$li_id = (int) $li_existing->ID;
	echo "  [1] Hauptseite aktualisiert (#{$li_id}).\n";
} else {
	$li_id = (int) wp_insert_post( $li_args );
	echo "  [1] Hauptseite angelegt (#{$li_id}).\n";
}
if ( $li_id > 0 ) {
	update_post_meta( $li_id, '_wp_page_template', PageTemplate::TEMPLATE );
	SitePages::set_li_id( $li_id );
	echo "  [1b] Vollbild-Vorlage zugewiesen, ID gemerkt.\n";
}

// 2. Interface-Seite ermitteln (bestehend unter interface-solutions oder alt interface-world).
$if_page = get_page_by_path( SitePages::SLUG_LI . '/' . SitePages::SLUG_INTERFACE )
	?: get_page_by_path( SitePages::SLUG_INTERFACE )
	?: get_page_by_path( SitePages::SLUG_LEGACY );

if ( ! $if_page instanceof WP_Post ) {
	// Falls die Demo-Landing-Seite noch nicht erzeugt wurde: minimal anlegen.
	$if_id = (int) wp_insert_post( [
		'post_title'   => 'Interface World Connections',
		'post_name'    => SitePages::SLUG_INTERFACE,
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_content' => "<!-- wp:shortcode -->[liw_header]<!-- /wp:shortcode -->\n\n<!-- wp:shortcode -->[liw_hero]<!-- /wp:shortcode -->\n\n<!-- wp:shortcode -->[liw_landingpage]<!-- /wp:shortcode -->\n\n<!-- wp:shortcode -->[liw_footer]<!-- /wp:shortcode -->",
	] );
	echo "  [2] Interface-Seite neu angelegt (#{$if_id}). Hinweis: liw-seed-demo-landing.php für die Abschnittsinhalte ausführen.\n";
	$if_page = get_post( $if_id );
} else {
	$if_id = (int) $if_page->ID;
	echo "  [2] Interface-Seite gefunden (#{$if_id}, Slug „{$if_page->post_name}“).\n";
}

// 2b. Verschachteln: Eltern = Hauptseite, Slug → interface-solutions, Kontextnavigation einfügen.
if ( $if_page instanceof WP_Post && $if_id > 0 && $li_id > 0 ) {
	$content = (string) $if_page->post_content;

	// Kontextnavigation (Breadcrump oben, Rücklink unten) idempotent einhängen.
	if ( false === strpos( $content, '[liw_context_nav]' ) ) {
		// Nach dem ersten [liw_header]-Block einsetzen, sonst am Anfang.
		$crumb = "\n\n<!-- wp:shortcode -->[liw_context_nav]<!-- /wp:shortcode -->";
		if ( false !== strpos( $content, '[liw_header]' ) ) {
			$content = preg_replace( '/(\[liw_header\]<!-- \/wp:shortcode -->)/', '$1' . $crumb, $content, 1 );
		} else {
			$content = '<!-- wp:shortcode -->[liw_context_nav]<!-- /wp:shortcode -->' . "\n\n" . $content;
		}
	}
	if ( false === strpos( $content, 'liw_context_nav position="bottom"' ) ) {
		$back = "<!-- wp:shortcode -->[liw_context_nav position=\"bottom\"]<!-- /wp:shortcode -->\n\n";
		if ( false !== strpos( $content, '[liw_footer]' ) ) {
			$content = str_replace( '<!-- wp:shortcode -->[liw_footer]', $back . '<!-- wp:shortcode -->[liw_footer]', $content );
		} else {
			$content .= "\n\n" . $back;
		}
	}

	wp_update_post( [
		'ID'          => $if_id,
		'post_parent' => $li_id,
		'post_name'   => SitePages::SLUG_INTERFACE,
		'post_content'=> $content,
	] );
	update_post_meta( $if_id, '_wp_page_template', PageTemplate::TEMPLATE );
	SitePages::set_interface_id( $if_id );
	echo "  [2b] Verschachtelt unter Hauptseite, Slug „interface-solutions“, Kontextnavigation ergänzt.\n";
}

// 3. Rewrite-Regeln aktualisieren (neue Pfadtiefe + Altrouten-Redirect).
flush_rewrite_rules( false );
echo "  [3] Rewrite-Regeln aktualisiert.\n";

echo "\nFertig.\n";
echo '  Hauptseite:   ' . get_permalink( $li_id ) . "\n";
if ( $if_id > 0 ) {
	echo '  Unterseite:   ' . get_permalink( $if_id ) . "\n";
	echo '  Altroute 301: /' . SitePages::SLUG_LEGACY . "/ → Unterseite (LegacyRedirect).\n";
}
echo "Hinweis: ggf. Einstellungen → Permalinks → Speichern.\n";
