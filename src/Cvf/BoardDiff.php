<?php
/**
 * Liebherr World – CAPDB Änderungs-Diff (Pflichtenheft §29.8).
 *
 * Reiner Vergleich zweier Board-Snapshots (z. B. veröffentlichte Version vs. Entwurf): welche Bereiche,
 * Übergänge und Plugin-Instanzen kamen hinzu bzw. fielen weg. Grundlage für die Diff-Prüfung vor der
 * Freigabe. Ohne WordPress testbar.
 *
 * @package Liebherr\InterfaceWorld\Cvf
 * @since   0.1.0-alpha.95
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Cvf;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BoardDiff {

	/**
	 * @param array<string,mixed> $from Snapshot der Ausgangsversion (z. B. veröffentlicht)
	 * @param array<string,mixed> $to   Snapshot der Zielversion (z. B. Entwurf)
	 * @return array{areas:array{added:array<int,string>,removed:array<int,string>},edges:array{added:array<int,string>,removed:array<int,string>},instances:array{added:array<int,string>,removed:array<int,string>},changed:bool}
	 */
	public static function compare( array $from, array $to ): array {
		$fa = self::area_keys( $from );
		$ta = self::area_keys( $to );
		$fe = self::edge_keys( $from );
		$te = self::edge_keys( $to );
		$fi = self::instance_keys( $from );
		$ti = self::instance_keys( $to );

		$areas = [ 'added' => array_values( array_diff( $ta, $fa ) ), 'removed' => array_values( array_diff( $fa, $ta ) ) ];
		$edges = [ 'added' => array_values( array_diff( $te, $fe ) ), 'removed' => array_values( array_diff( $fe, $te ) ) ];
		$inst  = [ 'added' => array_values( array_diff( $ti, $fi ) ), 'removed' => array_values( array_diff( $fi, $ti ) ) ];
		$changed = (bool) ( $areas['added'] || $areas['removed'] || $edges['added'] || $edges['removed'] || $inst['added'] || $inst['removed'] );

		return [ 'areas' => $areas, 'edges' => $edges, 'instances' => $inst, 'changed' => $changed ];
	}

	/** @param array<string,mixed> $s @return array<int,string> */
	private static function area_keys( array $s ): array {
		return array_map( static fn( $a ) => (string) $a['position'] . ':' . (string) $a['module_id'], (array) ( $s['areas'] ?? [] ) );
	}

	/** @param array<string,mixed> $s @return array<int,string> */
	private static function edge_keys( array $s ): array {
		// Positionsbasiert (Bereichs-Positionen statt DB-IDs), damit der Diff versionsübergreifend stabil ist.
		$pos = [];
		foreach ( (array) ( $s['areas'] ?? [] ) as $a ) {
			$pos[ (int) $a['id'] ] = (int) $a['position'];
		}
		$out = [];
		foreach ( (array) ( $s['edges'] ?? [] ) as $e ) {
			$out[] = ( $pos[ (int) $e['from_area_id'] ] ?? '?' ) . '->' . ( $pos[ (int) $e['to_area_id'] ] ?? '?' ) . ':' . (string) $e['trigger_type'];
		}
		return $out;
	}

	/** @param array<string,mixed> $s @return array<int,string> */
	private static function instance_keys( array $s ): array {
		$pos = [];
		foreach ( (array) ( $s['areas'] ?? [] ) as $a ) {
			$pos[ (int) $a['id'] ] = 'page#' . (int) $a['position'];
		}
		$out = [];
		foreach ( (array) ( $s['instances'] ?? [] ) as $i ) {
			$host = 'page' === (string) $i['host_type'] ? ( $pos[ (int) $i['host_id'] ] ?? 'page?' ) : 'edge';
			$out[] = (string) ( $i['plugin_key'] ?? '' ) . '@' . $host;
		}
		sort( $out );
		return $out;
	}
}
