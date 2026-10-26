<?php
/**
 * Liebherr World – Produktliste des Dealer Interface als Artikelvorlage spiegeln (ARY-PH-BV-1.0 Phase BV-0).
 *
 * Holt `GET /product-list` (Testumgebung, lesend, ≈ 14 700 Modelle) und schreibt sie idempotent (Upsert je Produkt_ID)
 * in `liw_product_template`. Ohne --confirm: Trockenlauf (ruft die Liste ab, zählt, schreibt nichts).
 * Optional: --dealer=<kürzel> (Standard: aktiver Händler).
 *
 *   docker exec araliya_wordpress php \
 *     /var/www/html/wp-content/plugins/liebherr-interface-world/scripts/liw-import-product-list.php --confirm
 *
 * @package Liebherr\InterfaceWorld
 * @since   0.1.0-alpha.156
 */

declare( strict_types = 1 );

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "Nur für die Kommandozeile.\n" );
	exit( 2 );
}

require_once dirname( __DIR__, 4 ) . '/wp-load.php';

use Liebherr\InterfaceWorld\SalesInterface\DealerRepository;
use Liebherr\InterfaceWorld\SalesInterface\ProductTemplateSchema;
use Liebherr\InterfaceWorld\SalesInterface\ProductTemplateService;

if ( ! class_exists( ProductTemplateService::class ) ) {
	fwrite( STDERR, "Plugin liebherr-interface-world ist nicht aktiv.\n" );
	exit( 1 );
}

$confirm = in_array( '--confirm', $argv, true );
$dealer  = DealerRepository::active_code();
foreach ( $argv as $arg ) {
	if ( preg_match( '/^--dealer=(.+)$/', $arg, $m ) ) {
		$dealer = sanitize_key( $m[1] );
	}
}

echo "== Liebherr Produktliste → Artikelvorlage (" . ( $confirm ? 'SCHREIBEN' : 'Trockenlauf' ) . ") ==\n";
echo "  Händler:  {$dealer}\n";
if ( $confirm ) {
	ProductTemplateSchema::create_table();
}
echo '  Vorlagen vorher: ' . ProductTemplateService::total() . "\n";
$t0  = microtime( true );
$res = ProductTemplateService::import( ! $confirm, $dealer, 0 );
echo '  Ergebnis: ' . $res['message'] . ' (übersprungen ' . $res['skipped'] . ', ' . round( microtime( true ) - $t0, 1 ) . " s)\n";
if ( ! $res['ok'] ) {
	exit( 3 );
}
if ( $confirm ) {
	echo '  Vorlagen nachher: ' . ProductTemplateService::total() . "\n";
	foreach ( array_slice( ProductTemplateService::machine_types( 'LI' ), 0, 10, true ) as $code => $n ) {
		echo '    Liebherr ' . str_pad( $code, 6 ) . ProductTemplateService::label_type( $code ) . ": {$n}\n";
	}
} else {
	echo "  Hinweis: Mit --confirm werden die Vorlagen geschrieben (wiederholbar, keine Dubletten).\n";
}
echo "== Fertig ==\n";
