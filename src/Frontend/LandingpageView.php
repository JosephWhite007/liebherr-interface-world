<?php
/**
 * Liebherr Interface Solutions – Landingpage View (zusammengesetzte Seite)
 *
 * Öffentlicher Shortcode `[liw_landingpage]` (Liebherr-Pflichtenheft §8): rendert alle
 * **veröffentlichten** `liw_section`-Abschnitte in ihrer Reihenfolge (`menu_order`, dann Titel)
 * untereinander als `<section>`-Blöcke. Damit steuert der Freigabeworkflow des Content Boards
 * (Entwurf → Prüfung → freigegeben → veröffentlicht) direkt, was auf der Seite erscheint –
 * nur Status `publish` ist sichtbar, alles andere bleibt unsichtbar.
 *
 * Kein eigenes Template, kein eigener Page-Builder (CLAUDE.md Abschnitt 5): Die Redaktion legt
 * eine normale WordPress-Seite an und setzt diesen Shortcode hinein. Inhalte laufen durch den
 * regulären `the_content`-Filter, dadurch werden Blöcke, eingebettete Shortcodes
 * (`[liw_graphic]`, `[liw_world_connections_map]`, `[liw_onboarding_form]`) und die
 * Core-Übersetzung (TranslationBridge/PostFieldAdapter) genau wie bei Einzelansichten aufgelöst.
 *
 * Sicherheit: nur `post_status = publish`; Ausgabe über `get_the_title()`/`the_content`-Filter
 * (WP-eigenes Escaping/KSES-Verhalten wie bei jeder Beitragsausgabe); Anker-IDs aus dem
 * Bauplan-Code bzw. Post-Slug, jeweils `sanitize_html_class()`. Rekursionsschutz, falls ein
 * Abschnitt selbst `[liw_landingpage]` enthält.
 *
 * Kein Inline-CSS/JS – Klassen `.liw-landingpage*` in assets/css/liebherr-frontend.css.
 *
 * @package Liebherr\InterfaceWorld\Frontend
 * @since   0.1.0-alpha.18
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Frontend;

use Liebherr\InterfaceWorld\Content\SectionBlueprint;
use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;
use Liebherr\InterfaceWorld\CPT\LiwSectionCpt;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class LandingpageView {

	public const SHORTCODE = 'liw_landingpage';

	/** Rekursionsschutz (Abschnitt enthält selbst den Landingpage-Shortcode). */
	private static bool $rendering = false;

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'render_shortcode' ] );
	}

	public static function render_shortcode(): string {
		if ( self::$rendering ) {
			return '';
		}
		self::$rendering = true;

		try {
			return self::render();
		} finally {
			self::$rendering = false;
		}
	}

	/** @return \WP_Post[] Veröffentlichte Abschnitte in Seitenreihenfolge. */
	public static function get_published_sections(): array {
		$posts = get_posts( [
			'post_type'      => LiwSectionCpt::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1, // Kuratierte Menge (14 laut Pflichtenheft §8).
			'orderby'        => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
		] );

		return array_values( array_filter( $posts, static fn( $p ): bool => $p instanceof \WP_Post ) );
	}

	private static function render(): string {
		$sections = self::get_published_sections();

		if ( [] === $sections ) {
			// Öffentlich: nichts ausgeben. Redaktion: Hinweis, damit die leere Seite erklärbar ist.
			if ( is_user_logged_in() && current_user_can( RoleBridge::CAP_MANAGE_CONTENT ) ) {
				return '<p class="liw-landingpage liw-landingpage--empty">' .
					esc_html__( 'Noch kein Abschnitt veröffentlicht. Abschnitte im Content Board auf „Veröffentlicht" setzen, damit sie hier erscheinen. (Dieser Hinweis ist nur für angemeldete Redakteure sichtbar.)', 'liebherr-interface-world' ) .
					'</p>';
			}
			return '';
		}

		global $post;
		$outer_post = $post;

		ob_start();
		echo '<div class="liw-landingpage">';
		foreach ( $sections as $section ) {
			$post = $section; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- bewusst für the_content-Filter/Shortcodes im Abschnittskontext, wird unten zurückgesetzt.
			setup_postdata( $post );

			$code   = (string) get_post_meta( $section->ID, SectionBlueprint::META_CODE, true );
			$anchor = sanitize_html_class( strtolower( '' !== $code ? $code : (string) $section->post_name ) );
			$title  = get_the_title( $section );
			?>
			<section id="<?php echo esc_attr( $anchor ); ?>" class="liw-landingpage__section<?php echo '' !== $anchor ? ' liw-landingpage__section--' . esc_attr( $anchor ) : ''; ?>">
				<?php if ( '' !== $title ) : ?>
					<h2 class="liw-landingpage__title"><?php echo esc_html( $title ); ?></h2>
				<?php endif; ?>
				<div class="liw-landingpage__content">
					<?php echo apply_filters( 'the_content', get_the_content( null, false, $section ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- reguläre WP-Inhaltsausgabe (Blöcke/Shortcodes/KSES wie in Einzelansichten). ?>
				</div>
			</section>
			<?php
		}
		echo '</div>';

		$post = $outer_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		if ( $outer_post instanceof \WP_Post ) {
			setup_postdata( $outer_post );
		} else {
			wp_reset_postdata();
		}

		return (string) ob_get_clean();
	}
}
