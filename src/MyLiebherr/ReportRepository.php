<?php
/**
 * Liebherr World – My Liebherr: Meldungen/Reports (Pflichtenheft My Liebherr §11, ADR-LIW-MYL-001 R4).
 *
 * Persistiert Meldungen zu Inhalten (Galerie/Freigabe/Adventure) mit Grund und Status (open → reviewed/dismissed/
 * actioned). Grundlage der Moderation ({@see ModerationService}). Melden darf jeder berechtigte Nutzer; Auflösen
 * nur Prüfer ({@see Roles::CAP_MODERATE}).
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.131
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ReportRepository {

	/** @return array<string,mixed>|null */
	public static function create( int $reporter_id, string $object_type, int $object_id, string $reason, string $note ): ?array {
		if ( $reporter_id <= 0 || $object_id <= 0 ) {
			return null;
		}
		global $wpdb;
		$wpdb->insert( Schema::report_table(), [ // phpcs:ignore WordPress.DB
			'object_type' => in_array( $object_type, [ 'gallery', 'share', 'adventure' ], true ) ? $object_type : 'gallery',
			'object_id'   => $object_id,
			'reporter_id' => $reporter_id,
			'reason'      => ContentRules::report_reason( $reason ),
			'note'        => sanitize_text_field( $note ),
			'status'      => 'open',
		] );
		return self::get( (int) $wpdb->insert_id );
	}

	/** @return array<string,mixed>|null */
	public static function get( int $id ): ?array {
		global $wpdb;
		$t   = Schema::report_table();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $row ) ? self::shape( $row ) : null;
	}

	/** Offene Meldungen (Queue). @return array<int,array<string,mixed>> */
	public static function open(): array {
		global $wpdb;
		$t    = Schema::report_table();
		$rows = $wpdb->get_results( "SELECT * FROM {$t} WHERE status = 'open' ORDER BY id DESC", ARRAY_A ); // phpcs:ignore WordPress.DB
		return is_array( $rows ) ? array_map( [ self::class, 'shape' ], $rows ) : [];
	}

	public static function set_status( int $id, string $to, int $resolver_id ): bool {
		if ( null === self::get( $id ) ) {
			return false;
		}
		global $wpdb;
		return false !== $wpdb->update( // phpcs:ignore WordPress.DB
			Schema::report_table(),
			[ 'status' => $to, 'resolver_id' => $resolver_id, 'resolved_at' => current_time( 'mysql' ) ],
			[ 'id' => $id ],
			[ '%s', '%d', '%s' ],
			[ '%d' ]
		);
	}

	/** @param array<string,mixed> $r @return array<string,mixed> */
	private static function shape( array $r ): array {
		return [
			'id'          => (int) $r['id'],
			'object_type' => (string) ( $r['object_type'] ?? 'gallery' ),
			'object_id'   => (int) ( $r['object_id'] ?? 0 ),
			'reporter_id' => (int) ( $r['reporter_id'] ?? 0 ),
			'reason'      => (string) ( $r['reason'] ?? 'wrong' ),
			'note'        => (string) ( $r['note'] ?? '' ),
			'status'      => (string) ( $r['status'] ?? 'open' ),
		];
	}
}
