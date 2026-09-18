<?php
/**
 * Liebherr Adventures – Klassifikation (Grundlagenkonzept §3).
 *
 * Zwei getrennte, verbindliche Achsen: Inhaltstyp (13, „worum geht es?") und Dringlichkeit/Tonalität
 * (4, „wie ist damit umzugehen?"). Codes sind stabil; Anzeigenamen via `__()` (administrierbar/übersetzbar,
 * §3.2). „funny/serious/critical" sind KEINE Themen, sondern Dringlichkeitsstufen. Rein, ohne WP-DB (testbar).
 *
 * @package Liebherr\InterfaceWorld\Adventures
 * @since   0.1.0-alpha.51
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Adventures;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Taxonomy {

	/** @return array<string,string> Code → Anzeigename (Inhaltstypen §3.2). */
	public static function content_types(): array {
		return [
			'work_advice'          => __( 'Work Advice', 'liebherr-interface-world' ),
			'tool_advice'          => __( 'Tool Advice', 'liebherr-interface-world' ),
			'service_help'         => __( 'Liebherr Help', 'liebherr-interface-world' ),
			'fault_observation'    => __( 'Fault Observation', 'liebherr-interface-world' ),
			'technical_detail'     => __( 'Technical Detail', 'liebherr-interface-world' ),
			'field_experience'     => __( 'Field Experience', 'liebherr-interface-world' ),
			'festival'             => __( 'Festivals', 'liebherr-interface-world' ),
			'come_together'        => __( 'Come Together', 'liebherr-interface-world' ),
			'common_aims'          => __( 'Common Aims', 'liebherr-interface-world' ),
			'liebherr_world'       => __( 'Liebherr World', 'liebherr-interface-world' ),
			'liebherr_families'    => __( 'Liebherr Families', 'liebherr-interface-world' ),
			'liebherr_connections' => __( 'Liebherr Connections', 'liebherr-interface-world' ),
			'liebherr_foundation'  => __( 'Liebherr Foundation', 'liebherr-interface-world' ),
		];
	}

	/** @return array<string,string> Code → Anzeigename (Dringlichkeit/Tonalität §3.3). */
	public static function urgency_levels(): array {
		return [
			'funny'       => __( 'Funny', 'liebherr-interface-world' ),
			'informative' => __( 'Informative', 'liebherr-interface-world' ),
			'serious'     => __( 'Serious', 'liebherr-interface-world' ),
			'critical'    => __( 'Critical', 'liebherr-interface-world' ),
		];
	}

	public static function is_valid_type( string $code ): bool {
		return array_key_exists( $code, self::content_types() );
	}

	public static function is_valid_urgency( string $code ): bool {
		return array_key_exists( $code, self::urgency_levels() );
	}

	/** Kritisch → nie ungeprüft öffentlich (§3.3/§9.3). */
	public static function is_critical( string $urgency ): bool {
		return 'critical' === $urgency;
	}
}
