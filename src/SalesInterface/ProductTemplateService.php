<?php
/**
 * Liebherr Interface Solutions – Produktvorlagen: Import aus `/product-list`, Suche, Übernahme ins Händler-Articlebook (BV-0).
 *
 * Import idempotent (Upsert je Produkt_ID), Trockenlauf zählt nur. Übernahme legt im Mandanten einen Artikel vom Typ
 * `physical` an (Name „<Hersteller> <Bezeichnung>", Gruppe je Maschinenart, Nummer aus dem Nummernkreis A-000001) und
 * merkt die Vorlage im type_meta (`template_product_id`), damit dieselbe Vorlage nicht doppelt übernommen wird.
 * Klartexte für Maschinenart/Hersteller/Aktiv: Codelisten liefern sie derzeit nicht (keine Liste in der Auswahlliste,
 * Rückfrage Liebherr) – die Codes werden mit einer kleinen Ersatztabelle (bekannte Kürzel) angezeigt, sonst roh.
 *
 * @package Liebherr\InterfaceWorld\SalesInterface
 * @since   0.1.0-alpha.156
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\SalesInterface;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ProductTemplateService {

	private const CORE_ART   = 'Araliya\\Platform\\Core\\Modules\\Articlebook\\ArticlebookRepository';
	private const CORE_GROUP = 'Araliya\\Platform\\Core\\Modules\\Articlebook\\ArticleGroupRepository';
	private const CORE_SCOPE = 'Araliya\\Platform\\Core\\Modules\\Articlebook\\ArticleTenantScope';

	/** Bekannte Kürzel (Erfahrungswerte aus der Liste; Rückfrage an Liebherr nach der Codeliste läuft). */
	public const MACHINE_TYPES = [
		'RADL' => 'Radlader', 'RBTB' => 'Raupenbagger', 'MBTB' => 'Mobilbagger', 'MB' => 'Mobilbagger (alt)', 'MBIN' => 'Mobilbagger Industrie',
		'RBIN' => 'Raupenbagger Industrie', 'KrOD' => 'Kran Oberdrehend', 'KrSE' => 'Kran Selbstaufsteller', 'TS' => 'Teleskoplader', 'PLAR' => 'Planierraupe',
		'S' => 'Sonstige', 'SERV' => 'Service', 'WKZG' => 'Werkzeug / Anbaugerät', 'GGTL' => 'Geräteträger', 'AU' => 'Autokran',
	];
	public const MANUFACTURERS = [ 'LI' => 'Liebherr', 'CAT' => 'Caterpillar', 'KM' => 'Komatsu', 'VOLV' => 'Volvo', 'HI' => 'Hitachi', 'PO' => 'Potain', 'CP' => 'Case', 'JCB' => 'JCB', 'AT' => 'Atlas', 'JD' => 'John Deere', 'OK' => 'O&K', 'HY' => 'Hyundai', 'AK' => 'Akerman' ];
	public const ACTIVE = [ 'FRLI' => 'freigegeben (Liebherr)', 'FREI' => 'freigegeben', 'NIFR' => 'nicht freigegeben' ];

	/**
	 * Produktliste holen und spiegeln.
	 *
	 * @return array{ok:bool,message:string,rows:int,written:int,skipped:int}
	 */
	public static function import( bool $dry_run, string $dealer_code, int $actor_id = 0 ): array {
		global $wpdb;
		$res = DealerApiClient::get( '/product-list', [], $actor_id, $dealer_code );
		if ( empty( $res['ok'] ) ) {
			return [ 'ok' => false, 'message' => '' !== (string) ( $res['error'] ?? '' ) ? (string) $res['error'] : __( 'Produktliste nicht abrufbar.', 'liebherr-interface-world' ), 'rows' => 0, 'written' => 0, 'skipped' => 0 ];
		}
		$rows    = DealerApiClient::rows_of( $res['data'] ?? null );
		$written = 0;
		$skipped = 0;
		$batch   = [];
		$flush   = static function () use ( &$batch, &$written, $wpdb ): void {
			if ( [] === $batch ) {
				return;
			}
			$place = implode( ',', array_fill( 0, count( $batch ), '(%d,%s,%s,%s,%d,%d,%s,%s)' ) );
			$args  = [];
			foreach ( $batch as $b ) {
				array_push( $args, $b['product_id'], $b['machine_type'], $b['manufacturer'], $b['designation'], $b['build_from'], $b['build_to'], $b['active_code'], $b['product_code'] );
			}
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . ProductTemplateSchema::table() . " (product_id, machine_type, manufacturer, designation, build_from, build_to, active_code, product_code) VALUES {$place} ON DUPLICATE KEY UPDATE machine_type = VALUES(machine_type), manufacturer = VALUES(manufacturer), designation = VALUES(designation), build_from = VALUES(build_from), build_to = VALUES(build_to), active_code = VALUES(active_code), product_code = VALUES(product_code)", $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$written += count( $batch );
			$batch    = [];
		};
		foreach ( $rows as $r ) {
			$pid = (int) ( $r['Produkt_ID'] ?? 0 );
			$des = sanitize_text_field( (string) ( $r['Produkt_Bez'] ?? '' ) );
			if ( $pid <= 0 || '' === $des ) {
				$skipped++;
				continue;
			}
			if ( $dry_run ) {
				$written++;
				continue;
			}
			$batch[] = [
				'product_id'   => $pid,
				'machine_type' => sanitize_text_field( (string) ( $r['Maschinenart_CL'] ?? '' ) ),
				'manufacturer' => sanitize_text_field( (string) ( $r['Hersteller_CL'] ?? '' ) ),
				'designation'  => mb_substr( $des, 0, 120 ),
				'build_from'   => (int) ( $r['BZBegin'] ?? 0 ),
				'build_to'     => (int) ( $r['BZEnde'] ?? 0 ),
				'active_code'  => sanitize_text_field( (string) ( $r['Aktiv_CL'] ?? '' ) ),
				'product_code' => sanitize_text_field( (string) ( $r['ProductCode'] ?? '' ) ),
			];
			if ( count( $batch ) >= 500 ) {
				$flush();
			}
		}
		$flush();
		if ( ! $dry_run ) {
			$wpdb->query( 'UPDATE ' . ProductTemplateSchema::table() . ' SET build_from = NULL WHERE build_from = 0' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( 'UPDATE ' . ProductTemplateSchema::table() . ' SET build_to = NULL WHERE build_to = 0' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			update_option( ProductTemplateSchema::OPTION_LAST, [ 'at' => time(), 'rows' => count( $rows ), 'written' => $written, 'dealer' => $dealer_code ], false );
		}
		return [ 'ok' => true, 'message' => sprintf( /* translators: 1: Zeilen 2: geschrieben */ __( 'Produktliste: %1$d Zeilen, %2$d Vorlagen.', 'liebherr-interface-world' ), count( $rows ), $written ), 'rows' => count( $rows ), 'written' => $written, 'skipped' => $skipped ];
	}

	public static function total(): int {
		global $wpdb;
		return ProductTemplateSchema::exists() ? (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . ProductTemplateSchema::table() ) : 0; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** @return array<int,array<string,mixed>> Suche nach Bezeichnung/Hersteller/Maschinenart (Standard: nur Liebherr, freigegeben). */
	public static function search( string $term, string $manufacturer = 'LI', string $machine_type = '', int $limit = 50 ): array {
		global $wpdb;
		if ( ! ProductTemplateSchema::exists() ) {
			return [];
		}
		$where = [ '1=1' ];
		if ( '' !== $manufacturer ) {
			$where[] = $wpdb->prepare( 'manufacturer = %s', $manufacturer );
		}
		if ( '' !== $machine_type ) {
			$where[] = $wpdb->prepare( 'machine_type = %s', $machine_type );
		}
		if ( '' !== trim( $term ) ) {
			$like    = '%' . $wpdb->esc_like( trim( $term ) ) . '%';
			$where[] = $wpdb->prepare( '(designation LIKE %s OR product_code LIKE %s)', $like, $like );
		}
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . ProductTemplateSchema::table() . ' WHERE ' . implode( ' AND ', $where ) . " ORDER BY (active_code = 'NIFR') ASC, manufacturer, designation LIMIT %d", $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $rows ) ? $rows : [];
	}

	/** @return array<string,int> Maschinenart ⇒ Anzahl (für Filter) */
	public static function machine_types( string $manufacturer = '' ): array {
		global $wpdb;
		if ( ! ProductTemplateSchema::exists() ) {
			return [];
		}
		$sql  = 'SELECT machine_type, COUNT(*) AS n FROM ' . ProductTemplateSchema::table() . ( '' !== $manufacturer ? $wpdb->prepare( ' WHERE manufacturer = %s', $manufacturer ) : '' ) . ' GROUP BY machine_type ORDER BY n DESC';
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$out  = [];
		foreach ( is_array( $rows ) ? $rows : [] as $r ) {
			$out[ (string) $r['machine_type'] ] = (int) $r['n'];
		}
		return $out;
	}

	public static function label_type( string $code ): string {
		return self::MACHINE_TYPES[ $code ] ?? $code;
	}

	public static function label_manufacturer( string $code ): string {
		return self::MANUFACTURERS[ $code ] ?? $code;
	}

	/** Vorlage lesen. @return array<string,mixed>|null */
	public static function get( int $product_id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . ProductTemplateSchema::table() . ' WHERE product_id = %d', $product_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Vorlage als Artikel in den Mandanten übernehmen (Gruppe je Maschinenart wird angelegt; Dublette = bereits übernommene Vorlage).
	 *
	 * @return int|\WP_Error Artikel-ID
	 */
	public static function adopt( int $org_id, int $product_id, int $actor_id = 0 ): int|\WP_Error {
		if ( ! class_exists( self::CORE_SCOPE ) ) {
			return new \WP_Error( 'liw_core', __( 'Articlebook nicht verfügbar.', 'liebherr-interface-world' ) );
		}
		$tpl = self::get( $product_id );
		if ( null === $tpl ) {
			return new \WP_Error( 'liw_tpl_missing', __( 'Vorlage nicht gefunden.', 'liebherr-interface-world' ) );
		}
		return ( self::CORE_SCOPE )::with( $org_id, static function () use ( $tpl, $product_id, $org_id ) {
			global $wpdb;
			$existing = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . ( self::CORE_ART )::table() . ' WHERE org_id = %d AND type_meta LIKE %s LIMIT 1', $org_id, '%"template_product_id":' . $product_id . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $existing > 0 ) {
				return new \WP_Error( 'liw_tpl_dup', sprintf( /* translators: %d: Artikel-ID */ __( 'Vorlage bereits übernommen (Artikel #%d).', 'liebherr-interface-world' ), $existing ) );
			}
			$type_code = (string) $tpl['machine_type'];
			$gkey      = 'o' . $org_id . '-' . sanitize_title( self::label_type( $type_code ) );
			if ( null === ( self::CORE_GROUP )::get_by_key( $gkey ) ) {
				( self::CORE_GROUP )::create( [ 'group_key' => $gkey, 'label' => self::label_type( $type_code ), 'meta' => [ 'machine_type' => $type_code ] ] );
			}
			$years = '' !== (string) $tpl['build_from'] ? ( (string) $tpl['build_from'] . ( '' !== (string) $tpl['build_to'] ? '–' . (string) $tpl['build_to'] : '' ) ) : '';
			$id    = ( self::CORE_ART )::create( [
				'type'              => 'physical',
				'name'              => trim( self::label_manufacturer( (string) $tpl['manufacturer'] ) . ' ' . (string) $tpl['designation'] ),
				'short_description' => trim( self::label_type( $type_code ) . ( '' !== $years ? ' · ' . __( 'Bauzeit', 'liebherr-interface-world' ) . ' ' . $years : '' ) ),
				'description'       => '',
				'group_key'         => $gkey,
				'status'            => 'active',
				'is_sellable'       => 1,
				'is_stock_managed'  => 0,
				'type_meta'         => [ 'template_product_id' => $product_id, 'machine_type' => $type_code, 'manufacturer' => (string) $tpl['manufacturer'], 'product_code' => (string) $tpl['product_code'], 'build_from' => (int) $tpl['build_from'], 'build_to' => (int) $tpl['build_to'] ],
			] );
			return $id > 0 ? $id : new \WP_Error( 'liw_tpl_create', __( 'Artikel konnte nicht angelegt werden.', 'liebherr-interface-world' ) );
		} );
	}
}
