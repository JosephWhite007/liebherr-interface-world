<?php
/**
 * Liebherr World – My Liebherr: My Contacts (Pflichtenheft My Liebherr §33, ADR-LIW-MYL-001 R4).
 *
 * Kontaktanfragen (ein-/ausgehend, annehmen/ablehnen), Contact Connections (Status steuern) und gemeinsame
 * Leistungen (vorschlagen/bestätigen). Der Erstkontakt erfolgt nur als strukturierte Anfrage; erst die Zustimmung
 * erzeugt eine Connection. Shortcode `[liw_my_contacts]`; self-gating; nur eigene Beteiligung (§35/SEC 01).
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.122
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ContactsView {

	public const SHORTCODE = 'liw_my_contacts';

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'assets' ] );
	}

	public static function assets(): void {
		if ( is_admin() ) {
			return;
		}
		$post = get_post();
		if ( $post instanceof \WP_Post && has_shortcode( (string) $post->post_content, self::SHORTCODE ) ) {
			OverviewView::assets_for_shortcode();
		}
	}

	public static function shortcode(): string {
		if ( ! Flags::enabled() || ! is_user_logged_in() || ! current_user_can( Roles::CAP_ACCESS ) ) {
			return '';
		}
		return '<div class="liw-myl">' . self::render( get_current_user_id() ) . '</div>';
	}

	public static function render( int $uid ): string {
		$requests = ContactRepository::requests_for( $uid );
		$incoming = array_filter( $requests, static fn( $r ) => (int) $r['recipient_id'] === $uid && 'requested' === $r['status'] );
		$outgoing = array_filter( $requests, static fn( $r ) => (int) $r['requester_id'] === $uid );

		return '<section class="liw-myl__contacts" id="liw-my-contacts">'
			. '<h2 class="liw-myl__tile-title">' . esc_html__( 'My Contacts', 'liebherr-interface-world' ) . '</h2>'
			. self::incoming_html( $incoming )
			. self::request_form_html()
			. self::outgoing_html( $outgoing )
			. self::connections_html( $uid, ContactRepository::connections_for( $uid ) )
			. '</section>';
	}

	/** @param array<int,array<string,mixed>> $reqs */
	private static function incoming_html( array $reqs ): string {
		if ( [] === $reqs ) {
			return '';
		}
		$li = '';
		foreach ( $reqs as $r ) {
			$id  = (int) $r['id'];
			$li .= '<li>' . esc_html( sprintf( /* translators: 1: user id 2: purpose */ __( 'Anfrage von #%1$d: %2$s', 'liebherr-interface-world' ), (int) $r['requester_id'], (string) $r['purpose'] ) ) . ' '
				. '<button type="button" class="liw-myl__action" data-liw-act="contacts/requests/' . $id . '/decision?decision=accept">' . esc_html__( 'Annehmen', 'liebherr-interface-world' ) . '</button> '
				. '<button type="button" class="liw-myl__wbtn" data-liw-act="contacts/requests/' . $id . '/decision?decision=decline">' . esc_html__( 'Ablehnen', 'liebherr-interface-world' ) . '</button></li>';
		}
		return '<h3 class="liw-myl__tile-title">' . esc_html__( 'Eingehende Anfragen', 'liebherr-interface-world' ) . '</h3><ul class="liw-myl__shares">' . $li . '</ul>';
	}

	/** @param array<int,array<string,mixed>> $reqs */
	private static function outgoing_html( array $reqs ): string {
		if ( [] === $reqs ) {
			return '';
		}
		$li = '';
		foreach ( $reqs as $r ) {
			$li .= '<li>' . esc_html( sprintf( /* translators: 1: recipient id 2: status */ __( 'An #%1$d – Status: %2$s', 'liebherr-interface-world' ), (int) $r['recipient_id'], (string) $r['status'] ) ) . '</li>';
		}
		return '<h3 class="liw-myl__tile-title">' . esc_html__( 'Gesendete Anfragen', 'liebherr-interface-world' ) . '</h3><ul class="liw-myl__shares">' . $li . '</ul>';
	}

	private static function request_form_html(): string {
		return '<form class="liw-myl__form" data-liw-post="contacts/requests">'
			. '<label class="liw-myl__field"><span>' . esc_html__( 'Empfänger E-Mail', 'liebherr-interface-world' ) . '</span><input type="email" name="recipient_email"></label>'
			. '<label class="liw-myl__field"><span>' . esc_html__( 'oder Empfänger-ID', 'liebherr-interface-world' ) . '</span><input type="number" name="recipient_id" min="0" value="0"></label>'
			. '<label class="liw-myl__field liw-myl__field--wide"><span>' . esc_html__( 'Anlass', 'liebherr-interface-world' ) . '</span><input type="text" name="purpose" maxlength="160"></label>'
			. '<label class="liw-myl__field"><span>' . esc_html__( 'Gewünschte Leistung', 'liebherr-interface-world' ) . '</span><input type="text" name="service_hint" maxlength="160"></label>'
			. '<label class="liw-myl__field"><span>' . esc_html__( 'Token-Rahmen', 'liebherr-interface-world' ) . '</span><input type="number" name="token_frame" min="0" value="0"></label>'
			. '<div class="liw-myl__formrow"><button type="submit" class="liw-myl__action">' . esc_html__( 'Kontakt anfragen', 'liebherr-interface-world' ) . '</button></div>'
			. '</form>';
	}

	/** @param array<int,array<string,mixed>> $conns */
	private static function connections_html( int $uid, array $conns ): string {
		if ( [] === $conns ) {
			return '<h3 class="liw-myl__tile-title">' . esc_html__( 'Verbindungen', 'liebherr-interface-world' ) . '</h3><p class="liw-myl__wallet-note">' . esc_html__( 'Noch keine Verbindungen.', 'liebherr-interface-world' ) . '</p>';
		}
		$out = '<h3 class="liw-myl__tile-title">' . esc_html__( 'Verbindungen', 'liebherr-interface-world' ) . '</h3>';
		foreach ( $conns as $c ) {
			$id    = (int) $c['id'];
			$other = ( (int) $c['party_a'] === $uid ) ? (int) $c['party_b'] : (int) $c['party_a'];
			$status = (string) $c['status'];
			$ctrls = '';
			foreach ( ContactState::connection_transitions()[ $status ] ?? [] as $to ) {
				$ctrls .= '<button type="button" class="liw-myl__wbtn" data-liw-act="connections/' . $id . '/status?status=' . esc_attr( $to ) . '">' . esc_html( $to ) . '</button> ';
			}
			$out .= '<div class="liw-myl__conn">'
				. '<div class="liw-myl__pocket-head"><strong>' . esc_html( sprintf( /* translators: 1: other user id 2: status */ __( 'Verbindung mit #%1$d (%2$s)', 'liebherr-interface-world' ), $other, $status ) ) . '</strong> ' . $ctrls . '</div>'
				. self::services_html( $id )
				. self::service_form_html( $id )
				. '</div>';
		}
		return $out;
	}

	private static function services_html( int $connection_id ): string {
		$svcs = ContactRepository::services_for( $connection_id );
		if ( [] === $svcs ) {
			return '';
		}
		$li = '';
		foreach ( $svcs as $s ) {
			$id   = (int) $s['id'];
			$ctrl = '';
			foreach ( ContactState::service_transitions()[ (string) $s['status'] ] ?? [] as $to ) {
				$ctrl .= '<button type="button" class="liw-myl__wbtn" data-liw-act="services/' . $id . '/status?status=' . esc_attr( $to ) . '">' . esc_html( $to ) . '</button> ';
			}
			$li .= '<li>' . esc_html( sprintf( '%s – %d Token (%s) ', (string) $s['description'], (int) $s['token_amount'], (string) $s['status'] ) ) . $ctrl . '</li>';
		}
		return '<ul class="liw-myl__shares">' . $li . '</ul>';
	}

	private static function service_form_html( int $connection_id ): string {
		return '<form class="liw-myl__shareform" data-liw-post="connections/' . $connection_id . '/services">'
			. '<input type="text" name="description" maxlength="200" placeholder="' . esc_attr__( 'Leistung', 'liebherr-interface-world' ) . '">'
			. '<input type="number" name="token_amount" min="0" value="0" aria-label="' . esc_attr__( 'Token', 'liebherr-interface-world' ) . '">'
			. '<button type="submit" class="liw-myl__wbtn">' . esc_html__( 'Leistung vorschlagen', 'liebherr-interface-world' ) . '</button>'
			. '</form>';
	}
}
