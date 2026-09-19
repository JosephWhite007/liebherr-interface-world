<?php
/**
 * Liebherr World – Customer View Flow: Besucher-Zustand (ADR-LIW-CVF-001 §3.1/§9).
 *
 * Zustandsautomat des vertikalen Durchstichs. Sichtbarkeit ist nie Autorisierung (§14): Übergänge entstehen
 * nur serverseitig durch geprüfte Ereignisse. Reine, ohne WordPress testbare Logik.
 *
 * @package Liebherr\InterfaceWorld\Cvf
 * @since   0.1.0-alpha.82
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Cvf;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class VisitorState {

	public const NEW              = 'new';
	public const AT_ENTRY         = 'at_entry';
	public const AT_CHALLENGE     = 'at_challenge';
	public const AT_MODULE_SELECT = 'at_module_select';
	public const AT_FIRST_ENTRY   = 'at_first_entry';
	public const IN_MODULE        = 'in_module';
	public const BLOCKED          = 'blocked';

	/** @return array<int,string> */
	public static function all(): array {
		return [
			self::NEW,
			self::AT_ENTRY,
			self::AT_CHALLENGE,
			self::AT_MODULE_SELECT,
			self::AT_FIRST_ENTRY,
			self::IN_MODULE,
			self::BLOCKED,
		];
	}

	public static function is_valid( string $state ): bool {
		return in_array( $state, self::all(), true );
	}

	/** Endzustand des Durchstichs erreicht (im Modul angekommen). */
	public static function is_admitted( string $state ): bool {
		return self::IN_MODULE === $state;
	}
}
