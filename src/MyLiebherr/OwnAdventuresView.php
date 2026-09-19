<?php
/**
 * Liebherr World – My Liebherr: Own Adventures (Pflichtenheft My Liebherr §32, ADR-LIW-MYL-001 R3).
 *
 * Wiederverwendung der bestehenden Adventures-Insel (CPT {@see \Liebherr\InterfaceWorld\Adventures\AdventureCpt}):
 * listet die vom angemeldeten Nutzer erstellten Beiträge, gefiltert nach Status, mit Maschine/Bauteil und
 * Tokenwert (aus {@see \Liebherr\InterfaceWorld\Adventures\RegistrationService}). KEINE zweite Datenhaltung.
 * Shortcode `[liw_my_adventures]`; self-gating; nur eigene Beiträge (author-gebunden, §35/SEC 01).
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.120
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

use Liebherr\InterfaceWorld\Adventures\AdventureCpt;
use Liebherr\InterfaceWorld\Adventures\RegistrationService;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class OwnAdventuresView {

	public const SHORTCODE = 'liw_my_adventures';

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
		if ( ! class_exists( AdventureCpt::class ) ) {
			return '';
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- reine Lesefilter (GET).
		$filter = isset( $_GET['liw_adv_status'] ) ? sanitize_key( (string) $_GET['liw_adv_status'] ) : '';
		$query  = isset( $_GET['liw_adv_q'] ) ? sanitize_text_field( (string) $_GET['liw_adv_q'] ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$hits = ( '' !== $query ) ? array_column( ThreeWordLabel::search( $query ), 'object_id' ) : null;

		$posts = get_posts( [
			'post_type'   => AdventureCpt::POST_TYPE,
			'author'      => $uid,
			'numberposts' => 50,
			'post_status' => 'any',
			'orderby'     => 'modified',
			'order'       => 'DESC',
		] );

		$has_reg = class_exists( RegistrationService::class );
		$rows    = '';
		$counts  = [];
		foreach ( $posts as $p ) {
			$pid    = (int) $p->ID;
			$status = $has_reg ? RegistrationService::current_status( $pid ) : (string) get_post_status( $p );
			$counts[ $status ] = ( $counts[ $status ] ?? 0 ) + 1;
			if ( '' !== $filter && $filter !== $status ) {
				continue;
			}
			if ( null !== $hits && ! in_array( $pid, array_map( 'intval', $hits ), true ) ) {
				continue; // Label-Suche aktiv, aber dieses Adventure passt nicht.
			}
			$machine = (string) get_post_meta( $pid, AdventureCpt::M_MACHINE, true );
			$comp    = (string) get_post_meta( $pid, AdventureCpt::M_COMPONENT, true );
			$token   = $has_reg ? (int) ( RegistrationService::get_registration( $pid )['token_value'] ?? 0 ) : 0;
			$rows   .= '<tr>'
				. '<td>' . esc_html( get_the_title( $p ) ) . '</td>'
				. '<td>' . esc_html( trim( $machine . ( '' !== $comp ? ' · ' . $comp : '' ) ) ) . '</td>'
				. '<td>' . esc_html( $status ) . '</td>'
				. '<td class="liw-myl__wtx-amt">' . esc_html( (string) $token ) . '</td>'
				. '<td>' . self::label_cell( $pid, $machine, $comp, (string) get_the_title( $p ) ) . '</td>'
				. '</tr>';
		}

		return '<section class="liw-myl__adv" id="liw-my-adventures">'
			. '<h2 class="liw-myl__tile-title">' . esc_html__( 'Own Adventures', 'liebherr-interface-world' ) . '</h2>'
			. self::search_html( $query )
			. self::filter_html( $counts, $filter )
			. ( '' !== $rows
				? '<table class="liw-myl__wtx"><thead><tr><th>' . esc_html__( 'Titel', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Maschine/Bauteil', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Status', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Token', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Drei-Wort-Name (Maschine · Problem · Handlung)', 'liebherr-interface-world' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table>'
				: '<p class="liw-myl__wallet-note">' . esc_html__( 'Keine eigenen Adventures in dieser Auswahl.', 'liebherr-interface-world' ) . '</p>' )
			. '</section>';
	}

	/** Zelle mit aktuellem Drei-Wort-Namen (oder Vorschlag) + Bestätigungs-/Änderungsformular (PUT, nur Autor). */
	private static function label_cell( int $pid, string $machine, string $comp, string $title ): string {
		$lbl = ThreeWordLabel::get( $pid );
		if ( null !== $lbl ) {
			$t1 = (string) $lbl['term_1'];
			$t2 = (string) $lbl['term_2'];
			$t3 = (string) $lbl['term_3'];
			$syn = (string) $lbl['synonyms'];
			$head = '<strong>' . esc_html( ThreeWordLabel::display( $t1, $t2, $t3 ) ) . '</strong>';
		} else {
			[ $t1, $t2, $t3 ] = ThreeWordLabel::suggest( $machine, $comp, $title );
			$syn = '';
			$head = '<em class="liw-myl__dream-tags">' . esc_html__( 'Vorschlag – bitte bestätigen', 'liebherr-interface-world' ) . '</em>';
		}
		return $head
			. '<form class="liw-myl__twform" data-liw-post="adventures/' . $pid . '/label" data-liw-method="PUT">'
			. '<input type="text" name="term_1" value="' . esc_attr( $t1 ) . '" aria-label="' . esc_attr__( 'Maschine', 'liebherr-interface-world' ) . '" placeholder="' . esc_attr__( 'Maschine', 'liebherr-interface-world' ) . '">'
			. '<input type="text" name="term_2" value="' . esc_attr( $t2 ) . '" aria-label="' . esc_attr__( 'Problem', 'liebherr-interface-world' ) . '" placeholder="' . esc_attr__( 'Problem', 'liebherr-interface-world' ) . '">'
			. '<input type="text" name="term_3" value="' . esc_attr( $t3 ) . '" aria-label="' . esc_attr__( 'Handlung', 'liebherr-interface-world' ) . '" placeholder="' . esc_attr__( 'Handlung', 'liebherr-interface-world' ) . '">'
			. '<input type="text" name="synonyms" value="' . esc_attr( $syn ) . '" aria-label="' . esc_attr__( 'Synonyme', 'liebherr-interface-world' ) . '" placeholder="' . esc_attr__( 'Synonyme (Komma)', 'liebherr-interface-world' ) . '">'
			. '<button type="submit" class="liw-myl__wbtn">' . esc_html__( 'Bestätigen', 'liebherr-interface-world' ) . '</button>'
			. '</form>';
	}

	/** Agenten-/Label-Suche (GET, serverseitig; kombiniert mit dem eigenen Bestand). */
	private static function search_html( string $q ): string {
		return '<form class="liw-myl__advsearch" method="get">'
			. '<input type="search" name="liw_adv_q" value="' . esc_attr( $q ) . '" placeholder="' . esc_attr__( 'Drei-Wort-Name / Synonym suchen …', 'liebherr-interface-world' ) . '">'
			. '<button type="submit" class="liw-myl__wbtn">' . esc_html__( 'Suchen', 'liebherr-interface-world' ) . '</button>'
			. ( '' !== $q ? ' <a class="liw-myl__wbtn" href="' . esc_url( remove_query_arg( 'liw_adv_q' ) ) . '">' . esc_html__( 'Zurücksetzen', 'liebherr-interface-world' ) . '</a>' : '' )
			. '</form>';
	}

	/**
	 * @param array<string,int> $counts
	 */
	private static function filter_html( array $counts, string $active ): string {
		$base  = remove_query_arg( 'liw_adv_status' );
		$links = '<a class="liw-myl__wbtn' . ( '' === $active ? ' is-active' : '' ) . '" href="' . esc_url( $base ) . '">' . esc_html__( 'Alle', 'liebherr-interface-world' ) . '</a> ';
		foreach ( $counts as $status => $n ) {
			$url    = esc_url( add_query_arg( 'liw_adv_status', $status, $base ) );
			$links .= '<a class="liw-myl__wbtn' . ( $active === $status ? ' is-active' : '' ) . '" href="' . $url . '">' . esc_html( $status . ' (' . $n . ')' ) . '</a> ';
		}
		return '<div class="liw-myl__advfilter">' . $links . '</div>';
	}
}
