<?php
/**
 * Liebherr World – My Liebherr: Rollen-Mapping (Pflichtenheft My Liebherr §3/§35, ADR-LIW-MYL-001 S1).
 *
 * KEINE eigenen My-Liebherr-Rollen: der persönliche Bereich ist für jede angemeldete Person da (§1). Zwei
 * feingranulare `liw_myl_`-Capabilities werden – wie {@see \Liebherr\InterfaceWorld\Cvf\Roles} und
 * {@see \Liebherr\InterfaceWorld\CoreBridge\RoleBridge} – auf die bestehenden ARALIYA-/WP-Rollen abgebildet:
 *
 *   - liw_myl_access     : persönlichen Bereich betreten (alle angemeldeten Rollen).
 *   - liw_myl_administer : My-Liebherr-Administration / fremde Objekte (nur Vollzugriffs-Rollen).
 *
 * Reine Mapping-Logik ({@see role_caps()}) ist ohne WordPress testbar; grant()/revoke() vergeben die Caps an
 * vorhandene Rollen (unbekannte Slugs werden übersprungen – robust gegenüber abweichenden Rollensätzen).
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.108
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Roles {

	public const CAP_ACCESS     = 'liw_myl_access';      // Persönlichen Bereich betreten.
	public const CAP_ADMINISTER = 'liw_myl_administer';  // My-Liebherr-Administration / fremde Objekte.

	/** Vollzugriffs-Rollen erhalten alle My-Liebherr-Caps. */
	private const FULL_ACCESS_ROLES = [ 'administrator', 'araliya_admin' ];

	/**
	 * Rollen, die den persönlichen Bereich betreten dürfen (jede angemeldete Person, §1). Bewusst großzügig
	 * inkl. der WP-Standardrollen; nicht vorhandene Slugs überspringt grant() folgenlos.
	 */
	private const ACCESS_ROLES = [
		'araliya_admin', 'araliya_ops', 'araliya_marketing', 'araliya_finance', 'araliya_reception',
		'araliya_therapist', 'araliya_ai_agent',
		'liw_partner',
		'editor', 'author', 'contributor', 'subscriber',
	];

	/** @return array<int,string> Alle My-Liebherr-Capabilities. */
	public static function all_caps(): array {
		return [ self::CAP_ACCESS, self::CAP_ADMINISTER ];
	}

	/**
	 * Ziel-Zuordnung Rollen-Slug → Menge der Caps. Reine Logik (ohne WordPress) – Grundlage für
	 * grant()/revoke() und testbar.
	 *
	 * @return array<string,array<int,string>>
	 */
	public static function role_caps(): array {
		$out = [];
		foreach ( self::ACCESS_ROLES as $slug ) {
			$out[ $slug ] = [ self::CAP_ACCESS ];
		}
		foreach ( self::FULL_ACCESS_ROLES as $slug ) {
			$out[ $slug ] = self::all_caps();
		}
		return $out;
	}

	/** Vergibt die My-Liebherr-Caps an vorhandene Rollen (idempotent). */
	public static function grant(): void {
		foreach ( self::role_caps() as $slug => $caps ) {
			$role = get_role( $slug );
			if ( $role instanceof \WP_Role ) {
				foreach ( $caps as $cap ) {
					$role->add_cap( $cap );
				}
			}
		}
		do_action( 'liw_myl_after_grant_roles' );
	}

	/** Entfernt alle My-Liebherr-Caps wieder (Deaktivierung); 'administrator' bleibt unberührt (Core-Konvention). */
	public static function revoke(): void {
		foreach ( array_keys( self::role_caps() ) as $slug ) {
			if ( 'administrator' === $slug ) {
				continue;
			}
			$role = get_role( $slug );
			if ( $role instanceof \WP_Role ) {
				foreach ( self::all_caps() as $cap ) {
					$role->remove_cap( $cap );
				}
			}
		}
	}
}
