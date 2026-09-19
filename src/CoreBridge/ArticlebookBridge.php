<?php
/**
 * Liebherr Interface Solutions – CoreBridge zum Artikelbook (Adventures-Basislogik).
 *
 * Adventure-Beiträge werden auf der Plattform „Artikelbook" registriert und verlinkt. Die eigentliche
 * Artikelbook-Anbindung lebt in araliya-platform-core und ist eine eigene Integrationsentscheidung; dieses
 * Bridge ist die EINZIGE Naht dafür: Es feuert den Filter `liw_articlebook_register` (Core/Site kann eine
 * echte Artikel-Referenz + URL liefern). Liefert kein Hook eine Referenz, wird eine lokale, deterministische
 * Registrier-Referenz erzeugt, damit die Basislogik eigenständig vollständig funktioniert; die echte
 * Verknüpfung kann später nachgezogen werden (kein direkter Core-Aufruf → keine harte Kopplung).
 *
 * @package Liebherr\InterfaceWorld\CoreBridge
 * @since   0.1.0-alpha.59
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\CoreBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ArticlebookBridge {

	/**
	 * Registriert einen Beitrag im Artikelbook (oder lokal als Fallback) und liefert Referenz + Link.
	 *
	 * @param array<string,mixed> $contribution id, uuid, title, type, category, token_value, version, author_ref.
	 * @return array{ref:string,url:string,source:string} source = 'articlebook' | 'local'
	 */
	public static function register( array $contribution ): array {
		/**
		 * Ermöglicht dem Core/der Site, den Beitrag echt im Artikelbook zu registrieren.
		 * Erwartete Rückgabe: array{ ref:string, url:string } – sonst null.
		 *
		 * @param null                $result       Standard: nicht registriert (null).
		 * @param array<string,mixed> $contribution Beitragsdaten.
		 */
		$hooked = apply_filters( 'liw_articlebook_register', null, $contribution );

		if ( is_array( $hooked ) && ! empty( $hooked['ref'] ) ) {
			return [
				'ref'    => (string) $hooked['ref'],
				'url'    => isset( $hooked['url'] ) ? esc_url_raw( (string) $hooked['url'] ) : '',
				'source' => 'articlebook',
			];
		}

		// Fallback: lokale, deterministische Registrier-Referenz (revisionssicher nachvollziehbar).
		$uuid = (string) ( $contribution['uuid'] ?? '' );
		$seed = '' !== $uuid ? $uuid : (string) ( $contribution['id'] ?? '0' );
		$ref  = 'LIW-ADV-' . strtoupper( substr( md5( $seed ), 0, 12 ) );

		return [ 'ref' => $ref, 'url' => '', 'source' => 'local' ];
	}

	/** Link zum Artikelbook-Eintrag (gespeicherte URL oder per Filter aufgelöst). */
	public static function link_url( string $ref, string $stored_url = '' ): string {
		$url = '' !== $stored_url ? $stored_url : '';
		/**
		 * Erlaubt dem Core/der Site, aus einer Artikelbook-Referenz eine Ziel-URL abzuleiten.
		 *
		 * @param string $url Aktuelle URL (ggf. leer).
		 * @param string $ref Artikelbook-Referenz.
		 */
		return (string) apply_filters( 'liw_articlebook_url', $url, $ref );
	}
}
