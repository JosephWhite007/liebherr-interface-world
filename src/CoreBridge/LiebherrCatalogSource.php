<?php
/**
 * Liebherr Interface World – Katalogquelle „Liebherr Produktvorlagen“ für das Articlebook Construction des Core.
 *
 * Wird nur geladen, wenn der Core-Vertrag existiert (ConstructionBridge). Daten: ProductTemplateService (liw_product_template).
 *
 * @package Liebherr\InterfaceWorld\CoreBridge
 * @since   0.1.0-alpha.170
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\CoreBridge;

use Araliya\Platform\Core\Modules\Construction\CatalogSourceInterface;
use Liebherr\InterfaceWorld\SalesInterface\ProductTemplateService;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class LiebherrCatalogSource implements CatalogSourceInterface { // [alpha.172] nicht final: LiebherrLiveCatalogSource erweitert um den Live-Vertrag

	public const KEY = 'liebherr';

	public function key(): string {
		return self::KEY;
	}

	public function label(): string {
		return __( 'Liebherr Produktvorlagen', 'liebherr-interface-world' );
	}

	public function total(): int {
		return ProductTemplateService::total();
	}

	/** @return array<string,array<string,string>> */
	public function filters(): array {
		$types = [];
		foreach ( ProductTemplateService::machine_types() as $code => $n ) {
			$types[ (string) $code ] = ProductTemplateService::label_type( (string) $code );
		}
		$manu = [];
		foreach ( self::manufacturers() as $code => $n ) {
			$manu[ $code ] = ProductTemplateService::label_manufacturer( $code );
		}
		return [ 'manufacturer' => $manu, 'machine_type' => $types ];
	}

	/** @return array<string,int> Herstellercode ⇒ Anzahl */
	private static function manufacturers(): array {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT manufacturer, COUNT(*) AS n FROM ' . \Liebherr\InterfaceWorld\SalesInterface\ProductTemplateSchema::table() . " WHERE manufacturer <> '' GROUP BY manufacturer ORDER BY n DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$out  = [];
		foreach ( is_array( $rows ) ? $rows : [] as $r ) {
			$out[ (string) $r['manufacturer'] ] = (int) $r['n'];
		}
		return $out;
	}

	public function search( string $term, array $f, int $limit ): array {
		$manufacturer = (string) ( $f['manufacturer'] ?? 'LI' ); // Standard: Liebherr; '' über Filter = alle Hersteller der Vorlagenliste
		$rows         = ProductTemplateService::search( $term, $manufacturer, (string) ( $f['machine_type'] ?? '' ), max( 1, min( 500, $limit ) ) );
		return array_map( [ self::class, 'normalize' ], $rows );
	}

	public function get( string $ref ): ?array {
		$row = ProductTemplateService::get( (int) $ref );
		return null === $row ? null : self::normalize( $row );
	}

	/** @param array<string,mixed> $t @return array<string,mixed> */
	private static function normalize( array $t ): array {
		$manu = (string) $t['manufacturer'];
		$type = (string) $t['machine_type'];
		return [
			'ref'                 => (string) (int) $t['product_id'],
			'manufacturer_number' => '' !== (string) $t['product_code'] ? (string) $t['product_code'] : (string) (int) $t['product_id'],
			'name'                => trim( ProductTemplateService::label_manufacturer( $manu ) . ' ' . (string) $t['designation'] ),
			'machine_type'        => $type,
			'machine_type_label'  => ProductTemplateService::label_type( $type ),
			'model'               => (string) $t['designation'],
			'build_from'          => (int) ( $t['build_from'] ?? 0 ),
			'build_to'            => (int) ( $t['build_to'] ?? 0 ),
			'manufacturer'        => $manu,
			'manufacturer_label'  => ProductTemplateService::label_manufacturer( $manu ),
			'type'                => 'machine',
		];
	}
}
