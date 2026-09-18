<?php
/**
 * Liebherr Interface Solutions – Components Board (Kern-Komponenten §8/§16).
 *
 * Pflegt die drei datengetriebenen Listen (Process Worlds LP-08, Roadmap LP-12, Onboarding-Schritte
 * LP-11) für die Shortcodes [liw_process_worlds]/[liw_roadmap]/[liw_onboarding_steps]. Eingabe je
 * Zeile „Titel | Text". Capability `liw_manage_content`, Nonce, Audit (SEC-005).
 *
 * @package Liebherr\InterfaceWorld\Admin\Pages
 * @since   0.1.0-alpha.29
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin\Pages;

use Liebherr\InterfaceWorld\CoreBridge\AuditBridge;
use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;
use Liebherr\InterfaceWorld\Settings\ComponentContent;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ComponentsBoardPage {

	public const MENU_SLUG = 'liw-components-board';

	private const NONCE_ACTION = 'liw_components_board_save';
	private const NONCE_NAME   = 'liw_components_board_nonce';

	/** @var array<string,string> Listen-Schlüssel → Label. */
	private const LISTS = [
		'process'    => 'Process World Cards (LP-08) – [liw_process_worlds]',
		'roadmap'    => 'Roadmap-Phasen (LP-12) – [liw_roadmap]',
		'onboarding' => 'Onboarding-Schritte (LP-11) – [liw_onboarding_steps]',
	];

	public static function render(): void {
		if ( ! current_user_can( RoleBridge::CAP_MANAGE_CONTENT ) ) {
			wp_die( esc_html__( 'Keine Berechtigung für das Components Board.', 'liebherr-interface-world' ) );
		}

		$notice = self::maybe_handle_submit();
		$data   = ComponentContent::get();

		echo '<div class="wrap"><h1>' . esc_html__( 'Components Board – Kern-Komponenten', 'liebherr-interface-world' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Inhalte der datengetriebenen Abschnitte. Je Zeile: „Titel | Text". Leere Liste setzt die Standardwerte (Pflichtenheft §8) zurück. Ausgabe über die Shortcodes im jeweiligen Abschnitt.', 'liebherr-interface-world' ) . '</p>';

		if ( null !== $notice ) {
			printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr( $notice['class'] ), esc_html( $notice['message'] ) );
		}

		echo '<form method="post" action="">';
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		echo '<input type="hidden" name="liw_action" value="save_components" />';
		echo '<table class="form-table" role="presentation"><tbody>';

		foreach ( self::LISTS as $key => $label ) {
			$lines = '';
			foreach ( $data[ $key ] as $item ) {
				$lines .= $item['title'] . ( '' !== $item['text'] ? ' | ' . $item['text'] : '' ) . "\n";
			}
			echo '<tr><th scope="row"><label for="liw-comp-' . esc_attr( $key ) . '">' . esc_html__( $label, 'liebherr-interface-world' ) . '</label></th><td>';
			printf( '<textarea id="liw-comp-%1$s" name="liw_components[%1$s]" rows="9" class="large-text code">%2$s</textarea>', esc_attr( $key ), esc_textarea( trim( $lines ) ) );
			echo '</td></tr>';
		}

		echo '</tbody></table>';
		submit_button( __( 'Komponenten speichern', 'liebherr-interface-world' ) );
		echo '</form></div>';
	}

	/** @return array{class:string,message:string}|null */
	private static function maybe_handle_submit(): ?array {
		if ( ! isset( $_POST['liw_action'] ) || 'save_components' !== $_POST['liw_action'] ) {
			return null;
		}
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		if ( ! current_user_can( RoleBridge::CAP_MANAGE_CONTENT ) ) {
			return [ 'class' => 'notice-error', 'message' => __( 'Keine Berechtigung.', 'liebherr-interface-world' ) ];
		}

		$raw_lists = wp_unslash( $_POST['liw_components'] ?? [] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- zeilenweise geparst + ComponentContent::sanitize().
		if ( ! is_array( $raw_lists ) ) {
			return [ 'class' => 'notice-error', 'message' => __( 'Ungültige Daten.', 'liebherr-interface-world' ) ];
		}

		$parsed = [];
		foreach ( array_keys( self::LISTS ) as $key ) {
			$parsed[ $key ] = self::parse_lines( is_string( $raw_lists[ $key ] ?? null ) ? (string) $raw_lists[ $key ] : '' );
		}

		$after = ComponentContent::save( $parsed );

		if ( AuditBridge::is_available() ) {
			AuditBridge::log( 'update', 'component_content', 0, [], [ 'process' => count( $after['process'] ), 'roadmap' => count( $after['roadmap'] ), 'onboarding' => count( $after['onboarding'] ) ], get_current_user_id() );
		}

		return [ 'class' => 'notice-success', 'message' => __( 'Komponenten gespeichert.', 'liebherr-interface-world' ) ];
	}

	/**
	 * „Titel | Text" je Zeile → Items.
	 *
	 * @return array<int,array{title:string,text:string}>
	 */
	private static function parse_lines( string $raw ): array {
		$items = [];
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			$parts = explode( '|', $line, 2 );
			$items[] = [ 'title' => trim( $parts[0] ), 'text' => isset( $parts[1] ) ? trim( $parts[1] ) : '' ];
		}
		return $items;
	}
}
