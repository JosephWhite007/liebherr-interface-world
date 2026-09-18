<?php
/**
 * Liebherr Local Intelligence – Frontend-Module (Pflichtenheft LI §8, elf Module).
 *
 * Ein Modul = ein Shortcode, gespeist aus Settings\LocalIntelligenceContent (administrierbar,
 * mehrsprachig über den bestehenden Sprach-Workflow). Zusätzlich der Composite-Shortcode
 * `[liw_local_intelligence]`, der alle Module in Pflichtenheft-Reihenfolge mit Sprungleiste rendert
 * (Muster wie LandingpageView, keine zweite Komponentenbibliothek). Modul 11 verwendet das
 * bestehende `[liw_contact_form]` wieder (LI §8 Modul 11 / §12.1 – kein neues Formularsystem).
 *
 * Gestaltung ausschließlich über `--brand-*` und `.liw-li*`-Klassen (assets/css). Interaktion nur als
 * fortschreitende Verbesserung (Szenario-Schalter/Filter in assets/js), Inhalte ohne JS verständlich.
 * Keine unbelegten Leistungs-/Echtzeitversprechen, Demo-Daten klar gekennzeichnet (LI §4/§7/§12.8).
 *
 * @package Liebherr\InterfaceWorld\Frontend
 * @since   0.1.0-alpha.41
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Frontend;

use Liebherr\InterfaceWorld\Content\SitePages;
use Liebherr\InterfaceWorld\Settings\LocalIntelligenceContent as Content;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class LocalIntelligenceView {

	public const SHORTCODE = 'liw_local_intelligence';

	/** Modul-Shortcode → Render-Methode. */
	private const MODULES = [
		'liw_li_hero'         => 'render_hero',
		'liw_li_vision'       => 'render_vision',
		'liw_li_flow'         => 'render_flow',
		'liw_simulation_world'=> 'render_simulation',
		'liw_li_knowledge'    => 'render_knowledge',
		'liw_li_trust'        => 'render_trust',
		'liw_li_global'       => 'render_global',
		'liw_li_usecases'     => 'render_usecases',
		'liw_interface_bridge'=> 'render_bridge',
		'liw_li_rollout'      => 'render_rollout',
		'liw_li_contact'      => 'render_contact',
	];

	private static bool $rendering = false;

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'render_composite' ] );
		foreach ( self::MODULES as $code => $method ) {
			add_shortcode( $code, [ self::class, $method ] );
		}
		add_shortcode( 'liw_context_nav', [ self::class, 'render_context_nav' ] );
	}

	/** Alle öffentlichen Shortcodes (für FrontendAssets-Enqueue). @return string[] */
	public static function shortcodes(): array {
		return array_merge( [ self::SHORTCODE, 'liw_context_nav' ], array_keys( self::MODULES ) );
	}

	// ---------------------------------------------------------------------
	// Composite
	// ---------------------------------------------------------------------

	public static function render_composite(): string {
		if ( self::$rendering ) {
			return '';
		}
		self::$rendering = true;
		try {
			// Hero zuerst (Modul 1), danach die sticky Sprungleiste, dann die weiteren Module.
			$rest = self::render_vision()
				. self::render_flow()
				. self::render_simulation()
				. self::render_knowledge()
				. self::render_trust()
				. self::render_global()
				. self::render_usecases()
				. self::render_bridge()
				. self::render_rollout()
				. self::render_contact();
			return '<div class="liw-li">' . self::render_hero() . self::render_nav() . $rest . '</div>';
		} finally {
			self::$rendering = false;
		}
	}

	/** Sprungleiste über die zehn Abschnitte nach dem Hero. */
	private static function render_nav(): string {
		$c     = Content::get();
		$links = [
			'li-vision'     => $c['vision']['title'],
			'li-flow'       => $c['flow']['title'],
			'li-simulation' => $c['simulation']['title'],
			'li-knowledge'  => __( 'Wissensassistenz', 'liebherr-interface-world' ),
			'li-trust'      => __( 'Datenqualität', 'liebherr-interface-world' ),
			'li-global'     => __( 'Weltweit', 'liebherr-interface-world' ),
			'li-usecases'   => $c['usecases']['title'],
			'li-bridge'     => __( 'Interface Solutions', 'liebherr-interface-world' ),
			'li-rollout'    => __( 'Einstieg', 'liebherr-interface-world' ),
			'li-contact'    => __( 'Kontakt', 'liebherr-interface-world' ),
		];
		$items = '';
		foreach ( $links as $anchor => $label ) {
			$items .= '<li class="liw-li__nav-item"><a class="liw-li__nav-link" href="#' . esc_attr( $anchor ) . '">' . esc_html( (string) $label ) . '</a></li>';
		}
		return '<nav class="liw-li__nav" aria-label="' . esc_attr__( 'Abschnitte der Seite', 'liebherr-interface-world' ) . '"><ul class="liw-li__nav-list">' . $items . '</ul></nav>';
	}

	// ---------------------------------------------------------------------
	// Module 1–11
	// ---------------------------------------------------------------------

	public static function render_hero(): string {
		$h        = Content::get()['hero'];
		$discover = '#li-vision';
		$platform = self::interface_href();
		ob_start();
		?>
		<section class="liw-li__hero" id="li-hero">
			<div class="liw-li__hero-bg" aria-hidden="true"></div>
			<div class="liw-li__inner liw-li__hero-inner">
				<p class="liw-li__eyebrow"><?php echo esc_html( (string) $h['eyebrow'] ); ?></p>
				<h1 class="liw-li__hero-headline"><?php echo esc_html( (string) $h['headline'] ); ?></h1>
				<p class="liw-li__hero-intro"><?php echo esc_html( (string) $h['intro'] ); ?></p>
				<div class="liw-li__cta-row">
					<a class="liw-cta liw-cta--primary" href="<?php echo esc_attr( $discover ); ?>"><?php echo esc_html( (string) $h['cta_primary_label'] ); ?></a>
					<a class="liw-cta liw-cta--secondary" href="<?php echo esc_url( $platform ); ?>"><?php echo esc_html( (string) $h['cta_secondary_label'] ); ?></a>
				</div>
				<p class="liw-li__tagline"><?php echo esc_html( (string) $h['tagline'] ); ?></p>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function render_vision(): string {
		$v = Content::get()['vision'];
		ob_start();
		?>
		<section class="liw-li__section liw-li__section--vision" id="li-vision">
			<div class="liw-li__inner">
				<?php echo self::head( $v['title'], $v['intro'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in head() escaped. ?>
				<ul class="liw-li__triad" role="list">
					<?php foreach ( (array) $v['fields'] as $i => $f ) : ?>
						<li class="liw-li__triad-item">
							<span class="liw-li__triad-num" aria-hidden="true"><?php echo (int) $i + 1; ?></span>
							<h3 class="liw-li__triad-title"><?php echo esc_html( (string) $f['title'] ); ?></h3>
							<p class="liw-li__triad-text"><?php echo esc_html( (string) $f['text'] ); ?></p>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function render_flow(): string {
		$f = Content::get()['flow'];
		ob_start();
		?>
		<section class="liw-li__section liw-li__section--flow" id="li-flow">
			<div class="liw-li__inner">
				<?php echo self::head( $f['title'], $f['intro'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<ol class="liw-li__flow" role="list">
					<?php foreach ( (array) $f['steps'] as $i => $s ) : ?>
						<li class="liw-li__flow-step">
							<span class="liw-li__flow-num" aria-hidden="true"><?php echo (int) $i + 1; ?></span>
							<span class="liw-li__flow-body">
								<span class="liw-li__flow-title"><?php echo esc_html( (string) $s['title'] ); ?></span>
								<span class="liw-li__flow-text"><?php echo esc_html( (string) $s['text'] ); ?></span>
							</span>
						</li>
					<?php endforeach; ?>
				</ol>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function render_simulation(): string {
		$s     = Content::get()['simulation'];
		$scen  = (array) $s['scenarios'];
		$first = isset( $scen[0]['key'] ) ? (string) $scen[0]['key'] : 'A';
		ob_start();
		?>
		<section class="liw-li__section liw-li__section--sim" id="li-simulation">
			<div class="liw-li__inner">
				<?php echo self::head( $s['title'], $s['intro'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<p class="liw-li__demo-note"><?php echo esc_html( (string) $s['demo_note'] ); ?></p>
				<div class="liw-li__sim" data-liw-sim>
					<div class="liw-li__sim-tabs" role="tablist" aria-label="<?php echo esc_attr__( 'Simulationsvarianten', 'liebherr-interface-world' ); ?>">
						<?php foreach ( $scen as $v ) : $key = (string) $v['key']; $active = $key === $first; ?>
							<button type="button" class="liw-li__sim-tab<?php echo $active ? ' is-active' : ''; ?>" role="tab" id="liw-sim-tab-<?php echo esc_attr( $key ); ?>" aria-controls="liw-sim-panel-<?php echo esc_attr( $key ); ?>" aria-selected="<?php echo $active ? 'true' : 'false'; ?>" data-liw-sim-tab="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( (string) $v['label'] ); ?></button>
						<?php endforeach; ?>
					</div>
					<?php foreach ( $scen as $v ) : $key = (string) $v['key']; $active = $key === $first; ?>
						<?php /* Ohne JS bleiben alle Panels sichtbar (§10); der Schalter versteckt inaktive erst nach Enhancement. */ ?>
						<div class="liw-li__sim-panel<?php echo $active ? ' is-active' : ''; ?>" role="tabpanel" id="liw-sim-panel-<?php echo esc_attr( $key ); ?>" aria-labelledby="liw-sim-tab-<?php echo esc_attr( $key ); ?>" data-liw-sim-panel="<?php echo esc_attr( $key ); ?>">
							<p class="liw-li__sim-summary"><?php echo esc_html( (string) $v['summary'] ); ?></p>
							<dl class="liw-li__sim-rows">
								<?php foreach ( (array) $v['rows'] as $row ) : ?>
									<div class="liw-li__sim-row">
										<dt class="liw-li__sim-key"><?php echo esc_html( (string) $row['label'] ); ?></dt>
										<dd class="liw-li__sim-val"><?php echo esc_html( (string) $row['value'] ); ?></dd>
									</div>
								<?php endforeach; ?>
							</dl>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function render_knowledge(): string {
		$k = Content::get()['knowledge'];
		ob_start();
		?>
		<section class="liw-li__section liw-li__section--knowledge" id="li-knowledge">
			<div class="liw-li__inner">
				<?php echo self::head( $k['title'], $k['intro'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div class="liw-li__qa">
					<p class="liw-li__qa-q"><span class="liw-li__qa-label"><?php echo esc_html__( 'Frage', 'liebherr-interface-world' ); ?></span><?php echo esc_html( (string) $k['question'] ); ?></p>
					<p class="liw-li__qa-a"><span class="liw-li__qa-label"><?php echo esc_html__( 'Antwort', 'liebherr-interface-world' ); ?></span><?php echo esc_html( (string) $k['answer'] ); ?></p>
					<p class="liw-li__qa-meta"><?php echo esc_html( (string) $k['source'] ); ?> · <?php echo esc_html( (string) $k['status'] ); ?></p>
					<p class="liw-li__qa-next"><?php echo esc_html( (string) $k['next_action'] ); ?></p>
				</div>
				<ul class="liw-li__cards" role="list">
					<?php foreach ( (array) $k['points'] as $p ) : ?>
						<li class="liw-li__card"><h3 class="liw-li__card-title"><?php echo esc_html( (string) $p['title'] ); ?></h3><p class="liw-li__card-text"><?php echo esc_html( (string) $p['text'] ); ?></p></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function render_trust(): string {
		$t = Content::get()['trust'];
		ob_start();
		?>
		<section class="liw-li__section liw-li__section--trust" id="li-trust">
			<div class="liw-li__inner">
				<?php echo self::head( $t['title'], $t['intro'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<ul class="liw-li__cards liw-li__cards--4" role="list">
					<?php foreach ( (array) $t['areas'] as $a ) : ?>
						<li class="liw-li__card"><h3 class="liw-li__card-title"><?php echo esc_html( (string) $a['title'] ); ?></h3><p class="liw-li__card-text"><?php echo esc_html( (string) $a['text'] ); ?></p></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function render_global(): string {
		$g = Content::get()['global'];
		ob_start();
		?>
		<section class="liw-li__section liw-li__section--global" id="li-global">
			<div class="liw-li__inner">
				<?php echo self::head( $g['title'], $g['intro'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<ul class="liw-li__devices" role="list">
					<?php foreach ( (array) $g['devices'] as $d ) : ?>
						<li class="liw-li__device"><?php echo esc_html( (string) $d ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p class="liw-li__note"><?php echo esc_html( (string) $g['workflow_note'] ); ?></p>
				<p class="liw-li__note liw-li__note--muted"><?php echo esc_html( (string) $g['languages_note'] ); ?></p>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function render_usecases(): string {
		$u    = Content::get()['usecases'];
		$tags = [];
		foreach ( (array) $u['items'] as $it ) {
			$tag = (string) ( $it['tag'] ?? '' );
			if ( '' !== $tag ) { $tags[ $tag ] = $tag; }
		}
		ob_start();
		?>
		<section class="liw-li__section liw-li__section--usecases" id="li-usecases">
			<div class="liw-li__inner">
				<?php echo self::head( $u['title'], $u['intro'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php if ( count( $tags ) > 1 ) : ?>
					<div class="liw-li__filters" data-liw-usecase-filter role="group" aria-label="<?php echo esc_attr__( 'Einsatzfelder filtern', 'liebherr-interface-world' ); ?>">
						<button type="button" class="liw-li__filter is-active" data-liw-filter="*" aria-pressed="true"><?php echo esc_html__( 'Alle', 'liebherr-interface-world' ); ?></button>
						<?php foreach ( $tags as $tag ) : ?>
							<button type="button" class="liw-li__filter" data-liw-filter="<?php echo esc_attr( $tag ); ?>" aria-pressed="false"><?php echo esc_html( $tag ); ?></button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<ul class="liw-li__usecases" role="list">
					<?php foreach ( (array) $u['items'] as $it ) : ?>
						<li class="liw-li__usecase" data-liw-tag="<?php echo esc_attr( (string) ( $it['tag'] ?? '' ) ); ?>">
							<?php if ( '' !== (string) ( $it['tag'] ?? '' ) ) : ?>
								<span class="liw-li__usecase-tag"><?php echo esc_html( (string) $it['tag'] ); ?></span>
							<?php endif; ?>
							<h3 class="liw-li__usecase-title"><?php echo esc_html( (string) $it['title'] ); ?></h3>
							<p class="liw-li__usecase-line"><strong><?php echo esc_html__( 'Problem:', 'liebherr-interface-world' ); ?></strong> <?php echo esc_html( (string) $it['problem'] ); ?></p>
							<p class="liw-li__usecase-line"><strong><?php echo esc_html__( 'Prinzip:', 'liebherr-interface-world' ); ?></strong> <?php echo esc_html( (string) $it['principle'] ); ?></p>
							<p class="liw-li__usecase-line"><strong><?php echo esc_html__( 'Nutzen:', 'liebherr-interface-world' ); ?></strong> <?php echo esc_html( (string) $it['benefit'] ); ?></p>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function render_bridge(): string {
		$b    = Content::get()['bridge'];
		$href = self::interface_href();
		ob_start();
		?>
		<section class="liw-li__section liw-li__section--bridge" id="li-bridge">
			<div class="liw-li__inner">
				<?php echo self::head( $b['title'], $b['intro'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<ul class="liw-li__bridge-points" role="list">
					<?php foreach ( (array) $b['points'] as $p ) : ?>
						<li class="liw-li__bridge-point"><?php echo esc_html( (string) $p ); ?></li>
					<?php endforeach; ?>
				</ul>
				<a class="liw-cta liw-cta--primary" href="<?php echo esc_url( $href ); ?>"><?php echo esc_html( (string) $b['cta_label'] ); ?></a>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function render_rollout(): string {
		$r = Content::get()['rollout'];
		ob_start();
		?>
		<section class="liw-li__section liw-li__section--rollout" id="li-rollout">
			<div class="liw-li__inner">
				<?php echo self::head( $r['title'], $r['intro'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<ol class="liw-li__rollout" role="list">
					<?php foreach ( (array) $r['steps'] as $i => $s ) : ?>
						<li class="liw-li__rollout-step">
							<span class="liw-li__rollout-num" aria-hidden="true"><?php echo (int) $i + 1; ?></span>
							<span class="liw-li__rollout-title"><?php echo esc_html( (string) $s['title'] ); ?></span>
							<span class="liw-li__rollout-text"><?php echo esc_html( (string) $s['text'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ol>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	public static function render_contact(): string {
		$c    = Content::get()['contact'];
		$href = self::interface_href();
		ob_start();
		?>
		<section class="liw-li__section liw-li__section--contact" id="li-contact">
			<div class="liw-li__inner">
				<?php echo self::head( $c['title'], $c['intro'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div class="liw-li__cta-row">
					<a class="liw-cta liw-cta--primary" href="#li-contact-form"><?php echo esc_html( (string) $c['cta_primary_label'] ); ?></a>
					<a class="liw-cta liw-cta--secondary" href="<?php echo esc_url( $href ); ?>"><?php echo esc_html( (string) $c['cta_secondary_label'] ); ?></a>
				</div>
				<div class="liw-li__form" id="li-contact-form">
					<?php echo do_shortcode( '[liw_contact_form]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- bestehendes Formular, selbst escaped. ?>
				</div>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Kontextnavigation für die untergeordnete Interface-Solutions-Seite (LI §3.3/§13):
	 * Breadcrumb Local Intelligence › Interface Solutions und Rücklink zur Hauptseite.
	 *
	 * @param array<string,string>|string $atts `position="top|bottom"`
	 */
	public static function render_context_nav( $atts = [] ): string {
		$atts    = shortcode_atts( [ 'position' => 'top' ], is_array( $atts ) ? $atts : [], 'liw_context_nav' );
		$li_url  = SitePages::li_url();
		if ( '' === $li_url ) {
			return '';
		}
		$li_label = __( 'Liebherr Local Intelligence', 'liebherr-interface-world' );
		if ( 'bottom' === $atts['position'] ) {
			return '<div class="liw-li__contextnav liw-li__contextnav--bottom"><a class="liw-cta liw-cta--secondary" href="' . esc_url( $li_url ) . '">&larr; ' . esc_html__( 'Zurück zu Local Intelligence', 'liebherr-interface-world' ) . '</a></div>';
		}
		return '<nav class="liw-li__contextnav" aria-label="' . esc_attr__( 'Brotkrume', 'liebherr-interface-world' ) . '"><ol class="liw-li__crumbs"><li><a href="' . esc_url( $li_url ) . '">' . esc_html( $li_label ) . '</a></li><li aria-current="page">' . esc_html__( 'Interface Solutions', 'liebherr-interface-world' ) . '</li></ol></nav>';
	}

	// ---------------------------------------------------------------------
	// Helfer
	// ---------------------------------------------------------------------

	/** Abschnittskopf: H2 + optionale Einleitung. */
	private static function head( $title, $intro ): string {
		$out = '<h2 class="liw-li__title">' . esc_html( (string) $title ) . '</h2>';
		if ( '' !== trim( (string) $intro ) ) {
			$out .= '<p class="liw-li__intro">' . esc_html( (string) $intro ) . '</p>';
		}
		return $out;
	}

	/** Ziel für „Technische Plattform / Interface Solutions": echte URL, sonst In-Page-Anker. */
	private static function interface_href(): string {
		$url = SitePages::interface_url();
		return '' !== $url ? $url : '#li-bridge';
	}
}
