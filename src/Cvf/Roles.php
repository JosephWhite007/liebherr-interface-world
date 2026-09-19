<?php
/**
 * Liebherr World – Customer View Flow: Rollen-Mapping (ADR-LIW-CVF-001 §2, JW-Entscheid §9.1).
 *
 * KEINE eigenen CVF-Rollen: die fünf fachlichen CVF-Rollen (Content-Editor, Workflow-Editor, Publisher,
 * Auditor, Administrator) werden über feingranulare `liw_cvf_`-Capabilities auf die bestehenden
 * ARALIYA-Rollen abgebildet (wie {@see \Liebherr\InterfaceWorld\CoreBridge\RoleBridge} für die
 * Interface-Solutions-Caps). Vollzugriffs-Rollen (administrator, araliya_admin) erhalten alle CVF-Caps.
 *
 * Reine Mapping-Logik ist ohne WordPress testbar; grant()/revoke() vergeben die Caps an vorhandene Rollen
 * (unbekannte Rollen-Slugs werden übersprungen – robust gegenüber abweichenden Core-Rollensätzen).
 *
 * @package Liebherr\InterfaceWorld\Cvf
 * @since   0.1.0-alpha.85
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Cvf;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Roles {

	public const CAP_EDIT_CONTENT  = 'liw_cvf_edit_content';   // Inhalte/Content-Keys pflegen.
	public const CAP_EDIT_WORKFLOW = 'liw_cvf_edit_workflow';  // Workflow/Stufen bearbeiten (Entwurf).
	public const CAP_PUBLISH       = 'liw_cvf_publish';        // Version veröffentlichen/zurückrollen.
	public const CAP_AUDIT         = 'liw_cvf_audit';          // Audit-/Execution-Log lesen.
	public const CAP_ADMINISTER    = 'liw_cvf_administer';     // CVF-Administration (Flags/Registry).

	/** Vollzugriffs-Rollen erhalten alle CVF-Caps. */
	private const FULL_ACCESS_ROLES = [ 'administrator', 'araliya_admin' ];

	/** @return array<int,string> Alle CVF-Capabilities. */
	public static function all_caps(): array {
		return [ self::CAP_EDIT_CONTENT, self::CAP_EDIT_WORKFLOW, self::CAP_PUBLISH, self::CAP_AUDIT, self::CAP_ADMINISTER ];
	}

	/**
	 * Fachliche CVF-Rolle → { caps, roles }. Reine Datenabbildung (ohne WordPress).
	 *
	 * @return array<string,array{caps:array<int,string>,roles:array<int,string>}>
	 */
	public static function map(): array {
		return [
			'content_editor'  => [ 'caps' => [ self::CAP_EDIT_CONTENT ],  'roles' => [ 'araliya_marketing' ] ],
			'workflow_editor' => [ 'caps' => [ self::CAP_EDIT_WORKFLOW ], 'roles' => [ 'araliya_ops' ] ],
			'publisher'       => [ 'caps' => [ self::CAP_PUBLISH ],       'roles' => [ 'araliya_ops', 'araliya_admin' ] ],
			'auditor'         => [ 'caps' => [ self::CAP_AUDIT ],         'roles' => [ 'araliya_finance', 'araliya_reception' ] ],
			'administrator'   => [ 'caps' => self::all_caps(),            'roles' => [ 'administrator', 'araliya_admin' ] ],
		];
	}

	/**
	 * Aggregiert die Ziel-Zuordnung ARALIYA-Rollen-Slug → Menge der Caps (inkl. Vollzugriffs-Rollen).
	 * Reine Logik (ohne WordPress) – Grundlage für grant()/revoke() und testbar.
	 *
	 * @return array<string,array<int,string>>
	 */
	public static function role_caps(): array {
		$out = [];
		foreach ( self::map() as $entry ) {
			foreach ( $entry['roles'] as $slug ) {
				$out[ $slug ] = array_values( array_unique( array_merge( $out[ $slug ] ?? [], $entry['caps'] ) ) );
			}
		}
		foreach ( self::FULL_ACCESS_ROLES as $slug ) {
			$out[ $slug ] = self::all_caps();
		}
		return $out;
	}

	/** Vergibt die CVF-Caps an vorhandene ARALIYA-Rollen (idempotent). */
	public static function grant(): void {
		foreach ( self::role_caps() as $slug => $caps ) {
			$role = get_role( $slug );
			if ( $role instanceof \WP_Role ) {
				foreach ( $caps as $cap ) {
					$role->add_cap( $cap );
				}
			}
		}
		do_action( 'liw_cvf_after_grant_roles' );
	}

	/** Entfernt alle CVF-Caps wieder (Deaktivierung); 'administrator' bleibt wie bei RoleBridge unberührt. */
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
