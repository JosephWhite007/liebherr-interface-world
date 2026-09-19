<?php
/**
 * Liebherr Adventures – Registrierungs-/Veröffentlichungs-Workflow (Basislogik §1–§9).
 *
 * Serverseitige Durchsetzung des verbindlichen Ablaufs: Der Ersteller registriert seinen Beitrag mit einem
 * selbst festgelegten Tokenwert (Rechtezusicherung Pflicht) → im Artikelbook registriert/verlinkt →
 * optional Validierung → Veröffentlichungsfreigabe im Liebherr-World-Netz. Jeder Schritt wird revisionssicher
 * im {@see TokenLedger} verkettet. Zugriffe anderer Nutzer werden mit akzeptiertem Tokenwert protokolliert.
 *
 * Zustandsübergänge nur gemäß {@see RegistrationStatus}; unzulässige Übergänge werden abgewiesen.
 *
 * @package Liebherr\InterfaceWorld\Adventures
 * @since   0.1.0-alpha.59
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Adventures;

use Liebherr\InterfaceWorld\CoreBridge\ArticlebookBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class RegistrationService {

	/** Aktueller Registrierungsstatus des Beitrags (Standard: Entwurf). */
	public static function current_status( int $post_id ): string {
		$s = (string) get_post_meta( $post_id, AdventureCpt::M_REG_STATUS, true );
		return RegistrationStatus::is_valid( $s ) ? $s : RegistrationStatus::DRAFT;
	}

	/**
	 * Registrierbündel eines Beitrags (für Anzeige/Prüfung).
	 *
	 * @return array<string,mixed>
	 */
	public static function get_registration( int $post_id ): array {
		$status = self::current_status( $post_id );
		return [
			'contribution_id' => $post_id,
			'uuid'            => (string) get_post_meta( $post_id, AdventureCpt::M_UUID, true ),
			'status'          => $status,
			'status_label'    => RegistrationStatus::label( $status ),
			'token_value'     => TokenPolicy::sanitize_value( get_post_meta( $post_id, AdventureCpt::M_TOKEN_VALUE, true ) ),
			'version'         => max( 1, (int) get_post_meta( $post_id, AdventureCpt::M_VERSION, true ) ),
			'reg_id'          => (string) get_post_meta( $post_id, AdventureCpt::M_REG_ID, true ),
			'articlebook_ref' => (string) get_post_meta( $post_id, AdventureCpt::M_ARTICLEBOOK_REF, true ),
			'articlebook_url' => ArticlebookBridge::link_url(
				(string) get_post_meta( $post_id, AdventureCpt::M_ARTICLEBOOK_REF, true ),
				(string) get_post_meta( $post_id, AdventureCpt::M_ARTICLEBOOK_URL, true )
			),
			'usage_scope'     => (string) get_post_meta( $post_id, AdventureCpt::M_USAGE_SCOPE, true ),
			'rights_ok'       => '1' === (string) get_post_meta( $post_id, AdventureCpt::M_RIGHTS_OK, true ),
			'usable_company'  => RegistrationStatus::usable_in_company_net( $status ),
			'published_world' => RegistrationStatus::published_in_world( $status ),
		];
	}

	private static function is_adventure( int $post_id ): bool {
		return $post_id > 0 && AdventureCpt::POST_TYPE === get_post_type( $post_id );
	}

	private static function uuid( int $post_id ): string {
		return (string) get_post_meta( $post_id, AdventureCpt::M_UUID, true );
	}

	/**
	 * Registriert einen Beitrag mit dem vom Ersteller festgelegten Tokenwert (§1/§3).
	 * Voraussetzung: Beitrag im Entwurf/eingereicht/Nachbesserung + ausdrückliche Rechtezusicherung.
	 *
	 * @param array<string,mixed> $opts token_value, rights_confirmed(bool), usage_scope, org_unit, author_ref, category.
	 * @return array{ok:bool,error?:string,status?:string,reg_id?:string,articlebook_ref?:string,articlebook_url?:string,source?:string}
	 */
	public static function register( int $post_id, array $opts ): array {
		if ( ! self::is_adventure( $post_id ) ) {
			return [ 'ok' => false, 'error' => 'not_found' ];
		}
		$from = self::current_status( $post_id );
		if ( ! in_array( $from, [ RegistrationStatus::DRAFT, RegistrationStatus::SUBMITTED, RegistrationStatus::REVISION ], true ) ) {
			return [ 'ok' => false, 'error' => 'already_registered' ];
		}
		if ( empty( $opts['rights_confirmed'] ) ) {
			return [ 'ok' => false, 'error' => 'rights_not_confirmed' ];
		}

		$token   = TokenPolicy::sanitize_value( $opts['token_value'] ?? 0 );
		$scope   = sanitize_text_field( (string) ( $opts['usage_scope'] ?? '' ) );
		$org     = sanitize_text_field( (string) ( $opts['org_unit'] ?? '' ) );
		$version = max( 1, (int) get_post_meta( $post_id, AdventureCpt::M_VERSION, true ) );
		$post    = get_post( $post_id );
		$uuid    = self::uuid( $post_id );

		// Artikelbook-Registrierung + Verlinkung (Naht; lokaler Fallback, wenn kein Core-Hook).
		$ab = ArticlebookBridge::register( [
			'id'          => $post_id,
			'uuid'        => $uuid,
			'title'       => $post instanceof \WP_Post ? $post->post_title : '',
			'type'        => (string) get_post_meta( $post_id, AdventureCpt::M_TYPE, true ),
			'category'    => sanitize_text_field( (string) ( $opts['category'] ?? '' ) ),
			'token_value' => $token,
			'version'     => $version,
			'author_ref'  => (int) ( $opts['author_ref'] ?? ( $post instanceof \WP_Post ? (int) $post->post_author : 0 ) ),
		] );
		$reg_id = 'REG-' . strtoupper( substr( '' !== $uuid ? $uuid : (string) $post_id, 0, 12 ) );

		update_post_meta( $post_id, AdventureCpt::M_TOKEN_VALUE, $token );
		update_post_meta( $post_id, AdventureCpt::M_USAGE_SCOPE, $scope );
		update_post_meta( $post_id, AdventureCpt::M_RIGHTS_OK, '1' );
		update_post_meta( $post_id, AdventureCpt::M_VERSION, $version );
		update_post_meta( $post_id, AdventureCpt::M_REG_ID, $reg_id );
		update_post_meta( $post_id, AdventureCpt::M_ARTICLEBOOK_REF, $ab['ref'] );
		update_post_meta( $post_id, AdventureCpt::M_ARTICLEBOOK_URL, $ab['url'] );
		update_post_meta( $post_id, AdventureCpt::M_REG_STATUS, RegistrationStatus::REGISTERED );

		TokenLedger::append( $post_id, TokenLedger::KIND_REGISTERED, [
			'contribution_uuid' => $uuid,
			'version'           => $version,
			'user_ref'          => (int) ( $opts['author_ref'] ?? get_current_user_id() ),
			'org_unit'          => $org,
			'token_value'       => $token,
			'usage_scope'       => $scope,
			'metadata'          => [ 'reg_id' => $reg_id, 'articlebook_ref' => $ab['ref'], 'articlebook_source' => $ab['source'], 'rights_confirmed' => true ],
		] );

		return [
			'ok'              => true,
			'status'          => RegistrationStatus::REGISTERED,
			'reg_id'          => $reg_id,
			'articlebook_ref' => $ab['ref'],
			'articlebook_url' => ArticlebookBridge::link_url( $ab['ref'], $ab['url'] ),
			'source'          => $ab['source'],
		];
	}

	/** Geführter Statuswechsel mit Ledger-Eintrag (Validierung/Freigabe/Sperre/Archiv). */
	private static function transition( int $post_id, string $to, string $kind, array $meta = [] ): array {
		if ( ! self::is_adventure( $post_id ) ) {
			return [ 'ok' => false, 'error' => 'not_found' ];
		}
		$from = self::current_status( $post_id );
		if ( ! RegistrationStatus::can_transition( $from, $to ) ) {
			return [ 'ok' => false, 'error' => 'invalid_transition', 'from' => $from, 'to' => $to ];
		}
		update_post_meta( $post_id, AdventureCpt::M_REG_STATUS, $to );
		TokenLedger::append( $post_id, $kind, [
			'contribution_uuid' => self::uuid( $post_id ),
			'version'           => max( 1, (int) get_post_meta( $post_id, AdventureCpt::M_VERSION, true ) ),
			'user_ref'          => get_current_user_id(),
			'token_value'       => TokenPolicy::sanitize_value( get_post_meta( $post_id, AdventureCpt::M_TOKEN_VALUE, true ) ),
			'metadata'          => array_merge( [ 'from' => $from, 'to' => $to ], $meta ),
		] );
		return [ 'ok' => true, 'status' => $to ];
	}

	/** Ersteller beantragt Validierung (§4). Registriert → Validierung beantragt. */
	public static function request_validation( int $post_id ): array {
		return self::transition( $post_id, RegistrationStatus::VALIDATION_REQUESTED, TokenLedger::KIND_VALIDATION_REQUESTED );
	}

	/** Prüf-/Validierungsstelle entscheidet (§4/§8): bestanden → validiert, sonst → Nachbesserung. */
	public static function set_validation_result( int $post_id, bool $passed, string $report = '' ): array {
		$to   = $passed ? RegistrationStatus::VALIDATED : RegistrationStatus::REVISION;
		$kind = $passed ? TokenLedger::KIND_VALIDATED : TokenLedger::KIND_REVISION;
		return self::transition( $post_id, $to, $kind, '' !== $report ? [ 'report' => sanitize_text_field( $report ) ] : [] );
	}

	/** Veröffentlichungsfreigabe im Liebherr-World-Netz (§7): validiert → freigegeben. */
	public static function publish_world( int $post_id ): array {
		$res = self::transition( $post_id, RegistrationStatus::PUBLISHED, TokenLedger::KIND_PUBLISHED );
		if ( ! empty( $res['ok'] ) ) {
			update_post_meta( $post_id, AdventureCpt::M_VISIBILITY, 'public_approved' );
			wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ] );
		}
		return $res;
	}

	/** Sperren bzw. archivieren (Plattformadministration §8). */
	public static function block( int $post_id ): array {
		return self::transition( $post_id, RegistrationStatus::BLOCKED, TokenLedger::KIND_BLOCKED );
	}
	public static function archive( int $post_id ): array {
		return self::transition( $post_id, RegistrationStatus::ARCHIVED, TokenLedger::KIND_ARCHIVED );
	}

	/**
	 * Tokenwert ändern (§6): gilt nur für zukünftige Zugriffe. Bei neuer Version wird hochgezählt.
	 * Bereits protokollierte Zugriffe bleiben unverändert (Ledger-Unveränderlichkeit).
	 */
	public static function set_token_value( int $post_id, int $new_value, bool $new_version = false ): array {
		if ( ! self::is_adventure( $post_id ) ) {
			return [ 'ok' => false, 'error' => 'not_found' ];
		}
		$value   = TokenPolicy::sanitize_value( $new_value );
		$version = TokenPolicy::next_version( (int) get_post_meta( $post_id, AdventureCpt::M_VERSION, true ), $new_version );
		update_post_meta( $post_id, AdventureCpt::M_TOKEN_VALUE, $value );
		update_post_meta( $post_id, AdventureCpt::M_VERSION, $version );
		TokenLedger::append( $post_id, TokenLedger::KIND_PRICE_CHANGE, [
			'contribution_uuid' => self::uuid( $post_id ),
			'version'           => $version,
			'user_ref'          => get_current_user_id(),
			'token_value'       => $value,
			'metadata'          => [ 'new_version' => $new_version ],
		] );
		return [ 'ok' => true, 'token_value' => $value, 'version' => $version ];
	}

	/**
	 * Zugriffsvorschau (§5): zeigt vor der Nutzung Tokenwert, Version und Nutzungsbedingungen an.
	 *
	 * @return array<string,mixed>
	 */
	public static function access_preview( int $post_id, int $viewer_id, int $budget = 0, bool $has_entitlement = false ): array {
		$reg    = self::get_registration( $post_id );
		$author = self::is_author( $post_id, $viewer_id );
		$access = TokenPolicy::resolve_access( (int) $reg['token_value'], $author, $budget, $has_entitlement );
		return [
			'contribution_id' => $post_id,
			'title'           => get_the_title( $post_id ),
			'version'         => $reg['version'],
			'status'          => $reg['status'],
			'status_label'    => $reg['status_label'],
			'usable'          => $reg['usable_company'],
			'is_author'       => $author,
			'token_value'     => $reg['token_value'],
			'usage_scope'     => $reg['usage_scope'],
			'usage_label'     => self::usage_label( (string) $reg['usage_scope'] ),
			'charge'          => (int) $access['charge'],
			'allowed'         => (bool) $access['allowed'] && $reg['usable_company'],
			'reason'          => (string) $access['reason'],
		];
	}

	/** Menschlich lesbarer Nutzungsumfang (§5). */
	public static function usage_label( string $scope ): string {
		switch ( $scope ) {
			case 'view':           return __( 'Nur ansehen', 'liebherr-interface-world' );
			case 'reuse_internal': return __( 'Intern weiterverwenden', 'liebherr-interface-world' );
			case 'reuse_world':    return __( 'Im Liebherr-World-Netz verwenden', 'liebherr-interface-world' );
			case '':               return __( 'Nicht angegeben', 'liebherr-interface-world' );
			default:               return $scope;
		}
	}

	/**
	 * Bestätigter Zugriff (§5/§6): protokolliert Nutzer, Beitrag+Version, akzeptierten Tokenwert, Umfang,
	 * Org-Einheit und Transaktions-ID revisionssicher. Nur für registrierte (nutzbare) Beiträge.
	 *
	 * @return array{ok:bool,error?:string,charge?:int,transaction_id?:string}
	 */
	public static function record_access( int $post_id, int $viewer_id, array $opts = [] ): array {
		if ( ! self::is_adventure( $post_id ) ) {
			return [ 'ok' => false, 'error' => 'not_found' ];
		}
		$reg = self::get_registration( $post_id );
		if ( ! $reg['usable_company'] ) {
			return [ 'ok' => false, 'error' => 'not_usable' ];
		}
		$author  = self::is_author( $post_id, $viewer_id );
		$budget  = (int) ( $opts['budget'] ?? 0 );
		$entitle = (bool) ( $opts['has_entitlement'] ?? false );
		$access  = TokenPolicy::resolve_access( (int) $reg['token_value'], $author, $budget, $entitle );
		if ( ! $access['allowed'] ) {
			return [ 'ok' => false, 'error' => 'insufficient_budget', 'charge' => (int) $access['charge'] ];
		}

		$entry = TokenLedger::append( $post_id, TokenLedger::KIND_ACCESS, [
			'contribution_uuid' => $reg['uuid'],
			'version'           => (int) $reg['version'],
			'user_ref'          => $viewer_id,
			'org_unit'          => sanitize_text_field( (string) ( $opts['org_unit'] ?? '' ) ),
			'token_value'       => (int) $access['charge'], // akzeptierter Tokenwert als Snapshot
			'usage_scope'       => sanitize_text_field( (string) ( $opts['usage_scope'] ?? $reg['usage_scope'] ) ),
			'metadata'          => [ 'reason' => $access['reason'], 'listed_token_value' => (int) $reg['token_value'] ],
		] );

		return [ 'ok' => true, 'charge' => (int) $access['charge'], 'transaction_id' => $entry['transaction_id'] ];
	}

	private static function is_author( int $post_id, int $viewer_id ): bool {
		$post = get_post( $post_id );
		return $post instanceof \WP_Post && $viewer_id > 0 && (int) $post->post_author === $viewer_id;
	}
}
