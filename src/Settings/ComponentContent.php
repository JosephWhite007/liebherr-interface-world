<?php
/**
 * Liebherr Interface Solutions – Inhalt der datengetriebenen Kern-Komponenten (§8/§16).
 *
 * Drei administrierbare Listen für die Landingpage-Abschnitte:
 *   - Process World Cards (LP-08): Sales, Configuration, Order, Goods, Finance, Service, Warranty
 *   - Roadmap-Phasen (LP-12): Contract Model … Global Rollout
 *   - Onboarding-Schritte (LP-11): neunstufig, Bestandsaufnahme … überwachter Produktivbetrieb
 *
 * Ablage als Option `liw_components`. Standardinhalte via `__()` (mehrsprachig); redaktionelle
 * Overrides sind literal (per-Sprache = Verfeinerung, s. To-Dos). `sanitize()` ist rein bis auf
 * WP-Sanitizer; die Struktur ist unit-testbar über `defaults()`.
 *
 * @package Liebherr\InterfaceWorld\Settings
 * @since   0.1.0-alpha.29
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Settings;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ComponentContent {

	public const OPTION = 'liw_components';

	/**
	 * @return array{process:array<int,array{title:string,text:string}>,roadmap:array<int,array{title:string,text:string}>,onboarding:array<int,array{title:string,text:string}>}
	 */
	public static function defaults(): array {
		return [
			'process' => [
				[ 'title' => __( 'Sales', 'liebherr-interface-world' ), 'text' => __( 'Angebots- und Vertriebsprozesse einheitlich abbilden.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Configuration', 'liebherr-interface-world' ), 'text' => __( 'Maschinen- und Anbaukonfiguration ohne Medienbruch.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Order', 'liebherr-interface-world' ), 'text' => __( 'Auftragsdaten konsistent zwischen Zentrale und Händler.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Goods', 'liebherr-interface-world' ), 'text' => __( 'Warenströme, Lieferungen und Bestände nachvollziehbar.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Finance', 'liebherr-interface-world' ), 'text' => __( 'Rechnungen und Zahlungen sauber zugeordnet.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Service', 'liebherr-interface-world' ), 'text' => __( 'Servicevorgänge und Bedarfsfälle strukturiert.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Warranty', 'liebherr-interface-world' ), 'text' => __( 'Gewährleistungsfälle einheitlich erfassen und prüfen.', 'liebherr-interface-world' ) ],
			],
			'roadmap' => [
				[ 'title' => __( 'Contract Model', 'liebherr-interface-world' ), 'text' => __( 'Vertrags- und Kooperationsmodell festlegen.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Magic Cube', 'liebherr-interface-world' ), 'text' => __( 'Physische und digitale Simulationsumgebung aufbauen.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Sandbox Validation', 'liebherr-interface-world' ), 'text' => __( 'Schnittstellen mit synthetischen Daten prüfen.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Pilot Dealer', 'liebherr-interface-world' ), 'text' => __( 'Erster Händler im überwachten Pilotbetrieb.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Interface LogiQ', 'liebherr-interface-world' ), 'text' => __( 'Produktive Vermittlungs- und Prüfschicht nach Abnahme.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Global Rollout', 'liebherr-interface-world' ), 'text' => __( 'Schrittweise weltweite Ausrollung.', 'liebherr-interface-world' ) ],
			],
			'onboarding' => [
				[ 'title' => __( 'Bestandsaufnahme', 'liebherr-interface-world' ), 'text' => __( 'Systemlandschaft und Schnittstellen des Händlers erfassen.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Zielbild & Scope', 'liebherr-interface-world' ), 'text' => __( 'Umfang, Objekte und Prozesse der Anbindung festlegen.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Vertrag & Freigabe', 'liebherr-interface-world' ), 'text' => __( 'Rahmen, Rollen und Freigabeverantwortliche klären.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Sandbox-Anbindung', 'liebherr-interface-world' ), 'text' => __( 'Anschluss an den Magic Cube mit synthetischen Daten.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Schnittstellentests', 'liebherr-interface-world' ), 'text' => __( 'Feld-Mapping, Fehlerfälle und Lasttests durchführen.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Verifizierung', 'liebherr-interface-world' ), 'text' => __( 'Datenübertragung, Stabilität und Sicherheit messen.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Validierung & Abnahme', 'liebherr-interface-world' ), 'text' => __( 'Fachliche Freigabe der geprüften Schnittstellen.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Pilotbetrieb', 'liebherr-interface-world' ), 'text' => __( 'Überwachter Betrieb mit begrenztem Umfang.', 'liebherr-interface-world' ) ],
				[ 'title' => __( 'Überwachter Produktivbetrieb', 'liebherr-interface-world' ), 'text' => __( 'Regelbetrieb mit fortlaufender Überwachung.', 'liebherr-interface-world' ) ],
			],
		];
	}

	/** @return array<string,array<int,array{title:string,text:string}>> */
	public static function get(): array {
		$stored = get_option( self::OPTION, [] );
		if ( ! is_array( $stored ) || [] === $stored ) {
			return self::defaults();
		}
		return self::sanitize( $stored );
	}

	/**
	 * @param array<string,mixed> $raw
	 * @return array<string,array<int,array{title:string,text:string}>>
	 */
	public static function save( array $raw ): array {
		$clean = self::sanitize( $raw );
		update_option( self::OPTION, $clean );
		return $clean;
	}

	/**
	 * @param array<string,mixed> $raw
	 * @return array<string,array<int,array{title:string,text:string}>>
	 */
	public static function sanitize( array $raw ): array {
		$def = self::defaults();
		$out = [];
		foreach ( [ 'process', 'roadmap', 'onboarding' ] as $list ) {
			if ( ! isset( $raw[ $list ] ) || ! is_array( $raw[ $list ] ) ) {
				$out[ $list ] = $def[ $list ];
				continue;
			}
			$items = [];
			foreach ( $raw[ $list ] as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$title = sanitize_text_field( (string) ( $item['title'] ?? '' ) );
				$text  = sanitize_text_field( (string) ( $item['text'] ?? '' ) );
				if ( '' !== $title ) {
					$items[] = [ 'title' => $title, 'text' => $text ];
				}
			}
			$out[ $list ] = [] !== $items ? $items : $def[ $list ];
		}
		return $out;
	}
}
