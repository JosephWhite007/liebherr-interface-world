<?php
/**
 * Liebherr Interface Solutions – Interface Catalog Service
 *
 * CRUD-Schicht für liw_interface. Jede Änderung wird über CoreBridge\AuditBridge protokolliert
 * (SEC-005). Öffentliche Ausgabe zeigt ausschließlich `public_metadata` (Liebherr §17: „Öffentliche
 * Inhalte und technische Schnittstellendaten sind strikt zu trennen").
 *
 * @package Liebherr\InterfaceWorld\Interfaces
 * @since   0.1.0-alpha.1
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Interfaces;

use Liebherr\InterfaceWorld\CoreBridge\AuditBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class InterfaceCatalogService {

	private const ALLOWED_DIRECTIONS = [ 'inbound', 'outbound', 'bidirectional' ];
	private const ALLOWED_STATUSES   = [ 'draft', 'in_simulation', 'verified', 'approved', 'retired' ];

	/**
	 * @param array{code:string,name:string,direction?:string,protocol?:?string,version?:string,doc_reference?:?string} $data
	 * @return int|\WP_Error Neue ID oder Fehler bei ungültigen Eingaben.
	 */
	public static function create( array $data, int $actor_id ): int|\WP_Error {
		global $wpdb;

		$code = sanitize_key( $data['code'] ?? '' );
		$name = sanitize_text_field( $data['name'] ?? '' );

		if ( '' === $code || '' === $name ) {
			return new \WP_Error( 'liw_invalid_input', __( 'Code und Name sind Pflichtfelder.', 'liebherr-interface-world' ) );
		}

		$direction = in_array( $data['direction'] ?? 'inbound', self::ALLOWED_DIRECTIONS, true )
			? $data['direction']
			: 'inbound';

		$row = [
			'code'             => $code,
			'name'             => $name,
			'direction'        => $direction,
			'protocol'         => isset( $data['protocol'] ) ? sanitize_text_field( $data['protocol'] ) : null,
			'version'          => sanitize_text_field( $data['version'] ?? '0.1.0' ),
			'lifecycle_status' => 'draft',
			'doc_reference'    => isset( $data['doc_reference'] ) ? esc_url_raw( $data['doc_reference'] ) : null,
			'created_by'       => $actor_id,
		];

		$inserted = $wpdb->insert( InterfaceCatalogSchema::table_name(), $row );
		if ( false === $inserted ) {
			return new \WP_Error( 'liw_db_error', __( 'Schnittstelle konnte nicht gespeichert werden.', 'liebherr-interface-world' ) );
		}

		$id = (int) $wpdb->insert_id;
		AuditBridge::log( 'create', 'interface', $id, [], $row, $actor_id );
		return $id;
	}

	public static function set_lifecycle_status( int $id, string $status, int $actor_id ): bool|\WP_Error {
		if ( ! in_array( $status, self::ALLOWED_STATUSES, true ) ) {
			return new \WP_Error( 'liw_invalid_status', __( 'Unbekannter Lifecycle-Status.', 'liebherr-interface-world' ) );
		}

		global $wpdb;
		$table = InterfaceCatalogSchema::table_name();
		$before = self::get( $id );
		if ( null === $before ) {
			return new \WP_Error( 'liw_not_found', __( 'Schnittstelle nicht gefunden.', 'liebherr-interface-world' ) );
		}

		$updated = $wpdb->update( $table, [ 'lifecycle_status' => $status ], [ 'id' => $id ] );
		if ( false === $updated ) {
			return new \WP_Error( 'liw_db_error', __( 'Status konnte nicht aktualisiert werden.', 'liebherr-interface-world' ) );
		}

		AuditBridge::log( 'status_change', 'interface', $id, [ 'lifecycle_status' => $before['lifecycle_status'] ], [ 'lifecycle_status' => $status ], $actor_id );
		return true;
	}

	/** @return array<string, mixed>|null */
	public static function get( int $id ): ?array {
		global $wpdb;
		$table = InterfaceCatalogSchema::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/** @return array<int, array<string, mixed>> */
	public static function get_all(): array {
		global $wpdb;
		$table = InterfaceCatalogSchema::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY code ASC", ARRAY_A );
		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Öffentliche Ansicht (World Connections / Interface-Übersicht): nur freigegebene
	 * Metadaten, niemals interne Felder (doc_reference, created_by).
	 *
	 * @return array<int, array{code:string,name:string,direction:string,lifecycle_status:string}>
	 */
	public static function get_public_catalog(): array {
		$rows = self::get_all();
		return array_map(
			static fn( array $row ): array => [
				'code'             => (string) $row['code'],
				'name'             => (string) $row['name'],
				'direction'        => (string) $row['direction'],
				'lifecycle_status' => (string) $row['lifecycle_status'],
			],
			array_filter( $rows, static fn( array $row ): bool => 'approved' === $row['lifecycle_status'] )
		);
	}
}
