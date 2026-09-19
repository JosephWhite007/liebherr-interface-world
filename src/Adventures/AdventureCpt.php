<?php
/**
 * Liebherr Adventures – Custom Post Type `liw_adventure` (Datenmodell §10, MVP-Teil).
 *
 * Ein Adventure = kleinste Informationseinheit (§2.2). Für den sichtbaren MVP als CPT umgesetzt
 * (nutzt WP-Status draft/pending/publish, Autor, Beitragsbild = Medium); die weiteren Felder
 * (Typ, Dringlichkeit, Sichtbarkeit, Drei-Wörter-Ort, UUID, Lösungsstatus) liegen in Post-Meta.
 * Nicht öffentlich als eigenes Archiv (Ausgabe über Shortcodes/Policy); im Backoffice sichtbar
 * (Moderation, §17). UUID statt fortlaufender öffentlicher ID (§10.2).
 *
 * @package Liebherr\InterfaceWorld\Adventures
 * @since   0.1.0-alpha.51
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Adventures;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AdventureCpt {

	public const POST_TYPE = 'liw_adventure';

	// Meta-Schlüssel.
	public const M_UUID       = '_liw_adv_uuid';
	public const M_TYPE       = '_liw_adv_type';
	public const M_URGENCY    = '_liw_adv_urgency';
	public const M_VISIBILITY = '_liw_adv_visibility';
	public const M_WORDS      = '_liw_adv_words';
	public const M_LAT        = '_liw_adv_lat';
	public const M_LNG        = '_liw_adv_lng';
	public const M_PROVIDER   = '_liw_adv_provider';
	public const M_PROVIDER_V = '_liw_adv_provider_version';
	public const M_ACCURACY   = '_liw_adv_accuracy';
	public const M_REGION     = '_liw_adv_region';
	public const M_PROTECTION = '_liw_adv_protection'; // exact | region | hidden
	public const M_MEDIA_ID   = '_liw_adv_media_id';
	public const M_MEDIA_URL  = '_liw_adv_media_url'; // MVP-Fallback: externe Bild-URL (bis Upload-Backend, §14/§21).
	public const M_SOLVED     = '_liw_adv_solved';     // open | in_progress | solved

	// Basislogik „Adventure Area" (Registrierung, Tokenwert, Veröffentlichung §1–§9).
	public const M_TOKEN_VALUE     = '_liw_adv_token_value';      // vom Ersteller festgelegte Tokenanzahl (int >= 0)
	public const M_REG_STATUS      = '_liw_adv_reg_status';       // RegistrationStatus::*
	public const M_REG_ID          = '_liw_adv_reg_id';           // eindeutige Registrier-ID
	public const M_VERSION         = '_liw_adv_version';          // Beitragsversion (int, ab 1)
	public const M_ARTICLEBOOK_REF = '_liw_adv_articlebook_ref';  // Referenz im Artikelbook
	public const M_ARTICLEBOOK_URL = '_liw_adv_articlebook_url';  // Link zum Artikelbook-Eintrag
	public const M_RIGHTS_OK       = '_liw_adv_rights_confirmed'; // '1' = Rechte/Bereitstellung zugesichert
	public const M_USAGE_SCOPE     = '_liw_adv_usage_scope';      // erlaubter Nutzungsumfang (Freitext/Kennung)

	public static function register(): void {
		register_post_type( self::POST_TYPE, [
			'labels'              => [
				'name'          => __( 'Adventures', 'liebherr-interface-world' ),
				'singular_name' => __( 'Adventure', 'liebherr-interface-world' ),
			],
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => false, // eigener Menüpunkt über AdminMenu (spätere Etappe/Moderation).
			'menu_icon'           => 'dashicons-camera',
			'supports'            => [ 'title', 'editor', 'thumbnail', 'author' ],
			'has_archive'         => false,
			'rewrite'             => false,
			'exclude_from_search' => true,
			'map_meta_cap'        => true,
		] );
	}
}
