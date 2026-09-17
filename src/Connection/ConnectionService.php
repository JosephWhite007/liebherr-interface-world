<?php
/**
 * Liebherr Interface Solutions – Connection Service
 *
 * @package Liebherr\InterfaceWorld\Connection
 * @since   0.1.0-alpha.1
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Connection;

use Liebherr\InterfaceWorld\CoreBridge\AuditBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ConnectionService {

	private const ALLOWED_PARTNER_TYPES   = [ 'dealer', 'supplier', 'customer' ];
	private const ALLOWED_DISPLAY_STATUSES = [ 'planned', 'active', 'inactive' ];

	/** @param array{region:string,partner_type?:string,public_flag?:bool} $data */
	public static function create( array $data, int $actor_id ): int|\WP_Error {
		global $wpdb;

		$region = sanitize_text_field( $data['region'] ?? '' );
		if ( '' === $region ) {
			return new \WP_Error( 'liw_invalid_input', __( 'Region ist Pflichtfeld.', 'liebherr-interface-world' ) );
		}

		$partner_type = in_array( $data['partner_type'] ?? 'dealer', self::ALLOWED_PARTNER_TYPES, true )
			? $data['partner_type']
			: 'dealer';

		$row = [
			'region'         => $region,
			'partner_type'   => $partner_type,
			'display_status' => 'planned',
			'public_flag'    => ! empty( $data['public_flag'] ) ? 1 : 0,
		];

		$inserted = $wpdb->insert( ConnectionSchema::table_name(), $row );
		if ( false === $inserted ) {
			return new \WP_Error( 'liw_db_error', __( 'Verbindung konnte nicht gespeichert werden.', 'liebherr-interface-world' ) );
		}

		$id = (int) $wpdb->insert_id;
		AuditBridge::log( 'create', 'connection', $id, [], $row, $actor_id );
		return $id;
	}

	/** Nur öffentlich freigegebene Einträge – für die World Connections Map (LP-06). */
	public static function get_public(): array {
		global $wpdb;
		$table = ConnectionSchema::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( "SELECT region, partner_type, display_status FROM {$table} WHERE public_flag = 1 ORDER BY region ASC", ARRAY_A );
		return is_array( $rows ) ? $rows : [];
	}

	/** Admin-Ansicht: alle Einträge, unabhängig vom Freigabestatus. */
	public static function get_all(): array {
		global $wpdb;
		$table = ConnectionSchema::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY region ASC", ARRAY_A );
		return is_array( $rows ) ? $rows : [];
	}

	/** @return array<string, mixed>|null */
	public static function get( int $id ): ?array {
		global $wpdb;
		$table = ConnectionSchema::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	public static function set_display_status( int $id, string $status, int $actor_id ): bool|\WP_Error {
		if ( ! in_array( $status, self::ALLOWED_DISPLAY_STATUSES, true ) ) {
			return new \WP_Error( 'liw_invalid_status', __( 'Unbekannter Anzeigestatus.', 'liebherr-interface-world' ) );
		}

		$before = self::get( $id );
		if ( null === $before ) {
			return new \WP_Error( 'liw_not_found', __( 'Verbindung nicht gefunden.', 'liebherr-interface-world' ) );
		}

		global $wpdb;
		$updated = $wpdb->update( ConnectionSchema::table_name(), [ 'display_status' => $status ], [ 'id' => $id ] );
		if ( false === $updated ) {
			return new \WP_Error( 'liw_db_error', __( 'Status konnte nicht aktualisiert werden.', 'liebherr-interface-world' ) );
		}

		AuditBridge::log( 'status_change', 'connection', $id, [ 'display_status' => $before['display_status'] ], [ 'display_status' => $status ], $actor_id );
		return true;
	}

	/**
	 * Öffentlichkeits-Flag umschalten (LP-06: erst nach expliziter Freigabe auf der World
	 * Connections Map sichtbar).
	 */
	public static function set_public_flag( int $id, bool $public, int $actor_id ): bool|\WP_Error {
		$before = self::get( $id );
		if ( null === $before ) {
			return new \WP_Error( 'liw_not_found', __( 'Verbindung nicht gefunden.', 'liebherr-interface-world' ) );
		}

		global $wpdb;
		$value   = $public ? 1 : 0;
		$updated = $wpdb->update( ConnectionSchema::table_name(), [ 'public_flag' => $value ], [ 'id' => $id ] );
		if ( false === $updated ) {
			return new \WP_Error( 'liw_db_error', __( 'Freigabe konnte nicht aktualisiert werden.', 'liebherr-interface-world' ) );
		}

		AuditBridge::log( 'visibility_change', 'connection', $id, [ 'public_flag' => (int) $before['public_flag'] ], [ 'public_flag' => $value ], $actor_id );
		return true;
	}

	public static function delete( int $id, int $actor_id ): bool|\WP_Error {
		$before = self::get( $id );
		if ( null === $before ) {
			return new \WP_Error( 'liw_not_found', __( 'Verbindung nicht gefunden.', 'liebherr-interface-world' ) );
		}

		global $wpdb;
		$deleted = $wpdb->delete( ConnectionSchema::table_name(), [ 'id' => $id ] );
		if ( false === $deleted ) {
			return new \WP_Error( 'liw_db_error', __( 'Verbindung konnte nicht gelöscht werden.', 'liebherr-interface-world' ) );
		}

		AuditBridge::log( 'delete', 'connection', $id, $before, [], $actor_id );
		return true;
	}
}
