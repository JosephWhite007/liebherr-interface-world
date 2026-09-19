<?php
/**
 * Liebherr World – CAPDB Validierung (Pflichtenheft §29.7/§31).
 *
 * Prüft einen Board-Entwurf vor der Veröffentlichung: Struktur (Bereiche/Kanten), Zielzonen-Kompatibilität
 * der Plugin-Instanzen (Scope), Timer-Konsistenz und Konflikte. Liefert eine Liste maschinenlesbarer
 * Problem-Schlüssel (leer = veröffentlichbar). Wird in späteren Etappen um Security-/A11y-Regeln erweitert.
 *
 * @package Liebherr\InterfaceWorld\Cvf
 * @since   0.1.0-alpha.89
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Cvf;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BoardValidator {

	/**
	 * @return array<int,string> Problem-Schlüssel (leer = gültig).
	 */
	public static function validate( int $version_id ): array {
		$problems = [];
		$areas    = BoardRepository::areas( $version_id );
		$edges    = BoardRepository::edges( $version_id );

		if ( 0 === count( $areas ) ) {
			$problems[] = 'no_areas';
			return $problems;
		}

		// Positionen eindeutig + ein Einstieg (Intelligence World) an Position 1.
		$positions = [];
		$area_ids  = [];
		$has_entry = false;
		foreach ( $areas as $a ) {
			$area_ids[] = (int) $a['id'];
			$pos        = (int) $a['position'];
			if ( in_array( $pos, $positions, true ) ) {
				$problems[] = 'duplicate_position_' . $pos;
			} else {
				$positions[] = $pos;
			}
			if ( 1 === $pos && 'intelligence_world' === (string) $a['module_id'] ) {
				$has_entry = true;
			}
		}
		if ( ! $has_entry ) {
			$problems[] = 'no_entry_area';
		}

		// Kanten referenzieren existierende Bereiche derselben Version.
		foreach ( $edges as $e ) {
			if ( ! in_array( (int) $e['from_area_id'], $area_ids, true ) ) {
				$problems[] = 'edge_from_unknown_' . (int) $e['id'];
			}
			if ( ! in_array( (int) $e['to_area_id'], $area_ids, true ) ) {
				$problems[] = 'edge_to_unknown_' . (int) $e['id'];
			}
		}

		// Plugin-Instanzen: Host existiert + Scope passt zum registrierten Typ + Timer widerspruchsfrei.
		$edge_ids = array_map( static fn( $e ) => (int) $e['id'], $edges );
		foreach ( BoardRepository::instances( $version_id ) as $ins ) {
			$host_type = (string) $ins['host_type'];
			$host_id   = (int) $ins['host_id'];
			$host_ok   = ( PluginTaxonomy::SCOPE_PAGE === $host_type && in_array( $host_id, $area_ids, true ) )
				|| ( PluginTaxonomy::SCOPE_EDGE === $host_type && in_array( $host_id, $edge_ids, true ) );
			if ( ! $host_ok ) {
				$problems[] = 'instance_host_unknown_' . (int) $ins['id'];
				continue;
			}
			$type = self::plugin_type( (int) $ins['plugin_type_id'] );
			if ( null === $type ) {
				$problems[] = 'instance_type_unknown_' . (int) $ins['id'];
			} elseif ( ! PluginTaxonomy::scope_allowed( $host_type, (string) $type['allowed_scopes'] ) ) {
				$problems[] = 'instance_scope_forbidden_' . (int) $ins['id'];
			}
			$sched = BoardRepository::schedule( (int) $ins['id'] );
			if ( null !== $sched && null !== $sched['close_at_ms'] && null !== $sched['duration_ms'] ) {
				$problems[] = 'schedule_close_and_duration_' . (int) $ins['id']; // §27: widersprüchlich.
			}
		}
		return $problems;
	}

	/** @return array<string,mixed>|null */
	private static function plugin_type( int $id ): ?array {
		global $wpdb;
		$t = BoardSchema::plugin_type_table();
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $r ?: null;
	}
}
