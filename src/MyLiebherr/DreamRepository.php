<?php
/**
 * Liebherr World – My Liebherr: My-Dreams-Repository (Pflichtenheft My Liebherr §31, ADR-LIW-MYL-001 R3).
 *
 * Persistiert das persönliche Maschinen-Bilderbuch (Tabelle {@see Schema::dream_table()}), standardmäßig privat.
 * Nur eigener Datensatz (Objektbezug §35/SEC 01) – alle Methoden sind auf `user_id` gebunden.
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.118
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class DreamRepository {

	/** @return array<int,array<string,mixed>> */
	public static function for_user( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return [];
		}
		global $wpdb;
		$t    = Schema::dream_table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE user_id = %d ORDER BY sort_rank ASC, id ASC", $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $rows ) ? array_map( [ self::class, 'shape' ], $rows ) : [];
	}

	/** @return array<string,mixed>|null */
	public static function get( int $user_id, int $id ): ?array {
		if ( $user_id <= 0 || $id <= 0 ) {
			return null;
		}
		global $wpdb;
		$t   = Schema::dream_table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d AND user_id = %d", $id, $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $row ) ? self::shape( $row ) : null;
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>|null
	 */
	public static function add( int $user_id, array $data ): ?array {
		if ( $user_id <= 0 ) {
			return null;
		}
		global $wpdb;
		$t    = Schema::dream_table();
		$rank = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(MAX(sort_rank),0)+1 FROM {$t} WHERE user_id = %d", $user_id ) ); // phpcs:ignore WordPress.DB
		$wpdb->insert( $t, self::clean( $data ) + [ 'user_id' => $user_id, 'sort_rank' => $rank ] ); // phpcs:ignore WordPress.DB
		return self::get( $user_id, (int) $wpdb->insert_id );
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>|null
	 */
	public static function update( int $user_id, int $id, array $data ): ?array {
		if ( null === self::get( $user_id, $id ) ) {
			return null;
		}
		global $wpdb;
		$wpdb->update( Schema::dream_table(), self::clean( $data ), [ 'id' => $id, 'user_id' => $user_id ] ); // phpcs:ignore WordPress.DB
		return self::get( $user_id, $id );
	}

	public static function delete( int $user_id, int $id ): bool {
		if ( null === self::get( $user_id, $id ) ) {
			return false;
		}
		global $wpdb;
		return false !== $wpdb->delete( Schema::dream_table(), [ 'id' => $id, 'user_id' => $user_id ], [ '%d', '%d' ] ); // phpcs:ignore WordPress.DB
	}

	/** Verschiebt einen Eintrag in der Reihenfolge (Rang-Tausch mit dem Nachbarn). */
	public static function move( int $user_id, int $id, string $dir ): bool {
		$items = self::for_user( $user_id );
		$idx   = null;
		foreach ( $items as $i => $it ) {
			if ( (int) $it['id'] === $id ) {
				$idx = $i;
				break;
			}
		}
		if ( null === $idx ) {
			return false;
		}
		$swap = 'up' === $dir ? $idx - 1 : $idx + 1;
		if ( $swap < 0 || $swap >= count( $items ) ) {
			return false;
		}
		global $wpdb;
		$t = Schema::dream_table();
		$wpdb->update( $t, [ 'sort_rank' => (int) $items[ $swap ]['rank'] ], [ 'id' => (int) $items[ $idx ]['id'], 'user_id' => $user_id ], [ '%d' ], [ '%d', '%d' ] ); // phpcs:ignore WordPress.DB
		$wpdb->update( $t, [ 'sort_rank' => (int) $items[ $idx ]['rank'] ], [ 'id' => (int) $items[ $swap ]['id'], 'user_id' => $user_id ], [ '%d' ], [ '%d', '%d' ] ); // phpcs:ignore WordPress.DB
		return true;
	}

	/**
	 * Bereinigt Eingabefelder für insert/update (Feldfreigabe + Wertlisten).
	 *
	 * @param array<string,mixed> $d
	 * @return array<string,mixed>
	 */
	private static function clean( array $d ): array {
		$out = [];
		if ( array_key_exists( 'media_id', $d ) )    { $out['media_id'] = max( 0, (int) $d['media_id'] ); }
		if ( array_key_exists( 'machine_ref', $d ) ) { $out['machine_ref'] = sanitize_text_field( (string) $d['machine_ref'] ); }
		if ( array_key_exists( 'title_words', $d ) ) { $out['title_words'] = ContentRules::three_words( sanitize_text_field( (string) $d['title_words'] ) ); }
		if ( array_key_exists( 'note', $d ) )        { $out['note'] = sanitize_textarea_field( (string) $d['note'] ); }
		if ( array_key_exists( 'tags', $d ) )        { $out['tags'] = sanitize_text_field( (string) $d['tags'] ); }
		if ( array_key_exists( 'collection', $d ) )  { $out['collection'] = sanitize_text_field( (string) $d['collection'] ); }
		if ( array_key_exists( 'cover', $d ) )       { $out['cover'] = ! empty( $d['cover'] ) ? 1 : 0; }
		if ( array_key_exists( 'wish_status', $d ) ) { $out['wish_status'] = ContentRules::wish( sanitize_text_field( (string) $d['wish_status'] ) ); }
		return $out;
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	private static function shape( array $row ): array {
		return [
			'id'          => (int) $row['id'],
			'media_id'    => (int) ( $row['media_id'] ?? 0 ),
			'machine_ref' => (string) ( $row['machine_ref'] ?? '' ),
			'title_words' => (string) ( $row['title_words'] ?? '' ),
			'note'        => (string) ( $row['note'] ?? '' ),
			'tags'        => (string) ( $row['tags'] ?? '' ),
			'collection'  => (string) ( $row['collection'] ?? '' ),
			'cover'       => (int) ( $row['cover'] ?? 0 ),
			'wish_status' => (string) ( $row['wish_status'] ?? 'idea' ),
			'rank'        => (int) ( $row['sort_rank'] ?? 0 ),
		];
	}
}
