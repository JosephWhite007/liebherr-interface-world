<?php
/**
 * Liebherr Interface Solutions – Codelisten-Tabelle `liw_codelist` (Stufe 1b).
 *
 * Zweck: Lokalspiegel der Liebherr-Codelisten aus der LIVISION-Auswahlliste (21 Listen × 8 Sprachen ≈ 49 100 Zeilen):
 * Felder wie Bundesland, Branche, Klassifizierung oder Anrede verweisen in der Dealer Interface API auf Codes, deren
 * Anzeigetexte hier nachgeschlagen werden (Seite „Sales Interface" → Codelisten, Validierung vor Schreibaufrufen).
 * Befüllung ausschließlich per CLI-Import (Terminal-Regel), Upsert über den eindeutigen Schlüssel Sprache+Liste+Code.
 * Listen-Metadaten (Name, Entität) und Sprachen liegen in Optionen (`liw_codelist_meta`, `liw_codelist_langs`).
 *
 * Regel dieses Plugins: Tabellen-COMMENT ohne runde Klammern (dbDelta-Falle).
 *
 * @package Liebherr\InterfaceWorld\SalesInterface
 * @since   0.1.0-alpha.149
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\SalesInterface;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class CodeListSchema {

	public const OPTION_META  = 'liw_codelist_meta';
	public const OPTION_LANGS = 'liw_codelist_langs';
	public const OPTION_LAST  = 'liw_codelist_last_import';

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'liw_codelist';
	}

	public static function create_table(): void {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();
		$table   = self::table_name();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( "CREATE TABLE {$table} (
			id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			language         TINYINT UNSIGNED NOT NULL,
			code_list        VARCHAR(8)   NOT NULL,
			code             VARCHAR(40)  NOT NULL,
			description      VARCHAR(255) NOT NULL DEFAULT '',
			additional_value VARCHAR(64)  NOT NULL DEFAULT '',
			PRIMARY KEY (id),
			UNIQUE KEY uniq_entry (language, code_list, code),
			KEY idx_list_lang (code_list, language),
			KEY idx_description (description)
		) {$charset} COMMENT='Liebherr Dealer Interface – Codelisten der LIVISION-Auswahlliste je Sprache';" );
	}
}
