<?php
/**
 * Liebherr World – Pocket Information: regelbasierte Personalisierung (Pflichtenheft My Liebherr §34, ADR-LIW-MYL-001 R5).
 *
 * Erzeugt aus den EIGENEN Daten der Person abgeleitete, ERKLÄRBARE Pocket-Infos (Briefing, Machine Pocket,
 * Adventure Pocket, Pocket Tasks). Grundsatz §34: keine neue Wahrheitsquelle – abgeleitete Items werden NICHT
 * persistiert, verweisen per Rücksprung auf ihr führendes Modul und tragen eine Begründung („weshalb angezeigt").
 * Rein lesend; kein Schreibzugriff. Über {@see Flags::rules_enabled()} abschaltbar.
 *
 * @package Liebherr\InterfaceWorld\Pocket
 * @since   0.1.0-alpha.128
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Pocket;

use Liebherr\InterfaceWorld\MyLiebherr\ContactRepository;
use Liebherr\InterfaceWorld\MyLiebherr\MachineRepository;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class PocketRules {

	/**
	 * Abgeleitete Pocket-Items für einen Nutzer (virtuell, nicht persistiert).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function derive( int $user_id ): array {
		if ( $user_id <= 0 || ! Flags::rules_enabled() ) {
			return [];
		}
		$home     = self::route();
		$machines = class_exists( MachineRepository::class ) ? MachineRepository::for_user( $user_id ) : [];
		$tasks    = self::contact_tasks( $user_id );
		$adv      = self::own_published_adventures( $user_id );
		$items    = [];

		// My Briefing – Tageszusammenfassung (immer oben, normal).
		$items[] = self::item(
			'auto-briefing',
			__( 'My Briefing', 'liebherr-interface-world' ),
			__( 'My Briefing', 'liebherr-interface-world' ),
			sprintf(
				/* translators: 1: machine count 2: task count 3: adventure count */
				__( '%1$d Maschinen · %2$d offene Aufgaben · %3$d eigene Adventures.', 'liebherr-interface-world' ),
				count( $machines ),
				count( $tasks ),
				$adv
			),
			'normal',
			$home,
			__( 'Automatisch aus deinem Konto zusammengefasst.', 'liebherr-interface-world' )
		);

		// Pocket Tasks – offene Kontaktanfragen / zu bestätigende Leistungen (hoch).
		foreach ( $tasks as $t ) {
			$items[] = $t;
		}

		// Machine Pocket – Schnellzugriff je Maschine (normal).
		foreach ( $machines as $m ) {
			$items[] = self::item(
				'auto-machine-' . (int) $m['id'],
				__( 'Machine Pocket', 'liebherr-interface-world' ),
				(string) $m['name'],
				trim( sprintf( '%s %s', (string) $m['serial'], (string) $m['location'] ) ),
				'normal',
				$home . '#liw-my-machines',
				__( 'In deinem Maschinenbestand.', 'liebherr-interface-world' )
			);
		}

		// Adventure Pocket – Hinweis auf eigene veröffentlichte Beiträge (normal).
		if ( $adv > 0 ) {
			$items[] = self::item(
				'auto-adventures',
				__( 'Adventure Pocket', 'liebherr-interface-world' ),
				__( 'Deine veröffentlichten Adventures', 'liebherr-interface-world' ),
				sprintf( /* translators: %d: count */ __( '%d veröffentlichte Beiträge.', 'liebherr-interface-world' ), $adv ),
				'normal',
				$home . '#liw-my-adventures',
				__( 'Auf Basis deiner eigenen Adventures.', 'liebherr-interface-world' )
			);
		}

		return $items;
	}

	/**
	 * Aufgaben aus Kontaktanfragen (eingehend, requested) und zu bestätigenden Leistungen (Empfänger = Nutzer).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function contact_tasks( int $user_id ): array {
		if ( ! class_exists( ContactRepository::class ) ) {
			return [];
		}
		$out  = [];
		$home = self::route();
		foreach ( ContactRepository::requests_for( $user_id ) as $r ) {
			if ( (int) $r['recipient_id'] === $user_id && 'requested' === $r['status'] ) {
				$out[] = self::item(
					'auto-req-' . (int) $r['id'],
					__( 'Pocket Tasks', 'liebherr-interface-world' ),
					__( 'Kontaktanfrage beantworten', 'liebherr-interface-world' ),
					(string) $r['purpose'],
					'high',
					$home . '#liw-my-contacts',
					__( 'Eine Kontaktanfrage wartet auf deine Entscheidung.', 'liebherr-interface-world' )
				);
			}
		}
		foreach ( ContactRepository::connections_for( $user_id ) as $c ) {
			foreach ( ContactRepository::services_for( (int) $c['id'] ) as $s ) {
				if ( 'proposed' === $s['status'] && (int) $s['receiver_id'] === $user_id ) {
					$out[] = self::item(
						'auto-svc-' . (int) $s['id'],
						__( 'Pocket Tasks', 'liebherr-interface-world' ),
						__( 'Leistung bestätigen', 'liebherr-interface-world' ),
						sprintf( '%s (%d Token)', (string) $s['description'], (int) $s['token_amount'] ),
						'high',
						$home . '#liw-my-contacts',
						__( 'Eine vorgeschlagene Leistung wartet auf deine Bestätigung.', 'liebherr-interface-world' )
					);
				}
			}
		}
		return $out;
	}

	private static function own_published_adventures( int $user_id ): int {
		if ( ! class_exists( '\Liebherr\InterfaceWorld\Adventures\AdventureCpt' ) ) {
			return 0;
		}
		$ids = get_posts( [
			'post_type'   => \Liebherr\InterfaceWorld\Adventures\AdventureCpt::POST_TYPE,
			'author'      => $user_id,
			'post_status' => 'publish',
			'numberposts' => 100,
			'fields'      => 'ids',
		] );
		return is_array( $ids ) ? count( $ids ) : 0;
	}

	private static function route(): string {
		$id = (int) get_option( 'liw_my_liebherr_page_id', 0 );
		return ( $id > 0 && 'publish' === get_post_status( $id ) ) ? (string) get_permalink( $id ) : '';
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function item( string $id, string $source, string $title, string $body, string $priority, string $route, string $reason ): array {
		return [
			'id'              => $id,
			'source'          => $source,
			'title'           => $title,
			'body'            => $body,
			'priority'        => $priority,
			'return_route'    => $route,
			'requires_ack'    => 0,
			'acknowledged_at' => null,
			'derived'         => true,
			'reason'          => $reason,
		];
	}
}
