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
use Liebherr\InterfaceWorld\CoreBridge\LanguageBridge;
use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;
use Liebherr\InterfaceWorld\Content\SectionSchedule;
use Liebherr\InterfaceWorld\CPT\LiwSectionCpt;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ContentBoardPage {

	public const MENU_SLUG = 'liw-content-board';

	private const NONCE_ACTION = 'liw_content_board_set_status';
	private const NONCE_NAME   = 'liw_content_board_nonce';

	/** Seit alpha.17: fehlende Standard-Abschnitte LP-01…LP-14 per Knopf anlegen (SectionSeeder). */
	private const SEED_NONCE_ACTION = 'liw_content_board_seed';
	private const SEED_NONCE_NAME   = 'liw_content_board_seed_nonce';

	public const REORDER_ACTION = 'liw_reorder_sections';
	public const REORDER_NONCE  = 'liw_reorder_sections_nonce';

	/** AJAX-Endpunkt für Drag-&-Drop-Reihenfolge registrieren (§19). */
	public static function register(): void {
		add_action( 'wp_ajax_' . self::REORDER_ACTION, [ self::class, 'ajax_reorder' ] );
	}

	/** Speichert die per Drag-&-Drop übermittelte Reihenfolge als menu_order (10,20,…). */
	public static function ajax_reorder(): void {
		if ( ! check_ajax_referer( self::REORDER_NONCE, '_wpnonce', false ) ) {
			wp_send_json_error( [ 'message' => 'bad_nonce' ], 400 );
		}
		if ( ! current_user_can( RoleBridge::CAP_MANAGE_CONTENT ) ) {
			wp_send_json_error( [ 'message' => 'forbidden' ], 403 );
		}
		$order = isset( $_POST['order'] ) && is_array( $_POST['order'] ) ? array_map( 'absint', wp_unslash( $_POST['order'] ) ) : [];
		$order = array_values( array_filter( $order ) );
		if ( [] === $order ) {
			wp_send_json_error( [ 'message' => 'empty' ], 400 );
		}
		$updated = self::apply_order( $order );
		AuditBridge::log( 'reorder', 'section', 0, [], [ 'count' => $updated ], get_current_user_id() );
		wp_send_json_success( [ 'updated' => $updated ] );
	}

	/**
	 * Setzt menu_order (10, 20, …) für die übergebene ID-Reihenfolge; nur `liw_section`-Posts.
	 *
	 * @param int[] $order
	 * @return int Anzahl aktualisierter Abschnitte
	 */
	public static function apply_order( array $order ): int {
		$position = 0;
		$updated  = 0;
		foreach ( $order as $post_id ) {
			$post_id = (int) $post_id;
			if ( LiwSectionCpt::POST_TYPE !== get_post_type( $post_id ) ) {
				continue;
			}
			$position += 10;
			wp_update_post( [ 'ID' => $post_id, 'menu_order' => $position ] );
			$updated++;
		}
		return $updated;
	}

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
		self::render_qa();
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

	/**
	 * Redaktions-Prüfung (§19): warnt bei veröffentlichten Abschnitten ohne Titel oder mit Bildern
	 * ohne Alt-Text. Reine Leseansicht; Übersetzungs-Vollständigkeit prüft das Sprach-Release-Gate.
	 */
	private static function render_qa(): void {
		$published = get_posts( [
			'post_type'      => LiwSectionCpt::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
		] );

		$warnings = [];
		foreach ( $published as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			$issues = [];
			if ( '' === trim( (string) get_the_title( $post ) ) ) {
				$issues[] = __( 'kein Titel', 'liebherr-interface-world' );
			}
			$imgs_without_alt = self::count_images_without_alt( (string) $post->post_content );
			if ( $imgs_without_alt > 0 ) {
				/* translators: %d: Anzahl Bilder ohne Alt-Text. */
				$issues[] = sprintf( _n( '%d Bild ohne Alt-Text', '%d Bilder ohne Alt-Text', $imgs_without_alt, 'liebherr-interface-world' ), $imgs_without_alt );
			}
			if ( [] !== $issues ) {
				$warnings[] = [ 'post' => $post, 'issues' => $issues ];
			}
		}

		echo '<h2>' . esc_html__( 'Redaktions-Prüfung (veröffentlichte Abschnitte)', 'liebherr-interface-world' ) . '</h2>';
		if ( [] === $warnings ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Keine Beanstandungen: alle veröffentlichten Abschnitte haben einen Titel und Bilder mit Alt-Text.', 'liebherr-interface-world' ) . '</p></div>';
			return;
		}
		echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Bitte vor dem Launch beheben (Pflichtfelder/Alt-Texte, §19/§26):', 'liebherr-interface-world' ) . '</p><ul style="list-style:disc;margin-left:20px">';
		foreach ( $warnings as $w ) {
			printf(
				'<li><a href="%1$s">%2$s</a> – %3$s</li>',
				esc_url( (string) get_edit_post_link( $w['post'] ) ),
				esc_html( get_the_title( $w['post'] ) !== '' ? get_the_title( $w['post'] ) : ( '#' . $w['post']->ID ) ),
				esc_html( implode( ', ', $w['issues'] ) )
			);
		}
		echo '</ul></div>';
	}

	/** Zählt <img>-Tags ohne nicht-leeres alt-Attribut (rein, testbar). */
	public static function count_images_without_alt( string $html ): int {
		if ( ! preg_match_all( '/<img\b[^>]*>/i', $html, $m ) ) {
			return 0;
		}
		$count = 0;
		foreach ( $m[0] as $tag ) {
			if ( ! preg_match( '/\balt\s*=\s*("[^"]+"|\'[^\']+\')/i', $tag ) ) {
				$count++;
			}
		}
		return $count;
	}

	private static function render_table(): void {
		$posts = get_posts( [
			'post_type'      => LiwSectionCpt::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => -1, // Kuratierte Anzahl (max. 14 laut Pflichtenheft §8) – keine Pagination nötig.
			'orderby'        => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
		] );

		echo '<p class="description">' . esc_html__( 'Zeilen per Ziehgriff (↕) verschieben, um die Reihenfolge auf der Landingpage zu ändern (wird sofort gespeichert).', 'liebherr-interface-world' ) . '</p>';
		printf(
			'<table class="widefat striped" data-liw-reorder="1" data-liw-nonce="%s">',
			esc_attr( wp_create_nonce( self::REORDER_NONCE ) )
		);
		echo '<thead><tr>';
		foreach ( [ 'Reihenfolge', 'Code', 'Titel', 'Status', 'Aktionen' ] as $column ) {
			echo '<th>' . esc_html( $column ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( [] === $posts ) {
			echo '<tr><td colspan="5">' . esc_html__( 'Noch keine Abschnitte angelegt.', 'liebherr-interface-world' ) . '</td></tr>';
		}

		foreach ( $posts as $post ) {
			$status = $post->post_status;
			printf( '<tr data-liw-id="%d">', (int) $post->ID );
			echo '<td><span class="liw-drag-handle" title="' . esc_attr__( 'Ziehen zum Sortieren', 'liebherr-interface-world' ) . '" aria-hidden="true">↕</span> <span class="liw-order-num">' . esc_html( (string) $post->menu_order ) . '</span></td>';
			echo '<td>' . esc_html( (string) get_post_meta( $post->ID, SectionBlueprint::META_CODE, true ) ?: '–' ) . '</td>';
			echo '<td>' . esc_html( get_the_title( $post ) ?: __( '(ohne Titel)', 'liebherr-interface-world' ) ) . '</td>';
			$status_cell = esc_html( self::STATUS_LABELS[ $status ] ?? $status );
			if ( SectionSchedule::has_window( $post->ID ) ) {
				$window_note = SectionSchedule::is_visible_now( $post->ID )
					? __( 'im Zeitfenster', 'liebherr-interface-world' )
					: __( 'außerhalb Zeitfenster', 'liebherr-interface-world' );
				$status_cell .= ' <span class="description" title="' . esc_attr__( 'Sichtbarkeits-Zeitfenster gesetzt', 'liebherr-interface-world' ) . '">🕒 ' . esc_html( $window_note ) . '</span>';
			}
			echo '<td>' . $status_cell . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Teile oben escaped.

			echo '<td class="liw-row-form--inline">';
			printf( '<a href="%s">%s</a>', esc_url( (string) get_edit_post_link( $post ) ), esc_html__( 'Bearbeiten', 'liebherr-interface-world' ) );

			$preview_link = 'publish' === $status ? get_permalink( $post ) : get_preview_post_link( $post );
			if ( is_string( $preview_link ) && '' !== $preview_link ) {
				printf( ' · <a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( $preview_link ), esc_html__( 'Vorschau', 'liebherr-interface-world' ) );
				// Vorschau je Sprache (§19): Core-Sprachsteuerung via ?lang=xx.
				$langs = LanguageBridge::active_langs();
				if ( count( $langs ) > 1 ) {
					$lang_links = [];
					foreach ( $langs as $lang ) {
						$lang_links[] = sprintf(
							'<a href="%s" target="_blank" rel="noopener">%s</a>',
							esc_url( add_query_arg( 'lang', $lang, $preview_link ) ),
							esc_html( strtoupper( (string) $lang ) )
						);
					}
					echo ' <span class="liw-preview-langs">(' . implode( ' · ', $lang_links ) . ')</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- je Link oben escaped.
				}
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
