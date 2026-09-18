<?php
/**
 * Liebherr Adventures – Berechtigungen & Sichtbarkeit (§8/§9/§22).
 *
 * Serverseitige Durchsetzung (UI-Ausblendung ist keine Zugriffskontrolle, §8). Die Kernregeln
 * (`effective_status`, `can_view`) sind rein und unit-testbar; die WP-gebundenen Wrapper prüfen Login/Caps.
 * Verbindlich: ein Critical-Beitrag wird nie ungeprüft veröffentlicht (§3.3/§9.3/§22.5); Sichtbarkeit
 * wird beim Lesen erzwungen (§22.6/§22.11).
 *
 * @package Liebherr\InterfaceWorld\Adventures
 * @since   0.1.0-alpha.51
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Adventures;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Policy {

	/** @return array<string,string> Sichtbarkeitsstufen (§9.1). */
	public static function visibilities(): array {
		return [
			'private_draft'   => __( 'Private Draft', 'liebherr-interface-world' ),
			'selected_team'   => __( 'Selected Team', 'liebherr-interface-world' ),
			'organization'    => __( 'Organization', 'liebherr-interface-world' ),
			'dealer_network'  => __( 'Dealer / Service Network', 'liebherr-interface-world' ),
			'world_internal'  => __( 'Liebherr World Internal', 'liebherr-interface-world' ),
			'partner_campaign' => __( 'Partner Campaign', 'liebherr-interface-world' ),
			'public_approved'  => __( 'Public Approved', 'liebherr-interface-world' ),
		];
	}

	public static function is_valid_visibility( string $v ): bool {
		return array_key_exists( $v, self::visibilities() );
	}

	/** Nur mit aktivem Intelligence-Zugang erstellen (§22.1). MVP: eingeloggter Nutzer; per Filter erweiterbar. */
	public static function can_create(): bool {
		return (bool) apply_filters( 'liw_adv_can_create', is_user_logged_in() );
	}

	/**
	 * Effektiver Veröffentlichungsstatus aus Absicht + Dringlichkeit (rein, §9.2/§22.5).
	 * draft → 'draft'; submit → 'pending' (Review). Critical bleibt IMMER 'pending' (Eskalation, nie auto-public).
	 */
	public static function effective_status( string $intent, string $urgency ): string {
		if ( 'draft' === $intent ) {
			return 'draft';
		}
		return 'pending'; // eingereicht: Moderation erforderlich (Critical ebenso; wird zusätzlich eskaliert).
	}

	/**
	 * Darf der Betrachter dieses Adventure sehen? Rein (nimmt bereits aufgelöste Zustände, §9.1/§22.6).
	 */
	public static function can_view( string $status, string $visibility, bool $is_author, bool $is_logged_in, bool $is_moderator = false ): bool {
		if ( $is_moderator || $is_author ) {
			return true;
		}
		if ( 'publish' !== $status ) {
			return false; // Entwürfe/zur Prüfung nur Autor/Moderator.
		}
		if ( 'public_approved' === $visibility ) {
			return true;
		}
		return $is_logged_in; // interne Sichtbarkeiten: nur angemeldete (verfeinerte Org/Team-Regeln später).
	}

	/** Moderations-/Freigaberecht (Cap; MVP: manage_options-nah über RoleBridge). */
	public static function can_moderate(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( \Liebherr\InterfaceWorld\CoreBridge\RoleBridge::CAP_MANAGE_CONTENT );
	}
}
