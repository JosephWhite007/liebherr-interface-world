<?php
/**
 * Liebherr Interface Solutions – Interaktive Weltkarte „Connected World" (LP-06, §8/§16, alpha.37).
 *
 * Öffentlicher Shortcode `[liw_world_map]`: abstrahierte Weltkarte (eigene Inline-SVG, KEINE externe
 * Kartenbibliothek, keine echten Koordinaten) mit einer zentralen Liebherr-Zentrale und Regionen-
 * Knoten, die aus den freigegebenen Verbindungen (`ConnectionService::get_public()`) aggregiert werden.
 * Knoten sind per Tastatur/Screenreader erreichbar; darunter steht eine vollständige Text-Alternative
 * (§26). Farben ausschließlich über `--brand-*`. Kein Inline-JS (Verhalten in liebherr-frontend.js).
 *
 * @package Liebherr\InterfaceWorld\Frontend
 * @since   0.1.0-alpha.37
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Frontend;

use Liebherr\InterfaceWorld\Connection\ConnectionService;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class WorldMapView {

	public const SHORTCODE = 'liw_world_map';

	/** Kanonische Regionen: Label + Knotenposition (viewBox 0 0 1000 520). */
	private const REGIONS = [
		'north_america' => [ 'label' => 'Nordamerika', 'x' => 215, 'y' => 175 ],
		'south_america' => [ 'label' => 'Südamerika', 'x' => 320, 'y' => 380 ],
		'europe'        => [ 'label' => 'Europa', 'x' => 510, 'y' => 150 ],
		'africa'        => [ 'label' => 'Afrika', 'x' => 540, 'y' => 330 ],
		'middle_east'   => [ 'label' => 'Naher Osten', 'x' => 620, 'y' => 235 ],
		'asia_pacific'  => [ 'label' => 'Asien-Pazifik', 'x' => 800, 'y' => 240 ],
		'other'         => [ 'label' => 'Weitere', 'x' => 500, 'y' => 455 ],
	];

	/** Zentrale (Hub). */
	private const HUB = [ 'x' => 510, 'y' => 150 ];

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'render' ] );
	}

	public static function render(): string {
		$agg = self::aggregate( ConnectionService::get_public() );

		ob_start();
		echo '<div class="liw-worldmap" data-liw-worldmap>';
		echo self::svg( $agg ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in svg() escaped.
		echo self::text_alternative( $agg ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in text_alternative() escaped.
		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * @param array<int,array<string,mixed>> $rows
	 * @return array<string,array{count:int,status:string,types:array<string,int>}>
	 */
	private static function aggregate( array $rows ): array {
		$out = [];
		foreach ( $rows as $row ) {
			$key    = self::canonical_region( (string) ( $row['region'] ?? '' ) );
			$status = (string) ( $row['display_status'] ?? 'planned' );
			$type   = (string) ( $row['partner_type'] ?? '' );
			if ( ! isset( $out[ $key ] ) ) {
				$out[ $key ] = [ 'count' => 0, 'status' => 'inactive', 'types' => [] ];
			}
			$out[ $key ]['count']++;
			if ( '' !== $type ) {
				$out[ $key ]['types'][ $type ] = ( $out[ $key ]['types'][ $type ] ?? 0 ) + 1;
			}
			// Höchststatus: active > planned > inactive.
			$rank    = [ 'inactive' => 0, 'planned' => 1, 'active' => 2 ];
			$current = $rank[ $out[ $key ]['status'] ] ?? 0;
			$incoming = $rank[ $status ] ?? 0;
			if ( $incoming > $current ) {
				$out[ $key ]['status'] = $status;
			}
		}
		return $out;
	}

	/** Freitext-Region → kanonischer Schlüssel (keyword-basiert). */
	public static function canonical_region( string $region ): string {
		$r = strtolower( $region );
		if ( ( false !== strpos( $r, 'south' ) || false !== strpos( $r, 'süd' ) ) && ( false !== strpos( $r, 'americ' ) || false !== strpos( $r, 'amerik' ) ) ) {
			return 'south_america';
		}
		if ( false !== strpos( $r, 'americ' ) || false !== strpos( $r, 'amerik' ) ) {
			return 'north_america';
		}
		if ( false !== strpos( $r, 'europ' ) ) {
			return 'europe';
		}
		if ( false !== strpos( $r, 'afric' ) || false !== strpos( $r, 'afrik' ) ) {
			return 'africa';
		}
		if ( false !== strpos( $r, 'asia' ) || false !== strpos( $r, 'asien' ) || false !== strpos( $r, 'pacific' ) || false !== strpos( $r, 'pazifik' ) ) {
			return 'asia_pacific';
		}
		if ( false !== strpos( $r, 'middle' ) || false !== strpos( $r, 'naher' ) || false !== strpos( $r, 'osten' ) || false !== strpos( $r, 'east' ) ) {
			return 'middle_east';
		}
		return 'other';
	}

	/** @param array<string,array{count:int,status:string,types:array<string,int>}> $agg */
	private static function svg( array $agg ): string {
		$continents = self::continents_svg();
		$lines      = '';
		$nodes      = '';

		foreach ( self::REGIONS as $key => $r ) {
			$has    = isset( $agg[ $key ] ) && $agg[ $key ]['count'] > 0;
			$status = $has ? $agg[ $key ]['status'] : 'none';
			$count  = $has ? $agg[ $key ]['count'] : 0;

			if ( $has && 'europe' !== $key ) {
				$lines .= sprintf(
					'<line class="liw-worldmap__link liw-worldmap__link--%1$s" x1="%2$d" y1="%3$d" x2="%4$d" y2="%5$d" />',
					esc_attr( $status ),
					(int) self::HUB['x'],
					(int) self::HUB['y'],
					(int) $r['x'],
					(int) $r['y']
				);
			}

			$label = sprintf(
				/* translators: 1: Region, 2: Anzahl Verbindungen. */
				_n( '%1$s: %2$d Verbindung', '%1$s: %2$d Verbindungen', $count, 'liebherr-interface-world' ),
				$r['label'],
				$count
			);

			$nodes .= sprintf(
				'<g class="liw-worldmap__node liw-worldmap__node--%1$s%2$s" data-region="%3$s" tabindex="0" role="button" aria-label="%4$s">'
				. '<circle cx="%5$d" cy="%6$d" r="%7$d" /><text x="%5$d" y="%8$d" text-anchor="middle">%9$s</text></g>',
				esc_attr( $has ? $status : 'none' ),
				$has ? '' : ' is-empty',
				esc_attr( $key ),
				esc_attr( $label ),
				(int) $r['x'],
				(int) $r['y'],
				$has ? 13 : 7,
				(int) $r['y'] - 20,
				esc_html( $r['label'] )
			);
		}

		// Zentrale/Hub.
		$hub = sprintf(
			'<g class="liw-worldmap__hub" aria-label="%1$s"><circle cx="%2$d" cy="%3$d" r="16" /><text x="%2$d" y="%4$d" text-anchor="middle">%5$s</text></g>',
			esc_attr__( 'Liebherr Zentrale', 'liebherr-interface-world' ),
			(int) self::HUB['x'],
			(int) self::HUB['y'],
			(int) self::HUB['y'] - 24,
			esc_html__( 'Zentrale', 'liebherr-interface-world' )
		);

		return '<svg class="liw-worldmap__svg" viewBox="0 0 1000 520" role="img" aria-label="'
			. esc_attr__( 'Weltweite Anbindung der Liebherr-Händler und -Niederlassungen', 'liebherr-interface-world' )
			. '" xmlns="http://www.w3.org/2000/svg">'
			. $continents . $lines . $nodes . $hub
			. '</svg>';
	}

	/** Sehr grobe, stilisierte Kontinent-Silhouetten (dekorativ, kein Anspruch auf Genauigkeit). */
	private static function continents_svg(): string {
		$blobs = [
			'M120,120 q80,-40 170,-10 q40,60 -10,120 q-90,50 -150,-10 q-40,-60 -10,-100 Z', // Nordamerika
			'M270,300 q70,-20 90,40 q10,90 -40,130 q-60,10 -70,-70 q-10,-70 20,-100 Z',      // Südamerika
			'M450,90 q80,-30 140,0 q20,50 -30,80 q-90,20 -120,-20 q-20,-40 10,-60 Z',        // Europa
			'M470,250 q90,-20 120,40 q20,110 -50,150 q-80,10 -90,-90 q-10,-80 20,-100 Z',    // Afrika
			'M650,140 q170,-40 260,20 q40,120 -60,180 q-160,40 -230,-40 q-40,-90 30,-160 Z', // Asien
		];
		$paths = '';
		foreach ( $blobs as $d ) {
			$paths .= '<path class="liw-worldmap__land" d="' . esc_attr( $d ) . '" />';
		}
		return $paths;
	}

	/** Text-Alternative (§26): vollständige Liste der Regionen und Verbindungen. */
	private static function text_alternative( array $agg ): string {
		$items = '';
		foreach ( self::REGIONS as $key => $r ) {
			if ( ! isset( $agg[ $key ] ) || $agg[ $key ]['count'] < 1 ) {
				continue;
			}
			$types = [];
			foreach ( $agg[ $key ]['types'] as $type => $n ) {
				$types[] = esc_html( $type . ' (' . $n . ')' );
			}
			$items .= sprintf(
				'<li data-region="%1$s"><strong>%2$s</strong> – %3$s%4$s</li>',
				esc_attr( $key ),
				esc_html( $r['label'] ),
				esc_html( self::status_label( $agg[ $key ]['status'] ) ),
				$types ? ' · ' . implode( ', ', $types ) : ''
			);
		}
		if ( '' === $items ) {
			$items = '<li>' . esc_html__( 'Noch keine freigegebenen Verbindungen.', 'liebherr-interface-world' ) . '</li>';
		}
		return '<ul class="liw-worldmap__list">' . $items . '</ul>';
	}

	private static function status_label( string $status ): string {
		$map = [
			'active'   => __( 'aktiv', 'liebherr-interface-world' ),
			'planned'  => __( 'geplant', 'liebherr-interface-world' ),
			'inactive' => __( 'inaktiv', 'liebherr-interface-world' ),
		];
		return $map[ $status ] ?? $status;
	}
}
