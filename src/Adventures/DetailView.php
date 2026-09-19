<?php
/**
 * Liebherr Adventures – Detailseite eines Beitrags (Backlog A3, §5/§7).
 *
 * Einzelansicht (`?adv=<ID>` bzw. Shortcode `[liw_adventure_detail]`): Titel, Klassifikation, Ort/Region,
 * Registrierungsstatus, Tokenwert, Artikelbook-Referenz und Medium. Der eigentliche Inhalt (Story) ist bei
 * kostenpflichtigen Beiträgen erst nach Tokenakzeptanz sichtbar (§5): Der bezahlte Text wird NICHT vorab ins
 * DOM eingebettet – nach der Bestätigung (Dialog aus alpha.61) lädt die Seite neu und der Server zeigt den
 * Inhalt, weil dann ein Zugriff protokolliert ist ({@see TokenLedger::has_access()}). Eigene Beiträge und
 * kostenlose Beiträge werden direkt gezeigt.
 *
 * @package Liebherr\InterfaceWorld\Adventures
 * @since   0.1.0-alpha.64
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Adventures;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class DetailView {

	public const SHORTCODE = 'liw_adventure_detail';
	public const QUERY_VAR  = 'adv';

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'render_shortcode' ] );
	}

	/** Aktuelle Beitrags-ID aus der Query (`?adv=ID`). 0 = keine. */
	public static function current_id(): int {
		return isset( $_GET[ self::QUERY_VAR ] ) ? max( 0, (int) $_GET[ self::QUERY_VAR ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reine Lese-Auswahl.
	}

	/** Link zur Detailseite eines Beitrags (relativ zur Adventures-Seite). */
	public static function url( int $id, string $base = '' ): string {
		$base = '' !== $base ? $base : self::adventures_base();
		return esc_url_raw( add_query_arg( self::QUERY_VAR, $id, $base ) );
	}

	private static function adventures_base(): string {
		$pid = (int) get_option( 'liw_adventures_page_id', 0 );
		return $pid > 0 ? (string) get_permalink( $pid ) : '';
	}

	public static function render_shortcode(): string {
		$id = self::current_id();
		if ( $id <= 0 ) {
			return '<div class="liw-adv"><p class="liw-advdetail__empty">' . esc_html__( 'Kein Beitrag ausgewählt.', 'liebherr-interface-world' ) . '</p></div>';
		}
		return '<div class="liw-adv" data-liw-adv>' . self::render( $id ) . '</div>';
	}

	/** Detail-Markup für einen Beitrag (ohne umschließenden Container). */
	public static function render( int $id ): string {
		$post = get_post( $id );
		if ( ! $post instanceof \WP_Post || AdventureCpt::POST_TYPE !== $post->post_type ) {
			return '<p class="liw-advdetail__empty">' . esc_html__( 'Beitrag nicht gefunden.', 'liebherr-interface-world' ) . '</p>';
		}

		$viewer      = get_current_user_id();
		$is_author   = (int) $post->post_author === $viewer && $viewer > 0;
		$is_mod      = Policy::can_moderate();
		$visibility  = (string) get_post_meta( $id, AdventureCpt::M_VISIBILITY, true );
		if ( ! Policy::can_view( (string) get_post_status( $post ), $visibility, $is_author, is_user_logged_in(), $is_mod ) ) {
			return '<p class="liw-advdetail__empty">' . esc_html__( 'Dieser Beitrag ist für Sie nicht verfügbar.', 'liebherr-interface-world' ) . '</p>';
		}

		$vm  = AdventureService::to_view( $post, $is_author || $is_mod );
		$reg = RegistrationService::get_registration( $id );

		$token   = (int) $reg['token_value'];
		$usable  = (bool) $reg['usable_company'];
		$has_acc = TokenLedger::has_access( $id, $viewer, (int) $reg['version'] );
		$gated   = ! $is_author && $token > 0 && $usable && ! $has_acc;

		$back = self::adventures_base();

		ob_start();
		?>
		<article class="liw-advdetail">
			<?php if ( '' !== $back ) : ?>
				<p class="liw-advdetail__back"><a href="<?php echo esc_url( $back ); ?>">&larr; <?php echo esc_html__( 'Zurück zur Übersicht', 'liebherr-interface-world' ); ?></a></p>
			<?php endif; ?>

			<div class="liw-advdetail__badges">
				<span class="liw-adv__badge liw-adv__badge--type"><?php echo esc_html( (string) $vm['type_label'] ); ?></span>
				<span class="liw-adv__badge liw-adv__badge--urg liw-adv__badge--<?php echo esc_attr( (string) $vm['urgency'] ); ?>"><?php echo esc_html( (string) $vm['urgency_label'] ); ?></span>
				<span class="liw-advdetail__status liw-wb__status--<?php echo esc_attr( (string) $reg['status'] ); ?>"><?php echo esc_html( (string) $reg['status_label'] ); ?></span>
			</div>

			<h1 class="liw-advdetail__title"><?php echo esc_html( (string) $vm['title'] ); ?></h1>

			<dl class="liw-advdetail__meta">
				<?php if ( '' !== (string) $vm['words'] ) : ?>
					<dt><?php echo esc_html__( 'Ort (three words)', 'liebherr-interface-world' ); ?></dt><dd><code>/// <?php echo esc_html( (string) $vm['words'] ); ?></code><?php echo '' !== (string) $vm['region'] ? ' · ' . esc_html( (string) $vm['region'] ) : ''; ?></dd>
				<?php endif; ?>
				<?php if ( '' !== (string) ( $vm['machine'] ?? '' ) || '' !== (string) ( $vm['component'] ?? '' ) ) : ?>
					<dt><?php echo esc_html__( 'Maschine / Bauteil', 'liebherr-interface-world' ); ?></dt>
					<dd><?php echo esc_html( trim( (string) ( $vm['machine'] ?? '' ) . ' · ' . (string) ( $vm['component'] ?? '' ), ' ·' ) ); ?></dd>
				<?php endif; ?>
				<dt><?php echo esc_html__( 'Tokenwert', 'liebherr-interface-world' ); ?></dt>
				<dd><?php echo $is_author ? esc_html__( 'kostenfrei (eigener Beitrag)', 'liebherr-interface-world' ) : esc_html( $token . ' ' . __( 'Tokens', 'liebherr-interface-world' ) ); ?><?php echo '' !== (string) $reg['usage_scope'] ? ' · ' . esc_html( RegistrationService::usage_label( (string) $reg['usage_scope'] ) ) : ''; ?></dd>
				<?php if ( '' !== (string) $reg['articlebook_ref'] ) : ?>
					<dt><?php echo esc_html__( 'Artikelbook', 'liebherr-interface-world' ); ?></dt>
					<dd><?php echo '' !== (string) $reg['articlebook_url'] ? '<a href="' . esc_url( (string) $reg['articlebook_url'] ) . '" target="_blank" rel="noopener">' . esc_html( (string) $reg['articlebook_ref'] ) . '</a>' : esc_html( (string) $reg['articlebook_ref'] ); ?></dd>
				<?php endif; ?>
			</dl>

			<?php if ( '' !== (string) $vm['image'] ) : ?>
				<img class="liw-advdetail__media" src="<?php echo esc_url( (string) $vm['image'] ); ?>" alt="" />
			<?php endif; ?>

			<?php if ( $gated ) : ?>
				<div class="liw-advdetail__gate" data-liw-adv-detail-gate="<?php echo esc_attr( (string) $id ); ?>">
					<p><?php echo esc_html__( 'Dieser Beitrag ist kostenpflichtig. Nach Bestätigung des Tokenwerts wird der Inhalt angezeigt.', 'liebherr-interface-world' ); ?></p>
					<button type="button" class="liw-cta liw-cta--primary" data-liw-adv-open="<?php echo esc_attr( (string) $id ); ?>"><?php echo esc_html( sprintf( /* translators: %d token count */ __( 'Zugriff bestätigen · %d Tokens', 'liebherr-interface-world' ), $token ) ); ?></button>
				</div>
			<?php else : ?>
				<div class="liw-advdetail__content">
					<?php
					// Voller Inhalt erst hier (nach Freigabe/Autor/kostenlos), nicht vorab im DOM.
					echo wp_kses_post( wpautop( (string) $post->post_content ) );
					?>
				</div>
			<?php endif; ?>
		</article>
		<?php
		return (string) ob_get_clean();
	}
}
