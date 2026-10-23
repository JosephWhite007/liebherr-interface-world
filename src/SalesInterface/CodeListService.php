<?php
/**
 * Liebherr Interface Solutions – Codelisten: Import und Abfrage (Stufe 1b).
 *
 * Import (CLI): liest die LIVISION-Auswahlliste (Blätter „CodeDetails" = Zeilen, „LanguageDetails" = Sprachen,
 * „Codelist" = Listen-Metadaten) und schreibt sie idempotent (Upsert) in `liw_codelist` sowie die Metadaten in Optionen.
 * Abfrage (Seite/Board): Listen mit Zeilenzahl, Sprachen, Suche nach Code oder Text je Liste und Sprache, Lookup eines
 * Codes. Reine Lesezugriffe sind prepared; keine Personendaten enthalten.
 *
 * @package Liebherr\InterfaceWorld\SalesInterface
 * @since   0.1.0-alpha.149
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\SalesInterface;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class CodeListService {

	/**
	 * Die Blätter werden an ihrer Kopfzeile erkannt, nicht am Namen: In der gelieferten Datei (Okt. 2026) trägt das
	 * Zeilenblatt den Namen „Codelist" und das Metadatenblatt „CodeDetails" – entgegen der Erwartung aus den Namen.
	 */
	public const HEADER_ROWS  = [ 'A' => 'Language', 'B' => 'CodeList' ];
	public const HEADER_META  = [ 'A' => 'Codeliste' ];
	public const HEADER_LANGS = [ 'A' => 'Language', 'B' => '' ];
	public const BATCH       = 500;
	public const MAX_RESULTS = 200;

	/** Spalten des Blatts „CodeDetails" (Kopfzeile: Language, CodeList, Description, Code, additionalValue). */
	private const COLS = [ 'A' => 'language', 'B' => 'code_list', 'C' => 'description', 'D' => 'code', 'E' => 'additional_value' ];

	/**
	 * Import aus der Auswahlliste. `$dry_run` zählt nur.
	 *
	 * @return array{rows:int,written:int,lists:int,languages:int,skipped:int}
	 */
	public static function import( string $path, bool $dry_run, ?callable $progress = null ): array {
		global $wpdb;
		$reader = new XlsxReader( $path );
		$sheets = self::detect_sheets( $reader );

		$langs = [];
		foreach ( $reader->rows( $sheets['langs'] ) as $r ) {
			if ( isset( $r['A'], $r['B'] ) && is_numeric( $r['A'] ) ) {
				$langs[ (int) $r['A'] ] = sanitize_text_field( $r['B'] );
			}
		}
		$meta = [];
		foreach ( $reader->rows( $sheets['meta'] ) as $r ) {
			if ( isset( $r['A'], $r['B'] ) && 'Codeliste' !== $r['A'] ) {
				$meta[ sanitize_text_field( $r['A'] ) ] = [ 'name' => sanitize_text_field( $r['B'] ), 'entity' => sanitize_text_field( $r['C'] ?? '' ) ];
			}
		}

		$stats = [ 'rows' => 0, 'written' => 0, 'lists' => 0, 'languages' => count( $langs ), 'skipped' => 0 ];
		$seen  = [];
		$batch = [];
		$flush = static function () use ( &$batch, &$stats, $wpdb, $dry_run ): void {
			if ( [] === $batch ) {
				return;
			}
			if ( ! $dry_run ) {
				$table = CodeListSchema::table_name();
				$place = implode( ',', array_fill( 0, count( $batch ), '(%d,%s,%s,%s,%s)' ) );
				$args  = [];
				foreach ( $batch as $b ) {
					array_push( $args, $b['language'], $b['code_list'], $b['code'], $b['description'], $b['additional_value'] );
				}
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Platzhalter oben erzeugt, Werte via prepare().
				$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (language, code_list, code, description, additional_value) VALUES {$place} ON DUPLICATE KEY UPDATE description = VALUES(description), additional_value = VALUES(additional_value)", $args ) );
			}
			$stats['written'] += count( $batch );
			$batch             = [];
		};

		$first = true;
		foreach ( $reader->rows( $sheets['rows'] ) as $r ) {
			if ( $first ) { // Kopfzeile
				$first = false;
				if ( 'Language' === ( $r['A'] ?? '' ) ) {
					continue;
				}
			}
			$row = [];
			foreach ( self::COLS as $col => $field ) {
				$row[ $field ] = (string) ( $r[ $col ] ?? '' );
			}
			if ( ! is_numeric( $row['language'] ) || '' === $row['code_list'] || '' === $row['code'] ) {
				$stats['skipped']++;
				continue;
			}
			$stats['rows']++;
			$seen[ $row['code_list'] ] = true;
			$batch[] = [
				'language'         => (int) $row['language'],
				'code_list'        => substr( sanitize_text_field( $row['code_list'] ), 0, 8 ),
				'code'             => substr( sanitize_text_field( $row['code'] ), 0, 40 ),
				'description'      => substr( sanitize_text_field( $row['description'] ), 0, 255 ),
				'additional_value' => substr( sanitize_text_field( $row['additional_value'] ), 0, 64 ),
			];
			if ( count( $batch ) >= self::BATCH ) {
				$flush();
				if ( null !== $progress ) {
					$progress( $stats['written'] );
				}
			}
		}
		$flush();
		$stats['lists'] = count( $seen );

		if ( ! $dry_run ) {
			update_option( CodeListSchema::OPTION_LANGS, $langs, false );
			update_option( CodeListSchema::OPTION_META, $meta, false );
			update_option( CodeListSchema::OPTION_LAST, [ 'at' => time(), 'file' => basename( $path ), 'rows' => $stats['rows'], 'lists' => $stats['lists'], 'languages' => $stats['languages'] ], false );
		}
		return $stats;
	}

	/**
	 * Blätter anhand der Kopfzeile zuordnen.
	 *
	 * @return array{rows:string,meta:string,langs:string}
	 */
	public static function detect_sheets( XlsxReader $reader ): array {
		$found = [ 'rows' => '', 'meta' => '', 'langs' => '' ];
		foreach ( $reader->sheet_names() as $name ) {
			$first = null;
			foreach ( $reader->rows( $name ) as $row ) {
				$first = $row;
				break;
			}
			if ( null === $first ) {
				continue;
			}
			if ( 'Language' === ( $first['A'] ?? '' ) && 'CodeList' === ( $first['B'] ?? '' ) ) {
				$found['rows'] = $name;
			} elseif ( 'Codeliste' === ( $first['A'] ?? '' ) ) {
				$found['meta'] = $name;
			} elseif ( 'Language' === ( $first['A'] ?? '' ) && ! isset( $first['B'] ) ) {
				$found['langs'] = $name;
			}
		}
		foreach ( $found as $kind => $name ) {
			if ( '' === $name ) {
				throw new \RuntimeException( 'Blatt nicht erkannt (Kopfzeile): ' . $kind );
			}
		}
		return $found;
	}

	/** @return array<int,string> Sprach-Nr ⇒ Name (Liebherr-Zählung: 0 Deutsch … 7 Chinesisch). */
	public static function languages(): array {
		$v = get_option( CodeListSchema::OPTION_LANGS, [] );
		return is_array( $v ) && [] !== $v ? $v : [ 0 => 'German', 1 => 'French', 2 => 'English', 3 => 'Italian', 4 => 'Spanish', 5 => 'Russian', 6 => 'Dutch', 7 => 'Chinese' ];
	}

	/** @return array<string,array{name:string,entity:string,rows:int}> Liste ⇒ Metadaten + Zeilen je Sprache (Sprache 0). */
	public static function lists(): array {
		global $wpdb;
		$meta  = get_option( CodeListSchema::OPTION_META, [] );
		$meta  = is_array( $meta ) ? $meta : [];
		$table = CodeListSchema::table_name();
		$rows  = $wpdb->get_results( "SELECT code_list, COUNT(*) AS n FROM {$table} WHERE language = 0 GROUP BY code_list", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out   = [];
		foreach ( is_array( $rows ) ? $rows : [] as $r ) {
			$out[ (string) $r['code_list'] ] = [ 'name' => (string) ( $meta[ $r['code_list'] ]['name'] ?? '' ), 'entity' => (string) ( $meta[ $r['code_list'] ]['entity'] ?? '' ), 'rows' => (int) $r['n'] ];
		}
		foreach ( $meta as $code => $m ) {
			if ( ! isset( $out[ $code ] ) ) {
				$out[ $code ] = [ 'name' => (string) ( $m['name'] ?? '' ), 'entity' => (string) ( $m['entity'] ?? '' ), 'rows' => 0 ];
			}
		}
		ksort( $out, SORT_NATURAL | SORT_FLAG_CASE );
		return $out;
	}

	public static function total(): int {
		global $wpdb;
		$table = CodeListSchema::table_name();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/** @return array<string,mixed>|null Letzter Import (Zeit, Datei, Zahlen). */
	public static function last_import(): ?array {
		$v = get_option( CodeListSchema::OPTION_LAST, null );
		return is_array( $v ) ? $v : null;
	}

	/**
	 * Suche nach Code (exakt/Präfix) oder Text (Teilstring) in einer Liste und Sprache.
	 *
	 * @return array<int,array{code:string,description:string,additional_value:string}>
	 */
	public static function search( string $code_list, int $language, string $term, int $limit = self::MAX_RESULTS ): array {
		global $wpdb;
		$table = CodeListSchema::table_name();
		$limit = max( 1, min( self::MAX_RESULTS, $limit ) );
		$term  = trim( $term );
		if ( '' === $term ) {
			$sql = $wpdb->prepare( "SELECT code, description, additional_value FROM {$table} WHERE code_list = %s AND language = %d ORDER BY description ASC LIMIT %d", $code_list, $language, $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$like = '%' . $wpdb->esc_like( $term ) . '%';
			$pre  = $wpdb->esc_like( $term ) . '%';
			$sql  = $wpdb->prepare( "SELECT code, description, additional_value FROM {$table} WHERE code_list = %s AND language = %d AND (code LIKE %s OR description LIKE %s) ORDER BY (code = %s) DESC, description ASC LIMIT %d", $code_list, $language, $pre, $like, $term, $limit ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : [];
	}

	/** Anzeigetext eines Codes; '' wenn unbekannt. */
	public static function label( string $code_list, string $code, int $language = 0 ): string {
		global $wpdb;
		$table = CodeListSchema::table_name();
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT description FROM {$table} WHERE code_list = %s AND code = %s AND language = %d LIMIT 1", $code_list, $code, $language ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
