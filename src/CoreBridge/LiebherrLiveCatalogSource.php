<?php
/**
 * Liebherr Interface World – Live-Katalogquelle (Core-Vertrag `LiveCatalogSourceInterface`, Core ab alpha.827, L-9 Vorbereitung).
 *
 * Erweitert die Vorlagenquelle um den Stand der Herstellerverbindung: `live` = Livision-Zugang des aktiven Händlers ist bereit
 * (Schlüssel aus Env, Testumgebung, Instanz nicht gesperrt), `version` = Plugin-Version, `synced_at` = letzter Vorlagen-Import aus
 * `/product-list`, `endpoint` = Livision-Basis ohne Zugangsdaten, `auth_ref` = Name der Env-Variable. `ping()` ruft den leichten
 * Lese-Endpunkt `/tab-text?CodeList=AH&Language=0` über den vorhandenen DealerApiClient (Readiness, Kill-Switch, Protokoll) – nie Schreibzugriffe.
 * Wird nur geladen, wenn der Core den Live-Vertrag kennt (ConstructionBridge); ältere Core-Stände erhalten die einfache Quelle.
 *
 * @package Liebherr\InterfaceWorld\CoreBridge
 * @since   0.1.0-alpha.172
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\CoreBridge;

use Araliya\Platform\Core\Modules\Construction\LiveCatalogSourceInterface;
use Liebherr\InterfaceWorld\SalesInterface\DealerApiClient;
use Liebherr\InterfaceWorld\SalesInterface\ProductTemplateSchema;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class LiebherrLiveCatalogSource extends LiebherrCatalogSource implements LiveCatalogSourceInterface {

	public const PING_PATH  = '/tab-text';
	/** Leichter Lese-Aufruf wie im Probe-Skript: Codeliste AH (Anrede, 3 Zeilen), Deutsch – ohne Parameter antwortet Livision mit 404 (alpha.175). */
	public const PING_QUERY = [ 'CodeList' => 'AH', 'Language' => '0' ];

	/** @return array{live:bool,version:string,synced_at:string,endpoint:string,auth_ref:string} */
	public function status(): array {
		global $wpdb;
		$ready  = DealerApiClient::readiness( null, false );
		$synced = ProductTemplateSchema::exists() ? (string) $wpdb->get_var( 'SELECT MAX(imported_at) FROM ' . ProductTemplateSchema::table() ) : ''; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return [
			'live'      => ! empty( $ready['ok'] ),
			'version'   => defined( 'LIW_VERSION' ) ? (string) LIW_VERSION : '',
			'synced_at' => '' !== $synced ? substr( $synced, 0, 16 ) : '',
			'endpoint'  => DealerApiClient::BASE_TEST,
			'auth_ref'  => InstanceBridge::ENV_KEY_TEST,
		];
	}

	/** @return array{ok:bool,code:int,ms:int,error:string} */
	public function ping(): array {
		$ready = DealerApiClient::readiness( null, false );
		if ( empty( $ready['ok'] ) ) {
			return [ 'ok' => false, 'code' => 0, 'ms' => 0, 'error' => (string) ( $ready['reason'] ?? 'not_ready' ) ];
		}
		$r = DealerApiClient::get( self::PING_PATH, self::PING_QUERY, get_current_user_id() );
		return [ 'ok' => ! empty( $r['ok'] ), 'code' => (int) ( $r['status'] ?? 0 ), 'ms' => (int) ( $r['duration_ms'] ?? 0 ), 'error' => (string) ( $r['error'] ?? '' ) ];
	}
}
