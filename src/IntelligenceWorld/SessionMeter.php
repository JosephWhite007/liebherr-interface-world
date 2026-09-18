<?php
/**
 * Liebherr Intelligence World – Session-Meter (Pflichtenheft-2 §13.1).
 *
 * Reine Berechnung der abrechenbaren aktiven Zeit aus Heartbeat-Zeitstempeln und einem Inaktivitäts-Timeout.
 * Grundsatz (§5.3/§13.1/§8): Nach Ablauf des Timeouts endet das aktive Intervall automatisch; nicht nutzbare
 * Zeit wird nicht berechnet. Bewusst konservativ: eine Lücke größer als der Timeout zählt gar nicht (statt
 * bis zum Timeout „aufzufüllen") – so wird nie zu viel berechnet. Ohne WP-Abhängigkeit (unit-testbar).
 *
 * @package Liebherr\InterfaceWorld\IntelligenceWorld
 * @since   0.1.0-alpha.47
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\IntelligenceWorld;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SessionMeter {

	/**
	 * @param int[]    $heartbeats  Aufsteigende UTC-Zeitstempel (inkl. Start als erster Heartbeat).
	 * @param int      $timeout     Inaktivitäts-Timeout in Sekunden (> 0).
	 * @param int|null $ended_at    Zeitpunkt des Sitzungsendes (UTC) oder null (noch aktiv/offen).
	 * @return int Abrechenbare aktive Sekunden.
	 */
	public static function active_seconds( array $heartbeats, int $timeout, ?int $ended_at = null ): int {
		$hb = array_values( array_filter( array_map( 'intval', $heartbeats ), static fn( $v ): bool => $v > 0 ) );
		sort( $hb );
		$hb = array_values( array_unique( $hb ) );

		if ( [] === $hb || $timeout <= 0 ) {
			return 0;
		}

		$active = 0;
		$count  = count( $hb );
		for ( $i = 0; $i < $count - 1; $i++ ) {
			$gap = $hb[ $i + 1 ] - $hb[ $i ];
			if ( $gap > 0 && $gap <= $timeout ) {
				$active += $gap;
			}
		}

		// Abschlusssegment: nur anrechnen, wenn zeitnah (innerhalb des Timeouts) beendet wurde.
		if ( null !== $ended_at ) {
			$tail = $ended_at - $hb[ $count - 1 ];
			if ( $tail > 0 && $tail <= $timeout ) {
				$active += $tail;
			}
		}

		return $active;
	}

	/** Gilt die Sitzung bei „now" als per Timeout inaktiv (letzter Heartbeat länger als Timeout her)? */
	public static function is_timed_out( int $last_heartbeat, int $timeout, int $now ): bool {
		return $timeout > 0 && ( $now - $last_heartbeat ) > $timeout;
	}
}
