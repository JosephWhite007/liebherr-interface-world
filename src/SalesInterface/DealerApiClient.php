<?php
/**
 * Liebherr Interface Solutions – Dealer Interface API: HTTP-Client (Stufe 1c, lesend).
 *
 * Einziger Ausgangskanal zur Liebherr Sales API v3 (ADR-LIW-SALES-001 §5.1). Bewusst eng: nur die
 * **Testumgebung**, nur **GET** (Produktion und schreibende Methoden sind in dieser Stufe nicht erreichbar –
 * weder per Parameter noch per Option). Zugangsdaten ausschließlich aus Env (`InstanceBridge::ENV_*`), nie aus
 * DB/Code; vor jedem Aufruf `InstanceBridge::may_send()` (Klon-/Not-Aus-Schutz). Jeder Aufruf wird auditiert
 * (Endpunkt, HTTP-Status, Dauer) – nie Nutzdaten, nie Header. Fehler sind neutral (keine Liebherr-Antwort im Klartext
 * nach außen). Letzter Lauf als Status in Option `liw_livision_last_probe` (Board/Sandbox-Reiter).
 *
 * @package Liebherr\InterfaceWorld\SalesInterface
 * @since   0.1.0-alpha.147
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\SalesInterface;

use Liebherr\InterfaceWorld\CoreBridge\AuditBridge;
use Liebherr\InterfaceWorld\CoreBridge\InstanceBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class DealerApiClient {

	public const BASE_TEST    = 'https://livision.azure-api.net/crm/v3';
	public const OPTION_PROBE = 'liw_livision_last_probe';

	/** Befund 24.10.2026: /tab-text antwortet nach ~17 s (Liebherr-Backend), übrige Endpunkte in 1–2 s. */
	private const TIMEOUT = 45;

	/** Lesende Endpunkte der Stufe 1c (Pfad ⇒ UserEmail-Header nötig). Alles andere wird abgelehnt. */
	public const READ_ENDPOINTS = [
		'/product-list'            => true,
		'/tab-text'                => true,
		'/opportunity'             => true,
		'/opportunity-id'          => true,
		'/customer-quotation-list' => true,
		'/livision-url'            => true,
		'/sales-orders'            => false,
	];

	/**
	 * Vorbedingungen ohne Aufruf prüfen (Board-Status, Probe-Trockenlauf).
	 *
	 * @return array{ok:bool,reason:string}
	 */
	public static function readiness(): array {
		if ( ! InstanceBridge::env_set( InstanceBridge::ENV_KEY_TEST ) ) {
			return [ 'ok' => false, 'reason' => 'key_missing' ];
		}
		if ( ! InstanceBridge::env_set( InstanceBridge::ENV_USER_EMAIL ) ) {
			return [ 'ok' => false, 'reason' => 'user_email_missing' ];
		}
		if ( ! InstanceBridge::may_send() ) {
			return [ 'ok' => false, 'reason' => 'instance_locked' ];
		}
		return [ 'ok' => true, 'reason' => 'ready' ];
	}

	/**
	 * GET gegen die Testumgebung.
	 *
	 * @param string               $path  Pfad aus READ_ENDPOINTS (optional mit Pfadparametern, z. B. /opportunity/123).
	 * @param array<string,string> $query Abfrageparameter (Werte werden als Text gesendet).
	 * @return array{ok:bool,status:int,data:mixed,rows:array<int,mixed>,error:string,duration_ms:int} `data` = Rohantwort,
	 *         `rows` = Nutzliste (Liebherr liefert teils `{status,data:[…]}`, teils direkt ein Array).
	 */
	public static function get( string $path, array $query = [], int $actor_id = 0 ): array {
		$path = '/' . ltrim( trim( $path ), '/' );
		$base = self::base_path( $path );
		if ( null === $base ) {
			return self::fail( $path, 0, 'endpoint_not_allowed', 0, $actor_id );
		}
		$ready = self::readiness();
		if ( ! $ready['ok'] ) {
			return self::fail( $path, 0, $ready['reason'], 0, $actor_id );
		}

		$headers = [ 'Ocp-Apim-Subscription-Key' => (string) getenv( InstanceBridge::ENV_KEY_TEST ), 'Accept' => 'application/json' ];
		if ( self::READ_ENDPOINTS[ $base ] ) {
			$headers['UserEmail'] = (string) getenv( InstanceBridge::ENV_USER_EMAIL );
		}
		$url   = self::BASE_TEST . $path . ( [] !== $query ? '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ) : '' );
		$start = microtime( true );
		$res   = wp_remote_get( $url, [ 'timeout' => self::TIMEOUT, 'headers' => $headers, 'redirection' => 0 ] );
		$ms    = (int) round( ( microtime( true ) - $start ) * 1000 );

		if ( is_wp_error( $res ) ) {
			return self::fail( $path, 0, 'transport_error', $ms, $actor_id );
		}
		$status = (int) wp_remote_retrieve_response_code( $res );
		$body   = (string) wp_remote_retrieve_body( $res );
		$data   = json_decode( $body, true );
		if ( $status < 200 || $status >= 300 ) {
			return self::fail( $path, $status, 'http_' . $status, $ms, $actor_id );
		}
		if ( null === $data && '' !== trim( $body ) ) {
			return self::fail( $path, $status, 'invalid_json', $ms, $actor_id );
		}

		$rows = self::rows_of( $data );
		self::record( $path, $status, true, 'ok', $ms, count( $rows ), $actor_id );
		return [ 'ok' => true, 'status' => $status, 'data' => $data, 'rows' => $rows, 'error' => '', 'duration_ms' => $ms ];
	}

	/** Basis-Endpunkt eines Pfads (Pfadparameter abgeschnitten); null = nicht freigegeben. */
	public static function base_path( string $path ): ?string {
		foreach ( array_keys( self::READ_ENDPOINTS ) as $base ) {
			if ( $path === $base || 0 === strpos( $path, $base . '/' ) ) {
				if ( '/opportunity' === $base && 0 === strpos( $path, '/opportunity-' ) ) {
					continue;
				}
				return $base;
			}
		}
		return null;
	}

	/**
	 * Nutzliste aus der Antwort: Umschlag `{status:{…},data:[…]}` (tab-text, product-list) oder direktes Array (sales-orders).
	 *
	 * @return array<int,mixed>
	 */
	public static function rows_of( $data ): array {
		if ( ! is_array( $data ) ) {
			return null === $data ? [] : [ $data ];
		}
		if ( isset( $data['data'] ) && is_array( $data['data'] ) && array_key_exists( 'status', $data ) ) {
			return array_values( $data['data'] );
		}
		return array_values( $data );
	}

	/** @return array{ok:bool,status:int,data:mixed,rows:array<int,mixed>,error:string,duration_ms:int} */
	private static function fail( string $path, int $status, string $error, int $ms, int $actor_id ): array {
		self::record( $path, $status, false, $error, $ms, 0, $actor_id );
		return [ 'ok' => false, 'status' => $status, 'data' => null, 'rows' => [], 'error' => $error, 'duration_ms' => $ms ];
	}

	private static function record( string $path, int $status, bool $ok, string $error, int $ms, int $rows, int $actor_id ): void {
		$entry = [ 'at' => time(), 'endpoint' => $path, 'status' => $status, 'ok' => $ok, 'error' => $error, 'duration_ms' => $ms, 'rows' => $rows, 'env' => 'test' ];
		update_option( self::OPTION_PROBE, $entry, false );
		if ( AuditBridge::is_available() ) {
			AuditBridge::log( $ok ? 'api_read' : 'api_fail', 'livision', 0, [], $entry, $actor_id );
		}
	}

	/** @return array<string,mixed>|null Letzter Lauf (ohne Nutzdaten). */
	public static function last_probe(): ?array {
		$v = get_option( self::OPTION_PROBE, null );
		return is_array( $v ) ? $v : null;
	}
}
