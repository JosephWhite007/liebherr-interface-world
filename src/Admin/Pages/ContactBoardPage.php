<?php
/**
 * Liebherr Interface Solutions – Contact Board (Admin-Seite, Kontakt-/Projektanfragen)
 *
 * Achtes Admin-Board: Sichtung, Statuspflege und Löschung (§24 Löschprozess) der über
 * `[liw_contact_form]` (LP-13, §22) eingegangenen Kontaktanfragen. Nutzt dieselbe Capability
 * wie das Onboarding Board (`liw_view_onboarding` – gleiche Zielgruppe „Anfragen sichten",
 * keine neue Capability nötig, KISS) und dieselben Bausteine (AdminPagination,
 * `liw-row-form--inline`). Kein Export in dieser Auslieferung (s. docs/LIW_TODO.md, §24).
 *
 * @package Liebherr\InterfaceWorld\Admin\Pages
 * @since   0.1.0-alpha.19
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin\Pages;

use Liebherr\InterfaceWorld\Admin\AdminPagination;
use Liebherr\InterfaceWorld\Contact\ContactService;
use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ContactBoardPage {

	public const MENU_SLUG = 'liw-contact-board';

	private const NONCE_ACTION = 'liw_contact_board_action';
	private const NONCE_NAME   = 'liw_contact_board_nonce';

	private const STATUS_LABELS = [
		'new'         => 'Neu',
		'in_progress' => 'In Bearbeitung',
		'closed'      => 'Abgeschlossen',
	];

	public static function render(): void {
		if ( ! current_user_can( RoleBridge::CAP_VIEW_ONBOARDING ) ) {
			wp_die( esc_html__( 'Keine Berechtigung für Kontaktanfragen.', 'liebherr-interface-world' ) );
		}

		$notice = self::maybe_handle_submit();

		echo '<div class="wrap"><h1>' . esc_html__( 'Kontaktanfragen (LP-13)', 'liebherr-interface-world' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Projektanfragen über das Kontaktformular – getrennt vom Partner-Onboarding. Einwilligungen liegen im Einwilligungsprotokoll; Löschen entfernt Anfrage und Einwilligungen zusammen (§24).', 'liebherr-interface-world' ) . '</p>';

		if ( null !== $notice ) {
			printf( '<div class="notice %s"><p>%s</p></div>', esc_attr( $notice['class'] ), esc_html( $notice['message'] ) );
		}

		self::render_export();
		self::render_retention();
		self::render_table();
		echo '</div>';
	}

	/** @return array{class:string,message:string}|null */
	private static function maybe_handle_submit(): ?array {
		$action = isset( $_POST['liw_action'] ) ? sanitize_key( wp_unslash( $_POST['liw_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce unten geprüft.
		if ( ! in_array( $action, [ 'set_status', 'delete', 'set_retention' ], true ) ) {
			return null;
		}
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		if ( 'set_retention' === $action ) {
			$days = absint( wp_unslash( $_POST['retention_days'] ?? 0 ) );
			\Liebherr\InterfaceWorld\Contact\ContactRetention::set_days( $days );
			return [
				'class'   => 'notice-success',
				'message' => 0 === $days
					? __( 'Automatische Löschung deaktiviert.', 'liebherr-interface-world' )
					/* translators: %d: Aufbewahrungsdauer in Tagen. */
					: sprintf( __( 'Aufbewahrungsfrist gespeichert: %d Tage.', 'liebherr-interface-world' ), $days ),
			];
		}

		$id = absint( wp_unslash( $_POST['request_id'] ?? 0 ) );

		if ( 'delete' === $action ) {
			$result = ContactService::delete( $id, get_current_user_id() );
			return is_wp_error( $result )
				? [ 'class' => 'notice-error', 'message' => $result->get_error_message() ]
				: [ 'class' => 'notice-success', 'message' => __( 'Anfrage und zugehörige Einwilligungen gelöscht.', 'liebherr-interface-world' ) ];
		}

		$status = sanitize_key( wp_unslash( $_POST['request_status'] ?? '' ) );
		$result = ContactService::set_status( $id, $status, get_current_user_id() );
		return is_wp_error( $result )
			? [ 'class' => 'notice-error', 'message' => $result->get_error_message() ]
			: [ 'class' => 'notice-success', 'message' => __( 'Status aktualisiert.', 'liebherr-interface-world' ) ];
	}

	/** §24 Export: CSV-Download aller Anfragen (Auskunfts-/Exportprozess), mit Datenschutzhinweis. */
	private static function render_export(): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:12px 0">';
		echo '<input type="hidden" name="action" value="' . esc_attr( \Liebherr\InterfaceWorld\Contact\ContactExporter::ACTION ) . '" />';
		wp_nonce_field( \Liebherr\InterfaceWorld\Contact\ContactExporter::ACTION, \Liebherr\InterfaceWorld\Contact\ContactExporter::NONCE_NAME );
		submit_button( __( 'Als CSV exportieren (§24)', 'liebherr-interface-world' ), 'secondary', 'submit', false );
		echo ' <span class="description">' . esc_html__( 'Enthält personenbezogene Daten inkl. Einwilligungen (Version/Zeit). Nur zweckgebunden verarbeiten, sicher ablegen und nach Gebrauch löschen (DSGVO).', 'liebherr-interface-world' ) . '</span>';
		echo '</form>';
	}

	/** SEC-007/§24: konfigurierbare Aufbewahrungsfrist (automatische Löschung per täglichem Cron). */
	private static function render_retention(): void {
		$days = \Liebherr\InterfaceWorld\Contact\ContactRetention::days();
		echo '<form method="post" style="margin:12px 0">';
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		echo '<input type="hidden" name="liw_action" value="set_retention" />';
		echo '<label for="liw-retention">' . esc_html__( 'Aufbewahrungsfrist (Tage, 0 = keine automatische Löschung):', 'liebherr-interface-world' ) . '</label> ';
		printf( '<input type="number" id="liw-retention" name="retention_days" min="0" max="3650" value="%d" style="width:90px" /> ', $days );
		submit_button( __( 'Frist speichern', 'liebherr-interface-world' ), 'secondary', 'submit', false );
		echo ' <span class="description">' . esc_html__( 'Datenminimierung (SEC-007): Anfragen älter als die Frist werden täglich automatisch mit ihren Einwilligungen gelöscht.', 'liebherr-interface-world' ) . '</span>';
		echo '</form>';
	}

	private static function render_table(): void {
		$page  = AdminPagination::current_page();
		$rows  = ContactService::get_all( $page );
		$total = ContactService::count_all();

		echo '<table class="widefat striped"><thead><tr>';
		foreach ( [ 'Eingang', 'Organisation / Kontakt', 'Rolle · Region', 'Projektinteresse', 'Nachricht', 'Status', 'Aktion' ] as $column ) {
			echo '<th>' . esc_html( $column ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( [] === $rows ) {
			echo '<tr><td colspan="7">' . esc_html__( 'Noch keine Kontaktanfragen.', 'liebherr-interface-world' ) . '</td></tr>';
		}

		$regions   = ContactService::regions();
		$interests = ContactService::interests();

		foreach ( $rows as $row ) {
			$id     = (int) $row['id'];
			$status = (string) $row['request_status'];
			$labels = array_map( static fn( string $k ): string => $interests[ $k ] ?? $k, array_filter( explode( ',', (string) $row['interests'] ) ) );

			echo '<tr>';
			echo '<td>' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $row['created_at'] ) ) . '</td>';
			echo '<td>' . esc_html( (string) $row['organisation'] ) . '<br /><small>' . esc_html( (string) $row['contact_name'] ) . ' · ' . esc_html( (string) $row['contact_email'] );
			if ( ! empty( $row['contact_phone'] ) ) {
				echo ' · ' . esc_html( (string) $row['contact_phone'] );
			}
			if ( ! empty( $row['local_system'] ) ) {
				echo '<br />' . esc_html__( 'ERP/CRM:', 'liebherr-interface-world' ) . ' ' . esc_html( (string) $row['local_system'] );
			}
			echo '</small></td>';
			echo '<td>' . esc_html( ContactService::ROLES[ $row['role'] ] ?? (string) $row['role'] ) . '<br /><small>' . esc_html( $regions[ $row['region'] ] ?? (string) $row['region'] ) . '</small></td>';
			echo '<td>' . esc_html( implode( ', ', $labels ) ) . '</td>';
			echo '<td>' . esc_html( (string) $row['message'] ) . '</td>';
			echo '<td>' . esc_html( self::STATUS_LABELS[ $status ] ?? $status ) . '</td>';

			echo '<td class="liw-row-form--inline">';
			echo '<form method="post" class="liw-row-form--inline">';
			wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
			echo '<input type="hidden" name="liw_action" value="set_status" />';
			echo '<input type="hidden" name="request_id" value="' . esc_attr( (string) $id ) . '" />';
			echo '<select name="request_status">';
			foreach ( ContactService::STATUSES as $option ) {
				printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $option ), selected( $status, $option, false ), esc_html( self::STATUS_LABELS[ $option ] ) );
			}
			echo '</select> ';
			submit_button( __( 'Übernehmen', 'liebherr-interface-world' ), 'small', '', false );
			echo '</form>';

			echo ' <form method="post" class="liw-row-form--inline">';
			wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
			echo '<input type="hidden" name="liw_action" value="delete" />';
			echo '<input type="hidden" name="request_id" value="' . esc_attr( (string) $id ) . '" />';
			submit_button( __( 'Löschen', 'liebherr-interface-world' ), 'small delete', '', false );
			echo '</form>';
			echo '</td>';

			echo '</tr>';
		}
		echo '</tbody></table>';

		AdminPagination::render( $page, $total, ContactService::REQUESTS_PER_PAGE );
	}
}
