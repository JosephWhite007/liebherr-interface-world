<?php
/**
 * Liebherr Interface Solutions – Testdaten-Konvention für Schreibaufrufe (Stufe 1d).
 *
 * Vorgabe JW 24.10.2026: Testkunden heißen immer „Sunnyside …" (Sunnyside One, Sunnyside Two, …) mit erkennbar
 * erfundenen „Island"-Adressen (Island Street, Island Home, …); Ansprechpartner heißen Alice (weiblich) oder Bob
 * (männlich). So sind Testdatensätze im Liebherr-System allein am Namen erkennbar. Reine Domänenlogik (WP-frei,
 * testbar); erzeugt KEINE realen Personen- oder Firmendaten.
 *
 * @package Liebherr\InterfaceWorld\SalesInterface
 * @since   0.1.0-alpha.148
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\SalesInterface;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class TestDataFactory {

	public const CUSTOMER_PREFIX = 'Sunnyside';
	public const CONTACT_FEMALE  = 'Alice';
	public const CONTACT_MALE    = 'Bob';

	private const NUMBER_WORDS = [ 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve' ];
	private const STREETS      = [ 'Island Street', 'Island Home', 'Island Road', 'Island Lane', 'Island Avenue', 'Island Harbour' ];

	/** „Sunnyside One" … „Sunnyside Twelve", danach „Sunnyside 13". */
	public static function customer_name( int $n ): string {
		$n = max( 1, $n );
		return self::CUSTOMER_PREFIX . ' ' . ( self::NUMBER_WORDS[ $n - 1 ] ?? (string) $n );
	}

	/**
	 * Body für POST /customer (Schema Customer, Pflicht: Name1, Street, PostalCode, City, SalesAccountNumber).
	 *
	 * @return array<string,string>
	 */
	public static function customer( int $n, string $sales_account_number, string $country = 'DE' ): array {
		$n = max( 1, $n );
		return [
			'Name1'              => self::customer_name( $n ),
			'Name2'              => 'Test Customer ARALIYA Interface',
			'Street'             => self::STREETS[ ( $n - 1 ) % count( self::STREETS ) ] . ' ' . $n,
			'PostalCode'         => sprintf( '%05d', 10000 + $n ),
			'City'               => 'Island City',
			'SalesAccountNumber' => $sales_account_number,
			'State'              => $country,
			'Note'               => 'Testdatensatz ARALIYA Interface Solutions – darf gelöscht werden.',
		];
	}

	/**
	 * Body für POST /contact (Schema Contact, Pflicht: LastName, LivisionCustomerId).
	 *
	 * @return array<string,int|string>
	 */
	public static function contact( int $livision_customer_id, bool $female = true, int $n = 1 ): array {
		$first = $female ? self::CONTACT_FEMALE : self::CONTACT_MALE;
		return [
			'Salutation'         => $female ? 'F' : 'H',
			'FirstName'          => $first,
			'LastName'           => self::CUSTOMER_PREFIX . ' ' . ( self::NUMBER_WORDS[ max( 1, $n ) - 1 ] ?? (string) $n ),
			'Function1'          => 'Test Contact',
			'Email'              => strtolower( $first ) . '.' . max( 1, $n ) . '@sunnyside.example',
			'Note'               => 'Testdatensatz ARALIYA Interface Solutions – darf gelöscht werden.',
			'LivisionCustomerId' => $livision_customer_id,
		];
	}

	/** Erkennbar als Testdatensatz nach Konvention (Prüfung vor jedem Schreibaufruf). */
	public static function is_test_customer_name( string $name ): bool {
		return 0 === strpos( $name, self::CUSTOMER_PREFIX . ' ' );
	}
}
