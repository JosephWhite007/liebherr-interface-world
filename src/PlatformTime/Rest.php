<?php
/**
 * Liebherr World – Plattformzeit: REST (Pflichtenheft My Liebherr §41.4, ADR-LIW-MYL-001 S9/S10).
 *
 *   POST my-liebherr/v1/platform-time/start     – Abschnitt reservieren/fortsetzen (Eintritt).
 *   POST my-liebherr/v1/platform-time/heartbeat – serverautoritärer Herzschlag (schreibt aktive Zeit fort).
 *   GET  my-liebherr/v1/platform-time/status    – laufende Zeit + Tokenstand (Anzeige der Schachuhr).
 *   POST my-liebherr/v1/platform-time/stop       – Abschnitt bestätigen (Verlassen) + Abrechnungssatz.
 *
 * Angemeldet erforderlich; nur eigene Sitzung (§35/SEC 01). Self-gating über {@see Flags::enabled()} – sonst
 * reason=disabled.
 *
 * @package Liebherr\InterfaceWorld\PlatformTime
 * @since   0.1.0-alpha.111
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\PlatformTime;

use Liebherr\InterfaceWorld\MyLiebherr\Roles as MylRoles;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Rest {

	public const NAMESPACE = 'my-liebherr/v1';

	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'routes' ] );
	}

	public static function routes(): void {
		$login = [ 'permission_callback' => [ self::class, 'require_access' ] ];
		register_rest_route( self::NAMESPACE, '/platform-time/start',     [ 'methods' => 'POST', 'callback' => [ self::class, 'start' ] ] + $login );
		register_rest_route( self::NAMESPACE, '/platform-time/heartbeat', [ 'methods' => 'POST', 'callback' => [ self::class, 'heartbeat' ] ] + $login );
		register_rest_route( self::NAMESPACE, '/platform-time/status',    [ 'methods' => 'GET',  'callback' => [ self::class, 'status' ] ] + $login );
		register_rest_route( self::NAMESPACE, '/platform-time/stop',      [ 'methods' => 'POST', 'callback' => [ self::class, 'stop' ] ] + $login );
	}

	/** Angemeldet + My-Liebherr-Zugang (WP prüft bei Cookie-Auth zusätzlich den REST-Nonce). */
	public static function require_access(): bool {
		return is_user_logged_in() && current_user_can( MylRoles::CAP_ACCESS );
	}

	public static function start( \WP_REST_Request $req ): \WP_REST_Response {
		unset( $req );
		if ( ! Flags::enabled() ) {
			return self::disabled();
		}
		$uid = get_current_user_id();
		SessionRepository::start( $uid );
		return new \WP_REST_Response( [ 'ok' => true ] + SessionRepository::status( $uid ), 200 );
	}

	public static function heartbeat( \WP_REST_Request $req ): \WP_REST_Response {
		unset( $req );
		if ( ! Flags::enabled() ) {
			return self::disabled();
		}
		return new \WP_REST_Response( [ 'ok' => true ] + SessionRepository::heartbeat( get_current_user_id() ), 200 );
	}

	public static function status( \WP_REST_Request $req ): \WP_REST_Response {
		unset( $req );
		if ( ! Flags::enabled() ) {
			return self::disabled();
		}
		return new \WP_REST_Response( [ 'ok' => true ] + SessionRepository::status( get_current_user_id() ), 200 );
	}

	public static function stop( \WP_REST_Request $req ): \WP_REST_Response {
		unset( $req );
		if ( ! Flags::enabled() ) {
			return self::disabled();
		}
		return new \WP_REST_Response( SessionRepository::stop( get_current_user_id() ), 200 );
	}

	private static function disabled(): \WP_REST_Response {
		return new \WP_REST_Response( [ 'ok' => false, 'reason' => 'disabled' ], 200 );
	}
}
