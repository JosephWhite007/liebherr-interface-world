<?php
/**
 * Liebherr Interface Solutions – Wallet Bridge (ADR-LIW-MYL-001 S1/S7, Pflichtenheft My Liebherr §7).
 *
 * EINZIGE Saldenquelle für My Liebherr ist die Plattform-Wallet (Health Wallet) des Cores
 * `Araliya\Platform\Core\Modules\Wallet\WalletService` – keine zweite Wallet/kein zweiter Ledger. Diese Bridge
 * kapselt den Zugriff wie die übrigen CoreBridges (guarded über class_exists, robust ohne Core). In dieser
 * Ausbaustufe LESEND (Saldo/Zusammenfassung/Transaktionen) für die My-Overview-Startseite; die schreibende
 * Naht (Reservieren→Bestätigen) ist als Methoden vorgesehen, wird aber erst mit dem kommenden Wallet-
 * Pflichtenheft (Mehrwährung/Token) scharf geschaltet. Identität = WP user_id (= guest_id der Wallet).
 *
 * @package Liebherr\InterfaceWorld\CoreBridge
 * @since   0.1.0-alpha.109
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\CoreBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class WalletBridge {

	private const WALLET_CLASS = 'Araliya\\Platform\\Core\\Modules\\Wallet\\WalletService';

	/** Ist die Plattform-Wallet verfügbar (Core aktiv)? */
	public static function available(): bool {
		return class_exists( self::WALLET_CLASS );
	}

	/**
	 * Saldo in Minor-Units (Cent) oder null, wenn die Wallet fehlt.
	 */
	public static function balance_cents( int $user_id ): ?int {
		if ( $user_id <= 0 || ! self::available() ) {
			return null;
		}
		$cls = self::WALLET_CLASS;
		return (int) $cls::get_balance( $user_id );
	}

	/**
	 * Kontozusammenfassung (Saldo/gutgeschrieben/belastet/Budget) oder null.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function summary( int $user_id ): ?array {
		if ( $user_id <= 0 || ! self::available() ) {
			return null;
		}
		$cls = self::WALLET_CLASS;
		$sum = $cls::get_summary( $user_id );
		return is_array( $sum ) ? $sum : null;
	}

	/**
	 * Letzte Buchungen (paginiert) oder leeres Array.
	 *
	 * @return array<int,mixed>
	 */
	public static function transactions( int $user_id, int $limit = 10, int $offset = 0 ): array {
		if ( $user_id <= 0 || ! self::available() ) {
			return [];
		}
		$cls  = self::WALLET_CLASS;
		$rows = $cls::get_transactions( $user_id, max( 1, $limit ), max( 0, $offset ) );
		return is_array( $rows ) ? $rows : [];
	}

	/** Formatiert Minor-Units als lesbaren EUR-Betrag (Anzeige; keine Rechenbasis). */
	public static function format_cents( int $cents ): string {
		return number_format_i18n( $cents / 100, 2 ) . ' €';
	}

	/** Lesbares Label für einen Buchungs-Quelltyp (Anzeige). Fällt auf den Rohwert zurück. */
	public static function source_label( string $source_type ): string {
		$map = [
			'stripe_payment'     => __( 'Zahlung', 'liebherr-interface-world' ),
			'voucher'            => __( 'Gutschein', 'liebherr-interface-world' ),
			'gift_voucher'       => __( 'Gutschein', 'liebherr-interface-world' ),
			'admin'              => __( 'Gutschrift', 'liebherr-interface-world' ),
			'admin_credit'       => __( 'Gutschrift', 'liebherr-interface-world' ),
			'refund'             => __( 'Rückerstattung', 'liebherr-interface-world' ),
			'invoice_payment'    => __( 'Rechnung', 'liebherr-interface-world' ),
			'booking_debit'      => __( 'Buchung', 'liebherr-interface-world' ),
			'admin_debit'        => __( 'Belastung', 'liebherr-interface-world' ),
			'slot_reservation'   => __( 'Reservierung', 'liebherr-interface-world' ),
			'token_billing_debit'=> __( 'Plattformnutzung', 'liebherr-interface-world' ),
		];
		return $map[ $source_type ] ?? $source_type;
	}

	/** Lesbares Label für einen Buchungsstatus (Anzeige). */
	public static function status_label( string $status ): string {
		$map = [
			'pending'   => __( 'ausstehend', 'liebherr-interface-world' ),
			'confirmed' => __( 'bestätigt', 'liebherr-interface-world' ),
			'settled'   => __( 'abgerechnet', 'liebherr-interface-world' ),
			'cancelled' => __( 'storniert', 'liebherr-interface-world' ),
			'reversed'  => __( 'rückgebucht', 'liebherr-interface-world' ),
		];
		return $map[ $status ] ?? $status;
	}
}
