<?php
/**
 * Liebherr Adventures – REST (§13, MVP: stream, locate, create).
 *
 *   GET  liw-adv/v1/stream  – sichtbare Adventures (Filter type/urgency/limit), öffentlich.
 *   POST liw-adv/v1/locate  – Koordinaten → Drei-Wörter-Ort (Mock/Provider), öffentlich.
 *   POST liw-adv/v1/create  – Adventure anlegen; NUR mit Intelligence-Zugang (Policy::can_create), Nonce.
 *
 * Serverseitige Autorisierung/Sichtbarkeit (§8/§13). Create verlangt Login + WP-Nonce (X-WP-Nonce);
 * eingeloggte Seiten werden nicht page-gecacht, daher hier kein Nonce-Cache-Problem wie bei der IW-Landing.
 *
 * @package Liebherr\InterfaceWorld\Adventures
 * @since   0.1.0-alpha.51
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Adventures;

use Liebherr\InterfaceWorld\Adventures\Location\LocationService;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Rest {

	public const NAMESPACE = 'liw-adv/v1';

	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'routes' ] );
	}

	public static function routes(): void {
		register_rest_route( self::NAMESPACE, '/stream', [
			'methods'             => 'GET',
			'callback'            => [ self::class, 'stream' ],
			'permission_callback' => '__return_true',
		] );
		register_rest_route( self::NAMESPACE, '/locate', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'locate' ],
			'permission_callback' => '__return_true',
		] );
		register_rest_route( self::NAMESPACE, '/create', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'create' ],
			'permission_callback' => static function (): bool { return Policy::can_create(); },
		] );
	}

	public static function stream( \WP_REST_Request $req ): \WP_REST_Response {
		$items = AdventureService::query( [
			'type'    => (string) $req->get_param( 'type' ),
			'urgency' => (string) $req->get_param( 'urgency' ),
			'limit'   => (int) $req->get_param( 'limit' ),
		] );
		return new \WP_REST_Response( [ 'ok' => true, 'items' => $items ], 200 );
	}

	public static function locate( \WP_REST_Request $req ): \WP_REST_Response {
		$lat = $req->get_param( 'lat' );
		$lng = $req->get_param( 'lng' );
		if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) {
			return new \WP_REST_Response( [ 'ok' => false, 'error' => __( 'Ungültige Koordinaten.', 'liebherr-interface-world' ) ], 200 );
		}
		$loc = LocationService::encode( (float) $lat, (float) $lng );
		return new \WP_REST_Response( [
			'ok'       => true,
			'words'    => $loc['words'],
			'lat'      => $loc['lat'],
			'lng'      => $loc['lng'],
			'region'   => AdventureService::region_for( $loc['lat'], $loc['lng'] ),
			'provider' => $loc['provider'],
		], 200 );
	}

	public static function create( \WP_REST_Request $req ): \WP_REST_Response {
		if ( ! Policy::can_create() ) {
			return new \WP_REST_Response( [ 'ok' => false, 'error' => __( 'Aktiver Intelligence-Zugang erforderlich.', 'liebherr-interface-world' ) ], 200 );
		}
		$res = AdventureService::create( [
			'title'      => (string) $req->get_param( 'title' ),
			'story'      => (string) $req->get_param( 'story' ),
			'type'       => (string) $req->get_param( 'type' ),
			'urgency'    => (string) $req->get_param( 'urgency' ),
			'visibility' => (string) $req->get_param( 'visibility' ),
			'intent'     => (string) $req->get_param( 'intent' ),
			'protection' => (string) $req->get_param( 'protection' ),
			'lat'        => $req->get_param( 'lat' ),
			'lng'        => $req->get_param( 'lng' ),
			'words'      => (string) $req->get_param( 'words' ),
			'image_url'  => (string) $req->get_param( 'image_url' ),
			'author_id'  => get_current_user_id(),
		] );
		if ( 0 === $res['id'] ) {
			return new \WP_REST_Response( [ 'ok' => false, 'error' => __( 'Adventure konnte nicht gespeichert werden.', 'liebherr-interface-world' ) ], 200 );
		}
		$critical = Taxonomy::is_critical( (string) $req->get_param( 'urgency' ) );
		return new \WP_REST_Response( [
			'ok'       => true,
			'uuid'     => $res['uuid'],
			'status'   => $res['status'],
			'words'    => $res['words'],
			'critical' => $critical,
			'message'  => $critical
				? __( 'Als kritisch eingestuft: nicht öffentlich, zur priorisierten Prüfung eingereicht. Bitte Maschine sichern und qualifiziertes Personal kontaktieren.', 'liebherr-interface-world' )
				: __( 'Adventure eingereicht. Es erscheint nach der Prüfung im Stream.', 'liebherr-interface-world' ),
		], 200 );
	}
}
