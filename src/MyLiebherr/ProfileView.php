<?php
/**
 * Liebherr World – My Liebherr: Profil & Rollen (Pflichtenheft My Liebherr §6/§14/§29, ADR-LIW-MYL-001 S5).
 *
 * Zeigt Stammdaten, Rollen und Organisationen (lesbar) und lässt erlaubte Profil-/Präferenzfelder bearbeiten
 * (Persona, Sprache, Zeitzone, aktive Organisation) – gespeichert über PATCH /me (Feldfreigabe §18). Ein
 * Datenschutz-Abschnitt weist auf Auskunft/Export/Löschung hin (Einstieg §14) ohne destruktive Aktion.
 * Shortcode `[liw_my_profile]`; zusätzlich in die My-Overview-Seite eingebettet. Self-gating; nur eigener
 * Nutzer (§35/SEC 01).
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.115
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ProfileView {

	public const SHORTCODE = 'liw_my_profile';

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'assets' ] );
	}

	/** Auf Seiten mit diesem Shortcode dieselben Assets laden wie die Overview (dedupliziert per Handle). */
	public static function assets(): void {
		if ( is_admin() ) {
			return;
		}
		$post = get_post();
		if ( ! $post instanceof \WP_Post || ! has_shortcode( (string) $post->post_content, self::SHORTCODE ) ) {
			return;
		}
		OverviewView::assets_for_shortcode();
	}

	public static function shortcode(): string {
		if ( ! Flags::enabled() ) {
			return '';
		}
		if ( ! is_user_logged_in() || ! current_user_can( Roles::CAP_ACCESS ) ) {
			return '<div class="liw-myl liw-myl--notice">' . esc_html__( 'Bitte melden Sie sich an, um Ihr Profil zu sehen.', 'liebherr-interface-world' ) . '</div>';
		}
		return '<div class="liw-myl">' . self::render( get_current_user_id() ) . '</div>';
	}

	/** Wiederverwendbarer Profil-Block (auch aus der Overview eingebettet). */
	public static function render( int $uid ): string {
		$ctx = Context::for_user( $uid );
		return '<section class="liw-myl__profile">'
			. '<h2 class="liw-myl__tile-title">' . esc_html__( 'Profil & Rollen', 'liebherr-interface-world' ) . '</h2>'
			. self::identity_html( $ctx )
			. self::form_html( $ctx )
			. self::privacy_html()
			. '</section>';
	}

	/** @param array<string,mixed> $ctx */
	private static function identity_html( array $ctx ): string {
		$roles = array_map( 'strval', (array) $ctx['roles'] );
		$roles_txt = '' !== implode( '', $roles ) ? implode( ', ', array_map( 'esc_html', $roles ) ) : esc_html__( 'keine', 'liebherr-interface-world' );
		$memberships = (array) $ctx['memberships'];
		$orgs = esc_html__( 'keine hinterlegt', 'liebherr-interface-world' );
		if ( array() !== $memberships ) {
			$parts = [];
			foreach ( $memberships as $m ) {
				$parts[] = esc_html( sprintf( '#%d / %s (%s)', (int) $m['org_id'], (string) $m['role'], (string) $m['status'] ) );
			}
			$orgs = implode( ', ', $parts );
		}
		return '<dl class="liw-myl__id">'
			. '<dt>' . esc_html__( 'Name', 'liebherr-interface-world' ) . '</dt><dd>' . esc_html( (string) $ctx['display_name'] ) . '</dd>'
			. '<dt>' . esc_html__( 'Rollen', 'liebherr-interface-world' ) . '</dt><dd>' . $roles_txt . '</dd>'
			. '<dt>' . esc_html__( 'Organisationen', 'liebherr-interface-world' ) . '</dt><dd>' . $orgs . '</dd>'
			. '</dl>';
	}

	/** @param array<string,mixed> $ctx */
	private static function form_html( array $ctx ): string {
		$personas = '<option value="">' . esc_html__( '– keine –', 'liebherr-interface-world' ) . '</option>';
		foreach ( Context::ALLOWED_PERSONAS as $p ) {
			$personas .= '<option value="' . esc_attr( $p ) . '"' . selected( (string) $ctx['persona'], $p, false ) . '>' . esc_html( $p ) . '</option>';
		}
		return '<form class="liw-myl__form" data-liw-profile-form'
			. ' data-saved="' . esc_attr__( 'Gespeichert.', 'liebherr-interface-world' ) . '"'
			. ' data-error="' . esc_attr__( 'Speichern fehlgeschlagen.', 'liebherr-interface-world' ) . '">'
			. '<label class="liw-myl__field"><span>' . esc_html__( 'Persona', 'liebherr-interface-world' ) . '</span><select name="persona">' . $personas . '</select></label>'
			. '<label class="liw-myl__field"><span>' . esc_html__( 'Sprache', 'liebherr-interface-world' ) . '</span><input type="text" name="locale" value="' . esc_attr( (string) $ctx['locale'] ) . '" maxlength="10"></label>'
			. '<label class="liw-myl__field"><span>' . esc_html__( 'Zeitzone', 'liebherr-interface-world' ) . '</span><input type="text" name="timezone" value="' . esc_attr( (string) $ctx['timezone'] ) . '" maxlength="64"></label>'
			. '<label class="liw-myl__field"><span>' . esc_html__( 'Aktive Organisation (ID)', 'liebherr-interface-world' ) . '</span><input type="number" name="active_org_id" value="' . esc_attr( (string) (int) $ctx['active_org_id'] ) . '" min="0"></label>'
			. '<div class="liw-myl__formrow"><button type="submit" class="liw-myl__action">' . esc_html__( 'Speichern', 'liebherr-interface-world' ) . '</button> <span class="liw-myl__status" data-liw-profile-status aria-live="polite"></span></div>'
			. '</form>';
	}

	private static function privacy_html(): string {
		return '<div class="liw-myl__privacy">'
			. '<h3 class="liw-myl__tile-title">' . esc_html__( 'Sicherheit & Datenschutz', 'liebherr-interface-world' ) . '</h3>'
			. '<p>' . esc_html__( 'Auskunft, Export und Löschung Ihrer personenbezogenen Daten sind auf Anfrage möglich, soweit keine gesetzlichen oder revisionsbezogenen Pflichten entgegenstehen.', 'liebherr-interface-world' ) . '</p>'
			. '</div>';
	}
}
