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
	public const OPT_RULES   = 'liw_pocket_rules_enabled';

	public static function enabled(): bool {
		$opt = (bool) get_option( self::OPT_ENABLED, false );
		return (bool) apply_filters( 'liw_pocket_enabled', $opt );
	}

	/**
	 * Regelbasierte Personalisierung (automatische Briefings/Alerts/Tasks) aktiv? Default AN, aber abschaltbar
	 * (§34/§5: Empfehlungen müssen abschaltbar sein). Option ODER Filter `liw_pocket_rules_enabled`.
	 */
	public static function rules_enabled(): bool {
		$opt = (bool) get_option( self::OPT_RULES, true );
		return (bool) apply_filters( 'liw_pocket_rules_enabled', $opt );
	}
}
