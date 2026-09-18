<?php
/**
 * Liebherr Interface Solutions – Onboarding Schema
 *
 * DB-Schema für `liw_partner_extra` (Liebherr-Pflichtenheft §22 „Onboarding-Formular").
 * Bewusst KEINE Kopie von `ary_partners` (Core, wiederverwendet über CoreBridge\PartnerBridge)
 * – nur die Liebherr-spezifischen Zusatzfelder, die im Core-Schema keinen Platz haben
 * (liw_partner_type-Klassifizierung dealer/supplier/customer, gewünschte Schnittstellen,
 * Freitext-Anfrage, Freigabeworkflow-Status). 1:1-Beziehung zu `ary_partners.id`.
 *
 * @package Liebherr\InterfaceWorld\Onboarding
 * @since   0.1.0-alpha.6
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Onboarding;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class OnboardingSchema {

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_partner_extra';
	}

	public static function create_table(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = self::table_name();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( "CREATE TABLE {$table} (
			id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			partner_id            BIGINT UNSIGNED NOT NULL COMMENT 'FK auf ary_partners.id (Core, wiederverwendet)',
			liw_partner_type      VARCHAR(20)  NOT NULL DEFAULT 'dealer' COMMENT 'dealer | supplier | customer (Liebherr-Klassifizierung, nicht Core-partner_type)',
			requested_interfaces  TEXT NULL COMMENT 'Freitext: gewünschte Schnittstellen/Anbindung',
			message               TEXT NULL,
			onboarding_status     VARCHAR(20)  NOT NULL DEFAULT 'new' COMMENT 'new | in_review | approved | rejected',
			created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at            DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_partner (partner_id),
			KEY idx_status (onboarding_status)
		) {$charset} COMMENT='Liebherr Interface World – Onboarding-Zusatzfelder zu ary_partners, §22';" );
	}
}
