<?php
/**
 * Liebherr World – My Liebherr: My-Machines-Repository (Pflichtenheft My Liebherr §29 Pos. 09, ADR-LIW-MYL-001 R5).
 *
 * Persistiert persönlich zugeordnete Maschinen (Tabelle {@see Schema::machine_table()}): Name, Seriennummer,
 * Standort, Notiz, optionaler Dokument-Link. Eigentümer-gebunden (§35/SEC 01).
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.123
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MachineRepository {

	/** @return array<int,array<string,mixed>> */
	public static function for_user( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return [];
		}
		global $wpdb;
		$t    = Schema::machine_table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE user_id = %d ORDER BY id DESC", $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $rows ) ? array_map( [ self::class, 'shape' ], $rows ) : [];
	}

	/** @return array<string,mixed>|null */
	public static function get( int $user_id, int $id ): ?array {
		global $wpdb;
		$t   = Schema::machine_table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d AND user_id = %d", $id, $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $row ) ? self::shape( $row ) : null;
	}

	/** @param array<string,mixed> $data @return array<string,mixed>|null */
	public static function add( int $user_id, array $data ): ?array {
		if ( $user_id <= 0 ) {
			return null;
		}
		global $wpdb;
		$wpdb->insert( Schema::machine_table(), self::clean( $data ) + [ 'user_id' => $user_id ] ); // phpcs:ignore WordPress.DB
		return self::get( $user_id, (int) $wpdb->insert_id );
	}

	public static function delete( int $user_id, int $id ): bool {
		if ( null === self::get( $user_id, $id ) ) {
			return false;
		}
		global $wpdb;
		return false !== $wpdb->delete( Schema::machine_table(), [ 'id' => $id, 'user_id' => $user_id ], [ '%d', '%d' ] ); // phpcs:ignore WordPress.DB
	}

	/** @param array<string,mixed> $d @return array<string,mixed> */
	private static function clean( array $d ): array {
		$out = [];
		if ( array_key_exists( 'name', $d ) )     { $out['name'] = sanitize_text_field( (string) $d['name'] ); }
		if ( array_key_exists( 'serial', $d ) )   { $out['serial'] = sanitize_text_field( (string) $d['serial'] ); }
		if ( array_key_exists( 'location', $d ) ) { $out['location'] = sanitize_text_field( (string) $d['location'] ); }
		if ( array_key_exists( 'note', $d ) )     { $out['note'] = sanitize_textarea_field( (string) $d['note'] ); }
		if ( array_key_exists( 'doc_url', $d ) )  { $out['doc_url'] = esc_url_raw( (string) $d['doc_url'] ); }
		return $out;
	}

	/** @param array<string,mixed> $row @return array<string,mixed> */
	private static function shape( array $row ): array {
		return [
			'id'       => (int) $row['id'],
			'name'     => (string) ( $row['name'] ?? '' ),
			'serial'   => (string) ( $row['serial'] ?? '' ),
			'location' => (string) ( $row['location'] ?? '' ),
			'note'     => (string) ( $row['note'] ?? '' ),
			'doc_url'  => (string) ( $row['doc_url'] ?? '' ),
		];
	}
}
