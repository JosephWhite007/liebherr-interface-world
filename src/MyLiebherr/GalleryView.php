<?php
/**
 * Liebherr World – My Liebherr: Own Gallery (Pflichtenheft My Liebherr §31, ADR-LIW-MYL-001 R3).
 *
 * Private Galerie: Bilder aus der Mediathek (Attachment-ID) mit Titel, Beschreibung, Tags, Album und Sichtbarkeit;
 * gezielte Freigaben an Kollegen (Person/Team) oder an die World (Review vorbehalten) je Objekt, inkl. Widerruf.
 * Shortcode `[liw_my_gallery]`; self-gating; nur eigene Objekte (§35/SEC 01).
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.119
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class GalleryView {

	public const SHORTCODE = 'liw_my_gallery';

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
		if ( ! Flags::enabled() || ! is_user_logged_in() || ! current_user_can( Roles::CAP_ACCESS ) ) {
			return '';
		}
		return '<div class="liw-myl">' . self::render( get_current_user_id() ) . '</div>';
	}

	public static function render( int $uid ): string {
		return '<section class="liw-myl__gallery" id="liw-my-gallery">'
			. '<h2 class="liw-myl__tile-title">' . esc_html__( 'Own Gallery', 'liebherr-interface-world' ) . '</h2>'
			. self::grid_html( GalleryRepository::for_owner( $uid ) )
			. self::add_form_html()
			. '</section>';
	}

	/** @param array<int,array<string,mixed>> $items */
	private static function grid_html( array $items ): string {
		if ( [] === $items ) {
			return '<p class="liw-myl__wallet-note">' . esc_html__( 'Noch keine Bilder in der Galerie.', 'liebherr-interface-world' ) . '</p>';
		}
		$cards = '';
		foreach ( $items as $it ) {
			$id  = (int) $it['id'];
			$mid = (int) $it['media_id'];
			$img = MediaPipeline::thumb( $mid, 'medium', '#' . $mid );
			$approve = ( $mid > 0 && MediaPipeline::S_APPROVED !== MediaPipeline::state( $mid ) && current_user_can( Roles::CAP_MODERATE ) )
				? '<button type="button" class="liw-myl__wbtn" data-liw-act="moderation/media/' . $mid . '/approve">' . esc_html__( 'Bild freigeben', 'liebherr-interface-world' ) . '</button>'
				: '';
			$cards .= '<figure class="liw-myl__dream">'
				. $img
				. '<figcaption>'
				. '<strong class="liw-myl__dream-title">' . esc_html( (string) $it['title'] ) . '</strong>' . $approve
				. ( '' !== (string) $it['album'] ? ' <span class="liw-myl__dream-tags">' . esc_html( (string) $it['album'] ) . '</span>' : '' )
				. ( '' !== (string) $it['description'] ? '<p class="liw-myl__dream-note">' . esc_html( (string) $it['description'] ) . '</p>' : '' )
				. self::shares_html( $id )
				. self::share_form_html( $id )
				. '<span class="liw-myl__wctl"><button type="button" class="liw-myl__wbtn" data-liw-act="gallery/' . $id . '" data-liw-method="DELETE" aria-label="' . esc_attr__( 'Entfernen', 'liebherr-interface-world' ) . '">✕</button></span>'
				. '</figcaption></figure>';
		}
		return '<div class="liw-myl__dream-grid">' . $cards . '</div>';
	}

	private static function shares_html( int $item_id ): string {
		$shares = ShareRepository::for_item( 'gallery', $item_id );
		if ( [] === $shares ) {
			return '';
		}
		$li = '';
		foreach ( $shares as $s ) {
			$who = 'world' === $s['recipient_type']
				? esc_html__( 'World', 'liebherr-interface-world' )
				: esc_html( sprintf( '%s #%d', $s['recipient_type'], (int) $s['recipient_id'] ) );
			$li .= '<li>' . $who . ' – ' . esc_html( (string) $s['scope'] ) . ' (' . esc_html( (string) $s['status'] ) . ') '
				. '<button type="button" class="liw-myl__wbtn" data-liw-act="shares/' . (int) $s['id'] . '" data-liw-method="DELETE" aria-label="' . esc_attr__( 'Freigabe widerrufen', 'liebherr-interface-world' ) . '">✕</button></li>';
		}
		return '<ul class="liw-myl__shares">' . $li . '</ul>';
	}

	private static function share_form_html( int $item_id ): string {
		return '<form class="liw-myl__shareform" data-liw-post="gallery/' . $item_id . '/shares">'
			. '<select name="recipient_type">'
			. '<option value="user">' . esc_html__( 'Person', 'liebherr-interface-world' ) . '</option>'
			. '<option value="team">' . esc_html__( 'Team', 'liebherr-interface-world' ) . '</option>'
			. '<option value="world">' . esc_html__( 'World (Review)', 'liebherr-interface-world' ) . '</option>'
			. '</select>'
			. '<input type="number" name="recipient_id" min="0" value="0" aria-label="' . esc_attr__( 'Empfänger-ID', 'liebherr-interface-world' ) . '">'
			. '<select name="scope"><option value="view">' . esc_html__( 'ansehen', 'liebherr-interface-world' ) . '</option><option value="comment">' . esc_html__( 'kommentieren', 'liebherr-interface-world' ) . '</option><option value="download">' . esc_html__( 'herunterladen', 'liebherr-interface-world' ) . '</option></select>'
			. '<button type="submit" class="liw-myl__wbtn">' . esc_html__( 'Teilen', 'liebherr-interface-world' ) . '</button>'
			. '</form>';
	}

	private static function add_form_html(): string {
		return '<p class="liw-myl__privacyhint">' . esc_html__( 'Hinweis: Nur freigegebene Mediathek-Bilder erscheinen; ungeprüfte landen in Prüfung. Vor Freigabe Gesichter, Kennzeichen, Kundendaten und Betriebsgeheimnisse prüfen (§14).', 'liebherr-interface-world' ) . '</p>'
			. '<form class="liw-myl__form" data-liw-post="gallery">'
			. '<label class="liw-myl__field"><span>' . esc_html__( 'Bild-ID (Mediathek)', 'liebherr-interface-world' ) . '</span><input type="number" name="media_id" min="0" value="0"></label>'
			. '<label class="liw-myl__field"><span>' . esc_html__( 'Titel', 'liebherr-interface-world' ) . '</span><input type="text" name="title" maxlength="160"></label>'
			. '<label class="liw-myl__field"><span>' . esc_html__( 'Album', 'liebherr-interface-world' ) . '</span><input type="text" name="album" maxlength="80"></label>'
			. '<label class="liw-myl__field liw-myl__field--wide"><span>' . esc_html__( 'Beschreibung', 'liebherr-interface-world' ) . '</span><input type="text" name="description" maxlength="255"></label>'
			. '<label class="liw-myl__field"><span>' . esc_html__( 'Tags', 'liebherr-interface-world' ) . '</span><input type="text" name="tags" maxlength="255"></label>'
			. '<div class="liw-myl__formrow"><button type="submit" class="liw-myl__action">' . esc_html__( 'Bild hinzufügen', 'liebherr-interface-world' ) . '</button></div>'
			. '</form>';
	}
}
