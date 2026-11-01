<?php
/**
 * Liebherr Interface Solutions – XLSX-Leser (Stufe 1b, Codelisten-Import) als Brücke zum Core-Leser.
 *
 * Seit alpha.180 (IMP-038) keine eigene ZipArchive/XMLReader-Implementierung mehr: Blattnamen und Zeilen liefert
 * `Araliya\Platform\Core\Modules\Addressbook\SpreadsheetReader` (Core alpha.852: `sheet_names()`, `cells()`). Die Schnittstelle
 * dieser Klasse (`sheet_names()`, `rows( $blatt )` = Spaltenbuchstabe ⇒ Wert, nur gefüllte Zellen) bleibt, damit
 * `CodeListService` und das Import-Skript unverändert arbeiten. Ohne Core-Plugin: sprechende Ausnahme.
 *
 * @package Liebherr\InterfaceWorld\SalesInterface
 * @since   0.1.0-alpha.149
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\SalesInterface;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class XlsxReader {

	public const CORE_READER = 'Araliya\\Platform\\Core\\Modules\\Addressbook\\SpreadsheetReader';

	/** @var object Core-Leser (SpreadsheetReader) */
	private object $reader;

	public function __construct( string $path ) {
		if ( ! class_exists( self::CORE_READER ) ) {
			throw new \RuntimeException( 'ARALIYA Platform Core fehlt – der XLSX-Leser kommt aus dem Core (IMP-038).' );
		}
		$core         = self::CORE_READER;
		$this->reader = new $core( $path ); // wirft RuntimeException bei unlesbarer Datei oder falschem Typ
	}

	/** @return string[] Blattnamen in Dateireihenfolge. */
	public function sheet_names(): array {
		return $this->reader->sheet_names();
	}

	/**
	 * Zeilen eines Blatts: Spaltenbuchstabe ⇒ Wert (nur gefüllte Zellen); leere Zeilen werden übersprungen.
	 *
	 * @return \Generator<int,array<string,string>>
	 */
	public function rows( string $sheet ): \Generator {
		yield from $this->reader->cells( $sheet );
	}
}
