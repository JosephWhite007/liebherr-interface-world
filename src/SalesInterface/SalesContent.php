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
				[ 'group' => 'customer', 'method' => 'POST', 'path' => '/customer', 'summary' => __( 'Kunde anlegen', 'liebherr-interface-world' ), 'params' => __( 'Body Customer. Pflicht: Name1, Street, PostalCode, City, SalesAccountNumber.', 'liebherr-interface-world' ), 'status' => 'draft' ],
				[ 'group' => 'customer', 'method' => 'PUT', 'path' => '/customer', 'summary' => __( 'Kunde aktualisieren', 'liebherr-interface-world' ), 'params' => __( 'Body CustomerUpdate. Pflicht wie Anlage plus LivisionCustomerId.', 'liebherr-interface-world' ), 'status' => 'draft' ],
				[ 'group' => 'customer', 'method' => 'POST', 'path' => '/contact', 'summary' => __( 'Ansprechpartner anlegen', 'liebherr-interface-world' ), 'params' => __( 'Body Contact. Pflicht: LastName, LivisionCustomerId.', 'liebherr-interface-world' ), 'status' => 'draft' ],
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
				[ 'group' => 'master', 'method' => 'GET', 'path' => '/product-list', 'summary' => __( 'Produktliste', 'liebherr-interface-world' ), 'params' => __( 'Nur Header UserEmail.', 'liebherr-interface-world' ), 'status' => 'draft' ],
				[ 'group' => 'master', 'method' => 'GET', 'path' => '/tab-text', 'summary' => __( 'Übersetzte Codelisten-Texte', 'liebherr-interface-world' ), 'params' => __( 'Pflicht: CodeList, Language (Zahl, siehe Codelisten).', 'liebherr-interface-world' ), 'status' => 'draft' ],
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
				[ 'title' => __( 'Zugangsdaten', 'liebherr-interface-world' ), 'text' => __( 'Schlüssel für Test liegen vor und werden ausschließlich in der Server-Umgebung hinterlegt; Produktionsschlüssel erst nach Entwicklung und Tests.', 'liebherr-interface-world' ) ],
			],
			'roadmap' => [
				[ 'title' => __( 'Stufe 1a – Beschreibung', 'liebherr-interface-world' ), 'text' => __( 'Geschützte Sales-Seite mit Schnittstellenbeschreibung und Pflege im Backend. Keine Aufrufe nach außen.', 'liebherr-interface-world' ), 'status' => 'in_progress' ],
				[ 'title' => __( 'Stufe 1b – Codelisten & Sandbox', 'liebherr-interface-world' ), 'text' => __( 'Import der Codelisten (8 Sprachen), Suche und Sandbox mit synthetischen Antworten.', 'liebherr-interface-world' ), 'status' => 'planned' ],
				[ 'title' => __( 'Stufe 1c – Testserver (lesend)', 'liebherr-interface-world' ), 'text' => __( 'Lesende Aufrufe gegen den Liebherr-Testserver mit Protokoll.', 'liebherr-interface-world' ), 'status' => 'planned' ],
				[ 'title' => __( 'Stufe 1d – Schreibende Endpunkte', 'liebherr-interface-world' ), 'text' => __( 'Kunde, Kontakt und Bedarfsfall anlegen/ändern, einzeln freigegeben.', 'liebherr-interface-world' ), 'status' => 'planned' ],
			],
			'guide' => [
				[ 'title' => __( 'Wozu diese Seite?', 'liebherr-interface-world' ), 'text' => __( 'Sie beschreibt die Liebherr Dealer Interface API für den Bereich Sales, damit Händler-Systeme sauber angebunden werden können. Sie ist nur mit Zugangscode erreichbar.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Reiter lesen', 'liebherr-interface-world' ), 'text' => __( 'Übersicht zeigt Zweck und Stand. Umgebungen & Zugang erklärt Test/Produktion und die Authentifizierung. Endpunkte listet alle Aufrufe je Gruppe mit Status. Datenmodell nennt die Felder. Codelisten verweist auf die Liebherr-Auswahllisten.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Status der Endpunkte', 'liebherr-interface-world' ), 'text' => __( 'Entwurf = beschrieben, noch nicht erprobt. In Simulation = gegen synthetische Daten geprüft. Verifiziert = gegen den Testserver geprüft. Freigegeben = für den Einsatz bestätigt.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Fragen und Änderungen', 'liebherr-interface-world' ), 'text' => __( 'Offene Punkte stehen unter „Klärungspunkte". Rückfragen bitte an das GoHeal-Entwicklerteam.', 'liebherr-interface-world' ) ],
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
