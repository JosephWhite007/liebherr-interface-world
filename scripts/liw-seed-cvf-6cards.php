<?php
/**
 * Liebherr World – CVF-Board: die zwei neuen Karten simulierbar machen (§36-Folgearbeit, ADR-LIW-MYL-001).
 *
 * Verdrahtet my_liebherr und pocket_information im aktuellen Board-ENTWURF: setzt die Karten aktiv, legt je einen
 * Übergang vom Einstieg (intelligence_world) an und hängt einen First-Entry-Text auf die Seite (Return-Route =
 * Seiten-URL). Danach laufen First Entry und Rücksprung beider Karten in der Board-Simulation. Idempotent.
 * Veröffentlichen erfolgt bewusst separat über das CVF-Board (Backoffice). Kein destruktiver Eingriff.
 *
 *   docker exec araliya_wordpress php \
 *     /var/www/html/wp-content/plugins/liebherr-interface-world/scripts/liw-seed-cvf-6cards.php --confirm
 *
 * @package Liebherr\InterfaceWorld
 * @since   0.1.0-alpha.132
 */

declare( strict_types = 1 );

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "Nur für die Kommandozeile.\n" );
	exit( 2 );
}
if ( ! in_array( '--confirm', $argv, true ) ) {
	fwrite( STDOUT, "Trockenlauf-Schutz: mit --confirm ausführen. Verdrahtet my_liebherr + pocket_information im Board-Entwurf.\n" );
	exit( 0 );
}

require_once dirname( __DIR__, 4 ) . '/wp-load.php';

use Liebherr\InterfaceWorld\Cvf\BoardRepository;

if ( ! class_exists( BoardRepository::class ) ) {
	fwrite( STDERR, "Plugin liebherr-interface-world ist nicht aktiv.\n" );
	exit( 1 );
}

echo "== CVF-Board: neue Karten verdrahten (my_liebherr + pocket_information) ==\n";

$draft = BoardRepository::ensure_draft( 1 );
BoardRepository::wire_module_card( $draft, 'my_liebherr', 'Willkommen in My Liebherr', 'Ihr persönlicher Bereich: Wallet, Dreams, Adventures, Kontakte und Einstellungen.' );
BoardRepository::wire_module_card( $draft, 'pocket_information', 'Pocket Information', 'Ihre personenbezogenen Kurzinfos: Briefing, Alerts und Aufgaben.' );

$areas = BoardRepository::areas( $draft );
$edges = BoardRepository::edges( $draft );
echo "  Entwurf #{$draft}: " . count( $areas ) . " Bereiche, " . count( $edges ) . " Übergänge.\n";
echo "  Hinweis: Die neuen Karten sind jetzt im Entwurf aktiv + simulierbar. Zum Scharfschalten im\n";
echo "  Backoffice 'CVF Board' veröffentlichen (und Runtime-Flag liw_cvf_board_enabled setzen).\n";
