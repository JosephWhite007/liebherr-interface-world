<?php
/**
 * Liebherr Intelligence World – Backoffice-Pflege-Board (Job A1, Pflichtenheft-2 §19).
 *
 * Administrierbare Pflege statt reiner Optionen: (1) Tarife/Budgets & Eintrittstexte (Option `liw_iw_world`,
 * {@see WorldContent}) und (2) der Katalog „Navigation & Hotels" – 13 Produktsegmente, Lösungswelt, 6 Hotels
 * je mit Drei-Wörter-Ort (Option `liw_iw_catalog`, {@see CatalogContent}). Speichern läuft über admin-post mit
 * Nonce + Capability; die eigentliche Bereinigung übernehmen die `sanitize()`/`save()` der Content-Klassen.
 *
 * `save_from_request()` ist von der HTTP-Schicht getrennt (nimmt ein reines Array) und damit unit-testbar.
 *
 * @package Liebherr\InterfaceWorld\Admin\Pages
 * @since   0.1.0-alpha.62
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin\Pages;

use Liebherr\InterfaceWorld\IntelligenceWorld\CatalogContent;
use Liebherr\InterfaceWorld\IntelligenceWorld\WorldContent;
use Liebherr\InterfaceWorld\IntelligenceWorld\Money;
use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class IntelligenceWorldBoardPage {

	public const MENU_SLUG = 'liw-iw-board';
	private const ACTION   = 'liw_iw_board_save';
	private const NONCE    = 'liw_iw_board';

	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, [ self::class, 'handle_save' ] );
	}

	/** admin-post-Handler: Nonce + Capability prüfen, speichern, zurückleiten. */
	public static function handle_save(): void {
		if ( ! current_user_can( RoleBridge::CAP_MANAGE_CONTENT ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'liebherr-interface-world' ) );
		}
		check_admin_referer( self::NONCE );
		self::save_from_request( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitized -- sanitize in Content-Klassen.
		wp_safe_redirect( add_query_arg( [ 'page' => self::MENU_SLUG, 'updated' => '1' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Übernimmt Tarife/Texte (WorldContent) und Katalog (CatalogContent) aus einem rohen Request-Array.
	 * Checkboxen werden explizit auf 0/1 normalisiert, damit Abwählen greift.
	 *
	 * @param array<string,mixed> $req
	 * @return array{world:array<string,mixed>,catalog:array<string,mixed>}
	 */
	public static function save_from_request( array $req ): array {
		$world = [];
		foreach ( [ 'landing', 'gate', 'pricing' ] as $group ) {
			if ( isset( $req[ $group ] ) && is_array( $req[ $group ] ) ) {
				$world[ $group ] = $req[ $group ];
			}
		}
		if ( isset( $world['pricing'] ) && is_array( $world['pricing'] ) ) {
			$world['pricing']['storage_is_minimum'] = ! empty( $world['pricing']['storage_is_minimum'] ) ? 1 : 0;
		}
		$saved_world = WorldContent::save( $world );

		$catalog = [];
		foreach ( [ 'segments', 'solution', 'hotels' ] as $group ) {
			if ( isset( $req[ $group ] ) && is_array( $req[ $group ] ) ) {
				$catalog[ $group ] = $req[ $group ];
			}
		}
		$saved_catalog = CatalogContent::save( $catalog );

		return [ 'world' => $saved_world, 'catalog' => $saved_catalog ];
	}

	public static function render(): void {
		if ( ! current_user_can( RoleBridge::CAP_MANAGE_CONTENT ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'liebherr-interface-world' ) );
		}
		$w   = WorldContent::get();
		$cat = CatalogContent::get();
		$cur = (string) $w['pricing']['currency'];

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Intelligence World – Pflege (Tarife & Navigation/Hotels)', 'liebherr-interface-world' ) . '</h1>';
		if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Gespeichert.', 'liebherr-interface-world' ) . '</p></div>';
		}
		echo '<p>' . esc_html__( 'Prototyp-Beispieldaten (§21). Preise/Budgets steuern Anzeige, Ticker und Abrechnung; die Katalogknoten erscheinen im Funktions-Hub der Intelligence World.', 'liebherr-interface-world' ) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" />';
		wp_nonce_field( self::NONCE );

		// ── Tarife / Budgets ──
		echo '<h2>' . esc_html__( 'Tarife & Budgets', 'liebherr-interface-world' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';
		self::text_row( 'pricing[currency]', __( 'Währung (3-stellig)', 'liebherr-interface-world' ), $cur );
		self::number_row( 'pricing[base_price_second_minor]', __( 'Preis je Sekunde (in Minor-Units, z. B. 9 = 0,09)', 'liebherr-interface-world' ), (int) $w['pricing']['base_price_second_minor'], __( 'Aktuell:', 'liebherr-interface-world' ) . ' ' . Money::format( (int) $w['pricing']['base_price_second_minor'], $cur ) . ' / ' . WorldContent::unit_label( (string) ( $w['pricing']['price_unit'] ?? 'second' ) ) );
		self::select_row( 'pricing[price_unit]', __( 'Abrechnungstakt', 'liebherr-interface-world' ), [ 'second' => WorldContent::unit_label( 'second' ), 'minute' => WorldContent::unit_label( 'minute' ), 'hour' => WorldContent::unit_label( 'hour' ) ], (string) ( $w['pricing']['price_unit'] ?? 'second' ) );
		self::number_row( 'pricing[session_budget_minor]', __( 'Budget (in Minor-Units, z. B. 500000 = 5.000,00)', 'liebherr-interface-world' ), (int) $w['pricing']['session_budget_minor'], __( 'Aktuell:', 'liebherr-interface-world' ) . ' ' . Money::format( (int) $w['pricing']['session_budget_minor'], $cur ) . ' / ' . WorldContent::period_label( (string) ( $w['pricing']['budget_period'] ?? 'month' ) ) );
		self::select_row( 'pricing[budget_period]', __( 'Budget-Zeitraum', 'liebherr-interface-world' ), [ 'session' => WorldContent::period_label( 'session' ), 'day' => WorldContent::period_label( 'day' ), 'month' => WorldContent::period_label( 'month' ), 'year' => WorldContent::period_label( 'year' ) ], (string) ( $w['pricing']['budget_period'] ?? 'month' ) );
		self::number_row( 'pricing[storage_budget_mb]', __( 'Lokaler Speicher (in MB, z. B. 1048576 = 1 TB)', 'liebherr-interface-world' ), (int) $w['pricing']['storage_budget_mb'], __( 'Aktuell:', 'liebherr-interface-world' ) . ' ' . WorldContent::storage_label( (int) $w['pricing']['storage_budget_mb'], (bool) ( $w['pricing']['storage_is_minimum'] ?? false ) ) );
		self::checkbox_row( 'pricing[storage_is_minimum]', __( 'Speicher ist Mindestwert („min.")', 'liebherr-interface-world' ), (bool) ( $w['pricing']['storage_is_minimum'] ?? false ) );
		self::text_row( 'pricing[access_code]', __( 'Demo-Zugangscode', 'liebherr-interface-world' ), (string) $w['pricing']['access_code'] );
		echo '</tbody></table>';

		// ── Eintrittstexte (Auszug) ──
		echo '<h2>' . esc_html__( 'Eröffnungs-/Eintrittstexte', 'liebherr-interface-world' ) . '</h2>';
		echo '<table class="form-table" role="presentation"><tbody>';
		self::text_row( 'landing[headline]', __( 'Überschrift', 'liebherr-interface-world' ), (string) $w['landing']['headline'] );
		self::text_row( 'landing[subline]', __( 'Unterzeile', 'liebherr-interface-world' ), (string) $w['landing']['subline'] );
		self::text_row( 'landing[cta]', __( 'Eintritts-Schaltfläche', 'liebherr-interface-world' ), (string) $w['landing']['cta'] );
		self::text_row( 'gate[heading]', __( 'Überschrift Eintrittsschleuse', 'liebherr-interface-world' ), (string) $w['gate']['heading'] );
		echo '</tbody></table>';

		// ── Katalog: Lösungswelt + Segmente ──
		echo '<h2>' . esc_html__( 'Produktsegmente & Lösungswelt', 'liebherr-interface-world' ) . '</h2>';
		self::node_editor( 'solution', [ $cat['solution'] ], [ 'label' => __( 'Name', 'liebherr-interface-world' ), 'tagline' => __( 'Untertitel', 'liebherr-interface-world' ), 'blurb' => __( 'Beschreibung', 'liebherr-interface-world' ), 'three_words' => __( 'Ort (three words)', 'liebherr-interface-world' ) ], true );
		self::node_editor( 'segments', is_array( $cat['segments'] ) ? $cat['segments'] : [], [ 'label' => __( 'Name', 'liebherr-interface-world' ), 'tagline' => __( 'Untertitel', 'liebherr-interface-world' ), 'blurb' => __( 'Beschreibung', 'liebherr-interface-world' ), 'three_words' => __( 'Ort (three words)', 'liebherr-interface-world' ) ], false );

		// ── Katalog: Hotels ──
		echo '<h2>' . esc_html__( 'Hotelwelt', 'liebherr-interface-world' ) . '</h2>';
		self::node_editor( 'hotels', is_array( $cat['hotels'] ) ? $cat['hotels'] : [], [ 'name' => __( 'Name', 'liebherr-interface-world' ), 'place' => __( 'Standort', 'liebherr-interface-world' ), 'blurb' => __( 'Beschreibung', 'liebherr-interface-world' ), 'three_words' => __( 'Ort (three words)', 'liebherr-interface-world' ) ], false );

		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Speichern', 'liebherr-interface-world' ) . '</button></p>';
		echo '</form></div>';
	}

	// ── Feld-Helfer (alle Werte escaped) ─────────────────────────────────────

	private static function text_row( string $name, string $label, string $value ): void {
		echo '<tr><th scope="row"><label>' . esc_html( $label ) . '</label></th><td>';
		echo '<input type="text" class="regular-text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
		echo '</td></tr>';
	}

	private static function number_row( string $name, string $label, int $value, string $hint = '' ): void {
		echo '<tr><th scope="row"><label>' . esc_html( $label ) . '</label></th><td>';
		echo '<input type="number" step="1" min="0" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" />';
		if ( '' !== $hint ) { echo ' <span class="description">' . esc_html( $hint ) . '</span>'; }
		echo '</td></tr>';
	}

	private static function checkbox_row( string $name, string $label, bool $checked ): void {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		echo '<label><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"' . checked( $checked, true, false ) . ' /> ' . esc_html__( 'aktiv', 'liebherr-interface-world' ) . '</label>';
		echo '</td></tr>';
	}

	/** @param array<string,string> $options */
	private static function select_row( string $name, string $label, array $options, string $value ): void {
		echo '<tr><th scope="row"><label>' . esc_html( $label ) . '</label></th><td><select name="' . esc_attr( $name ) . '">';
		foreach ( $options as $val => $text ) {
			echo '<option value="' . esc_attr( (string) $val ) . '"' . selected( (string) $val, $value, false ) . '>' . esc_html( (string) $text ) . '</option>';
		}
		echo '</select></td></tr>';
	}

	/**
	 * Editor für eine Knotenliste (Segmente/Hotels/Lösungswelt). `$is_single` = eine feste Zeile (solution).
	 *
	 * @param array<int,array<string,mixed>> $nodes
	 * @param array<string,string>           $fields feldschlüssel => Label
	 */
	private static function node_editor( string $group, array $nodes, array $fields, bool $is_single ): void {
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( $fields as $lbl ) { echo '<th>' . esc_html( $lbl ) . '</th>'; }
		echo '</tr></thead><tbody>';
		$i = 0;
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) { continue; }
			$idx  = $is_single ? '' : (string) $i;
			$base = $is_single ? $group : $group . '[' . $idx . ']';
			echo '<tr>';
			// key als Hidden mitführen (stabile Zuordnung).
			echo '<input type="hidden" name="' . esc_attr( $base . '[key]' ) . '" value="' . esc_attr( (string) ( $node['key'] ?? '' ) ) . '" />';
			foreach ( $fields as $f => $lbl ) {
				$val  = (string) ( $node[ $f ] ?? '' );
				$name = $base . '[' . $f . ']';
				if ( 'blurb' === $f ) {
					echo '<td><textarea rows="2" style="width:100%" name="' . esc_attr( $name ) . '">' . esc_textarea( $val ) . '</textarea></td>';
				} else {
					echo '<td><input type="text" style="width:100%" name="' . esc_attr( $name ) . '" value="' . esc_attr( $val ) . '" /></td>';
				}
			}
			echo '</tr>';
			$i++;
		}
		echo '</tbody></table>';
	}
}
