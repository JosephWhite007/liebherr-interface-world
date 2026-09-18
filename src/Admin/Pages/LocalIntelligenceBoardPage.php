<?php
/**
 * Liebherr Local Intelligence – Pflege-Board (Pflichtenheft LI §12.3).
 *
 * Redaktionelle Pflege der elf Module der Hauptseite (Überschrift, Einleitung, CTAs sowie die
 * Listen als Zeilenformat). Ablage über Settings\LocalIntelligenceContent (Option), Ausgabe über die
 * Shortcodes bzw. `[liw_local_intelligence]`. Capability `liw_manage_content`, Nonce, Audit (SEC-005).
 * Muster identisch zu ComponentsBoardPage (keine zweite Verwaltungslogik).
 *
 * @package Liebherr\InterfaceWorld\Admin\Pages
 * @since   0.1.0-alpha.41
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin\Pages;

use Liebherr\InterfaceWorld\CoreBridge\AuditBridge;
use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;
use Liebherr\InterfaceWorld\Settings\LocalIntelligenceContent as Content;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class LocalIntelligenceBoardPage {

	public const MENU_SLUG = 'liw-local-intelligence-board';

	private const NONCE_ACTION = 'liw_li_board_save';
	private const NONCE_NAME   = 'liw_li_board_nonce';

	public static function render(): void {
		if ( ! current_user_can( RoleBridge::CAP_MANAGE_CONTENT ) ) {
			wp_die( esc_html__( 'Keine Berechtigung für das Local-Intelligence-Board.', 'liebherr-interface-world' ) );
		}

		$notice = self::maybe_handle_submit();
		$d      = Content::get();

		echo '<div class="wrap"><h1>' . esc_html__( 'Local Intelligence – Inhalte', 'liebherr-interface-world' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Redaktionelle Inhalte der Hauptseite. Listen im Format „Titel | Text" je Zeile; leere Felder setzen den Standard (Pflichtenheft) zurück. Nur Demonstrationsinhalte – keine echten Geschäftsdaten.', 'liebherr-interface-world' ) . '</p>';

		if ( null !== $notice ) {
			printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr( $notice['class'] ), esc_html( $notice['message'] ) );
		}

		echo '<form method="post" action="">';
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		echo '<input type="hidden" name="liw_action" value="save_li" />';

		// Intro-Overlay (Sternenregen + Eintritts-Fenster).
		self::group( __( 'Intro-Overlay (Sternenregen + Eintritt)', 'liebherr-interface-world' ) );
		self::text( 'intro][title', __( 'Titel', 'liebherr-interface-world' ), (string) $d['intro']['title'] );
		self::text( 'intro][subtitle', __( 'Untertitel', 'liebherr-interface-world' ), (string) $d['intro']['subtitle'] );
		self::area( 'intro][text', __( 'Begrüßungstext', 'liebherr-interface-world' ), (string) $d['intro']['text'] );
		self::text( 'intro][terms_button_label', __( 'Button „Nutzungsbedingungen"', 'liebherr-interface-world' ), (string) $d['intro']['terms_button_label'] );
		self::text( 'intro][terms_heading', __( 'Überschrift Nutzungsbedingungen', 'liebherr-interface-world' ), (string) $d['intro']['terms_heading'] );
		self::area( 'intro][terms_body', __( 'Nutzungsbedingungen (Text)', 'liebherr-interface-world' ), (string) $d['intro']['terms_body'] );
		self::text( 'intro][math_label', __( 'Label Rechenaufgabe', 'liebherr-interface-world' ), (string) $d['intro']['math_label'] );
		self::text( 'intro][accept_label', __( 'Button „Eintreten"', 'liebherr-interface-world' ), (string) $d['intro']['accept_label'] );
		self::text( 'intro][accept_hint', __( 'Hinweistext (Eintritt)', 'liebherr-interface-world' ), (string) $d['intro']['accept_hint'] );

		// Modul 1 – Hero.
		self::group( __( 'Modul 1 – Hero', 'liebherr-interface-world' ) );
		self::text( 'hero][eyebrow', __( 'Eyebrow', 'liebherr-interface-world' ), (string) $d['hero']['eyebrow'] );
		self::text( 'hero][headline', __( 'Hauptüberschrift', 'liebherr-interface-world' ), (string) $d['hero']['headline'] );
		self::area( 'hero][intro', __( 'Einleitung', 'liebherr-interface-world' ), (string) $d['hero']['intro'] );
		self::text( 'hero][tagline', __( 'Kurzzeile', 'liebherr-interface-world' ), (string) $d['hero']['tagline'] );
		self::text( 'hero][cta_primary_label', __( 'CTA primär', 'liebherr-interface-world' ), (string) $d['hero']['cta_primary_label'] );
		self::text( 'hero][cta_secondary_label', __( 'CTA sekundär', 'liebherr-interface-world' ), (string) $d['hero']['cta_secondary_label'] );

		// Titel/Intro + Paar-Liste-Module.
		self::title_intro_list( __( 'Modul 2 – Vision', 'liebherr-interface-world' ), 'vision', 'fields', $d['vision'] );
		self::title_intro_list( __( 'Modul 3 – Datenbewegung', 'liebherr-interface-world' ), 'flow', 'steps', $d['flow'] );

		// Modul 4 – Simulation (Kopf; Szenarien A/B/C über Seeder/Default gepflegt).
		self::group( __( 'Modul 4 – Simulation World', 'liebherr-interface-world' ) );
		self::text( 'simulation][title', __( 'Überschrift', 'liebherr-interface-world' ), (string) $d['simulation']['title'] );
		self::area( 'simulation][intro', __( 'Einleitung', 'liebherr-interface-world' ), (string) $d['simulation']['intro'] );
		self::text( 'simulation][demo_note', __( 'Demo-Hinweis', 'liebherr-interface-world' ), (string) $d['simulation']['demo_note'] );
		echo '<p class="description">' . esc_html__( 'Szenarien A/B/C werden über die Standardwerte bzw. den Seeder gepflegt (Demo-Daten).', 'liebherr-interface-world' ) . '</p>';

		// Modul 5 – Knowledge.
		self::group( __( 'Modul 5 – Wissensassistenz', 'liebherr-interface-world' ) );
		self::text( 'knowledge][title', __( 'Überschrift', 'liebherr-interface-world' ), (string) $d['knowledge']['title'] );
		self::area( 'knowledge][intro', __( 'Einleitung', 'liebherr-interface-world' ), (string) $d['knowledge']['intro'] );
		self::area( 'knowledge][question', __( 'Frage', 'liebherr-interface-world' ), (string) $d['knowledge']['question'] );
		self::area( 'knowledge][answer', __( 'Antwort', 'liebherr-interface-world' ), (string) $d['knowledge']['answer'] );
		self::text( 'knowledge][source', __( 'Quelle', 'liebherr-interface-world' ), (string) $d['knowledge']['source'] );
		self::text( 'knowledge][status', __( 'Freigabestatus', 'liebherr-interface-world' ), (string) $d['knowledge']['status'] );
		self::area( 'knowledge][next_action', __( 'Nächste Aktion', 'liebherr-interface-world' ), (string) $d['knowledge']['next_action'] );
		self::list_area( 'knowledge_points', __( 'Punkte (Titel | Text)', 'liebherr-interface-world' ), $d['knowledge']['points'] );

		self::title_intro_list( __( 'Modul 6 – Datenqualität', 'liebherr-interface-world' ), 'trust', 'areas', $d['trust'] );

		// Modul 7 – Global.
		self::group( __( 'Modul 7 – Weltweite Nutzung', 'liebherr-interface-world' ) );
		self::text( 'global][title', __( 'Überschrift', 'liebherr-interface-world' ), (string) $d['global']['title'] );
		self::area( 'global][intro', __( 'Einleitung', 'liebherr-interface-world' ), (string) $d['global']['intro'] );
		self::plain_area( 'global_devices', __( 'Endgeräte (eins je Zeile)', 'liebherr-interface-world' ), (array) $d['global']['devices'] );
		self::area( 'global][workflow_note', __( 'Workflow-Hinweis', 'liebherr-interface-world' ), (string) $d['global']['workflow_note'] );
		self::area( 'global][languages_note', __( 'Sprachen-Hinweis', 'liebherr-interface-world' ), (string) $d['global']['languages_note'] );

		// Modul 8 – Usecases.
		self::group( __( 'Modul 8 – Einsatzfelder', 'liebherr-interface-world' ) );
		self::text( 'usecases][title', __( 'Überschrift', 'liebherr-interface-world' ), (string) $d['usecases']['title'] );
		self::area( 'usecases][intro', __( 'Einleitung', 'liebherr-interface-world' ), (string) $d['usecases']['intro'] );
		$uc = '';
		foreach ( (array) $d['usecases']['items'] as $it ) {
			$uc .= $it['tag'] . ' | ' . $it['title'] . ' | ' . $it['problem'] . ' | ' . $it['principle'] . ' | ' . $it['benefit'] . "\n";
		}
		echo '<table class="form-table" role="presentation"><tbody><tr><th scope="row"><label for="liw-li-usecases">' . esc_html__( 'Felder (Tag | Titel | Problem | Prinzip | Nutzen)', 'liebherr-interface-world' ) . '</label></th><td>';
		printf( '<textarea id="liw-li-usecases" name="liw_li_usecases" rows="8" class="large-text code">%s</textarea>', esc_textarea( trim( $uc ) ) );
		echo '</td></tr></tbody></table>';

		// Modul 9 – Bridge.
		self::group( __( 'Modul 9 – Interface Solutions (Brücke)', 'liebherr-interface-world' ) );
		self::text( 'bridge][title', __( 'Überschrift', 'liebherr-interface-world' ), (string) $d['bridge']['title'] );
		self::area( 'bridge][intro', __( 'Einleitung', 'liebherr-interface-world' ), (string) $d['bridge']['intro'] );
		self::plain_area( 'bridge_points', __( 'Punkte (eins je Zeile)', 'liebherr-interface-world' ), (array) $d['bridge']['points'] );
		self::text( 'bridge][cta_label', __( 'CTA-Text', 'liebherr-interface-world' ), (string) $d['bridge']['cta_label'] );

		self::title_intro_list( __( 'Modul 10 – Rollout', 'liebherr-interface-world' ), 'rollout', 'steps', $d['rollout'] );

		// Modul 11 – Contact.
		self::group( __( 'Modul 11 – Abschluss & Kontakt', 'liebherr-interface-world' ) );
		self::text( 'contact][title', __( 'Überschrift', 'liebherr-interface-world' ), (string) $d['contact']['title'] );
		self::area( 'contact][intro', __( 'Einleitung', 'liebherr-interface-world' ), (string) $d['contact']['intro'] );
		self::text( 'contact][cta_primary_label', __( 'CTA primär', 'liebherr-interface-world' ), (string) $d['contact']['cta_primary_label'] );
		self::text( 'contact][cta_secondary_label', __( 'CTA sekundär', 'liebherr-interface-world' ), (string) $d['contact']['cta_secondary_label'] );

		submit_button( __( 'Local-Intelligence-Inhalte speichern', 'liebherr-interface-world' ) );
		echo '</form></div>';
	}

	// ---- Render-Helfer -------------------------------------------------

	private static function group( string $label ): void {
		echo '<h2 class="title">' . esc_html( $label ) . '</h2>';
	}

	private static function text( string $name, string $label, string $value ): void {
		$id = 'liw-li-' . sanitize_html_class( str_replace( [ '[', ']' ], '-', $name ) );
		echo '<table class="form-table" role="presentation"><tbody><tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
		printf( '<input type="text" id="%1$s" name="liw_li[%2$s]" value="%3$s" class="large-text" />', esc_attr( $id ), esc_attr( $name ), esc_attr( $value ) );
		echo '</td></tr></tbody></table>';
	}

	private static function area( string $name, string $label, string $value ): void {
		$id = 'liw-li-' . sanitize_html_class( str_replace( [ '[', ']' ], '-', $name ) );
		echo '<table class="form-table" role="presentation"><tbody><tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
		printf( '<textarea id="%1$s" name="liw_li[%2$s]" rows="3" class="large-text">%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( $value ) );
		echo '</td></tr></tbody></table>';
	}

	/** @param array<int,array{title:string,text:string}> $items */
	private static function list_area( string $key, string $label, array $items ): void {
		$lines = '';
		foreach ( $items as $it ) {
			$lines .= $it['title'] . ( '' !== ( $it['text'] ?? '' ) ? ' | ' . $it['text'] : '' ) . "\n";
		}
		echo '<table class="form-table" role="presentation"><tbody><tr><th scope="row"><label for="liw-li-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		printf( '<textarea id="liw-li-%1$s" name="liw_li_%1$s" rows="6" class="large-text code">%2$s</textarea>', esc_attr( $key ), esc_textarea( trim( $lines ) ) );
		echo '</td></tr></tbody></table>';
	}

	/** @param array<int,string> $items */
	private static function plain_area( string $key, string $label, array $items ): void {
		echo '<table class="form-table" role="presentation"><tbody><tr><th scope="row"><label for="liw-li-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		printf( '<textarea id="liw-li-%1$s" name="liw_li_%1$s" rows="6" class="large-text code">%2$s</textarea>', esc_attr( $key ), esc_textarea( implode( "\n", $items ) ) );
		echo '</td></tr></tbody></table>';
	}

	/** @param array<string,mixed> $data */
	private static function title_intro_list( string $group, string $modkey, string $listkey, array $data ): void {
		self::group( $group );
		self::text( $modkey . '][title', __( 'Überschrift', 'liebherr-interface-world' ), (string) $data['title'] );
		self::area( $modkey . '][intro', __( 'Einleitung', 'liebherr-interface-world' ), (string) $data['intro'] );
		self::list_area( $modkey . '_' . $listkey, __( 'Einträge (Titel | Text)', 'liebherr-interface-world' ), (array) $data[ $listkey ] );
	}

	// ---- Speichern -----------------------------------------------------

	/** @return array{class:string,message:string}|null */
	private static function maybe_handle_submit(): ?array {
		if ( ! isset( $_POST['liw_action'] ) || 'save_li' !== $_POST['liw_action'] ) {
			return null;
		}
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		if ( ! current_user_can( RoleBridge::CAP_MANAGE_CONTENT ) ) {
			return [ 'class' => 'notice-error', 'message' => __( 'Keine Berechtigung.', 'liebherr-interface-world' ) ];
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Content::sanitize() bereinigt jedes Feld.
		$raw = wp_unslash( $_POST['liw_li'] ?? [] );
		$raw = is_array( $raw ) ? $raw : [];

		// Listen (Titel | Text) einhängen.
		$raw['vision']['fields']  = self::parse_pairs( self::post_line( 'liw_li_vision_fields' ) );
		$raw['flow']['steps']     = self::parse_pairs( self::post_line( 'liw_li_flow_steps' ) );
		$raw['trust']['areas']    = self::parse_pairs( self::post_line( 'liw_li_trust_areas' ) );
		$raw['rollout']['steps']  = self::parse_pairs( self::post_line( 'liw_li_rollout_steps' ) );
		$raw['knowledge']['points'] = self::parse_pairs( self::post_line( 'liw_li_knowledge_points' ) );

		// Einfache Listen (eins je Zeile).
		$raw['global']['devices'] = self::parse_single( self::post_line( 'liw_li_global_devices' ) );
		$raw['bridge']['points']  = self::parse_single( self::post_line( 'liw_li_bridge_points' ) );

		// Einsatzfelder (Tag | Titel | Problem | Prinzip | Nutzen).
		$raw['usecases']['items'] = self::parse_usecases( self::post_line( 'liw_li_usecases' ) );

		$after = Content::save( $raw );

		if ( AuditBridge::is_available() ) {
			AuditBridge::log( 'update', 'local_intelligence', 0, [], [ 'usecases' => count( $after['usecases']['items'] ) ], get_current_user_id() );
		}

		return [ 'class' => 'notice-success', 'message' => __( 'Local-Intelligence-Inhalte gespeichert.', 'liebherr-interface-world' ) ];
	}

	private static function post_line( string $key ): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- zeilenweise geparst + Content::sanitize().
		$v = wp_unslash( $_POST[ $key ] ?? '' );
		return is_string( $v ) ? $v : '';
	}

	/** @return array<int,array{title:string,text:string}> */
	private static function parse_pairs( string $raw ): array {
		$out = [];
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) { continue; }
			$p = explode( '|', $line, 2 );
			$out[] = [ 'title' => trim( $p[0] ), 'text' => isset( $p[1] ) ? trim( $p[1] ) : '' ];
		}
		return $out;
	}

	/** @return array<int,string> */
	private static function parse_single( string $raw ): array {
		$out = [];
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( (string) $line );
			if ( '' !== $line ) { $out[] = $line; }
		}
		return $out;
	}

	/** @return array<int,array<string,string>> */
	private static function parse_usecases( string $raw ): array {
		$out = [];
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) { continue; }
			$p = array_map( 'trim', explode( '|', $line ) );
			$out[] = [
				'tag'       => $p[0] ?? '',
				'title'     => $p[1] ?? '',
				'problem'   => $p[2] ?? '',
				'principle' => $p[3] ?? '',
				'benefit'   => $p[4] ?? '',
			];
		}
		return $out;
	}
}
