<?php
/**
 * Liebherr World – My Liebherr: Zustandsautomaten Kontakt/Connection/Leistung (Pflichtenheft My Liebherr §33, ADR-LIW-MYL-001 R4).
 *
 * Reine Übergangstabellen (ohne WordPress, testbar). Nicht definierte Übergänge sind serverseitig abzulehnen
 * (§19-Prinzip). Grundlage für die Repositories und REST-Guards.
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.122
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ContactState {

	/** @return array<string,array<int,string>> */
	public static function request_transitions(): array {
		return [
			'requested' => [ 'accepted', 'declined', 'expired', 'cancelled' ],
			'accepted'  => [],
			'declined'  => [],
			'expired'   => [],
			'cancelled' => [],
		];
	}

	/** @return array<string,array<int,string>> */
	public static function connection_transitions(): array {
		return [
			'accepted' => [ 'active', 'ended', 'blocked' ],
			'active'   => [ 'paused', 'ended', 'disputed' ],
			'paused'   => [ 'active', 'ended' ],
			'disputed' => [ 'active', 'ended', 'blocked' ],
			'ended'    => [],
			'blocked'  => [],
		];
	}

	/** @return array<string,array<int,string>> */
	public static function service_transitions(): array {
		return [
			'proposed'  => [ 'confirmed', 'cancelled' ],
			'confirmed' => [ 'settled', 'disputed', 'cancelled' ],
			'settled'   => [],
			'disputed'  => [ 'settled', 'cancelled' ],
			'cancelled' => [],
		];
	}

	public static function can_request( string $from, string $to ): bool {
		return in_array( $to, self::request_transitions()[ $from ] ?? [], true );
	}

	public static function can_connection( string $from, string $to ): bool {
		return in_array( $to, self::connection_transitions()[ $from ] ?? [], true );
	}

	public static function can_service( string $from, string $to ): bool {
		return in_array( $to, self::service_transitions()[ $from ] ?? [], true );
	}
}
