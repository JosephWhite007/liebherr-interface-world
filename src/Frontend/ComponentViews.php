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
			$cards .= '<li class="liw-pcard"><h3 class="liw-pcard__title">' . esc_html( $item['title'] ) . '</h3>'
				. ( '' !== $item['text'] ? '<p class="liw-pcard__text">' . esc_html( $item['text'] ) . '</p>' : '' )
				. '</li>';
		}
		return '<div class="liw-pworlds"><ul class="liw-pworlds__grid" role="list">' . $cards . '</ul></div>';
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
