<?php
/**
 * Liebherr World – CAPDB Repository: Entwurf/Publish/Rollback + Board-CRUD (ADR-LIW-CVF-002, §29/§30).
 *
 * Versionsmodell: eine `liw_cvf_workflow_version` ist Entwurf (state 'draft') oder unveränderlich
 * veröffentlicht (state 'published' + Prüfsumme). Alle Board-Zeilen (Bereiche, Kanten, Plugin-Instanzen,
 * Schedules, Layout) hängen an einer version_id. Publish schnappt den Entwurf ein; Rollback erzeugt einen
 * neuen Entwurf aus einer alten Version und veröffentlicht ihn (Verlauf bleibt, konsistent mit §10/§29).
 *
 * @package Liebherr\InterfaceWorld\Cvf
 * @since   0.1.0-alpha.89
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Cvf;

use Liebherr\InterfaceWorld\Frontend\WorldSwitcher;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BoardRepository {

	// ── Versionslebenszyklus ─────────────────────────────────────────────────
	/** Liefert die aktuelle Entwurfs-Version (oder erzeugt eine aus der letzten veröffentlichten). */
	public static function ensure_draft( int $user_id = 0 ): int {
		$id = self::draft_id();
		if ( $id > 0 ) {
			return $id;
		}
		return self::create_draft( $user_id );
	}

	public static function draft_id(): int {
		global $wpdb;
		$t = Schema::version_table();
		return (int) $wpdb->get_var( "SELECT id FROM {$t} WHERE state = 'draft' ORDER BY id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB
	}

	public static function published_id(): int {
		global $wpdb;
		$t = Schema::version_table();
		return (int) $wpdb->get_var( "SELECT id FROM {$t} WHERE state = 'published' ORDER BY id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB
	}

	/** Erzeugt einen neuen Entwurf: kopiert die Board-Zeilen der letzten Version oder seedet die Startkonfig. */
	public static function create_draft( int $user_id = 0 ): int {
		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s' );
		$wpdb->insert( Schema::version_table(), [ // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'version'        => 'draft-' . time(),
			'schema_version' => WorkflowVersion::SCHEMA_VERSION,
			'state'          => 'draft',
			'config_json'    => (string) wp_json_encode( WorkflowVersion::default_config() ),
			'checksum'       => '',
			'published_by'   => $user_id > 0 ? $user_id : null,
			'created_at'     => $now,
		], [ '%s', '%d', '%s', '%s', '%s', '%d', '%s' ] );
		$draft = (int) $wpdb->insert_id;

		$src = self::published_id();
		if ( $src > 0 ) {
			self::copy_board( $src, $draft );
		}
		// Leerer Entwurf (keine Vorgängerversion ODER Altversion im flachen Phase-2-Format ohne Board-Zeilen):
		// mit der Startkonfiguration seeden, damit das Board nie leer startet.
		if ( 0 === count( self::areas( $draft ) ) ) {
			self::seed_start_config( $draft );
		}
		return $draft;
	}

	/** Kopiert Bereiche, Kanten, Instanzen, Schedules und Layout einer Version in eine andere. */
	public static function copy_board( int $from_version, int $to_version ): void {
		$area_map = [];
		foreach ( self::areas( $from_version ) as $a ) {
			$area_map[ (int) $a['id'] ] = self::add_area( $to_version, (string) $a['module_id'], (int) $a['position'], (string) ( $a['route_id'] ?? '' ), (string) $a['status'], (string) ( $a['validity_json'] ?? '' ) );
		}
		$inst_hosts = [];
		foreach ( self::edges( $from_version ) as $e ) {
			$new_from = $area_map[ (int) $e['from_area_id'] ] ?? 0;
			$new_to   = $area_map[ (int) $e['to_area_id'] ] ?? 0;
			$new_edge = self::add_edge( $to_version, $new_from, $new_to, (string) $e['trigger_type'], (string) ( $e['condition_json'] ?? '' ), (int) $e['priority'] );
			$inst_hosts[ 'edge:' . (int) $e['id'] ] = [ 'type' => 'edge', 'id' => $new_edge ];
		}
		foreach ( $area_map as $old => $new ) {
			$inst_hosts[ 'page:' . $old ] = [ 'type' => 'page', 'id' => $new ];
		}
		foreach ( self::instances( $from_version ) as $ins ) {
			$key  = $ins['host_type'] . ':' . (int) $ins['host_id'];
			$host = $inst_hosts[ $key ] ?? null;
			if ( null === $host ) { continue; }
			$new_ins = self::add_instance( $to_version, (int) $ins['plugin_type_id'], (string) $host['type'], (int) $host['id'], (string) $ins['status'], (int) $ins['priority'], (string) ( $ins['config_json'] ?? '' ) );
			$sched = self::schedule( (int) $ins['id'] );
			if ( null !== $sched ) {
				unset( $sched['id'] );
				$sched['instance_id'] = $new_ins;
				self::set_schedule( $new_ins, $sched );
			}
		}
	}

	// ── Startkonfiguration (§24.1/§28): vier Bereiche + Übergänge ──────────────
	public static function seed_start_config( int $version_id ): void {
		$worlds = WorldSwitcher::worlds();
		$order  = [ 'intelligence_world', 'local_intelligence', 'interface_solutions', 'adventures' ];
		$area   = [];
		$pos    = 1;
		foreach ( $order as $key ) {
			$route          = isset( $worlds[ $key ]['url'] ) ? (string) $worlds[ $key ]['url'] : '';
			$area[ $key ]   = self::add_area( $version_id, $key, $pos, $route, 'active', '' );
			$pos++;
		}
		// Erweiterung auf sechs Bereichskarten (Pflichtenheft My Liebherr §36): die neuen Karten my_liebherr und
		// pocket_information werden additiv angelegt, aber INITIAL DEAKTIVIERT (status=inactive) und ohne Übergänge –
		// so bleiben bestehende Viererflows unverändert und keine Modulauflösung/Runtime wird beeinflusst.
		foreach ( [ 'my_liebherr' => (int) get_option( 'liw_my_liebherr_page_id', 0 ), 'pocket_information' => 0 ] as $key => $page_id ) {
			$route        = ( $page_id > 0 && 'publish' === get_post_status( $page_id ) ) ? (string) get_permalink( $page_id ) : '';
			$area[ $key ] = self::add_area( $version_id, $key, $pos, $route, 'inactive', '' );
			$pos++;
		}
		// Übergänge: Intelligence World → die drei Fachmodule (Modulauswahl nach WORLD_GRANTED) mit Plugin-Kette
		// (§28): zweistellige Addition am Übergang; First-Entry-Text auf der Modulseite. Nur wenn die Typen
		// bereits registriert sind (PluginRegistry::sync lief) – sonst reine Bereiche/Kanten.
		$type_challenge = PluginRegistry::type_id( 'challenge_addition' );
		$type_first     = PluginRegistry::type_id( 'first_entry_text' );
		foreach ( [ 'local_intelligence', 'interface_solutions', 'adventures' ] as $mod ) {
			if ( ! isset( $area['intelligence_world'], $area[ $mod ] ) ) {
				continue;
			}
			$edge = self::add_edge( $version_id, $area['intelligence_world'], $area[ $mod ], 'world_granted', '', 100 );
			if ( $type_challenge > 0 ) {
				self::add_instance( $version_id, $type_challenge, PluginTaxonomy::SCOPE_EDGE, $edge, 'configured', 100, (string) wp_json_encode( [ 'difficulty' => 'double' ] ) );
			}
			if ( $type_first > 0 ) {
				self::add_instance( $version_id, $type_first, PluginTaxonomy::SCOPE_PAGE, $area[ $mod ], 'configured', 100, (string) wp_json_encode( [ 'title' => '', 'body' => '' ] ) );
			}
		}
	}

	// ── CRUD Bereiche ──────────────────────────────────────────────────────────
	public static function add_area( int $version_id, string $module_id, int $position, string $route_id, string $status = 'active', string $validity_json = '' ): int {
		global $wpdb;
		$wpdb->insert( BoardSchema::area_table(), [ // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'version_id' => $version_id, 'module_id' => $module_id, 'position' => $position,
			'route_id' => '' !== $route_id ? $route_id : null, 'status' => $status,
			'validity_json' => '' !== $validity_json ? $validity_json : null,
		], [ '%d', '%s', '%d', '%s', '%s', '%s' ] );
		return (int) $wpdb->insert_id;
	}

	/** @return array<int,array<string,mixed>> */
	public static function areas( int $version_id ): array {
		global $wpdb;
		$t = BoardSchema::area_table();
		$r = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE version_id = %d ORDER BY position ASC, id ASC", $version_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $r ) ? $r : [];
	}

	public static function delete_area( int $version_id, int $area_id ): void {
		global $wpdb;
		$wpdb->delete( BoardSchema::area_table(), [ 'id' => $area_id, 'version_id' => $version_id ], [ '%d', '%d' ] ); // phpcs:ignore WordPress.DB
	}

	// ── CRUD Kanten ──────────────────────────────────────────────────────────
	public static function add_edge( int $version_id, int $from, int $to, string $trigger = 'manual', string $condition_json = '', int $priority = 100 ): int {
		global $wpdb;
		$wpdb->insert( BoardSchema::edge_table(), [ // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'version_id' => $version_id, 'from_area_id' => $from, 'to_area_id' => $to,
			'trigger_type' => $trigger, 'condition_json' => '' !== $condition_json ? $condition_json : null, 'priority' => $priority,
		], [ '%d', '%d', '%d', '%s', '%s', '%d' ] );
		return (int) $wpdb->insert_id;
	}

	/** @return array<int,array<string,mixed>> */
	public static function edges( int $version_id ): array {
		global $wpdb;
		$t = BoardSchema::edge_table();
		$r = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE version_id = %d ORDER BY priority ASC, id ASC", $version_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $r ) ? $r : [];
	}

	public static function delete_edge( int $version_id, int $edge_id ): void {
		global $wpdb;
		$wpdb->delete( BoardSchema::edge_table(), [ 'id' => $edge_id, 'version_id' => $version_id ], [ '%d', '%d' ] ); // phpcs:ignore WordPress.DB
	}

	// ── CRUD Plugin-Instanzen + Schedule ───────────────────────────────────────
	public static function add_instance( int $version_id, int $type_id, string $host_type, int $host_id, string $status = 'configured', int $priority = 100, string $config_json = '' ): int {
		global $wpdb;
		$wpdb->insert( BoardSchema::plugin_instance_table(), [ // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			'version_id' => $version_id, 'plugin_type_id' => $type_id, 'host_type' => $host_type, 'host_id' => $host_id,
			'status' => $status, 'priority' => $priority, 'config_json' => '' !== $config_json ? $config_json : null,
		], [ '%d', '%d', '%s', '%d', '%s', '%d', '%s' ] );
		return (int) $wpdb->insert_id;
	}

	/**
	 * @param array{host_type?:string,host_id?:int} $filter
	 * @return array<int,array<string,mixed>>
	 */
	public static function instances( int $version_id, array $filter = [] ): array {
		global $wpdb;
		$t = BoardSchema::plugin_instance_table();
		if ( isset( $filter['host_type'], $filter['host_id'] ) ) {
			$r = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE version_id = %d AND host_type = %s AND host_id = %d ORDER BY priority ASC, id ASC", $version_id, (string) $filter['host_type'], (int) $filter['host_id'] ), ARRAY_A ); // phpcs:ignore WordPress.DB
		} else {
			$r = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE version_id = %d ORDER BY priority ASC, id ASC", $version_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		}
		return is_array( $r ) ? $r : [];
	}

	/** Aktualisiert Konfiguration (und optional Status) einer Instanz im gegebenen Entwurf. */
	public static function update_instance( int $version_id, int $instance_id, string $config_json, ?string $status = null ): void {
		global $wpdb;
		$data   = [ 'config_json' => '' !== $config_json ? $config_json : null ];
		$format = [ '%s' ];
		if ( null !== $status && '' !== $status ) {
			$data['status'] = $status;
			$format[]       = '%s';
		}
		$wpdb->update( BoardSchema::plugin_instance_table(), $data, [ 'id' => $instance_id, 'version_id' => $version_id ], $format, [ '%d', '%d' ] ); // phpcs:ignore WordPress.DB
	}

	public static function delete_instance( int $version_id, int $instance_id ): void {
		global $wpdb;
		$wpdb->delete( BoardSchema::plugin_schedule_table(), [ 'instance_id' => $instance_id ], [ '%d' ] ); // phpcs:ignore WordPress.DB
		$wpdb->delete( BoardSchema::plugin_instance_table(), [ 'id' => $instance_id, 'version_id' => $version_id ], [ '%d', '%d' ] ); // phpcs:ignore WordPress.DB
	}

	/** @param array<string,mixed> $data */
	public static function set_schedule( int $instance_id, array $data ): void {
		global $wpdb;
		$t   = BoardSchema::plugin_schedule_table();
		$row = [
			'instance_id'     => $instance_id,
			'time_origin'     => (string) ( $data['time_origin'] ?? 'page.entered' ),
			'open_at_ms'      => isset( $data['open_at_ms'] ) ? (int) $data['open_at_ms'] : null,
			'close_at_ms'     => isset( $data['close_at_ms'] ) ? (int) $data['close_at_ms'] : null,
			'duration_ms'     => isset( $data['duration_ms'] ) ? (int) $data['duration_ms'] : null,
			'minimum_open_ms' => isset( $data['minimum_open_ms'] ) ? (int) $data['minimum_open_ms'] : null,
			'timeout_ms'      => isset( $data['timeout_ms'] ) ? (int) $data['timeout_ms'] : null,
			'repeat_policy'   => (string) ( $data['repeat_policy'] ?? 'once_per_version' ),
			'resume_policy'   => (string) ( $data['resume_policy'] ?? 'continue' ),
			'cancel_on'       => isset( $data['cancel_on'] ) && '' !== $data['cancel_on'] ? (string) $data['cancel_on'] : null,
		];
		$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE instance_id = %d", $instance_id ) ); // phpcs:ignore WordPress.DB
		if ( $exists > 0 ) {
			$wpdb->update( $t, $row, [ 'instance_id' => $instance_id ] ); // phpcs:ignore WordPress.DB
		} else {
			$wpdb->insert( $t, $row ); // phpcs:ignore WordPress.DB
		}
	}

	/** @return array<string,mixed>|null */
	public static function schedule( int $instance_id ): ?array {
		global $wpdb;
		$t = BoardSchema::plugin_schedule_table();
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE instance_id = %d", $instance_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return $r ?: null;
	}

	// ── Layout ─────────────────────────────────────────────────────────────────
	/** @param array<string,mixed> $positions */
	public static function save_layout( int $version_id, string $viewport, array $positions, int $zoom, int $user_id ): void {
		global $wpdb;
		$t   = BoardSchema::layout_table();
		$row = [ 'version_id' => $version_id, 'viewport' => $viewport, 'node_positions_json' => (string) wp_json_encode( $positions ), 'zoom' => $zoom, 'updated_by' => $user_id > 0 ? $user_id : null, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ];
		$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE version_id = %d AND viewport = %s", $version_id, $viewport ) ); // phpcs:ignore WordPress.DB
		if ( $exists > 0 ) {
			$wpdb->update( $t, $row, [ 'id' => $exists ] ); // phpcs:ignore WordPress.DB
		} else {
			$wpdb->insert( $t, $row ); // phpcs:ignore WordPress.DB
		}
	}

	// ── Prüfsumme + Publish/Rollback ────────────────────────────────────────────
	/** Kanonische, reihenfolgestabile Prüfsumme über die Board-Struktur einer Version. */
	public static function board_checksum( int $version_id ): string {
		$snap = [
			'areas'     => array_map( static fn( $a ) => [ $a['module_id'], (int) $a['position'], (string) ( $a['route_id'] ?? '' ), $a['status'] ], self::areas( $version_id ) ),
			'edges'     => array_map( static fn( $e ) => [ (int) $e['from_area_id'], (int) $e['to_area_id'], $e['trigger_type'], (int) $e['priority'] ], self::edges( $version_id ) ),
			'instances' => array_map( static fn( $i ) => [ (int) $i['plugin_type_id'], $i['host_type'], (int) $i['host_id'], $i['status'], (int) $i['priority'], (string) ( $i['config_json'] ?? '' ) ], self::instances( $version_id ) ),
		];
		return WorkflowVersion::checksum( $snap );
	}

	/**
	 * Veröffentlicht einen Entwurf unveränderlich (Prüfsumme + Zeitstempel). Vier-Augen wie WorkflowRepository.
	 *
	 * @return array{ok:bool,reason:string,version:int,checksum:string}
	 */
	public static function publish_draft( int $draft_id, int $user_id, bool $four_eyes ): array {
		global $wpdb;
		$t = Schema::version_table();
		$state = (string) $wpdb->get_var( $wpdb->prepare( "SELECT state FROM {$t} WHERE id = %d", $draft_id ) ); // phpcs:ignore WordPress.DB
		if ( 'draft' !== $state ) {
			return [ 'ok' => false, 'reason' => 'not_a_draft', 'version' => 0, 'checksum' => '' ];
		}
		$problems = BoardValidator::validate( $draft_id );
		if ( count( $problems ) > 0 ) {
			return [ 'ok' => false, 'reason' => 'invalid:' . implode( ',', $problems ), 'version' => 0, 'checksum' => '' ];
		}
		if ( $four_eyes && $user_id > 0 && WorkflowRepository::last_published_by() === $user_id ) {
			return [ 'ok' => false, 'reason' => 'four_eyes_same_person', 'version' => 0, 'checksum' => '' ];
		}
		$checksum = self::board_checksum( $draft_id );
		$version  = WorkflowRepository::next_version();
		$wpdb->update( $t, [ // phpcs:ignore WordPress.DB
			'version'      => $version,
			'state'        => 'published',
			'checksum'     => $checksum,
			'published_at' => gmdate( 'Y-m-d H:i:s' ),
			'published_by' => $user_id > 0 ? $user_id : null,
		], [ 'id' => $draft_id ] );
		return [ 'ok' => true, 'reason' => 'ok', 'version' => $draft_id, 'checksum' => $checksum ];
	}

	/**
	 * Rollback: neuen Entwurf aus einer veröffentlichten Version erzeugen und sofort veröffentlichen.
	 *
	 * @return array{ok:bool,reason:string,version:int,checksum:string}
	 */
	public static function rollback_to( int $version_id, int $user_id, bool $four_eyes ): array {
		global $wpdb;
		$t = Schema::version_table();
		$state = (string) $wpdb->get_var( $wpdb->prepare( "SELECT state FROM {$t} WHERE id = %d", $version_id ) ); // phpcs:ignore WordPress.DB
		if ( 'published' !== $state ) {
			return [ 'ok' => false, 'reason' => 'not_published', 'version' => 0, 'checksum' => '' ];
		}
		// Bestehenden Entwurf verwerfen, damit die Kopie sauber ist.
		$existing = self::draft_id();
		if ( $existing > 0 ) {
			self::discard_draft( $existing );
		}
		$draft = self::create_draft( $user_id ); // erzeugt Entwurf aus der ZULETZT veröffentlichten – nicht der Zielversion
		// Board des Entwurfs leeren und aus der Zielversion kopieren.
		self::clear_board( $draft );
		self::copy_board( $version_id, $draft );
		return self::publish_draft( $draft, $user_id, $four_eyes );
	}

	public static function discard_draft( int $draft_id ): void {
		self::clear_board( $draft_id );
		global $wpdb;
		$wpdb->delete( Schema::version_table(), [ 'id' => $draft_id, 'state' => 'draft' ], [ '%d', '%s' ] ); // phpcs:ignore WordPress.DB
	}

	public static function clear_board( int $version_id ): void {
		global $wpdb;
		foreach ( self::instances( $version_id ) as $ins ) {
			$wpdb->delete( BoardSchema::plugin_schedule_table(), [ 'instance_id' => (int) $ins['id'] ], [ '%d' ] ); // phpcs:ignore WordPress.DB
		}
		$wpdb->delete( BoardSchema::plugin_instance_table(), [ 'version_id' => $version_id ], [ '%d' ] ); // phpcs:ignore WordPress.DB
		$wpdb->delete( BoardSchema::edge_table(), [ 'version_id' => $version_id ], [ '%d' ] ); // phpcs:ignore WordPress.DB
		$wpdb->delete( BoardSchema::area_table(), [ 'version_id' => $version_id ], [ '%d' ] ); // phpcs:ignore WordPress.DB
	}
}
