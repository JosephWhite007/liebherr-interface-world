<?php
/**
 * Liebherr Adventures – Medien-Upload (Backlog A6, §14).
 *
 * Nimmt ein hochgeladenes Bild entgegen und legt es als WordPress-Anhang an (nutzt Core-Medienpipeline
 * inkl. Metadaten/Thumbnails). Nur Bilder (jpg/png/webp/gif) im MVP; Video-Upload/Transcoding ist bewusst
 * eine spätere Etappe (§14). Die reine Endungs-/Typprüfung (`is_allowed_ext`) ist ohne WordPress testbar.
 *
 * @package Liebherr\InterfaceWorld\Adventures
 * @since   0.1.0-alpha.67
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Adventures;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class UploadService {

	/** @return array<int,string> Erlaubte Bild-Endungen (MVP). */
	public static function allowed_exts(): array {
		return [ 'jpg', 'jpeg', 'png', 'webp', 'gif' ];
	}

	/** Rein/testbar: ist die Dateiendung ein erlaubter Bildtyp? */
	public static function is_allowed_ext( string $filename ): bool {
		$ext = strtolower( (string) pathinfo( $filename, PATHINFO_EXTENSION ) );
		return '' !== $ext && in_array( $ext, self::allowed_exts(), true );
	}

	/** @return array<string,string> mimes-Overrides für wp_handle_upload. */
	public static function allowed_mimes(): array {
		return [
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'webp'     => 'image/webp',
			'gif'      => 'image/gif',
		];
	}

	/**
	 * Legt aus einem hochgeladenen Bild einen Anhang an. Erwartet ein `$_FILES`-Element.
	 *
	 * @param array<string,mixed> $file
	 * @return array{ok:bool,error?:string,id?:int,url?:string}
	 */
	public static function handle( array $file ): array {
		$name = (string) ( $file['name'] ?? '' );
		if ( '' === $name || ! isset( $file['tmp_name'] ) ) {
			return [ 'ok' => false, 'error' => 'no_file' ];
		}
		if ( ! self::is_allowed_ext( $name ) ) {
			return [ 'ok' => false, 'error' => 'type_not_allowed' ];
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$_FILES['liw_adv_upload'] = $file;
		$attachment_id = media_handle_upload( 'liw_adv_upload', 0, [], [ 'test_form' => false, 'mimes' => self::allowed_mimes() ] );
		unset( $_FILES['liw_adv_upload'] );

		if ( is_wp_error( $attachment_id ) ) {
			return [ 'ok' => false, 'error' => 'upload_failed' ];
		}
		$id  = (int) $attachment_id;
		$url = (string) wp_get_attachment_image_url( $id, 'large' );
		return [ 'ok' => true, 'id' => $id, 'url' => $url ];
	}
}
