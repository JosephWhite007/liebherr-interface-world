<?php
/**
 * Liebherr Interface Solutions – minimaler XLSX-Leser (Stufe 1b, Codelisten-Import).
 *
 * Liest Tabellenblätter einer .xlsx-Datei zeilenweise als Generator – ohne Composer-Abhängigkeit (keine neue externe
 * Bibliothek, Kat. A vermieden): ZipArchive + XMLReader, damit auch das 12-MB-Blatt „CodeDetails" (≈ 49 100 Zeilen)
 * mit konstantem Speicher verarbeitet wird. Unterstützt Shared Strings, Inline-Strings und Zahlen; keine Formeln/Stile.
 * Reine PHP-Logik (WP-frei, unit-testbar).
 *
 * @package Liebherr\InterfaceWorld\SalesInterface
 * @since   0.1.0-alpha.149
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\SalesInterface;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class XlsxReader {

	private \ZipArchive $zip;
	/** @var array<string,string> Blattname ⇒ Pfad im Archiv */
	private array $sheets = [];
	/** @var string[]|null */
	private ?array $shared = null;

	public function __construct( string $path ) {
		if ( ! is_readable( $path ) ) {
			throw new \RuntimeException( 'Datei nicht lesbar: ' . $path );
		}
		$this->zip = new \ZipArchive();
		if ( true !== $this->zip->open( $path, \ZipArchive::RDONLY ) ) {
			throw new \RuntimeException( 'Kein gültiges XLSX-Archiv: ' . $path );
		}
		$this->sheets = $this->read_sheet_map();
	}

	public function __destruct() {
		$this->zip->close();
	}

	/** @return string[] Blattnamen in Dateireihenfolge. */
	public function sheet_names(): array {
		return array_keys( $this->sheets );
	}

	/**
	 * Zeilen eines Blatts: Spaltenbuchstabe ⇒ Wert (nur gefüllte Zellen); leere Zeilen werden übersprungen.
	 *
	 * @return \Generator<int,array<string,string>>
	 */
	public function rows( string $sheet ): \Generator {
		$path = $this->sheets[ $sheet ] ?? null;
		if ( null === $path ) {
			throw new \RuntimeException( 'Blatt nicht gefunden: ' . $sheet );
		}
		$this->shared ??= $this->read_shared_strings();
		$xml  = $this->open_xml( $path );
		$more = $xml->read();
		while ( $more ) {
			if ( \XMLReader::ELEMENT !== $xml->nodeType || 'row' !== $xml->localName ) {
				$more = $xml->read();
				continue;
			}
			$node = $xml->expand();
			if ( ! $node instanceof \DOMElement ) {
				continue;
			}
			$row = [];
			foreach ( $node->getElementsByTagName( 'c' ) as $c ) {
				/** @var \DOMElement $c */
				$ref = (string) $c->getAttribute( 'r' );
				$col = (string) preg_replace( '/\d+$/', '', $ref );
				$val = $this->cell_value( $c );
				if ( '' !== $val ) {
					$row[ $col ] = $val;
				}
			}
			if ( [] !== $row ) {
				yield $row;
			}
			$more = $xml->next(); // zum nächsten Geschwister-<row>; kein zusätzliches read(), sonst wird jede zweite Zeile übersprungen
		}
		$xml->close();
	}

	private function cell_value( \DOMElement $c ): string {
		$type = (string) $c->getAttribute( 't' );
		if ( 'inlineStr' === $type ) {
			return trim( $c->textContent );
		}
		$v = $c->getElementsByTagName( 'v' )->item( 0 );
		if ( null === $v ) {
			return '';
		}
		$raw = (string) $v->textContent;
		if ( 's' === $type ) {
			return trim( $this->shared[ (int) $raw ] ?? '' );
		}
		return trim( $raw );
	}

	/** @return array<string,string> */
	private function read_sheet_map(): array {
		$wb = $this->zip->getFromName( 'xl/workbook.xml' );
		$rl = $this->zip->getFromName( 'xl/_rels/workbook.xml.rels' );
		if ( false === $wb || false === $rl ) {
			throw new \RuntimeException( 'workbook.xml fehlt.' );
		}
		$targets = [];
		foreach ( ( new \SimpleXMLElement( $rl ) )->Relationship as $r ) {
			$targets[ (string) $r['Id'] ] = 'xl/' . ltrim( str_replace( '/xl/', '', (string) $r['Target'] ), '/' );
		}
		$map = [];
		$doc = new \SimpleXMLElement( $wb );
		foreach ( $doc->sheets->sheet as $s ) {
			$rid = (string) $s->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' )['id'];
			if ( isset( $targets[ $rid ] ) ) {
				$map[ (string) $s['name'] ] = $targets[ $rid ];
			}
		}
		return $map;
	}

	/** @return string[] */
	private function read_shared_strings(): array {
		$idx = $this->zip->locateName( 'xl/sharedStrings.xml' );
		if ( false === $idx ) {
			return [];
		}
		$out = [];
		$xml = $this->open_xml( 'xl/sharedStrings.xml' );
		$more = $xml->read();
		while ( $more ) {
			if ( \XMLReader::ELEMENT === $xml->nodeType && 'si' === $xml->localName ) {
				$node  = $xml->expand();
				$out[] = $node instanceof \DOMNode ? (string) $node->textContent : '';
				$more  = $xml->next();
				continue;
			}
			$more = $xml->read();
		}
		$xml->close();
		return $out;
	}

	private function open_xml( string $entry ): \XMLReader {
		$xml = new \XMLReader();
		$uri = 'zip://' . $this->zip->filename . '#' . $entry;
		if ( ! @$xml->open( $uri, null, LIBXML_NONET ) ) {
			throw new \RuntimeException( 'Archiv-Eintrag nicht lesbar: ' . $entry );
		}
		return $xml;
	}
}
