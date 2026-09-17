<?php
/**
 * Liebherr Interface Solutions – Media Bridge
 *
 * ABWEICHUNG von der ursprünglichen Annahme (Phase-3-Plan): `Modules\ImageManager` wurde vor
 * der Implementierung geprüft. Es ist ein kalenderbasierter Bildwechsel-Mechanismus mit
 * Rollback (`araliya_image_schedule`, Cron `araliya_image_swap_cron`) – ein anderer Zweck als
 * das in Liebherr-Pflichtenheft §18 geforderte „Media Board" (Assets, Alt-Texte, Rechte,
 * Freigaben, Verwendungsnachweis). Eine Bindung wäre Zweckentfremdung, kein Reuse.
 *
 * Stattdessen: native WP-Medienverwaltung (Medienbibliothek) + zwei zusätzliche, standardkonforme
 * Attachment-Meta-Felder (Copyright/Quelle, Freigabestatus) – kein neues Mediensystem
 * (CI-005: „Alt-Texte, Copyright-Angaben und Asset-Quelle im Medienobjekt speichern").
 * `_wp_attachment_image_alt` ist der native WP-Meta-Key für Alt-Text und wird unverändert
 * mitgenutzt.
 *
 * @package Liebherr\InterfaceWorld\CoreBridge
 * @since   0.1.0-alpha.1
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\CoreBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MediaBridge {

	public const META_COPYRIGHT = '_liw_media_copyright';
	public const META_SOURCE    = '_liw_media_source';
	public const META_APPROVED  = '_liw_media_approved'; // '1' | '0', Freigabestatus CI-005

	public static function register(): void {
		add_filter( 'attachment_fields_to_edit', [ self::class, 'add_fields' ], 10, 2 );
		add_filter( 'attachment_fields_to_save', [ self::class, 'save_fields' ], 10, 2 );
	}

	/** @param array<string, mixed> $form_fields */
	public static function add_fields( array $form_fields, \WP_Post $post ): array {
		$form_fields[ self::META_COPYRIGHT ] = [
			'label' => __( 'Copyright / Rechteinhaber', 'liebherr-interface-world' ),
			'value' => get_post_meta( $post->ID, self::META_COPYRIGHT, true ),
			'input' => 'text',
		];
		$form_fields[ self::META_SOURCE ] = [
			'label' => __( 'Asset-Quelle (Liebherr freigegeben / lizenziert / Platzhalter)', 'liebherr-interface-world' ),
			'value' => get_post_meta( $post->ID, self::META_SOURCE, true ),
			'input' => 'text',
		];
		$form_fields[ self::META_APPROVED ] = [
			'label' => __( 'Freigegeben (CI-005)', 'liebherr-interface-world' ),
			'value' => get_post_meta( $post->ID, self::META_APPROVED, true ) ? __( 'Ja', 'liebherr-interface-world' ) : __( 'Nein', 'liebherr-interface-world' ),
			'input' => 'text',
		];
		return $form_fields;
	}

	/**
	 * @param array<string, mixed> $post
	 * @param array<string, mixed> $attachment
	 */
	public static function save_fields( array $post, array $attachment ): array {
		if ( isset( $attachment[ self::META_COPYRIGHT ] ) ) {
			update_post_meta( $post['ID'], self::META_COPYRIGHT, sanitize_text_field( $attachment[ self::META_COPYRIGHT ] ) );
		}
		if ( isset( $attachment[ self::META_SOURCE ] ) ) {
			update_post_meta( $post['ID'], self::META_SOURCE, sanitize_text_field( $attachment[ self::META_SOURCE ] ) );
		}
		return $post;
	}

	/** CI-005 Gate: nur freigegebene Assets dürfen öffentlich eingebunden werden. */
	public static function is_approved( int $attachment_id ): bool {
		return (bool) get_post_meta( $attachment_id, self::META_APPROVED, true );
	}
}
