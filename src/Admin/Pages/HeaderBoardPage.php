<?php
/**
 * Liebherr Interface Solutions – Header Board (Navigation & CTAs, §7/§16).
 *
 * Administrierbare Konfiguration des `[liw_header]`/`[liw_hero]`-Chromes: Menüpunkte (Label | Ziel,
 * je Zeile), Primär-/Sekundär-CTA, optionaler Portal-Login und Hero-Bild (Auswahl aus freigegebenen
 * Media-Board-Bildern). Capability `liw_manage_content`, Nonce, Audit (SEC-005).
 *
 * @package Liebherr\InterfaceWorld\Admin\Pages
 * @since   0.1.0-alpha.28
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin\Pages;

use Liebherr\InterfaceWorld\CoreBridge\AuditBridge;
use Liebherr\InterfaceWorld\CoreBridge\MediaBridge;
use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;
use Liebherr\InterfaceWorld\Settings\HeaderSettings;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class HeaderBoardPage {

	public const MENU_SLUG = 'liw-header-board';

	private const NONCE_ACTION = 'liw_header_board_save';
	private const NONCE_NAME   = 'liw_header_board_nonce';

	public static function render(): void {
		if ( ! current_user_can( RoleBridge::CAP_MANAGE_CONTENT ) ) {
			wp_die( esc_html__( 'Keine Berechtigung für das Header Board.', 'liebherr-interface-world' ) );
		}

		$notice = self::maybe_handle_submit();
		$cfg    = HeaderSettings::get();

		echo '<div class="wrap"><h1>' . esc_html__( 'Header Board – Navigation &amp; CTAs', 'liebherr-interface-world' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Hauptnavigation, Primär-/Sekundär-CTA, optionaler Portal-Login und Hero-Bild der Landingpage (Shortcodes [liw_header] und [liw_hero]). Ziele: In-Page-Anker (#lp-04), relativer Pfad (/interface-world/…) oder http(s)-URL.', 'liebherr-interface-world' ) . '</p>';

		if ( null !== $notice ) {
			printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr( $notice['class'] ), esc_html( $notice['message'] ) );
		}

		$nav_lines = '';
		foreach ( $cfg['nav'] as $item ) {
			$nav_lines .= $item['label'] . ' | ' . $item['target'] . "\n";
		}

		echo '<form method="post" action="">';
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		echo '<input type="hidden" name="liw_action" value="save_header" />';
		echo '<table class="form-table" role="presentation"><tbody>';

		echo '<tr><th scope="row"><label for="liw-header-nav">' . esc_html__( 'Menüpunkte (je Zeile: Label | Ziel)', 'liebherr-interface-world' ) . '</label></th><td>';
		printf( '<textarea id="liw-header-nav" name="liw_header_nav" rows="7" class="large-text code">%s</textarea>', esc_textarea( trim( $nav_lines ) ) );
		echo '<p class="description">' . esc_html__( 'Leere Liste = keine Sprungnavigation im Header.', 'liebherr-interface-world' ) . '</p></td></tr>';

		self::cta_rows( 'cta_primary', __( 'Primärer CTA', 'liebherr-interface-world' ), $cfg['cta_primary'] );
		self::cta_rows( 'cta_secondary', __( 'Sekundärer CTA', 'liebherr-interface-world' ), $cfg['cta_secondary'] );

		echo '<tr><th scope="row">' . esc_html__( 'Portal-Login', 'liebherr-interface-world' ) . '</th><td>';
		printf( '<label><input type="checkbox" name="liw_header_portal_enabled" value="1" %s /> %s</label><br />', checked( ! empty( $cfg['portal_enabled'] ), true, false ), esc_html__( 'Portal-Login rechts außen anzeigen', 'liebherr-interface-world' ) );
		printf( '<input type="url" name="liw_header_portal_url" value="%s" class="regular-text" placeholder="%s" />', esc_attr( (string) $cfg['portal_url'] ), esc_attr__( 'Standard: WordPress-Login', 'liebherr-interface-world' ) );
		echo '</td></tr>';

		self::hero_row( (int) $cfg['hero_image_id'] );

		echo '</tbody></table>';
		submit_button( __( 'Header speichern', 'liebherr-interface-world' ) );
		echo '</form></div>';
	}

	/** @param array{label:string,target:string} $cta */
	private static function cta_rows( string $key, string $label, array $cta ): void {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		printf( '<input type="text" name="liw_header_%1$s_label" value="%2$s" class="regular-text" aria-label="%3$s" /> ', esc_attr( $key ), esc_attr( $cta['label'] ), esc_attr__( 'Label', 'liebherr-interface-world' ) );
		printf( '<input type="text" name="liw_header_%1$s_target" value="%2$s" class="regular-text" aria-label="%3$s" />', esc_attr( $key ), esc_attr( $cta['target'] ), esc_attr__( 'Ziel', 'liebherr-interface-world' ) );
		echo '</td></tr>';
	}

	private static function hero_row( int $current ): void {
		$images = get_posts( [
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'post_status'    => 'inherit',
			'posts_per_page' => 60,
			'no_found_rows'  => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => [ [ 'key' => '_liw_media_role', 'value' => [ 'hero', 'machine', 'service' ], 'compare' => 'IN' ] ],
		] );

		echo '<tr><th scope="row"><label for="liw-header-hero">' . esc_html__( 'Hero-Bild (LP-01)', 'liebherr-interface-world' ) . '</label></th><td>';
		echo '<select id="liw-header-hero" name="liw_header_hero_image_id">';
		echo '<option value="0">' . esc_html__( '— kein Bild (neutraler Verlauf) —', 'liebherr-interface-world' ) . '</option>';
		foreach ( $images as $img ) {
			$suffix = MediaBridge::is_approved( $img->ID ) ? '' : ' ' . __( '(nicht freigegeben)', 'liebherr-interface-world' );
			printf( '<option value="%1$d" %2$s>%3$s%4$s</option>', (int) $img->ID, selected( $current, (int) $img->ID, false ), esc_html( get_the_title( $img ) ), esc_html( $suffix ) );
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Nur freigegebene Bilder werden im Frontend angezeigt (CI-005); ein nicht freigegebenes Bild führt zum neutralen Verlauf.', 'liebherr-interface-world' ) . '</p>';
		echo '</td></tr>';
	}

	/** @return array{class:string,message:string}|null */
	private static function maybe_handle_submit(): ?array {
		if ( ! isset( $_POST['liw_action'] ) || 'save_header' !== $_POST['liw_action'] ) {
			return null;
		}
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		if ( ! current_user_can( RoleBridge::CAP_MANAGE_CONTENT ) ) {
			return [ 'class' => 'notice-error', 'message' => __( 'Keine Berechtigung.', 'liebherr-interface-world' ) ];
		}

		$nav_raw = (string) wp_unslash( $_POST['liw_header_nav'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- zeilenweise unten geparst/bereinigt.
		$nav     = [];
		foreach ( preg_split( '/\r\n|\r|\n/', $nav_raw ) as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			$parts = explode( '|', $line, 2 );
			if ( count( $parts ) === 2 ) {
				$nav[] = [ 'label' => trim( $parts[0] ), 'target' => trim( $parts[1] ) ];
			}
		}

		$raw = [
			'nav'            => $nav,
			'cta_primary'    => [ 'label' => (string) wp_unslash( $_POST['liw_header_cta_primary_label'] ?? '' ), 'target' => (string) wp_unslash( $_POST['liw_header_cta_primary_target'] ?? '' ) ], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- HeaderSettings::sanitize().
			'cta_secondary'  => [ 'label' => (string) wp_unslash( $_POST['liw_header_cta_secondary_label'] ?? '' ), 'target' => (string) wp_unslash( $_POST['liw_header_cta_secondary_target'] ?? '' ) ], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- HeaderSettings::sanitize().
			'portal_enabled' => ! empty( $_POST['liw_header_portal_enabled'] ),
			'portal_url'     => (string) wp_unslash( $_POST['liw_header_portal_url'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- esc_url_raw in sanitize().
			'hero_image_id'  => (int) ( $_POST['liw_header_hero_image_id'] ?? 0 ),
		];

		$before = HeaderSettings::get();
		$after  = HeaderSettings::save( $raw );

		if ( AuditBridge::is_available() ) {
			AuditBridge::log( 'update', 'header_settings', 0, [ 'nav' => count( $before['nav'] ) ], [ 'nav' => count( $after['nav'] ) ], get_current_user_id() );
		}

		return [ 'class' => 'notice-success', 'message' => __( 'Header-Einstellungen gespeichert.', 'liebherr-interface-world' ) ];
	}
}
