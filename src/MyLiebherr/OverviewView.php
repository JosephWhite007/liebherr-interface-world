<?php
/**
 * Liebherr World – My Liebherr: My Overview (Startseite, ADR-LIW-MYL-001 S4, Pflichtenheft My Liebherr §5/§30).
 *
 * Bankkonto-artige persönliche Startseite: beantwortet in Sekunden „Was besitze ich · was ist neu · was muss
 * ich tun · welche Leistung wurde gebucht" und bietet einen festen Schnellaktionsbereich (§30). Der Saldo wird
 * LESEND aus der Plattform-Wallet gezogen ({@see \Liebherr\InterfaceWorld\CoreBridge\WalletBridge}) – keine
 * zweite Saldenquelle. Shortcode `[liw_my_liebherr]`, self-gating über {@see Flags::enabled()}; nur für
 * angemeldete Nutzer mit {@see Roles::CAP_ACCESS} (§35, SEC 01).
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.110
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

use Liebherr\InterfaceWorld\CoreBridge\WalletBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class OverviewView {

	public const SHORTCODE = 'liw_my_liebherr';

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'assets' ] );
	}

	/** CSS nur auf der My-Liebherr-Seite laden (filemtime-Cache-Buster, da ?ver von WP Rocket entfernt werden kann). */
	public static function assets(): void {
		$page_id = (int) get_option( 'liw_my_liebherr_page_id', 0 );
		if ( $page_id <= 0 || ! is_page( $page_id ) ) {
			return;
		}
		$rel  = 'assets/css/liw-my-liebherr.css';
		$path = LIW_PATH . $rel;
		$ver  = is_readable( $path ) ? (string) filemtime( $path ) : LIW_VERSION;
		wp_enqueue_style( 'liw-my-liebherr', LIW_URL . $rel . '?v=' . $ver, [], null );
	}

	public static function shortcode(): string {
		if ( ! Flags::enabled() ) {
			return '';
		}
		if ( ! is_user_logged_in() ) {
			return '<div class="liw-myl liw-myl--notice">' . esc_html__( 'Bitte melden Sie sich an, um Ihren persönlichen Bereich My Liebherr zu sehen.', 'liebherr-interface-world' ) . '</div>';
		}
		if ( ! current_user_can( Roles::CAP_ACCESS ) ) {
			return '<div class="liw-myl liw-myl--notice">' . esc_html__( 'Ihr Konto ist für My Liebherr noch nicht freigeschaltet.', 'liebherr-interface-world' ) . '</div>';
		}

		$uid = get_current_user_id();
		$ctx = Context::for_user( $uid );

		return '<div class="liw-myl">'
			. self::header_html( (string) $ctx['display_name'] )
			. '<div class="liw-myl__grid">'
			. self::tile_wallet( $uid )
			. self::tile( __( 'Was ist neu', 'liebherr-interface-world' ), esc_html__( 'Neue relevante Updates erscheinen hier, sobald sie verfügbar sind.', 'liebherr-interface-world' ) )
			. self::tile( __( 'Was muss ich tun', 'liebherr-interface-world' ), esc_html__( 'Offene Aufgaben und Freigaben werden hier gebündelt.', 'liebherr-interface-world' ) )
			. self::tile( __( 'Gebuchte Leistung', 'liebherr-interface-world' ), esc_html__( 'Zuletzt gebuchte Leistungen und Belege erscheinen hier.', 'liebherr-interface-world' ) )
			. '</div>'
			. self::quick_actions_html()
			. '<p class="liw-myl__clockhint">' . esc_html__( 'Ihre Plattformzeit läuft als Session-Uhr unten links mit.', 'liebherr-interface-world' ) . '</p>'
			. '</div>';
	}

	private static function header_html( string $name ): string {
		$greet = '' !== $name
			? sprintf( /* translators: %s: display name */ __( 'Willkommen zurück, %s', 'liebherr-interface-world' ), $name )
			: __( 'Willkommen in Ihrem persönlichen Bereich', 'liebherr-interface-world' );
		return '<header class="liw-myl__head">'
			. '<h1 class="liw-myl__title">' . esc_html__( 'My Liebherr', 'liebherr-interface-world' ) . '</h1>'
			. '<p class="liw-myl__greet">' . esc_html( $greet ) . '</p>'
			. '</header>';
	}

	/** Wallet-Kachel mit echtem, aber read-only Saldo aus der Plattform-Wallet (falls verfügbar). */
	private static function tile_wallet( int $uid ): string {
		$body = esc_html__( 'Die Plattform-Wallet ist derzeit nicht erreichbar.', 'liebherr-interface-world' );
		$cents = WalletBridge::balance_cents( $uid );
		if ( null !== $cents ) {
			$body = '<span class="liw-myl__balance">' . esc_html( WalletBridge::format_cents( $cents ) ) . '</span>'
				. '<span class="liw-myl__balance-label">' . esc_html__( 'verfügbarer Saldo', 'liebherr-interface-world' ) . '</span>';
		}
		return '<section class="liw-myl__tile liw-myl__tile--wallet"><h2 class="liw-myl__tile-title">' . esc_html__( 'Was besitze ich', 'liebherr-interface-world' ) . '</h2><div class="liw-myl__tile-body">' . $body . '</div></section>';
	}

	private static function tile( string $title, string $body_html ): string {
		return '<section class="liw-myl__tile"><h2 class="liw-myl__tile-title">' . esc_html( $title ) . '</h2><div class="liw-myl__tile-body">' . $body_html . '</div></section>';
	}

	private static function quick_actions_html(): string {
		$actions = [];
		if ( current_user_can( 'read' ) ) {
			$actions[] = [ 'label' => __( 'Wallet öffnen', 'liebherr-interface-world' ), 'url' => admin_url( 'admin.php?page=araliya-lav-wallet-planner' ), 'disabled' => ! current_user_can( 'manage_options' ) && ! current_user_can( 'araliya_view_frontoffice' ) ];
		}
		$adv_id = (int) get_option( 'liw_adventures_page_id', 0 );
		if ( $adv_id > 0 ) {
			$actions[] = [ 'label' => __( 'Adventure einstellen', 'liebherr-interface-world' ), 'url' => (string) get_permalink( $adv_id ), 'disabled' => false ];
		}
		$actions[] = [ 'label' => __( 'Pocket Information', 'liebherr-interface-world' ), 'url' => '', 'disabled' => true ];

		$items = '';
		foreach ( $actions as $a ) {
			if ( ! empty( $a['disabled'] ) || '' === (string) $a['url'] ) {
				$items .= '<li><span class="liw-myl__action is-disabled" aria-disabled="true">' . esc_html( $a['label'] ) . '</span></li>';
				continue;
			}
			$items .= '<li><a class="liw-myl__action" href="' . esc_url( (string) $a['url'] ) . '">' . esc_html( $a['label'] ) . '</a></li>';
		}
		return '<nav class="liw-myl__quick" aria-label="' . esc_attr__( 'Schnellaktionen', 'liebherr-interface-world' ) . '"><h2 class="liw-myl__tile-title">' . esc_html__( 'Schnellaktionen', 'liebherr-interface-world' ) . '</h2><ul class="liw-myl__actions">' . $items . '</ul></nav>';
	}
}
