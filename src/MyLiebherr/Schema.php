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

	public static function dashboard_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_myl_dashboard_layout';
	}

	public static function dream_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_myl_dream_item';
	}

	public static function gallery_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_myl_gallery_item';
	}

	public static function share_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_myl_share_grant';
	}

	public static function contact_request_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_myl_contact_request';
	}

	public static function connection_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_myl_connection';
	}

	public static function service_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_myl_service_exchange';
	}

	public static function machine_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_myl_machine';
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

		$d = self::dashboard_table();
		dbDelta( "CREATE TABLE {$d} (
			id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id     BIGINT UNSIGNED NOT NULL,
			device      VARCHAR(16)  NOT NULL DEFAULT 'default',
			layout_json LONGTEXT      NOT NULL,
			version     INT UNSIGNED  NOT NULL DEFAULT 1,
			updated_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_user_device (user_id, device)
		) {$charset} COMMENT='Liebherr My Liebherr – persoenliches Dashboard-Layout je Nutzer und Geraetetyp';" );

		$dr = self::dream_table();
		dbDelta( "CREATE TABLE {$dr} (
			id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id     BIGINT UNSIGNED NOT NULL,
			media_id    BIGINT UNSIGNED NOT NULL DEFAULT 0,
			machine_ref VARCHAR(120) NOT NULL DEFAULT '',
			title_words VARCHAR(120) NOT NULL DEFAULT '',
			note        TEXT         NULL,
			tags        VARCHAR(255) NOT NULL DEFAULT '',
			collection  VARCHAR(80)  NOT NULL DEFAULT '',
			cover       TINYINT      NOT NULL DEFAULT 0,
			wish_status VARCHAR(16)  NOT NULL DEFAULT 'idea',
			sort_rank   INT UNSIGNED NOT NULL DEFAULT 0,
			created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_user (user_id),
			KEY idx_rank (user_id, sort_rank)
		) {$charset} COMMENT='Liebherr My Liebherr – My Dreams persoenliches Maschinen-Bilderbuch';" );

		$gl = self::gallery_table();
		dbDelta( "CREATE TABLE {$gl} (
			id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			owner_id        BIGINT UNSIGNED NOT NULL,
			media_id        BIGINT UNSIGNED NOT NULL DEFAULT 0,
			title           VARCHAR(160) NOT NULL DEFAULT '',
			description     TEXT         NULL,
			tags            VARCHAR(255) NOT NULL DEFAULT '',
			album           VARCHAR(80)  NOT NULL DEFAULT '',
			visibility      VARCHAR(16)  NOT NULL DEFAULT 'private',
			status          VARCHAR(16)  NOT NULL DEFAULT 'active',
			current_version INT UNSIGNED NOT NULL DEFAULT 1,
			created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_owner (owner_id),
			KEY idx_visibility (visibility)
		) {$charset} COMMENT='Liebherr My Liebherr – Own Gallery private Bilder und Medien';" );

		$sh = self::share_table();
		dbDelta( "CREATE TABLE {$sh} (
			id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			item_type      VARCHAR(16)  NOT NULL DEFAULT 'gallery',
			item_id        BIGINT UNSIGNED NOT NULL,
			grantor_id     BIGINT UNSIGNED NOT NULL,
			recipient_type VARCHAR(16)  NOT NULL DEFAULT 'user',
			recipient_id   BIGINT UNSIGNED NOT NULL DEFAULT 0,
			scope          VARCHAR(16)  NOT NULL DEFAULT 'view',
			expires_at     DATETIME     NULL,
			status         VARCHAR(16)  NOT NULL DEFAULT 'active',
			created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_item (item_type, item_id),
			KEY idx_grantor (grantor_id),
			KEY idx_recipient (recipient_type, recipient_id)
		) {$charset} COMMENT='Liebherr My Liebherr – Freigaben Kollegen und World';" );

		$cr = self::contact_request_table();
		dbDelta( "CREATE TABLE {$cr} (
			id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			requester_id BIGINT UNSIGNED NOT NULL,
			recipient_id BIGINT UNSIGNED NOT NULL,
			purpose      VARCHAR(160) NOT NULL DEFAULT '',
			service_hint VARCHAR(160) NOT NULL DEFAULT '',
			token_frame  INT UNSIGNED NOT NULL DEFAULT 0,
			status       VARCHAR(16)  NOT NULL DEFAULT 'requested',
			created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_requester (requester_id),
			KEY idx_recipient (recipient_id),
			KEY idx_status (status)
		) {$charset} COMMENT='Liebherr My Liebherr – Kontaktanfragen';" );

		$cn = self::connection_table();
		dbDelta( "CREATE TABLE {$cn} (
			id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			party_a           BIGINT UNSIGNED NOT NULL,
			party_b           BIGINT UNSIGNED NOT NULL,
			agreement_version INT UNSIGNED NOT NULL DEFAULT 1,
			limits            VARCHAR(160) NOT NULL DEFAULT '',
			status            VARCHAR(16)  NOT NULL DEFAULT 'accepted',
			valid_from        DATETIME     NULL,
			valid_to          DATETIME     NULL,
			created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_a (party_a),
			KEY idx_b (party_b),
			KEY idx_status (status)
		) {$charset} COMMENT='Liebherr My Liebherr – Contact Connections zwischen Profilen';" );

		$se = self::service_table();
		dbDelta( "CREATE TABLE {$se} (
			id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			connection_id   BIGINT UNSIGNED NOT NULL,
			provider_id     BIGINT UNSIGNED NOT NULL,
			receiver_id     BIGINT UNSIGNED NOT NULL,
			description     VARCHAR(200) NOT NULL DEFAULT '',
			token_amount    INT UNSIGNED NOT NULL DEFAULT 0,
			status          VARCHAR(16)  NOT NULL DEFAULT 'proposed',
			idempotency_key VARCHAR(64)  NOT NULL,
			created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_idem (idempotency_key),
			KEY idx_connection (connection_id)
		) {$charset} COMMENT='Liebherr My Liebherr – gemeinsame Leistungen je Connection';" );

		$mc = self::machine_table();
		dbDelta( "CREATE TABLE {$mc} (
			id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id    BIGINT UNSIGNED NOT NULL,
			name       VARCHAR(160) NOT NULL DEFAULT '',
			serial     VARCHAR(120) NOT NULL DEFAULT '',
			location   VARCHAR(160) NOT NULL DEFAULT '',
			note       TEXT         NULL,
			doc_url    VARCHAR(300) NOT NULL DEFAULT '',
			created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_user (user_id)
		) {$charset} COMMENT='Liebherr My Liebherr – persoenlich zugeordnete Maschinen';" );
	}
}
