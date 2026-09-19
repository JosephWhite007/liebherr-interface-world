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
	private const IMG_OPT     = 'liw_emergency_suitcase_image_id'; // Mediathek-Bild (Koffer) statt Platzhalter-SVG.
	private const USED_TTL   = 900; // Einmal-Sperre einer eingelösten Aufgabe (s).

	/**
	 * URL des Koffer-Icons: gemäß CI-005 das freigegebene Mediathek-Bild (kleine thumbnail-Größe, damit die
	 * hohe Auflösung nicht stört); sonst das Platzhalter-SVG. Bild per Option/Filter
	 * `liw_emergency_suitcase_image_id` gesetzt.
	 */
	public static function icon_url(): string {
		$id = (int) apply_filters( 'liw_emergency_suitcase_image_id', (int) get_option( self::IMG_OPT, 0 ) );
		if ( $id > 0 && \Liebherr\InterfaceWorld\CoreBridge\MediaBridge::is_approved( $id ) ) {
			$url = wp_get_attachment_image_url( $id, 'thumbnail' );
			if ( is_string( $url ) && '' !== $url ) {
				return $url;
			}
		}
		return LIW_URL . 'assets/img/liw-emergency-suitcase.svg';
	}

	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'routes' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'enqueue' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
		add_action( 'wp_footer', [ self::class, 'render_button' ], 99 );
		add_action( 'admin_footer', [ self::class, 'render_button' ], 99 );
		add_action( 'template_redirect', [ self::class, 'maybe_render_fallback' ], 1 ); // JS-freier Pfad, vor redirect_canonical (Prio 10).
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
			'icon'     => esc_url_raw( self::icon_url() ),
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
		$icon  = esc_url( self::icon_url() );
		// Echter Link auf den JS-freien Fallback; das Overlay-JS fängt den Klick ab (progressive Enhancement).
		return '<a href="' . esc_url( self::fallback_url() ) . '" class="' . esc_attr( $cls ) . '" role="button" data-liw-emg aria-label="' . $label . '" title="' . $label . '">'
			. '<span class="liw-emg-dot__ico" style="background-image:url(' . $icon . ')" aria-hidden="true"></span>'
			. '</a>';
	}

	/** Ziel-URL des JS-freien Fallbacks – trägt den aktuellen Ort als Kontext mit. */
	private static function fallback_url(): string {
		$from = is_admin()
			? 'admin'
			: ( isset( $_SERVER['REQUEST_URI'] ) ? (string) strtok( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), '?' ) : '/' );
		return add_query_arg( [ 'liw_help' => 1, 'from' => $from ], home_url( '/' ) );
	}

	// ── JS-freier Fallback (Barrierefreiheit) ────────────────────────────────
	/**
	 * Rendert bei ?liw_help=1 eine eigenständige Emergency-Seite: serverseitiges Aufgaben-Formular (POST) →
	 * bei richtiger Antwort die Emergency-Area. Nutzt dieselben Engines wie der REST-/Overlay-Pfad (keine
	 * Logik-Duplizierung). Läuft nur im Frontend (template_redirect); das Overlay-JS fängt den Klick sonst ab.
	 */
	public static function maybe_render_fallback(): void {
		if ( ! isset( $_GET['liw_help'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- öffentliche Hilfe, kein Statuswechsel
			return;
		}
		if ( ! apply_filters( 'liw_emergency_enabled', true ) ) {
			return;
		}
		$from   = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$secret = self::secret();
		$note   = '';
		$hub    = '';
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';

		if ( 'POST' === $method && isset( $_POST['liw_emg_token'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- signiertes Challenge-Token ersetzt Nonce, kein Statuswechsel
			$token  = sanitize_text_field( wp_unslash( (string) $_POST['liw_emg_token'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$answer = isset( $_POST['liw_emg_answer'] ) ? (int) $_POST['liw_emg_answer'] : PHP_INT_MIN; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$res    = EmergencyChallenge::verify( $token, $answer, $secret, time() );
			if ( $res['ok'] ) {
				$used_key = 'liw_emg_used_' . $res['nonce'];
				if ( '' !== $res['nonce'] && false !== get_transient( $used_key ) ) {
					$note = __( 'Diese Aufgabe wurde bereits verwendet.', 'liebherr-interface-world' );
				} else {
					if ( '' !== $res['nonce'] ) {
						set_transient( $used_key, 1, self::USED_TTL );
					}
					$ctx = ( 'admin' === $from ) ? 'admin' : HelpTopicCatalog::detect( $from, false, '' );
					$hub = self::render_hub( $ctx );
				}
			} elseif ( 'expired' === $res['reason'] ) {
				$note = __( 'Aufgabe abgelaufen. Bitte lösen Sie die neue Aufgabe.', 'liebherr-interface-world' );
			} elseif ( 'wrong' === $res['reason'] ) {
				$note = __( 'Leider falsch. Bitte lösen Sie die neue Aufgabe.', 'liebherr-interface-world' );
			} else {
				$note = __( 'Bitte lösen Sie die Aufgabe.', 'liebherr-interface-world' );
			}
		}

		$challenge = EmergencyChallenge::create( $secret, time() );
		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=utf-8' );
			nocache_headers();
		}
		echo self::fallback_page_html( $from, $note, $hub, $challenge ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in fallback_page_html escaped.
		exit;
	}

	/** Baut die vollständige Fallback-Seite (testbar). Bei gefülltem $hub die Area, sonst das Aufgaben-Formular. */
	public static function fallback_page_html( string $from, string $note, string $hub, array $challenge ): string {
		$css   = esc_url( LIW_URL . 'assets/css/liw-emergency.css' );
		$title = __( 'Hilfe – Emergency-Area', 'liebherr-interface-world' );
		$home  = esc_url( home_url( '/' ) );

		if ( '' !== $hub ) {
			$body = $hub . '<p class="liw-emg-fallback__back"><a href="' . $home . '">' . esc_html( __( 'Zurück zur Seite', 'liebherr-interface-world' ) ) . '</a></p>';
		} else {
			$action = esc_url( add_query_arg( [ 'liw_help' => 1, 'from' => $from ], home_url( '/' ) ) );
			$body   = '<h2 class="liw-emg-modal__title">' . esc_html( $title ) . '</h2>';
			if ( '' !== $note ) {
				$body .= '<p class="liw-emg-note">' . esc_html( $note ) . '</p>';
			}
			$body .= '<p class="liw-emg-ask">' . esc_html( __( 'Bitte lösen Sie zum Eintritt in die Emergency-Area diese Aufgabe:', 'liebherr-interface-world' ) ) . '</p>';
			$body .= '<p class="liw-emg-q">' . esc_html( EmergencyChallenge::question( (int) $challenge['a'], (int) $challenge['b'] ) ) . '</p>';
			$body .= '<form class="liw-emg-form" method="post" action="' . $action . '">';
			$body .= '<input type="hidden" name="liw_emg_token" value="' . esc_attr( (string) $challenge['token'] ) . '" />';
			$body .= '<label class="liw-emg-form__lab" for="liw-emg-answer">' . esc_html( __( 'Ergebnis', 'liebherr-interface-world' ) ) . '</label>';
			$body .= '<input class="liw-emg-form__inp" id="liw-emg-answer" name="liw_emg_answer" type="number" inputmode="numeric" autocomplete="off" required />';
			$body .= '<button class="liw-emg-form__go" type="submit">' . esc_html( __( 'Eintreten', 'liebherr-interface-world' ) ) . '</button>';
			$body .= '</form>';
		}

		return '<!doctype html><html lang="de"><head><meta charset="utf-8" />'
			. '<meta name="viewport" content="width=device-width, initial-scale=1" />'
			. '<meta name="robots" content="noindex,nofollow" />'
			. '<title>' . esc_html( $title ) . '</title>'
			. '<link rel="stylesheet" href="' . $css . '" />'
			. '<style>body.liw-emg-fallback{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#0c0e14;padding:16px;}body.liw-emg-fallback .liw-emg-modal{position:relative;}</style>'
			. '</head><body class="liw-emg-fallback">'
			. '<main class="liw-emg-modal" role="main">' . $body . '</main>'
			. '</body></html>';
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
