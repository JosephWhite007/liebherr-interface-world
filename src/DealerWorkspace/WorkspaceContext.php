<?php
/**
 * Liebherr World – Dealer Workspace: Kontext (ARY-PH-AB-1.0 §5.2, Phase 3)
 *
 * Verbindet den angemeldeten Händler-Benutzer mit seinem Core-Mandanten (TenantContext) und dem Liebherr-Händler-
 * datensatz (`liw_dealer.org_id`). Alle Workspace-Ansichten holen Rechte und Daten ausschließlich über die Core-
 * Repositories – die Mandantengrenze wird dort erzwungen, hier wird nur gelesen, wer gerade arbeitet.
 * Ohne Core-Addressbook (Klasse fehlt) ist der Workspace nicht verfügbar.
 *
 * @package Liebherr\InterfaceWorld\DealerWorkspace
 * @since   0.1.0-alpha.152
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\DealerWorkspace;

use Liebherr\InterfaceWorld\SalesInterface\DealerRepository;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class WorkspaceContext {

	public const CORE_TENANT = 'Araliya\\Platform\\Core\\Modules\\Addressbook\\TenantContext';
	public const CORE_AUTH   = 'Araliya\\Platform\\Core\\Modules\\Addressbook\\WorkspaceAuth';
	public const CORE_MODULE = 'Araliya\\Platform\\Core\\Modules\\Addressbook\\AddressbookModule';
	public const OPTION_PAGE = 'liw_dealer_workspace_page_id';

	public static function available(): bool {
		return class_exists( self::CORE_TENANT ) && class_exists( self::CORE_AUTH );
	}

	public static function logged_in(): bool {
		return is_user_logged_in();
	}

	/** Aktiver Mandant des Benutzers (0 = keiner). */
	public static function org(): int {
		return self::available() ? (int) ( self::CORE_TENANT )::active_org() : 0;
	}

	/** @return array<int,array{id:int,name:string,status:string}> */
	public static function orgs(): array {
		return self::available() ? ( self::CORE_TENANT )::visible_orgs() : [];
	}

	public static function org_name( int $org ): string {
		return (string) ( self::orgs()[ $org ]['name'] ?? '' );
	}

	public static function can( string $cap ): bool {
		$org = self::org();
		return $org > 0 && ( self::CORE_TENANT )::can( $cap, $org );
	}

	/** Org-Funktion des Benutzers im aktiven Mandanten (Operator: 'operator'). */
	public static function role(): string {
		if ( ! self::available() ) {
			return '';
		}
		if ( ( self::CORE_TENANT )::is_operator() ) {
			return 'operator';
		}
		return (string) ( ( self::CORE_TENANT )::memberships()[ self::org() ] ?? '' );
	}

	/** Liebherr-Händlerdatensatz des aktiven Mandanten (Zugangsdaten, Schreibschalter); null = nicht verknüpft. */
	public static function dealer(): ?array {
		return DealerRepository::for_org( self::org() );
	}

	public static function page_url(): string {
		$id = (int) get_option( self::OPTION_PAGE, 0 );
		return ( $id > 0 && 'publish' === get_post_status( $id ) ) ? (string) get_permalink( $id ) : '';
	}

	/** URL eines Workspace-Reiters (GET-Parameter `ws`), optional mit weiteren Parametern. */
	public static function url( string $tab = '', array $args = [] ): string {
		$base = self::page_url();
		if ( '' === $base ) {
			return home_url( '/' );
		}
		$q = [] !== $args ? $args : [];
		if ( '' !== $tab ) {
			$q = [ 'ws' => $tab ] + $q;
		}
		return [] !== $q ? add_query_arg( $q, $base ) : $base;
	}
}
