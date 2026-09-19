<?php
/**
 * Liebherr Adventures – Registrierungs-/Veröffentlichungsstatus (Basislogik §4/§7).
 *
 * Reiner Status-Automat für den verbindlichen Ablauf eines Adventure-Beitrags: Entwurf → eingereicht →
 * registriert → (optional) Validierung → validiert → freigegeben, plus Nachbesserung/Gesperrt/Archiviert.
 * Nur registrierte Beiträge sind im firmeneigenen Netz nutzbar; für das Liebherr-World-Netz sind zusätzlich
 * Validierung UND ausdrückliche Freigabe nötig. Ohne WordPress-Laufzeit unit-testbar.
 *
 * @package Liebherr\InterfaceWorld\Adventures
 * @since   0.1.0-alpha.59
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Adventures;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class RegistrationStatus {

	public const DRAFT                 = 'draft';                 // Entwurf – noch nicht eingereicht
	public const SUBMITTED             = 'submitted';             // Eingereicht – Registrierung wird geprüft
	public const REGISTERED            = 'registered';            // Registriert – im firmeneigenen Netz nutzbar
	public const VALIDATION_REQUESTED  = 'validation_requested';  // Validierung beantragt – Prüfung läuft
	public const VALIDATED             = 'validated';             // Validiert – Prüfung erfolgreich
	public const PUBLISHED             = 'published';             // Zur Veröffentlichung freigegeben (Liebherr World)
	public const REVISION              = 'revision';              // Nachbesserung erforderlich
	public const BLOCKED               = 'blocked';               // Gesperrt – vorübergehend nicht nutzbar
	public const ARCHIVED              = 'archived';              // Archiviert – nicht mehr aktiv angeboten

	/** @return array<string,string> Status → Bedeutung (§4). */
	public static function labels(): array {
		return [
			self::DRAFT                => __( 'Entwurf', 'liebherr-interface-world' ),
			self::SUBMITTED            => __( 'Eingereicht', 'liebherr-interface-world' ),
			self::REGISTERED           => __( 'Registriert', 'liebherr-interface-world' ),
			self::VALIDATION_REQUESTED => __( 'Validierung beantragt', 'liebherr-interface-world' ),
			self::VALIDATED            => __( 'Validiert', 'liebherr-interface-world' ),
			self::PUBLISHED            => __( 'Zur Veröffentlichung freigegeben', 'liebherr-interface-world' ),
			self::REVISION             => __( 'Nachbesserung erforderlich', 'liebherr-interface-world' ),
			self::BLOCKED              => __( 'Gesperrt', 'liebherr-interface-world' ),
			self::ARCHIVED             => __( 'Archiviert', 'liebherr-interface-world' ),
		];
	}

	public static function label( string $status ): string {
		return self::labels()[ $status ] ?? $status;
	}

	public static function is_valid( string $status ): bool {
		return array_key_exists( $status, self::labels() );
	}

	/**
	 * Erlaubte Übergänge (verbindlicher Ablauf §2/§7). Quelle → Zielmenge.
	 *
	 * @return array<string,array<int,string>>
	 */
	public static function transitions(): array {
		return [
			self::DRAFT                => [ self::SUBMITTED ],
			self::SUBMITTED            => [ self::REGISTERED, self::REVISION ],
			self::REGISTERED           => [ self::VALIDATION_REQUESTED, self::BLOCKED, self::ARCHIVED ],
			self::VALIDATION_REQUESTED => [ self::VALIDATED, self::REVISION ],
			self::VALIDATED            => [ self::PUBLISHED, self::BLOCKED, self::ARCHIVED ],
			self::PUBLISHED            => [ self::BLOCKED, self::ARCHIVED ],
			self::REVISION             => [ self::SUBMITTED, self::ARCHIVED ],
			self::BLOCKED              => [ self::REGISTERED, self::VALIDATED, self::PUBLISHED, self::ARCHIVED ],
			self::ARCHIVED             => [ self::REGISTERED ],
		];
	}

	/** Ist der Übergang $from → $to zulässig? */
	public static function can_transition( string $from, string $to ): bool {
		$map = self::transitions();
		return isset( $map[ $from ] ) && in_array( $to, $map[ $from ], true );
	}

	/**
	 * Im firmeneigenen Netz nutzbar/abrufbar? Nur registrierte Beiträge (§3/§7), solange nicht gesperrt/archiviert.
	 */
	public static function usable_in_company_net( string $status ): bool {
		return in_array( $status, [ self::REGISTERED, self::VALIDATION_REQUESTED, self::VALIDATED, self::PUBLISHED ], true );
	}

	/** Im Liebherr-World-Netz sichtbar? Nur nach Validierung UND ausdrücklicher Freigabe (§7). */
	public static function published_in_world( string $status ): bool {
		return self::PUBLISHED === $status;
	}

	/** Kann der Ersteller jetzt eine Validierung beantragen? (§4) */
	public static function can_request_validation( string $status ): bool {
		return self::REGISTERED === $status;
	}
}
