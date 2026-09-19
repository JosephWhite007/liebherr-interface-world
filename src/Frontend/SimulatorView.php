<?php
/**
 * Liebherr Simulation World – Startbildschirm (Shortcode `[liw_simulator]`).
 *
 * Zeigt ein vollflächiges Cockpit-Startbild mit „Start your journey"-Aktion. Das Startbild wird – wie beim
 * World-Connections-Visual – bevorzugt geladen: freigegebenes Media-Board-Bild (Option
 * `liw_simulator_image_id`, CI-005) ODER Datei `assets/img/liw-simulator-start.(jpg|png|webp)`, überschreibbar
 * per Filter `liw_simulator_image`. Ohne Bild erscheint ein dunkler Platzhalter mit Hinweis. Das Ziel der
 * Aktion ist konfigurierbar (Option `liw_simulator_target` / Filter `liw_simulator_target`, Standard:
 * Intelligence World, sonst In-Page). Rein tokenbasiert (`--brand-*`), reduced-motion-fest.
 *
 * @package Liebherr\InterfaceWorld\Frontend
 * @since   0.1.0-alpha.53
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Frontend;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SimulatorView {

	public const SHORTCODE = 'liw_simulator';

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'render' ] );
	}

	/** Startbild-URL (Media-Board-Bild ODER Datei), per Filter überschreibbar; '' = kein Bild. */
	public static function image_url(): string {
		$id = (int) get_option( 'liw_simulator_image_id', 0 );
		if ( $id > 0 && \Liebherr\InterfaceWorld\CoreBridge\MediaBridge::is_approved( $id ) ) {
			$url = wp_get_attachment_image_url( $id, 'full' );
			if ( is_string( $url ) && '' !== $url ) {
				return (string) apply_filters( 'liw_simulator_image', $url );
			}
		}
		foreach ( [ 'jpg', 'jpeg', 'png', 'webp' ] as $ext ) {
			$rel = 'assets/img/liw-simulator-start.' . $ext;
			if ( is_readable( LIW_PATH . $rel ) ) {
				return (string) apply_filters( 'liw_simulator_image', LIW_URL . $rel );
			}
		}
		return (string) apply_filters( 'liw_simulator_image', '' );
	}

	private static function target(): string {
		$opt = (string) get_option( 'liw_simulator_target', '' );
		if ( '' === $opt ) {
			$iw = (int) get_option( 'liw_iw_page_id', 0 );
			// „Go" führt in die Intelligence World und – nach dem Eintritt – direkt zum Simulation Builder
			// (Anker; die Weltansicht wird nach dem Access Gate sichtbar und dorthin gescrollt).
			$opt = $iw > 0 ? (string) get_permalink( $iw ) . '#liw-iw-simulation' : '#liw-sim';
		}
		return (string) apply_filters( 'liw_simulator_target', $opt );
	}

	/**
	 * @param array<string,string>|string $atts headline / cta überschreibbar.
	 */
	public static function render( $atts = [] ): string {
		$atts = shortcode_atts(
			[
				'headline' => __( 'Start your journey', 'liebherr-interface-world' ),
				'subline'  => __( 'Technologie verbindet – Menschen verstehen sich.', 'liebherr-interface-world' ),
				'cta'      => __( 'Go', 'liebherr-interface-world' ),
			],
			is_array( $atts ) ? $atts : [],
			self::SHORTCODE
		);
		$img    = self::image_url();
		$target = self::target();

		ob_start();
		?>
		<section class="liw-sim<?php echo '' !== $img ? ' liw-sim--image' : ' liw-sim--plain'; ?>" id="liw-sim">
			<?php if ( '' !== $img ) : ?>
				<img class="liw-sim__bg" src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr__( 'Liebherr Simulation World – Cockpit', 'liebherr-interface-world' ); ?>" loading="eager" decoding="async" />
			<?php endif; ?>
			<div class="liw-sim__overlay">
				<p class="liw-sim__eyebrow"><?php echo esc_html__( 'LIEBHERR SIMULATION WORLD', 'liebherr-interface-world' ); ?></p>
				<h1 class="liw-sim__headline"><?php echo esc_html( (string) $atts['headline'] ); ?></h1>
				<?php if ( '' !== trim( (string) $atts['subline'] ) ) : ?>
					<p class="liw-sim__subline"><?php echo esc_html( (string) $atts['subline'] ); ?></p>
				<?php endif; ?>
				<a class="liw-cta liw-cta--primary liw-sim__cta" href="<?php echo esc_url( $target ); ?>"><?php echo esc_html( (string) $atts['cta'] ); ?></a>
				<?php if ( '' === $img && current_user_can( 'manage_options' ) ) : ?>
					<p class="liw-sim__note"><?php echo esc_html__( 'Hinweis (nur Admins): Startbild fehlt – Datei assets/img/liw-simulator-start.jpg ablegen oder Media-Board-Bild-ID in Option liw_simulator_image_id setzen.', 'liebherr-interface-world' ); ?></p>
				<?php endif; ?>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}
}
