<?php
/**
 * Liebherr World – Customer View Flow: Repository der veröffentlichten Workflow-Versionen (§10/§12).
 *
 * Veröffentlichen legt eine UNVERÄNDERLICHE Zeile an (Config + Prüfsumme); es gibt kein Update/Delete auf
 * veröffentlichte Versionen (Rollback = neue Version aus altem Stand). Validierung über {@see WorkflowVersion}.
 *
 * @package Liebherr\InterfaceWorld\Cvf
 * @since   0.1.0-alpha.83
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Cvf;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class WorkflowRepository {

	/**
	 * Veröffentlicht eine Config als neue, unveränderliche Version.
	 *
	 * @param array<string,mixed> $config
	 * @return array{ok:bool,reason:string,id:int,version:string,checksum:string}
	 */
	public static function publish( array $config, int $user_id = 0 ): array {
		if ( ! WorkflowVersion::is_valid( $config ) ) {
			return [ 'ok' => false, 'reason' => 'invalid_config', 'id' => 0, 'version' => '', 'checksum' => '' ];
		}
		global $wpdb;
		$t        = Schema::version_table();
		$checksum = WorkflowVersion::checksum( $config );
		$now      = gmdate( 'Y-m-d H:i:s' );
		$version  = self::next_version();

		$ok = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$t,
			[
				'version'        => $version,
				'schema_version' => (int) ( $config['schema_version'] ?? WorkflowVersion::SCHEMA_VERSION ),
				'state'          => 'published',
				'config_json'    => (string) wp_json_encode( $config ),
				'checksum'       => $checksum,
				'published_at'   => $now,
				'published_by'   => $user_id > 0 ? $user_id : null,
				'created_at'     => $now,
			],
			[ '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s' ]
		);
		if ( false === $ok ) {
			return [ 'ok' => false, 'reason' => 'db_error', 'id' => 0, 'version' => $version, 'checksum' => $checksum ];
		}
		return [ 'ok' => true, 'reason' => 'ok', 'id' => (int) $wpdb->insert_id, 'version' => $version, 'checksum' => $checksum ];
	}

	/** Nächste fortlaufende Versionsnummer (v1, v2, …). */
	public static function next_version(): string {
		global $wpdb;
		$t = Schema::version_table();
		$n = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" ); // phpcs:ignore WordPress.DB
		return 'v' . ( $n + 1 );
	}

	/**
	 * Aktuell gültige (zuletzt veröffentlichte) Version.
	 *
	 * @return array{id:int,version:string,checksum:string,config:array<string,mixed>}|null
	 */
	public static function get_active(): ?array {
		global $wpdb;
		$t   = Schema::version_table();
		$row = $wpdb->get_row( "SELECT id, version, checksum, config_json FROM {$t} WHERE state = 'published' ORDER BY id DESC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * @return array{id:int,version:string,checksum:string,config:array<string,mixed>}|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		$t   = Schema::version_table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id, version, checksum, config_json FROM {$t} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $row ? self::hydrate( $row ) : null;
	}

	/**
	 * Stellt sicher, dass eine aktive Version existiert (sonst Default-Config veröffentlichen).
	 *
	 * @return array{id:int,version:string,checksum:string,config:array<string,mixed>}
	 */
	public static function ensure_active(): array {
		$active = self::get_active();
		if ( null !== $active ) {
			return $active;
		}
		$res = self::publish( WorkflowVersion::default_config() );
		return self::get( $res['id'] ) ?? [ 'id' => 0, 'version' => '', 'checksum' => '', 'config' => WorkflowVersion::default_config() ];
	}

	/**
	 * Versionshistorie (neueste zuerst).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function history( int $limit = 20 ): array {
		global $wpdb;
		$t     = Schema::version_table();
		$limit = max( 1, min( 100, $limit ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT id, version, checksum, state, published_at, published_by FROM {$t} ORDER BY id DESC LIMIT %d", $limit ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $rows ) ? $rows : [];
	}

	/** Benutzer-ID des letzten Veröffentlichers (0 wenn keiner/unbekannt) – Grundlage der Vier-Augen-Prüfung. */
	public static function last_published_by(): int {
		global $wpdb;
		$t = Schema::version_table();
		return (int) $wpdb->get_var( "SELECT published_by FROM {$t} WHERE state = 'published' ORDER BY id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Veröffentlicht mit optionaler Vier-Augen-Prüfung: ist sie aktiv, darf der Freigebende nicht der
	 * letzte Veröffentlicher sein (§9.4). Reine Guard-Logik oberhalb von publish().
	 *
	 * @param array<string,mixed> $config
	 * @return array{ok:bool,reason:string,id:int,version:string,checksum:string}
	 */
	public static function publish_guarded( array $config, int $user_id, bool $four_eyes ): array {
		if ( $four_eyes && $user_id > 0 && self::last_published_by() === $user_id ) {
			return [ 'ok' => false, 'reason' => 'four_eyes_same_person', 'id' => 0, 'version' => '', 'checksum' => '' ];
		}
		return self::publish( $config, $user_id );
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array{id:int,version:string,checksum:string,config:array<string,mixed>}
	 */
	private static function hydrate( array $row ): array {
		$config = json_decode( (string) $row['config_json'], true );
		return [
			'id'       => (int) $row['id'],
			'version'  => (string) $row['version'],
			'checksum' => (string) $row['checksum'],
			'config'   => is_array( $config ) ? $config : [],
		];
	}
}
