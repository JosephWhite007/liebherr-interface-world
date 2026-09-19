<?php
/**
 * Liebherr World – My Liebherr: Freigabe-Repository (Pflichtenheft My Liebherr §31, ADR-LIW-MYL-001 R3).
 *
 * Persistiert gezielte Freigaben (Tabelle {@see Schema::share_table()}): an einzelne Personen/Teams (Kollegen,
 * Status `active`) oder an die berechtigte Plattformöffentlichkeit (World, Status `pending` – die redaktionelle
 * Freigabe/Review folgt, §31; keine automatische Weltveröffentlichung). Der Aufrufer stellt sicher, dass der
 * Grantor Eigentümer des Objekts ist.
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.119
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ShareRepository {

	/**
	 * Legt eine Freigabe an. World-Freigaben starten als `pending` (Review vorbehalten), sonst `active`.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function add( int $grantor_id, string $item_type, int $item_id, string $recipient_type, int $recipient_id, string $scope, ?string $expires_at = null ): ?array {
		if ( $grantor_id <= 0 || $item_id <= 0 ) {
			return null;
		}
		$rtype  = ContentRules::recipient_type( $recipient_type );
		$status = ( 'world' === $rtype ) ? 'pending' : 'active';
		global $wpdb;
		$wpdb->insert( Schema::share_table(), [ // phpcs:ignore WordPress.DB
			'item_type'      => in_array( $item_type, [ 'gallery', 'dream' ], true ) ? $item_type : 'gallery',
			'item_id'        => $item_id,
			'grantor_id'     => $grantor_id,
			'recipient_type' => $rtype,
			'recipient_id'   => ( 'world' === $rtype ) ? 0 : max( 0, $recipient_id ),
			'scope'          => ContentRules::scope( $scope ),
			'expires_at'     => $expires_at,
			'status'         => $status,
		] );
		return self::get( (int) $wpdb->insert_id );
	}

	/** @return array<string,mixed>|null */
	public static function get( int $id ): ?array {
		if ( $id <= 0 ) {
			return null;
		}
		global $wpdb;
		$t   = Schema::share_table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $row ) ? self::shape( $row ) : null;
	}

	/** Freigaben eines Objekts (für Eigentümer-Ansicht). @return array<int,array<string,mixed>> */
	public static function for_item( string $item_type, int $item_id ): array {
		global $wpdb;
		$t    = Schema::share_table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE item_type = %s AND item_id = %d ORDER BY id DESC", $item_type, $item_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $rows ) ? array_map( [ self::class, 'shape' ], $rows ) : [];
	}

	/** An einen Nutzer eingehende, aktive Freigaben. @return array<int,array<string,mixed>> */
	public static function incoming_for_user( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return [];
		}
		global $wpdb;
		$t    = Schema::share_table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE recipient_type = 'user' AND recipient_id = %d AND status = 'active' ORDER BY id DESC", $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $rows ) ? array_map( [ self::class, 'shape' ], $rows ) : [];
	}

	/** World-Freigaben mit gegebenem Status (Standard: alle). @return array<int,array<string,mixed>> */
	public static function world_list( string $status = '' ): array {
		global $wpdb;
		$t = Schema::share_table();
		if ( '' !== $status ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE recipient_type = 'world' AND status = %s ORDER BY id DESC", $status ), ARRAY_A ); // phpcs:ignore WordPress.DB
		} else {
			$rows = $wpdb->get_results( "SELECT * FROM {$t} WHERE recipient_type = 'world' ORDER BY id DESC", ARRAY_A ); // phpcs:ignore WordPress.DB
		}
		return is_array( $rows ) ? array_map( [ self::class, 'shape' ], $rows ) : [];
	}

	/** Widerruft eine Freigabe (nur durch den Grantor). */
	public static function revoke( int $grantor_id, int $id ): bool {
		$g = self::get( $id );
		if ( null === $g || (int) $g['grantor_id'] !== $grantor_id ) {
			return false;
		}
		global $wpdb;
		return false !== $wpdb->delete( Schema::share_table(), [ 'id' => $id ], [ '%d' ] ); // phpcs:ignore WordPress.DB
	}

	/** Prüfer-Aktion: setzt den Status einer Freigabe (z. B. World-Review pending → published/blocked). */
	public static function set_status( int $id, string $to ): bool {
		if ( null === self::get( $id ) ) {
			return false;
		}
		global $wpdb;
		return false !== $wpdb->update( Schema::share_table(), [ 'status' => $to ], [ 'id' => $id ], [ '%s' ], [ '%d' ] ); // phpcs:ignore WordPress.DB
	}

	/** Entfernt alle Freigaben eines Objekts (beim Löschen des Objekts). */
	public static function delete_for_item( string $item_type, int $item_id ): void {
		global $wpdb;
		$wpdb->delete( Schema::share_table(), [ 'item_type' => $item_type, 'item_id' => $item_id ], [ '%s', '%d' ] ); // phpcs:ignore WordPress.DB
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	private static function shape( array $row ): array {
		return [
			'id'             => (int) $row['id'],
			'item_type'      => (string) ( $row['item_type'] ?? 'gallery' ),
			'item_id'        => (int) ( $row['item_id'] ?? 0 ),
			'grantor_id'     => (int) ( $row['grantor_id'] ?? 0 ),
			'recipient_type' => (string) ( $row['recipient_type'] ?? 'user' ),
			'recipient_id'   => (int) ( $row['recipient_id'] ?? 0 ),
			'scope'          => (string) ( $row['scope'] ?? 'view' ),
			'status'         => (string) ( $row['status'] ?? 'active' ),
			'expires_at'     => isset( $row['expires_at'] ) ? (string) $row['expires_at'] : null,
		];
	}
}
