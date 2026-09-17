<?php
/**
 * Liebherr Interface Solutions – Simulation Board (Admin-Seite)
 *
 * Zweites Admin-Board dieser Auslieferung (Grundgerüst, analog InterfaceBoardPage):
 * Liste + Anlage-Formular für Simulationswelten (liw_simulation_world) und, je nach
 * ausgewählter Welt, deren Testszenarien (liw_test_scenario) – Liebherr-Pflichtenheft
 * §17 „Magic Cube". Status-Übergänge (Welt validieren, Szenario als bestanden/fehlgeschlagen
 * markieren) sind bewusst nicht Teil dieser Auslieferung (YAGNI – erst wenn die Simulations-
 * Engine selbst angebunden wird, s. Abschlussbericht/CHANGELOG).
 *
 * @package Liebherr\InterfaceWorld\Admin\Pages
 * @since   0.1.0-alpha.4
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin\Pages;

use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;
use Liebherr\InterfaceWorld\Simulation\SimulationService;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SimulationBoardPage {

	public const MENU_SLUG = 'liw-simulation-board';

	private const NONCE_ACTION_WORLD    = 'liw_simulation_board_create_world';
	private const NONCE_NAME_WORLD      = 'liw_simulation_board_world_nonce';
	private const NONCE_ACTION_SCENARIO = 'liw_simulation_board_add_scenario';
	private const NONCE_NAME_SCENARIO   = 'liw_simulation_board_scenario_nonce';

	public static function render(): void {
		if ( ! current_user_can( RoleBridge::CAP_MANAGE_INTERFACES ) ) {
			wp_die( esc_html__( 'Keine Berechtigung für das Simulation Board.', 'liebherr-interface-world' ) );
		}

		$notice   = self::maybe_handle_submit();
		$world_id = isset( $_GET['world_id'] ) ? absint( wp_unslash( $_GET['world_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reine Ansichtsnavigation, keine Datenänderung.

		echo '<div class="wrap"><h1>' . esc_html__( 'Simulation Board – Magic Cube', 'liebherr-interface-world' ) . '</h1>';

		if ( null !== $notice ) {
			printf( '<div class="notice %s"><p>%s</p></div>', esc_attr( $notice['class'] ), esc_html( $notice['message'] ) );
		}

		self::render_world_form();
		$worlds = self::render_worlds_table( $world_id );

		if ( $world_id > 0 && isset( $worlds[ $world_id ] ) ) {
			self::render_scenario_form( $world_id );
			self::render_scenarios_table( $world_id, $worlds[ $world_id ]['name'] );
		}

		echo '</div>';
	}

	/** @return array{class:string,message:string}|null */
	private static function maybe_handle_submit(): ?array {
		if ( isset( $_POST['liw_action'] ) && 'create_world' === $_POST['liw_action'] ) {
			check_admin_referer( self::NONCE_ACTION_WORLD, self::NONCE_NAME_WORLD );

			$result = SimulationService::create_world(
				[
					'name'    => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
					'scope'   => sanitize_text_field( wp_unslash( $_POST['scope'] ?? '' ) ),
					'version' => sanitize_text_field( wp_unslash( $_POST['version'] ?? '0.1.0' ) ),
				],
				get_current_user_id()
			);

			if ( is_wp_error( $result ) ) {
				return [ 'class' => 'notice-error', 'message' => $result->get_error_message() ];
			}
			return [ 'class' => 'notice-success', 'message' => __( 'Simulationswelt angelegt.', 'liebherr-interface-world' ) ];
		}

		if ( isset( $_POST['liw_action'] ) && 'add_scenario' === $_POST['liw_action'] ) {
			check_admin_referer( self::NONCE_ACTION_SCENARIO, self::NONCE_NAME_SCENARIO );

			$result = SimulationService::add_scenario(
				[
					'world_id'        => absint( wp_unslash( $_POST['world_id'] ?? 0 ) ),
					'category'        => sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) ),
					'expected_result' => sanitize_textarea_field( wp_unslash( $_POST['expected_result'] ?? '' ) ),
				],
				get_current_user_id()
			);

			if ( is_wp_error( $result ) ) {
				return [ 'class' => 'notice-error', 'message' => $result->get_error_message() ];
			}
			return [ 'class' => 'notice-success', 'message' => __( 'Testszenario angelegt.', 'liebherr-interface-world' ) ];
		}

		return null;
	}

	private static function render_world_form(): void {
		echo '<h2>' . esc_html__( 'Neue Simulationswelt', 'liebherr-interface-world' ) . '</h2>';
		echo '<form method="post">';
		wp_nonce_field( self::NONCE_ACTION_WORLD, self::NONCE_NAME_WORLD );
		echo '<input type="hidden" name="liw_action" value="create_world" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="liw_world_name">' . esc_html__( 'Name', 'liebherr-interface-world' ) . '</label></th>';
		echo '<td><input type="text" id="liw_world_name" name="name" class="regular-text" required /></td></tr>';
		echo '<tr><th><label for="liw_world_scope">' . esc_html__( 'Geltungsbereich', 'liebherr-interface-world' ) . '</label></th>';
		echo '<td><input type="text" id="liw_world_scope" name="scope" class="regular-text" placeholder="' . esc_attr__( 'z. B. Händler-Rollout DACH', 'liebherr-interface-world' ) . '" /></td></tr>';
		echo '<tr><th><label for="liw_world_version">' . esc_html__( 'Version', 'liebherr-interface-world' ) . '</label></th>';
		echo '<td><input type="text" id="liw_world_version" name="version" class="regular-text" value="0.1.0" /></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Simulationswelt anlegen', 'liebherr-interface-world' ) );
		echo '</form>';
	}

	/**
	 * @return array<int, array<string, mixed>> Welten indiziert nach id (für die Scenario-Sektion).
	 */
	private static function render_worlds_table( int $selected_world_id ): array {
		$rows   = SimulationService::get_worlds();
		$by_id  = [];

		echo '<h2>' . esc_html__( 'Simulationswelten', 'liebherr-interface-world' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( [ 'Name', 'Geltungsbereich', 'Version', 'Status', '' ] as $column ) {
			echo '<th>' . esc_html( $column ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( [] === $rows ) {
			echo '<tr><td colspan="5">' . esc_html__( 'Noch keine Simulationswelten angelegt.', 'liebherr-interface-world' ) . '</td></tr>';
		}

		foreach ( $rows as $row ) {
			$id         = (int) $row['id'];
			$by_id[ $id ] = $row;
			$is_active  = ( $id === $selected_world_id );
			$view_url   = add_query_arg( [ 'page' => self::MENU_SLUG, 'world_id' => $id ], admin_url( 'admin.php' ) );

			printf(
				'<tr%s><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><a href="%s">%s</a></td></tr>',
				$is_active ? ' class="liw-active-row"' : '',
				esc_html( (string) $row['name'] ),
				esc_html( (string) ( $row['scope'] ?? '' ) ),
				esc_html( (string) $row['version'] ),
				esc_html( (string) $row['validation_status'] ),
				esc_url( $view_url ),
				esc_html__( 'Szenarien anzeigen', 'liebherr-interface-world' )
			);
		}
		echo '</tbody></table>';

		return $by_id;
	}

	private static function render_scenario_form( int $world_id ): void {
		echo '<h2>' . sprintf(
			/* translators: %d: interne ID der Simulationswelt. */
			esc_html__( 'Neues Testszenario (Welt #%d)', 'liebherr-interface-world' ),
			$world_id
		) . '</h2>';
		echo '<form method="post">';
		wp_nonce_field( self::NONCE_ACTION_SCENARIO, self::NONCE_NAME_SCENARIO );
		echo '<input type="hidden" name="liw_action" value="add_scenario" />';
		echo '<input type="hidden" name="world_id" value="' . esc_attr( (string) $world_id ) . '" />';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th><label for="liw_scenario_category">' . esc_html__( 'Kategorie', 'liebherr-interface-world' ) . '</label></th>';
		echo '<td><input type="text" id="liw_scenario_category" name="category" class="regular-text" required /></td></tr>';
		echo '<tr><th><label for="liw_scenario_expected">' . esc_html__( 'Erwartetes Ergebnis', 'liebherr-interface-world' ) . '</label></th>';
		echo '<td><textarea id="liw_scenario_expected" name="expected_result" class="large-text" rows="3"></textarea></td></tr>';
		echo '</tbody></table>';
		submit_button( __( 'Testszenario anlegen', 'liebherr-interface-world' ) );
		echo '</form>';
	}

	private static function render_scenarios_table( int $world_id, string $world_name ): void {
		$rows = SimulationService::get_scenarios_for_world( $world_id );

		echo '<h2>' . sprintf(
			/* translators: %s: Name der Simulationswelt. */
			esc_html__( 'Testszenarien – %s', 'liebherr-interface-world' ),
			esc_html( $world_name )
		) . '</h2>';
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( [ 'Kategorie', 'Erwartetes Ergebnis', 'Status', 'Letzter Lauf' ] as $column ) {
			echo '<th>' . esc_html( $column ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( [] === $rows ) {
			echo '<tr><td colspan="4">' . esc_html__( 'Noch keine Testszenarien für diese Welt.', 'liebherr-interface-world' ) . '</td></tr>';
		}

		foreach ( $rows as $row ) {
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_html( (string) $row['category'] ),
				esc_html( (string) ( $row['expected_result'] ?? '' ) ),
				esc_html( (string) $row['status'] ),
				esc_html( (string) ( $row['last_run_at'] ?? '—' ) )
			);
		}
		echo '</tbody></table>';
	}
}
