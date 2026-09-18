<?php
/**
 * Liebherr Interface Solutions – SEO Bridge
 *
 * ABWEICHUNG von der ursprünglichen Annahme (Phase-3-Plan): `Modules\I18nSeo\I18nRouter`
 * wurde vor der Implementierung geprüft und ist NICHT generisch – der Router ist fest auf
 * `POST_TYPES = ['suite','apartment','treeroom']` verdrahtet (araliya-platform-core,
 * src/Modules/I18nSeo/I18nRouter.php). Eine Bindung an `liw_section` hätte eine Änderung an
 * dieser Kernkomponente des Core-Plugins erfordert – das ist außerhalb des Liebherr-Moduls und
 * laut CLAUDE.md Abschnitt 30 nur mit ausdrücklicher Begründung/Freigabe zulässig (Kategorie A:
 * Eingriff in eine öffentliche Core-Komponente). Es wurde NICHT eigenmächtig geändert.
 *
 * Stattdessen: eigene, minimale hreflang-/Canonical-Ausgabe für die Liebherr-Routen über
 * Standard-WP-Hooks (`wp_head`) – kein Nachbau eines Sprachsystems, nur Meta-Tag-Ausgabe auf
 * Basis der ohnehin über die Translation Registry gepflegten aktiven Sprachen.
 *
 * ENTSCHEIDUNG 18.09.2026 (I18nSeo-Analyse, Option A, LOGBUCH_TECHNIK alpha.24): Die Core-
 * Sprachsteuerung (Cookie/`?lang=`) greift bereits überall; der Core-Router deckt Seiten (`page`)
 * ohnehin nicht ab, ein Ergänzen von `liw_section` allein hätte die Trägerseite nicht erfasst.
 * Daher bleibt diese schlanke Lösung, neu ausgerichtet auf die Trägerseite, und schweigt sobald
 * der Core-Router aktiv ist. Option B (Router um `page` + `liw_section` erweitern) bleibt als
 * Folgepunkt, wenn der Router plattformweit aktiv geschaltet wird (docs/LIW_TODO.md).
 *
 * @package Liebherr\InterfaceWorld\CoreBridge
 * @since   0.1.0-alpha.1
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\CoreBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SeoBridge {

	/** Shortcodes, die eine Seite zur öffentlichen LIW-Fläche machen (Träger der Landingpage bzw. Hauptseite). */
	private const CARRIER_SHORTCODES = [ 'liw_landingpage', 'liw_local_intelligence' ];

	public static function register(): void {
		add_action( 'wp_head', [ self::class, 'render_robots' ], 4 );
		add_action( 'wp_head', [ self::class, 'render_hreflang' ], 5 );
		add_action( 'wp_head', [ self::class, 'render_open_graph' ], 6 );
		add_filter( 'get_canonical_url', [ self::class, 'filter_canonical' ], 10, 2 );
		add_filter( 'wp_sitemaps_post_types', [ self::class, 'filter_sitemap_post_types' ] );
		add_filter( 'wp_sitemaps_posts_query_args', [ self::class, 'filter_sitemap_page_args' ], 10, 2 );
	}

	/**
	 * Prototyp-Konformität (LI-Pflichtenheft §9.2/§12.7): Solange keine offizielle Liebherr-Freigabe
	 * vorliegt, dürfen die (mit vorläufiger CI versehenen) LIW-Seiten nicht indexiert werden. Gibt auf
	 * den öffentlichen LIW-Flächen `noindex,follow` aus. Umschaltbar über Option `liw_public_release`
	 * (Standard: nicht freigegeben) bzw. Filter `liw_allow_indexing`.
	 */
	public static function render_robots(): void {
		if ( ! self::is_liw_public_view() || self::indexing_allowed() ) {
			return;
		}
		echo '<meta name="robots" content="noindex,follow" />' . "\n";
	}

	/** Indexierung erlaubt? Erst nach dokumentierter Freigabe (Option/Filter). */
	public static function indexing_allowed(): bool {
		$allow = (bool) get_option( 'liw_public_release', false );
		return (bool) apply_filters( 'liw_allow_indexing', $allow );
	}

	/**
	 * Prototyp nicht in die XML-Sitemap (§12.7): schließt Haupt- und Interface-Seite aus der
	 * `page`-Sitemap aus, solange nicht freigegeben.
	 *
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	public static function filter_sitemap_page_args( array $args, string $post_type ): array {
		if ( 'page' !== $post_type || self::indexing_allowed() ) {
			return $args;
		}
		$ids = array_values( array_filter( [
			\Liebherr\InterfaceWorld\Content\SitePages::li_id(),
			\Liebherr\InterfaceWorld\Content\SitePages::interface_id(),
		] ) );
		if ( [] !== $ids ) {
			$existing = isset( $args['post__not_in'] ) && is_array( $args['post__not_in'] ) ? $args['post__not_in'] : [];
			$args['post__not_in'] = array_merge( $existing, $ids );
		}
		return $args;
	}

	/**
	 * Canonical je Sprache (§21): hängt bei aktiver Nicht-Standardsprache `?lang=xx` an die von
	 * WordPress erzeugte Canonical-URL an – kein zweites Canonical-Tag (Doppelungen vermeiden).
	 * Schweigt bei aktivem Core-Router (der erzeugt eigene Präfix-URLs).
	 */
	public static function filter_canonical( string $canonical_url, \WP_Post $post ): string {
		if ( LanguageBridge::core_router_active() || ! self::is_liw_post( $post ) ) {
			return $canonical_url;
		}
		$lang = LanguageBridge::current_lang();
		if ( '' === $lang || 'de' === $lang ) {
			return $canonical_url; // Standardsprache = Basis-URL.
		}
		return add_query_arg( 'lang', $lang, $canonical_url );
	}

	/** Open-Graph-Tags je Sprache (§21): Titel/URL/Locale, optional Bild – nur auf LIW-Flächen. */
	public static function render_open_graph(): void {
		if ( LanguageBridge::core_router_active() || ! self::is_liw_public_view() ) {
			return;
		}
		echo self::open_graph_markup( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in open_graph_markup() escaped.
			wp_strip_all_tags( (string) wp_get_document_title() ),
			(string) get_permalink(),
			LanguageBridge::current_lang(),
			self::og_image_url()
		);
	}

	/**
	 * Reine Markup-Erzeugung (testbar): og:type/title/url/locale und optional og:image.
	 */
	public static function open_graph_markup( string $title, string $url, string $lang, string $image_url = '' ): string {
		$out  = sprintf( '<meta property="og:type" content="website" />' . "\n" );
		$out .= sprintf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $title ) );
		$out .= sprintf( '<meta property="og:url" content="%s" />' . "\n", esc_url( '' !== $lang && 'de' !== $lang ? add_query_arg( 'lang', $lang, $url ) : $url ) );
		if ( '' !== $lang ) {
			$out .= sprintf( '<meta property="og:locale" content="%s" />' . "\n", esc_attr( $lang ) );
		}
		if ( '' !== $image_url ) {
			$out .= sprintf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $image_url ) );
		}
		return $out;
	}

	/** OG-Bild = freigegebenes Hero-Bild (CI-005); leer, wenn keines gewählt/freigegeben. */
	private static function og_image_url(): string {
		$hero_id = (int) ( \Liebherr\InterfaceWorld\Settings\HeaderSettings::get()['hero_image_id'] ?? 0 );
		if ( $hero_id > 0 && MediaBridge::is_approved( $hero_id ) ) {
			$url = wp_get_attachment_image_url( $hero_id, 'full' );
			return is_string( $url ) ? $url : '';
		}
		return '';
	}

	/** Abschnitts-Fragmente nicht einzeln in die Sitemap (§21): kanonisch ist die Landingpage. */
	public static function filter_sitemap_post_types( array $post_types ): array {
		unset( $post_types['liw_section'] );
		return $post_types;
	}

	/** Gehört der Post zu den LIW-Flächen (Trägerseite mit Shortcode oder Abschnitt)? */
	public static function is_liw_post( \WP_Post $post ): bool {
		if ( 'liw_section' === $post->post_type ) {
			return true;
		}
		return 'page' === $post->post_type && self::has_carrier_shortcode( (string) $post->post_content );
	}

	/** Enthält der Inhalt einen der Träger-Shortcodes (Landingpage oder Local-Intelligence-Hauptseite)? */
	private static function has_carrier_shortcode( string $content ): bool {
		foreach ( self::CARRIER_SHORTCODES as $sc ) {
			if ( has_shortcode( $content, $sc ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Ziel seit alpha.24 (I18nSeo-Analyse, Option A): die öffentliche Fläche ist die Trägerseite mit
	 * `[liw_landingpage]` (plus Einzelansichten von `liw_section`). Schweigt, wenn der Core-I18nRouter
	 * aktiv ist – der liefert dann eigenes hreflang mit Sprach-Präfix-URLs (kein doppeltes Markup).
	 */
	public static function render_hreflang(): void {
		if ( LanguageBridge::core_router_active() || ! self::is_liw_public_view() ) {
			return;
		}

		$langs = LanguageBridge::active_langs();
		if ( count( $langs ) < 2 ) {
			return; // Nur ausgeben, wenn es tatsächlich mehr als eine Sprache gibt (kein Leerlärm).
		}

		echo self::hreflang_markup( (string) get_permalink(), $langs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in hreflang_markup() escaped.
	}

	/** Trägerseite (Seite mit Landingpage-Shortcode) oder Einzelansicht eines Abschnitts. */
	public static function is_liw_public_view(): bool {
		if ( is_singular( 'liw_section' ) ) {
			return true;
		}
		$post = get_post();
		return is_page() && $post instanceof \WP_Post && self::has_carrier_shortcode( (string) $post->post_content );
	}

	/**
	 * Reine Markup-Erzeugung (testbar ohne Request): je Sprache `?lang=xx`, x-default = Basis-URL.
	 *
	 * @param string[] $langs
	 */
	public static function hreflang_markup( string $base_url, array $langs ): string {
		$out = '';
		foreach ( $langs as $lang ) {
			$out .= sprintf( '<link rel="alternate" hreflang="%s" href="%s" />' . "\n", esc_attr( $lang ), esc_url( add_query_arg( 'lang', $lang, $base_url ) ) );
		}
		$out .= sprintf( '<link rel="alternate" hreflang="x-default" href="%s" />' . "\n", esc_url( $base_url ) );
		return $out;
	}
}
