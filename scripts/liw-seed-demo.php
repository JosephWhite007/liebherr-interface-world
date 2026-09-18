<?php
/**
 * Liebherr Interface Solutions – Demo-/Seed-Daten (§33, KEINE echten Geschäftsdaten).
 *
 * Legt beispielhafte, klar als DEMO gekennzeichnete Schnittstellen, Verbindungen und
 * Simulationswelten/-szenarien an, damit die Boards und die Landingpage ohne echte Daten
 * vorführbar sind (Nichtziel §4: keine produktiven Daten in Demo/Sandbox). Idempotent:
 * vorhandene DEMO-Einträge werden nicht doppelt angelegt.
 *
 * Terminal-Regel (CLAUDE.md): Ausführung im Dev-Container durch Joseph:
 *   docker exec araliya_wordpress php \
 *     /var/www/html/wp-content/plugins/liebherr-interface-world/scripts/liw-seed-demo.php --confirm
 *
 * @package Liebherr\InterfaceWorld
 * @since   0.1.0-alpha.34
 */

declare( strict_types = 1 );

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "Nur für die Kommandozeile.\n" );
	exit( 2 );
}
if ( ! in_array( '--confirm', $argv, true ) ) {
	fwrite( STDOUT, "Trockenlauf-Schutz: Bitte mit --confirm ausführen. Es werden DEMO-Daten (keine echten) angelegt.\n" );
	exit( 0 );
}

require_once dirname( __DIR__, 4 ) . '/wp-load.php';

use Liebherr\InterfaceWorld\Connection\ConnectionService;
use Liebherr\InterfaceWorld\Interfaces\InterfaceCatalogService;
use Liebherr\InterfaceWorld\Simulation\SimulationService;

if ( ! class_exists( InterfaceCatalogService::class ) ) {
	fwrite( STDERR, "Plugin liebherr-interface-world ist nicht aktiv.\n" );
	exit( 1 );
}

$created = 0;
$skipped = 0;
$failed  = 0;

echo "== Liebherr Interface World – DEMO-Daten (keine echten Geschaeftsdaten) ==\n";

// ── Schnittstellen (Codes mit DEMO- Präfix, idempotent über vorhandene Codes) ──
// Codes werden vom Service kleingeschrieben gespeichert → Vergleich case-insensitiv.
$existing_codes = array_map( static fn( array $r ): string => strtolower( (string) ( $r['code'] ?? '' ) ), InterfaceCatalogService::get_all() );
$demo_interfaces = [
	[ 'code' => 'DEMO-ERP-IN', 'name' => 'DEMO Auftragsimport (ERP → Zentral)', 'direction' => 'inbound', 'protocol' => 'REST/JSON' ],
	[ 'code' => 'DEMO-CFG-BI', 'name' => 'DEMO Konfigurationsabgleich', 'direction' => 'bidirectional', 'protocol' => 'REST/JSON' ],
	[ 'code' => 'DEMO-INV-OUT', 'name' => 'DEMO Rechnungsexport (Zentral → Händler)', 'direction' => 'outbound', 'protocol' => 'sFTP/CSV' ],
];
foreach ( $demo_interfaces as $iface ) {
	if ( in_array( strtolower( $iface['code'] ), $existing_codes, true ) ) {
		echo "  [übersprungen] Schnittstelle {$iface['code']}\n";
		$skipped++;
		continue;
	}
	$res = InterfaceCatalogService::create( $iface, 0 );
	if ( is_wp_error( $res ) ) {
		echo "  [FEHLER] Schnittstelle {$iface['code']}: " . $res->get_error_message() . "\n";
		$failed++;
	} else {
		echo "  [angelegt #{$res}] Schnittstelle {$iface['code']}\n";
		$created++;
	}
}

// ── Verbindungen (Regionen; idempotent über Region+Typ) ──
$existing_conns = array_map(
	static fn( array $r ): string => (string) ( $r['region'] ?? '' ) . '|' . (string) ( $r['partner_type'] ?? '' ),
	ConnectionService::get_all()
);
$demo_conns = [
	[ 'region' => 'DEMO Europe', 'partner_type' => 'dealer', 'public_flag' => true ],
	[ 'region' => 'DEMO Asia-Pacific', 'partner_type' => 'supplier', 'public_flag' => true ],
	[ 'region' => 'DEMO North America', 'partner_type' => 'customer', 'public_flag' => false ],
];
foreach ( $demo_conns as $conn ) {
	if ( in_array( $conn['region'] . '|' . $conn['partner_type'], $existing_conns, true ) ) {
		echo "  [übersprungen] Verbindung {$conn['region']}\n";
		$skipped++;
		continue;
	}
	$res = ConnectionService::create( $conn, 0 );
	if ( is_wp_error( $res ) ) {
		echo "  [FEHLER] Verbindung {$conn['region']}: " . $res->get_error_message() . "\n";
		$failed++;
	} else {
		echo "  [angelegt #{$res}] Verbindung {$conn['region']}\n";
		$created++;
	}
}

// ── Simulationswelt + Szenarien (idempotent über Weltnamen) ──
$existing_worlds = array_map( static fn( array $r ): string => (string) ( $r['name'] ?? '' ), SimulationService::get_worlds() );
$world_name = 'DEMO Magic Cube Sandbox';
if ( in_array( $world_name, $existing_worlds, true ) ) {
	echo "  [übersprungen] Simulationswelt {$world_name}\n";
	$skipped++;
} else {
	$world_id = SimulationService::create_world( [ 'name' => $world_name, 'scope' => 'DEMO Interface-Verifizierung', 'version' => '1.0.0' ], 0 );
	if ( is_wp_error( $world_id ) ) {
		echo "  [FEHLER] Simulationswelt: " . $world_id->get_error_message() . "\n";
		$failed++;
	} else {
		echo "  [angelegt #{$world_id}] Simulationswelt {$world_name}\n";
		$created++;
		foreach ( [
			[ 'category' => 'DEMO Feld-Mapping', 'expected_result' => 'Alle Pflichtfelder korrekt übertragen.' ],
			[ 'category' => 'DEMO Fehlerfall', 'expected_result' => 'Ungültige Datensätze werden abgewiesen und protokolliert.' ],
			[ 'category' => 'DEMO Lasttest', 'expected_result' => 'Durchsatz stabil unter Ziel.last.' ],
		] as $sc ) {
			$sc['world_id'] = (int) $world_id;
			$res = SimulationService::add_scenario( $sc, 0 );
			if ( is_wp_error( $res ) ) {
				echo "    [FEHLER] Szenario {$sc['category']}: " . $res->get_error_message() . "\n";
				$failed++;
			} else {
				echo "    [angelegt #{$res}] Szenario {$sc['category']}\n";
				$created++;
			}
		}
	}
}

echo "\n" . "Ergebnis: {$created} angelegt, {$skipped} uebersprungen, {$failed} fehlgeschlagen." . "\n";
echo 'DEMO-Daten dienen nur der Vorfuehrung (§4/§33) – keine echten Geschaeftsdaten.' . "\n";
exit( $failed > 0 ? 1 : 0 );
