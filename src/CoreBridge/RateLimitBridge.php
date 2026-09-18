<?php
/**
 * Liebherr Interface Solutions – Rate-Limit Bridge (SEC-004).
 *
 * Dünner Wrapper um `Araliya\Platform\Core\Core\RateLimiter::check()` (verifiziert: generisch,
 * IP-basiert, 60-s-Fenster). Kein eigenes Rate-Limiting (CoreBridge, Variante A). Bei fehlendem
 * Core wird NICHT blockiert (`true`), damit die öffentlichen Formulare nutzbar bleiben – der
 * Missbrauchsschutz ist eine Härtung, kein Funktionsträger (Graceful Degradation, §14).
 *
 * @package Liebherr\InterfaceWorld\CoreBridge
 * @since   0.1.0-alpha.33
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\CoreBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class RateLimitBridge {

	private const RATE_LIMITER_CLASS = 'Araliya\\Platform\\Core\\Core\\RateLimiter';

	/** Standard-Limit für öffentliche Formular-Absendungen (Requests/Minute je IP). */
	public const FORM_LIMIT = 5;

	/**
	 * @param string $action fachlicher Bezeichner (wird mit `liw_` präfixiert)
	 * @return bool true = erlaubt, false = Limit überschritten
	 */
	public static function allow( string $action, int $limit = self::FORM_LIMIT ): bool {
		if ( ! class_exists( self::RATE_LIMITER_CLASS ) || ! method_exists( self::RATE_LIMITER_CLASS, 'check' ) ) {
			return true; // Ohne Core kein Blockieren (Graceful Degradation).
		}
		return (bool) call_user_func( [ self::RATE_LIMITER_CLASS, 'check' ], 'liw_' . $action, max( 1, $limit ) );
	}
}
