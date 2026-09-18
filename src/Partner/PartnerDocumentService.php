<?php
/**
 * Liebherr Interface Solutions – Partner Document Service
 *
 * Geschützte Dokumente für angemeldete Partner (Rolle liw_partner). Sicherheitsmuster 1:1 vom
 * Core `Modules\Documents\DocumentService` übernommen (ADR-074 Secure File Storage), weil das
 * Core-Modul gastgebunden ist und hier nicht wiederverwendet werden kann (LOGBUCH_TECHNIK
 * alpha.21):
 *   - Upload-Verzeichnis unterhalb von wp-content/uploads mit 0750, `.htaccess Deny from all`
 *     und leerer index.html – Dateien sind nie per URL erreichbar.
 *   - Zufälliger `stored_name` (bin2hex(random_bytes(16)) + Erweiterung), wird nie ausgegeben.
 *   - Doppelte Typprüfung: Dateiname (wp_check_filetype) UND Inhalt (finfo) müssen zur
 *     Whitelist passen; Größenlimit 10 MB.
 *   - Auslieferung ausschließlich über PartnerDocumentsView::handle_download() (eingeloggt +
 *     Capability + Nonce), Streaming per readfile – kein Token nötig, weil Website-Login mit
 *     Cookie vorliegt (Core braucht Token wegen seiner cookielosen App-API).
 *   - Soft-Delete (Datei bleibt für Audit-Trail), jede Aktion inkl. Download im Audit (§10).
 *
 * ANNAHME-LIW-12: Alle Partner mit `liw_partner_access` sehen dieselben Dokumente (keine
 * Zuordnung je Partner/Region). Ausreichend für die bereinigte Prozessdokumentation; eine
 * Zuordnung wäre additiv (Join-Tabelle) nachrüstbar.
 *
 * @package Liebherr\InterfaceWorld\Partner
 * @since   0.1.0-alpha.22
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Partner;

use Liebherr\InterfaceWorld\CoreBridge\AuditBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class PartnerDocumentService {

	public const UPLOAD_DIR     = 'liw-partner-documents';
	public const MAX_SIZE_BYTES = 10 * 1024 * 1024;

	/** Erweiterung → erlaubter MIME-Typ (Whitelist, Inhalt UND Name müssen passen). */
	public const ALLOWED = [
		'pdf'  => 'application/pdf',
		'png'  => 'image/png',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	];

	/** Absoluter Pfad des gesperrten Verzeichnisses; legt es samt Schutzdateien an. */
	public static function get_upload_path(): string {
		$path = wp_upload_dir()['basedir'] . '/' . self::UPLOAD_DIR;

		if ( ! is_dir( $path ) ) {
			wp_mkdir_p( $path );
			@chmod( $path, 0750 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		if ( ! file_exists( $path . '/.htaccess' ) ) {
			file_put_contents( $path . '/.htaccess', "Deny from all\nRequire all denied\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Schutzdatei, Muster Core DocumentService.
		}
		if ( ! file_exists( $path . '/index.html' ) ) {
			file_put_contents( $path . '/index.html', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		return $path;
	}

	/**
	 * Nimmt eine hochgeladene Datei ($_FILES-Eintrag) entgegen.
	 *
	 * @param array{name:string,tmp_name:string,size:int,error:int} $file
	 */
	public static function upload( array $file, string $title, string $description, int $actor_id ): int|\WP_Error {
		$title = sanitize_text_field( $title );
		if ( '' === $title ) {
			return new \WP_Error( 'liw_invalid_input', __( 'Ein Titel ist Pflicht.', 'liebherr-interface-world' ) );
		}
		if ( ! isset( $file['tmp_name'], $file['name'] ) || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || ( ! is_uploaded_file( (string) $file['tmp_name'] ) && ! self::is_test_upload( $file ) ) ) {
			return new \WP_Error( 'liw_upload_failed', __( 'Keine gültige Datei hochgeladen.', 'liebherr-interface-world' ) );
		}
		$size = (int) ( $file['size'] ?? filesize( (string) $file['tmp_name'] ) );
		if ( $size <= 0 || $size > self::MAX_SIZE_BYTES ) {
			return new \WP_Error( 'liw_too_large', __( 'Datei leer oder größer als 10 MB.', 'liebherr-interface-world' ) );
		}

		$original = sanitize_file_name( (string) $file['name'] );
		$ext      = strtolower( pathinfo( $original, PATHINFO_EXTENSION ) );
		if ( ! isset( self::ALLOWED[ $ext ] ) ) {
			return new \WP_Error( 'liw_type_not_allowed', __( 'Dateityp nicht erlaubt (PDF, PNG, JPG, DOCX).', 'liebherr-interface-world' ) );
		}
		$finfo     = new \finfo( FILEINFO_MIME_TYPE );
		$real_mime = (string) $finfo->file( (string) $file['tmp_name'] );
		if ( $real_mime !== self::ALLOWED[ $ext ] ) {
			return new \WP_Error( 'liw_type_mismatch', __( 'Dateiinhalt passt nicht zur Dateiendung.', 'liebherr-interface-world' ) );
		}

		$stored = bin2hex( random_bytes( 16 ) ) . '.' . $ext;
		$target = self::get_upload_path() . '/' . $stored;
		$moved  = self::is_test_upload( $file ) ? copy( (string) $file['tmp_name'], $target ) : move_uploaded_file( (string) $file['tmp_name'], $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
		if ( ! $moved ) {
			return new \WP_Error( 'liw_store_failed', __( 'Datei konnte nicht gespeichert werden.', 'liebherr-interface-world' ) );
		}
		@chmod( $target, 0640 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		global $wpdb;
		$row = [
			'title'         => $title,
			'description'   => sanitize_textarea_field( $description ) ?: null,
			'original_name' => $original,
			'stored_name'   => $stored,
			'mime_type'     => $real_mime,
			'size_bytes'    => $size,
			'sha256'        => hash_file( 'sha256', $target ),
			'status'        => 'active',
			'uploaded_by'   => $actor_id > 0 ? $actor_id : null,
		];
		if ( false === $wpdb->insert( PartnerDocumentSchema::table_name(), $row ) ) {
			wp_delete_file( $target );
			return new \WP_Error( 'liw_db_error', __( 'Dokument konnte nicht gespeichert werden.', 'liebherr-interface-world' ) );
		}
		$id = (int) $wpdb->insert_id;

		AuditBridge::log( 'create', 'partner_document', $id, [], [ 'title' => $title, 'mime' => $real_mime, 'size' => $size, 'sha256' => $row['sha256'] ], $actor_id );
		return $id;
	}

	/** @return array<string, mixed>|null Nur aktive Dokumente. */
	public static function get( int $id ): ?array {
		global $wpdb;
		$table = PartnerDocumentSchema::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND status = 'active'", $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/** @return array<int, array<string, mixed>> Aktive Dokumente, neueste zuerst. */
	public static function get_active(): array {
		global $wpdb;
		$table = PartnerDocumentSchema::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( "SELECT id, title, description, original_name, mime_type, size_bytes, created_at FROM {$table} WHERE status = 'active' ORDER BY created_at DESC, id DESC", ARRAY_A );
		return is_array( $rows ) ? $rows : [];
	}

	/** Absoluter Dateipfad eines aktiven Dokuments oder null; realpath-Guard auf das Verzeichnis. */
	public static function file_path( array $row ): ?string {
		$base = realpath( self::get_upload_path() );
		$path = realpath( self::get_upload_path() . '/' . basename( (string) $row['stored_name'] ) );
		if ( false === $base || false === $path || ! str_starts_with( $path, $base . DIRECTORY_SEPARATOR ) || ! is_readable( $path ) ) {
			return null;
		}
		return $path;
	}

	/** Soft-Delete: Datei bleibt für den Audit-Trail erhalten, Dokument verschwindet aus allen Listen. */
	public static function soft_delete( int $id, int $actor_id ): bool|\WP_Error {
		$row = self::get( $id );
		if ( null === $row ) {
			return new \WP_Error( 'liw_not_found', __( 'Dokument nicht gefunden.', 'liebherr-interface-world' ) );
		}
		global $wpdb;
		$ok = $wpdb->update( PartnerDocumentSchema::table_name(), [ 'status' => 'deleted', 'deleted_at' => current_time( 'mysql' ) ], [ 'id' => $id ] );
		if ( false === $ok ) {
			return new \WP_Error( 'liw_db_error', __( 'Dokument konnte nicht gelöscht werden.', 'liebherr-interface-world' ) );
		}
		AuditBridge::log( 'delete', 'partner_document', $id, [ 'title' => $row['title'] ], [], $actor_id );
		return true;
	}

	/** Protokolliert einen erfolgten Download (§10 Audit – wer, was, wann). */
	public static function log_download( int $id, int $user_id ): void {
		AuditBridge::log( 'download', 'partner_document', $id, [], [ 'user_id' => $user_id ], $user_id );
	}

	/**
	 * Erkennt Uploads aus dem Selbsttest (Terminal, kein echter HTTP-Upload): nur wenn die Datei
	 * innerhalb des WP-Temp-Verzeichnisses liegt UND WP_ENVIRONMENT_TYPE=development ist.
	 * Im Produktivbetrieb bleibt is_uploaded_file() zwingend.
	 */
	private static function is_test_upload( array $file ): bool {
		if ( 'development' !== wp_get_environment_type() || PHP_SAPI !== 'cli' ) {
			return false;
		}
		$tmp = realpath( (string) ( $file['tmp_name'] ?? '' ) );
		$dir = realpath( get_temp_dir() );
		return false !== $tmp && false !== $dir && str_starts_with( $tmp, rtrim( $dir, '/' ) . '/' );
	}
}
