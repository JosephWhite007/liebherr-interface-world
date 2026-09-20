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
use Liebherr\InterfaceWorld\Cvf\ChallengeService;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Rest {

	public const NAMESPACE = 'my-liebherr/v1';

	/** Signaturgeheimnis der Standby-Rechenaufgabe (eigenständig; wird bei Bedarf erzeugt). */
	private const SECRET_OPT = 'liw_ptime_secret';

	/** Einmalgebrauch der Aufgaben-Nonce (Sekunden). */
	private const USED_TTL = 600;

	/** Schwierigkeit der Standby-Rückkehr: zwei zweistellige Zahlen (wie die Welten-Gates). */
	private const DIFFICULTY = 'double';

	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'routes' ] );
	}

	public static function routes(): void {
		$login = [ 'permission_callback' => [ self::class, 'require_access' ] ];
		register_rest_route( self::NAMESPACE, '/platform-time/start',     [ 'methods' => 'POST', 'callback' => [ self::class, 'start' ] ] + $login );
		register_rest_route( self::NAMESPACE, '/platform-time/heartbeat', [ 'methods' => 'POST', 'callback' => [ self::class, 'heartbeat' ] ] + $login );
		register_rest_route( self::NAMESPACE, '/platform-time/status',    [ 'methods' => 'GET',  'callback' => [ self::class, 'status' ] ] + $login );
		register_rest_route( self::NAMESPACE, '/platform-time/stop',      [ 'methods' => 'POST', 'callback' => [ self::class, 'stop' ] ] + $login );
		register_rest_route( self::NAMESPACE, '/platform-time/standby',   [ 'methods' => 'POST', 'callback' => [ self::class, 'standby' ] ] + $login );
		register_rest_route( self::NAMESPACE, '/platform-time/resume',    [ 'methods' => 'POST', 'callback' => [ self::class, 'resume' ] ] + $login );
		register_rest_route( self::NAMESPACE, '/platform-time/challenge', [ 'methods' => 'GET',  'callback' => [ self::class, 'challenge' ] ] + $login );
	}

	/** Angemeldet + My-Liebherr-Zugang (WP prüft bei Cookie-Auth zusätzlich den REST-Nonce). */
	public static function require_access(): bool {
		return is_user_logged_in() && current_user_can( MylRoles::CAP_ACCESS );
	}

	private static function secret(): string {
		$s = (string) get_option( self::SECRET_OPT, '' );
		if ( '' === $s ) {
			$s = bin2hex( random_bytes( 32 ) );
			update_option( self::SECRET_OPT, $s, false );
		}
		return $s;
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

	/** Standby: Abschnitt einfrieren + Plattform sperren (ADR-LIW-MYL-002 §7). Keine Abrechnung. */
	public static function standby( \WP_REST_Request $req ): \WP_REST_Response {
		unset( $req );
		if ( ! Flags::enabled() ) {
			return self::disabled();
		}
		return new \WP_REST_Response( [ 'ok' => true ] + SessionRepository::standby( get_current_user_id() ), 200 );
	}

	/** GET: liefert die Standby-Rückkehr-Rechenaufgabe (Frage + signiertes Token; Summe bleibt am Server). */
	public static function challenge( \WP_REST_Request $req ): \WP_REST_Response {
		unset( $req );
		if ( ! Flags::enabled() ) {
			return self::disabled();
		}
		$c = ChallengeService::create( self::secret(), time(), self::DIFFICULTY );
		return new \WP_REST_Response( [
			'ok'       => true,
			'question' => ChallengeService::question( (int) $c['a'], (int) $c['b'] ),
			'token'    => (string) $c['token'],
		], 200 );
	}

	/** Resume: prüft die Rechenaufgabe (einmalig) und setzt den Abschnitt fort (ADR-LIW-MYL-002 §7). */
	public static function resume( \WP_REST_Request $req ): \WP_REST_Response {
		if ( ! Flags::enabled() ) {
			return self::disabled();
		}
		$token  = (string) $req->get_param( 'token' );
		$answer = (int) $req->get_param( 'answer' );
		$res    = ChallengeService::verify( $token, $answer, self::secret(), time() );
		if ( ! $res['ok'] ) {
			return new \WP_REST_Response( [ 'ok' => false, 'reason' => $res['reason'] ], 200 );
		}
		// Einmalgebrauch der Nonce erzwingen (wie Emergency): eine gelöste Aufgabe öffnet nur einmal.
		$used_key = 'liw_ptime_used_' . $res['nonce'];
		if ( '' !== $res['nonce'] && false !== get_transient( $used_key ) ) {
			return new \WP_REST_Response( [ 'ok' => false, 'reason' => 'used' ], 200 );
		}
		if ( '' !== $res['nonce'] ) {
			set_transient( $used_key, 1, self::USED_TTL );
		}
		return new \WP_REST_Response( SessionRepository::resume( get_current_user_id() ), 200 );
	}

	private static function disabled(): \WP_REST_Response {
		return new \WP_REST_Response( [ 'ok' => false, 'reason' => 'disabled' ], 200 );
	}
}
