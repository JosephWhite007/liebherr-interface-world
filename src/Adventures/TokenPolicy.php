<?php
/**
 * Liebherr Adventures – Tokenregeln (Basislogik §1/§5/§6).
 *
 * Der Ersteller legt den Tokenwert fest; die Plattform registriert/verwaltet ihn, setzt ihn aber nicht selbst.
 * Reine, ohne WordPress testbare Regeln: Zugriffskosten auflösen (eigene Beiträge frei, sonst Budgetprüfung),
 * Tokenwert normalisieren, Preisänderung nur für zukünftige Zugriffe (Snapshot beim Zugriff → im Ledger).
 *
 * Tokenwert = ganzzahlige Tokenanzahl (>= 0). Kein Float.
 *
 * @package Liebherr\InterfaceWorld\Adventures
 * @since   0.1.0-alpha.59
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Adventures;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class TokenPolicy {

	/** Normalisiert einen eingegebenen Tokenwert auf eine nicht-negative Ganzzahl. */
	public static function sanitize_value( $value ): int {
		return max( 0, (int) $value );
	}

	/**
	 * Löst die Zugriffskosten auf (rein, §5/§6). Eigene Beiträge des Erstellers sind ohne Belastung einsehbar;
	 * für andere gilt der festgelegte Tokenwert, sofern das Budget (oder eine Berechtigung) ausreicht.
	 *
	 * @param int  $token_value      Vom Ersteller festgelegter aktueller Tokenwert.
	 * @param bool $is_author        Betrachter ist der Ersteller/Rechteinhaber.
	 * @param int  $budget           Verfügbares Tokenbudget des Nutzers.
	 * @param bool $has_entitlement  Sonderberechtigung (z. B. Freigabe der Org-Einheit) – umgeht Budgetprüfung.
	 * @return array{charge:int,allowed:bool,reason:string}
	 */
	public static function resolve_access( int $token_value, bool $is_author, int $budget, bool $has_entitlement = false ): array {
		$value = self::sanitize_value( $token_value );

		if ( $is_author ) {
			return [ 'charge' => 0, 'allowed' => true, 'reason' => 'author' ];
		}
		if ( 0 === $value ) {
			return [ 'charge' => 0, 'allowed' => true, 'reason' => 'free' ];
		}
		if ( $has_entitlement ) {
			return [ 'charge' => $value, 'allowed' => true, 'reason' => 'entitlement' ];
		}
		if ( max( 0, $budget ) >= $value ) {
			return [ 'charge' => $value, 'allowed' => true, 'reason' => 'budget_ok' ];
		}
		return [ 'charge' => $value, 'allowed' => false, 'reason' => 'insufficient_budget' ];
	}

	/**
	 * Neue Beitragsversion? Dann darf ein neuer Tokenwert gelten (§6). Bereits bestätigte Zugriffe bleiben
	 * unverändert (das erzwingt die Unveränderlichkeit des Ledgers, nicht diese Funktion).
	 */
	public static function next_version( int $current_version, bool $bump ): int {
		$current_version = max( 1, $current_version );
		return $bump ? $current_version + 1 : $current_version;
	}
}
