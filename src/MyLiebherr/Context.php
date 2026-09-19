<?php
/**
 * Liebherr World – My Liebherr: Persönlicher Kontext (Pflichtenheft My Liebherr §6/§35, ADR-LIW-MYL-001 S1).
 *
 * Bündelt die persönliche Sicht einer Person: Profil, WP-Rollen, Mitgliedschaften, aktive Organisation/Rolle
 * und die effektiven My-Liebherr-Capabilities. {@see for_user()} baut diese Sicht zur Laufzeit; die
 * Feld-Allowlist {@see allowed_fields()}, die Persona-Liste und {@see sanitize_patch()} sind reine Logik
 * (ohne WordPress testbar) und setzen die Feldfreigabe für PATCH /me durch (§18: „Erlaubte Profil- und
 * Präferenzfelder").
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.108
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Context {

	/** Zulässige Personas (Standardlayouts je Persona, §5). */
	public const ALLOWED_PERSONAS = [ 'employee', 'customer', 'prospect', 'reviewer', 'team_lead' ];

	/**
	 * Über PATCH /me erlaubte, selbst pflegbare Felder (Feldfreigabe §18).
	 *
	 * @return array<int,string>
	 */
	public static function allowed_fields(): array {
		return [ 'persona', 'locale', 'timezone', 'active_org_id', 'active_role' ];
	}

	/**
	 * Filtert eine PATCH-Eingabe auf erlaubte, bereinigte Felder. Unbekannte Felder und eine unzulässige
	 * Persona werden verworfen (reine Logik; nutzt nur sanitize_text_field).
	 *
	 * @param array<string,mixed> $input
	 * @return array<string,mixed>
	 */
	public static function sanitize_patch( array $input ): array {
		$out = [];
		foreach ( self::allowed_fields() as $field ) {
			if ( ! array_key_exists( $field, $input ) ) {
				continue;
			}
			$value = $input[ $field ];
			if ( 'active_org_id' === $field ) {
				$out[ $field ] = max( 0, (int) $value );
				continue;
			}
			$clean = sanitize_text_field( (string) $value );
			if ( 'persona' === $field && '' !== $clean && ! in_array( $clean, self::ALLOWED_PERSONAS, true ) ) {
				continue; // Unzulässige Persona verwerfen.
			}
			$out[ $field ] = $clean;
		}
		return $out;
	}

	/**
	 * Baut die persönliche Kontext-Sicht eines Nutzers (Grundlage für GET /me).
	 *
	 * @return array<string,mixed>
	 */
	public static function for_user( int $user_id ): array {
		$profile = ProfileRepository::ensure( $user_id );
		$user    = get_userdata( $user_id );
		$roles   = ( $user instanceof \WP_User ) ? array_values( $user->roles ) : [];

		return [
			'user_id'         => $user_id,
			'display_name'    => ( $user instanceof \WP_User ) ? $user->display_name : '',
			'persona'         => $profile['persona'],
			'locale'          => $profile['locale'],
			'timezone'        => $profile['timezone'],
			'active_org_id'   => $profile['active_org_id'],
			'active_role'     => $profile['active_role'],
			'privacy_version' => $profile['privacy_version'],
			'roles'           => $roles,
			'memberships'     => MembershipRepository::for_user( $user_id ),
			'capabilities'    => EntitlementService::granted_for( $user_id ),
		];
	}
}
