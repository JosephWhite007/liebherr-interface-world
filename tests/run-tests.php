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
if ( ! function_exists( '__' ) ) { function __( $s, $d = '' ) { return $s; } }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', (string) $s ) ); } }
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

// 2f. Brand Tokens (Etappe 1, CI §10–12) – reine Logik ohne WordPress.
echo "-- Brand Tokens (alpha.27) --\n";
require_once $root . '/src/Branding/BrandTokens.php';
$bt_defaults = \Liebherr\InterfaceWorld\Branding\BrandTokens::defaults();
$hex_ok = true;
foreach ( [ 'primary', 'secondary', 'surface', 'text', 'muted', 'border' ] as $k ) {
	if ( ! preg_match( '/^#[0-9a-f]{6}$/i', (string) ( $bt_defaults[ $k ] ?? '' ) ) ) { $hex_ok = false; }
}
liw_assert( 'Fallback-Farben sind gültige #rrggbb', $hex_ok, $checks, $failures );
liw_assert( 'Fallback ist markenneutral (kein Liebherr-Gelb #ffd000)', '#ffd000' !== strtolower( (string) $bt_defaults['primary'] ), $checks, $failures );
$san = \Liebherr\InterfaceWorld\Branding\BrandTokens::sanitize( [ 'primary' => '#ffd000', 'secondary' => 'NICHT-HEX', 'heading_font' => 'LiebherrHead}; body{display:none', 'radius' => '4px', 'content_max' => '9999vw', 'logo_id' => '42' ] );
liw_assert( 'sanitize übernimmt gültiges Hex', '#ffd000' === $san['primary'], $checks, $failures );
liw_assert( 'sanitize verwirft ungültiges Hex (Fallback)', $san['secondary'] === $bt_defaults['secondary'], $checks, $failures );
liw_assert( 'sanitize entfernt CSS-Injektion aus Schrift', ! str_contains( (string) $san['heading_font'], '}' ) && ! str_contains( (string) $san['heading_font'], '{' ) && ! str_contains( (string) $san['heading_font'], ';' ), $checks, $failures );
liw_assert( 'sanitize akzeptiert Radius px, verwirft ungültige Breite', '4px' === $san['radius'] && $san['content_max'] === $bt_defaults['content_max'], $checks, $failures );
liw_assert( 'sanitize logo_id als int', 42 === $san['logo_id'], $checks, $failures );
$css = \Liebherr\InterfaceWorld\Branding\BrandTokens::css_from( $bt_defaults );
liw_assert( 'css_from liefert :root mit --brand-primary und --content-max', str_starts_with( $css, ':root{' ) && str_contains( $css, '--brand-primary:' ) && str_contains( $css, '--content-max:' ), $checks, $failures );
$css_evil = \Liebherr\InterfaceWorld\Branding\BrandTokens::css_from( [ 'primary' => '#000000}html{display:none' ] );
liw_assert( 'css_from kann nicht aus :root ausbrechen', 1 === substr_count( $css_evil, '{' ) && 1 === substr_count( $css_evil, '}' ), $checks, $failures );

// 2g. Header-/Navigations-Einstellungen (Etappe 2, §7) – reine Ziel-Normalisierung ohne WordPress.
echo "-- Header Settings (alpha.28) --\n";
require_once $root . '/src/Settings/HeaderSettings.php';
$nt = [ 'Liebherr\\InterfaceWorld\\Settings\\HeaderSettings', 'normalize_target' ];
liw_assert( 'normalize_target: In-Page-Anker bleibt', '#lp-04' === $nt( '#lp-04' ), $checks, $failures );
liw_assert( 'normalize_target: Anker säubert Sonderzeichen', '#lp04' === $nt( '#lp 04!' ), $checks, $failures );
liw_assert( 'normalize_target: relativer Pfad bleibt', '/interface-world/magic-cube' === $nt( '/interface-world/magic-cube' ), $checks, $failures );
liw_assert( 'normalize_target: http(s)-URL bleibt', 'https://example.com/x' === $nt( 'https://example.com/x' ), $checks, $failures );
liw_assert( 'normalize_target: javascript: verworfen', '' === $nt( 'javascript:alert(1)' ), $checks, $failures );
liw_assert( 'normalize_target: protokollrelativ verworfen', '' === $nt( '//evil.example' ), $checks, $failures );
liw_assert( 'normalize_target: leer bleibt leer', '' === $nt( '   ' ), $checks, $failures );

// 2h. Kern-Komponenten-Inhalt (Etappe 3, §8) – Struktur der Standardlisten.
echo "-- Component Content (alpha.29) --\n";
require_once $root . '/src/Settings/ComponentContent.php';
$cc = \Liebherr\InterfaceWorld\Settings\ComponentContent::defaults();
liw_assert( 'Process: 7 Karten (Sales…Warranty)', isset( $cc['process'] ) && count( $cc['process'] ) === 7, $checks, $failures );
liw_assert( 'Roadmap: 6 Phasen (Contract…Global Rollout)', isset( $cc['roadmap'] ) && count( $cc['roadmap'] ) === 6, $checks, $failures );
liw_assert( 'Onboarding: 9 Schritte (§8/§11 neunstufig)', isset( $cc['onboarding'] ) && count( $cc['onboarding'] ) === 9, $checks, $failures );
$cc_all_titled = true;
foreach ( [ 'process', 'roadmap', 'onboarding' ] as $lk ) {
	foreach ( $cc[ $lk ] as $it ) { if ( '' === trim( (string) ( $it['title'] ?? '' ) ) ) { $cc_all_titled = false; } }
}
liw_assert( 'jeder Listeneintrag hat einen Titel', $cc_all_titled, $checks, $failures );
$cc_first = $cc['process'][0]['title'];
liw_assert( 'Process erste Karte = Sales', 'Sales' === $cc_first, $checks, $failures );

// 2i. Redaktions-Prüfung: Alt-Text-Zählung (Etappe 4, §19/§26) – reine Logik.
echo "-- Redaktions-Prüfung (alpha.30) --\n";
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); } }
require_once $root . '/src/CPT/LiwSectionCpt.php';
require_once $root . '/src/Admin/Pages/ContentBoardPage.php';
$cia = [ 'Liebherr\\InterfaceWorld\\Admin\\Pages\\ContentBoardPage', 'count_images_without_alt' ];
liw_assert( 'Bild ohne alt zählt', 1 === $cia( '<p>x</p><img src="a.jpg">' ), $checks, $failures );
liw_assert( 'Bild mit alt zählt nicht', 0 === $cia( '<img src="a.jpg" alt="Beschreibung">' ), $checks, $failures );
liw_assert( 'leeres alt="" zählt als fehlend', 1 === $cia( '<img src="a.jpg" alt="">' ), $checks, $failures );
liw_assert( 'gemischt: 2 ohne, 1 mit → 2', 2 === $cia( '<img src="1.jpg"><img alt="ok" src="2.jpg"><img src="3.jpg" alt="">' ), $checks, $failures );
liw_assert( 'kein Bild → 0', 0 === $cia( '<p>nur Text</p>' ), $checks, $failures );

// 2j. Rate-Limit-Bridge (Etappe 7, SEC-004) – ohne Core Graceful Degradation (nicht blockieren).
echo "-- Rate-Limit-Bridge (alpha.33) --\n";
require_once $root . '/src/CoreBridge/RateLimitBridge.php';
liw_assert( 'allow() ohne Core → true (Graceful Degradation)', true === \Liebherr\InterfaceWorld\CoreBridge\RateLimitBridge::allow( 'contact' ), $checks, $failures );

// 2k. Sichtbarkeits-Zeitfenster (Etappe 9, §19) – reine Fensterlogik.
echo "-- Sichtbarkeits-Zeitfenster (alpha.36) --\n";
require_once $root . '/src/Content/SectionSchedule.php';
$sw = [ 'Liebherr\\InterfaceWorld\\Content\\SectionSchedule', 'is_within_window' ];
$now = 1_000_000_000; // fixer UTC-Referenzpunkt
liw_assert( 'ohne Grenzen → sichtbar', true === $sw( '', '', $now ), $checks, $failures );
liw_assert( 'from in der Zukunft → unsichtbar', false === $sw( gmdate( 'Y-m-d H:i:s', $now + 3600 ), '', $now ), $checks, $failures );
liw_assert( 'from in der Vergangenheit → sichtbar', true === $sw( gmdate( 'Y-m-d H:i:s', $now - 3600 ), '', $now ), $checks, $failures );
liw_assert( 'until in der Vergangenheit → unsichtbar', false === $sw( '', gmdate( 'Y-m-d H:i:s', $now - 3600 ), $now ), $checks, $failures );
liw_assert( 'until in der Zukunft → sichtbar', true === $sw( '', gmdate( 'Y-m-d H:i:s', $now + 3600 ), $now ), $checks, $failures );
liw_assert( 'im Fenster (from<now<until) → sichtbar', true === $sw( gmdate( 'Y-m-d H:i:s', $now - 60 ), gmdate( 'Y-m-d H:i:s', $now + 60 ), $now ), $checks, $failures );
liw_assert( 'ungültiger Wert wird als offen behandelt', true === $sw( 'kein-datum', '', $now ), $checks, $failures );

// 2l. Optik/CI (Etappe „Optik", §8/§11) – reine Logik.
echo "-- Optik/CI (alpha.37) --\n";
require_once $root . '/src/Frontend/WorldMapView.php';
$cr = [ 'Liebherr\\InterfaceWorld\\Frontend\\WorldMapView', 'canonical_region' ];
liw_assert( 'canonical_region: DEMO Europe → europe', 'europe' === $cr( 'DEMO Europe' ), $checks, $failures );
liw_assert( 'canonical_region: North America → north_america', 'north_america' === $cr( 'DEMO North America' ), $checks, $failures );
liw_assert( 'canonical_region: Südamerika → south_america', 'south_america' === $cr( 'Südamerika' ), $checks, $failures );
liw_assert( 'canonical_region: Asien-Pazifik → asia_pacific', 'asia_pacific' === $cr( 'DEMO Asia-Pacific' ), $checks, $failures );
liw_assert( 'canonical_region: Unbekannt → other', 'other' === $cr( 'Irgendwo' ), $checks, $failures );
$bt2 = \Liebherr\InterfaceWorld\Branding\BrandTokens::css_from( \Liebherr\InterfaceWorld\Branding\BrandTokens::defaults() );
liw_assert( 'BrandTokens: --brand-on-primary im CSS', str_contains( $bt2, '--brand-on-primary:' ), $checks, $failures );
$bt3 = \Liebherr\InterfaceWorld\Branding\BrandTokens::sanitize( [ 'on_primary' => '#202326' ] );
liw_assert( 'BrandTokens: on_primary sanitisiert Hex', '#202326' === $bt3['on_primary'], $checks, $failures );
$bt4 = \Liebherr\InterfaceWorld\Branding\BrandTokens::sanitize( [ 'brand_text' => '  Liebherr Interface Solutions  ' ] );
liw_assert( 'BrandTokens: brand_text sanitisiert', 'Liebherr Interface Solutions' === $bt4['brand_text'], $checks, $failures );

// 2m. Local Intelligence – Content-Modell (LI-Pflichtenheft §8/§12.3, alpha.41) – reine Logik.
echo "-- Local Intelligence Content (alpha.41) --\n";
if ( ! function_exists( 'sanitize_textarea_field' ) ) { function sanitize_textarea_field( $s ) { return trim( (string) $s ); } }
require_once $root . '/src/Settings/LocalIntelligenceContent.php';
$li = \Liebherr\InterfaceWorld\Settings\LocalIntelligenceContent::defaults();
$li_modules = [ 'hero', 'vision', 'flow', 'simulation', 'knowledge', 'trust', 'global', 'usecases', 'bridge', 'rollout', 'contact' ];
liw_assert( 'defaults() hat alle elf Modul-Zweige', 11 === count( array_intersect( array_keys( $li ), $li_modules ) ), $checks, $failures );
liw_assert( 'Vision: 3 Nutzenfelder (Simulieren/Verstehen/Entscheiden)', 3 === count( $li['vision']['fields'] ) && 'Simulieren' === $li['vision']['fields'][0]['title'], $checks, $failures );
liw_assert( 'Flow: 5 Schritte (globales Wissen → lokale Entscheidung)', 5 === count( $li['flow']['steps'] ), $checks, $failures );
liw_assert( 'Simulation: 3 Szenarien A/B/C', 3 === count( $li['simulation']['scenarios'] ) && 'A' === $li['simulation']['scenarios'][0]['key'], $checks, $failures );
liw_assert( 'Datenqualität: 4 Themenbereiche', 4 === count( $li['trust']['areas'] ), $checks, $failures );
liw_assert( 'Einsatzfelder: 6 Bereiche', 6 === count( $li['usecases']['items'] ), $checks, $failures );
liw_assert( 'Rollout: 6 Schritte', 6 === count( $li['rollout']['steps'] ), $checks, $failures );
liw_assert( 'sanitize([]) == defaults() (leere Eingabe → Standard)', \Liebherr\InterfaceWorld\Settings\LocalIntelligenceContent::sanitize( [] ) === $li, $checks, $failures );
$li_over = \Liebherr\InterfaceWorld\Settings\LocalIntelligenceContent::sanitize( [ 'hero' => [ 'headline' => 'Neue Headline' ], 'vision' => [ 'fields' => [] ] ] );
liw_assert( 'sanitize übernimmt Hero-Override', 'Neue Headline' === $li_over['hero']['headline'], $checks, $failures );
liw_assert( 'sanitize: leere Liste fällt auf Standard zurück', $li_over['vision']['fields'] === $li['vision']['fields'], $checks, $failures );
liw_assert( 'Hero enthält Dreiklang-Kurzzeile', 'Simulieren. Verstehen. Entscheiden.' === $li['hero']['tagline'], $checks, $failures );
liw_assert( 'Intro-Zweig vorhanden (Titel + Nutzungsbedingungen + Rechen-Labels)', isset( $li['intro'] ) && '' !== trim( (string) $li['intro']['title'] ) && '' !== trim( (string) $li['intro']['terms_body'] ) && '' !== trim( (string) $li['intro']['math_label'] ), $checks, $failures );
liw_assert( 'Nutzungsbedingungen enthalten Abschnitte 5.1–5.8', str_contains( $li['intro']['terms_body'], '## 5.1 ' ) && str_contains( $li['intro']['terms_body'], '## 5.8 ' ) && str_contains( $li['intro']['terms_body'], 'Heartbeat' ), $checks, $failures );
// SEO-Prototyp-Konformität (LI §9.2/§12.7): SeoBridge kennt den Composite-Shortcode + noindex-Logik.
$seo_src = (string) file_get_contents( $root . '/src/CoreBridge/SeoBridge.php' );
liw_assert( 'SeoBridge: LI-Composite als Träger-Shortcode erfasst', str_contains( $seo_src, "'liw_landingpage', 'liw_local_intelligence'" ), $checks, $failures );
liw_assert( 'SeoBridge: noindex-Guard (render_robots + indexing_allowed)', str_contains( $seo_src, 'render_robots' ) && str_contains( $seo_src, 'noindex,follow' ) && str_contains( $seo_src, 'liw_public_release' ), $checks, $failures );

// 2n. Intelligence World – Fundament (Pflichtenheft-2 §11/§12/§13, alpha.47) – reine Logik ohne WP.
echo "-- Intelligence World Fundament (alpha.47) --\n";
if ( ! function_exists( 'wp_json_encode' ) ) { function wp_json_encode( $data, $options = 0, $depth = 512 ) { return json_encode( $data, $options, $depth ); } }
require_once $root . '/src/IntelligenceWorld/EventTypes.php';
require_once $root . '/src/IntelligenceWorld/Money.php';
require_once $root . '/src/IntelligenceWorld/PriceRule.php';
require_once $root . '/src/IntelligenceWorld/SessionMeter.php';
require_once $root . '/src/IntelligenceWorld/EventLog.php';

use Liebherr\InterfaceWorld\IntelligenceWorld\EventLog;
use Liebherr\InterfaceWorld\IntelligenceWorld\EventTypes;
use Liebherr\InterfaceWorld\IntelligenceWorld\Money;
use Liebherr\InterfaceWorld\IntelligenceWorld\PriceRule;
use Liebherr\InterfaceWorld\IntelligenceWorld\SessionMeter;

liw_assert( 'EventTypes: 23 Typen aus §11, is_valid greift', 23 === count( EventTypes::all() ) && EventTypes::is_valid( 'session_started' ) && ! EventTypes::is_valid( 'bogus' ), $checks, $failures );

liw_assert( 'Money: multiply/sum Integer-genau', 4500 === Money::multiply( 150, 30 ) && 175 === Money::sum( [ 100, 50, 25 ] ) && 0 === Money::multiply( 150, -5 ), $checks, $failures );
liw_assert( 'Money: Format de/en aus Minor-Units', '1.234,56 EUR' === Money::format( 123456, 'EUR', 'de' ) && 'EUR 1,234.56' === Money::format( 123456, 'EUR', 'en' ) && '-0,05 EUR' === Money::format( -5, 'EUR', 'de' ), $checks, $failures );

$pr_min  = new PriceRule( PriceRule::UNIT_MINUTE, 20, 'EUR', 'v1', 1000, null );
$pr_flat = new PriceRule( PriceRule::UNIT_FLAT_PROJECT, 5000, 'EUR', 'v1', 1000, null );
$pr_free = new PriceRule( PriceRule::UNIT_FREE, 999, 'EUR', 'v1', 1000, null );
liw_assert( 'PriceRule: per_minute = Preis×Menge', 200 === $pr_min->cost_minor( 10 ), $checks, $failures );
liw_assert( 'PriceRule: flat_project mengenunabhängig', 5000 === $pr_flat->cost_minor( 99 ), $checks, $failures );
liw_assert( 'PriceRule: free = 0', 0 === $pr_free->cost_minor( 100 ), $checks, $failures );
liw_assert( 'PriceRule: 10 Tarifarten (§13.2)', 10 === count( PriceRule::units() ), $checks, $failures );
$pr_old = new PriceRule( PriceRule::UNIT_CALL, 100, 'EUR', 'v1', 1000, 1999 );
$pr_new = new PriceRule( PriceRule::UNIT_CALL, 120, 'EUR', 'v2', 2000, null );
liw_assert( 'PriceRule: select_active nicht rückwirkend (§13.3)', 'v1' === PriceRule::select_active( [ $pr_old, $pr_new ], 1500 )->version && 'v2' === PriceRule::select_active( [ $pr_old, $pr_new ], 2500 )->version, $checks, $failures );

// SessionMeter (§13.1): aktive Sekunden aus Heartbeats + Timeout.
$t = 120;
liw_assert( 'SessionMeter: leere Liste = 0', 0 === SessionMeter::active_seconds( [], $t ), $checks, $failures );
liw_assert( 'SessionMeter: regelmäßige Heartbeats summieren Lücken', 90 === SessionMeter::active_seconds( [ 1000, 1030, 1060, 1090 ], $t ), $checks, $failures );
liw_assert( 'SessionMeter: Lücke > Timeout zählt nicht (nicht nutzbare Zeit)', 60 === SessionMeter::active_seconds( [ 1000, 1030, 1600, 1630 ], $t ), $checks, $failures );
liw_assert( 'SessionMeter: Abschluss zeitnah wird angerechnet', 40 === SessionMeter::active_seconds( [ 1000, 1030 ], $t, 1040 ), $checks, $failures );
liw_assert( 'SessionMeter: Abschluss nach Timeout nicht angerechnet', 30 === SessionMeter::active_seconds( [ 1000, 1030 ], $t, 5000 ), $checks, $failures );
liw_assert( 'SessionMeter: is_timed_out', SessionMeter::is_timed_out( 1000, $t, 1200 ) && ! SessionMeter::is_timed_out( 1000, $t, 1100 ), $checks, $failures );

// EventLog Hash-Kette (reine Funktionen).
$core1 = [ 'event_uid' => 'e1', 'session_code' => 'S', 'seq' => 1, 'type' => 'session_started', 'module' => '', 'occurred_at' => '2026-09-19 10:00:00', 'price_rule_version' => 'v1', 'metadata' => [ 'b' => 2, 'a' => 1 ] ];
$h1a = EventLog::hash( '', $core1 );
$core1b = $core1; $core1b['metadata'] = [ 'a' => 1, 'b' => 2 ]; // andere Schlüsselreihenfolge
liw_assert( 'EventLog: canonical stabil (Schlüsselreihenfolge egal)', EventLog::hash( '', $core1 ) === EventLog::hash( '', $core1b ), $checks, $failures );
$core2 = [ 'event_uid' => 'e2', 'session_code' => 'S', 'seq' => 2, 'type' => 'session_heartbeat', 'module' => '', 'occurred_at' => '2026-09-19 10:00:30', 'price_rule_version' => '', 'metadata' => [] ];
$h2 = EventLog::hash( $h1a, $core2 );
liw_assert( 'EventLog: Kette verknüpft (prev_hash geht ein)', $h2 !== EventLog::hash( 'anders', $core2 ) && 64 === strlen( $h1a ), $checks, $failures );
$core1_tampered = $core1; $core1_tampered['occurred_at'] = '2026-09-19 09:00:00';
liw_assert( 'EventLog: Manipulation ändert Hash', EventLog::hash( '', $core1 ) !== EventLog::hash( '', $core1_tampered ), $checks, $failures );

// 2o. Intelligence World – Phase 2 (Eintritt/Billing-Anzeige, alpha.48) – reine Logik ohne WP.
echo "-- Intelligence World Phase 2 (alpha.48) --\n";
require_once $root . '/src/IntelligenceWorld/WorldContent.php';
require_once $root . '/src/IntelligenceWorld/Rest.php';
$iw_def = \Liebherr\InterfaceWorld\IntelligenceWorld\WorldContent::defaults();
liw_assert( 'WorldContent: landing/gate/pricing + Demo-Code', isset( $iw_def['landing'], $iw_def['gate'], $iw_def['pricing'] ) && 'LIEBHERR-DEMO' === $iw_def['pricing']['access_code'], $checks, $failures );
liw_assert( 'WorldContent: sanitize([]) == defaults()', \Liebherr\InterfaceWorld\IntelligenceWorld\WorldContent::sanitize( [] ) === $iw_def, $checks, $failures );
// Preismodell (alpha.55): Sekundentakt 0,09 EUR/Sek., Monatsbudget 5.000,00 EUR, 1 TB (min.).
$IWWC = '\Liebherr\InterfaceWorld\IntelligenceWorld\WorldContent';
liw_assert( 'WorldContent: Preis 0,09/Sek. (9 Minor, unit=second)', 9 === $iw_def['pricing']['base_price_second_minor'] && 'second' === $iw_def['pricing']['price_unit'], $checks, $failures );
liw_assert( 'WorldContent: Budget 5.000,00/Monat (500000 Minor, period=month)', 500000 === $iw_def['pricing']['session_budget_minor'] && 'month' === $iw_def['pricing']['budget_period'], $checks, $failures );
liw_assert( 'WorldContent: Speicher 1 TB Mindestwert (1048576 MB, is_minimum)', 1048576 === $iw_def['pricing']['storage_budget_mb'] && true === $iw_def['pricing']['storage_is_minimum'], $checks, $failures );
liw_assert( 'WorldContent: storage_label(1048576, true) == „1 TB (min.)"', '1 TB (min.)' === $IWWC::storage_label( 1048576, true ) && '1 TB' === $IWWC::storage_label( 1048576, false ), $checks, $failures );
liw_assert( 'WorldContent: storage_label 2048 MB == 2 GB, 512 MB == 512 MB', '2 GB' === $IWWC::storage_label( 2048, false ) && '512 MB' === $IWWC::storage_label( 512, false ), $checks, $failures );
liw_assert( 'WorldContent: unit_label(second)=Sek., period_label(month)=Monat', 'Sek.' === $IWWC::unit_label( 'second' ) && 'Monat' === $IWWC::period_label( 'month' ), $checks, $failures );
$bs = \Liebherr\InterfaceWorld\IntelligenceWorld\Rest::billing_status( 100, 9, 500000 );
liw_assert( 'Rest::billing_status: 100s×0,09/Sek. = 9,00 (900 Cent), ok', 900 === $bs['base_cost_minor'] && 0 === $bs['budget_pct'] && 'ok' === $bs['level'], $checks, $failures );
$bs2 = \Liebherr\InterfaceWorld\IntelligenceWorld\Rest::billing_status( 100, 9, 1000 );
liw_assert( 'Rest::billing_status: Warnstufe high bei 90 %', 900 === $bs2['base_cost_minor'] && 90 === $bs2['budget_pct'] && 'high' === $bs2['level'], $checks, $failures );
$bs3 = \Liebherr\InterfaceWorld\IntelligenceWorld\Rest::billing_status( 200, 9, 1000 );
liw_assert( 'Rest::billing_status: Budget überschritten = limit', 'limit' === $bs3['level'] && $bs3['budget_pct'] >= 100, $checks, $failures );
liw_assert( 'Rest::format_duration: §8-Beispiel 02:14:38', '02:14:38' === \Liebherr\InterfaceWorld\IntelligenceWorld\Rest::format_duration( 8078 ) && '00:00:00' === \Liebherr\InterfaceWorld\IntelligenceWorld\Rest::format_duration( 0 ), $checks, $failures );
liw_assert( 'Favicon: goldenes Planet-SVG vorhanden + XML-wohlgeformt', is_readable( $root . '/assets/img/liw-planet-icon.svg' ) && false !== @simplexml_load_file( $root . '/assets/img/liw-planet-icon.svg' ), $checks, $failures );

// 2n. Intelligence World – Navigation & Hotels (13 Segmente + Lösungswelt + 6 Hotels, §3/§19, alpha.54).
echo "-- Intelligence World – Navigation & Hotels (alpha.54) --\n";
require_once $root . '/src/IntelligenceWorld/CatalogContent.php';
$cat_def = \Liebherr\InterfaceWorld\IntelligenceWorld\CatalogContent::defaults();
liw_assert( 'CatalogContent: genau 13 Produktsegmente', is_array( $cat_def['segments'] ) && 13 === count( $cat_def['segments'] ), $checks, $failures );
liw_assert( 'CatalogContent: genau 6 Hotels', is_array( $cat_def['hotels'] ) && 6 === count( $cat_def['hotels'] ), $checks, $failures );
liw_assert( 'CatalogContent: Lösungswelt vorhanden (segmentübergreifend)', isset( $cat_def['solution']['label'] ) && '' !== $cat_def['solution']['label'], $checks, $failures );
liw_assert( 'CatalogContent: alle Segmente tragen einen Drei-Wörter-Ort (word.word.word)', ( static function ( array $segs ): bool { foreach ( $segs as $s ) { if ( 3 !== count( explode( '.', (string) ( $s['three_words'] ?? '' ) ) ) ) { return false; } } return true; } )( $cat_def['segments'] ), $checks, $failures );
liw_assert( 'CatalogContent: sanitize([]) == defaults()', \Liebherr\InterfaceWorld\IntelligenceWorld\CatalogContent::sanitize( [] ) === $cat_def, $checks, $failures );
liw_assert( 'CatalogContent: sanitize_three_words normalisiert „Filled Count Soap" → filled.count.soap', 'filled.count.soap' === \Liebherr\InterfaceWorld\IntelligenceWorld\CatalogContent::sanitize_three_words( 'Filled Count Soap' ), $checks, $failures );
liw_assert( 'CatalogContent: sanitize_three_words lehnt Zwei-Wort-Eingabe ab (leer)', '' === \Liebherr\InterfaceWorld\IntelligenceWorld\CatalogContent::sanitize_three_words( 'only.two' ), $checks, $failures );
$cat_custom = \Liebherr\InterfaceWorld\IntelligenceWorld\CatalogContent::sanitize( [ 'segments' => [ [ 'label' => 'Nur Eins', 'three_words' => 'a.b.c' ], [ 'nokey' => 'x' ] ] ] );
liw_assert( 'CatalogContent: sanitize verwirft Knoten ohne Anzeigenamen', 1 === count( $cat_custom['segments'] ) && 'Nur Eins' === $cat_custom['segments'][0]['label'], $checks, $failures );
require_once $root . '/src/IntelligenceWorld/NavigationView.php';
liw_assert( 'NavigationView: Shortcode-Konstante + render_sections vorhanden', 'liw_iw_navigation' === \Liebherr\InterfaceWorld\IntelligenceWorld\NavigationView::SHORTCODE && method_exists( \Liebherr\InterfaceWorld\IntelligenceWorld\NavigationView::class, 'render_sections' ), $checks, $failures );

// 2s. Intelligence World – Simulation Builder (geführte Szenarien/Forecasts, §6.4, alpha.56).
echo "-- Intelligence World – Simulation Builder (alpha.56) --\n";
require_once $root . '/src/IntelligenceWorld/SimulationModel.php';
require_once $root . '/src/IntelligenceWorld/SimulationView.php';
$SM = '\Liebherr\InterfaceWorld\IntelligenceWorld\SimulationModel';
$fc = $SM::forecast( 1000, 100, 12, 'base' );
liw_assert( 'SimulationModel: Basis 1000 @10%/12P → 12 Werte, Start 1100, Ende 3138', 12 === count( $fc['values'] ) && 1100 === $fc['values'][0] && 3138 === $fc['end'], $checks, $failures );
liw_assert( 'SimulationModel: delta_permille = 2138 (Ende vs. Basis)', 2138 === $fc['delta_permille'], $checks, $failures );
liw_assert( 'SimulationModel: Szenario skaliert Wachstum (A=70‰, B=100‰, C=130‰)', 70 === $SM::forecast( 1000, 100, 6, 'conservative' )['effective_permille'] && 100 === $SM::forecast( 1000, 100, 6, 'base' )['effective_permille'] && 130 === $SM::forecast( 1000, 100, 6, 'ambitious' )['effective_permille'], $checks, $failures );
liw_assert( 'SimulationModel: Perioden auf 60 geklemmt, ungültiges Szenario → base', 60 === $SM::forecast( 1000, 100, 999, 'quatsch' )['periods'] && 'base' === $SM::forecast( 1000, 100, 999, 'quatsch' )['scenario'], $checks, $failures );
liw_assert( 'SimulationModel: Basis < 0 geklemmt (alle Werte 0, delta 0)', 0 === $SM::forecast( -5, 100, 6, 'base' )['end'] && 0 === $SM::forecast( -5, 100, 6, 'base' )['delta_permille'], $checks, $failures );
$bl1 = $SM::sample_baseline( 'earthmoving' );
$bl2 = $SM::sample_baseline( 'earthmoving' );
liw_assert( 'SimulationModel: sample_baseline deterministisch + in Grenzen (base 1000..9999, growth 10..99)', $bl1 === $bl2 && $bl1['base'] >= 1000 && $bl1['base'] <= 9999 && $bl1['growth_permille'] >= 10 && $bl1['growth_permille'] <= 99, $checks, $failures );
liw_assert( 'SimulationView: Shortcode-Konstante + render_section/render_forecast vorhanden', 'liw_iw_simulation' === \Liebherr\InterfaceWorld\IntelligenceWorld\SimulationView::SHORTCODE && method_exists( \Liebherr\InterfaceWorld\IntelligenceWorld\SimulationView::class, 'render_section' ) && method_exists( \Liebherr\InterfaceWorld\IntelligenceWorld\SimulationView::class, 'render_forecast' ), $checks, $failures );

// 2t. Intelligence World – Nutzungs-/Kostenprotokoll (§5.5/§8, alpha.58).
echo "-- Intelligence World – Nutzungs-/Kostenprotokoll (alpha.58) --\n";
require_once $root . '/src/IntelligenceWorld/ProtocolBuilder.php';
$ET = '\Liebherr\InterfaceWorld\IntelligenceWorld\EventTypes';
liw_assert( 'EventTypes::label: bekannter Typ übersetzt, unbekannter unverändert', 'Sitzung gestartet' === $ET::label( 'session_started' ) && 'gibt_es_nicht' === $ET::label( 'gibt_es_nicht' ), $checks, $failures );
$pb_session = [ 'session_code' => 'S-TEST', 'status' => 'ended', 'started_at' => '2026-01-01T00:00:00Z', 'ended_at' => '2026-01-01T00:01:40Z', 'active_seconds' => 100 ];
$pb_events  = [ [ 'seq' => 1, 'type' => 'session_started', 'occurred_at' => '2026-01-01T00:00:00Z', 'module' => '' ], [ 'seq' => 2, 'type' => 'session_ended', 'occurred_at' => '2026-01-01T00:01:40Z' ] ];
$pb_billing = \Liebherr\InterfaceWorld\IntelligenceWorld\Rest::billing_status( 100, 9, 500000 );
$pb = \Liebherr\InterfaceWorld\IntelligenceWorld\ProtocolBuilder::build( $pb_session, $pb_events, $pb_billing, 'EUR', true, '2026-01-01T00:02:00Z' );
liw_assert( 'ProtocolBuilder: Kopf + aktive Zeit 00:01:40', 'S-TEST' === $pb['session_code'] && 'ended' === $pb['status'] && '00:01:40' === $pb['active_display'], $checks, $failures );
liw_assert( 'ProtocolBuilder: Basiskosten 100s×0,09 = 9,00 EUR', 900 === $pb['billing']['base_cost_minor'] && '9,00 EUR' === $pb['billing']['base_cost_display'], $checks, $failures );
liw_assert( 'ProtocolBuilder: 2 Ereignisse mit lesbaren Labels + Integritätsflag', 2 === $pb['event_count'] && 'Sitzung gestartet' === $pb['events'][0]['label'] && 'Sitzung beendet' === $pb['events'][1]['label'] && true === $pb['integrity_ok'] && true === $pb['prototype'], $checks, $failures );

// 2p. Liebherr Adventures – Fundament (vierte Insel, §3/§4/§9, alpha.51) – reine Logik ohne WP.
echo "-- Liebherr Adventures (alpha.51) --\n";
require_once $root . '/src/Adventures/Taxonomy.php';
require_once $root . '/src/Adventures/Location/ProviderInterface.php';
require_once $root . '/src/Adventures/Location/MockProvider.php';
require_once $root . '/src/Adventures/Policy.php';
use Liebherr\InterfaceWorld\Adventures\Taxonomy;
use Liebherr\InterfaceWorld\Adventures\Policy;
use Liebherr\InterfaceWorld\Adventures\Location\MockProvider;

liw_assert( 'Adventures: 13 Inhaltstypen + 4 Dringlichkeitsstufen (getrennt, §3)', 13 === count( Taxonomy::content_types() ) && 4 === count( Taxonomy::urgency_levels() ), $checks, $failures );
liw_assert( 'Adventures: is_critical nur bei critical', Taxonomy::is_critical( 'critical' ) && ! Taxonomy::is_critical( 'funny' ) && Taxonomy::is_valid_type( 'work_advice' ) && ! Taxonomy::is_valid_type( 'x' ), $checks, $failures );

$mp = new MockProvider();
$enc = $mp->encode( 48.10, 9.79 );
liw_assert( 'MockProvider: 3 Wörter (word.word.word)', 3 === count( explode( '.', $enc['words'] ) ) && '' !== $enc['words'], $checks, $failures );
$dec = $mp->decode( $enc['words'] );
liw_assert( 'MockProvider: decode(encode) = Rasterzentrum (round-trip)', null !== $dec && abs( $dec['lat'] - $enc['lat'] ) < 0.0001 && abs( $dec['lng'] - $enc['lng'] ) < 0.0001, $checks, $failures );
liw_assert( 'MockProvider: deterministisch', $mp->encode( 48.10, 9.79 )['words'] === $enc['words'], $checks, $failures );
liw_assert( 'MockProvider: ungültiger Code → null', null === $mp->decode( 'nope.nope.nope' ) && null === $mp->decode( 'apple.anchor' ), $checks, $failures );

liw_assert( 'Policy: 7 Sichtbarkeitsstufen (§9.1)', 7 === count( Policy::visibilities() ), $checks, $failures );
liw_assert( 'Policy: Entwurf bleibt Entwurf, Einreichen → pending (Critical auch)', 'draft' === Policy::effective_status( 'draft', 'funny' ) && 'pending' === Policy::effective_status( 'submit', 'critical' ) && 'pending' === Policy::effective_status( 'submit', 'informative' ), $checks, $failures );
liw_assert( 'Policy: public_approved+publish öffentlich sichtbar', Policy::can_view( 'publish', 'public_approved', false, false, false ), $checks, $failures );
liw_assert( 'Policy: interne Sichtbarkeit nur für Angemeldete', ! Policy::can_view( 'publish', 'organization', false, false, false ) && Policy::can_view( 'publish', 'organization', false, true, false ), $checks, $failures );
liw_assert( 'Policy: nicht-publish (Critical/pending) nicht öffentlich (§22.5)', ! Policy::can_view( 'pending', 'public_approved', false, true, false ) && Policy::can_view( 'pending', 'public_approved', true, false, false ), $checks, $failures );

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
