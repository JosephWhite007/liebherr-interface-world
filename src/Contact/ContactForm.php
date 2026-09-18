<?php
/**
 * Liebherr Interface Solutions – Kontaktformular (Frontend, LP-13)
 *
 * Öffentlicher Shortcode `[liw_contact_form]` (Pflichtenheft §8 LP-13, §22 Feldliste) mit
 * admin-post-Handler für eingeloggte UND anonyme Besucher. Bewusst getrennt vom
 * Onboarding-Formular (§22: andere Zielgruppe – Zentrale, Technologiepartner, sonstige
 * Projektkontakte – und kein Partner-Datensatz). Gleiche Schutzmechanik wie `OnboardingForm`
 * (Nonce, Honeypot mit stillem Erfolg, Redirect mit Statusparameter), gleiche `liw-*`-Klassen
 * für das Design System – kein Inline-CSS/JS.
 *
 * Validierung und Speicherung liegen vollständig in `ContactService::submit_request()`; dieses
 * Formular ist nur Darstellung + Übergabe (AC-008: serverseitig validiert, gespeichert,
 * bestätigt).
 *
 * @package Liebherr\InterfaceWorld\Contact
 * @since   0.1.0-alpha.19
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Contact;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ContactForm {

	public const SHORTCODE = 'liw_contact_form';

	private const NONCE_ACTION  = 'liw_contact_submit';
	private const NONCE_NAME    = 'liw_contact_nonce';
	private const HONEYPOT_NAME = 'liw_hp_company_url';
	private const POST_ACTION   = 'liw_submit_contact';
	private const STATE_PARAM   = 'liw_contact';

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
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="liw-contact-form">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::POST_ACTION ); ?>" />
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
			<input type="hidden" name="liw_redirect" value="<?php echo esc_url( self::current_url() ); ?>" />

			<p class="liw-visually-hidden" aria-hidden="true">
				<label for="liw_hp_company_url"><?php esc_html_e( 'Bitte dieses Feld leer lassen', 'liebherr-interface-world' ); ?></label>
				<input type="text" id="liw_hp_company_url" name="<?php echo esc_attr( self::HONEYPOT_NAME ); ?>" tabindex="-1" autocomplete="off" />
			</p>

			<p>
				<label for="liw_ct_org"><?php esc_html_e( 'Organisation', 'liebherr-interface-world' ); ?> *</label><br />
				<input type="text" id="liw_ct_org" name="organisation" required />
			</p>
			<p>
				<label for="liw_ct_name"><?php esc_html_e( 'Kontaktperson', 'liebherr-interface-world' ); ?> *</label><br />
				<input type="text" id="liw_ct_name" name="contact_name" required />
			</p>
			<p>
				<label for="liw_ct_email"><?php esc_html_e( 'Geschäftliche E-Mail', 'liebherr-interface-world' ); ?> *</label><br />
				<input type="email" id="liw_ct_email" name="contact_email" required />
			</p>
			<p>
				<label for="liw_ct_phone"><?php esc_html_e( 'Telefon', 'liebherr-interface-world' ); ?></label><br />
				<input type="tel" id="liw_ct_phone" name="contact_phone" />
			</p>
			<p>
				<label for="liw_ct_region"><?php esc_html_e( 'Land/Region', 'liebherr-interface-world' ); ?> *</label><br />
				<select id="liw_ct_region" name="region" required>
					<option value=""><?php esc_html_e( 'Bitte wählen', 'liebherr-interface-world' ); ?></option>
					<?php foreach ( ContactService::regions() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label for="liw_ct_role"><?php esc_html_e( 'Rolle', 'liebherr-interface-world' ); ?> *</label><br />
				<select id="liw_ct_role" name="role" required>
					<option value=""><?php esc_html_e( 'Bitte wählen', 'liebherr-interface-world' ); ?></option>
					<?php foreach ( ContactService::ROLES as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p>
				<label for="liw_ct_system"><?php esc_html_e( 'Lokales ERP/CRM', 'liebherr-interface-world' ); ?></label><br />
				<input type="text" id="liw_ct_system" name="local_system" />
			</p>
			<fieldset class="liw-contact-form__interests">
				<legend><?php esc_html_e( 'Projektinteresse', 'liebherr-interface-world' ); ?> *</legend>
				<?php foreach ( ContactService::interests() as $key => $label ) : ?>
					<label class="liw-contact-form__interest">
						<input type="checkbox" name="interests[]" value="<?php echo esc_attr( $key ); ?>" />
						<?php echo esc_html( $label ); ?>
					</label>
				<?php endforeach; ?>
			</fieldset>
			<p>
				<label for="liw_ct_message"><?php esc_html_e( 'Nachricht', 'liebherr-interface-world' ); ?> *</label><br />
				<textarea id="liw_ct_message" name="message" rows="5" required></textarea>
			</p>
			<p>
				<label>
					<input type="checkbox" name="privacy_consent" value="1" required />
					<?php esc_html_e( 'Ich habe die Datenschutzhinweise gelesen und stimme der Verarbeitung meiner Angaben zur Bearbeitung dieser Projektanfrage zu.', 'liebherr-interface-world' ); ?> *
				</label>
			</p>
			<p>
				<label>
					<input type="checkbox" name="marketing_consent" value="1" />
					<?php esc_html_e( 'Ich möchte zusätzlich Informationen zu Interface World Connections erhalten (optional, jederzeit widerrufbar).', 'liebherr-interface-world' ); ?>
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

		// Honeypot: stiller Erfolg ohne Verarbeitung (Bots nicht auf den Schutz hinweisen).
		if ( ! empty( $_POST[ self::HONEYPOT_NAME ] ) ) {
			wp_safe_redirect( add_query_arg( self::STATE_PARAM, 'success', $redirect ) );
			exit;
		}

		// Rate-Limit (SEC-004): zu viele Absendungen je IP → generische Fehlermeldung (keine Details, SEC-010).
		if ( ! \Liebherr\InterfaceWorld\CoreBridge\RateLimitBridge::allow( 'contact' ) ) {
			wp_safe_redirect( add_query_arg( self::STATE_PARAM, 'error', $redirect ) );
			exit;
		}

		$interests = isset( $_POST['interests'] ) && is_array( $_POST['interests'] ) ? array_map( 'sanitize_key', wp_unslash( $_POST['interests'] ) ) : [];

		$result = ContactService::submit_request( [
			'organisation'      => wp_unslash( $_POST['organisation'] ?? '' ),
			'contact_name'      => wp_unslash( $_POST['contact_name'] ?? '' ),
			'contact_email'     => wp_unslash( $_POST['contact_email'] ?? '' ),
			'contact_phone'     => wp_unslash( $_POST['contact_phone'] ?? '' ),
			'region'            => wp_unslash( $_POST['region'] ?? '' ),
			'role'              => wp_unslash( $_POST['role'] ?? '' ),
			'local_system'      => wp_unslash( $_POST['local_system'] ?? '' ),
			'interests'         => $interests,
			'message'           => wp_unslash( $_POST['message'] ?? '' ),
			'privacy_consent'   => ! empty( $_POST['privacy_consent'] ),
			'marketing_consent' => ! empty( $_POST['marketing_consent'] ),
		] );

		wp_safe_redirect( add_query_arg( self::STATE_PARAM, is_wp_error( $result ) ? 'error' : 'success', $redirect ) );
		exit;
	}

	private static function render_feedback_notice(): string {
		if ( ! isset( $_GET[ self::STATE_PARAM ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reine Anzeigelogik nach Redirect.
			return '';
		}
		$state = sanitize_key( wp_unslash( $_GET[ self::STATE_PARAM ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'success' === $state ) {
			return '<p class="liw-onboarding-notice liw-onboarding-notice--success">' . esc_html__( 'Danke für Ihre Anfrage! Wir melden uns zeitnah bei Ihnen.', 'liebherr-interface-world' ) . '</p>';
		}
		if ( 'error' === $state ) {
			return '<p class="liw-onboarding-notice liw-onboarding-notice--error">' . esc_html__( 'Ihre Anfrage konnte nicht übermittelt werden. Bitte prüfen Sie die Pflichtfelder und versuchen Sie es erneut.', 'liebherr-interface-world' ) . '</p>';
		}
		return '';
	}

	private static function current_url(): string {
		$url = add_query_arg( null, null ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- liest nur REQUEST_URI.
		return home_url( remove_query_arg( self::STATE_PARAM, $url ) );
	}
}
