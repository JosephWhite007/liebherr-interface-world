<?php
/**
 * Liebherr Interface Solutions – Sichtbarkeits-Zeitfenster für Abschnitte (§19).
 *
 * Optionales Zeitfenster je `liw_section` über den nativen `future`-Status hinaus: ein Abschnitt kann
 * ab `valid_from` und/oder bis `valid_until` sichtbar geschaltet werden. Beide Werte sind optional
 * (leer = keine Grenze) und werden als UTC gespeichert. Reine `is_within_window()` ist ohne WordPress
 * testbar; die Speicherung/Anzeige (Zeitzonen-Umrechnung) übernimmt die Metabox.
 *
 * @package Liebherr\InterfaceWorld\Content
 * @since   0.1.0-alpha.36
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Content;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SectionSchedule {

	public const META_FROM  = '_liw_valid_from';
	public const META_UNTIL = '_liw_valid_until';

	/**
	 * Rein: liegt „jetzt" (UTC-Timestamp) im Fenster? Leere Grenzen = offen. Ungültige Werte
	 * werden ignoriert (als offen behandelt), damit ein Tippfehler nie unbeabsichtigt alles ausblendet.
	 */
	public static function is_within_window( ?string $from_utc, ?string $until_utc, int $now_ts ): bool {
		$from = ( null !== $from_utc && '' !== trim( $from_utc ) ) ? strtotime( $from_utc . ' UTC' ) : false;
		if ( false !== $from && $now_ts < $from ) {
			return false;
		}
		$until = ( null !== $until_utc && '' !== trim( $until_utc ) ) ? strtotime( $until_utc . ' UTC' ) : false;
		if ( false !== $until && $now_ts > $until ) {
			return false;
		}
		return true;
	}

	/** @return array{from:string,until:string} gespeicherte UTC-Werte (leer = keine Grenze). */
	public static function window_of( int $post_id ): array {
		return [
			'from'  => (string) get_post_meta( $post_id, self::META_FROM, true ),
			'until' => (string) get_post_meta( $post_id, self::META_UNTIL, true ),
		];
	}

	/** Ist der Abschnitt jetzt (Serverzeit UTC) im Sichtbarkeitsfenster? */
	public static function is_visible_now( int $post_id, ?int $now_ts = null ): bool {
		$w = self::window_of( $post_id );
		return self::is_within_window( $w['from'], $w['until'], $now_ts ?? time() );
	}

	/** Ist überhaupt ein Fenster gesetzt (für Kennzeichnung im Board)? */
	public static function has_window( int $post_id ): bool {
		$w = self::window_of( $post_id );
		return '' !== $w['from'] || '' !== $w['until'];
	}
}
