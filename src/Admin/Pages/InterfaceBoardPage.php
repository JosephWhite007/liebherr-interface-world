<?php
/**
 * Liebherr Interface Solutions – Interface Board (Admin-Seite)
 *
 * Erstes Admin-Board dieser Auslieferung (Grundgerüst): Liste + Formular für den
 * Schnittstellenkatalog (liw_interface, Liebherr-Pflichtenheft §18 Interface Board).
 * Simulation Board, Content Board, Media Board etc. folgen als Folgeauslieferung
 * (s. Abschlussbericht).
 *
 * @package Liebherr\InterfaceWorld\Admin\Pages
 * @since   0.1.0-alpha.1
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin\Pages;

use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;
use Liebherr\InterfaceWorld\Interfaces\InterfaceCatalogService;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class InterfaceBoardPage {

	private const NONCE_ACTION = 'liw_interface_board_create';
	private const NONCE_NAME   = 'liw_interface_board_nonce';

	/** Lifecycle-Status (Spiegel von InterfaceCatalogService::ALLOWED_STATUSES) → Label. */
	private const STATUS_LABELS = [
		'draft'         => 'Entwurf',
		'in_simulation' => 'In Simulation',
		'verified'      => 'Verifiziert',
		'approved'      => 'Freigegeben',
		'retired'       => 'Stillgelegt',
	];

	public static function render(): void {
		if ( ! current_user_can( RoleBridge::CAP_MANAGE_INTERFACES ) ) {
			wp_die( esc_html__( 'Keine Berechtigung für den Schnittstellenkatalog.', 'liebherr-interface-world' ) );
		}

		$notice = self::maybe_handle_submit();

		echo '<div class="wrap"><h1>' . esc_html__( 'Interface Board – Schnittstellenkatalog', 'liebherr-interface-world' ) . '</h1>';

		if ( null !== $notice ) {
			printf( '<div class="notice %s"><p>%s</p></div>', esc_attr( $notice['class'] ), esc_html( $notice['message'] ) );
		}

		self::render_form();
		self::render_table();
		echo '</div>';
	}

	/** @return array{class:string,message:string}|null */
	private static function maybe_handle_submit(): ?array {
		$action = isset( $_POST['liw_action'] ) ? sanitize_key( wp_unslash( $_POST['liw_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce unten geprüft.
		if ( '' === $action ) {
			return null;
		}
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		if ( 'set_lifecycle' === $action ) {
			$result = InterfaceCatalogService::set_lifecycle_status(
				(int) ( $_POST['interface_id'] ?? 0 ),
				sanitize_text_field( wp_unslash( $_POST['lifecycle_status'] ?? '' ) ),
				get_current_user_id()
			);
			if ( is_wp_error( $result ) ) {
				return [ 'class' => 'notice-error', 'message' => $result->get_error_message() ];
			}
			return [ 'class' => 'notice-success', 'message' => __( 'Status aktualisiert.', 'liebherr-interface-world' ) ];
		}

		if ( 'create_interface' !== $action ) {
			return null;
		}

		$result = InterfaceCatalogService::create(
			[
				'code'      => sanitize_text_field( wp_unslash( $_POST['code'] ?? '' ) ),
				'name'      => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
				'direction' => sanitize_text_field( wp_unslash( $_POST['direction'] ?? 'inbound' ) ),
				'protocol'  => sanitize_text_field( wp_unslash( $_POST['protocol'] ?? '' ) ),
			],
			get_current_user_id()
		);

		if ( is_wp_error( $result ) ) {
			return [ 'class' => 'notice-error', 'message' => $result->get_error_message() ];
		}

		return [ 'class' => 'notice-success', 'message' => __( 'Schnittstelle angelegt.', 'liebherr-interface-world' ) ];
	}

	private static function render_form(): void {
		echo '<h2>' . esc_html__( 'Neue Schnittstelle', 'liebherr-interface-world' ) . '</h2>';
		echo '<form method="post">';
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		echo '<input type="hidden" name="liw_action" value="create_interface" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="liw_code">' . esc_html__( 'Code', 'liebherr-interface-world' ) . '</label></th>';
		echo '<td><input type="text" id="liw_code" name="code" class="regular-text" required /></td></tr>';
		echo '<tr><th><label for="liw_name">' . esc_html__( 'Name', 'liebherr-interface-world' ) . '</label></th>';
		echo '<td><input type="text" id="liw_name" name="name" class="regular-text" required /></td></tr>';
		echo '<tr><th><label for="liw_direction">' . esc_html__( 'Richtung', 'liebherr-interface-world' ) . '</label></th>';
		echo '<td><select id="liw_direction" name="direction">';
		foreach ( [ 'inbound', 'outbound', 'bidirectional' ] as $direction ) {
			printf( '<option value="%1$s">%1$s</option>', esc_attr( $direction ) );
		}
		echo '</select></td></tr>';
		echo '<tr><th><label for="liw_protocol">' . esc_html__( 'Protokoll', 'liebherr-interface-world' ) . '</label></th>';
		echo '<td><input type="text" id="liw_protocol" name="protocol" class="regular-text" /></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Anlegen', 'liebherr-interface-world' ) );
		echo '</form>';
	}

	private static function render_table(): void {
		$rows = InterfaceCatalogService::get_all();

		echo '<h2>' . esc_html__( 'Schnittstellenkatalog', 'liebherr-interface-world' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( [ 'Code', 'Name', 'Richtung', 'Protokoll', 'Version', 'Status', 'Status ändern' ] as $column ) {
			echo '<th>' . esc_html( $column ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( [] === $rows ) {
			echo '<tr><td colspan="7">' . esc_html__( 'Noch keine Schnittstellen erfasst.', 'liebherr-interface-world' ) . '</td></tr>';
		}

		foreach ( $rows as $row ) {
			echo '<tr>';
			foreach ( [ 'code', 'name', 'direction', 'protocol', 'version' ] as $field ) {
				echo '<td>' . esc_html( (string) ( $row[ $field ] ?? '' ) ) . '</td>';
			}
			$current = (string) ( $row['lifecycle_status'] ?? '' );
			echo '<td>' . esc_html( self::STATUS_LABELS[ $current ] ?? $current ) . '</td>';
			echo '<td>';
			self::render_status_form( (int) ( $row['id'] ?? 0 ), $current );
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/** Statuswechsel-Formular je Zeile (Lifecycle §17, Interface-Board). */
	private static function render_status_form( int $id, string $current ): void {
		if ( $id <= 0 ) {
			return;
		}
		echo '<form method="post" style="display:flex;gap:6px;align-items:center">';
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		echo '<input type="hidden" name="liw_action" value="set_lifecycle" />';
		printf( '<input type="hidden" name="interface_id" value="%d" />', $id );
		echo '<select name="lifecycle_status" aria-label="' . esc_attr__( 'Lifecycle-Status', 'liebherr-interface-world' ) . '">';
		foreach ( self::STATUS_LABELS as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $current, $value, false ), esc_html( $label ) );
		}
		echo '</select>';
		submit_button( __( 'Setzen', 'liebherr-interface-world' ), 'small', 'submit', false );
		echo '</form>';
	}
}
