<?php
/**
 * Liebherr World – My Liebherr: Regeln für Dreams/Gallery/Shares (Pflichtenheft My Liebherr §31/§32, ADR-LIW-MYL-001 R3).
 *
 * Reine, WordPress-freie Wertlisten und Normalisierer (Wunschstatus, Sichtbarkeit, Freigabe-Scope,
 * Empfängertyp, Drei-Wort-Titel). Grundlage für Repositories/REST; ohne WP testbar.
 *
 * @package Liebherr\InterfaceWorld\MyLiebherr
 * @since   0.1.0-alpha.118
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\MyLiebherr;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ContentRules {

	public const WISH        = [ 'idea', 'wish', 'favorite' ];
	public const VISIBILITY  = [ 'private', 'colleagues', 'world' ];
	public const SCOPE       = [ 'view', 'comment', 'download' ];
	public const RECIPIENT   = [ 'user', 'team', 'world' ];

	public static function wish( string $v ): string {
		return in_array( $v, self::WISH, true ) ? $v : 'idea';
	}

	public static function visibility( string $v ): string {
		return in_array( $v, self::VISIBILITY, true ) ? $v : 'private';
	}

	public static function scope( string $v ): string {
		return in_array( $v, self::SCOPE, true ) ? $v : 'view';
	}

	public static function recipient_type( string $v ): string {
		return in_array( $v, self::RECIPIENT, true ) ? $v : 'user';
	}

	/**
	 * Normalisiert einen Drei-Wort-Titel: trimmt, verdichtet Leerraum, begrenzt auf max. drei Wörter (§31/§32).
	 */
	public static function three_words( string $s ): string {
		$s     = trim( preg_replace( '/\s+/', ' ', $s ) ?? '' );
		if ( '' === $s ) {
			return '';
		}
		$parts = array_slice( explode( ' ', $s ), 0, 3 );
		return implode( ' ', $parts );
	}

	/** Genau drei Wörter? (für strikte Drei-Wort-Namen, §32). */
	public static function is_three_words( string $s ): bool {
		$s = trim( preg_replace( '/\s+/', ' ', $s ) ?? '' );
		return '' !== $s && 3 === count( explode( ' ', $s ) );
	}
}
