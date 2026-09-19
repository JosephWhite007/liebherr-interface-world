<?php
/**
 * Liebherr Interface Solutions – Medien-Picker auf freigegebene Bibliothek beschränken (Backlog A11, CI-005).
 *
 * Beschränkt den WordPress-Medien-Modal auf freigegebene Anhänge (`_liw_media_approved = 1`), aber NUR wenn
 * die Abfrage das Flag `liw_approved_only` trägt. Das Flag setzt ein kleines, SEITENGEBUNDEN eingebundenes
 * Skript ausschließlich auf den Liebherr-Backoffice-Board-Seiten (Seitenslug beginnt mit `liw-`). Damit bleibt
 * die globale Mediathek unangetastet, und die Beschränkung greift zuverlässig dort, wo CI-005 gilt – ohne das
 * unzuverlässige Raten am `post_id`-Kontext (bewusst vermieden, siehe To-Do).
 *
 * `restrict()` ist rein und ohne WordPress unit-testbar.
 *
 * @package Liebherr\InterfaceWorld\Admin
 * @since   0.1.0-alpha.72
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin;

use Liebherr\InterfaceWorld\CoreBridge\MediaBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ApprovedMediaFilter {

	public static function register(): void {
		add_filter( 'ajax_query_attachments_args', [ self::class, 'maybe_restrict' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	/**
	 * Ergänzt eine meta_query auf „freigegeben" (rein/testbar).
	 *
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	public static function restrict( array $args ): array {
		$approved = [ 'key' => MediaBridge::META_APPROVED, 'value' => '1', 'compare' => '=' ];
		if ( empty( $args['meta_query'] ) || ! is_array( $args['meta_query'] ) ) {
			$args['meta_query'] = [ $approved ];
		} else {
			$args['meta_query'] = array_merge( [ 'relation' => 'AND' ], [ $args['meta_query'], [ $approved ] ] );
		}
		return $args;
	}

	/**
	 * Filter-Callback: beschränkt nur, wenn die Abfrage `liw_approved_only` trägt.
	 *
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	public static function maybe_restrict( array $args ): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reine Lese-Flag-Prüfung; Ergebnis nur restriktiver.
		$flag = $_REQUEST['query']['liw_approved_only'] ?? ( $args['liw_approved_only'] ?? '' );
		if ( ! empty( $flag ) ) {
			$args = self::restrict( $args );
		}
		unset( $args['liw_approved_only'] );
		return $args;
	}

	/** Ist gerade eine Liebherr-Backoffice-Board-Seite offen? (Seitenslug beginnt mit `liw-`.) */
	public static function is_liw_admin_page(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reiner Seiten-Check.
		$page = isset( $_GET['page'] ) ? sanitize_key( (string) $_GET['page'] ) : '';
		return '' !== $page && 0 === strpos( $page, 'liw-' );
	}

	/** Auf LIW-Board-Seiten: Medien-Abfragen auf freigegebene Medien beschränken (Flag setzen). */
	public static function enqueue( string $hook ): void {
		if ( ! self::is_liw_admin_page() ) {
			return;
		}
		$js = "(function(){if(!window.wp||!wp.media||!wp.media.query){return;}"
			. "var orig=wp.media.query;wp.media.query=function(props){props=props||{};props.liw_approved_only=1;return orig(props);};})();";
		wp_add_inline_script( 'media-views', $js );
	}
}
