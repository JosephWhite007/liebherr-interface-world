<?php
/**
 * Liebherr World – My Liebherr: Medien-Pipeline (Pflichtenheft My Liebherr §14/§16, ADR-LIW-MYL-001).
 *
 * Prüft Mediathek-Anhänge, die in Dreams/Gallery verwendet werden: erlaubte MIME-Typen, Größenlimit, Virenscan
 * (Naht) und Freigabestatus (CI-005 über {@see \Liebherr\InterfaceWorld\CoreBridge\MediaBridge}). Nicht
 * freigegebene/ungeprüfte Medien landen in Quarantäne (Anzeige „in Prüfung" statt Bild). Die reinen Regeln
 * ({@see mime_allowed()}/{@see size_ok()}/{@see classify()}) sind ohne WordPress testbar.
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.133
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

use Liebherr\InterfaceWorld\CoreBridge\MediaBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MediaPipeline {

	public const ALLOWED_MIME = [ 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ];

	// Zustände: none (kein Bild), invalid (Typ/Größe), quarantine (ungeprüft/nicht freigegeben), approved.
	public const S_NONE       = 'none';
	public const S_INVALID    = 'invalid';
	public const S_QUARANTINE = 'quarantine';
	public const S_APPROVED   = 'approved';

	public static function mime_allowed( string $mime ): bool {
		return in_array( $mime, self::ALLOWED_MIME, true );
	}

	/** Größenlimit in Bytes (Standard 8 MB), administrierbar. */
	public static function max_bytes(): int {
		$b = (int) get_option( 'liw_myl_media_max_bytes', 8388608 );
		return max( 1, (int) apply_filters( 'liw_myl_media_max_bytes', $b ) );
	}

	public static function size_ok( int $bytes, int $max ): bool {
		return $bytes > 0 && $bytes <= $max;
	}

	/** Reine Zustandsableitung. */
	public static function classify( bool $exists, bool $mime_ok, bool $size_ok, bool $scan_ok, bool $approved ): string {
		if ( ! $exists || ! $mime_ok || ! $size_ok ) {
			return self::S_INVALID;
		}
		if ( ! $scan_ok || ! $approved ) {
			return self::S_QUARANTINE;
		}
		return self::S_APPROVED;
	}

	/**
	 * Formal-Validierung eines Anhangs (Typ + Größe). @return array{ok:bool,reason:string}
	 */
	public static function validate( int $attachment_id ): array {
		if ( $attachment_id <= 0 ) {
			return [ 'ok' => true, 'reason' => '' ]; // kein Bild ist erlaubt (Maschinenbezug/Text)
		}
		if ( 'attachment' !== get_post_type( $attachment_id ) ) {
			return [ 'ok' => false, 'reason' => 'not_attachment' ];
		}
		if ( ! self::mime_allowed( (string) get_post_mime_type( $attachment_id ) ) ) {
			return [ 'ok' => false, 'reason' => 'mime_not_allowed' ];
		}
		$path  = (string) get_attached_file( $attachment_id );
		$bytes = ( '' !== $path && is_readable( $path ) ) ? (int) filesize( $path ) : 0;
		if ( $bytes > 0 && ! self::size_ok( $bytes, self::max_bytes() ) ) {
			return [ 'ok' => false, 'reason' => 'too_large' ];
		}
		return [ 'ok' => true, 'reason' => '' ];
	}

	/** Virenscan-Naht: Standard „bestanden"; ein externer Scanner kann über den Filter blocken. */
	public static function scan_ok( int $attachment_id ): bool {
		return (bool) apply_filters( 'liw_media_scan_passed', true, $attachment_id );
	}

	/** Anzeigezustand eines in Dreams/Gallery referenzierten Anhangs. */
	public static function state( int $attachment_id ): string {
		if ( $attachment_id <= 0 ) {
			return self::S_NONE;
		}
		$exists  = 'attachment' === get_post_type( $attachment_id );
		$mime_ok = $exists && self::mime_allowed( (string) get_post_mime_type( $attachment_id ) );
		$path    = $exists ? (string) get_attached_file( $attachment_id ) : '';
		$bytes   = ( '' !== $path && is_readable( $path ) ) ? (int) filesize( $path ) : 0;
		$size_ok = 0 === $bytes || self::size_ok( $bytes, self::max_bytes() ); // 0 = unbekannt → nicht als invalid werten
		return self::classify( $exists, $mime_ok, $size_ok, self::scan_ok( $attachment_id ), MediaBridge::is_approved( $attachment_id ) );
	}

	/**
	 * Vorschaubild eines referenzierten Anhangs – nur bei Freigabe das Bild, sonst ein „in Prüfung"/ungültig-
	 * Platzhalter (Quarantäne §16). Fallback-Text (z. B. Maschinenbezug) wird bei fehlendem/ungültigem Bild gezeigt.
	 */
	public static function thumb( int $attachment_id, string $size = 'medium', string $fallback = '' ): string {
		$state = self::state( $attachment_id );
		if ( self::S_APPROVED === $state ) {
			return wp_get_attachment_image( $attachment_id, $size, false, [ 'class' => 'liw-myl__dream-img' ] );
		}
		if ( self::S_QUARANTINE === $state ) {
			return '<span class="liw-myl__dream-ref liw-myl__media-hold">' . esc_html__( 'Bild in Prüfung', 'liebherr-interface-world' ) . '</span>';
		}
		if ( self::S_INVALID === $state ) {
			return '<span class="liw-myl__dream-ref liw-myl__media-bad">' . esc_html__( 'Bild ungültig', 'liebherr-interface-world' ) . '</span>';
		}
		return '<span class="liw-myl__dream-ref">' . esc_html( $fallback ) . '</span>'; // none
	}

	/** Prüfer-Freigabe eines Anhangs (setzt CI-005-Meta). */
	public static function approve( int $attachment_id ): bool {
		if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
			return false;
		}
		update_post_meta( $attachment_id, MediaBridge::META_APPROVED, '1' );
		return true;
	}
}
