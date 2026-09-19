<?php
/**
 * Liebherr World – My Liebherr: Mitgliedschafts-Repository (Pflichtenheft My Liebherr §17, ADR-LIW-MYL-001 S1).
 *
 * Liest die Mitgliedschaften eines Nutzers (Nutzer × Organisation × Rolle, Tabelle
 * {@see Schema::membership_table()}). In S1 werden Mitgliedschaften nur gelesen; das Anlegen/Pflegen folgt mit
 * dem Organisations-/Onboarding-Ausbau (R1). Sind keine Zeilen vorhanden, bleibt die Liste leer – der
 * `me`-Endpunkt meldet dann die WP-Rollen und einen leeren Organisationssatz.
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.108
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MembershipRepository {

	/**
	 * Mitgliedschaften eines Nutzers (neueste zuerst).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function for_user( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return [];
		}
		global $wpdb;
		$table = Schema::membership_table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC", $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! is_array( $rows ) ) {
			return [];
		}
		return array_map( [ self::class, 'shape' ], $rows );
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	private static function shape( array $row ): array {
		return [
			'id'         => (int) ( $row['id'] ?? 0 ),
			'org_id'     => (int) ( $row['org_id'] ?? 0 ),
			'role'       => (string) ( $row['role'] ?? '' ),
			'status'     => (string) ( $row['status'] ?? 'active' ),
			'valid_from' => isset( $row['valid_from'] ) ? (string) $row['valid_from'] : null,
			'valid_to'   => isset( $row['valid_to'] ) ? (string) $row['valid_to'] : null,
		];
	}
}
