<?php
/**
 * Liebherr World – My Liebherr: Feature-Flags (ADR-LIW-MYL-001 §4, Stufe S1).
 *
 * Der persönliche Bereich „My Liebherr" läuft ausschließlich hinter einem Flag (Default AUS), damit die
 * bestehenden vier Welten unberührt bleiben, bis der Bereich ausdrücklich freigegeben wird. Analog zu
 * {@see \Liebherr\InterfaceWorld\Cvf\Flags}.
 *
 *   - liw_myl_enabled : schaltet My Liebherr (Navigation, REST, Widgets) scharf.
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.108
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Flags {

	public const OPT_ENABLED = 'liw_myl_enabled';

	/** My Liebherr aktiv? Default AUS; über Option ODER Filter `liw_myl_enabled` einschaltbar. */
	public static function enabled(): bool {
		$opt = (bool) get_option( self::OPT_ENABLED, false );
		return (bool) apply_filters( 'liw_myl_enabled', $opt );
	}
}
