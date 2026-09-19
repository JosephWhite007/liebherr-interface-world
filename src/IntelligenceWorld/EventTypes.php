<?php
/**
 * Liebherr Intelligence World – Ereignistypen (Pflichtenheft-2 §11).
 *
 * Reine Konstantenliste; keine WP-Abhängigkeit (unit-testbar). Der EventLog akzeptiert nur bekannte Typen.
 *
 * @package Liebherr\InterfaceWorld\IntelligenceWorld
 * @since   0.1.0-alpha.47
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\IntelligenceWorld;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class EventTypes {

	public const ACCESS_CODE_VERIFIED       = 'access_code_verified';
	public const TERMS_PRESENTED            = 'terms_presented';
	public const TERMS_ACCEPTED             = 'terms_accepted';
	public const STORAGE_BUDGET_GRANTED     = 'storage_budget_granted';
	public const STORAGE_EXTENSION_REQUESTED = 'storage_extension_requested';
	public const STORAGE_EXTENSION_DECIDED  = 'storage_extension_decided';
	public const SESSION_STARTED            = 'session_started';
	public const SESSION_HEARTBEAT          = 'session_heartbeat';
	public const SESSION_PAUSED             = 'session_paused';
	public const SESSION_RESUMED            = 'session_resumed';
	public const MODULE_STARTED             = 'module_started';
	public const MODULE_STOPPED             = 'module_stopped';
	public const DATA_SOURCE_ACCESSED       = 'data_source_accessed';
	public const QUERY_EXECUTED             = 'query_executed';
	public const SIMULATION_CONFIGURED      = 'simulation_configured';
	public const COST_ESTIMATE_CONFIRMED    = 'cost_estimate_confirmed';
	public const COMPUTE_JOB_STARTED        = 'compute_job_started';
	public const COMPUTE_JOB_COMPLETED      = 'compute_job_completed';
	public const COMPUTE_JOB_FAILED         = 'compute_job_failed';
	public const RESULT_EXPORTED            = 'result_exported';
	public const SESSION_ENDED              = 'session_ended';
	public const PROTOCOL_GENERATED         = 'protocol_generated';
	public const BILLING_ADJUSTMENT_CREATED = 'billing_adjustment_created';

	/** @return string[] Alle gültigen Ereignistypen. */
	public static function all(): array {
		return [
			self::ACCESS_CODE_VERIFIED,
			self::TERMS_PRESENTED,
			self::TERMS_ACCEPTED,
			self::STORAGE_BUDGET_GRANTED,
			self::STORAGE_EXTENSION_REQUESTED,
			self::STORAGE_EXTENSION_DECIDED,
			self::SESSION_STARTED,
			self::SESSION_HEARTBEAT,
			self::SESSION_PAUSED,
			self::SESSION_RESUMED,
			self::MODULE_STARTED,
			self::MODULE_STOPPED,
			self::DATA_SOURCE_ACCESSED,
			self::QUERY_EXECUTED,
			self::SIMULATION_CONFIGURED,
			self::COST_ESTIMATE_CONFIRMED,
			self::COMPUTE_JOB_STARTED,
			self::COMPUTE_JOB_COMPLETED,
			self::COMPUTE_JOB_FAILED,
			self::RESULT_EXPORTED,
			self::SESSION_ENDED,
			self::PROTOCOL_GENERATED,
			self::BILLING_ADJUSTMENT_CREATED,
		];
	}

	public static function is_valid( string $type ): bool {
		return in_array( $type, self::all(), true );
	}

	/** Menschlich lesbare Bezeichnung eines Ereignistyps (für das Nutzungsprotokoll, §8). */
	public static function label( string $type ): string {
		$labels = [
			self::ACCESS_CODE_VERIFIED        => __( 'Bestätigungscode geprüft', 'liebherr-interface-world' ),
			self::TERMS_PRESENTED             => __( 'Nutzungsbedingungen angezeigt', 'liebherr-interface-world' ),
			self::TERMS_ACCEPTED              => __( 'Nutzungsbedingungen akzeptiert', 'liebherr-interface-world' ),
			self::STORAGE_BUDGET_GRANTED      => __( 'Speicher-Grundbudget gewährt', 'liebherr-interface-world' ),
			self::STORAGE_EXTENSION_REQUESTED => __( 'Speichererweiterung angefragt', 'liebherr-interface-world' ),
			self::STORAGE_EXTENSION_DECIDED   => __( 'Speichererweiterung entschieden', 'liebherr-interface-world' ),
			self::SESSION_STARTED             => __( 'Sitzung gestartet', 'liebherr-interface-world' ),
			self::SESSION_HEARTBEAT           => __( 'Aktivitätssignal', 'liebherr-interface-world' ),
			self::SESSION_PAUSED              => __( 'Sitzung pausiert', 'liebherr-interface-world' ),
			self::SESSION_RESUMED             => __( 'Sitzung fortgesetzt', 'liebherr-interface-world' ),
			self::MODULE_STARTED              => __( 'Modul gestartet', 'liebherr-interface-world' ),
			self::MODULE_STOPPED              => __( 'Modul beendet', 'liebherr-interface-world' ),
			self::DATA_SOURCE_ACCESSED        => __( 'Datenquelle genutzt', 'liebherr-interface-world' ),
			self::QUERY_EXECUTED              => __( 'Abfrage ausgeführt', 'liebherr-interface-world' ),
			self::SIMULATION_CONFIGURED       => __( 'Simulation konfiguriert', 'liebherr-interface-world' ),
			self::COST_ESTIMATE_CONFIRMED     => __( 'Kostenschätzung bestätigt', 'liebherr-interface-world' ),
			self::COMPUTE_JOB_STARTED         => __( 'Rechenauftrag gestartet', 'liebherr-interface-world' ),
			self::COMPUTE_JOB_COMPLETED       => __( 'Rechenauftrag abgeschlossen', 'liebherr-interface-world' ),
			self::COMPUTE_JOB_FAILED          => __( 'Rechenauftrag fehlgeschlagen', 'liebherr-interface-world' ),
			self::RESULT_EXPORTED             => __( 'Ergebnis exportiert', 'liebherr-interface-world' ),
			self::SESSION_ENDED               => __( 'Sitzung beendet', 'liebherr-interface-world' ),
			self::PROTOCOL_GENERATED          => __( 'Protokoll erstellt', 'liebherr-interface-world' ),
			self::BILLING_ADJUSTMENT_CREATED  => __( 'Abrechnungskorrektur erstellt', 'liebherr-interface-world' ),
		];
		return $labels[ $type ] ?? $type;
	}
}
