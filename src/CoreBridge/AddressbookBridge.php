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
use Liebherr\InterfaceWorld\SalesInterface\DealerRepository;
use Liebherr\InterfaceWorld\SalesInterface\LivisionSync;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AddressbookBridge {

	public const FILTER_VALUE_LIST = 'araliya_addressbook_value_list';

	public const ACTION_SYNC  = 'araliya_addressbook_sync_actions';
	public const FILTER_PUSH  = 'araliya_addressbook_push';

	public static function register(): void {
		add_filter( self::FILTER_VALUE_LIST, [ self::class, 'value_list' ], 10, 3 );
		add_action( self::ACTION_SYNC, [ self::class, 'render_sync_actions' ], 10, 5 );
		add_filter( self::FILTER_PUSH, [ self::class, 'push' ], 10, 6 );
	}

	/**
	 * Core-Backend, Reiter „Schnittstellen“ der Adresse bzw. Fuß der Kontaktmaske: Livision-Status, Vorprüfung, Übertragen-Button
	 * (Phase 4). Nur wenn der Mandant mit einem Liebherr-Händler verknüpft ist.
	 */
	public static function render_sync_actions( $org, $kind, $id, $nonce_action, $nonce_name ): void {
		$org    = (int) $org;
		$id     = (int) $id;
		$kind   = 'contact' === $kind ? 'contact' : 'address';
		$dealer = DealerRepository::for_org( $org );
		if ( null === $dealer || ! LivisionSync::available() ) {
			echo '<p class="ary-ab__hint">' . esc_html__( 'Liebherr Livision: kein Händler mit diesem Mandanten verknüpft (Dealer Board → Org-ID).', 'liebherr-interface-world' ) . '</p>';
			return;
		}
		$check = LivisionSync::precheck( $org, $kind, $id );
		$lid   = LivisionSync::livision_id( $org, $kind, $id );
		echo '<h3>' . esc_html__( 'Liebherr Livision', 'liebherr-interface-world' ) . '</h3>';
		echo '<p class="ary-ab__meta">' . esc_html( $lid > 0 ? sprintf( /* translators: %d: ID */ __( 'Livision-ID %d', 'liebherr-interface-world' ), $lid ) : __( 'Noch nicht übertragen.', 'liebherr-interface-world' ) ) . ' · ' . esc_html( sprintf( /* translators: 1: Händler, 2: Schalter */ __( 'Händler %1$s, Schreiben %2$s', 'liebherr-interface-world' ), (string) $dealer['code'], $dealer['write_enabled'] ? __( 'frei', 'liebherr-interface-world' ) : __( 'gesperrt', 'liebherr-interface-world' ) ) ) . '</p>';
		if ( [] !== $check['warnings'] ) {
			echo '<ul class="ul-disc">';
			foreach ( $check['warnings'] as $w ) {
				echo '<li>' . esc_html( $w ) . '</li>';
			}
			echo '</ul>';
		}
		if ( $check['ok'] && $dealer['write_enabled'] ) {
			echo '<form method="post" class="ary-ab__inline">';
			wp_nonce_field( (string) $nonce_action, (string) $nonce_name );
			echo '<input type="hidden" name="ab_action" value="sync_push" /><input type="hidden" name="system" value="livision" /><input type="hidden" name="kind" value="' . esc_attr( $kind ) . '" /><input type="hidden" name="id" value="' . $id . '" />';
			echo '<button type="submit" class="button button-primary">' . esc_html( $lid > 0 ? __( 'An Livision übertragen (aktualisieren)', 'liebherr-interface-world' ) : __( 'An Livision übertragen (anlegen)', 'liebherr-interface-world' ) ) . '</button></form>';
		}
	}

	/** Core-Filter: Übertragung ausführen (Rechte prüft LivisionSync::push erneut). */
	public static function push( $result, $system, $org, $kind, $id, $actor ) {
		if ( 'livision' !== $system ) {
			return $result;
		}
		$r = LivisionSync::push( (int) $org, 'contact' === $kind ? 'contact' : 'address', (int) $id, (int) $actor );
		return [ 'ok' => $r['ok'], 'message' => $r['message'] ];
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
