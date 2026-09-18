<?php
/**
 * Liebherr Interface Solutions – Interface Catalog Schema
 *
 * DB-Schema für liw_interface (Schnittstellenkatalog, Liebherr-Pflichtenheft §17/§18
 * Interface Board). Fachlich neu – kein Vorbild im Core (Kernstück der ersten
 * Ausbaustufe, s. Machbarkeitsprüfung).
 *
 * @package Liebherr\InterfaceWorld\Interfaces
 * @since   0.1.0-alpha.1
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Interfaces;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class InterfaceCatalogSchema {

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_interface';
	}

	public static function create_table(): void {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();
		$table   = self::table_name();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// dbDelta kennt kein ENUM — VARCHAR mit Anwendungsvalidierung (Muster: CertificationSchema).
		// Regel für alle Schemata dieses Plugins (Befund Docker-Praxistest 18.09.2026, alpha.16):
		// Tabellen-COMMENT ohne runde Klammern! dbDelta() extrahiert den Spaltenblock gierig bis
		// zur LETZTEN ')' der Anweisung – eine Klammer im COMMENT erzeugt bei jedem erneuten
		// Abgleich (maybe_upgrade_database) ein fehlerhaftes "ALTER TABLE … ADD COLUMN )".
		// Geprüft von tests/run-tests.php (statisch) und scripts/liw-selftest.php [0] (live).
		dbDelta( "CREATE TABLE {$table} (
			id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code             VARCHAR(64)  NOT NULL,
			name             VARCHAR(255) NOT NULL,
			direction        VARCHAR(20)  NOT NULL DEFAULT 'inbound',
			protocol         VARCHAR(64)  NULL,
			version          VARCHAR(32)  NOT NULL DEFAULT '0.1.0',
			lifecycle_status VARCHAR(20)  NOT NULL DEFAULT 'draft',
			doc_reference    VARCHAR(500) NULL,
			public_metadata  LONGTEXT     NULL COMMENT 'JSON: nur oeffentlich freigegebene Metadaten (Pflichtenheft §17)',
			created_by       BIGINT UNSIGNED NULL,
			created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at       DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_code (code),
			KEY idx_status (lifecycle_status)
		) {$charset} COMMENT='Liebherr Interface World – Schnittstellenkatalog, Pflichtenheft §17 liw_interface';" );
	}
}
