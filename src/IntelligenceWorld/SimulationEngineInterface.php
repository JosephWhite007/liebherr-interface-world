<?php
/**
 * Liebherr Intelligence World – Vertrag einer Simulations-Engine (Pflichtenheft-2 §6.4).
 *
 * Austauschbare Naht: der Simulation Builder rechnet gegen diesen Vertrag, nicht gegen ein konkretes Modell.
 * Standard ist die Mock-Engine ({@see SimulationMockEngine}); eine echte Engine kann über den Filter
 * `liw_iw_simulation_engine` (siehe {@see SimulationEngine}) eingehängt werden, ohne den Builder zu ändern.
 *
 * @package Liebherr\InterfaceWorld\IntelligenceWorld
 * @since   0.1.0-alpha.74
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\IntelligenceWorld;

if ( ! defined( 'ABSPATH' ) ) { exit; }

interface SimulationEngineInterface {

	/**
	 * Berechnet eine Prognosereihe. Rückgabe wie {@see SimulationModel::forecast()}
	 * (Schlüssel u. a. scenario, periods, base, values[], end, total, delta_permille, effective_permille).
	 *
	 * @return array<string,mixed>
	 */
	public function forecast( int $base, int $growth_permille, int $periods, string $scenario ): array;

	/** Kurzkennung/Version der Engine (für Anzeige/Protokoll, z. B. „mock-1"). */
	public function id(): string;
}
