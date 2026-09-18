<?php
/**
 * Liebherr Interface Solutions – Role Bridge
 *
 * Nutzt das native WP-Rollenmodell und die bestehenden ARALIYA-Rollen (Core\RoleManager)
 * statt eines eigenen Rollensystems (Entscheidung JW 17.09.2026, Variante A;
 * CLAUDE.md Abschnitt 5: „WP-Rollenmodell bevorzugen"). Liebherr registriert lediglich
 * eigene, feingranulare Capabilities und vergibt sie an vorhandene ARALIYA-Rollen.
 *
 * Rollenzuordnung (Liebherr-Pflichtenheft §5) → ARALIYA-Rolle:
 *   Interface Admin / System Admin → araliya_admin
 *   Redaktion (Content Board)      → araliya_marketing (CAP_CONTENT-Träger)
 *   Händler / Lieferant / Partner  → eigene Rolle `liw_partner` (seit alpha.21, Stufe 1 des geschützten
 *                                    Partnerbereichs: Konto + Login; Dokumentenbereich folgt)
 *
 * @package Liebherr\InterfaceWorld\CoreBridge
 * @since   0.1.0-alpha.1
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\CoreBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class RoleBridge {

	/** Eigene Capabilities des Plugins (Präfix liw_, wie von der Ein-Plugin-Ausnahme erwartet). */
	public const CAP_MANAGE_INTERFACES  = 'liw_manage_interfaces';   // Interface-/Simulationskatalog pflegen
	public const CAP_MANAGE_CONTENT     = 'liw_manage_content';      // Sektionen/Content Board
	public const CAP_VIEW_ONBOARDING    = 'liw_view_onboarding';     // Partner-/Onboarding-Anfragen einsehen
	public const CAP_PARTNER_ACCESS     = 'liw_partner_access';      // Zugang zum geschützten Partnerbereich (Rolle liw_partner)

	/**
	 * Eigene WP-Rolle für freigegebene Händler/Lieferanten/Kunden (alpha.21). Bewusst eine
	 * eigene Rolle statt einer ARALIYA-Rolle: Core-Rollen tragen Hotel-/Gesundheits-Capabilities
	 * (Therapeut, Reception …), die ein Liebherr-Partner nie haben darf (Least Privilege,
	 * Pflichtenheft §10/§23 rollenbasierter Zugriff). Capabilities: nur `read` (WP-Minimum für
	 * Login/Profil) + CAP_PARTNER_ACCESS – kein Backend-Zugriff über das eigene Profil hinaus.
	 */
	public const ROLE_PARTNER = 'liw_partner';

	private const ROLE_ADMIN_CLASS = 'Araliya\\Platform\\Core\\Core\\RoleManager';

	/**
	 * Rollen, die im Core-`RoleManager` als "vollständiger Systemzugriff" behandelt werden
	 * (Core vergibt dort jede Capability explizit auch an 'administrator', s.
	 * araliya-platform-core/src/Core/RoleManager.php: "Extends the built-in 'administrator' —
	 * we add ARALIYA capabilities"). Bugfix 2026-09-17: ursprünglich fehlte 'administrator'
	 * hier, wodurch der native WP-Administrator-Account das Interface-World-Menü nicht sah.
	 */
	private const FULL_ACCESS_ROLES = [ 'administrator', 'araliya_admin' ];

	/**
	 * Legt die Partner-Rolle an bzw. gleicht ihre Capabilities ab (idempotent; Aktivierung und
	 * Versionswechsel). Bestehende Benutzer mit dieser Rolle bleiben unberührt.
	 */
	public static function ensure_partner_role(): void {
		$role = get_role( self::ROLE_PARTNER );
		if ( ! $role instanceof \WP_Role ) {
			$role = add_role( self::ROLE_PARTNER, __( 'Liebherr Interface Partner', 'liebherr-interface-world' ), [ 'read' => true ] );
		}
		if ( $role instanceof \WP_Role ) {
			$role->add_cap( 'read' );
			$role->add_cap( self::CAP_PARTNER_ACCESS );
		}
	}

	/** Vergibt die Liebherr-Capabilities an bestehende ARALIYA-Rollen (Aktivierung). */
	public static function grant_capabilities(): void {
		self::ensure_partner_role();

		foreach ( self::FULL_ACCESS_ROLES as $role_slug ) {
			$role = get_role( $role_slug );
			if ( $role instanceof \WP_Role ) {
				$role->add_cap( self::CAP_MANAGE_INTERFACES );
				$role->add_cap( self::CAP_MANAGE_CONTENT );
				$role->add_cap( self::CAP_VIEW_ONBOARDING );
			}
		}

		$marketing = get_role( 'araliya_marketing' );
		if ( $marketing instanceof \WP_Role ) {
			$marketing->add_cap( self::CAP_MANAGE_CONTENT );
		}

		/**
		 * Erweiterungspunkt für spätere Portal-Rollen (Händler/Lieferant, Stufe 2).
		 * Andere Module/Plugins können hierüber eigene Rollen ergänzen, ohne diese Klasse zu ändern.
		 */
		do_action( 'liw_after_grant_capabilities' );
	}

	/**
	 * Entfernt die Liebherr-Capabilities wieder (Deaktivierung). Rollen selbst bleiben unangetastet.
	 * 'administrator' wird bewusst NICHT entfernt (Core-Konvention, RoleManager::deactivate():
	 * "Does NOT remove 'administrator'") — der native Admin-Account soll auch nach einer
	 * Deaktivierung nicht plötzlich Capabilities verlieren, die er vorher hatte.
	 * Die Rolle `liw_partner` wird bei Deaktivierung ebenfalls NICHT entfernt: Benutzer mit
	 * dieser Rolle würden sonst rollenlos (WP-Konvention: Rollen mit Benutzern nur bei
	 * Deinstallation entfernen; keine Deinstallationsroutine in dieser Ausbaustufe).
	 */
	public static function revoke_capabilities(): void {
		foreach ( [ 'araliya_admin', 'araliya_marketing' ] as $role_slug ) {
			$role = get_role( $role_slug );
			if ( $role instanceof \WP_Role ) {
				$role->remove_cap( self::CAP_MANAGE_INTERFACES );
				$role->remove_cap( self::CAP_MANAGE_CONTENT );
				$role->remove_cap( self::CAP_VIEW_ONBOARDING );
			}
		}
	}

	public static function core_role_manager_available(): bool {
		return class_exists( self::ROLE_ADMIN_CLASS );
	}
}
