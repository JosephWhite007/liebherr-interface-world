<?php
/**
 * Liebherr World – CAPDB Tabellenansicht/Editor (Pflichtenheft §6, §24.2, §29).
 *
 * Der VERPFLICHTENDE nicht-visuelle Bearbeitungsweg: Entwurf erzeugen/verwerfen, Bereiche/Übergänge/
 * Plugin-Instanzen + Zeitsteuerung als Tabellen pflegen, validieren, unveränderlich veröffentlichen und auf
 * eine frühere Version zurückrollen. Das visuelle Timeline-Board (spätere Etappe) baut auf denselben
 * Repository-/Validierungs-Operationen auf. Bearbeiten: {@see Roles::CAP_EDIT_WORKFLOW}; Veröffentlichen/
 * Rollback zusätzlich {@see Roles::CAP_PUBLISH}. Ein admin-post-Dispatch (`op`) mit Nonce.
 *
 * @package Liebherr\InterfaceWorld\Admin\Pages
 * @since   0.1.0-alpha.91
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin\Pages;

use Liebherr\InterfaceWorld\Cvf\BoardRepository;
use Liebherr\InterfaceWorld\Cvf\BoardSnapshot;
use Liebherr\InterfaceWorld\Cvf\BoardValidator;
use Liebherr\InterfaceWorld\Cvf\Flags;
use Liebherr\InterfaceWorld\Cvf\PluginRegistry;
use Liebherr\InterfaceWorld\Cvf\PluginTaxonomy;
use Liebherr\InterfaceWorld\Cvf\Roles;
use Liebherr\InterfaceWorld\Cvf\Schema;
use Liebherr\InterfaceWorld\Frontend\WorldSwitcher;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class CvfBoardEditorPage {

	public const MENU_SLUG = 'liw-cvf-board-editor';
	private const ACTION   = 'liw_cvf_board_op';
	private const NONCE    = 'liw_cvf_board_op';

	public const HANDLE = 'liw-cvf-board';
	private const AJAX  = 'liw_cvf_board_ajax';

	public static function register(): void {
		add_action( 'admin_post_' . self::ACTION, [ self::class, 'handle' ] );
		add_action( 'wp_ajax_' . self::AJAX, [ self::class, 'ajax' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	private static function ver( string $rel ): string {
		$m = is_readable( LIW_PATH . $rel ) ? (int) filemtime( LIW_PATH . $rel ) : 0;
		return $m > 0 ? (string) $m : LIW_VERSION;
	}

	public static function enqueue( string $hook ): void {
		if ( false === strpos( $hook, self::MENU_SLUG ) ) {
			return; // nur auf der Editor-Seite.
		}
		wp_enqueue_style( self::HANDLE, LIW_URL . 'assets/css/liw-cvf-board.css', [], self::ver( 'assets/css/liw-cvf-board.css' ) );
		wp_enqueue_script( self::HANDLE, LIW_URL . 'assets/js/liw-cvf-board.js', [], self::ver( 'assets/js/liw-cvf-board.js' ), true );
		wp_localize_script( self::HANDLE, 'liwCvfBoard', [
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'action'=> self::AJAX,
			'nonce' => wp_create_nonce( self::AJAX ),
			'i18n'  => [
				'baukasten'  => __( 'Modulbaukasten', 'liebherr-interface-world' ),
				'pageZone'   => __( 'Seiten-Plugins', 'liebherr-interface-world' ),
				'edgeZone'   => __( 'Übergangs-Plugins', 'liebherr-interface-world' ),
				'dropHere'   => __( 'Plugin hierher ziehen', 'liebherr-interface-world' ),
				'addKeyboard'=> __( 'Hinzufügen', 'liebherr-interface-world' ),
				'forbidden'  => __( 'Unzulässige Zielzone für diesen Plugin-Typ.', 'liebherr-interface-world' ),
				'remove'     => __( 'Entfernen', 'liebherr-interface-world' ),
				'zoomIn'     => __( 'Vergrößern', 'liebherr-interface-world' ),
				'zoomOut'    => __( 'Verkleinern', 'liebherr-interface-world' ),
				'empty'      => __( 'Kein Board – zuerst Bereiche anlegen.', 'liebherr-interface-world' ),
				'simTitle'   => __( 'Simulation (Abspielkopf)', 'liebherr-interface-world' ),
				'play'       => __( 'Start', 'liebherr-interface-world' ),
				'pause'      => __( 'Pause', 'liebherr-interface-world' ),
				'step'       => __( 'Schritt', 'liebherr-interface-world' ),
				'reset'      => __( 'Zurücksetzen', 'liebherr-interface-world' ),
				'simNote'    => __( 'Reine Vorschau – es werden keine echten Freigaben, Nachrichten oder Aktionen ausgeführt.', 'liebherr-interface-world' ),
			],
		] );
	}

	/** AJAX-Dispatcher für das visuelle Board (Snapshot lesen, Instanz hinzufügen/entfernen). */
	public static function ajax(): void {
		if ( ! self::can_edit() || ! check_ajax_referer( self::AJAX, 'nonce', false ) ) {
			wp_send_json_error( [ 'reason' => 'forbidden' ], 403 );
		}
		$op    = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( (string) $_POST['op'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$draft = BoardRepository::ensure_draft( get_current_user_id() );

		if ( 'snapshot' === $op ) {
			wp_send_json_success( self::board_data( $draft ) );
		}
		if ( 'add_instance' === $op ) {
			$key     = isset( $_POST['plugin_key'] ) ? sanitize_key( wp_unslash( (string) $_POST['plugin_key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$host    = isset( $_POST['host_type'] ) ? sanitize_key( wp_unslash( (string) $_POST['host_type'] ) ) : '';    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$host_id = isset( $_POST['host_id'] ) ? (int) $_POST['host_id'] : 0;                                          // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$type_id = PluginRegistry::type_id( $key );
			$defs    = PluginRegistry::definitions();
			if ( 0 === $type_id || ! isset( $defs[ $key ] ) || ! PluginTaxonomy::scope_allowed( $host, (string) $defs[ $key ]['scopes'] ) ) {
				wp_send_json_error( [ 'reason' => 'forbidden_scope' ], 400 );
			}
			$config = PluginRegistry::with_defaults( $key, [] );
			BoardRepository::add_instance( $draft, $type_id, $host, $host_id, 'configured', 100, (string) wp_json_encode( $config ) );
			wp_send_json_success( self::board_data( $draft ) );
		}
		if ( 'del_instance' === $op ) {
			BoardRepository::delete_instance( $draft, isset( $_POST['instance_id'] ) ? (int) $_POST['instance_id'] : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_send_json_success( self::board_data( $draft ) );
		}
		if ( 'sim' === $op ) {
			wp_send_json_success( [ 'events' => self::build_sim( $draft ) ] );
		}
		wp_send_json_error( [ 'reason' => 'unknown_op' ], 400 );
	}

	/**
	 * Baut den chronologischen Simulations-Plan (Abspielkopf): open/close-Marker aller Plugin-Instanzen je
	 * Host, zeitsortiert. REINE Vorschau über {@see BoardRuntime} – führt KEINE echten Grants/Aktionen aus (§27.1).
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function build_sim( int $draft ): array {
		$snap   = BoardSnapshot::of_version( $draft );
		$events = [];
		$hosts  = [];
		foreach ( (array) $snap['areas'] as $a ) {
			$hosts[] = [ 'type' => 'page', 'id' => (int) $a['id'], 'label' => 'Seite #' . (int) $a['position'] . ' ' . (string) $a['module_id'] ];
		}
		foreach ( (array) $snap['edges'] as $e ) {
			$hosts[] = [ 'type' => 'edge', 'id' => (int) $e['id'], 'label' => 'Übergang #' . (int) $e['from_area_id'] . '→#' . (int) $e['to_area_id'] ];
		}
		foreach ( $hosts as $h ) {
			$plugins = \Liebherr\InterfaceWorld\Cvf\BoardRuntime::plugins_for( $snap, $h['type'], $h['id'] );
			$markers = \Liebherr\InterfaceWorld\Cvf\BoardRuntime::marker_plan( $plugins, (array) $snap['schedules'] );
			foreach ( $markers as $m ) {
				$events[] = [ 'at_ms' => (int) $m['at_ms'], 'type' => (string) $m['type'], 'plugin_key' => (string) $m['plugin_key'], 'host' => (string) $h['label'] ];
			}
		}
		usort( $events, static fn( $a, $b ) => ( $a['at_ms'] <=> $b['at_ms'] ) );
		return $events;
	}

	/** @return array<string,mixed> Board-Daten des Entwurfs für das visuelle Board (inkl. Typen-Bibliothek). */
	private static function board_data( int $draft ): array {
		$snap  = BoardSnapshot::of_version( $draft );
		$types = [];
		foreach ( PluginRegistry::definitions() as $k => $d ) {
			$types[] = [ 'key' => $k, 'label' => (string) $d['label'], 'category' => (string) $d['category'], 'scopes' => (string) $d['scopes'] ];
		}
		$snap['types'] = $types;
		$snap['draft'] = $draft;
		return $snap;
	}

	private static function can_edit(): bool {
		return current_user_can( Roles::CAP_EDIT_WORKFLOW ) || current_user_can( 'manage_options' );
	}
	private static function can_publish(): bool {
		return current_user_can( Roles::CAP_PUBLISH ) || current_user_can( 'manage_options' );
	}

	private static function redirect( string $key, string $val ): void {
		wp_safe_redirect( add_query_arg( [ 'page' => self::MENU_SLUG, $key => rawurlencode( $val ) ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Ein admin-post-Dispatcher für alle Board-Operationen. */
	public static function handle(): void {
		if ( ! self::can_edit() ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'liebherr-interface-world' ) );
		}
		check_admin_referer( self::NONCE );
		$op   = isset( $_POST['op'] ) ? sanitize_key( wp_unslash( (string) $_POST['op'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security
		$uid  = get_current_user_id();
		$draft = BoardRepository::ensure_draft( $uid );

		switch ( $op ) {
			case 'add_area':
				BoardRepository::add_area( $draft, sanitize_text_field( (string) ( $post['module_id'] ?? '' ) ), (int) ( $post['position'] ?? 0 ), esc_url_raw( (string) ( $post['route_id'] ?? '' ) ), 'active', '' );
				self::redirect( 'updated', 'area_added' );
				break;
			case 'del_area':
				BoardRepository::delete_area( $draft, (int) ( $post['area_id'] ?? 0 ) );
				self::redirect( 'updated', 'area_deleted' );
				break;
			case 'add_edge':
				BoardRepository::add_edge( $draft, (int) ( $post['from_area_id'] ?? 0 ), (int) ( $post['to_area_id'] ?? 0 ), sanitize_key( (string) ( $post['trigger_type'] ?? 'manual' ) ), '', (int) ( $post['priority'] ?? 100 ) );
				self::redirect( 'updated', 'edge_added' );
				break;
			case 'del_edge':
				BoardRepository::delete_edge( $draft, (int) ( $post['edge_id'] ?? 0 ) );
				self::redirect( 'updated', 'edge_deleted' );
				break;
			case 'add_instance':
				self::op_add_instance( $draft, $post );
				break;
			case 'del_instance':
				BoardRepository::delete_instance( $draft, (int) ( $post['instance_id'] ?? 0 ) );
				self::redirect( 'updated', 'instance_deleted' );
				break;
			case 'set_schedule':
				BoardRepository::set_schedule( (int) ( $post['instance_id'] ?? 0 ), [
					'time_origin'   => sanitize_text_field( (string) ( $post['time_origin'] ?? 'page.entered' ) ),
					'open_at_ms'    => self::secs_to_ms( $post['open_at'] ?? '' ),
					'close_at_ms'   => self::secs_to_ms( $post['close_at'] ?? '' ),
					'duration_ms'   => self::secs_to_ms( $post['duration'] ?? '' ),
					'timeout_ms'    => self::secs_to_ms( $post['timeout'] ?? '' ),
					'repeat_policy' => sanitize_key( (string) ( $post['repeat_policy'] ?? 'once_per_version' ) ),
					'resume_policy' => sanitize_key( (string) ( $post['resume_policy'] ?? 'continue' ) ),
				] );
				self::redirect( 'updated', 'schedule_saved' );
				break;
			case 'discard':
				BoardRepository::discard_draft( $draft );
				self::redirect( 'updated', 'discarded' );
				break;
			case 'publish':
				if ( ! self::can_publish() ) { self::redirect( 'error', 'no_publish_right' ); }
				$res = BoardRepository::publish_draft( $draft, $uid, Flags::four_eyes() );
				self::redirect( $res['ok'] ? 'updated' : 'error', $res['ok'] ? 'published' : $res['reason'] );
				break;
			case 'rollback':
				if ( ! self::can_publish() ) { self::redirect( 'error', 'no_publish_right' ); }
				$res = BoardRepository::rollback_to( (int) ( $post['version_id'] ?? 0 ), $uid, Flags::four_eyes() );
				self::redirect( $res['ok'] ? 'updated' : 'error', $res['ok'] ? 'rolled_back' : $res['reason'] );
				break;
			default:
				self::redirect( 'error', 'unknown_op' );
		}
	}

	/** @param array<string,mixed> $post */
	private static function op_add_instance( int $draft, array $post ): void {
		$key      = sanitize_key( (string) ( $post['plugin_key'] ?? '' ) );
		$type_id  = PluginRegistry::type_id( $key );
		$host     = sanitize_key( (string) ( $post['host_type'] ?? '' ) );
		$host_id  = (int) ( $post['host_id'] ?? 0 );
		if ( 0 === $type_id || ! PluginTaxonomy::is_scope( $host ) ) {
			self::redirect( 'error', 'bad_instance' );
		}
		$config = [];
		$raw    = trim( (string) ( $post['config_json'] ?? '' ) );
		if ( '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) { $config = $decoded; }
		}
		$config   = PluginRegistry::with_defaults( $key, $config );
		$problems = PluginRegistry::validate_config( $key, $config );
		if ( count( $problems ) > 0 ) {
			self::redirect( 'error', 'config:' . implode( ',', $problems ) );
		}
		BoardRepository::add_instance( $draft, $type_id, $host, $host_id, 'configured', (int) ( $post['priority'] ?? 100 ), (string) wp_json_encode( $config ) );
		self::redirect( 'updated', 'instance_added' );
	}

	private static function secs_to_ms( $v ): ?int {
		$v = trim( (string) $v );
		return ( '' === $v || ! is_numeric( $v ) ) ? null : (int) round( ( (float) $v ) * 1000 );
	}

	// ── Ansicht ───────────────────────────────────────────────────────────────
	public static function render(): void {
		if ( ! self::can_edit() ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'liebherr-interface-world' ) );
		}
		$uid       = get_current_user_id();
		$draft     = BoardRepository::ensure_draft( $uid );
		$areas     = BoardRepository::areas( $draft );
		$edges     = BoardRepository::edges( $draft );
		$instances = BoardRepository::instances( $draft );
		$problems  = BoardValidator::validate( $draft );
		$post_url  = admin_url( 'admin-post.php' );
		$worlds    = WorldSwitcher::worlds();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( '🧭 CVF Board – Tabellenansicht', 'liebherr-interface-world' ); ?></h1>
			<?php self::notice(); ?>
			<p><?php echo esc_html__( 'Verpflichtende Tabellenansicht des Workflow-Boards. Änderungen betreffen nur den Entwurf; erst „Veröffentlichen" macht sie wirksam.', 'liebherr-interface-world' ); ?></p>
			<p>
				<strong><?php echo esc_html__( 'Entwurf', 'liebherr-interface-world' ); ?> #<?php echo (int) $draft; ?></strong>
				· <?php echo esc_html__( 'Aktive Version', 'liebherr-interface-world' ); ?>: #<?php echo (int) BoardRepository::published_id(); ?>
				· <?php echo count( $problems ) > 0
					? '<span style="color:#b32d2e">' . esc_html__( 'Validierung: ', 'liebherr-interface-world' ) . esc_html( implode( ', ', $problems ) ) . '</span>'
					: '<span style="color:#1a7f37">' . esc_html__( 'Validierung: gültig', 'liebherr-interface-world' ) . '</span>'; ?>
			</p>

			<?php // Lifecycle. ?>
			<form method="post" action="<?php echo esc_url( $post_url ); ?>" style="display:inline">
				<?php wp_nonce_field( self::NONCE ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<input type="hidden" name="op" value="publish" />
				<button class="button button-primary" <?php disabled( count( $problems ) > 0 ); ?>><?php echo esc_html__( 'Entwurf veröffentlichen', 'liebherr-interface-world' ); ?></button>
			</form>
			<form method="post" action="<?php echo esc_url( $post_url ); ?>" style="display:inline" onsubmit="return confirm('<?php echo esc_js( __( 'Entwurf verwerfen?', 'liebherr-interface-world' ) ); ?>');">
				<?php wp_nonce_field( self::NONCE ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
				<input type="hidden" name="op" value="discard" />
				<button class="button"><?php echo esc_html__( 'Entwurf verwerfen', 'liebherr-interface-world' ); ?></button>
			</form>

			<h2><?php echo esc_html__( 'Visuelles Board', 'liebherr-interface-world' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Plugin-Typen aus dem Modulbaukasten per Drag-and-Drop auf die Zonen ziehen (oder per Tastatur über „Hinzufügen"). Änderungen betreffen den Entwurf.', 'liebherr-interface-world' ); ?></p>
			<div class="liw-board" data-liw-board><p><?php echo esc_html__( 'Board wird geladen …', 'liebherr-interface-world' ); ?></p></div>

			<h2><?php echo esc_html__( 'Bereiche (Timeline)', 'liebherr-interface-world' ); ?></h2>
			<table class="widefat striped"><thead><tr>
				<th><?php echo esc_html__( 'Pos', 'liebherr-interface-world' ); ?></th>
				<th><?php echo esc_html__( 'Modul', 'liebherr-interface-world' ); ?></th>
				<th><?php echo esc_html__( 'Route', 'liebherr-interface-world' ); ?></th>
				<th><?php echo esc_html__( 'ID', 'liebherr-interface-world' ); ?></th>
				<th></th>
			</tr></thead><tbody>
			<?php foreach ( $areas as $a ) : ?>
				<tr>
					<td><?php echo (int) $a['position']; ?></td>
					<td><?php echo esc_html( (string) $a['module_id'] ); ?></td>
					<td><?php echo esc_html( (string) ( $a['route_id'] ?? '' ) ); ?></td>
					<td>#<?php echo (int) $a['id']; ?></td>
					<td><?php self::mini_op( $post_url, 'del_area', [ 'area_id' => (int) $a['id'] ], __( 'Löschen', 'liebherr-interface-world' ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
			<form method="post" action="<?php echo esc_url( $post_url ); ?>">
				<?php wp_nonce_field( self::NONCE ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" /><input type="hidden" name="op" value="add_area" />
				<select name="module_id">
					<?php foreach ( $worlds as $k => $w ) : ?>
						<option value="<?php echo esc_attr( (string) $k ); ?>"><?php echo esc_html( (string) $w['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="number" name="position" value="<?php echo (int) ( count( $areas ) + 1 ); ?>" style="width:70px" />
				<input type="url" name="route_id" placeholder="<?php echo esc_attr__( 'Route/URL (optional)', 'liebherr-interface-world' ); ?>" class="regular-text" />
				<button class="button"><?php echo esc_html__( 'Bereich hinzufügen', 'liebherr-interface-world' ); ?></button>
			</form>

			<h2><?php echo esc_html__( 'Übergänge', 'liebherr-interface-world' ); ?></h2>
			<table class="widefat striped"><thead><tr>
				<th><?php echo esc_html__( 'Von → Zu', 'liebherr-interface-world' ); ?></th>
				<th><?php echo esc_html__( 'Trigger', 'liebherr-interface-world' ); ?></th>
				<th><?php echo esc_html__( 'Priorität', 'liebherr-interface-world' ); ?></th>
				<th><?php echo esc_html__( 'ID', 'liebherr-interface-world' ); ?></th><th></th>
			</tr></thead><tbody>
			<?php foreach ( $edges as $e ) : ?>
				<tr>
					<td>#<?php echo (int) $e['from_area_id']; ?> → #<?php echo (int) $e['to_area_id']; ?></td>
					<td><?php echo esc_html( (string) $e['trigger_type'] ); ?></td>
					<td><?php echo (int) $e['priority']; ?></td>
					<td>#<?php echo (int) $e['id']; ?></td>
					<td><?php self::mini_op( $post_url, 'del_edge', [ 'edge_id' => (int) $e['id'] ], __( 'Löschen', 'liebherr-interface-world' ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
			<form method="post" action="<?php echo esc_url( $post_url ); ?>">
				<?php wp_nonce_field( self::NONCE ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" /><input type="hidden" name="op" value="add_edge" />
				<?php echo esc_html__( 'von', 'liebherr-interface-world' ); ?> <?php self::area_select( 'from_area_id', $areas ); ?>
				<?php echo esc_html__( 'zu', 'liebherr-interface-world' ); ?> <?php self::area_select( 'to_area_id', $areas ); ?>
				<input type="text" name="trigger_type" value="world_granted" style="width:130px" />
				<input type="number" name="priority" value="100" style="width:70px" />
				<button class="button"><?php echo esc_html__( 'Übergang hinzufügen', 'liebherr-interface-world' ); ?></button>
			</form>

			<h2><?php echo esc_html__( 'Plugin-Instanzen', 'liebherr-interface-world' ); ?></h2>
			<table class="widefat striped"><thead><tr>
				<th><?php echo esc_html__( 'Typ', 'liebherr-interface-world' ); ?></th>
				<th><?php echo esc_html__( 'Host', 'liebherr-interface-world' ); ?></th>
				<th><?php echo esc_html__( 'Status', 'liebherr-interface-world' ); ?></th>
				<th><?php echo esc_html__( 'Konfig', 'liebherr-interface-world' ); ?></th>
				<th><?php echo esc_html__( 'Zeit (Sek.)', 'liebherr-interface-world' ); ?></th><th></th>
			</tr></thead><tbody>
			<?php foreach ( $instances as $ins ) : $sched = BoardRepository::schedule( (int) $ins['id'] ); ?>
				<tr>
					<td><?php echo esc_html( self::type_key( (int) $ins['plugin_type_id'] ) ); ?></td>
					<td><?php echo esc_html( (string) $ins['host_type'] ); ?> #<?php echo (int) $ins['host_id']; ?></td>
					<td><?php echo esc_html( (string) $ins['status'] ); ?></td>
					<td><code><?php echo esc_html( self::short( (string) ( $ins['config_json'] ?? '' ) ) ); ?></code></td>
					<td>
						<form method="post" action="<?php echo esc_url( $post_url ); ?>" class="liw-cvf-sched">
							<?php wp_nonce_field( self::NONCE ); ?>
							<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" /><input type="hidden" name="op" value="set_schedule" /><input type="hidden" name="instance_id" value="<?php echo (int) $ins['id']; ?>" />
							<?php echo esc_html__( 'auf', 'liebherr-interface-world' ); ?> <input type="number" step="0.1" name="open_at" value="<?php echo esc_attr( self::ms_to_secs( $sched['open_at_ms'] ?? null ) ); ?>" style="width:60px" placeholder="open" />
							<?php echo esc_html__( 'zu', 'liebherr-interface-world' ); ?> <input type="number" step="0.1" name="close_at" value="<?php echo esc_attr( self::ms_to_secs( $sched['close_at_ms'] ?? null ) ); ?>" style="width:60px" placeholder="close" />
							<button class="button button-small"><?php echo esc_html__( 'Zeit', 'liebherr-interface-world' ); ?></button>
						</form>
					</td>
					<td><?php self::mini_op( $post_url, 'del_instance', [ 'instance_id' => (int) $ins['id'] ], __( 'Löschen', 'liebherr-interface-world' ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody></table>
			<form method="post" action="<?php echo esc_url( $post_url ); ?>">
				<?php wp_nonce_field( self::NONCE ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" /><input type="hidden" name="op" value="add_instance" />
				<select name="plugin_key">
					<?php foreach ( PluginRegistry::definitions() as $k => $d ) : ?>
						<option value="<?php echo esc_attr( (string) $k ); ?>"><?php echo esc_html( (string) $d['label'] . ' (' . $d['scopes'] . ')' ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="host_type"><option value="edge">edge</option><option value="page">page</option></select>
				<input type="number" name="host_id" placeholder="<?php echo esc_attr__( 'Host-ID (Bereich/Übergang)', 'liebherr-interface-world' ); ?>" style="width:150px" />
				<input type="text" name="config_json" placeholder='{"difficulty":"double"}' class="regular-text" />
				<button class="button"><?php echo esc_html__( 'Plugin hinzufügen', 'liebherr-interface-world' ); ?></button>
			</form>

			<h2><?php echo esc_html__( 'Veröffentlichte Versionen / Rollback', 'liebherr-interface-world' ); ?></h2>
			<?php self::versions_table( $post_url ); ?>
		</div>
		<?php
	}

	/** @param array<int,array<string,mixed>> $areas */
	private static function area_select( string $name, array $areas ): void {
		echo '<select name="' . esc_attr( $name ) . '">';
		foreach ( $areas as $a ) {
			echo '<option value="' . (int) $a['id'] . '">#' . (int) $a['id'] . ' ' . esc_html( (string) $a['module_id'] ) . '</option>';
		}
		echo '</select>';
	}

	/** @param array<string,int> $fields */
	private static function mini_op( string $url, string $op, array $fields, string $label ): void {
		echo '<form method="post" action="' . esc_url( $url ) . '" style="display:inline">';
		wp_nonce_field( self::NONCE );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" /><input type="hidden" name="op" value="' . esc_attr( $op ) . '" />';
		foreach ( $fields as $k => $v ) {
			echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . (int) $v . '" />';
		}
		echo '<button class="button-link-delete button-link">' . esc_html( $label ) . '</button></form>';
	}

	private static function versions_table( string $post_url ): void {
		$rows = \Liebherr\InterfaceWorld\Cvf\WorkflowRepository::history( 15 );
		echo '<table class="widefat striped"><thead><tr><th>Version</th><th>Status</th><th>Prüfsumme</th><th>Veröffentlicht</th><th></th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$published = 'published' === (string) $r['state'];
			echo '<tr><td>' . esc_html( (string) $r['version'] ) . ' (#' . (int) $r['id'] . ')</td><td>' . esc_html( (string) $r['state'] ) . '</td><td><code>' . esc_html( substr( (string) $r['checksum'], 0, 12 ) ) . '</code></td><td>' . esc_html( (string) ( $r['published_at'] ?? '' ) ) . '</td><td>';
			if ( $published ) {
				echo '<form method="post" action="' . esc_url( $post_url ) . '" style="display:inline" onsubmit="return confirm(\'' . esc_js( __( 'Auf diese Version zurückrollen?', 'liebherr-interface-world' ) ) . '\');">';
				wp_nonce_field( self::NONCE );
				echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '" /><input type="hidden" name="op" value="rollback" /><input type="hidden" name="version_id" value="' . (int) $r['id'] . '" />';
				echo '<button class="button button-small">' . esc_html__( 'Rollback', 'liebherr-interface-world' ) . '</button></form>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function type_key( int $type_id ): string {
		foreach ( PluginRegistry::all_types() as $t ) {
			if ( (int) $t['id'] === $type_id ) { return (string) $t['plugin_key']; }
		}
		return '#' . $type_id;
	}

	private static function short( string $s ): string {
		return strlen( $s ) > 48 ? substr( $s, 0, 48 ) . '…' : $s;
	}
	private static function ms_to_secs( $ms ): string {
		return ( null === $ms || '' === $ms ) ? '' : (string) ( (int) $ms / 1000 );
	}

	private static function notice(): void {
		$u = isset( $_GET['updated'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['updated'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$e = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['error'] ) ) : '';     // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' !== $u ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Gespeichert: ', 'liebherr-interface-world' ) . esc_html( $u ) . '</p></div>';
		}
		if ( '' !== $e ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Fehler: ', 'liebherr-interface-world' ) . esc_html( $e ) . '</p></div>';
		}
	}
}
