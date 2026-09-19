<?php
/**
 * Liebherr World – Pocket Information: Feed-Zusammenführung (Pflichtenheft My Liebherr §34, ADR-LIW-MYL-001 R5).
 *
 * Führt gespeicherte Pocket-Items ({@see PocketRepository}) und abgeleitete, regelbasierte Items
 * ({@see PocketRules}) zu einem priorisierten Feed zusammen (Alerts high/critical zuerst). Die reine Sortier-
 * /Merge-Logik {@see merge()} ist ohne WordPress testbar. Sicherheitsmeldungen (kritisch/hoch) dürfen nicht durch
 * Empfehlungen verdrängt werden – daher zuerst nach Priorität, dann gespeicherte vor abgeleiteten (§34).
 *
 * @package Liebherr\InterfaceWorld\Pocket
 * @since   0.1.0-alpha.128
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Pocket;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FeedService {

	private const ORDER = [ 'critical' => 0, 'high' => 1, 'normal' => 2, 'low' => 3 ];

	/**
	 * Vollständiger Feed eines Nutzers: gespeicherte + (falls aktiv) abgeleitete Items, priorisiert.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function feed( int $user_id ): array {
		return self::merge( PocketRepository::feed( $user_id ), PocketRules::derive( $user_id ) );
	}

	/**
	 * Reine Merge-/Sortierlogik: nach Priorität (critical→low), innerhalb gleicher Priorität gespeicherte zuerst,
	 * danach abgeleitete. Stabil (behält die jeweilige Eingangsreihenfolge).
	 *
	 * @param array<int,array<string,mixed>> $stored
	 * @param array<int,array<string,mixed>> $derived
	 * @return array<int,array<string,mixed>>
	 */
	public static function merge( array $stored, array $derived ): array {
		$rows = [];
		$seq  = 0;
		foreach ( $stored as $it ) {
			$it['derived'] = false;
			$rows[]        = [ 'sort' => [ self::rank( (string) ( $it['priority'] ?? 'normal' ) ), 0, $seq++ ], 'item' => $it ];
		}
		foreach ( $derived as $it ) {
			$it['derived'] = true;
			$rows[]        = [ 'sort' => [ self::rank( (string) ( $it['priority'] ?? 'normal' ) ), 1, $seq++ ], 'item' => $it ];
		}
		usort( $rows, static function ( $a, $b ) {
			return $a['sort'] <=> $b['sort'];
		} );
		return array_map( static fn( $r ) => $r['item'], $rows );
	}

	private static function rank( string $priority ): int {
		return self::ORDER[ $priority ] ?? 2;
	}
}
