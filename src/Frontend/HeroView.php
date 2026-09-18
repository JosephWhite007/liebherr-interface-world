<?php
/**
 * Liebherr Interface Solutions – Hero Network (LP-01, §8/§16).
 *
 * Öffentlicher Shortcode `[liw_hero]`: großformatiger Hero mit dezenter Netzwerk-Ebene, H1, Subline
 * und den beiden CTAs aus Settings\HeaderSettings („Start Integration" / „Explore the Simulation").
 * Bildmotiv nur, wenn im Media Board freigegeben (CI-005); sonst neutraler Verlauf (kein unfreigegebenes
 * Liebherr-Motiv im Frontend). Headline/Subline über Attribute (im Abschnittstext gepflegt → über die
 * Core-Übersetzung mehrsprachig); Standardtexte via `__()`. Netzwerk-Ebene ist dekorativ (aria-hidden)
 * und respektiert `prefers-reduced-motion` (CSS). Kein Inline-CSS/-JS.
 *
 * Beispiel im LP-01-Abschnitt:
 *   [liw_hero headline="One structure. Connected worldwide." subline="…" image="123"]
 *
 * @package Liebherr\InterfaceWorld\Frontend
 * @since   0.1.0-alpha.28
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Frontend;

use Liebherr\InterfaceWorld\CoreBridge\MediaBridge;
use Liebherr\InterfaceWorld\Settings\HeaderSettings;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class HeroView {

	public const SHORTCODE = 'liw_hero';

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'render' ] );
	}

	/**
	 * @param array<string,string>|string $atts
	 */
	public static function render( $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'headline' => __( 'One structure. Connected worldwide.', 'liebherr-interface-world' ),
				'subline'  => __( 'Sichere Verbindung zentraler Liebherr-Systeme mit lokalen Händler-, Lieferanten- und Kundensystemen.', 'liebherr-interface-world' ),
				'image'    => '',
			],
			is_array( $atts ) ? $atts : [],
			self::SHORTCODE
		);

		$cfg      = HeaderSettings::get();
		$image_id = '' !== (string) $atts['image'] ? (int) $atts['image'] : (int) $cfg['hero_image_id'];
		$has_img  = $image_id > 0 && MediaBridge::is_approved( $image_id );

		ob_start();
		?>
		<section class="liw-hero<?php echo $has_img ? ' liw-hero--image' : ' liw-hero--plain'; ?>" id="lp-01">
			<?php if ( $has_img ) : ?>
				<div class="liw-hero__media">
					<?php echo wp_get_attachment_image( $image_id, 'full', false, [ 'class' => 'liw-hero__img', 'loading' => 'eager', 'decoding' => 'async' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core-Markup, Attribute escaped. ?>
				</div>
			<?php endif; ?>
			<div class="liw-hero__network" aria-hidden="true"></div>
			<div class="liw-hero__content">
				<h1 class="liw-hero__headline"><?php echo esc_html( (string) $atts['headline'] ); ?></h1>
				<?php if ( '' !== trim( (string) $atts['subline'] ) ) : ?>
					<p class="liw-hero__subline"><?php echo esc_html( (string) $atts['subline'] ); ?></p>
				<?php endif; ?>
				<div class="liw-hero__cta">
					<a class="liw-cta liw-cta--primary" href="<?php echo esc_url( (string) $cfg['cta_primary']['target'] ); ?>"><?php echo esc_html( (string) $cfg['cta_primary']['label'] ); ?></a>
					<a class="liw-cta liw-cta--secondary" href="<?php echo esc_url( (string) $cfg['cta_secondary']['target'] ); ?>"><?php echo esc_html( (string) $cfg['cta_secondary']['label'] ); ?></a>
				</div>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}
}
