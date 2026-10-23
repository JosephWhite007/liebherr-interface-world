<?php
/**
 * Liebherr Interface Solutions – Instance Bridge
 *
 * Kopplung an den Installer-Vertrag des Core (ADR-134): (1) deklariert die instanzspezifischen Env-Keys des
 * Liebherr-Adapters im `EnvironmentManifest` (Manifest-Pflicht, Klon-Policy „neu eingeben"), (2) fragt vor jedem
 * ausgehenden Aufruf `InstanceState::may_send()` – ein geklonter/unkommissionierter Stand sendet nie an Liebherr.
 * Ohne Core: Manifest entfällt, Senden gilt als nicht erlaubt (fail closed).
 *
 * @package Liebherr\InterfaceWorld\CoreBridge
 * @since   0.1.0-alpha.147
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\CoreBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class InstanceBridge {

	/** Adapter-Schlüssel für Kill-Switch und Verbindungstests im Core. */
	public const ADAPTER = 'liebherr-livision';

	public const ENV_KEY_TEST   = 'LIW_LIVISION_KEY_TEST';
	public const ENV_USER_EMAIL = 'LIW_LIVISION_USER_EMAIL';

	private const STATE    = 'Araliya\\Platform\\Core\\Core\\Environment\\InstanceState';
	private const MANIFEST = 'Araliya\\Platform\\Core\\Core\\Environment\\EnvironmentManifest';
	private const FIELD    = 'Araliya\\Platform\\Core\\Core\\Environment\\FieldDefinition';

	public static function register(): void {
		if ( class_exists( self::MANIFEST ) ) {
			add_filter( ( self::MANIFEST )::FILTER, [ self::class, 'declare_keys' ] );
			// Core lädt vor diesem Plugin und hat das Manifest ggf. schon zwischengespeichert – Cache verwerfen.
			( self::MANIFEST )::reset();
		}
	}

	/**
	 * @param array<string,object> $defs
	 * @return array<string,object>
	 */
	public static function declare_keys( array $defs ): array {
		if ( ! class_exists( self::FIELD ) ) {
			return $defs;
		}
		$F     = self::FIELD;
		$specs = [
			[ 'key' => self::ENV_KEY_TEST, 'label' => 'Liebherr Dealer Interface: Subscription-Key Test (Env)', 'type' => $F::TYPE_SECRET ],
			[ 'key' => self::ENV_USER_EMAIL, 'label' => 'Liebherr Dealer Interface: UserEmail-Header (Env)', 'type' => $F::TYPE_EMAIL, 'sensitivity' => $F::SENS_INTERNAL ],
		];
		foreach ( $specs as $spec ) {
			$defs[ $spec['key'] ] = new $F( $spec + [
				'group'        => 'liebherr',
				'source'       => $F::SOURCE_ENV,
				'clone_policy' => $F::CLONE_REENTER,
				'adapter'      => self::ADAPTER,
				'required'     => false,
			] );
		}
		return $defs;
	}

	/** Darf dieser Stand nach außen senden (Not-Aus, Kill-Switch, Klon-Zustand)? Ohne Core: nein. */
	public static function may_send(): bool {
		return class_exists( self::STATE ) && (bool) ( self::STATE )::may_send( self::ADAPTER );
	}

	/** Status eines Env-Werts ohne Offenlegung. */
	public static function env_set( string $name ): bool {
		$v = getenv( $name );
		return is_string( $v ) && '' !== trim( $v );
	}
}
