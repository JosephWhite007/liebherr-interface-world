<?php
/**
 * Liebherr Interface Solutions – CSV-Export der Kontaktanfragen (§24).
 *
 * Bereitet die personenbezogenen Kontaktanfragen als CSV auf (Auskunfts-/Exportprozess §24).
 * Zugriff nur mit `liw_view_onboarding`, Nonce-geschützt; der Export wird auditiert (SEC-005).
 * Format: UTF-8 mit BOM und Semikolon-Trenner (Excel-DE-freundlich). Einwilligungen werden mit
 * Textversion und Zeitstempel je Anfrage ausgewiesen; Marketing- und Kontaktzweck bleiben getrennt.
 *
 * @package Liebherr\InterfaceWorld\Contact
 * @since   0.1.0-alpha.30
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Contact;

use Liebherr\InterfaceWorld\Consent\ConsentLogService;
use Liebherr\InterfaceWorld\CoreBridge\AuditBridge;
use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ContactExporter {

	public const ACTION     = 'liw_contact_export';
	public const NONCE_NAME = 'liw_contact_export_nonce';

	private const STATUS_LABELS = [
		'new'         => 'Neu',
		'in_progress' => 'In Bearbeitung',
		'closed'      => 'Abgeschlossen',
	];

	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, [ self::class, 'handle' ] );
	}

	public static function handle(): void {
		if ( ! current_user_can( RoleBridge::CAP_VIEW_ONBOARDING ) ) {
			wp_die( esc_html__( 'Keine Berechtigung für den Export.', 'liebherr-interface-world' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( self::ACTION, self::NONCE_NAME );

		$rows = ContactService::get_all_for_export();

		AuditBridge::log( 'export', 'contact_request', 0, [], [ 'count' => count( $rows ), 'format' => 'csv' ], get_current_user_id() );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="liw-kontaktanfragen-' . gmdate( 'Ymd-His' ) . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM für Excel.

		fputcsv( $out, [
			'ID', 'Erstellt', 'Organisation', 'Kontaktperson', 'E-Mail', 'Telefon', 'Land/Region',
			'Rolle', 'Lokales ERP/CRM', 'Projektinteresse', 'Nachricht', 'Status',
			'Datenschutz-Einwilligung', 'DS-Textversion', 'DS-Zeitpunkt',
			'Marketing-Einwilligung', 'MK-Textversion', 'MK-Zeitpunkt',
		], ';' );

		foreach ( $rows as $row ) {
			$id       = (int) ( $row['id'] ?? 0 );
			$consents = ConsentLogService::list_for_request( $id, ConsentLogService::KIND_CONTACT );
			$privacy  = $consents[ ConsentLogService::TYPE_PRIVACY ] ?? null;
			$mkt      = $consents[ ConsentLogService::TYPE_MARKETING ] ?? null;

			fputcsv( $out, [
				$id,
				(string) ( $row['created_at'] ?? '' ),
				(string) ( $row['organisation'] ?? '' ),
				(string) ( $row['contact_name'] ?? '' ),
				(string) ( $row['contact_email'] ?? '' ),
				(string) ( $row['contact_phone'] ?? '' ),
				ContactService::REGIONS[ (string) ( $row['region'] ?? '' ) ] ?? (string) ( $row['region'] ?? '' ),
				ContactService::ROLES[ (string) ( $row['role'] ?? '' ) ] ?? (string) ( $row['role'] ?? '' ),
				(string) ( $row['local_system'] ?? '' ),
				self::interest_labels( (string) ( $row['interests'] ?? '' ) ),
				(string) ( $row['message'] ?? '' ),
				self::STATUS_LABELS[ (string) ( $row['request_status'] ?? '' ) ] ?? (string) ( $row['request_status'] ?? '' ),
				null !== $privacy ? 'ja' : 'nein',
				null !== $privacy ? $privacy['text_version'] : '',
				null !== $privacy ? $privacy['granted_at'] : '',
				null !== $mkt ? 'ja' : 'nein',
				null !== $mkt ? $mkt['text_version'] : '',
				null !== $mkt ? $mkt['granted_at'] : '',
			], ';' );
		}

		fclose( $out );
		exit;
	}

	/** Kommagetrennte Interessen-Schlüssel → lesbare, mit „, " verbundene Labels. */
	private static function interest_labels( string $keys ): string {
		$out = [];
		foreach ( array_filter( array_map( 'trim', explode( ',', $keys ) ) ) as $key ) {
			$out[] = ContactService::INTERESTS[ $key ] ?? $key;
		}
		return implode( ', ', $out );
	}
}
