<?php
/**
 * Liebherr World – Codelisten aus der LIVISION-Auswahlliste importieren (Stufe 1b, ADR-LIW-SALES-001).
 *
 * Liest `LIVISION_Auswahlliste.xlsx` (Blätter CodeDetails / LanguageDetails / Codelist) und schreibt die ≈ 49 100 Zeilen
 * idempotent (Upsert je Sprache+Liste+Code) in `liw_codelist`; Listen-Metadaten und Sprachen in Optionen.
 * Ohne --confirm: Trockenlauf (zählt nur). Datei-Standardpfad: data/LIVISION_Auswahlliste.xlsx im Plugin (git-ignoriert).
 *
 *   docker exec araliya_wordpress php \
 *     /var/www/html/wp-content/plugins/liebherr-interface-world/scripts/liw-import-livision-codelists.php --confirm
 *   Optional: --file=/pfad/zur/Auswahlliste.xlsx
 *
 * @package Liebherr\InterfaceWorld
 * @since   0.1.0-alpha.149
 */

declare( strict_types = 1 );

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "Nur für die Kommandozeile.\n" );
	exit( 2 );
}

require_once dirname( __DIR__, 4 ) . '/wp-load.php';

use Liebherr\InterfaceWorld\SalesInterface\CodeListSchema;
use Liebherr\InterfaceWorld\SalesInterface\CodeListService;

if ( ! class_exists( CodeListService::class ) ) {
	fwrite( STDERR, "Plugin liebherr-interface-world ist nicht aktiv.\n" );
	exit( 1 );
}

$file = dirname( __DIR__ ) . '/data/LIVISION_Auswahlliste.xlsx';
foreach ( $argv as $arg ) {
	if ( preg_match( '/^--file=(.+)$/', $arg, $m ) ) {
		$file = $m[1];
	}
}
$confirm = in_array( '--confirm', $argv, true );

echo "== LIVISION-Codelisten importieren ==\n";
echo "  Datei:   {$file}\n";
if ( ! is_readable( $file ) ) {
	fwrite( STDERR, "  Datei nicht lesbar. Auswahlliste nach data/ kopieren oder --file= angeben.\n" );
	exit( 1 );
}
echo '  Vorher:  ' . number_format( CodeListService::total(), 0, ',', '.' ) . " Zeilen in liw_codelist\n";
echo '  Modus:   ' . ( $confirm ? 'Import (Upsert)' : 'Trockenlauf – nichts wird geschrieben' ) . "\n";

CodeListSchema::create_table();
$t0    = microtime( true );
$stats = CodeListService::import( $file, ! $confirm, static function ( int $n ): void {
	echo "    … " . number_format( $n, 0, ',', '.' ) . " Zeilen\n";
} );
$sec = round( microtime( true ) - $t0, 1 );

echo "\n  Zeilen gelesen:     " . number_format( $stats['rows'], 0, ',', '.' ) . "\n";
echo '  Zeilen geschrieben: ' . number_format( $stats['written'], 0, ',', '.' ) . ( $confirm ? '' : ' (Trockenlauf)' ) . "\n";
echo "  Übersprungen:       {$stats['skipped']}\n";
echo "  Listen:             {$stats['lists']}   Sprachen: {$stats['languages']}   Dauer: {$sec} s\n";
echo '  Nachher: ' . number_format( CodeListService::total(), 0, ',', '.' ) . " Zeilen in liw_codelist\n";
if ( $confirm ) {
	foreach ( CodeListService::lists() as $code => $l ) {
		echo '    ' . str_pad( $code, 5 ) . str_pad( $l['name'], 36 ) . str_pad( (string) $l['rows'], 6, ' ', STR_PAD_LEFT ) . " Codes (DE)\n";
	}
}
echo "\nFertig.\n";
