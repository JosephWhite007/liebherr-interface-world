<?php
/**
 * Liebherr Emergency – Zugangs-Rechenaufgabe für den Hilfe-Koffer.
 *
 * Bewusst NIEDRIGSCHWELLIG: zwei EINSTELLIGE Zahlen (1–9) statt der zweistelligen Addition an den großen
 * Welten-Gates – der Koffer ist eine Hilfe/Notfall-Schwelle, kein Schutzwall.
 *
 * Seit alpha.81 ein dünner Adapter auf den einheitlichen {@see \Liebherr\InterfaceWorld\Cvf\ChallengeService}
 * (ADR-LIW-CVF-001 §5): die Signier-/TTL-/Nonce-Logik lebt dort an EINER Stelle (keine Redundanz), hier nur
 * die Festlegung „Schwierigkeit = single". Öffentliche API unverändert. Einmalgebrauch erzwingt die
 * Laufzeitschicht per Transient.
 *
 * @package Liebherr\InterfaceWorld\Emergency
 * @since   0.1.0-alpha.78
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Emergency;

use Liebherr\InterfaceWorld\Cvf\ChallengeService;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class EmergencyChallenge {

	/** Standard-Gültigkeitsdauer einer Aufgabe in Sekunden. */
	public const DEFAULT_TTL = ChallengeService::DEFAULT_TTL;

	/** Schwierigkeit des Koffers: zwei einstellige Zahlen. */
	private const DIFFICULTY = 'single';

	/**
	 * Erzeugt eine neue Aufgabe (einstellig).
	 *
	 * @return array{a:int,b:int,token:string,expires:int}
	 */
	public static function create( string $secret, int $now, int $ttl = self::DEFAULT_TTL ): array {
		$c = ChallengeService::create( $secret, $now, self::DIFFICULTY, $ttl );
		return [
			'a'       => $c['a'],
			'b'       => $c['b'],
			'token'   => $c['token'],
			'expires' => $c['expires'],
		];
	}

	/** Barrierefreie Aufgabenfrage (reiner Text, ohne Lösung). */
	public static function question( int $a, int $b ): string {
		return ChallengeService::question( $a, $b );
	}

	public static function make_token( int $a, int $b, int $exp, string $nonce, string $secret ): string {
		return ChallengeService::make_token( $a, $b, $exp, $nonce, $secret );
	}

	/**
	 * Prüft Token + Antwort.
	 *
	 * @return array{ok:bool,reason:string,nonce:string}
	 */
	public static function verify( string $token, int $answer, string $secret, int $now ): array {
		return ChallengeService::verify( $token, $answer, $secret, $now );
	}

	/**
	 * Zerlegt und verifiziert ein Token signaturgeprüft.
	 *
	 * @return array{a:int,b:int,exp:int,nonce:string}|null
	 */
	public static function parse( string $token, string $secret ): ?array {
		return ChallengeService::parse( $token, $secret );
	}
}
