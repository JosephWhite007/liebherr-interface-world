<?php
/**
 * Liebherr World – My Liebherr: REST (Pflichtenheft My Liebherr §18, ADR-LIW-MYL-001 S1).
 *
 *   GET   my-liebherr/v1/me – Profil, Rollen, Organisationen und aktiver Kontext (nur eigener Nutzer, Objektfilter).
 *   PATCH my-liebherr/v1/me – erlaubte Profil-/Präferenzfelder (Feldfreigabe über {@see Context::sanitize_patch()}).
 *
 * Angemeldet erforderlich ({@see require_login()}); WordPress erzwingt für Cookie-basierte Schreibzugriffe im
 * Browser zusätzlich den REST-Nonce (`X-WP-Nonce`, §16). Alles nur aktiv hinter {@see Flags::enabled()} – sonst
 * reason=disabled. Sichtbarkeit ≠ Berechtigung: der Endpunkt bezieht sich ausschließlich auf den angemeldeten
 * Nutzer selbst (§35, SEC 01).
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.108
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Rest {

	public const NAMESPACE = 'my-liebherr/v1';

	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'routes' ] );
	}

	public static function routes(): void {
		register_rest_route( self::NAMESPACE, '/me', [
			[
				'methods'             => 'GET',
				'callback'            => [ self::class, 'get_me' ],
				'permission_callback' => [ self::class, 'require_login' ],
			],
			[
				'methods'             => 'PATCH',
				'callback'            => [ self::class, 'patch_me' ],
				'permission_callback' => [ self::class, 'require_login' ],
			],
		] );
	}

	/** Angemeldet erforderlich (WP prüft bei Cookie-Auth zusätzlich den REST-Nonce). */
	public static function require_login(): bool {
		return is_user_logged_in();
	}

	public static function get_me( \WP_REST_Request $req ): \WP_REST_Response {
		unset( $req );
		if ( ! Flags::enabled() ) {
			return self::disabled();
		}
		$uid = get_current_user_id();
		if ( $uid <= 0 ) {
			return new \WP_REST_Response( [ 'ok' => false, 'reason' => 'not_logged_in' ], 401 );
		}
		return new \WP_REST_Response( [ 'ok' => true, 'me' => Context::for_user( $uid ) ], 200 );
	}

	public static function patch_me( \WP_REST_Request $req ): \WP_REST_Response {
		if ( ! Flags::enabled() ) {
			return self::disabled();
		}
		$uid = get_current_user_id();
		if ( $uid <= 0 ) {
			return new \WP_REST_Response( [ 'ok' => false, 'reason' => 'not_logged_in' ], 401 );
		}
		$params = $req->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = (array) $req->get_params();
		}
		$patch = Context::sanitize_patch( $params );
		ProfileRepository::update( $uid, $patch );
		return new \WP_REST_Response( [
			'ok'      => true,
			'me'      => Context::for_user( $uid ),
			'applied' => array_keys( $patch ),
		], 200 );
	}

	private static function disabled(): \WP_REST_Response {
		return new \WP_REST_Response( [ 'ok' => false, 'reason' => 'disabled' ], 200 );
	}
}
