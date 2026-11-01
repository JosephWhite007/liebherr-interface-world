<?php
/**
 * Liebherr Interface World – Brücke zum Articlebook Construction des Core (Artikelbuch V2, Core ab alpha.815).
 *
 * Registriert die Liebherr-Produktvorlagen (liw_product_template, 14.701 Modelle) als Katalogquelle über den Core-Vertrag
 * `Modules\Construction\CatalogSourceInterface` (Filter `araliya_construction_catalog_sources`). Das Plugin liefert nur Daten –
 * Masken, Rechte, Nummernkreis und Übernahme in die Händlerschicht bleiben im Core (Konzept ARY-KZ-ABC-0.2 Kap. 12, JW 31.10.2026).
 *
 * @package Liebherr\InterfaceWorld\CoreBridge
 * @since   0.1.0-alpha.170
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\CoreBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ConstructionBridge {

	public const FILTER_SOURCES = 'araliya_construction_catalog_sources';
	public const CORE_INTERFACE = '\\Araliya\\Platform\\Core\\Modules\\Construction\\CatalogSourceInterface';
	public const CORE_LIVE_INTERFACE = '\\Araliya\\Platform\\Core\\Modules\\Construction\\LiveCatalogSourceInterface'; // Core ab alpha.827 (alpha.172)

	public static function register(): void {
		add_filter( self::FILTER_SOURCES, [ self::class, 'sources' ] );
	}

	/**
	 * Katalogquelle anhängen – nur wenn der Core den Vertrag kennt (ältere Core-Stände: keine Quelle, kein Fehler).
	 *
	 * @param array<int,object> $sources
	 * @return array<int,object>
	 */
	public static function sources( array $sources ): array {
		if ( interface_exists( self::CORE_LIVE_INTERFACE ) ) {
			$sources[] = new LiebherrLiveCatalogSource(); // [alpha.172] Stand + Verbindungsprüfung für API Management / Hersteller-Schnittstelle
		} elseif ( interface_exists( self::CORE_INTERFACE ) ) {
			$sources[] = new LiebherrCatalogSource();
		}
		return $sources;
	}
}
