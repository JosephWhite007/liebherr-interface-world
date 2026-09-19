<?php
/**
 * Liebherr World – CAPDB Plugin-Registry (Pflichtenheft §25/§26).
 *
 * Zentrale, im Code gepflegte Bibliothek der Plugin-Typen der Startversion. Jeder Typ hat eine stabile
 * key-ID, Kategorie, erlaubte Zielzonen (Scopes), ein validiertes Parameterschema und eine Capability-Klasse.
 * Es gibt KEINEN frei ausführbaren Code im Board (§26): die Typen sind fest, nur ihre Instanzen/Parameter
 * werden administriert. `sync()` schreibt die Definitionen idempotent in `liw_cvf_plugin_type`.
 *
 * Die Definitionen selbst (`definitions()`, `validate_config()`) sind rein/ohne WordPress testbar.
 *
 * @package Liebherr\InterfaceWorld\Cvf
 * @since   0.1.0-alpha.90
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Cvf;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class PluginRegistry {

	/**
	 * Sieben registrierte Typen der Startversion – je einer pro Kategorie (JW-Empfehlung §9.3 des Plans).
	 *
	 * @return array<string,array{category:string,scopes:string,capability:string,label:string,schema:array<string,mixed>}>
	 */
	public static function definitions(): array {
		return [
			'access_code' => [
				'category'   => PluginTaxonomy::CAT_SECURITY,
				'scopes'     => 'edge',
				'capability' => 'security',
				'label'      => 'Codeprüfung',
				'schema'     => [ 'min_length' => [ 'type' => 'int', 'default' => 4, 'min' => 1, 'max' => 64 ] ],
			],
			'challenge_addition' => [
				'category'   => PluginTaxonomy::CAT_SECURITY,
				'scopes'     => 'edge,page',
				'capability' => 'security',
				'label'      => 'Zweistellige Addition',
				'schema'     => [ 'difficulty' => [ 'type' => 'enum', 'values' => [ 'single', 'double' ], 'default' => 'double' ] ],
			],
			'first_entry_text' => [
				'category'   => PluginTaxonomy::CAT_INFORMATION,
				'scopes'     => 'page',
				'capability' => 'display',
				'label'      => 'First-Entry-Text',
				'schema'     => [ 'title' => [ 'type' => 'text', 'default' => '' ], 'body' => [ 'type' => 'text', 'default' => '' ] ],
			],
			'open_module' => [
				'category'   => PluginTaxonomy::CAT_NAVIGATION,
				'scopes'     => 'edge,page',
				'capability' => 'navigation',
				'label'      => 'Modul öffnen',
				'schema'     => [ 'label' => [ 'type' => 'text', 'default' => 'Eintreten' ] ],
			],
			'set_grant' => [
				'category'   => PluginTaxonomy::CAT_STATUS,
				'scopes'     => 'edge',
				'capability' => 'status',
				'label'      => 'Grant setzen',
				'schema'     => [ 'grant_key' => [ 'type' => 'text', 'default' => 'MODULE_GRANTED' ] ],
			],
			'countdown' => [
				'category'   => PluginTaxonomy::CAT_TIMING,
				'scopes'     => 'edge,page',
				'capability' => 'display',
				'label'      => 'Countdown',
				'schema'     => [ 'seconds' => [ 'type' => 'int', 'default' => 5, 'min' => 1, 'max' => 600 ] ],
			],
			'measurement' => [
				'category'   => PluginTaxonomy::CAT_MEASUREMENT,
				'scopes'     => 'edge,page',
				'capability' => 'measurement',
				'label'      => 'Ablaufmessung',
				'schema'     => [ 'event_class' => [ 'type' => 'text', 'default' => 'flow' ] ],
			],
			'confirm' => [
				'category'   => PluginTaxonomy::CAT_INTERACTION,
				'scopes'     => 'page',
				'capability' => 'display',
				'label'      => 'Bestätigung',
				'schema'     => [ 'label' => [ 'type' => 'text', 'default' => 'Ich bestätige' ] ],
			],
		];
	}

	/**
	 * Validiert eine Instanz-Konfiguration gegen das Parameterschema des Typs (rein).
	 *
	 * @param array<string,mixed> $config
	 * @return array<int,string> Problem-Schlüssel (leer = gültig).
	 */
	public static function validate_config( string $key, array $config ): array {
		$defs = self::definitions();
		if ( ! isset( $defs[ $key ] ) ) {
			return [ 'unknown_type' ];
		}
		$problems = [];
		foreach ( $defs[ $key ]['schema'] as $field => $spec ) {
			if ( ! isset( $config[ $field ] ) ) {
				continue; // fehlend → Default greift.
			}
			$val  = $config[ $field ];
			$type = (string) ( $spec['type'] ?? 'text' );
			if ( 'enum' === $type && ! in_array( (string) $val, (array) ( $spec['values'] ?? [] ), true ) ) {
				$problems[] = 'bad_enum_' . $field;
			} elseif ( 'int' === $type ) {
				if ( ! is_numeric( $val ) ) {
					$problems[] = 'not_int_' . $field;
				} else {
					$n = (int) $val;
					if ( isset( $spec['min'] ) && $n < (int) $spec['min'] ) { $problems[] = 'below_min_' . $field; }
					if ( isset( $spec['max'] ) && $n > (int) $spec['max'] ) { $problems[] = 'above_max_' . $field; }
				}
			}
		}
		return $problems;
	}

	/** Füllt eine Konfiguration mit den Schema-Defaults auf (rein). @param array<string,mixed> $config @return array<string,mixed> */
	public static function with_defaults( string $key, array $config ): array {
		$defs = self::definitions();
		if ( ! isset( $defs[ $key ] ) ) {
			return $config;
		}
		foreach ( $defs[ $key ]['schema'] as $field => $spec ) {
			if ( ! isset( $config[ $field ] ) && array_key_exists( 'default', $spec ) ) {
				$config[ $field ] = $spec['default'];
			}
		}
		return $config;
	}

	// ── WP-gebundene Registry (Tabelle plugin_type) ────────────────────────────
	/** Schreibt die Definitionen idempotent in die Tabelle (Upsert nach plugin_key). */
	public static function sync(): void {
		global $wpdb;
		$t = BoardSchema::plugin_type_table();
		foreach ( self::definitions() as $key => $def ) {
			$row = [
				'plugin_key'            => $key,
				'category'              => (string) $def['category'],
				'manifest_version'      => 1,
				'allowed_scopes'        => (string) $def['scopes'],
				'parameter_schema_json' => (string) wp_json_encode( $def['schema'] ),
				'capability_class'      => (string) $def['capability'],
			];
			$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE plugin_key = %s", $key ) ); // phpcs:ignore WordPress.DB
			if ( $id > 0 ) {
				$wpdb->update( $t, $row, [ 'id' => $id ] ); // phpcs:ignore WordPress.DB
			} else {
				$wpdb->insert( $t, $row ); // phpcs:ignore WordPress.DB
			}
		}
	}

	public static function type_id( string $key ): int {
		global $wpdb;
		$t = BoardSchema::plugin_type_table();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE plugin_key = %s", $key ) ); // phpcs:ignore WordPress.DB
	}

	/** @return array<int,array<string,mixed>> */
	public static function all_types(): array {
		global $wpdb;
		$t = BoardSchema::plugin_type_table();
		$r = $wpdb->get_results( "SELECT * FROM {$t} ORDER BY category, plugin_key", ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $r ) ? $r : [];
	}
}
