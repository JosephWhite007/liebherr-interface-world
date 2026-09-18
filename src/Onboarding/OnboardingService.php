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
use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;

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

	/** User-Meta am WP-Konto: Rückverweis auf ary_partners.id (für Partnerbereich/Dokumente, Stufe 2). */
	public const USER_META_PARTNER_ID = '_liw_partner_id';

	/**
	 * Legt für eine FREIGEGEBENE Onboarding-Anfrage ein WP-Benutzerkonto mit Rolle `liw_partner`
	 * an (alpha.21, Stufe 1 des geschützten Partnerbereichs). Idempotent: ist bereits ein Konto
	 * verknüpft, wird nichts geändert (Fehler `liw_account_exists`).
	 *
	 * ANNAHME-LIW-11: Konten werden NICHT automatisch bei Freigabe angelegt, sondern bewusst per
	 * Aktion im Onboarding Board (zweiter Schritt nach der fachlichen Freigabe – Least Privilege,
	 * §10/§23). Existiert zur Kontakt-E-Mail bereits ein WP-Benutzer, wird dieser verknüpft und
	 * erhält die Partner-Rolle ZUSÄTZLICH (keine bestehende Rolle wird entfernt).
	 *
	 * Passwort: nie erzeugt/versendet – der Partner setzt es selbst über den WP-Standardlink
	 * (`wp_new_user_notification( …, 'user' )`, zeitlich begrenzter Reset-Link).
	 *
	 * @return int|\WP_Error WP-User-ID
	 */
	public static function create_partner_account( int $partner_id, int $actor_id ): int|\WP_Error {
		$extra = self::get_extra( $partner_id );
		if ( null === $extra ) {
			return new \WP_Error( 'liw_not_found', __( 'Anfrage nicht gefunden.', 'liebherr-interface-world' ) );
		}
		if ( 'approved' !== $extra['onboarding_status'] ) {
			return new \WP_Error( 'liw_not_approved', __( 'Ein Partnerkonto kann nur für freigegebene Anfragen angelegt werden.', 'liebherr-interface-world' ) );
		}
		if ( ! empty( $extra['wp_user_id'] ) && get_userdata( (int) $extra['wp_user_id'] ) instanceof \WP_User ) {
			return new \WP_Error( 'liw_account_exists', __( 'Für diese Anfrage existiert bereits ein Partnerkonto.', 'liebherr-interface-world' ) );
		}

		$partner = PartnerBridge::get( $partner_id );
		$email   = sanitize_email( (string) ( $partner['contact_email'] ?? '' ) );
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'liw_invalid_email', __( 'Die Kontakt-E-Mail des Partners ist ungültig – Konto kann nicht angelegt werden.', 'liebherr-interface-world' ) );
		}

		RoleBridge::ensure_partner_role();

		$existing = get_user_by( 'email', $email );
		if ( $existing instanceof \WP_User ) {
			$existing->add_role( RoleBridge::ROLE_PARTNER );
			$user_id = (int) $existing->ID;
			$linked  = true;
		} else {
			$base     = sanitize_user( strstr( $email, '@', true ) ?: $email, true ) ?: 'partner';
			$username = $base;
			$i        = 1;
			while ( username_exists( $username ) ) {
				$username = $base . '-' . ( ++$i );
			}
			$user_id = wp_insert_user( [
				'user_login'   => $username,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 32, true, true ), // wird nie kommuniziert; Partner setzt eigenes Passwort per Link.
				'display_name' => sanitize_text_field( (string) ( $partner['contact_name'] ?: $partner['name'] ) ),
				'role'         => RoleBridge::ROLE_PARTNER,
			] );
			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}
			$user_id = (int) $user_id;
			$linked  = false;
		}

		update_user_meta( $user_id, self::USER_META_PARTNER_ID, $partner_id );

		global $wpdb;
		$wpdb->update( OnboardingSchema::table_name(), [ 'wp_user_id' => $user_id ], [ 'partner_id' => $partner_id ] );

		if ( ! $linked ) {
			wp_new_user_notification( $user_id, null, 'user' ); // Passwort-setzen-Link an den Partner, keine Admin-Kopie.
		}

		AuditBridge::log( 'account_create', 'onboarding_request', $partner_id, [], [ 'wp_user_id' => $user_id, 'linked_existing' => $linked, 'role' => RoleBridge::ROLE_PARTNER ], $actor_id );
		return $user_id;
	}
}
