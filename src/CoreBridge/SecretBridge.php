<?php
/**
 * Liebherr Interface Solutions – Secret Bridge
 *
 * Verschlüsselung händlerbezogener Zugangsdaten (API-Keys je Händler, ADR-LIW-SALES-001 Stufe 1d) über den
 * Core-`EncryptionService` (AES-256-GCM, Master Key nur aus Env `ARALIYA_ENCRYPTION_MASTER_KEY`, ADR-031/ADR-135).
 * Fail closed: ohne Master Key wird nie Klartext gespeichert – `seal()` liefert '' und der Aufrufer verweigert das
 * Speichern. Werte erscheinen nie in Logs/Audit/Board.
 *
 * @package Liebherr\InterfaceWorld\CoreBridge
 * @since   0.1.0-alpha.148
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\CoreBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SecretBridge {

	private const SERVICE = 'Araliya\\Platform\\Core\\Core\\EncryptionService';

	public static function is_available(): bool {
		return class_exists( self::SERVICE ) && (bool) ( self::SERVICE )::is_available();
	}

	/** Klartext → Ciphertext-Envelope; '' wenn Verschlüsselung nicht verfügbar (dann nicht speichern!). */
	public static function seal( string $plaintext ): string {
		if ( '' === $plaintext || ! self::is_available() ) {
			return '';
		}
		return (string) ( self::SERVICE )::encrypt_blob( $plaintext );
	}

	/** Ciphertext → Klartext; '' bei fehlendem Key, Manipulation oder leerem Wert. */
	public static function open( string $ciphertext ): string {
		if ( '' === $ciphertext || ! self::is_available() ) {
			return '';
		}
		return (string) ( self::SERVICE )::decrypt_blob( $ciphertext );
	}
}
