<?php
/**
 * Liebherr Interface Solutions – World Connections Board (Admin-Seite)
 *
 * Drittes Admin-Board dieser Auslieferung: Datenpflege für liw_connection (Liebherr-
 * Pflichtenheft §17/LP-06 „World Connections Map"). Frontend-Visualisierung ist bewusst
 * NICHT Teil dieser Auslieferung (Entscheidung JW 18.09.2026: erst Datenpflege, dann
 * Visualisierung) – dieses Board liefert Anlage, Sichtbarkeits- und Statuspflege sowie
 * Löschung, damit die Karte später mit echten, geprüften Daten befüllt werden kann.
 *
 * ANNAHME-LIW-3 (Annahmen-Protokoll, CLAUDE.md Abschnitt 8): Das Pflichtenheft benennt
 * keine explizite Rollenzuordnung für die World Connections Map. Da es sich um
 * öffentlichkeitswirksame, redaktionelle Freigabeentscheidungen handelt (welche Region
 * auf der Landingpage erscheint), wird dieselbe Capability wie beim Content Board
 * verwendet: `RoleBridge::CAP_MANAGE_CONTENT` (vergeben an araliya_admin/administrator
 * und araliya_marketing – „Redaktion" laut Pflichtenheft §5). Bei Bedarf jederzeit auf
 * eine eigene Capability umstellbar, ohne Datenmodelländerung.
 *
 * @package Liebherr\InterfaceWorld\Admin\Pages
 * @since   0.1.0-alpha.5
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin\Pages;

use Liebherr\InterfaceWorld\Connection\ConnectionService;
use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ConnectionBoardPage {

	public const MENU_SLUG = 'liw-connection-board';

	private const NONCE_ACTION_CREATE = 'liw_connection_board_create';
	private const NONCE_NAME_CREATE   = 'liw_connection_board_create_nonce';
	private const NONCE_ACTION_ROW    = 'liw_connection_board_row_action';
	private const NONCE_NAME_ROW      = 'liw_connection_board_row_nonce';

	private const DISPLAY_STATUSES = [ 'planned', 'active', 'inactive' ];
	private const PARTNER_TYPES    = [ 'dealer', 'supplier', 'customer' ];

	public static function render(): void {
		if ( ! current_user_can( RoleBridge::CAP_MANAGE_CONTENT ) ) {
			wp_die( esc_html__( 'Keine Berechtigung für die World Connections Map.', 'liebherr-interface-world' ) );
		}

		$notice = self::maybe_handle_submit();

		echo '<div class="wrap"><h1>' . esc_html__( 'World Connections Map – Datenpflege', 'liebherr-interface-world' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Frontend-Karte folgt als eigene Auslieferung. Hier werden Regionen/Partner-Einträge angelegt, freigegeben und gepflegt.', 'liebherr-interface-world' ) . '</p>';

		if ( null !== $notice ) {
			printf( '<div class="notice %s"><p>%s</p></div>', esc_attr( $notice['class'] ), esc_html( $notice['message'] ) );
		}

		self::render_create_form();
		self::render_table();
		echo '</div>';
	}

	/** @return array{class:string,message:string}|null */
	private static function maybe_handle_submit(): ?array {
		$action = isset( $_POST['liw_action'] ) ? sanitize_key( wp_unslash( $_POST['liw_action'] ) ) : '';
		if ( '' === $action ) {
			return null;
		}

		$actor_id = get_current_user_id();

		if ( 'create_connection' === $action ) {
			check_admin_referer( self::NONCE_ACTION_CREATE, self::NONCE_NAME_CREATE );

			$result = ConnectionService::create(
				[
					'region'       => sanitize_text_field( wp_unslash( $_POST['region'] ?? '' ) ),
					'partner_type' => sanitize_text_field( wp_unslash( $_POST['partner_type'] ?? 'dealer' ) ),
					'public_flag'  => ! empty( $_POST['public_flag'] ),
				],
				$actor_id
			);

			if ( is_wp_error( $result ) ) {
				return [ 'class' => 'notice-error', 'message' => $result->get_error_message() ];
			}
			return [ 'class' => 'notice-success', 'message' => __( 'Verbindung angelegt (Status: geplant, standardmäßig nicht öffentlich).', 'liebherr-interface-world' ) ];
		}

		if ( in_array( $action, [ 'set_status', 'toggle_public', 'delete_connection' ], true ) ) {
			check_admin_referer( self::NONCE_ACTION_ROW, self::NONCE_NAME_ROW );
			$id = absint( wp_unslash( $_POST['id'] ?? 0 ) );

			$result = match ( $action ) {
				'set_status'         => ConnectionService::set_display_status( $id, sanitize_text_field( wp_unslash( $_POST['display_status'] ?? '' ) ), $actor_id ),
				'toggle_public'      => ConnectionService::set_public_flag( $id, ! empty( $_POST['public_flag'] ), $actor_id ),
				'delete_connection'  => ConnectionService::delete( $id, $actor_id ),
				default              => new \WP_Error( 'liw_invalid_action', __( 'Unbekannte Aktion.', 'liebherr-interface-world' ) ),
			};

			if ( is_wp_error( $result ) ) {
				return [ 'class' => 'notice-error', 'message' => $result->get_error_message() ];
			}
			return [ 'class' => 'notice-success', 'message' => __( 'Eintrag aktualisiert.', 'liebherr-interface-world' ) ];
		}

		return null;
	}

	private static function render_create_form(): void {
		echo '<h2>' . esc_html__( 'Neue Verbindung', 'liebherr-interface-world' ) . '</h2>';
		echo '<form method="post">';
		wp_nonce_field( self::NONCE_ACTION_CREATE, self::NONCE_NAME_CREATE );
		echo '<input type="hidden" name="liw_action" value="create_connection" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="liw_conn_region">' . esc_html__( 'Region', 'liebherr-interface-world' ) . '</label></th>';
		echo '<td><input type="text" id="liw_conn_region" name="region" class="regular-text" required placeholder="' . esc_attr__( 'z. B. Süddeutschland', 'liebherr-interface-world' ) . '" /></td></tr>';
		echo '<tr><th><label for="liw_conn_type">' . esc_html__( 'Partnertyp', 'liebherr-interface-world' ) . '</label></th>';
		echo '<td><select id="liw_conn_type" name="partner_type">';
		foreach ( self::PARTNER_TYPES as $type ) {
			printf( '<option value="%1$s">%1$s</option>', esc_attr( $type ) );
		}
		echo '</select></td></tr>';
		echo '<tr><th>' . esc_html__( 'Sofort öffentlich?', 'liebherr-interface-world' ) . '</th>';
		echo '<td><label><input type="checkbox" name="public_flag" value="1" /> ' . esc_html__( 'Ja, auf der World Connections Map anzeigen (LP-06)', 'liebherr-interface-world' ) . '</label></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Verbindung anlegen', 'liebherr-interface-world' ) );
		echo '</form>';
	}

	private static function render_table(): void {
		$rows = ConnectionService::get_all();

		echo '<h2>' . esc_html__( 'Verbindungen', 'liebherr-interface-world' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( [ 'Region', 'Partnertyp', 'Status', 'Öffentlich', 'Aktionen' ] as $column ) {
			echo '<th>' . esc_html( $column ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( [] === $rows ) {
			echo '<tr><td colspan="5">' . esc_html__( 'Noch keine Verbindungen angelegt.', 'liebherr-interface-world' ) . '</td></tr>';
		}

		foreach ( $rows as $row ) {
			$id          = (int) $row['id'];
			$is_public   = ! empty( $row['public_flag'] );
			$status      = (string) $row['display_status'];

			echo '<tr>';
			echo '<td>' . esc_html( (string) $row['region'] ) . '</td>';
			echo '<td>' . esc_html( (string) $row['partner_type'] ) . '</td>';

			echo '<td><form method="post" style="display:flex;gap:6px;align-items:center;">';
			wp_nonce_field( self::NONCE_ACTION_ROW, self::NONCE_NAME_ROW );
			echo '<input type="hidden" name="liw_action" value="set_status" />';
			echo '<input type="hidden" name="id" value="' . esc_attr( (string) $id ) . '" />';
			echo '<select name="display_status">';
			foreach ( self::DISPLAY_STATUSES as $option ) {
				printf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $option ), selected( $status, $option, false ) );
			}
			echo '</select> ';
			submit_button( __( 'Übernehmen', 'liebherr-interface-world' ), 'small', '', false );
			echo '</form></td>';

			echo '<td><form method="post">';
			wp_nonce_field( self::NONCE_ACTION_ROW, self::NONCE_NAME_ROW );
			echo '<input type="hidden" name="liw_action" value="toggle_public" />';
			echo '<input type="hidden" name="id" value="' . esc_attr( (string) $id ) . '" />';
			echo '<input type="hidden" name="public_flag" value="' . ( $is_public ? '0' : '1' ) . '" />';
			submit_button(
				$is_public ? __( 'Öffentlich – zurückziehen', 'liebherr-interface-world' ) : __( 'Freigeben', 'liebherr-interface-world' ),
				$is_public ? 'secondary small' : 'primary small',
				'',
				false
			);
			echo '</form></td>';

			echo '<td><form method="post" onsubmit="return confirm(\'' . esc_js( __( 'Diese Verbindung wirklich löschen?', 'liebherr-interface-world' ) ) . '\');">';
			wp_nonce_field( self::NONCE_ACTION_ROW, self::NONCE_NAME_ROW );
			echo '<input type="hidden" name="liw_action" value="delete_connection" />';
			echo '<input type="hidden" name="id" value="' . esc_attr( (string) $id ) . '" />';
			submit_button( __( 'Löschen', 'liebherr-interface-world' ), 'delete small', '', false );
			echo '</form></td>';

			echo '</tr>';
		}
		echo '</tbody></table>';
	}
}
