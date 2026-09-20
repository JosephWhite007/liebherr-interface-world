<?php
/**
 * Liebherr World – Plattformzeit: plattformweite Sperre bei Standby (ADR-LIW-MYL-002 §5, Pflichtenheft §41.8).
 *
 * Der Server ist die Wahrheit: Solange die eigene Sitzung im Standby (`paused`) ist, werden die gated
 * REST-Routen der Plattform (my-liebherr/v1 + pocket/v1) mit HTTP 423 „Locked" beantwortet – die
 * Plattformzeit-Steuerroute (platform-time/*) bleibt frei, damit die Rückkehr (Rechenaufgabe → resume)
 * möglich ist. Im Frontend wird zusätzlich das Standby-Overlay eingeblendet ({@see LockOverlay}); die
 * „Liebherr World"-Leiste und der Sprachumschalter schweben darüber und bleiben bedienbar
 * (gemeinsamer Helfer `LiwWorldbarLock`, CSS-Muster `html.liw-intro-lock`).
 *
 * Umfang (Festlegung 3): Die REST-Sperre wirkt für alle Welten (sie alle nutzen die gated Namespaces).
 * Das Overlay wird auf dem Frontend ausgegeben, wo die „Liebherr World"-Leiste existiert; die Enforcement
 * (kein gated Datenfluss) gilt unabhängig davon überall. Nur wirksam bei {@see Flags::lock_enabled()},
 * angemeldet und mit My-Liebherr-Zugang.
 *
 * @package Liebherr\InterfaceWorld\PlatformTime
 * @since   0.1.0-alpha.139
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\PlatformTime;

use Liebherr\InterfaceWorld\MyLiebherr\Roles as MylRoles;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class LockGuard {

	public static function register(): void {
		add_filter( 'rest_pre_dispatch', [ self::class, 'guard_rest' ], 10, 3 );
		add_action( 'wp_enqueue_scripts', [ self::class, 'assets' ] );
		add_action( 'wp_footer', [ self::class, 'render_overlay' ] );
	}

	/** Grundvoraussetzung: Feature + Sperre scharf, angemeldet, My-Liebherr-Zugang. */
	private static function eligible(): bool {
		return Flags::lock_enabled() && is_user_logged_in() && current_user_can( MylRoles::CAP_ACCESS );
	}

	/** Ist die eigene Sitzung gerade gesperrt (Standby)? */
	public static function is_locked(): bool {
		if ( ! self::eligible() ) {
			return false;
		}
		return '' !== SessionRepository::lock_state( get_current_user_id() );
	}

	// ── REST-Enforcement ────────────────────────────────────────────────────

	/**
	 * Blockt gated Plattform-REST-Routen während des Standby mit 423. Die Plattformzeit-Steuerung
	 * (platform-time/*) bleibt frei, damit die Rückkehr möglich ist.
	 *
	 * @param mixed            $result  Bisheriges Ergebnis (null = weiter dispatchen).
	 * @param \WP_REST_Server  $server  REST-Server.
	 * @param \WP_REST_Request $request Anfrage.
	 * @return mixed
	 */
	public static function guard_rest( $result, $server, $request ) {
		unset( $server );
		if ( null !== $result ) {
			return $result; // Ein anderer Filter hat bereits entschieden.
		}
		if ( ! ( $request instanceof \WP_REST_Request ) ) {
			return $result;
		}
		$route = (string) $request->get_route();
		if ( ! self::is_gated_route( $route ) ) {
			return $result;
		}
		if ( ! self::is_locked() ) {
			return $result;
		}
		return new \WP_Error(
			'liw_platform_locked',
			__( 'Die Plattform ist im Standby gesperrt. Bitte zuerst über die Zeit-Uhr die Rechenaufgabe lösen.', 'liebherr-interface-world' ),
			[ 'status' => 423, 'reason' => 'standby' ]
		);
	}

	/** Gated = Plattform-App-Routen (alle Welten), aber NICHT die Plattformzeit-Steuerung. */
	private static function is_gated_route( string $route ): bool {
		$route = '/' . ltrim( $route, '/' );
		if ( 0 === strpos( $route, '/my-liebherr/v1/platform-time' ) ) {
			return false; // Steuerung/Rückkehr immer erreichbar.
		}
		return ( 0 === strpos( $route, '/my-liebherr/v1' ) || 0 === strpos( $route, '/pocket/v1' ) );
	}

	// ── Frontend-Overlay (Anzeige) ────────────────────────────────────────────

	private static function bust( string $rel ): string {
		$path = LIW_PATH . $rel;
		return LIW_URL . $rel . '?v=' . ( is_readable( $path ) ? (string) filemtime( $path ) : LIW_VERSION );
	}

	public static function assets(): void {
		if ( is_admin() || ! self::is_locked() ) {
			return;
		}
		// „Liebherr World"-Leiste + Sperr-CSS: liebherr-frontend.css trägt html.liw-intro-lock/.liw-switcher.
		// Gleicher Handle wie FrontendAssets → kein Doppel-Load, wenn die Seite es ohnehin lädt.
		if ( ! wp_style_is( 'liw-frontend', 'enqueued' ) ) {
			wp_enqueue_style( 'liw-frontend', self::bust( 'assets/css/liebherr-frontend.css' ), [], null );
		}
		wp_enqueue_style( 'liw-ptime-lock', self::bust( 'assets/css/liw-ptime-lock.css' ), [ 'liw-frontend' ], null );
		wp_enqueue_script( 'liw-worldbar-lock', self::bust( 'assets/js/liw-worldbar-lock.js' ), [], null, true );
		wp_enqueue_script( 'liw-ptime-lock', self::bust( 'assets/js/liw-ptime-lock.js' ), [ 'liw-worldbar-lock' ], null, true );
		wp_localize_script( 'liw-ptime-lock', 'liwPtimeLock', [
			'root'  => esc_url_raw( rest_url( Rest::NAMESPACE . '/platform-time/' ) ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'i18n'  => [
				'wrong'   => __( 'Leider falsch. Bitte erneut versuchen.', 'liebherr-interface-world' ),
				'expired' => __( 'Aufgabe abgelaufen – eine neue wird geladen.', 'liebherr-interface-world' ),
				'error'   => __( 'Es ist ein Fehler aufgetreten. Bitte erneut versuchen.', 'liebherr-interface-world' ),
			],
		] );
	}

	public static function render_overlay(): void {
		if ( is_admin() || ! self::is_locked() ) {
			return;
		}
		echo LockOverlay::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in LockOverlay::render() escaped.
	}
}
