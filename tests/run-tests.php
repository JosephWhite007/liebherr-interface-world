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
