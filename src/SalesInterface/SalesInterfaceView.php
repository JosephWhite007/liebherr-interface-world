<?php
/**
 * Liebherr Interface Solutions – Sales Interface: Frontend-Ansicht (Shortcode `[liw_sales_interface]`).
 *
 * Geschützte Unterseite hinter der Process-World-Karte „Sales" (ADR-LIW-SALES-001): die Schnittstellen-
 * beschreibung der Liebherr Dealer Interface API in acht Reitern (Anker auf einer Seite, ohne JavaScript;
 * Anleitung zuletzt). Inhalt aus {@see SalesContent}. Serverseitig geschützt: ohne Freigabe
 * ({@see GateBridge::is_unlocked()}) erscheint kein technischer Inhalt (Sichtbarkeit ist keine Autorisierung).
 * Die Seite ist `noindex` und aus der WP-Sitemap ausgeschlossen. Gestaltung nur über `--brand-*`-Tokens.
 *
 * @package Liebherr\InterfaceWorld\SalesInterface
 * @since   0.1.0-alpha.146
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\SalesInterface;

use Liebherr\InterfaceWorld\Content\SitePages;
use Liebherr\InterfaceWorld\CoreBridge\GateBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SalesInterfaceView {

	public const SHORTCODE   = 'liw_sales_interface';
	public const OPTION_PAGE = 'liw_sales_page_id';

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'render' ] );
		add_filter( 'wp_robots', [ self::class, 'robots' ] );
		add_filter( 'wp_sitemaps_posts_query_args', [ self::class, 'exclude_from_sitemap' ], 10, 2 );
	}

	/** @param array<string,mixed> $robots */
	public static function robots( array $robots ): array {
		$post = get_post();
		if ( $post instanceof \WP_Post && has_shortcode( (string) $post->post_content, self::SHORTCODE ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
			unset( $robots['index'], $robots['follow'] );
		}
		return $robots;
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>
	 */
	public static function exclude_from_sitemap( array $args, string $post_type ): array {
		$page_id = (int) get_option( self::OPTION_PAGE, 0 );
		if ( 'page' === $post_type && $page_id > 0 ) {
			$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? [] ), [ $page_id ] );
		}
		return $args;
	}

	/** @return array<string,string> Reiter-Anker → Beschriftung (Reihenfolge = Anzeige; Anleitung zuletzt). */
	public static function tabs(): array {
		return [
			'overview'     => __( 'Übersicht', 'liebherr-interface-world' ),
			'environments' => __( 'Umgebungen & Zugang', 'liebherr-interface-world' ),
			'endpoints'    => __( 'Endpunkte', 'liebherr-interface-world' ),
			'datamodel'    => __( 'Datenmodell', 'liebherr-interface-world' ),
			'codelists'    => __( 'Codelisten', 'liebherr-interface-world' ),
			'sandbox'      => __( 'Sandbox', 'liebherr-interface-world' ),
			'roadmap'      => __( 'Roadmap & Klärungspunkte', 'liebherr-interface-world' ),
			'guide'        => __( 'Anleitung', 'liebherr-interface-world' ),
		];
	}

	/** @return array<string,string> */
	public static function status_labels(): array {
		return [
			'draft'         => __( 'Entwurf', 'liebherr-interface-world' ),
			'in_simulation' => __( 'In Simulation', 'liebherr-interface-world' ),
			'verified'      => __( 'Verifiziert', 'liebherr-interface-world' ),
			'approved'      => __( 'Freigegeben', 'liebherr-interface-world' ),
			'planned'       => __( 'Geplant', 'liebherr-interface-world' ),
			'in_progress'   => __( 'In Arbeit', 'liebherr-interface-world' ),
			'done'          => __( 'Erledigt', 'liebherr-interface-world' ),
		];
	}

	public static function render(): string {
		if ( ! GateBridge::is_unlocked() ) {
			return '<div class="liw-sales"><p class="liw-sales__locked" role="status">'
				. esc_html__( 'Dieser Bereich ist nur mit Zugangscode erreichbar.', 'liebherr-interface-world' ) . '</p></div>';
		}

		$c    = SalesContent::get();
		$back = SitePages::interface_url();

		$out  = '<div class="liw-sales" id="liw-sales">';
		$out .= '<header class="liw-sales__head">';
		if ( '' !== $back ) {
			$out .= '<a class="liw-sales__back" href="' . esc_url( $back ) . '">← ' . esc_html__( 'Process Worlds', 'liebherr-interface-world' ) . '</a>';
		}
		$out .= '<h1 class="liw-sales__title">' . esc_html( (string) $c['intro']['title'] ) . '</h1>';
		$out .= '<p class="liw-sales__lead">' . esc_html( (string) $c['intro']['lead'] ) . '</p>';
		$out .= '</header>';

		$out .= '<nav class="liw-sales__nav" aria-label="' . esc_attr__( 'Reiter Sales Interface', 'liebherr-interface-world' ) . '"><ul>';
		foreach ( self::tabs() as $anchor => $label ) {
			$out .= '<li><a href="#liw-sales-' . esc_attr( $anchor ) . '">' . esc_html( $label ) . '</a></li>';
		}
		$out .= '</ul></nav>';

		$tabs = self::tabs();
		$out .= self::section( 'overview', $tabs, self::overview( $c ) );
		$out .= self::section( 'environments', $tabs, self::environments( $c ) );
		$out .= self::section( 'endpoints', $tabs, self::endpoints( $c ) );
		$out .= self::section( 'datamodel', $tabs, self::datamodel( $c ) );
		$out .= self::section( 'codelists', $tabs, self::codelists( $c ) );
		$out .= self::section( 'sandbox', $tabs, self::sandbox() );
		$out .= self::section( 'roadmap', $tabs, self::roadmap( $c ) );
		$out .= self::section( 'guide', $tabs, self::guide( $c ) );
		return $out . '</div>';
	}

	/** @param array<string,string> $tabs */
	private static function section( string $anchor, array $tabs, string $body ): string {
		return '<section class="liw-sales__section" id="liw-sales-' . esc_attr( $anchor ) . '" aria-labelledby="liw-sales-h-' . esc_attr( $anchor ) . '">'
			. '<h2 class="liw-sales__h2" id="liw-sales-h-' . esc_attr( $anchor ) . '">' . esc_html( $tabs[ $anchor ] ) . '</h2>' . $body . '</section>';
	}

	/** @param array<string,mixed> $c */
	private static function overview( array $c ): string {
		$counts = [];
		foreach ( $c['endpoints'] as $ep ) {
			$counts[ $ep['group'] ] = ( $counts[ $ep['group'] ] ?? 0 ) + 1;
		}
		$out  = '<p class="liw-sales__text">' . esc_html( (string) $c['intro']['lead'] ) . '</p><ul class="liw-sales__cards" role="list">';
		foreach ( $c['groups'] as $g ) {
			$out .= '<li class="liw-sales__card"><h3 class="liw-sales__h3">' . esc_html( $g['label'] ) . '</h3><p>' . esc_html( $g['text'] ) . '</p>'
				. '<p class="liw-sales__meta">' . esc_html( sprintf( /* translators: %d: Anzahl Endpunkte */ _n( '%d Endpunkt', '%d Endpunkte', (int) ( $counts[ $g['key'] ] ?? 0 ), 'liebherr-interface-world' ), (int) ( $counts[ $g['key'] ] ?? 0 ) ) ) . '</p></li>';
		}
		$out .= '</ul>';
		$out .= '<p class="liw-sales__meta">' . esc_html( sprintf( /* translators: 1: Anzahl Endpunkte */ __( 'Gesamt: %1$d Endpunkte. Status-Legende siehe Anleitung.', 'liebherr-interface-world' ), count( $c['endpoints'] ) ) ) . '</p>';
		return $out;
	}

	/** @param array<string,mixed> $c */
	private static function environments( array $c ): string {
		$out = '<div class="liw-sales__scroll"><table class="liw-sales__table"><thead><tr><th scope="col">' . esc_html__( 'Umgebung', 'liebherr-interface-world' )
			. '</th><th scope="col">' . esc_html__( 'Basis-URL', 'liebherr-interface-world' ) . '</th><th scope="col">' . esc_html__( 'Hinweis', 'liebherr-interface-world' ) . '</th></tr></thead><tbody>';
		foreach ( $c['environments'] as $e ) {
			$out .= '<tr><th scope="row">' . esc_html( $e['label'] ) . '</th><td><code>' . esc_html( $e['url'] ) . '</code></td><td>' . esc_html( $e['note'] ) . '</td></tr>';
		}
		$out .= '</tbody></table></div>';
		$out .= '<h3 class="liw-sales__h3">' . esc_html__( 'Authentifizierung', 'liebherr-interface-world' ) . '</h3><p class="liw-sales__text">' . esc_html( (string) $c['auth'] ) . '</p>';
		$out .= '<div class="liw-sales__scroll"><table class="liw-sales__table"><thead><tr><th scope="col">' . esc_html__( 'Typ', 'liebherr-interface-world' ) . '</th><th scope="col">' . esc_html__( 'Name', 'liebherr-interface-world' ) . '</th><th scope="col">' . esc_html__( 'Ort', 'liebherr-interface-world' ) . '</th></tr></thead><tbody>'
			. '<tr><td>Subscription-Key</td><td><code>Ocp-Apim-Subscription-Key</code></td><td>HTTP-Header</td></tr>'
			. '<tr><td>Subscription-Key</td><td><code>subscription-key</code></td><td>' . esc_html__( 'URL-Parameter', 'liebherr-interface-world' ) . '</td></tr>'
			. '<tr><td>' . esc_html__( 'Benutzer', 'liebherr-interface-world' ) . '</td><td><code>UserEmail</code></td><td>HTTP-Header</td></tr>'
			. '</tbody></table></div>';
		return $out;
	}

	/** @param array<string,mixed> $c */
	private static function endpoints( array $c ): string {
		$labels = self::status_labels();
		$out    = '';
		foreach ( $c['groups'] as $g ) {
			$rows = '';
			foreach ( $c['endpoints'] as $ep ) {
				if ( $ep['group'] !== $g['key'] ) {
					continue;
				}
				$rows .= '<tr><td><span class="liw-sales__method liw-sales__method--' . esc_attr( strtolower( $ep['method'] ) ) . '">' . esc_html( $ep['method'] ) . '</span></td>'
					. '<td><code>' . esc_html( $ep['path'] ) . '</code></td><td>' . esc_html( $ep['summary'] ) . '</td><td>' . esc_html( $ep['params'] ) . '</td>'
					. '<td><span class="liw-sales__status liw-sales__status--' . esc_attr( $ep['status'] ) . '">' . esc_html( $labels[ $ep['status'] ] ?? $ep['status'] ) . '</span></td></tr>';
			}
			if ( '' === $rows ) {
				continue;
			}
			$out .= '<h3 class="liw-sales__h3">' . esc_html( $g['label'] ) . '</h3><div class="liw-sales__scroll"><table class="liw-sales__table"><thead><tr>'
				. '<th scope="col">' . esc_html__( 'Methode', 'liebherr-interface-world' ) . '</th><th scope="col">' . esc_html__( 'Pfad', 'liebherr-interface-world' ) . '</th>'
				. '<th scope="col">' . esc_html__( 'Zweck', 'liebherr-interface-world' ) . '</th><th scope="col">' . esc_html__( 'Parameter', 'liebherr-interface-world' ) . '</th>'
				. '<th scope="col">' . esc_html__( 'Status', 'liebherr-interface-world' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
		}
		return $out;
	}

	/** @param array<string,mixed> $c */
	private static function datamodel( array $c ): string {
		$out = '';
		foreach ( $c['schemas'] as $s ) {
			$out .= '<h3 class="liw-sales__h3">' . esc_html( $s['name'] ) . '</h3>';
			if ( '' !== $s['required'] ) {
				$out .= '<p class="liw-sales__meta"><strong>' . esc_html__( 'Pflichtfelder', 'liebherr-interface-world' ) . ':</strong> ' . esc_html( $s['required'] ) . '</p>';
			}
			if ( ! str_contains( $s['fields'], ':' ) ) {
				$out .= '<p class="liw-sales__text">' . esc_html( $s['fields'] ) . '</p>';
				continue;
			}
			$required = array_map( 'trim', explode( ',', $s['required'] ) );
			$rows     = '';
			foreach ( explode( ';', $s['fields'] ) as $pair ) {
				$bits = array_map( 'trim', explode( ':', $pair, 2 ) );
				if ( '' === $bits[0] ) {
					continue;
				}
				$rows .= '<tr><th scope="row"><code>' . esc_html( $bits[0] ) . '</code></th><td>' . esc_html( $bits[1] ?? '' ) . '</td><td>'
					. ( in_array( $bits[0], $required, true ) ? esc_html__( 'Ja', 'liebherr-interface-world' ) : '' ) . '</td></tr>';
			}
			$out .= '<div class="liw-sales__scroll"><table class="liw-sales__table"><thead><tr><th scope="col">' . esc_html__( 'Feld', 'liebherr-interface-world' )
				. '</th><th scope="col">' . esc_html__( 'Typ', 'liebherr-interface-world' ) . '</th><th scope="col">' . esc_html__( 'Pflicht', 'liebherr-interface-world' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table></div>';
		}
		return $out;
	}

	/** @param array<string,mixed> $c */
	private static function codelists( array $c ): string {
		$out = '<p class="liw-sales__text">' . esc_html__( 'Felder wie Bundesland, Branche oder Klassifizierung verweisen auf interne Codelisten von Liebherr. Die Anzeigetexte liefert GET /tab-text je Sprache; die Auswahlliste umfasst acht Sprachen (0 Deutsch, 1 Französisch, 2 Englisch, 3 Italienisch, 4 Spanisch, 5 Russisch, 6 Niederländisch, 7 Chinesisch). Import und Suche der Listeninhalte folgen in Stufe 1b.', 'liebherr-interface-world' ) . '</p>';
		$out .= '<div class="liw-sales__scroll"><table class="liw-sales__table"><thead><tr><th scope="col">' . esc_html__( 'Codeliste', 'liebherr-interface-world' ) . '</th><th scope="col">'
			. esc_html__( 'Bezeichnung', 'liebherr-interface-world' ) . '</th><th scope="col">' . esc_html__( 'Entität', 'liebherr-interface-world' ) . '</th></tr></thead><tbody>';
		foreach ( $c['codelists'] as $l ) {
			$out .= '<tr><th scope="row"><code>' . esc_html( $l['code'] ) . '</code></th><td>' . esc_html( $l['name'] ) . '</td><td>' . esc_html( $l['entity'] ) . '</td></tr>';
		}
		return $out . '</tbody></table></div>';
	}

	private static function sandbox(): string {
		$out   = '<p class="liw-sales__text">' . esc_html__( 'Testverbindung zur Liebherr-Testumgebung (nur lesend, Stufe 1c). Aufrufe werden vom Entwicklerteam ausgelöst; hier steht der Stand des letzten Aufrufs – ohne Nutzdaten.', 'liebherr-interface-world' ) . '</p>';
		$probe = DealerApiClient::last_probe();
		$out  .= '<dl class="liw-sales__dl"><dt>' . esc_html__( 'Umgebung', 'liebherr-interface-world' ) . '</dt><dd><code>' . esc_html( DealerApiClient::BASE_TEST ) . '</code></dd>';
		$out  .= '<dt>' . esc_html__( 'Letzter Aufruf', 'liebherr-interface-world' ) . '</dt><dd>' . esc_html( \Liebherr\InterfaceWorld\Admin\Pages\SalesBoardPage::probe_label( $probe ) ) . '</dd></dl>';
		$out  .= '<p class="liw-sales__meta">' . esc_html__( 'Synthetische Beispielantworten (Sandbox-Simulation) folgen in Stufe 1b.', 'liebherr-interface-world' ) . '</p>';
		return $out;
	}

	/** @param array<string,mixed> $c */
	private static function roadmap( array $c ): string {
		$labels = self::status_labels();
		$out    = '<h3 class="liw-sales__h3">' . esc_html__( 'Stufenplan', 'liebherr-interface-world' ) . '</h3><ol class="liw-sales__steps">';
		foreach ( $c['roadmap'] as $r ) {
			$out .= '<li><strong>' . esc_html( $r['title'] ) . '</strong> <span class="liw-sales__status liw-sales__status--' . esc_attr( $r['status'] ) . '">' . esc_html( $labels[ $r['status'] ] ?? $r['status'] ) . '</span><br>' . esc_html( $r['text'] ) . '</li>';
		}
		$out .= '</ol><h3 class="liw-sales__h3">' . esc_html__( 'Klärungspunkte', 'liebherr-interface-world' ) . '</h3><dl class="liw-sales__dl">';
		foreach ( $c['clarifications'] as $k ) {
			$out .= '<dt>' . esc_html( $k['title'] ) . '</dt><dd>' . esc_html( $k['text'] ) . '</dd>';
		}
		return $out . '</dl>';
	}

	/** @param array<string,mixed> $c */
	private static function guide( array $c ): string {
		$out = '<dl class="liw-sales__dl">';
		foreach ( $c['guide'] as $g ) {
			$out .= '<dt>' . esc_html( $g['title'] ) . '</dt><dd>' . esc_html( $g['text'] ) . '</dd>';
		}
		return $out . '</dl>';
	}
}
