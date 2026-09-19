<?php
/**
 * Liebherr World – Customer View Flow: begehbarer Durchstich im Frontend (ADR-LIW-CVF-001 §5/§7).
 *
 * Shortcode `[liw_cvf_flow]` rendert den vertikalen Durchstich (Eingang → Challenge → Modulauswahl →
 * First-Entry → Modul). Nur aktiv hinter {@see Flags::enabled()}; sonst leer (bzw. dezenter Hinweis für
 * Administratoren). Die Schritte laufen über die {@see Rest}-Endpunkte; ohne Freigabe bleibt der bestehende
 * IW-Eintritt unberührt.
 *
 * @package Liebherr\InterfaceWorld\Cvf
 * @since   0.1.0-alpha.86
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Cvf;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FlowView {

	public const SHORTCODE = 'liw_cvf_flow';
	public const HANDLE    = 'liw-cvf-flow';

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'maybe_enqueue' ] );
		add_filter( 'style_loader_src', [ self::class, 'bust' ], 9999, 2 );
		add_filter( 'script_loader_src', [ self::class, 'bust' ], 9999, 2 );
	}

	private static function ver( string $rel ): string {
		$m = is_readable( LIW_PATH . $rel ) ? (int) filemtime( LIW_PATH . $rel ) : 0;
		return $m > 0 ? (string) $m : LIW_VERSION;
	}

	public static function bust( $src, $handle ) {
		if ( self::HANDLE !== $handle || ! is_string( $src ) || false !== strpos( $src, 'v=' ) ) {
			return $src;
		}
		foreach ( [ 'assets/css/liw-cvf-flow.css', 'assets/js/liw-cvf-flow.js' ] as $rel ) {
			if ( false !== strpos( $src, $rel ) ) {
				return add_query_arg( 'v', self::ver( $rel ), $src );
			}
		}
		return $src;
	}

	private static function has_shortcode_on_page(): bool {
		$post = get_post();
		return $post instanceof \WP_Post && has_shortcode( (string) $post->post_content, self::SHORTCODE );
	}

	public static function maybe_enqueue(): void {
		if ( ! Flags::enabled() || ! self::has_shortcode_on_page() ) {
			return;
		}
		wp_enqueue_style( self::HANDLE, LIW_URL . 'assets/css/liw-cvf-flow.css', [], self::ver( 'assets/css/liw-cvf-flow.css' ) );
		wp_enqueue_script( self::HANDLE, LIW_URL . 'assets/js/liw-cvf-flow.js', [], self::ver( 'assets/js/liw-cvf-flow.js' ), true );
		wp_localize_script( self::HANDLE, 'liwCvf', [
			'rest' => esc_url_raw( rest_url( Rest::NAMESPACE . '/' ) ),
			'i18n' => [
				'entryTitle'   => __( 'Eingang – Zugangscode', 'liebherr-interface-world' ),
				'entryLead'    => __( 'Bitte geben Sie Ihren Zugangscode ein.', 'liebherr-interface-world' ),
				'code'         => __( 'Zugangscode', 'liebherr-interface-world' ),
				'continue'     => __( 'Weiter', 'liebherr-interface-world' ),
				'challengeLead'=> __( 'Bitte lösen Sie zur Bestätigung diese Aufgabe:', 'liebherr-interface-world' ),
				'answer'       => __( 'Ergebnis', 'liebherr-interface-world' ),
				'moduleTitle'  => __( 'Modul wählen', 'liebherr-interface-world' ),
				'moduleLead'   => __( 'Wählen Sie den Bereich, in den Sie eintreten möchten.', 'liebherr-interface-world' ),
				'firstEntry'   => __( 'Willkommen – bereit zum Eintritt.', 'liebherr-interface-world' ),
				'enter'        => __( 'Eintreten', 'liebherr-interface-world' ),
				'done'         => __( 'Zugang gewährt. Sie werden weitergeleitet …', 'liebherr-interface-world' ),
				'open'         => __( 'Modul öffnen', 'liebherr-interface-world' ),
				'blocked'      => __( 'Der Zugang wurde blockiert.', 'liebherr-interface-world' ),
				'wrongCode'    => __( 'Zugangscode falsch.', 'liebherr-interface-world' ),
				'locked'       => __( 'Zu viele Versuche – bitte später erneut.', 'liebherr-interface-world' ),
				'wrongCalc'    => __( 'Leider falsch – neue Aufgabe.', 'liebherr-interface-world' ),
				'error'        => __( 'Es ist ein Fehler aufgetreten.', 'liebherr-interface-world' ),
				'loading'      => __( 'Bitte warten …', 'liebherr-interface-world' ),
			],
		] );
	}

	public static function shortcode(): string {
		if ( ! Flags::enabled() ) {
			if ( current_user_can( 'manage_options' ) ) {
				return '<div class="liw-cvf liw-cvf--off"><p>' . esc_html__( 'Customer View Flow ist deaktiviert (Flag liw_cvf_enabled). Nur für Administratoren sichtbar.', 'liebherr-interface-world' ) . '</p></div>';
			}
			return '';
		}
		return '<div class="liw-cvf" data-liw-cvf><p class="liw-cvf__loading">' . esc_html__( 'Bitte warten …', 'liebherr-interface-world' ) . '</p></div>';
	}
}
