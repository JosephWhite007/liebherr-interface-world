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
use Liebherr\InterfaceWorld\CoreBridge\LanguageBridge;
use Liebherr\InterfaceWorld\CoreBridge\MediaBridge;
use Liebherr\InterfaceWorld\Settings\HeaderSettings;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class HeaderView {

	public const SHORTCODE = 'liw_header';

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'render' ] );
	}

	public static function render(): string {
		$cfg = HeaderSettings::get();

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
		$logo_id = BrandTokens::logo_id();
		if ( $logo_id > 0 && MediaBridge::is_approved( $logo_id ) ) {
			$img = wp_get_attachment_image( $logo_id, 'medium', false, [ 'class' => 'liw-header__logo', 'alt' => get_bloginfo( 'name' ) ] );
			if ( '' !== $img ) {
				return $img;
			}
		}
		return '<span class="liw-header__wordmark">' . esc_html( get_bloginfo( 'name' ) ) . '</span>';
	}
}
