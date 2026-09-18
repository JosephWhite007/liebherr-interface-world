<?php
/**
 * Liebherr Interface Solutions – Import der Marken-Assets als CI-005-KANDIDATEN.
 *
 * Lädt die für die Landingpage benötigten Liebherr-Assets (Logo, Webfonts, Bildmotive)
 * server-seitig von liebherr.com in die WordPress-Medienbibliothek und legt sie im
 * Media Board als NICHT FREIGEGEBENE Kandidaten an (CI-005): jeweils mit Copyright,
 * Quelle und Freigabestatus `0`. Es findet KEINE Verwendung auf öffentlichen Seiten statt,
 * bevor Liebherrs dokumentierte Freigabe vorliegt (CI-002/CI-005, Markenschutz). Kein
 * erfundenes Branding – die Werte stammen unverändert aus Liebherrs eigenem Auftritt.
 *
 * Idempotent: Ein Asset mit identischer Quelle-URL (`_liw_media_source` beginnt mit der URL)
 * wird nicht erneut angelegt. Erneuter Lauf meldet „übersprungen".
 *
 * Terminal-Regel (CLAUDE.md): Ausführung im Dev-Container durch Joseph:
 *   docker exec araliya_wordpress php \
 *     /var/www/html/wp-content/plugins/liebherr-interface-world/scripts/liw-import-brand-assets.php --confirm
 *
 * @package Liebherr\InterfaceWorld
 * @since   0.1.0-alpha.27
 */

declare( strict_types = 1 );

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "Nur für die Kommandozeile.\n" );
	exit( 2 );
}
if ( ! in_array( '--confirm', $argv, true ) ) {
	fwrite( STDOUT, "Trockenlauf-Schutz: Bitte mit --confirm ausführen. Es werden Marken-Assets als NICHT freigegebene Kandidaten importiert.\n" );
	exit( 0 );
}

require_once dirname( __DIR__, 4 ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

use Liebherr\InterfaceWorld\CoreBridge\MediaBridge;

if ( ! class_exists( MediaBridge::class ) ) {
	fwrite( STDERR, "Plugin liebherr-interface-world ist nicht aktiv.\n" );
	exit( 1 );
}

// SVG (Logo) und woff2 (Webfonts) sind kein Standard-Upload-Typ; für diesen kontrollierten
// Import temporär zulassen, damit wp_upload_bits() sie ablegt. Betrifft nur diesen CLI-Lauf.
add_filter( 'upload_mimes', static function ( array $mimes ): array {
	$mimes['svg']   = 'image/svg+xml';
	$mimes['woff2'] = 'font/woff2';
	return $mimes;
} );

$COPYRIGHT = '© Liebherr (liebherr.com)';
$SOURCE    = 'liebherr.com – Kandidat, Freigabe durch Liebherr ausstehend (CI-005)';

/**
 * Asset-Manifest. role: logo|font|hero|machine|service. alt: sachlicher deutscher Alt-Text.
 * mime wird explizit gesetzt (SVG/woff2 umgehen so den Uploader-MIME-Filter beim programmatischen Insert).
 *
 * @var array<int,array{url:string,mime:string,role:string,title:string,alt:string}>
 */
$assets = [
	// Logo (offizielles CI-Logo).
	[ 'url' => 'https://www-assets.liebherr.com/media/global/global-media/liebherr_logos/logo_ci_liebherr.svg', 'mime' => 'image/svg+xml', 'role' => 'logo', 'title' => 'Liebherr Logo (CI)', 'alt' => 'Liebherr' ],
	// Webfonts (Latin) – LiebherrHead (Headline) + LiebherrText (Fließtext).
	[ 'url' => 'https://assets-cdn.liebherr.com/assets/api/bfb44747-d200-4af8-a756-fab6de288b32/original/LiebherrHead-Regular_Web.woff2', 'mime' => 'font/woff2', 'role' => 'font', 'title' => 'LiebherrHead Regular (Web)', 'alt' => '' ],
	[ 'url' => 'https://assets-cdn.liebherr.com/assets/api/3c3d21f7-2747-4a07-bb1b-f9f4598c21df/original/LiebherrHead-Black_Web.woff2', 'mime' => 'font/woff2', 'role' => 'font', 'title' => 'LiebherrHead Black (Web)', 'alt' => '' ],
	[ 'url' => 'https://assets-cdn.liebherr.com/assets/api/cbef770b-7eef-4164-8dc7-a304a545b01f/original/LiebherrText-Regular_Web.woff2', 'mime' => 'font/woff2', 'role' => 'font', 'title' => 'LiebherrText Regular (Web)', 'alt' => '' ],
	[ 'url' => 'https://assets-cdn.liebherr.com/assets/api/d726fec5-dd05-4133-a47c-77d7900263f6/original/LiebherrText-Medium_Web.woff2', 'mime' => 'font/woff2', 'role' => 'font', 'title' => 'LiebherrText Medium (Web)', 'alt' => '' ],
	[ 'url' => 'https://assets-cdn.liebherr.com/assets/api/53da5b14-5565-4436-8d08-3bf3cf0262ae/original/LiebherrText-Bold_Web.woff2', 'mime' => 'font/woff2', 'role' => 'font', 'title' => 'LiebherrText Bold (Web)', 'alt' => '' ],
	// Bildmotive (Baumaschinen / Hero-Kandidaten).
	[ 'url' => 'https://www-assets.liebherr.com/media/bu-media/lhbu-emt/products/collective-motifs/liebherr-all-earthmovers_w736.webp', 'mime' => 'image/webp', 'role' => 'hero', 'title' => 'Liebherr Earthmovers (Sammelmotiv)', 'alt' => 'Liebherr Erdbewegungsmaschinen im Sammelmotiv' ],
	[ 'url' => 'https://www-assets.liebherr.com/media/bu-media/lhbu-lbc/images/applications/kraneinsaetze/hc-l/liebherr-hc-l-sagrada-familia-1_w736.jpg', 'mime' => 'image/jpeg', 'role' => 'machine', 'title' => 'Liebherr HC-L Kran (Sagrada Família)', 'alt' => 'Liebherr HC-L Turmdrehkran im Einsatz' ],
	[ 'url' => 'https://www-assets.liebherr.com/media/bu-media/lhbu-corp/images/products/liebherr-mk-1920x1920px_w736.jpg', 'mime' => 'image/jpeg', 'role' => 'machine', 'title' => 'Liebherr MK (Mobilbaukran)', 'alt' => 'Liebherr Mobilbaukran MK' ],
	[ 'url' => 'https://www-assets.liebherr.com/media/bu-media/lhbu-corp/images/products/construction-equipment/liebherr-2025-sammelbild-lmt-2-1920x1920_w736.jpg', 'mime' => 'image/jpeg', 'role' => 'machine', 'title' => 'Liebherr Mobil- und Raupenkrane (Sammelbild)', 'alt' => 'Liebherr Mobil- und Raupenkrane im Sammelbild' ],
	[ 'url' => 'https://www-assets.liebherr.com/media/bu-media/lhbu-corp/images/products/construction-equipment/liebherr-service-banner-1920x1280_w768.jpg', 'mime' => 'image/jpeg', 'role' => 'service', 'title' => 'Liebherr Service', 'alt' => 'Liebherr Service an Baumaschinen' ],
];

/** Existiert bereits ein Attachment mit dieser Quelle-URL? */
function liw_asset_exists( string $url ): int {
	$q = new WP_Query( [
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		'meta_query'     => [ [ 'key' => MediaBridge::META_SOURCE, 'value' => $url, 'compare' => 'LIKE' ] ],
	] );
	return $q->have_posts() ? (int) $q->posts[0] : 0;
}

$imported = 0;
$skipped  = 0;
$failed   = 0;

echo '== Liebherr Marken-Assets -> Media Board (CI-005-Kandidaten, NICHT freigegeben) ==' . "\n";

foreach ( $assets as $a ) {
	$url   = $a['url'];
	$label = $a['title'];

	$exists = liw_asset_exists( $url );
	if ( $exists ) {
		echo "  [übersprungen] bereits vorhanden (#{$exists}): {$label}\n";
		$skipped++;
		continue;
	}

	$tmp = download_url( $url, 30 );
	if ( is_wp_error( $tmp ) ) {
		echo "  [FEHLER] Download: {$label} – " . $tmp->get_error_message() . "\n";
		$failed++;
		continue;
	}

	$filename = sanitize_file_name( wp_basename( wp_parse_url( $url, PHP_URL_PATH ) ) );
	$bytes    = (string) file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

	$upload = wp_upload_bits( $filename, null, $bytes );
	if ( ! empty( $upload['error'] ) ) {
		echo "  [FEHLER] Ablage: {$label} – {$upload['error']}\n";
		$failed++;
		continue;
	}

	$attach_id = wp_insert_attachment(
		[
			'post_mime_type' => $a['mime'],
			'post_title'     => 'LIEBHERR-KANDIDAT: ' . $label,
			'post_content'   => '',
			'post_status'    => 'inherit',
		],
		$upload['file']
	);
	if ( is_wp_error( $attach_id ) || 0 === $attach_id ) {
		echo "  [FEHLER] Attachment: {$label}\n";
		$failed++;
		continue;
	}

	// Bild-Metadaten (Thumbnails/Größen) nur für Rastergrafiken; SVG/woff2 auslassen.
	if ( in_array( $a['mime'], [ 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ], true ) ) {
		$meta = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
		if ( is_array( $meta ) && [] !== $meta ) {
			wp_update_attachment_metadata( $attach_id, $meta );
		}
	}

	// CI-005-Metafelder: Copyright, Quelle, Freigabe = 0 (nicht freigegeben) + Alt-Text + Rolle.
	update_post_meta( $attach_id, MediaBridge::META_COPYRIGHT, $COPYRIGHT );
	update_post_meta( $attach_id, MediaBridge::META_SOURCE, $SOURCE . ' | ' . $url );
	update_post_meta( $attach_id, MediaBridge::META_APPROVED, '0' );
	update_post_meta( $attach_id, '_liw_media_role', $a['role'] );
	if ( '' !== $a['alt'] ) {
		update_post_meta( $attach_id, '_wp_attachment_image_alt', $a['alt'] );
	}

	echo "  [importiert #{$attach_id}] {$label}  ({$a['role']}, " . size_format( strlen( $bytes ) ) . ")\n";
	$imported++;
}

echo "\n" . "Ergebnis: {$imported} importiert, {$skipped} uebersprungen, {$failed} fehlgeschlagen." . "\n";
echo 'Alle importierten Assets stehen im Media Board als NICHT FREIGEGEBEN (CI-005). Verwendung erst nach dokumentierter Liebherr-Freigabe.' . "\n";
exit( $failed > 0 ? 1 : 0 );
