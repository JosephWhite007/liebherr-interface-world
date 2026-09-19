<?php
/**
 * Liebherr Adventures – Insel-Frontend (Shortcode `[liw_adventures]`, §7, MVP).
 *
 * Rendert die vierte Insel: Hero mit Kernbotschaft, Filterleiste (Inhaltstyp/Dringlichkeit), Create-Panel
 * (nur mit Intelligence-Zugang), Stream (serverseitig vorgerendert, per JS aktualisierbar) und die
 * zurückhaltende Ortsdienst-Partner-Attribution (§4.2). Ohne JS bleiben Hero + initialer Stream lesbar.
 *
 * @package Liebherr\InterfaceWorld\Adventures
 * @since   0.1.0-alpha.51
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Adventures;

use Liebherr\InterfaceWorld\Adventures\Location\LocationService;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AdventuresView {

	public const SHORTCODE = 'liw_adventures';
	private const HANDLE    = 'liw-adventures';

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'render' ] );
		add_action( 'wp_enqueue_scripts', [ self::class, 'maybe_enqueue' ] );
		add_filter( 'style_loader_src', [ self::class, 'bust' ], 9999, 2 );
		add_filter( 'script_loader_src', [ self::class, 'bust' ], 9999, 2 );
	}

	private static function ver( string $rel ): string {
		$m = is_readable( LIW_PATH . $rel ) ? (int) filemtime( LIW_PATH . $rel ) : 0;
		return $m > 0 ? (string) $m : LIW_VERSION;
	}

	public static function bust( $src, $handle ) {
		if ( self::HANDLE !== $handle || ! is_string( $src ) || false !== strpos( $src, 'v=' ) ) {
			return $src;
		}
		foreach ( [ 'assets/css/liw-adventures.css', 'assets/js/liw-adventures.js' ] as $rel ) {
			if ( false !== strpos( $src, $rel ) ) {
				return add_query_arg( 'v', self::ver( $rel ), $src );
			}
		}
		return $src;
	}

	public static function maybe_enqueue(): void {
		$post = get_post();
		if ( ! $post instanceof \WP_Post || ! has_shortcode( (string) $post->post_content, self::SHORTCODE ) ) {
			return;
		}
		wp_enqueue_style( self::HANDLE, LIW_URL . 'assets/css/liw-adventures.css', [], self::ver( 'assets/css/liw-adventures.css' ) );
		wp_add_inline_style( self::HANDLE, \Liebherr\InterfaceWorld\Branding\BrandTokens::css_root() );
		wp_enqueue_script( self::HANDLE, LIW_URL . 'assets/js/liw-adventures.js', [], self::ver( 'assets/js/liw-adventures.js' ), true );
		wp_localize_script( self::HANDLE, 'liwAdv', [
			'rest'      => esc_url_raw( rest_url( Rest::NAMESPACE . '/' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'canCreate' => Policy::can_create(),
			'types'     => Taxonomy::content_types(),
			'urgencies' => Taxonomy::urgency_levels(),
			'i18n'      => [
				'locating' => __( 'Standort wird ermittelt …', 'liebherr-interface-world' ),
				'located'  => __( 'Ort ermittelt', 'liebherr-interface-world' ),
				'geoErr'   => __( 'Standort nicht verfügbar – bitte Koordinaten eingeben.', 'liebherr-interface-world' ),
				'empty'    => __( 'Noch keine Adventures für diese Auswahl.', 'liebherr-interface-world' ),
				'saving'   => __( 'Wird gespeichert …', 'liebherr-interface-world' ),
				'needTitle'  => __( 'Titel erforderlich.', 'liebherr-interface-world' ),
				'needRights' => __( 'Bitte die Rechte-Zusicherung bestätigen.', 'liebherr-interface-world' ),
				'articlebook' => __( 'Artikelbook', 'liebherr-interface-world' ),
			],
		] );
	}

	public static function render(): string {
		$initial   = AdventureService::query( [ 'limit' => 24 ] );
		$partner   = LocationService::attribution_text();
		$can       = Policy::can_create();

		ob_start();
		?>
		<div class="liw-adv" data-liw-adv>
			<section class="liw-adv__hero">
				<p class="liw-adv__eyebrow"><?php echo esc_html__( 'LIEBHERR ADVENTURES · Vierte Insel', 'liebherr-interface-world' ); ?></p>
				<h1 class="liw-adv__headline"><?php echo esc_html__( 'One place. Three words. One shared experience.', 'liebherr-interface-world' ); ?></h1>
				<p class="liw-adv__subline"><?php echo esc_html__( 'Erfahrungen sichtbar machen, Wissen auffindbar, Hilfe ortsgenau. Foto oder Kurzvideo + Drei-Wörter-Ort + Klassifikation.', 'liebherr-interface-world' ); ?></p>
				<div class="liw-adv__cta-row">
					<a class="liw-cta liw-cta--primary" href="#liw-adv-create"><?php echo esc_html__( 'Create Adventure', 'liebherr-interface-world' ); ?></a>
					<a class="liw-cta liw-cta--secondary" href="#liw-adv-stream"><?php echo esc_html__( 'Discover', 'liebherr-interface-world' ); ?></a>
				</div>
				<p class="liw-adv__partner"><?php echo esc_html( $partner ); ?> · <?php echo esc_html__( 'Nur Demo-Daten (Prototyp).', 'liebherr-interface-world' ); ?></p>
			</section>

			<section class="liw-adv__filters" data-liw-adv-filters aria-label="<?php echo esc_attr__( 'Adventures filtern', 'liebherr-interface-world' ); ?>">
				<label class="liw-adv__filter"><span><?php echo esc_html__( 'Inhaltstyp', 'liebherr-interface-world' ); ?></span>
					<select data-liw-adv-filter="type">
						<option value="">— <?php echo esc_html__( 'Alle', 'liebherr-interface-world' ); ?> —</option>
						<?php foreach ( Taxonomy::content_types() as $code => $label ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label class="liw-adv__filter"><span><?php echo esc_html__( 'Dringlichkeit', 'liebherr-interface-world' ); ?></span>
					<select data-liw-adv-filter="urgency">
						<option value="">— <?php echo esc_html__( 'Alle', 'liebherr-interface-world' ); ?> —</option>
						<?php foreach ( Taxonomy::urgency_levels() as $code => $label ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</section>

			<?php if ( $can ) : ?>
				<?php echo SubmissionForm::render( 'frontend' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in SubmissionForm escaped. ?>
			<?php else : ?>
				<section class="liw-adv__gate-note" id="liw-adv-create">
					<p><?php echo esc_html__( 'Zum Erstellen von Adventures ist ein aktiver Intelligence-Zugang (Anmeldung) erforderlich.', 'liebherr-interface-world' ); ?></p>
				</section>
			<?php endif; ?>

			<section class="liw-adv__stream-wrap" id="liw-adv-stream">
				<h2><?php echo esc_html__( 'Discover', 'liebherr-interface-world' ); ?></h2>
				<ul class="liw-adv__stream" data-liw-adv-stream role="list">
					<?php if ( [] === $initial ) : ?>
						<li class="liw-adv__empty"><?php echo esc_html__( 'Noch keine Adventures veröffentlicht.', 'liebherr-interface-world' ); ?></li>
					<?php else : ?>
						<?php foreach ( $initial as $a ) : ?>
							<?php echo self::card( $a ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in card() escaped. ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</ul>
			</section>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/** @param array<string,mixed> $a */
	public static function card( array $a ): string {
		$badges = '<span class="liw-adv__badge liw-adv__badge--type">' . esc_html( (string) $a['type_label'] ) . '</span>'
			. '<span class="liw-adv__badge liw-adv__badge--urg liw-adv__badge--' . esc_attr( (string) $a['urgency'] ) . '">' . esc_html( (string) $a['urgency_label'] ) . '</span>';
		$media = '' !== (string) $a['image']
			? '<span class="liw-adv__card-media" style="background-image:url(' . esc_url( (string) $a['image'] ) . ')"></span>'
			: '<span class="liw-adv__card-media liw-adv__card-media--empty" aria-hidden="true"></span>';
		return '<li class="liw-adv__card">'
			. $media
			. '<span class="liw-adv__card-body">'
			. '<span class="liw-adv__badges">' . $badges . '</span>'
			. '<span class="liw-adv__card-title">' . esc_html( (string) $a['title'] ) . '</span>'
			. ( '' !== (string) $a['words'] ? '<span class="liw-adv__card-words">/// ' . esc_html( (string) $a['words'] ) . '</span>' : '' )
			. '<span class="liw-adv__card-meta">' . esc_html( trim( (string) $a['region'] . ' · ' . (string) $a['date'], ' ·' ) ) . '</span>'
			. ( '' !== (string) $a['story'] ? '<span class="liw-adv__card-story">' . esc_html( (string) $a['story'] ) . '</span>' : '' )
			. '</span>'
			. '</li>';
	}
}
