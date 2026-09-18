<?php
/**
 * Liebherr Interface Solutions – Onboarding-Formular (Frontend)
 *
 * Öffentlicher Shortcode `[liw_onboarding_form]` (§22) + admin-post-Handler für die
 * Verarbeitung (funktioniert für eingeloggte UND anonyme Besucher, `admin_post_nopriv_*`).
 * Reines semantisches HTML, kein Inline-CSS/JS (CLAUDE.md Frontend-Abschnitt) – Gestaltung
 * obliegt dem zentralen Design System des Themes über die `liw-*`-Klassen.
 *
 * ANNAHME-LIW-5 (Annahmen-Protokoll): Das unsichtbare Honeypot-Feld setzt voraus, dass das
 * Theme/Design System eine Utility-Klasse `.liw-visually-hidden` bereitstellt (Standard-
 * Screenreader-/Spam-Schutz-Pattern: `display:none` bzw. Off-Screen-Positionierung). Ohne
 * diese Klasse ist das Feld sichtbar, aber die Formularfunktion bleibt unbeeinträchtigt –
 * es wäre dann nur kein wirksamer Spam-Schutz. Bitte im Design System ergänzen, falls noch
 * nicht vorhanden.
 *
 * @package Liebherr\InterfaceWorld\Onboarding
 * @since   0.1.0-alpha.6
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Onboarding;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class OnboardingForm {

	private const SHORTCODE     = 'liw_onboarding_form';
	private const NONCE_ACTION  = 'liw_onboarding_submit';
	private const NONCE_NAME    = 'liw_onboarding_nonce';
	private const HONEYPOT_NAME = 'liw_hp_website';
	private const POST_ACTION   = 'liw_submit_onboarding';

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'render_shortcode' ] );
		add_action( 'admin_post_' . self::POST_ACTION, [ self::class, 'handle_submit' ] );
		add_action( 'admin_post_nopriv_' . self::POST_ACTION, [ self::class, 'handle_submit' ] );
	}

	public static function render_shortcode(): string {
		ob_start();

		$feedback = self::render_feedback_notice();
		if ( '' !== $feedback ) {
			echo $feedback; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bereits in render_feedback_notice() escaped.
		}

		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="liw-onboarding-form">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::POST_ACTION ); ?>" />
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
			<input type="hidden" name="liw_redirect" value="<?php echo esc_url( self::current_url() ); ?>" />

			<p class="liw-visually-hidden" aria-hidden="true">
				<label for="liw_hp_website"><?php esc_html_e( 'Bitte dieses Feld leer lassen', 'liebherr-interface-world' ); ?></label>
				<input type="text" id="liw_hp_website" name="<?php echo esc_attr( self::HONEYPOT_NAME ); ?>" tabindex="-1" autocomplete="off" />
			</p>

			<p>
				<label for="liw_ob_name"><?php esc_html_e( 'Name / Firma', 'liebherr-interface-world' ); ?> *</label><br />
				<input type="text" id="liw_ob_name" name="name" required />
			</p>
			<p>
				<label for="liw_ob_email"><?php esc_html_e( 'E-Mail', 'liebherr-interface-world' ); ?> *</label><br />
				<input type="email" id="liw_ob_email" name="contact_email" required />
			</p>
			<p>
				<label for="liw_ob_phone"><?php esc_html_e( 'Telefon', 'liebherr-interface-world' ); ?></label><br />
				<input type="tel" id="liw_ob_phone" name="contact_phone" />
			</p>
			<p>
				<label for="liw_ob_city"><?php esc_html_e( 'Ort', 'liebherr-interface-world' ); ?></label><br />
				<input type="text" id="liw_ob_city" name="city" />
			</p>
			<p>
				<label for="liw_ob_website"><?php esc_html_e( 'Website', 'liebherr-interface-world' ); ?></label><br />
				<input type="url" id="liw_ob_website" name="website_url" />
			</p>
			<p>
				<label for="liw_ob_type"><?php esc_html_e( 'Ich bin', 'liebherr-interface-world' ); ?> *</label><br />
				<select id="liw_ob_type" name="liw_partner_type" required>
					<option value="dealer"><?php esc_html_e( 'Händler', 'liebherr-interface-world' ); ?></option>
					<option value="supplier"><?php esc_html_e( 'Lieferant', 'liebherr-interface-world' ); ?></option>
					<option value="customer"><?php esc_html_e( 'Kunde', 'liebherr-interface-world' ); ?></option>
				</select>
			</p>
			<p>
				<label for="liw_ob_interfaces"><?php esc_html_e( 'Gewünschte Schnittstellen/Anbindung', 'liebherr-interface-world' ); ?></label><br />
				<textarea id="liw_ob_interfaces" name="requested_interfaces" rows="2"></textarea>
			</p>
			<p>
				<label for="liw_ob_message"><?php esc_html_e( 'Nachricht', 'liebherr-interface-world' ); ?></label><br />
				<textarea id="liw_ob_message" name="message" rows="4"></textarea>
			</p>
			<p>
				<label>
					<input type="checkbox" name="privacy_consent" value="1" required />
					<?php esc_html_e( 'Ich habe die Datenschutzhinweise gelesen und stimme der Verarbeitung meiner Angaben zur Bearbeitung dieser Anfrage zu.', 'liebherr-interface-world' ); ?> *
				</label>
			</p>
			<p>
				<label>
					<input type="checkbox" name="marketing_consent" value="1" />
					<?php esc_html_e( 'Ich möchte zusätzlich Informationen zu Interface World Connections erhalten (optional).', 'liebherr-interface-world' ); ?>
				</label>
			</p>
			<p>
				<button type="submit"><?php esc_html_e( 'Anfrage senden', 'liebherr-interface-world' ); ?></button>
			</p>
		</form>
		<?php

		return (string) ob_get_clean();
	}

	public static function handle_submit(): void {
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$redirect = isset( $_POST['liw_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['liw_redirect'] ) ) : home_url( '/' );

		// Honeypot: stiller Erfolg ohne Verarbeitung, um Bots nicht auf den Schutzmechanismus hinzuweisen.
		if ( ! empty( $_POST[ self::HONEYPOT_NAME ] ) ) {
			wp_safe_redirect( add_query_arg( 'liw_onboarding', 'success', $redirect ) );
			exit;
		}

		// Rate-Limit (SEC-004): zu viele Absendungen je IP → generische Fehlermeldung (SEC-010).
		if ( ! \Liebherr\InterfaceWorld\CoreBridge\RateLimitBridge::allow( 'onboarding' ) ) {
			wp_safe_redirect( add_query_arg( 'liw_onboarding', 'error', $redirect ) );
			exit;
		}

		$result = OnboardingService::submit_request( [
			'name'                 => wp_unslash( $_POST['name'] ?? '' ),
			'contact_email'        => wp_unslash( $_POST['contact_email'] ?? '' ),
			'contact_phone'        => wp_unslash( $_POST['contact_phone'] ?? '' ),
			'city'                 => wp_unslash( $_POST['city'] ?? '' ),
			'website_url'          => wp_unslash( $_POST['website_url'] ?? '' ),
			'liw_partner_type'     => wp_unslash( $_POST['liw_partner_type'] ?? '' ),
			'requested_interfaces' => wp_unslash( $_POST['requested_interfaces'] ?? '' ),
			'message'              => wp_unslash( $_POST['message'] ?? '' ),
			'privacy_consent'      => ! empty( $_POST['privacy_consent'] ),
			'marketing_consent'    => ! empty( $_POST['marketing_consent'] ),
		] );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'liw_onboarding', 'error', $redirect ) );
			exit;
		}

		wp_safe_redirect( add_query_arg( 'liw_onboarding', 'success', $redirect ) );
		exit;
	}

	private static function render_feedback_notice(): string {
		if ( ! isset( $_GET['liw_onboarding'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reine Anzeigelogik nach Redirect, keine Datenänderung.
			return '';
		}

		$state = sanitize_key( wp_unslash( $_GET['liw_onboarding'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'success' === $state ) {
			return '<p class="liw-onboarding-notice liw-onboarding-notice--success">' . esc_html__( 'Danke für Ihre Anfrage! Wir melden uns zeitnah bei Ihnen.', 'liebherr-interface-world' ) . '</p>';
		}

		if ( 'error' === $state ) {
			return '<p class="liw-onboarding-notice liw-onboarding-notice--error">' . esc_html__( 'Ihre Anfrage konnte nicht übermittelt werden. Bitte prüfen Sie Ihre Angaben und versuchen Sie es erneut.', 'liebherr-interface-world' ) . '</p>';
		}

		return '';
	}

	/** Aktuelle URL ohne bestehende `liw_onboarding`-Statusparameter (Standard-WP-Idiom). */
	private static function current_url(): string {
		$url = add_query_arg( null, null ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- liest nur REQUEST_URI, keine Formularverarbeitung.
		$url = remove_query_arg( 'liw_onboarding', $url );
		return home_url( $url );
	}
}
