<?php
/**
 * Liebherr World – Customer View Flow: REST des begehbaren Durchstichs (ADR-LIW-CVF-001 §13).
 *
 *   POST liw-cvf/v1/begin      – Sitzung starten (anonym) → Schritt „Eingang".
 *   POST liw-cvf/v1/code       – Zugangscode prüfen (AccessService, Lockout) → Schritt „Challenge".
 *   POST liw-cvf/v1/challenge  – Rechenaufgabe prüfen (ChallengeService, einmalig) → „Modulauswahl".
 *   POST liw-cvf/v1/module     – Modul wählen → „First-Entry".
 *   POST liw-cvf/v1/first-entry– First-Entry bestätigen → „done" (Ziel-Modul).
 *
 * Öffentlich + cache-sicher (kein Nonce; signierte Tokens/serverseitige Prüfung ersetzen es). Alles nur
 * aktiv hinter {@see Flags::enabled()} – sonst reason=disabled. Fortschritt ausschließlich über die reine
 * {@see Runtime}; Sichtbarkeit ≠ Autorisierung (§14).
 *
 * @package Liebherr\InterfaceWorld\Cvf
 * @since   0.1.0-alpha.86
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Cvf;

use Liebherr\InterfaceWorld\Frontend\WorldSwitcher;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Rest {

	public const NAMESPACE = 'liw-cvf/v1';

	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'routes' ] );
	}

	public static function routes(): void {
		$post = [ 'methods' => 'POST', 'permission_callback' => '__return_true' ];
		register_rest_route( self::NAMESPACE, '/begin',       $post + [ 'callback' => [ self::class, 'begin' ] ] );
		register_rest_route( self::NAMESPACE, '/code',        $post + [ 'callback' => [ self::class, 'code' ] ] );
		register_rest_route( self::NAMESPACE, '/challenge',   $post + [ 'callback' => [ self::class, 'challenge' ] ] );
		register_rest_route( self::NAMESPACE, '/module',      $post + [ 'callback' => [ self::class, 'module' ] ] );
		register_rest_route( self::NAMESPACE, '/first-entry', $post + [ 'callback' => [ self::class, 'first_entry' ] ] );
	}

	// ── Helfer ────────────────────────────────────────────────────────────────
	private static function anon( \WP_REST_Request $req ): string {
		return sanitize_text_field( (string) $req->get_param( 'anon' ) );
	}

	private static function disabled(): \WP_REST_Response {
		return new \WP_REST_Response( [ 'ok' => false, 'reason' => 'disabled' ], 200 );
	}

	/** Aktive Module des Durchstichs = die vier Inseln (Wiederverwendung WorldSwitcher, keine Redundanz). */
	public static function modules(): array {
		$out = [];
		foreach ( WorldSwitcher::worlds() as $key => $w ) {
			$out[] = [ 'key' => (string) $key, 'label' => (string) $w['label'], 'url' => (string) $w['url'] ];
		}
		return $out;
	}

	private static function module_by_key( string $key ): ?array {
		foreach ( self::modules() as $m ) {
			if ( $m['key'] === $key ) {
				return $m;
			}
		}
		return null;
	}

	/**
	 * Baut die Antwort für einen Sitzungszustand inkl. der schrittabhängigen Extras.
	 *
	 * @param array<string,mixed> $extra
	 */
	private static function respond( int $session_id, string $state, array $extra = [], bool $ok = true ): \WP_REST_Response {
		$type = Steps::type_for( $state );
		if ( 'challenge' === $type && ! isset( $extra['challenge'] ) ) {
			$extra['challenge'] = self::new_challenge();
		}
		if ( 'module_select' === $type && ! isset( $extra['modules'] ) ) {
			$extra['modules'] = self::modules();
		}
		return new \WP_REST_Response( [
			'ok'      => $ok,
			'session' => $session_id,
			'step'    => Steps::describe( $state, $extra ),
		], 200 );
	}

	/** Erzeugt eine neue Challenge in der von der aktiven Config vorgegebenen Schwierigkeit. */
	private static function new_challenge(): array {
		$active = WorkflowRepository::ensure_active();
		$diff   = Runtime::challenge_difficulty( $active['config'] );
		$c      = ChallengeService::create( AccessService::secret(), time(), $diff );
		return [
			'a'        => $c['a'],
			'b'        => $c['b'],
			'question' => ChallengeService::question( $c['a'], $c['b'] ),
			'token'    => $c['token'],
			'expires'  => $c['expires'],
		];
	}

	// ── Endpunkte ───────────────────────────────────────────────────────────────
	public static function begin( \WP_REST_Request $req ): \WP_REST_Response {
		if ( ! Flags::enabled() ) {
			return self::disabled();
		}
		$anon = self::anon( $req );
		if ( '' === $anon ) {
			return new \WP_REST_Response( [ 'ok' => false, 'reason' => 'no_visitor' ], 200 );
		}
		// Zugangscode-Prototyp: falls noch keiner konfiguriert, aus dem IW-Demo-Code seeden.
		AccessService::ensure_configured( self::default_code() );
		$sess = SessionRepository::start( $anon );
		return self::respond( (int) $sess['id'], (string) $sess['state'] );
	}

	public static function code( \WP_REST_Request $req ): \WP_REST_Response {
		if ( ! Flags::enabled() ) {
			return self::disabled();
		}
		$anon = self::anon( $req );
		$sess = SessionRepository::get_by_visitor( $anon );
		if ( null === $sess ) {
			return new \WP_REST_Response( [ 'ok' => false, 'reason' => 'no_session' ], 200 );
		}
		$att = AccessService::attempt( (string) $req->get_param( 'code' ), $anon );
		if ( ! $att['ok'] ) {
			return new \WP_REST_Response( [ 'ok' => false, 'reason' => $att['reason'], 'remaining' => $att['remaining'] ], 200 );
		}
		$adv = SessionRepository::advance( (int) $sess['id'], Runtime::EV_CODE_OK );
		return self::respond( (int) $sess['id'], $adv['state'], [], 'ok' === $adv['reason'] );
	}

	public static function challenge( \WP_REST_Request $req ): \WP_REST_Response {
		if ( ! Flags::enabled() ) {
			return self::disabled();
		}
		$anon = self::anon( $req );
		$sess = SessionRepository::get_by_visitor( $anon );
		if ( null === $sess ) {
			return new \WP_REST_Response( [ 'ok' => false, 'reason' => 'no_session' ], 200 );
		}
		$res = ChallengeService::verify( (string) $req->get_param( 'token' ), (int) $req->get_param( 'answer' ), AccessService::secret(), time() );
		if ( ! $res['ok'] ) {
			return new \WP_REST_Response( [ 'ok' => false, 'reason' => $res['reason'], 'step' => Steps::describe( VisitorState::AT_CHALLENGE, [ 'challenge' => self::new_challenge() ] ) ], 200 );
		}
		$used = 'liw_cvf_ch_used_' . $res['nonce'];
		if ( '' !== $res['nonce'] && false !== get_transient( $used ) ) {
			return new \WP_REST_Response( [ 'ok' => false, 'reason' => 'used' ], 200 );
		}
		if ( '' !== $res['nonce'] ) {
			set_transient( $used, 1, ChallengeService::DEFAULT_TTL );
		}
		$adv = SessionRepository::advance( (int) $sess['id'], Runtime::EV_CHALLENGE_OK );
		return self::respond( (int) $sess['id'], $adv['state'], [], 'ok' === $adv['reason'] );
	}

	public static function module( \WP_REST_Request $req ): \WP_REST_Response {
		if ( ! Flags::enabled() ) {
			return self::disabled();
		}
		$anon = self::anon( $req );
		$sess = SessionRepository::get_by_visitor( $anon );
		if ( null === $sess ) {
			return new \WP_REST_Response( [ 'ok' => false, 'reason' => 'no_session' ], 200 );
		}
		$module = self::module_by_key( sanitize_text_field( (string) $req->get_param( 'module' ) ) );
		if ( null === $module ) {
			return new \WP_REST_Response( [ 'ok' => false, 'reason' => 'unknown_module' ], 200 );
		}
		$adv = SessionRepository::advance( (int) $sess['id'], Runtime::EV_MODULE_SELECTED );
		return self::respond( (int) $sess['id'], $adv['state'], [ 'module' => $module ], 'ok' === $adv['reason'] );
	}

	public static function first_entry( \WP_REST_Request $req ): \WP_REST_Response {
		if ( ! Flags::enabled() ) {
			return self::disabled();
		}
		$anon = self::anon( $req );
		$sess = SessionRepository::get_by_visitor( $anon );
		if ( null === $sess ) {
			return new \WP_REST_Response( [ 'ok' => false, 'reason' => 'no_session' ], 200 );
		}
		$module = self::module_by_key( sanitize_text_field( (string) $req->get_param( 'module' ) ) );
		$adv    = SessionRepository::advance( (int) $sess['id'], Runtime::EV_FIRST_ENTRY_DONE );
		$extra  = ( null !== $module ) ? [ 'target' => $module['url'], 'module' => $module ] : [];
		return self::respond( (int) $sess['id'], $adv['state'], $extra, 'ok' === $adv['reason'] );
	}

	/** Standard-Zugangscode für den Prototyp (aus der IW-Welt-Option, sonst LIEBHERR-DEMO). */
	private static function default_code(): string {
		$world = get_option( 'liw_iw_world', [] );
		$code  = is_array( $world ) && isset( $world['access_code'] ) ? (string) $world['access_code'] : '';
		return '' !== $code ? $code : 'LIEBHERR-DEMO';
	}
}
