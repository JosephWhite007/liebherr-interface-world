<?php
/**
 * Liebherr World – Plattformzeit: Session-Uhr-Kernlogik (Pflichtenheft My Liebherr §41, ADR-LIW-MYL-001 S9).
 *
 * Serverautoritäre, inkrementelle Zeitverbuchung: aus dem letzten „gesehen"-Zeitpunkt und „jetzt" wird die
 * verbrauchte Zeit fortgeschrieben. Konservativ wie der IW-{@see \Liebherr\InterfaceWorld\IntelligenceWorld\SessionMeter}:
 * eine Lücke größer als der Inaktivitäts-Timeout zählt NICHT (Idle → Pause), sonst wird sie voll angerechnet.
 * Der Browser-Timer dient nur der Anzeige; maßgeblich ist diese serverseitige Fortschreibung. Ohne
 * WP-Abhängigkeit (unit-testbar).
 *
 * @package Liebherr\InterfaceWorld\PlatformTime
 * @since   0.1.0-alpha.111
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\PlatformTime;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SessionClock {

	/**
	 * Schreibt die aktive Zeit fort. Liefert die neuen aktiven Sekunden, den neuen „gesehen"-Zeitpunkt und ob
	 * das Intervall als Idle (Pause) gewertet wurde.
	 *
	 * @param int $active    Bisher angerechnete aktive Sekunden.
	 * @param int $last_seen Letzter „gesehen"-Zeitpunkt (UTC).
	 * @param int $now       Aktueller Zeitpunkt (UTC).
	 * @param int $timeout   Inaktivitäts-Timeout in Sekunden (> 0).
	 * @return array{active:int,last_seen:int,idle:bool}
	 */
	public static function accrue( int $active, int $last_seen, int $now, int $timeout ): array {
		$active = max( 0, $active );
		if ( $now <= $last_seen || $timeout <= 0 ) {
			return [ 'active' => $active, 'last_seen' => max( $last_seen, $now ), 'idle' => false ];
		}
		$gap  = $now - $last_seen;
		$idle = $gap > $timeout;
		if ( ! $idle ) {
			$active += $gap; // zeitnah → voll anrechnen
		}
		return [ 'active' => $active, 'last_seen' => $now, 'idle' => $idle ];
	}
}
