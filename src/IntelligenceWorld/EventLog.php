<?php
/**
 * Liebherr Intelligence World – Ereignis-Ledger (Pflichtenheft-2 §10.10/§11/§16).
 *
 * Append-only Protokoll aller abrechnungs- und nutzungsrelevanten Ereignisse. Jede Zeile ist über eine
 * Hash-Kette (integrity_hash = SHA-256 aus prev_hash + kanonischem Ereigniskern) mit der vorherigen
 * verbunden – nachträgliche Änderungen werden dadurch erkennbar (manipulationsgeschützt, §16). Idempotenz
 * über `dedupe_key` je Sitzung schützt vor Doppelbuchung/Replay. Zeitstempel in UTC.
 *
 * Die reinen Bausteine `canonical()` und `hash()` sind ohne WP unit-testbar; `append()/verify_chain()`
 * nutzen $wpdb und werden im Docker-Selbsttest geprüft.
 *
 * @package Liebherr\InterfaceWorld\IntelligenceWorld
 * @since   0.1.0-alpha.47
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\IntelligenceWorld;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class EventLog {

	/** Kanonische, deterministische Serialisierung des Ereigniskerns (stabile Schlüsselreihenfolge). */
	public static function canonical( array $core ): string {
		$ordered = [
			'event_uid'          => (string) ( $core['event_uid'] ?? '' ),
			'session_code'       => (string) ( $core['session_code'] ?? '' ),
			'seq'                => (int) ( $core['seq'] ?? 0 ),
			'type'               => (string) ( $core['type'] ?? '' ),
			'module'             => (string) ( $core['module'] ?? '' ),
			'occurred_at'        => (string) ( $core['occurred_at'] ?? '' ),
			'price_rule_version' => (string) ( $core['price_rule_version'] ?? '' ),
			'metadata'           => self::normalize_meta( $core['metadata'] ?? [] ),
		];
		return (string) wp_json_encode( $ordered );
	}

	/** @param mixed $meta */
	private static function normalize_meta( $meta ): array {
		if ( ! is_array( $meta ) ) {
			return [];
		}
		ksort( $meta );
		return $meta;
	}

	/** Verkettungs-Hash aus vorherigem Hash und Ereigniskern. */
	public static function hash( string $prev_hash, array $core ): string {
		return hash( 'sha256', $prev_hash . '|' . self::canonical( $core ) );
	}

	/**
	 * Hängt ein Ereignis an das Ledger der Sitzung an (idempotent bei gesetztem `dedupe_key`).
	 *
	 * @param array<string,mixed> $opts module, occurred_at (UTC 'Y-m-d H:i:s'), price_rule_version,
	 *                                   dedupe_key, metadata (array), event_uid.
	 * @return array{id:int,seq:int,event_uid:string,integrity_hash:string,prev_hash:string,duplicate:bool}
	 */
	public static function append( string $session_code, string $type, array $opts = [] ): array {
		global $wpdb;
		$table = Schema::event_table();

		if ( ! EventTypes::is_valid( $type ) ) {
			return [ 'id' => 0, 'seq' => 0, 'event_uid' => '', 'integrity_hash' => '', 'prev_hash' => '', 'duplicate' => false ];
		}

		$dedupe = isset( $opts['dedupe_key'] ) ? (string) $opts['dedupe_key'] : '';
		if ( '' !== $dedupe ) {
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, seq, event_uid, integrity_hash, prev_hash FROM {$table} WHERE session_code = %s AND dedupe_key = %s", $session_code, $dedupe ), ARRAY_A ); // phpcs:ignore WordPress.DB
			if ( is_array( $existing ) ) {
				return [
					'id'             => (int) $existing['id'],
					'seq'            => (int) $existing['seq'],
					'event_uid'      => (string) $existing['event_uid'],
					'integrity_hash' => (string) $existing['integrity_hash'],
					'prev_hash'      => (string) $existing['prev_hash'],
					'duplicate'      => true,
				];
			}
		}

		// Sequenz + vorheriger Hash (Kettenanker).
		$last = $wpdb->get_row( $wpdb->prepare( "SELECT seq, integrity_hash FROM {$table} WHERE session_code = %s ORDER BY seq DESC LIMIT 1", $session_code ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$seq       = is_array( $last ) ? ( (int) $last['seq'] + 1 ) : 1;
		$prev_hash = is_array( $last ) ? (string) $last['integrity_hash'] : '';

		$event_uid   = isset( $opts['event_uid'] ) && '' !== (string) $opts['event_uid'] ? (string) $opts['event_uid'] : wp_generate_uuid4();
		$occurred_at = isset( $opts['occurred_at'] ) && '' !== (string) $opts['occurred_at'] ? (string) $opts['occurred_at'] : gmdate( 'Y-m-d H:i:s' );
		$module      = isset( $opts['module'] ) ? (string) $opts['module'] : '';
		$prv         = isset( $opts['price_rule_version'] ) ? (string) $opts['price_rule_version'] : '';
		$metadata    = isset( $opts['metadata'] ) && is_array( $opts['metadata'] ) ? $opts['metadata'] : [];

		$core = [
			'event_uid'          => $event_uid,
			'session_code'       => $session_code,
			'seq'                => $seq,
			'type'               => $type,
			'module'             => $module,
			'occurred_at'        => $occurred_at,
			'price_rule_version' => $prv,
			'metadata'           => $metadata,
		];
		$integrity = self::hash( $prev_hash, $core );

		$wpdb->insert( // phpcs:ignore WordPress.DB
			$table,
			[
				'event_uid'          => $event_uid,
				'session_code'       => $session_code,
				'seq'                => $seq,
				'type'               => $type,
				'module'             => '' !== $module ? $module : null,
				'occurred_at'        => $occurred_at,
				'price_rule_version' => '' !== $prv ? $prv : null,
				'dedupe_key'         => '' !== $dedupe ? $dedupe : null,
				'metadata'           => [] !== $metadata ? (string) wp_json_encode( $metadata ) : null,
				'prev_hash'          => '' !== $prev_hash ? $prev_hash : null,
				'integrity_hash'     => $integrity,
			],
			[ '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		return [
			'id'             => (int) $wpdb->insert_id,
			'seq'            => $seq,
			'event_uid'      => $event_uid,
			'integrity_hash' => $integrity,
			'prev_hash'      => $prev_hash,
			'duplicate'      => false,
		];
	}

	/** @return array<int,array<string,mixed>> Ereignisse der Sitzung, aufsteigend nach Sequenz. */
	public static function chain_for( string $session_code ): array {
		global $wpdb;
		$table = Schema::event_table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE session_code = %s ORDER BY seq ASC", $session_code ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $rows ) ? $rows : [];
	}

	/** Prüft die Hash-Kette der Sitzung (erkennt nachträgliche Manipulation). */
	public static function verify_chain( string $session_code ): bool {
		$prev = '';
		foreach ( self::chain_for( $session_code ) as $row ) {
			$core = [
				'event_uid'          => (string) $row['event_uid'],
				'session_code'       => (string) $row['session_code'],
				'seq'                => (int) $row['seq'],
				'type'               => (string) $row['type'],
				'module'             => (string) ( $row['module'] ?? '' ),
				'occurred_at'        => (string) $row['occurred_at'],
				'price_rule_version' => (string) ( $row['price_rule_version'] ?? '' ),
				'metadata'           => null !== $row['metadata'] ? (array) json_decode( (string) $row['metadata'], true ) : [],
			];
			$expected = self::hash( $prev, $core );
			if ( ! hash_equals( $expected, (string) $row['integrity_hash'] ) ) {
				return false;
			}
			$prev = (string) $row['integrity_hash'];
		}
		return true;
	}
}
