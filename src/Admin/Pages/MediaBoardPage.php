<?php
/**
 * Liebherr Interface Solutions – Media Board (Admin-Seite)
 *
 * Fünftes Admin-Board: Bulk-Übersicht und -Freigabe für Medien (Liebherr-Pflichtenheft §18
 * „Media Board" – Assets, Copyright, Freigabe). Nutzt die native WP-Medienbibliothek + die
 * CoreBridge\MediaBridge-Metafelder (`_liw_media_copyright`, `_liw_media_source`,
 * `_liw_media_approved`, CI-005) – kein eigenes Mediensystem, kein Datenmodell zusätzlich
 * zu den bereits seit alpha.1 registrierten Attachment-Meta-Feldern.
 *
 * Capability: `liw_manage_content` (wie World Connections Map, ANNAHME-LIW-3-Präzedenzfall:
 * Medienfreigabe ist ebenfalls eine redaktionelle Publizieren-Entscheidung).
 *
 * @package Liebherr\InterfaceWorld\Admin\Pages
 * @since   0.1.0-alpha.7
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin\Pages;

use Liebherr\InterfaceWorld\Admin\AdminPagination;
use Liebherr\InterfaceWorld\CoreBridge\MediaBridge;
use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class MediaBoardPage {

	public const MENU_SLUG = 'liw-media-board';

	private const NONCE_ACTION  = 'liw_media_board_save';
	private const NONCE_NAME    = 'liw_media_board_nonce';

	/** Seitengröße der Bulk-Tabelle (Feinschliff alpha.10: vorher harte Obergrenze ohne Blättern). */
	private const ITEMS_PER_PAGE = 50;

	public static function render(): void {
		if ( ! current_user_can( RoleBridge::CAP_MANAGE_CONTENT ) ) {
			wp_die( esc_html__( 'Keine Berechtigung für das Media Board.', 'liebherr-interface-world' ) );
		}

		$notice = self::maybe_handle_submit();
		$filter = isset( $_GET['liw_filter'] ) ? sanitize_key( wp_unslash( $_GET['liw_filter'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reine Filteransicht, keine Datenänderung.

		echo '<div class="wrap"><h1>' . esc_html__( 'Media Board – Assets &amp; Freigabe', 'liebherr-interface-world' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Copyright, Quelle und CI-005-Freigabe für Medien der Interface-World-Landingpage. Bulk-Speichern für alle Zeilen gleichzeitig.', 'liebherr-interface-world' ) . '</p>';

		if ( null !== $notice ) {
			printf( '<div class="notice %s"><p>%s</p></div>', esc_attr( $notice['class'] ), esc_html( $notice['message'] ) );
		}

		self::render_filter_links( $filter );
		self::render_bulk_form( $filter );
		echo '</div>';
	}

	/** @return array{class:string,message:string}|null */
	private static function maybe_handle_submit(): ?array {
		if ( ! isset( $_POST['liw_action'] ) || 'save_media' !== $_POST['liw_action'] ) {
			return null;
		}
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		if ( ! current_user_can( 'upload_files' ) ) {
			return [ 'class' => 'notice-error', 'message' => __( 'Keine Berechtigung zum Bearbeiten von Medien.', 'liebherr-interface-world' ) ];
		}

		$items = wp_unslash( $_POST['liw_media'] ?? [] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		if ( ! is_array( $items ) ) {
			return [ 'class' => 'notice-error', 'message' => __( 'Ungültige Daten.', 'liebherr-interface-world' ) ];
		}

		$updated = 0;
		foreach ( $items as $attachment_id => $fields ) {
			$attachment_id = absint( $attachment_id );
			if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
				continue;
			}

			update_post_meta( $attachment_id, MediaBridge::META_COPYRIGHT, sanitize_text_field( $fields['copyright'] ?? '' ) );
			update_post_meta( $attachment_id, MediaBridge::META_SOURCE, sanitize_text_field( $fields['source'] ?? '' ) );
			update_post_meta( $attachment_id, MediaBridge::META_APPROVED, ! empty( $fields['approved'] ) ? '1' : '0' );
			++$updated;
		}

		return [
			'class'   => 'notice-success',
			/* translators: %d: Anzahl der aktualisierten Medien. */
			'message' => sprintf( _n( '%d Medium aktualisiert.', '%d Medien aktualisiert.', $updated, 'liebherr-interface-world' ), $updated ),
		];
	}

	private static function render_filter_links( string $active ): void {
		$links = [
			'all'      => __( 'Alle', 'liebherr-interface-world' ),
			'approved' => __( 'Freigegeben', 'liebherr-interface-world' ),
			'pending'  => __( 'Nicht freigegeben', 'liebherr-interface-world' ),
		];

		echo '<ul class="subsubsub">';
		$parts = [];
		foreach ( $links as $key => $label ) {
			$url    = add_query_arg( [ 'page' => self::MENU_SLUG, 'liw_filter' => $key ], admin_url( 'admin.php' ) );
			$class  = ( $key === $active ) ? ' class="current"' : '';
			$parts[] = sprintf( '<li><a href="%s"%s>%s</a></li>', esc_url( $url ), $class, esc_html( $label ) );
		}
		echo implode( ' | ', $parts );
		echo '</ul><div class="liw-clear"></div>';
	}

	private static function render_bulk_form( string $filter ): void {
		$page       = AdminPagination::current_page();
		$query_args = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => self::ITEMS_PER_PAGE,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		if ( 'approved' === $filter || 'pending' === $filter ) {
			$query_args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'     => MediaBridge::META_APPROVED,
					'value'   => '1',
					'compare' => 'approved' === $filter ? '=' : '!=',
				],
			];
		}

		// Feinschliff alpha.10: `WP_Query` statt `get_posts()` – liefert `found_posts` für die
		// Pagination-Anzeige mit (vorher: hart auf die ersten 50 neuesten Anhänge begrenzt,
		// ohne Blättern zu älteren Medien).
		$query       = new \WP_Query( $query_args );
		$attachments = $query->posts;

		echo '<form method="post">';
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		echo '<input type="hidden" name="liw_action" value="save_media" />';

		echo '<table class="widefat striped"><thead><tr>';
		foreach ( [ 'Vorschau', 'Titel', 'Copyright / Rechteinhaber', 'Asset-Quelle', 'Freigegeben (CI-005)' ] as $column ) {
			echo '<th>' . esc_html( $column ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( [] === $attachments ) {
			echo '<tr><td colspan="5">' . esc_html__( 'Keine Medien gefunden.', 'liebherr-interface-world' ) . '</td></tr>';
		}

		foreach ( $attachments as $attachment ) {
			$id        = $attachment->ID;
			$copyright = get_post_meta( $id, MediaBridge::META_COPYRIGHT, true );
			$source    = get_post_meta( $id, MediaBridge::META_SOURCE, true );
			$approved  = MediaBridge::is_approved( $id );

			echo '<tr>';
			echo '<td>' . wp_get_attachment_image( $id, [ 60, 60 ] ) . '</td>';
			echo '<td>' . esc_html( get_the_title( $id ) ) . '<br /><a href="' . esc_url( get_edit_post_link( $id ) ?? '' ) . '">' . esc_html__( 'Details bearbeiten', 'liebherr-interface-world' ) . '</a></td>';
			printf( '<td><input type="text" name="liw_media[%1$d][copyright]" value="%2$s" class="regular-text" /></td>', $id, esc_attr( (string) $copyright ) );
			printf( '<td><input type="text" name="liw_media[%1$d][source]" value="%2$s" class="regular-text" /></td>', $id, esc_attr( (string) $source ) );
			printf(
				'<td><input type="checkbox" name="liw_media[%1$d][approved]" value="1" %2$s /></td>',
				$id,
				checked( $approved, true, false )
			);
			echo '</tr>';
		}
		echo '</tbody></table>';

		if ( [] !== $attachments ) {
			submit_button( __( 'Alle Änderungen speichern', 'liebherr-interface-world' ) );
		}
		echo '</form>';

		AdminPagination::render( $page, (int) $query->found_posts, self::ITEMS_PER_PAGE );
	}
}
