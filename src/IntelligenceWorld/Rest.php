<?php
/**
 * Liebherr Intelligence World – REST-Schnittstelle der Eintrittsschleuse & Sitzung (Pflichtenheft-2 §4.2/§7).
 *
 * Serverseitige Abrechnungswahrheit (§9): Der Client zeigt nur an, gerechnet und protokolliert wird hier.
 * Endpunkte (Namespace liw-iw/v1):
 *   POST session/start     – Code + Zustimmungen prüfen, Ereignisse loggen, Sitzung starten.
 *   POST session/heartbeat – Aktivitäts-Ping, aktuellen Status zurückgeben.
 *   POST session/end       – Sitzung beenden, Abschlussstatus zurückgeben.
 *   GET  session/status    – aktuellen Sitzungs-/Kostenstatus abrufen.
 *
 * PROTOTYP (§21): Der Code ist ein Demo-Code (kein echtes Login); keine echte Abrechnung/kein Payment.
 * CSRF-Schutz über ein lokalisiertes Nonce (`liw_iw_nonce`).
 *
 * `billing_status()` ist rein (Integer-Minor-Units) und unit-testbar.
 *
 * @package Liebherr\InterfaceWorld\IntelligenceWorld
 * @since   0.1.0-alpha.48
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\IntelligenceWorld;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Rest {

	public const NAMESPACE  = 'liw-iw/v1';
	public const NONCE_NAME = 'liw_iw_nonce';

	public static function register(): void {
		add_action( 'rest_api_init', [ self::class, 'routes' ] );
	}

	public static function routes(): void {
		// PROTOTYP (§21): öffentliche Endpunkte ohne Nonce-Zwang. Grund: Full-Page-Caching (WP Rocket) bäckt
		// ein WP-Nonce in die gecachte Seite ein → nach Cache-Ablauf schlüge die Prüfung fehl und der Eintritt
		// würde fälschlich als „Code ungültig" abgewiesen. Das eigentliche Tor ist der Bestätigungscode; es
		// werden keine echten Kosten/Daten bewegt. Vor Produktivbetrieb: frisches Nonce/Token-Verfahren (Härtung).
		$perm = '__return_true';

		register_rest_route( self::NAMESPACE, '/session/start', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'start' ],
			'permission_callback' => $perm,
		] );
		register_rest_route( self::NAMESPACE, '/session/heartbeat', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'heartbeat' ],
			'permission_callback' => $perm,
		] );
		register_rest_route( self::NAMESPACE, '/session/end', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'end' ],
			'permission_callback' => $perm,
		] );
		register_rest_route( self::NAMESPACE, '/session/status', [
			'methods'             => 'GET',
			'callback'            => [ self::class, 'status' ],
			'permission_callback' => $perm,
		] );
		register_rest_route( self::NAMESPACE, '/session/protocol', [
			'methods'             => 'GET',
			'callback'            => [ self::class, 'protocol' ],
			'permission_callback' => $perm,
		] );
		register_rest_route( self::NAMESPACE, '/session/use', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'use_module' ],
			'permission_callback' => $perm,
		] );
		register_rest_route( self::NAMESPACE, '/session/protocol-pdf', [
			'methods'             => 'GET',
			'callback'            => [ self::class, 'protocol_pdf' ],
			'permission_callback' => $perm,
		] );
	}

	/** Nutzungs-/Kostenprotokoll als serverseitig erzeugtes PDF (§5.5/§8, Download). */
	public static function protocol_pdf( \WP_REST_Request $req ) {
		$code = self::require_session( $req );
		if ( null === $code ) {
			return new \WP_REST_Response( [ 'ok' => false ], 404 );
		}
		$protocol = self::build_protocol( $code, WorldContent::get() );
		$pdf      = PdfDocument::from_lines( 'Liebherr Intelligence World - Nutzungs- und Kostenprotokoll', ProtocolBuilder::to_lines( $protocol ) );
		if ( ! headers_sent() ) {
			header( 'Content-Type: application/pdf' );
			header( 'Content-Disposition: attachment; filename="liebherr-iw-protokoll-' . sanitize_file_name( $code ) . '.pdf"' );
			header( 'Content-Length: ' . strlen( $pdf ) );
			header( 'X-Robots-Tag: noindex' );
		}
		echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput -- Binär-PDF.
		exit;
	}

	/**
	 * Kostenpflichtiges Modul / Rechenlast nutzen (§6.4/§8): schreibt ein Ereignis mit Kosten ins Ledger.
	 * Die Kosten stammen aus {@see ModuleCatalog}; sie erscheinen anschließend als Posten im Protokoll.
	 */
	public static function use_module( \WP_REST_Request $req ): \WP_REST_Response {
		$code = self::require_session( $req );
		if ( null === $code ) {
			return new \WP_REST_Response( [ 'ok' => false ], 200 );
		}
		$row = SessionService::get( $code );
		if ( null === $row || 'active' !== (string) $row['status'] ) {
			return new \WP_REST_Response( [ 'ok' => false, 'error' => 'session_not_active' ], 200 );
		}
		$action = (string) $req->get_param( 'action' );
		if ( ! ModuleCatalog::is_valid( $action ) ) {
			return new \WP_REST_Response( [ 'ok' => false, 'error' => 'unknown_module' ], 200 );
		}
		$cfg   = WorldContent::get();
		$cur   = (string) $cfg['pricing']['currency'];
		$units = max( 1, (int) $req->get_param( 'units' ) );
		$def   = ModuleCatalog::get( $action );
		$cost  = ModuleCatalog::cost_minor( $action, $units, $cur );

		EventLog::append( $code, (string) $def['event'], [
			'metadata' => [
				'action'      => $action,
				'label'       => ModuleCatalog::label( $action ),
				'units'       => $units,
				'cost_minor'  => $cost,
				'currency'    => $cur,
			],
		] );

		return new \WP_REST_Response( [
			'ok'           => true,
			'action'       => $action,
			'label'        => ModuleCatalog::label( $action ),
			'units'        => $units,
			'cost_minor'   => $cost,
			'cost_display' => Money::format( $cost, $cur ),
			'status'       => self::status_for( $code, $cfg ),
		], 200 );
	}

	public static function check_nonce( \WP_REST_Request $req ): bool {
		return false !== wp_verify_nonce( (string) $req->get_header( 'X-LIW-Nonce' ), self::NONCE_NAME );
	}

	// ---------------------------------------------------------------------

	public static function start( \WP_REST_Request $req ): \WP_REST_Response {
		$code    = trim( (string) $req->get_param( 'code' ) );
		$c_terms = self::truthy( $req->get_param( 'consent_terms' ) );
		$c_store = self::truthy( $req->get_param( 'consent_storage' ) );
		$cfg     = WorldContent::get();

		if ( ! $c_terms || ! $c_store ) {
			return new \WP_REST_Response( [ 'ok' => false, 'error' => __( 'Bitte bestätigen Sie beide Pflichterklärungen, um einzutreten.', 'liebherr-interface-world' ) ], 200 );
		}
		if ( ! hash_equals( (string) $cfg['pricing']['access_code'], $code ) ) {
			return new \WP_REST_Response( [ 'ok' => false, 'error' => __( 'Der Bestätigungscode ist nicht gültig.', 'liebherr-interface-world' ) ], 200 );
		}

		$prv  = 'demo-1';
		$code_session = SessionService::start( [ 'user_ref' => get_current_user_id(), 'price_rule_version' => $prv, 'meta' => [ 'prototype' => 1 ] ] );

		// Eintrittsereignisse protokollieren (§11).
		EventLog::append( $code_session, EventTypes::ACCESS_CODE_VERIFIED, [ 'price_rule_version' => $prv ] );
		EventLog::append( $code_session, EventTypes::TERMS_PRESENTED, [ 'price_rule_version' => $prv ] );
		EventLog::append( $code_session, EventTypes::TERMS_ACCEPTED, [ 'price_rule_version' => $prv, 'metadata' => [ 'consent_terms' => true, 'consent_storage' => true ] ] );
		EventLog::append( $code_session, EventTypes::STORAGE_BUDGET_GRANTED, [ 'price_rule_version' => $prv, 'metadata' => [ 'budget_mb' => (int) $cfg['pricing']['storage_budget_mb'] ] ] );

		return new \WP_REST_Response( [
			'ok'           => true,
			'session_code' => $code_session,
			'config'       => self::public_config( $cfg ),
			'status'       => self::status_for( $code_session, $cfg ),
		], 200 );
	}

	public static function heartbeat( \WP_REST_Request $req ): \WP_REST_Response {
		$code = self::require_session( $req );
		if ( null === $code ) {
			return new \WP_REST_Response( [ 'ok' => false ], 200 );
		}
		SessionService::heartbeat( $code );
		return new \WP_REST_Response( [ 'ok' => true, 'status' => self::status_for( $code, WorldContent::get() ) ], 200 );
	}

	public static function end( \WP_REST_Request $req ): \WP_REST_Response {
		$code = self::require_session( $req );
		if ( null === $code ) {
			return new \WP_REST_Response( [ 'ok' => false ], 200 );
		}
		SessionService::end( $code );
		EventLog::append( $code, EventTypes::PROTOCOL_GENERATED, [] );
		return new \WP_REST_Response( [ 'ok' => true, 'status' => self::status_for( $code, WorldContent::get() ), 'final' => true ], 200 );
	}

	public static function status( \WP_REST_Request $req ): \WP_REST_Response {
		$code = self::require_session( $req );
		if ( null === $code ) {
			return new \WP_REST_Response( [ 'ok' => false ], 200 );
		}
		return new \WP_REST_Response( [ 'ok' => true, 'status' => self::status_for( $code, WorldContent::get() ) ], 200 );
	}

	/** Nutzungs-/Kostenprotokoll der Sitzung (§5.5/§8): strukturiert, für JSON-Download + Druck/PDF. */
	public static function protocol( \WP_REST_Request $req ): \WP_REST_Response {
		$code = self::require_session( $req );
		if ( null === $code ) {
			return new \WP_REST_Response( [ 'ok' => false ], 200 );
		}
		return new \WP_REST_Response( [ 'ok' => true, 'protocol' => self::build_protocol( $code, WorldContent::get() ) ], 200 );
	}

	/**
	 * Stellt das Protokoll aus Sitzung + Ledger + Abrechnung zusammen (reine Bauteile in {@see ProtocolBuilder}).
	 *
	 * @param array<string,mixed> $cfg
	 * @return array<string,mixed>
	 */
	public static function build_protocol( string $session_code, array $cfg ): array {
		$session = SessionService::get( $session_code ) ?? [ 'session_code' => $session_code ];
		$events  = EventLog::chain_for( $session_code );
		$active  = (int) ( $session['active_seconds'] ?? 0 );
		$billing = self::billing_status( $active, (int) $cfg['pricing']['base_price_second_minor'], (int) $cfg['pricing']['session_budget_minor'] );
		return ProtocolBuilder::build(
			$session,
			$events,
			$billing,
			(string) $cfg['pricing']['currency'],
			EventLog::verify_chain( $session_code ),
			gmdate( 'Y-m-d\TH:i:s\Z' )
		);
	}

	// ---------------------------------------------------------------------

	/** Reine Kost/Budget-Berechnung (Integer-Minor-Units, Sekundentakt) – unit-testbar. */
	public static function billing_status( int $active_seconds, int $price_second_minor, int $budget_minor ): array {
		$active = max( 0, $active_seconds );
		$base   = $active * max( 0, $price_second_minor ); // Basiskosten pro Sekunde, ganzzahlig
		$pct    = $budget_minor > 0 ? (int) floor( $base * 100 / $budget_minor ) : 0;
		$level  = $pct >= 100 ? 'limit' : ( $pct >= 80 ? 'high' : ( $pct >= 50 ? 'mid' : 'ok' ) );
		return [
			'active_seconds'  => $active,
			'base_cost_minor' => $base,
			'budget_minor'    => max( 0, $budget_minor ),
			'budget_pct'      => $pct,
			'level'           => $level,
		];
	}

	/** hh:mm:ss (auch > 24 h). Rein. */
	public static function format_duration( int $seconds ): string {
		$seconds = max( 0, $seconds );
		return sprintf( '%02d:%02d:%02d', intdiv( $seconds, 3600 ), intdiv( $seconds % 3600, 60 ), $seconds % 60 );
	}

	/** @param array<string,mixed> $cfg */
	private static function status_for( string $session_code, array $cfg ): array {
		$row     = SessionService::get( $session_code );
		$active  = null !== $row ? (int) $row['active_seconds'] : 0;
		$status  = null !== $row ? (string) $row['status'] : 'unknown';
		$price   = (int) $cfg['pricing']['base_price_second_minor'];
		$budget  = (int) $cfg['pricing']['session_budget_minor'];
		$cur     = (string) $cfg['pricing']['currency'];
		$period  = (string) ( $cfg['pricing']['budget_period'] ?? 'month' );
		$b       = self::billing_status( $active, $price, $budget );

		return array_merge( $b, [
			'session_code'         => $session_code,
			'session_status'       => $status,
			'active_display'       => self::format_duration( $active ),
			'base_cost_display'    => Money::format( $b['base_cost_minor'], $cur ),
			'budget_display'       => Money::format( $budget, $cur ) . ' / ' . WorldContent::period_label( $period ),
			'currency'             => $cur,
		] );
	}

	/** @param array<string,mixed> $cfg @return array<string,mixed> */
	private static function public_config( array $cfg ): array {
		$cur    = (string) $cfg['pricing']['currency'];
		$unit   = (string) ( $cfg['pricing']['price_unit'] ?? 'second' );
		$period = (string) ( $cfg['pricing']['budget_period'] ?? 'month' );
		$mb     = (int) $cfg['pricing']['storage_budget_mb'];
		$is_min = (bool) ( $cfg['pricing']['storage_is_minimum'] ?? false );
		return [
			'currency'          => $cur,
			'price_second'      => (int) $cfg['pricing']['base_price_second_minor'],
			'price_display'     => Money::format( (int) $cfg['pricing']['base_price_second_minor'], $cur ) . ' / ' . WorldContent::unit_label( $unit ),
			'session_budget'    => (int) $cfg['pricing']['session_budget_minor'],
			'budget_display'    => Money::format( (int) $cfg['pricing']['session_budget_minor'], $cur ) . ' / ' . WorldContent::period_label( $period ),
			'storage_budget_mb' => $mb,
			'storage_display'   => WorldContent::storage_label( $mb, $is_min ),
		];
	}

	private static function require_session( \WP_REST_Request $req ): ?string {
		$code = trim( (string) $req->get_param( 'session_code' ) );
		if ( '' === $code ) {
			return null;
		}
		return null !== SessionService::get( $code ) ? $code : null;
	}

	/** @param mixed $v */
	private static function truthy( $v ): bool {
		return true === $v || '1' === $v || 1 === $v || 'true' === $v;
	}
}
