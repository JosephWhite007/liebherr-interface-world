<?php
/**
 * Liebherr World – Pocket Information: REST (Pflichtenheft My Liebherr §37, ADR-LIW-MYL-001 R5).
 *
 *   GET    pocket/v1/feed            – personenbezogene Pocket-Infos (berechtigungsgefiltert)
 *   POST   pocket/v1/items           – Pocket-Item anlegen (manuell/seedbar)
 *   POST   pocket/v1/items/{id}/ack  – Pflichtinformation quittieren
 *   DELETE pocket/v1/items/{id}      – Item entfernen
 *
 * Angemeldet + {@see \Liebherr\InterfaceWorld\MyLiebherr\Roles::CAP_ACCESS}; nur eigene Items (§35/SEC 01).
 * Self-gating über {@see Flags::enabled()}.
 *
 * @package Liebherr\InterfaceWorld\Pocket
 * @since   0.1.0-alpha.124
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Pocket;

use Liebherr\InterfaceWorld\MyLiebherr\Roles as MylRoles;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Rest {

	public const NAMESPACE = 'pocket/v1';

	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'routes' ] );
	}

	public static function routes(): void {
		$perm = [ 'permission_callback' => [ self::class, 'require_access' ] ];
		$id   = '(?P<id>\d+)';
		register_rest_route( self::NAMESPACE, '/feed', [ [ 'methods' => 'GET', 'callback' => [ self::class, 'feed' ] ] + $perm ] );
		register_rest_route( self::NAMESPACE, '/items', [ [ 'methods' => 'POST', 'callback' => [ self::class, 'create' ] ] + $perm ] );
		register_rest_route( self::NAMESPACE, '/items/' . $id . '/ack', [ [ 'methods' => 'POST', 'callback' => [ self::class, 'ack' ] ] + $perm ] );
		register_rest_route( self::NAMESPACE, '/items/' . $id, [ [ 'methods' => 'DELETE', 'callback' => [ self::class, 'delete' ] ] + $perm ] );
	}

	public static function require_access(): bool {
		return is_user_logged_in() && current_user_can( MylRoles::CAP_ACCESS );
	}

	private static function gate(): ?\WP_REST_Response {
		return Flags::enabled() ? null : new \WP_REST_Response( [ 'ok' => false, 'reason' => 'disabled' ], 200 );
	}

	public static function feed( \WP_REST_Request $r ): \WP_REST_Response {
		unset( $r );
		return self::gate() ?? new \WP_REST_Response( [ 'ok' => true, 'feed' => FeedService::feed( get_current_user_id() ) ], 200 );
	}

	public static function create( \WP_REST_Request $r ): \WP_REST_Response {
		$g = self::gate(); if ( $g ) { return $g; }
		$item = PocketRepository::add( get_current_user_id(), (array) $r->get_params() );
		return new \WP_REST_Response( [ 'ok' => null !== $item, 'item' => $item ], 200 );
	}

	public static function ack( \WP_REST_Request $r ): \WP_REST_Response {
		$g = self::gate(); if ( $g ) { return $g; }
		return new \WP_REST_Response( PocketRepository::acknowledge( get_current_user_id(), (int) $r['id'] ), 200 );
	}

	public static function delete( \WP_REST_Request $r ): \WP_REST_Response {
		$g = self::gate(); if ( $g ) { return $g; }
		return new \WP_REST_Response( [ 'ok' => PocketRepository::delete( get_current_user_id(), (int) $r['id'] ) ], 200 );
	}
}
