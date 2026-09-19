<?php
/**
 * Liebherr World – My Liebherr: My Machines (Pflichtenheft My Liebherr §29 Pos. 09, ADR-LIW-MYL-001 R5).
 *
 * Persönlich zugeordnete Maschinen: Name, Seriennummer, Standort, Notiz, optionaler Dokument-Link; anlegen und
 * entfernen. Shortcode `[liw_my_machines]`; self-gating; nur eigene Maschinen (§35/SEC 01).
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.123
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MachinesView {

	public const SHORTCODE = 'liw_my_machines';

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
		return '<section class="liw-myl__machines" id="liw-my-machines">'
			. '<h2 class="liw-myl__tile-title">' . esc_html__( 'My Machines', 'liebherr-interface-world' ) . '</h2>'
			. self::list_html( MachineRepository::for_user( $uid ) )
			. self::add_form_html()
			. '</section>';
	}

	/** @param array<int,array<string,mixed>> $items */
	private static function list_html( array $items ): string {
		if ( [] === $items ) {
			return '<p class="liw-myl__wallet-note">' . esc_html__( 'Noch keine Maschinen zugeordnet.', 'liebherr-interface-world' ) . '</p>';
		}
		$rows = '';
		foreach ( $items as $m ) {
			$id   = (int) $m['id'];
			$doc  = '' !== (string) $m['doc_url'] ? '<a href="' . esc_url( (string) $m['doc_url'] ) . '">' . esc_html__( 'Dokument', 'liebherr-interface-world' ) . '</a>' : '';
			$rows .= '<tr>'
				. '<td>' . esc_html( (string) $m['name'] ) . '</td>'
				. '<td>' . esc_html( (string) $m['serial'] ) . '</td>'
				. '<td>' . esc_html( (string) $m['location'] ) . '</td>'
				. '<td>' . $doc . '</td>'
				. '<td><button type="button" class="liw-myl__wbtn" data-liw-act="machines/' . $id . '" data-liw-method="DELETE" aria-label="' . esc_attr__( 'Entfernen', 'liebherr-interface-world' ) . '">✕</button></td>'
				. '</tr>';
		}
		return '<table class="liw-myl__wtx"><thead><tr>'
			. '<th>' . esc_html__( 'Name', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Seriennummer', 'liebherr-interface-world' ) . '</th>'
			. '<th>' . esc_html__( 'Standort', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Doku', 'liebherr-interface-world' ) . '</th><th></th>'
			. '</tr></thead><tbody>' . $rows . '</tbody></table>';
	}

	private static function add_form_html(): string {
		return '<form class="liw-myl__form" data-liw-post="machines">'
			. '<label class="liw-myl__field"><span>' . esc_html__( 'Name', 'liebherr-interface-world' ) . '</span><input type="text" name="name" maxlength="160"></label>'
			. '<label class="liw-myl__field"><span>' . esc_html__( 'Seriennummer', 'liebherr-interface-world' ) . '</span><input type="text" name="serial" maxlength="120"></label>'
			. '<label class="liw-myl__field"><span>' . esc_html__( 'Standort', 'liebherr-interface-world' ) . '</span><input type="text" name="location" maxlength="160"></label>'
			. '<label class="liw-myl__field"><span>' . esc_html__( 'Dokument-URL', 'liebherr-interface-world' ) . '</span><input type="url" name="doc_url"></label>'
			. '<label class="liw-myl__field liw-myl__field--wide"><span>' . esc_html__( 'Notiz', 'liebherr-interface-world' ) . '</span><input type="text" name="note" maxlength="255"></label>'
			. '<div class="liw-myl__formrow"><button type="submit" class="liw-myl__action">' . esc_html__( 'Maschine hinzufügen', 'liebherr-interface-world' ) . '</button></div>'
			. '</form>';
	}
}
