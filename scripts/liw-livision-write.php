<?php
/**
 * Liebherr World – Dealer Interface API: Schreibaufrufe gegen die Testumgebung (Stufe 1d, Kunde/Kontakt).
 *
 * Ohne --confirm: **Trockenlauf** – zeigt Händler, Vorbedingungen und den vollständigen Body, sendet nichts.
 * Mit --confirm: genau EIN Schritt. Schritte (--step=…):
 *   customer         POST /customer   – nächsten „Sunnyside N"-Testkunden anlegen (Konvention JW 24.10.2026)
 *   contact          POST /contact    – Alice (--male: Bob) zum zuletzt angelegten Testkunden
 *   customer-update  PUT  /customer   – Note/Name2 des Testkunden ändern
 *   contact-update   PUT  /contact    – Function1 des Testkontakts ändern
 * Optionen: --dealer=<kürzel> (Standard: aktiver Händler), --n=<Nr> (Sunnyside-Nummer, Standard: nächste freie).
 * Angelegte IDs werden je Händler in Option `liw_livision_test_records` gemerkt (keine Dubletten bei Wiederholung).
 * Keys werden nie ausgegeben; jeder Lauf landet im Audit.
 *
 *   docker exec araliya_wordpress php \
 *     /var/www/html/wp-content/plugins/liebherr-interface-world/scripts/liw-livision-write.php --step=customer
 *
 * @package Liebherr\InterfaceWorld
 * @since   0.1.0-alpha.148
 */

declare( strict_types = 1 );

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "Nur für die Kommandozeile.\n" );
	exit( 2 );
}

require_once dirname( __DIR__, 4 ) . '/wp-load.php';

use Liebherr\InterfaceWorld\SalesInterface\DealerApiClient;
use Liebherr\InterfaceWorld\SalesInterface\DealerRepository;
use Liebherr\InterfaceWorld\SalesInterface\TestDataFactory;

if ( ! class_exists( DealerApiClient::class ) ) {
	fwrite( STDERR, "Plugin liebherr-interface-world ist nicht aktiv.\n" );
	exit( 1 );
}

const LIW_OPT_RECORDS = 'liw_livision_test_records';

$opts = [ 'step' => '', 'dealer' => '', 'n' => '' ];
foreach ( $argv as $arg ) {
	if ( preg_match( '/^--([a-z]+)=(.*)$/', $arg, $m ) && array_key_exists( $m[1], $opts ) ) {
		$opts[ $m[1] ] = $m[2];
	}
}
$confirm = in_array( '--confirm', $argv, true );
$male    = in_array( '--male', $argv, true );
$dealer  = '' !== $opts['dealer'] ? sanitize_key( $opts['dealer'] ) : DealerRepository::active_code();
$step    = $opts['step'];

$records_all = get_option( LIW_OPT_RECORDS, [] );
$records_all = is_array( $records_all ) ? $records_all : [];
$rec         = is_array( $records_all[ $dealer ] ?? null ) ? $records_all[ $dealer ] : [];

echo "== Liebherr Dealer Interface – Schreibaufruf (Testumgebung) ==\n";
echo "  Händler:   {$dealer}\n";
$ready = DealerApiClient::readiness( $dealer, true );
echo '  Freigabe:  ' . ( $ready['ok'] ? 'bereit (Schreibschalter AN)' : 'blockiert (' . $ready['reason'] . ')' ) . "\n";
$creds = DealerRepository::credentials( $dealer );
$san   = (string) ( $creds['sales_account_number'] ?? '' );
echo '  SalesAccountNumber: ' . ( '' !== $san ? 'hinterlegt' : 'FEHLT – im Dealer Board eintragen' ) . "\n";
echo '  Gemerkt:   Kunde #' . ( $rec['customer_id'] ?? '–' ) . ' (' . ( $rec['customer_name'] ?? '–' ) . '), Kontakt #' . ( $rec['contact_id'] ?? '–' ) . "\n";

$n = '' !== $opts['n'] ? max( 1, (int) $opts['n'] ) : (int) ( $rec['next_n'] ?? 1 );

switch ( $step ) {
	case 'customer':
		$method = 'POST';
		$path   = '/customer';
		$body   = TestDataFactory::customer( $n, $san, (string) ( DealerRepository::find( $dealer )['country'] ?? 'DE' ) );
		break;
	case 'contact':
		$method = 'POST';
		$path   = '/contact';
		$body   = TestDataFactory::contact( (int) ( $rec['customer_id'] ?? 0 ), ! $male, (int) ( $rec['customer_n'] ?? $n ) );
		break;
	case 'customer-update':
		$method = 'PUT';
		$path   = '/customer';
		$body   = TestDataFactory::customer( (int) ( $rec['customer_n'] ?? $n ), $san, (string) ( DealerRepository::find( $dealer )['country'] ?? 'DE' ) );
		$body['LivisionCustomerId'] = (int) ( $rec['customer_id'] ?? 0 );
		$body['Name2']              = 'Test Customer ARALIYA – aktualisiert ' . gmdate( 'Y-m-d H:i' );
		break;
	case 'contact-update':
		$method = 'PUT';
		$path   = '/contact';
		$body   = TestDataFactory::contact( (int) ( $rec['customer_id'] ?? 0 ), ! $male, (int) ( $rec['customer_n'] ?? $n ) );
		$body['LivisionContactId'] = (int) ( $rec['contact_id'] ?? 0 );
		$body['Function1']         = 'ALEI'; // FN-Code Abteilungsleiter – Funktion ist ein Code, kein Freitext (Liebherr 27.10.2026)
		break;
	default:
		echo "\nBitte --step=customer|contact|customer-update|contact-update angeben.\n";
		exit( 2 );
}

echo "\n  Aufruf:    {$method} " . DealerApiClient::BASE_TEST . "{$path}\n";
echo "  Body:\n";
foreach ( $body as $k => $v ) {
	echo '    ' . str_pad( $k, 20 ) . ' = ' . ( '' === (string) $v ? '(leer)' : (string) $v ) . "\n";
}
$missing = DealerApiClient::missing_fields( "{$method} {$path}", $body );
if ( [] !== $missing ) {
	echo "\n  Pflichtfelder fehlen: " . implode( ', ', $missing ) . "\n";
}

$dry = DealerApiClient::send( $method, $path, $body, 0, $dealer, true );
if ( ! $confirm ) {
	echo "\nTrockenlauf – nichts gesendet. " . ( $dry['ok'] ? 'Mit --confirm wird genau dieser Aufruf ausgeführt.' : 'Blockiert: ' . $dry['error'] ) . "\n";
	exit( $dry['ok'] ? 0 : 3 );
}
if ( ! $dry['ok'] ) {
	echo "\nAbbruch: {$dry['error']}\n";
	exit( 3 );
}

$res = DealerApiClient::send( $method, $path, $body, 0, $dealer, false );
echo "\n  HTTP:      {$res['status']}  ({$res['duration_ms']} ms)\n";
if ( ! $res['ok'] ) {
	echo "  Ergebnis:  FEHLER – {$res['error']}\n";
	exit( 4 );
}
$data = $res['data'];
echo '  Antwort:   ' . substr( (string) wp_json_encode( $data, JSON_UNESCAPED_UNICODE ), 0, 600 ) . "\n";

// IDs aus der Antwort merken (Liebherr-Antwortform wird beim ersten Echtlauf verifiziert; mehrere Schlüssel probiert).
$find_id = static function ( $d, array $keys ): int {
	$stack = [ $d ];
	while ( [] !== $stack ) {
		$cur = array_pop( $stack );
		if ( ! is_array( $cur ) ) {
			continue;
		}
		foreach ( $keys as $k ) {
			if ( isset( $cur[ $k ] ) && is_numeric( $cur[ $k ] ) ) {
				return (int) $cur[ $k ];
			}
		}
		foreach ( $cur as $v ) {
			if ( is_array( $v ) ) {
				$stack[] = $v;
			}
		}
	}
	return 0;
};
if ( 'customer' === $step ) {
	$id = $find_id( $data, [ 'LivisionCustomerId', 'Kunde_ID', 'CustomerId', 'Id', 'ID', 'id' ] );
	$rec = [ 'customer_id' => $id, 'customer_n' => $n, 'customer_name' => $body['Name1'], 'next_n' => $n + 1, 'contact_id' => 0 ];
	echo '  Gemerkt:   LivisionCustomerId ' . ( $id > 0 ? $id : 'NICHT erkannt – Antwort prüfen' ) . "\n";
} elseif ( 'contact' === $step ) {
	$id = $find_id( $data, [ 'LivisionContactId', 'Ansprechpartner_ID', 'ContactId', 'Id', 'ID', 'id' ] );
	$rec['contact_id'] = $id;
	echo '  Gemerkt:   LivisionContactId ' . ( $id > 0 ? $id : 'NICHT erkannt – Antwort prüfen' ) . "\n";
}
$records_all[ $dealer ] = $rec;
update_option( LIW_OPT_RECORDS, $records_all, false );
echo "\nFertig. Lauf in Option liw_livision_last_write und im Audit Board vermerkt.\n";
