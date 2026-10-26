<?php
/**
 * Liebherr Interface Solutions – Produktliste als Artikelvorlage (ARY-PH-BV-1.0 Phase BV-0).
 *
 * Lokalspiegel von `GET /product-list` des Dealer Interface (14 701 Modelle aller Hersteller: Maschinenart, Hersteller,
 * Bezeichnung, Bauzeitraum, Aktiv-Kennzeichen). Händler übernehmen Modelle per Klick als Artikel in ihr Articlebook
 * (Core, Mandant). Die Vorlage selbst ist mandantenfrei (Plattform) und ersetzt keine Artikel – ANNAHME-BV0-1: eigene
 * Tabelle statt 14 701 Zeilen im Plattform-Articlebook (Mandant 0), damit ARALIYAs eigene Artikel und Nummernkreise
 * unberührt bleiben.
 *
 * @package Liebherr\InterfaceWorld\SalesInterface
 * @since   0.1.0-alpha.156
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\SalesInterface;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ProductTemplateSchema {

	public const TABLE       = 'liw_product_template';
	public const OPTION_LAST = 'liw_product_template_last_import';

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	public static function create_table(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( 'CREATE TABLE ' . self::table() . " (
			id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id       BIGINT UNSIGNED NOT NULL,
			machine_type     VARCHAR(20)  NOT NULL DEFAULT '',
			manufacturer     VARCHAR(20)  NOT NULL DEFAULT '',
			designation      VARCHAR(120) NOT NULL DEFAULT '',
			build_from       SMALLINT UNSIGNED NULL,
			build_to         SMALLINT UNSIGNED NULL,
			active_code      VARCHAR(10)  NOT NULL DEFAULT '',
			product_code     VARCHAR(60)  NOT NULL DEFAULT '',
			imported_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uniq_product (product_id),
			KEY idx_type_manu (machine_type, manufacturer),
			KEY idx_designation (designation)
		) {$charset} COMMENT='Liebherr Dealer Interface – Produktliste als Artikelvorlage je Modell';" );
	}

	public static function exists(): bool {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', self::table() ) ) === self::table();
	}
}
