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
if ( ! function_exists( 'apply_filters' ) ) { function apply_filters( $tag, $value = null ) { return $value; } }
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
liw_assert( 'FrontendAssets registriert das Skript im Footer mit LIW_VERSION', (bool) preg_match( "/wp_enqueue_script\(\s*self::HANDLE,\s*LIW_URL\s*\.\s*'assets\/js\/liebherr-frontend\.js',\s*\[\s*'liw-worldbar-lock'\s*\],\s*LIW_VERSION,\s*true\s*\)/", $frontassets ), $checks, $failures );
liw_assert( 'FrontendAssets laedt den Weltleisten-Sperr-Helfer als Abhaengigkeit', str_contains( $frontassets, "'liw-worldbar-lock'," ) && str_contains( $frontassets, "assets/js/liw-worldbar-lock.js" ), $checks, $failures );
liw_assert( 'Skript ist an .liw-landingpage__nav gebunden', str_contains( $anchornav_src, '.liw-landingpage__nav' ), $checks, $failures );
liw_assert( 'Scrollspy nutzt IntersectionObserver', str_contains( $anchornav_src, 'IntersectionObserver' ), $checks, $failures );
liw_assert( 'aktiver Link erhält aria-current + Klasse is-current', str_contains( $anchornav_src, "'aria-current'" ) && str_contains( $anchornav_src, 'is-current' ), $checks, $failures );
liw_assert( 'kein Inline-Handler/eval im Skript', ! preg_match( '/\beval\s*\(/', $anchornav_src ), $checks, $failures );
liw_assert( 'CSS enthält Aktiv-Zustand .liw-landingpage__nav-link.is-current', str_contains( $frontend_css, '.liw-landingpage__nav-link.is-current' ), $checks, $failures );

// Plattformzeit-Sichtbarkeit (alpha.141): Time-Pille ohne JS sichtbar + Rocket-Kompatibilität.
$clock_src  = (string) file_get_contents( $root . '/src/PlatformTime/ClockWidget.php' );
$rocket_src = (string) file_get_contents( $root . '/src/Frontend/RocketCompat.php' );
liw_assert( 'ClockWidget rendert die Time-Pille ohne hidden (sichtbar auch bei verzoegertem JS)', str_contains( $clock_src, "class=\"liw-ptime is-collapsed\" data-liw-ptime>" ), $checks, $failures );
liw_assert( 'RocketCompat: RUCSS-Safelist enthaelt .liw-ptime', str_contains( $rocket_src, "'.liw-ptime'" ), $checks, $failures );
liw_assert( 'RocketCompat: Delay-JS-Ausschluss fuer liw-ptime-clock.js', str_contains( $rocket_src, 'rocket_delay_js_exclusions' ) && str_contains( $rocket_src, 'assets/js/liw-ptime-clock.js' ), $checks, $failures );

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
// Engine-Naht (alpha.74).
require_once $root . '/src/IntelligenceWorld/SimulationEngineInterface.php';
require_once $root . '/src/IntelligenceWorld/SimulationMockEngine.php';
require_once $root . '/src/IntelligenceWorld/SimulationEngine.php';
$SE = '\Liebherr\InterfaceWorld\IntelligenceWorld\SimulationEngine';
liw_assert( 'SimulationEngine: Standard = Mock, is_mock true, forecast == SimulationModel', $SE::is_mock() && 'mock-1' === $SE::resolve()->id() && $SE::resolve()->forecast( 1000, 100, 12, 'base' ) === $SM::forecast( 1000, 100, 12, 'base' ), $checks, $failures );

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

// 2v. Compute-Metering + kostenpflichtige Module (§6.4/§8, alpha.63).
echo "-- Intelligence World – Compute-Metering / Module (alpha.63) --\n";
require_once $root . '/src/IntelligenceWorld/ModuleCatalog.php';
$MC = '\Liebherr\InterfaceWorld\IntelligenceWorld\ModuleCatalog';
liw_assert( 'ModuleCatalog: 5 Aktionen + is_valid', 5 === count( $MC::actions() ) && $MC::is_valid( 'data_query' ) && ! $MC::is_valid( 'nope' ), $checks, $failures );
liw_assert( 'ModuleCatalog: cost_minor data_query×1=15, compute×3=360 (per unit)', 15 === $MC::cost_minor( 'data_query', 1 ) && 360 === $MC::cost_minor( 'compute', 3 ), $checks, $failures );
liw_assert( 'ModuleCatalog: public_list liefert price_display', ( function () use ( $MC ): bool { $l = $MC::public_list( 'EUR' ); return isset( $l[0]['price_display'], $l[0]['key'] ); } )(), $checks, $failures );
// Protokoll mit Modul-Posten: Basis + Module summieren.
$pb_ev2 = [
	[ 'seq' => 1, 'type' => 'session_started', 'occurred_at' => '2026-01-01T00:00:00Z' ],
	[ 'seq' => 2, 'type' => 'query_executed', 'occurred_at' => '2026-01-01T00:00:10Z', 'metadata' => wp_json_encode( [ 'action' => 'data_query', 'label' => 'Datenabfrage', 'units' => 2, 'cost_minor' => 30 ] ) ],
	[ 'seq' => 3, 'type' => 'compute_job_completed', 'occurred_at' => '2026-01-01T00:00:20Z', 'metadata' => [ 'action' => 'simulation', 'label' => 'Simulation ausführen', 'units' => 1, 'cost_minor' => 500 ] ],
];
$pb2 = \Liebherr\InterfaceWorld\IntelligenceWorld\ProtocolBuilder::build( $pb_session, $pb_ev2, $pb_billing, 'EUR', true, '2026-01-01T00:02:00Z' );
liw_assert( 'ProtocolBuilder: 2 Modul-Posten + Modulsumme 5,30 EUR (530)', 2 === count( $pb2['line_items'] ) && 530 === $pb2['billing']['modules_cost_minor'] && '5,30 EUR' === $pb2['billing']['modules_display'], $checks, $failures );
liw_assert( 'ProtocolBuilder: Gesamt = Basis (900) + Module (530) = 1430 (14,30 EUR)', 1430 === $pb2['billing']['total_cost_minor'] && '14,30 EUR' === $pb2['billing']['total_display'], $checks, $failures );
// PDF-Export (A10, alpha.71).
require_once $root . '/src/IntelligenceWorld/PdfDocument.php';
$pb_lines = \Liebherr\InterfaceWorld\IntelligenceWorld\ProtocolBuilder::to_lines( $pb2 );
liw_assert( 'ProtocolBuilder::to_lines: enthält Sitzung + Gesamtkosten + Ereignisse', in_array( 'Sitzung: S-TEST', $pb_lines, true ) && ( (bool) preg_grep( '/^Gesamtkosten: /', $pb_lines ) ) && ( (bool) preg_grep( '/^Ereignisse \(/', $pb_lines ) ), $checks, $failures );
$pdf = \Liebherr\InterfaceWorld\IntelligenceWorld\PdfDocument::from_lines( 'Protokoll', $pb_lines );
liw_assert( 'PdfDocument: gültiges PDF (%PDF … %%EOF) + transliteriert (EUR statt €)', 0 === strpos( $pdf, '%PDF-1.' ) && false !== strpos( $pdf, '%%EOF' ) && false !== strpos( $pdf, 'EUR' ) && false === strpos( $pdf, '€' ) && strlen( $pdf ) > 400, $checks, $failures );

// Approved-Media-Filter (A11, alpha.72).
require_once $root . '/src/CoreBridge/MediaBridge.php';
require_once $root . '/src/Admin/ApprovedMediaFilter.php';
$AMF = '\Liebherr\InterfaceWorld\Admin\ApprovedMediaFilter';
$amf1 = $AMF::restrict( [] );
liw_assert( 'ApprovedMediaFilter: restrict ergänzt approved-meta_query', isset( $amf1['meta_query'][0]['key'] ) && '_liw_media_approved' === $amf1['meta_query'][0]['key'] && '1' === $amf1['meta_query'][0]['value'], $checks, $failures );
liw_assert( 'ApprovedMediaFilter: ohne Flag unverändert, mit Flag beschränkt', empty( $AMF::maybe_restrict( [] )['meta_query'] ) && ! empty( $AMF::maybe_restrict( [ 'liw_approved_only' => 1 ] )['meta_query'] ) && ! isset( $AMF::maybe_restrict( [ 'liw_approved_only' => 1 ] )['liw_approved_only'] ), $checks, $failures );

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

// 2u. Adventure Area – Basislogik: Tokenwert, Registrierung, Ledger (§1–§9, alpha.59).
echo "-- Adventures Basislogik (Token/Registrierung, alpha.59) --\n";
require_once $root . '/src/Adventures/RegistrationStatus.php';
require_once $root . '/src/Adventures/TokenPolicy.php';
require_once $root . '/src/Adventures/TokenLedger.php';
$RS = '\Liebherr\InterfaceWorld\Adventures\RegistrationStatus';
$TP = '\Liebherr\InterfaceWorld\Adventures\TokenPolicy';
$TL = '\Liebherr\InterfaceWorld\Adventures\TokenLedger';
liw_assert( 'RegistrationStatus: 9 Status + gültige/ungültige Übergänge', 9 === count( $RS::labels() ) && $RS::can_transition( 'draft', 'submitted' ) && $RS::can_transition( 'validated', 'published' ) && ! $RS::can_transition( 'draft', 'published' ) && ! $RS::can_transition( 'registered', 'validated' ), $checks, $failures );
liw_assert( 'RegistrationStatus: nur registriert+ nutzbar im Firmennetz; nur published im World-Netz', $RS::usable_in_company_net( 'registered' ) && $RS::usable_in_company_net( 'validated' ) && ! $RS::usable_in_company_net( 'draft' ) && ! $RS::usable_in_company_net( 'blocked' ) && $RS::published_in_world( 'published' ) && ! $RS::published_in_world( 'validated' ) && $RS::can_request_validation( 'registered' ) && ! $RS::can_request_validation( 'draft' ), $checks, $failures );
liw_assert( 'TokenPolicy: Ersteller frei (0), fremd mit Budget belastet, ohne Budget abgelehnt', 0 === $TP::resolve_access( 25, true, 0 )['charge'] && 'author' === $TP::resolve_access( 25, true, 0 )['reason'] && $TP::resolve_access( 25, false, 100 )['allowed'] && 25 === $TP::resolve_access( 25, false, 100 )['charge'] && ! $TP::resolve_access( 25, false, 5 )['allowed'], $checks, $failures );
liw_assert( 'TokenPolicy: Wert 0 frei, Berechtigung umgeht Budget, sanitize/next_version', 'free' === $TP::resolve_access( 0, false, 0 )['reason'] && $TP::resolve_access( 25, false, 0, true )['allowed'] && 0 === $TP::sanitize_value( -7 ) && 3 === $TP::next_version( 2, true ) && 2 === $TP::next_version( 2, false ), $checks, $failures );
$tl_core = [ 'entry_uid' => 'e1', 'contribution_id' => 7, 'seq' => 1, 'kind' => 'access', 'token_value' => 25, 'metadata' => [ 'b' => 2, 'a' => 1 ] ];
$tl_same = [ 'metadata' => [ 'a' => 1, 'b' => 2 ], 'kind' => 'access', 'seq' => 1, 'contribution_id' => 7, 'token_value' => 25, 'entry_uid' => 'e1' ];
liw_assert( 'TokenLedger: canonical stabil (Schlüsselreihenfolge egal)', $TL::canonical( $tl_core ) === $TL::canonical( $tl_same ), $checks, $failures );
liw_assert( 'TokenLedger: Hash verkettet (prev_hash geht ein) + Manipulation ändert Hash', $TL::hash( 'PREV', $tl_core ) !== $TL::hash( '', $tl_core ) && $TL::hash( '', $tl_core ) !== $TL::hash( '', array_merge( $tl_core, [ 'token_value' => 26 ] ) ), $checks, $failures );
liw_assert( 'TokenLedger: gültige Vorgangsarten (access/registered/…)', $TL::is_valid_kind( 'access' ) && $TL::is_valid_kind( 'registered' ) && ! $TL::is_valid_kind( 'quatsch' ) && 9 === count( $TL::kinds() ), $checks, $failures );
require_once $root . '/src/Adventures/DetailView.php';
liw_assert( 'DetailView: Shortcode-Konstante + QUERY_VAR + render()', 'liw_adventure_detail' === \Liebherr\InterfaceWorld\Adventures\DetailView::SHORTCODE && 'adv' === \Liebherr\InterfaceWorld\Adventures\DetailView::QUERY_VAR && method_exists( \Liebherr\InterfaceWorld\Adventures\DetailView::class, 'render' ), $checks, $failures );
require_once $root . '/src/Adventures/UploadService.php';
$US = '\Liebherr\InterfaceWorld\Adventures\UploadService';
liw_assert( 'UploadService: is_allowed_ext akzeptiert Bilder, lehnt exe/pdf ab', $US::is_allowed_ext( 'foto.JPG' ) && $US::is_allowed_ext( 'bild.webp' ) && ! $US::is_allowed_ext( 'schad.exe' ) && ! $US::is_allowed_ext( 'doku.pdf' ) && ! $US::is_allowed_ext( 'ohneendung' ), $checks, $failures );
require_once $root . '/src/Adventures/GetHelpAssistant.php';
$GH = '\Liebherr\InterfaceWorld\Adventures\GetHelpAssistant';
liw_assert( 'GetHelpAssistant: 5 geführte Schritte + Shortcode + erster Schritt „secure"', 5 === count( $GH::steps() ) && 'liw_adventures_help' === $GH::SHORTCODE && 'secure' === $GH::steps()[0]['key'], $checks, $failures );
require_once $root . '/src/Admin/Pages/LocalIntelligenceBoardPage.php';
$LIB    = '\Liebherr\InterfaceWorld\Admin\Pages\LocalIntelligenceBoardPage';
$li_scn = $LIB::parse_scenarios( "A | Variante A | Basis\n- Absatz | 100\n- Mix | 70/30\nB | Variante B | Wachstum\n- Absatz | 150" );
liw_assert( 'LI-Szenario-Parser: 2 Szenarien, A=2 Zeilen (Wert 100), B summary „Wachstum"', 2 === count( $li_scn ) && 'A' === $li_scn[0]['key'] && 2 === count( $li_scn[0]['rows'] ) && '100' === $li_scn[0]['rows'][0]['value'] && 'B' === $li_scn[1]['key'] && 'Wachstum' === $li_scn[1]['summary'], $checks, $failures );

// CVF – einheitlicher ChallengeService (ADR-LIW-CVF-001 §5, alpha.81).
require_once $root . '/src/Cvf/ChallengeService.php';
$CS = '\Liebherr\InterfaceWorld\Cvf\ChallengeService';
$__cs_now = 2000000;
$__cs_s   = $CS::create( 'cvf-secret', $__cs_now, 'double' );
liw_assert( 'ChallengeService: double → zwei ZWEISTELLIGE Zahlen (10..99) + Stufe double', $__cs_s['a'] >= 10 && $__cs_s['a'] <= 99 && $__cs_s['b'] >= 10 && $__cs_s['b'] <= 99 && 'double' === $__cs_s['difficulty'], $checks, $failures );
liw_assert( 'ChallengeService: single → einstellig; unbekannte Stufe fällt auf single', 'single' === $CS::create( 'x', $__cs_now, 'single' )['difficulty'] && 'single' === $CS::create( 'x', $__cs_now, 'quatsch' )['difficulty'] && $CS::create( 'x', $__cs_now, 'single' )['a'] <= 9, $checks, $failures );
liw_assert( 'ChallengeService: Roundtrip richtig=ok, falsch=wrong, abgelaufen=expired, Fremdsecret=invalid', true === $CS::verify( $__cs_s['token'], $__cs_s['a'] + $__cs_s['b'], 'cvf-secret', $__cs_now )['ok'] && 'wrong' === $CS::verify( $__cs_s['token'], 0, 'cvf-secret', $__cs_now )['reason'] && 'expired' === $CS::verify( $__cs_s['token'], $__cs_s['a'] + $__cs_s['b'], 'cvf-secret', $__cs_s['expires'] + 1 )['reason'] && 'invalid' === $CS::verify( $__cs_s['token'], $__cs_s['a'] + $__cs_s['b'], 'anders', $__cs_now )['reason'], $checks, $failures );

// CVF – Durchstich-Runtime: Enums, versionierte Config, deterministische Zustandsmaschine (alpha.82).
require_once $root . '/src/Cvf/StageType.php';
require_once $root . '/src/Cvf/VisitorState.php';
require_once $root . '/src/Cvf/WorkflowVersion.php';
require_once $root . '/src/Cvf/Runtime.php';
$WV = '\Liebherr\InterfaceWorld\Cvf\WorkflowVersion';
$RT = '\Liebherr\InterfaceWorld\Cvf\Runtime';
$VS = '\Liebherr\InterfaceWorld\Cvf\VisitorState';
$cfg = $WV::default_config();
liw_assert( 'WorkflowVersion: Default-Config gültig (Eingang→Challenge→Modulwahl→First-Entry)', $WV::is_valid( $cfg ) && 4 === count( $WV::stages( $cfg ) ), $checks, $failures );
liw_assert( 'WorkflowVersion: Prüfsumme stabil + reihenfolge-/formatunabhängig kanonisch', $WV::checksum( $cfg ) === $WV::checksum( $WV::default_config() ) && 64 === strlen( $WV::checksum( $cfg ) ), $checks, $failures );
$__wv_dd = $WV::set_challenge_difficulty( $cfg, 'double' );
$__wv_sd = $WV::set_challenge_difficulty( $cfg, 'quatsch' );
liw_assert( 'WorkflowVersion: set_challenge_difficulty setzt double, unbekannt faellt auf single, bleibt gueltig', '\Liebherr\InterfaceWorld\Cvf\Runtime'::challenge_difficulty( $__wv_dd ) === 'double' && '\Liebherr\InterfaceWorld\Cvf\Runtime'::challenge_difficulty( $__wv_sd ) === 'single' && $WV::is_valid( $__wv_dd ), $checks, $failures );
$__v_empty = $WV::validate( [ 'stages' => [] ] );
$__v_nochal = $WV::validate( [ 'stages' => [ [ 'key' => 'e', 'type' => 'entry' ] ] ] );
$__v_chalfirst = $WV::validate( [ 'stages' => [ [ 'key' => 'c', 'type' => 'challenge' ] ] ] );
liw_assert( 'WorkflowVersion: leere Config -> no_stages; nur-Entry -> missing_challenge; Challenge-first -> first_stage_not_entry', in_array( 'no_stages', $__v_empty, true ) && in_array( 'missing_challenge', $__v_nochal, true ) && in_array( 'first_stage_not_entry', $__v_chalfirst, true ), $checks, $failures );
// Happy Path des Durchstichs.
$st = $RT::start();
liw_assert( 'Runtime: Start = new', $VS::NEW === $st, $checks, $failures );
$r1 = $RT::next( $st, $RT::EV_BEGIN, $cfg );
$r2 = $RT::next( $r1['state'], $RT::EV_CODE_OK, $cfg );
$r3 = $RT::next( $r2['state'], $RT::EV_CHALLENGE_OK, $cfg );
$r4 = $RT::next( $r3['state'], $RT::EV_MODULE_SELECTED, $cfg );
$r5 = $RT::next( $r4['state'], $RT::EV_FIRST_ENTRY_DONE, $cfg );
liw_assert( 'Runtime: Happy Path new→…→in_module (Challenge zweistellig aus Config)', 'at_entry' === $r1['state'] && 'at_challenge' === $r2['state'] && 'issue_challenge' === $r2['action'] && 'double' === $r2['params']['difficulty'] && 'at_module_select' === $r3['state'] && 'at_first_entry' === $r4['state'] && $VS::is_admitted( $r5['state'] ), $checks, $failures );
liw_assert( 'Runtime: ungültiger Übergang lässt Zustand unverändert (reason=invalid_transition)', 'new' === $RT::next( $VS::NEW, $RT::EV_CHALLENGE_OK, $cfg )['state'] && 'invalid_transition' === $RT::next( $VS::NEW, $RT::EV_CHALLENGE_OK, $cfg )['reason'], $checks, $failures );
liw_assert( 'Runtime: block aus jedem Zustand → blocked (und bleibt blockiert)', 'blocked' === $RT::next( $VS::AT_CHALLENGE, $RT::EV_BLOCK, $cfg )['state'] && 'is_blocked' === $RT::next( $VS::BLOCKED, $RT::EV_CODE_OK, $cfg )['reason'], $checks, $failures );

// CVF – AccessService: gehaerteter Zugangscode (Hash/Secret), reine Kernlogik (alpha.84).
require_once $root . '/src/Cvf/AccessService.php';
$AS = '\Liebherr\InterfaceWorld\Cvf\AccessService';
liw_assert( 'AccessService: normalize ignoriert Gross-/Kleinschreibung + Randleerzeichen', 'LIEBHERR-DEMO' === $AS::normalize( '  liebherr-demo ' ), $checks, $failures );
$__as_secret = 'as-secret-123';
$__as_hash   = $AS::hash_code( 'LIEBHERR-DEMO', $__as_secret );
liw_assert( 'AccessService: hash_code deterministisch + verify akzeptiert richtige Eingabe (case-insensitiv)', $__as_hash === $AS::hash_code( 'liebherr-demo', $__as_secret ) && true === $AS::verify( 'liebherr-demo', $__as_hash, $__as_secret ), $checks, $failures );
liw_assert( 'AccessService: falscher Code + leerer Hash werden abgelehnt', false === $AS::verify( 'FALSCH', $__as_hash, $__as_secret ) && false === $AS::verify( 'LIEBHERR-DEMO', '', $__as_secret ), $checks, $failures );

// CVF – Rollen-Mapping (reine Mapping-Logik, alpha.85).
require_once $root . '/src/Cvf/Roles.php';
$RO = '\Liebherr\InterfaceWorld\Cvf\Roles';
$__rc = $RO::role_caps();
liw_assert( 'Roles: 5 CVF-Caps; administrator/araliya_admin erhalten alle', 5 === count( $RO::all_caps() ) && $__rc['administrator'] === $RO::all_caps() && $__rc['araliya_admin'] === $RO::all_caps(), $checks, $failures );
liw_assert( 'Roles: Marketing→edit_content, Ops→edit_workflow+publish, keine eigenen CVF-Rollen', in_array( $RO::CAP_EDIT_CONTENT, $__rc['araliya_marketing'], true ) && in_array( $RO::CAP_EDIT_WORKFLOW, $__rc['araliya_ops'], true ) && in_array( $RO::CAP_PUBLISH, $__rc['araliya_ops'], true ) && 5 === count( $RO::map() ), $checks, $failures );

// CVF – Steps (reine Schritt-Beschreibung, alpha.86).
require_once $root . '/src/Cvf/Steps.php';
$STP = '\Liebherr\InterfaceWorld\Cvf\Steps';
liw_assert( 'Steps: Zustand → Schritt-Typ (at_challenge=challenge, in_module=done, blocked=blocked)', 'challenge' === $STP::type_for( 'at_challenge' ) && 'done' === $STP::type_for( 'in_module' ) && 'blocked' === $STP::type_for( 'blocked' ) && 'entry' === $STP::type_for( 'at_entry' ), $checks, $failures );
$__stp = $STP::describe( 'at_module_select', [ 'modules' => [ [ 'key' => 'x' ] ] ] );
liw_assert( 'Steps: describe traegt type+state+extra', 'module_select' === $__stp['type'] && 'at_module_select' === $__stp['state'] && 1 === count( $__stp['modules'] ), $checks, $failures );

// CAPDB – Plugin-Zustandsautomat + Scopes/Kategorien (rein, alpha.89).
require_once $root . '/src/Cvf/PluginState.php';
require_once $root . '/src/Cvf/PluginTaxonomy.php';
$PS = '\Liebherr\InterfaceWorld\Cvf\PluginState';
$PX = '\Liebherr\InterfaceWorld\Cvf\PluginTaxonomy';
liw_assert( 'PluginState: 11 Zustaende; erlaubte/verbotene Uebergaenge (open->closing ok, open->scheduled nein)', 11 === count( $PS::all() ) && $PS::can_transition( 'open', 'closing' ) && $PS::can_transition( 'configured', 'scheduled' ) && ! $PS::can_transition( 'open', 'scheduled' ) && ! $PS::can_transition( 'closed', 'open' ), $checks, $failures );
liw_assert( 'PluginTaxonomy: 2 Scopes, 7 Kategorien, parse_scopes + scope_allowed', 2 === count( $PX::scopes() ) && 7 === count( $PX::categories() ) && [ 'page', 'edge' ] === $PX::parse_scopes( 'page, edge, quatsch' ) && $PX::scope_allowed( 'edge', 'page,edge' ) && ! $PX::scope_allowed( 'edge', 'page' ), $checks, $failures );

// CAPDB – Plugin-Registry (Definitionen + Config-Validierung, rein, alpha.90).
require_once $root . '/src/Cvf/PluginRegistry.php';
$PR = '\Liebherr\InterfaceWorld\Cvf\PluginRegistry';
$__defs = $PR::definitions();
$__cats = array_unique( array_map( static fn( $d ) => $d['category'], $__defs ) );
liw_assert( 'PluginRegistry: 8 Typen decken alle 7 Kategorien ab', 8 === count( $__defs ) && 7 === count( $__cats ) && isset( $__defs['challenge_addition'], $__defs['open_module'], $__defs['set_grant'], $__defs['confirm'] ), $checks, $failures );
liw_assert( 'PluginRegistry: validate_config enum/int/unknown', [] === $PR::validate_config( 'challenge_addition', [ 'difficulty' => 'double' ] ) && in_array( 'bad_enum_difficulty', $PR::validate_config( 'challenge_addition', [ 'difficulty' => 'triple' ] ), true ) && in_array( 'below_min_seconds', $PR::validate_config( 'countdown', [ 'seconds' => 0 ] ), true ) && in_array( 'not_int_seconds', $PR::validate_config( 'countdown', [ 'seconds' => 'x' ] ), true ) && [ 'unknown_type' ] === $PR::validate_config( 'gibtsnicht', [] ), $checks, $failures );
liw_assert( 'PluginRegistry: with_defaults fuellt Schema-Defaults', 'double' === $PR::with_defaults( 'challenge_addition', [] )['difficulty'] && 5 === $PR::with_defaults( 'countdown', [] )['seconds'], $checks, $failures );

// CAPDB – BoardRuntime (reine Aufloesung, alpha.92).
require_once $root . '/src/Cvf/BoardRuntime.php';
$BR = '\Liebherr\InterfaceWorld\Cvf\BoardRuntime';
$__snap = [
	'areas' => [ [ 'id' => 5, 'module_id' => 'm', 'position' => 2, 'status' => 'active' ], [ 'id' => 4, 'module_id' => 'iw', 'position' => 1, 'status' => 'active' ] ],
	'edges' => [ [ 'id' => 9, 'from_area_id' => 4, 'to_area_id' => 5, 'trigger_type' => 't', 'priority' => 200 ], [ 'id' => 8, 'from_area_id' => 4, 'to_area_id' => 5, 'trigger_type' => 't', 'priority' => 100 ] ],
	'instances' => [ [ 'id' => 1, 'plugin_key' => 'a', 'host_type' => 'edge', 'host_id' => 8, 'status' => 'configured', 'priority' => 50 ], [ 'id' => 2, 'plugin_key' => 'b', 'host_type' => 'edge', 'host_id' => 8, 'status' => 'disabled', 'priority' => 10 ] ],
];
liw_assert( 'BoardRuntime: entry_area = kleinste Position (iw@1)', 'iw' === $BR::entry_area( $__snap )['module_id'], $checks, $failures );
liw_assert( 'BoardRuntime: edges_from nach Prioritaet (100 vor 200)', 100 === (int) $BR::edges_from( $__snap, 4 )[0]['priority'], $checks, $failures );
liw_assert( 'BoardRuntime: plugins_for schliesst disabled aus', 1 === count( $BR::plugins_for( $__snap, 'edge', 8 ) ) && 'a' === $BR::plugins_for( $__snap, 'edge', 8 )[0]['plugin_key'], $checks, $failures );
$__win = $BR::resolve_window( [ 'open_at_ms' => 5000, 'close_at_ms' => null, 'duration_ms' => 15000, 'timeout_ms' => null, 'resume_policy' => 'restart' ] );
liw_assert( 'BoardRuntime: resolve_window leitet closeAt aus openAt+duration ab (20000) + resume', 20000 === $__win['close_at_ms'] && 5000 === $__win['open_at_ms'] && 'restart' === $__win['resume_policy'], $checks, $failures );
$__mk = $BR::marker_plan( [ [ 'id' => 1, 'plugin_key' => 'a' ] ], [ 1 => [ 'open_at_ms' => 5000, 'close_at_ms' => 20000 ] ] );
liw_assert( 'BoardRuntime: marker_plan open vor close, zeitsortiert', 2 === count( $__mk ) && 'open' === $__mk[0]['type'] && 5000 === $__mk[0]['at_ms'] && 'close' === $__mk[1]['type'], $checks, $failures );

// CAPDB – BoardDiff (reiner Snapshot-Vergleich, alpha.95).
require_once $root . '/src/Cvf/BoardDiff.php';
$BD = '\Liebherr\InterfaceWorld\Cvf\BoardDiff';
$__snapA = [ 'areas' => [ [ 'id' => 1, 'module_id' => 'iw', 'position' => 1 ] ], 'edges' => [], 'instances' => [ [ 'plugin_key' => 'x', 'host_type' => 'page', 'host_id' => 1 ] ] ];
$__snapB = [ 'areas' => [ [ 'id' => 9, 'module_id' => 'iw', 'position' => 1 ] ], 'edges' => [], 'instances' => [ [ 'plugin_key' => 'x', 'host_type' => 'page', 'host_id' => 9 ], [ 'plugin_key' => 'y', 'host_type' => 'page', 'host_id' => 9 ] ] ];
$__d = $BD::compare( $__snapA, $__snapB );
liw_assert( 'BoardDiff: erkennt hinzugefuegtes Plugin (positionsstabil), changed=true', $__d['changed'] && [ 'y@page#1' ] === $__d['instances']['added'] && [] === $__d['instances']['removed'] && [] === $__d['areas']['added'], $checks, $failures );
liw_assert( 'BoardDiff: identische Struktur -> changed=false', false === $BD::compare( $__snapA, $__snapA )['changed'], $checks, $failures );

// Emergency – Hilfe-Koffer (einstellige Rechenaufgabe) + kontextbezogene Emergency-Area (alpha.78).
require_once $root . '/src/Emergency/EmergencyChallenge.php';
require_once $root . '/src/Emergency/HelpTopicCatalog.php';
require_once $root . '/src/Emergency/EmergencyController.php';
$EC = '\Liebherr\InterfaceWorld\Emergency\EmergencyChallenge';
$__now = 1000000;
$__c   = $EC::create( 'secret-xyz', $__now );
liw_assert( 'EmergencyChallenge: zwei EINSTELLIGE Summanden (1..9) + Token + Ablauf in der Zukunft', $__c['a'] >= 1 && $__c['a'] <= 9 && $__c['b'] >= 1 && $__c['b'] <= 9 && '' !== $__c['token'] && $__c['expires'] > $__now, $checks, $failures );
liw_assert( 'EmergencyChallenge: question ohne Loesung ("a + b = ?")', ( $__c['a'] . ' + ' . $__c['b'] . ' = ?' ) === $EC::question( $__c['a'], $__c['b'] ), $checks, $failures );
$__sum = $__c['a'] + $__c['b'];
liw_assert( 'EmergencyChallenge: richtige Antwort verifiziert (ok)', true === $EC::verify( $__c['token'], $__sum, 'secret-xyz', $__now )['ok'], $checks, $failures );
liw_assert( 'EmergencyChallenge: falsche Antwort → ok=false, reason=wrong', false === $EC::verify( $__c['token'], $__sum + 1, 'secret-xyz', $__now )['ok'] && 'wrong' === $EC::verify( $__c['token'], $__sum + 1, 'secret-xyz', $__now )['reason'], $checks, $failures );
liw_assert( 'EmergencyChallenge: abgelaufenes Token → reason=expired', 'expired' === $EC::verify( $__c['token'], $__sum, 'secret-xyz', $__c['expires'] + 1 )['reason'], $checks, $failures );
liw_assert( 'EmergencyChallenge: falsches Secret → reason=invalid (Signatur)', 'invalid' === $EC::verify( $__c['token'], $__sum, 'anderes-secret', $__now )['reason'], $checks, $failures );
liw_assert( 'EmergencyChallenge: manipuliertes Token → reason=invalid', 'invalid' === $EC::verify( $__c['token'] . 'x', $__sum, 'secret-xyz', $__now )['reason'], $checks, $failures );
$HTC = '\Liebherr\InterfaceWorld\Emergency\HelpTopicCatalog';
liw_assert( 'HelpTopicCatalog: Frontend-Pfad /intelligence-world/ → intelligence-world', 'intelligence-world' === $HTC::detect( '/intelligence-world/', false, '' ), $checks, $failures );
liw_assert( 'HelpTopicCatalog: Admin-Seite liw-adventures → adventures', 'adventures' === $HTC::detect( '', true, 'liw-adventures' ), $checks, $failures );
liw_assert( 'HelpTopicCatalog: unbekannter Admin-Kontext → admin, unbekanntes Frontend → default', 'admin' === $HTC::detect( '', true, 'irgendwas' ) && 'default' === $HTC::detect( '/impressum/', false, '' ), $checks, $failures );
liw_assert( 'HelpTopicCatalog: unbekannter Schluessel faellt auf default zurueck', 'default' === $HTC::topic( 'gibtsnicht' )['key'], $checks, $failures );
liw_assert( 'HelpTopicCatalog: allgemeine Schritte = GetHelpAssistant-SSOT (5, keine Duplikate)', 5 === count( $HTC::topic( 'default' )['steps'] ), $checks, $failures );
$EMC = '\Liebherr\InterfaceWorld\Emergency\EmergencyController';
$__hub = $EMC::render_hub( 'adventures' );
liw_assert( 'EmergencyController: render_hub liefert Section mit Titel + <details>-Schritten', str_contains( $__hub, 'liw-emg-hub' ) && str_contains( $__hub, '<details>' ) && str_contains( $__hub, 'Adventures' ), $checks, $failures );

// My Liebherr – Fundament S1: Rollen-Mapping, Kontext-Feldfreigabe, Entitlement-Kernlogik (ADR-LIW-MYL-001).
echo "-- My Liebherr (S1) --\n";
require_once $root . '/src/MyLiebherr/Roles.php';
require_once $root . '/src/MyLiebherr/EntitlementService.php';
require_once $root . '/src/MyLiebherr/Context.php';
$MR = '\Liebherr\InterfaceWorld\MyLiebherr\Roles';
$ME = '\Liebherr\InterfaceWorld\MyLiebherr\EntitlementService';
$MC = '\Liebherr\InterfaceWorld\MyLiebherr\Context';
$__mrc = $MR::role_caps();
liw_assert( 'MyL Roles: 3 Caps (access, administer, moderate); administrator/araliya_admin erhalten alle', 3 === count( $MR::all_caps() ) && $__mrc['administrator'] === $MR::all_caps() && $__mrc['araliya_admin'] === $MR::all_caps(), $checks, $failures );
liw_assert( 'MyL Roles: araliya_ops = access + moderate (Prüfer), kein administer', in_array( $MR::CAP_ACCESS, $__mrc['araliya_ops'], true ) && in_array( $MR::CAP_MODERATE, $__mrc['araliya_ops'], true ) && ! in_array( $MR::CAP_ADMINISTER, $__mrc['araliya_ops'], true ), $checks, $failures );
liw_assert( 'MyL Context: allowed_fields = genau 5 (persona, locale, timezone, active_org_id, active_role)', 5 === count( $MC::allowed_fields() ) && in_array( 'active_org_id', $MC::allowed_fields(), true ), $checks, $failures );
$__patch = $MC::sanitize_patch( [ 'persona' => 'employee', 'locale' => 'de_DE', 'active_org_id' => '7', 'evil' => 'x', 'active_role' => 'ops' ] );
liw_assert( 'MyL Context: sanitize_patch behaelt erlaubte Felder, verwirft unbekannte, castet org_id', 'employee' === $__patch['persona'] && 7 === $__patch['active_org_id'] && ! array_key_exists( 'evil', $__patch ), $checks, $failures );
liw_assert( 'MyL Context: unzulaessige Persona wird verworfen', ! array_key_exists( 'persona', $MC::sanitize_patch( [ 'persona' => 'hacker' ] ) ), $checks, $failures );
$__acc = [ $MR::CAP_ACCESS ];
$__adm = [ $MR::CAP_ACCESS, $MR::CAP_ADMINISTER ];
liw_assert( 'MyL Entitlement: fehlende Cap → false', false === $ME::can( $__acc, $MR::CAP_ADMINISTER ), $checks, $failures );
liw_assert( 'MyL Entitlement: eigenes Objekt mit Cap → true', true === $ME::can( $__acc, $MR::CAP_ACCESS, [ 'owner_user_id' => 5, 'actor_user_id' => 5 ] ), $checks, $failures );
liw_assert( 'MyL Entitlement: fremdes Objekt ohne Administer → false, mit Administer → true', false === $ME::can( $__acc, $MR::CAP_ACCESS, [ 'owner_user_id' => 9, 'actor_user_id' => 5 ] ) && true === $ME::can( $__adm, $MR::CAP_ACCESS, [ 'owner_user_id' => 9, 'actor_user_id' => 5 ] ), $checks, $failures );
liw_assert( 'MyL Entitlement: Org-Mismatch ohne Administer → false, passende Org → true', false === $ME::can( $__acc, $MR::CAP_ACCESS, [ 'required_org_id' => 3, 'active_org_id' => 1 ] ) && true === $ME::can( $__acc, $MR::CAP_ACCESS, [ 'required_org_id' => 3, 'active_org_id' => 3 ] ), $checks, $failures );

// My Liebherr R3 (ADR-LIW-MYL-001 §31/§32): reine Regeln für Dreams/Gallery/Shares.
echo "-- My Liebherr R3 Content-Regeln --\n";
require_once $root . '/src/MyLiebherr/ContentRules.php';
$CR = '\Liebherr\InterfaceWorld\MyLiebherr\ContentRules';
liw_assert( 'ContentRules: wish/visibility/scope/recipient fallen auf Standard zurück', 'idea' === $CR::wish( 'x' ) && 'favorite' === $CR::wish( 'favorite' ) && 'private' === $CR::visibility( 'x' ) && 'view' === $CR::scope( 'x' ) && 'user' === $CR::recipient_type( 'x' ) && 'world' === $CR::recipient_type( 'world' ), $checks, $failures );
liw_assert( 'ContentRules: three_words verdichtet + begrenzt auf drei; is_three_words prüft genau drei', 'Raupe Hydraulik Entlueften' === $CR::three_words( '  Raupe   Hydraulik Entlueften extra ' ) && true === $CR::is_three_words( 'a b c' ) && false === $CR::is_three_words( 'a b' ), $checks, $failures );
require_once $root . '/src/MyLiebherr/ContactState.php';
$CST = '\Liebherr\InterfaceWorld\MyLiebherr\ContactState';
liw_assert( 'ContactState: requested→accepted/declined ok, requested→active verboten', $CST::can_request( 'requested', 'accepted' ) && $CST::can_request( 'requested', 'declined' ) && ! $CST::can_request( 'requested', 'active' ), $checks, $failures );
liw_assert( 'ContactState: connection accepted→active, active→paused/ended, ended→(nichts)', $CST::can_connection( 'accepted', 'active' ) && $CST::can_connection( 'active', 'paused' ) && $CST::can_connection( 'active', 'ended' ) && ! $CST::can_connection( 'ended', 'active' ), $checks, $failures );
liw_assert( 'ContactState: service proposed→confirmed, confirmed→settled, settled→(nichts)', $CST::can_service( 'proposed', 'confirmed' ) && $CST::can_service( 'confirmed', 'settled' ) && ! $CST::can_service( 'settled', 'confirmed' ), $checks, $failures );

// My Liebherr Dashboard S3 (ADR-LIW-MYL-001 §5): Widget-Katalog (Cap-Filter) + reine Layout-Logik.
echo "-- My Liebherr Dashboard (S3) --\n";
require_once $root . '/src/MyLiebherr/WidgetCatalog.php';
require_once $root . '/src/MyLiebherr/DashboardService.php';
$WC = '\Liebherr\InterfaceWorld\MyLiebherr\WidgetCatalog';
$DS = '\Liebherr\InterfaceWorld\MyLiebherr\DashboardService';
$__caps = [ $MR::CAP_ACCESS ];
liw_assert( 'MyL Dashboard: WidgetCatalog liefert 4 berechtigte Widgets (mit access)', 4 === count( $WC::permitted_for( $__caps ) ) && [] === $WC::permitted_for( [] ), $checks, $failures );
$__def = $DS::default_layout( $__caps );
liw_assert( 'MyL Dashboard: default_layout = alle berechtigten sichtbar, Reihenfolge wallet zuerst', 4 === count( $__def ) && 'wallet' === $__def[0]['key'] && true === $__def[0]['visible'], $checks, $failures );
$__stored = [ [ 'key' => 'tasks', 'visible' => true ], [ 'key' => 'wallet', 'visible' => false ], [ 'key' => 'unknown', 'visible' => true ] ];
$__res = $DS::resolve( $__caps, $__stored );
liw_assert( 'MyL Dashboard: resolve wahrt gespeicherte Reihenfolge/Sichtbarkeit, verwirft Unbekanntes, ergaenzt Rest', 'tasks' === $__res[0]['key'] && 'wallet' === $__res[1]['key'] && false === $__res[1]['visible'] && 4 === count( $__res ) && ! in_array( 'unknown', array_column( $__res, 'key' ), true ), $checks, $failures );
liw_assert( 'MyL Dashboard: nicht berechtigte Caps → leeres Layout', [] === $DS::resolve( [], $__stored ), $checks, $failures );

// Plattformzeit S9/S10 (ADR-LIW-MYL-001, §41): Tokenregel, serverautoritäre Zeitlogik, Wallet-Guard.
echo "-- Plattformzeit (S9/S10) --\n";
require_once $root . '/src/PlatformTime/TokenRule.php';
require_once $root . '/src/PlatformTime/SessionClock.php';
require_once $root . '/src/CoreBridge/WalletBridge.php';
$TR = '\Liebherr\InterfaceWorld\PlatformTime\TokenRule';
$SCk = '\Liebherr\InterfaceWorld\PlatformTime\SessionClock';
$WB = '\Liebherr\InterfaceWorld\CoreBridge\WalletBridge';
$__tr = new $TR( 10, 'ptime-1' );
liw_assert( 'PTime TokenRule: 60s=10 Token, 6s=1, 59s=9 (konservativ ganzzahlig)', 10 === $__tr->tokens_for( 60 ) && 1 === $__tr->tokens_for( 6 ) && 9 === $__tr->tokens_for( 59 ), $checks, $failures );
$__trc = new $TR( 10, 'ptime-1', 5, 8 );
liw_assert( 'PTime TokenRule: Min/Max-Clamp (0s→min 5, 600s→max 8)', 5 === $__trc->tokens_for( 0 ) && 8 === $__trc->tokens_for( 600 ), $checks, $failures );
$__acc1 = $SCk::accrue( 0, 100, 130, 300 );
liw_assert( 'PTime SessionClock: zeitnah rechnet Gap voll an (30s, kein Idle)', 30 === $__acc1['active'] && 130 === $__acc1['last_seen'] && false === $__acc1['idle'], $checks, $failures );
$__acc2 = $SCk::accrue( 30, 100, 500, 300 );
liw_assert( 'PTime SessionClock: Gap > Timeout → Idle/Pause, keine Anrechnung', 30 === $__acc2['active'] && true === $__acc2['idle'] && 500 === $__acc2['last_seen'], $checks, $failures );
$__acc3 = $SCk::accrue( 10, 100, 100, 300 );
liw_assert( 'PTime SessionClock: now <= last_seen → keine Änderung', 10 === $__acc3['active'] && false === $__acc3['idle'], $checks, $failures );
liw_assert( 'PTime WalletBridge: ohne Core nicht verfügbar; balance_cents(0)=null (Gast-Guard)', false === $WB::available() && null === $WB::balance_cents( 0 ), $checks, $failures );
liw_assert( 'MyL WalletBridge: source_label bekannt (booking_debit→Buchung), unbekannt→Rohwert; status_label pending→ausstehend', 'Buchung' === $WB::source_label( 'booking_debit' ) && 'nope' === $WB::source_label( 'nope' ) && 'ausstehend' === $WB::status_label( 'pending' ), $checks, $failures );

// My Liebherr Medien-Pipeline (ADR-LIW-MYL-001 §14/§16): reine MIME-/Größen-/Klassifikationslogik.
echo "-- My Liebherr Medien-Pipeline --\n";
require_once $root . '/src/MyLiebherr/MediaPipeline.php';
$MP = '\Liebherr\InterfaceWorld\MyLiebherr\MediaPipeline';
liw_assert( 'MediaPipeline: erlaubte MIME (jpeg/png/webp/gif), abgelehnt application/pdf', $MP::mime_allowed( 'image/jpeg' ) && $MP::mime_allowed( 'image/webp' ) && ! $MP::mime_allowed( 'application/pdf' ), $checks, $failures );
liw_assert( 'MediaPipeline: size_ok nur >0 und <= Max', $MP::size_ok( 1000, 8388608 ) && ! $MP::size_ok( 0, 8388608 ) && ! $MP::size_ok( 9000000, 8388608 ), $checks, $failures );
liw_assert( 'MediaPipeline: classify invalid/quarantine/approved', $MP::S_INVALID === $MP::classify( false, true, true, true, true ) && $MP::S_INVALID === $MP::classify( true, false, true, true, true ) && $MP::S_QUARANTINE === $MP::classify( true, true, true, true, false ) && $MP::S_QUARANTINE === $MP::classify( true, true, true, false, true ) && $MP::S_APPROVED === $MP::classify( true, true, true, true, true ), $checks, $failures );

// My Liebherr Moderation (ADR-LIW-MYL-001 §11/§31): reine Übergangsregeln + Prüfer-Rollenmapping.
echo "-- My Liebherr Moderation --\n";
require_once $root . '/src/MyLiebherr/ModerationService.php';
$MOD = '\Liebherr\InterfaceWorld\MyLiebherr\ModerationService';
liw_assert( 'Moderation: World-Review nur pending→published/blocked', $MOD::can_world_review( 'pending', 'published' ) && $MOD::can_world_review( 'pending', 'blocked' ) && ! $MOD::can_world_review( 'published', 'blocked' ), $checks, $failures );
liw_assert( 'Moderation: Report-Aktion nur auf open/reviewed + gültige Aktion', $MOD::can_report_action( 'open', 'suspend' ) && $MOD::can_report_action( 'reviewed', 'refund' ) && ! $MOD::can_report_action( 'dismissed', 'suspend' ) && ! $MOD::can_report_action( 'open', 'quatsch' ), $checks, $failures );
$__mrc2 = $MR::role_caps();
liw_assert( 'MyL Roles: araliya_ops/reception erhalten CAP_MODERATE (Prüfer); araliya_marketing nicht', in_array( $MR::CAP_MODERATE, $__mrc2['araliya_ops'], true ) && in_array( $MR::CAP_MODERATE, $__mrc2['araliya_reception'], true ) && ! in_array( $MR::CAP_MODERATE, $__mrc2['araliya_marketing'] ?? [], true ), $checks, $failures );

// My Liebherr Drei-Wort-Label (ADR-LIW-MYL-001 §32): reine Normalisierung/Validierung/Vorschlag/Tokens.
echo "-- My Liebherr Drei-Wort-Label --\n";
require_once $root . '/src/MyLiebherr/ThreeWordLabel.php';
$TW = '\Liebherr\InterfaceWorld\MyLiebherr\ThreeWordLabel';
liw_assert( 'ThreeWordLabel: normalize → ein Wort, Großanfang (" hYDRAULIK zusatz" → "Hydraulik")', 'Hydraulik' === $TW::normalize_term( ' hYDRAULIK zusatz' ), $checks, $failures );
$__tw_w = $TW::from_words( 'raupe', 'hydraulik', 'entlüften' );
liw_assert( 'ThreeWordLabel: from_words normalisiert alle drei; valid=true; display "Raupe Hydraulik Entlüften"', 'Raupe' === $__tw_w[0] && $TW::valid( $__tw_w[0], $__tw_w[1], $__tw_w[2] ) && 'Raupe Hydraulik Entlüften' === $TW::display( $__tw_w[0], $__tw_w[1], $__tw_w[2] ), $checks, $failures );
liw_assert( 'ThreeWordLabel: valid=false bei fehlendem Begriff', false === $TW::valid( 'Raupe', '', 'Entlüften' ), $checks, $failures );
$__tw_s = $TW::suggest( 'Raupe R9200', 'Hydraulikpumpe', 'Foo Entlüften Bar' );
liw_assert( 'ThreeWordLabel: suggest bildet drei Begriffe aus Maschine/Bauteil/Titel', 'Raupe' === $__tw_s[0] && 'Hydraulikpumpe' === $__tw_s[1] && '' !== $__tw_s[2], $checks, $failures );
$__tw_tok = $TW::tokens( [ 'term_1' => 'Raupe', 'term_2' => 'Hydraulik', 'term_3' => 'Entlüften', 'synonyms' => 'Bagger, Kette' ] );
liw_assert( 'ThreeWordLabel: tokens = 3 Begriffe + Synonyme (5)', 5 === count( $__tw_tok ) && in_array( 'Bagger', $__tw_tok, true ), $checks, $failures );

// My Liebherr Overview-Daten (ADR-LIW-MYL-001 §5): reine truncate-Logik der Widget-Listen.
echo "-- My Liebherr Overview-Daten --\n";
require_once $root . '/src/MyLiebherr/OverviewData.php';
$OD = '\Liebherr\InterfaceWorld\MyLiebherr\OverviewData';
$__od_items = [];
for ( $__i = 1; $__i <= 6; $__i++ ) { $__od_items[] = [ 'label' => 'L' . $__i, 'url' => '' ]; }
$__od_t = $OD::truncate( $__od_items, 4 );
liw_assert( 'OverviewData: truncate auf 4 + „+2 …"-Hinweis (5. Eintrag)', 5 === count( $__od_t ) && 'L1' === $__od_t[0]['label'] && '+2 …' === $__od_t[4]['label'], $checks, $failures );
liw_assert( 'OverviewData: kurze Liste bleibt unverändert', 3 === count( $OD::truncate( [ [ 'label' => 'a', 'url' => '' ], [ 'label' => 'b', 'url' => '' ], [ 'label' => 'c', 'url' => '' ] ], 4 ) ), $checks, $failures );

// Pocket regelbasiert (ADR-LIW-MYL-001 §34): reine Feed-Merge-/Sortierlogik (Priorität, gespeicherte vor abgeleiteten).
echo "-- Pocket Feed-Merge --\n";
require_once $root . '/src/Pocket/FeedService.php';
$FS = '\Liebherr\InterfaceWorld\Pocket\FeedService';
$__fs_m = $FS::merge(
	[ [ 'priority' => 'normal', 'title' => 'S1' ], [ 'priority' => 'critical', 'title' => 'S2' ] ],
	[ [ 'priority' => 'high', 'title' => 'D1' ], [ 'priority' => 'normal', 'title' => 'D2' ] ]
);
liw_assert(
	'Pocket Feed: critical zuerst, dann high; gleiche Prio → gespeichert vor abgeleitet; derived-Flag gesetzt',
	'S2' === $__fs_m[0]['title'] && false === $__fs_m[0]['derived']
	&& 'D1' === $__fs_m[1]['title'] && true === $__fs_m[1]['derived']
	&& 'S1' === $__fs_m[2]['title'] && 'D2' === $__fs_m[3]['title'] && true === $__fs_m[3]['derived'],
	$checks, $failures
);

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
