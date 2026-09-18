<?php
/**
 * Liebherr Adventures – Ortsdienst-Resolver + Partner-Attribution (§4).
 *
 * Löst den aktiven Drei-Wörter-Provider auf (Prototyp: Mock; austauschbar über Filter
 * `liw_adv_three_word_provider` – kein hart verdrahteter Anbieter). Kapselt encode/decode und die
 * administrierbare, zurückhaltende Partnerdarstellung „Location powered by …" (§4.2).
 *
 * @package Liebherr\InterfaceWorld\Adventures\Location
 * @since   0.1.0-alpha.51
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Adventures\Location;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class LocationService {

	public static function provider(): ProviderInterface {
		$provider = apply_filters( 'liw_adv_three_word_provider', new MockProvider() );
		return $provider instanceof ProviderInterface ? $provider : new MockProvider();
	}

	/** @return array{words:string,lat:float,lng:float,accuracy:string,provider:string,provider_version:string,resolved_at:string} */
	public static function encode( float $lat, float $lng ): array {
		$p   = self::provider();
		$loc = $p->encode( $lat, $lng );
		return array_merge( $loc, [
			'provider'         => $p->name(),
			'provider_version' => $p->version(),
			'resolved_at'      => gmdate( 'Y-m-d H:i:s' ),
		] );
	}

	/** @return array{lat:float,lng:float}|null */
	public static function decode( string $words ): ?array {
		return self::provider()->decode( $words );
	}

	/** Normalisierte Ortswörter (klein, Punkt-getrennt) oder '' bei ungültig. */
	public static function normalize_words( string $words ): string {
		$parts = array_filter( array_map( static fn( $w ) => preg_replace( '/[^a-z]/', '', strtolower( trim( $w ) ) ) ?? '', explode( '.', $words ) ) );
		return 3 === count( $parts ) ? implode( '.', $parts ) : '';
	}

	/**
	 * Partner-Attribution (administrierbar, §4.2). Prototyp-Standard verweist auf den Mock; echte Marke/
	 * Logo/Link erst nach Lizenz-/Freigabe-Gate (§4.3) über Option `liw_adv_location_partner`.
	 *
	 * @return array{name:string,logo_id:int,link:string,note:string}
	 */
	public static function partner(): array {
		$stored = get_option( 'liw_adv_location_partner', [] );
		$def    = [
			'name'    => self::provider()->name(),
			'logo_id' => 0,
			'link'    => '',
			'note'    => __( 'Prototyp – Beispiel-Ortsdienst; echter Anbieter folgt nach Freigabe.', 'liebherr-interface-world' ),
		];
		return is_array( $stored ) ? array_merge( $def, $stored ) : $def;
	}

	/** Anzeigetext „Location powered by …". */
	public static function attribution_text(): string {
		return sprintf(
			/* translators: %s = Ortsdienst-Anbietername */
			__( 'Location powered by %s', 'liebherr-interface-world' ),
			self::partner()['name']
		);
	}
}
