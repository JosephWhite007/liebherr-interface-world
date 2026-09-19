<?php
/**
 * Liebherr World – CAPDB Board-Snapshot (liest eine veröffentlichte Version als Array für die Runtime).
 *
 * Wandelt die DB-Zeilen einer Version in die reine Struktur um, die {@see BoardRuntime} und das Frontend/
 * die Simulation konsumieren (Bereiche, Kanten, Instanzen inkl. plugin_key, Schedules). Runtime arbeitet
 * ausschließlich mit veröffentlichten Versionen (§31.1).
 *
 * @package Liebherr\InterfaceWorld\Cvf
 * @since   0.1.0-alpha.92
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Cvf;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BoardSnapshot {

	/** @return array<string,mixed>|null */
	public static function active(): ?array {
		$id = BoardRepository::published_id();
		return $id > 0 ? self::of_version( $id ) : null;
	}

	/** @return array<string,mixed> */
	public static function of_version( int $version_id ): array {
		$type_keys = [];
		foreach ( PluginRegistry::all_types() as $t ) {
			$type_keys[ (int) $t['id'] ] = (string) $t['plugin_key'];
		}
		$areas = array_map( static fn( $a ) => [
			'id' => (int) $a['id'], 'module_id' => (string) $a['module_id'], 'position' => (int) $a['position'],
			'route_id' => (string) ( $a['route_id'] ?? '' ), 'status' => (string) $a['status'],
		], BoardRepository::areas( $version_id ) );

		$edges = array_map( static fn( $e ) => [
			'id' => (int) $e['id'], 'from_area_id' => (int) $e['from_area_id'], 'to_area_id' => (int) $e['to_area_id'],
			'trigger_type' => (string) $e['trigger_type'], 'priority' => (int) $e['priority'],
		], BoardRepository::edges( $version_id ) );

		$instances = [];
		$schedules = [];
		foreach ( BoardRepository::instances( $version_id ) as $i ) {
			$iid         = (int) $i['id'];
			$instances[] = [
				'id' => $iid, 'plugin_type_id' => (int) $i['plugin_type_id'],
				'plugin_key' => $type_keys[ (int) $i['plugin_type_id'] ] ?? '',
				'host_type' => (string) $i['host_type'], 'host_id' => (int) $i['host_id'],
				'status' => (string) $i['status'], 'priority' => (int) $i['priority'],
				'config' => json_decode( (string) ( $i['config_json'] ?? '' ), true ) ?: [],
			];
			$sched = BoardRepository::schedule( $iid );
			if ( null !== $sched ) {
				$schedules[ $iid ] = $sched;
			}
		}

		return [ 'version_id' => $version_id, 'areas' => $areas, 'edges' => $edges, 'instances' => $instances, 'schedules' => $schedules ];
	}
}
