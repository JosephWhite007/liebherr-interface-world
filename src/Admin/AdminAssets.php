<?php
/**
 * Liebherr Interface Solutions – Admin Assets
 *
 * Feinschliff (alpha.10): Drei Admin-Boards enthielten Inline-Styles (`style="..."` direkt
 * im HTML) – Verstoß gegen CLAUDE.md Abschnitt 5 „Frontend: keine Inline-Styles". Diese
 * Klasse lädt `assets/css/liebherr-admin.css` ausschließlich auf den eigenen Board-Seiten
 * (Hook-Suffix enthält `liw-`, analog zur Shortcode-Prüfung in `Frontend\FrontendAssets`),
 * nicht global im WP-Backend (Performance).
 *
 * @package Liebherr\InterfaceWorld\Admin
 * @since   0.1.0-alpha.10
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AdminAssets {

	private const HANDLE = 'liw-admin';

	public static function register(): void {
		add_action( 'admin_enqueue_scripts', [ self::class, 'maybe_enqueue' ] );
	}

	public static function maybe_enqueue( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, 'liw-' ) ) {
			return;
		}

		wp_enqueue_style(
			self::HANDLE,
			LIW_URL . 'assets/css/liebherr-admin.css',
			[],
			LIW_VERSION
		);
	}
}
