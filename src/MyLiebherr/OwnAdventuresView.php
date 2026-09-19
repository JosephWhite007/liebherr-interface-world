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
		$filter = isset( $_GET['liw_adv_status'] ) ? sanitize_key( (string) $_GET['liw_adv_status'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reiner Lesefilter.
		$posts  = get_posts( [
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
			$status = $has_reg ? RegistrationService::current_status( (int) $p->ID ) : (string) get_post_status( $p );
			$counts[ $status ] = ( $counts[ $status ] ?? 0 ) + 1;
			if ( '' !== $filter && $filter !== $status ) {
				continue;
			}
			$machine = (string) get_post_meta( (int) $p->ID, AdventureCpt::M_MACHINE, true );
			$comp    = (string) get_post_meta( (int) $p->ID, AdventureCpt::M_COMPONENT, true );
			$token   = $has_reg ? (int) ( RegistrationService::get_registration( (int) $p->ID )['token_value'] ?? 0 ) : 0;
			$rows   .= '<tr>'
				. '<td>' . esc_html( get_the_title( $p ) ) . '</td>'
				. '<td>' . esc_html( trim( $machine . ( '' !== $comp ? ' · ' . $comp : '' ) ) ) . '</td>'
				. '<td>' . esc_html( $status ) . '</td>'
				. '<td class="liw-myl__wtx-amt">' . esc_html( (string) $token ) . '</td>'
				. '</tr>';
		}

		return '<section class="liw-myl__adv" id="liw-my-adventures">'
			. '<h2 class="liw-myl__tile-title">' . esc_html__( 'Own Adventures', 'liebherr-interface-world' ) . '</h2>'
			. self::filter_html( $counts, $filter )
			. ( '' !== $rows
				? '<table class="liw-myl__wtx"><thead><tr><th>' . esc_html__( 'Titel', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Maschine/Bauteil', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Status', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Token', 'liebherr-interface-world' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table>'
				: '<p class="liw-myl__wallet-note">' . esc_html__( 'Keine eigenen Adventures in dieser Auswahl.', 'liebherr-interface-world' ) . '</p>' )
			. '</section>';
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
