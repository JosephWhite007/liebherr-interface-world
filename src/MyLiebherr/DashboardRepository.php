<?php
/**
 * Liebherr World – My Liebherr: Dashboard-Repository (Pflichtenheft My Liebherr §5/§17, ADR-LIW-MYL-001 S3).
 *
 * Persistiert das persönliche Dashboard-Layout je Nutzer und Gerätetyp (Tabelle
 * {@see Schema::dashboard_table()}) serverseitig (§5). Nur eigener Datensatz (Objektbezug §35/SEC 01).
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.114
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class DashboardRepository {

	/**
	 * Gespeichertes Layout (roh) oder null.
	 *
	 * @return array<int,array{key:string,visible:bool}>|null
	 */
	public static function get( int $user_id, string $device = 'default' ): ?array {
		if ( $user_id <= 0 ) {
			return null;
		}
		global $wpdb;
		$table = Schema::dashboard_table();
		$json  = $wpdb->get_var( $wpdb->prepare( "SELECT layout_json FROM {$table} WHERE user_id = %d AND device = %s", $user_id, $device ) ); // phpcs:ignore WordPress.DB
		if ( null === $json ) {
			return null;
		}
		$decoded = json_decode( (string) $json, true );
		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Speichert das Layout (bereits über {@see DashboardService::sanitize()} bereinigt).
	 *
	 * @param array<int,array{key:string,visible:bool}> $layout
	 */
	public static function save( int $user_id, array $layout, string $device = 'default' ): void {
		if ( $user_id <= 0 ) {
			return;
		}
		global $wpdb;
		$table = Schema::dashboard_table();
		$json  = (string) wp_json_encode( array_values( $layout ) );
		$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE user_id = %d AND device = %s", $user_id, $device ) ); // phpcs:ignore WordPress.DB
		if ( $exists > 0 ) {
			$wpdb->update( $table, [ 'layout_json' => $json, 'updated_at' => current_time( 'mysql' ) ], [ 'id' => $exists ], [ '%s', '%s' ], [ '%d' ] ); // phpcs:ignore WordPress.DB
			return;
		}
		$wpdb->insert( $table, [ 'user_id' => $user_id, 'device' => $device, 'layout_json' => $json ], [ '%d', '%s', '%s' ] ); // phpcs:ignore WordPress.DB
	}

	/** Setzt auf die Rollenvorlage zurück (löscht das gespeicherte Layout). */
	public static function reset( int $user_id, string $device = 'default' ): void {
		if ( $user_id <= 0 ) {
			return;
		}
		global $wpdb;
		$wpdb->delete( Schema::dashboard_table(), [ 'user_id' => $user_id, 'device' => $device ], [ '%d', '%s' ] ); // phpcs:ignore WordPress.DB
	}
}
