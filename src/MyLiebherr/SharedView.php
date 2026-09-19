<?php
/**
 * Liebherr World – My Liebherr: Shared with Colleagues / with the World (Pflichtenheft My Liebherr §31, ADR-LIW-MYL-001 R3).
 *
 * Zwei lesende Ansichten: `[liw_shared_colleagues]` zeigt die mit dem angemeldeten Nutzer geteilten Galerie-Objekte
 * (eingehende Freigaben, Status active). `[liw_shared_world]` zeigt die an die World gerichteten Freigaben mit ihrem
 * Status (Review vorbehalten). Bilder werden über {@see GalleryRepository::get_any()} aufgelöst – Zugriff nur über
 * eine bestehende Freigabe.
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.119
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SharedView {

	public const SC_COLLEAGUES = 'liw_shared_colleagues';
	public const SC_WORLD      = 'liw_shared_world';

	public static function register(): void {
		add_shortcode( self::SC_COLLEAGUES, [ self::class, 'colleagues' ] );
		add_shortcode( self::SC_WORLD, [ self::class, 'world' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'assets' ] );
	}

	public static function assets(): void {
		if ( is_admin() ) {
			return;
		}
		$post = get_post();
		if ( $post instanceof \WP_Post && ( has_shortcode( (string) $post->post_content, self::SC_COLLEAGUES ) || has_shortcode( (string) $post->post_content, self::SC_WORLD ) ) ) {
			OverviewView::assets_for_shortcode();
		}
	}

	private static function gate(): bool {
		return Flags::enabled() && is_user_logged_in() && current_user_can( Roles::CAP_ACCESS );
	}

	public static function colleagues(): string {
		if ( ! self::gate() ) {
			return '';
		}
		$shares = ShareRepository::incoming_for_user( get_current_user_id() );
		$body   = self::items_html( $shares, true );
		return '<div class="liw-myl"><section class="liw-myl__shared" id="liw-shared-colleagues">'
			. '<h2 class="liw-myl__tile-title">' . esc_html__( 'Shared with Colleagues', 'liebherr-interface-world' ) . '</h2>'
			. $body . '</section></div>';
	}

	public static function world(): string {
		if ( ! self::gate() ) {
			return '';
		}
		$shares = ShareRepository::world_list();
		$body   = self::items_html( $shares, false );
		return '<div class="liw-myl"><section class="liw-myl__shared" id="liw-shared-world">'
			. '<h2 class="liw-myl__tile-title">' . esc_html__( 'Shared with the World', 'liebherr-interface-world' ) . '</h2>'
			. $body . '</section></div>';
	}

	/**
	 * @param array<int,array<string,mixed>> $shares
	 */
	private static function items_html( array $shares, bool $show_scope ): string {
		if ( [] === $shares ) {
			return '<p class="liw-myl__wallet-note">' . esc_html__( 'Keine Einträge.', 'liebherr-interface-world' ) . '</p>';
		}
		$cards = '';
		foreach ( $shares as $s ) {
			if ( 'gallery' !== $s['item_type'] ) {
				continue;
			}
			$item = GalleryRepository::get_any( (int) $s['item_id'] );
			if ( null === $item ) {
				continue;
			}
			$img = (int) $item['media_id'] > 0
				? wp_get_attachment_image( (int) $item['media_id'], 'medium', false, [ 'class' => 'liw-myl__dream-img' ] )
				: '<span class="liw-myl__dream-ref">#' . (int) $item['media_id'] . '</span>';
			$meta = $show_scope
				? esc_html( (string) $s['scope'] )
				: esc_html( (string) $s['status'] );
			$cards .= '<figure class="liw-myl__dream">' . $img . '<figcaption>'
				. '<strong class="liw-myl__dream-title">' . esc_html( (string) $item['title'] ) . '</strong>'
				. ' <span class="liw-myl__dream-tags">' . $meta . '</span>'
				. ( $show_scope ? '' : self::report_form_html( (int) $item['id'] ) )
				. '</figcaption></figure>';
		}
		return '' !== $cards ? '<div class="liw-myl__dream-grid">' . $cards . '</div>' : '<p class="liw-myl__wallet-note">' . esc_html__( 'Keine Einträge.', 'liebherr-interface-world' ) . '</p>';
	}

	/** „Melden"-Formular für ein World-Bild (§11 Meldegründe). */
	private static function report_form_html( int $gallery_id ): string {
		$reasons = '';
		foreach ( ContentRules::REPORT_REASONS as $r ) {
			$reasons .= '<option value="' . esc_attr( $r ) . '">' . esc_html( self::reason_label( $r ) ) . '</option>';
		}
		return '<form class="liw-myl__reportform" data-liw-post="reports">'
			. '<input type="hidden" name="object_type" value="gallery">'
			. '<input type="hidden" name="object_id" value="' . $gallery_id . '">'
			. '<select name="reason" aria-label="' . esc_attr__( 'Meldegrund', 'liebherr-interface-world' ) . '">' . $reasons . '</select>'
			. '<input type="text" name="note" maxlength="255" placeholder="' . esc_attr__( 'Hinweis (optional)', 'liebherr-interface-world' ) . '">'
			. '<button type="submit" class="liw-myl__wbtn">' . esc_html__( 'Melden', 'liebherr-interface-world' ) . '</button>'
			. '</form>';
	}

	private static function reason_label( string $r ): string {
		$map = [
			'dangerous'        => __( 'gefährlich', 'liebherr-interface-world' ),
			'wrong'            => __( 'falsch', 'liebherr-interface-world' ),
			'outdated'         => __( 'veraltet', 'liebherr-interface-world' ),
			'privacy'          => __( 'Datenschutz', 'liebherr-interface-world' ),
			'rights'           => __( 'Rechteverletzung', 'liebherr-interface-world' ),
			'duplicate'        => __( 'Duplikat', 'liebherr-interface-world' ),
			'misleading_price' => __( 'irreführender Preis', 'liebherr-interface-world' ),
		];
		return $map[ $r ] ?? $r;
	}
}
