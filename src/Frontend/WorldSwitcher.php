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

	/** key der aktuell angezeigten Insel (oder '' ). */
	public static function current_key(): string {
		$id = (int) get_queried_object_id();
		foreach ( self::worlds() as $key => $w ) {
			if ( $w['id'] === $id ) {
				return $key;
			}
		}
		return '';
	}

	public static function shortcode(): string {
		return self::render( self::current_key() );
	}

	public static function render( string $current = '' ): string {
		$worlds = self::worlds();
		if ( count( $worlds ) < 2 ) {
			return ''; // Ohne mindestens zwei Zielen keine Umschaltung.
		}
		$items = '';
		foreach ( $worlds as $key => $w ) {
			$is_cur = $key === $current;
			$items .= sprintf(
				'<li class="liw-switcher__item"><a class="liw-switcher__link%1$s" href="%2$s"%3$s>%4$s</a></li>',
				$is_cur ? ' is-current' : '',
				esc_url( $w['url'] ),
				$is_cur ? ' aria-current="page"' : '',
				esc_html( $w['label'] )
			);
		}
		return '<nav class="liw-switcher" aria-label="' . esc_attr__( 'Liebherr World – Bereiche', 'liebherr-interface-world' ) . '">'
			. '<span class="liw-switcher__brand">' . esc_html__( 'Liebherr World', 'liebherr-interface-world' ) . '</span>'
			. '<ul class="liw-switcher__list">' . $items . '</ul></nav>';
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
