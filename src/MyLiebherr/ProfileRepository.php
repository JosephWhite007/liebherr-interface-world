<?php
/**
 * Liebherr World – My Liebherr: Profil-Repository (Pflichtenheft My Liebherr §17, ADR-LIW-MYL-001 S1).
 *
 * Persistiert das persönliche Profil je Nutzer (Tabelle {@see Schema::profile_table()}). Legt bei Erstzugriff
 * eine Standardzeile an (aus WP-Locale/Timezone), liest sie und aktualisiert die vom {@see Context}
 * freigegebenen Felder. Nur eigener Datensatz – Aufrufer stellt den Objektbezug sicher (SEC 01/§35).
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.108
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ProfileRepository {

	/**
	 * Liest das Profil eines Nutzers.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function get( int $user_id ): ?array {
		if ( $user_id <= 0 ) {
			return null;
		}
		global $wpdb;
		$table = Schema::profile_table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return is_array( $row ) ? self::shape( $row ) : null;
	}

	/**
	 * Liefert das Profil und legt es bei Erstzugriff mit Standardwerten an (WP-Locale/Timezone).
	 *
	 * @return array<string,mixed>
	 */
	public static function ensure( int $user_id ): array {
		$existing = self::get( $user_id );
		if ( null !== $existing ) {
			return $existing;
		}
		global $wpdb;
		$table = Schema::profile_table();
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			[
				'user_id'  => $user_id,
				'locale'   => (string) get_user_locale( $user_id ),
				'timezone' => (string) wp_timezone_string(),
			],
			[ '%d', '%s', '%s' ]
		);
		$got = self::get( $user_id );
		return null !== $got ? $got : self::shape( [ 'user_id' => $user_id ] );
	}

	/**
	 * Aktualisiert erlaubte Profilfelder (bereits über {@see Context::sanitize_patch()} gefiltert/bereinigt).
	 *
	 * @param array<string,mixed> $fields
	 * @return array<string,mixed>
	 */
	public static function update( int $user_id, array $fields ): array {
		self::ensure( $user_id );
		$allowed = Context::allowed_fields();
		$data    = [];
		$format  = [];
		foreach ( $fields as $key => $value ) {
			if ( ! in_array( $key, $allowed, true ) ) {
				continue;
			}
			if ( 'active_org_id' === $key ) {
				$data[ $key ] = (int) $value;
				$format[]     = '%d';
			} else {
				$data[ $key ] = (string) $value;
				$format[]     = '%s';
			}
		}
		if ( array() !== $data ) {
			$data['updated_at'] = current_time( 'mysql' );
			$format[]           = '%s';
			global $wpdb;
			$wpdb->update( Schema::profile_table(), $data, [ 'user_id' => $user_id ], $format, [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		return self::ensure( $user_id );
	}

	/**
	 * Normalisiert eine DB-Zeile in die typisierte Profilform.
	 *
	 * @param array<string,mixed> $row
	 * @return array<string,mixed>
	 */
	private static function shape( array $row ): array {
		return [
			'user_id'         => (int) ( $row['user_id'] ?? 0 ),
			'persona'         => (string) ( $row['persona'] ?? '' ),
			'locale'          => (string) ( $row['locale'] ?? '' ),
			'timezone'        => (string) ( $row['timezone'] ?? '' ),
			'active_org_id'   => (int) ( $row['active_org_id'] ?? 0 ),
			'active_role'     => (string) ( $row['active_role'] ?? '' ),
			'privacy_version' => (int) ( $row['privacy_version'] ?? 0 ),
		];
	}
}
