<?php
/**
 * Liebherr Interface Solutions – Consent Log Service
 *
 * @package Liebherr\InterfaceWorld\Consent
 * @since   0.1.0-alpha.1
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Consent;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ConsentLogService {

	public const TYPE_PRIVACY   = 'privacy';
	public const TYPE_MARKETING = 'marketing';

	/**
	 * Herkunft der Anfrage (seit alpha.19): trennt die ID-Räume von Onboarding (ary_partners.id)
	 * und Kontaktanfrage (liw_contact_request.id) im selben Protokoll. Default `onboarding`,
	 * damit alle bestehenden Aufrufe und Altdaten unverändert gültig bleiben.
	 */
	public const KIND_ONBOARDING = 'onboarding';
	public const KIND_CONTACT    = 'contact';

	private const KINDS = [ self::KIND_ONBOARDING, self::KIND_CONTACT ];

	public static function record( int $request_id, string $consent_type, string $text_version, string $request_kind = self::KIND_ONBOARDING ): bool {
		if ( ! in_array( $consent_type, [ self::TYPE_PRIVACY, self::TYPE_MARKETING ], true ) || ! in_array( $request_kind, self::KINDS, true ) ) {
			return false;
		}

		global $wpdb;
		$inserted = $wpdb->insert( ConsentLogSchema::table_name(), [
			'request_id'   => $request_id,
			'request_kind' => $request_kind,
			'consent_type' => $consent_type,
			'text_version' => sanitize_text_field( $text_version ),
			'ip_address'   => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : null,
		] );

		return false !== $inserted;
	}

	public static function has_consent( int $request_id, string $consent_type, string $request_kind = self::KIND_ONBOARDING ): bool {
		global $wpdb;
		$table = ConsentLogSchema::table_name();
		$count = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE request_id = %d AND consent_type = %s AND request_kind = %s", $request_id, $consent_type, $request_kind )
		);
		return (int) $count > 0;
	}

	/**
	 * Einwilligungen einer Anfrage (für Auskunft/Export §24): Typ, Textversion, Zeitstempel.
	 *
	 * @return array<string,array{text_version:string,granted_at:string}> je consent_type
	 */
	public static function list_for_request( int $request_id, string $request_kind = self::KIND_ONBOARDING ): array {
		if ( ! in_array( $request_kind, self::KINDS, true ) ) {
			return [];
		}
		global $wpdb;
		$table = ConsentLogSchema::table_name();
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT consent_type, text_version, granted_at FROM {$table} WHERE request_id = %d AND request_kind = %s ORDER BY granted_at ASC", $request_id, $request_kind ),
			ARRAY_A
		);
		$out = [];
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row['consent_type'] ] = [
				'text_version' => (string) $row['text_version'],
				'granted_at'   => (string) $row['granted_at'],
			];
		}
		return $out;
	}

	/** §24 Löschprozess: alle Einwilligungen einer Anfrage entfernen. */
	public static function delete_for_request( int $request_id, string $request_kind ): bool {
		if ( ! in_array( $request_kind, self::KINDS, true ) ) {
			return false;
		}
		global $wpdb;
		return false !== $wpdb->delete( ConsentLogSchema::table_name(), [ 'request_id' => $request_id, 'request_kind' => $request_kind ] );
	}
}
