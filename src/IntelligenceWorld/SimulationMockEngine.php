<?php
/**
 * Liebherr Intelligence World – Mock-Simulations-Engine (Standard, Pflichtenheft-2 §6.4/§21).
 *
 * Deterministisches Beispielmodell: delegiert an {@see SimulationModel::forecast()}. Prototyp – keine echte
 * Prognose. Wird verwendet, solange keine echte Engine über den Filter `liw_iw_simulation_engine` gesetzt ist.
 *
 * @package Liebherr\InterfaceWorld\IntelligenceWorld
 * @since   0.1.0-alpha.74
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\IntelligenceWorld;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SimulationMockEngine implements SimulationEngineInterface {

	public function forecast( int $base, int $growth_permille, int $periods, string $scenario ): array {
		return SimulationModel::forecast( $base, $growth_permille, $periods, $scenario );
	}

	public function id(): string {
		return 'mock-1';
	}
}
