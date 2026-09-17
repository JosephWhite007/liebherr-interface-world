<?php
/**
 * Liebherr Interface Solutions – Onboarding Board (Admin-Seite)
 *
 * Viertes Admin-Board: Sichtung und Freigabe der über `[liw_onboarding_form]` (§22)
 * eingegangenen Partner-Anfragen. Zeigt die Core-Partnerdaten (ary_partners, über
 * OnboardingService::get_all_requests() bereits gejoint) zusammen mit den Liebherr-
 * Zusatzfeldern (liw_partner_extra). Nutzt die bislang ungenutzte Capability
 * `liw_view_onboarding` (RoleBridge, seit alpha.1 vorbereitet für genau diesen Zweck).
 *
 * @package Liebherr\InterfaceWorld\Admin\Pages
 * @since   0.1.0-alpha.6
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin\Pages;

use Liebherr\InterfaceWorld\Admin\AdminPagination;
use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;
use Liebherr\InterfaceWorld\Onboarding\OnboardingService;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class OnboardingBoardPage {

	public const MENU_SLUG = 'liw-onboarding-board';

	private const NONCE_ACTION = 'liw_onboarding_board_set_status';
	private const NONCE_NAME   = 'liw_onboarding_board_nonce';

	public static function render(): void {
		if ( ! current_user_can( RoleBridge::CAP_VIEW_ONBOARDING ) ) {
			wp_die( esc_html__( 'Keine Berechtigung für Onboarding-Anfragen.', 'liebherr-interface-world' ) );
		}

		$notice = self::maybe_handle_submit();

		echo '<div class="wrap"><h1>' . esc_html__( 'Onboarding – Partneranfragen', 'liebherr-interface-world' ) . '</h1>';

		if ( null !== $notice ) {
			printf( '<div class="notice %s"><p>%s</p></div>', esc_attr( $notice['class'] ), esc_html( $notice['message'] ) );
		}

		self::render_table();
		echo '</div>';
	}

	/** @return array{class:string,message:string}|null */
	private static function maybe_handle_submit(): ?array {
		if ( ! isset( $_POST['liw_action'] ) || 'set_status' !== $_POST['liw_action'] ) {
			return null;
		}
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$partner_id = absint( wp_unslash( $_POST['partner_id'] ?? 0 ) );
		$status     = sanitize_key( wp_unslash( $_POST['onboarding_status'] ?? '' ) );

		$result = OnboardingService::set_status( $partner_id, $status, get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			return [ 'class' => 'notice-error', 'message' => $result->get_error_message() ];
		}
		return [ 'class' => 'notice-success', 'message' => __( 'Status aktualisiert.', 'liebherr-interface-world' ) ];
	}

	private static function render_table(): void {
		$page  = AdminPagination::current_page();
		$rows  = OnboardingService::get_all_requests( $page );
		$total = OnboardingService::count_all_requests();

		echo '<table class="widefat striped"><thead><tr>';
		foreach ( [ 'Name/Firma', 'Kontakt', 'Typ', 'Gewünschte Schnittstellen', 'Nachricht', 'Status', 'Aktion' ] as $column ) {
			echo '<th>' . esc_html( $column ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( [] === $rows ) {
			echo '<tr><td colspan="7">' . esc_html__( 'Noch keine Onboarding-Anfragen.', 'liebherr-interface-world' ) . '</td></tr>';
		}

		foreach ( $rows as $row ) {
			$partner_id = (int) $row['partner_id'];
			$status     = (string) $row['onboarding_status'];

			echo '<tr>';
			echo '<td>' . esc_html( (string) $row['name'] ) . '<br /><small>' . esc_html( (string) ( $row['city'] ?? '' ) ) . '</small></td>';
			echo '<td>' . esc_html( (string) $row['contact_email'] ) . '<br /><small>' . esc_html( (string) ( $row['contact_phone'] ?? '' ) ) . '</small></td>';
			echo '<td>' . esc_html( (string) $row['liw_partner_type'] ) . '</td>';
			echo '<td>' . esc_html( (string) ( $row['requested_interfaces'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( (string) ( $row['message'] ?? '' ) ) . '</td>';
			echo '<td>' . esc_html( $status ) . '</td>';

			echo '<td><form method="post" class="liw-row-form--inline">';
			wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
			echo '<input type="hidden" name="liw_action" value="set_status" />';
			echo '<input type="hidden" name="partner_id" value="' . esc_attr( (string) $partner_id ) . '" />';
			echo '<select name="onboarding_status">';
			foreach ( OnboardingService::STATUSES as $option ) {
				printf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $option ), selected( $status, $option, false ) );
			}
			echo '</select> ';
			submit_button( __( 'Übernehmen', 'liebherr-interface-world' ), 'small', '', false );
			echo '</form></td>';

			echo '</tr>';
		}
		echo '</tbody></table>';

		AdminPagination::render( $page, $total, OnboardingService::REQUESTS_PER_PAGE );
	}
}
