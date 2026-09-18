<?php
/**
 * Liebherr Interface Solutions – Simulation Schema
 *
 * DB-Schema für liw_simulation_world und liw_test_scenario (Magic Cube / Sandbox,
 * Liebherr-Pflichtenheft §17). Fachlich neu – kein Vorbild im Core.
 *
 * @package Liebherr\InterfaceWorld\Simulation
 * @since   0.1.0-alpha.1
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Simulation;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SimulationSchema {

	public static function worlds_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_simulation_world';
	}

	public static function scenarios_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_test_scenario';
	}

	public static function create_tables(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$worlds = self::worlds_table();
		dbDelta( "CREATE TABLE {$worlds} (
			id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name              VARCHAR(255) NOT NULL,
			scope             VARCHAR(255) NULL,
			version           VARCHAR(32)  NOT NULL DEFAULT '0.1.0',
			validation_status VARCHAR(20)  NOT NULL DEFAULT 'draft',
			created_by        BIGINT UNSIGNED NULL,
			created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at        DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_validation (validation_status)
		) {$charset} COMMENT='Liebherr Interface World – Simulationswelten, Magic Cube, §17 liw_simulation_world';" );

		$scenarios = self::scenarios_table();
		dbDelta( "CREATE TABLE {$scenarios} (
			id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			world_id        BIGINT UNSIGNED NOT NULL,
			category        VARCHAR(100) NOT NULL,
			expected_result TEXT NULL,
			status          VARCHAR(20)  NOT NULL DEFAULT 'pending',
			last_run_at     DATETIME NULL,
			created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_world (world_id),
			KEY idx_status (status)
		) {$charset} COMMENT='Liebherr Interface World – Testszenarien je Simulationswelt, §17 liw_test_scenario';" );
	}
}
