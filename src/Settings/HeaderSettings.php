<?php
/**
 * Liebherr Interface Solutions – Header-/Navigations-Einstellungen (§7/§16).
 *
 * Administrierbare Konfiguration der Hauptnavigation: Menüpunkte (Label + Ziel), Primär-/Sekundär-CTA,
 * optionaler Portal-Login und Hero-Bild. Ablage als Option `liw_header`; Standardwerte entsprechen §7/§8
 * (Solution, Architecture, Magic Cube, World Connections, Security, Contact; „Start Integration" /
 * „Explore the Simulation"). Standard-Labels laufen über `__()` (übersetzbar); redaktionell überschriebene
 * Labels sind literal (per-Sprache-Labels = Verfeinerung, s. To-Dos). Ziel-Normalisierung ist rein/testbar.
 *
 * @package Liebherr\InterfaceWorld\Settings
 * @since   0.1.0-alpha.28
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Settings;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class HeaderSettings {

	public const OPTION = 'liw_header';

	/**
	 * Standardkonfiguration (§7/§8). Labels via __() – erst zur Laufzeit, daher als Methode.
	 *
	 * @return array{nav:array<int,array{label:string,target:string}>,cta_primary:array{label:string,target:string},cta_secondary:array{label:string,target:string},portal_enabled:bool,portal_url:string,hero_image_id:int}
	 */
	public static function defaults(): array {
		return [
			'nav' => [
				[ 'label' => __( 'Solution', 'liebherr-interface-world' ), 'target' => '#lp-02' ],
				[ 'label' => __( 'Architecture', 'liebherr-interface-world' ), 'target' => '#lp-03' ],
				[ 'label' => __( 'Magic Cube', 'liebherr-interface-world' ), 'target' => '#lp-04' ],
				[ 'label' => __( 'World Connections', 'liebherr-interface-world' ), 'target' => '#lp-06' ],
				[ 'label' => __( 'Security', 'liebherr-interface-world' ), 'target' => '#lp-10' ],
				[ 'label' => __( 'Contact', 'liebherr-interface-world' ), 'target' => '#lp-13' ],
			],
			'cta_primary'    => [ 'label' => __( 'Start Integration', 'liebherr-interface-world' ), 'target' => '#lp-13' ],
			'cta_secondary'  => [ 'label' => __( 'Explore the Simulation', 'liebherr-interface-world' ), 'target' => '#lp-04' ],
			'portal_enabled' => false,
			'portal_url'     => '',
			'hero_image_id'  => 0,
		];
	}

	/** @return array<string,mixed> gemergte, bereinigte Einstellungen. */
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
		$out = $def;

		if ( isset( $raw['nav'] ) && is_array( $raw['nav'] ) ) {
			$nav = [];
			foreach ( $raw['nav'] as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$label  = sanitize_text_field( (string) ( $item['label'] ?? '' ) );
				$target = self::normalize_target( (string) ( $item['target'] ?? '' ) );
				if ( '' !== $label && '' !== $target ) {
					$nav[] = [ 'label' => $label, 'target' => $target ];
				}
			}
			$out['nav'] = $nav; // darf leer sein (Navigation abschaltbar).
		}

		foreach ( [ 'cta_primary', 'cta_secondary' ] as $key ) {
			if ( isset( $raw[ $key ] ) && is_array( $raw[ $key ] ) ) {
				$label  = sanitize_text_field( (string) ( $raw[ $key ]['label'] ?? '' ) );
				$target = self::normalize_target( (string) ( $raw[ $key ]['target'] ?? '' ) );
				$out[ $key ] = [
					'label'  => '' !== $label ? $label : $def[ $key ]['label'],
					'target' => '' !== $target ? $target : $def[ $key ]['target'],
				];
			}
		}

		$out['portal_enabled'] = ! empty( $raw['portal_enabled'] );
		$out['portal_url']     = isset( $raw['portal_url'] ) ? esc_url_raw( trim( (string) $raw['portal_url'] ) ) : '';
		$out['hero_image_id']  = isset( $raw['hero_image_id'] ) ? max( 0, (int) $raw['hero_image_id'] ) : 0;

		return $out;
	}

	/**
	 * Rein (ohne WordPress): normalisiert ein Navigations-/CTA-Ziel.
	 * Erlaubt einen In-Page-Anker (`#abschnitt`), einen relativen Pfad (`/interface-world/…`)
	 * oder eine absolute http(s)-URL. Alles andere wird verworfen (leer).
	 */
	public static function normalize_target( string $target ): string {
		$target = trim( $target );
		if ( '' === $target ) {
			return '';
		}
		if ( '#' === $target[0] ) {
			$frag = preg_replace( '/[^A-Za-z0-9_\-]/', '', substr( $target, 1 ) );
			return '' !== (string) $frag ? '#' . $frag : '';
		}
		if ( '/' === $target[0] && ! str_starts_with( $target, '//' ) ) {
			return preg_replace( '/[\s"\'<>]/', '', $target ) ?? '';
		}
		if ( preg_match( '#^https?://#i', $target ) ) {
			return preg_replace( '/[\s"\'<>]/', '', $target ) ?? '';
		}
		return '';
	}
}
