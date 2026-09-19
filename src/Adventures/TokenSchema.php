<?php
/**
 * Liebherr Adventures – DB-Schema des Token-/Registrierungs-Ledgers (Basislogik §5/§6/§9).
 *
 * Tabelle liw_adv_ledger: append-only, revisionssicher (Hash-Kette je Beitrag). Dokumentiert alle
 * nachweispflichtigen Vorgänge: Registrierung, Validierung, Veröffentlichung, Preisänderung und jeden
 * Tokenzugriff (Nutzer, Beitrag+Version, Zeitpunkt, akzeptierter Tokenwert, Nutzungsumfang, Org-Einheit,
 * technische Transaktions-ID).
 *
 * WICHTIG (Falle alpha.16): Tabellen-/Spalten-COMMENT ohne runde Klammern (dbDelta ist gierig).
 *
 * @package Liebherr\InterfaceWorld\Adventures
 * @since   0.1.0-alpha.59
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Adventures;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class TokenSchema {

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_adv_ledger';
	}

	public static function create_table(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$t = self::table();
		dbDelta( "CREATE TABLE {$t} (
			id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			entry_uid          VARCHAR(64)  NOT NULL,
			contribution_id    BIGINT UNSIGNED NOT NULL,
			contribution_uuid  VARCHAR(64)  NULL,
			seq                BIGINT UNSIGNED NOT NULL DEFAULT 0,
			kind               VARCHAR(32)  NOT NULL,
			version            INT UNSIGNED NOT NULL DEFAULT 1,
			user_ref           BIGINT UNSIGNED NULL,
			org_unit           VARCHAR(64)  NULL,
			token_value        INT UNSIGNED NOT NULL DEFAULT 0,
			usage_scope        VARCHAR(64)  NULL,
			transaction_id     VARCHAR(64)  NULL,
			occurred_at        DATETIME     NOT NULL,
			dedupe_key         VARCHAR(128) NULL,
			metadata           LONGTEXT     NULL COMMENT 'JSON: Vorgangs-Metadaten',
			prev_hash          CHAR(64)     NULL,
			integrity_hash     CHAR(64)     NOT NULL,
			created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_entry_uid (entry_uid),
			UNIQUE KEY uniq_dedupe (contribution_id, dedupe_key),
			KEY idx_contrib_seq (contribution_id, seq),
			KEY idx_kind (kind),
			KEY idx_user (user_ref),
			KEY idx_transaction (transaction_id)
		) {$charset} COMMENT='Liebherr Adventures – Token-/Registrierungs-Ledger append-only, Basislogik liw_adv_ledger';" );
	}
}
