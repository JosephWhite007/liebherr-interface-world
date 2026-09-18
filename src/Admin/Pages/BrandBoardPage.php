<?php
/**
 * Liebherr Interface Solutions – Brand Board (Design-Tokens, §10–12).
 *
 * Administrierbares CI-Fundament: pflegt die zentralen Design-Tokens (`--brand-*`, Schriftstacks,
 * Radius, Inhaltsbreite, Logo) als Option. Bis zur dokumentierten Liebherr-Freigabe gelten neutrale
 * Fallbacks (CI-002, Markenschutz) – hier trägt die Redaktion nach Freigabe die Originalwerte ein
 * (docs/LIW_BRAND_TOKENS.md). Ausgabe am Frontend über `wp_add_inline_style()` (FrontendAssets).
 *
 * Capability: `liw_manage_content`. Nonce-geschützt, Änderungen werden auditiert (SEC-005).
 *
 * @package Liebherr\InterfaceWorld\Admin\Pages
 * @since   0.1.0-alpha.27
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin\Pages;

use Liebherr\InterfaceWorld\Branding\BrandTokens;
use Liebherr\InterfaceWorld\CoreBridge\AuditBridge;
use Liebherr\InterfaceWorld\CoreBridge\MediaBridge;
use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BrandBoardPage {

	public const MENU_SLUG = 'liw-brand-board';

	private const NONCE_ACTION = 'liw_brand_board_save';
	private const NONCE_NAME   = 'liw_brand_board_nonce';

	/** @var array<string,string> Farb-Token → Label. */
	private const COLOR_FIELDS = [
		'primary'   => 'Primärfarbe (--brand-primary)',
		'on_primary' => 'Text auf Primärfarbe (--brand-on-primary)',
		'secondary' => 'Sekundärfarbe (--brand-secondary)',
		'surface'   => 'Fläche (--brand-surface)',
		'text'      => 'Text (--brand-text)',
		'muted'     => 'Gedämpft (--brand-muted)',
		'border'    => 'Rahmen (--brand-border)',
	];

	public static function render(): void {
		if ( ! current_user_can( RoleBridge::CAP_MANAGE_CONTENT ) ) {
			wp_die( esc_html__( 'Keine Berechtigung für das Brand Board.', 'liebherr-interface-world' ) );
		}

		$notice = self::maybe_handle_submit();
		$t      = BrandTokens::get();

		echo '<div class="wrap"><h1>' . esc_html__( 'Brand Board – Design-Tokens (CI)', 'liebherr-interface-world' ) . '</h1>';
		echo '<p class="description">' . esc_html__( 'Zentrale CI-Tokens der Landingpage (Pflichtenheft §10–12). Solange kein freigegebenes Liebherr-Brand-Kit vorliegt, gelten neutrale Fallbacks – kein erfundenes Liebherr-Branding (CI-002). Nach dokumentierter Freigabe hier die Originalwerte eintragen (siehe docs/LIW_BRAND_TOKENS.md) und die Assets im Media Board freigeben.', 'liebherr-interface-world' ) . '</p>';

		if ( null !== $notice ) {
			printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr( $notice['class'] ), esc_html( $notice['message'] ) );
		}

		echo '<form method="post" action="">';
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		echo '<input type="hidden" name="liw_action" value="save_brand" />';
		echo '<table class="form-table" role="presentation"><tbody>';

		foreach ( self::COLOR_FIELDS as $key => $label ) {
			$val = (string) $t[ $key ];
			echo '<tr><th scope="row"><label for="liw-brand-' . esc_attr( $key ) . '">' . esc_html__( $label, 'liebherr-interface-world' ) . '</label></th><td>';
			printf(
				'<input type="color" id="liw-brand-%1$s" name="liw_brand[%1$s]" value="%2$s" /> <input type="text" name="liw_brand_hex[%1$s]" value="%2$s" class="regular-text" pattern="#[0-9a-fA-F]{6}" aria-label="%3$s" />',
				esc_attr( $key ),
				esc_attr( $val ),
				esc_attr__( 'Hex-Wert', 'liebherr-interface-world' )
			);
			echo '</td></tr>';
		}

		self::text_row( 'heading_font', __( 'Headline-Schrift (--font-heading)', 'liebherr-interface-world' ), (string) $t['heading_font'] );
		self::text_row( 'body_font', __( 'Text-Schrift (--font-body)', 'liebherr-interface-world' ), (string) $t['body_font'] );
		self::text_row( 'radius', __( 'Radius (--radius-control, z. B. 2px)', 'liebherr-interface-world' ), (string) $t['radius'] );
		self::text_row( 'content_max', __( 'Inhaltsbreite (--content-max, z. B. 1440px)', 'liebherr-interface-world' ), (string) $t['content_max'] );
		self::text_row( 'brand_text', __( 'Wortmarke (Text; leer = WP-Seitentitel)', 'liebherr-interface-world' ), (string) ( $t['brand_text'] ?? '' ) );

		self::render_logo_row( (int) $t['logo_id'] );

		echo '</tbody></table>';
		submit_button( __( 'Tokens speichern', 'liebherr-interface-world' ) );
		echo '</form>';

		echo '<hr /><h2>' . esc_html__( 'Vorschau', 'liebherr-interface-world' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Aktuell ausgegebener CSS-Block (Frontend, :root):', 'liebherr-interface-world' ) . '</p>';
		echo '<pre style="max-width:900px;overflow:auto;background:#f6f7f7;padding:12px;border:1px solid #dcdcde">' . esc_html( BrandTokens::css_root() ) . '</pre>';
		echo '</div>';
	}

	private static function text_row( string $key, string $label, string $value ): void {
		echo '<tr><th scope="row"><label for="liw-brand-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
		printf(
			'<input type="text" id="liw-brand-%1$s" name="liw_brand[%1$s]" value="%2$s" class="regular-text" />',
			esc_attr( $key ),
			esc_attr( $value )
		);
		echo '</td></tr>';
	}

	/** Logo-Auswahl aus Media-Board-Assets der Rolle „logo"; unfreigegebene sind gekennzeichnet. */
	private static function render_logo_row( int $current ): void {
		$logos = get_posts( [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 50,
			'no_found_rows'  => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => [ [ 'key' => '_liw_media_role', 'value' => 'logo', 'compare' => '=' ] ],
		] );

		echo '<tr><th scope="row"><label for="liw-brand-logo">' . esc_html__( 'Logo (aus Media Board)', 'liebherr-interface-world' ) . '</label></th><td>';
		if ( [] === $logos ) {
			echo '<p>' . esc_html__( 'Noch kein Logo-Asset im Media Board (Rolle „logo"). Über das Media Board bereitstellen.', 'liebherr-interface-world' ) . '</p>';
			echo '<input type="hidden" name="liw_brand[logo_id]" value="' . esc_attr( (string) $current ) . '" />';
			echo '</td></tr>';
			return;
		}

		echo '<select id="liw-brand-logo" name="liw_brand[logo_id]">';
		echo '<option value="0">' . esc_html__( '— kein Logo —', 'liebherr-interface-world' ) . '</option>';
		foreach ( $logos as $logo ) {
			$approved = MediaBridge::is_approved( $logo->ID );
			$suffix   = $approved ? '' : ' ' . __( '(nicht freigegeben)', 'liebherr-interface-world' );
			printf(
				'<option value="%1$d" %2$s>%3$s%4$s</option>',
				(int) $logo->ID,
				selected( $current, (int) $logo->ID, false ),
				esc_html( get_the_title( $logo ) ),
				esc_html( $suffix )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Ein nicht freigegebenes Logo wird im Frontend NICHT ausgegeben (CI-005), bis es im Media Board freigegeben ist.', 'liebherr-interface-world' ) . '</p>';
		echo '</td></tr>';
	}

	/** @return array{class:string,message:string}|null */
	private static function maybe_handle_submit(): ?array {
		if ( ! isset( $_POST['liw_action'] ) || 'save_brand' !== $_POST['liw_action'] ) {
			return null;
		}
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		if ( ! current_user_can( RoleBridge::CAP_MANAGE_CONTENT ) ) {
			return [ 'class' => 'notice-error', 'message' => __( 'Keine Berechtigung.', 'liebherr-interface-world' ) ];
		}

		$raw = wp_unslash( $_POST['liw_brand'] ?? [] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- BrandTokens::sanitize() validiert alle Felder.
		if ( ! is_array( $raw ) ) {
			return [ 'class' => 'notice-error', 'message' => __( 'Ungültige Daten.', 'liebherr-interface-world' ) ];
		}

		// Freitext-Hexfelder (falls direkt eingegeben) haben Vorrang vor dem color-Picker.
		$hex = wp_unslash( $_POST['liw_brand_hex'] ?? [] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize() prüft #rrggbb.
		if ( is_array( $hex ) ) {
			foreach ( $hex as $k => $v ) {
				if ( is_string( $v ) && preg_match( '/^#[0-9a-fA-F]{6}$/', trim( $v ) ) ) {
					$raw[ $k ] = trim( $v );
				}
			}
		}

		$before = BrandTokens::get();
		$after  = BrandTokens::save( $raw );

		if ( AuditBridge::is_available() ) {
			AuditBridge::log( 'update', 'brand_tokens', 0, $before, $after, get_current_user_id() );
		}

		return [ 'class' => 'notice-success', 'message' => __( 'Design-Tokens gespeichert.', 'liebherr-interface-world' ) ];
	}
}
