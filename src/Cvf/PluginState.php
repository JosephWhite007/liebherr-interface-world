<?php
/**
 * Liebherr World – CAPDB Plugin-Zustandsautomat (Pflichtenheft §26.2).
 *
 * Elf verbindliche Zustände mit erlaubten Folgezuständen. Rein/ohne WordPress testbar. Der Server erzwingt
 * diese Übergänge – ein Plugin ist kein frei ausführbarer Code, sondern ein zustandsgeführter Funktionstyp.
 *
 * @package Liebherr\InterfaceWorld\Cvf
 * @since   0.1.0-alpha.89
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Cvf;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class PluginState {

	public const UNASSIGNED = 'unassigned';
	public const CONFIGURED = 'configured';
	public const SCHEDULED  = 'scheduled';
	public const OPENING    = 'opening';
	public const OPEN       = 'open';
	public const CLOSING    = 'closing';
	public const CLOSED     = 'closed';
	public const COMPLETED  = 'completed';
	public const DISABLED   = 'disabled';
	public const FAILED     = 'failed';
	public const CANCELLED  = 'cancelled';

	/** @return array<string,array<int,string>> Zustand → erlaubte Folgezustände (§26.2). */
	public static function transitions(): array {
		return [
			self::UNASSIGNED => [ self::CONFIGURED ],
			self::CONFIGURED => [ self::SCHEDULED, self::DISABLED ],
			self::SCHEDULED  => [ self::OPENING, self::CANCELLED ],
			self::OPENING    => [ self::OPEN, self::FAILED ],
			self::OPEN       => [ self::CLOSING, self::COMPLETED, self::FAILED ],
			self::CLOSING    => [ self::CLOSED, self::FAILED ],
			self::CLOSED     => [ self::SCHEDULED, self::COMPLETED ],
			self::COMPLETED  => [ self::SCHEDULED ],
			self::DISABLED   => [ self::CONFIGURED ],
			self::FAILED     => [ self::SCHEDULED, self::DISABLED ],
			self::CANCELLED  => [ self::SCHEDULED ],
		];
	}

	/** @return array<int,string> */
	public static function all(): array {
		return array_keys( self::transitions() );
	}

	public static function is_valid( string $state ): bool {
		return in_array( $state, self::all(), true );
	}

	/** Ist der Übergang von $from nach $to erlaubt? */
	public static function can_transition( string $from, string $to ): bool {
		$t = self::transitions();
		return isset( $t[ $from ] ) && in_array( $to, $t[ $from ], true );
	}
}
