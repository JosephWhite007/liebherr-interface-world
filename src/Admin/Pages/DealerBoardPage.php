<?php
/**
 * Liebherr Interface Solutions – Dealer Board (Backoffice, Stufe 1d).
 *
 * Erfassung der Händler, die an die Liebherr Dealer Interface API angebunden werden (Entscheidung JW 24.10.2026:
 * Plattform für viele Händler weltweit, Schnittstellen je Händler getrennt). Je Händler: Kürzel, Name, Land, Umgebung,
 * SalesAccountNumber, UserEmail, API-Key (nur schreiben – Anzeige „hinterlegt/fehlt"), Schreibschalter, Status.
 * Auswahl des aktiven Händlers für Probe-/Schreibskripte. Capability `liw_manage_interfaces`, Nonce, Audit.
 * Letzter Block = Anleitung (Grundannahme §1.15).
 *
 * @package Liebherr\InterfaceWorld\Admin\Pages
 * @since   0.1.0-alpha.148
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin\Pages;

use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;
use Liebherr\InterfaceWorld\CoreBridge\SecretBridge;
use Liebherr\InterfaceWorld\SalesInterface\DealerApiClient;
use Liebherr\InterfaceWorld\SalesInterface\DealerRepository;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class DealerBoardPage {

	public const MENU_SLUG = 'liw-dealer-board';

	private const NONCE_ACTION = 'liw_dealer_board';
	private const NONCE_NAME   = 'liw_dealer_board_nonce';

	public static function render(): void {
		if ( ! current_user_can( RoleBridge::CAP_MANAGE_INTERFACES ) ) {
			wp_die( esc_html__( 'Keine Berechtigung für das Dealer Board.', 'liebherr-interface-world' ) );
		}
		$notice = self::maybe_handle_submit();
		DealerRepository::ensure_default();

		echo '<div class="wrap"><h1>' . esc_html__( 'Dealer Board – Händler & Schnittstellen-Zugang', 'liebherr-interface-world' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Ein Datensatz je Händler: eigene Zugangsdaten zur Liebherr Dealer Interface API, eigene SalesAccountNumber, eigener Schreibschalter. API-Keys werden verschlüsselt gespeichert und nie angezeigt.', 'liebherr-interface-world' ) . '</p>';
		if ( null !== $notice ) {
			printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr( $notice['class'] ), esc_html( $notice['message'] ) );
		}
		if ( ! SecretBridge::is_available() ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Verschlüsselung nicht verfügbar (ARALIYA_ENCRYPTION_MASTER_KEY fehlt): API-Keys können nicht gespeichert werden.', 'liebherr-interface-world' ) . '</p></div>';
		}

		self::render_table();
		$edit = isset( $_GET['dealer'] ) ? sanitize_key( wp_unslash( (string) $_GET['dealer'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur Auswahl des Formulars.
		self::render_form( '' !== $edit ? DealerRepository::find( $edit ) : null );
		self::render_guide();
		echo '</div>';
	}

	private static function render_table(): void {
		$active = DealerRepository::active_code();
		$labels = SalesBoardPage::class;
		echo '<h2>' . esc_html__( 'Händler', 'liebherr-interface-world' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Kürzel', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Name', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Land', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Umgebung', 'liebherr-interface-world' ) . '</th><th>SalesAccountNumber</th><th>UserEmail</th><th>API-Key</th><th>' . esc_html__( 'Schreiben', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Status', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Verbindung', 'liebherr-interface-world' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( DealerRepository::all() as $d ) {
			$ready = DealerApiClient::readiness( (string) $d['code'], false );
			$creds = DealerRepository::credentials( (string) $d['code'] );
			$key   = $d['has_key'] ? __( 'hinterlegt', 'liebherr-interface-world' ) : ( '' !== (string) ( $creds['key'] ?? '' ) ? __( 'aus Env', 'liebherr-interface-world' ) : __( 'fehlt', 'liebherr-interface-world' ) );
			echo '<tr' . ( $active === $d['code'] ? ' class="liw-active"' : '' ) . '><td><code>' . esc_html( (string) $d['code'] ) . '</code>' . ( $active === $d['code'] ? ' ★' : '' ) . '</td>'
				. '<td>' . esc_html( (string) $d['name'] ) . '</td><td>' . esc_html( (string) $d['country'] ) . '</td><td>' . esc_html( (string) $d['environment'] ) . '</td>'
				. '<td>' . esc_html( '' !== (string) $d['sales_account_number'] ? (string) $d['sales_account_number'] : '–' ) . '</td>'
				. '<td>' . esc_html( '' !== (string) $d['user_email'] ? (string) $d['user_email'] : ( '' !== (string) ( $creds['user_email'] ?? '' ) ? __( 'aus Env', 'liebherr-interface-world' ) : '–' ) ) . '</td>'
				. '<td>' . esc_html( $key ) . '</td><td>' . esc_html( $d['write_enabled'] ? __( 'AN', 'liebherr-interface-world' ) : __( 'aus', 'liebherr-interface-world' ) ) . '</td>'
				. '<td>' . esc_html( (string) $d['status'] ) . '</td><td>' . esc_html( $ready['ok'] ? __( 'bereit', 'liebherr-interface-world' ) : $ready['reason'] ) . '</td>'
				. '<td><a href="' . esc_url( add_query_arg( [ 'page' => self::MENU_SLUG, 'dealer' => $d['code'] ], admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Bearbeiten', 'liebherr-interface-world' ) . '</a>';
			if ( $active !== $d['code'] ) {
				echo ' · <form method="post" class="liw-inline">';
				wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
				echo '<input type="hidden" name="liw_action" value="set_active" /><input type="hidden" name="code" value="' . esc_attr( (string) $d['code'] ) . '" /><button type="submit" class="button-link">' . esc_html__( 'Aktiv setzen', 'liebherr-interface-world' ) . '</button></form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		unset( $labels );
		$w = DealerApiClient::last_write();
		echo '<p class="description">' . esc_html__( 'Letzter Schreibaufruf:', 'liebherr-interface-world' ) . ' ' . esc_html( null === $w ? __( 'noch keiner', 'liebherr-interface-world' ) : sprintf( '%s · %s · %s %s · %s (HTTP %d)', wp_date( 'd.m.Y H:i', (int) $w['at'] ), (string) $w['dealer'], (string) $w['method'], (string) $w['endpoint'], ! empty( $w['ok'] ) ? 'OK' : 'Fehler ' . (string) $w['error'], (int) $w['status'] ) ) . '</p>';
	}

	/** @param array<string,mixed>|null $d */
	private static function render_form( ?array $d ): void {
		$is_edit = null !== $d;
		echo '<h2>' . esc_html( $is_edit ? sprintf( /* translators: %s: Händlername */ __( 'Händler bearbeiten: %s', 'liebherr-interface-world' ), (string) $d['name'] ) : __( 'Händler anlegen', 'liebherr-interface-world' ) ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ) . '">';
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		echo '<input type="hidden" name="liw_action" value="save_dealer" /><table class="form-table" role="presentation"><tbody>';
		$f = static function ( string $name, string $label, string $value, string $type = 'text', string $hint = '', bool $readonly = false ): void {
			printf( '<tr><th scope="row"><label for="liw-dealer-%1$s">%2$s</label></th><td><input type="%4$s" id="liw-dealer-%1$s" name="dealer[%1$s]" class="regular-text" value="%3$s"%6$s autocomplete="off" />%5$s</td></tr>', esc_attr( $name ), esc_html( $label ), esc_attr( $value ), esc_attr( $type ), '' !== $hint ? '<p class="description">' . esc_html( $hint ) . '</p>' : '', $readonly ? ' readonly' : '' );
		};
		$f( 'code', __( 'Kürzel (Slug)', 'liebherr-interface-world' ), (string) ( $d['code'] ?? '' ), 'text', __( 'Eindeutig, z. B. tradler. Nach dem Anlegen nicht änderbar.', 'liebherr-interface-world' ), $is_edit );
		$f( 'name', __( 'Name', 'liebherr-interface-world' ), (string) ( $d['name'] ?? '' ) );
		$f( 'country', __( 'Land (ISO2)', 'liebherr-interface-world' ), (string) ( $d['country'] ?? '' ) );
		$f( 'org_id', __( 'Addressbook-Mandant (Org-ID)', 'liebherr-interface-world' ), (string) ( $d['org_id'] ?? '0' ), 'number', __( 'ID der IAM-Organisation (Core → Addressbook → Team & Einstellungen); 0 = nicht zugeordnet. Nötig für Dealer Workspace und Livision-Sync.', 'liebherr-interface-world' ) );
		printf( '<tr><th scope="row"><label for="liw-dealer-environment">%s</label></th><td><select id="liw-dealer-environment" name="dealer[environment]">', esc_html__( 'Umgebung', 'liebherr-interface-world' ) );
		foreach ( DealerRepository::ENVIRONMENTS as $env ) {
			printf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $env ), selected( $d['environment'] ?? 'test', $env, false ) );
		}
		echo '</select><p class="description">' . esc_html__( 'In Stufe 1 spricht der Client nur „test" an; „production" wird erfasst, aber nicht aufgerufen.', 'liebherr-interface-world' ) . '</p></td></tr>';
		$f( 'sales_account_number', 'SalesAccountNumber', (string) ( $d['sales_account_number'] ?? '' ), 'text', __( 'Pflicht für POST/PUT /customer (Händlerkonto in Livision).', 'liebherr-interface-world' ) );
		$f( 'user_email', 'UserEmail', (string) ( $d['user_email'] ?? '' ), 'email', __( 'Header „UserEmail" – aufrufender Benutzer laut Liebherr.', 'liebherr-interface-world' ) );
		$f( 'api_key', __( 'API-Key (Subscription-Key)', 'liebherr-interface-world' ), '', 'password', $is_edit && ! empty( $d['has_key'] ) ? __( 'Hinterlegt. Leer lassen = behalten · „-" = löschen.', 'liebherr-interface-world' ) : __( 'Wird verschlüsselt gespeichert und nie angezeigt.', 'liebherr-interface-world' ) );
		printf( '<tr><th scope="row">%s</th><td><label><input type="checkbox" name="dealer[write_enabled]" value="1"%s /> %s</label><p class="description">%s</p></td></tr>', esc_html__( 'Schreibschalter', 'liebherr-interface-world' ), checked( ! empty( $d['write_enabled'] ), true, false ), esc_html__( 'Schreibaufrufe (Kunde/Kontakt anlegen, ändern) für diesen Händler erlauben', 'liebherr-interface-world' ), esc_html__( 'Standard AUS. Nur für Testhändler mit Sunnyside-Konvention einschalten.', 'liebherr-interface-world' ) );
		printf( '<tr><th scope="row"><label for="liw-dealer-status">%s</label></th><td><select id="liw-dealer-status" name="dealer[status]">', esc_html__( 'Status', 'liebherr-interface-world' ) );
		foreach ( DealerRepository::STATUSES as $st ) {
			printf( '<option value="%1$s"%2$s>%1$s</option>', esc_attr( $st ), selected( $d['status'] ?? 'active', $st, false ) );
		}
		echo '</select></td></tr>';
		printf( '<tr><th scope="row"><label for="liw-dealer-notes">%s</label></th><td><textarea id="liw-dealer-notes" name="dealer[notes]" rows="3" class="large-text">%s</textarea></td></tr>', esc_html__( 'Notizen', 'liebherr-interface-world' ), esc_textarea( (string) ( $d['notes'] ?? '' ) ) );
		echo '</tbody></table>';
		submit_button( $is_edit ? __( 'Händler speichern', 'liebherr-interface-world' ) : __( 'Händler anlegen', 'liebherr-interface-world' ) );
		echo '</form>';
	}

	private static function render_guide(): void {
		echo '<h2>' . esc_html__( 'Anleitung', 'liebherr-interface-world' ) . '</h2><div class="card">';
		echo '<p>' . esc_html__( 'Zweck: Die Plattform bindet viele Händler an die Liebherr Dealer Interface API an. Jeder Händler hat eigene Zugangsdaten und eine eigene SalesAccountNumber; Schnittstellen werden je Händler getrennt bearbeitet.', 'liebherr-interface-world' ) . '</p>';
		echo '<p>' . esc_html__( 'Ablauf: Händler anlegen → SalesAccountNumber, UserEmail und API-Key eintragen → „Aktiv setzen" → Probe im Terminal (scripts/liw-livision-probe.php --dealer=<kürzel> --confirm). Für Schreibtests zusätzlich den Schreibschalter einschalten; scripts/liw-livision-write.php zeigt ohne --confirm den Trockenlauf.', 'liebherr-interface-world' ) . '</p>';
		echo '<p>' . esc_html__( 'Testdaten-Konvention: Testkunden heißen „Sunnyside One/Two/…" mit „Island …"-Adressen, Ansprechpartner Alice (w) oder Bob (m). Der Client lehnt Kunden ohne „Sunnyside"-Namen ab. Der Händler „tradler" nutzt die Env-Zugangsdaten, solange hier nichts eingetragen ist.', 'liebherr-interface-world' ) . '</p></div>';
	}

	/** @return array{class:string,message:string}|null */
	private static function maybe_handle_submit(): ?array {
		$action = isset( $_POST['liw_action'] ) ? sanitize_key( wp_unslash( (string) $_POST['liw_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce unten geprüft.
		if ( '' === $action ) {
			return null;
		}
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );
		if ( ! current_user_can( RoleBridge::CAP_MANAGE_INTERFACES ) ) {
			return [ 'class' => 'notice-error', 'message' => __( 'Keine Berechtigung.', 'liebherr-interface-world' ) ];
		}
		if ( 'set_active' === $action ) {
			$ok = DealerRepository::set_active( sanitize_key( wp_unslash( (string) ( $_POST['code'] ?? '' ) ) ), get_current_user_id() );
			return [ 'class' => $ok ? 'notice-success' : 'notice-error', 'message' => $ok ? __( 'Aktiver Händler gesetzt.', 'liebherr-interface-world' ) : __( 'Händler unbekannt.', 'liebherr-interface-world' ) ];
		}
		if ( 'save_dealer' === $action ) {
			$raw = wp_unslash( $_POST['dealer'] ?? [] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- DealerRepository::save() sanitisiert jedes Feld.
			if ( ! is_array( $raw ) ) {
				return [ 'class' => 'notice-error', 'message' => __( 'Ungültige Daten.', 'liebherr-interface-world' ) ];
			}
			$result = DealerRepository::save( $raw, get_current_user_id() );
			if ( $result instanceof \WP_Error ) {
				return [ 'class' => 'notice-error', 'message' => $result->get_error_message() ];
			}
			return [ 'class' => 'notice-success', 'message' => __( 'Händler gespeichert.', 'liebherr-interface-world' ) ];
		}
		return null;
	}
}
