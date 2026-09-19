<?php
/**
 * Liebherr World – Pocket Information: Ansicht (Pflichtenheft My Liebherr §34, ADR-LIW-MYL-001 R5).
 *
 * Sechster Reiter: personenbezogener Feed kurzer, unmittelbar nutzbarer Infos; Alerts (high/critical) zuerst.
 * Pflicht-Items verlangen eine bewusste Quittierung (getrennt von der Anzeige protokolliert). Shortcode
 * `[liw_pocket]`; self-gating über {@see Flags::enabled()}; nur eigener Nutzer + {@see MylRoles::CAP_ACCESS}.
 *
 * @package Liebherr\InterfaceWorld\Pocket
 * @since   0.1.0-alpha.124
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Pocket;

use Liebherr\InterfaceWorld\MyLiebherr\OverviewView;
use Liebherr\InterfaceWorld\MyLiebherr\Roles as MylRoles;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class PocketView {

	public const SHORTCODE = 'liw_pocket';

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
		if ( ! Flags::enabled() || ! is_user_logged_in() || ! current_user_can( MylRoles::CAP_ACCESS ) ) {
			return '';
		}
		$uid  = get_current_user_id();
		$root = esc_url( rest_url( Rest::NAMESPACE . '/' ) );
		return '<div class="liw-myl"><section class="liw-myl__pocket" id="liw-pocket" data-liw-root="' . $root . '">'
			. '<h2 class="liw-myl__tile-title">' . esc_html__( 'Pocket Information', 'liebherr-interface-world' ) . '</h2>'
			. self::feed_html( PocketRepository::feed( $uid ) )
			. self::add_form_html()
			. '</section></div>';
	}

	/** @param array<int,array<string,mixed>> $items */
	private static function feed_html( array $items ): string {
		if ( [] === $items ) {
			return '<p class="liw-myl__wallet-note">' . esc_html__( 'Keine aktuellen Pocket-Infos.', 'liebherr-interface-world' ) . '</p>';
		}
		$out = '<ul class="liw-myl__pocket-list">';
		foreach ( $items as $it ) {
			$id   = (int) $it['id'];
			$prio = (string) $it['priority'];
			$ack  = ( (int) $it['requires_ack'] === 1 && null === $it['acknowledged_at'] );
			$out .= '<li class="liw-myl__pocket-item liw-myl__pocket-item--' . esc_attr( $prio ) . '">'
				. '<div class="liw-myl__pocket-head">'
				. '<strong>' . esc_html( (string) $it['title'] ) . '</strong>'
				. ' <span class="liw-myl__dream-wish liw-myl__pocket-prio--' . esc_attr( $prio ) . '">' . esc_html( self::prio_label( $prio ) ) . '</span>'
				. ( '' !== (string) $it['source'] ? ' <span class="liw-myl__dream-tags">' . esc_html( (string) $it['source'] ) . '</span>' : '' )
				. '</div>'
				. ( '' !== (string) $it['body'] ? '<p class="liw-myl__dream-note">' . esc_html( (string) $it['body'] ) . '</p>' : '' )
				. '<div class="liw-myl__pocket-actions">'
				. ( '' !== (string) $it['return_route'] ? '<a class="liw-myl__wbtn" href="' . esc_url( (string) $it['return_route'] ) . '">' . esc_html__( 'Zur Quelle', 'liebherr-interface-world' ) . '</a> ' : '' )
				. ( $ack ? '<button type="button" class="liw-myl__action" data-liw-act="items/' . $id . '/ack">' . esc_html__( 'Quittieren', 'liebherr-interface-world' ) . '</button> ' : ( (int) $it['requires_ack'] === 1 ? '<span class="liw-myl__status">' . esc_html__( 'quittiert', 'liebherr-interface-world' ) . '</span> ' : '' ) )
				. '<button type="button" class="liw-myl__wbtn" data-liw-act="items/' . $id . '" data-liw-method="DELETE" aria-label="' . esc_attr__( 'Entfernen', 'liebherr-interface-world' ) . '">✕</button>'
				. '</div></li>';
		}
		return $out . '</ul>';
	}

	private static function add_form_html(): string {
		$prio = '';
		foreach ( PocketRepository::PRIORITY as $p ) {
			$prio .= '<option value="' . esc_attr( $p ) . '"' . ( 'normal' === $p ? ' selected' : '' ) . '>' . esc_html( self::prio_label( $p ) ) . '</option>';
		}
		return '<form class="liw-myl__form" data-liw-post="items">'
			. '<label class="liw-myl__field"><span>' . esc_html__( 'Titel', 'liebherr-interface-world' ) . '</span><input type="text" name="title" maxlength="180"></label>'
			. '<label class="liw-myl__field"><span>' . esc_html__( 'Quelle', 'liebherr-interface-world' ) . '</span><input type="text" name="source" maxlength="80"></label>'
			. '<label class="liw-myl__field"><span>' . esc_html__( 'Priorität', 'liebherr-interface-world' ) . '</span><select name="priority">' . $prio . '</select></label>'
			. '<label class="liw-myl__field liw-myl__field--wide"><span>' . esc_html__( 'Text', 'liebherr-interface-world' ) . '</span><input type="text" name="body" maxlength="255"></label>'
			. '<label class="liw-myl__field"><span>' . esc_html__( 'Rücksprung-URL', 'liebherr-interface-world' ) . '</span><input type="url" name="return_route"></label>'
			. '<label class="liw-myl__cover"><input type="checkbox" name="requires_ack" value="1"> ' . esc_html__( 'Quittierung erforderlich', 'liebherr-interface-world' ) . '</label>'
			. '<div class="liw-myl__formrow"><button type="submit" class="liw-myl__action">' . esc_html__( 'Pocket-Info anlegen', 'liebherr-interface-world' ) . '</button></div>'
			. '</form>';
	}

	private static function prio_label( string $p ): string {
		$map = [
			'low'      => __( 'niedrig', 'liebherr-interface-world' ),
			'normal'   => __( 'normal', 'liebherr-interface-world' ),
			'high'     => __( 'hoch', 'liebherr-interface-world' ),
			'critical' => __( 'kritisch', 'liebherr-interface-world' ),
		];
		return $map[ $p ] ?? $p;
	}
}
