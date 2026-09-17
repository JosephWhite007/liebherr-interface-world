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

	public static function record( int $request_id, string $consent_type, string $text_version ): bool {
		if ( ! in_array( $consent_type, [ self::TYPE_PRIVACY, self::TYPE_MARKETING ], true ) ) {
			return false;
		}

		global $wpdb;
		$inserted = $wpdb->insert( ConsentLogSchema::table_name(), [
			'request_id'   => $request_id,
			'consent_type' => $consent_type,
			'text_version' => sanitize_text_field( $text_version ),
			'ip_address'   => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : null,
		] );

		return false !== $inserted;
	}

	public static function has_consent( int $request_id, string $consent_type ): bool {
		global $wpdb;
		$table = ConsentLogSchema::table_name();
		$count = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE request_id = %d AND consent_type = %s", $request_id, $consent_type )
		);
		return (int) $count > 0;
	}
}
