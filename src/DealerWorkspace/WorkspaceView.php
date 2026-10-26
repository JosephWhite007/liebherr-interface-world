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
		$tabs = [ 'overview' => __( 'Übersicht', 'liebherr-interface-world' ), 'addresses' => __( 'Adressen', 'liebherr-interface-world' ), 'contacts' => __( 'Ansprechpartner', 'liebherr-interface-world' ), 'interfaces' => __( 'Schnittstellen', 'liebherr-interface-world' ), 'team' => __( 'Team', 'liebherr-interface-world' ), 'guide' => __( 'Anleitung', 'liebherr-interface-world' ) ];
		$out .= '<nav class="liw-dw__nav" aria-label="' . esc_attr__( 'Workspace', 'liebherr-interface-world' ) . '"><ul>';
		foreach ( $tabs as $k => $label ) {
			$active = $k === $tab || ( 'addresses' === $k && in_array( $tab, [ 'address', 'contact' ], true ) );
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
		$out .= '<li class="liw-dw__tile liw-dw__tile--muted"><h2>' . esc_html__( 'Artikel', 'liebherr-interface-world' ) . '</h2><p>' . esc_html__( 'Artikelstammdaten je Händler folgen (Articlebook-Mandantenfähigkeit, Phase 5).', 'liebherr-interface-world' ) . '</p></li>';
		return $out . '</ul></section>';
	}

	private static function addresses( int $org ): string {
		$f = [
			'q'      => isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'status' => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( (string) $_GET['status'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'role'   => isset( $_GET['role'] ) ? sanitize_key( wp_unslash( (string) $_GET['role'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
		$out .= '</select><button type="submit" class="liw-dw__button liw-dw__button--small">' . esc_html__( 'Filtern', 'liebherr-interface-world' ) . '</button>';
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
				$out .= $p === $res['page'] ? '<strong>' . $p . '</strong> ' : '<a href="' . esc_url( WorkspaceContext::url( 'addresses', array_filter( [ 'q' => $f['q'], 'status' => $f['status'], 'role' => $f['role'], 'pg' => $p ] ) ) ) . '">' . $p . '</a> ';
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
		$out .= '</div><p class="liw-dw__field"><span class="liw-dw__label">' . esc_html__( 'Rollen', 'liebherr-interface-world' ) . '</span>';
		foreach ( ( self::CORE_SAN )::ROLES as $r ) {
			$out .= '<label class="liw-dw__check"><input type="checkbox" name="address[roles][]" value="' . esc_attr( $r ) . '"' . checked( in_array( $r, (array) $a['roles'], true ), true, false ) . ( $can ? '' : ' disabled' ) . ' /> ' . esc_html( self::role_name( $r ) ) . '</label>';
		}
		$out .= '</p><label class="liw-dw__field"><span>' . esc_html__( 'Status', 'liebherr-interface-world' ) . '</span><select name="address[status]"' . ( $can ? '' : ' disabled' ) . '>';
		foreach ( ( self::CORE_SAN )::STATUSES as $s ) {
			$out .= '<option value="' . esc_attr( $s ) . '"' . selected( $v( 'status' ), $s, false ) . '>' . esc_html( self::status_label( $s ) ) . '</option>';
		}
		$out .= '</select></label>';
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
		$out .= '</div><label class="liw-dw__check"><input type="checkbox" name="contact[decider]" value="1"' . checked( ! empty( $c['decider'] ), true, false ) . ( $can ? '' : ' disabled' ) . ' /> ' . esc_html__( 'Entscheider', 'liebherr-interface-world' ) . '</label>';
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
		return $out . '</section>';
	}

	private static function guide(): string {
		$items = [
			[ __( 'Wozu der Workspace', 'liebherr-interface-world' ), __( 'Hier pflegt Ihr Haus seine Kunden-, Lieferanten- und Interessentenadressen samt Ansprechpartnern – getrennt von allen anderen Händlern. Gleiche Masken für alle, nur Ihre Daten.', 'liebherr-interface-world' ) ],
			[ __( 'Anmeldung', 'liebherr-interface-world' ), __( 'Mit Benutzername oder E-Mail und Passwort. Nach fünf Fehlversuchen ist die Anmeldung 15 Minuten gesperrt. „Passwort vergessen“ sendet einen Link.', 'liebherr-interface-world' ) ],
			[ __( 'Rechte', 'liebherr-interface-world' ), __( 'Händler-Admin: anlegen, ändern, archivieren, Team. Vertrieb: anlegen, ändern. Nur lesen: ansehen. Endgültiges Löschen ist dem GoHeal-Operator vorbehalten – archivieren Sie stattdessen.', 'liebherr-interface-world' ) ],
			[ __( 'Adressen', 'liebherr-interface-world' ), __( 'Suche über Nummer, Name, Ort, PLZ, E-Mail; Filter nach Status und Rolle. Pflichtfelder: Name 1, PLZ, Ort, Land. Die Nummer vergibt das System (K-000001 …). Codes für Bundesland, Branche, Klassifizierung und Adressinfo folgen den Liebherr-Codelisten.', 'liebherr-interface-world' ) ],
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
		$map = [ 'operator' => __( 'GoHeal-Operator', 'liebherr-interface-world' ), 'dealer_admin' => __( 'Händler-Admin', 'liebherr-interface-world' ), 'dealer_sales' => __( 'Vertrieb', 'liebherr-interface-world' ), 'dealer_viewer' => __( 'Nur lesen', 'liebherr-interface-world' ) ];
		return $map[ $f ] ?? $f;
	}

	public static function status_label( string $s ): string {
		$map = [ 'active' => __( 'aktiv', 'liebherr-interface-world' ), 'inactive' => __( 'inaktiv', 'liebherr-interface-world' ), 'archived' => __( 'archiviert', 'liebherr-interface-world' ) ];
		return $map[ $s ] ?? $s;
	}

	public static function role_name( string $r ): string {
		$map = [ 'customer' => __( 'Kunde', 'liebherr-interface-world' ), 'supplier' => __( 'Lieferant', 'liebherr-interface-world' ), 'prospect' => __( 'Interessent', 'liebherr-interface-world' ), 'partner' => __( 'Partner', 'liebherr-interface-world' ), 'other' => __( 'Sonstiges', 'liebherr-interface-world' ) ];
		return $map[ $r ] ?? $r;
	}
}
