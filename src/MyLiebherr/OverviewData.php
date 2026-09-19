<?php
/**
 * Liebherr World – My Liebherr: Aggregation der Overview-Widgets (Pflichtenheft My Liebherr §5/§30, ADR-LIW-MYL-001).
 *
 * Liefert die echten Inhalte der drei Kacheln „Was ist neu", „Was muss ich tun", „Gebuchte Leistung" aus den
 * bestehenden Quellen (Kontakte/Leistungen, Pocket, geteilte Bilder, Wallet) – keine neue Wahrheitsquelle. Rein
 * lesend, eigentümerbezogen (§35/SEC 01). Cross-Modul-Aufrufe sind class_exists-guarded (robust ohne Teilmodule).
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.129
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

use Liebherr\InterfaceWorld\CoreBridge\WalletBridge;
use Liebherr\InterfaceWorld\Pocket\PocketRepository;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class OverviewData {

	/**
	 * Kürzt eine Liste auf max. Einträge und hängt einen „+N weitere"-Hinweis an. Rein (ohne WordPress).
	 *
	 * @param array<int,array{label:string,url:string}> $items
	 * @return array<int,array{label:string,url:string}>
	 */
	public static function truncate( array $items, int $max = 4 ): array {
		if ( count( $items ) <= $max ) {
			return array_values( $items );
		}
		$rest = count( $items ) - $max;
		$out  = array_slice( array_values( $items ), 0, $max );
		$out[] = [ 'label' => sprintf( /* translators: %d: remaining count */ '+%d …', $rest ), 'url' => '' ];
		return $out;
	}

	/** Offene Aufgaben: Kontaktanfragen, zu bestätigende Leistungen, unquittierte Pflicht-Pocket-Infos. @return array<int,array{label:string,url:string}> */
	public static function tasks( int $user_id ): array {
		$home = self::home();
		$out  = [];
		if ( class_exists( ContactRepository::class ) ) {
			foreach ( ContactRepository::requests_for( $user_id ) as $r ) {
				if ( (int) $r['recipient_id'] === $user_id && 'requested' === $r['status'] ) {
					$out[] = [ 'label' => __( 'Kontaktanfrage beantworten', 'liebherr-interface-world' ) . ': ' . (string) $r['purpose'], 'url' => $home . '#liw-my-contacts' ];
				}
			}
			foreach ( ContactRepository::connections_for( $user_id ) as $c ) {
				foreach ( ContactRepository::services_for( (int) $c['id'] ) as $s ) {
					if ( 'proposed' === $s['status'] && (int) $s['receiver_id'] === $user_id ) {
						$out[] = [ 'label' => __( 'Leistung bestätigen', 'liebherr-interface-world' ) . ': ' . (string) $s['description'], 'url' => $home . '#liw-my-contacts' ];
					}
				}
			}
		}
		if ( class_exists( PocketRepository::class ) ) {
			foreach ( PocketRepository::feed( $user_id ) as $p ) {
				if ( 1 === (int) $p['requires_ack'] && null === $p['acknowledged_at'] ) {
					$out[] = [ 'label' => __( 'Quittieren', 'liebherr-interface-world' ) . ': ' . (string) $p['title'], 'url' => self::pocket() ];
				}
			}
		}
		return $out;
	}

	/** Neues: mit mir geteilte Bilder + hohe/kritische Pocket-Infos. @return array<int,array{label:string,url:string}> */
	public static function updates( int $user_id ): array {
		$home = self::home();
		$out  = [];
		if ( class_exists( ShareRepository::class ) && class_exists( GalleryRepository::class ) ) {
			foreach ( ShareRepository::incoming_for_user( $user_id ) as $s ) {
				if ( 'gallery' !== $s['item_type'] ) {
					continue;
				}
				$item  = GalleryRepository::get_any( (int) $s['item_id'] );
				$title = null !== $item ? (string) $item['title'] : ( '#' . (int) $s['item_id'] );
				$out[] = [ 'label' => __( 'Mit dir geteilt', 'liebherr-interface-world' ) . ': ' . $title, 'url' => $home . '#liw-shared-colleagues' ];
			}
		}
		if ( class_exists( PocketRepository::class ) ) {
			foreach ( PocketRepository::feed( $user_id ) as $p ) {
				if ( in_array( (string) $p['priority'], [ 'high', 'critical' ], true ) ) {
					$out[] = [ 'label' => (string) $p['title'], 'url' => self::pocket() ];
				}
			}
		}
		return $out;
	}

	/** Gebuchte Leistung: letzte Wallet-Buchungen. @return array<int,array{label:string,url:string}> */
	public static function bookings( int $user_id ): array {
		$out = [];
		foreach ( WalletBridge::transactions( $user_id, 4 ) as $r ) {
			$amount = (int) ( is_object( $r ) ? ( $r->amount_cents ?? 0 ) : ( $r['amount_cents'] ?? 0 ) );
			$type   = (string) ( is_object( $r ) ? ( $r->source_type ?? '' ) : ( $r['source_type'] ?? '' ) );
			$sign   = $amount >= 0 ? '+' : '−';
			$out[]  = [ 'label' => WalletBridge::source_label( $type ) . ': ' . $sign . ' ' . WalletBridge::format_cents( abs( $amount ) ), 'url' => '#liw-my-wallet' ];
		}
		return $out;
	}

	private static function home(): string {
		$id = (int) get_option( 'liw_my_liebherr_page_id', 0 );
		return ( $id > 0 && 'publish' === get_post_status( $id ) ) ? (string) get_permalink( $id ) : '';
	}

	private static function pocket(): string {
		$id = (int) get_option( 'liw_pocket_page_id', 0 );
		return ( $id > 0 && 'publish' === get_post_status( $id ) ) ? (string) get_permalink( $id ) : self::home();
	}
}
