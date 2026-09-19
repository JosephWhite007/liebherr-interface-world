<?php
/**
 * Liebherr World – My Liebherr: Entitlement Service (Pflichtenheft My Liebherr §3/§20/§35, ADR-LIW-MYL-001 S1).
 *
 * Rechte werden als Schnittmenge aus Konto, Organisation, Rolle, Objektbezug und Freigabestatus berechnet
 * (§3/§35). Sichtbarkeit ≠ Berechtigung (SEC 01: jede Aktion serverseitig prüfen). Die Kernentscheidung
 * {@see can()} ist reine Logik (ohne WordPress testbar); {@see granted_for()} liest die effektiven Caps eines
 * WP-Nutzers zur Laufzeit.
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.108
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class EntitlementService {

	/**
	 * Reine Berechtigungsentscheidung. Prüft in dieser Reihenfolge: Cap vorhanden → Objektbezug (fremde
	 * Objekte nur mit Administer-Cap) → Organisationsbezug (aktive Org muss passen, sonst nur Administer).
	 *
	 * @param array<int,string>   $granted_caps Effektive Caps des Handelnden.
	 * @param string              $capability   Benötigte Capability.
	 * @param array<string,mixed> $context      owner_user_id, actor_user_id, required_org_id, active_org_id.
	 */
	public static function can( array $granted_caps, string $capability, array $context = [] ): bool {
		if ( ! in_array( $capability, $granted_caps, true ) ) {
			return false;
		}
		$is_admin = in_array( Roles::CAP_ADMINISTER, $granted_caps, true );

		// Objektbezug: fremder Datensatz nur mit Administer-Cap (Mandanten-/Objektschutz §35, SEC 01).
		if ( isset( $context['owner_user_id'], $context['actor_user_id'] )
			&& (int) $context['owner_user_id'] !== (int) $context['actor_user_id']
			&& ! $is_admin ) {
			return false;
		}

		// Organisationsbezug: verlangte Organisation muss der aktiven entsprechen (sonst nur Administer).
		if ( isset( $context['required_org_id'] ) && (int) $context['required_org_id'] > 0 ) {
			$active = (int) ( $context['active_org_id'] ?? 0 );
			if ( $active !== (int) $context['required_org_id'] && ! $is_admin ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Effektive My-Liebherr-Caps eines WP-Nutzers (Laufzeit). Leeres Array für Gäste/unbekannte Nutzer.
	 *
	 * @return array<int,string>
	 */
	public static function granted_for( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return [];
		}
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return [];
		}
		$out = [];
		foreach ( Roles::all_caps() as $cap ) {
			if ( $user->has_cap( $cap ) ) {
				$out[] = $cap;
			}
		}
		return $out;
	}
}
