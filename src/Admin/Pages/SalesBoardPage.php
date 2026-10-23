<?php
/**
 * Liebherr Interface Solutions – Sales Board (Backoffice).
 *
 * Pflegt die geschützte Unterseite hinter der Process-World-Karte „Sales" (ADR-LIW-SALES-001): Einleitung,
 * Authentifizierungstext, Umgebungen, Endpunkt-Gruppen, Endpunkte samt Status, Datenmodell, Codelisten,
 * Klärungspunkte, Stufenplan und Anleitung. Eingabe je Zeile „Spalte | Spalte | …" (Muster Components Board).
 * Zeigt den Seitenstatus (Seite/Schutz) und – ohne Werte – ob die Env-Schlüssel für Stufe 1c hinterlegt sind.
 * Capability `liw_manage_interfaces`, Nonce, Audit (SEC-005). Letzter Block = Anleitung (Grundannahme §1.15).
 *
 * @package Liebherr\InterfaceWorld\Admin\Pages
 * @since   0.1.0-alpha.146
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin\Pages;

use Liebherr\InterfaceWorld\CoreBridge\AuditBridge;
use Liebherr\InterfaceWorld\CoreBridge\GateBridge;
use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;
use Liebherr\InterfaceWorld\SalesInterface\CodeListService;
use Liebherr\InterfaceWorld\SalesInterface\DealerApiClient;
use Liebherr\InterfaceWorld\SalesInterface\SalesContent;
use Liebherr\InterfaceWorld\SalesInterface\SalesInterfaceView;
use Liebherr\InterfaceWorld\Settings\ComponentContent;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SalesBoardPage {

	public const MENU_SLUG = 'liw-sales-board';

	private const NONCE_ACTION = 'liw_sales_board_save';
	private const NONCE_NAME   = 'liw_sales_board_nonce';

	/** Env-Variablen für Stufe 1c (nur Status „hinterlegt/fehlt", nie der Wert). */
	private const ENV_KEYS = [ 'LIW_LIVISION_KEY_TEST', 'LIW_LIVISION_USER_EMAIL' ];

	/** @return array<string,string> Listen-Schlüssel → Beschriftung inkl. Eingabeformat. */
	private static function list_labels(): array {
		return [
			'environments'   => __( 'Umgebungen – Schlüssel | Bezeichnung | URL (https) | Hinweis', 'liebherr-interface-world' ),
			'groups'         => __( 'Endpunkt-Gruppen – Schlüssel | Bezeichnung | Beschreibung', 'liebherr-interface-world' ),
			'endpoints'      => __( 'Endpunkte – Gruppen-Schlüssel | Methode | Pfad | Zweck | Parameter | Status (draft, in_simulation, verified, approved)', 'liebherr-interface-world' ),
			'schemas'        => __( 'Datenmodell – Name | Pflichtfelder (Komma) | Felder („Feld:Typ; Feld:Typ") oder Kurztext', 'liebherr-interface-world' ),
			'codelists'      => __( 'Codelisten – Code | Bezeichnung | Entität', 'liebherr-interface-world' ),
			'clarifications' => __( 'Klärungspunkte – Titel | Text', 'liebherr-interface-world' ),
			'roadmap'        => __( 'Stufenplan – Titel | Text | Status (planned, in_progress, done)', 'liebherr-interface-world' ),
			'guide'          => __( 'Anleitung der Seite – Titel | Text', 'liebherr-interface-world' ),
		];
	}

	public static function render(): void {
		if ( ! current_user_can( RoleBridge::CAP_MANAGE_INTERFACES ) ) {
			wp_die( esc_html__( 'Keine Berechtigung für das Sales Board.', 'liebherr-interface-world' ) );
		}

		$notice = self::maybe_handle_submit();
		$data   = SalesContent::get();

		echo '<div class="wrap"><h1>' . esc_html__( 'Sales Board – Sales Interface', 'liebherr-interface-world' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Inhalte der geschützten Unterseite hinter der Karte „Sales" (Schnittstellenbeschreibung Dealer Interface API v3). Je Zeile: Spalten durch „|" getrennt. Eine leere Liste setzt die Standardwerte zurück.', 'liebherr-interface-world' ) . '</p>';

		if ( null !== $notice ) {
			printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr( $notice['class'] ), esc_html( $notice['message'] ) );
		}

		self::render_status();

		echo '<form method="post" action="">';
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		echo '<input type="hidden" name="liw_action" value="save_sales" />';
		echo '<table class="form-table" role="presentation"><tbody>';

		printf( '<tr><th scope="row"><label for="liw-sales-title">%s</label></th><td><input type="text" id="liw-sales-title" name="liw_sales[intro][title]" class="regular-text" value="%s" /></td></tr>', esc_html__( 'Seitentitel', 'liebherr-interface-world' ), esc_attr( (string) $data['intro']['title'] ) );
		printf( '<tr><th scope="row"><label for="liw-sales-lead">%s</label></th><td><textarea id="liw-sales-lead" name="liw_sales[intro][lead]" rows="3" class="large-text">%s</textarea></td></tr>', esc_html__( 'Einleitung', 'liebherr-interface-world' ), esc_textarea( (string) $data['intro']['lead'] ) );
		printf( '<tr><th scope="row"><label for="liw-sales-auth">%s</label></th><td><textarea id="liw-sales-auth" name="liw_sales[auth]" rows="4" class="large-text">%s</textarea><p class="description">%s</p></td></tr>', esc_html__( 'Authentifizierung (Text)', 'liebherr-interface-world' ), esc_textarea( (string) $data['auth'] ), esc_html__( 'Keine Schlüsselwerte eintragen – Schlüssel gehören ausschließlich in die Server-Umgebung.', 'liebherr-interface-world' ) );

		foreach ( self::list_labels() as $key => $label ) {
			printf(
				'<tr><th scope="row"><label for="liw-sales-%1$s">%2$s</label></th><td><textarea id="liw-sales-%1$s" name="liw_sales[%1$s]" rows="%3$d" class="large-text code">%4$s</textarea></td></tr>',
				esc_attr( $key ),
				esc_html( $label ),
				in_array( $key, [ 'endpoints', 'schemas' ], true ) ? 14 : 7,
				esc_textarea( SalesContent::format_lines( $key, $data[ $key ] ) )
			);
		}

		echo '</tbody></table>';
		submit_button( __( 'Sales Interface speichern', 'liebherr-interface-world' ) );
		echo '</form>';

		self::render_guide();
		echo '</div>';
	}

	private static function render_status(): void {
		$page_id = (int) get_option( SalesInterfaceView::OPTION_PAGE, 0 );
		$page    = $page_id > 0 ? get_post( $page_id ) : null;
		echo '<h2>' . esc_html__( 'Status', 'liebherr-interface-world' ) . '</h2><table class="widefat striped"><tbody>';

		if ( $page instanceof \WP_Post ) {
			$link = '<a href="' . esc_url( (string) get_permalink( $page ) ) . '" target="_blank" rel="noopener">' . esc_html( (string) get_permalink( $page ) ) . '</a>';
			echo '<tr><th>' . esc_html__( 'Seite', 'liebherr-interface-world' ) . '</th><td>' . wp_kses( $link, [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ] ) . '</td></tr>';
		} else {
			echo '<tr><th>' . esc_html__( 'Seite', 'liebherr-interface-world' ) . '</th><td>' . esc_html__( 'Noch nicht angelegt – Skript scripts/liw-seed-sales-interface.php --confirm ausführen.', 'liebherr-interface-world' ) . '</td></tr>';
		}

		$protected = $page instanceof \WP_Post && GateBridge::is_protected( $page->ID );
		echo '<tr><th>' . esc_html__( 'Zugangsschutz', 'liebherr-interface-world' ) . '</th><td>'
			. esc_html( $protected ? __( 'Aktiv: Core-Regel „mit Code" (Code und Umgebungsschalter unter ARALIYA → Zugangsschutz).', 'liebherr-interface-world' ) : __( 'Nicht gesetzt – Seite ist nicht geschützt.', 'liebherr-interface-world' ) ) . '</td></tr>';

		$card_ok = false;
		foreach ( ComponentContent::get()['process'] as $card ) {
			if ( ComponentContent::SALES_TARGET === ( $card['target'] ?? '' ) ) {
				$card_ok = true;
			}
		}
		echo '<tr><th>' . esc_html__( 'Karte „Sales"', 'liebherr-interface-world' ) . '</th><td>' . esc_html( $card_ok ? __( 'Verlinkt (Components Board).', 'liebherr-interface-world' ) : __( 'Ohne Link – im Components Board die Zielseite eintragen.', 'liebherr-interface-world' ) ) . '</td></tr>';

		foreach ( self::ENV_KEYS as $env ) {
			$value = getenv( $env );
			echo '<tr><th><code>' . esc_html( $env ) . '</code></th><td>'
				. esc_html( is_string( $value ) && '' !== $value ? __( 'hinterlegt (Wert wird nie angezeigt)', 'liebherr-interface-world' ) : __( 'fehlt – für die Testverbindung (Stufe 1c) nötig', 'liebherr-interface-world' ) ) . '</td></tr>';
		}

		// Stufe 1c: Testverbindung (nur lesend, nur Testumgebung) – Vorbedingungen und letzter Lauf, nie Nutzdaten.
		$ready = DealerApiClient::readiness();
		echo '<tr><th>' . esc_html__( 'Aktiver Händler', 'liebherr-interface-world' ) . '</th><td><code>' . esc_html( $ready['dealer'] ) . '</code> – <a href="' . esc_url( admin_url( 'admin.php?page=' . DealerBoardPage::MENU_SLUG ) ) . '">' . esc_html__( 'Dealer Board', 'liebherr-interface-world' ) . '</a></td></tr>';
		echo '<tr><th>' . esc_html__( 'Testverbindung', 'liebherr-interface-world' ) . '</th><td>'
			. esc_html( $ready['ok'] ? __( 'bereit – Probe: scripts/liw-livision-probe.php --confirm', 'liebherr-interface-world' ) : sprintf( /* translators: %s: Grund */ __( 'blockiert (%s)', 'liebherr-interface-world' ), self::reason_label( $ready['reason'] ) ) ) . '</td></tr>';
		$probe = DealerApiClient::last_probe();
		echo '<tr><th>' . esc_html__( 'Letzter Aufruf', 'liebherr-interface-world' ) . '</th><td>' . esc_html( self::probe_label( $probe ) ) . '</td></tr>';
		$imp = CodeListService::last_import();
		echo '<tr><th>' . esc_html__( 'Codelisten', 'liebherr-interface-world' ) . '</th><td>' . esc_html( null === $imp
			? __( 'nicht importiert – scripts/liw-import-livision-codelists.php --confirm', 'liebherr-interface-world' )
			: sprintf( /* translators: 1: Zeilen, 2: Listen, 3: Sprachen, 4: Datum, 5: Datei */ __( '%1$s Zeilen, %2$d Listen, %3$d Sprachen – importiert %4$s (%5$s)', 'liebherr-interface-world' ), number_format_i18n( CodeListService::total() ), (int) $imp['lists'], (int) $imp['languages'], wp_date( 'd.m.Y H:i', (int) $imp['at'] ), (string) $imp['file'] ) ) . '</td></tr>';
		echo '</tbody></table>';
	}

	private static function reason_label( string $reason ): string {
		$map = [
			'key_missing'        => __( 'Subscription-Key fehlt in der Umgebung', 'liebherr-interface-world' ),
			'user_email_missing' => __( 'UserEmail fehlt in der Umgebung', 'liebherr-interface-world' ),
			'instance_locked'    => __( 'Instanz nicht freigegeben (Klon/Not-Aus/Kill-Switch)', 'liebherr-interface-world' ),
			'dealer_unknown'     => __( 'Händler unbekannt', 'liebherr-interface-world' ),
			'dealer_inactive'    => __( 'Händler inaktiv', 'liebherr-interface-world' ),
			'environment_not_test' => __( 'Händler nicht auf Testumgebung', 'liebherr-interface-world' ),
			'write_disabled'     => __( 'Schreibschalter aus', 'liebherr-interface-world' ),
		];
		return $map[ $reason ] ?? $reason;
	}

	/** @param array<string,mixed>|null $probe */
	public static function probe_label( ?array $probe ): string {
		if ( null === $probe ) {
			return __( 'noch keiner', 'liebherr-interface-world' );
		}
		return sprintf(
			/* translators: 1: Datum, 2: Endpunkt, 3: Ergebnis, 4: HTTP-Status, 5: Dauer ms */
			__( '%1$s · GET %2$s · %3$s (HTTP %4$d, %5$d ms)', 'liebherr-interface-world' ),
			wp_date( 'd.m.Y H:i', (int) ( $probe['at'] ?? 0 ) ),
			( ! empty( $probe['dealer'] ) ? '[' . (string) $probe['dealer'] . '] ' : '' ) . (string) ( $probe['endpoint'] ?? '' ),
			! empty( $probe['ok'] ) ? __( 'OK', 'liebherr-interface-world' ) : __( 'Fehler', 'liebherr-interface-world' ) . ' ' . (string) ( $probe['error'] ?? '' ),
			(int) ( $probe['status'] ?? 0 ),
			(int) ( $probe['duration_ms'] ?? 0 )
		);
	}

	private static function render_guide(): void {
		echo '<h2>' . esc_html__( 'Anleitung', 'liebherr-interface-world' ) . '</h2><div class="card">';
		echo '<p>' . esc_html__( 'Zweck: Das Sales Board pflegt die Schnittstellenbeschreibung, die Tradler-Mitarbeiter hinter der Karte „Sales" (Interface Solutions → Process Worlds) mit Zugangscode lesen.', 'liebherr-interface-world' ) . '</p>';
		echo '<p>' . esc_html__( 'Ablauf: Inhalte hier ändern und speichern → Seite öffnen und prüfen. Endpunkte erhalten einen Status (Entwurf → In Simulation → Verifiziert → Freigegeben). Texte erscheinen in der Standardsprache; Übersetzungen laufen über das Language Board.', 'liebherr-interface-world' ) . '</p>';
		echo '<p>' . esc_html__( 'Schutz: Die Seite trägt die Core-Regel „mit Code" (Standardcode wie auf der übrigen Plattform). Code ändern oder Schutz je Umgebung schalten: ARALIYA-Zugangsschutz. Händlerindividuelle Codes sind für eine spätere Stufe vorgesehen.', 'liebherr-interface-world' ) . '</p>';
		echo '<p>' . esc_html__( 'Schlüssel: Subscription-Keys und die Absender-E-Mail gehören nur in die Server-Umgebung (LIW_LIVISION_KEY_TEST, LIW_LIVISION_USER_EMAIL), nie in dieses Formular. Die Testverbindung (Stufe 1c) ist lesend, nur gegen die Testumgebung und wird per Skript ausgelöst; Ergebnis steht oben unter „Letzter Aufruf“ und im Audit Board.', 'liebherr-interface-world' ) . '</p></div>';
	}

	/** @return array{class:string,message:string}|null */
	private static function maybe_handle_submit(): ?array {
		if ( ! isset( $_POST['liw_action'] ) || 'save_sales' !== $_POST['liw_action'] ) {
			return null;
		}
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		if ( ! current_user_can( RoleBridge::CAP_MANAGE_INTERFACES ) ) {
			return [ 'class' => 'notice-error', 'message' => __( 'Keine Berechtigung.', 'liebherr-interface-world' ) ];
		}

		$raw = wp_unslash( $_POST['liw_sales'] ?? [] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- zeilenweise geparst + SalesContent::sanitize().
		if ( ! is_array( $raw ) ) {
			return [ 'class' => 'notice-error', 'message' => __( 'Ungültige Daten.', 'liebherr-interface-world' ) ];
		}

		$parsed = [
			'intro' => is_array( $raw['intro'] ?? null ) ? $raw['intro'] : [],
			'auth'  => is_string( $raw['auth'] ?? null ) ? (string) $raw['auth'] : '',
		];
		foreach ( array_keys( SalesContent::LISTS ) as $key ) {
			$parsed[ $key ] = SalesContent::parse_lines( $key, is_string( $raw[ $key ] ?? null ) ? (string) $raw[ $key ] : '' );
		}

		$after = SalesContent::save( $parsed );

		if ( AuditBridge::is_available() ) {
			AuditBridge::log( 'update', 'sales_interface', 0, [], [ 'endpoints' => count( $after['endpoints'] ), 'schemas' => count( $after['schemas'] ), 'codelists' => count( $after['codelists'] ) ], get_current_user_id() );
		}

		return [ 'class' => 'notice-success', 'message' => __( 'Sales Interface gespeichert.', 'liebherr-interface-world' ) ];
	}
}
