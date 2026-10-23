<?php
/**
 * Liebherr World – Dealer Interface API: Verbindungsprobe gegen die Testumgebung (Stufe 1c, nur lesend).
 *
 * Ohne --confirm: Trockenlauf (prüft nur Env-Keys und Instanzfreigabe, sendet nichts).
 * Mit --confirm: ein GET gegen https://livision.azure-api.net/crm/v3 – Standard `/tab-text?CodeList=AH&Language=0`
 * (Anrede, Deutsch). Andere lesende Endpunkte: --endpoint=/product-list, --endpoint=/tab-text --CodeList=1B --Language=2 …
 * Ausgabe: Status, Dauer, Zeilenzahl, die ersten Zeilen (nur Codelisten-/Produktdaten, keine Personen). Keys werden nie
 * ausgegeben. Jeder Lauf landet in Option `liw_livision_last_probe` und im Audit.
 *
 *   docker exec araliya_wordpress php \
 *     /var/www/html/wp-content/plugins/liebherr-interface-world/scripts/liw-livision-probe.php --confirm
 *
 * @package Liebherr\InterfaceWorld
 * @since   0.1.0-alpha.147
 */

declare( strict_types = 1 );

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "Nur für die Kommandozeile.\n" );
	exit( 2 );
}

require_once dirname( __DIR__, 4 ) . '/wp-load.php';

use Liebherr\InterfaceWorld\CoreBridge\InstanceBridge;
use Liebherr\InterfaceWorld\SalesInterface\DealerApiClient;

if ( ! class_exists( DealerApiClient::class ) ) {
	fwrite( STDERR, "Plugin liebherr-interface-world ist nicht aktiv.\n" );
	exit( 1 );
}

$opts     = [];
$endpoint = '/tab-text';
foreach ( $argv as $arg ) {
	if ( preg_match( '/^--([A-Za-z]+)=(.*)$/', $arg, $m ) ) {
		if ( 'endpoint' === $m[1] ) {
			$endpoint = $m[2];
		} else {
			$opts[ $m[1] ] = $m[2];
		}
	}
}
if ( '/tab-text' === $endpoint && [] === $opts ) {
	$opts = [ 'CodeList' => 'AH', 'Language' => '0' ];
}

echo "== Liebherr Dealer Interface – Verbindungsprobe (Testumgebung, lesend) ==\n";
echo "  Basis:     " . DealerApiClient::BASE_TEST . "\n";
echo "  Endpunkt:  GET {$endpoint}" . ( [] !== $opts ? ' ?' . http_build_query( $opts ) : '' ) . "\n";
echo '  Key:       ' . ( InstanceBridge::env_set( InstanceBridge::ENV_KEY_TEST ) ? 'hinterlegt' : 'FEHLT (' . InstanceBridge::ENV_KEY_TEST . ')' ) . "\n";
echo '  UserEmail: ' . ( InstanceBridge::env_set( InstanceBridge::ENV_USER_EMAIL ) ? 'hinterlegt' : 'FEHLT (' . InstanceBridge::ENV_USER_EMAIL . ')' ) . "\n";
$ready = DealerApiClient::readiness();
echo '  Freigabe:  ' . ( $ready['ok'] ? 'bereit' : 'blockiert (' . $ready['reason'] . ')' ) . "\n";

if ( ! in_array( '--confirm', $argv, true ) ) {
	echo "\nTrockenlauf – nichts gesendet. Mit --confirm wird genau ein GET ausgeführt.\n";
	exit( $ready['ok'] ? 0 : 3 );
}
if ( ! $ready['ok'] ) {
	echo "\nAbbruch: Vorbedingungen nicht erfüllt.\n";
	exit( 3 );
}

$res = DealerApiClient::get( $endpoint, $opts );
echo "\n  HTTP:      {$res['status']}  ({$res['duration_ms']} ms)\n";
if ( ! $res['ok'] ) {
	echo "  Ergebnis:  FEHLER – {$res['error']}\n";
	echo "  Hinweis:   401/403 = Key oder UserEmail nicht akzeptiert · 404 = Pfad · transport_error = Netz/DNS/Timeout.\n";
	exit( 4 );
}
$rows = $res['rows'];
echo '  Ergebnis:  OK – ' . count( $rows ) . " Element(e)\n";
$shown = 0;
foreach ( $rows as $row ) {
	if ( $shown++ >= 10 ) {
		echo "  …\n";
		break;
	}
	echo '    ' . ( is_scalar( $row ) ? (string) $row : wp_json_encode( $row, JSON_UNESCAPED_UNICODE ) ) . "\n";
}
echo "\nFertig. Lauf in Option liw_livision_last_probe und im Audit Board vermerkt.\n";
