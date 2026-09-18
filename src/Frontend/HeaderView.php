<?php
/**
 * Liebherr Interface Solutions – Header / Hauptnavigation (§7/§16, LP-Chrome).
 *
 * Öffentlicher Shortcode `[liw_header]`: Logo (nur wenn im Media Board freigegeben, CI-005; sonst
 * neutraler Text-Wortmarke – kein erfundenes Logo, CI-002), konfigurierbare Navigation, Primär-/
 * Sekundär-CTA („Start Integration" / „Explore the Simulation"), Core-Sprachumschalter und optionaler
 * Portal-Login. Sticky (CSS) und auf Mobilgeräten als zugängliches Off-Canvas-Menü
 * (Umschalter `.liw-header__toggle`, `aria-expanded`, Skript in assets/js/liebherr-frontend.js).
 *
 * Werte aus Settings\HeaderSettings; Farben/Schriften ausschließlich über `--brand-*` (§11).
 * Kein Inline-CSS/-JS.
 *
 * @package Liebherr\InterfaceWorld\Frontend
 * @since   0.1.0-alpha.28
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Frontend;

use Liebherr\InterfaceWorld\Branding\BrandTokens;
use Liebherr\InterfaceWorld\Content\SitePages;
use Liebherr\InterfaceWorld\CoreBridge\LanguageBridge;
use Liebherr\InterfaceWorld\CoreBridge\MediaBridge;
use Liebherr\InterfaceWorld\Settings\HeaderSettings;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class HeaderView {

	public const SHORTCODE = 'liw_header';

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'render' ] );
	}

	/**
	 * @param array<string,string>|string $atts `nav="li"` verwendet den Local-Intelligence-Menüsatz
	 *        (Anker der Hauptseite + Link zur Interface-Solutions-Unterseite) statt der globalen
	 *        Header-Settings (deren #lp-*-Anker nur auf der Interface-Solutions-Seite existieren).
	 */
	public static function render( $atts = [] ): string {
		$atts = shortcode_atts( [ 'nav' => '' ], is_array( $atts ) ? $atts : [], self::SHORTCODE );
		$cfg  = 'li' === $atts['nav'] ? self::li_nav_config() : HeaderSettings::get();

		ob_start();
		?>
		<header class="liw-header" data-liw-header>
			<div class="liw-header__inner">
				<a class="liw-header__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<?php echo self::brand_markup(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in brand_markup() escaped. ?>
				</a>

				<button type="button" class="liw-header__toggle" aria-expanded="false" aria-controls="liw-header-nav">
					<span class="liw-visually-hidden"><?php esc_html_e( 'Menü öffnen', 'liebherr-interface-world' ); ?></span>
					<span class="liw-header__toggle-bar" aria-hidden="true"></span>
				</button>

				<nav id="liw-header-nav" class="liw-header__nav" aria-label="<?php esc_attr_e( 'Hauptnavigation', 'liebherr-interface-world' ); ?>">
					<?php if ( ! empty( $cfg['nav'] ) ) : ?>
						<ul class="liw-header__nav-list">
							<?php foreach ( $cfg['nav'] as $item ) : ?>
								<li class="liw-header__nav-item"><a class="liw-header__nav-link" href="<?php echo esc_url( (string) $item['target'] ); ?>"><?php echo esc_html( (string) $item['label'] ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<div class="liw-header__actions">
						<div class="liw-header__lang"><?php echo LanguageBridge::switcher_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-Widget-Markup. ?></div>
						<a class="liw-cta liw-cta--secondary" href="<?php echo esc_url( (string) $cfg['cta_secondary']['target'] ); ?>"><?php echo esc_html( (string) $cfg['cta_secondary']['label'] ); ?></a>
						<a class="liw-cta liw-cta--primary" href="<?php echo esc_url( (string) $cfg['cta_primary']['target'] ); ?>"><?php echo esc_html( (string) $cfg['cta_primary']['label'] ); ?></a>
						<?php if ( ! empty( $cfg['portal_enabled'] ) ) : ?>
							<a class="liw-header__portal" href="<?php echo esc_url( '' !== (string) $cfg['portal_url'] ? (string) $cfg['portal_url'] : wp_login_url() ); ?>"><?php esc_html_e( 'Portal-Login', 'liebherr-interface-world' ); ?></a>
						<?php endif; ?>
					</div>
				</nav>
			</div>
		</header>
		<?php
		return (string) ob_get_clean();
	}

	/** Logo (nur freigegeben, CI-005) oder neutrale Text-Wortmarke (kein erfundenes Logo, CI-002). */
	private static function brand_markup(): string {
		$brand   = BrandTokens::brand_text();
		$logo_id = BrandTokens::logo_id();
		if ( $logo_id > 0 && MediaBridge::is_approved( $logo_id ) ) {
			// Direkt-URL statt wp_get_attachment_image(): SVG-Anhänge liefern sonst width="1" height="1"
			// (kein intrinsisches Seitenverhältnis) → das Logo würde als 1×1 px gerendert. Größe via CSS.
			$url = wp_get_attachment_image_url( $logo_id, 'full' );
			if ( is_string( $url ) && '' !== $url ) {
				return sprintf(
					'<img class="liw-header__logo" src="%s" alt="%s" decoding="async" />',
					esc_url( $url ),
					esc_attr( $brand )
				);
			}
		}
		return '<span class="liw-header__wordmark">' . esc_html( $brand ) . '</span>';
	}

	/**
	 * Menüsatz für die Local-Intelligence-Hauptseite: Anker der Hauptseite (`#li-*`) + Link zur
	 * technischen Unterseite. Ersetzt die globalen Header-Settings, deren #lp-*-Anker dort nicht
	 * existieren (behebt die tote obere Menüleiste auf `/liebherr-local-intelligence/`).
	 *
	 * @return array{nav:array<int,array{label:string,target:string}>,cta_primary:array{label:string,target:string},cta_secondary:array{label:string,target:string},portal_enabled:bool,portal_url:string,hero_image_id:int}
	 */
	private static function li_nav_config(): array {
		$base            = HeaderSettings::get(); // Portal/Hero/Logo-Kontext beibehalten.
		$interface_url   = SitePages::interface_url();
		$interface_href  = '' !== $interface_url ? $interface_url : '#li-bridge';

		$base['nav'] = [
			[ 'label' => __( 'Vision', 'liebherr-interface-world' ), 'target' => '#li-vision' ],
			[ 'label' => __( 'Simulation World', 'liebherr-interface-world' ), 'target' => '#li-simulation' ],
			[ 'label' => __( 'Einsatzfelder', 'liebherr-interface-world' ), 'target' => '#li-usecases' ],
			[ 'label' => __( 'Interface Solutions', 'liebherr-interface-world' ), 'target' => $interface_href ],
			[ 'label' => __( 'Kontakt', 'liebherr-interface-world' ), 'target' => '#li-contact' ],
		];
		$base['cta_secondary'] = [ 'label' => __( 'Technische Plattform', 'liebherr-interface-world' ), 'target' => $interface_href ];
		$base['cta_primary']   = [ 'label' => __( 'Demonstration anfragen', 'liebherr-interface-world' ), 'target' => '#li-contact-form' ];

		return $base;
	}
}
