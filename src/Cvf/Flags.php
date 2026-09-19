<?php
/**
 * Liebherr World – Customer View Flow: Feature-Flags (ADR-LIW-CVF-001 §6/§9).
 *
 * Der neue Customer-View-Flow läuft ausschließlich hinter Flags (Default AUS), damit der heutige, bewährte
 * IW-Eintritt unverändert bleibt, bis die CVF-Runtime ausdrücklich freigegeben wird (JW-Entscheid §9.3).
 *
 *   - liw_cvf_enabled   : schaltet die CVF-Runtime/den Durchstich scharf.
 *   - liw_cvf_four_eyes : Vier-Augen-Freigabe beim Veröffentlichen (optional/konfigurierbar, JW-Entscheid §9.4).
 *
 * @package Liebherr\InterfaceWorld\Cvf
 * @since   0.1.0-alpha.84
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Cvf;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Flags {

	public const OPT_ENABLED   = 'liw_cvf_enabled';
	public const OPT_FOUR_EYES = 'liw_cvf_four_eyes';

	/** CVF-Runtime aktiv? Default AUS; über Option ODER Filter `liw_cvf_enabled` einschaltbar. */
	public static function enabled(): bool {
		$opt = (bool) get_option( self::OPT_ENABLED, false );
		return (bool) apply_filters( 'liw_cvf_enabled', $opt );
	}

	/** Vier-Augen-Freigabe erzwingen? Default AUS; Option ODER Filter `liw_cvf_four_eyes`. */
	public static function four_eyes(): bool {
		$opt = (bool) get_option( self::OPT_FOUR_EYES, false );
		return (bool) apply_filters( 'liw_cvf_four_eyes', $opt );
	}
}
