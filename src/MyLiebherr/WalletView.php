<?php
/**
 * Liebherr World – My Liebherr: My Wallet (Pflichtenheft My Liebherr §7/§18/§29, ADR-LIW-MYL-001 S8).
 *
 * Read-only Wallet-Ansicht auf die Plattform Health Wallet ({@see \Liebherr\InterfaceWorld\CoreBridge\WalletBridge}):
 * Saldo, gutgeschrieben/belastet gesamt, Jahresbudget/-verbrauch und die letzten Buchungen als verständliche Belege
 * (§30: „jede Tokenbewegung … ein verständlicher Beleg"). Keine zweite Saldenquelle; Beträge kommen ausschließlich
 * aus dem Wallet-Ledger. Shortcode `[liw_my_wallet]`, zusätzlich in die My-Overview-Seite eingebettet. Self-gating;
 * nur eigener Nutzer (§35/SEC 01). Schreibende Buchungen sind hier bewusst nicht möglich (Kauf/Abrechnung laufen
 * über die jeweiligen Fachprozesse).
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.117
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

use Liebherr\InterfaceWorld\CoreBridge\WalletBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class WalletView {

	public const SHORTCODE = 'liw_my_wallet';

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'assets' ] );
	}

	public static function assets(): void {
		if ( is_admin() ) {
			return;
		}
		$post = get_post();
		if ( ! $post instanceof \WP_Post || ! has_shortcode( (string) $post->post_content, self::SHORTCODE ) ) {
			return;
		}
		OverviewView::assets_for_shortcode();
	}

	public static function shortcode(): string {
		if ( ! Flags::enabled() ) {
			return '';
		}
		if ( ! is_user_logged_in() || ! current_user_can( Roles::CAP_ACCESS ) ) {
			return '<div class="liw-myl liw-myl--notice">' . esc_html__( 'Bitte melden Sie sich an, um Ihre Wallet zu sehen.', 'liebherr-interface-world' ) . '</div>';
		}
		return '<div class="liw-myl">' . self::render( get_current_user_id() ) . '</div>';
	}

	/** Wiederverwendbarer Wallet-Block (auch aus der Overview eingebettet). */
	public static function render( int $uid ): string {
		$out = '<section class="liw-myl__wallet" id="liw-my-wallet">'
			. '<h2 class="liw-myl__tile-title">' . esc_html__( 'My Wallet', 'liebherr-interface-world' ) . '</h2>';

		if ( ! WalletBridge::available() ) {
			return $out . '<p class="liw-myl__wallet-note">' . esc_html__( 'Die Plattform-Wallet ist derzeit nicht erreichbar.', 'liebherr-interface-world' ) . '</p></section>';
		}

		$sum = WalletBridge::summary( $uid ) ?? [];
		$out .= self::summary_html( $sum ) . self::transactions_html( $uid );
		return $out . '</section>';
	}

	/** @param array<string,mixed> $sum */
	private static function summary_html( array $sum ): string {
		$cards = [
			[ __( 'Saldo', 'liebherr-interface-world' ), (string) ( $sum['balance_formatted'] ?? '—' ), true ],
			[ __( 'Gutgeschrieben gesamt', 'liebherr-interface-world' ), WalletBridge::format_cents( (int) ( $sum['total_credited_cents'] ?? 0 ) ), false ],
			[ __( 'Belastet gesamt', 'liebherr-interface-world' ), WalletBridge::format_cents( (int) ( $sum['total_debited_cents'] ?? 0 ) ), false ],
			[ __( 'Jahresbudget', 'liebherr-interface-world' ), (string) ( $sum['budget_formatted'] ?? '—' ), false ],
		];
		$html = '<div class="liw-myl__wallet-cards">';
		foreach ( $cards as $c ) {
			$html .= '<div class="liw-myl__wallet-card' . ( $c[2] ? ' is-primary' : '' ) . '">'
				. '<span class="liw-myl__wallet-cval">' . esc_html( $c[1] ) . '</span>'
				. '<span class="liw-myl__wallet-clabel">' . esc_html( $c[0] ) . '</span>'
				. '</div>';
		}
		return $html . '</div>';
	}

	private static function transactions_html( int $uid ): string {
		$rows = WalletBridge::transactions( $uid, 10 );
		if ( [] === $rows ) {
			return '<p class="liw-myl__wallet-note">' . esc_html__( 'Noch keine Buchungen.', 'liebherr-interface-world' ) . '</p>';
		}
		$body = '';
		foreach ( $rows as $r ) {
			$amount = (int) ( is_object( $r ) ? ( $r->amount_cents ?? 0 ) : ( $r['amount_cents'] ?? 0 ) );
			$type   = (string) ( is_object( $r ) ? ( $r->source_type ?? '' ) : ( $r['source_type'] ?? '' ) );
			$status = (string) ( is_object( $r ) ? ( $r->status ?? '' ) : ( $r['status'] ?? '' ) );
			$when   = (string) ( is_object( $r ) ? ( $r->created_at ?? '' ) : ( $r['created_at'] ?? '' ) );
			$sign   = $amount >= 0 ? '+' : '−';
			$cls    = $amount >= 0 ? 'is-credit' : 'is-debit';
			$date   = '' !== $when ? mysql2date( get_option( 'date_format' ) ?: 'Y-m-d', $when ) : '';
			$body  .= '<tr>'
				. '<td>' . esc_html( $date ) . '</td>'
				. '<td>' . esc_html( WalletBridge::source_label( $type ) ) . '</td>'
				. '<td class="liw-myl__wtx-amt ' . $cls . '">' . esc_html( $sign . ' ' . WalletBridge::format_cents( abs( $amount ) ) ) . '</td>'
				. '<td>' . esc_html( WalletBridge::status_label( $status ) ) . '</td>'
				. '</tr>';
		}
		return '<table class="liw-myl__wtx"><thead><tr>'
			. '<th>' . esc_html__( 'Datum', 'liebherr-interface-world' ) . '</th>'
			. '<th>' . esc_html__( 'Art', 'liebherr-interface-world' ) . '</th>'
			. '<th>' . esc_html__( 'Betrag', 'liebherr-interface-world' ) . '</th>'
			. '<th>' . esc_html__( 'Status', 'liebherr-interface-world' ) . '</th>'
			. '</tr></thead><tbody>' . $body . '</tbody></table>';
	}
}
