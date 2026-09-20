<?php
/**
 * Liebherr World – Plattformzeit: DB-Schema (Pflichtenheft My Liebherr §41.3, ADR-LIW-MYL-001 S9/S10).
 *
 *   - liw_ptime_session : Plattform-Zeitabschnitt je Nutzer (serverautoritäre Zeit + Zustand).
 *   - liw_ptime_charge  : append-only Token-Abrechnungssatz je abgeschlossenem Abschnitt (Nachweis; Buchung folgt Wallet).
 *
 * WICHTIG (Falle alpha.16): Tabellen-/Spalten-COMMENT ohne runde Klammern (dbDelta ist gierig).
 *
 * @package Liebherr\InterfaceWorld\PlatformTime
 * @since   0.1.0-alpha.111
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\PlatformTime;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Schema {

	public static function session_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_ptime_session';
	}

	public static function charge_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_ptime_charge';
	}

	public static function create_tables(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$s = self::session_table();
		dbDelta( "CREATE TABLE {$s} (
			id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id            BIGINT UNSIGNED NOT NULL,
			status             VARCHAR(16)  NOT NULL DEFAULT 'running',
			rule_version       VARCHAR(32)  NOT NULL DEFAULT '',
			active_seconds     INT UNSIGNED NOT NULL DEFAULT 0,
			paused_seconds     INT UNSIGNED NOT NULL DEFAULT 0,
			started_at         DATETIME     NOT NULL,
			paused_at          DATETIME     NULL,
			last_seen_at       DATETIME     NOT NULL,
			ended_at           DATETIME     NULL,
			created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_user (user_id),
			KEY idx_status (status)
		) {$charset} COMMENT='Liebherr Plattformzeit – serverautoritaerer Zeitabschnitt je Nutzer';" );

		$c = self::charge_table();
		dbDelta( "CREATE TABLE {$c} (
			id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id      BIGINT UNSIGNED NOT NULL,
			user_id         BIGINT UNSIGNED NOT NULL,
			token_amount    INT UNSIGNED NOT NULL DEFAULT 0,
			rule_version    VARCHAR(32)  NOT NULL DEFAULT '',
			status          VARCHAR(16)  NOT NULL DEFAULT 'pending',
			idempotency_key VARCHAR(64)  NOT NULL,
			wallet_ref      VARCHAR(64)  NOT NULL DEFAULT '',
			created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_idem (idempotency_key),
			KEY idx_session (session_id),
			KEY idx_user (user_id)
		) {$charset} COMMENT='Liebherr Plattformzeit – append-only Token-Abrechnungssatz je Abschnitt';" );
	}
}
