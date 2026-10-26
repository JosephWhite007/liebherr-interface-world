<?php
/**
 * Liebherr Interface Solutions – Dealer Interface API: HTTP-Client (Stufe 1c lesend, Stufe 1d schreibend).
 *
 * Einziger Ausgangskanal zur Liebherr Sales API v3 (ADR-LIW-SALES-001 §5.1). Bewusst eng: nur die **Testumgebung**
 * (Produktions-Host kommt im Code nicht vor), Lesen nur über die sieben READ_ENDPOINTS, Schreiben nur über die vier
 * WRITE_ENDPOINTS (Kunde/Kontakt anlegen und ändern) und nur, wenn der **Händler** den Schreibschalter trägt.
 * Händlerbezogen (seit alpha.148): Zugangsdaten je Händler aus `DealerRepository::credentials()` (Key verschlüsselt,
 * Env-Fallback für die Erstanbindung), nie aus Code/Optionen im Klartext. Vor jedem Aufruf `InstanceBridge::may_send()`
 * (Klon-/Not-Aus-Schutz). Audit je Aufruf (Händler, Endpunkt, Status, Dauer – keine Nutzdaten/Header); letzter Lauf
 * in Option `liw_livision_last_probe` (lesend) bzw. `liw_livision_last_write` (schreibend). Fehler neutral.
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
	public const OPTION_WRITE = 'liw_livision_last_write';

	/** Befund 24.10.2026: /tab-text antwortet kalt nach ~17 s (Liebherr-Backend), übrige Endpunkte in 1–2 s. */
	private const TIMEOUT = 45;

	/** Lesende Endpunkte (Pfad ⇒ UserEmail-Header nötig). Alles andere wird abgelehnt. */
	public const READ_ENDPOINTS = [
		'/product-list'            => true,
		'/tab-text'                => true,
		'/opportunity'             => true,
		'/opportunity-id'          => true,
		'/customer-quotation-list' => true,
		'/livision-url'            => true,
		'/sales-orders'            => false,
	];

	/** Schreibende Endpunkte der Stufe 1d („METHOD /pfad" ⇒ Pflichtfelder laut Guide §4.2–4.5). */
	public const WRITE_ENDPOINTS = [
		'POST /customer' => [ 'Name1', 'Street', 'PostalCode', 'City', 'SalesAccountNumber' ],
		'PUT /customer'  => [ 'Name1', 'Street', 'PostalCode', 'City', 'SalesAccountNumber', 'LivisionCustomerId' ],
		'POST /contact'  => [ 'LastName', 'LivisionCustomerId' ],
		'PUT /contact'   => [ 'LastName', 'LivisionCustomerId', 'LivisionContactId' ],
	];

	/**
	 * Vorbedingungen ohne Aufruf (Board-Status, Trockenlauf). `$write` prüft zusätzlich den Schreibschalter.
	 *
	 * @return array{ok:bool,reason:string,dealer:string}
	 */
	public static function readiness( ?string $dealer_code = null, bool $write = false ): array {
		$code  = $dealer_code ?? DealerRepository::active_code();
		$creds = DealerRepository::credentials( $code );
		if ( null === $creds ) {
			return [ 'ok' => false, 'reason' => 'dealer_unknown', 'dealer' => $code ];
		}
		if ( 'active' !== $creds['status'] ) {
			return [ 'ok' => false, 'reason' => 'dealer_inactive', 'dealer' => $code ];
		}
		if ( 'test' !== $creds['environment'] ) {
			return [ 'ok' => false, 'reason' => 'environment_not_test', 'dealer' => $code ];
		}
		if ( '' === $creds['key'] ) {
			return [ 'ok' => false, 'reason' => 'key_missing', 'dealer' => $code ];
		}
		if ( '' === $creds['user_email'] ) {
			return [ 'ok' => false, 'reason' => 'user_email_missing', 'dealer' => $code ];
		}
		if ( $write && ! $creds['write_enabled'] ) {
			return [ 'ok' => false, 'reason' => 'write_disabled', 'dealer' => $code ];
		}
		if ( ! InstanceBridge::may_send() ) {
			return [ 'ok' => false, 'reason' => 'instance_locked', 'dealer' => $code ];
		}
		return [ 'ok' => true, 'reason' => 'ready', 'dealer' => $code ];
	}

	/**
	 * GET gegen die Testumgebung.
	 *
	 * @param array<string,string> $query
	 * @return array{ok:bool,status:int,data:mixed,rows:array<int,mixed>,error:string,duration_ms:int}
	 */
	public static function get( string $path, array $query = [], int $actor_id = 0, ?string $dealer_code = null ): array {
		$path = '/' . ltrim( trim( $path ), '/' );
		$base = self::base_path( $path );
		$code = $dealer_code ?? DealerRepository::active_code();
		if ( null === $base ) {
			return self::fail( self::OPTION_PROBE, $code, 'GET', $path, 0, 'endpoint_not_allowed', 0, $actor_id );
		}
		$ready = self::readiness( $code, false );
		if ( ! $ready['ok'] ) {
			return self::fail( self::OPTION_PROBE, $code, 'GET', $path, 0, $ready['reason'], 0, $actor_id );
		}
		$url = self::BASE_TEST . $path . ( [] !== $query ? '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 ) : '' );
		return self::request( self::OPTION_PROBE, $code, 'GET', $path, $url, null, self::READ_ENDPOINTS[ $base ], $actor_id );
	}

	/**
	 * Schreibaufruf (POST/PUT) gegen die Testumgebung – nur WRITE_ENDPOINTS, nur mit Schreibschalter des Händlers,
	 * nur mit vollständigen Pflichtfeldern. `$dry_run` validiert und liefert den Body ohne zu senden.
	 *
	 * @param array<string,mixed> $body
	 * @return array{ok:bool,status:int,data:mixed,rows:array<int,mixed>,error:string,duration_ms:int,body?:array<string,mixed>}
	 */
	public static function send( string $method, string $path, array $body, int $actor_id = 0, ?string $dealer_code = null, bool $dry_run = false ): array {
		$method = strtoupper( trim( $method ) );
		$path   = '/' . ltrim( trim( $path ), '/' );
		$code   = $dealer_code ?? DealerRepository::active_code();
		$spec   = $method . ' ' . $path;
		if ( ! isset( self::WRITE_ENDPOINTS[ $spec ] ) ) {
			return self::fail( self::OPTION_WRITE, $code, $method, $path, 0, 'endpoint_not_allowed', 0, $actor_id, $dry_run );
		}
		$missing = self::missing_fields( $spec, $body );
		if ( [] !== $missing ) {
			return self::fail( self::OPTION_WRITE, $code, $method, $path, 0, 'missing_' . strtolower( implode( '_', $missing ) ), 0, $actor_id, $dry_run );
		}
		if ( '/customer' === $path && ! TestDataFactory::is_test_customer_name( (string) ( $body['Name1'] ?? '' ) ) ) {
			return self::fail( self::OPTION_WRITE, $code, $method, $path, 0, 'not_a_test_customer', 0, $actor_id, $dry_run );
		}
		$ready = self::readiness( $code, true );
		if ( ! $ready['ok'] ) {
			return self::fail( self::OPTION_WRITE, $code, $method, $path, 0, $ready['reason'], 0, $actor_id, $dry_run );
		}
		if ( $dry_run ) {
			return [ 'ok' => true, 'status' => 0, 'data' => null, 'rows' => [], 'error' => 'dry_run', 'duration_ms' => 0, 'body' => $body ];
		}
		return self::request( self::OPTION_WRITE, $code, $method, $path, self::BASE_TEST . $path, $body, true, $actor_id );
	}

	/** @param array<string,mixed> $body @return string[] fehlende Pflichtfelder. */
	public static function missing_fields( string $spec, array $body ): array {
		$missing = [];
		foreach ( self::WRITE_ENDPOINTS[ $spec ] ?? [] as $field ) {
			$v = $body[ $field ] ?? null;
			if ( null === $v || '' === trim( (string) $v ) ) {
				$missing[] = $field;
			}
		}
		return $missing;
	}

	/** Basis-Endpunkt eines Lesepfads (Pfadparameter abgeschnitten); null = nicht freigegeben. */
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

	/** @param array<string,mixed>|null $body */
	private static function request( string $option, string $code, string $method, string $path, string $url, ?array $body, bool $with_user_email, int $actor_id ): array {
		$creds   = DealerRepository::credentials( $code );
		$headers = [ 'Ocp-Apim-Subscription-Key' => (string) ( $creds['key'] ?? '' ), 'Accept' => 'application/json' ];
		if ( $with_user_email ) {
			$headers['UserEmail'] = (string) ( $creds['user_email'] ?? '' );
		}
		$args = [ 'timeout' => self::TIMEOUT, 'headers' => $headers, 'redirection' => 0, 'method' => $method ];
		if ( null !== $body ) {
			$headers['Content-Type'] = 'application/json';
			$args['headers']         = $headers;
			$args['body']            = wp_json_encode( $body, JSON_UNESCAPED_UNICODE );
		}
		$start = microtime( true );
		$res   = wp_remote_request( $url, $args );
		$ms    = (int) round( ( microtime( true ) - $start ) * 1000 );

		if ( is_wp_error( $res ) ) {
			return self::fail( $option, $code, $method, $path, 0, 'transport_error', $ms, $actor_id );
		}
		$status = (int) wp_remote_retrieve_response_code( $res );
		$raw    = (string) wp_remote_retrieve_body( $res );
		$data   = json_decode( $raw, true );
		if ( $status < 200 || $status >= 300 ) {
			// Fachliche Fehlermeldung des Liebherr-Backends (z. B. „Partner not associated to requested entity.") mitführen –
			// kurz, ohne Nutzdaten; hilft bei der Klärung mit Liebherr.
			$msg = is_array( $data ) ? (string) ( $data['status']['message'] ?? $data['message'] ?? '' ) : '';
			$msg = '' !== $msg ? ': ' . substr( sanitize_text_field( $msg ), 0, 120 ) : '';
			return self::fail( $option, $code, $method, $path, $status, 'http_' . $status . $msg, $ms, $actor_id );
		}
		if ( null === $data && '' !== trim( $raw ) ) {
			return self::fail( $option, $code, $method, $path, $status, 'invalid_json', $ms, $actor_id );
		}
		$rows = self::rows_of( $data );
		self::record( $option, $code, $method, $path, $status, true, 'ok', $ms, count( $rows ), $actor_id );
		return [ 'ok' => true, 'status' => $status, 'data' => $data, 'rows' => $rows, 'error' => '', 'duration_ms' => $ms ];
	}

	private static function fail( string $option, string $code, string $method, string $path, int $status, string $error, int $ms, int $actor_id, bool $silent = false ): array {
		if ( ! $silent ) {
			self::record( $option, $code, $method, $path, $status, false, $error, $ms, 0, $actor_id );
		}
		return [ 'ok' => false, 'status' => $status, 'data' => null, 'rows' => [], 'error' => $error, 'duration_ms' => $ms ];
	}

	private static function record( string $option, string $code, string $method, string $path, int $status, bool $ok, string $error, int $ms, int $rows, int $actor_id ): void {
		$entry = [ 'at' => time(), 'dealer' => $code, 'method' => $method, 'endpoint' => $path, 'status' => $status, 'ok' => $ok, 'error' => $error, 'duration_ms' => $ms, 'rows' => $rows, 'env' => 'test' ];
		update_option( $option, $entry, false );
		if ( AuditBridge::is_available() ) {
			$action = 'GET' === $method ? ( $ok ? 'api_read' : 'api_fail' ) : ( $ok ? 'api_write' : 'api_write_fail' );
			AuditBridge::log( $action, 'livision', 0, [], $entry, $actor_id );
		}
	}

	/** @return array<string,mixed>|null Letzter Lese-Lauf (ohne Nutzdaten). */
	public static function last_probe(): ?array {
		$v = get_option( self::OPTION_PROBE, null );
		return is_array( $v ) ? $v : null;
	}

	/** @return array<string,mixed>|null Letzter Schreib-Lauf (ohne Nutzdaten). */
	public static function last_write(): ?array {
		$v = get_option( self::OPTION_WRITE, null );
		return is_array( $v ) ? $v : null;
	}
}
