<?php
/**
 * Liebherr Intelligence World – minimaler PDF-Generator (Backlog A10, §5.5/§8).
 *
 * Erzeugt ohne Fremdbibliothek ein gültiges, mehrseitiges PDF aus Textzeilen (Core-Font Helvetica,
 * WinAnsi-nah). Nicht-ASCII (Umlaute, €, ·) wird ASCII-nah transliteriert, um Encoding-Probleme zu vermeiden.
 * Rein und ohne WordPress unit-testbar (`from_lines()` liefert die PDF-Bytes). Für das Nutzungs-/Kostenprotokoll
 * als serverseitiger Download (Alternative zum Browser-Druck).
 *
 * @package Liebherr\InterfaceWorld\IntelligenceWorld
 * @since   0.1.0-alpha.71
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\IntelligenceWorld;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class PdfDocument {

	private const PAGE_W = 595.0; // A4 Punkte
	private const PAGE_H = 842.0;
	private const MARGIN = 50.0;
	private const LEAD   = 14.0;  // Zeilenhöhe
	private const SIZE   = 10.0;

	/**
	 * Erzeugt ein PDF aus Titel + Textzeilen (automatischer Seitenumbruch).
	 *
	 * @param string            $title
	 * @param array<int,string> $lines
	 */
	public static function from_lines( string $title, array $lines ): string {
		$all = array_merge( [ strtoupper( $title ), '' ], array_map( 'strval', $lines ) );

		$per_page = (int) floor( ( self::PAGE_H - 2 * self::MARGIN ) / self::LEAD );
		$per_page = max( 1, $per_page );
		$pages    = array_chunk( $all, $per_page );
		if ( [] === $pages ) { $pages = [ [ '' ] ]; }

		// Objektnummern: 1=Catalog, 2=Pages, 3=Font, dann je Seite Page+Content.
		$font_obj  = 3;
		$objects   = [];
		$page_objs = [];
		$obj_num   = 4;

		foreach ( $pages as $chunk ) {
			$page_obj    = $obj_num++;
			$content_obj = $obj_num++;
			$page_objs[] = $page_obj;

			$stream  = "BT /F1 " . self::num( self::SIZE ) . " Tf " . self::num( self::MARGIN ) . ' '
				. self::num( self::PAGE_H - self::MARGIN ) . " Td " . self::num( self::LEAD ) . " TL\n";
			foreach ( $chunk as $line ) {
				$stream .= '(' . self::esc( self::translit( (string) $line ) ) . ") Tj T*\n";
			}
			$stream .= "ET";

			$objects[ $content_obj ] = "<< /Length " . strlen( $stream ) . " >>\nstream\n" . $stream . "\nendstream";
			$objects[ $page_obj ]    = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " . self::num( self::PAGE_W ) . ' ' . self::num( self::PAGE_H )
				. "] /Resources << /Font << /F1 {$font_obj} 0 R >> >> /Contents {$content_obj} 0 R >>";
		}

		$kids = implode( ' ', array_map( static fn( int $n ): string => "{$n} 0 R", $page_objs ) );
		$objects[1] = "<< /Type /Catalog /Pages 2 0 R >>";
		$objects[2] = "<< /Type /Pages /Count " . count( $page_objs ) . " /Kids [ {$kids} ] >>";
		$objects[3] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>";

		ksort( $objects );
		$pdf     = "%PDF-1.4\n";
		$offsets = [];
		foreach ( $objects as $num => $body ) {
			$offsets[ $num ] = strlen( $pdf );
			$pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
		}
		$xref_pos = strlen( $pdf );
		$count    = count( $objects ) + 1;
		$pdf     .= "xref\n0 {$count}\n0000000000 65535 f \n";
		for ( $i = 1; $i < $count; $i++ ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offsets[ $i ] ?? 0 );
		}
		$pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xref_pos}\n%%EOF";
		return $pdf;
	}

	private static function num( float $v ): string {
		return rtrim( rtrim( number_format( $v, 2, '.', '' ), '0' ), '.' );
	}

	/** PDF-String-Escape für ( ) \\. */
	private static function esc( string $s ): string {
		return str_replace( [ '\\', '(', ')', "\r", "\n" ], [ '\\\\', '\\(', '\\)', '', '' ], $s );
	}

	/** Nicht-ASCII ASCII-nah ersetzen (robuste Anzeige ohne Font-Embedding). */
	private static function translit( string $s ): string {
		$map = [
			'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss',
			'€' => 'EUR', '·' => '-', '–' => '-', '—' => '-', '„' => '"', '“' => '"', '”' => '"',
			'’' => "'", '‘' => "'", '→' => '->', '/' => '/', '…' => '...',
		];
		$s = strtr( $s, $map );
		// Verbleibende Nicht-ASCII entfernen.
		return (string) preg_replace( '/[^\x20-\x7E]/', '', $s );
	}
}
