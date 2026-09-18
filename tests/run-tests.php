<?php
/**
 * Liebherr Interface Solutions – Selftest (ohne WordPress)
 *
 * Analog zu tests/YenneferBilling/run-tests.php im Core: minimale Sanity-Checks, die ohne
 * WP-Bootstrap laufen (Terminal-Regel/Tests §31 Liebherr-Pflichtenheft: Unit Tests für
 * Validatoren/Statuslogik). Vollständige Integrationstests (DB, Hooks, Shortcodes) laufen
 * mit `scripts/liw-selftest.php` in der Docker-Dev-Umgebung (seit alpha.10, Docker-
 * Praxistest – docker exec araliya_wordpress php .../liebherr-interface-world/scripts/liw-selftest.php).
 *
 * Ausführung: php tests/run-tests.php
 */

declare( strict_types = 1 );

$root = dirname( __DIR__ );

$failures = 0;
$checks   = 0;

/** @param mixed $actual */
function liw_assert( string $label, bool $condition, int &$checks, int &$failures ): void {
	$checks++;
	if ( $condition ) {
		echo "  [OK] {$label}\n";
		return;
	}
	$failures++;
	echo "  [FAIL] {$label}\n";
}

echo "== Liebherr Interface Solutions – Selftest ==\n";

// 1. Alle PHP-Dateien syntaktisch fehlerfrei (php -l je Datei).
echo "-- Syntax --\n";
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/src', FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $file ) {
	if ( 'php' !== $file->getExtension() ) {
		continue;
	}
	$output    = [];
	$exit_code = 0;
	exec( sprintf( 'php -l %s 2>&1', escapeshellarg( (string) $file ) ), $output, $exit_code );
	liw_assert( 'Syntax: ' . str_replace( $root . '/', '', (string) $file ), 0 === $exit_code, $checks, $failures );
}

// 2. Erlaubte direction-/status-Werte der Interface-/Simulation-Validierung (Statuslogik ohne WP).
echo "-- Validierungslisten (Statuslogik) --\n";
$catalog_service_src = file_get_contents( $root . '/src/Interfaces/InterfaceCatalogService.php' );
liw_assert(
	'ALLOWED_DIRECTIONS enthält inbound/outbound/bidirectional',
	false !== $catalog_service_src && str_contains( $catalog_service_src, "'inbound', 'outbound', 'bidirectional'" ),
	$checks,
	$failures
);
liw_assert(
	'ALLOWED_STATUSES enthält den vollen Lifecycle draft..retired',
	false !== $catalog_service_src && str_contains( $catalog_service_src, 'draft' ) && str_contains( $catalog_service_src, 'retired' ),
	$checks,
	$failures
);

// 2b. Tabellen-COMMENTs ohne runde Klammern (dbDelta-Regel, s. InterfaceCatalogSchema; Befund alpha.16).
echo "-- dbDelta-Kompatibilität --\n";
$iterator_schema = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/src', FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator_schema as $file ) {
	if ( 'php' !== $file->getExtension() ) {
		continue;
	}
	$content = (string) file_get_contents( (string) $file );
	if ( ! preg_match_all( "/COMMENT='([^']*)'/", $content, $m ) ) {
		continue;
	}
	$has_paren = false;
	foreach ( $m[1] as $comment ) {
		if ( str_contains( $comment, '(' ) || str_contains( $comment, ')' ) ) {
			$has_paren = true;
		}
	}
	liw_assert( 'Tabellen-COMMENT ohne Klammern: ' . str_replace( $root . '/', '', (string) $file ), ! $has_paren, $checks, $failures );
}

// 2c. Bauplan LP-01…LP-14 (Content\SectionBlueprint) – reine Datenklasse, ohne WP ladbar.
echo "-- Landingpage-Bauplan (Pflichtenheft §8) --\n";
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', $root . '/' ); }
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); } }
require_once $root . '/src/Content/SectionBlueprint.php';
$blueprint = \Liebherr\InterfaceWorld\Content\SectionBlueprint::all();
$codes     = array_keys( $blueprint );
liw_assert( 'Bauplan hat genau 14 Abschnitte', 14 === count( $codes ), $checks, $failures );
liw_assert( 'Codes lückenlos LP-01…LP-14 in Reihenfolge', $codes === array_map( static fn( int $i ): string => sprintf( 'LP-%02d', $i ), range( 1, 14 ) ), $checks, $failures );
liw_assert( 'Jeder Abschnitt hat Titel und Redaktionsvorgabe', [] === array_filter( $blueprint, static fn( array $s ): bool => '' === trim( $s['title'] ) || '' === trim( $s['brief'] ) ), $checks, $failures );
$known_shortcodes = [ 'liw_graphic', 'liw_world_connections_map', 'liw_onboarding_form', 'liw_contact_form' ];
$embeds_ok = true;
foreach ( $blueprint as $s ) {
	if ( isset( $s['embed'] ) && ! preg_match( '/^\[(' . implode( '|', $known_shortcodes ) . ')\b/', $s['embed'] ) ) {
		$embeds_ok = false;
	}
}
liw_assert( 'Eingebettete Bausteine verweisen nur auf existierende Shortcodes', $embeds_ok, $checks, $failures );
// 2d. Jede [liw_graphic]-Einbettung im Bauplan zeigt auf eine Whitelist-Grafik, deren SVG-Datei existiert und XML-wohlgeformt ist.
require_once $root . '/src/Frontend/SectionGraphicView.php';
$graphics_ok = true;
foreach ( \Liebherr\InterfaceWorld\Frontend\SectionGraphicView::GRAPHICS as $g_name => $g_file ) {
	$g_path = $root . '/assets/img/' . $g_file;
	$g_xml  = is_readable( $g_path ) ? @simplexml_load_file( $g_path ) : false;
	if ( false === $g_xml || ! str_contains( (string) $g_xml['class'], 'liw-graphic--' . $g_name ) ) {
		$graphics_ok = false;
	}
}
liw_assert( 'Alle Whitelist-Grafiken vorhanden, wohlgeformt, mit passender Klasse (' . count( \Liebherr\InterfaceWorld\Frontend\SectionGraphicView::GRAPHICS ) . ')', $graphics_ok, $checks, $failures );
$embed_names_ok = true;
foreach ( $blueprint as $s ) {
	if ( isset( $s['embed'] ) && preg_match( '/^\[liw_graphic name="([a-z-]+)"\]$/', $s['embed'], $mm ) && ! isset( \Liebherr\InterfaceWorld\Frontend\SectionGraphicView::GRAPHICS[ $mm[1] ] ) ) {
		$embed_names_ok = false;
	}
}
liw_assert( 'Bauplan-Grafiknamen sind alle in der Whitelist', $embed_names_ok, $checks, $failures );
liw_assert( 'menu_order: LP-01 = 10, LP-14 = 140, unbekannt = 0', 10 === \Liebherr\InterfaceWorld\Content\SectionBlueprint::menu_order_for( 'LP-01' ) && 140 === \Liebherr\InterfaceWorld\Content\SectionBlueprint::menu_order_for( 'LP-14' ) && 0 === \Liebherr\InterfaceWorld\Content\SectionBlueprint::menu_order_for( 'LP-99' ), $checks, $failures );
$lp07 = \Liebherr\InterfaceWorld\Content\SectionBlueprint::draft_content( 'LP-07' );
liw_assert( 'draft_content(LP-07): Absatz + Shortcode-Block, Vorgabe escaped', str_contains( $lp07, '<!-- wp:paragraph -->' ) && str_contains( $lp07, '<!-- wp:shortcode -->[liw_graphic name="data-model"]<!-- /wp:shortcode -->' ) && ! str_contains( $lp07, '<script' ), $checks, $failures );
liw_assert( 'draft_content(unbekannt) = leer', '' === \Liebherr\InterfaceWorld\Content\SectionBlueprint::draft_content( 'LP-99' ), $checks, $failures );

// 2e. Aktive Anker-Hervorhebung der Sprungleiste (alpha.26): Asset registriert, korrekt gebunden.
echo "-- Aktive Anker-Hervorhebung (alpha.26) --\n";
$anchornav_js  = $root . '/assets/js/liebherr-frontend.js';
$anchornav_src = is_readable( $anchornav_js ) ? (string) file_get_contents( $anchornav_js ) : '';
$frontassets   = (string) file_get_contents( $root . '/src/Frontend/FrontendAssets.php' );
$frontend_css  = (string) file_get_contents( $root . '/assets/css/liebherr-frontend.css' );
liw_assert( 'assets/js/liebherr-frontend.js vorhanden', '' !== $anchornav_src, $checks, $failures );
liw_assert( 'FrontendAssets registriert das Skript im Footer mit LIW_VERSION', (bool) preg_match( "/wp_enqueue_script\(\s*self::HANDLE,\s*LIW_URL\s*\.\s*'assets\/js\/liebherr-frontend\.js',\s*\[\],\s*LIW_VERSION,\s*true\s*\)/", $frontassets ), $checks, $failures );
liw_assert( 'Skript ist an .liw-landingpage__nav gebunden', str_contains( $anchornav_src, '.liw-landingpage__nav' ), $checks, $failures );
liw_assert( 'Scrollspy nutzt IntersectionObserver', str_contains( $anchornav_src, 'IntersectionObserver' ), $checks, $failures );
liw_assert( 'aktiver Link erhält aria-current + Klasse is-current', str_contains( $anchornav_src, "'aria-current'" ) && str_contains( $anchornav_src, 'is-current' ), $checks, $failures );
liw_assert( 'kein Inline-Handler/eval im Skript', ! preg_match( '/\beval\s*\(/', $anchornav_src ), $checks, $failures );
liw_assert( 'CSS enthält Aktiv-Zustand .liw-landingpage__nav-link.is-current', str_contains( $frontend_css, '.liw-landingpage__nav-link.is-current' ), $checks, $failures );

// 3. strict_types=1 in jeder src/-Datei (Coding Standard, CLAUDE.md Abschnitt 5).
echo "-- Coding Standard --\n";
$iterator2 = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/src', FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator2 as $file ) {
	if ( 'php' !== $file->getExtension() ) {
		continue;
	}
	$content = (string) file_get_contents( (string) $file );
	liw_assert(
		'declare(strict_types=1): ' . str_replace( $root . '/', '', (string) $file ),
		(bool) preg_match( '/declare\(\s*strict_types\s*=\s*1\s*\)/', $content ),
		$checks,
		$failures
	);
}

echo "\n{$checks} Prüfungen, {$failures} fehlgeschlagen.\n";
exit( $failures > 0 ? 1 : 0 );
