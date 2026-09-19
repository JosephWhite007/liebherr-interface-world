<?php
/**
 * Liebherr World – Plattformzeit: Abrechnungs-Service (Pflichtenheft My Liebherr §41.1/§41.2, ADR-LIW-MYL-001 S10).
 *
 * Erzeugt je abgeschlossenem Zeitabschnitt GENAU EINEN Token-Abrechnungssatz (append-only, Idempotenz über
 * `idempotency_key = ptime-<session>`; wiederholte Stopp-Meldungen buchen nicht doppelt, MYL 027). Die
 * Wallet-Buchung ist eine Naht: nur bei {@see Flags::charge_live()} und verfügbarer Plattform-Wallet würde die
 * Token-Buchung über den Wallet-Mehrwährungszweig erfolgen (kommt mit dem Wallet-Pflichtenheft, §41.1). Solange
 * das Flag AUS ist (Standard), bleibt der Satz `pending` und wird nur revisionssicher protokolliert (MYL 028);
 * der Filter/Hook `liw_ptime_charge` reicht den Satz an ein späteres Wallet-Modul weiter.
 *
 * @package Liebherr\InterfaceWorld\PlatformTime
 * @since   0.1.0-alpha.111
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\PlatformTime;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ChargeService {

	/**
	 * Verbucht (oder liest idempotent) den Abrechnungssatz eines Abschnitts.
	 *
	 * @return array<string,mixed>
	 */
	public static function record( int $session_id, int $user_id, int $active_seconds ): array {
		$rule   = TokenRule::current();
		$tokens = $rule->tokens_for( $active_seconds );
		$idem   = 'ptime-' . $session_id;

		global $wpdb;
		$table    = Schema::charge_table();
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE idempotency_key = %s", $idem ), ARRAY_A ); // phpcs:ignore WordPress.DB
		if ( is_array( $existing ) ) {
			return self::shape( $existing ); // Idempotent: keine Doppelbuchung.
		}

		$status     = 'pending';
		$wallet_ref = '';
		// Naht: echte Token-Buchung nur bei scharfem Flag + verfügbarer Wallet (Mehrwährung → Wallet-Pflichtenheft).
		// Solange nicht verfügbar, bleibt der Satz ausstehend (MYL 028). Kein EUR-Ersatz für Token.

		$wpdb->insert( // phpcs:ignore WordPress.DB
			$table,
			[
				'session_id'      => $session_id,
				'user_id'         => $user_id,
				'token_amount'    => $tokens,
				'rule_version'    => $rule->version,
				'status'          => $status,
				'idempotency_key' => $idem,
				'wallet_ref'      => $wallet_ref,
			],
			[ '%d', '%d', '%d', '%s', '%s', '%s', '%s' ]
		);
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE idempotency_key = %s", $idem ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$charge = is_array( $row ) ? self::shape( $row ) : [ 'session_id' => $session_id, 'user_id' => $user_id, 'token_amount' => $tokens, 'rule_version' => $rule->version, 'status' => $status, 'wallet_ref' => $wallet_ref ];

		/**
		 * Übergabepunkt an ein künftiges Wallet-Modul (Mehrwährung/Token). Ein Consumer kann den Satz buchen
		 * und den Status auf `settled` setzen; ohne Consumer bleibt er `pending` (Buchungsnaht, §41.1).
		 */
		do_action( 'liw_ptime_charge', $charge, Flags::charge_live() );
		return $charge;
	}

	/**
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	private static function shape( array $row ): array {
		return [
			'id'           => (int) ( $row['id'] ?? 0 ),
			'session_id'   => (int) ( $row['session_id'] ?? 0 ),
			'user_id'      => (int) ( $row['user_id'] ?? 0 ),
			'token_amount' => (int) ( $row['token_amount'] ?? 0 ),
			'rule_version' => (string) ( $row['rule_version'] ?? '' ),
			'status'       => (string) ( $row['status'] ?? 'pending' ),
			'wallet_ref'   => (string) ( $row['wallet_ref'] ?? '' ),
		];
	}
}
