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

	/** Assets nur auf der My-Liebherr-Seite laden (filemtime-Cache-Buster, da ?ver von WP Rocket entfernt werden kann). */
	public static function assets(): void {
		$page_id = (int) get_option( 'liw_my_liebherr_page_id', 0 );
		if ( $page_id <= 0 || ! is_page( $page_id ) ) {
			return;
		}
		self::assets_for_shortcode();
	}

	/** Enqueue der My-Liebherr-Assets (CSS/JS + REST-Localize) – auch von {@see ProfileView} genutzt. */
	public static function assets_for_shortcode(): void {
		$css = 'assets/css/liw-my-liebherr.css';
		$js  = 'assets/js/liw-my-liebherr.js';
		wp_enqueue_style( 'liw-my-liebherr', LIW_URL . $css . '?v=' . self::bust( $css ), [], null );
		wp_enqueue_script( 'liw-my-liebherr', LIW_URL . $js . '?v=' . self::bust( $js ), [], null, true );
		wp_localize_script( 'liw-my-liebherr', 'liwMyl', [
			'root'  => esc_url_raw( rest_url( Rest::NAMESPACE . '/' ) ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		] );
	}

	private static function bust( string $rel ): string {
		$path = LIW_PATH . $rel;
		return is_readable( $path ) ? (string) filemtime( $path ) : LIW_VERSION;
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

		$uid  = get_current_user_id();
		$ctx  = Context::for_user( $uid );
		$caps = EntitlementService::granted_for( $uid );

		return '<div class="liw-myl">'
			. self::header_html( (string) $ctx['display_name'] )
			. self::widgets_html( $uid, $caps )
			. self::quick_actions_html()
			. ProfileView::render( $uid )
			. '<p class="liw-myl__clockhint">' . esc_html__( 'Ihre Plattformzeit läuft als Session-Uhr unten links mit.', 'liebherr-interface-world' ) . '</p>'
			. '</div>';
	}

	/**
	 * Rendert die berechtigten Dashboard-Widgets in der persönlich gespeicherten Reihenfolge/Sichtbarkeit (§5),
	 * mit Bedienelementen zum Umsortieren/Aus- und Einblenden (serverseitig via PUT /dashboard gespeichert) und
	 * einer Rollen-Reset-Aktion.
	 *
	 * @param array<int,string> $caps
	 */
	private static function widgets_html( int $uid, array $caps ): string {
		$layout = DashboardService::resolve( $caps, DashboardRepository::get( $uid ) );
		$grid   = '';
		$hidden = '';
		foreach ( $layout as $entry ) {
			$key  = (string) $entry['key'];
			$meta = self::widget_meta( $key, $uid );
			if ( null === $meta ) {
				continue;
			}
			if ( empty( $entry['visible'] ) ) {
				$hidden .= '<li class="liw-myl__hidden-item" data-liw-widget="' . esc_attr( $key ) . '">'
					. '<span>' . esc_html( $meta['title'] ) . '</span> '
					. '<button type="button" class="liw-myl__wbtn" data-liw-show>' . esc_html__( 'einblenden', 'liebherr-interface-world' ) . '</button></li>';
				continue;
			}
			$grid .= '<section class="liw-myl__tile" data-liw-widget="' . esc_attr( $key ) . '">'
				. '<div class="liw-myl__wctl">'
				. '<button type="button" class="liw-myl__wbtn" data-liw-move="up" aria-label="' . esc_attr__( 'Nach oben', 'liebherr-interface-world' ) . '">▲</button>'
				. '<button type="button" class="liw-myl__wbtn" data-liw-move="down" aria-label="' . esc_attr__( 'Nach unten', 'liebherr-interface-world' ) . '">▼</button>'
				. '<button type="button" class="liw-myl__wbtn" data-liw-hide aria-label="' . esc_attr__( 'Ausblenden', 'liebherr-interface-world' ) . '">✕</button>'
				. '</div>'
				. '<h2 class="liw-myl__tile-title">' . esc_html( $meta['title'] ) . '</h2>'
				. '<div class="liw-myl__tile-body">' . $meta['body'] . '</div>'
				. '</section>';
		}

		$tray = '' !== $hidden
			? '<div class="liw-myl__hidden"><span class="liw-myl__hidden-title">' . esc_html__( 'Ausgeblendet:', 'liebherr-interface-world' ) . '</span><ul class="liw-myl__hidden-list">' . $hidden . '</ul></div>'
			: '';

		return '<div class="liw-myl__dashboard" data-liw-dashboard>'
			. '<div class="liw-myl__grid">' . $grid . '</div>'
			. $tray
			. '<button type="button" class="liw-myl__reset" data-liw-reset>' . esc_html__( 'Dashboard zurücksetzen', 'liebherr-interface-world' ) . '</button>'
			. '</div>';
	}

	/**
	 * Titel + Rumpf eines Widgets nach Key (nur berechtigte Keys erreichen dies über DashboardService).
	 *
	 * @return array{title:string,body:string}|null
	 */
	private static function widget_meta( string $key, int $uid ): ?array {
		switch ( $key ) {
			case 'wallet':
				return [ 'title' => __( 'Was besitze ich', 'liebherr-interface-world' ), 'body' => self::wallet_body( $uid ) ];
			case 'updates':
				return [ 'title' => __( 'Was ist neu', 'liebherr-interface-world' ), 'body' => esc_html__( 'Neue relevante Updates erscheinen hier, sobald sie verfügbar sind.', 'liebherr-interface-world' ) ];
			case 'tasks':
				return [ 'title' => __( 'Was muss ich tun', 'liebherr-interface-world' ), 'body' => esc_html__( 'Offene Aufgaben und Freigaben werden hier gebündelt.', 'liebherr-interface-world' ) ];
			case 'bookings':
				return [ 'title' => __( 'Gebuchte Leistung', 'liebherr-interface-world' ), 'body' => esc_html__( 'Zuletzt gebuchte Leistungen und Belege erscheinen hier.', 'liebherr-interface-world' ) ];
		}
		return null;
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

	/** Rumpf des Wallet-Widgets mit echtem, aber read-only Saldo aus der Plattform-Wallet (falls verfügbar). */
	private static function wallet_body( int $uid ): string {
		$cents = WalletBridge::balance_cents( $uid );
		if ( null !== $cents ) {
			return '<span class="liw-myl__balance">' . esc_html( WalletBridge::format_cents( $cents ) ) . '</span>'
				. '<span class="liw-myl__balance-label">' . esc_html__( 'verfügbarer Saldo', 'liebherr-interface-world' ) . '</span>';
		}
		return esc_html__( 'Die Plattform-Wallet ist derzeit nicht erreichbar.', 'liebherr-interface-world' );
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
