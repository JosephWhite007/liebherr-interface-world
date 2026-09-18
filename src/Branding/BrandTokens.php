<?php
/**
 * Liebherr Interface Solutions – Brand Tokens (CI-Fundament, §10–12).
 *
 * Zentrale, administrierbare Design-Tokens der Landingpage (Pflichtenheft §11). Solange kein
 * freigegebenes Liebherr-Brand-Kit vorliegt (§34), gelten NEUTRALE, industrielle Fallbacks –
 * KEIN erfundenes Liebherr-Logo/-Farbschema (CI-002, Markenschutz). Die im Brand Board gepflegten
 * Werte überschreiben die Fallbacks; sobald Liebherrs Freigabe dokumentiert ist, werden die in
 * `docs/LIW_BRAND_TOKENS.md` erfassten Originalwerte eingetragen und die zugehörigen Media-Board-
 * Assets auf „freigegeben" gesetzt.
 *
 * Ausgabe als CSS-Custom-Properties `--brand-*` über `wp_add_inline_style()` am Frontend-Stylesheet
 * (kein hart codiertes Markenwert in Komponenten, §11/§14). `sanitize()` und `css_from()` sind rein
 * (ohne WordPress) und damit unit-testbar.
 *
 * @package Liebherr\InterfaceWorld\Branding
 * @since   0.1.0-alpha.27
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Branding;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class BrandTokens {

	public const OPTION = 'liw_brand_tokens';

	/**
	 * Neutrale Fallbacks (industriell, markenneutral). Bewusst NICHT Liebherr-Gelb – erst nach
	 * dokumentierter Freigabe (dann aus docs/LIW_BRAND_TOKENS.md belegen).
	 *
	 * @var array<string,string|int>
	 */
	private const DEFAULTS = [
		'primary'      => '#3a3f45',
		'secondary'    => '#6b7278',
		'surface'      => '#ffffff',
		'text'         => '#1f1f1f',
		'muted'        => '#666666',
		'border'       => '#d9d9d9',
		'heading_font' => 'Arial, Helvetica, sans-serif',
		'body_font'    => 'Arial, Helvetica, sans-serif',
		'radius'       => '2px',
		'content_max'  => '1440px',
		'logo_id'      => 0,
	];

	/** CSS-Variablenname je Token-Schlüssel (Pflichtenheft §11). */
	private const CSS_VARS = [
		'primary'      => '--brand-primary',
		'secondary'    => '--brand-secondary',
		'surface'      => '--brand-surface',
		'text'         => '--brand-text',
		'muted'        => '--brand-muted',
		'border'       => '--brand-border',
		'heading_font' => '--font-heading',
		'body_font'    => '--font-body',
		'radius'       => '--radius-control',
		'content_max'  => '--content-max',
	];

	/** @return array<string,string|int> Fallback-Werte (Kopie). */
	public static function defaults(): array {
		return self::DEFAULTS;
	}

	/** @return array<string,string|int> Gemergte, bereinigte Tokens (Option über Fallbacks). */
	public static function get(): array {
		$stored = get_option( self::OPTION, [] );
		return self::sanitize( is_array( $stored ) ? $stored : [] );
	}

	/**
	 * Persistiert eingereichte Rohwerte (bereinigt).
	 *
	 * @param array<string,mixed> $raw
	 * @return array<string,string|int> die gespeicherten Werte
	 */
	public static function save( array $raw ): array {
		$clean = self::sanitize( $raw );
		update_option( self::OPTION, $clean );
		return $clean;
	}

	/**
	 * Rein: validiert/normalisiert; ungültige Werte fallen auf den Fallback zurück.
	 * Farben nur #rrggbb; Schriftstacks auf sichere Zeichen begrenzt (keine CSS-Injektion);
	 * Radius/Breite nur Zahl + px|rem|em|%; logo_id absint.
	 *
	 * @param array<string,mixed> $raw
	 * @return array<string,string|int>
	 */
	public static function sanitize( array $raw ): array {
		$out = self::DEFAULTS;

		foreach ( [ 'primary', 'secondary', 'surface', 'text', 'muted', 'border' ] as $key ) {
			if ( isset( $raw[ $key ] ) && is_string( $raw[ $key ] ) && preg_match( '/^#[0-9a-fA-F]{6}$/', trim( $raw[ $key ] ) ) ) {
				$out[ $key ] = strtolower( trim( $raw[ $key ] ) );
			}
		}

		foreach ( [ 'heading_font', 'body_font' ] as $key ) {
			if ( isset( $raw[ $key ] ) && is_string( $raw[ $key ] ) ) {
				$font = preg_replace( '/[^A-Za-z0-9 ,\-\'"]/', '', $raw[ $key ] );
				$font = trim( (string) preg_replace( '/\s+/', ' ', (string) $font ) );
				if ( '' !== $font && strlen( $font ) <= 120 ) {
					$out[ $key ] = $font;
				}
			}
		}

		foreach ( [ 'radius', 'content_max' ] as $key ) {
			if ( isset( $raw[ $key ] ) && is_string( $raw[ $key ] ) && preg_match( '/^\d{1,4}(px|rem|em|%)$/', trim( $raw[ $key ] ) ) ) {
				$out[ $key ] = trim( $raw[ $key ] );
			}
		}

		if ( isset( $raw['logo_id'] ) ) {
			$out['logo_id'] = max( 0, (int) $raw['logo_id'] );
		}

		return $out;
	}

	/**
	 * Rein: baut den `:root{ … }`-CSS-Block aus (bereits bereinigten) Tokens.
	 *
	 * @param array<string,string|int> $tokens
	 */
	public static function css_from( array $tokens ): string {
		$decls = [];
		foreach ( self::CSS_VARS as $key => $var ) {
			$val = (string) ( $tokens[ $key ] ?? self::DEFAULTS[ $key ] );
			// Sicherheitsnetz: kein Ausbruch aus dem Deklarationsblock oder dem <style>-Element.
			$val = str_replace( [ '}', '{', ';', '<', '>' ], '', $val );
			$decls[] = $var . ':' . $val;
		}
		return ':root{' . implode( ';', $decls ) . '}';
	}

	/** CSS-Block aus den aktuell gespeicherten Tokens. */
	public static function css_root(): string {
		return self::css_from( self::get() );
	}

	/** Gewählte Logo-Attachment-ID (0 = keine). */
	public static function logo_id(): int {
		return (int) self::get()['logo_id'];
	}
}
