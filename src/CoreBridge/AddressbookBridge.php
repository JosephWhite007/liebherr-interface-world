<?php
/**
 * Liebherr Interface Solutions – Addressbook Bridge
 *
 * Kopplung an das Core-Addressbook (ARY-PH-AB-1.0): liefert dem Core-Importer und den Masken die Liebherr-Codelisten
 * (`liw_codelist`, Stufe 1b) über den Filter `araliya_addressbook_value_list` als Text ⇒ Code je Liste und Sprache.
 * Der Core kennt keine Liebherr-Tabelle; ohne dieses Plugin bleiben Codes im Import leer (Text in Import-Hinweisen).
 * Später (Phase 3/4): Dealer Workspace und Livision-Sync hängen sich hier ein.
 *
 * @package Liebherr\InterfaceWorld\CoreBridge
 * @since   0.1.0-alpha.151
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\CoreBridge;

use Liebherr\InterfaceWorld\SalesInterface\CodeListService;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AddressbookBridge {

	public const FILTER_VALUE_LIST = 'araliya_addressbook_value_list';

	public static function register(): void {
		add_filter( self::FILTER_VALUE_LIST, [ self::class, 'value_list' ], 10, 3 );
	}

	/**
	 * @param array<string,string> $list     bereits gesammelte Werte (Text ⇒ Code)
	 * @param string               $code_list Liebherr-Listenkürzel (1B, 3B, KL, …)
	 * @param int                  $language  0 = Deutsch (Liebherr-Zählung)
	 * @return array<string,string>
	 */
	public static function value_list( $list, $code_list, $language = 0 ): array {
		global $wpdb;
		$list      = is_array( $list ) ? $list : [];
		$code_list = (string) preg_replace( '/[^A-Za-z0-9]/', '', (string) $code_list );
		if ( '' === $code_list || ! class_exists( CodeListService::class ) ) {
			return $list;
		}
		$table = \Liebherr\InterfaceWorld\SalesInterface\CodeListSchema::table_name();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT code, description FROM {$table} WHERE code_list = %s AND language = %d", $code_list, (int) $language ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( is_array( $rows ) ? $rows : [] as $r ) {
			$list[ (string) $r['description'] ] = (string) $r['code'];
		}
		return $list;
	}
}
