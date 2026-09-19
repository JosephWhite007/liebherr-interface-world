<?php
/**
 * Liebherr World – My Liebherr: Moderation (Pflichtenheft My Liebherr §11/§13/§31, ADR-LIW-MYL-001 R4).
 *
 * Prüfer-Workflow: World-Review von Galerie-Freigaben (pending → published/blocked), Auflösung von Meldungen
 * (dismiss/suspend/refund). Reine Übergangsregeln ({@see can_world_review()}/{@see can_report_action()}) sind
 * ohne WordPress testbar; die Aktionen orchestrieren Share-/Gallery-/Report-Repository. Erstattung ist eine Naht
 * (Hook `liw_myl_refund`, echte Wallet-Buchung erst mit dem Wallet-Pflichtenheft).
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.131
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ModerationService {

	/** Zulässige Report-Aktionen (offen/reviewed → …). @return array<int,string> */
	public static function report_actions(): array {
		return [ 'dismiss', 'suspend', 'refund' ];
	}

	/** World-Review nur aus 'pending' nach 'published' oder 'blocked'. */
	public static function can_world_review( string $from, string $to ): bool {
		return 'pending' === $from && in_array( $to, [ 'published', 'blocked' ], true );
	}

	/** Report-Aktion nur auf offene/geprüfte Meldung. */
	public static function can_report_action( string $status, string $action ): bool {
		return in_array( $status, [ 'open', 'reviewed' ], true ) && in_array( $action, self::report_actions(), true );
	}

	/**
	 * Prüf-Queue: ausstehende World-Freigaben + offene Meldungen.
	 *
	 * @return array<string,mixed>
	 */
	public static function queue(): array {
		return [
			'world_pending' => ShareRepository::world_list( 'pending' ),
			'reports'       => ReportRepository::open(),
		];
	}

	/**
	 * World-Freigabe prüfen: approve → published, reject → blocked.
	 *
	 * @return array<string,mixed>
	 */
	public static function review_share( int $share_id, string $decision ): array {
		$share = ShareRepository::get( $share_id );
		if ( null === $share ) {
			return [ 'ok' => false, 'reason' => 'not_found' ];
		}
		$to = 'approve' === $decision ? 'published' : ( 'reject' === $decision ? 'blocked' : '' );
		if ( '' === $to || ! self::can_world_review( (string) $share['status'], $to ) ) {
			return [ 'ok' => false, 'reason' => 'invalid_transition' ];
		}
		ShareRepository::set_status( $share_id, $to );
		return [ 'ok' => true, 'status' => $to, 'share' => ShareRepository::get( $share_id ) ];
	}

	/**
	 * Meldung auflösen: dismiss (verwerfen), suspend (Objekt sperren, §11 QX), refund (Erstattung anstoßen – Naht).
	 *
	 * @return array<string,mixed>
	 */
	public static function resolve_report( int $report_id, string $action, int $resolver_id ): array {
		$report = ReportRepository::get( $report_id );
		if ( null === $report ) {
			return [ 'ok' => false, 'reason' => 'not_found' ];
		}
		if ( ! self::can_report_action( (string) $report['status'], $action ) ) {
			return [ 'ok' => false, 'reason' => 'invalid_action' ];
		}
		if ( 'dismiss' === $action ) {
			ReportRepository::set_status( $report_id, 'dismissed', $resolver_id );
			return [ 'ok' => true, 'status' => 'dismissed' ];
		}
		if ( 'suspend' === $action ) {
			if ( 'gallery' === $report['object_type'] ) {
				GalleryRepository::moderate_status( (int) $report['object_id'], 'suspended' );
			}
			/** Für andere Objekttypen (adventure) können Consumer über diesen Hook sperren. */
			do_action( 'liw_myl_moderation_suspend', $report );
			ReportRepository::set_status( $report_id, 'actioned', $resolver_id );
			return [ 'ok' => true, 'status' => 'actioned', 'suspended' => true ];
		}
		// refund: Naht – die echte Wallet-Erstattung übernimmt das kommende Wallet-Modul.
		do_action( 'liw_myl_refund', $report );
		ReportRepository::set_status( $report_id, 'actioned', $resolver_id );
		return [ 'ok' => true, 'status' => 'actioned', 'refund' => 'queued' ];
	}
}
