<?php
/**
 * Liebherr World – My Liebherr: Moderations-Queue (Pflichtenheft My Liebherr §11/§31, ADR-LIW-MYL-001 R4).
 *
 * Prüferansicht: ausstehende World-Freigaben (freigeben/ablehnen) und offene Meldungen (verwerfen/sperren/
 * Erstattung anstoßen). Shortcode `[liw_moderation]`; nur sichtbar für Prüfer ({@see Roles::CAP_MODERATE}) –
 * für alle anderen leer. Aktionen laufen über die Moderations-REST (nur Prüfer).
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.131
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ModerationView {

	public const SHORTCODE = 'liw_moderation';

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
		if ( ! Flags::enabled() || ! is_user_logged_in() || ! current_user_can( Roles::CAP_MODERATE ) ) {
			return ''; // Nur Prüfer sehen die Queue.
		}
		$queue = ModerationService::queue();
		return '<div class="liw-myl"><section class="liw-myl__moderation" id="liw-moderation">'
			. '<h2 class="liw-myl__tile-title">' . esc_html__( 'Moderation', 'liebherr-interface-world' ) . '</h2>'
			. self::world_html( (array) $queue['world_pending'] )
			. self::reports_html( (array) $queue['reports'] )
			. '</section></div>';
	}

	/** @param array<int,array<string,mixed>> $shares */
	private static function world_html( array $shares ): string {
		$out = '<h3 class="liw-myl__tile-title">' . esc_html__( 'Ausstehende World-Freigaben', 'liebherr-interface-world' ) . '</h3>';
		if ( [] === $shares ) {
			return $out . '<p class="liw-myl__wallet-note">' . esc_html__( 'Keine ausstehenden Freigaben.', 'liebherr-interface-world' ) . '</p>';
		}
		$li = '';
		foreach ( $shares as $s ) {
			$sid   = (int) $s['id'];
			$title = self::gallery_title( (int) $s['item_id'] );
			$li   .= '<li>' . esc_html( sprintf( /* translators: 1: title 2: grantor id */ __( '„%1$s" (von #%2$d)', 'liebherr-interface-world' ), $title, (int) $s['grantor_id'] ) ) . ' '
				. '<button type="button" class="liw-myl__action" data-liw-act="moderation/shares/' . $sid . '/review?decision=approve">' . esc_html__( 'Freigeben', 'liebherr-interface-world' ) . '</button> '
				. '<button type="button" class="liw-myl__wbtn" data-liw-act="moderation/shares/' . $sid . '/review?decision=reject">' . esc_html__( 'Ablehnen', 'liebherr-interface-world' ) . '</button></li>';
		}
		return $out . '<ul class="liw-myl__shares">' . $li . '</ul>';
	}

	/** @param array<int,array<string,mixed>> $reports */
	private static function reports_html( array $reports ): string {
		$out = '<h3 class="liw-myl__tile-title">' . esc_html__( 'Offene Meldungen', 'liebherr-interface-world' ) . '</h3>';
		if ( [] === $reports ) {
			return $out . '<p class="liw-myl__wallet-note">' . esc_html__( 'Keine offenen Meldungen.', 'liebherr-interface-world' ) . '</p>';
		}
		$li = '';
		foreach ( $reports as $r ) {
			$rid  = (int) $r['id'];
			$desc = sprintf( '%s #%d – %s', (string) $r['object_type'], (int) $r['object_id'], (string) $r['reason'] );
			$li  .= '<li>' . esc_html( $desc ) . ( '' !== (string) $r['note'] ? ' <em>' . esc_html( (string) $r['note'] ) . '</em>' : '' ) . ' '
				. '<button type="button" class="liw-myl__wbtn" data-liw-act="moderation/reports/' . $rid . '/resolve?action=dismiss">' . esc_html__( 'Verwerfen', 'liebherr-interface-world' ) . '</button> '
				. '<button type="button" class="liw-myl__wbtn" data-liw-act="moderation/reports/' . $rid . '/resolve?action=suspend">' . esc_html__( 'Sperren', 'liebherr-interface-world' ) . '</button> '
				. '<button type="button" class="liw-myl__wbtn" data-liw-act="moderation/reports/' . $rid . '/resolve?action=refund">' . esc_html__( 'Erstattung', 'liebherr-interface-world' ) . '</button></li>';
		}
		return $out . '<ul class="liw-myl__shares">' . $li . '</ul>';
	}

	private static function gallery_title( int $id ): string {
		$item = class_exists( GalleryRepository::class ) ? GalleryRepository::get_any( $id ) : null;
		return null !== $item ? (string) $item['title'] : ( '#' . $id );
	}
}
