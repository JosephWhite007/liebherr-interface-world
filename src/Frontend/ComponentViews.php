<?php
/**
 * Liebherr Interface Solutions – Datengetriebene Kern-Komponenten (§8/§16).
 *
 * Drei öffentliche Shortcodes, gespeist aus Settings\ComponentContent (administrierbar):
 *   [liw_process_worlds]  – Karten Sales…Warranty (LP-08)
 *   [liw_roadmap]         – Phasen-Zeitleiste (LP-12)
 *   [liw_onboarding_steps]– neunstufige Schrittliste (LP-11)
 *
 * Reiner Text/HTML (keine Bild-/Bewegungsabhängigkeit → von sich aus barrierearm, §26 Textalternative).
 * Gestaltung ausschließlich über `--brand-*` (§11), kein Inline-CSS/-JS.
 *
 * @package Liebherr\InterfaceWorld\Frontend
 * @since   0.1.0-alpha.29
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Frontend;

use Liebherr\InterfaceWorld\CoreBridge\GateBridge;
use Liebherr\InterfaceWorld\Settings\ComponentContent;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ComponentViews {

	public const SC_PROCESS    = 'liw_process_worlds';
	public const SC_ROADMAP    = 'liw_roadmap';
	public const SC_ONBOARDING = 'liw_onboarding_steps';

	public static function register(): void {
		add_shortcode( self::SC_PROCESS, [ self::class, 'render_process' ] );
		add_shortcode( self::SC_ROADMAP, [ self::class, 'render_roadmap' ] );
		add_shortcode( self::SC_ONBOARDING, [ self::class, 'render_onboarding' ] );
	}

	public static function render_process(): string {
		$items = ComponentContent::get()['process'];
		if ( [] === $items ) {
			return '';
		}
		$cards = '';
		foreach ( $items as $item ) {
			$body = '<h3 class="liw-pcard__title">' . esc_html( $item['title'] ) . '</h3>'
				. ( '' !== $item['text'] ? '<p class="liw-pcard__text">' . esc_html( $item['text'] ) . '</p>' : '' );
			$page = self::target_page( (string) ( $item['target'] ?? '' ) );
			if ( null === $page ) {
				$cards .= '<li class="liw-pcard">' . $body . '</li>';
				continue;
			}
			// Karte mit Zielseite: ganze Fläche ist der Link (Hover/Fokus-Effekt per CSS); 🔒 nur bei Core-Regel »mit Code«.
			$lock   = GateBridge::is_protected( $page->ID )
				? '<span class="liw-pcard__lock">🔒 ' . esc_html__( 'Zugangscode erforderlich', 'liebherr-interface-world' ) . '</span>'
				: '';
			$cards .= '<li class="liw-pcard liw-pcard--link"><a class="liw-pcard__link" href="' . esc_url( (string) get_permalink( $page ) ) . '">'
				. $body . $lock . '<span class="liw-pcard__arrow" aria-hidden="true">→</span></a></li>';
		}
		return '<div class="liw-pworlds"><ul class="liw-pworlds__grid" role="list">' . $cards . '</ul></div>';
	}

	/** Veröffentlichte Zielseite einer Process-Karte (Seitenpfad); null = kein Link. */
	private static function target_page( string $path ): ?\WP_Post {
		if ( '' === $path ) {
			return null;
		}
		$page = get_page_by_path( $path );
		return ( $page instanceof \WP_Post && 'publish' === $page->post_status ) ? $page : null;
	}

	public static function render_roadmap(): string {
		$items = ComponentContent::get()['roadmap'];
		if ( [] === $items ) {
			return '';
		}
		$steps = '';
		foreach ( $items as $i => $item ) {
			$steps .= '<li class="liw-roadmap__phase"><span class="liw-roadmap__marker" aria-hidden="true">' . ( (int) $i + 1 ) . '</span>'
				. '<span class="liw-roadmap__body"><span class="liw-roadmap__title">' . esc_html( $item['title'] ) . '</span>'
				. ( '' !== $item['text'] ? '<span class="liw-roadmap__text">' . esc_html( $item['text'] ) . '</span>' : '' )
				. '</span></li>';
		}
		return '<ol class="liw-roadmap">' . $steps . '</ol>';
	}

	public static function render_onboarding(): string {
		$items = ComponentContent::get()['onboarding'];
		if ( [] === $items ) {
			return '';
		}
		$steps = '';
		foreach ( $items as $item ) {
			$steps .= '<li class="liw-steps__item"><span class="liw-steps__title">' . esc_html( $item['title'] ) . '</span>'
				. ( '' !== $item['text'] ? '<span class="liw-steps__text">' . esc_html( $item['text'] ) . '</span>' : '' )
				. '</li>';
		}
		return '<ol class="liw-steps">' . $steps . '</ol>';
	}
}
