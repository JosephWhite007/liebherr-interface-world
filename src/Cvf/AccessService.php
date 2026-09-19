<?php
/**
 * Liebherr World – Customer View Flow: gehärteter Zugangscode (ADR-LIW-CVF-001 §5.1, JW-Entscheid §9.2).
 *
 * Der Zugangscode wird NICHT im Klartext gespeichert, sondern als HMAC-SHA256 gegen ein serverseitiges
 * Secret (Option liw_cvf_secret). Prüfung zeitkonstant (hash_equals). Rate-Limit/Lockout pro Drossel-
 * schlüssel (z. B. Besucher/IP) über Transients. Aktiv nur hinter dem Flag {@see Flags::enabled()} – der
 * bestehende IW-Prototyp-Eintritt (Klartext-Demo-Code) bleibt Default, bis die CVF-Runtime scharfgeschaltet
 * wird. Reine, ohne WordPress testbare Kernlogik (normalize/hash_code/verify) + WP-gebundene Verwaltung.
 *
 * @package Liebherr\InterfaceWorld\Cvf
 * @since   0.1.0-alpha.84
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Cvf;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AccessService {

	private const OPT_SECRET = 'liw_cvf_secret';
	private const OPT_HASH   = 'liw_cvf_access_hash';

	public const MAX_ATTEMPTS = 5;
	public const LOCK_TTL     = 300; // Sekunden Sperre nach zu vielen Fehlversuchen.

	// ── Reine Kernlogik (WP-frei testbar) ────────────────────────────────────
	/** Normalisiert einen Code (Groß-/Kleinschreibung + Randleerzeichen egal). */
	public static function normalize( string $code ): string {
		return strtoupper( trim( $code ) );
	}

	/** HMAC-SHA256 des normalisierten Codes gegen das Secret. */
	public static function hash_code( string $code, string $secret ): string {
		return hash_hmac( 'sha256', self::normalize( $code ), $secret );
	}

	/** Zeitkonstante Prüfung einer Eingabe gegen einen gespeicherten Hash. */
	public static function verify( string $input, string $stored_hash, string $secret ): bool {
		if ( '' === $stored_hash ) {
			return false;
		}
		return hash_equals( $stored_hash, self::hash_code( $input, $secret ) );
	}

	// ── WP-gebundene Verwaltung ───────────────────────────────────────────────
	public static function secret(): string {
		$s = (string) get_option( self::OPT_SECRET, '' );
		if ( '' === $s ) {
			$s = bin2hex( random_bytes( 32 ) );
			update_option( self::OPT_SECRET, $s, false );
		}
		return $s;
	}

	public static function get_hash(): string {
		return (string) get_option( self::OPT_HASH, '' );
	}

	public static function is_configured(): bool {
		return '' !== self::get_hash();
	}

	/** Legt den Zugangscode fest (speichert nur den Hash, nie den Klartext). */
	public static function set_code( string $code ): void {
		update_option( self::OPT_HASH, self::hash_code( $code, self::secret() ), false );
	}

	// ── Rate-Limit / Lockout ──────────────────────────────────────────────────
	private static function lock_key( string $throttle_key ): string {
		return 'liw_cvf_lock_' . md5( $throttle_key );
	}

	public static function is_locked( string $throttle_key ): bool {
		return (int) get_transient( self::lock_key( $throttle_key ) ) >= self::MAX_ATTEMPTS;
	}

	private static function register_failure( string $throttle_key ): int {
		$key = self::lock_key( $throttle_key );
		$n   = (int) get_transient( $key ) + 1;
		set_transient( $key, $n, self::LOCK_TTL );
		return $n;
	}

	public static function clear( string $throttle_key ): void {
		delete_transient( self::lock_key( $throttle_key ) );
	}

	/**
	 * Prüft eine Code-Eingabe mit Lockout.
	 *
	 * @return array{ok:bool,reason:string,remaining:int}
	 */
	public static function attempt( string $input, string $throttle_key ): array {
		if ( self::is_locked( $throttle_key ) ) {
			return [ 'ok' => false, 'reason' => 'locked', 'remaining' => 0 ];
		}
		if ( ! self::is_configured() ) {
			return [ 'ok' => false, 'reason' => 'not_configured', 'remaining' => self::MAX_ATTEMPTS ];
		}
		if ( self::verify( $input, self::get_hash(), self::secret() ) ) {
			self::clear( $throttle_key );
			return [ 'ok' => true, 'reason' => 'ok', 'remaining' => self::MAX_ATTEMPTS ];
		}
		$n         = self::register_failure( $throttle_key );
		$remaining = max( 0, self::MAX_ATTEMPTS - $n );
		return [ 'ok' => false, 'reason' => ( 0 === $remaining ? 'locked' : 'wrong' ), 'remaining' => $remaining ];
	}
}
