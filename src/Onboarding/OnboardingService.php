<?php
/**
 * Liebherr Interface Solutions – Onboarding Service
 *
 * Geschäftslogik für Partner-Onboarding-Anfragen (§22). Legt bei Eingang einen Core-Partner
 * an (CoreBridge\PartnerBridge, status='pending') und die Liebherr-Zusatzfelder
 * (OnboardingSchema). Freigabe (approve/reject) spiegelt den Status auf beide Tabellen.
 *
 * @package Liebherr\InterfaceWorld\Onboarding
 * @since   0.1.0-alpha.6
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Onboarding;

use Liebherr\InterfaceWorld\Consent\ConsentLogService;
use Liebherr\InterfaceWorld\CoreBridge\AuditBridge;
use Liebherr\InterfaceWorld\CoreBridge\PartnerBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class OnboardingService {

	public const PARTNER_TYPES = [ 'dealer', 'supplier', 'customer' ];
	public const STATUSES      = [ 'new', 'in_review', 'approved', 'rejected' ];

	/** Feinschliff alpha.10: Seitengröße für die Admin-Liste (vorher: ungebremster JOIN ohne LIMIT). */
	public const REQUESTS_PER_PAGE = 20;

	/** Aktuelle Fassung des Datenschutztexts, mit dem das Formular Einwilligung einholt (§22/§24). */
	public const PRIVACY_TEXT_VERSION = '2026-09-18-v1';

	/**
	 * @param array{
	 *   name:string, contact_email:string, contact_name?:string, contact_phone?:string,
	 *   city?:string, website_url?:string, liw_partner_type?:string,
	 *   requested_interfaces?:string, message?:string, privacy_consent:bool
	 * } $data
	 */
	public static function submit_request( array $data ): int|\WP_Error {
		$name  = sanitize_text_field( $data['name'] ?? '' );
		$email = sanitize_email( $data['contact_email'] ?? '' );

		if ( '' === $name || ! is_email( $email ) ) {
			return new \WP_Error( 'liw_invalid_input', __( 'Name und eine gültige E-Mail-Adresse sind Pflichtfelder.', 'liebherr-interface-world' ) );
		}

		if ( empty( $data['privacy_consent'] ) ) {
			return new \WP_Error( 'liw_consent_required', __( 'Der Datenschutzhinweis muss bestätigt werden.', 'liebherr-interface-world' ) );
		}

		$partner_type = in_array( $data['liw_partner_type'] ?? 'dealer', self::PARTNER_TYPES, true )
			? $data['liw_partner_type']
			: 'dealer';

		$partner_id = PartnerBridge::create( [
			'name'          => $name,
			'status'        => 'pending', // Erst nach Prüfung (approve) 'active' – s. set_status().
			'contact_name'  => sanitize_text_field( $data['contact_name'] ?? $name ),
			'contact_email' => $email,
			'contact_phone' => sanitize_text_field( $data['contact_phone'] ?? '' ),
			'city'          => sanitize_text_field( $data['city'] ?? '' ),
			'website_url'   => isset( $data['website_url'] ) ? esc_url_raw( $data['website_url'] ) : '',
		] );

		if ( is_wp_error( $partner_id ) ) {
			return $partner_id;
		}

		global $wpdb;
		$row = [
			'partner_id'           => $partner_id,
			'liw_partner_type'     => $partner_type,
			'requested_interfaces' => sanitize_textarea_field( $data['requested_interfaces'] ?? '' ),
			'message'              => sanitize_textarea_field( $data['message'] ?? '' ),
			'onboarding_status'    => 'new',
		];

		$inserted = $wpdb->insert( OnboardingSchema::table_name(), $row );
		if ( false === $inserted ) {
			return new \WP_Error( 'liw_db_error', __( 'Anfrage konnte nicht gespeichert werden.', 'liebherr-interface-world' ) );
		}

		ConsentLogService::record( $partner_id, ConsentLogService::TYPE_PRIVACY, self::PRIVACY_TEXT_VERSION );
		if ( ! empty( $data['marketing_consent'] ) ) {
			ConsentLogService::record( $partner_id, ConsentLogService::TYPE_MARKETING, self::PRIVACY_TEXT_VERSION );
		}

		// actor_id 0: öffentliches Formular, kein eingeloggter Benutzer (SEC-005 – Nachvollziehbarkeit
		// bleibt über IP-Protokollierung im Consent-Log gewahrt, s. ConsentLogSchema).
		AuditBridge::log( 'create', 'onboarding_request', $partner_id, [], [ 'name' => $name, 'liw_partner_type' => $partner_type ], 0 );

		return $partner_id;
	}

	public static function set_status( int $partner_id, string $status, int $actor_id ): bool|\WP_Error {
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return new \WP_Error( 'liw_invalid_status', __( 'Unbekannter Onboarding-Status.', 'liebherr-interface-world' ) );
		}

		global $wpdb;
		$table  = OnboardingSchema::table_name();
		$before = self::get_extra( $partner_id );
		if ( null === $before ) {
			return new \WP_Error( 'liw_not_found', __( 'Anfrage nicht gefunden.', 'liebherr-interface-world' ) );
		}

		$updated = $wpdb->update( $table, [ 'onboarding_status' => $status ], [ 'partner_id' => $partner_id ] );
		if ( false === $updated ) {
			return new \WP_Error( 'liw_db_error', __( 'Status konnte nicht aktualisiert werden.', 'liebherr-interface-world' ) );
		}

		// Core-Partnerstatus spiegeln: erst nach Freigabe wird der Partner in Core als aktiv geführt.
		$core_status = match ( $status ) {
			'approved' => 'active',
			'rejected' => 'inactive',
			default    => 'pending',
		};
		PartnerBridge::update( $partner_id, [ 'status' => $core_status ] );

		AuditBridge::log( 'status_change', 'onboarding_request', $partner_id, [ 'onboarding_status' => $before['onboarding_status'] ], [ 'onboarding_status' => $status ], $actor_id );
		return true;
	}

	/** @return array<string, mixed>|null */
	public static function get_extra( int $partner_id ): ?array {
		global $wpdb;
		$table = OnboardingSchema::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE partner_id = %d", $partner_id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Admin-Ansicht: Onboarding-Anfragen inkl. der zugehörigen Core-Partnerdaten (Name,
	 * Kontakt) in einer Abfrage – vermeidet N+1-Zugriffe auf ary_partners (Performance).
	 *
	 * Feinschliff alpha.10: vorher ungebremster JOIN ohne LIMIT (Performance-Risiko bei
	 * wachsendem öffentlichem Formular, §22). Jetzt seitenweise mit `LIMIT`/`OFFSET`,
	 * Gesamtzahl über {@see count_all_requests()} für die Pagination-Anzeige.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_all_requests( int $page = 1, int $per_page = self::REQUESTS_PER_PAGE ): array {
		global $wpdb;
		$extra_table   = OnboardingSchema::table_name();
		$partner_table = $wpdb->prefix . 'ary_partners';
		$page          = max( 1, $page );
		$per_page      = max( 1, $per_page );
		$offset        = ( $page - 1 ) * $per_page;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Tabellennamen sind feste Konstanten, keine Nutzereingabe.
				"SELECT e.id, e.partner_id, e.liw_partner_type, e.requested_interfaces, e.message,
				        e.onboarding_status, e.created_at,
				        p.name, p.contact_name, p.contact_email, p.contact_phone, p.city, p.website_url, p.status AS core_status
				 FROM {$extra_table} e
				 INNER JOIN {$partner_table} p ON p.id = e.partner_id
				 ORDER BY e.created_at DESC
				 LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	/** Gesamtzahl aller Onboarding-Anfragen (für die Pagination-Anzeige, s. {@see get_all_requests()}). */
	public static function count_all_requests(): int {
		global $wpdb;
		$table = OnboardingSchema::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}
}
