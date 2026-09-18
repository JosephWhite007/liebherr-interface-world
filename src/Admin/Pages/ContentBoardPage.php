<?php
/**
 * Liebherr Interface Solutions – Content Board (Admin-Seite, §19 Redaktionsfunktionen)
 *
 * Grundgerüst (Entscheidung Joseph White, 18.09.2026): Das Pflichtenheft verlangt für das
 * Content Board volles CMS-Verhalten (Drag-and-Drop-Reihenfolge, Zeitsteuerung, Mehrsprachen-/
 * Geräte-Vorschau, CTA-Ziel-Picker, Pflichtfeld-Warnungen). Diese Auslieferung deckt bewusst
 * nur den Kern ab und nutzt dafür konsequent bereits vorhandene WP-Bordmittel statt sie
 * nachzubauen (CLAUDE.md Abschnitt 5 „keine Doppelentwicklungen"):
 *
 * - Titel/Inhalt/Reihenfolge/Revisionen: nativer WP-Editor (CPT unterstützt bereits
 *   'title','editor','page-attributes','revisions' seit alpha.1) – Revisionen vergleichen/
 *   wiederherstellen funktioniert dadurch bereits ohne zusätzlichen Code.
 * - Freigabeworkflow Entwurf → Prüfung → freigegeben → veröffentlicht: native Post-Status
 *   (draft/pending/`liw_approved`/publish) statt eigenem Freigabe-Datenmodell. Dieses Board
 *   ergänzt lediglich eine Statuswechsel-Aktion je Zeile, da der Block-Editor den
 *   Custom-Status `liw_approved` nicht in seinem eigenen Status-Dropdown anbietet.
 *
 * ANNAHME-LIW-6 (Annahmen-Protokoll): Reihenfolge (`menu_order`) wird über das native
 * „Reihenfolge"-Feld im Beitrags-Editor gepflegt (page-attributes-Metabox), nicht über
 * Drag-and-Drop in diesem Board. Grund: YAGNI, bis eine tatsächliche Häufung an Abschnitten
 * eine Umsortierung per Drag-and-Drop nötig macht; Alternative: eigene Sortable-UI (JS),
 * Auswirkung bei Änderung: additiv, kein Datenmodellbruch (`menu_order` existiert bereits
 * nativ).
 *
 * Bewusst NICHT Teil dieser Auslieferung (s. docs/LIW_TODO.md): Zeitsteuerte
 * Veröffentlichung über den bereits nativ vorhandenen `future`-Status hinaus, Vorschau je
 * Sprache/Gerät, CTA-Ziel-Picker, Medien-Picker-Beschränkung auf freigegebene Bibliothek,
 * Pflichtfeldprüfung/Übersetzungswarnungen.
 *
 * @package Liebherr\InterfaceWorld\Admin\Pages
 * @since   0.1.0-alpha.13
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin\Pages;

use Liebherr\InterfaceWorld\Content\SectionBlueprint;
use Liebherr\InterfaceWorld\Content\SectionSeeder;
use Liebherr\InterfaceWorld\CoreBridge\AuditBridge;
use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;
use Liebherr\InterfaceWorld\CPT\LiwSectionCpt;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ContentBoardPage {

	public const MENU_SLUG = 'liw-content-board';

	private const NONCE_ACTION = 'liw_content_board_set_status';
	private const NONCE_NAME   = 'liw_content_board_nonce';

	/** Seit alpha.17: fehlende Standard-Abschnitte LP-01…LP-14 per Knopf anlegen (SectionSeeder). */
	private const SEED_NONCE_ACTION = 'liw_content_board_seed';
	private const SEED_NONCE_NAME   = 'liw_content_board_seed_nonce';

	/** Freigabeworkflow §19: Entwurf → Prüfung → freigegeben → veröffentlicht. */
	private const WORKFLOW_STATUSES = [ 'draft', 'pending', LiwSectionCpt::STATUS_APPROVED, 'publish' ];

	private const STATUS_LABELS = [
		'draft'                          => 'Entwurf',
		'pending'                        => 'Prüfung',
		LiwSectionCpt::STATUS_APPROVED   => 'Freigegeben',
		'publish'                        => 'Veröffentlicht',
	];

	public static function render(): void {
		if ( ! current_user_can( RoleBridge::CAP_MANAGE_CONTENT ) ) {
			wp_die( esc_html__( 'Keine Berechtigung für das Content Board.', 'liebherr-interface-world' ) );
		}

		$notice = self::maybe_handle_submit();

		echo '<div class="wrap"><h1>' . esc_html__( 'Content Board – Landingpage-Abschnitte', 'liebherr-interface-world' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Verwaltet die Landingpage-Abschnitte (LP-01…LP-14, Pflichtenheft §8). Titel, Inhalt, Reihenfolge und Revisionen im jeweiligen Beitrags-Editor; hier: Übersicht und Freigabeworkflow.', 'liebherr-interface-world' ) . '</p>';

		if ( null !== $notice ) {
			printf( '<div class="notice %s"><p>%s</p></div>', esc_attr( $notice['class'] ), esc_html( $notice['message'] ) );
		}

		printf(
			'<p><a href="%s" class="page-title-action">%s</a></p>',
			esc_url( admin_url( 'post-new.php?post_type=' . LiwSectionCpt::POST_TYPE ) ),
			esc_html__( 'Neuen Abschnitt anlegen', 'liebherr-interface-world' )
		);

		self::render_seed_form();
		self::render_table();
		echo '</div>';
	}

	/** Knopf „fehlende Standard-Abschnitte anlegen" – nur sichtbar, solange Codes aus dem Bauplan fehlen. */
	private static function render_seed_form(): void {
		$missing = SectionSeeder::missing_codes();
		if ( [] === $missing ) {
			return;
		}

		echo '<form method="post" class="liw-row-form--inline">';
		wp_nonce_field( self::SEED_NONCE_ACTION, self::SEED_NONCE_NAME );
		echo '<input type="hidden" name="liw_action" value="seed_sections" />';
		submit_button(
			sprintf(
				/* translators: %d: Anzahl fehlender Abschnitte */
				_n( '%d fehlenden Standard-Abschnitt anlegen', '%d fehlende Standard-Abschnitte anlegen', count( $missing ), 'liebherr-interface-world' ),
				count( $missing )
			),
			'secondary',
			'',
			false
		);
		echo ' <span class="description">' . esc_html( implode( ', ', $missing ) ) . ' – ' . esc_html__( 'als Entwürfe mit Redaktionsvorgabe aus dem Pflichtenheft §8', 'liebherr-interface-world' ) . '</span>';
		echo '</form>';
	}

	/** @return array{class:string,message:string}|null */
	private static function maybe_handle_submit(): ?array {
		$action = isset( $_POST['liw_action'] ) ? sanitize_key( wp_unslash( $_POST['liw_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce wird je Aktion unten geprüft.

		if ( 'seed_sections' === $action ) {
			check_admin_referer( self::SEED_NONCE_ACTION, self::SEED_NONCE_NAME );
			$result = SectionSeeder::seed_missing( get_current_user_id() );
			if ( [] !== $result['errors'] ) {
				return [ 'class' => 'notice-error', 'message' => sprintf( __( 'Fehler beim Anlegen: %s', 'liebherr-interface-world' ), implode( '; ', array_map( static fn( string $c, string $m ): string => "{$c}: {$m}", array_keys( $result['errors'] ), $result['errors'] ) ) ) ];
			}
			return [
				'class'   => 'notice-success',
				'message' => sprintf(
					/* translators: 1: Anzahl angelegter, 2: Anzahl übersprungener Abschnitte */
					__( '%1$d Abschnitt(e) angelegt, %2$d bereits vorhanden.', 'liebherr-interface-world' ),
					count( $result['created'] ),
					count( $result['skipped'] )
				),
			];
		}

		if ( 'set_status' !== $action ) {
			return null;
		}
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$post_id = absint( wp_unslash( $_POST['post_id'] ?? 0 ) );
		$status  = sanitize_key( wp_unslash( $_POST['post_status'] ?? '' ) );

		if ( ! in_array( $status, self::WORKFLOW_STATUSES, true ) ) {
			return [ 'class' => 'notice-error', 'message' => __( 'Unbekannter Status.', 'liebherr-interface-world' ) ];
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || LiwSectionCpt::POST_TYPE !== $post->post_type ) {
			return [ 'class' => 'notice-error', 'message' => __( 'Abschnitt nicht gefunden.', 'liebherr-interface-world' ) ];
		}

		$before = $post->post_status;
		$result = wp_update_post( [ 'ID' => $post_id, 'post_status' => $status ], true );
		if ( is_wp_error( $result ) ) {
			return [ 'class' => 'notice-error', 'message' => $result->get_error_message() ];
		}

		AuditBridge::log( 'status_change', 'section', $post_id, [ 'post_status' => $before ], [ 'post_status' => $status ], get_current_user_id() );
		return [ 'class' => 'notice-success', 'message' => __( 'Status aktualisiert.', 'liebherr-interface-world' ) ];
	}

	private static function render_table(): void {
		$posts = get_posts( [
			'post_type'      => LiwSectionCpt::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => -1, // Kuratierte Anzahl (max. 14 laut Pflichtenheft §8) – keine Pagination nötig.
			'orderby'        => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
		] );

		echo '<table class="widefat striped"><thead><tr>';
		foreach ( [ 'Reihenfolge', 'Code', 'Titel', 'Status', 'Aktionen' ] as $column ) {
			echo '<th>' . esc_html( $column ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( [] === $posts ) {
			echo '<tr><td colspan="5">' . esc_html__( 'Noch keine Abschnitte angelegt.', 'liebherr-interface-world' ) . '</td></tr>';
		}

		foreach ( $posts as $post ) {
			$status = $post->post_status;
			echo '<tr>';
			echo '<td>' . esc_html( (string) $post->menu_order ) . '</td>';
			echo '<td>' . esc_html( (string) get_post_meta( $post->ID, SectionBlueprint::META_CODE, true ) ?: '–' ) . '</td>';
			echo '<td>' . esc_html( get_the_title( $post ) ?: __( '(ohne Titel)', 'liebherr-interface-world' ) ) . '</td>';
			echo '<td>' . esc_html( self::STATUS_LABELS[ $status ] ?? $status ) . '</td>';

			echo '<td class="liw-row-form--inline">';
			printf( '<a href="%s">%s</a>', esc_url( (string) get_edit_post_link( $post ) ), esc_html__( 'Bearbeiten', 'liebherr-interface-world' ) );

			$preview_link = 'publish' === $status ? get_permalink( $post ) : get_preview_post_link( $post );
			if ( is_string( $preview_link ) && '' !== $preview_link ) {
				printf( ' · <a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $preview_link ), esc_html__( 'Vorschau', 'liebherr-interface-world' ) );
			}

			echo ' · <form method="post" class="liw-row-form--inline">';
			wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
			echo '<input type="hidden" name="liw_action" value="set_status" />';
			echo '<input type="hidden" name="post_id" value="' . esc_attr( (string) $post->ID ) . '" />';
			echo '<select name="post_status">';
			foreach ( self::WORKFLOW_STATUSES as $option ) {
				printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $option ), selected( $status, $option, false ), esc_html( self::STATUS_LABELS[ $option ] ) );
			}
			echo '</select> ';
			submit_button( __( 'Übernehmen', 'liebherr-interface-world' ), 'small', '', false );
			echo '</form>';
			echo '</td>';

			echo '</tr>';
		}
		echo '</tbody></table>';
	}
}
