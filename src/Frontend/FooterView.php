<?php
/**
 * Liebherr Interface Solutions – Footer (LP-14, §8/§14, alpha.38).
 *
 * Öffentlicher Shortcode `[liw_footer]`: schwarze Meta-/Legal-Leiste im Liebherr-Stil (Vorbild:
 * liebherr.com-Startseite) – Wortmarke links, rechtliche Navigation rechts, sehr kleine Kennzeichnung
 * „Solution Provider: GoHeal" (CI-003) und Copyright. Legal-Links über den Filter
 * `liw_footer_legal_links` administrierbar/anpassbar; Datenschutz nutzt standardmäßig die WP-Datenschutzseite.
 * Farben/Schriften über die zentralen Tokens; kein Inline-CSS.
 *
 * @package Liebherr\InterfaceWorld\Frontend
 * @since   0.1.0-alpha.38
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Frontend;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class FooterView {

	public const SHORTCODE = 'liw_footer';

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'render' ] );
	}

	public static function render(): string {
		$brand = get_bloginfo( 'name' );

		ob_start();
		?>
		<footer class="liw-footer">
			<div class="liw-footer__inner">
				<a class="liw-footer__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( $brand ); ?></a>
				<nav class="liw-footer__legal" aria-label="<?php esc_attr_e( 'Rechtliches', 'liebherr-interface-world' ); ?>">
					<?php foreach ( self::legal_links() as $link ) : ?>
						<a class="liw-footer__legal-link" href="<?php echo esc_url( $link['href'] ); ?>"><?php echo esc_html( $link['label'] ); ?></a>
					<?php endforeach; ?>
				</nav>
			</div>
			<div class="liw-footer__meta">
				<span class="liw-footer__copyright">© <?php echo esc_html( (string) gmdate( 'Y' ) . ' ' . $brand ); ?></span>
				<span class="liw-footer__provider"><?php esc_html_e( 'Solution Provider: GoHeal', 'liebherr-interface-world' ); ?></span>
			</div>
		</footer>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Rechtliche Footer-Links (Vorbild Liebherr-Startseite). Über `liw_footer_legal_links` anpassbar.
	 *
	 * @return array<int,array{label:string,href:string}>
	 */
	private static function legal_links(): array {
		$privacy = get_privacy_policy_url();
		$links   = [
			[ 'label' => __( 'Impressum', 'liebherr-interface-world' ), 'href' => '#' ],
			[ 'label' => __( 'Datenschutzhinweis', 'liebherr-interface-world' ), 'href' => '' !== $privacy ? $privacy : '#' ],
			[ 'label' => __( 'Kontakt', 'liebherr-interface-world' ), 'href' => '#lp-13' ],
			[ 'label' => __( 'Privacy Settings', 'liebherr-interface-world' ), 'href' => '#' ],
			[ 'label' => __( 'Barrierefreiheitserklärung', 'liebherr-interface-world' ), 'href' => '#' ],
		];

		/**
		 * @param array<int,array{label:string,href:string}> $links
		 */
		$filtered = apply_filters( 'liw_footer_legal_links', $links );
		if ( ! is_array( $filtered ) ) {
			return $links;
		}

		$out = [];
		foreach ( $filtered as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$label = sanitize_text_field( (string) ( $item['label'] ?? '' ) );
			$href  = (string) ( $item['href'] ?? '' );
			$href  = ( '' !== $href && '#' === $href[0] ) ? '#' . preg_replace( '/[^A-Za-z0-9_\-]/', '', substr( $href, 1 ) ) : esc_url_raw( $href );
			if ( '' !== $label ) {
				$out[] = [ 'label' => $label, 'href' => '' !== $href ? $href : '#' ];
			}
		}
		return [] !== $out ? $out : $links;
	}
}
