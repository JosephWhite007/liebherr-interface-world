<?php
/**
 * Liebherr Adventures – Tokenkonto je Nutzer (Basislogik §5/§6, Backlog A5).
 *
 * Persistiertes Tokenguthaben pro Nutzer (User-Meta `_liw_adv_token_balance`) statt eines pauschalen
 * Filter-Defaults. Beim ersten Zugriff wird das Start-Guthaben gewährt (Option `liw_adv_token_default`,
 * Standard 1000; per Filter `liw_adv_token_default` überschreibbar). `charge()` bucht nur bei ausreichendem
 * Guthaben ab (verhindert Überziehung); `grant()` lädt auf. Prototyp (§21): kein echtes Zahlungsmittel.
 *
 * @package Liebherr\InterfaceWorld\Adventures
 * @since   0.1.0-alpha.66
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Adventures;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class TokenAccount {

	public const META_BALANCE = '_liw_adv_token_balance';

	/** Start-Guthaben eines neuen Kontos (administrierbar). */
	public static function default_balance(): int {
		$def = (int) get_option( 'liw_adv_token_default', 1000 );
		return max( 0, (int) apply_filters( 'liw_adv_token_default', $def ) );
	}

	/** Aktuelles Guthaben; legt bei Erstzugriff das Start-Guthaben an. 0 für Gast (user_id <= 0). */
	public static function balance( int $user_id ): int {
		if ( $user_id <= 0 ) {
			return 0;
		}
		$raw = get_user_meta( $user_id, self::META_BALANCE, true );
		if ( '' === $raw || null === $raw ) {
			$start = self::default_balance();
			update_user_meta( $user_id, self::META_BALANCE, $start );
			return $start;
		}
		return max( 0, (int) $raw );
	}

	/** Bucht `$amount` ab, wenn das Guthaben reicht. Gibt bei Erfolg das neue Guthaben zurück, sonst null. */
	public static function charge( int $user_id, int $amount ): ?int {
		if ( $user_id <= 0 || $amount < 0 ) {
			return null;
		}
		$balance = self::balance( $user_id );
		if ( $amount > $balance ) {
			return null;
		}
		$new = $balance - $amount;
		update_user_meta( $user_id, self::META_BALANCE, $new );
		return $new;
	}

	/** Lädt das Konto um `$amount` auf. Gibt das neue Guthaben zurück. */
	public static function grant( int $user_id, int $amount ): int {
		if ( $user_id <= 0 ) {
			return 0;
		}
		$new = self::balance( $user_id ) + max( 0, $amount );
		update_user_meta( $user_id, self::META_BALANCE, $new );
		return $new;
	}
}
