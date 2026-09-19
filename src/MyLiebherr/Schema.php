<?php
/**
 * Liebherr World – My Liebherr: DB-Schema (Pflichtenheft My Liebherr §17, ADR-LIW-MYL-001 S1).
 *
 * Zwei Tabellen des Fundaments:
 *   - liw_myl_profile    : persönliches Profil je Nutzer (Persona, Sprache, Zeitzone, aktive Org/Rolle, Datenschutzstand).
 *   - liw_myl_membership : Mitgliedschaften Nutzer × Organisation × Rolle mit Status und Gültigkeit.
 *
 * WICHTIG (Falle alpha.16): Tabellen-/Spalten-COMMENT ohne runde Klammern (dbDelta ist gierig).
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.108
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Schema {

	public static function profile_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_myl_profile';
	}

	public static function membership_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_myl_membership';
	}

	public static function create_tables(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$p = self::profile_table();
		dbDelta( "CREATE TABLE {$p} (
			id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id         BIGINT UNSIGNED NOT NULL,
			persona         VARCHAR(40)  NOT NULL DEFAULT '',
			locale          VARCHAR(10)  NOT NULL DEFAULT '',
			timezone        VARCHAR(64)  NOT NULL DEFAULT '',
			active_org_id   BIGINT UNSIGNED NOT NULL DEFAULT 0,
			active_role     VARCHAR(40)  NOT NULL DEFAULT '',
			privacy_version INT UNSIGNED NOT NULL DEFAULT 0,
			created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_user (user_id)
		) {$charset} COMMENT='Liebherr My Liebherr – persoenliches Profil je Nutzer';" );

		$m = self::membership_table();
		dbDelta( "CREATE TABLE {$m} (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id    BIGINT UNSIGNED NOT NULL,
			org_id     BIGINT UNSIGNED NOT NULL DEFAULT 0,
			role       VARCHAR(40)  NOT NULL DEFAULT '',
			status     VARCHAR(16)  NOT NULL DEFAULT 'active',
			valid_from DATETIME     NULL,
			valid_to   DATETIME     NULL,
			created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_membership (user_id, org_id, role),
			KEY idx_user (user_id),
			KEY idx_status (status)
		) {$charset} COMMENT='Liebherr My Liebherr – Mitgliedschaften Nutzer Organisation Rolle';" );
	}
}
