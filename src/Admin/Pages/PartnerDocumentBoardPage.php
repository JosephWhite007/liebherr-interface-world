<?php
/**
 * Liebherr Interface Solutions – Partnerdokumente (Admin-Seite)
 *
 * Neuntes Admin-Board: Upload, Übersicht und Soft-Delete der geschützten Partnerdokumente
 * (PartnerDocumentService). Capability `liw_manage_content` (Redaktion entscheidet, was Partner
 * sehen). Upload per Multipart-Formular mit Nonce; alle Prüfungen (Typ, Größe, Inhalt) liegen
 * im Service. Bewusst NICHT die Medienbibliothek: deren Dateien sind öffentlich per URL.
 *
 * @package Liebherr\InterfaceWorld\Admin\Pages
 * @since   0.1.0-alpha.22
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin\Pages;

use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;
use Liebherr\InterfaceWorld\Partner\PartnerDocumentService;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class PartnerDocumentBoardPage {

	public const MENU_SLUG = 'liw-partner-documents';

	private const NONCE_ACTION = 'liw_partner_documents_action';
	private const NONCE_NAME   = 'liw_partner_documents_nonce';

	public static function render(): void {
		if ( ! current_user_can( RoleBridge::CAP_MANAGE_CONTENT ) ) {
			wp_die( esc_html__( 'Keine Berechtigung für Partnerdokumente.', 'liebherr-interface-world' ) );
		}

		$notice = self::maybe_handle_submit();

		echo '<div class="wrap"><h1>' . esc_html__( 'Partnerdokumente (geschützter Bereich)', 'liebherr-interface-world' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Dokumente hier sind nur für angemeldete Benutzer mit der Rolle „Liebherr Interface Partner" über den Shortcode [liw_partner_documents] herunterladbar – nie per öffentlicher URL. Jeder Download wird protokolliert. Bitte nur Fassungen hochladen, die für Partner freigegeben sind (keine internen Endpunkt-/Systemnamen ohne Freigabe).', 'liebherr-interface-world' ) . '</p>';

		if ( null !== $notice ) {
			printf( '<div class="notice %s"><p>%s</p></div>', esc_attr( $notice['class'] ), esc_html( $notice['message'] ) );
		}

		self::render_upload_form();
		self::render_table();
		echo '</div>';
	}

	/** @return array{class:string,message:string}|null */
	private static function maybe_handle_submit(): ?array {
		$action = isset( $_POST['liw_action'] ) ? sanitize_key( wp_unslash( $_POST['liw_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce unten geprüft.
		if ( ! in_array( $action, [ 'upload', 'delete' ], true ) ) {
			return null;
		}
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		if ( 'delete' === $action ) {
			$result = PartnerDocumentService::soft_delete( absint( wp_unslash( $_POST['document_id'] ?? 0 ) ), get_current_user_id() );
			return is_wp_error( $result )
				? [ 'class' => 'notice-error', 'message' => $result->get_error_message() ]
				: [ 'class' => 'notice-success', 'message' => __( 'Dokument entfernt (für Partner nicht mehr sichtbar; Datei bleibt für den Audit-Trail erhalten).', 'liebherr-interface-world' ) ];
		}

		$file = isset( $_FILES['liw_document'] ) && is_array( $_FILES['liw_document'] ) ? $_FILES['liw_document'] : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- vollständige Validierung im Service.
		$result = PartnerDocumentService::upload(
			$file,
			sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ),
			get_current_user_id()
		);
		return is_wp_error( $result )
			? [ 'class' => 'notice-error', 'message' => $result->get_error_message() ]
			: [ 'class' => 'notice-success', 'message' => __( 'Dokument hochgeladen und für Partner bereitgestellt.', 'liebherr-interface-world' ) ];
	}

	private static function render_upload_form(): void {
		?>
		<h2><?php esc_html_e( 'Dokument bereitstellen', 'liebherr-interface-world' ); ?></h2>
		<form method="post" enctype="multipart/form-data" class="liw-upload-form">
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
			<input type="hidden" name="liw_action" value="upload" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="liw_pd_title"><?php esc_html_e( 'Titel', 'liebherr-interface-world' ); ?> *</label></th>
					<td><input type="text" id="liw_pd_title" name="title" class="regular-text" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="liw_pd_description"><?php esc_html_e( 'Beschreibung', 'liebherr-interface-world' ); ?></label></th>
					<td><textarea id="liw_pd_description" name="description" rows="3" class="large-text"></textarea></td>
				</tr>
				<tr>
					<th scope="row"><label for="liw_pd_file"><?php esc_html_e( 'Datei', 'liebherr-interface-world' ); ?> *</label></th>
					<td>
						<input type="file" id="liw_pd_file" name="liw_document" accept=".pdf,.png,.jpg,.jpeg,.docx" required />
						<p class="description"><?php esc_html_e( 'PDF, PNG, JPG oder DOCX, max. 10 MB. Dateiname und Inhalt werden geprüft.', 'liebherr-interface-world' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Hochladen', 'liebherr-interface-world' ) ); ?>
		</form>
		<?php
	}

	private static function render_table(): void {
		$rows = PartnerDocumentService::get_active();

		echo '<h2>' . esc_html__( 'Bereitgestellte Dokumente', 'liebherr-interface-world' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( [ 'Titel', 'Datei', 'Größe', 'Bereitgestellt', 'Aktion' ] as $column ) {
			echo '<th>' . esc_html( $column ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( [] === $rows ) {
			echo '<tr><td colspan="5">' . esc_html__( 'Noch keine Dokumente bereitgestellt.', 'liebherr-interface-world' ) . '</td></tr>';
		}

		foreach ( $rows as $row ) {
			echo '<tr>';
			echo '<td><strong>' . esc_html( (string) $row['title'] ) . '</strong>';
			if ( ! empty( $row['description'] ) ) {
				echo '<br /><small>' . esc_html( (string) $row['description'] ) . '</small>';
			}
			echo '</td>';
			echo '<td>' . esc_html( (string) $row['original_name'] ) . '<br /><small>' . esc_html( (string) $row['mime_type'] ) . '</small></td>';
			echo '<td>' . esc_html( size_format( (int) $row['size_bytes'] ) ) . '</td>';
			echo '<td>' . esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $row['created_at'] ) ) . '</td>';
			echo '<td><form method="post" class="liw-row-form--inline">';
			wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
			echo '<input type="hidden" name="liw_action" value="delete" />';
			echo '<input type="hidden" name="document_id" value="' . esc_attr( (string) $row['id'] ) . '" />';
			submit_button( __( 'Entfernen', 'liebherr-interface-world' ), 'small delete', '', false );
			echo '</form></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}
}
