<?php
/**
 * Liebherr World – Pocket Information: DB-Schema (Pflichtenheft My Liebherr §34/§37, ADR-LIW-MYL-001 R5).
 *
 * Eine Tabelle: liw_pocket_item – personenbezogene Kurzinfos mit Quelle, Priorität, Gültigkeit, Rücksprungziel
 * und (optionaler) Pflichtquittierung. COMMENT ohne runde Klammern (dbDelta-Regel).
 *
 * @package Liebherr\InterfaceWorld\Pocket
 * @since   0.1.0-alpha.124
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Pocket;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Schema {

	public static function item_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_pocket_item';
	}

	public static function create_tables(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$t = self::item_table();
		dbDelta( "CREATE TABLE {$t} (
			id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id         BIGINT UNSIGNED NOT NULL,
			source          VARCHAR(80)  NOT NULL DEFAULT '',
			title           VARCHAR(180) NOT NULL DEFAULT '',
			body            TEXT         NULL,
			priority        VARCHAR(16)  NOT NULL DEFAULT 'normal',
			validity_until  DATETIME     NULL,
			return_route    VARCHAR(300) NOT NULL DEFAULT '',
			requires_ack    TINYINT      NOT NULL DEFAULT 0,
			acknowledged_at DATETIME     NULL,
			created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_user (user_id),
			KEY idx_priority (priority)
		) {$charset} COMMENT='Liebherr Pocket Information – personenbezogene Kurzinfos';" );
	}
}
