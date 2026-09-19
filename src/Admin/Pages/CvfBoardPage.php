<?php
/**
 * Liebherr World – Customer View Flow: Backoffice-Board (ADR-LIW-CVF-001 §6/§10, JW-Entscheid §9).
 *
 * Administriert den Durchstich im wp-admin: Feature-Flag + Vier-Augen-Schalter, Zugangscode setzen (nur Hash),
 * die veröffentlichte Version ansehen und – nach Änderung der Challenge-Schwierigkeit – als NEUE,
 * unveränderliche Version veröffentlichen (mit leichtgewichtiger Vier-Augen-Prüfung), Versionshistorie und
 * das Execution-Log. Zugriff über die CVF-Capability {@see Roles::CAP_ADMINISTER}.
 *
 * @package Liebherr\InterfaceWorld\Admin\Pages
 * @since   0.1.0-alpha.87
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin\Pages;

use Liebherr\InterfaceWorld\Cvf\AccessService;
use Liebherr\InterfaceWorld\Cvf\Flags;
use Liebherr\InterfaceWorld\Cvf\Roles;
use Liebherr\InterfaceWorld\Cvf\Runtime;
use Liebherr\InterfaceWorld\Cvf\SessionRepository;
use Liebherr\InterfaceWorld\Cvf\WorkflowRepository;
use Liebherr\InterfaceWorld\Cvf\WorkflowVersion;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class CvfBoardPage {

	public const MENU_SLUG = 'liw-cvf-board';

	private const A_FLAGS = 'liw_cvf_save_flags';
	private const A_CODE  = 'liw_cvf_set_code';
	private const A_PUB   = 'liw_cvf_publish';

	public static function register(): void {
		add_action( 'admin_post_' . self::A_FLAGS, [ self::class, 'handle_flags' ] );
		add_action( 'admin_post_' . self::A_CODE, [ self::class, 'handle_code' ] );
		add_action( 'admin_post_' . self::A_PUB, [ self::class, 'handle_publish' ] );
	}

	private static function guard(): void {
		if ( ! current_user_can( Roles::CAP_ADMINISTER ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'liebherr-interface-world' ) );
		}
	}

	private static function redirect( string $key, string $val ): void {
		wp_safe_redirect( add_query_arg( [ 'page' => self::MENU_SLUG, $key => $val ], admin_url( 'admin.php' ) ) );
		exit;
	}

	// ── Handler ───────────────────────────────────────────────────────────────
	public static function handle_flags(): void {
		self::guard();
		check_admin_referer( self::A_FLAGS );
		update_option( Flags::OPT_ENABLED, isset( $_POST['enabled'] ) ? 1 : 0 );      // phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer oben.
		update_option( Flags::OPT_FOUR_EYES, isset( $_POST['four_eyes'] ) ? 1 : 0 );  // phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::redirect( 'updated', 'flags' );
	}

	public static function handle_code(): void {
		self::guard();
		check_admin_referer( self::A_CODE );
		$code = isset( $_POST['access_code'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['access_code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' === $code ) {
			self::redirect( 'error', 'empty_code' );
		}
		AccessService::set_code( $code );
		self::redirect( 'updated', 'code' );
	}

	public static function handle_publish(): void {
		self::guard();
		check_admin_referer( self::A_PUB );
		$diff    = isset( $_POST['difficulty'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['difficulty'] ) ) : 'double'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$active  = WorkflowRepository::ensure_active();
		$config  = WorkflowVersion::set_challenge_difficulty( $active['config'], $diff );
		$res     = WorkflowRepository::publish_guarded( $config, get_current_user_id(), Flags::four_eyes() );
		self::redirect( $res['ok'] ? 'updated' : 'error', $res['ok'] ? 'published' : $res['reason'] );
	}

	// ── Ansicht ───────────────────────────────────────────────────────────────
	public static function render(): void {
		self::guard();
		$active = WorkflowRepository::ensure_active();
		$diff   = Runtime::challenge_difficulty( $active['config'] );
		$post   = admin_url( 'admin-post.php' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( '🧭 Customer View Flow', 'liebherr-interface-world' ); ?></h1>
			<?php self::notice(); ?>
			<p><?php echo esc_html__( 'Administriert den geführten Besucher-Durchstich (Eingang → Challenge → Modulauswahl → First-Entry). Standardmäßig deaktiviert – der bestehende Eintritt bleibt unberührt, bis der Flag gesetzt ist.', 'liebherr-interface-world' ); ?></p>

			<h2><?php echo esc_html__( 'Status & Flags', 'liebherr-interface-world' ); ?></h2>
			<form method="post" action="<?php echo esc_url( $post ); ?>">
				<?php wp_nonce_field( self::A_FLAGS ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::A_FLAGS ); ?>" />
				<p>
					<label><input type="checkbox" name="enabled" value="1" <?php checked( Flags::enabled() ); ?> /> <?php echo esc_html__( 'Customer View Flow aktiv (liw_cvf_enabled)', 'liebherr-interface-world' ); ?></label><br />
					<label><input type="checkbox" name="four_eyes" value="1" <?php checked( Flags::four_eyes() ); ?> /> <?php echo esc_html__( 'Vier-Augen-Freigabe beim Veröffentlichen verlangen', 'liebherr-interface-world' ); ?></label>
				</p>
				<p><button type="submit" class="button button-primary"><?php echo esc_html__( 'Flags speichern', 'liebherr-interface-world' ); ?></button></p>
			</form>

			<h2><?php echo esc_html__( 'Zugangscode', 'liebherr-interface-world' ); ?></h2>
			<p><?php echo AccessService::is_configured()
				? esc_html__( 'Ein Zugangscode ist gesetzt (nur als Hash gespeichert).', 'liebherr-interface-world' )
				: esc_html__( 'Es ist noch kein Zugangscode gesetzt.', 'liebherr-interface-world' ); ?></p>
			<form method="post" action="<?php echo esc_url( $post ); ?>">
				<?php wp_nonce_field( self::A_CODE ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::A_CODE ); ?>" />
				<p><input type="text" name="access_code" class="regular-text" autocomplete="off" placeholder="<?php echo esc_attr__( 'Neuen Zugangscode eingeben', 'liebherr-interface-world' ); ?>" /></p>
				<p><button type="submit" class="button"><?php echo esc_html__( 'Zugangscode setzen', 'liebherr-interface-world' ); ?></button></p>
			</form>

			<h2><?php echo esc_html__( 'Veröffentlichte Version', 'liebherr-interface-world' ); ?></h2>
			<p>
				<strong><?php echo esc_html( (string) $active['version'] ); ?></strong>
				— <?php echo esc_html__( 'Prüfsumme', 'liebherr-interface-world' ); ?>: <code><?php echo esc_html( substr( (string) $active['checksum'], 0, 16 ) ); ?>…</code>
			</p>
			<form method="post" action="<?php echo esc_url( $post ); ?>">
				<?php wp_nonce_field( self::A_PUB ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::A_PUB ); ?>" />
				<p>
					<label for="liw-cvf-diff"><?php echo esc_html__( 'Challenge-Schwierigkeit', 'liebherr-interface-world' ); ?></label><br />
					<select id="liw-cvf-diff" name="difficulty">
						<option value="single" <?php selected( 'single', $diff ); ?>><?php echo esc_html__( 'Einstellig (1–9)', 'liebherr-interface-world' ); ?></option>
						<option value="double" <?php selected( 'double', $diff ); ?>><?php echo esc_html__( 'Zweistellig (10–99)', 'liebherr-interface-world' ); ?></option>
					</select>
				</p>
				<p class="description"><?php echo esc_html__( 'Veröffentlichen legt eine neue, unveränderliche Version an. Bei aktiver Vier-Augen-Freigabe muss der Freigebende ein anderer sein als der letzte Veröffentlicher.', 'liebherr-interface-world' ); ?></p>
				<p><button type="submit" class="button button-primary"><?php echo esc_html__( 'Als neue Version veröffentlichen', 'liebherr-interface-world' ); ?></button></p>
			</form>

			<h2><?php echo esc_html__( 'Versionshistorie', 'liebherr-interface-world' ); ?></h2>
			<?php self::history_table(); ?>

			<h2><?php echo esc_html__( 'Execution-Log (jüngste Übergänge)', 'liebherr-interface-world' ); ?></h2>
			<?php self::log_table(); ?>
		</div>
		<?php
	}

	private static function notice(): void {
		$updated = isset( $_GET['updated'] ) ? sanitize_key( (string) $_GET['updated'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error   = isset( $_GET['error'] ) ? sanitize_key( (string) $_GET['error'] ) : '';     // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$map     = [
			'flags'     => __( 'Flags gespeichert.', 'liebherr-interface-world' ),
			'code'      => __( 'Zugangscode gesetzt.', 'liebherr-interface-world' ),
			'published' => __( 'Neue Version veröffentlicht.', 'liebherr-interface-world' ),
		];
		$emap = [
			'four_eyes_same_person' => __( 'Vier-Augen-Freigabe: Der Freigebende darf nicht der letzte Veröffentlicher sein.', 'liebherr-interface-world' ),
			'empty_code'            => __( 'Bitte einen Zugangscode eingeben.', 'liebherr-interface-world' ),
			'invalid_config'        => __( 'Ungültige Konfiguration – nicht veröffentlicht.', 'liebherr-interface-world' ),
		];
		if ( '' !== $updated && isset( $map[ $updated ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $map[ $updated ] ) . '</p></div>';
		}
		if ( '' !== $error ) {
			$msg = $emap[ $error ] ?? __( 'Aktion fehlgeschlagen.', 'liebherr-interface-world' );
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}
	}

	private static function history_table(): void {
		$rows = WorkflowRepository::history( 20 );
		if ( 0 === count( $rows ) ) {
			echo '<p>' . esc_html__( 'Noch keine Versionen.', 'liebherr-interface-world' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>'
			. '<th>' . esc_html__( 'Version', 'liebherr-interface-world' ) . '</th>'
			. '<th>' . esc_html__( 'Status', 'liebherr-interface-world' ) . '</th>'
			. '<th>' . esc_html__( 'Prüfsumme', 'liebherr-interface-world' ) . '</th>'
			. '<th>' . esc_html__( 'Veröffentlicht', 'liebherr-interface-world' ) . '</th>'
			. '<th>' . esc_html__( 'Durch', 'liebherr-interface-world' ) . '</th>'
			. '</tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$user = (int) ( $r['published_by'] ?? 0 );
			$name = $user > 0 ? get_the_author_meta( 'display_name', $user ) : '—';
			echo '<tr>'
				. '<td><strong>' . esc_html( (string) $r['version'] ) . '</strong></td>'
				. '<td>' . esc_html( (string) $r['state'] ) . '</td>'
				. '<td><code>' . esc_html( substr( (string) $r['checksum'], 0, 16 ) ) . '…</code></td>'
				. '<td>' . esc_html( (string) ( $r['published_at'] ?? '' ) ) . '</td>'
				. '<td>' . esc_html( (string) $name ) . '</td>'
				. '</tr>';
		}
		echo '</tbody></table>';
	}

	private static function log_table(): void {
		$rows = SessionRepository::recent_log( 30 );
		if ( 0 === count( $rows ) ) {
			echo '<p>' . esc_html__( 'Noch keine Übergänge protokolliert.', 'liebherr-interface-world' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr>'
			. '<th>' . esc_html__( 'Zeit', 'liebherr-interface-world' ) . '</th>'
			. '<th>' . esc_html__( 'Sitzung', 'liebherr-interface-world' ) . '</th>'
			. '<th>' . esc_html__( 'Ereignis', 'liebherr-interface-world' ) . '</th>'
			. '<th>' . esc_html__( 'Übergang', 'liebherr-interface-world' ) . '</th>'
			. '<th>' . esc_html__( 'Aktion', 'liebherr-interface-world' ) . '</th>'
			. '<th>' . esc_html__( 'Ergebnis', 'liebherr-interface-world' ) . '</th>'
			. '</tr></thead><tbody>';
		foreach ( $rows as $r ) {
			echo '<tr>'
				. '<td>' . esc_html( (string) ( $r['created_at'] ?? '' ) ) . '</td>'
				. '<td>#' . (int) ( $r['session_id'] ?? 0 ) . '</td>'
				. '<td>' . esc_html( (string) ( $r['event'] ?? '' ) ) . '</td>'
				. '<td>' . esc_html( (string) ( $r['from_state'] ?? '' ) ) . ' → ' . esc_html( (string) ( $r['to_state'] ?? '' ) ) . '</td>'
				. '<td>' . esc_html( (string) ( $r['action'] ?? '' ) ) . '</td>'
				. '<td>' . esc_html( (string) ( $r['reason'] ?? '' ) ) . '</td>'
				. '</tr>';
		}
		echo '</tbody></table>';
	}
}
