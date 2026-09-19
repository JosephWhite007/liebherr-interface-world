<?php
/**
 * Liebherr World – My Liebherr: REST Kontakte/Connections/Leistungen + Maschinen (§33/§29, ADR-LIW-MYL-001 R4/R5).
 *
 *   POST/GET  contacts/requests · POST contacts/requests/{id}/decision
 *   GET       connections · POST connections/{id}/status · GET/POST connections/{id}/services
 *   POST      services/{id}/status
 *   GET/POST  machines · DELETE machines/{id}
 *
 * Angemeldet + {@see Roles::CAP_ACCESS}; nur eigene Objekte/Beteiligung (§35/SEC 01). Self-gating über {@see Flags::enabled()}.
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.122
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ContactsRest {

	public const NAMESPACE = 'my-liebherr/v1';

	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'routes' ] );
	}

	public static function routes(): void {
		$perm = [ 'permission_callback' => [ self::class, 'require_access' ] ];
		$id   = '(?P<id>\d+)';
		register_rest_route( self::NAMESPACE, '/contacts/requests', [
			[ 'methods' => 'GET', 'callback' => [ self::class, 'req_list' ] ] + $perm,
			[ 'methods' => 'POST', 'callback' => [ self::class, 'req_create' ] ] + $perm,
		] );
		register_rest_route( self::NAMESPACE, '/contacts/requests/' . $id . '/decision', [ [ 'methods' => 'POST', 'callback' => [ self::class, 'req_decide' ] ] + $perm ] );
		register_rest_route( self::NAMESPACE, '/connections', [ [ 'methods' => 'GET', 'callback' => [ self::class, 'conn_list' ] ] + $perm ] );
		register_rest_route( self::NAMESPACE, '/connections/' . $id . '/status', [ [ 'methods' => 'POST', 'callback' => [ self::class, 'conn_status' ] ] + $perm ] );
		register_rest_route( self::NAMESPACE, '/connections/' . $id . '/services', [
			[ 'methods' => 'GET', 'callback' => [ self::class, 'svc_list' ] ] + $perm,
			[ 'methods' => 'POST', 'callback' => [ self::class, 'svc_propose' ] ] + $perm,
		] );
		register_rest_route( self::NAMESPACE, '/services/' . $id . '/status', [ [ 'methods' => 'POST', 'callback' => [ self::class, 'svc_status' ] ] + $perm ] );
		register_rest_route( self::NAMESPACE, '/machines', [
			[ 'methods' => 'GET', 'callback' => [ self::class, 'mac_list' ] ] + $perm,
			[ 'methods' => 'POST', 'callback' => [ self::class, 'mac_create' ] ] + $perm,
		] );
		register_rest_route( self::NAMESPACE, '/machines/' . $id, [ [ 'methods' => 'DELETE', 'callback' => [ self::class, 'mac_delete' ] ] + $perm ] );
	}

	public static function require_access(): bool {
		return is_user_logged_in() && current_user_can( Roles::CAP_ACCESS );
	}

	private static function gate(): ?\WP_REST_Response {
		return Flags::enabled() ? null : new \WP_REST_Response( [ 'ok' => false, 'reason' => 'disabled' ], 200 );
	}

	private static function uid(): int {
		return get_current_user_id();
	}

	/** Empfänger auflösen: recipient_id direkt oder recipient_email (ohne Profildaten preiszugeben, §33). */
	private static function resolve_recipient( \WP_REST_Request $r ): int {
		$rid = (int) $r->get_param( 'recipient_id' );
		if ( $rid > 0 ) {
			return $rid;
		}
		$email = sanitize_email( (string) $r->get_param( 'recipient_email' ) );
		if ( '' !== $email ) {
			$u = get_user_by( 'email', $email );
			if ( $u instanceof \WP_User ) {
				return (int) $u->ID;
			}
		}
		return 0;
	}

	// ── Requests ───────────────────────────────────────────────────────────────
	public static function req_list( \WP_REST_Request $r ): \WP_REST_Response {
		unset( $r );
		return self::gate() ?? new \WP_REST_Response( [ 'ok' => true, 'requests' => ContactRepository::requests_for( self::uid() ) ], 200 );
	}

	public static function req_create( \WP_REST_Request $r ): \WP_REST_Response {
		$g = self::gate(); if ( $g ) { return $g; }
		$recipient = self::resolve_recipient( $r );
		$req = ContactRepository::create_request( self::uid(), $recipient, (string) $r->get_param( 'purpose' ), (string) $r->get_param( 'service_hint' ), (int) $r->get_param( 'token_frame' ) );
		return new \WP_REST_Response( [ 'ok' => null !== $req, 'request' => $req, 'reason' => null === $req ? 'unknown_recipient' : '' ], 200 );
	}

	public static function req_decide( \WP_REST_Request $r ): \WP_REST_Response {
		$g = self::gate(); if ( $g ) { return $g; }
		return new \WP_REST_Response( ContactRepository::decide( self::uid(), (int) $r['id'], (string) $r->get_param( 'decision' ) ), 200 );
	}

	// ── Connections ──────────────────────────────────────────────────────────────
	public static function conn_list( \WP_REST_Request $r ): \WP_REST_Response {
		unset( $r );
		return self::gate() ?? new \WP_REST_Response( [ 'ok' => true, 'connections' => ContactRepository::connections_for( self::uid() ) ], 200 );
	}

	public static function conn_status( \WP_REST_Request $r ): \WP_REST_Response {
		$g = self::gate(); if ( $g ) { return $g; }
		return new \WP_REST_Response( ContactRepository::set_connection_status( self::uid(), (int) $r['id'], (string) $r->get_param( 'status' ) ), 200 );
	}

	// ── Service Exchange ─────────────────────────────────────────────────────────
	public static function svc_list( \WP_REST_Request $r ): \WP_REST_Response {
		$g = self::gate(); if ( $g ) { return $g; }
		$conn = ContactRepository::connection( (int) $r['id'] );
		if ( null === $conn || ! ContactRepository::is_party( self::uid(), $conn ) ) {
			return new \WP_REST_Response( [ 'ok' => false, 'reason' => 'forbidden' ], 403 );
		}
		return new \WP_REST_Response( [ 'ok' => true, 'services' => ContactRepository::services_for( (int) $r['id'] ) ], 200 );
	}

	public static function svc_propose( \WP_REST_Request $r ): \WP_REST_Response {
		$g = self::gate(); if ( $g ) { return $g; }
		return new \WP_REST_Response( ContactRepository::propose_service( self::uid(), (int) $r['id'], (string) $r->get_param( 'description' ), (int) $r->get_param( 'token_amount' ) ), 200 );
	}

	public static function svc_status( \WP_REST_Request $r ): \WP_REST_Response {
		$g = self::gate(); if ( $g ) { return $g; }
		return new \WP_REST_Response( ContactRepository::set_service_status( self::uid(), (int) $r['id'], (string) $r->get_param( 'status' ) ), 200 );
	}

	// ── Machines ─────────────────────────────────────────────────────────────────
	public static function mac_list( \WP_REST_Request $r ): \WP_REST_Response {
		unset( $r );
		return self::gate() ?? new \WP_REST_Response( [ 'ok' => true, 'machines' => MachineRepository::for_user( self::uid() ) ], 200 );
	}

	public static function mac_create( \WP_REST_Request $r ): \WP_REST_Response {
		$g = self::gate(); if ( $g ) { return $g; }
		$m = MachineRepository::add( self::uid(), (array) $r->get_params() );
		return new \WP_REST_Response( [ 'ok' => null !== $m, 'machine' => $m ], 200 );
	}

	public static function mac_delete( \WP_REST_Request $r ): \WP_REST_Response {
		$g = self::gate(); if ( $g ) { return $g; }
		return new \WP_REST_Response( [ 'ok' => MachineRepository::delete( self::uid(), (int) $r['id'] ) ], 200 );
	}
}
