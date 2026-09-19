<?php
/**
 * Liebherr World – My Liebherr: My Dreams Ansicht (Pflichtenheft My Liebherr §31, ADR-LIW-MYL-001 R3).
 *
 * Persönliches Maschinen-Bilderbuch: Bild (Mediathek-ID) oder Maschinenbezug, Drei-Wort-Titel, Notiz, Tags,
 * Sammlung, Cover und Wunschstatus; hinzufügen/ändern/entfernen/ordnen über die REST-Endpunkte
 * ({@see ContentRest}). Standardmäßig privat. Shortcode `[liw_my_dreams]`; self-gating; nur eigener Nutzer.
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.118
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class DreamsView {

	public const SHORTCODE = 'liw_my_dreams';

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'assets' ] );
	}

	public static function assets(): void {
		if ( is_admin() ) {
			return;
		}
		$post = get_post();
		if ( $post instanceof \WP_Post && has_shortcode( (string) $post->post_content, self::SHORTCODE ) ) {
			OverviewView::assets_for_shortcode();
		}
	}

	public static function shortcode(): string {
		if ( ! Flags::enabled() ) {
			return '';
		}
		if ( ! is_user_logged_in() || ! current_user_can( Roles::CAP_ACCESS ) ) {
			return '';
		}
		return '<div class="liw-myl">' . self::render( get_current_user_id() ) . '</div>';
	}

	public static function render( int $uid ): string {
		$items = DreamRepository::for_user( $uid );
		return '<section class="liw-myl__dreams" id="liw-my-dreams">'
			. '<h2 class="liw-myl__tile-title">' . esc_html__( 'My Dreams', 'liebherr-interface-world' ) . '</h2>'
			. self::grid_html( $items )
			. self::add_form_html()
			. '</section>';
	}

	/** @param array<int,array<string,mixed>> $items */
	private static function grid_html( array $items ): string {
		if ( [] === $items ) {
			return '<p class="liw-myl__wallet-note">' . esc_html__( 'Noch keine Traum-Maschinen gemerkt.', 'liebherr-interface-world' ) . '</p>';
		}
		$cards = '';
		foreach ( $items as $it ) {
			$id  = (int) $it['id'];
			$img = MediaPipeline::thumb( (int) $it['media_id'], 'medium', (string) $it['machine_ref'] );
			$cards .= '<figure class="liw-myl__dream' . ( $it['cover'] ? ' is-cover' : '' ) . '">'
				. $img
				. '<figcaption>'
				. '<strong class="liw-myl__dream-title">' . esc_html( (string) $it['title_words'] ) . '</strong>'
				. ' <span class="liw-myl__dream-wish liw-myl__dream-wish--' . esc_attr( (string) $it['wish_status'] ) . '">' . esc_html( self::wish_label( (string) $it['wish_status'] ) ) . '</span>'
				. ( '' !== (string) $it['note'] ? '<p class="liw-myl__dream-note">' . esc_html( (string) $it['note'] ) . '</p>' : '' )
				. ( '' !== (string) $it['tags'] ? '<span class="liw-myl__dream-tags">' . esc_html( (string) $it['tags'] ) . '</span>' : '' )
				. '<span class="liw-myl__wctl">'
				. '<button type="button" class="liw-myl__wbtn" data-liw-act="dreams/' . $id . '/move?dir=up" aria-label="' . esc_attr__( 'Nach oben', 'liebherr-interface-world' ) . '">▲</button>'
				. '<button type="button" class="liw-myl__wbtn" data-liw-act="dreams/' . $id . '/move?dir=down" aria-label="' . esc_attr__( 'Nach unten', 'liebherr-interface-world' ) . '">▼</button>'
				. '<button type="button" class="liw-myl__wbtn" data-liw-act="dreams/' . $id . '" data-liw-method="DELETE" aria-label="' . esc_attr__( 'Entfernen', 'liebherr-interface-world' ) . '">✕</button>'
				. '</span>'
				. '</figcaption></figure>';
		}
		return '<div class="liw-myl__dream-grid">' . $cards . '</div>';
	}

	private static function add_form_html(): string {
		$wish = '';
		foreach ( ContentRules::WISH as $w ) {
			$wish .= '<option value="' . esc_attr( $w ) . '">' . esc_html( self::wish_label( $w ) ) . '</option>';
		}
		return '<form class="liw-myl__form" data-liw-post="dreams">'
			. '<label class="liw-myl__field"><span>' . esc_html__( 'Bild-ID (Mediathek)', 'liebherr-interface-world' ) . '</span><input type="number" name="media_id" min="0" value="0"></label>'
			. '<label class="liw-myl__field"><span>' . esc_html__( 'Maschinenbezug', 'liebherr-interface-world' ) . '</span><input type="text" name="machine_ref" maxlength="120"></label>'
			. '<label class="liw-myl__field"><span>' . esc_html__( 'Drei-Wort-Titel', 'liebherr-interface-world' ) . '</span><input type="text" name="title_words" maxlength="120" placeholder="Raupe Hydraulik Traum"></label>'
			. '<label class="liw-myl__field"><span>' . esc_html__( 'Sammlung', 'liebherr-interface-world' ) . '</span><input type="text" name="collection" maxlength="80"></label>'
			. '<label class="liw-myl__field"><span>' . esc_html__( 'Wunschstatus', 'liebherr-interface-world' ) . '</span><select name="wish_status">' . $wish . '</select></label>'
			. '<label class="liw-myl__field liw-myl__field--wide"><span>' . esc_html__( 'Notiz', 'liebherr-interface-world' ) . '</span><input type="text" name="note" maxlength="255"></label>'
			. '<label class="liw-myl__field"><span>' . esc_html__( 'Tags', 'liebherr-interface-world' ) . '</span><input type="text" name="tags" maxlength="255"></label>'
			. '<label class="liw-myl__cover"><input type="checkbox" name="cover" value="1"> ' . esc_html__( 'Als Cover', 'liebherr-interface-world' ) . '</label>'
			. '<div class="liw-myl__formrow"><button type="submit" class="liw-myl__action">' . esc_html__( 'Traum-Maschine merken', 'liebherr-interface-world' ) . '</button></div>'
			. '</form>';
	}

	private static function wish_label( string $w ): string {
		$map = [
			'idea'     => __( 'Idee', 'liebherr-interface-world' ),
			'wish'     => __( 'Wunsch', 'liebherr-interface-world' ),
			'favorite' => __( 'Favorit', 'liebherr-interface-world' ),
		];
		return $map[ $w ] ?? $w;
	}
}
