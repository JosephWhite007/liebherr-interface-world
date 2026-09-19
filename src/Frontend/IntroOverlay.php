<?php
/**
 * Liebherr Local Intelligence – Intro-Overlay (Sternenregen + Eintritts-Fenster, alpha.45).
 *
 * Öffentlicher Shortcode `[liw_intro]`: cineastischer Seiteneinstieg (~7 s). Aus dem Dunkel erscheint
 * der Titel „Liebherr Local Intelligence", kleine (freigegebene) Liebherr-Logos fliegen wie aus einem
 * Sternenhimmel; ein wegklickbares Fenster zeigt die Nutzungsbedingungen (aufklappbar) und – statt eines
 * CAPTCHA-Codes – eine einfache Rechenaufgabe aus zwei zweistelligen Zahlen als Eintritts-Bestätigung
 * („Kontrollkästchen"-Ersatz). Nach dem Lösen blendet die Seite langsam aus dem Dunkel auf.
 *
 * Barrierefreiheit/Robustheit: Das Overlay ist ohne JavaScript `hidden` (kein Trap – die Seite ist voll
 * nutzbar); erst das Enhancement-Skript aktiviert es. `prefers-reduced-motion` schaltet den Sternenregen
 * ab (nur ruhiges Ein-/Ausblenden). Die Rechenaufgabe ist eine niedrigschwellige Mensch-Bestätigung.
 *
 * Seit alpha.88 läuft sie über den zentralen {@see \Liebherr\InterfaceWorld\Cvf\ChallengeService} (ADR-
 * LIW-CVF-001 §5, Redundanz-Abbau): cache-sicher (die Aufgabe kommt per REST, NICHT im gecachten HTML) und
 * serverseitig geprüft (signiert/TTL – die Summe verlässt den Server nie). Bleibt ein Soft-Gate: bei
 * unerreichbarem Server blockiert das Skript den Zugang nicht.
 *
 * Texte aus Settings\LocalIntelligenceContent['intro'] (administrierbar). Logo nur, wenn im Media Board
 * freigegeben (CI-005); sonst brand-farbene „Sterne".
 *
 * @package Liebherr\InterfaceWorld\Frontend
 * @since   0.1.0-alpha.45
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Frontend;

use Liebherr\InterfaceWorld\Branding\BrandTokens;
use Liebherr\InterfaceWorld\CoreBridge\MediaBridge;
use Liebherr\InterfaceWorld\Cvf\AccessService;
use Liebherr\InterfaceWorld\Cvf\ChallengeService;
use Liebherr\InterfaceWorld\Settings\LocalIntelligenceContent as Content;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class IntroOverlay {

	public const SHORTCODE = 'liw_intro';
	public const NAMESPACE = 'liw-intro/v1';

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'render' ] );
		add_action( 'rest_api_init', [ self::class, 'routes' ] );
	}

	/**
	 * REST: cache-sichere Rechen-Challenge über den zentralen ChallengeService (ADR-LIW-CVF-001 §5).
	 * Die Aufgabe wird NICHT ins (gecachte) Seiten-HTML eingebettet, sondern per JS geholt/geprüft; die
	 * Summe verlässt den Server nie. Öffentlich (kein Nonce → cache-sicher), niedrigschwellige Mensch-Prüfung.
	 */
	public static function routes(): void {
		register_rest_route( self::NAMESPACE, '/challenge', [
			'methods'             => 'GET',
			'callback'            => [ self::class, 'rest_challenge' ],
			'permission_callback' => '__return_true',
		] );
		register_rest_route( self::NAMESPACE, '/verify', [
			'methods'             => 'POST',
			'callback'            => [ self::class, 'rest_verify' ],
			'permission_callback' => '__return_true',
		] );
	}

	public static function rest_challenge(): \WP_REST_Response {
		$c = ChallengeService::create( AccessService::secret(), time(), 'double' );
		return new \WP_REST_Response( [
			'a'        => $c['a'],
			'b'        => $c['b'],
			'question' => ChallengeService::question( $c['a'], $c['b'] ),
			'token'    => $c['token'],
			'expires'  => $c['expires'],
		], 200 );
	}

	public static function rest_verify( \WP_REST_Request $req ): \WP_REST_Response {
		$res = ChallengeService::verify( (string) $req->get_param( 'token' ), (int) $req->get_param( 'answer' ), AccessService::secret(), time() );
		return new \WP_REST_Response( [ 'ok' => $res['ok'], 'reason' => $res['reason'] ], 200 );
	}

	public static function render(): string {
		$c = Content::get()['intro'];

		// Flieg-Logo nur, wenn freigegeben (CI-005); sonst sorgt das Skript für neutrale Sterne.
		$logo_url = '';
		$logo_id  = BrandTokens::logo_id();
		if ( $logo_id > 0 && MediaBridge::is_approved( $logo_id ) ) {
			$url = wp_get_attachment_image_url( $logo_id, 'full' );
			$logo_url = is_string( $url ) ? $url : '';
		}

		ob_start();
		?>
		<div class="liw-intro" id="liw-intro" data-liw-intro
			data-liw-rest="<?php echo esc_url( rest_url( self::NAMESPACE . '/' ) ); ?>"
			data-liw-logo="<?php echo esc_url( $logo_url ); ?>"
			role="dialog" aria-modal="true" aria-labelledby="liw-intro-title" hidden>
			<div class="liw-intro__backdrop" aria-hidden="true"></div>
			<div class="liw-intro__sky" aria-hidden="true"></div>
			<div class="liw-intro__stage">
				<div class="liw-intro__brand">
					<h2 class="liw-intro__title" id="liw-intro-title"><?php echo esc_html( (string) $c['title'] ); ?></h2>
					<?php if ( '' !== trim( (string) $c['subtitle'] ) ) : ?>
						<p class="liw-intro__subtitle"><?php echo esc_html( (string) $c['subtitle'] ); ?></p>
					<?php endif; ?>
				</div>

				<div class="liw-intro__window">
					<?php if ( '' !== trim( (string) $c['text'] ) ) : ?>
						<p class="liw-intro__text"><?php echo esc_html( (string) $c['text'] ); ?></p>
					<?php endif; ?>

					<button type="button" class="liw-intro__terms-toggle" aria-expanded="false" aria-controls="liw-intro-terms">
						<?php echo esc_html( (string) $c['terms_button_label'] ); ?>
					</button>
					<div class="liw-intro__terms" id="liw-intro-terms" hidden>
						<h3 class="liw-intro__terms-heading"><?php echo esc_html( (string) $c['terms_heading'] ); ?></h3>
						<div class="liw-intro__terms-body"><?php echo self::terms_html( (string) $c['terms_body'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pro Segment mit esc_html() escaped. ?></div>
					</div>

					<form class="liw-intro__gate" data-liw-gate>
						<label class="liw-intro__gate-label" for="liw-intro-answer">
							<?php echo esc_html( (string) $c['math_label'] ); ?>
							<span class="liw-intro__equation" data-liw-eq aria-live="polite"></span>
						</label>
						<input class="liw-intro__answer" id="liw-intro-answer" type="text" inputmode="numeric"
							autocomplete="off" aria-describedby="liw-intro-hint" />
						<button type="submit" class="liw-intro__enter liw-cta liw-cta--primary" disabled>
							<?php echo esc_html( (string) $c['accept_label'] ); ?>
						</button>
						<p class="liw-intro__hint" id="liw-intro-hint" role="status"><?php echo esc_html( (string) $c['accept_hint'] ); ?></p>
					</form>
				</div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Rendert die als schlichtes Markup gepflegten Nutzungsbedingungen zu sicherem HTML:
	 *   `## …`  → Abschnittsüberschrift (h4)
	 *   `- …`   → Aufzählung (ul/li)
	 *   `1. …`  → nummerierte Liste (ol/li)
	 *   sonst   → Absatz (p), Leerzeile trennt/schließt Listen.
	 * Jedes Textsegment wird mit `esc_html()` escaped (kein roher HTML-Durchlass aus der Option).
	 */
	public static function terms_html( string $raw ): string {
		$out  = '';
		$list = ''; // '' | 'ul' | 'ol'
		$close_list = static function () use ( &$list, &$out ): void {
			if ( '' !== $list ) { $out .= '</' . $list . '>'; $list = ''; }
		};
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) { $close_list(); continue; }
			if ( 0 === strpos( $line, '## ' ) ) {
				$close_list();
				$out .= '<h4 class="liw-intro__terms-section">' . esc_html( substr( $line, 3 ) ) . '</h4>';
			} elseif ( 0 === strpos( $line, '- ' ) ) {
				if ( 'ul' !== $list ) { $close_list(); $out .= '<ul>'; $list = 'ul'; }
				$out .= '<li>' . esc_html( substr( $line, 2 ) ) . '</li>';
			} elseif ( preg_match( '/^\d+\.\s+(.*)$/', $line, $m ) ) {
				if ( 'ol' !== $list ) { $close_list(); $out .= '<ol>'; $list = 'ol'; }
				$out .= '<li>' . esc_html( $m[1] ) . '</li>';
			} else {
				$close_list();
				$out .= '<p>' . esc_html( $line ) . '</p>';
			}
		}
		$close_list();
		return $out;
	}
}
