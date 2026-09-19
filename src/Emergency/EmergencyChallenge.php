<?php
/**
 * Liebherr Emergency – Zugangs-Rechenaufgabe für den Hilfe-Koffer.
 *
 * Bewusst NIEDRIGSCHWELLIG: zwei EINSTELLIGE Zahlen (1–9) statt der zweistelligen Addition an den großen
 * Welten-Gates – der Koffer ist eine Hilfe/Notfall-Schwelle, kein Schutzwall. Die Aufgabe wird serverseitig
 * signiert (HMAC), hat eine kurze Gültigkeit (TTL) und ist über die Nonce einmal einlösbar (Einmalgebrauch
 * erzwingt die Laufzeitschicht per Transient). Die Summe verlässt den Server nie – der Client bekommt nur die
 * beiden Summanden und das signierte Token. Reine, ohne WordPress testbare Logik.
 *
 * Kandidat zur späteren Zusammenführung mit dem einheitlichen ChallengeService (siehe ADR-LIW-CVF-001 §5) –
 * bis dahin eigenständig, damit keine Abhängigkeit zum noch nicht gebauten Customer-View-Flow entsteht.
 *
 * @package Liebherr\InterfaceWorld\Emergency
 * @since   0.1.0-alpha.78
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Emergency;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class EmergencyChallenge {

	/** Standard-Gültigkeitsdauer einer Aufgabe in Sekunden. */
	public const DEFAULT_TTL = 300;

	/** Kleinste/größte einstellige Zahl (0 und 1 vermeiden, damit die Aufgabe eine echte Addition bleibt). */
	private const MIN = 1;
	private const MAX = 9;

	/**
	 * Erzeugt eine neue Aufgabe.
	 *
	 * @return array{a:int,b:int,token:string,expires:int}
	 */
	public static function create( string $secret, int $now, int $ttl = self::DEFAULT_TTL ): array {
		$a     = random_int( self::MIN, self::MAX );
		$b     = random_int( self::MIN, self::MAX );
		$exp   = $now + max( 30, $ttl );
		$nonce = bin2hex( random_bytes( 8 ) );
		return [
			'a'       => $a,
			'b'       => $b,
			'token'   => self::make_token( $a, $b, $exp, $nonce, $secret ),
			'expires' => $exp,
		];
	}

	/** Barrierefreie Aufgabenfrage (reiner Text, ohne Lösung). */
	public static function question( int $a, int $b ): string {
		return $a . ' + ' . $b . ' = ?';
	}

	/**
	 * Baut ein signiertes Token: base64url(payload) . "." . hmac. Payload enthält Summanden, Ablauf, Nonce.
	 */
	public static function make_token( int $a, int $b, int $exp, string $nonce, string $secret ): string {
		$payload = $a . '.' . $b . '.' . $exp . '.' . $nonce;
		$enc     = self::b64url_encode( $payload );
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
		$payload = self::b64url_decode( $enc );
		$parts   = explode( '.', $payload );
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
