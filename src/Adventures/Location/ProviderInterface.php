<?php
/**
 * Liebherr Adventures – Drei-Wörter-Ortsdienst: Provider-Vertrag (Grundlagenkonzept §4).
 *
 * Der konkrete Anbieter (Name, API, Endpunkte) darf NICHT fest verdrahtet werden (§4.1). Konsumierender
 * Code spricht nur dieses Interface an; die konkrete Implementierung (Prototyp: Mock) wird über
 * LocationService/Filter aufgelöst. Ortscode = drei englische Wörter (§15: immer in englischer Providerform).
 *
 * @package Liebherr\InterfaceWorld\Adventures\Location
 * @since   0.1.0-alpha.51
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Adventures\Location;

if ( ! defined( 'ABSPATH' ) ) { exit; }

interface ProviderInterface {

	/** Anbietername (nur für Attribution/Anzeige, nicht hart in Logik verwenden). */
	public function name(): string;

	/** Provider-/Datenversion (für Nachvollziehbarkeit, §4.1). */
	public function version(): string;

	/**
	 * Koordinaten → Ortscode.
	 * @return array{words:string,lat:float,lng:float,accuracy:string}
	 */
	public function encode( float $lat, float $lng ): array;

	/**
	 * Ortscode → Koordinaten (umgekehrte Auflösung, §13). Null bei ungültigem Code.
	 * @return array{lat:float,lng:float}|null
	 */
	public function decode( string $words ): ?array;
}
