<?php
/**
 * Liebherr Emergency – Hilfe-Koffer & Emergency-Area (plattformweiter Hilfeassistent).
 *
 * Ein winziger oranger Koffer-Punkt erscheint auf der GESAMTEN Plattform (Frontend UND wp-admin, plus per
 * Shortcode [liw_emergency_suitcase] an beliebigen Stellen). Ein Klick öffnet IMMER zuerst das Overlay-Plugin,
 * das nach der Emergency-Zahl fragt – zwei EINSTELLIGE Zahlen (nicht die zweistellige Addition der großen
 * Gates). Erst nach richtiger Antwort öffnet sich die Emergency-Area: ein kontextbezogener Hilfe-Hub zum Ort,
 * an dem man gerade ist (HelpTopicCatalog). Serverseitige Verifikation (EmergencyChallenge, signiert + TTL +
 * Einmalgebrauch über Transient); die Lösung verlässt den Server nie.
 *
 * @package Liebherr\InterfaceWorld\Emergency
 * @since   0.1.0-alpha.78
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Emergency;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class EmergencyController {

	public const NAMESPACE   = 'liw-emg/v1';
	public const HANDLE      = 'liw-emergency';
	public const SHORTCODE   = 'liw_emergency_suitcase';
	private const SECRET_OPT = 'liw_emg_secret';
	private const USED_TTL   = 900; // Einmal-Sperre einer eingelösten Aufgabe (s).

	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'routes' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
		add_action( 'wp_footer', [ self::class, 'render_button' ], 99 );
		add_action( 'admin_footer', [ self::class, 'render_button' ], 99 );
		add_shortcode( self::SHORTCODE, [ self::class, 'shortcode' ] );
		add_filter( 'style_loader_src', [ self::class, 'bust' ], 9999, 2 );
		add_filter( 'script_loader_src', [ self::class, 'bust' ], 9999, 2 );
	}

	// ── Secret ──────────────────────────────────────────────────────────────
	private static function secret(): string {
		$s = (string) get_option( self::SECRET_OPT, '' );
		if ( '' === $s ) {
			$s = bin2hex( random_bytes( 32 ) );
			update_option( self::SECRET_OPT, $s, false );
		}
		return $s;
	}

	// ── Assets ──────────────────────────────────────────────────────────────
	private static function ver( string $rel ): string {
		$m = is_readable( LIW_PATH . $rel ) ? (int) filemtime( LIW_PATH . $rel ) : 0;
		return $m > 0 ? (string) $m : LIW_VERSION;
	}

	public static function bust( $src, $handle ) {
		if ( self::HANDLE !== $handle || ! is_string( $src ) || false !== strpos( $src, 'v=' ) ) {
			return $src;
		}
		foreach ( [ 'assets/css/liw-emergency.css', 'assets/js/liw-emergency.js' ] as $rel ) {
			if ( false !== strpos( $src, $rel ) ) {
				return add_query_arg( 'v', self::ver( $rel ), $src );
			}
		}
		return $src;
	}

	public static function enqueue(): void {
		if ( ! apply_filters( 'liw_emergency_enabled', true ) ) {
			return;
		}
		wp_enqueue_style( self::HANDLE, LIW_URL . 'assets/css/liw-emergency.css', [], self::ver( 'assets/css/liw-emergency.css' ) );
		wp_enqueue_script( self::HANDLE, LIW_URL . 'assets/js/liw-emergency.js', [], self::ver( 'assets/js/liw-emergency.js' ), true );
		wp_localize_script( self::HANDLE, 'liwEmg', [
			'rest'     => esc_url_raw( rest_url( self::NAMESPACE . '/' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'isAdmin'  => is_admin(),
			'adminPage'=> isset( $_GET['page'] ) ? sanitize_key( wp_unslash( (string) $_GET['page'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'icon'     => esc_url_raw( LIW_URL . 'assets/img/liw-emergency-suitcase.svg' ),
			'i18n'     => [
				'title'    => __( 'Hilfe – Emergency', 'liebherr-interface-world' ),
				'ask'      => __( 'Bitte lösen Sie zum Eintritt in die Emergency-Area diese Aufgabe:', 'liebherr-interface-world' ),
				'answer'   => __( 'Ergebnis', 'liebherr-interface-world' ),
				'enter'    => __( 'Eintreten', 'liebherr-interface-world' ),
				'close'    => __( 'Schließen', 'liebherr-interface-world' ),
				'wrong'    => __( 'Leider falsch. Neue Aufgabe …', 'liebherr-interface-world' ),
				'expired'  => __( 'Aufgabe abgelaufen. Neue Aufgabe …', 'liebherr-interface-world' ),
				'error'    => __( 'Es ist ein Fehler aufgetreten. Bitte erneut versuchen.', 'liebherr-interface-world' ),
				'loading'  => __( 'Aufgabe wird geladen …', 'liebherr-interface-world' ),
			],
		] );
	}

	// ── Button ──────────────────────────────────────────────────────────────
	/** Fester, winziger Koffer-Punkt (unten rechts) – auf jeder Seite. */
	public static function render_button(): void {
		if ( ! apply_filters( 'liw_emergency_enabled', true ) ) {
			return;
		}
		echo self::button_html( true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- statisch/escaped.
	}

	/** Shortcode: platziert denselben Koffer-Punkt inline an beliebiger Stelle. */
	public static function shortcode(): string {
		return self::button_html( false );
	}

	private static function button_html( bool $floating ): string {
		$cls   = 'liw-emg-dot' . ( $floating ? ' liw-emg-dot--float' : ' liw-emg-dot--inline' );
		$label = esc_attr__( 'Hilfe – Emergency-Area öffnen', 'liebherr-interface-world' );
		$icon  = esc_url( LIW_URL . 'assets/img/liw-emergency-suitcase.svg' );
		return '<button type="button" class="' . esc_attr( $cls ) . '" data-liw-emg aria-label="' . $label . '" title="' . $label . '">'
			. '<span class="liw-emg-dot__ico" style="background-image:url(' . $icon . ')" aria-hidden="true"></span>'
			. '</button>';
	}

	// ── REST ──────────────────────────────────────────────────────────────
	public static function routes(): void {
		register_rest_route( self::NAMESPACE, '/challenge', [
			'methods'             => 'GET',
			'callback'            => [ self::class, 'challenge' ],
			'permission_callback' => '__return_true',
		] );
		register_rest_route( self::NAMESPACE, '/verify', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'verify' ],
			'permission_callback' => '__return_true',
		] );
	}

	/** GET /challenge – liefert zwei einstellige Summanden + signiertes Token (Summe bleibt serverseitig). */
	public static function challenge( \WP_REST_Request $req ): \WP_REST_Response {
		unset( $req );
		$c = EmergencyChallenge::create( self::secret(), time() );
		return new \WP_REST_Response( [
			'a'        => $c['a'],
			'b'        => $c['b'],
			'question' => EmergencyChallenge::question( $c['a'], $c['b'] ),
			'token'    => $c['token'],
			'expires'  => $c['expires'],
		], 200 );
	}

	/** POST /verify {token, answer, path, admin, page} – prüft und liefert bei Erfolg die Emergency-Area. */
	public static function verify( \WP_REST_Request $req ): \WP_REST_Response {
		$token  = (string) $req->get_param( 'token' );
		$answer = (int) $req->get_param( 'answer' );
		$res    = EmergencyChallenge::verify( $token, $answer, self::secret(), time() );

		if ( ! $res['ok'] ) {
			return new \WP_REST_Response( [ 'ok' => false, 'reason' => $res['reason'] ], 200 );
		}
		// Einmalgebrauch: eingelöste Nonce sperren (Replay-Schutz).
		$used_key = 'liw_emg_used_' . $res['nonce'];
		if ( '' !== $res['nonce'] && false !== get_transient( $used_key ) ) {
			return new \WP_REST_Response( [ 'ok' => false, 'reason' => 'used' ], 200 );
		}
		if ( '' !== $res['nonce'] ) {
			set_transient( $used_key, 1, self::USED_TTL );
		}

		$path  = (string) $req->get_param( 'path' );
		$admin = (bool) $req->get_param( 'admin' );
		$page  = (string) $req->get_param( 'page' );
		$ctx   = HelpTopicCatalog::detect( $path, $admin, $page );

		return new \WP_REST_Response( [
			'ok'  => true,
			'ctx' => $ctx,
			'hub' => self::render_hub( $ctx ),
		], 200 );
	}

	/** Rendert die Emergency-Area (kontextbezogener Hilfe-Hub) als sicheres HTML. */
	public static function render_hub( string $ctx_key ): string {
		$topic = HelpTopicCatalog::topic( $ctx_key );
		$out   = '<section class="liw-emg-hub" aria-labelledby="liw-emg-hub-h">';
		$out  .= '<h2 class="liw-emg-hub__title" id="liw-emg-hub-h">' . esc_html( (string) $topic['title'] ) . '</h2>';
		$out  .= '<p class="liw-emg-hub__lead">' . esc_html( (string) $topic['intro'] ) . '</p>';
		$out  .= '<ol class="liw-emg-hub__steps" role="list">';
		foreach ( $topic['steps'] as $s ) {
			$out .= '<li class="liw-emg-hub__step"><details><summary>' . esc_html( (string) $s['title'] ) . '</summary><p>' . esc_html( (string) $s['text'] ) . '</p></details></li>';
		}
		$out .= '</ol></section>';
		return $out;
	}
}
