<?php
/**
 * Liebherr Interface Solutions – Admin-Paginierung (gemeinsame Ansicht)
 *
 * Feinschliff (alpha.10): Media Board und Onboarding Board luden ihre Listen bisher
 * ungebremst (Media Board: hart auf 50 neueste Anhänge begrenzt, kein Blättern; Onboarding
 * Board: kompletter JOIN ohne LIMIT). Diese Klasse kapselt das gemeinsame Pagination-Markup
 * (WordPress' `paginate_links()`), damit beide Boards dieselbe, geprüfte Umsetzung nutzen
 * statt Code zu duplizieren (DRY, CLAUDE.md Abschnitt 5).
 *
 * @package Liebherr\InterfaceWorld\Admin
 * @since   0.1.0-alpha.10
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AdminPagination {

	/** Liest die aktuelle Seite aus `$_GET['paged']` (reine Navigation, keine Datenänderung – keine Nonce nötig). */
	public static function current_page(): int {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reine Seitennavigation, keine Datenänderung.
		$paged = isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 1;
		return max( 1, $paged );
	}

	public static function render( int $current_page, int $total_items, int $per_page ): void {
		$total_pages = (int) ceil( $total_items / max( 1, $per_page ) );
		if ( $total_pages <= 1 ) {
			return;
		}

		$base_url = remove_query_arg( 'paged' );
		$links    = paginate_links(
			[
				'base'      => add_query_arg( 'paged', '%#%', $base_url ),
				'format'    => '',
				'current'   => $current_page,
				'total'     => $total_pages,
				'prev_text' => __( '« Zurück', 'liebherr-interface-world' ),
				'next_text' => __( 'Weiter »', 'liebherr-interface-world' ),
			]
		);

		if ( $links ) {
			echo '<div class="liw-pagination tablenav"><div class="tablenav-pages">' . $links . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- paginate_links() liefert bereits escaptes Markup.
		}
	}
}
