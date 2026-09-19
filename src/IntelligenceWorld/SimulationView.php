<?php
/**
 * Liebherr Intelligence World – Simulation Builder: Frontend (Pflichtenheft-2 §6.4).
 *
 * Geführtes Szenario-Formular (Segment · Szenario A/B/C · Zeithorizont) mit deterministischer
 * Beispiel-Prognose ({@see SimulationModel}). Die Default-Prognose wird serverseitig gerendert
 * (funktioniert ohne JavaScript); mit aktivem Skript rechnet `assets/js/liw-iw-simulation.js` bei
 * jeder Eingabe live nach (progressive Enhancement, keine Server-Roundtrips).
 *
 * Zwei Nutzungswege: inline im Funktions-Hub der Intelligence World ({@see self::render_section()})
 * und eigenständig über den Shortcode `[liw_iw_simulation]`.
 *
 * PROTOTYP (§21): Beispieldaten – keine echte Prognose.
 *
 * @package Liebherr\InterfaceWorld\IntelligenceWorld
 * @since   0.1.0-alpha.56
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\IntelligenceWorld;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SimulationView {

	public const SHORTCODE = 'liw_iw_simulation';
	private const HANDLE    = 'liw-iw-simulation';

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'render_shortcode' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'maybe_enqueue' ] );
		add_filter( 'script_loader_src', [ self::class, 'bust' ], 9999, 2 );
		add_filter( 'style_loader_src', [ self::class, 'bust' ], 9999, 2 );
	}

	/** filemtime-`?v=` für die eigene JS-Datei (Umgebung strippt `?ver` – bekannte Falle). */
	public static function bust( $src, $handle ) {
		if ( self::HANDLE !== $handle || ! is_string( $src ) || false !== strpos( $src, 'v=' ) ) {
			return $src;
		}
		if ( false !== strpos( $src, 'assets/js/liw-iw-simulation.js' ) ) {
			return add_query_arg( 'v', self::ver( 'assets/js/liw-iw-simulation.js' ), $src );
		}
		return $src;
	}

	/** Skript laden, wenn die Intelligence World oder der eigenständige Shortcode auf der Seite steht. */
	public static function maybe_enqueue(): void {
		$post = get_post();
		if ( ! $post instanceof \WP_Post ) { return; }
		$content = (string) $post->post_content;
		if ( ! has_shortcode( $content, self::SHORTCODE ) && ! has_shortcode( $content, WorldView::SHORTCODE ) ) {
			return;
		}
		// CSS kommt aus liw-intelligence-world.css (bei [liw_intelligence_world] bereits geladen). Für den
		// eigenständigen Shortcode stellen wir es hier sicher.
		if ( has_shortcode( $content, self::SHORTCODE ) && ! has_shortcode( $content, WorldView::SHORTCODE ) ) {
			$css = 'assets/css/liw-intelligence-world.css';
			wp_enqueue_style( 'liw-intelligence-world', LIW_URL . $css, [], self::ver( $css ) );
			wp_add_inline_style( 'liw-intelligence-world', \Liebherr\InterfaceWorld\Branding\BrandTokens::css_root() );
		}
		$js = 'assets/js/liw-iw-simulation.js';
		wp_enqueue_script( self::HANDLE, LIW_URL . $js, [], self::ver( $js ), true );
		wp_localize_script( self::HANDLE, 'liwIwSim', [
			'rest'      => esc_url_raw( rest_url( Rest::NAMESPACE . '/' ) ),
			'useServer' => ! SimulationEngine::is_mock(), // echte Engine registriert → serverseitig rechnen.
			'engine'    => SimulationEngine::resolve()->id(),
		] );
	}

	private static function ver( string $relative ): string {
		$path = LIW_PATH . ltrim( $relative, '/' );
		$m    = is_readable( $path ) ? (int) filemtime( $path ) : 0;
		return $m > 0 ? (string) $m : LIW_VERSION;
	}

	/** Shortcode-Einstieg (eigenständige Seite). */
	public static function render_shortcode(): string {
		return '<div class="liw-iw liw-iw--sim-standalone" data-liw-iw-sim-standalone>' . self::render_section() . '</div>';
	}

	/**
	 * Kernmarkup: geführtes Formular + serverseitige Default-Prognose (#liw-iw-simulation).
	 */
	public static function render_section(): string {
		$segments = CatalogContent::segments();
		if ( [] === $segments ) {
			$segments = [ [ 'key' => 'default', 'label' => __( 'Segment', 'liebherr-interface-world' ) ] ];
		}
		$first    = $segments[0];
		$baseline = SimulationModel::sample_baseline( (string) ( $first['key'] ?? 'default' ) );
		$default  = SimulationEngine::resolve()->forecast( $baseline['base'], $baseline['growth_permille'], 12, 'base' );

		ob_start();
		?>
		<section class="liw-iw__nav liw-iw__sim" id="liw-iw-simulation" aria-labelledby="liw-iw-sim-h" data-liw-sim>
			<h3 class="liw-iw__nav-title" id="liw-iw-sim-h"><?php echo esc_html__( 'Simulation Builder', 'liebherr-interface-world' ); ?></h3>
			<p class="liw-iw__nav-lead"><?php echo esc_html__( 'Geführte Szenarien und Forecasts (Beispieldaten). Wählen Sie Segment, Szenario und Zeithorizont – die Prognose aktualisiert sich sofort.', 'liebherr-interface-world' ); ?></p>

			<form class="liw-iw__sim-form" data-liw-sim-form onsubmit="return false;">
				<label class="liw-iw__sim-field">
					<span class="liw-iw__sim-label"><?php echo esc_html__( 'Segment', 'liebherr-interface-world' ); ?></span>
					<select class="liw-iw__sim-input" data-liw-sim-segment>
						<?php foreach ( $segments as $seg ) :
							$bl = SimulationModel::sample_baseline( (string) ( $seg['key'] ?? 'default' ) ); ?>
							<option value="<?php echo esc_attr( (string) ( $seg['key'] ?? '' ) ); ?>" data-base="<?php echo esc_attr( (string) $bl['base'] ); ?>" data-growth="<?php echo esc_attr( (string) $bl['growth_permille'] ); ?>"><?php echo esc_html( (string) ( $seg['label'] ?? $seg['key'] ?? '' ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>

				<fieldset class="liw-iw__sim-field liw-iw__sim-scenarios">
					<legend class="liw-iw__sim-label"><?php echo esc_html__( 'Szenario', 'liebherr-interface-world' ); ?></legend>
					<?php foreach ( SimulationModel::scenarios() as $key => $s ) : ?>
						<label class="liw-iw__sim-radio">
							<input type="radio" name="liw-sim-scenario" value="<?php echo esc_attr( $key ); ?>" data-liw-sim-scenario <?php checked( 'base', $key ); ?> />
							<span><?php echo esc_html( SimulationModel::scenario_label( $key ) ); ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>

				<label class="liw-iw__sim-field">
					<span class="liw-iw__sim-label"><?php echo esc_html__( 'Zeithorizont (Perioden)', 'liebherr-interface-world' ); ?></span>
					<select class="liw-iw__sim-input" data-liw-sim-horizon>
						<?php foreach ( SimulationModel::HORIZONS as $h ) : ?>
							<option value="<?php echo esc_attr( (string) $h ); ?>" <?php selected( 12, $h ); ?>><?php echo esc_html( (string) $h ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</form>

			<div class="liw-iw__sim-out" data-liw-sim-out aria-live="polite">
				<?php echo self::render_forecast( $default, (string) ( $first['label'] ?? $first['key'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- intern escaped. ?>
			</div>
			<div class="liw-iw__sim-saverow">
				<button type="button" class="liw-cta liw-cta--secondary" data-liw-sim-save><?php echo esc_html__( 'Szenario speichern', 'liebherr-interface-world' ); ?></button>
			</div>
			<div class="liw-iw__sim-saved" data-liw-sim-saved hidden></div>
			<p class="liw-iw__node-note"><?php echo esc_html__( 'Prototyp – Beispieldaten/-modell, keine echte Prognose. Werte deterministisch aus dem Segment abgeleitet. Gespeicherte Szenarien liegen lokal im Browser.', 'liebherr-interface-world' ); ?></p>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Ergebnisblock: Auswertungszeile + Balkendiagramm (Inline-SVG) + Wertetabelle. Alles escaped.
	 *
	 * @param array<string,mixed> $fc  Ergebnis von {@see SimulationModel::forecast()}.
	 */
	public static function render_forecast( array $fc, string $segment_label ): string {
		/** @var array<int,int> $values */
		$values = is_array( $fc['values'] ?? null ) ? $fc['values'] : [];
		$scen   = SimulationModel::scenario_label( (string) ( $fc['scenario'] ?? 'base' ) );
		$delta  = (int) ( $fc['delta_permille'] ?? 0 );
		$sign   = $delta >= 0 ? '+' : '';
		$eff    = (int) ( $fc['effective_permille'] ?? 0 );

		ob_start();
		?>
		<p class="liw-iw__sim-summary">
			<span class="liw-iw__sim-badge"><?php echo esc_html( $segment_label ); ?></span>
			<span class="liw-iw__sim-badge liw-iw__sim-badge--scen"><?php echo esc_html( $scen ); ?></span>
			<?php printf(
				/* translators: 1: start value, 2: end value, 3: signed delta in percent, 4: effective growth in percent */
				esc_html__( 'Start %1$s → Ende %2$s (%3$s %% über den Zeitraum, effektives Wachstum %4$s %% je Periode).', 'liebherr-interface-world' ),
				esc_html( self::num( (int) ( $fc['base'] ?? 0 ) ) ),
				esc_html( self::num( (int) ( $fc['end'] ?? 0 ) ) ),
				esc_html( $sign . self::permille_to_pct( $delta ) ),
				esc_html( self::permille_to_pct( $eff ) )
			); ?>
		</p>
		<?php echo self::bars_svg( $values ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selbst erzeugtes SVG mit gecasteten Zahlen. ?>
		<details class="liw-iw__sim-tablewrap">
			<summary class="liw-iw__sim-tabletoggle"><?php echo esc_html__( 'Werte anzeigen', 'liebherr-interface-world' ); ?></summary>
			<table class="liw-iw__sim-table">
				<thead><tr><th scope="col"><?php echo esc_html__( 'Periode', 'liebherr-interface-world' ); ?></th><th scope="col"><?php echo esc_html__( 'Wert', 'liebherr-interface-world' ); ?></th></tr></thead>
				<tbody>
					<?php foreach ( $values as $i => $v ) : ?>
						<tr><td><?php echo esc_html( (string) ( $i + 1 ) ); ?></td><td><?php echo esc_html( self::num( (int) $v ) ); ?></td></tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</details>
		<?php
		return (string) ob_get_clean();
	}

	/** Balkendiagramm als Inline-SVG (rein dekorativ; die Tabelle ist die zugängliche Alternative). */
	private static function bars_svg( array $values ): string {
		$n = count( $values );
		if ( 0 === $n ) { return ''; }
		$max = max( 1, (int) max( $values ) );
		$w   = 100.0;
		$h   = 40.0;
		$gap = $n > 1 ? 1.5 : 0.0;
		$bw  = ( $w - ( $gap * ( $n - 1 ) ) ) / $n;

		$rects = '';
		foreach ( array_values( $values ) as $idx => $v ) {
			$bh = ( (int) $v / $max ) * ( $h - 2 );
			$x  = $idx * ( $bw + $gap );
			$y  = $h - $bh;
			$rects .= sprintf(
				'<rect class="liw-iw__sim-bar" x="%s" y="%s" width="%s" height="%s" rx="0.4" />',
				self::f( $x ), self::f( $y ), self::f( $bw ), self::f( max( 0.0, $bh ) )
			);
		}
		return '<svg class="liw-iw__sim-chart" viewBox="0 0 ' . self::f( $w ) . ' ' . self::f( $h )
			. '" preserveAspectRatio="none" role="img" aria-label="' . esc_attr__( 'Prognose-Balkendiagramm', 'liebherr-interface-world' ) . '" focusable="false">'
			. $rects . '</svg>';
	}

	private static function f( float $v ): string {
		return rtrim( rtrim( number_format( $v, 2, '.', '' ), '0' ), '.' );
	}

	/** Tausenderformat ohne Nachkommastellen (Anzeige). */
	private static function num( int $v ): string {
		return number_format( $v, 0, ',', '.' );
	}

	/** Promille → Prozentanzeige mit einer Nachkommastelle (z. B. 234 ‰ → „23,4"). */
	private static function permille_to_pct( int $permille ): string {
		return number_format( $permille / 10, 1, ',', '.' );
	}
}
