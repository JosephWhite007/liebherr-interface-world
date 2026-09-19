<?php
/**
 * Liebherr World – Customer View Flow: Stufentypen des vertikalen Durchstichs (ADR-LIW-CVF-001 §7 Phase 1).
 *
 * Der erste sichtbare Ausbau ist ein schmaler End-to-End-Weg: Eingang → Rechen-Challenge → Modulauswahl →
 * First-Entry. Diese Typen benennen die Stufen; die konkrete Abfolge steht als versionierte Config
 * ({@see WorkflowVersion}), nicht hart codiert. Reine, ohne WordPress testbare Konstanten/Helfer.
 *
 * @package Liebherr\InterfaceWorld\Cvf
 * @since   0.1.0-alpha.82
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Cvf;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class StageType {

	public const ENTRY         = 'entry';          // Eingang (Zugangscode).
	public const CHALLENGE     = 'challenge';      // Human-Verification-Rechenaufgabe.
	public const MODULE_SELECT = 'module_select';  // Modulauswahl.
	public const FIRST_ENTRY   = 'first_entry';    // First-Entry-/Intro-Stufe eines Moduls.

	/** @return array<int,string> Alle Typen in fachlicher Reihenfolge. */
	public static function all(): array {
		return [ self::ENTRY, self::CHALLENGE, self::MODULE_SELECT, self::FIRST_ENTRY ];
	}

	public static function is_valid( string $type ): bool {
		return in_array( $type, self::all(), true );
	}
}
