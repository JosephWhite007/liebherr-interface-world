<?php
/**
 * Liebherr Intelligence World – Ansicht „Navigation & Hotels" (Pflichtenheft-2 §3/§19).
 *
 * Rendert die 13 Produktsegmente + Lösungswelt als Globus-Knotengitter und die sechs Hotels als
 * eigene Knotenliste. Jeder Knoten öffnet per `<details>` seine Detailkarte – bewusst ohne JavaScript
 * (barrierefrei, tastaturbedienbar, cache-sicher; funktioniert auch ohne aktives Skript).
 *
 * Zwei Nutzungswege:
 *   1) inline im Funktions-Hub der Intelligence World (WorldView ruft {@see self::render_sections()});
 *   2) eigenständig über den Shortcode `[liw_iw_navigation]` (z. B. für eine dedizierte Seite).
 *
 * PROTOTYP (§21): Beispieldaten aus {@see CatalogContent}, vor Produktivbetrieb kuratieren.
 *
 * @package Liebherr\InterfaceWorld\IntelligenceWorld
 * @since   0.1.0-alpha.54
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\IntelligenceWorld;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class NavigationView {

	public const SHORTCODE = 'liw_iw_navigation';
	private const HANDLE   = 'liw-intelligence-world';

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'render_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'maybe_enqueue' ] );
	}

	/** Stellt die IW-Stile bereit, wenn der eigenständige Shortcode auf der Seite steht. */
	public static function maybe_enqueue(): void {
		$post = get_post();
		if ( ! $post instanceof \WP_Post || ! has_shortcode( (string) $post->post_content, self::SHORTCODE ) ) {
			return;
		}
		$css = 'assets/css/liw-intelligence-world.css';
		$m   = is_readable( LIW_PATH . $css ) ? (int) filemtime( LIW_PATH . $css ) : 0;
		wp_enqueue_style( self::HANDLE, LIW_URL . $css, [], $m > 0 ? (string) $m : LIW_VERSION );
		wp_add_inline_style( self::HANDLE, \Liebherr\InterfaceWorld\Branding\BrandTokens::css_root() );
	}

	/** Shortcode-Einstieg (eigenständige Seite): mit umschließendem Rahmen. */
	public static function render_shortcode(): string {
		return '<div class="liw-iw liw-iw--nav-standalone" data-liw-iw-nav>' . self::render_sections() . '</div>';
	}

	/**
	 * Kernmarkup: Segmente-Abschnitt (#liw-iw-segments) + Hotels-Abschnitt (#liw-iw-hotels).
	 * Wird sowohl inline (WorldView) als auch eigenständig (Shortcode) genutzt.
	 */
	public static function render_sections(): string {
		$segments = CatalogContent::segments();
		$solution = CatalogContent::solution_world();
		$hotels   = CatalogContent::hotels();

		ob_start();
		?>
		<section class="liw-iw__nav liw-iw__nav--segments" id="liw-iw-segments" aria-labelledby="liw-iw-segments-h">
			<h3 class="liw-iw__nav-title" id="liw-iw-segments-h"><?php echo esc_html__( 'Produktsegmente & Lösungswelt', 'liebherr-interface-world' ); ?></h3>
			<p class="liw-iw__nav-lead"><?php echo esc_html__( 'Wählen Sie ein Segment als Einstieg in Simulationen und Datenflüsse. Ein Knoten öffnet seine Kurzbeschreibung und den Drei-Wörter-Ort.', 'liebherr-interface-world' ); ?></p>
			<ul class="liw-iw__grid" role="list">
				<?php
				if ( ! empty( $solution['label'] ) ) {
					echo self::node_li( (string) $solution['label'], (string) ( $solution['tagline'] ?? '' ), (string) ( $solution['blurb'] ?? '' ), (string) ( $solution['three_words'] ?? '' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in node_li escaped.
				}
				foreach ( $segments as $seg ) {
					echo self::node_li( (string) ( $seg['label'] ?? '' ), (string) ( $seg['tagline'] ?? '' ), (string) ( $seg['blurb'] ?? '' ), (string) ( $seg['three_words'] ?? '' ), false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in node_li escaped.
				}
				?>
			</ul>
		</section>

		<section class="liw-iw__nav liw-iw__nav--hotels" id="liw-iw-hotels" aria-labelledby="liw-iw-hotels-h">
			<h3 class="liw-iw__nav-title" id="liw-iw-hotels-h"><?php echo esc_html__( 'Hotelwelt', 'liebherr-interface-world' ); ?></h3>
			<p class="liw-iw__nav-lead"><?php echo esc_html__( 'Sechs Liebherr-Hotels als eigene Erlebnis- und Simulationsknoten. Beispieldaten – im Backoffice kuratierbar.', 'liebherr-interface-world' ); ?></p>
			<ul class="liw-iw__grid liw-iw__grid--hotels" role="list">
				<?php
				foreach ( $hotels as $h ) {
					echo self::node_li( (string) ( $h['name'] ?? '' ), (string) ( $h['place'] ?? '' ), (string) ( $h['blurb'] ?? '' ), (string) ( $h['three_words'] ?? '' ), false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in node_li escaped.
				}
				?>
			</ul>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Ein Knoten als `<li><details>`: Kopf = Titel + Untertitel, aufgeklappt = Beschreibung + Ort.
	 * Alle dynamischen Werte werden hier escaped.
	 */
	private static function node_li( string $title, string $subtitle, string $blurb, string $three_words, bool $is_solution ): string {
		if ( '' === $title ) { return ''; }
		$cls = 'liw-iw__node' . ( $is_solution ? ' liw-iw__node--solution' : '' );

		ob_start();
		?>
		<li class="<?php echo esc_attr( $cls ); ?>">
			<details class="liw-iw__node-det">
				<summary class="liw-iw__node-sum">
					<span class="liw-iw__node-sum-row">
						<span class="liw-iw__node-dot" aria-hidden="true"></span>
						<span class="liw-iw__node-head">
							<span class="liw-iw__node-title"><?php echo esc_html( $title ); ?></span>
							<?php if ( '' !== $subtitle ) : ?>
								<span class="liw-iw__node-sub"><?php echo esc_html( $subtitle ); ?></span>
							<?php endif; ?>
						</span>
						<span class="liw-iw__node-caret" aria-hidden="true">›</span>
					</span>
				</summary>
				<div class="liw-iw__node-body">
					<?php if ( '' !== $blurb ) : ?>
						<p class="liw-iw__node-blurb"><?php echo esc_html( $blurb ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== $three_words ) : ?>
						<p class="liw-iw__node-loc">
							<span class="liw-iw__node-loc-k"><?php echo esc_html__( 'Ort (three words):', 'liebherr-interface-world' ); ?></span>
							<code class="liw-iw__three-words">///<?php echo esc_html( $three_words ); ?></code>
						</p>
					<?php endif; ?>
					<p class="liw-iw__node-note"><?php echo esc_html__( 'Prototyp – Beispieldaten. Simulationen folgen in einer weiteren Etappe.', 'liebherr-interface-world' ); ?></p>
				</div>
			</details>
		</li>
		<?php
		return (string) ob_get_clean();
	}
}
