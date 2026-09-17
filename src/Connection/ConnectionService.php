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

	private const ALLOWED_PARTNER_TYPES = [ 'dealer', 'supplier', 'customer' ];

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
}
