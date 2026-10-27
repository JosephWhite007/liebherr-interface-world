<?php
/**
 * Liebherr World – Dealer Workspace: Frontend (ARY-PH-AB-1.0 §5.2, Phase 3)
 *
 * Shortcode `[liw_dealer_workspace]` auf der Seite /dealer-workspace/: eigene Login-Maske (Core WorkspaceAuth, Rolle
 * araliya_dealer_user), danach Reiter Übersicht · Adressen · Ansprechpartner · Schnittstellen · Team · Anleitung mit
 * denselben Daten und Rechten wie das Backend (Core-Repositories, Mandantengrenze). Serverseitig gerendert (Formulare,
 * GET-Parameter), kein JavaScript nötig; Liebherr-CI über `--brand-*`; `noindex`, `DONOTCACHEPAGE`.
 * Händler-Benutzer haben kein Backend – dies ist ihre einzige Oberfläche.
 *
 * @package Liebherr\InterfaceWorld\DealerWorkspace
 * @since   0.1.0-alpha.152
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\DealerWorkspace;

use Liebherr\InterfaceWorld\CoreBridge\AuditBridge;
use Liebherr\InterfaceWorld\SalesInterface\LivisionSync;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class WorkspaceView {

	public const SHORTCODE = 'liw_dealer_workspace';
	public const NONCE     = 'liw_dw';
	public const PER_PAGE  = 25;

	private const CORE_ADDR    = 'Araliya\\Platform\\Core\\Modules\\Addressbook\\AddressRepository';
	private const CORE_CONTACT = 'Araliya\\Platform\\Core\\Modules\\Addressbook\\ContactRepository';
	private const CORE_REF     = 'Araliya\\Platform\\Core\\Modules\\Addressbook\\ExternalRefRepository';
	private const CORE_SAN     = 'Araliya\\Platform\\Core\\Modules\\Addressbook\\AddressSanitizer';
	private const CORE_ART     = 'Araliya\\Platform\\Core\\Modules\\Articlebook\\ArticlebookRepository';
	private const CORE_GROUP   = 'Araliya\\Platform\\Core\\Modules\\Articlebook\\ArticleGroupRepository';
	private const CORE_SCOPE   = 'Araliya\\Platform\\Core\\Modules\\Articlebook\\ArticleTenantScope';

	/** @var array{class:string,text:string}|null */
	private static ?array $notice = null;

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'render' ] );
		add_action( 'template_redirect', [ self::class, 'handle_post' ] );
		add_filter( 'wp_robots', [ self::class, 'robots' ] );
		add_filter( 'araliya_ab_workspace_url', [ self::class, 'workspace_url' ] );
	}

	public static function workspace_url( $url ): string {
		$own = WorkspaceContext::page_url();
		return '' !== $own ? $own : (string) $url;
	}

	/** @param array<string,mixed> $robots */
	public static function robots( array $robots ): array {
		if ( self::on_page() ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
			unset( $robots['index'], $robots['follow'] );
		}
		return $robots;
	}

	private static function on_page(): bool {
		$post = get_post();
		return $post instanceof \WP_Post && has_shortcode( (string) $post->post_content, self::SHORTCODE );
	}

	// ── POST-Verarbeitung (vor der Ausgabe, damit Redirects möglich sind) ───────────────────────

	public static function handle_post(): void {
		if ( ! self::on_page() ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
		$action = isset( $_POST['dw_action'] ) ? sanitize_key( wp_unslash( (string) $_POST['dw_action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( '' === $action || ! WorkspaceContext::available() ) {
			return;
		}
		if ( ! isset( $_POST['_dwnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['_dwnonce'] ) ), self::NONCE ) ) {
			self::$notice = [ 'class' => 'error', 'text' => __( 'Sitzung abgelaufen – bitte erneut senden.', 'liebherr-interface-world' ) ];
			return;
		}
		$auth = WorkspaceContext::CORE_AUTH;
		if ( 'login' === $action ) {
			$user = $auth::attempt_login( (string) wp_unslash( $_POST['login'] ?? '' ), (string) wp_unslash( $_POST['password'] ?? '' ), ! empty( $_POST['remember'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Core sanitisiert; Passwort darf nicht verändert werden.
			if ( $user instanceof \WP_Error ) {
				self::$notice = [ 'class' => 'error', 'text' => $user->get_error_message() ];
				return;
			}
			wp_safe_redirect( WorkspaceContext::url( 'overview' ) );
			exit;
		}
		if ( 'logout' === $action ) {
			$auth::logout();
			wp_safe_redirect( WorkspaceContext::url() );
			exit;
		}
		if ( ! WorkspaceContext::logged_in() ) {
			return;
		}
		$tenant = WorkspaceContext::CORE_TENANT;
		$org    = WorkspaceContext::org();
		$actor  = get_current_user_id();
		$M      = WorkspaceContext::CORE_MODULE;
		switch ( $action ) {
			case 'switch_org':
				$tenant::set_active_org( (int) ( $_POST['org_id'] ?? 0 ) );
				wp_safe_redirect( WorkspaceContext::url( 'overview' ) );
				exit;
			case 'save_address':
				$data = wp_unslash( $_POST['address'] ?? [] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Core-Repository sanitisiert.
				$data = is_array( $data ) ? $data : [];
				$id   = (int) ( $_POST['id'] ?? 0 );
				$res  = $id > 0 ? ( self::CORE_ADDR )::update( $org, $id, $data, $actor ) : ( self::CORE_ADDR )::create( $org, $data, $actor );
				if ( $res instanceof \WP_Error ) {
					self::$notice = [ 'class' => 'error', 'text' => $res->get_error_message() ];
					return;
				}
				if ( class_exists( 'Araliya\\Platform\\Core\\Admin\\Pages\\AddressbookPage' ) ) {
					\Araliya\Platform\Core\Admin\Pages\AddressbookPage::save_tags_from_post( $org, $id > 0 ? $id : (int) $res, $data['tags'] ?? null, $actor ); // [alpha.156] Merkmale (BV-1)
				}
				wp_safe_redirect( WorkspaceContext::url( 'address', [ 'id' => $id > 0 ? $id : (int) $res, 'saved' => 1 ] ) );
				exit;
			case 'set_status':
				$res = ( self::CORE_ADDR )::set_status( $org, (int) ( $_POST['id'] ?? 0 ), sanitize_key( wp_unslash( (string) ( $_POST['status'] ?? '' ) ) ), $actor );
				self::$notice = $res instanceof \WP_Error ? [ 'class' => 'error', 'text' => $res->get_error_message() ] : [ 'class' => 'ok', 'text' => __( 'Status geändert.', 'liebherr-interface-world' ) ];
				return;
			case 'save_contact':
				$data = wp_unslash( $_POST['contact'] ?? [] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Core-Repository sanitisiert.
				$res  = ( self::CORE_CONTACT )::save( $org, is_array( $data ) ? $data : [], $actor );
				if ( $res instanceof \WP_Error ) {
					self::$notice = [ 'class' => 'error', 'text' => $res->get_error_message() ];
					return;
				}
				wp_safe_redirect( WorkspaceContext::url( 'address', [ 'id' => (int) ( $data['address_id'] ?? 0 ), 'saved' => 1 ] ) );
				exit;
			case 'save_article':
				if ( ! class_exists( self::CORE_SCOPE ) || ! $tenant::can( (int) ( $_POST['id'] ?? 0 ) > 0 ? $M::CAP_EDIT : $M::CAP_CREATE, $org ) ) {
					self::$notice = [ 'class' => 'error', 'text' => __( 'Keine Berechtigung für Artikel.', 'liebherr-interface-world' ) ];
					return;
				}
				$data = wp_unslash( $_POST['article'] ?? [] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Core-Repository sanitisiert.
				$data = is_array( $data ) ? $data : [];
				$id   = (int) ( $_POST['id'] ?? 0 );
				$res  = ( self::CORE_SCOPE )::with( $org, static function () use ( $data, $id ) {
					$type = in_array( $data['type'] ?? '', ( self::CORE_SCOPE )::DEALER_TYPES, true ) ? (string) $data['type'] : 'physical';
					$row  = [
						'type' => $type, 'name' => (string) ( $data['name'] ?? '' ), 'article_number' => (string) ( $data['article_number'] ?? '' ), 'ean' => (string) ( $data['ean'] ?? '' ),
						'description' => (string) ( $data['description'] ?? '' ), 'short_description' => (string) ( $data['short_description'] ?? '' ), 'price' => (float) ( $data['price'] ?? 0 ),
						'currency' => (string) ( $data['currency'] ?? 'EUR' ), 'status' => (string) ( $data['status'] ?? 'draft' ), 'group_key' => (string) ( $data['group_key'] ?? '' ),
						'tax_rate_bp' => '' === (string) ( $data['tax_rate_bp'] ?? '' ) ? null : (int) $data['tax_rate_bp'],
						'stock_qty' => 'service' === $type || '' === (string) ( $data['stock_qty'] ?? '' ) ? null : (int) $data['stock_qty'],
						'is_sellable' => 1, 'is_stock_managed' => 'physical' === $type && '' !== (string) ( $data['stock_qty'] ?? '' ) ? 1 : 0,
					];
					if ( '' === trim( $row['name'] ) ) {
						return new \WP_Error( 'liw_article_name', __( 'Name ist Pflicht.', 'liebherr-interface-world' ) );
					}
					if ( $id > 0 ) {
						if ( null === ( self::CORE_ART )::get_by_id( $id ) ) {
							return new \WP_Error( 'liw_article_missing', __( 'Artikel nicht gefunden.', 'liebherr-interface-world' ) );
						}
						return ( self::CORE_ART )::update( $id, $row ) ? $id : new \WP_Error( 'liw_article_update', __( 'Artikel konnte nicht gespeichert werden (Nummer bereits vergeben?).', 'liebherr-interface-world' ) );
					}
					$new = ( self::CORE_ART )::create( $row );
					return $new > 0 ? $new : new \WP_Error( 'liw_article_create', __( 'Artikel konnte nicht angelegt werden (Nummer bereits vergeben?).', 'liebherr-interface-world' ) );
				} );
				if ( $res instanceof \WP_Error ) {
					self::$notice = [ 'class' => 'error', 'text' => $res->get_error_message() ];
					return;
				}
				if ( AuditBridge::is_available() ) {
					AuditBridge::log( $id > 0 ? 'update' : 'create', 'dealer_article', (int) $res, [], [ 'org_id' => $org ], $actor );
				}
				wp_safe_redirect( WorkspaceContext::url( 'article', [ 'id' => (int) $res, 'saved' => 1 ] ) );
				exit;
			case 'adopt_template':
				// [alpha.156] BV-0: Modell aus der Liebherr-Produktliste als Artikel übernehmen.
				if ( ! $tenant::can( $M::CAP_CREATE, $org ) ) {
					self::$notice = [ 'class' => 'error', 'text' => __( 'Keine Berechtigung für Artikel.', 'liebherr-interface-world' ) ];
					return;
				}
				$res = \Liebherr\InterfaceWorld\SalesInterface\ProductTemplateService::adopt( $org, (int) ( $_POST['product_id'] ?? 0 ), $actor );
				if ( $res instanceof \WP_Error ) {
					self::$notice = [ 'class' => 'error', 'text' => $res->get_error_message() ];
					return;
				}
				if ( AuditBridge::is_available() ) {
					AuditBridge::log( 'create', 'dealer_article', (int) $res, [], [ 'org_id' => $org, 'template' => (int) ( $_POST['product_id'] ?? 0 ) ], $actor );
				}
				self::$notice = [ 'class' => 'ok', 'text' => sprintf( /* translators: %d: Artikel-ID */ __( 'Vorlage übernommen – Artikel #%d angelegt.', 'liebherr-interface-world' ), (int) $res ) ];
				return;
			case 'save_group':
				if ( ! class_exists( self::CORE_SCOPE ) || ! $tenant::can( $M::CAP_CREATE, $org ) ) {
					return;
				}
				$label = sanitize_text_field( wp_unslash( (string) ( $_POST['label'] ?? '' ) ) );
				$key   = 'o' . $org . '-' . sanitize_title( $label ); // Gruppenschlüssel global eindeutig, je Mandant präfixiert
				$gid   = '' !== $label ? ( self::CORE_SCOPE )::with( $org, static fn() => ( self::CORE_GROUP )::create( [ 'group_key' => $key, 'label' => $label ] ) ) : 0;
				self::$notice = $gid > 0 ? [ 'class' => 'ok', 'text' => __( 'Gruppe angelegt.', 'liebherr-interface-world' ) ] : [ 'class' => 'error', 'text' => __( 'Gruppe konnte nicht angelegt werden (leer oder vorhanden).', 'liebherr-interface-world' ) ];
				return;
			case 'ack':
			case 'save_insight':
			case 'add_need':
			case 'need_done':
			case 'need_reopen':
			case 'seed_rules':
			case 'apply_rules':
			case 'add_rule':
			case 'delete_rule':
			case 'tag_accept':
			case 'tag_reject':
			case 'add_industry':
			case 'save_view':
			case 'vote_view':
			case 'delete_view':
				// [alpha.155] Action-Board-Aktionen (Quittung, Ideenbaum, Bedarfsmarker) laufen über die Core-Seite – eine Logik, zwei Oberflächen.
				if ( class_exists( 'Araliya\\Platform\\Core\\Admin\\Pages\\AddressbookActionPage' ) ) {
					$r = \Araliya\Platform\Core\Admin\Pages\AddressbookActionPage::handle_shared_action( $action, $org, $actor, wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Core sanitisiert.
					if ( null !== $r ) {
						self::$notice = $r;
					}
				}
				return;
			case 'push_livision':
				$kind = 'contact' === ( $_POST['kind'] ?? '' ) ? 'contact' : 'address';
				$res  = LivisionSync::push( $org, $kind, (int) ( $_POST['id'] ?? 0 ), $actor );
				self::$notice = $res['ok'] ? [ 'class' => 'ok', 'text' => $res['message'] ] : [ 'class' => 'error', 'text' => $res['message'] ];
				return;
			case 'add_member':
				if ( ! $tenant::can( $M::CAP_MANAGE_TEAM, $org ) ) {
					return;
				}
				$email = sanitize_email( wp_unslash( (string) ( $_POST['email'] ?? '' ) ) );
				$user  = '' !== $email ? get_user_by( 'email', $email ) : false;
				if ( ! $user ) {
					$login = sanitize_user( wp_unslash( (string) ( $_POST['login'] ?? '' ) ), true );
					if ( '' === $email || '' === $login ) {
						self::$notice = [ 'class' => 'error', 'text' => __( 'E-Mail und Benutzername sind Pflicht.', 'liebherr-interface-world' ) ];
						return;
					}
					$uid = wp_insert_user( [ 'user_login' => $login, 'user_email' => $email, 'display_name' => sanitize_text_field( wp_unslash( (string) ( $_POST['display'] ?? $login ) ) ), 'user_pass' => wp_generate_password( 24, true ), 'role' => $M::ROLE_DEALER_USER ] );
					if ( $uid instanceof \WP_Error ) {
						self::$notice = [ 'class' => 'error', 'text' => $uid->get_error_message() ];
						return;
					}
					wp_new_user_notification( (int) $uid, null, 'user' );
					$user = get_userdata( (int) $uid );
				} elseif ( ! in_array( $M::ROLE_DEALER_USER, (array) $user->roles, true ) ) {
					self::$notice = [ 'class' => 'error', 'text' => __( 'Dieses Konto kann nur der Operator zuordnen.', 'liebherr-interface-world' ) ];
					return;
				}
				$tenant::set_membership( $org, (int) $user->ID, sanitize_key( wp_unslash( (string) ( $_POST['function'] ?? 'dealer_viewer' ) ) ), $actor );
				if ( AuditBridge::is_available() ) {
					AuditBridge::log( 'member_set', 'dealer_workspace', (int) $user->ID, [], [ 'org_id' => $org ], $actor );
				}
				self::$notice = [ 'class' => 'ok', 'text' => __( 'Teammitglied gespeichert.', 'liebherr-interface-world' ) ];
				return;
			case 'save_alias':
			case 'delete_alias':
			case 'csp_reconcile':
				// [alpha.157] Customer Solution Provider – Betreuer-Zuordnung/Abgleich über die Core-Seite.
				if ( class_exists( 'Araliya\\Platform\\Core\\Admin\\Pages\\AddressbookTeamPage' ) ) {
					$r = \Araliya\Platform\Core\Admin\Pages\AddressbookTeamPage::handle_csp_action( $action, $org, $actor, wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Core sanitisiert.
					if ( null !== $r ) {
						self::$notice = $r;
					}
				}
				return;
			case 'end_member':
				$uid = (int) ( $_POST['user_id'] ?? 0 );
				if ( $tenant::can( $M::CAP_MANAGE_TEAM, $org ) && $uid !== $actor ) {
					$tenant::end_membership( $org, $uid );
					self::$notice = [ 'class' => 'ok', 'text' => __( 'Mitgliedschaft beendet.', 'liebherr-interface-world' ) ];
				}
				return;
		}
	}

	// ── Ausgabe ─────────────────────────────────────────────────────────────

	public static function render(): string {
		if ( ! WorkspaceContext::available() ) {
			return '<div class="liw-dw"><p>' . esc_html__( 'Dealer Workspace ist nicht verfügbar (Core-Addressbook fehlt).', 'liebherr-interface-world' ) . '</p></div>';
		}
		$out = '<div class="liw-dw" id="liw-dw">';
		if ( null !== self::$notice ) {
			$out .= '<p class="liw-dw__notice liw-dw__notice--' . esc_attr( self::$notice['class'] ) . '" role="status">' . esc_html( self::$notice['text'] ) . '</p>';
		}
		if ( ! WorkspaceContext::logged_in() ) {
			return $out . self::login_form() . '</div>';
		}
		$org = WorkspaceContext::org();
		if ( $org <= 0 ) {
			return $out . '<p class="liw-dw__notice liw-dw__notice--error">' . esc_html__( 'Diesem Konto ist kein Händler zugeordnet. Bitte an den Operator wenden.', 'liebherr-interface-world' ) . '</p>' . self::logout_form() . '</div>';
		}
		$tab = isset( $_GET['ws'] ) ? sanitize_key( wp_unslash( (string) $_GET['ws'] ) ) : 'overview'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$out .= self::header( $org, $tab );
		switch ( $tab ) {
			case 'addresses':
				$out .= self::addresses( $org );
				break;
			case 'address':
				$out .= self::address( $org, (int) ( $_GET['id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				break;
			case 'contact':
				$out .= self::contact( $org, (int) ( $_GET['id'] ?? 0 ), (int) ( $_GET['address_id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				break;
			case 'contacts':
				$out .= self::contacts( $org );
				break;
			case 'articles':
				$out .= self::articles( $org );
				break;
			case 'article':
				$out .= self::article( $org, (int) ( $_GET['id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				break;
			case 'actionboard':
				$out .= self::actionboard( $org );
				break;
			case 'interfaces':
				$out .= self::interfaces( $org );
				break;
			case 'team':
				$out .= self::team( $org );
				break;
			case 'guide':
				$out .= self::guide();
				break;
			default:
				$out .= self::overview( $org );
		}
		return $out . '</div>';
	}

	private static function nonce(): string {
		return '<input type="hidden" name="_dwnonce" value="' . esc_attr( wp_create_nonce( self::NONCE ) ) . '" />';
	}

	private static function login_form(): string {
		$auth = WorkspaceContext::CORE_AUTH;
		$out  = '<section class="liw-dw__login"><h1 class="liw-dw__title">' . esc_html__( 'Dealer Workspace', 'liebherr-interface-world' ) . '</h1>';
		$out .= '<p class="liw-dw__lead">' . esc_html__( 'Anmeldung für Händler-Benutzer. Kundenstammdaten, Ansprechpartner und Schnittstellen Ihres Hauses.', 'liebherr-interface-world' ) . '</p>';
		$out .= '<form method="post" class="liw-dw__form liw-dw__form--login" action="' . esc_url( WorkspaceContext::url() ) . '">' . self::nonce() . '<input type="hidden" name="dw_action" value="login" />';
		$out .= '<label class="liw-dw__field"><span>' . esc_html__( 'Benutzername oder E-Mail', 'liebherr-interface-world' ) . '</span><input type="text" name="login" autocomplete="username" required /></label>';
		$out .= '<label class="liw-dw__field"><span>' . esc_html__( 'Passwort', 'liebherr-interface-world' ) . '</span><input type="password" name="password" autocomplete="current-password" required /></label>';
		$out .= '<label class="liw-dw__check"><input type="checkbox" name="remember" value="1" /> ' . esc_html__( 'Angemeldet bleiben', 'liebherr-interface-world' ) . '</label>';
		$out .= '<button type="submit" class="liw-dw__button">' . esc_html__( 'Anmelden', 'liebherr-interface-world' ) . '</button>';
		$out .= '<p class="liw-dw__meta"><a href="' . esc_url( wp_lostpassword_url( WorkspaceContext::url() ) ) . '">' . esc_html__( 'Passwort vergessen?', 'liebherr-interface-world' ) . '</a> · ' . esc_html( sprintf( /* translators: %d: Versuche */ __( 'Nach %d Fehlversuchen wird die Anmeldung 15 Minuten gesperrt.', 'liebherr-interface-world' ), (int) $auth::MAX_FAILS ) ) . '</p>';
		return $out . '</form></section>';
	}

	private static function logout_form(): string {
		return '<form method="post" class="liw-dw__inline" action="' . esc_url( WorkspaceContext::url() ) . '">' . self::nonce() . '<input type="hidden" name="dw_action" value="logout" /><button type="submit" class="liw-dw__link">' . esc_html__( 'Abmelden', 'liebherr-interface-world' ) . '</button></form>';
	}

	private static function header( int $org, string $tab ): string {
		$user = wp_get_current_user();
		$out  = '<header class="liw-dw__head"><div><h1 class="liw-dw__title">' . esc_html__( 'Dealer Workspace', 'liebherr-interface-world' ) . '</h1><p class="liw-dw__lead">' . esc_html( WorkspaceContext::org_name( $org ) ) . ' · ' . esc_html( (string) $user->display_name ) . ' <span class="liw-dw__role">' . esc_html( self::role_label( WorkspaceContext::role() ) ) . '</span></p></div><div class="liw-dw__headactions">';
		$orgs = WorkspaceContext::orgs();
		if ( count( $orgs ) > 1 ) {
			$out .= '<form method="post" class="liw-dw__inline" action="' . esc_url( WorkspaceContext::url() ) . '">' . self::nonce() . '<input type="hidden" name="dw_action" value="switch_org" /><select name="org_id">';
			foreach ( $orgs as $o ) {
				$out .= '<option value="' . (int) $o['id'] . '"' . selected( $org, (int) $o['id'], false ) . '>' . esc_html( $o['name'] ) . '</option>';
			}
			$out .= '</select> <button type="submit" class="liw-dw__button liw-dw__button--small">' . esc_html__( 'Wechseln', 'liebherr-interface-world' ) . '</button></form>';
		}
		$out .= self::logout_form() . '</div></header>';
		$tabs = [ 'overview' => __( 'Übersicht', 'liebherr-interface-world' ), 'addresses' => __( 'Adressen', 'liebherr-interface-world' ), 'contacts' => __( 'Ansprechpartner', 'liebherr-interface-world' ), 'articles' => __( 'Artikel', 'liebherr-interface-world' ), 'actionboard' => __( 'Action Board', 'liebherr-interface-world' ), 'interfaces' => __( 'Schnittstellen', 'liebherr-interface-world' ), 'team' => __( 'Team', 'liebherr-interface-world' ), 'guide' => __( 'Anleitung', 'liebherr-interface-world' ) ];
		$out .= '<nav class="liw-dw__nav" aria-label="' . esc_attr__( 'Workspace', 'liebherr-interface-world' ) . '"><ul>';
		foreach ( $tabs as $k => $label ) {
			$active = $k === $tab || ( 'addresses' === $k && in_array( $tab, [ 'address', 'contact' ], true ) ) || ( 'articles' === $k && 'article' === $tab );
			$out   .= '<li><a' . ( $active ? ' class="is-active" aria-current="page"' : '' ) . ' href="' . esc_url( WorkspaceContext::url( $k ) ) . '">' . esc_html( $label ) . '</a></li>';
		}
		return $out . '</ul></nav>';
	}

	private static function overview( int $org ): string {
		$dealer = WorkspaceContext::dealer();
		$count  = ( self::CORE_ADDR )::count( $org );
		$tiles  = [
			[ 'addresses', __( 'Adressen', 'liebherr-interface-world' ), sprintf( /* translators: %s: Anzahl */ __( '%s Firmen – Kunden, Lieferanten, Interessenten', 'liebherr-interface-world' ), number_format_i18n( $count ) ) ],
			[ 'contacts', __( 'Ansprechpartner', 'liebherr-interface-world' ), __( 'Personen zu Ihren Adressen', 'liebherr-interface-world' ) ],
			[ 'interfaces', __( 'Schnittstellen', 'liebherr-interface-world' ), null === $dealer ? __( 'Kein Liebherr-Händlerdatensatz verknüpft', 'liebherr-interface-world' ) : sprintf( /* translators: %s: Status */ __( 'Liebherr Livision – Schreiben %s', 'liebherr-interface-world' ), $dealer['write_enabled'] ? __( 'freigegeben', 'liebherr-interface-world' ) : __( 'gesperrt', 'liebherr-interface-world' ) ) ],
			[ 'team', __( 'Team', 'liebherr-interface-world' ), __( 'Benutzer und Funktionen Ihres Hauses', 'liebherr-interface-world' ) ],
			[ 'guide', __( 'Anleitung', 'liebherr-interface-world' ), __( 'So arbeiten Sie mit dem Workspace', 'liebherr-interface-world' ) ],
		];
		$out = '<section class="liw-dw__section"><ul class="liw-dw__tiles" role="list">';
		foreach ( $tiles as [ $tab, $title, $text ] ) {
			$out .= '<li class="liw-dw__tile"><a href="' . esc_url( WorkspaceContext::url( $tab ) ) . '"><h2>' . esc_html( $title ) . '</h2><p>' . esc_html( $text ) . '</p></a></li>';
		}
		$count_art = class_exists( self::CORE_SCOPE ) ? (int) ( self::CORE_SCOPE )::with( $org, static fn() => ( self::CORE_ART )::count_by_status()['total'] ?? 0 ) : 0;
		$out .= '<li class="liw-dw__tile"><a href="' . esc_url( WorkspaceContext::url( 'articles' ) ) . '"><h2>' . esc_html__( 'Artikel', 'liebherr-interface-world' ) . '</h2><p>' . esc_html( sprintf( /* translators: %s: Anzahl */ __( '%s Artikel – Ware und Dienstleistungen Ihres Hauses', 'liebherr-interface-world' ), number_format_i18n( $count_art ) ) ) . '</p></a></li>';
		return $out . '</ul></section>';
	}

	private static function addresses( int $org ): string {
		$f = [
			'q'      => isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'status' => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( (string) $_GET['status'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'role'   => isset( $_GET['role'] ) ? sanitize_key( wp_unslash( (string) $_GET['role'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'tag'    => isset( $_GET['tag'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['tag'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'csp'    => (int) ( $_GET['csp'] ?? 0 ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'page'   => (int) ( $_GET['pg'] ?? 1 ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		];
		$res = ( self::CORE_ADDR )::search( $org, $f );
		$M   = WorkspaceContext::CORE_MODULE;
		$out = '<section class="liw-dw__section"><form method="get" class="liw-dw__filters" action="' . esc_url( WorkspaceContext::page_url() ) . '"><input type="hidden" name="ws" value="addresses" />';
		$out .= '<input type="search" name="q" value="' . esc_attr( $f['q'] ) . '" placeholder="' . esc_attr__( 'Nummer, Name, Ort, PLZ, E-Mail', 'liebherr-interface-world' ) . '" />';
		$out .= '<select name="status"><option value="">' . esc_html__( 'aktiv + inaktiv', 'liebherr-interface-world' ) . '</option>';
		foreach ( ( self::CORE_SAN )::STATUSES as $s ) {
			$out .= '<option value="' . esc_attr( $s ) . '"' . selected( $f['status'], $s, false ) . '>' . esc_html( self::status_label( $s ) ) . '</option>';
		}
		$out .= '</select><select name="role"><option value="">' . esc_html__( 'alle Rollen', 'liebherr-interface-world' ) . '</option>';
		foreach ( ( self::CORE_SAN )::ROLES as $r ) {
			$out .= '<option value="' . esc_attr( $r ) . '"' . selected( $f['role'], $r, false ) . '>' . esc_html( self::role_name( $r ) ) . '</option>';
		}
		$out .= '</select>';
		$tagrepo = 'Araliya\\Platform\\Core\\Modules\\Addressbook\\TagRepository';
		if ( class_exists( $tagrepo ) ) { // [alpha.156] Merkmal-Filter (BV-1)
			$out .= '<select name="tag"><option value="">' . esc_html__( 'alle Merkmale', 'liebherr-interface-world' ) . '</option><option value="none"' . selected( $f['tag'], 'none', false ) . '>' . esc_html__( 'ohne neue Branche', 'liebherr-interface-world' ) . '</option>';
			foreach ( $tagrepo::kind_labels() as $kind => $klabel ) {
				$out .= '<optgroup label="' . esc_attr( $klabel ) . '">';
				foreach ( $tagrepo::names( $kind, $org ) as $key => $name ) {
					$out .= '<option value="' . esc_attr( $kind . ':' . $key ) . '"' . selected( $f['tag'], $kind . ':' . $key, false ) . '>' . esc_html( $name ) . '</option>';
				}
				$out .= '</optgroup>';
			}
			$out .= '</select>';
		}
		$cspsvc = 'Araliya\\Platform\\Core\\Modules\\Addressbook\\CspService';
		if ( class_exists( $cspsvc ) ) { // [alpha.157] CSP-Filter
			$out .= '<select name="csp"><option value="0">' . esc_html__( 'alle CSP', 'liebherr-interface-world' ) . '</option><option value="' . get_current_user_id() . '"' . selected( $f['csp'], get_current_user_id(), false ) . '>' . esc_html__( 'meine Kunden', 'liebherr-interface-world' ) . '</option><option value="-1"' . selected( $f['csp'], -1, false ) . '>' . esc_html__( 'ohne CSP', 'liebherr-interface-world' ) . '</option>';
			foreach ( $cspsvc::team_options( $org ) as $uid => $name ) {
				$out .= '<option value="' . (int) $uid . '"' . selected( $f['csp'], $uid, false ) . '>' . esc_html( $name ) . '</option>';
			}
			$out .= '</select>';
		}
		$out .= '<button type="submit" class="liw-dw__button liw-dw__button--small">' . esc_html__( 'Filtern', 'liebherr-interface-world' ) . '</button>';
		if ( WorkspaceContext::can( $M::CAP_CREATE ) ) {
			$out .= ' <a class="liw-dw__button liw-dw__button--small" href="' . esc_url( WorkspaceContext::url( 'address' ) ) . '">+ ' . esc_html__( 'Adresse anlegen', 'liebherr-interface-world' ) . '</a>';
		}
		$out .= '</form><p class="liw-dw__meta">' . esc_html( sprintf( /* translators: 1: Anzahl 2: Seite 3: Seiten */ __( '%1$s Adressen · Seite %2$d von %3$d', 'liebherr-interface-world' ), number_format_i18n( $res['total'] ), $res['page'], max( 1, $res['pages'] ) ) ) . '</p>';
		$out .= '<div class="liw-dw__scroll"><table class="liw-dw__table"><thead><tr><th>' . esc_html__( 'Nummer', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Name', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'PLZ / Ort', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Rollen', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Status', 'liebherr-interface-world' ) . '</th></tr></thead><tbody>';
		if ( [] === $res['rows'] ) {
			$out .= '<tr><td colspan="5">' . esc_html__( 'Keine Adressen gefunden.', 'liebherr-interface-world' ) . '</td></tr>';
		}
		foreach ( $res['rows'] as $a ) {
			$url  = WorkspaceContext::url( 'address', [ 'id' => (int) $a['id'] ] );
			$out .= '<tr><td><a href="' . esc_url( $url ) . '"><code>' . esc_html( (string) $a['number'] ) . '</code></a></td><td><a href="' . esc_url( $url ) . '">' . esc_html( (string) $a['name1'] ) . '</a></td><td>' . esc_html( trim( (string) $a['postal_code'] . ' ' . (string) $a['city'] ) ) . '</td><td>' . esc_html( implode( ', ', array_map( [ self::class, 'role_name' ], array_filter( explode( ',', (string) ( $a['roles'] ?? '' ) ) ) ) ) ) . '</td><td>' . esc_html( self::status_label( (string) $a['status'] ) ) . '</td></tr>';
		}
		$out .= '</tbody></table></div>';
		if ( $res['pages'] > 1 ) {
			$out .= '<p class="liw-dw__pager">';
			for ( $p = 1; $p <= $res['pages']; $p++ ) {
				$out .= $p === $res['page'] ? '<strong>' . $p . '</strong> ' : '<a href="' . esc_url( WorkspaceContext::url( 'addresses', array_filter( [ 'q' => $f['q'], 'status' => $f['status'], 'role' => $f['role'], 'tag' => $f['tag'], 'csp' => $f['csp'] ?: '', 'pg' => $p ] ) ) ) . '">' . $p . '</a> ';
			}
			$out .= '</p>';
		}
		return $out . '</section>';
	}

	private static function address( int $org, int $id ): string {
		$M = WorkspaceContext::CORE_MODULE;
		$a = $id > 0 ? ( self::CORE_ADDR )::get( $org, $id ) : null;
		if ( $id > 0 && null === $a ) {
			return '<section class="liw-dw__section"><p>' . esc_html__( 'Adresse nicht gefunden.', 'liebherr-interface-world' ) . '</p></section>';
		}
		$can = WorkspaceContext::can( $id > 0 ? $M::CAP_EDIT : $M::CAP_CREATE );
		$a   = $a ?? [ 'roles' => [ 'customer' ], 'country' => 'DE', 'status' => 'active' ];
		$v   = static fn( string $k ): string => (string) ( $a[ $k ] ?? '' );
		$out = '<section class="liw-dw__section"><p class="liw-dw__meta"><a href="' . esc_url( WorkspaceContext::url( 'addresses' ) ) . '">← ' . esc_html__( 'Adressen', 'liebherr-interface-world' ) . '</a></p>';
		$out .= '<h2 class="liw-dw__h2">' . esc_html( $id > 0 ? $v( 'number' ) . ' – ' . $v( 'name1' ) : __( 'Neue Adresse', 'liebherr-interface-world' ) ) . '</h2>';
		if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$out .= '<p class="liw-dw__notice liw-dw__notice--ok">' . esc_html__( 'Gespeichert.', 'liebherr-interface-world' ) . '</p>';
		}
		$out .= '<form method="post" class="liw-dw__form" action="' . esc_url( WorkspaceContext::url( 'address', $id > 0 ? [ 'id' => $id ] : [] ) ) . '">' . self::nonce() . '<input type="hidden" name="dw_action" value="save_address" /><input type="hidden" name="id" value="' . (int) $id . '" />';
		$fields = [
			[ 'name1', __( 'Name 1 *', 'liebherr-interface-world' ), 191 ], [ 'name2', __( 'Name 2', 'liebherr-interface-world' ), 191 ], [ 'street', __( 'Straße', 'liebherr-interface-world' ), 191 ], [ 'street_add1', __( 'Zusatz', 'liebherr-interface-world' ), 191 ],
			[ 'postal_code', __( 'PLZ *', 'liebherr-interface-world' ), 20 ], [ 'city', __( 'Ort *', 'liebherr-interface-world' ), 120 ], [ 'country', __( 'Land (ISO2) *', 'liebherr-interface-world' ), 2 ], [ 'state', __( 'Bundesland (1B)', 'liebherr-interface-world' ), 20 ],
			[ 'phone', __( 'Telefon', 'liebherr-interface-world' ), 60 ], [ 'email', __( 'E-Mail', 'liebherr-interface-world' ), 191 ], [ 'website', __( 'Website', 'liebherr-interface-world' ), 255 ], [ 'vat_id', __( 'USt-ID', 'liebherr-interface-world' ), 30 ],
			[ 'class_abc', __( 'Klassifizierung (KL)', 'liebherr-interface-world' ), 20 ], [ 'industry1', __( 'Branche 1 (3B)', 'liebherr-interface-world' ), 20 ], [ 'industry2', __( 'Branche 2 (3B)', 'liebherr-interface-world' ), 20 ], [ 'address_info', __( 'Adressinfo (2K)', 'liebherr-interface-world' ), 20 ],
			[ 'sales_area', __( 'Verkaufsgebiet', 'liebherr-interface-world' ), 120 ], [ 'advisor', __( 'Betreuer', 'liebherr-interface-world' ), 191 ],
		];
		$out .= '<div class="liw-dw__grid">';
		foreach ( $fields as [ $n, $label, $max ] ) {
			$out .= '<label class="liw-dw__field"><span>' . esc_html( $label ) . '</span><input type="text" name="address[' . esc_attr( $n ) . ']" value="' . esc_attr( $v( $n ) ) . '" maxlength="' . (int) $max . '"' . ( $can ? '' : ' readonly' ) . ' /></label>';
		}
		$out .= '</div>';
		if ( class_exists( 'Araliya\\Platform\\Core\\Admin\\Pages\\AddressbookPage' ) ) { // [alpha.157] Customer Solution Provider
			ob_start();
			\Araliya\Platform\Core\Admin\Pages\AddressbookPage::csp_select( $org, 'address[csp_user_id]', (int) ( $a['csp_user_id'] ?? 0 ), $can, __( 'Customer Solution Provider (Verkäufer mit dem Wissen)', 'liebherr-interface-world' ), __( '– kein CSP –', 'liebherr-interface-world' ) );
			$out .= ob_get_clean();
		}
		$out .= '<p class="liw-dw__field"><span class="liw-dw__label">' . esc_html__( 'Rollen', 'liebherr-interface-world' ) . '</span>';
		foreach ( ( self::CORE_SAN )::ROLES as $r ) {
			$out .= '<label class="liw-dw__check"><input type="checkbox" name="address[roles][]" value="' . esc_attr( $r ) . '"' . checked( in_array( $r, (array) $a['roles'], true ), true, false ) . ( $can ? '' : ' disabled' ) . ' /> ' . esc_html( self::role_name( $r ) ) . '</label>';
		}
		$out .= '</p><label class="liw-dw__field"><span>' . esc_html__( 'Status', 'liebherr-interface-world' ) . '</span><select name="address[status]"' . ( $can ? '' : ' disabled' ) . '>';
		foreach ( ( self::CORE_SAN )::STATUSES as $s ) {
			$out .= '<option value="' . esc_attr( $s ) . '"' . selected( $v( 'status' ), $s, false ) . '>' . esc_html( self::status_label( $s ) ) . '</option>';
		}
		$out .= '</select></label>';
		if ( class_exists( 'Araliya\\Platform\\Core\\Admin\\Pages\\AddressbookPage' ) ) { // [alpha.156] Merkmale Branche · Anwendung · Potenzial (BV-1)
			$out .= '<details class="liw-dw__tags"><summary class="liw-dw__h3">' . esc_html__( 'Merkmale: Branche · Anwendung · Maschinenpotenzial', 'liebherr-interface-world' ) . '</summary>';
			ob_start();
			\Araliya\Platform\Core\Admin\Pages\AddressbookPage::render_tag_fields( $org, $id, $can );
			$out .= ob_get_clean() . '</details>';
		}
		if ( $can ) {
			$out .= '<button type="submit" class="liw-dw__button">' . esc_html__( 'Speichern', 'liebherr-interface-world' ) . '</button>';
		}
		$out .= '</form>';
		if ( $id > 0 ) {
			$out .= self::contacts_of( $org, $id );
			$out .= self::sync_box( $org, 'address', $id );
			if ( '' !== $v( 'notes_import' ) ) {
				$out .= '<p class="liw-dw__meta"><strong>' . esc_html__( 'Import-Hinweise:', 'liebherr-interface-world' ) . '</strong> ' . esc_html( $v( 'notes_import' ) ) . '</p>';
			}
		}
		return $out . '</section>';
	}

	private static function contacts_of( int $org, int $address_id ): string {
		$M   = WorkspaceContext::CORE_MODULE;
		$out = '<h3 class="liw-dw__h3">' . esc_html__( 'Ansprechpartner', 'liebherr-interface-world' ) . '</h3><div class="liw-dw__scroll"><table class="liw-dw__table"><thead><tr><th>' . esc_html__( 'Name', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Funktion', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'E-Mail', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Telefon', 'liebherr-interface-world' ) . '</th></tr></thead><tbody>';
		$list = ( self::CORE_CONTACT )::for_address( $org, $address_id );
		if ( [] === $list ) {
			$out .= '<tr><td colspan="4">' . esc_html__( 'Noch keine Ansprechpartner.', 'liebherr-interface-world' ) . '</td></tr>';
		}
		foreach ( $list as $c ) {
			$out .= '<tr><td><a href="' . esc_url( WorkspaceContext::url( 'contact', [ 'id' => (int) $c['id'] ] ) ) . '">' . esc_html( trim( (string) $c['first_name'] . ' ' . (string) $c['last_name'] ) ) . '</a></td><td>' . esc_html( (string) $c['function1'] ) . '</td><td>' . esc_html( (string) $c['email'] ) . '</td><td>' . esc_html( (string) ( '' !== (string) $c['mobile'] ? $c['mobile'] : $c['phone'] ) ) . '</td></tr>';
		}
		$out .= '</tbody></table></div>';
		if ( WorkspaceContext::can( $M::CAP_CREATE ) ) {
			$out .= '<p><a class="liw-dw__button liw-dw__button--small" href="' . esc_url( WorkspaceContext::url( 'contact', [ 'address_id' => $address_id ] ) ) . '">+ ' . esc_html__( 'Ansprechpartner anlegen', 'liebherr-interface-world' ) . '</a></p>';
		}
		return $out;
	}

	private static function contact( int $org, int $id, int $address_id ): string {
		$M = WorkspaceContext::CORE_MODULE;
		$c = $id > 0 ? ( self::CORE_CONTACT )::get( $org, $id ) : null;
		if ( $id > 0 && null === $c ) {
			return '<section class="liw-dw__section"><p>' . esc_html__( 'Ansprechpartner nicht gefunden.', 'liebherr-interface-world' ) . '</p></section>';
		}
		$address_id = $c ? (int) $c['address_id'] : $address_id;
		$addr       = ( self::CORE_ADDR )::get( $org, $address_id );
		if ( null === $addr ) {
			return '<section class="liw-dw__section"><p>' . esc_html__( 'Adresse nicht gefunden.', 'liebherr-interface-world' ) . '</p></section>';
		}
		$can = WorkspaceContext::can( $id > 0 ? $M::CAP_EDIT : $M::CAP_CREATE );
		$v   = static fn( string $k ): string => (string) ( $c[ $k ] ?? '' );
		$out = '<section class="liw-dw__section"><p class="liw-dw__meta"><a href="' . esc_url( WorkspaceContext::url( 'address', [ 'id' => $address_id ] ) ) . '">← ' . esc_html( (string) $addr['number'] . ' ' . (string) $addr['name1'] ) . '</a></p>';
		$out .= '<h2 class="liw-dw__h2">' . esc_html( $id > 0 ? trim( $v( 'first_name' ) . ' ' . $v( 'last_name' ) ) : __( 'Neuer Ansprechpartner', 'liebherr-interface-world' ) ) . '</h2>';
		$out .= '<form method="post" class="liw-dw__form" action="' . esc_url( WorkspaceContext::url( 'contact', $id > 0 ? [ 'id' => $id ] : [ 'address_id' => $address_id ] ) ) . '">' . self::nonce() . '<input type="hidden" name="dw_action" value="save_contact" /><input type="hidden" name="contact[id]" value="' . (int) $id . '" /><input type="hidden" name="contact[address_id]" value="' . (int) $address_id . '" /><div class="liw-dw__grid">';
		foreach ( [ [ 'salutation', __( 'Anrede (AH: F/H)', 'liebherr-interface-world' ), 10 ], [ 'title', __( 'Titel', 'liebherr-interface-world' ), 40 ], [ 'first_name', __( 'Vorname', 'liebherr-interface-world' ), 120 ], [ 'last_name', __( 'Nachname *', 'liebherr-interface-world' ), 120 ], [ 'function1', __( 'Funktion (FN)', 'liebherr-interface-world' ), 20 ], [ 'department', __( 'Abteilung (PV)', 'liebherr-interface-world' ), 20 ], [ 'email', __( 'E-Mail', 'liebherr-interface-world' ), 191 ], [ 'mobile', __( 'Mobil', 'liebherr-interface-world' ), 60 ], [ 'phone', __( 'Telefon', 'liebherr-interface-world' ), 60 ] ] as [ $n, $label, $max ] ) {
			$out .= '<label class="liw-dw__field"><span>' . esc_html( $label ) . '</span><input type="text" name="contact[' . esc_attr( $n ) . ']" value="' . esc_attr( $v( $n ) ) . '" maxlength="' . (int) $max . '"' . ( $can ? '' : ' readonly' ) . ' /></label>';
		}
		$out .= '</div>';
		if ( class_exists( 'Araliya\\Platform\\Core\\Admin\\Pages\\AddressbookPage' ) ) { // [alpha.157] Exklusivkontakt (CSP)
			ob_start();
			\Araliya\Platform\Core\Admin\Pages\AddressbookPage::csp_select( $org, 'contact[csp_user_id]', (int) ( $c['csp_user_id'] ?? 0 ), $can, __( 'Exklusivkontakt – Customer Solution Provider', 'liebherr-interface-world' ), __( '– erbt den Firmen-CSP –', 'liebherr-interface-world' ) );
			$out .= ob_get_clean();
		}
		$out .= '<label class="liw-dw__check"><input type="checkbox" name="contact[decider]" value="1"' . checked( ! empty( $c['decider'] ), true, false ) . ( $can ? '' : ' disabled' ) . ' /> ' . esc_html__( 'Entscheider', 'liebherr-interface-world' ) . '</label>';
		$out .= '<label class="liw-dw__field"><span>' . esc_html__( 'Notiz', 'liebherr-interface-world' ) . '</span><textarea name="contact[note]" rows="3"' . ( $can ? '' : ' readonly' ) . '>' . esc_textarea( $v( 'note' ) ) . '</textarea></label>';
		$out .= '<input type="hidden" name="contact[status]" value="' . esc_attr( $v( 'status' ) ?: 'active' ) . '" />';
		if ( $can ) {
			$out .= '<button type="submit" class="liw-dw__button">' . esc_html__( 'Speichern', 'liebherr-interface-world' ) . '</button>';
		}
		$out .= '</form>';
		if ( $id > 0 ) {
			$out .= self::sync_box( $org, 'contact', $id );
		}
		return $out . '</section>';
	}

	private static function contacts( int $org ): string {
		$q   = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$res = ( self::CORE_CONTACT )::search( $org, $q, (int) ( $_GET['pg'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$out = '<section class="liw-dw__section"><form method="get" class="liw-dw__filters" action="' . esc_url( WorkspaceContext::page_url() ) . '"><input type="hidden" name="ws" value="contacts" /><input type="search" name="q" value="' . esc_attr( $q ) . '" placeholder="' . esc_attr__( 'Name, E-Mail, Firma', 'liebherr-interface-world' ) . '" /><button type="submit" class="liw-dw__button liw-dw__button--small">' . esc_html__( 'Suchen', 'liebherr-interface-world' ) . '</button></form>';
		$out .= '<p class="liw-dw__meta">' . esc_html( sprintf( /* translators: %s: Anzahl */ __( '%s Ansprechpartner', 'liebherr-interface-world' ), number_format_i18n( $res['total'] ) ) ) . '</p><div class="liw-dw__scroll"><table class="liw-dw__table"><thead><tr><th>' . esc_html__( 'Name', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Firma', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Funktion', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'E-Mail', 'liebherr-interface-world' ) . '</th></tr></thead><tbody>';
		if ( [] === $res['rows'] ) {
			$out .= '<tr><td colspan="4">' . esc_html__( 'Keine Ansprechpartner gefunden.', 'liebherr-interface-world' ) . '</td></tr>';
		}
		foreach ( $res['rows'] as $c ) {
			$out .= '<tr><td><a href="' . esc_url( WorkspaceContext::url( 'contact', [ 'id' => (int) $c['id'] ] ) ) . '">' . esc_html( trim( (string) $c['first_name'] . ' ' . (string) $c['last_name'] ) ) . '</a></td><td><a href="' . esc_url( WorkspaceContext::url( 'address', [ 'id' => (int) $c['address_id'] ] ) ) . '">' . esc_html( (string) ( $c['address_name'] ?? '' ) ) . '</a></td><td>' . esc_html( (string) $c['function1'] ) . '</td><td>' . esc_html( (string) $c['email'] ) . '</td></tr>';
		}
		return $out . '</tbody></table></div></section>';
	}

	/** Livision-Block (Phase 4): Referenz, Sync-Status, Vorprüfung, Übertragen-Button. */
	private static function sync_box( int $org, string $kind, int $id ): string {
		$M      = WorkspaceContext::CORE_MODULE;
		$dealer = WorkspaceContext::dealer();
		$refs   = [];
		foreach ( ( self::CORE_REF )::for_entity( $org, $kind, $id ) as $r ) {
			$refs[ (string) $r['system_key'] ] = $r;
		}
		$out = '<h3 class="liw-dw__h3">' . esc_html__( 'Liebherr Livision', 'liebherr-interface-world' ) . '</h3>';
		if ( null === $dealer ) {
			return $out . '<p class="liw-dw__meta">' . esc_html__( 'Kein Liebherr-Händlerdatensatz mit diesem Mandanten verknüpft (Operator: Dealer Board → Org-ID).', 'liebherr-interface-world' ) . '</p>';
		}
		$ref  = $refs['livision'] ?? null;
		$out .= '<p class="liw-dw__meta">' . esc_html( null === $ref ? __( 'Noch nicht an Livision übertragen.', 'liebherr-interface-world' ) : sprintf( /* translators: 1: ID 2: Status 3: Zeit */ __( 'Livision-ID %1$s · Status %2$s · %3$s', 'liebherr-interface-world' ), (string) $ref['external_id'], (string) ( $ref['sync_status'] ?: '–' ), (string) ( $ref['synced_at'] ?: '–' ) ) . ( ! empty( $ref['sync_error'] ) ? ' · ' . (string) $ref['sync_error'] : '' ) ) . '</p>';
		$check = LivisionSync::precheck( $org, $kind, $id );
		if ( [] !== $check['warnings'] ) {
			$out .= '<ul class="liw-dw__warnings">';
			foreach ( $check['warnings'] as $w ) {
				$out .= '<li>' . esc_html( $w ) . '</li>';
			}
			$out .= '</ul>';
		}
		if ( $check['ok'] && $dealer['write_enabled'] && WorkspaceContext::can( $M::CAP_EDIT ) ) {
			$out .= '<form method="post" class="liw-dw__inline" action="' . esc_url( WorkspaceContext::url( 'address' === $kind ? 'address' : 'contact', [ 'id' => $id ] ) ) . '">' . self::nonce() . '<input type="hidden" name="dw_action" value="push_livision" /><input type="hidden" name="kind" value="' . esc_attr( $kind ) . '" /><input type="hidden" name="id" value="' . (int) $id . '" /><button type="submit" class="liw-dw__button liw-dw__button--small">' . esc_html( null === $ref ? __( 'An Livision übertragen (anlegen)', 'liebherr-interface-world' ) : __( 'An Livision übertragen (aktualisieren)', 'liebherr-interface-world' ) ) . '</button></form>';
		} elseif ( ! $dealer['write_enabled'] ) {
			$out .= '<p class="liw-dw__meta">' . esc_html__( 'Schreibschalter des Händlers ist aus (Dealer Board).', 'liebherr-interface-world' ) . '</p>';
		}
		return $out;
	}

	/** Reiter Artikel: Liste der Händler-Artikel (Scope = Mandant), Gruppen anlegen (P5). */
	private static function articles( int $org ): string {
		if ( ! class_exists( self::CORE_SCOPE ) ) {
			return '<section class="liw-dw__section"><p>' . esc_html__( 'Articlebook nicht verfügbar.', 'liebherr-interface-world' ) . '</p></section>';
		}
		$M     = WorkspaceContext::CORE_MODULE;
		$q     = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type  = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( (string) $_GET['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$rows  = ( self::CORE_SCOPE )::with( $org, static fn() => ( self::CORE_ART )::get_all( array_filter( [ 'type' => in_array( $type, ( self::CORE_SCOPE )::DEALER_TYPES, true ) ? $type : null, 'limit' => 500 ] ) ) );
		$groups = ( self::CORE_SCOPE )::with( $org, static fn() => ( self::CORE_GROUP )::get_all( false ) );
		$glabel = [];
		foreach ( $groups as $g ) {
			$glabel[ (string) $g['group_key'] ] = (string) $g['label'];
		}
		if ( '' !== $q ) {
			$needle = mb_strtolower( $q );
			$rows   = array_values( array_filter( $rows, static fn( array $a ): bool => str_contains( mb_strtolower( $a['name'] . ' ' . $a['article_number'] . ' ' . $a['ean'] ), $needle ) ) );
		}
		$out  = '<section class="liw-dw__section"><form method="get" class="liw-dw__filters" action="' . esc_url( WorkspaceContext::page_url() ) . '"><input type="hidden" name="ws" value="articles" />';
		$out .= '<input type="search" name="q" value="' . esc_attr( $q ) . '" placeholder="' . esc_attr__( 'Name, Nummer, EAN', 'liebherr-interface-world' ) . '" /><select name="type"><option value="">' . esc_html__( 'alle Typen', 'liebherr-interface-world' ) . '</option>';
		foreach ( ( self::CORE_SCOPE )::DEALER_TYPES as $t ) {
			$out .= '<option value="' . esc_attr( $t ) . '"' . selected( $type, $t, false ) . '>' . esc_html( ( self::CORE_ART )::type_label( $t ) ) . '</option>';
		}
		$out .= '</select><button type="submit" class="liw-dw__button liw-dw__button--small">' . esc_html__( 'Filtern', 'liebherr-interface-world' ) . '</button>';
		if ( WorkspaceContext::can( $M::CAP_CREATE ) ) {
			$out .= ' <a class="liw-dw__button liw-dw__button--small" href="' . esc_url( WorkspaceContext::url( 'article' ) ) . '">+ ' . esc_html__( 'Artikel anlegen', 'liebherr-interface-world' ) . '</a>';
		}
		$out .= '</form><p class="liw-dw__meta">' . esc_html( sprintf( /* translators: %s: Anzahl */ __( '%s Artikel', 'liebherr-interface-world' ), number_format_i18n( count( $rows ) ) ) ) . '</p>';
		$out .= '<div class="liw-dw__scroll"><table class="liw-dw__table"><thead><tr><th>' . esc_html__( 'Nummer', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Name', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Typ', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Gruppe', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Preis', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Bestand', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Status', 'liebherr-interface-world' ) . '</th></tr></thead><tbody>';
		if ( [] === $rows ) {
			$out .= '<tr><td colspan="7">' . esc_html__( 'Noch keine Artikel.', 'liebherr-interface-world' ) . '</td></tr>';
		}
		foreach ( $rows as $a ) {
			$url  = WorkspaceContext::url( 'article', [ 'id' => (int) $a['id'] ] );
			$out .= '<tr><td><a href="' . esc_url( $url ) . '"><code>' . esc_html( (string) $a['article_number'] ) . '</code></a></td><td><a href="' . esc_url( $url ) . '">' . esc_html( (string) $a['name'] ) . '</a></td><td>' . esc_html( ( self::CORE_ART )::type_label( (string) $a['type'] ) ) . '</td><td>' . esc_html( $glabel[ (string) $a['group_key'] ] ?? '' ) . '</td><td>' . esc_html( number_format_i18n( (float) $a['price'], 2 ) . ' ' . (string) $a['currency'] ) . '</td><td>' . esc_html( null === $a['stock_qty'] ? '–' : (string) $a['stock_qty'] ) . '</td><td>' . esc_html( self::status_label( (string) $a['status'] ) ) . '</td></tr>';
		}
		$out .= '</tbody></table></div>';
		if ( WorkspaceContext::can( $M::CAP_CREATE ) ) {
			$out .= '<h3 class="liw-dw__h3">' . esc_html__( 'Artikelgruppen', 'liebherr-interface-world' ) . '</h3><p class="liw-dw__meta">' . esc_html( [] === $glabel ? __( 'Noch keine Gruppen.', 'liebherr-interface-world' ) : implode( ' · ', $glabel ) ) . '</p>';
			$out .= '<form method="post" class="liw-dw__inline" action="' . esc_url( WorkspaceContext::url( 'articles' ) ) . '">' . self::nonce() . '<input type="hidden" name="dw_action" value="save_group" /><input type="text" name="label" maxlength="120" placeholder="' . esc_attr__( 'Neue Gruppe', 'liebherr-interface-world' ) . '" required /> <button type="submit" class="liw-dw__button liw-dw__button--small">' . esc_html__( 'Gruppe anlegen', 'liebherr-interface-world' ) . '</button></form>';
		}
		$out .= self::template_box( $org );
		return $out . '</section>';
	}

	/** BV-0: Suche in der Liebherr-Produktliste (Vorlagen) und Übernahme per Klick (alpha.156). */
	private static function template_box( int $org ): string {
		$M   = WorkspaceContext::CORE_MODULE;
		$svc = 'Liebherr\\InterfaceWorld\\SalesInterface\\ProductTemplateService';
		if ( ! WorkspaceContext::can( $M::CAP_CREATE ) || ! class_exists( $svc ) || 0 === $svc::total() ) {
			return '';
		}
		$tq = isset( $_GET['tq'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['tq'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tt = isset( $_GET['tt'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['tt'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tm = isset( $_GET['tm'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['tm'] ) ) : 'LI'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$out  = '<h3 class="liw-dw__h3">' . esc_html__( 'Aus der Liebherr-Produktliste übernehmen', 'liebherr-interface-world' ) . '</h3>';
		$out .= '<p class="liw-dw__meta">' . esc_html( sprintf( /* translators: %s: Anzahl */ __( '%s Modelle als Vorlage (Dealer Interface /product-list). Übernehmen legt einen Artikel vom Typ Maschine mit Gruppe je Maschinenart an.', 'liebherr-interface-world' ), number_format_i18n( $svc::total() ) ) ) . '</p>';
		$out .= '<form method="get" class="liw-dw__filters" action="' . esc_url( WorkspaceContext::page_url() ) . '"><input type="hidden" name="ws" value="articles" />';
		$out .= '<input type="search" name="tq" value="' . esc_attr( $tq ) . '" placeholder="' . esc_attr__( 'Bezeichnung, z. B. L 550 oder R 926', 'liebherr-interface-world' ) . '" />';
		$out .= '<select name="tm"><option value="LI"' . selected( $tm, 'LI', false ) . '>' . esc_html__( 'Liebherr', 'liebherr-interface-world' ) . '</option><option value=""' . selected( $tm, '', false ) . '>' . esc_html__( 'alle Hersteller', 'liebherr-interface-world' ) . '</option></select>';
		$out .= '<select name="tt"><option value="">' . esc_html__( 'alle Maschinenarten', 'liebherr-interface-world' ) . '</option>';
		foreach ( $svc::machine_types( $tm ) as $code => $n ) {
			$out .= '<option value="' . esc_attr( (string) $code ) . '"' . selected( $tt, (string) $code, false ) . '>' . esc_html( $svc::label_type( (string) $code ) . ' (' . $n . ')' ) . '</option>';
		}
		$out .= '</select><button type="submit" class="liw-dw__button liw-dw__button--small">' . esc_html__( 'Suchen', 'liebherr-interface-world' ) . '</button></form>';
		if ( '' === $tq && '' === $tt ) {
			return $out;
		}
		$rows = $svc::search( $tq, $tm, $tt, 60 );
		$out .= '<div class="liw-dw__scroll"><table class="liw-dw__table"><thead><tr><th>' . esc_html__( 'Hersteller', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Bezeichnung', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Maschinenart', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Bauzeit', 'liebherr-interface-world' ) . '</th><th></th></tr></thead><tbody>';
		if ( [] === $rows ) {
			$out .= '<tr><td colspan="5">' . esc_html__( 'Keine Vorlage gefunden.', 'liebherr-interface-world' ) . '</td></tr>';
		}
		foreach ( $rows as $r ) {
			$years = '' !== (string) $r['build_from'] ? (string) $r['build_from'] . ( '' !== (string) $r['build_to'] ? '–' . (string) $r['build_to'] : '' ) : '';
			$out  .= '<tr><td>' . esc_html( $svc::label_manufacturer( (string) $r['manufacturer'] ) ) . '</td><td><strong>' . esc_html( (string) $r['designation'] ) . '</strong>' . ( '' !== (string) $r['product_code'] ? ' <code>' . esc_html( (string) $r['product_code'] ) . '</code>' : '' ) . '</td><td>' . esc_html( $svc::label_type( (string) $r['machine_type'] ) ) . '</td><td>' . esc_html( $years ) . '</td>';
			$out  .= '<td><form method="post" class="liw-dw__inline" action="' . esc_url( WorkspaceContext::url( 'articles' ) ) . '">' . self::nonce() . '<input type="hidden" name="dw_action" value="adopt_template" /><input type="hidden" name="product_id" value="' . (int) $r['product_id'] . '" /><button type="submit" class="liw-dw__button liw-dw__button--small">' . esc_html__( 'Übernehmen', 'liebherr-interface-world' ) . '</button></form></td></tr>';
		}
		return $out . '</tbody></table></div>';
	}

	/** Artikel anlegen/bearbeiten (nur Händler-Typen physical/service). */
	private static function article( int $org, int $id ): string {
		if ( ! class_exists( self::CORE_SCOPE ) ) {
			return '';
		}
		$M = WorkspaceContext::CORE_MODULE;
		$a = $id > 0 ? ( self::CORE_SCOPE )::with( $org, static fn() => ( self::CORE_ART )::get_by_id( $id ) ) : null;
		if ( $id > 0 && null === $a ) {
			return '<section class="liw-dw__section"><p>' . esc_html__( 'Artikel nicht gefunden.', 'liebherr-interface-world' ) . '</p></section>';
		}
		$can    = WorkspaceContext::can( $id > 0 ? $M::CAP_EDIT : $M::CAP_CREATE );
		$a      = $a ?? [ 'type' => 'physical', 'status' => 'draft', 'currency' => 'EUR' ];
		$v      = static fn( string $k ): string => (string) ( $a[ $k ] ?? '' );
		$groups = ( self::CORE_SCOPE )::with( $org, static fn() => ( self::CORE_GROUP )::get_all( false ) );
		$out    = '<section class="liw-dw__section"><p class="liw-dw__meta"><a href="' . esc_url( WorkspaceContext::url( 'articles' ) ) . '">← ' . esc_html__( 'Artikel', 'liebherr-interface-world' ) . '</a></p>';
		$out   .= '<h2 class="liw-dw__h2">' . esc_html( $id > 0 ? $v( 'article_number' ) . ' – ' . $v( 'name' ) : __( 'Neuer Artikel', 'liebherr-interface-world' ) ) . '</h2>';
		if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$out .= '<p class="liw-dw__notice liw-dw__notice--ok">' . esc_html__( 'Gespeichert.', 'liebherr-interface-world' ) . '</p>';
		}
		$out .= '<form method="post" class="liw-dw__form" action="' . esc_url( WorkspaceContext::url( 'article', $id > 0 ? [ 'id' => $id ] : [] ) ) . '">' . self::nonce() . '<input type="hidden" name="dw_action" value="save_article" /><input type="hidden" name="id" value="' . (int) $id . '" /><div class="liw-dw__grid">';
		$out .= '<label class="liw-dw__field"><span>' . esc_html__( 'Typ', 'liebherr-interface-world' ) . '</span><select name="article[type]"' . ( $can ? '' : ' disabled' ) . '>';
		foreach ( ( self::CORE_SCOPE )::DEALER_TYPES as $t ) {
			$out .= '<option value="' . esc_attr( $t ) . '"' . selected( $v( 'type' ), $t, false ) . '>' . esc_html( ( self::CORE_ART )::type_label( $t ) ) . '</option>';
		}
		$out .= '</select></label>';
		foreach ( [ [ 'name', __( 'Name *', 'liebherr-interface-world' ), 191 ], [ 'article_number', __( 'Artikelnummer (leer = automatisch A-000001)', 'liebherr-interface-world' ), 50 ], [ 'ean', __( 'EAN', 'liebherr-interface-world' ), 20 ], [ 'price', __( 'Preis', 'liebherr-interface-world' ), 12 ], [ 'currency', __( 'Währung', 'liebherr-interface-world' ), 3 ], [ 'tax_rate_bp', __( 'MwSt (Basispunkte, 1900 = 19 %)', 'liebherr-interface-world' ), 5 ], [ 'stock_qty', __( 'Bestand (nur Ware, leer = nicht geführt)', 'liebherr-interface-world' ), 9 ] ] as [ $n, $label, $max ] ) {
			$out .= '<label class="liw-dw__field"><span>' . esc_html( $label ) . '</span><input type="text" name="article[' . esc_attr( $n ) . ']" value="' . esc_attr( $v( $n ) ) . '" maxlength="' . (int) $max . '"' . ( $can ? '' : ' readonly' ) . ' /></label>';
		}
		$out .= '<label class="liw-dw__field"><span>' . esc_html__( 'Gruppe', 'liebherr-interface-world' ) . '</span><select name="article[group_key]"' . ( $can ? '' : ' disabled' ) . '><option value="">–</option>';
		foreach ( $groups as $g ) {
			$out .= '<option value="' . esc_attr( (string) $g['group_key'] ) . '"' . selected( $v( 'group_key' ), (string) $g['group_key'], false ) . '>' . esc_html( (string) $g['label'] ) . '</option>';
		}
		$out .= '</select></label><label class="liw-dw__field"><span>' . esc_html__( 'Status', 'liebherr-interface-world' ) . '</span><select name="article[status]"' . ( $can ? '' : ' disabled' ) . '>';
		foreach ( [ 'draft', 'active', 'inactive' ] as $s ) {
			$out .= '<option value="' . esc_attr( $s ) . '"' . selected( $v( 'status' ), $s, false ) . '>' . esc_html( self::status_label( $s ) ) . '</option>';
		}
		$out .= '</select></label></div>';
		$out .= '<label class="liw-dw__field"><span>' . esc_html__( 'Kurzbeschreibung', 'liebherr-interface-world' ) . '</span><input type="text" name="article[short_description]" value="' . esc_attr( $v( 'short_description' ) ) . '" maxlength="500"' . ( $can ? '' : ' readonly' ) . ' /></label>';
		$out .= '<label class="liw-dw__field"><span>' . esc_html__( 'Beschreibung', 'liebherr-interface-world' ) . '</span><textarea name="article[description]" rows="4"' . ( $can ? '' : ' readonly' ) . '>' . esc_textarea( $v( 'description' ) ) . '</textarea></label>';
		if ( $can ) {
			$out .= '<button type="submit" class="liw-dw__button">' . esc_html__( 'Speichern', 'liebherr-interface-world' ) . '</button>';
		}
		return $out . '</form></section>';
	}

	/** Action Board im Workspace: Heute (Pure · Pure Plus · Detail · Karten) + Klassifizierung – Darstellung über die Core-Seite. */
	private static function actionboard( int $org ): string {
		if ( ! class_exists( 'Araliya\\Platform\\Core\\Admin\\Pages\\AddressbookActionPage' ) ) {
			return '<section class="liw-dw__section"><p>' . esc_html__( 'Action Board nicht verfügbar.', 'liebherr-interface-world' ) . '</p></section>';
		}
		$sub = isset( $_GET['sub'] ) ? sanitize_key( wp_unslash( (string) $_GET['sub'] ) ) : 'today'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$out = '<section class="liw-dw__section liw-dw__ab"><h2 class="liw-dw__h2">' . esc_html__( 'Action Board', 'liebherr-interface-world' ) . '</h2><p class="liw-dw__meta"><a href="' . esc_url( WorkspaceContext::url( 'actionboard' ) ) . '">' . esc_html__( 'Heute', 'liebherr-interface-world' ) . '</a> · <a href="' . esc_url( WorkspaceContext::url( 'actionboard', [ 'sub' => 'classes' ] ) ) . '">' . esc_html__( 'Klassifizierung', 'liebherr-interface-world' ) . '</a> · <a href="' . esc_url( WorkspaceContext::url( 'actionboard', [ 'sub' => 'industries' ] ) ) . '">' . esc_html__( 'Branchen', 'liebherr-interface-world' ) . '</a> · <a href="' . esc_url( WorkspaceContext::url( 'actionboard', [ 'sub' => 'views' ] ) ) . '">' . esc_html__( 'Branchenviews', 'liebherr-interface-world' ) . '</a> · <a href="' . esc_url( WorkspaceContext::url( 'actionboard', [ 'sub' => 'log' ] ) ) . '">' . esc_html__( 'Protokoll', 'liebherr-interface-world' ) . '</a></p>';
		ob_start();
		if ( 'classes' === $sub ) {
			\Araliya\Platform\Core\Admin\Pages\AddressbookActionPage::render_classes( $org, WorkspaceContext::url( 'addresses' ) );
		} elseif ( 'views' === $sub ) {
			\Araliya\Platform\Core\Admin\Pages\AddressbookActionPage::render_views( $org, self::NONCE, '', 'dw_action', true, WorkspaceContext::url( 'actionboard', [ 'sub' => 'views' ] ) );
		} elseif ( 'industries' === $sub ) {
			\Araliya\Platform\Core\Admin\Pages\AddressbookActionPage::render_industries( $org, WorkspaceContext::url( 'addresses' ), self::NONCE, '', 'dw_action', true );
		} elseif ( 'log' === $sub ) {
			\Araliya\Platform\Core\Admin\Pages\AddressbookActionPage::render_log( $org );
		} else {
			\Araliya\Platform\Core\Admin\Pages\AddressbookActionPage::render_today( $org, self::NONCE, '', 'dw_action', WorkspaceContext::url( 'address', [ 'id' => 0 ] ) . '&id=', true, WorkspaceContext::url( 'actionboard' ), WorkspaceContext::url( 'contact', [ 'id' => 0 ] ) . '&id=' );
		}
		return $out . ob_get_clean() . '</section>';
	}

	private static function interfaces( int $org ): string {
		$dealer = WorkspaceContext::dealer();
		$out    = '<section class="liw-dw__section"><h2 class="liw-dw__h2">' . esc_html__( 'Schnittstellen', 'liebherr-interface-world' ) . '</h2>';
		if ( null === $dealer ) {
			return $out . '<p>' . esc_html__( 'Kein Liebherr-Händlerdatensatz verknüpft.', 'liebherr-interface-world' ) . '</p></section>';
		}
		$stats = LivisionSync::stats( $org );
		$out  .= '<dl class="liw-dw__dl"><dt>' . esc_html__( 'Händler', 'liebherr-interface-world' ) . '</dt><dd>' . esc_html( (string) $dealer['name'] ) . ' (' . esc_html( (string) $dealer['code'] ) . ')</dd>';
		$out  .= '<dt>' . esc_html__( 'Umgebung', 'liebherr-interface-world' ) . '</dt><dd>' . esc_html( (string) $dealer['environment'] ) . '</dd>';
		$out  .= '<dt>' . esc_html__( 'Schreiben', 'liebherr-interface-world' ) . '</dt><dd>' . esc_html( $dealer['write_enabled'] ? __( 'freigegeben', 'liebherr-interface-world' ) : __( 'gesperrt', 'liebherr-interface-world' ) ) . '</dd>';
		$out  .= '<dt>' . esc_html__( 'Adressen mit Livision-ID', 'liebherr-interface-world' ) . '</dt><dd>' . esc_html( number_format_i18n( $stats['addresses_linked'] ) . ' / ' . number_format_i18n( $stats['addresses_total'] ) ) . '</dd>';
		$out  .= '<dt>' . esc_html__( 'Ansprechpartner mit Livision-ID', 'liebherr-interface-world' ) . '</dt><dd>' . esc_html( number_format_i18n( $stats['contacts_linked'] ) ) . '</dd>';
		$out  .= '<dt>' . esc_html__( 'Letzte Übertragung', 'liebherr-interface-world' ) . '</dt><dd>' . esc_html( $stats['last'] ) . '</dd></dl>';
		$out  .= '<p class="liw-dw__meta">' . esc_html__( 'Übertragen wird je Adresse bzw. Ansprechpartner über die Schaltfläche in der Detailansicht – nach Vorprüfung der Pflichtfelder, Codes und Feldlängen.', 'liebherr-interface-world' ) . '</p>';
		return $out . '</section>';
	}

	private static function team( int $org ): string {
		$tenant = WorkspaceContext::CORE_TENANT;
		$M      = WorkspaceContext::CORE_MODULE;
		$can    = WorkspaceContext::can( $M::CAP_MANAGE_TEAM );
		$out    = '<section class="liw-dw__section"><h2 class="liw-dw__h2">' . esc_html__( 'Team', 'liebherr-interface-world' ) . '</h2><div class="liw-dw__scroll"><table class="liw-dw__table"><thead><tr><th>' . esc_html__( 'Benutzer', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'E-Mail', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Funktion', 'liebherr-interface-world' ) . '</th><th>' . esc_html__( 'Status', 'liebherr-interface-world' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $tenant::team( $org ) as $m ) {
			$out .= '<tr><td>' . esc_html( $m['display'] ) . '</td><td>' . esc_html( $m['email'] ) . '</td><td>' . esc_html( self::role_label( $m['function'] ) ) . '</td><td>' . esc_html( $m['status'] ) . '</td><td>';
			if ( $can && 'active' === $m['status'] && $m['user_id'] !== get_current_user_id() ) {
				$out .= '<form method="post" class="liw-dw__inline" action="' . esc_url( WorkspaceContext::url( 'team' ) ) . '">' . self::nonce() . '<input type="hidden" name="dw_action" value="end_member" /><input type="hidden" name="user_id" value="' . (int) $m['user_id'] . '" /><button type="submit" class="liw-dw__link">' . esc_html__( 'Beenden', 'liebherr-interface-world' ) . '</button></form>';
			}
			$out .= '</td></tr>';
		}
		$out .= '</tbody></table></div>';
		if ( $can ) {
			$out .= '<h3 class="liw-dw__h3">' . esc_html__( 'Teammitglied hinzufügen', 'liebherr-interface-world' ) . '</h3><form method="post" class="liw-dw__form" action="' . esc_url( WorkspaceContext::url( 'team' ) ) . '">' . self::nonce() . '<input type="hidden" name="dw_action" value="add_member" /><div class="liw-dw__grid">';
			$out .= '<label class="liw-dw__field"><span>' . esc_html__( 'E-Mail *', 'liebherr-interface-world' ) . '</span><input type="email" name="email" required /></label><label class="liw-dw__field"><span>' . esc_html__( 'Benutzername (neues Konto)', 'liebherr-interface-world' ) . '</span><input type="text" name="login" /></label><label class="liw-dw__field"><span>' . esc_html__( 'Anzeigename', 'liebherr-interface-world' ) . '</span><input type="text" name="display" /></label>';
			$out .= '<label class="liw-dw__field"><span>' . esc_html__( 'Funktion', 'liebherr-interface-world' ) . '</span><select name="function">';
			foreach ( $tenant::FUNCTIONS as $f ) {
				$out .= '<option value="' . esc_attr( $f ) . '">' . esc_html( self::role_label( $f ) ) . '</option>';
			}
			$out .= '</select></label></div><button type="submit" class="liw-dw__button">' . esc_html__( 'Hinzufügen', 'liebherr-interface-world' ) . '</button><p class="liw-dw__meta">' . esc_html__( 'Neue Konten setzen ihr Passwort über den E-Mail-Link und arbeiten ausschließlich hier im Workspace.', 'liebherr-interface-world' ) . '</p></form>';
		}
		if ( class_exists( 'Araliya\\Platform\\Core\\Admin\\Pages\\AddressbookTeamPage' ) ) { // [alpha.157] Customer Solution Provider
			ob_start();
			\Araliya\Platform\Core\Admin\Pages\AddressbookTeamPage::render_csp_box( $org, [ 'nonce_action' => self::NONCE, 'nonce_name' => '', 'field' => 'dw_action', 'workspace' => true ] );
			$out .= '<div class="liw-dw__ab liw-dw__csp">' . ob_get_clean() . '</div>';
		}
		return $out . '</section>';
	}

	private static function guide(): string {
		$items = [
			[ __( 'Wozu der Workspace', 'liebherr-interface-world' ), __( 'Hier pflegt Ihr Haus seine Kunden-, Lieferanten- und Interessentenadressen samt Ansprechpartnern – getrennt von allen anderen Händlern. Gleiche Masken für alle, nur Ihre Daten.', 'liebherr-interface-world' ) ],
			[ __( 'Anmeldung', 'liebherr-interface-world' ), __( 'Mit Benutzername oder E-Mail und Passwort. Nach fünf Fehlversuchen ist die Anmeldung 15 Minuten gesperrt. „Passwort vergessen“ sendet einen Link.', 'liebherr-interface-world' ) ],
			[ __( 'Rechte', 'liebherr-interface-world' ), __( 'Händler-Admin: anlegen, ändern, archivieren, Team. Vertrieb: anlegen, ändern. Nur lesen: ansehen. Endgültiges Löschen ist dem GoHeal-Operator vorbehalten – archivieren Sie stattdessen.', 'liebherr-interface-world' ) ],
			[ __( 'Adressen', 'liebherr-interface-world' ), __( 'Suche über Nummer, Name, Ort, PLZ, E-Mail; Filter nach Status und Rolle. Pflichtfelder: Name 1, PLZ, Ort, Land. Die Nummer vergibt das System (K-000001 …). Codes für Bundesland, Branche, Klassifizierung und Adressinfo folgen den Liebherr-Codelisten.', 'liebherr-interface-world' ) ],
			[ __( 'Artikel', 'liebherr-interface-world' ), __( 'Reiter „Artikel“: Ware und Dienstleistungen Ihres Hauses mit Nummer (A-000001 …), EAN, Gruppe, Preis, MwSt und Bestand. Die Artikel der Plattform sind für Sie unsichtbar; Ihre Artikel sieht kein anderer Händler.', 'liebherr-interface-world' ) ],
			[ __( 'Livision', 'liebherr-interface-world' ), __( 'In der Detailansicht steht „An Livision übertragen“. Vorher prüft der Workspace Pflichtfelder, Codes und Feldlängen (Name 2 max. 50, Funktion max. 10 Zeichen). Die Livision-ID und der Status werden gespeichert; Übertragen ist nur möglich, wenn der Operator den Schreibschalter gesetzt hat.', 'liebherr-interface-world' ) ],
			[ __( 'Datenschutz', 'liebherr-interface-world' ), __( 'Private Angaben von Ansprechpartnern nur mit Einwilligung. Jede Änderung wird protokolliert.', 'liebherr-interface-world' ) ],
		];
		$out = '<section class="liw-dw__section"><h2 class="liw-dw__h2">' . esc_html__( 'Anleitung', 'liebherr-interface-world' ) . '</h2><dl class="liw-dw__dl">';
		foreach ( $items as [ $t, $d ] ) {
			$out .= '<dt>' . esc_html( $t ) . '</dt><dd>' . esc_html( $d ) . '</dd>';
		}
		return $out . '</dl></section>';
	}

	public static function role_label( string $f ): string {
		$map = [ 'operator' => __( 'GoHeal-Operator', 'liebherr-interface-world' ), 'dealer_admin' => __( 'Händler-Admin', 'liebherr-interface-world' ), 'dealer_sales' => __( 'Vertrieb', 'liebherr-interface-world' ), 'dealer_csp' => __( 'Verkäufer – nur eigene Kunden (CSP)', 'liebherr-interface-world' ), 'dealer_viewer' => __( 'Nur lesen', 'liebherr-interface-world' ) ];
		return $map[ $f ] ?? $f;
	}

	public static function status_label( string $s ): string {
		$map = [ 'active' => __( 'aktiv', 'liebherr-interface-world' ), 'inactive' => __( 'inaktiv', 'liebherr-interface-world' ), 'archived' => __( 'archiviert', 'liebherr-interface-world' ), 'draft' => __( 'Entwurf', 'liebherr-interface-world' ) ];
		return $map[ $s ] ?? $s;
	}

	public static function role_name( string $r ): string {
		$map = [ 'customer' => __( 'Kunde', 'liebherr-interface-world' ), 'supplier' => __( 'Lieferant', 'liebherr-interface-world' ), 'prospect' => __( 'Interessent', 'liebherr-interface-world' ), 'partner' => __( 'Partner', 'liebherr-interface-world' ), 'other' => __( 'Sonstiges', 'liebherr-interface-world' ) ];
		return $map[ $r ] ?? $r;
	}
}
