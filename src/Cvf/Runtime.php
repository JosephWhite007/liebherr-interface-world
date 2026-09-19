<?php
/**
 * Liebherr World – Customer View Flow: deterministische Runtime des Durchstichs (ADR-LIW-CVF-001 §9).
 *
 * Rein und deterministisch: aus (Zustand, Ereignis, veröffentlichte Config) folgt genau ein nächster Zustand
 * plus die auszuführende Aktion. Keine Autorisierung hier – die Aufrufschicht prüft Ereignisse serverseitig
 * (Zugangscode, Challenge) und meldet nur das Ergebnis. Ohne WordPress testbar.
 *
 * Ereignisse: begin, code_ok, challenge_ok, module_selected, first_entry_done, block.
 *
 * @package Liebherr\InterfaceWorld\Cvf
 * @since   0.1.0-alpha.82
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Cvf;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Runtime {

	public const EV_BEGIN            = 'begin';
	public const EV_CODE_OK          = 'code_ok';
	public const EV_CHALLENGE_OK     = 'challenge_ok';
	public const EV_MODULE_SELECTED  = 'module_selected';
	public const EV_FIRST_ENTRY_DONE = 'first_entry_done';
	public const EV_BLOCK            = 'block';

	/** Startzustand. */
	public static function start(): string {
		return VisitorState::NEW;
	}

	/**
	 * Führt einen Übergang aus.
	 *
	 * @param array<string,mixed> $config Veröffentlichte Workflow-Config (steuert u. a. die Challenge-Stufe).
	 * @return array{state:string,action:string,reason:string,params:array<string,mixed>}
	 */
	public static function next( string $state, string $event, array $config ): array {
		// „block" ist aus jedem Zustand heraus möglich (z. B. Sicherheitsabbruch).
		if ( self::EV_BLOCK === $event ) {
			return self::step( VisitorState::BLOCKED, 'halt', 'blocked' );
		}
		if ( VisitorState::BLOCKED === $state ) {
			return self::step( $state, 'none', 'is_blocked' );
		}

		$V = VisitorState::class;
		switch ( $state . '|' . $event ) {
			case $V::NEW . '|' . self::EV_BEGIN:
				return self::step( VisitorState::AT_ENTRY, 'show_entry', 'ok' );
			case $V::AT_ENTRY . '|' . self::EV_CODE_OK:
				return self::step( VisitorState::AT_CHALLENGE, 'issue_challenge', 'ok', [ 'difficulty' => self::challenge_difficulty( $config ) ] );
			case $V::AT_CHALLENGE . '|' . self::EV_CHALLENGE_OK:
				return self::step( VisitorState::AT_MODULE_SELECT, 'show_modules', 'ok' );
			case $V::AT_MODULE_SELECT . '|' . self::EV_MODULE_SELECTED:
				return self::step( VisitorState::AT_FIRST_ENTRY, 'first_entry', 'ok' );
			case $V::AT_FIRST_ENTRY . '|' . self::EV_FIRST_ENTRY_DONE:
				return self::step( VisitorState::IN_MODULE, 'open_module', 'ok' );
		}
		return self::step( $state, 'none', 'invalid_transition' );
	}

	/** Liest die Challenge-Schwierigkeit aus der Config (Fallback: single). */
	public static function challenge_difficulty( array $config ): string {
		foreach ( WorkflowVersion::stages( $config ) as $stage ) {
			if ( isset( $stage['type'] ) && StageType::CHALLENGE === $stage['type'] ) {
				return ChallengeService::normalize( isset( $stage['difficulty'] ) ? (string) $stage['difficulty'] : ChallengeService::DEFAULT_DIFFICULTY );
			}
		}
		return ChallengeService::DEFAULT_DIFFICULTY;
	}

	/**
	 * @param array<string,mixed> $params
	 * @return array{state:string,action:string,reason:string,params:array<string,mixed>}
	 */
	private static function step( string $state, string $action, string $reason, array $params = [] ): array {
		return [ 'state' => $state, 'action' => $action, 'reason' => $reason, 'params' => $params ];
	}
}
