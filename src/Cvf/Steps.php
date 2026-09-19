<?php
/**
 * Liebherr World – Customer View Flow: Schritt-Beschreibung des Durchstichs (ADR-LIW-CVF-001 §7).
 *
 * Rein: bildet einen Besucher-Zustand auf einen Schritt-Deskriptor ab, den das Frontend rendert
 * (Typ + Titel + Hinweis). Keine WordPress-Abhängigkeit (Übersetzung/konkrete Daten reicht die
 * Aufrufschicht hinein).
 *
 * @package Liebherr\InterfaceWorld\Cvf
 * @since   0.1.0-alpha.86
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Cvf;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Steps {

	/**
	 * Bildet einen Zustand auf einen Schritt-Typ ab (was das Frontend als Nächstes zeigt).
	 */
	public static function type_for( string $state ): string {
		switch ( $state ) {
			case VisitorState::AT_ENTRY:
				return 'entry';
			case VisitorState::AT_CHALLENGE:
				return 'challenge';
			case VisitorState::AT_MODULE_SELECT:
				return 'module_select';
			case VisitorState::AT_FIRST_ENTRY:
				return 'first_entry';
			case VisitorState::IN_MODULE:
				return 'done';
			case VisitorState::BLOCKED:
				return 'blocked';
			default:
				return 'begin';
		}
	}

	/**
	 * Schritt-Deskriptor für einen Zustand.
	 *
	 * @param array<string,mixed> $extra Zusätzliche, kontextabhängige Felder (z. B. challenge, modules, target).
	 * @return array<string,mixed>
	 */
	public static function describe( string $state, array $extra = [] ): array {
		$type = self::type_for( $state );
		return array_merge(
			[
				'type'  => $type,
				'state' => $state,
			],
			$extra
		);
	}
}
