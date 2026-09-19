<?php
/**
 * Liebherr World – Customer View Flow: versionierte Workflow-Config (ADR-LIW-CVF-001 §10/§12).
 *
 * Der Ablauf des Durchstichs ist DATEN, nicht Code: eine Liste von Stufen ({@see StageType}). Eine
 * veröffentlichte Version ist unveränderlich und trägt eine Prüfsumme (checksum) über ihre kanonische Form.
 * Reine, ohne WordPress testbare Logik: Default-Config, kanonische Serialisierung, Prüfsumme, Validierung.
 * Persistenz/Publish (Tabelle liw_cvf_workflow_version) folgt in einer eigenen Etappe.
 *
 * @package Liebherr\InterfaceWorld\Cvf
 * @since   0.1.0-alpha.82
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Cvf;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class WorkflowVersion {

	public const SCHEMA_VERSION = 1;

	/**
	 * Default-Durchstich: Eingang → Challenge (zweistellig) → Modulauswahl → First-Entry.
	 *
	 * @return array{schema_version:int,stages:array<int,array<string,mixed>>}
	 */
	public static function default_config(): array {
		return [
			'schema_version' => self::SCHEMA_VERSION,
			'stages'         => [
				[ 'key' => 'entry',         'type' => StageType::ENTRY ],
				[ 'key' => 'challenge',     'type' => StageType::CHALLENGE, 'difficulty' => 'double' ],
				[ 'key' => 'module_select', 'type' => StageType::MODULE_SELECT ],
				[ 'key' => 'first_entry',   'type' => StageType::FIRST_ENTRY ],
			],
		];
	}

	/**
	 * @param array<string,mixed> $config
	 * @return array<int,array<string,mixed>>
	 */
	public static function stages( array $config ): array {
		$stages = $config['stages'] ?? [];
		return is_array( $stages ) ? array_values( $stages ) : [];
	}

	/**
	 * Prüft eine Config und liefert die Liste der Probleme (leer = gültig).
	 *
	 * @param array<string,mixed> $config
	 * @return array<int,string>
	 */
	public static function validate( array $config ): array {
		$problems = [];
		$stages   = self::stages( $config );
		if ( 0 === count( $stages ) ) {
			$problems[] = 'no_stages';
			return $problems;
		}
		$keys  = [];
		$types = [];
		foreach ( $stages as $i => $stage ) {
			$key  = isset( $stage['key'] ) ? (string) $stage['key'] : '';
			$type = isset( $stage['type'] ) ? (string) $stage['type'] : '';
			if ( '' === $key ) {
				$problems[] = 'stage_' . $i . '_missing_key';
			} elseif ( in_array( $key, $keys, true ) ) {
				$problems[] = 'duplicate_key_' . $key;
			} else {
				$keys[] = $key;
			}
			if ( ! StageType::is_valid( $type ) ) {
				$problems[] = 'stage_' . $i . '_invalid_type';
			} else {
				$types[] = $type;
			}
		}
		if ( ! isset( $stages[0]['type'] ) || StageType::ENTRY !== $stages[0]['type'] ) {
			$problems[] = 'first_stage_not_entry';
		}
		if ( ! in_array( StageType::CHALLENGE, $types, true ) ) {
			$problems[] = 'missing_challenge';
		}
		return $problems;
	}

	/** @param array<string,mixed> $config */
	public static function is_valid( array $config ): bool {
		return 0 === count( self::validate( $config ) );
	}

	/**
	 * Kanonische Serialisierung (Schlüssel rekursiv sortiert) – Grundlage der Prüfsumme.
	 *
	 * @param mixed $value
	 */
	public static function canonical( $value ): string {
		if ( is_array( $value ) ) {
			$is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );
			if ( $is_list ) {
				$parts = array_map( [ self::class, 'canonical' ], $value );
				return '[' . implode( ',', $parts ) . ']';
			}
			ksort( $value );
			$parts = [];
			foreach ( $value as $k => $v ) {
				$parts[] = json_encode( (string) $k ) . ':' . self::canonical( $v );
			}
			return '{' . implode( ',', $parts ) . '}';
		}
		return (string) json_encode( $value );
	}

	/** @param array<string,mixed> $config */
	public static function checksum( array $config ): string {
		return hash( 'sha256', self::canonical( $config ) );
	}

	/**
	 * Setzt die Schwierigkeit der Challenge-Stufe (rein). Unbekannte Stufe fällt auf den ChallengeService-
	 * Default zurück. Liefert eine NEUE Config (Original unverändert).
	 *
	 * @param array<string,mixed> $config
	 * @return array<string,mixed>
	 */
	public static function set_challenge_difficulty( array $config, string $difficulty ): array {
		$difficulty = ChallengeService::normalize( $difficulty );
		$stages     = self::stages( $config );
		foreach ( $stages as $i => $stage ) {
			if ( isset( $stage['type'] ) && StageType::CHALLENGE === $stage['type'] ) {
				$stages[ $i ]['difficulty'] = $difficulty;
			}
		}
		$config['stages'] = $stages;
		return $config;
	}
}
