<?php
/**
 * Liebherr World – Plattformzeit: schwebende Session-Uhr / „Schachuhr" (Pflichtenheft My Liebherr §41.6, S11).
 *
 * Plattformweit schwebendes, jederzeit ein-/ausklappbares Widget (unten links, kollidiert nicht mit dem
 * Hilfe-Koffer unten rechts). Zeigt verstrichene aktive Zeit + laufende Tokenkosten; die Anzeige tickt lokal,
 * maßgeblich ist der serverautoritäre Heartbeat (§41.1). Nur für angemeldete Nutzer mit
 * {@see \Liebherr\InterfaceWorld\MyLiebherr\Roles::CAP_ACCESS} und nur bei {@see Flags::enabled()}. Muster wie
 * der Emergency-Koffer (Front + wp-admin).
 *
 * @package Liebherr\InterfaceWorld\PlatformTime
 * @since   0.1.0-alpha.112
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\PlatformTime;

use Liebherr\InterfaceWorld\MyLiebherr\Roles as MylRoles;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ClockWidget {

	public static function register(): void {
		add_action( 'wp_enqueue_scripts', [ self::class, 'assets' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'assets' ] );
		add_action( 'wp_footer', [ self::class, 'render' ] );
		add_action( 'admin_footer', [ self::class, 'render' ] );
	}

	/** Uhr sichtbar? Nur scharf geschaltet, angemeldet und mit My-Liebherr-Zugang. */
	public static function gate(): bool {
		return Flags::enabled() && is_user_logged_in() && current_user_can( MylRoles::CAP_ACCESS );
	}

	private static function bust( string $rel ): string {
		$path = LIW_PATH . $rel;
		return LIW_URL . $rel . '?v=' . ( is_readable( $path ) ? (string) filemtime( $path ) : LIW_VERSION );
	}

	public static function assets(): void {
		if ( ! self::gate() ) {
			return;
		}
		wp_enqueue_style( 'liw-ptime-clock', self::bust( 'assets/css/liw-ptime-clock.css' ), [], null );
		wp_enqueue_script( 'liw-ptime-clock', self::bust( 'assets/js/liw-ptime-clock.js' ), [], null, true );
		wp_localize_script( 'liw-ptime-clock', 'liwPtime', [
			'root'     => esc_url_raw( rest_url( Rest::NAMESPACE . '/platform-time/' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'interval' => 30, // Heartbeat-Intervall (Sekunden); serverautoritär (§41.1).
			'i18n'     => [
				'title'   => __( 'Plattformzeit', 'liebherr-interface-world' ),
				'tokens'  => __( 'Token', 'liebherr-interface-world' ),
				'stop'    => __( 'Sitzung beenden', 'liebherr-interface-world' ),
				'hide'    => __( 'Ausblenden', 'liebherr-interface-world' ),
				'show'    => __( 'Plattformzeit', 'liebherr-interface-world' ),
				'stopped' => __( 'Sitzung beendet', 'liebherr-interface-world' ),
			],
		] );
	}

	public static function render(): void {
		if ( ! self::gate() ) {
			return;
		}
		echo '<div class="liw-ptime" data-liw-ptime hidden>'
			. '<button type="button" class="liw-ptime__toggle" data-liw-ptime-toggle aria-expanded="true">'
			. '<span class="liw-ptime__dot" aria-hidden="true"></span>'
			. '<span class="liw-ptime__label">' . esc_html__( 'Plattformzeit', 'liebherr-interface-world' ) . '</span>'
			. '</button>'
			. '<div class="liw-ptime__panel" data-liw-ptime-panel>'
			. '<span class="liw-ptime__time" data-liw-ptime-time>00:00:00</span>'
			. '<span class="liw-ptime__tokens"><strong data-liw-ptime-tokens>0</strong> ' . esc_html__( 'Token', 'liebherr-interface-world' ) . '</span>'
			. '<button type="button" class="liw-ptime__stop" data-liw-ptime-stop>' . esc_html__( 'Sitzung beenden', 'liebherr-interface-world' ) . '</button>'
			. '</div></div>';
	}
}
