<?php
/**
 * Liebherr Interface Solutions – Liebherr-CI anwenden (Demo/Präsentation, alpha.37).
 *
 * Führt drei von Joseph White autorisierte Schritte aus (Anweisung 18.09.2026 „jetzt freigeben"):
 *   1. Media-Board-Kandidaten (Logo, Webfonts, Bildmotive) auf FREIGEGEBEN setzen (_liw_media_approved=1).
 *   2. Brand-Tokens (Brand Board) mit den echten, von liebherr.com abgelesenen Werten belegen
 *      (docs/LIW_BRAND_TOKENS.md) – vorläufig bis zur endgültigen Liebherr-Freigabe, reversibel.
 *   3. Hero-Bild (Header Board) auf das freigegebene Liebherr-Motiv setzen.
 *
 * Idempotent (mehrfach ausführbar). HINWEIS: Die Freigabe ist eine menschliche Entscheidung; dieses
 * Skript setzt sie auf ausdrückliche Autorisierung um und protokolliert das im Audit-Log.
 *
 *   docker exec araliya_wordpress php \
 *     /var/www/html/wp-content/plugins/liebherr-interface-world/scripts/liw-apply-liebherr-ci.php --confirm
 *
 * @package Liebherr\InterfaceWorld
 * @since   0.1.0-alpha.37
 */

declare( strict_types = 1 );

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "Nur für die Kommandozeile.\n" );
	exit( 2 );
}
if ( ! in_array( '--confirm', $argv, true ) ) {
	fwrite( STDOUT, "Trockenlauf-Schutz: mit --confirm ausführen. Setzt Liebherr-Assets auf freigegeben und wendet die CI an (autorisiert JW).\n" );
	exit( 0 );
}

require_once dirname( __DIR__, 4 ) . '/wp-load.php';

use Liebherr\InterfaceWorld\Branding\BrandTokens;
use Liebherr\InterfaceWorld\CoreBridge\AuditBridge;
use Liebherr\InterfaceWorld\CoreBridge\MediaBridge;
use Liebherr\InterfaceWorld\Settings\HeaderSettings;

if ( ! class_exists( BrandTokens::class ) ) {
	fwrite( STDERR, "Plugin liebherr-interface-world ist nicht aktiv.\n" );
	exit( 1 );
}

echo "== Liebherr-CI anwenden (autorisiert JW, vorlaeufig bis endgueltige Freigabe) ==\n";

// 1. Kandidaten freigeben.
$q = new WP_Query( [
	'post_type'      => 'attachment',
	'post_status'    => 'inherit',
	'posts_per_page' => 100,
	'no_found_rows'  => true,
	// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	'meta_query'     => [ [ 'key' => MediaBridge::META_SOURCE, 'value' => 'liebherr.com', 'compare' => 'LIKE' ] ],
] );
$approved = 0;
$logo_id  = 0;
$hero_id  = 0;
foreach ( $q->posts as $p ) {
	update_post_meta( $p->ID, MediaBridge::META_APPROVED, '1' );
	$approved++;
	$role = (string) get_post_meta( $p->ID, '_liw_media_role', true );
	if ( 'logo' === $role && 0 === $logo_id ) {
		$logo_id = (int) $p->ID;
	}
	if ( 'hero' === $role && 0 === $hero_id ) {
		$hero_id = (int) $p->ID;
	}
}
echo "  [1] {$approved} Assets freigegeben (approved=1). Logo #{$logo_id}, Hero #{$hero_id}\n";

// 2. Brand-Tokens (echte Liebherr-Werte, docs/LIW_BRAND_TOKENS.md).
$tokens = BrandTokens::save( [
	'primary'      => '#ffd000',
	'on_primary'   => '#202326',
	'secondary'    => '#2779c4',
	'surface'      => '#ffffff',
	'text'         => '#202326',
	'muted'        => '#6b7278',
	'border'       => '#d3d8dd',
	'heading_font' => 'LiebherrHead, Arial, Helvetica, sans-serif',
	'body_font'    => 'LiebherrText, Arial, Helvetica, sans-serif',
	'radius'       => '2px',
	'content_max'  => '1440px',
	'logo_id'      => $logo_id,
] );
echo "  [2] Brand-Tokens gesetzt (Primaer {$tokens['primary']}, Logo #{$tokens['logo_id']}).\n";

// 3. Hero-Bild im Header Board.
if ( $hero_id > 0 ) {
	$header = HeaderSettings::get();
	$header['hero_image_id'] = $hero_id;
	HeaderSettings::save( $header );
	echo "  [3] Hero-Bild gesetzt (#{$hero_id}).\n";
}

AuditBridge::log( 'apply_ci', 'brand_tokens', 0, [], [ 'approved' => $approved, 'logo_id' => $logo_id, 'hero_id' => $hero_id, 'authorized_by' => 'JW', 'note' => 'vorlaeufig bis endgueltige Liebherr-Freigabe' ], get_current_user_id() );

echo "\n" . 'Fertig. CI angewendet (vorlaeufig). Ruecknahme: Brand Board zuruecksetzen + Media Board approved=0.' . "\n";
