<?php
/**
 * Liebherr World – Plattformzeit: Feature-Flags (Pflichtenheft My Liebherr §41, ADR-LIW-MYL-001 S9/S10).
 *
 *   - liw_ptime_enabled     : Session-Uhr / Zeitmessung scharf (Widget + REST). Default AUS.
 *   - liw_ptime_charge_live : tatsächliche Wallet-Buchung der Zeitabrechnung. Default AUS – solange AUS, werden
 *                             Abrechnungen nur als ausstehend protokolliert (Wallet-Mehrwährung kommt mit dem
 *                             Wallet-Pflichtenheft, §41.1).
 *
 * @package Liebherr\InterfaceWorld\PlatformTime
 * @since   0.1.0-alpha.111
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\PlatformTime;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Flags {

	public const OPT_ENABLED     = 'liw_ptime_enabled';
	public const OPT_CHARGE_LIVE = 'liw_ptime_charge_live';
	public const OPT_LOCK        = 'liw_ptime_lock_enabled';

	/** Zeitmessung/Session-Uhr aktiv? Default AUS; Option ODER Filter `liw_ptime_enabled`. */
	public static function enabled(): bool {
		$opt = (bool) get_option( self::OPT_ENABLED, false );
		return (bool) apply_filters( 'liw_ptime_enabled', $opt );
	}

	/**
	 * Plattformweite Sperre bei Standby/Beenden aktiv? Nur wirksam, wenn die Zeitmessung überhaupt läuft
	 * ({@see self::enabled()}); zusätzlich per Option/Filter `liw_ptime_lock_enabled` abschaltbar (Default AN),
	 * damit die Uhr notfalls ohne Sperre betrieben werden kann (ADR-LIW-MYL-002 §10).
	 */
	public static function lock_enabled(): bool {
		if ( ! self::enabled() ) {
			return false;
		}
		$opt = (bool) get_option( self::OPT_LOCK, true );
		return (bool) apply_filters( 'liw_ptime_lock_enabled', $opt );
	}

	/** Echte Wallet-Buchung der Zeitabrechnung aktiv? Default AUS; Option ODER Filter `liw_ptime_charge_live`. */
	public static function charge_live(): bool {
		$opt = (bool) get_option( self::OPT_CHARGE_LIVE, false );
		return (bool) apply_filters( 'liw_ptime_charge_live', $opt );
	}
}
