<?php
/**
 * Liebherr World – Livision-Sync: Addressbook → Dealer Interface API (ARY-PH-AB-1.0 §5.4, Phase 4)
 *
 * Überträgt Adressen (→ POST/PUT /customer) und Ansprechpartner (→ POST/PUT /contact) des Core-Addressbooks an die
 * Liebherr-Testumgebung – je Mandant über den verknüpften Händler (`liw_dealer.org_id`: Key, UserEmail,
 * SalesAccountNumber, Schreibschalter). Vor dem Senden: Vorprüfung (Pflichtfelder laut Guide, Codes gegen
 * `liw_codelist`, Feldlängen aus dem Echtlauf 26.10.2026: Name2 ≤ 50, Function1 ≤ 10). Ergebnis: Livision-ID und
 * **SalesAccountNumber** (Befund 26.10.2026, 21:30): Livision prüft sie je Kunde auf Eindeutigkeit – „Customer already
 * existing“, sobald eine Nummer zweimal verwendet wird. Sie ist also die **Kundennummer des Händlers für diesen Kunden**,
 * nicht das Händlerkonto. Gesendet wird die ERP-Kundennummer des Kunden (externe Referenz `erp`), sonst die
 * Addressbook-Nummer (K-000803). Die SalesAccountNumber im Dealer Board bleibt nur Vorgabe für das Skript-Testdatenpaar.
 * Sync-Status als externe Referenz im Addressbook, Audit über DealerApiClient. Konfliktregel: Addressbook ist Quelle
 * der Stammdaten, Livision liefert IDs. Feldmapping laut Konzept ARY-ADDRESSBOOK Kap. 7.
 *
 * @package Liebherr\InterfaceWorld\SalesInterface
 * @since   0.1.0-alpha.152
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\SalesInterface;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class LivisionSync {

	public const SYSTEM = 'livision';

	/** Feldlängen, die Livision beim Echtlauf 26.10.2026 erkennbar beschnitten hat (weitere unbekannt → nicht geraten). */
	public const MAX_NAME2     = 50;
	public const MAX_FUNCTION1 = 10;

	private const CORE_ADDR    = 'Araliya\\Platform\\Core\\Modules\\Addressbook\\AddressRepository';
	private const CORE_CONTACT = 'Araliya\\Platform\\Core\\Modules\\Addressbook\\ContactRepository';
	private const CORE_REF     = 'Araliya\\Platform\\Core\\Modules\\Addressbook\\ExternalRefRepository';
	private const CORE_TENANT  = 'Araliya\\Platform\\Core\\Modules\\Addressbook\\TenantContext';
	private const CORE_MODULE  = 'Araliya\\Platform\\Core\\Modules\\Addressbook\\AddressbookModule';

	public static function available(): bool {
		return class_exists( self::CORE_ADDR ) && class_exists( self::CORE_REF );
	}

	// ── Mapping (WP-frei, testbar) ─────────────────────────────────────────

	/**
	 * Adresse → Customer-Body (Guide §5 Customer). Telefon/Fax werden in Vorwahl + Nummer getrennt.
	 *
	 * @param array<string,mixed> $a
	 * @return array<string,mixed>
	 */
	public static function customer_body( array $a, string $sales_account_number, int $livision_id = 0 ): array {
		$sales_account_number = '' !== $sales_account_number ? $sales_account_number : (string) ( $a['number'] ?? '' );
		$phone = self::split_phone( (string) ( $a['phone'] ?? '' ) );
		$fax   = self::split_phone( (string) ( $a['fax'] ?? '' ) );
		$body  = [
			'Name1'                         => (string) ( $a['name1'] ?? '' ),
			'Name2'                         => mb_substr( (string) ( $a['name2'] ?? '' ), 0, self::MAX_NAME2 ),
			'Street'                        => (string) ( $a['street'] ?? '' ),
			'StreetAdditional1'             => (string) ( $a['street_add1'] ?? '' ),
			'StreetAdditional2'             => (string) ( $a['street_add2'] ?? '' ),
			'PostalCode'                    => (string) ( $a['postal_code'] ?? '' ),
			'City'                          => (string) ( $a['city'] ?? '' ),
			'Website'                       => (string) ( $a['website'] ?? '' ),
			'Email'                         => (string) ( $a['email'] ?? '' ),
			'SalesArea'                     => (string) ( $a['sales_area'] ?? '' ),
			'FederalState'                  => (string) ( $a['state'] ?? '' ),
			'State'                         => (string) ( $a['country'] ?? '' ),
			'AdministrativeDistrict'        => (string) ( $a['district'] ?? '' ),
			'ClassABC'                      => (string) ( $a['class_abc'] ?? '' ),
			'ClassHAGB'                     => (string) ( $a['class_hagb'] ?? '' ),
			'Keyaccount'                    => ! empty( $a['key_account'] ) ? 'true' : 'false',
			'NumberOfEmployee'              => isset( $a['employees'] ) && '' !== (string) $a['employees'] ? (int) $a['employees'] : null,
			'SalesAccountNumber'            => $sales_account_number,
			'InternationalDialingCodePhone' => $phone['code'],
			'Phone'                         => $phone['number'],
			'Telefax'                       => $fax['number'],
			'InternationalDialingCodeFax'   => $fax['code'],
			'PostalCodePostOfficeBox'       => (string) ( $a['po_box_zip'] ?? '' ),
			'PostOfficeBox'                 => (string) ( $a['po_box'] ?? '' ),
			'Industry1'                     => (string) ( $a['industry1'] ?? '' ),
			'Industry2'                     => (string) ( $a['industry2'] ?? '' ),
			'Industry3'                     => (string) ( $a['industry3'] ?? '' ),
			'Advisor'                       => (string) ( $a['advisor'] ?? '' ),
			'AddressInfo'                   => (string) ( $a['address_info'] ?? '' ),
			'Note'                          => '', // interne Notizen bleiben intern (Konzept Kap. 7)
		];
		if ( $livision_id > 0 ) {
			$body['LivisionCustomerId'] = $livision_id;
		}
		return array_filter( $body, static fn( $v ): bool => null !== $v && '' !== $v );
	}

	/**
	 * Ansprechpartner → Contact-Body (Guide §5 Contact).
	 *
	 * @param array<string,mixed> $c
	 * @return array<string,mixed>
	 */
	public static function contact_body( array $c, int $livision_customer_id, int $livision_contact_id = 0 ): array {
		$mobile = self::split_phone( (string) ( $c['mobile'] ?? '' ) );
		$phone  = self::split_phone( (string) ( $c['phone'] ?? '' ) );
		$phone2 = self::split_phone( (string) ( $c['phone2'] ?? '' ) );
		$fax    = self::split_phone( (string) ( $c['fax'] ?? '' ) );
		$body   = [
			'Salutation'                     => (string) ( $c['salutation'] ?? '' ),
			'Title'                          => (string) ( $c['title'] ?? '' ),
			'FirstName'                      => (string) ( $c['first_name'] ?? '' ),
			'LastName'                       => (string) ( $c['last_name'] ?? '' ),
			'Function1'                      => mb_substr( (string) ( $c['function1'] ?? '' ), 0, self::MAX_FUNCTION1 ),
			'Function2'                      => mb_substr( (string) ( $c['function2'] ?? '' ), 0, self::MAX_FUNCTION1 ),
			'Department'                     => (string) ( $c['department'] ?? '' ),
			'Decider'                        => ! empty( $c['decider'] ) ? 'true' : '',
			'InternationalDialingCodeMobile' => $mobile['code'],
			'Mobile'                         => $mobile['number'],
			'InternationalDialingCodePhone'  => $phone['code'],
			'Phone'                          => $phone['number'],
			'InternationalDialingCodePhone2' => $phone2['code'],
			'Phone2'                         => $phone2['number'],
			'Email'                          => (string) ( $c['email'] ?? '' ),
			'Telefax'                        => $fax['number'],
			'InternationalDialingCodeFax'    => $fax['code'],
			'Note'                           => '',
			'LivisionCustomerId'             => $livision_customer_id,
		];
		if ( $livision_contact_id > 0 ) {
			$body['LivisionContactId'] = $livision_contact_id;
		}
		return array_filter( $body, static fn( $v ): bool => null !== $v && '' !== $v );
	}

	/**
	 * „+49 861 90944 30" / „0049 …" → Vorwahl + Rest; ohne Länderkennung bleibt alles in `number`.
	 *
	 * @return array{code:string,number:string}
	 */
	public static function split_phone( string $raw ): array {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return [ 'code' => '', 'number' => '' ];
		}
		if ( preg_match( '/^(?:\+|00)(\d{1,3})[\s\-\/.]*(.+)$/', $raw, $m ) ) {
			return [ 'code' => '+' . $m[1], 'number' => trim( $m[2] ) ];
		}
		return [ 'code' => '', 'number' => $raw ];
	}

	// ── Vorprüfung ─────────────────────────────────────────────────────────

	/**
	 * Prüft, ob Adresse/Kontakt übertragbar ist: Händler verknüpft + Schreibschalter, Pflichtfelder, Codes, Feldlängen.
	 *
	 * @return array{ok:bool,warnings:string[]}
	 */
	public static function precheck( int $org_id, string $kind, int $id ): array {
		$w = [];
		if ( ! self::available() ) {
			return [ 'ok' => false, 'warnings' => [ __( 'Core-Addressbook nicht verfügbar.', 'liebherr-interface-world' ) ] ];
		}
		$dealer = DealerRepository::for_org( $org_id );
		if ( null === $dealer ) {
			return [ 'ok' => false, 'warnings' => [ __( 'Kein Liebherr-Händler mit diesem Mandanten verknüpft.', 'liebherr-interface-world' ) ] ];
		}
		if ( 'address' === $kind ) {
			$a = ( self::CORE_ADDR )::get( $org_id, $id );
			if ( null === $a ) {
				return [ 'ok' => false, 'warnings' => [ __( 'Adresse nicht gefunden.', 'liebherr-interface-world' ) ] ];
			}
			foreach ( [ 'name1' => 'Name1', 'street' => 'Street', 'postal_code' => 'PostalCode', 'city' => 'City' ] as $f => $api ) {
				if ( '' === trim( (string) ( $a[ $f ] ?? '' ) ) ) {
					$w[] = sprintf( /* translators: %s: Feld */ __( 'Pflichtfeld für Livision fehlt: %s', 'liebherr-interface-world' ), $api );
				}
			}
			if ( mb_strlen( (string) ( $a['name2'] ?? '' ) ) > self::MAX_NAME2 ) {
				$w[] = sprintf( /* translators: %d: Länge */ __( 'Name 2 wird von Livision auf %d Zeichen gekürzt.', 'liebherr-interface-world' ), self::MAX_NAME2 );
			}
			foreach ( [ 'state' => '1B', 'district' => '1L', 'class_abc' => 'KL', 'industry1' => '3B', 'industry2' => '3B', 'industry3' => '3B', 'address_info' => '2K' ] as $f => $list ) {
				$code = (string) ( $a[ $f ] ?? '' );
				if ( '' !== $code && ! self::code_exists( $list, $code ) ) {
					$w[] = sprintf( /* translators: 1: Feld, 2: Code, 3: Liste */ __( '%1$s: Code „%2$s“ nicht in Codeliste %3$s.', 'liebherr-interface-world' ), $f, $code, $list );
				}
			}
			if ( '' !== (string) ( $a['country'] ?? '' ) && ! self::code_exists( 'LN', (string) $a['country'] ) ) {
				$w[] = sprintf( /* translators: %s: Land */ __( 'Land „%s“ nicht in Codeliste LN.', 'liebherr-interface-world' ), (string) $a['country'] );
			}
		} else {
			$c = ( self::CORE_CONTACT )::get( $org_id, $id );
			if ( null === $c ) {
				return [ 'ok' => false, 'warnings' => [ __( 'Ansprechpartner nicht gefunden.', 'liebherr-interface-world' ) ] ];
			}
			if ( '' === trim( (string) $c['last_name'] ) ) {
				$w[] = __( 'Pflichtfeld für Livision fehlt: LastName', 'liebherr-interface-world' );
			}
			if ( 0 === self::livision_id( $org_id, 'address', (int) $c['address_id'] ) ) {
				$w[] = __( 'Die zugehörige Adresse ist noch nicht an Livision übertragen (LivisionCustomerId fehlt).', 'liebherr-interface-world' );
			}
			if ( mb_strlen( (string) ( $c['function1'] ?? '' ) ) > self::MAX_FUNCTION1 ) {
				$w[] = sprintf( /* translators: %d: Länge */ __( 'Funktion 1 wird von Livision auf %d Zeichen gekürzt.', 'liebherr-interface-world' ), self::MAX_FUNCTION1 );
			}
			foreach ( [ 'salutation' => 'AH', 'function1' => 'FN', 'function2' => 'FN', 'department' => 'PV' ] as $f => $list ) {
				$code = (string) ( $c[ $f ] ?? '' );
				if ( '' !== $code && ! self::code_exists( $list, $code ) ) {
					$w[] = sprintf( /* translators: 1: Feld, 2: Code, 3: Liste */ __( '%1$s: Code „%2$s“ nicht in Codeliste %3$s.', 'liebherr-interface-world' ), $f, $code, $list );
				}
			}
		}
		$blocking = array_filter( $w, static fn( string $m ): bool => str_contains( $m, 'Pflichtfeld' ) || str_contains( $m, 'LivisionCustomerId fehlt' ) || str_contains( $m, 'SalesAccountNumber' ) );
		return [ 'ok' => [] === $blocking, 'warnings' => $w ];
	}

	public static function code_exists( string $list, string $code ): bool {
		global $wpdb;
		$t = CodeListSchema::table_name();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE code_list = %s AND code = %s", $list, $code ) ) > 0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function livision_id( int $org_id, string $kind, int $id ): int {
		foreach ( ( self::CORE_REF )::for_entity( $org_id, $kind, $id ) as $r ) {
			if ( self::SYSTEM === $r['system_key'] && is_numeric( $r['external_id'] ) ) {
				return (int) $r['external_id'];
			}
		}
		return 0;
	}

	// ── Übertragung ────────────────────────────────────────────────────────

	/**
	 * Adresse oder Ansprechpartner anlegen/aktualisieren. Rechte: `araliya_ab_edit` im Mandanten (TenantContext).
	 *
	 * @return array{ok:bool,message:string,livision_id:int,dry_body?:array<string,mixed>}
	 */
	public static function push( int $org_id, string $kind, int $id, int $actor, bool $dry_run = false ): array {
		if ( ! self::available() ) {
			return [ 'ok' => false, 'message' => __( 'Core-Addressbook nicht verfügbar.', 'liebherr-interface-world' ), 'livision_id' => 0 ];
		}
		if ( ! ( self::CORE_TENANT )::can( ( self::CORE_MODULE )::CAP_EDIT, $org_id, $actor ) ) {
			return [ 'ok' => false, 'message' => __( 'Keine Berechtigung zum Übertragen.', 'liebherr-interface-world' ), 'livision_id' => 0 ];
		}
		$check = self::precheck( $org_id, $kind, $id );
		if ( ! $check['ok'] ) {
			return [ 'ok' => false, 'message' => implode( ' ', $check['warnings'] ), 'livision_id' => 0 ];
		}
		$dealer = DealerRepository::for_org( $org_id );
		$code   = (string) $dealer['code'];

		if ( 'address' === $kind ) {
			$a       = ( self::CORE_ADDR )::get( $org_id, $id );
			$existing = self::livision_id( $org_id, 'address', $id );
			$body    = self::customer_body( $a, self::sales_account_number( $org_id, $id, $a ), $existing );
			$method  = $existing > 0 ? 'PUT' : 'POST';
			$path    = '/customer';
			$id_key  = 'LivisionCustomerId';
		} else {
			$c        = ( self::CORE_CONTACT )::get( $org_id, $id );
			$cust     = self::livision_id( $org_id, 'address', (int) $c['address_id'] );
			$existing = self::livision_id( $org_id, 'contact', $id );
			$body     = self::contact_body( $c, $cust, $existing );
			$method   = $existing > 0 ? 'PUT' : 'POST';
			$path     = '/contact';
			$id_key   = 'LivisionContactId';
		}
		$res = DealerApiClient::send( $method, $path, $body, $actor, $code, $dry_run );
		if ( $dry_run ) {
			return [ 'ok' => $res['ok'], 'message' => $res['ok'] ? __( 'Trockenlauf OK.', 'liebherr-interface-world' ) : (string) $res['error'], 'livision_id' => $existing, 'dry_body' => $body ];
		}
		if ( ! $res['ok'] ) {
			( self::CORE_REF )::set( $org_id, $kind, $id, self::SYSTEM, $existing > 0 ? (string) $existing : '-', '', 'error', (string) $res['error'] );
			if ( 0 === $existing ) { // Platzhalter „-“ nicht als ID stehen lassen
				( self::CORE_REF )::remove( $org_id, $kind, $id, self::SYSTEM );
			}
			return [ 'ok' => false, 'message' => sprintf( /* translators: 1: Methode/Pfad, 2: Fehler */ __( 'Livision %1$s fehlgeschlagen: %2$s', 'liebherr-interface-world' ), $method . ' ' . $path, (string) $res['error'] ), 'livision_id' => $existing ];
		}
		$new_id = self::extract_id( $res['data'], $id_key );
		if ( $new_id <= 0 ) {
			$new_id = $existing;
		}
		( self::CORE_REF )::set( $org_id, $kind, $id, self::SYSTEM, (string) $new_id, 'address' === $kind ? (string) ( $body['SalesAccountNumber'] ?? '' ) : '', 'synced', '' );
		return [ 'ok' => true, 'message' => sprintf( /* translators: 1: Aktion, 2: ID */ __( 'Livision: %1$s – ID %2$d.', 'liebherr-interface-world' ), 'POST' === $method ? __( 'angelegt', 'liebherr-interface-world' ) : __( 'aktualisiert', 'liebherr-interface-world' ), $new_id ), 'livision_id' => $new_id ];
	}

	/** Kundennummer für Livision: ERP-Referenz des Kunden, sonst Addressbook-Nummer (je Kunde eindeutig). */
	public static function sales_account_number( int $org_id, int $address_id, array $a ): string {
		foreach ( ( self::CORE_REF )::for_entity( $org_id, 'address', $address_id ) as $r ) {
			if ( 'erp' === $r['system_key'] && '' !== trim( (string) $r['external_id'] ) ) {
				return trim( (string) $r['external_id'] );
			}
		}
		return (string) ( $a['number'] ?? '' );
	}

	/** ID aus der Livision-Antwort (`{status,data:[{LivisionCustomerId…}]}`). */
	public static function extract_id( $data, string $key ): int {
		$stack = [ $data ];
		while ( [] !== $stack ) {
			$cur = array_pop( $stack );
			if ( ! is_array( $cur ) ) {
				continue;
			}
			if ( isset( $cur[ $key ] ) && is_numeric( $cur[ $key ] ) ) {
				return (int) $cur[ $key ];
			}
			foreach ( $cur as $v ) {
				if ( is_array( $v ) ) {
					$stack[] = $v;
				}
			}
		}
		return 0;
	}

	/** @return array{addresses_total:int,addresses_linked:int,contacts_linked:int,last:string} */
	public static function stats( int $org_id ): array {
		global $wpdb;
		$p    = $wpdb->prefix;
		$refs = $p . 'ary_ab_external_ref';
		$last = (string) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(synced_at) FROM {$refs} WHERE org_id = %d AND system_key = %s", $org_id, self::SYSTEM ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return [
			'addresses_total'  => self::available() ? (int) ( self::CORE_ADDR )::count( $org_id ) : 0,
			'addresses_linked' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$refs} WHERE org_id = %d AND system_key = %s AND entity_type = 'address'", $org_id, self::SYSTEM ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'contacts_linked'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$refs} WHERE org_id = %d AND system_key = %s AND entity_type = 'contact'", $org_id, self::SYSTEM ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'last'             => '' !== $last ? $last : '–',
		];
	}
}
