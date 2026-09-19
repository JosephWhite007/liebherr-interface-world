<?php
/**
 * Liebherr Adventures – Token-/Registrierungs-Ledger (Basislogik §5/§6/§9, revisionssicher).
 *
 * Append-only Hash-Kette je Beitrag (analog IntelligenceWorld\EventLog). Jeder nachweispflichtige Vorgang
 * (Registrierung, Validierung, Veröffentlichung, Preisänderung, Tokenzugriff) wird unveränderlich verkettet;
 * bereits bestätigte Zugriffe können damit nicht nachträglich verändert werden. Die reinen Bausteine
 * `canonical()` und `hash()` sind ohne WordPress unit-testbar; `append()`/`verify_chain()` nutzen wpdb.
 *
 * @package Liebherr\InterfaceWorld\Adventures
 * @since   0.1.0-alpha.59
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Adventures;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class TokenLedger {

	public const KIND_REGISTERED           = 'registered';
	public const KIND_VALIDATION_REQUESTED = 'validation_requested';
	public const KIND_VALIDATED            = 'validated';
	public const KIND_REVISION             = 'revision';
	public const KIND_PUBLISHED            = 'published';
	public const KIND_BLOCKED              = 'blocked';
	public const KIND_ARCHIVED             = 'archived';
	public const KIND_PRICE_CHANGE         = 'price_change';
	public const KIND_ACCESS               = 'access';

	/** @return array<int,string> */
	public static function kinds(): array {
		return [
			self::KIND_REGISTERED, self::KIND_VALIDATION_REQUESTED, self::KIND_VALIDATED, self::KIND_REVISION,
			self::KIND_PUBLISHED, self::KIND_BLOCKED, self::KIND_ARCHIVED, self::KIND_PRICE_CHANGE, self::KIND_ACCESS,
		];
	}

	public static function is_valid_kind( string $kind ): bool {
		return in_array( $kind, self::kinds(), true );
	}

	/** Kanonische Serialisierung des Ledger-Kerns (Schlüsselreihenfolge stabil, Metadaten sortiert). */
	public static function canonical( array $core ): string {
		$ordered = [
			'entry_uid'         => (string) ( $core['entry_uid'] ?? '' ),
			'contribution_id'   => (int) ( $core['contribution_id'] ?? 0 ),
			'contribution_uuid' => (string) ( $core['contribution_uuid'] ?? '' ),
			'seq'               => (int) ( $core['seq'] ?? 0 ),
			'kind'              => (string) ( $core['kind'] ?? '' ),
			'version'           => (int) ( $core['version'] ?? 1 ),
			'user_ref'          => (int) ( $core['user_ref'] ?? 0 ),
			'org_unit'          => (string) ( $core['org_unit'] ?? '' ),
			'token_value'       => (int) ( $core['token_value'] ?? 0 ),
			'usage_scope'       => (string) ( $core['usage_scope'] ?? '' ),
			'transaction_id'    => (string) ( $core['transaction_id'] ?? '' ),
			'occurred_at'       => (string) ( $core['occurred_at'] ?? '' ),
			'metadata'          => self::normalize_meta( $core['metadata'] ?? [] ),
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

	public static function hash( string $prev_hash, array $core ): string {
		return hash( 'sha256', $prev_hash . '|' . self::canonical( $core ) );
	}

	/**
	 * Hängt einen Vorgang an das Ledger des Beitrags an (idempotent bei gesetztem `dedupe_key`).
	 *
	 * @param array<string,mixed> $opts contribution_uuid, version, user_ref, org_unit, token_value,
	 *                                   usage_scope, transaction_id, occurred_at, dedupe_key, metadata, entry_uid.
	 * @return array{id:int,seq:int,entry_uid:string,transaction_id:string,integrity_hash:string,duplicate:bool}
	 */
	public static function append( int $contribution_id, string $kind, array $opts = [] ): array {
		global $wpdb;
		$table = TokenSchema::table();

		$empty = [ 'id' => 0, 'seq' => 0, 'entry_uid' => '', 'transaction_id' => '', 'integrity_hash' => '', 'duplicate' => false ];
		if ( $contribution_id <= 0 || ! self::is_valid_kind( $kind ) ) {
			return $empty;
		}

		$dedupe = isset( $opts['dedupe_key'] ) ? (string) $opts['dedupe_key'] : '';
		if ( '' !== $dedupe ) {
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id, seq, entry_uid, transaction_id, integrity_hash FROM {$table} WHERE contribution_id = %d AND dedupe_key = %s", $contribution_id, $dedupe ), ARRAY_A ); // phpcs:ignore WordPress.DB
			if ( is_array( $existing ) ) {
				return [
					'id'             => (int) $existing['id'],
					'seq'            => (int) $existing['seq'],
					'entry_uid'      => (string) $existing['entry_uid'],
					'transaction_id' => (string) $existing['transaction_id'],
					'integrity_hash' => (string) $existing['integrity_hash'],
					'duplicate'      => true,
				];
			}
		}

		$last      = $wpdb->get_row( $wpdb->prepare( "SELECT seq, integrity_hash FROM {$table} WHERE contribution_id = %d ORDER BY seq DESC LIMIT 1", $contribution_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$seq       = is_array( $last ) ? ( (int) $last['seq'] + 1 ) : 1;
		$prev_hash = is_array( $last ) ? (string) $last['integrity_hash'] : '';

		$entry_uid      = isset( $opts['entry_uid'] ) && '' !== (string) $opts['entry_uid'] ? (string) $opts['entry_uid'] : wp_generate_uuid4();
		$transaction_id = isset( $opts['transaction_id'] ) && '' !== (string) $opts['transaction_id'] ? (string) $opts['transaction_id'] : ( self::KIND_ACCESS === $kind ? 'TXN-' . strtoupper( substr( wp_generate_uuid4(), 0, 12 ) ) : '' );
		$occurred_at    = isset( $opts['occurred_at'] ) && '' !== (string) $opts['occurred_at'] ? (string) $opts['occurred_at'] : gmdate( 'Y-m-d H:i:s' );
		$uuid           = isset( $opts['contribution_uuid'] ) ? (string) $opts['contribution_uuid'] : '';
		$version        = isset( $opts['version'] ) ? max( 1, (int) $opts['version'] ) : 1;
		$user_ref       = isset( $opts['user_ref'] ) ? (int) $opts['user_ref'] : 0;
		$org_unit       = isset( $opts['org_unit'] ) ? (string) $opts['org_unit'] : '';
		$token_value    = isset( $opts['token_value'] ) ? max( 0, (int) $opts['token_value'] ) : 0;
		$usage_scope    = isset( $opts['usage_scope'] ) ? (string) $opts['usage_scope'] : '';
		$metadata       = isset( $opts['metadata'] ) && is_array( $opts['metadata'] ) ? $opts['metadata'] : [];

		$core = [
			'entry_uid'         => $entry_uid,
			'contribution_id'   => $contribution_id,
			'contribution_uuid' => $uuid,
			'seq'               => $seq,
			'kind'              => $kind,
			'version'           => $version,
			'user_ref'          => $user_ref,
			'org_unit'          => $org_unit,
			'token_value'       => $token_value,
			'usage_scope'       => $usage_scope,
			'transaction_id'    => $transaction_id,
			'occurred_at'       => $occurred_at,
			'metadata'          => $metadata,
		];
		$integrity = self::hash( $prev_hash, $core );

		$wpdb->insert( // phpcs:ignore WordPress.DB
			$table,
			[
				'entry_uid'         => $entry_uid,
				'contribution_id'   => $contribution_id,
				'contribution_uuid' => '' !== $uuid ? $uuid : null,
				'seq'               => $seq,
				'kind'              => $kind,
				'version'           => $version,
				'user_ref'          => $user_ref > 0 ? $user_ref : null,
				'org_unit'          => '' !== $org_unit ? $org_unit : null,
				'token_value'       => $token_value,
				'usage_scope'       => '' !== $usage_scope ? $usage_scope : null,
				'transaction_id'    => '' !== $transaction_id ? $transaction_id : null,
				'occurred_at'       => $occurred_at,
				'dedupe_key'        => '' !== $dedupe ? $dedupe : null,
				'metadata'          => [] !== $metadata ? (string) wp_json_encode( $metadata ) : null,
				'prev_hash'         => '' !== $prev_hash ? $prev_hash : null,
				'integrity_hash'    => $integrity,
			],
			[ '%s', '%d', '%s', '%d', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		return [
			'id'             => (int) $wpdb->insert_id,
			'seq'            => $seq,
			'entry_uid'      => $entry_uid,
			'transaction_id' => $transaction_id,
			'integrity_hash' => $integrity,
			'duplicate'      => false,
		];
	}

	/** @return array<int,array<string,mixed>> Vorgänge des Beitrags, aufsteigend nach Sequenz. */
	public static function chain_for( int $contribution_id ): array {
		global $wpdb;
		$table = TokenSchema::table();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE contribution_id = %d ORDER BY seq ASC", $contribution_id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $rows ) ? $rows : [];
	}

	/** Prüft die Hash-Kette des Beitrags (erkennt nachträgliche Manipulation). */
	public static function verify_chain( int $contribution_id ): bool {
		$prev = '';
		foreach ( self::chain_for( $contribution_id ) as $row ) {
			$core = [
				'entry_uid'         => (string) $row['entry_uid'],
				'contribution_id'   => (int) $row['contribution_id'],
				'contribution_uuid' => (string) ( $row['contribution_uuid'] ?? '' ),
				'seq'               => (int) $row['seq'],
				'kind'              => (string) $row['kind'],
				'version'           => (int) $row['version'],
				'user_ref'          => (int) ( $row['user_ref'] ?? 0 ),
				'org_unit'          => (string) ( $row['org_unit'] ?? '' ),
				'token_value'       => (int) $row['token_value'],
				'usage_scope'       => (string) ( $row['usage_scope'] ?? '' ),
				'transaction_id'    => (string) ( $row['transaction_id'] ?? '' ),
				'occurred_at'       => (string) $row['occurred_at'],
				'metadata'          => null !== $row['metadata'] ? (array) json_decode( (string) $row['metadata'], true ) : [],
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
