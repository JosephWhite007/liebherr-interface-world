<?php
/**
 * Liebherr World – CAPDB Datenmodell (ADR-LIW-CVF-002 §3, Pflichtenheft §30).
 *
 * Sieben Tabellen für das Workflow Administration Board. Alle board-/instanzbezogenen Zeilen sind an eine
 * `liw_cvf_workflow_version` gebunden (Entwurf = state 'draft', veröffentlicht = 'published' + Prüfsumme).
 * `plugin_type` ist zentral gepflegt und NICHT versioniert. `plugin_execution` ist append-only.
 *
 * WICHTIG (Falle alpha.16): Tabellen-/Spalten-COMMENT ohne runde Klammern (dbDelta ist gierig).
 *
 * @package Liebherr\InterfaceWorld\Cvf
 * @since   0.1.0-alpha.89
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Cvf;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BoardSchema {

	public static function area_table(): string { global $wpdb; return $wpdb->prefix . 'liw_cvf_board_area'; }
	public static function edge_table(): string { global $wpdb; return $wpdb->prefix . 'liw_cvf_board_edge'; }
	public static function plugin_type_table(): string { global $wpdb; return $wpdb->prefix . 'liw_cvf_plugin_type'; }
	public static function plugin_instance_table(): string { global $wpdb; return $wpdb->prefix . 'liw_cvf_plugin_instance'; }
	public static function plugin_schedule_table(): string { global $wpdb; return $wpdb->prefix . 'liw_cvf_plugin_schedule'; }
	public static function plugin_execution_table(): string { global $wpdb; return $wpdb->prefix . 'liw_cvf_plugin_execution'; }
	public static function layout_table(): string { global $wpdb; return $wpdb->prefix . 'liw_cvf_board_layout'; }

	public static function create_tables(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$a = self::area_table();
		dbDelta( "CREATE TABLE {$a} (
			id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			version_id    BIGINT UNSIGNED NOT NULL,
			module_id     VARCHAR(64)  NOT NULL,
			position      INT UNSIGNED NOT NULL DEFAULT 0,
			route_id      VARCHAR(128) NULL,
			status        VARCHAR(16)  NOT NULL DEFAULT 'active',
			validity_json LONGTEXT     NULL,
			PRIMARY KEY (id),
			KEY idx_version (version_id),
			KEY idx_module (module_id)
		) {$charset} COMMENT='Liebherr CAPDB – Timeline-Bereiche je Version';" );

		$e = self::edge_table();
		dbDelta( "CREATE TABLE {$e} (
			id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			version_id     BIGINT UNSIGNED NOT NULL,
			from_area_id   BIGINT UNSIGNED NOT NULL,
			to_area_id     BIGINT UNSIGNED NOT NULL,
			trigger_type   VARCHAR(32)  NOT NULL DEFAULT 'manual',
			condition_json LONGTEXT     NULL,
			priority       INT UNSIGNED NOT NULL DEFAULT 100,
			PRIMARY KEY (id),
			KEY idx_version (version_id),
			KEY idx_from (from_area_id),
			KEY idx_to (to_area_id)
		) {$charset} COMMENT='Liebherr CAPDB – administrierbare Uebergaenge zwischen Bereichen';" );

		$pt = self::plugin_type_table();
		dbDelta( "CREATE TABLE {$pt} (
			id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			plugin_key           VARCHAR(64)  NOT NULL,
			category             VARCHAR(32)  NOT NULL,
			manifest_version     INT UNSIGNED NOT NULL DEFAULT 1,
			allowed_scopes       VARCHAR(64)  NOT NULL,
			parameter_schema_json LONGTEXT    NULL,
			capability_class     VARCHAR(64)  NOT NULL DEFAULT 'display',
			PRIMARY KEY (id),
			UNIQUE KEY uniq_key (plugin_key)
		) {$charset} COMMENT='Liebherr CAPDB – registrierte Plugin-Typen zentral, unversioniert';" );

		$pi = self::plugin_instance_table();
		dbDelta( "CREATE TABLE {$pi} (
			id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			version_id     BIGINT UNSIGNED NOT NULL,
			plugin_type_id BIGINT UNSIGNED NOT NULL,
			host_type      VARCHAR(8)   NOT NULL,
			host_id        BIGINT UNSIGNED NOT NULL,
			status         VARCHAR(16)  NOT NULL DEFAULT 'configured',
			priority       INT UNSIGNED NOT NULL DEFAULT 100,
			config_json    LONGTEXT     NULL,
			PRIMARY KEY (id),
			KEY idx_version (version_id),
			KEY idx_host (host_type, host_id),
			KEY idx_type (plugin_type_id)
		) {$charset} COMMENT='Liebherr CAPDB – Plugin-Instanzen auf Seiten oder Uebergaengen';" );

		$ps = self::plugin_schedule_table();
		dbDelta( "CREATE TABLE {$ps} (
			id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			instance_id   BIGINT UNSIGNED NOT NULL,
			time_origin   VARCHAR(32)  NOT NULL DEFAULT 'page.entered',
			open_at_ms    INT UNSIGNED NULL,
			close_at_ms   INT UNSIGNED NULL,
			duration_ms   INT UNSIGNED NULL,
			minimum_open_ms INT UNSIGNED NULL,
			timeout_ms    INT UNSIGNED NULL,
			repeat_policy VARCHAR(32)  NOT NULL DEFAULT 'once_per_version',
			resume_policy VARCHAR(16)  NOT NULL DEFAULT 'continue',
			cancel_on     VARCHAR(32)  NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_instance (instance_id)
		) {$charset} COMMENT='Liebherr CAPDB – Zeit-/Timersteuerung je Plugin-Instanz';" );

		$pe = self::plugin_execution_table();
		dbDelta( "CREATE TABLE {$pe} (
			id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id     BIGINT UNSIGNED NOT NULL,
			instance_id    BIGINT UNSIGNED NOT NULL,
			state          VARCHAR(16)  NOT NULL,
			opened_at      DATETIME     NULL,
			closed_at      DATETIME     NULL,
			result         VARCHAR(32)  NULL,
			safe_error_code VARCHAR(32) NULL,
			created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_session (session_id),
			KEY idx_instance (instance_id)
		) {$charset} COMMENT='Liebherr CAPDB – append-only Plugin-Ausfuehrungsprotokoll';" );

		$bl = self::layout_table();
		dbDelta( "CREATE TABLE {$bl} (
			id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			version_id         BIGINT UNSIGNED NOT NULL,
			viewport           VARCHAR(16)  NOT NULL DEFAULT 'desktop',
			node_positions_json LONGTEXT     NULL,
			zoom               INT UNSIGNED NOT NULL DEFAULT 100,
			updated_by         BIGINT UNSIGNED NULL,
			updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_version_viewport (version_id, viewport)
		) {$charset} COMMENT='Liebherr CAPDB – Board-Layout Positionen und Zoom je Version';" );
	}
}
