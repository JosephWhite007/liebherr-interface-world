<?php
/**
 * Liebherr Interface Solutions – Contact Service
 *
 * Fachlogik der Kontakt-/Projektanfrage (Pflichtenheft §22 Feldliste, LP-13, AC-008):
 * serverseitige Validierung, Speicherung, Einwilligungsprotokoll (§24, Zweckbindung:
 * Datenschutz- und Marketing-Einwilligung getrennt, nie gekoppelt), Benachrichtigung der
 * konfigurierten Empfänger, Audit (SEC-005), Statuspflege für das Contact Board.
 *
 * ANNAHME-LIW-8: Das Pflichtenheft nennt für „Land/Region" nur „Auswahl" ohne Werteliste.
 * Bis zur fachlichen Vorgabe wird eine Weltregionen-Liste (`REGIONS`) verwendet – über den
 * Filter `liw_contact_regions` ohne Codeänderung anpassbar.
 * ANNAHME-LIW-9: „Projektinteresse (Mehrfachauswahl)" ohne Werteliste im Pflichtenheft. Die
 * Optionen (`INTERESTS`) folgen den Bausteinen der Landingpage (Schnittstellen, Magic Cube,
 * Onboarding, Datenmodell, Sicherheit) – Filter `liw_contact_interests`.
 * ANNAHME-LIW-10: „Konfigurierte Empfänger" – bis zur CRM-/Empfängerdefinition durch die
 * Projektleitung (Pflichtenheft §31 offene Punkte) geht die Benachrichtigung an die
 * WordPress-Admin-E-Mail; Filter `liw_contact_recipients`. Keine CRM-Übergabe (nicht definiert).
 *
 * @package Liebherr\InterfaceWorld\Contact
 * @since   0.1.0-alpha.19
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Contact;

use Liebherr\InterfaceWorld\Consent\ConsentLogService;
use Liebherr\InterfaceWorld\CoreBridge\AuditBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ContactService {

	public const ROLES = [
		'central'            => 'Zentrale',
		'dealer'             => 'Händler',
		'supplier'           => 'Lieferant',
		'technology_partner' => 'Technologiepartner',
		'other'              => 'Sonstiger Projektkontakt',
	];

	/** ANNAHME-LIW-8 – Filter `liw_contact_regions`. */
	public const REGIONS = [
		'europe'        => 'Europa',
		'north_america' => 'Nordamerika',
		'south_america' => 'Südamerika',
		'africa'        => 'Afrika',
		'middle_east'   => 'Naher Osten',
		'asia_pacific'  => 'Asien-Pazifik',
		'other'         => 'Sonstige',
	];

	/** ANNAHME-LIW-9 – Filter `liw_contact_interests`. */
	public const INTERESTS = [
		'interfaces'   => 'Schnittstellen-Anbindung',
		'simulation'   => 'Simulation / Magic Cube',
		'onboarding'   => 'Händler-Onboarding',
		'data_model'   => 'Datenmodell / Referenzstruktur',
		'security'     => 'Sicherheit / Compliance',
		'other'        => 'Sonstiges',
	];

	public const STATUSES = [ 'new', 'in_progress', 'closed' ];

	public const REQUESTS_PER_PAGE = 20;

	/** Fassung des Datenschutztexts für das Kontaktformular (§24: Version + Zeitstempel). */
	public const PRIVACY_TEXT_VERSION = '2026-09-18-contact-v1';

	/** @return array<string, string> */
	public static function regions(): array {
		$regions = apply_filters( 'liw_contact_regions', self::REGIONS );
		return is_array( $regions ) && [] !== $regions ? $regions : self::REGIONS;
	}

	/** @return array<string, string> */
	public static function interests(): array {
		$interests = apply_filters( 'liw_contact_interests', self::INTERESTS );
		return is_array( $interests ) && [] !== $interests ? $interests : self::INTERESTS;
	}

	/**
	 * @param array{
	 *   organisation:string, contact_name:string, contact_email:string, contact_phone?:string,
	 *   region:string, role:string, local_system?:string, interests:string[], message:string,
	 *   privacy_consent:bool, marketing_consent?:bool
	 * } $data
	 */
	public static function submit_request( array $data ): int|\WP_Error {
		$organisation = sanitize_text_field( $data['organisation'] ?? '' );
		$contact_name = sanitize_text_field( $data['contact_name'] ?? '' );
		$email        = sanitize_email( $data['contact_email'] ?? '' );
		$region       = sanitize_key( $data['region'] ?? '' );
		$role         = sanitize_key( $data['role'] ?? '' );
		$message      = sanitize_textarea_field( $data['message'] ?? '' );

		$interests = array_values( array_intersect(
			array_map( 'sanitize_key', array_map( 'strval', (array) ( $data['interests'] ?? [] ) ) ),
			array_keys( self::interests() )
		) );

		if ( '' === $organisation || '' === $contact_name || ! is_email( $email ) || '' === $message ) {
			return new \WP_Error( 'liw_invalid_input', __( 'Organisation, Kontaktperson, eine gültige geschäftliche E-Mail-Adresse und eine Nachricht sind Pflichtfelder.', 'liebherr-interface-world' ) );
		}
		if ( ! isset( self::regions()[ $region ] ) ) {
			return new \WP_Error( 'liw_invalid_region', __( 'Bitte Land/Region auswählen.', 'liebherr-interface-world' ) );
		}
		if ( ! isset( self::ROLES[ $role ] ) ) {
			return new \WP_Error( 'liw_invalid_role', __( 'Bitte eine Rolle auswählen.', 'liebherr-interface-world' ) );
		}
		if ( [] === $interests ) {
			return new \WP_Error( 'liw_invalid_interest', __( 'Bitte mindestens ein Projektinteresse auswählen.', 'liebherr-interface-world' ) );
		}
		if ( empty( $data['privacy_consent'] ) ) {
			return new \WP_Error( 'liw_consent_required', __( 'Der Datenschutzhinweis muss bestätigt werden.', 'liebherr-interface-world' ) );
		}

		global $wpdb;
		$row = [
			'organisation'   => $organisation,
			'contact_name'   => $contact_name,
			'contact_email'  => $email,
			'contact_phone'  => sanitize_text_field( $data['contact_phone'] ?? '' ) ?: null,
			'region'         => $region,
			'role'           => $role,
			'local_system'   => sanitize_text_field( $data['local_system'] ?? '' ) ?: null,
			'interests'      => implode( ',', $interests ),
			'message'        => $message,
			'request_status' => 'new',
		];

		$inserted = $wpdb->insert( ContactSchema::table_name(), $row );
		if ( false === $inserted ) {
			return new \WP_Error( 'liw_db_error', __( 'Anfrage konnte nicht gespeichert werden.', 'liebherr-interface-world' ) );
		}
		$id = (int) $wpdb->insert_id;

		// §24: Einwilligungen mit Version/Zeitstempel; Marketing nur bei eigener, separater Zustimmung.
		ConsentLogService::record( $id, ConsentLogService::TYPE_PRIVACY, self::PRIVACY_TEXT_VERSION, ConsentLogService::KIND_CONTACT );
		if ( ! empty( $data['marketing_consent'] ) ) {
			ConsentLogService::record( $id, ConsentLogService::TYPE_MARKETING, self::PRIVACY_TEXT_VERSION, ConsentLogService::KIND_CONTACT );
		}

		// Audit ohne personenbezogene Freitexte (SEC-005, Datensparsamkeit): nur Klassifizierung.
		AuditBridge::log( 'create', 'contact_request', $id, [], [ 'role' => $role, 'region' => $region, 'interests' => $interests ], 0 );

		self::notify_recipients( $id, $row );

		return $id;
	}

	public static function set_status( int $id, string $status, int $actor_id ): bool|\WP_Error {
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return new \WP_Error( 'liw_invalid_status', __( 'Unbekannter Anfrage-Status.', 'liebherr-interface-world' ) );
		}

		$before = self::get( $id );
		if ( null === $before ) {
			return new \WP_Error( 'liw_not_found', __( 'Anfrage nicht gefunden.', 'liebherr-interface-world' ) );
		}

		global $wpdb;
		$updated = $wpdb->update( ContactSchema::table_name(), [ 'request_status' => $status ], [ 'id' => $id ] );
		if ( false === $updated ) {
			return new \WP_Error( 'liw_db_error', __( 'Status konnte nicht aktualisiert werden.', 'liebherr-interface-world' ) );
		}

		AuditBridge::log( 'status_change', 'contact_request', $id, [ 'request_status' => $before['request_status'] ], [ 'request_status' => $status ], $actor_id );
		return true;
	}

	/** @return array<string, mixed>|null */
	public static function get( int $id ): ?array {
		global $wpdb;
		$table = ContactSchema::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/** @return array<int, array<string, mixed>> Neueste zuerst, seitenweise (Muster OnboardingService). */
	public static function get_all( int $page = 1 ): array {
		global $wpdb;
		$table  = ContactSchema::table_name();
		$offset = max( 0, $page - 1 ) * self::REQUESTS_PER_PAGE;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", self::REQUESTS_PER_PAGE, $offset ), ARRAY_A );
		return is_array( $rows ) ? $rows : [];
	}

	public static function count_all(): int {
		global $wpdb;
		$table = ContactSchema::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	public static function delete( int $id, int $actor_id ): bool|\WP_Error {
		$before = self::get( $id );
		if ( null === $before ) {
			return new \WP_Error( 'liw_not_found', __( 'Anfrage nicht gefunden.', 'liebherr-interface-world' ) );
		}

		global $wpdb;
		$deleted = $wpdb->delete( ContactSchema::table_name(), [ 'id' => $id ] );
		if ( false === $deleted ) {
			return new \WP_Error( 'liw_db_error', __( 'Anfrage konnte nicht gelöscht werden.', 'liebherr-interface-world' ) );
		}

		// §24 Löschprozess: zugehörige Einwilligungen mitlöschen (gleicher Zweck, keine Waisen).
		ConsentLogService::delete_for_request( $id, ConsentLogService::KIND_CONTACT );
		AuditBridge::log( 'delete', 'contact_request', $id, [ 'role' => $before['role'], 'region' => $before['region'] ], [], $actor_id );
		return true;
	}

	/** @return string[] */
	public static function recipients(): array {
		$default = [ (string) get_option( 'admin_email' ) ];
		$list    = apply_filters( 'liw_contact_recipients', $default );
		return array_values( array_filter( array_map( 'sanitize_email', (array) $list ), 'is_email' ) );
	}

	/** @param array<string, mixed> $row */
	private static function notify_recipients( int $id, array $row ): void {
		$recipients = self::recipients();
		if ( [] === $recipients ) {
			return;
		}

		$interest_labels = array_map( static fn( string $k ): string => self::interests()[ $k ] ?? $k, explode( ',', (string) $row['interests'] ) );

		$subject = sprintf(
			/* translators: 1: Anfrage-ID, 2: Organisation */
			__( '[Interface World] Neue Kontaktanfrage #%1$d – %2$s', 'liebherr-interface-world' ),
			$id,
			(string) $row['organisation']
		);

		$lines = [
			__( 'Organisation', 'liebherr-interface-world' ) . ': ' . $row['organisation'],
			__( 'Kontaktperson', 'liebherr-interface-world' ) . ': ' . $row['contact_name'],
			__( 'E-Mail', 'liebherr-interface-world' ) . ': ' . $row['contact_email'],
			__( 'Telefon', 'liebherr-interface-world' ) . ': ' . ( $row['contact_phone'] ?? '–' ),
			__( 'Land/Region', 'liebherr-interface-world' ) . ': ' . ( self::regions()[ $row['region'] ] ?? $row['region'] ),
			__( 'Rolle', 'liebherr-interface-world' ) . ': ' . ( self::ROLES[ $row['role'] ] ?? $row['role'] ),
			__( 'Lokales ERP/CRM', 'liebherr-interface-world' ) . ': ' . ( $row['local_system'] ?? '–' ),
			__( 'Projektinteresse', 'liebherr-interface-world' ) . ': ' . implode( ', ', $interest_labels ),
			'',
			(string) $row['message'],
			'',
			__( 'Bearbeitung im Backend: Interface World → Kontaktanfragen.', 'liebherr-interface-world' ),
		];

		wp_mail( $recipients, $subject, implode( "\n", $lines ) );
	}
}
