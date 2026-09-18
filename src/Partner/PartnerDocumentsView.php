<?php
/**
 * Liebherr Interface Solutions – Partnerdokumente (Frontend + geschützter Download)
 *
 * Shortcode `[liw_partner_documents]`: zeigt angemeldeten Benutzern mit `liw_partner_access`
 * die aktiven Partnerdokumente mit Download-Knopf; Besucher ohne Login sehen nur einen
 * Login-Link, angemeldete Benutzer ohne Recht einen Hinweis. Download über admin-post
 * (`liw_partner_document_download`, nur für angemeldete Benutzer – kein nopriv-Hook),
 * Prüfkette: eingeloggt → Capability → Nonce → aktives Dokument → Datei im gesperrten
 * Verzeichnis (realpath-Guard) → Streaming mit Content-Disposition, Audit `download`.
 *
 * Kein Inline-CSS/JS; Klassen `.liw-partner-docs*` in assets/css/liebherr-frontend.css.
 *
 * @package Liebherr\InterfaceWorld\Partner
 * @since   0.1.0-alpha.22
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Partner;

use Liebherr\InterfaceWorld\CoreBridge\RoleBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class PartnerDocumentsView {

	public const SHORTCODE  = 'liw_partner_documents';
	public const POST_ACTION = 'liw_partner_document_download';

	private const NONCE_ACTION = 'liw_partner_document_download';
	private const NONCE_NAME   = 'liw_pd_nonce';

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'render_shortcode' ] );
		add_action( 'admin_post_' . self::POST_ACTION, [ self::class, 'handle_download' ] ); // bewusst KEIN admin_post_nopriv_.
		add_filter( 'show_admin_bar', [ self::class, 'hide_admin_bar_for_partners' ] );
	}

	/** Partner ohne weitere Rollen brauchen keine WP-Admin-Leiste (reine Frontend-Nutzer). */
	public static function hide_admin_bar_for_partners( bool $show ): bool {
		$user = wp_get_current_user();
		if ( $user instanceof \WP_User && $user->exists() && [ RoleBridge::ROLE_PARTNER ] === array_values( (array) $user->roles ) ) {
			return false;
		}
		return $show;
	}

	public static function render_shortcode(): string {
		if ( ! is_user_logged_in() ) {
			return '<div class="liw-partner-docs liw-partner-docs--login"><p>' .
				esc_html__( 'Dieser Bereich ist freigegebenen Partnern vorbehalten.', 'liebherr-interface-world' ) . ' ' .
				sprintf( '<a href="%s">%s</a>', esc_url( wp_login_url( self::current_url() ) ), esc_html__( 'Zum Partner-Login', 'liebherr-interface-world' ) ) .
				'</p></div>';
		}
		if ( ! current_user_can( RoleBridge::CAP_PARTNER_ACCESS ) ) {
			return '<div class="liw-partner-docs liw-partner-docs--denied"><p>' .
				esc_html__( 'Ihr Konto ist für den Partnerbereich nicht freigegeben. Bitte wenden Sie sich an Ihre Ansprechperson bei Interface World Connections.', 'liebherr-interface-world' ) .
				'</p></div>';
		}

		$docs = PartnerDocumentService::get_active();
		if ( [] === $docs ) {
			return '<div class="liw-partner-docs liw-partner-docs--empty"><p>' . esc_html__( 'Derzeit sind keine Dokumente bereitgestellt.', 'liebherr-interface-world' ) . '</p></div>';
		}

		ob_start();
		?>
		<ul class="liw-partner-docs">
			<?php foreach ( $docs as $doc ) : ?>
				<li class="liw-partner-docs__item">
					<div class="liw-partner-docs__meta">
						<strong class="liw-partner-docs__title"><?php echo esc_html( (string) $doc['title'] ); ?></strong>
						<?php if ( ! empty( $doc['description'] ) ) : ?>
							<p class="liw-partner-docs__description"><?php echo esc_html( (string) $doc['description'] ); ?></p>
						<?php endif; ?>
						<span class="liw-partner-docs__file"><?php echo esc_html( (string) $doc['original_name'] ); ?> · <?php echo esc_html( size_format( (int) $doc['size_bytes'] ) ); ?> · <?php echo esc_html( mysql2date( get_option( 'date_format' ), (string) $doc['created_at'] ) ); ?></span>
					</div>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="liw-partner-docs__form">
						<input type="hidden" name="action" value="<?php echo esc_attr( self::POST_ACTION ); ?>" />
						<input type="hidden" name="document_id" value="<?php echo esc_attr( (string) $doc['id'] ); ?>" />
						<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
						<button type="submit"><?php esc_html_e( 'Herunterladen', 'liebherr-interface-world' ); ?></button>
					</form>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
		return (string) ob_get_clean();
	}

	public static function handle_download(): void {
		if ( ! is_user_logged_in() || ! current_user_can( RoleBridge::CAP_PARTNER_ACCESS ) ) {
			wp_die( esc_html__( 'Kein Zugriff auf Partnerdokumente.', 'liebherr-interface-world' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$id  = absint( wp_unslash( $_POST['document_id'] ?? 0 ) );
		$row = PartnerDocumentService::get( $id );
		$path = null !== $row ? PartnerDocumentService::file_path( $row ) : null;
		if ( null === $row || null === $path ) {
			wp_die( esc_html__( 'Dokument nicht gefunden.', 'liebherr-interface-world' ), '', [ 'response' => 404 ] );
		}

		PartnerDocumentService::log_download( $id, get_current_user_id() );

		nocache_headers();
		header( 'Content-Type: ' . $row['mime_type'] );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( (string) $row['original_name'] ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- kontrolliertes Streaming aus gesperrtem Verzeichnis.
		exit;
	}

	private static function current_url(): string {
		return home_url( add_query_arg( null, null ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- liest nur REQUEST_URI.
	}
}
