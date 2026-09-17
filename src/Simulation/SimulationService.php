<?php
/**
 * Liebherr Interface Solutions – Simulation Service
 *
 * CRUD-Schicht für Simulationswelten und Testszenarien. Änderungen laufen über
 * CoreBridge\AuditBridge (SEC-005).
 *
 * @package Liebherr\InterfaceWorld\Simulation
 * @since   0.1.0-alpha.1
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Simulation;

use Liebherr\InterfaceWorld\CoreBridge\AuditBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SimulationService {

	/** @param array{name:string,scope?:?string,version?:string} $data */
	public static function create_world( array $data, int $actor_id ): int|\WP_Error {
		global $wpdb;

		$name = sanitize_text_field( $data['name'] ?? '' );
		if ( '' === $name ) {
			return new \WP_Error( 'liw_invalid_input', __( 'Name der Simulationswelt ist Pflichtfeld.', 'liebherr-interface-world' ) );
		}

		$row = [
			'name'              => $name,
			'scope'             => isset( $data['scope'] ) ? sanitize_text_field( $data['scope'] ) : null,
			'version'           => sanitize_text_field( $data['version'] ?? '0.1.0' ),
			'validation_status' => 'draft',
			'created_by'        => $actor_id,
		];

		$inserted = $wpdb->insert( SimulationSchema::worlds_table(), $row );
		if ( false === $inserted ) {
			return new \WP_Error( 'liw_db_error', __( 'Simulationswelt konnte nicht gespeichert werden.', 'liebherr-interface-world' ) );
		}

		$id = (int) $wpdb->insert_id;
		AuditBridge::log( 'create', 'simulation_world', $id, [], $row, $actor_id );
		return $id;
	}

	/** @param array{world_id:int,category:string,expected_result?:?string} $data */
	public static function add_scenario( array $data, int $actor_id ): int|\WP_Error {
		global $wpdb;

		$world_id = (int) ( $data['world_id'] ?? 0 );
		$category = sanitize_text_field( $data['category'] ?? '' );

		if ( $world_id <= 0 || '' === $category ) {
			return new \WP_Error( 'liw_invalid_input', __( 'world_id und category sind Pflichtfelder.', 'liebherr-interface-world' ) );
		}

		$row = [
			'world_id'        => $world_id,
			'category'        => $category,
			'expected_result' => isset( $data['expected_result'] ) ? sanitize_textarea_field( $data['expected_result'] ) : null,
			'status'          => 'pending',
		];

		$inserted = $wpdb->insert( SimulationSchema::scenarios_table(), $row );
		if ( false === $inserted ) {
			return new \WP_Error( 'liw_db_error', __( 'Testszenario konnte nicht gespeichert werden.', 'liebherr-interface-world' ) );
		}

		$id = (int) $wpdb->insert_id;
		AuditBridge::log( 'create', 'test_scenario', $id, [], $row, $actor_id );
		return $id;
	}

	/** @return array<int, array<string, mixed>> */
	public static function get_worlds(): array {
		global $wpdb;
		$table = SimulationSchema::worlds_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC", ARRAY_A );
		return is_array( $rows ) ? $rows : [];
	}

	/** @return array<int, array<string, mixed>> */
	public static function get_scenarios_for_world( int $world_id ): array {
		global $wpdb;
		$table = SimulationSchema::scenarios_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE world_id = %d ORDER BY id ASC", $world_id ), ARRAY_A );
		return is_array( $rows ) ? $rows : [];
	}
}
