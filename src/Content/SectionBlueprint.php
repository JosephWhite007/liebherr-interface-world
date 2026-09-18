<?php
/**
 * Liebherr Interface Solutions – Section Blueprint (Bauplan LP-01…LP-14)
 *
 * Einzige Quelle für die 14 Landingpage-Abschnitte laut Liebherr-Pflichtenheft §8 („Struktur
 * der Landingpage", Tabelle LP-01…LP-14): Code, Titel, Reihenfolge, redaktionelle Vorgabe
 * (Kurzinhalt wörtlich aus dem Pflichtenheft) und – wo bereits vorhanden – der einzubettende
 * Baustein (Shortcode). Reine Daten, keine Logik; genutzt von `SectionSeeder`.
 *
 * Die Vorgabe-Texte sind bewusst KEIN fertiger Marketingtext, sondern die Pflichtenheft-
 * Beschreibung als Redaktionshinweis im Entwurf (Regel „nicht erfinden": Formulierungen für
 * die öffentliche Seite liefert die Redaktion, nicht das Plugin). Ausnahme LP-01: H1 und
 * CTA-Beschriftungen stehen wörtlich im Pflichtenheft und werden übernommen.
 *
 * Sicherheit (§17, Joseph 18.09.2026): keine realen System-, API- oder Standortnamen in den
 * Vorgaben – nur die generischen Begriffe des Pflichtenhefts.
 *
 * @package Liebherr\InterfaceWorld\Content
 * @since   0.1.0-alpha.17
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Content;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SectionBlueprint {

	/** Post-Meta-Schlüssel, der einen liw_section-Beitrag seinem Pflichtenheft-Code zuordnet. */
	public const META_CODE = '_liw_lp_code';

	/** Abstand der menu_order-Werte, damit Redaktion Abschnitte dazwischen einsortieren kann. */
	public const ORDER_STEP = 10;

	/**
	 * @return array<string, array{title:string,brief:string,embed?:string}>
	 *   Schlüssel = Pflichtenheft-Code in Seitenreihenfolge; `embed` = Shortcode eines bereits
	 *   gebauten Bausteins, der im Entwurf direkt platziert wird.
	 */
	public static function all(): array {
		return [
			'LP-01' => [
				'title' => 'Hero',
				'brief' => 'Großformatiges Baumaschinenmotiv oder freigegebenes Liebherr-Motiv mit digitaler Netzwerkebene. H1: „One structure. Connected worldwide." Subline erklärt sichere Verbindung zentraler Liebherr-Systeme mit lokalen Händler-, Lieferanten- und Kundensystemen. CTAs: Start Integration und Explore the Simulation.',
			],
			'LP-02' => [
				'title' => 'Ausgangslage',
				'brief' => 'Darstellung der heutigen Systemvielfalt und der Risiken wiederholter Datenübersetzungen: Feldabweichungen, Dubletten, Medienbrüche, Statuskonflikte, Zeitverlust und manuelle Nacharbeit.',
			],
			'LP-03' => [
				'title' => 'Zielbild',
				'brief' => 'Einheitliche Liebherr-Referenzstruktur als verbindende Schicht. Visualisierung Zentral-System → Interface LogiQ → lokale Systeme.',
				'embed' => '[liw_graphic name="target-model"]',
			],
			'LP-04' => [
				'title' => 'Magic Cube',
				'brief' => 'Physische und digitale Simulationsumgebung. Erklärung von Sandbox, synthetischen Daten, Schnittstellentests, Fehlerfällen, Lasttests, Verifizierung und Validierung.',
				'embed' => '[liw_graphic name="magic-cube"]',
			],
			'LP-05' => [
				'title' => 'Interface LogiQ',
				'brief' => 'Darstellung der produktiven Vermittlungs-, Prüf- und Übersetzungsschicht nach erfolgreicher Abnahme.',
			],
			'LP-06' => [
				'title' => 'World Connections',
				'brief' => 'Interaktive Weltkarte oder abstrakte Netzwerkdarstellung mit Regionen, Händlerknoten und Statusstufen. Keine realen Standorte ohne Freigabe.',
				'embed' => '[liw_world_connections_map]',
			],
			'LP-07' => [
				'title' => 'Data Model',
				'brief' => 'Objektgruppen Kunde, Kontakt, Händler, Maschine, Konfiguration, Angebot, Auftrag, Bedarfsfall, Lieferung, Rechnung und Zahlung.',
				'embed' => '[liw_graphic name="data-model"]',
			],
			'LP-08' => [
				'title' => 'Process Worlds',
				'brief' => 'Karten für Sales, Configuration, Order, Goods, Finance, Service und Warranty.',
				'embed' => '[liw_graphic name="process-worlds"]',
			],
			'LP-09' => [
				'title' => 'Goods and Finance',
				'brief' => 'Gegenüberstellung und Verbindung von Waren- und Finanzströmen mit messbarem Nutzen.',
			],
			'LP-10' => [
				'title' => 'Security',
				'brief' => 'Zero-trust-orientierte Darstellung, getrennte Umgebungen, rollenbasierter Zugriff, Audit, Versionierung und Freigaben.',
			],
			'LP-11' => [
				'title' => 'Onboarding',
				'brief' => 'Neunstufiger Händleranschluss von der Bestandsaufnahme bis zum überwachten Produktivbetrieb.',
				'embed' => '[liw_onboarding_form]',
			],
			'LP-12' => [
				'title' => 'Roadmap',
				'brief' => 'Phasen: Contract Model, Magic Cube, Sandbox Validation, Pilot Dealer, Interface LogiQ, Global Rollout.',
				'embed' => '[liw_graphic name="roadmap"]',
			],
			'LP-13' => [
				'title' => 'Kontakt',
				'brief' => 'Kurzes qualifiziertes Anfrageformular mit Auswahl Zentralbereich, Händler, Lieferant, Technologiepartner oder sonstiger Projektkontakt.',
				'embed' => '[liw_contact_form]',
			],
			'LP-14' => [
				'title' => 'Footer',
				'brief' => 'Rechtliches, Datenschutz, Barrierefreiheit, Sprachen und sehr kleine Kennzeichnung „Solution Provider: GoHeal".',
			],
		];
	}

	/** Reihenfolge-Wert eines Codes (LP-01 → 10, LP-02 → 20 …); 0 bei unbekanntem Code. */
	public static function menu_order_for( string $code ): int {
		$position = array_search( $code, array_keys( self::all() ), true );
		return false === $position ? 0 : ( (int) $position + 1 ) * self::ORDER_STEP;
	}

	/**
	 * Block-Editor-Markup des Entwurfs: Redaktionshinweis als Absatz, darunter ggf. der
	 * bereits gebaute Baustein als Shortcode-Block (statt Classic-Block, damit die Redaktion
	 * sauber blockweise weiterarbeiten kann).
	 */
	public static function draft_content( string $code ): string {
		$section = self::all()[ $code ] ?? null;
		if ( null === $section ) {
			return '';
		}

		$blocks = [
			'<!-- wp:paragraph --><p><strong>' . esc_html( $code ) . ' – Redaktionsvorgabe (Pflichtenheft §8):</strong> ' . esc_html( $section['brief'] ) . '</p><!-- /wp:paragraph -->',
		];
		if ( isset( $section['embed'] ) ) {
			$blocks[] = '<!-- wp:shortcode -->' . $section['embed'] . '<!-- /wp:shortcode -->';
		}

		return implode( "\n\n", $blocks );
	}
}
