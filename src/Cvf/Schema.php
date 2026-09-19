<?php
/**
 * Liebherr World – Customer View Flow: DB-Schema des Durchstichs (ADR-LIW-CVF-001 §12).
 *
 * Drei Tabellen:
 *   - liw_cvf_workflow_version : veröffentlichte Workflow-Version, UNVERÄNDERLICH, mit Prüfsumme.
 *   - liw_cvf_visitor_session  : Besucher-Sitzung (anonym), verweist auf die genutzte Version + Zustand.
 *   - liw_cvf_execution_log    : append-only Protokoll jedes Zustandsübergangs (Nachvollziehbarkeit §17).
 *
 * WICHTIG (Falle alpha.16): Tabellen-/Spalten-COMMENT ohne runde Klammern (dbDelta ist gierig).
 *
 * @package Liebherr\InterfaceWorld\Cvf
 * @since   0.1.0-alpha.83
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Cvf;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Schema {

	public static function version_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_cvf_workflow_version';
	}

	public static function session_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_cvf_visitor_session';
	}

	public static function log_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_cvf_execution_log';
	}

	public static function create_tables(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$v = self::version_table();
		dbDelta( "CREATE TABLE {$v} (
			id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			version        VARCHAR(32)  NOT NULL,
			schema_version INT UNSIGNED NOT NULL DEFAULT 1,
			state          VARCHAR(16)  NOT NULL DEFAULT 'published',
			config_json    LONGTEXT     NOT NULL,
			checksum       CHAR(64)     NOT NULL,
			published_at   DATETIME     NULL,
			published_by   BIGINT UNSIGNED NULL,
			created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_version (version),
			KEY idx_state (state),
			KEY idx_checksum (checksum)
		) {$charset} COMMENT='Liebherr CVF – veroeffentlichte Workflow-Versionen unveraenderlich';" );

		$s = self::session_table();
		dbDelta( "CREATE TABLE {$s} (
			id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			anon_visitor_id     VARCHAR(64)  NOT NULL,
			workflow_version_id BIGINT UNSIGNED NOT NULL,
			state               VARCHAR(32)  NOT NULL,
			issued_at           DATETIME     NOT NULL,
			expires_at          DATETIME     NULL,
			updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_visitor (anon_visitor_id),
			KEY idx_version (workflow_version_id),
			KEY idx_state (state)
		) {$charset} COMMENT='Liebherr CVF – anonyme Besucher-Sitzungen des Durchstichs';" );

		$l = self::log_table();
		dbDelta( "CREATE TABLE {$l} (
			id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id          BIGINT UNSIGNED NOT NULL,
			workflow_version_id BIGINT UNSIGNED NOT NULL,
			event               VARCHAR(32)  NOT NULL,
			from_state          VARCHAR(32)  NOT NULL,
			to_state            VARCHAR(32)  NOT NULL,
			action              VARCHAR(32)  NOT NULL,
			reason              VARCHAR(32)  NOT NULL,
			created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_session (session_id),
			KEY idx_event (event)
		) {$charset} COMMENT='Liebherr CVF – append-only Protokoll der Zustandsuebergaenge';" );
	}
}
