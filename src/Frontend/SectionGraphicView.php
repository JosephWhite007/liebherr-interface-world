<?php
/**
 * Liebherr Interface Solutions – Section Graphic View (Inline-SVG-Shortcode)
 *
 * Öffentlicher Shortcode `[liw_graphic name="data-model"]` bzw. `name="process-worlds"`
 * (Liebherr-Pflichtenheft §8, LP-07 Data Model / LP-08 Process Worlds). Bettet die mit
 * alpha.12 gelieferten, anonymisierten SVG-Grafiken **inline** in den Inhalt eines
 * `liw_section`-Beitrags ein – bewusst nicht als `<img src>`, weil nur Inline-Markup die
 * `var(--ary-*)`-Design-Tokens der Seiten-CSS übernimmt (s. docs/LIW_LANDINGPAGE_KONZEPT.md,
 * „Offene Punkte" → jetzt eingelöst).
 *
 * Sicherheit (CLAUDE.md „Sicherheit"; Joseph 18.09.2026: Plattform muss gegen Angriffe von
 * außen absolut sicher sein):
 * - Kein Dateipfad-Parameter. `name` wird ausschließlich gegen die feste Whitelist
 *   `GRAPHICS` aufgelöst; jeder andere Wert liefert einen leeren String. Damit ist
 *   Path-Traversal/beliebiges Datei-Lesen per Shortcode-Attribut ausgeschlossen.
 * - Zusätzlicher realpath()-Guard: die aufgelöste Datei muss innerhalb von
 *   `LIW_PATH . 'assets/img/'` liegen, sonst keine Ausgabe.
 * - Die SVG-Dateien sind vom Plugin selbst ausgeliefertes, versioniertes Markup (kein
 *   Nutzer-Upload), daher direkte Ausgabe ohne wp_kses (das SVG ohnehin nicht kennt).
 *   Alle Attributwerte, die aus dem Shortcode stammen (caption), werden escaped.
 *
 * Performance: Datei wird pro Request höchstens einmal gelesen (statischer Cache);
 * Dateien sind wenige KB groß, ein Transient wäre YAGNI.
 *
 * Kein Inline-CSS/JS – Gestaltung über `.liw-graphic*`-Klassen in
 * assets/css/liebherr-frontend.css (ANNAHME-LIW-7: eine Grafik erscheint je Seite nur einmal;
 * die SVGs tragen feste `id`s für title/desc, bei doppelter Einbettung wären die ids
 * mehrfach vorhanden – akzeptiert, da LP-07/LP-08 je genau einen Abschnitt haben).
 *
 * @package Liebherr\InterfaceWorld\Frontend
 * @since   0.1.0-alpha.15
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Frontend;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SectionGraphicView {

	public const SHORTCODE = 'liw_graphic';

	/**
	 * Whitelist: Shortcode-Name → Dateiname unter assets/img/. Einzige Quelle für
	 * erlaubte Grafiken – neue Grafiken werden hier ergänzt, nie per Attribut übergeben.
	 */
	public const GRAPHICS = [
		'data-model'     => 'liw-data-model.svg',     // LP-07
		'process-worlds' => 'liw-process-worlds.svg', // LP-08
	];

	/** @var array<string, string> Pro Request gelesene SVG-Inhalte (name → markup). */
	private static array $cache = [];

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'render_shortcode' ] );
	}

	/**
	 * @param array<string, string>|string $atts Shortcode-Attribute (WP übergibt '' ohne Attribute).
	 */
	public static function render_shortcode( $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'name'    => '',
				'caption' => '',
			],
			is_array( $atts ) ? $atts : [],
			self::SHORTCODE
		);

		$name = sanitize_key( (string) $atts['name'] );
		$svg  = self::get_svg_markup( $name );
		if ( '' === $svg ) {
			return '';
		}

		$caption = sanitize_text_field( (string) $atts['caption'] );

		ob_start();
		?>
		<figure class="liw-graphic-figure liw-graphic-figure--<?php echo esc_attr( $name ); ?>">
			<?php echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- eigenes, versioniertes Plugin-Asset aus fester Whitelist (s. Klassen-Doc). ?>
			<?php if ( '' !== $caption ) : ?>
				<figcaption class="liw-graphic-figure__caption"><?php echo esc_html( $caption ); ?></figcaption>
			<?php endif; ?>
		</figure>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Liefert das SVG-Markup einer Whitelist-Grafik oder '' bei unbekanntem Namen,
	 * fehlender Datei oder Pfad außerhalb von assets/img/.
	 */
	public static function get_svg_markup( string $name ): string {
		if ( ! isset( self::GRAPHICS[ $name ] ) ) {
			return '';
		}

		if ( isset( self::$cache[ $name ] ) ) {
			return self::$cache[ $name ];
		}

		$base = realpath( LIW_PATH . 'assets/img' );
		$file = realpath( LIW_PATH . 'assets/img/' . self::GRAPHICS[ $name ] );

		if ( false === $base || false === $file || ! str_starts_with( $file, $base . DIRECTORY_SEPARATOR ) || ! is_readable( $file ) ) {
			self::$cache[ $name ] = '';
			return '';
		}

		$markup = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lokales, plugineigenes Asset.
		self::$cache[ $name ] = false === $markup ? '' : trim( $markup );

		return self::$cache[ $name ];
	}
}
