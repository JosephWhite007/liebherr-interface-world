<?php
/**
 * Liebherr Interface Solutions – Partner Document Schema
 *
 * DB-Schema für liw_partner_document: Metadaten geschützter Dokumente, die nur angemeldete
 * Benutzer mit `liw_partner_access` (Rolle liw_partner, alpha.21) herunterladen dürfen.
 * Pflichtenheft §10/§23 (rollenbasierter Zugriff, Audit), Entscheidung Joseph White 18.09.2026
 * (Option A – eigener Bereich im Plugin, s. LOGBUCH_TECHNIK alpha.21/alpha.22).
 *
 * Datenmodell-Prüfung: Core `Modules\Documents` ist gastgebunden (guest_id), die WP-Medien-
 * bibliothek liefert Dateien immer öffentlich aus – beides ungeeignet. Diese Tabelle hält nur
 * Metadaten; die Datei liegt unter zufälligem Namen in einem gesperrten Verzeichnis
 * (PartnerDocumentService). Kein Bezug zu einzelnen Partnern (alle freigegebenen Partner sehen
 * dieselben Dokumente – ANNAHME-LIW-12, s. Service).
 *
 * @package Liebherr\InterfaceWorld\Partner
 * @since   0.1.0-alpha.22
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Partner;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class PartnerDocumentSchema {

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_partner_document';
	}

	public static function create_table(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = self::table_name();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Regel: Tabellen-COMMENT ohne runde Klammern (dbDelta, s. InterfaceCatalogSchema).
		dbDelta( "CREATE TABLE {$table} (
			id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title          VARCHAR(255) NOT NULL,
			description    TEXT NULL,
			original_name  VARCHAR(255) NOT NULL COMMENT 'Dateiname beim Upload, nur fuer Anzeige/Download-Header',
			stored_name    VARCHAR(64)  NOT NULL COMMENT 'Zufaelliger Dateiname im gesperrten Verzeichnis, nie ausgegeben',
			mime_type      VARCHAR(100) NOT NULL,
			size_bytes     BIGINT UNSIGNED NOT NULL DEFAULT 0,
			sha256         CHAR(64)     NOT NULL COMMENT 'Integritaet/Versionierung, Pflichtenheft §10',
			status         VARCHAR(20)  NOT NULL DEFAULT 'active' COMMENT 'active | deleted, Soft-Delete fuer Audit-Trail',
			uploaded_by    BIGINT UNSIGNED NULL,
			created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			deleted_at     DATETIME NULL,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_stored (stored_name),
			KEY idx_status (status)
		) {$charset} COMMENT='Liebherr Interface World – geschuetzte Partnerdokumente, Metadaten, §10/§23';" );
	}
}
