<?php
/**
 * Liebherr Intelligence World – DB-Schema (Fundament, Pflichtenheft-2 §12).
 *
 * Zwei Kern-Tabellen der Abrechnungs-/Nutzungsschicht:
 *   - liw_iw_session : Sitzungen (Start/Ende/Heartbeat/aktive Dauer/Status), §12 „Session".
 *   - liw_iw_event   : append-only Ereignis-Ledger (§10.10/§11/§16) mit Hash-Kette + Sequenz je Sitzung;
 *                      manipulationsgeschützt (jede Zeile verweist auf den Hash der vorherigen).
 *
 * Prototyp (§21): kein echtes Payment; Beträge werden dennoch korrekt als Integer-Minor-Units geführt und
 * Zeitstempel in UTC gespeichert, damit die spätere echte Abrechnung anschlussfähig ist.
 *
 * WICHTIG (Falle alpha.16): Tabellen-COMMENT ohne runde Klammern (dbDelta-gierig, s. InterfaceCatalogSchema).
 *
 * @package Liebherr\InterfaceWorld\IntelligenceWorld
 * @since   0.1.0-alpha.47
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\IntelligenceWorld;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Schema {

	public static function session_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_iw_session';
	}

	public static function event_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_iw_event';
	}

	public static function create_tables(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$session = self::session_table();
		dbDelta( "CREATE TABLE {$session} (
			id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_code       VARCHAR(64)  NOT NULL,
			user_ref           BIGINT UNSIGNED NULL,
			mandant            VARCHAR(64)  NULL,
			status             VARCHAR(20)  NOT NULL DEFAULT 'active',
			price_rule_version VARCHAR(32)  NULL,
			started_at         DATETIME     NOT NULL,
			ended_at           DATETIME     NULL,
			last_heartbeat_at  DATETIME     NULL,
			active_seconds     INT UNSIGNED NOT NULL DEFAULT 0,
			meta               LONGTEXT     NULL COMMENT 'JSON: Beispieldaten/Kontext im Prototyp',
			created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at         DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_session_code (session_code),
			KEY idx_status (status),
			KEY idx_user (user_ref)
		) {$charset} COMMENT='Liebherr Intelligence World – Sitzungen, Pflichtenheft-2 §12 liw_iw_session';" );

		$event = self::event_table();
		dbDelta( "CREATE TABLE {$event} (
			id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_uid          VARCHAR(64)  NOT NULL,
			session_code       VARCHAR(64)  NOT NULL,
			seq                BIGINT UNSIGNED NOT NULL DEFAULT 0,
			type               VARCHAR(48)  NOT NULL,
			module             VARCHAR(64)  NULL,
			occurred_at        DATETIME     NOT NULL,
			price_rule_version VARCHAR(32)  NULL,
			dedupe_key         VARCHAR(128) NULL,
			metadata           LONGTEXT     NULL COMMENT 'JSON: technische Metadaten des Ereignisses',
			prev_hash          CHAR(64)     NULL,
			integrity_hash     CHAR(64)     NOT NULL,
			created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_event_uid (event_uid),
			UNIQUE KEY uniq_dedupe (session_code, dedupe_key),
			KEY idx_session_seq (session_code, seq),
			KEY idx_type (type)
		) {$charset} COMMENT='Liebherr Intelligence World – Ereignis-Ledger append-only, Pflichtenheft-2 §11 liw_iw_event';" );
	}
}
