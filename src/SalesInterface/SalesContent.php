<?php
/**
 * Liebherr Interface Solutions – Sales Interface: Inhalt der Schnittstellenbeschreibung.
 *
 * Datenquelle der geschützten Unterseite hinter der Process-World-Karte „Sales" (ADR-LIW-SALES-001).
 * Strukturierte, im Sales Board pflegbare Listen statt fest verdrahtetem Fließtext: Umgebungen, Endpunkt-
 * Gruppen, Endpunkte (Dealer Interface API v3), Datenmodell, Codelisten, Klärungspunkte, Stufenplan, Anleitung.
 * Ablage als Option `liw_sales_interface`. Standardinhalte via `__()` (mehrsprachig, wie ComponentContent);
 * redaktionelle Overrides sind literal. Enthält ausdrücklich KEINE Zugangsdaten (Keys liegen nur in Env,
 * Stufe 1c). Reine Struktur-/Sanitize-Logik, Standardwerte unit-testbar über `defaults()`.
 *
 * @package Liebherr\InterfaceWorld\SalesInterface
 * @since   0.1.0-alpha.146
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\SalesInterface;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SalesContent {

	public const OPTION = 'liw_sales_interface';

	public const STATUSES = [ 'draft', 'in_simulation', 'verified', 'approved', 'planned', 'in_progress', 'done' ];
	public const METHODS  = [ 'GET', 'POST', 'PUT', 'DELETE', 'PATCH' ];

	/** @var array<string,string[]> Listen-Schlüssel → Spalten (Reihenfolge = Eingabeformat „a | b | c" im Board). */
	public const LISTS = [
		'environments'   => [ 'key', 'label', 'url', 'note' ],
		'groups'         => [ 'key', 'label', 'text' ],
		'endpoints'      => [ 'group', 'method', 'path', 'summary', 'params', 'status' ],
		'schemas'        => [ 'name', 'required', 'fields' ],
		'codelists'      => [ 'code', 'name', 'entity' ],
		'clarifications' => [ 'title', 'text' ],
		'progress'       => [ 'title', 'text', 'status' ], // [alpha.163] Umsetzungsstand – was ist gebaut, was offen
		'roadmap'        => [ 'title', 'text', 'status' ],
		'guide'          => [ 'title', 'text' ],
	];

	/** @return array<string,mixed> */
	public static function get(): array {
		$stored = get_option( self::OPTION, [] );
		if ( ! is_array( $stored ) || [] === $stored ) {
			return self::defaults();
		}
		return self::sanitize( $stored );
	}

	/**
	 * @param array<string,mixed> $raw
	 * @return array<string,mixed>
	 */
	public static function save( array $raw ): array {
		$clean = self::sanitize( $raw );
		update_option( self::OPTION, $clean );
		return $clean;
	}

	/**
	 * @param array<string,mixed> $raw
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $raw ): array {
		$def = self::defaults();
		$out = [];

		$intro        = is_array( $raw['intro'] ?? null ) ? $raw['intro'] : [];
		$out['intro'] = [
			'title' => self::text( $intro['title'] ?? '', $def['intro']['title'] ),
			'lead'  => self::text( $intro['lead'] ?? '', $def['intro']['lead'] ),
		];
		$out['auth'] = self::text( $raw['auth'] ?? '', $def['auth'] );

		foreach ( self::LISTS as $list => $columns ) {
			$rows = [];
			if ( isset( $raw[ $list ] ) && is_array( $raw[ $list ] ) ) {
				foreach ( $raw[ $list ] as $row ) {
					$clean = is_array( $row ) ? self::sanitize_row( $list, $columns, $row ) : null;
					if ( null !== $clean ) {
						$rows[] = $clean;
					}
				}
			}
			$out[ $list ] = [] !== $rows ? $rows : $def[ $list ];
		}
		return $out;
	}

	/**
	 * @param string[]            $columns
	 * @param array<string,mixed> $row
	 * @return array<string,string>|null null = Zeile verwerfen (Pflichtspalte leer).
	 */
	private static function sanitize_row( string $list, array $columns, array $row ): ?array {
		$out = [];
		foreach ( $columns as $col ) {
			$out[ $col ] = sanitize_text_field( (string) ( $row[ $col ] ?? '' ) );
		}
		$first = $columns[0];
		if ( '' === $out[ $first ] ) {
			return null;
		}
		if ( 'endpoints' === $list ) {
			$out['group']  = sanitize_key( $out['group'] );
			$out['method'] = strtoupper( $out['method'] );
			if ( ! in_array( $out['method'], self::METHODS, true ) || '' === $out['group'] ) {
				return null;
			}
			$out['path'] = '/' . ltrim( $out['path'], '/' );
		}
		if ( in_array( $list, [ 'environments', 'groups' ], true ) ) {
			$out['key'] = sanitize_key( $out['key'] );
			if ( '' === $out['key'] ) {
				return null;
			}
		}
		if ( 'environments' === $list ) {
			$url        = esc_url_raw( $out['url'], [ 'https' ] );
			$out['url'] = $url;
		}
		if ( isset( $out['status'] ) && ! in_array( $out['status'], self::STATUSES, true ) ) {
			$out['status'] = 'draft';
		}
		return $out;
	}

	private static function text( $value, string $fallback ): string {
		$clean = sanitize_textarea_field( (string) $value );
		return '' !== trim( $clean ) ? $clean : $fallback;
	}

	/**
	 * Standardinhalt gemäß Tradler-Dealer-Interface-Guide (Sales API v3) und LIVISION-Auswahlliste.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return [
			'intro' => [
				'title' => __( 'Sales Interface', 'liebherr-interface-world' ),
				'lead'  => __( 'Schnittstellenbeschreibung der Liebherr Dealer Interface API (Sales API v3) für Händler-Anbindungen: Kunden, Ansprechpartner, Bedarfsfälle, Angebote und Aufträge.', 'liebherr-interface-world' ),
			],
			'auth' => __( 'Jeder Aufruf trägt einen Subscription-Key (Header „Ocp-Apim-Subscription-Key" oder Query „subscription-key"). Zusätzlich verlangt jeder Endpunkt den Header „UserEmail" mit dem aufrufenden Benutzer – Ausnahme: GET /sales-orders. Schlüsselwerte werden nie auf dieser Seite angezeigt; sie werden von Liebherr persönlich übergeben.', 'liebherr-interface-world' ),
			'environments' => [
				[ 'key' => 'test', 'label' => __( 'Test', 'liebherr-interface-world' ), 'url' => 'https://livision.azure-api.net/crm/v3', 'note' => __( 'Entwicklung und Abnahme; Schlüssel im Auftaktgespräch übergeben.', 'liebherr-interface-world' ) ],
				[ 'key' => 'production', 'label' => __( 'Produktion', 'liebherr-interface-world' ), 'url' => 'https://emt-api.liebherr.com/crm/v3', 'note' => __( 'Schlüssel erst nach abgeschlossener Entwicklung und Tests.', 'liebherr-interface-world' ) ],
				[ 'key' => 'portal', 'label' => __( 'Developer Portal (Test)', 'liebherr-interface-world' ), 'url' => 'https://livision.developer.azure-api.net/', 'note' => __( 'Interaktive API-Dokumentation von Liebherr.', 'liebherr-interface-world' ) ],
			],
			'groups' => [
				[ 'key' => 'customer', 'label' => __( 'Kunde & Kontakt', 'liebherr-interface-world' ), 'text' => __( 'Kunden und Ansprechpartner in Livision anlegen und aktualisieren.', 'liebherr-interface-world' ) ],
				[ 'key' => 'opportunity', 'label' => __( 'Bedarfsfall (Opportunity)', 'liebherr-interface-world' ), 'text' => __( 'Bedarfsfälle suchen, lesen, aktualisieren und als verloren melden.', 'liebherr-interface-world' ) ],
				[ 'key' => 'quotation', 'label' => __( 'Angebot & Livision-Links', 'liebherr-interface-world' ), 'text' => __( 'Angebote einsehen und per Deep-Link in Livision öffnen oder ändern.', 'liebherr-interface-world' ) ],
				[ 'key' => 'orders', 'label' => __( 'Aufträge', 'liebherr-interface-world' ), 'text' => __( 'Verkaufsaufträge abfragen.', 'liebherr-interface-world' ) ],
				[ 'key' => 'master', 'label' => __( 'Stammdaten', 'liebherr-interface-world' ), 'text' => __( 'Produktliste und übersetzte Codelisten-Texte.', 'liebherr-interface-world' ) ],
			],
			'endpoints' => [
				[ 'group' => 'customer', 'method' => 'POST', 'path' => '/customer', 'summary' => __( 'Kunde anlegen', 'liebherr-interface-world' ), 'params' => __( 'Body Customer. Pflicht: Name1, Street, PostalCode, City, SalesAccountNumber (= Kundennummer des Händlers, je Kunde eindeutig).', 'liebherr-interface-world' ), 'status' => 'verified' ],
				[ 'group' => 'customer', 'method' => 'PUT', 'path' => '/customer', 'summary' => __( 'Kunde aktualisieren', 'liebherr-interface-world' ), 'params' => __( 'Body CustomerUpdate. Pflicht wie Anlage plus LivisionCustomerId.', 'liebherr-interface-world' ), 'status' => 'verified' ],
				[ 'group' => 'customer', 'method' => 'POST', 'path' => '/contact', 'summary' => __( 'Ansprechpartner anlegen', 'liebherr-interface-world' ), 'params' => __( 'Body Contact. Pflicht: LastName, LivisionCustomerId.', 'liebherr-interface-world' ), 'status' => 'verified' ],
				[ 'group' => 'customer', 'method' => 'PUT', 'path' => '/contact', 'summary' => __( 'Ansprechpartner aktualisieren', 'liebherr-interface-world' ), 'params' => __( 'Body ContactUpdate. Pflicht: LastName, LivisionCustomerId, LivisionContactId.', 'liebherr-interface-world' ), 'status' => 'draft' ],
				[ 'group' => 'opportunity', 'method' => 'GET', 'path' => '/opportunity', 'summary' => __( 'Bedarfsfall suchen', 'liebherr-interface-world' ), 'params' => __( 'Optionale Filter: salesTerritory, customerName, customerZip, city, countryCode, opportunityType, realisation, advisor, contact, decisionDate.', 'liebherr-interface-world' ), 'status' => 'draft' ],
				[ 'group' => 'opportunity', 'method' => 'PUT', 'path' => '/opportunity', 'summary' => __( 'Bedarfsfall aktualisieren', 'liebherr-interface-world' ), 'params' => __( 'Body in der Spezifikation noch nicht definiert (siehe Klärungspunkte).', 'liebherr-interface-world' ), 'status' => 'draft' ],
				[ 'group' => 'opportunity', 'method' => 'DELETE', 'path' => '/opportunity', 'summary' => __( 'Bedarfsfall als verloren melden', 'liebherr-interface-world' ), 'params' => __( 'Body UpdateOpportunityBase/UpdateLostOpportunity: Opportunity_ID, CompetitorProduct_ID, Reason, ConstructionYear, Classification_CL, NewUsed_CL, SaleDate, Quantity.', 'liebherr-interface-world' ), 'status' => 'draft' ],
				[ 'group' => 'opportunity', 'method' => 'GET', 'path' => '/opportunity-id', 'summary' => __( 'Bedarfsfall-ID aus Angebot ermitteln', 'liebherr-interface-world' ), 'params' => __( 'Pflicht: QuotationId, QuotationVersion.', 'liebherr-interface-world' ), 'status' => 'draft' ],
				[ 'group' => 'opportunity', 'method' => 'GET', 'path' => '/opportunity/{opportunityId}', 'summary' => __( 'Bedarfsfall lesen', 'liebherr-interface-world' ), 'params' => __( 'Pfad: opportunityId (Ganzzahl).', 'liebherr-interface-world' ), 'status' => 'draft' ],
				[ 'group' => 'opportunity', 'method' => 'GET', 'path' => '/opportunity/{opportunityId}/configuration/{configurationId}', 'summary' => __( 'B2B-Konfiguration aus Bedarfsfall lesen', 'liebherr-interface-world' ), 'params' => __( 'Pfad: opportunityId (Ganzzahl), configurationId (Text).', 'liebherr-interface-world' ), 'status' => 'draft' ],
				[ 'group' => 'quotation', 'method' => 'GET', 'path' => '/livision-url', 'summary' => __( 'Deep-Link in Livision (Kunde/Kontakt/Bedarfsfall)', 'liebherr-interface-world' ), 'params' => __( 'Pflicht: LivisionCustomerId. Optional: LivisionContactId, LivisionOpportunityId.', 'liebherr-interface-world' ), 'status' => 'draft' ],
				[ 'group' => 'quotation', 'method' => 'GET', 'path' => '/livision-url-for-update', 'summary' => __( 'Deep-Link zum Ändern eines Angebots', 'liebherr-interface-world' ), 'params' => __( 'Pflicht: DocumentNo, DialogLanguage (ISO2), QuotationLanguage (ISO2). Optional: DocumentVersion.', 'liebherr-interface-world' ), 'status' => 'draft' ],
				[ 'group' => 'quotation', 'method' => 'GET', 'path' => '/lias-opentrans-document', 'summary' => __( 'OpenTrans-Dokument abrufen', 'liebherr-interface-world' ), 'params' => __( 'Pflicht: TransactionRowGuid. Optional: GetWithRtf (Standard false).', 'liebherr-interface-world' ), 'status' => 'draft' ],
				[ 'group' => 'quotation', 'method' => 'GET', 'path' => '/customer-quotation-list', 'summary' => __( 'Angebotsliste eines Kunden/Bedarfsfalls', 'liebherr-interface-world' ), 'params' => __( 'Pflicht: LivisionCustomerId, LivisionOpportunityId.', 'liebherr-interface-world' ), 'status' => 'draft' ],
				[ 'group' => 'orders', 'method' => 'GET', 'path' => '/sales-orders', 'summary' => __( 'Verkaufsaufträge abfragen', 'liebherr-interface-world' ), 'params' => __( 'Alle optional: configurationNumber, salesOrderNumber, LanguageCode, oderDateFrom, orderDateTo, SalesOrgCodeSupplier. Kein UserEmail-Header nötig.', 'liebherr-interface-world' ), 'status' => 'draft' ],
				[ 'group' => 'master', 'method' => 'GET', 'path' => '/product-list', 'summary' => __( 'Produktliste', 'liebherr-interface-world' ), 'params' => __( 'Nur Header UserEmail.', 'liebherr-interface-world' ), 'status' => 'verified' ],
				[ 'group' => 'master', 'method' => 'GET', 'path' => '/tab-text', 'summary' => __( 'Übersetzte Codelisten-Texte', 'liebherr-interface-world' ), 'params' => __( 'Pflicht: CodeList, Language (Zahl, siehe Codelisten).', 'liebherr-interface-world' ), 'status' => 'verified' ],
			],
			'schemas' => [
				[ 'name' => 'Customer', 'required' => 'Name1, Street, PostalCode, City, SalesAccountNumber', 'fields' => 'Name1:string; Name2:string; Street:string; StreetAdditional1:string; StreetAdditional2:string; PostalCode:string; City:string; Website:string; Email:string; SalesArea:string; FederalState:string; State:string; AdministrativeDistrict:string; ClassABC:string; ClassHAGB:string; Keyaccount:string; NumberOfEmployee:integer; SalesAccountNumber:string; InternationalDialingCodePhone:string; Phone:string; Telefax:string; InternationalDialingCodeFax:string; PostalCodePostOfficeBox:string; PostOfficeBox:string; LHZEK:string; Top300:string; Note:string; Industry1:string; Industry2:string; Industry3:string; CustomerInfo1:string; CustomerInfo2:string; CustomerInfo3:string; AdvisorFieldSales:string; AdvisorOffice:string; Advisor:string; LivisionCustomerId:integer; Branche:string; AddressInfo:string' ],
				[ 'name' => 'CustomerUpdate', 'required' => 'Name1, Street, PostalCode, City, SalesAccountNumber, LivisionCustomerId', 'fields' => __( 'Wie Customer, ohne Branche und AddressInfo.', 'liebherr-interface-world' ) ],
				[ 'name' => 'Contact', 'required' => 'LastName, LivisionCustomerId', 'fields' => 'Salutation:string; Title:string; FirstName:string; LastName:string; Function1:string; Function2:string; Department:string; Decider:string; Birthday:string; Hobby:string; InternationalDialingCodeMobile:string; Mobile:string; InternationalDialingCodePhone:string; Phone:string; InternationalDialingCodePhone2:string; Phone2:string; Email:string; Street:string; StreetAdditional1:string; StreetAdditional2:string; PostalCode:string; City:string; Note:string; Telefax:string; InternationalDialingCodeFax:string; PrivatePhone:string; InternationalDialingCodePrivatePhone:string; LivisionContactId:integer; LivisionCustomerId:integer' ],
				[ 'name' => 'ContactUpdate', 'required' => 'LastName, LivisionCustomerId, LivisionContactId', 'fields' => __( 'Wie Contact.', 'liebherr-interface-world' ) ],
				[ 'name' => 'Opportunity', 'required' => '', 'fields' => 'LivisionOpportunityId:integer; QuotationId:integer; CompetitorProductId:integer; Reason:string; CompetitorPrice:integer; CompetitorCurrency:string; ConstructionYear:integer; DeliveryDate:string; InventoryState:string; NewUsed:string; LivisionCustomerId:integer; SaleDate:string; ProductId:integer; Comment:string; Quantity:integer; PersonInCharge:string; Phase:string; OpportunityType:string' ],
			],
			'codelists' => [
				[ 'code' => '1B', 'name' => __( 'Bundesland Kunde', 'liebherr-interface-world' ), 'entity' => __( 'Kunde', 'liebherr-interface-world' ) ],
				[ 'code' => '1L', 'name' => __( 'Landkreis Kunde', 'liebherr-interface-world' ), 'entity' => __( 'Kunde', 'liebherr-interface-world' ) ],
				[ 'code' => '2K', 'name' => __( 'Adressinfo Kunde', 'liebherr-interface-world' ), 'entity' => __( 'Kunde', 'liebherr-interface-world' ) ],
				[ 'code' => '3B', 'name' => __( 'Branche Kunde', 'liebherr-interface-world' ), 'entity' => __( 'Kunde', 'liebherr-interface-world' ) ],
				[ 'code' => 'KL', 'name' => __( 'Klassifizierung Kunde', 'liebherr-interface-world' ), 'entity' => __( 'Kunde', 'liebherr-interface-world' ) ],
				[ 'code' => 'LN', 'name' => __( 'Land Kunde', 'liebherr-interface-world' ), 'entity' => __( 'Kunde', 'liebherr-interface-world' ) ],
				[ 'code' => 'AH', 'name' => __( 'Anrede Ansprechpartner', 'liebherr-interface-world' ), 'entity' => __( 'Ansprechpartner', 'liebherr-interface-world' ) ],
				[ 'code' => 'FN', 'name' => __( 'Funktion Ansprechpartner', 'liebherr-interface-world' ), 'entity' => __( 'Ansprechpartner', 'liebherr-interface-world' ) ],
				[ 'code' => 'Ma', 'name' => __( 'Marketing Aktion Ansprechpartner', 'liebherr-interface-world' ), 'entity' => __( 'Ansprechpartner', 'liebherr-interface-world' ) ],
				[ 'code' => 'PV', 'name' => __( 'Abteilung Ansprechpartner', 'liebherr-interface-world' ), 'entity' => __( 'Ansprechpartner', 'liebherr-interface-world' ) ],
				[ 'code' => '5G', 'name' => __( 'Grund Verlustmeldung', 'liebherr-interface-world' ), 'entity' => __( 'Bedarfsfall – Verlustmeldung', 'liebherr-interface-world' ) ],
				[ 'code' => 'BA', 'name' => __( 'Art Bedarfsfall', 'liebherr-interface-world' ), 'entity' => __( 'Bedarfsfall', 'liebherr-interface-world' ) ],
				[ 'code' => 'TF', 'name' => __( 'Realisierung Bedarfsfall', 'liebherr-interface-world' ), 'entity' => __( 'Bedarfsfall', 'liebherr-interface-world' ) ],
				[ 'code' => 'NG', 'name' => __( 'Neu / Gebraucht', 'liebherr-interface-world' ), 'entity' => __( 'Bedarfsfall, Maschine', 'liebherr-interface-world' ) ],
				[ 'code' => 'I1', 'name' => __( 'Inventarstatus Maschine', 'liebherr-interface-world' ), 'entity' => __( 'Maschine', 'liebherr-interface-world' ) ],
				[ 'code' => 'DL', 'name' => __( 'Thema Aktivität', 'liebherr-interface-world' ), 'entity' => __( 'Aktivität', 'liebherr-interface-world' ) ],
				[ 'code' => 'DL1', 'name' => __( 'Thema Aktivität (Variante)', 'liebherr-interface-world' ), 'entity' => __( 'Aktivität', 'liebherr-interface-world' ) ],
				[ 'code' => 'F6', 'name' => __( 'Abteilung Aktivität', 'liebherr-interface-world' ), 'entity' => __( 'Aktivität', 'liebherr-interface-world' ) ],
				[ 'code' => 'RP', 'name' => __( 'Ergebnis Aktivität', 'liebherr-interface-world' ), 'entity' => __( 'Aktivität', 'liebherr-interface-world' ) ],
				[ 'code' => 'SC', 'name' => __( 'Aktivität Aktivität', 'liebherr-interface-world' ), 'entity' => __( 'Aktivität', 'liebherr-interface-world' ) ],
			],
			'clarifications' => [
				[ 'title' => __( 'PUT /opportunity ohne Body', 'liebherr-interface-world' ), 'text' => __( 'Die Spezifikation definiert für das Aktualisieren eines Bedarfsfalls noch keinen Request-Body. Klärung mit Liebherr nötig, bevor der Endpunkt umgesetzt wird.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Parametername „oderDateFrom"', 'liebherr-interface-world' ), 'text' => __( 'GET /sales-orders führt „oderDateFrom" (vermutlich Tippfehler für orderDateFrom). Bis zur Bestätigung wird der Name genau so dokumentiert, wie er in der Spezifikation steht.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Sprachangaben', 'liebherr-interface-world' ), 'text' => __( 'Deep-Link-Endpunkte erwarten ISO2-Sprachcodes (z. B. DE), GET /tab-text erwartet eine Zahl (0 Deutsch, 1 Französisch, 2 Englisch, 3 Italienisch, 4 Spanisch, 5 Russisch, 6 Niederländisch, 7 Chinesisch laut Auswahlliste).', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'SalesAccountNumber', 'liebherr-interface-world' ), 'text' => __( 'Je Kunde eindeutig – Livision meldet „Customer already existing" bei Wiederverwendung. Die Plattform sendet die Händler-Kundennummer (ERP-Referenz, sonst Addressbook-Nummer). Bestätigung durch Liebherr angefragt.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'UserEmail', 'liebherr-interface-world' ), 'text' => __( 'Muss ein Livision-Benutzer des Händlers sein; andere Adressen liefern 401/400. Je Händler im Dealer Board bzw. in der Umgebung hinterlegt.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Codes der Produktliste', 'liebherr-interface-world' ), 'text' => __( 'Maschinenart_CL, Hersteller_CL und Aktiv_CL der Produktliste sind in keiner Codeliste der Auswahlliste enthalten; die Plattform zeigt bekannte Kürzel aus einer Ersatztabelle. Codeliste bei Liebherr angefragt.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Zugangsdaten', 'liebherr-interface-world' ), 'text' => __( 'Schlüssel für Test liegen vor und werden ausschließlich in der Server-Umgebung hinterlegt; Produktionsschlüssel erst nach Entwicklung und Tests.', 'liebherr-interface-world' ) ],
			],
			'progress' => [
				[ 'title' => __( 'Sales-Seite & Schnittstellenbeschreibung', 'liebherr-interface-world' ), 'text' => __( 'Geschützte Seite mit allen 17 Endpunkten, Datenmodell, Codelisten, Pflege im Dealer Board. Stufe 1a.', 'liebherr-interface-world' ), 'status' => 'done' ],
				[ 'title' => __( 'Codelisten (20 Listen, 8 Sprachen, 48 912 Einträge)', 'liebherr-interface-world' ), 'text' => __( 'Import aus der LIVISION-Auswahlliste, Suche auf dieser Seite, Klartexte im Addressbook (Klassifizierung, Branche). Stufe 1b.', 'liebherr-interface-world' ), 'status' => 'done' ],
				[ 'title' => __( 'Lesende Aufrufe gegen den Testserver', 'liebherr-interface-world' ), 'text' => __( 'tab-text und product-list verifiziert (Dealer Board → Verbindungsprobe, Protokoll). Stufe 1c.', 'liebherr-interface-world' ), 'status' => 'done' ],
				[ 'title' => __( 'Kunde und Ansprechpartner anlegen (schreibend)', 'liebherr-interface-world' ), 'text' => __( 'POST /customer und POST /contact mit Trockenlauf und Schreibschalter je Händler verifiziert (Testkunden Sunnyside, Livision-IDs im Addressbook). Stufe 1d, Teil 1.', 'liebherr-interface-world' ), 'status' => 'done' ],
				[ 'title' => __( 'Addressbook & Dealer Workspace', 'liebherr-interface-world' ), 'text' => __( 'Adress-Stammdaten je Händler (Tradler: 802 Adressen importiert), eigenes Login, Ansprechpartner, Artikel, Customer Solution Provider, Merkmale Branche/Anwendung/Potenzial, Übertragung an Livision aus der Maske.', 'liebherr-interface-world' ), 'status' => 'done' ],
				[ 'title' => __( 'Action Board', 'liebherr-interface-world' ), 'text' => __( '12 Top-Kunden des Tages (Pure, Pure Plus mit Ideenbaum, Detail, Karten), Klassifizierung, Hitlisten, 85 Zielbranchen, Branchenviews mit Bewertung, Know-how-Workflows, Ideen → Agent Board, Magic-Cube-Anbindung (lokale KI, Dev-Stub).', 'liebherr-interface-world' ), 'status' => 'done' ],
				[ 'title' => __( 'Produktliste als Artikelvorlage', 'liebherr-interface-world' ), 'text' => __( 'Spiegel von GET /product-list (14 701 Modelle) mit Übernahme je Händler ins Articlebook – Skript fertig, Import (--confirm) steht aus.', 'liebherr-interface-world' ), 'status' => 'in_progress' ],
				[ 'title' => __( 'Bedarfsfall, Angebot, Aufträge', 'liebherr-interface-world' ), 'text' => __( 'GET /opportunity ohne Filter liefert 500; PUT /opportunity ohne Body in der Spezifikation. Umsetzung nach Klärung mit Liebherr. Stufe 1d, Teil 2.', 'liebherr-interface-world' ), 'status' => 'planned' ],
				[ 'title' => __( 'Livision-Pull (Kunden aus Livision lesen)', 'liebherr-interface-world' ), 'text' => __( 'Abgleich Livision → Addressbook (Dubletten, KundeID). Geplant nach Teil 2.', 'liebherr-interface-world' ), 'status' => 'planned' ],
				[ 'title' => __( 'Rückfragen an Liebherr und Tradler', 'liebherr-interface-world' ), 'text' => __( 'Liebherr: Bestätigung SalesAccountNumber, Löschung der Testkunden, fehlende Codeliste Maschinenart/Hersteller/Aktiv der Produktliste. Tradler: vier Codes ohne Zuordnung (Vorführung, Gastronomie, Potenzial). Texte liegen versandfertig vor.', 'liebherr-interface-world' ), 'status' => 'in_progress' ],
				[ 'title' => __( 'Produktion', 'liebherr-interface-world' ), 'text' => __( 'Produktionsschlüssel und Freigabe erst nach abgeschlossener Entwicklung und Tests; die Plattform adressiert Produktion bisher nicht.', 'liebherr-interface-world' ), 'status' => 'planned' ],
			],
			'roadmap' => [
				[ 'title' => __( 'Stufe 1a – Beschreibung', 'liebherr-interface-world' ), 'text' => __( 'Geschützte Sales-Seite mit Schnittstellenbeschreibung und Pflege im Backend. Keine Aufrufe nach außen.', 'liebherr-interface-world' ), 'status' => 'done' ],
				[ 'title' => __( 'Stufe 1b – Codelisten & Suche', 'liebherr-interface-world' ), 'text' => __( 'Import der Codelisten (8 Sprachen) und Suche auf dieser Seite; die Sandbox zeigt die letzte reale Verbindungsprobe statt synthetischer Antworten.', 'liebherr-interface-world' ), 'status' => 'done' ],
				[ 'title' => __( 'Stufe 1c – Testserver (lesend)', 'liebherr-interface-world' ), 'text' => __( 'Lesende Aufrufe gegen den Liebherr-Testserver mit Protokoll (tab-text, product-list).', 'liebherr-interface-world' ), 'status' => 'done' ],
				[ 'title' => __( 'Stufe 1d – Schreibende Endpunkte', 'liebherr-interface-world' ), 'text' => __( 'Kunde und Kontakt anlegen/ändern verifiziert (Trockenlauf, Schreibschalter je Händler, Testkunden Sunnyside). Bedarfsfall nach Klärung des Request-Bodys.', 'liebherr-interface-world' ), 'status' => 'in_progress' ],
				[ 'title' => __( 'Stufe 2 – Addressbook als Quelle', 'liebherr-interface-world' ), 'text' => __( 'Stammdaten entstehen im Addressbook (Dealer Workspace) und werden per Knopf an Livision übertragen; Livision liefert IDs zurück. Produktliste als Artikelvorlage.', 'liebherr-interface-world' ), 'status' => 'done' ],
				[ 'title' => __( 'Stufe 3 – Pull und Bedarfsfälle', 'liebherr-interface-world' ), 'text' => __( 'Kunden aus Livision lesen und abgleichen; Bedarfsfälle, Angebote und Aufträge einbinden; Produktion freischalten.', 'liebherr-interface-world' ), 'status' => 'planned' ],
			],
			'guide' => [
				[ 'title' => __( 'Wozu diese Seite?', 'liebherr-interface-world' ), 'text' => __( 'Sie beschreibt die Liebherr Dealer Interface API (Sales API v3) für Händler-Anbindungen und dokumentiert, was die Plattform davon bereits nutzt. Sie ist nur mit Zugangscode erreichbar; Pflege der Inhalte im Backend unter Interface World → Sales Board.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Reiter lesen', 'liebherr-interface-world' ), 'text' => __( 'Übersicht: Zweck und Gruppen. Umsetzungsstand: was gebaut ist und was offen bleibt. Umgebungen & Zugang: Test/Produktion und Authentifizierung (Subscription-Key, UserEmail). Endpunkte: alle 17 Aufrufe je Gruppe mit Status. Datenmodell: Pflichtfelder und Feldlängen. Codelisten: die 20 Listen mit Suche in acht Sprachen. Sandbox: letzte reale Verbindungsprobe. Roadmap & Klärungspunkte: Stufenplan und offene Fragen an Liebherr. Anleitung: dieser Reiter.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Status der Endpunkte', 'liebherr-interface-world' ), 'text' => __( 'Entwurf = beschrieben, noch nicht erprobt. In Simulation = gegen synthetische Daten geprüft. Verifiziert = gegen den Liebherr-Testserver geprüft (derzeit: Kunde und Kontakt anlegen/ändern, Produktliste, Codelisten-Texte). Freigegeben = für den Einsatz mit Produktionsschlüssel freigegeben (noch keiner).', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Status im Umsetzungsstand und Stufenplan', 'liebherr-interface-world' ), 'text' => __( 'Erledigt = in der Plattform umgesetzt und in der Entwicklungsumgebung geprüft. In Arbeit = gebaut, aber ein Schritt steht aus (z. B. Import, Rückfrage). Geplant = Umsetzung nach Klärung oder Freigabe.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Wie die Übertragung an Livision läuft', 'liebherr-interface-world' ), 'text' => __( 'Kunden und Ansprechpartner entstehen im Addressbook (Dealer Workspace oder Backend). In der Adress- bzw. Ansprechpartner-Maske zeigt „Schnittstellen" die Vorprüfung (Pflichtfelder, Feldlängen, Codes) und den Knopf „An Livision übertragen". Voraussetzung: der Händler hat im Dealer Board Schlüssel, UserEmail und den Schreibschalter gesetzt. Livision liefert die KundeID/KontaktID zurück, die als externe Referenz gespeichert wird. Testkunden heißen immer Sunnyside (Alice/Bob); Produktion wird nicht adressiert.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Produktliste und Codelisten nutzen', 'liebherr-interface-world' ), 'text' => __( 'Die Produktliste (14 701 Modelle aller Hersteller) wird als Artikelvorlage gespiegelt; Händler übernehmen Modelle im Dealer Workspace → Artikel per Klick ins eigene Articlebook. Codelisten-Klartexte erscheinen in Klassifizierung, Branchen und Importberichten; die Suche auf dieser Seite hilft bei Codes in den Masken.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Fragen und Änderungen', 'liebherr-interface-world' ), 'text' => __( 'Offene Punkte stehen unter „Roadmap & Klärungspunkte"; die Rückfragetexte an Liebherr und Tradler liegen im Projektordner. Rückfragen bitte an das GoHeal-Entwicklerteam.', 'liebherr-interface-world' ) ],
			],
		];
	}

	/**
	 * Board-Eingabe „a | b | c" je Zeile → Zeilenliste (nur Spalten der Liste, Rest verfällt).
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function parse_lines( string $list, string $raw ): array {
		$columns = self::LISTS[ $list ] ?? [];
		$rows    = [];
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) ?: [] as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			$parts = array_map( 'trim', explode( '|', $line, count( $columns ) ) );
			$row   = [];
			foreach ( $columns as $i => $col ) {
				$row[ $col ] = $parts[ $i ] ?? '';
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/** Zeilenliste → Board-Textarea (Umkehrung von {@see parse_lines()}). */
	public static function format_lines( string $list, array $rows ): string {
		$columns = self::LISTS[ $list ] ?? [];
		$lines   = [];
		foreach ( $rows as $row ) {
			$cells = [];
			foreach ( $columns as $col ) {
				$cells[] = str_replace( '|', '/', (string) ( $row[ $col ] ?? '' ) );
			}
			$lines[] = rtrim( implode( ' | ', $cells ), ' |' );
		}
		return implode( "\n", $lines );
	}
}
