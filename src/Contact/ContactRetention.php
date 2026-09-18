<?php
/**
 * Liebherr Interface Solutions – Aufbewahrungsfrist für Kontaktanfragen (SEC-007/§24).
 *
 * Datenminimierung: Kontaktanfragen (personenbezogen) werden nach einer konfigurierbaren Frist
 * automatisch gelöscht – inklusive der zugehörigen Einwilligungen (über ContactService::delete()).
 * Frist 0 = deaktiviert (Standard; keine automatische Löschung ohne bewusste Einstellung).
 * Täglicher WP-Cron; jede Löschung wird auditiert (SEC-005).
 *
 * @package Liebherr\InterfaceWorld\Contact
 * @since   0.1.0-alpha.33
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Contact;

use Liebherr\InterfaceWorld\CoreBridge\AuditBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ContactRetention {

	public const OPTION    = 'liw_contact_retention_days';
	public const CRON_HOOK = 'liw_contact_retention_cron';

	public static function register(): void {
		add_action( self::CRON_HOOK, [ self::class, 'run' ] );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/** Aufbewahrungsdauer in Tagen (0 = deaktiviert). */
	public static function days(): int {
		return max( 0, (int) get_option( self::OPTION, 0 ) );
	}

	public static function set_days( int $days ): void {
		update_option( self::OPTION, max( 0, $days ) );
	}

	/** Täglicher Lauf: Anfragen älter als die Frist löschen (mit Einwilligungen). */
	public static function run(): void {
		$days = self::days();
		if ( $days <= 0 ) {
			return;
		}
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$ids    = ContactService::ids_older_than( $cutoff );
		$deleted = 0;
		foreach ( $ids as $id ) {
			if ( true === ContactService::delete( (int) $id, 0 ) ) {
				$deleted++;
			}
		}
		if ( $deleted > 0 ) {
			AuditBridge::log( 'retention_delete', 'contact_request', 0, [], [ 'count' => $deleted, 'older_than' => $cutoff, 'days' => $days ], 0 );
		}
	}

	/** Cron beim Deaktivieren entfernen. */
	public static function unschedule(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}
}
