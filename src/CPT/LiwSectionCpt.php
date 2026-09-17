<?php
/**
 * Liebherr Interface Solutions – Section CPT
 *
 * Bildet die 14 Landingpage-Abschnitte (LP-01…LP-14, Liebherr-Pflichtenheft §8) als Custom
 * Post Type ab – kein eigenes `liw_page/section/content`-Schema (CLAUDE.md Abschnitt 5:
 * „WP-APIs bevorzugen, keine Eigenentwicklung wenn WordPress eine saubere Lösung besitzt").
 * post_title/post_content werden vom Core-Übersetzungssystem automatisch erfasst
 * (PostFieldAdapter, s. CoreBridge\TranslationBridge).
 *
 * Freigabeworkflow (Liebherr §19: Entwurf → Prüfung → freigegeben → veröffentlicht) nutzt den
 * nativen WP-Post-Status-Mechanismus (`register_post_status`) statt eines eigenen
 * Freigabe-Datenmodells – WP kennt Entwurf/Prüfung/Veröffentlicht bereits nativ, ergänzt wird
 * nur der plattformspezifische Zwischenstatus „freigegeben".
 *
 * @package Liebherr\InterfaceWorld\CPT
 * @since   0.1.0-alpha.1
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\CPT;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class LiwSectionCpt {

	public const POST_TYPE      = 'liw_section';
	public const STATUS_APPROVED = 'liw_approved';

	public static function register(): void {
		register_post_type( self::POST_TYPE, [
			'labels'       => [
				'name'          => __( 'Interface World – Abschnitte', 'liebherr-interface-world' ),
				'singular_name' => __( 'Abschnitt', 'liebherr-interface-world' ),
				'add_new_item'  => __( 'Neuen Abschnitt anlegen', 'liebherr-interface-world' ),
				'edit_item'     => __( 'Abschnitt bearbeiten', 'liebherr-interface-world' ),
			],
			'public'       => true,
			'show_in_menu' => false, // Eigenes Admin-Board (InterfaceBoardPage) statt Standardmenü.
			'show_ui'      => true,
			'has_archive'  => false,
			'rewrite'      => [ 'slug' => 'interface-world', 'with_front' => false ],
			'supports'     => [ 'title', 'editor', 'revisions', 'page-attributes' ],
			// Bewusst Standard-'post'-Capability-Type statt eigener Capabilities (KISS/YAGNI,
			// CLAUDE.md Abschnitt 5): Zugriff auf das Admin-Board wird zusätzlich über
			// CoreBridge\RoleBridge::CAP_MANAGE_CONTENT gegated, nicht über CPT-Edit-Caps.
			'capability_type' => 'post',
			'show_in_rest'    => true,
		] );

		register_post_status( self::STATUS_APPROVED, [
			'label'                     => _x( 'Freigegeben', 'post status', 'liebherr-interface-world' ),
			'public'                    => false,
			'internal'                  => true,
			'protected'                 => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Freigegeben <span class="count">(%s)</span>', 'Freigegeben <span class="count">(%s)</span>', 'liebherr-interface-world' ),
		] );
	}
}
