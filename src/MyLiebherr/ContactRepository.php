<?php
/**
 * Liebherr World – My Liebherr: Kontakte/Connections/Leistungen (Pflichtenheft My Liebherr §33, ADR-LIW-MYL-001 R4).
 *
 * Persistiert Kontaktanfragen, Contact Connections und gemeinsame Leistungen (Service Exchange). Der Erstkontakt
 * läuft nur über eine strukturierte Anfrage; erst die ausdrückliche Zustimmung erzeugt eine Connection. Eine
 * Connection ist NIE eine Kontovollmacht (§33-Sicherheitsgrenze): sichtbar sind nur gemeinsam autorisierte
 * Leistungen. Die echte Wallet-Buchung einer bestätigten Leistung ist eine Naht (Hook `liw_myl_service_charge`,
 * deferred bis Wallet-Pflichtenheft). Übergänge werden über {@see ContactState} bewacht.
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.122
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ContactRepository {

	// ── Kontaktanfragen ───────────────────────────────────────────────────────
	/** @return array<string,mixed>|null */
	public static function create_request( int $requester, int $recipient, string $purpose, string $service_hint, int $token_frame ): ?array {
		if ( $requester <= 0 || $recipient <= 0 || $requester === $recipient ) {
			return null;
		}
		global $wpdb;
		$wpdb->insert( Schema::contact_request_table(), [ // phpcs:ignore WordPress.DB
			'requester_id' => $requester,
			'recipient_id' => $recipient,
			'purpose'      => sanitize_text_field( $purpose ),
			'service_hint' => sanitize_text_field( $service_hint ),
			'token_frame'  => max( 0, $token_frame ),
			'status'       => 'requested',
		] );
		return self::request( (int) $wpdb->insert_id );
	}

	/** @return array<string,mixed>|null */
	public static function request( int $id ): ?array {
		global $wpdb;
		$t   = Schema::contact_request_table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $row ) ? self::shape_request( $row ) : null;
	}

	/** @return array<int,array<string,mixed>> */
	public static function requests_for( int $user_id ): array {
		global $wpdb;
		$t    = Schema::contact_request_table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE requester_id = %d OR recipient_id = %d ORDER BY id DESC", $user_id, $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $rows ) ? array_map( [ self::class, 'shape_request' ], $rows ) : [];
	}

	/**
	 * Empfänger entscheidet über eine Anfrage. accept → Connection (accepted). @return array<string,mixed>
	 */
	public static function decide( int $user_id, int $id, string $decision ): array {
		$req = self::request( $id );
		if ( null === $req || (int) $req['recipient_id'] !== $user_id ) {
			return [ 'ok' => false, 'reason' => 'forbidden' ];
		}
		$to = 'accept' === $decision ? 'accepted' : ( 'decline' === $decision ? 'declined' : '' );
		if ( '' === $to || ! ContactState::can_request( (string) $req['status'], $to ) ) {
			return [ 'ok' => false, 'reason' => 'invalid_transition' ];
		}
		global $wpdb;
		$wpdb->update( Schema::contact_request_table(), [ 'status' => $to ], [ 'id' => $id ], [ '%s' ], [ '%d' ] ); // phpcs:ignore WordPress.DB
		$conn = null;
		if ( 'accepted' === $to ) {
			$conn = self::create_connection( (int) $req['requester_id'], (int) $req['recipient_id'] );
		}
		return [ 'ok' => true, 'request' => self::request( $id ), 'connection' => $conn ];
	}

	// ── Connections ───────────────────────────────────────────────────────────
	/** @return array<string,mixed>|null */
	public static function create_connection( int $party_a, int $party_b ): ?array {
		global $wpdb;
		$wpdb->insert( Schema::connection_table(), [ // phpcs:ignore WordPress.DB
			'party_a' => $party_a, 'party_b' => $party_b, 'status' => 'accepted', 'valid_from' => current_time( 'mysql' ),
		] );
		return self::connection( (int) $wpdb->insert_id );
	}

	/** @return array<string,mixed>|null */
	public static function connection( int $id ): ?array {
		global $wpdb;
		$t   = Schema::connection_table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $row ) ? self::shape_connection( $row ) : null;
	}

	/** @return array<int,array<string,mixed>> */
	public static function connections_for( int $user_id ): array {
		global $wpdb;
		$t    = Schema::connection_table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE party_a = %d OR party_b = %d ORDER BY id DESC", $user_id, $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $rows ) ? array_map( [ self::class, 'shape_connection' ], $rows ) : [];
	}

	public static function is_party( int $user_id, array $conn ): bool {
		return (int) $conn['party_a'] === $user_id || (int) $conn['party_b'] === $user_id;
	}

	/** @return array<string,mixed> */
	public static function set_connection_status( int $user_id, int $id, string $to ): array {
		$conn = self::connection( $id );
		if ( null === $conn || ! self::is_party( $user_id, $conn ) ) {
			return [ 'ok' => false, 'reason' => 'forbidden' ];
		}
		if ( ! ContactState::can_connection( (string) $conn['status'], $to ) ) {
			return [ 'ok' => false, 'reason' => 'invalid_transition' ];
		}
		global $wpdb;
		$data = [ 'status' => $to ];
		if ( 'ended' === $to ) {
			$data['valid_to'] = current_time( 'mysql' );
		}
		$wpdb->update( Schema::connection_table(), $data, [ 'id' => $id ] ); // phpcs:ignore WordPress.DB
		return [ 'ok' => true, 'connection' => self::connection( $id ) ];
	}

	// ── Service Exchange ──────────────────────────────────────────────────────
	/** @return array<string,mixed> */
	public static function propose_service( int $user_id, int $connection_id, string $description, int $token_amount ): array {
		$conn = self::connection( $connection_id );
		if ( null === $conn || ! self::is_party( $user_id, $conn ) ) {
			return [ 'ok' => false, 'reason' => 'forbidden' ];
		}
		if ( ! in_array( (string) $conn['status'], [ 'accepted', 'active' ], true ) ) {
			return [ 'ok' => false, 'reason' => 'connection_not_active' ];
		}
		$receiver = ( (int) $conn['party_a'] === $user_id ) ? (int) $conn['party_b'] : (int) $conn['party_a'];
		global $wpdb;
		$wpdb->insert( Schema::service_table(), [ // phpcs:ignore WordPress.DB
			'connection_id'   => $connection_id,
			'provider_id'     => $user_id,
			'receiver_id'     => $receiver,
			'description'     => sanitize_text_field( $description ),
			'token_amount'    => max( 0, $token_amount ),
			'status'          => 'proposed',
			'idempotency_key' => 'svc-' . $connection_id . '-' . wp_generate_password( 10, false ),
		] );
		return [ 'ok' => true, 'service' => self::service( (int) $wpdb->insert_id ) ];
	}

	/** @return array<string,mixed>|null */
	public static function service( int $id ): ?array {
		global $wpdb;
		$t   = Schema::service_table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $row ) ? self::shape_service( $row ) : null;
	}

	/** @return array<int,array<string,mixed>> */
	public static function services_for( int $connection_id ): array {
		global $wpdb;
		$t    = Schema::service_table();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE connection_id = %d ORDER BY id DESC", $connection_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $rows ) ? array_map( [ self::class, 'shape_service' ], $rows ) : [];
	}

	/**
	 * Statuswechsel einer Leistung durch einen Beteiligten. confirm erzeugt die Buchungsnaht (Hook, deferred).
	 *
	 * @return array<string,mixed>
	 */
	public static function set_service_status( int $user_id, int $id, string $to ): array {
		$svc = self::service( $id );
		if ( null === $svc ) {
			return [ 'ok' => false, 'reason' => 'not_found' ];
		}
		$conn = self::connection( (int) $svc['connection_id'] );
		if ( null === $conn || ! self::is_party( $user_id, $conn ) ) {
			return [ 'ok' => false, 'reason' => 'forbidden' ];
		}
		if ( ! ContactState::can_service( (string) $svc['status'], $to ) ) {
			return [ 'ok' => false, 'reason' => 'invalid_transition' ];
		}
		global $wpdb;
		$wpdb->update( Schema::service_table(), [ 'status' => $to ], [ 'id' => $id ] ); // phpcs:ignore WordPress.DB
		$svc = self::service( $id );
		if ( 'confirmed' === $to ) {
			/**
			 * Buchungsnaht: eine bestätigte Leistung würde beide Wallets betreffen (§33). Die echte Buchung
			 * übernimmt das kommende Wallet-Modul (Mehrwährung/Token); ohne Consumer bleibt sie unbebucht.
			 */
			do_action( 'liw_myl_service_charge', $svc, $conn );
		}
		return [ 'ok' => true, 'service' => $svc ];
	}

	// ── Shapes ────────────────────────────────────────────────────────────────
	/** @param array<string,mixed> $r @return array<string,mixed> */
	private static function shape_request( array $r ): array {
		return [
			'id'           => (int) $r['id'],
			'requester_id' => (int) $r['requester_id'],
			'recipient_id' => (int) $r['recipient_id'],
			'purpose'      => (string) ( $r['purpose'] ?? '' ),
			'service_hint' => (string) ( $r['service_hint'] ?? '' ),
			'token_frame'  => (int) ( $r['token_frame'] ?? 0 ),
			'status'       => (string) ( $r['status'] ?? 'requested' ),
		];
	}

	/** @param array<string,mixed> $r @return array<string,mixed> */
	private static function shape_connection( array $r ): array {
		return [
			'id'      => (int) $r['id'],
			'party_a' => (int) $r['party_a'],
			'party_b' => (int) $r['party_b'],
			'status'  => (string) ( $r['status'] ?? 'accepted' ),
			'limits'  => (string) ( $r['limits'] ?? '' ),
		];
	}

	/** @param array<string,mixed> $r @return array<string,mixed> */
	private static function shape_service( array $r ): array {
		return [
			'id'            => (int) $r['id'],
			'connection_id' => (int) $r['connection_id'],
			'provider_id'   => (int) $r['provider_id'],
			'receiver_id'   => (int) $r['receiver_id'],
			'description'   => (string) ( $r['description'] ?? '' ),
			'token_amount'  => (int) ( $r['token_amount'] ?? 0 ),
			'status'        => (string) ( $r['status'] ?? 'proposed' ),
		];
	}
}
