<?php
/**
 * Liebherr World – Pocket Information: Repository (Pflichtenheft My Liebherr §34, ADR-LIW-MYL-001 R5).
 *
 * Persistiert personenbezogene Pocket-Items (Tabelle {@see Schema::item_table()}). Der Feed liefert die noch
 * gültigen Items (validity_until leer oder in der Zukunft), Alerts (high/critical) zuerst. Quittierung ist eine
 * bewusste Handlung, getrennt von der Anzeige protokolliert (§34). Eigentümer-gebunden (§35/SEC 01).
 *
 * @package Liebherr\InterfaceWorld\Pocket
 * @since   0.1.0-alpha.124
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Pocket;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class PocketRepository {

	public const PRIORITY = [ 'low', 'normal', 'high', 'critical' ];

	private static function priority( string $p ): string {
		return in_array( $p, self::PRIORITY, true ) ? $p : 'normal';
	}

	/** Gültige Items des Nutzers, Alerts (Priorität) zuerst. @return array<int,array<string,mixed>> */
	public static function feed( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return [];
		}
		global $wpdb;
		$t    = Schema::item_table();
		$now  = current_time( 'mysql' );
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$t} WHERE user_id = %d AND ( validity_until IS NULL OR validity_until >= %s )
			 ORDER BY FIELD(priority,'critical','high','normal','low'), id DESC",
			$user_id, $now
		), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $rows ) ? array_map( [ self::class, 'shape' ], $rows ) : [];
	}

	/** @return array<string,mixed>|null */
	public static function get( int $user_id, int $id ): ?array {
		global $wpdb;
		$t   = Schema::item_table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d AND user_id = %d", $id, $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $row ) ? self::shape( $row ) : null;
	}

	/** @param array<string,mixed> $data @return array<string,mixed>|null */
	public static function add( int $user_id, array $data ): ?array {
		if ( $user_id <= 0 ) {
			return null;
		}
		global $wpdb;
		$wpdb->insert( Schema::item_table(), [ // phpcs:ignore WordPress.DB
			'user_id'      => $user_id,
			'source'       => sanitize_text_field( (string) ( $data['source'] ?? '' ) ),
			'title'        => sanitize_text_field( (string) ( $data['title'] ?? '' ) ),
			'body'         => sanitize_textarea_field( (string) ( $data['body'] ?? '' ) ),
			'priority'     => self::priority( sanitize_text_field( (string) ( $data['priority'] ?? 'normal' ) ) ),
			'return_route' => esc_url_raw( (string) ( $data['return_route'] ?? '' ) ),
			'requires_ack' => ! empty( $data['requires_ack'] ) ? 1 : 0,
		] );
		return self::get( $user_id, (int) $wpdb->insert_id );
	}

	/** Quittiert ein Pflicht-Item (bewusste Bestätigung, getrennt protokolliert). @return array<string,mixed> */
	public static function acknowledge( int $user_id, int $id ): array {
		$item = self::get( $user_id, $id );
		if ( null === $item ) {
			return [ 'ok' => false, 'reason' => 'not_found' ];
		}
		global $wpdb;
		$wpdb->update( Schema::item_table(), [ 'acknowledged_at' => current_time( 'mysql' ) ], [ 'id' => $id, 'user_id' => $user_id ], [ '%s' ], [ '%d', '%d' ] ); // phpcs:ignore WordPress.DB
		return [ 'ok' => true, 'item' => self::get( $user_id, $id ) ];
	}

	public static function delete( int $user_id, int $id ): bool {
		if ( null === self::get( $user_id, $id ) ) {
			return false;
		}
		global $wpdb;
		return false !== $wpdb->delete( Schema::item_table(), [ 'id' => $id, 'user_id' => $user_id ], [ '%d', '%d' ] ); // phpcs:ignore WordPress.DB
	}

	/** @param array<string,mixed> $row @return array<string,mixed> */
	private static function shape( array $row ): array {
		return [
			'id'              => (int) $row['id'],
			'source'          => (string) ( $row['source'] ?? '' ),
			'title'           => (string) ( $row['title'] ?? '' ),
			'body'            => (string) ( $row['body'] ?? '' ),
			'priority'        => (string) ( $row['priority'] ?? 'normal' ),
			'return_route'    => (string) ( $row['return_route'] ?? '' ),
			'requires_ack'    => (int) ( $row['requires_ack'] ?? 0 ),
			'acknowledged_at' => isset( $row['acknowledged_at'] ) ? (string) $row['acknowledged_at'] : null,
		];
	}
}
