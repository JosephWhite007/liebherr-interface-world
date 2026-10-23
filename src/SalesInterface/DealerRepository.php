<?php
/**
 * Liebherr Interface Solutions – Händler-Repository (Stufe 1d).
 *
 * CRUD für `liw_dealer` plus Auflösung der Zugangsdaten je Händler. API-Key: nur verschlüsselt gespeichert
 * (SecretBridge), beim Lesen nur für den Aufruf entschlüsselt, nie ausgegeben. Env-Fallback: Der Standardhändler
 * `tradler` (Dev-Erstanbindung) nutzt `LIW_LIVISION_KEY_TEST` / `LIW_LIVISION_USER_EMAIL`, solange im Datensatz
 * nichts hinterlegt ist – so bleibt die bestehende Testverbindung (Stufe 1c) ohne Datenmigration lauffähig.
 * Jede Änderung wird auditiert (ohne Werte). Produktion ist als `environment` erlaubt zu erfassen, wird in Stufe 1
 * aber vom Client nicht angesprochen.
 *
 * @package Liebherr\InterfaceWorld\SalesInterface
 * @since   0.1.0-alpha.148
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\SalesInterface;

use Liebherr\InterfaceWorld\CoreBridge\AuditBridge;
use Liebherr\InterfaceWorld\CoreBridge\InstanceBridge;
use Liebherr\InterfaceWorld\CoreBridge\SecretBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class DealerRepository {

	public const DEFAULT_CODE  = 'tradler';
	public const OPTION_ACTIVE = 'liw_livision_active_dealer';
	public const ENVIRONMENTS  = [ 'test', 'production' ];
	public const STATUSES      = [ 'active', 'inactive' ];

	/** Standardhändler anlegen, falls die Tabelle leer ist (idempotent; Versionswechsel + Seed). */
	public static function ensure_default(): void {
		if ( null === self::find( self::DEFAULT_CODE ) ) {
			self::save( [ 'code' => self::DEFAULT_CODE, 'name' => 'Tradler Baumaschinen', 'country' => 'DE', 'environment' => 'test', 'notes' => 'Erstanbindung; Zugangsdaten aus Env, solange hier nichts hinterlegt ist.' ], 0 );
		}
		if ( '' === (string) get_option( self::OPTION_ACTIVE, '' ) ) {
			update_option( self::OPTION_ACTIVE, self::DEFAULT_CODE, false );
		}
	}

	/** @return array<int,array<string,mixed>> Alle Händler (ohne Key-Ciphertext). */
	public static function all(): array {
		global $wpdb;
		$t    = DealerSchema::table_name();
		$rows = $wpdb->get_results( "SELECT id, code, name, country, environment, sales_account_number, user_email, write_enabled, status, notes, created_at, updated_at, api_key_enc <> '' AND api_key_enc IS NOT NULL AS has_key FROM {$t} ORDER BY name ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return is_array( $rows ) ? array_map( [ self::class, 'cast' ], $rows ) : [];
	}

	/** @return array<string,mixed>|null */
	public static function find( string $code ): ?array {
		global $wpdb;
		$t   = DealerSchema::table_name();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE code = %s", sanitize_key( $code ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! is_array( $row ) ) {
			return null;
		}
		$row['has_key'] = '' !== (string) ( $row['api_key_enc'] ?? '' );
		unset( $row['api_key_enc'] );
		return self::cast( $row );
	}

	public static function active_code(): string {
		$code = sanitize_key( (string) get_option( self::OPTION_ACTIVE, self::DEFAULT_CODE ) );
		return '' !== $code ? $code : self::DEFAULT_CODE;
	}

	public static function set_active( string $code, int $actor_id ): bool {
		$code = sanitize_key( $code );
		if ( null === self::find( $code ) ) {
			return false;
		}
		update_option( self::OPTION_ACTIVE, $code, false );
		self::audit( 'set_active', $code, [], $actor_id );
		return true;
	}

	/**
	 * Anlegen/Ändern. `api_key` (Klartext) wird nur verschlüsselt übernommen; leer = Key behalten; '-' = Key löschen.
	 *
	 * @param array<string,mixed> $data
	 * @return int|\WP_Error Datensatz-ID.
	 */
	public static function save( array $data, int $actor_id ): int|\WP_Error {
		global $wpdb;
		$code = sanitize_key( (string) ( $data['code'] ?? '' ) );
		$name = sanitize_text_field( (string) ( $data['name'] ?? '' ) );
		if ( '' === $code || '' === $name ) {
			return new \WP_Error( 'liw_dealer_invalid', __( 'Kürzel und Name sind Pflichtfelder.', 'liebherr-interface-world' ) );
		}
		$env    = (string) ( $data['environment'] ?? 'test' );
		$env    = in_array( $env, self::ENVIRONMENTS, true ) ? $env : 'test';
		$status = (string) ( $data['status'] ?? 'active' );
		$status = in_array( $status, self::STATUSES, true ) ? $status : 'active';
		$row    = [
			'code'                 => $code,
			'name'                 => $name,
			'country'              => strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) ( $data['country'] ?? '' ) ) ?? '', 0, 2 ) ),
			'environment'          => $env,
			'sales_account_number' => sanitize_text_field( (string) ( $data['sales_account_number'] ?? '' ) ),
			'user_email'           => sanitize_email( (string) ( $data['user_email'] ?? '' ) ),
			'write_enabled'        => ! empty( $data['write_enabled'] ) ? 1 : 0,
			'status'               => $status,
			'notes'                => sanitize_textarea_field( (string) ( $data['notes'] ?? '' ) ),
			'updated_at'           => current_time( 'mysql', true ),
		];

		$api_key = trim( (string) ( $data['api_key'] ?? '' ) );
		if ( '-' === $api_key ) {
			$row['api_key_enc'] = '';
		} elseif ( '' !== $api_key ) {
			$sealed = SecretBridge::seal( $api_key );
			if ( '' === $sealed ) {
				return new \WP_Error( 'liw_dealer_no_encryption', __( 'API-Key nicht gespeichert: Verschlüsselung nicht verfügbar (Master Key fehlt).', 'liebherr-interface-world' ) );
			}
			$row['api_key_enc'] = $sealed;
		}

		$t        = DealerSchema::table_name();
		$existing = self::find( $code );
		if ( null === $existing ) {
			$row['created_at'] = $row['updated_at'];
			if ( false === $wpdb->insert( $t, $row ) ) {
				return new \WP_Error( 'liw_dealer_db', __( 'Händler konnte nicht gespeichert werden.', 'liebherr-interface-world' ) );
			}
			$id = (int) $wpdb->insert_id;
			self::audit( 'create', $code, self::audit_fields( $row ), $actor_id );
			return $id;
		}
		$wpdb->update( $t, $row, [ 'code' => $code ] );
		self::audit( 'update', $code, self::audit_fields( $row ), $actor_id );
		return (int) $existing['id'];
	}

	/**
	 * Zugangsdaten für einen Aufruf (nur im Speicher, nie persistiert/geloggt).
	 *
	 * @return array{key:string,user_email:string,sales_account_number:string,environment:string,write_enabled:bool,status:string,name:string}|null
	 */
	public static function credentials( string $code ): ?array {
		global $wpdb;
		$t   = DealerSchema::table_name();
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE code = %s", sanitize_key( $code ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! is_array( $row ) ) {
			return null;
		}
		$key   = SecretBridge::open( (string) ( $row['api_key_enc'] ?? '' ) );
		$email = (string) $row['user_email'];
		if ( self::DEFAULT_CODE === $row['code'] ) { // Env-Fallback nur für die Erstanbindung.
			if ( '' === $key ) {
				$key = (string) getenv( InstanceBridge::ENV_KEY_TEST );
			}
			if ( '' === $email ) {
				$email = (string) getenv( InstanceBridge::ENV_USER_EMAIL );
			}
		}
		return [
			'key'                  => trim( $key ),
			'user_email'           => trim( $email ),
			'sales_account_number' => (string) $row['sales_account_number'],
			'environment'          => (string) $row['environment'],
			'write_enabled'        => (bool) (int) $row['write_enabled'],
			'status'               => (string) $row['status'],
			'name'                 => (string) $row['name'],
		];
	}

	/** @param array<string,mixed> $row */
	private static function cast( array $row ): array {
		$row['id']            = (int) ( $row['id'] ?? 0 );
		$row['write_enabled'] = (bool) (int) ( $row['write_enabled'] ?? 0 );
		$row['has_key']       = (bool) (int) ( $row['has_key'] ?? 0 );
		return $row;
	}

	/** @param array<string,mixed> $row @return array<string,mixed> Audit-Felder ohne Geheimnisse. */
	private static function audit_fields( array $row ): array {
		return [ 'name' => $row['name'], 'country' => $row['country'], 'environment' => $row['environment'], 'write_enabled' => $row['write_enabled'], 'status' => $row['status'], 'key_changed' => array_key_exists( 'api_key_enc', $row ) ];
	}

	/** @param array<string,mixed> $fields */
	private static function audit( string $action, string $code, array $fields, int $actor_id ): void {
		if ( AuditBridge::is_available() ) {
			AuditBridge::log( 'dealer_' . $action, 'dealer', 0, [], [ 'code' => $code ] + $fields, $actor_id );
		}
	}
}
