<?php
/**
 * Liebherr Intelligence World – Resolver der Simulations-Engine (Pflichtenheft-2 §6.4).
 *
 * Liefert die aktive Engine: standardmäßig die Mock-Engine, oder – falls über den Filter
 * `liw_iw_simulation_engine` eine {@see SimulationEngineInterface}-Instanz bereitgestellt wird – diese echte
 * Engine. So kann eine reale Simulations-Engine (Gruppe-B-Zulieferung) ohne Umbau des Builders angeschlossen
 * werden. `is_mock()` sagt, ob noch der Standard aktiv ist (steuert u. a., ob das Frontend serverseitig rechnet).
 *
 * @package Liebherr\InterfaceWorld\IntelligenceWorld
 * @since   0.1.0-alpha.74
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\IntelligenceWorld;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SimulationEngine {

	/** Aktive Engine (Filter-Override oder Mock-Standard). */
	public static function resolve(): SimulationEngineInterface {
		$engine = apply_filters( 'liw_iw_simulation_engine', null );
		if ( $engine instanceof SimulationEngineInterface ) {
			return $engine;
		}
		return new SimulationMockEngine();
	}

	/** Ist noch die Mock-Standard-Engine aktiv (keine echte Engine registriert)? */
	public static function is_mock(): bool {
		return self::resolve() instanceof SimulationMockEngine;
	}
}
