<?php
/**
 * Liebherr World – Pocket Information: Feature-Flag (Pflichtenheft My Liebherr §34, ADR-LIW-MYL-001 R5).
 *
 *   liw_pocket_enabled : schaltet den sechsten Reiter Pocket Information (Feed/REST/Widget) scharf. Default AUS.
 *
 * @package Liebherr\InterfaceWorld\Pocket
 * @since   0.1.0-alpha.124
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Pocket;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Flags {

	public const OPT_ENABLED = 'liw_pocket_enabled';

	public static function enabled(): bool {
		$opt = (bool) get_option( self::OPT_ENABLED, false );
		return (bool) apply_filters( 'liw_pocket_enabled', $opt );
	}
}
