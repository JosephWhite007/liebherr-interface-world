<?php
/**
 * Liebherr Interface Solutions – Consent Log Schema
 *
 * ABWEICHUNG von der ursprünglichen Annahme (Phase-3-Plan): `Modules\Consent\ConsentService`
 * wurde vor der Implementierung geprüft und ist an `guest_id` gebunden (Health-Domain,
 * Gast-Entität der ARALIYA Constitution). Liebherr-Kontaktformular-Absender (Organisationen,
 * Händler, Lieferanten) sind keine Gäste – eine Bindung an ConsentService wäre eine
 * Zweckentfremdung eines Health-spezifischen Contracts, kein Reuse. Deshalb: eigene, sehr
 * schlanke Consent-Ablage nach demselben fachlichen Muster (Version + Zeitstempel, Liebherr §24),
 * kein neues „System" — nur eine Tabelle mit derselben Prüfsemantik.
 *
 * Zweckbindung (§22): Datenschutz- und Marketingeinwilligung werden als getrennte
 * consent_type-Zeilen gespeichert, nie gekoppelt.
 *
 * @package Liebherr\InterfaceWorld\Consent
 * @since   0.1.0-alpha.1
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Consent;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ConsentLogSchema {

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_consent_log';
	}

	public static function create_table(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = self::table_name();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( "CREATE TABLE {$table} (
			id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			request_id     BIGINT UNSIGNED NOT NULL COMMENT 'FK auf ary_partners.id (Core-Partnermodul, wiederverwendet) oder liw_partner_extra.id',
			consent_type   VARCHAR(20)  NOT NULL COMMENT 'privacy | marketing (§22/§24, nie gekoppelt)',
			text_version   VARCHAR(32)  NOT NULL,
			granted_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			ip_address     VARCHAR(45)  NULL,
			PRIMARY KEY (id),
			KEY idx_request (request_id),
			KEY idx_type (consent_type)
		) {$charset} COMMENT='Liebherr Interface World – Einwilligungsprotokoll (§22/§24, getrennt von Core Guest-Consent)';" );
	}
}
