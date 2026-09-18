<?php
/**
 * Liebherr Intelligence World – Frontend (Blue-Planet-Landing + Eintrittsschleuse + Sitzungsleiste).
 *
 * Öffentlicher Shortcode `[liw_intelligence_world]` (Pflichtenheft-2 §4.1/§4.2/§7):
 *   - Blue-Planet-Hero (dekorativer Planet-SVG, reduced-motion-fest, Listen-Alternative-tauglich);
 *   - Eintrittsschleuse (Access Gate): Code + Prototyp-/Preishinweis + Nutzungsbedingungen + zwei
 *     Pflicht-Einwilligungen + Bestätigungsschaltfläche (erst aktiv, wenn Code und beide Zustimmungen da);
 *   - nach Start: kompakte Sitzungs-/Kostenleiste (Ticker) mit „Sitzung beenden".
 *
 * Der eigentliche Start/Heartbeat/Ende läuft über die REST-Schnittstelle (serverseitige Abrechnungswahrheit,
 * §9). Ohne JavaScript bleibt die Landing lesbar; die kostenpflichtige Sitzung erfordert bewusst JS.
 * PROTOTYP (§21): Demo-Code, Beispieltarife, keine echte Abrechnung.
 *
 * @package Liebherr\InterfaceWorld\IntelligenceWorld
 * @since   0.1.0-alpha.48
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\IntelligenceWorld;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class WorldView {

	public const SHORTCODE = 'liw_intelligence_world';
	private const HANDLE   = 'liw-intelligence-world';

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'render' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'maybe_enqueue' ] );
		// Cache-Busting: manche Umgebungen strippen `?ver` von statischen Assets (bekannte Falle). Wir hängen
		// spät eine filemtime-`?v=` an die eigenen Dateien an (überlebt das Stripping), damit CSS/JS-Änderungen
		// im Browser ankommen. Muster wie Frontend\FrontendAssets::bust_src.
		add_filter( 'style_loader_src', [ self::class, 'bust' ], 9999, 2 );
		add_filter( 'script_loader_src', [ self::class, 'bust' ], 9999, 2 );
	}

	/** Hängt für die eigenen IW-Assets eine filemtime-`?v=` an, falls sie fehlt. */
	public static function bust( $src, $handle ) {
		if ( self::HANDLE !== $handle || ! is_string( $src ) || false !== strpos( $src, 'v=' ) ) {
			return $src;
		}
		if ( false !== strpos( $src, 'assets/css/liw-intelligence-world.css' ) ) {
			return add_query_arg( 'v', self::ver( 'assets/css/liw-intelligence-world.css' ), $src );
		}
		if ( false !== strpos( $src, 'assets/js/liw-intelligence-world.js' ) ) {
			return add_query_arg( 'v', self::ver( 'assets/js/liw-intelligence-world.js' ), $src );
		}
		return $src;
	}

	public static function maybe_enqueue(): void {
		$post = get_post();
		if ( ! $post instanceof \WP_Post || ! has_shortcode( (string) $post->post_content, self::SHORTCODE ) ) {
			return;
		}
		$css = 'assets/css/liw-intelligence-world.css';
		$js  = 'assets/js/liw-intelligence-world.js';
		wp_enqueue_style( self::HANDLE, LIW_URL . $css, [], self::ver( $css ) );
		wp_add_inline_style( self::HANDLE, \Liebherr\InterfaceWorld\Branding\BrandTokens::css_root() );
		wp_enqueue_script( self::HANDLE, LIW_URL . $js, [], self::ver( $js ), true );

		$cfg = WorldContent::get();
		wp_localize_script( self::HANDLE, 'liwIw', [
			'rest'   => esc_url_raw( rest_url( Rest::NAMESPACE . '/' ) ),
			'nonce'  => wp_create_nonce( Rest::NONCE_NAME ),
			'config' => [
				'currency'          => (string) $cfg['pricing']['currency'],
				'price_minute'      => (int) $cfg['pricing']['base_price_minute_minor'],
				'session_budget'    => (int) $cfg['pricing']['session_budget_minor'],
				'storage_budget_mb' => (int) $cfg['pricing']['storage_budget_mb'],
			],
			'i18n' => [
				'invalid'   => __( 'Der Bestätigungscode ist nicht gültig.', 'liebherr-interface-world' ),
				'consents'  => __( 'Bitte bestätigen Sie beide Pflichterklärungen.', 'liebherr-interface-world' ),
				'time'      => __( 'Sitzungszeit', 'liebherr-interface-world' ),
				'base'      => __( 'Basiskosten', 'liebherr-interface-world' ),
				'budget'    => __( 'Budget', 'liebherr-interface-world' ),
				'warn50'    => __( 'Hinweis: 50 % des Sitzungsbudgets erreicht.', 'liebherr-interface-world' ),
				'warn80'    => __( 'Achtung: 80 % des Sitzungsbudgets erreicht.', 'liebherr-interface-world' ),
				'warn100'   => __( 'Budget erschöpft: 100 % des Sitzungsbudgets erreicht.', 'liebherr-interface-world' ),
				'ended'     => __( 'Sitzung beendet.', 'liebherr-interface-world' ),
			],
		] );
	}

	private static function ver( string $relative ): string {
		$path = LIW_PATH . ltrim( $relative, '/' );
		$m    = is_readable( $path ) ? (int) filemtime( $path ) : 0;
		return $m > 0 ? (string) $m : LIW_VERSION;
	}

	public static function render(): string {
		$c   = WorldContent::get();
		$cur = (string) $c['pricing']['currency'];
		$price_display  = Money::format( (int) $c['pricing']['base_price_minute_minor'], $cur ) . ' / min';
		$budget_display = Money::format( (int) $c['pricing']['session_budget_minor'], $cur );

		ob_start();
		?>
		<div class="liw-iw" data-liw-iw>
			<section class="liw-iw__hero" id="liw-iw-hero">
				<div class="liw-iw__planet" aria-hidden="true"><?php echo self::planet_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- statisches dekoratives SVG. ?></div>
				<div class="liw-iw__hero-inner">
					<p class="liw-iw__eyebrow"><?php echo esc_html( (string) $c['landing']['eyebrow'] ); ?></p>
					<h1 class="liw-iw__headline"><?php echo esc_html( (string) $c['landing']['headline'] ); ?></h1>
					<p class="liw-iw__subline"><?php echo esc_html( (string) $c['landing']['subline'] ); ?></p>
					<a class="liw-cta liw-cta--primary liw-iw__enter" href="#liw-iw-gate"><?php echo esc_html( (string) $c['landing']['cta'] ); ?></a>
					<p class="liw-iw__provider"><?php echo esc_html__( 'Solution Provider: GoHeal', 'liebherr-interface-world' ); ?></p>
				</div>
			</section>

			<section class="liw-iw__gate" id="liw-iw-gate" data-liw-iw-gate>
				<div class="liw-iw__gate-card">
					<h2 class="liw-iw__gate-heading"><?php echo esc_html( (string) $c['gate']['heading'] ); ?></h2>
					<p class="liw-iw__note"><?php echo esc_html( (string) $c['gate']['prototype_note'] ); ?></p>
					<p class="liw-iw__required-note"><?php echo esc_html__( 'Mit * markierte Felder sind Pflichtfelder.', 'liebherr-interface-world' ); ?></p>

					<label class="liw-iw__field">
						<span class="liw-iw__field-label"><?php echo esc_html( (string) $c['gate']['code_label'] ); ?> <span class="liw-iw__req" aria-hidden="true">*</span></span>
						<input type="text" class="liw-iw__code" autocomplete="off" data-liw-iw-code />
					</label>

					<button type="button" class="liw-iw__terms-toggle" aria-expanded="false" aria-controls="liw-iw-terms"><?php echo esc_html( (string) $c['gate']['terms_button'] ); ?></button>
					<div class="liw-iw__terms" id="liw-iw-terms" hidden>
						<h3><?php echo esc_html( (string) $c['gate']['terms_heading'] ); ?></h3>
						<?php
						// Wiederverwendung des bestehenden Terms-Renderers + der vollständigen Bedingungen 5.1–5.8.
						echo \Liebherr\InterfaceWorld\Frontend\IntroOverlay::terms_html( \Liebherr\InterfaceWorld\Settings\LocalIntelligenceContent::default_terms() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pro Segment escaped.
						?>
					</div>

					<p class="liw-iw__price"><strong><?php echo esc_html__( 'Preis:', 'liebherr-interface-world' ); ?></strong> <?php echo esc_html( $price_display ); ?> · <strong><?php echo esc_html__( 'Sitzungsbudget:', 'liebherr-interface-world' ); ?></strong> <?php echo esc_html( $budget_display ); ?> · <strong><?php echo esc_html__( 'Lokaler Speicher:', 'liebherr-interface-world' ); ?></strong> <?php echo esc_html( (string) (int) $c['pricing']['storage_budget_mb'] ); ?> MB</p>
					<p class="liw-iw__price-info"><?php echo esc_html( (string) $c['gate']['price_info'] ); ?></p>

					<label class="liw-iw__consent"><input type="checkbox" data-liw-iw-consent="terms" required /> <span><?php echo esc_html( (string) $c['gate']['consent_terms'] ); ?> <span class="liw-iw__req" aria-hidden="true">*</span></span></label>
					<label class="liw-iw__consent"><input type="checkbox" data-liw-iw-consent="storage" required /> <span><?php echo esc_html( (string) $c['gate']['consent_storage'] ); ?> <span class="liw-iw__req" aria-hidden="true">*</span></span></label>

					<button type="button" class="liw-cta liw-cta--primary liw-iw__confirm" data-liw-iw-confirm disabled><?php echo esc_html( (string) $c['gate']['confirm_label'] ); ?></button>
					<p class="liw-iw__gate-msg" role="status" data-liw-iw-msg></p>
				</div>
			</section>

			<section class="liw-iw__world" data-liw-iw-world hidden aria-live="polite">
				<div class="liw-iw__statusbar" data-liw-iw-statusbar>
					<span class="liw-iw__stat"><span class="liw-iw__stat-k"><?php echo esc_html__( 'Sitzungszeit', 'liebherr-interface-world' ); ?></span> <strong data-liw-iw-time>00:00:00</strong></span>
					<span class="liw-iw__stat"><span class="liw-iw__stat-k"><?php echo esc_html__( 'Basiskosten', 'liebherr-interface-world' ); ?></span> <strong data-liw-iw-base>—</strong></span>
					<span class="liw-iw__stat liw-iw__stat--budget"><span class="liw-iw__stat-k"><?php echo esc_html__( 'Budget', 'liebherr-interface-world' ); ?></span>
						<span class="liw-iw__budget"><span class="liw-iw__budget-fill" data-liw-iw-budgetfill></span></span>
						<strong data-liw-iw-budgetpct>0 %</strong>
					</span>
					<button type="button" class="liw-cta liw-cta--secondary liw-iw__end" data-liw-iw-end><?php echo esc_html__( 'Sitzung beenden', 'liebherr-interface-world' ); ?></button>
				</div>
				<div class="liw-iw__world-body">
					<h2 class="liw-iw__hub-title"><?php echo esc_html__( 'Funktionsbereiche', 'liebherr-interface-world' ); ?></h2>
					<p class="liw-iw__demo-note"><?php echo esc_html__( 'Sie sind in der Intelligence World (Prototyp, Beispieldaten). Wählen Sie einen Bereich. Produktsegmente, Lösungswelt, Hotels und der Simulation Builder folgen in weiteren Etappen.', 'liebherr-interface-world' ); ?></p>
					<ul class="liw-iw__hub" role="list">
						<?php foreach ( self::hub_tiles() as $tile ) : ?>
							<?php if ( $tile['enabled'] && '' !== $tile['url'] ) : ?>
								<li class="liw-iw__tile liw-iw__tile--live">
									<a class="liw-iw__tile-link" href="<?php echo esc_url( $tile['url'] ); ?>">
										<span class="liw-iw__tile-title"><?php echo esc_html( $tile['label'] ); ?></span>
										<span class="liw-iw__tile-desc"><?php echo esc_html( $tile['desc'] ); ?></span>
										<span class="liw-iw__tile-cta"><?php echo esc_html__( 'Öffnen →', 'liebherr-interface-world' ); ?></span>
									</a>
								</li>
							<?php else : ?>
								<li class="liw-iw__tile liw-iw__tile--soon" aria-disabled="true">
									<span class="liw-iw__tile-title"><?php echo esc_html( $tile['label'] ); ?></span>
									<span class="liw-iw__tile-desc"><?php echo esc_html( $tile['desc'] ); ?></span>
									<span class="liw-iw__tile-badge"><?php echo esc_html__( 'in Vorbereitung', 'liebherr-interface-world' ); ?></span>
								</li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ul>
					<div class="liw-iw__protocol" data-liw-iw-protocol hidden></div>
				</div>
			</section>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Funktions-Hub nach dem Eintritt: verlinkt die bereits gebauten Bereiche (über die Seiten-Registry,
	 * nicht hart codiert) und zeigt die kommenden als „in Vorbereitung". Erweiterbar via Filter
	 * `liw_iw_hub_tiles` (spätere Etappe: administrierbare 14-Punkte-Taxonomie/Hotels, §3/§19).
	 *
	 * @return array<int,array{label:string,desc:string,url:string,enabled:bool}>
	 */
	private static function hub_tiles(): array {
		$li     = \Liebherr\InterfaceWorld\Content\SitePages::li_url();
		$if     = \Liebherr\InterfaceWorld\Content\SitePages::interface_url();
		$adv_id = (int) get_option( 'liw_adventures_page_id', 0 );
		$adv    = $adv_id > 0 && 'publish' === get_post_status( $adv_id ) ? (string) get_permalink( $adv_id ) : '';

		$tiles = [
			[
				'label'   => __( 'Local Intelligence', 'liebherr-interface-world' ),
				'desc'    => __( 'Geschützte lokale Entscheidungsräume: Simulieren, Verstehen, Entscheiden.', 'liebherr-interface-world' ),
				'url'     => $li,
				'enabled' => '' !== $li,
			],
			[
				'label'   => __( 'Interface Solutions', 'liebherr-interface-world' ),
				'desc'    => __( 'Technisches Herzstück: Schnittstellen, Konverter, Datenflüsse.', 'liebherr-interface-world' ),
				'url'     => $if,
				'enabled' => '' !== $if,
			],
			[
				'label'   => __( 'Adventures', 'liebherr-interface-world' ),
				'desc'    => __( 'Vierte Insel: ortsbezogene Erlebnisse, Wissen und Hilfe (Drei-Wörter-Ort).', 'liebherr-interface-world' ),
				'url'     => $adv,
				'enabled' => '' !== $adv,
			],
			[
				'label'   => __( 'Produktsegmente & Lösungswelt', 'liebherr-interface-world' ),
				'desc'    => __( '13 Produktsegmente + übergreifende Lösungswelt als Globus-Navigation.', 'liebherr-interface-world' ),
				'url'     => '',
				'enabled' => false,
			],
			[
				'label'   => __( 'Hotelwelt', 'liebherr-interface-world' ),
				'desc'    => __( 'Sechs Liebherr-Hotels als eigene Erlebnis- und Simulationsknoten.', 'liebherr-interface-world' ),
				'url'     => '',
				'enabled' => false,
			],
			[
				'label'   => __( 'Simulation Builder', 'liebherr-interface-world' ),
				'desc'    => __( 'Geführte Szenarien, Forecasts und Auswertungen (Beispieldaten).', 'liebherr-interface-world' ),
				'url'     => '',
				'enabled' => false,
			],
		];

		/** @var array<int,array{label:string,desc:string,url:string,enabled:bool}> $tiles */
		$tiles = apply_filters( 'liw_iw_hub_tiles', $tiles );
		return is_array( $tiles ) ? $tiles : [];
	}

	/** Dekoratives Blue-Planet-SVG (Erde + Orbit + Lichtpunkte), rein statisch. */
	private static function planet_svg(): string {
		return '<svg class="liw-iw__planet-svg" viewBox="0 0 600 600" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg" focusable="false" aria-hidden="true">'
			. '<defs><radialGradient id="liwIwGlobe" cx="40%" cy="38%" r="70%">'
			. '<stop offset="0%" stop-color="#0C5EA8"/><stop offset="60%" stop-color="#061C33"/><stop offset="100%" stop-color="#05070B"/>'
			. '</radialGradient></defs>'
			. '<circle class="liw-iw__orbit" cx="300" cy="300" r="210" />'
			. '<circle class="liw-iw__orbit liw-iw__orbit--2" cx="300" cy="300" r="260" />'
			. '<circle cx="300" cy="300" r="160" fill="url(#liwIwGlobe)" />'
			. '<circle class="liw-iw__globe-ring" cx="300" cy="300" r="160" />'
			. '<g class="liw-iw__nodes">'
			. '<circle class="liw-iw__node" cx="300" cy="90"  r="5" /><circle class="liw-iw__node" cx="510" cy="300" r="5" />'
			. '<circle class="liw-iw__node" cx="300" cy="510" r="5" /><circle class="liw-iw__node" cx="90"  cy="300" r="5" />'
			. '<circle class="liw-iw__node" cx="455" cy="145" r="4" /><circle class="liw-iw__node" cx="455" cy="455" r="4" />'
			. '<circle class="liw-iw__node" cx="145" cy="455" r="4" /><circle class="liw-iw__node" cx="145" cy="145" r="4" />'
			. '</g></svg>';
	}
}
