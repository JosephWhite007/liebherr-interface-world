<?php
/**
 * Liebherr Interface Solutions – Contact Schema
 *
 * DB-Schema für liw_contact_request (Liebherr-Pflichtenheft §22 „Kontakt und Onboarding
 * Formular", Feldliste; LP-13 Kontakt; §24 Datenschutz: Export-/Löschprozesse vorbereiten).
 *
 * Datenmodell-Prüfung vor Anlage (CLAUDE.md „Datenbank"): Eine Kontaktanfrage ist KEIN
 * Partner – Rollen wie „Zentrale" oder „Sonstige" dürfen keinen `ary_partners`-Datensatz
 * erzeugen (anders als das Onboarding, das bewusst einen Partner im Status pending anlegt).
 * `liw_partner_extra` passt daher nicht; eine eigene, schlanke Tabelle ist die kleinste
 * saubere Lösung. Keine Kopie von Partnerdaten, keine redundanten Felder.
 *
 * Einwilligungen liegen NICHT hier, sondern im bestehenden Einwilligungsprotokoll
 * (`liw_consent_log`, seit alpha.19 mit `request_kind = 'contact'`) – eine Quelle für §24.
 *
 * @package Liebherr\InterfaceWorld\Contact
 * @since   0.1.0-alpha.19
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Contact;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ContactSchema {

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_contact_request';
	}

	public static function create_table(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = self::table_name();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Regel: Tabellen-COMMENT ohne runde Klammern (dbDelta, s. InterfaceCatalogSchema).
		dbDelta( "CREATE TABLE {$table} (
			id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			organisation      VARCHAR(255) NOT NULL,
			contact_name      VARCHAR(255) NOT NULL,
			contact_email     VARCHAR(255) NOT NULL,
			contact_phone     VARCHAR(64)  NULL,
			region            VARCHAR(64)  NOT NULL COMMENT 'Land/Region, Auswahlwert aus ContactService::REGIONS',
			role              VARCHAR(32)  NOT NULL COMMENT 'central | dealer | supplier | technology_partner | other',
			local_system      VARCHAR(255) NULL COMMENT 'Lokales ERP/CRM, Freitext',
			interests         VARCHAR(500) NOT NULL COMMENT 'Projektinteresse, kommagetrennte Schluessel aus ContactService::INTERESTS',
			message           TEXT NOT NULL,
			request_status    VARCHAR(20)  NOT NULL DEFAULT 'new' COMMENT 'new | in_progress | closed',
			created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at        DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_status (request_status),
			KEY idx_created (created_at)
		) {$charset} COMMENT='Liebherr Interface World – Kontakt-/Projektanfragen, §22 LP-13, getrennt vom Partner-Onboarding';" );
	}
}
