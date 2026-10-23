<?php
/**
 * Liebherr Interface Solutions – Gate Bridge
 *
 * Einziger Kopplungspunkt zum Core-Zugangsschutz (`Modules\Access\SiteGate*`, Code 3268 bzw. Env
 * `ARY_SITE_GATE_CODE`). Die Sales-Unterseite wird damit nicht neu geschützt, sondern erhält die
 * Core-Regel »mit Code« (Variante A, ADR-LIW-SALES-001; Variante B – Händlercodes – folgt später
 * über dieselbe Naht). Ohne Core: Seite gilt als nicht freigeschaltet (fail closed).
 *
 * @package Liebherr\InterfaceWorld\CoreBridge
 * @since   0.1.0-alpha.146
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\CoreBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class GateBridge {

	private const SETTINGS = 'Araliya\\Platform\\Core\\Modules\\Access\\SiteGateSettings';
	private const GATE     = 'Araliya\\Platform\\Core\\Modules\\Access\\SiteGate';
	private const CODE     = 'code';

	public static function is_available(): bool {
		return class_exists( self::SETTINGS ) && class_exists( self::GATE );
	}

	/** Setzt für die Seite die Core-Regel »mit Code«. */
	public static function protect( int $page_id ): bool {
		if ( $page_id <= 0 || ! self::is_available() ) {
			return false;
		}
		( self::SETTINGS )::set_override( $page_id, self::CODE );
		return true;
	}

	/** Trägt die Seite die Regel »mit Code«? (Anzeige 🔒, Backend-Status). */
	public static function is_protected( int $page_id ): bool {
		return $page_id > 0 && self::is_available() && self::CODE === ( self::SETTINGS )::override_of( $page_id );
	}

	/**
	 * Darf der aktuelle Besucher geschützte Inhalte sehen? Zweite Verteidigungslinie – die Maske selbst
	 * setzt der Core vor der Seite durch. Berechtigte Backend-Nutzer sehen immer; der Core-Gate-Cookie
	 * zählt, wenn der Schutz in dieser Umgebung aktiv ist (Dev: Standard AUS → Vorschau offen).
	 */
	public static function is_unlocked(): bool {
		if ( current_user_can( RoleBridge::CAP_MANAGE_INTERFACES ) ) {
			return true;
		}
		if ( ! self::is_available() ) {
			return false;
		}
		if ( ! ( self::SETTINGS )::enabled() ) {
			return true;
		}
		return (bool) ( self::GATE )::has_valid_cookie();
	}
}
