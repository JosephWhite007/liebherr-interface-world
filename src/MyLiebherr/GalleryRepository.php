<?php
/**
 * Liebherr World – My Liebherr: Own-Gallery-Repository (Pflichtenheft My Liebherr §31, ADR-LIW-MYL-001 R3).
 *
 * Persistiert die private Galerie (Tabelle {@see Schema::gallery_table()}). Bilder werden über ihre
 * Mediathek-Attachment-ID referenziert (Wiederverwendung der WP-Medien/MediaBridge, kein zweiter Upload-Weg).
 * Eigentümer-gebunden (§35/SEC 01); {@see get_any()} liest ein Objekt eigentümerübergreifend für berechtigte
 * Freigaben (Aufrufer prüft die Freigabe).
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.119
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class GalleryRepository {

	/** @return array<int,array<string,mixed>> */
	public static function for_owner( int $owner_id ): array {
		if ( $owner_id <= 0 ) {
			return [];
		}
		global $wpdb;
		$t    = Schema::gallery_table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE owner_id = %d AND status = 'active' ORDER BY id DESC", $owner_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $rows ) ? array_map( [ self::class, 'shape' ], $rows ) : [];
	}

	/** @return array<string,mixed>|null */
	public static function get( int $owner_id, int $id ): ?array {
		if ( $owner_id <= 0 || $id <= 0 ) {
			return null;
		}
		global $wpdb;
		$t   = Schema::gallery_table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d AND owner_id = %d", $id, $owner_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $row ) ? self::shape( $row ) : null;
	}

	/** Eigentümerübergreifendes Lesen (nur für berechtigte Freigaben; Aufrufer prüft Grant). @return array<string,mixed>|null */
	public static function get_any( int $id ): ?array {
		if ( $id <= 0 ) {
			return null;
		}
		global $wpdb;
		$t   = Schema::gallery_table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $row ) ? self::shape( $row ) : null;
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>|null
	 */
	public static function add( int $owner_id, array $data ): ?array {
		if ( $owner_id <= 0 ) {
			return null;
		}
		global $wpdb;
		$wpdb->insert( Schema::gallery_table(), self::clean( $data ) + [ 'owner_id' => $owner_id, 'status' => 'active' ] ); // phpcs:ignore WordPress.DB
		return self::get( $owner_id, (int) $wpdb->insert_id );
	}

	/**
	 * @param array<string,mixed> $data
	 * @return array<string,mixed>|null
	 */
	public static function update( int $owner_id, int $id, array $data ): ?array {
		if ( null === self::get( $owner_id, $id ) ) {
			return null;
		}
		global $wpdb;
		$wpdb->update( Schema::gallery_table(), self::clean( $data ), [ 'id' => $id, 'owner_id' => $owner_id ] ); // phpcs:ignore WordPress.DB
		return self::get( $owner_id, $id );
	}

	/** Löscht ein Objekt und alle zugehörigen Freigaben (§31: bestehende Freigaben werden mit entfernt). */
	public static function delete( int $owner_id, int $id ): bool {
		if ( null === self::get( $owner_id, $id ) ) {
			return false;
		}
		ShareRepository::delete_for_item( 'gallery', $id );
		global $wpdb;
		return false !== $wpdb->delete( Schema::gallery_table(), [ 'id' => $id, 'owner_id' => $owner_id ], [ '%d', '%d' ] ); // phpcs:ignore WordPress.DB
	}

	/** Prüfer-Aktion (eigentümerübergreifend): Objektstatus setzen, z. B. 'suspended' (§11 Sperren) / 'active'. */
	public static function moderate_status( int $id, string $status ): bool {
		if ( null === self::get_any( $id ) ) {
			return false;
		}
		global $wpdb;
		return false !== $wpdb->update( Schema::gallery_table(), [ 'status' => $status ], [ 'id' => $id ], [ '%s' ], [ '%d' ] ); // phpcs:ignore WordPress.DB
	}

	/**
	 * @param array<string,mixed> $d
	 * @return array<string,mixed>
	 */
	private static function clean( array $d ): array {
		$out = [];
		if ( array_key_exists( 'media_id', $d ) )    { $out['media_id'] = max( 0, (int) $d['media_id'] ); }
		if ( array_key_exists( 'title', $d ) )       { $out['title'] = sanitize_text_field( (string) $d['title'] ); }
		if ( array_key_exists( 'description', $d ) ) { $out['description'] = sanitize_textarea_field( (string) $d['description'] ); }
		if ( array_key_exists( 'tags', $d ) )        { $out['tags'] = sanitize_text_field( (string) $d['tags'] ); }
		if ( array_key_exists( 'album', $d ) )       { $out['album'] = sanitize_text_field( (string) $d['album'] ); }
		if ( array_key_exists( 'visibility', $d ) )  { $out['visibility'] = ContentRules::visibility( sanitize_text_field( (string) $d['visibility'] ) ); }
		return $out;
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	private static function shape( array $row ): array {
		return [
			'id'          => (int) $row['id'],
			'owner_id'    => (int) ( $row['owner_id'] ?? 0 ),
			'media_id'    => (int) ( $row['media_id'] ?? 0 ),
			'title'       => (string) ( $row['title'] ?? '' ),
			'description' => (string) ( $row['description'] ?? '' ),
			'tags'        => (string) ( $row['tags'] ?? '' ),
			'album'       => (string) ( $row['album'] ?? '' ),
			'visibility'  => (string) ( $row['visibility'] ?? 'private' ),
			'status'      => (string) ( $row['status'] ?? 'active' ),
		];
	}
}
