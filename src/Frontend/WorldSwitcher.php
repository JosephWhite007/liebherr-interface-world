<?php
/**
 * Liebherr World – gemeinsamer Plattform-Umschalter der vier Logik-Inseln.
 *
 * Verlinkt die vier Bereiche untereinander (Intelligence World · Local Intelligence · Interface Solutions ·
 * Adventures) als schlanke Leiste. Erscheint automatisch oben auf den vier Insel-Seiten (the_content der
 * Hauptabfrage) und ist zusätzlich als Shortcode `[liw_world_switcher]` platzierbar. Ziele werden über die
 * Seiten-Registry/Optionen aufgelöst (nicht hart codiert); nur vorhandene Seiten werden verlinkt.
 *
 * @package Liebherr\InterfaceWorld\Frontend
 * @since   0.1.0-alpha.52
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Frontend;

use Liebherr\InterfaceWorld\Content\SitePages;
use Liebherr\InterfaceWorld\MyLiebherr\Flags as MylFlags;
use Liebherr\InterfaceWorld\MyLiebherr\Roles as MylRoles;
use Liebherr\InterfaceWorld\Pocket\Flags as PocketFlags;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class WorldSwitcher {

	public static function register(): void {
		add_shortcode( 'liw_world_switcher', [ self::class, 'shortcode' ] );
		add_filter( 'the_content', [ self::class, 'maybe_prepend' ], 9 );
	}

	/**
	 * Vier Inseln (key → [label, url, page_id]); nur vorhandene Seiten.
	 * @return array<string,array{label:string,url:string,id:int}>
	 */
	public static function worlds(): array {
		$iw_id  = (int) get_option( 'liw_iw_page_id', 0 );
		$adv_id = (int) get_option( 'liw_adventures_page_id', 0 );
		$li_id  = SitePages::li_id();
		$if_id  = SitePages::interface_id();

		$all = [
			'intelligence_world' => [ 'label' => __( 'Intelligence World', 'liebherr-interface-world' ), 'id' => $iw_id ],
			'local_intelligence' => [ 'label' => __( 'Local Intelligence', 'liebherr-interface-world' ), 'id' => $li_id ],
			'interface_solutions'=> [ 'label' => __( 'Interface Solutions', 'liebherr-interface-world' ), 'id' => $if_id ],
			'adventures'         => [ 'label' => __( 'Adventures', 'liebherr-interface-world' ), 'id' => $adv_id ],
		];
		$out = [];
		foreach ( $all as $key => $w ) {
			if ( $w['id'] > 0 && 'publish' === get_post_status( $w['id'] ) ) {
				$out[ $key ] = [ 'label' => $w['label'], 'url' => (string) get_permalink( $w['id'] ), 'id' => $w['id'] ];
			}
		}
		return $out;
	}

	/**
	 * Vollständige Plattform-Reiter für die Leiste: die vier Inseln (aus {@see worlds()}) plus – nur wenn My
	 * Liebherr scharf ist ({@see MylFlags::enabled()}) – der persönliche Reiter „My Liebherr" (rollenabhängig,
	 * §35: nur angemeldet mit {@see MylRoles::CAP_ACCESS} und veröffentlichter Seite) und der 6. Reiter „Pocket
	 * Information" als sichtbarer, aber deaktivierter Platzhalter „in Vorbereitung" (JW-Entscheid 19.09.2026,
	 * bewusste Ausprägung von Pflichtenheft §29/§34 „Platzhalter"). {@see worlds()} bleibt bewusst vierinselig,
	 * damit die CVF-Modulauflösung unverändert bleibt (keine Redundanz).
	 *
	 * @return array<int,array{key:string,label:string,url:string,disabled:bool,note:string}>
	 */
	public static function platform_tabs(): array {
		$tabs = [];
		foreach ( self::worlds() as $key => $w ) {
			$tabs[] = [ 'key' => $key, 'label' => $w['label'], 'url' => $w['url'], 'disabled' => false, 'note' => '' ];
		}
		if ( ! MylFlags::enabled() ) {
			return $tabs;
		}
		$myl_id = (int) get_option( 'liw_my_liebherr_page_id', 0 );
		if ( $myl_id > 0 && 'publish' === get_post_status( $myl_id )
			&& is_user_logged_in() && current_user_can( MylRoles::CAP_ACCESS ) ) {
			$tabs[] = [ 'key' => 'my_liebherr', 'label' => __( 'My Liebherr', 'liebherr-interface-world' ), 'url' => (string) get_permalink( $myl_id ), 'disabled' => false, 'note' => '' ];
		}
		$pocket_id = (int) get_option( 'liw_pocket_page_id', 0 );
		if ( PocketFlags::enabled() && $pocket_id > 0 && 'publish' === get_post_status( $pocket_id )
			&& is_user_logged_in() && current_user_can( MylRoles::CAP_ACCESS ) ) {
			$tabs[] = [ 'key' => 'pocket_information', 'label' => __( 'Pocket Information', 'liebherr-interface-world' ), 'url' => (string) get_permalink( $pocket_id ), 'disabled' => false, 'note' => '' ];
		} else {
			$tabs[] = [ 'key' => 'pocket_information', 'label' => __( 'Pocket Information', 'liebherr-interface-world' ), 'url' => '', 'disabled' => true, 'note' => __( 'in Vorbereitung', 'liebherr-interface-world' ) ];
		}
		return $tabs;
	}

	/** key des aktuell angezeigten Bereichs (Insel oder My-Liebherr-Seite), oder '' . */
	public static function current_key(): string {
		$id = (int) get_queried_object_id();
		foreach ( self::worlds() as $key => $w ) {
			if ( $w['id'] === $id ) {
				return $key;
			}
		}
		if ( $id > 0 && $id === (int) get_option( 'liw_my_liebherr_page_id', 0 ) ) {
			return 'my_liebherr';
		}
		if ( $id > 0 && $id === (int) get_option( 'liw_pocket_page_id', 0 ) ) {
			return 'pocket_information';
		}
		return '';
	}

	public static function shortcode(): string {
		return self::render( self::current_key() );
	}

	public static function render( string $current = '' ): string {
		$tabs = self::platform_tabs();
		if ( count( $tabs ) < 2 ) {
			return ''; // Ohne mindestens zwei Ziele keine Umschaltung.
		}
		$items = '';
		foreach ( $tabs as $tab ) {
			$is_cur = $tab['key'] === $current;
			if ( ! empty( $tab['disabled'] ) ) {
				$items .= sprintf(
					'<li class="liw-switcher__item"><span class="liw-switcher__link is-disabled" aria-disabled="true" title="%2$s">%1$s <em class="liw-switcher__note">%2$s</em></span></li>',
					esc_html( $tab['label'] ),
					esc_html( $tab['note'] )
				);
				continue;
			}
			$items .= sprintf(
				'<li class="liw-switcher__item"><a class="liw-switcher__link%1$s" href="%2$s"%3$s>%4$s</a></li>',
				$is_cur ? ' is-current' : '',
				esc_url( $tab['url'] ),
				$is_cur ? ' aria-current="page"' : '',
				esc_html( $tab['label'] )
			);
		}
		return '<nav class="liw-switcher" aria-label="' . esc_attr__( 'Liebherr World – Bereiche', 'liebherr-interface-world' ) . '">'
			. '<div class="liw-switcher__inner">'
			. '<span class="liw-switcher__brand">' . esc_html__( 'Liebherr World', 'liebherr-interface-world' ) . '</span>'
			. '<ul class="liw-switcher__list">' . $items . '</ul>'
			. '</div></nav>';
	}

	/** Automatisch oben auf den vier Insel-Seiten einfügen (nur Hauptabfrage, nicht in eingebetteten Abschnitten). */
	public static function maybe_prepend( string $content ): string {
		if ( is_admin() || ! is_main_query() || ! in_the_loop() ) {
			return $content;
		}
		$current = self::current_key();
		if ( '' === $current ) {
			return $content;
		}
		if ( false !== strpos( $content, 'liw-switcher' ) ) {
			return $content; // bereits per Shortcode vorhanden.
		}
		return self::render( $current ) . $content;
	}
}
