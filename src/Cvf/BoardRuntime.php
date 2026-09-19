<?php
/**
 * Liebherr World – CAPDB Board-Runtime (Pflichtenheft §9/§27/§28).
 *
 * Reine, deterministische Auflösung eines veröffentlichten Board-Snapshots: Einstiegsbereich, ausgehende
 * Übergänge nach Priorität, Plugin-Ketten je Host (Seite/Übergang) und die aufgelösten Zeitfenster. Keine
 * Autorisierung/DB hier – die Aufrufschicht prüft serverseitig und protokolliert (plugin_execution).
 *
 * @package Liebherr\InterfaceWorld\Cvf
 * @since   0.1.0-alpha.92
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Cvf;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BoardRuntime {

	/**
	 * Einstiegsbereich: aktiver Bereich mit der kleinsten Position (Standard: Intelligence World @1).
	 *
	 * @param array<string,mixed> $snapshot
	 * @return array<string,mixed>|null
	 */
	public static function entry_area( array $snapshot ): ?array {
		$best = null;
		foreach ( (array) ( $snapshot['areas'] ?? [] ) as $a ) {
			if ( 'active' !== (string) ( $a['status'] ?? 'active' ) ) {
				continue;
			}
			if ( null === $best || (int) $a['position'] < (int) $best['position'] ) {
				$best = $a;
			}
		}
		return $best;
	}

	/**
	 * Ausgehende Übergänge eines Bereichs, nach Priorität aufsteigend (kleiner = wichtiger).
	 *
	 * @param array<string,mixed> $snapshot
	 * @return array<int,array<string,mixed>>
	 */
	public static function edges_from( array $snapshot, int $area_id ): array {
		$out = [];
		foreach ( (array) ( $snapshot['edges'] ?? [] ) as $e ) {
			if ( (int) $e['from_area_id'] === $area_id ) {
				$out[] = $e;
			}
		}
		usort( $out, static fn( $a, $b ) => ( (int) $a['priority'] <=> (int) $b['priority'] ) ?: ( (int) $a['id'] <=> (int) $b['id'] ) );
		return $out;
	}

	/**
	 * Plugin-Instanzen eines Hosts (Seite/Übergang), deaktivierte ausgeschlossen, nach Priorität.
	 *
	 * @param array<string,mixed> $snapshot
	 * @return array<int,array<string,mixed>>
	 */
	public static function plugins_for( array $snapshot, string $host_type, int $host_id ): array {
		$out = [];
		foreach ( (array) ( $snapshot['instances'] ?? [] ) as $i ) {
			if ( (string) $i['host_type'] === $host_type && (int) $i['host_id'] === $host_id && PluginState::DISABLED !== (string) ( $i['status'] ?? '' ) ) {
				$out[] = $i;
			}
		}
		usort( $out, static fn( $a, $b ) => ( (int) $a['priority'] <=> (int) $b['priority'] ) ?: ( (int) $a['id'] <=> (int) $b['id'] ) );
		return $out;
	}

	/**
	 * Löst das Zeitfenster einer Instanz auf: closeAt wird ggf. aus openAt + duration abgeleitet.
	 *
	 * @param array<string,mixed>|null $schedule
	 * @return array{open_at_ms:int,close_at_ms:?int,timeout_ms:?int,resume_policy:string}
	 */
	public static function resolve_window( ?array $schedule ): array {
		$open  = ( $schedule && null !== ( $schedule['open_at_ms'] ?? null ) ) ? (int) $schedule['open_at_ms'] : 0;
		$close = ( $schedule && null !== ( $schedule['close_at_ms'] ?? null ) ) ? (int) $schedule['close_at_ms'] : null;
		$dur   = ( $schedule && null !== ( $schedule['duration_ms'] ?? null ) ) ? (int) $schedule['duration_ms'] : null;
		if ( null === $close && null !== $dur ) {
			$close = $open + $dur;
		}
		return [
			'open_at_ms'    => max( 0, $open ),
			'close_at_ms'   => ( null !== $close ) ? max( 0, $close ) : null,
			'timeout_ms'    => ( $schedule && null !== ( $schedule['timeout_ms'] ?? null ) ) ? (int) $schedule['timeout_ms'] : null,
			'resume_policy' => (string) ( $schedule['resume_policy'] ?? 'continue' ),
		];
	}

	/**
	 * Erzeugt einen chronologischen Marker-Plan (für Simulation/Anzeige): je Plugin ein open- und ein
	 * close-Marker in Millisekunden ab timeOrigin, nach Zeit sortiert. Rein.
	 *
	 * @param array<int,array<string,mixed>> $plugins  Instanzen (mit Feld plugin_key)
	 * @param array<int,array<string,mixed>> $schedules instance_id → schedule
	 * @return array<int,array{at_ms:int,type:string,instance_id:int,plugin_key:string}>
	 */
	public static function marker_plan( array $plugins, array $schedules ): array {
		$markers = [];
		foreach ( $plugins as $p ) {
			$iid    = (int) $p['id'];
			$win    = self::resolve_window( $schedules[ $iid ] ?? null );
			$key    = (string) ( $p['plugin_key'] ?? '' );
			$markers[] = [ 'at_ms' => (int) $win['open_at_ms'], 'type' => 'open', 'instance_id' => $iid, 'plugin_key' => $key ];
			if ( null !== $win['close_at_ms'] ) {
				$markers[] = [ 'at_ms' => (int) $win['close_at_ms'], 'type' => 'close', 'instance_id' => $iid, 'plugin_key' => $key ];
			}
		}
		usort( $markers, static fn( $a, $b ) => ( $a['at_ms'] <=> $b['at_ms'] ) ?: ( $a['instance_id'] <=> $b['instance_id'] ) );
		return $markers;
	}
}
