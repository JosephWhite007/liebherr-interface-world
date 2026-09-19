<?php
/**
 * Liebherr World – My Liebherr: Drei-Wort-Label (Pflichtenheft My Liebherr §32/§37, ADR-LIW-MYL-001 S14).
 *
 * Normierter Drei-Wort-Name nach dem Muster Maschine · Problem · Handlung (z. B. „Raupe Hydraulik Entlüften").
 * Die UI schlägt Begriffe vor, der Autor bestätigt sie; Synonyme und (perspektivisch) Übersetzungen werden für
 * die Suche ergänzt. Der Name ersetzt keine ID/Taxonomie, sondern ist ein zusätzlicher semantischer Anker – die
 * Agentensuche kombiniert Drei-Wort-Name, stabile Objekt-ID und Volltext ({@see search()}).
 *
 * Reine Logik (normalize/from_words/valid/display/tokens/suggest) ist ohne WordPress testbar; set/get/search
 * persistieren in {@see Schema::three_word_table()}.
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.130
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ThreeWordLabel {

	public const OBJECT_ADVENTURE = 'adventure';

	/** Rollen der drei Positionen (Reihenfolge fest): Maschine · Problem · Handlung. @return array<int,string> */
	public static function roles(): array {
		return [ 'machine', 'problem', 'action' ];
	}

	/** Normalisiert einen Einzelbegriff: ein Wort, getrimmt, Kleinbuchstaben mit Großanfang. Rein. */
	public static function normalize_term( string $term ): string {
		$term = trim( preg_replace( '/\s+/', ' ', $term ) ?? '' );
		if ( '' === $term ) {
			return '';
		}
		$first = explode( ' ', $term )[0]; // genau EIN Wort je Position
		if ( function_exists( 'mb_strtolower' ) ) {
			$first = mb_strtolower( $first, 'UTF-8' );
			return mb_strtoupper( mb_substr( $first, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $first, 1, null, 'UTF-8' );
		}
		return ucfirst( strtolower( $first ) );
	}

	/**
	 * Normalisiert drei Begriffe.
	 *
	 * @return array{0:string,1:string,2:string}
	 */
	public static function from_words( string $t1, string $t2, string $t3 ): array {
		return [ self::normalize_term( $t1 ), self::normalize_term( $t2 ), self::normalize_term( $t3 ) ];
	}

	/** Genau drei nicht-leere Begriffe? (§32: „genau drei normalisierte Begriffe"). */
	public static function valid( string $t1, string $t2, string $t3 ): bool {
		return '' !== $t1 && '' !== $t2 && '' !== $t3;
	}

	public static function display( string $t1, string $t2, string $t3 ): string {
		return trim( $t1 . ' ' . $t2 . ' ' . $t3 );
	}

	/**
	 * Vorschlag aus Maschinen-/Bauteil-/Titelkontext (Autor bestätigt/ändert). Rein.
	 *
	 * @return array{0:string,1:string,2:string}
	 */
	public static function suggest( string $machine, string $component, string $title ): array {
		$m = self::normalize_term( $machine );
		$p = self::normalize_term( '' !== $component ? $component : $title );
		$a = self::normalize_term( self::second_word( $title ) );
		return [ $m, $p, $a ];
	}

	private static function second_word( string $s ): string {
		$parts = explode( ' ', trim( preg_replace( '/\s+/', ' ', $s ) ?? '' ) );
		return $parts[1] ?? ( $parts[0] ?? '' );
	}

	/**
	 * Suchtokens eines Labels (für Index/Agentensuche): die drei Begriffe + Synonyme (komma/space-getrennt). Rein.
	 *
	 * @param array<string,mixed> $row
	 * @return array<int,string>
	 */
	public static function tokens( array $row ): array {
		$out = [ (string) ( $row['term_1'] ?? '' ), (string) ( $row['term_2'] ?? '' ), (string) ( $row['term_3'] ?? '' ) ];
		foreach ( preg_split( '/[,\s]+/', (string) ( $row['synonyms'] ?? '' ) ) ?: [] as $syn ) {
			if ( '' !== trim( $syn ) ) {
				$out[] = trim( $syn );
			}
		}
		return array_values( array_filter( $out, static fn( $t ) => '' !== $t ) );
	}

	// ── Persistenz ──────────────────────────────────────────────────────────────
	private static function locale(): string {
		return substr( (string) get_locale(), 0, 2 ) ?: 'de';
	}

	/** @return array<string,mixed>|null */
	public static function get( int $object_id, string $object_type = self::OBJECT_ADVENTURE, string $locale = '' ): ?array {
		$locale = '' !== $locale ? $locale : self::locale();
		global $wpdb;
		$t   = Schema::three_word_table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE object_type = %s AND object_id = %d AND locale = %s", $object_type, $object_id, $locale ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Setzt/aktualisiert den Drei-Wort-Namen (normalisiert). Liefert ok=false bei ungültiger Dreiheit.
	 *
	 * @return array<string,mixed>
	 */
	public static function set( int $object_id, string $t1, string $t2, string $t3, string $synonyms = '', string $object_type = self::OBJECT_ADVENTURE, string $locale = '' ): array {
		[ $n1, $n2, $n3 ] = self::from_words( $t1, $t2, $t3 );
		if ( ! self::valid( $n1, $n2, $n3 ) ) {
			return [ 'ok' => false, 'reason' => 'need_three_terms' ];
		}
		$locale = '' !== $locale ? $locale : self::locale();
		$data   = [
			'object_type'      => $object_type,
			'object_id'        => $object_id,
			'locale'           => $locale,
			'term_1'           => $n1,
			'term_2'           => $n2,
			'term_3'           => $n3,
			'synonyms'         => sanitize_text_field( $synonyms ),
			'taxonomy_version' => (string) get_option( 'liw_myl_taxonomy_version', '1' ),
		];
		global $wpdb;
		$t   = Schema::three_word_table();
		$row = self::get( $object_id, $object_type, $locale );
		if ( null !== $row ) {
			$wpdb->update( $t, $data, [ 'id' => (int) $row['id'] ] ); // phpcs:ignore WordPress.DB
		} else {
			$wpdb->insert( $t, $data ); // phpcs:ignore WordPress.DB
		}
		return [ 'ok' => true, 'label' => self::get( $object_id, $object_type, $locale ), 'display' => self::display( $n1, $n2, $n3 ) ];
	}

	/**
	 * Agentensuche über Labels: matcht Begriffe/Synonyme (case-insensitiv, Teilstring). Liefert Objekt-Referenzen.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function search( string $query, string $object_type = self::OBJECT_ADVENTURE, int $limit = 20 ): array {
		$q = trim( $query );
		if ( '' === $q ) {
			return [];
		}
		global $wpdb;
		$t    = Schema::three_word_table();
		$like = '%' . $wpdb->esc_like( $q ) . '%';
		$rows = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB
			"SELECT object_id, locale, term_1, term_2, term_3, synonyms FROM {$t}
			 WHERE object_type = %s AND ( term_1 LIKE %s OR term_2 LIKE %s OR term_3 LIKE %s OR synonyms LIKE %s )
			 ORDER BY object_id DESC LIMIT %d",
			$object_type, $like, $like, $like, $like, max( 1, $limit )
		), ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return [];
		}
		return array_map( static function ( $r ) {
			return [
				'object_id' => (int) $r['object_id'],
				'locale'    => (string) $r['locale'],
				'name'      => self::display( (string) $r['term_1'], (string) $r['term_2'], (string) $r['term_3'] ),
			];
		}, $rows );
	}
}
