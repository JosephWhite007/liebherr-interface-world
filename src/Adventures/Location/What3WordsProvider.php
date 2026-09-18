<?php
/**
 * Liebherr Adventures – what3words-Ortsdienst (echter Provider, §4).
 *
 * Bindet what3words über den vorhandenen Provider-Vertrag an: Koordinaten ↔ drei englische Wörter,
 * **immer `language=en`** (keine Sprachumschalung, §15/Nutzervorgabe). Der API-Key liegt NICHT im Repo
 * (§22.15): er kommt aus der Konstante `LIW_W3W_API_KEY` (wp-config) oder der Option `liw_w3w_api_key`,
 * über Filter `liw_w3w_api_key` überschreibbar. Ohne Key/bei Fehler wirft der Provider – LocationService
 * fällt dann auf den Mock zurück (Prototyp, §4.3). Robust: kurze Timeouts, Fehler werden abgefangen.
 *
 * @package Liebherr\InterfaceWorld\Adventures\Location
 * @since   0.1.0-alpha.53
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Adventures\Location;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class What3WordsProvider implements ProviderInterface {

	private const API = 'https://api.what3words.com/v3/';

	public function name(): string {
		return 'what3words';
	}

	public function version(): string {
		return 'w3w-v3';
	}

	/** API-Key aus Konstante/Option/Filter; '' = nicht konfiguriert. */
	public static function api_key(): string {
		$key = defined( 'LIW_W3W_API_KEY' ) ? (string) constant( 'LIW_W3W_API_KEY' ) : (string) get_option( 'liw_w3w_api_key', '' );
		return (string) apply_filters( 'liw_w3w_api_key', $key );
	}

	public static function configured(): bool {
		return '' !== self::api_key();
	}

	public function encode( float $lat, float $lng ): array {
		$data = $this->request( 'convert-to-3wa', [
			'coordinates' => $lat . ',' . $lng,
			'language'    => 'en', // fix Englisch – keine Umschaltung.
		] );
		$words = isset( $data['words'] ) ? (string) $data['words'] : '';
		if ( '' === $words ) {
			throw new \RuntimeException( 'what3words: keine Wörter erhalten' );
		}
		$coord = isset( $data['coordinates'] ) && is_array( $data['coordinates'] ) ? $data['coordinates'] : [];
		return [
			'words'    => $words,
			'lat'      => isset( $coord['lat'] ) ? (float) $coord['lat'] : $lat,
			'lng'      => isset( $coord['lng'] ) ? (float) $coord['lng'] : $lng,
			'accuracy' => 'w3w_3m',
		];
	}

	public function decode( string $words ): ?array {
		$w = LocationService::normalize_words( $words );
		if ( '' === $w ) {
			return null;
		}
		try {
			$data = $this->request( 'convert-to-coordinates', [ 'words' => $w ] );
		} catch ( \Throwable $e ) {
			return null;
		}
		$coord = isset( $data['coordinates'] ) && is_array( $data['coordinates'] ) ? $data['coordinates'] : [];
		if ( ! isset( $coord['lat'], $coord['lng'] ) ) {
			return null;
		}
		return [ 'lat' => (float) $coord['lat'], 'lng' => (float) $coord['lng'] ];
	}

	/**
	 * @param array<string,string> $args
	 * @return array<string,mixed>
	 */
	private function request( string $endpoint, array $args ): array {
		$key = self::api_key();
		if ( '' === $key ) {
			throw new \RuntimeException( 'what3words: kein API-Key konfiguriert' );
		}
		$args['key'] = $key;
		$url = self::API . $endpoint . '?' . http_build_query( $args );

		$res = wp_remote_get( $url, [ 'timeout' => 5, 'redirection' => 1 ] );
		if ( is_wp_error( $res ) ) {
			throw new \RuntimeException( 'what3words: ' . $res->get_error_message() );
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			throw new \RuntimeException( 'what3words: HTTP ' . wp_remote_retrieve_response_code( $res ) );
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $body ) || isset( $body['error'] ) ) {
			throw new \RuntimeException( 'what3words: Fehlerantwort' );
		}
		return $body;
	}
}
