<?php
/**
 * Liebherr Interface Solutions – Connection Schema
 *
 * DB-Schema für liw_connection (World Connections Map, Liebherr-Pflichtenheft §17/LP-06).
 * Nur Anzeige-Status, keine echten Standort-/Kundendaten (Nichtziel §4: keine produktiven
 * Kundendaten in Demo-/Sandbox-Bereichen).
 *
 * @package Liebherr\InterfaceWorld\Connection
 * @since   0.1.0-alpha.1
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Connection;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ConnectionSchema {

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_connection';
	}

	public static function create_table(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = self::table_name();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( "CREATE TABLE {$table} (
			id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			region         VARCHAR(255) NOT NULL,
			partner_type   VARCHAR(20)  NOT NULL DEFAULT 'dealer',
			display_status VARCHAR(20)  NOT NULL DEFAULT 'planned',
			public_flag    TINYINT(1)   NOT NULL DEFAULT 0,
			created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at     DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_region (region),
			KEY idx_public (public_flag)
		) {$charset} COMMENT='Liebherr Interface World – World Connections Map (§17 liw_connection, keine realen Standorte ohne Freigabe LP-06)';" );
	}
}
