<?php
/**
 * Liebherr Interface Solutions – Metabox „Sichtbarkeits-Zeitfenster" für Abschnitte (§19).
 *
 * Zwei optionale Felder (ab / bis) im `liw_section`-Editor. Eingabe in der Website-Zeitzone,
 * Speicherung als UTC (Content\SectionSchedule). Leeres Feld = keine Grenze. Nonce + Capability
 * (`edit_post`) geschützt.
 *
 * @package Liebherr\InterfaceWorld\Admin
 * @since   0.1.0-alpha.36
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin;

use Liebherr\InterfaceWorld\Content\SectionSchedule;
use Liebherr\InterfaceWorld\CPT\LiwSectionCpt;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SectionScheduleMetabox {

	private const NONCE_ACTION = 'liw_section_schedule_save';
	private const NONCE_NAME   = 'liw_section_schedule_nonce';

	public static function register(): void {
		add_action( 'add_meta_boxes', [ self::class, 'add' ] );
		add_action( 'save_post_' . LiwSectionCpt::POST_TYPE, [ self::class, 'save' ], 10, 2 );
	}

	public static function add(): void {
		add_meta_box(
			'liw-section-schedule',
			__( 'Sichtbarkeits-Zeitfenster', 'liebherr-interface-world' ),
			[ self::class, 'render' ],
			LiwSectionCpt::POST_TYPE,
			'side'
		);
	}

	public static function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		$from = self::utc_to_local_input( (string) get_post_meta( $post->ID, SectionSchedule::META_FROM, true ) );
		$until = self::utc_to_local_input( (string) get_post_meta( $post->ID, SectionSchedule::META_UNTIL, true ) );

		echo '<p><label for="liw-valid-from"><strong>' . esc_html__( 'Sichtbar ab', 'liebherr-interface-world' ) . '</strong></label><br />';
		printf( '<input type="datetime-local" id="liw-valid-from" name="liw_valid_from" value="%s" style="width:100%%" /></p>', esc_attr( $from ) );
		echo '<p><label for="liw-valid-until"><strong>' . esc_html__( 'Sichtbar bis', 'liebherr-interface-world' ) . '</strong></label><br />';
		printf( '<input type="datetime-local" id="liw-valid-until" name="liw_valid_until" value="%s" style="width:100%%" /></p>', esc_attr( $until ) );
		echo '<p class="description">' . esc_html__( 'Leer lassen = keine Grenze. Außerhalb des Fensters erscheint der Abschnitt nicht auf der Landingpage (zusätzlich zum Veröffentlichungsstatus). Zeiten in der Website-Zeitzone.', 'liebherr-interface-world' ) . '</p>';
	}

	public static function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		self::save_field( $post_id, SectionSchedule::META_FROM, (string) ( $_POST['liw_valid_from'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- in save_field() validiert.
		self::save_field( $post_id, SectionSchedule::META_UNTIL, (string) ( $_POST['liw_valid_until'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
	}

	/** Speichert einen datetime-local-Wert als UTC oder löscht das Meta bei leer/ungültig. */
	private static function save_field( int $post_id, string $meta_key, string $raw ): void {
		$raw = trim( (string) wp_unslash( $raw ) );
		if ( '' === $raw || ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $raw ) ) {
			delete_post_meta( $post_id, $meta_key );
			return;
		}
		try {
			$dt  = new \DateTimeImmutable( str_replace( 'T', ' ', $raw ), wp_timezone() );
			$utc = $dt->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
			update_post_meta( $post_id, $meta_key, $utc );
		} catch ( \Exception $e ) {
			delete_post_meta( $post_id, $meta_key );
		}
	}

	/** UTC-Speicherwert → „Y-m-d\TH:i" in Website-Zeitzone für das Eingabefeld. */
	private static function utc_to_local_input( string $utc ): string {
		if ( '' === $utc ) {
			return '';
		}
		try {
			$dt = new \DateTimeImmutable( $utc . ' UTC' );
			return $dt->setTimezone( wp_timezone() )->format( 'Y-m-d\TH:i' );
		} catch ( \Exception $e ) {
			return '';
		}
	}
}
