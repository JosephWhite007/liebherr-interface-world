<?php
/**
 * Liebherr World – My Liebherr: REST für Dreams/Gallery/Shares (Pflichtenheft My Liebherr §31/§37, ADR-LIW-MYL-001 R3).
 *
 *   GET/POST         my-liebherr/v1/dreams              – Dreams lesen/hinzufügen
 *   PATCH/DELETE     my-liebherr/v1/dreams/{id}         – Dream ändern/entfernen
 *   POST             my-liebherr/v1/dreams/{id}/move    – Reihenfolge ändern
 *   GET/POST         my-liebherr/v1/gallery             – Galerie lesen/hinzufügen
 *   PATCH/DELETE     my-liebherr/v1/gallery/{id}        – Galerie-Objekt ändern/entfernen
 *   GET/POST         my-liebherr/v1/gallery/{id}/shares – Freigaben lesen/erteilen
 *   DELETE           my-liebherr/v1/shares/{id}         – Freigabe widerrufen
 *
 * Angemeldet + {@see Roles::CAP_ACCESS}; nur eigene Objekte (§35/SEC 01). Self-gating über {@see Flags::enabled()}.
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.119
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ContentRest {

	public const NAMESPACE = 'my-liebherr/v1';

	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'routes' ] );
	}

	public static function routes(): void {
		$perm = [ 'permission_callback' => [ self::class, 'require_access' ] ];
		$id   = '(?P<id>\d+)';
		register_rest_route( self::NAMESPACE, '/dreams', [
			[ 'methods' => 'GET', 'callback' => [ self::class, 'dreams_list' ] ] + $perm,
			[ 'methods' => 'POST', 'callback' => [ self::class, 'dreams_create' ] ] + $perm,
		] );
		register_rest_route( self::NAMESPACE, '/dreams/' . $id, [
			[ 'methods' => 'PATCH', 'callback' => [ self::class, 'dreams_update' ] ] + $perm,
			[ 'methods' => 'DELETE', 'callback' => [ self::class, 'dreams_delete' ] ] + $perm,
		] );
		register_rest_route( self::NAMESPACE, '/dreams/' . $id . '/move', [ [ 'methods' => 'POST', 'callback' => [ self::class, 'dreams_move' ] ] + $perm ] );
		register_rest_route( self::NAMESPACE, '/gallery', [
			[ 'methods' => 'GET', 'callback' => [ self::class, 'gallery_list' ] ] + $perm,
			[ 'methods' => 'POST', 'callback' => [ self::class, 'gallery_create' ] ] + $perm,
		] );
		register_rest_route( self::NAMESPACE, '/gallery/' . $id, [
			[ 'methods' => 'PATCH', 'callback' => [ self::class, 'gallery_update' ] ] + $perm,
			[ 'methods' => 'DELETE', 'callback' => [ self::class, 'gallery_delete' ] ] + $perm,
		] );
		register_rest_route( self::NAMESPACE, '/gallery/' . $id . '/shares', [
			[ 'methods' => 'GET', 'callback' => [ self::class, 'shares_list' ] ] + $perm,
			[ 'methods' => 'POST', 'callback' => [ self::class, 'shares_create' ] ] + $perm,
		] );
		register_rest_route( self::NAMESPACE, '/shares/' . $id, [ [ 'methods' => 'DELETE', 'callback' => [ self::class, 'shares_revoke' ] ] + $perm ] );
		register_rest_route( self::NAMESPACE, '/adventures/' . $id . '/label', [
			[ 'methods' => 'GET', 'callback' => [ self::class, 'label_get' ] ] + $perm,
			[ 'methods' => 'PUT', 'callback' => [ self::class, 'label_put' ] ] + $perm,
		] );
		register_rest_route( self::NAMESPACE, '/adventures/labels/search', [ [ 'methods' => 'GET', 'callback' => [ self::class, 'label_search' ] ] + $perm ] );
	}

	public static function require_access(): bool {
		return is_user_logged_in() && current_user_can( Roles::CAP_ACCESS );
	}

	private static function gate(): ?\WP_REST_Response {
		if ( ! Flags::enabled() ) {
			return new \WP_REST_Response( [ 'ok' => false, 'reason' => 'disabled' ], 200 );
		}
		return null;
	}

	private static function uid(): int {
		return get_current_user_id();
	}

	// ── Dreams ──────────────────────────────────────────────────────────────────
	public static function dreams_list( \WP_REST_Request $r ): \WP_REST_Response {
		unset( $r );
		return self::gate() ?? new \WP_REST_Response( [ 'ok' => true, 'dreams' => DreamRepository::for_user( self::uid() ) ], 200 );
	}

	public static function dreams_create( \WP_REST_Request $r ): \WP_REST_Response {
		$g = self::gate(); if ( $g ) { return $g; }
		$item = DreamRepository::add( self::uid(), (array) $r->get_params() );
		return new \WP_REST_Response( [ 'ok' => null !== $item, 'dream' => $item ], 200 );
	}

	public static function dreams_update( \WP_REST_Request $r ): \WP_REST_Response {
		$g = self::gate(); if ( $g ) { return $g; }
		$item = DreamRepository::update( self::uid(), (int) $r['id'], (array) $r->get_params() );
		return new \WP_REST_Response( [ 'ok' => null !== $item, 'dream' => $item ], 200 );
	}

	public static function dreams_delete( \WP_REST_Request $r ): \WP_REST_Response {
		$g = self::gate(); if ( $g ) { return $g; }
		return new \WP_REST_Response( [ 'ok' => DreamRepository::delete( self::uid(), (int) $r['id'] ) ], 200 );
	}

	public static function dreams_move( \WP_REST_Request $r ): \WP_REST_Response {
		$g = self::gate(); if ( $g ) { return $g; }
		$dir = 'up' === (string) $r->get_param( 'dir' ) ? 'up' : 'down';
		return new \WP_REST_Response( [ 'ok' => DreamRepository::move( self::uid(), (int) $r['id'], $dir ) ], 200 );
	}

	// ── Gallery ─────────────────────────────────────────────────────────────────
	public static function gallery_list( \WP_REST_Request $r ): \WP_REST_Response {
		unset( $r );
		return self::gate() ?? new \WP_REST_Response( [ 'ok' => true, 'gallery' => GalleryRepository::for_owner( self::uid() ) ], 200 );
	}

	public static function gallery_create( \WP_REST_Request $r ): \WP_REST_Response {
		$g = self::gate(); if ( $g ) { return $g; }
		$item = GalleryRepository::add( self::uid(), (array) $r->get_params() );
		return new \WP_REST_Response( [ 'ok' => null !== $item, 'item' => $item ], 200 );
	}

	public static function gallery_update( \WP_REST_Request $r ): \WP_REST_Response {
		$g = self::gate(); if ( $g ) { return $g; }
		$item = GalleryRepository::update( self::uid(), (int) $r['id'], (array) $r->get_params() );
		return new \WP_REST_Response( [ 'ok' => null !== $item, 'item' => $item ], 200 );
	}

	public static function gallery_delete( \WP_REST_Request $r ): \WP_REST_Response {
		$g = self::gate(); if ( $g ) { return $g; }
		return new \WP_REST_Response( [ 'ok' => GalleryRepository::delete( self::uid(), (int) $r['id'] ) ], 200 );
	}

	// ── Shares ──────────────────────────────────────────────────────────────────
	public static function shares_list( \WP_REST_Request $r ): \WP_REST_Response {
		$g = self::gate(); if ( $g ) { return $g; }
		$item_id = (int) $r['id'];
		if ( null === GalleryRepository::get( self::uid(), $item_id ) ) {
			return new \WP_REST_Response( [ 'ok' => false, 'reason' => 'forbidden' ], 403 );
		}
		return new \WP_REST_Response( [ 'ok' => true, 'shares' => ShareRepository::for_item( 'gallery', $item_id ) ], 200 );
	}

	public static function shares_create( \WP_REST_Request $r ): \WP_REST_Response {
		$g = self::gate(); if ( $g ) { return $g; }
		$item_id = (int) $r['id'];
		if ( null === GalleryRepository::get( self::uid(), $item_id ) ) {
			return new \WP_REST_Response( [ 'ok' => false, 'reason' => 'forbidden' ], 403 );
		}
		$share = ShareRepository::add(
			self::uid(),
			'gallery',
			$item_id,
			(string) $r->get_param( 'recipient_type' ),
			(int) $r->get_param( 'recipient_id' ),
			(string) $r->get_param( 'scope' )
		);
		return new \WP_REST_Response( [ 'ok' => null !== $share, 'share' => $share ], 200 );
	}

	public static function shares_revoke( \WP_REST_Request $r ): \WP_REST_Response {
		$g = self::gate(); if ( $g ) { return $g; }
		return new \WP_REST_Response( [ 'ok' => ShareRepository::revoke( self::uid(), (int) $r['id'] ) ], 200 );
	}

	// ── Drei-Wort-Label (§32) ─────────────────────────────────────────────────────
	/** Nur der Autor des Adventures (oder Administer) darf den Drei-Wort-Namen setzen. */
	private static function owns_adventure( int $id ): bool {
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || 'liw_adventure' !== $post->post_type ) {
			return false;
		}
		return (int) $post->post_author === self::uid() || current_user_can( Roles::CAP_ADMINISTER );
	}

	public static function label_get( \WP_REST_Request $r ): \WP_REST_Response {
		$g = self::gate(); if ( $g ) { return $g; }
		return new \WP_REST_Response( [ 'ok' => true, 'label' => ThreeWordLabel::get( (int) $r['id'] ) ], 200 );
	}

	public static function label_put( \WP_REST_Request $r ): \WP_REST_Response {
		$g = self::gate(); if ( $g ) { return $g; }
		if ( ! self::owns_adventure( (int) $r['id'] ) ) {
			return new \WP_REST_Response( [ 'ok' => false, 'reason' => 'forbidden' ], 403 );
		}
		$res = ThreeWordLabel::set(
			(int) $r['id'],
			(string) $r->get_param( 'term_1' ),
			(string) $r->get_param( 'term_2' ),
			(string) $r->get_param( 'term_3' ),
			(string) $r->get_param( 'synonyms' )
		);
		return new \WP_REST_Response( $res, 200 );
	}

	public static function label_search( \WP_REST_Request $r ): \WP_REST_Response {
		$g = self::gate(); if ( $g ) { return $g; }
		return new \WP_REST_Response( [ 'ok' => true, 'results' => ThreeWordLabel::search( (string) $r->get_param( 'q' ) ) ], 200 );
	}
}
