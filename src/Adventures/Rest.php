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
		// Basislogik „Adventure Area" (§1–§9): Registrierung, Validierung, Tokenzugriff, Moderation.
		register_rest_route( self::NAMESPACE, '/register', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'register_contribution' ],
			'permission_callback' => static function (): bool { return Policy::can_create(); },
		] );
		register_rest_route( self::NAMESPACE, '/request-validation', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'request_validation' ],
			'permission_callback' => static function (): bool { return Policy::can_create(); },
		] );
		register_rest_route( self::NAMESPACE, '/access', [
			'methods'             => 'GET',
			'callback'            => [ self::class, 'access' ],
			'permission_callback' => static function (): bool { return is_user_logged_in(); },
		] );
		register_rest_route( self::NAMESPACE, '/accept', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'accept' ],
			'permission_callback' => static function (): bool { return is_user_logged_in(); },
		] );
		register_rest_route( self::NAMESPACE, '/moderate', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'moderate' ],
			'permission_callback' => static function (): bool { return Policy::can_moderate(); },
		] );
	}

	/** Registriert einen (eigenen) Beitrag mit frei festgelegtem Tokenwert; Rechte-Zusicherung Pflicht (§1/§3). */
	public static function register_contribution( \WP_REST_Request $req ): \WP_REST_Response {
		$post_id = (int) $req->get_param( 'post_id' );
		if ( ! self::owns_or_moderates( $post_id ) ) {
			return new \WP_REST_Response( [ 'ok' => false, 'error' => __( 'Nicht berechtigt.', 'liebherr-interface-world' ) ], 200 );
		}
		$res = RegistrationService::register( $post_id, [
			'token_value'      => (int) $req->get_param( 'token_value' ),
			'usage_scope'      => (string) $req->get_param( 'usage_scope' ),
			'rights_confirmed' => self::truthy( $req->get_param( 'rights_confirmed' ) ),
			'category'         => (string) $req->get_param( 'category' ),
			'org_unit'         => (string) $req->get_param( 'org_unit' ),
			'author_ref'       => get_current_user_id(),
		] );
		return new \WP_REST_Response( array_merge( $res, [ 'registration' => RegistrationService::get_registration( $post_id ) ] ), 200 );
	}

	/** Ersteller beantragt die optionale Validierung (§4). */
	public static function request_validation( \WP_REST_Request $req ): \WP_REST_Response {
		$post_id = (int) $req->get_param( 'post_id' );
		if ( ! self::owns_or_moderates( $post_id ) ) {
			return new \WP_REST_Response( [ 'ok' => false, 'error' => __( 'Nicht berechtigt.', 'liebherr-interface-world' ) ], 200 );
		}
		return new \WP_REST_Response( RegistrationService::request_validation( $post_id ), 200 );
	}

	/** Zugriffsvorschau (§5): zeigt Tokenwert + Nutzungsbedingungen vor der Bestätigung. */
	public static function access( \WP_REST_Request $req ): \WP_REST_Response {
		$post_id = (int) $req->get_param( 'post_id' );
		$preview = RegistrationService::access_preview( $post_id, get_current_user_id(), self::budget_for( get_current_user_id() ) );
		return new \WP_REST_Response( [ 'ok' => true, 'preview' => $preview ], 200 );
	}

	/** Bestätigter Tokenzugriff (§5/§6): protokolliert Nutzung revisionssicher + bucht das Tokenkonto ab. */
	public static function accept( \WP_REST_Request $req ): \WP_REST_Response {
		$post_id = (int) $req->get_param( 'post_id' );
		$user    = get_current_user_id();
		$res     = RegistrationService::record_access( $post_id, $user, [
			'budget'      => self::budget_for( $user ),
			'org_unit'    => (string) $req->get_param( 'org_unit' ),
			'usage_scope' => (string) $req->get_param( 'usage_scope' ),
		] );
		// Echtes Tokenkonto abbuchen – nur bei tatsächlicher Budgetbelastung (nicht Autor/frei/Berechtigung).
		if ( ! empty( $res['ok'] ) && (int) ( $res['charge'] ?? 0 ) > 0 && 'budget_ok' === ( $res['reason'] ?? '' ) ) {
			TokenAccount::charge( $user, (int) $res['charge'] );
		}
		$res['balance'] = TokenAccount::balance( $user );
		return new \WP_REST_Response( $res, 200 );
	}

	/** Prüf-/Freigabe-Aktionen (§8): validieren, freigeben, sperren, archivieren. Nur Moderation. */
	public static function moderate( \WP_REST_Request $req ): \WP_REST_Response {
		$post_id = (int) $req->get_param( 'post_id' );
		$action  = (string) $req->get_param( 'action' );
		switch ( $action ) {
			case 'validate_pass': $res = RegistrationService::set_validation_result( $post_id, true, (string) $req->get_param( 'report' ) ); break;
			case 'validate_fail': $res = RegistrationService::set_validation_result( $post_id, false, (string) $req->get_param( 'report' ) ); break;
			case 'publish':       $res = RegistrationService::publish_world( $post_id ); break;
			case 'block':         $res = RegistrationService::block( $post_id ); break;
			case 'archive':       $res = RegistrationService::archive( $post_id ); break;
			default:              $res = [ 'ok' => false, 'error' => 'unknown_action' ];
		}
		return new \WP_REST_Response( $res, 200 );
	}

	/** Tokenbudget = aktuelles Guthaben des Nutzerkontos (§5/§6, Backlog A5). */
	private static function budget_for( int $user_id ): int {
		return (int) apply_filters( 'liw_adv_token_budget', TokenAccount::balance( $user_id ), $user_id );
	}

	private static function owns_or_moderates( int $post_id ): bool {
		if ( $post_id <= 0 || AdventureCpt::POST_TYPE !== get_post_type( $post_id ) ) {
			return false;
		}
		if ( Policy::can_moderate() ) {
			return true;
		}
		$post = get_post( $post_id );
		return $post instanceof \WP_Post && (int) $post->post_author === get_current_user_id();
	}

	private static function truthy( $v ): bool {
		return in_array( $v, [ true, 1, '1', 'true', 'on', 'yes' ], true );
	}

	public static function stream( \WP_REST_Request $req ): \WP_REST_Response {
		$items = AdventureService::query( [
			'type'      => (string) $req->get_param( 'type' ),
			'urgency'   => (string) $req->get_param( 'urgency' ),
			'search'    => (string) $req->get_param( 'search' ),
			'machine'   => (string) $req->get_param( 'machine' ),
			'component' => (string) $req->get_param( 'component' ),
			'limit'     => (int) $req->get_param( 'limit' ),
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
			'machine'    => (string) $req->get_param( 'machine' ),
			'component'  => (string) $req->get_param( 'component' ),
			'author_id'  => get_current_user_id(),
		] );
		if ( 0 === $res['id'] ) {
			return new \WP_REST_Response( [ 'ok' => false, 'error' => __( 'Adventure konnte nicht gespeichert werden.', 'liebherr-interface-world' ) ], 200 );
		}
		$critical = Taxonomy::is_critical( (string) $req->get_param( 'urgency' ) );

		// Basislogik §1/§3: Ist der Tokenwert gesetzt UND die Rechte-Zusicherung bestätigt, wird der Beitrag
		// direkt registriert (im Artikelbook registriert/verlinkt). Sonst bleibt er Entwurf im Arbeitsbereich.
		$registration = null;
		if ( self::truthy( $req->get_param( 'rights_confirmed' ) ) ) {
			$reg = RegistrationService::register( (int) $res['id'], [
				'token_value'      => (int) $req->get_param( 'token_value' ),
				'usage_scope'      => (string) $req->get_param( 'usage_scope' ),
				'rights_confirmed' => true,
				'category'         => (string) $req->get_param( 'type' ),
				'org_unit'         => (string) $req->get_param( 'org_unit' ),
				'author_ref'       => get_current_user_id(),
			] );
			$registration = array_merge( $reg, [ 'state' => RegistrationService::get_registration( (int) $res['id'] ) ] );
		}

		return new \WP_REST_Response( [
			'ok'           => true,
			'id'           => $res['id'],
			'uuid'         => $res['uuid'],
			'status'       => $res['status'],
			'words'        => $res['words'],
			'critical'     => $critical,
			'registration' => $registration,
			'message'      => $critical
				? __( 'Als kritisch eingestuft: nicht öffentlich, zur priorisierten Prüfung eingereicht. Bitte Maschine sichern und qualifiziertes Personal kontaktieren.', 'liebherr-interface-world' )
				: ( null !== $registration && ! empty( $registration['ok'] )
					? __( 'Beitrag registriert und im Artikelbook eingetragen. Er ist im firmeneigenen Netz nutzbar.', 'liebherr-interface-world' )
					: __( 'Adventure gespeichert. Ohne Registrierung bleibt es Entwurf in Ihrem Arbeitsbereich.', 'liebherr-interface-world' ) ),
		], 200 );
	}
}
