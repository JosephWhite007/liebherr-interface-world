<?php
/**
 * Liebherr Interface Solutions – Webfont-Einbindung (@font-face) für die CI (§11, alpha.37).
 *
 * Erzeugt @font-face-Regeln für die im Media Board FREIGEGEBENEN Liebherr-Webfonts (Rolle „font"),
 * damit die Brand-Token-Schriften (LiebherrHead/LiebherrText) tatsächlich rendern. Nicht freigegebene
 * Fonts werden nicht eingebunden (CI-005). Familie/Gewicht/Stil werden aus dem Dateinamen abgeleitet.
 * Ausgabe über `wp_add_inline_style` am Frontend-Stylesheet.
 *
 * @package Liebherr\InterfaceWorld\Frontend
 * @since   0.1.0-alpha.37
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Frontend;

use Liebherr\InterfaceWorld\CoreBridge\MediaBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FontFaceService {

	/** Dateiname-Muster → [Familie, Gewicht, Stil]. */
	private const MAP = [
		'LiebherrHead-Regular' => [ 'LiebherrHead', '400', 'normal' ],
		'LiebherrHead-Black'   => [ 'LiebherrHead', '900', 'normal' ],
		'LiebherrText-Regular' => [ 'LiebherrText', '400', 'normal' ],
		'LiebherrText-Medium'  => [ 'LiebherrText', '500', 'normal' ],
		'LiebherrText-Bold'    => [ 'LiebherrText', '700', 'normal' ],
	];

	/** @return string @font-face-CSS der freigegebenen Fonts (leer, wenn keine freigegeben). */
	public static function css(): string {
		$fonts = get_posts( [
			'post_type'      => 'attachment',
			'post_mime_type' => 'font/woff2',
			'post_status'    => 'inherit',
			'posts_per_page' => 20,
			'no_found_rows'  => true,
			'fields'         => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => [ [ 'key' => '_liw_media_role', 'value' => 'font', 'compare' => '=' ] ],
		] );

		$css = '';
		foreach ( (array) $fonts as $id ) {
			$id = (int) $id;
			if ( ! MediaBridge::is_approved( $id ) ) {
				continue; // CI-005: nur freigegebene Fonts einbinden.
			}
			$url  = (string) wp_get_attachment_url( $id );
			$file = wp_basename( (string) get_post_meta( $id, '_wp_attached_file', true ) );
			foreach ( self::MAP as $needle => $spec ) {
				if ( false !== stripos( $file, $needle ) ) {
					$css .= sprintf(
						"@font-face{font-family:'%s';font-style:%s;font-weight:%s;font-display:swap;src:url('%s') format('woff2');}",
						$spec[0],
						$spec[2],
						$spec[1],
						esc_url( $url )
					);
					break;
				}
			}
		}
		return $css;
	}
}
