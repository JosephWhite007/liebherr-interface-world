<?php
/**
 * Liebherr Interface Solutions – Händler-Tabelle `liw_dealer` (Stufe 1d).
 *
 * Zweck: Die Plattform bindet viele Händler weltweit an die Liebherr Dealer Interface API an – jeder mit eigenen
 * Zugangsdaten (Subscription-Key, UserEmail), eigener SalesAccountNumber und eigenem Schreibschalter. Diese Tabelle
 * ist die eine Wahrheit „welcher Händler, welche Schnittstellendaten" (Entscheidung JW 24.10.2026: Eingabefeld je
 * Händler statt globaler Env-Werte). Der API-Key liegt nur verschlüsselt (SecretBridge, Master Key aus Env).
 *
 * Regel dieses Plugins: Tabellen-COMMENT ohne runde Klammern (dbDelta-Falle, s. InterfaceCatalogSchema).
 *
 * @package Liebherr\InterfaceWorld\SalesInterface
 * @since   0.1.0-alpha.148
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\SalesInterface;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class DealerSchema {

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_dealer';
	}

	public static function create_table(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = self::table_name();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( "CREATE TABLE {$table} (
			id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			code                 VARCHAR(40)  NOT NULL,
			name                 VARCHAR(190) NOT NULL,
			country              VARCHAR(2)   NOT NULL DEFAULT '',
			environment          VARCHAR(12)  NOT NULL DEFAULT 'test',
			sales_account_number VARCHAR(40)  NOT NULL DEFAULT '',
			user_email           VARCHAR(190) NOT NULL DEFAULT '',
			api_key_enc          TEXT         NULL,
			write_enabled        TINYINT(1)   NOT NULL DEFAULT 0,
			status               VARCHAR(12)  NOT NULL DEFAULT 'active',
			notes                TEXT         NULL,
			created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_code (code),
			KEY idx_status (status)
		) {$charset} COMMENT='Liebherr Dealer Interface – Haendler mit eigenen Schnittstellen-Zugangsdaten';" );
	}
}
