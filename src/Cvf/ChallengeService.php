<?php
/**
 * Liebherr World – Customer View Flow: einheitlicher ChallengeService (ADR-LIW-CVF-001 §5).
 *
 * EINE Quelle für die „Human Verification"-Rechenaufgabe der gesamten Plattform: signiert (HMAC), mit
 * kurzer Gültigkeit (TTL) und über die Nonce einmal einlösbar; die Summe verlässt den Server nie. Die
 * Schwierigkeit ist konfigurierbar:
 *   - `single` → zwei EINSTELLIGE Zahlen (1–9)   – niedrigschwellig, z. B. Hilfe-Koffer/Emergency.
 *   - `double` → zwei ZWEISTELLIGE Zahlen (10–99) – die großen Welten-Gates.
 *
 * Reine, ohne WordPress testbare Logik. Der Einmalgebrauch (Nonce-Sperre) wird von der jeweiligen
 * Laufzeitschicht (Transient) erzwungen. Ziel (ADR §5): die heute doppelten Rechen-Gates (LI-IntroOverlay
 * und das Download-Consent-Plugin) ziehen schrittweise auf diesen Service um, danach entfällt der Altpfad.
 *
 * @package Liebherr\InterfaceWorld\Cvf
 * @since   0.1.0-alpha.81
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Cvf;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ChallengeService {

	/** Standard-Gültigkeitsdauer einer Aufgabe in Sekunden. */
	public const DEFAULT_TTL = 300;

	/** Schwierigkeitsstufen → [min, max] je Summand. */
	public const DIFFICULTIES = [
		'single' => [ 1, 9 ],
		'double' => [ 10, 99 ],
	];

	public const DEFAULT_DIFFICULTY = 'single';

	/** Normalisiert eine Stufe und liefert deren [min, max]. */
	public static function bounds( string $difficulty ): array {
		return self::DIFFICULTIES[ self::normalize( $difficulty ) ];
	}

	public static function normalize( string $difficulty ): string {
		return isset( self::DIFFICULTIES[ $difficulty ] ) ? $difficulty : self::DEFAULT_DIFFICULTY;
	}

	/**
	 * Erzeugt eine neue Aufgabe.
	 *
	 * @return array{a:int,b:int,difficulty:string,token:string,expires:int}
	 */
	public static function create( string $secret, int $now, string $difficulty = self::DEFAULT_DIFFICULTY, int $ttl = self::DEFAULT_TTL ): array {
		$difficulty     = self::normalize( $difficulty );
		[ $min, $max ]  = self::DIFFICULTIES[ $difficulty ];
		$a              = random_int( $min, $max );
		$b              = random_int( $min, $max );
		$exp            = $now + max( 30, $ttl );
		$nonce          = bin2hex( random_bytes( 8 ) );
		return [
			'a'          => $a,
			'b'          => $b,
			'difficulty' => $difficulty,
			'token'      => self::make_token( $a, $b, $exp, $nonce, $secret ),
			'expires'    => $exp,
		];
	}

	/** Barrierefreie Aufgabenfrage (reiner Text, ohne Lösung). */
	public static function question( int $a, int $b ): string {
		return $a . ' + ' . $b . ' = ?';
	}

	/** Baut ein signiertes Token: base64url(payload) . "." . hmac. */
	public static function make_token( int $a, int $b, int $exp, string $nonce, string $secret ): string {
		$enc = self::b64url_encode( $a . '.' . $b . '.' . $exp . '.' . $nonce );
		return $enc . '.' . self::sign( $enc, $secret );
	}

	/**
	 * Prüft Token + Antwort.
	 *
	 * @return array{ok:bool,reason:string,nonce:string}
	 */
	public static function verify( string $token, int $answer, string $secret, int $now ): array {
		$parsed = self::parse( $token, $secret );
		if ( null === $parsed ) {
			return [ 'ok' => false, 'reason' => 'invalid', 'nonce' => '' ];
		}
		if ( $now > $parsed['exp'] ) {
			return [ 'ok' => false, 'reason' => 'expired', 'nonce' => $parsed['nonce'] ];
		}
		if ( $answer !== ( $parsed['a'] + $parsed['b'] ) ) {
			return [ 'ok' => false, 'reason' => 'wrong', 'nonce' => $parsed['nonce'] ];
		}
		return [ 'ok' => true, 'reason' => 'ok', 'nonce' => $parsed['nonce'] ];
	}

	/**
	 * Zerlegt und verifiziert ein Token signaturgeprüft.
	 *
	 * @return array{a:int,b:int,exp:int,nonce:string}|null
	 */
	public static function parse( string $token, string $secret ): ?array {
		$dot = strrpos( $token, '.' );
		if ( false === $dot || 0 === $dot ) {
			return null;
		}
		$enc = substr( $token, 0, $dot );
		$sig = substr( $token, $dot + 1 );
		if ( ! hash_equals( self::sign( $enc, $secret ), $sig ) ) {
			return null;
		}
		$parts = explode( '.', self::b64url_decode( $enc ) );
		if ( 4 !== count( $parts ) ) {
			return null;
		}
		return [
			'a'     => (int) $parts[0],
			'b'     => (int) $parts[1],
			'exp'   => (int) $parts[2],
			'nonce' => (string) $parts[3],
		];
	}

	private static function sign( string $data, string $secret ): string {
		return hash_hmac( 'sha256', $data, $secret );
	}

	private static function b64url_encode( string $raw ): string {
		return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );
	}

	private static function b64url_decode( string $enc ): string {
		return (string) base64_decode( strtr( $enc, '-_', '+/' ), true );
	}
}
