<?php
/**
 * Liebherr Interface Solutions – Partner Bridge
 *
 * Dünner Wrapper um `Araliya\Platform\Core\Modules\Partner\PartnerService` (verifiziert:
 * generische CRUD-Schicht über `ary_partners`, keine Guest-/Health-Bindung – anders als
 * ConsentService/ChangeRequestService, s. ADR-LIW-001). Wiederverwendet für das
 * Onboarding-Formular (Liebherr-Pflichtenheft §22) statt einer eigenen Partnertabelle
 * (Entscheidung JW 17.09.2026, Variante A).
 *
 * WICHTIGER BEFUND (geprüft vor Implementierung, CLAUDE.md Abschnitt 8 „nicht raten"):
 * `PartnerService::VALID_TYPES` ist auf `['clinic','doctor','wellness','supplier',
 * 'insurance','general']` begrenzt (Health-Domain-Vokabular). Liebherrs eigene
 * Partnertypen (dealer/supplier/customer, s. `Connection\ConnectionService`) passen dort
 * NICHT 1:1 hinein – `dealer` und `customer` sind keine gültigen Core-`partner_type`-Werte.
 * Statt Core's Enum zu erweitern (Kategorie-A-Core-Änderung, hier nicht vorgenommen):
 * Liebherr-Partner werden in `ary_partners.partner_type` immer als `'general'` angelegt
 * (neutral, bereits gültig), die eigentliche Liebherr-Klassifizierung (dealer/supplier/
 * customer) lebt ausschließlich in `Onboarding\OnboardingSchema` (`liw_partner_extra`).
 * So bleibt `ary_partners` unverändert und Core-konform, Liebherr-Fachlichkeit ist sauber
 * getrennt (ADR-LIW-001-Prinzip: Reuse ohne Zweckentfremdung).
 *
 * @package Liebherr\InterfaceWorld\CoreBridge
 * @since   0.1.0-alpha.6
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\CoreBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class PartnerBridge {

	private const PARTNER_SERVICE_CLASS = 'Araliya\\Platform\\Core\\Modules\\Partner\\PartnerService';

	/** ary_partners.partner_type für alle über Liebherr angelegten Partner (s. Klassenkommentar). */
	public const NEUTRAL_PARTNER_TYPE = 'general';

	public static function is_available(): bool {
		return class_exists( self::PARTNER_SERVICE_CLASS );
	}

	/** @param array<string, mixed> $data */
	public static function create( array $data ): int|\WP_Error {
		if ( ! self::is_available() ) {
			return new \WP_Error( 'liw_core_unavailable', __( 'ARALIYA Platform Core (Partnermodul) ist nicht verfügbar.', 'liebherr-interface-world' ) );
		}

		$data['partner_type'] = self::NEUTRAL_PARTNER_TYPE;

		return call_user_func( [ self::PARTNER_SERVICE_CLASS, 'create' ], $data );
	}

	/** @param array<string, mixed> $data */
	public static function update( int $partner_id, array $data ): bool|\WP_Error {
		if ( ! self::is_available() ) {
			return new \WP_Error( 'liw_core_unavailable', __( 'ARALIYA Platform Core (Partnermodul) ist nicht verfügbar.', 'liebherr-interface-world' ) );
		}

		// partner_type bleibt bewusst unveränderbar über diese Bridge (s. Klassenkommentar).
		unset( $data['partner_type'] );

		return call_user_func( [ self::PARTNER_SERVICE_CLASS, 'update' ], $partner_id, $data );
	}

	/** @return array<string, mixed>|null */
	public static function get( int $partner_id ): ?array {
		if ( ! self::is_available() ) {
			return null;
		}

		return call_user_func( [ self::PARTNER_SERVICE_CLASS, 'get' ], $partner_id );
	}
}
