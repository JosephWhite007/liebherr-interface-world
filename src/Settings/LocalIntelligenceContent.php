<?php
/**
 * Liebherr Local Intelligence – Administrierbares Content-Modell (Pflichtenheft LI §8/§12.3).
 *
 * Ablage als Option `liw_local_intelligence`. Enthält den redaktionellen Text aller elf Module der
 * übergeordneten Hauptseite „Liebherr Local Intelligence" (Hero, Vision, Datenbewegung, Simulation
 * World, Wissensassistenz, Datenqualität, weltweite Nutzung, Einsatzfelder, Interface-Brücke,
 * Rollout, Kontakt-CTA). Standardtexte via `__()` (DE = redaktionelle Ausgangsfassung, EN/weitere
 * über den bestehenden Sprach-Workflow – identisches Muster wie Settings\ComponentContent, keine
 * zweite Übersetzungslogik). `sanitize()`/`defaults()`/`get()` sind rein und unit-testbar.
 *
 * WICHTIG (LI §4/§7/§12.8): ausschließlich Demonstrationsinhalte, keine echten Geschäftsdaten,
 * keine unbelegten Leistungs-, Sicherheits- oder Echtzeitversprechen, keine erfundenen Kennzahlen.
 *
 * @package Liebherr\InterfaceWorld\Settings
 * @since   0.1.0-alpha.41
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Settings;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class LocalIntelligenceContent {

	public const OPTION = 'liw_local_intelligence';

	/**
	 * Vollständige Standardinhalte aller elf Module.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return [
			// Intro-Overlay (Sternenregen + Eintritts-Fenster, alpha.45).
			'intro' => [
				'title'              => __( 'Liebherr Local Intelligence', 'liebherr-interface-world' ),
				'subtitle'           => __( 'Simulieren. Verstehen. Entscheiden.', 'liebherr-interface-world' ),
				'text'               => __( 'Willkommen im geschützten Entscheidungsraum. Bitte bestätigen Sie den Eintritt.', 'liebherr-interface-world' ),
				'terms_button_label' => __( 'Nutzungsbedingungen anzeigen', 'liebherr-interface-world' ),
				'terms_heading'      => __( 'Nutzungsbedingungen', 'liebherr-interface-world' ),
				'terms_body'         => __( 'Diese Seite ist eine interne Demonstration (Prototyp). Es gelten die noch festzulegenden Nutzungsbedingungen; die verbindliche Fassung folgt mit der offiziellen Freigabe. Inhalte und dargestellte Zahlen sind Beispieldaten. Bitte behandeln Sie die Inhalte vertraulich. Ergänzend gelten Impressum und Datenschutzhinweis im Seitenfuß.', 'liebherr-interface-world' ),
				'math_label'         => __( 'Zur Bestätigung bitte rechnen:', 'liebherr-interface-world' ),
				'accept_label'       => __( 'Eintreten', 'liebherr-interface-world' ),
				'accept_hint'        => __( 'Bitte lösen Sie die Rechenaufgabe, um einzutreten.', 'liebherr-interface-world' ),
			],
			// Modul 1 – Hero und Einstieg.
			'hero' => [
				'eyebrow'  => __( 'LIEBHERR LOCAL INTELLIGENCE', 'liebherr-interface-world' ),
				'headline' => __( 'Entscheidungen dort vorbereiten, wo Märkte entstehen.', 'liebherr-interface-world' ),
				'intro'    => __( 'Geschützte lokale Systeme verbinden freigegebenes Unternehmenswissen, realitätsnahe Daten und Simulationen für Niederlassungen und Vertriebspartner weltweit.', 'liebherr-interface-world' ),
				'tagline'  => __( 'Simulieren. Verstehen. Entscheiden.', 'liebherr-interface-world' ),
				'cta_primary_label'   => __( 'Local Intelligence entdecken', 'liebherr-interface-world' ),
				'cta_secondary_label' => __( 'Technische Plattform ansehen', 'liebherr-interface-world' ),
			],
			// Modul 2 – Die Vision auf einen Blick.
			'vision' => [
				'title' => __( 'Ein geschützter Raum für bessere Entscheidungen', 'liebherr-interface-world' ),
				'intro' => __( 'Liebherr Local Intelligence schafft für Niederlassungen und Vertriebspartner weltweit einen geschützten, eigenständigen Raum für Planung, Simulation und fundierte Entscheidungen. Verkaufs-, Finanzierungs- und Marktszenarien lassen sich mit realitätsnahen Daten Schritt für Schritt durchspielen, vergleichen und bewerten.', 'liebherr-interface-world' ),
				'fields' => [
					[ 'title' => __( 'Simulieren', 'liebherr-interface-world' ), 'text' => __( 'Szenarien mit freigegebenen, realitätsnahen Daten prüfen.', 'liebherr-interface-world' ) ],
					[ 'title' => __( 'Verstehen', 'liebherr-interface-world' ), 'text' => __( 'Unternehmenswissen unmittelbar und kontextbezogen nutzen.', 'liebherr-interface-world' ) ],
					[ 'title' => __( 'Entscheiden', 'liebherr-interface-world' ), 'text' => __( 'Märkte schneller, strukturierter und sicherer bedienen.', 'liebherr-interface-world' ) ],
				],
			],
			// Modul 3 – Von globalem Wissen zu lokaler Handlungsfähigkeit.
			'flow' => [
				'title' => __( 'Von globalem Wissen zu lokaler Handlungsfähigkeit', 'liebherr-interface-world' ),
				'intro' => __( 'Freigegebenes Wissen und validierte Daten werden über die technische Plattform sicher bereitgestellt und im geschützten lokalen Raum zu nachvollziehbaren Entscheidungen.', 'liebherr-interface-world' ),
				'steps' => [
					[ 'title' => __( 'Freigegebenes Wissen', 'liebherr-interface-world' ), 'text' => __( 'Validierte Daten aus dem Unternehmensnetz.', 'liebherr-interface-world' ) ],
					[ 'title' => __( 'Sichere Bereitstellung', 'liebherr-interface-world' ), 'text' => __( 'Über Interface Solutions strukturiert übergeben.', 'liebherr-interface-world' ) ],
					[ 'title' => __( 'Lokaler Intelligenzraum', 'liebherr-interface-world' ), 'text' => __( 'Geschützter Raum der Niederlassung oder des Händlers.', 'liebherr-interface-world' ) ],
					[ 'title' => __( 'Simulation & Auswertung', 'liebherr-interface-world' ), 'text' => __( 'Assistenz und visuelle Auswertung vor Ort.', 'liebherr-interface-world' ) ],
					[ 'title' => __( 'Fundierte Entscheidung', 'liebherr-interface-world' ), 'text' => __( 'Lokale Entscheidung mit nachvollziehbarer Datengrundlage.', 'liebherr-interface-world' ) ],
				],
			],
			// Modul 4 – Liebherr Simulation World (Szenarien A/B/C).
			'simulation' => [
				'title'     => __( 'Szenarien durchspielen, bevor Entscheidungen fallen', 'liebherr-interface-world' ),
				'intro'     => __( 'Die Simulation World ist der erste sichtbare Anwendungsraum: Verkaufs-, Finanzierungs-, Markt-, Produktions- und Produktmix-Szenarien lassen sich als Varianten vergleichen.', 'liebherr-interface-world' ),
				'demo_note' => __( 'Beispieldaten zur Veranschaulichung – keine echten Geschäftszahlen.', 'liebherr-interface-world' ),
				'scenarios' => [
					[
						'key'     => 'A',
						'label'   => __( 'Variante A · Basis', 'liebherr-interface-world' ),
						'summary' => __( 'Konservative Annahmen, stabile Nachfrage.', 'liebherr-interface-world' ),
						'rows'    => [
							[ 'label' => __( 'Absatz (Beispiel)', 'liebherr-interface-world' ), 'value' => __( '100 Einheiten', 'liebherr-interface-world' ) ],
							[ 'label' => __( 'Finanzierungsmix', 'liebherr-interface-world' ), 'value' => __( '70 % Kauf / 30 % Leasing', 'liebherr-interface-world' ) ],
							[ 'label' => __( 'Lieferzeit', 'liebherr-interface-world' ), 'value' => __( 'Standard', 'liebherr-interface-world' ) ],
						],
					],
					[
						'key'     => 'B',
						'label'   => __( 'Variante B · Wachstum', 'liebherr-interface-world' ),
						'summary' => __( 'Höhere Nachfrage, erweiterte Kapazität.', 'liebherr-interface-world' ),
						'rows'    => [
							[ 'label' => __( 'Absatz (Beispiel)', 'liebherr-interface-world' ), 'value' => __( '135 Einheiten', 'liebherr-interface-world' ) ],
							[ 'label' => __( 'Finanzierungsmix', 'liebherr-interface-world' ), 'value' => __( '55 % Kauf / 45 % Leasing', 'liebherr-interface-world' ) ],
							[ 'label' => __( 'Lieferzeit', 'liebherr-interface-world' ), 'value' => __( 'Erweitert', 'liebherr-interface-world' ) ],
						],
					],
					[
						'key'     => 'C',
						'label'   => __( 'Variante C · Risiko', 'liebherr-interface-world' ),
						'summary' => __( 'Volatiler Markt, vorsichtige Planung.', 'liebherr-interface-world' ),
						'rows'    => [
							[ 'label' => __( 'Absatz (Beispiel)', 'liebherr-interface-world' ), 'value' => __( '85 Einheiten', 'liebherr-interface-world' ) ],
							[ 'label' => __( 'Finanzierungsmix', 'liebherr-interface-world' ), 'value' => __( '80 % Kauf / 20 % Leasing', 'liebherr-interface-world' ) ],
							[ 'label' => __( 'Lieferzeit', 'liebherr-interface-world' ), 'value' => __( 'Konservativ', 'liebherr-interface-world' ) ],
						],
					],
				],
			],
			// Modul 5 – Wissen wird zum Assistenzsystem.
			'knowledge' => [
				'title'       => __( 'Freigegebenes Unternehmenswissen genau dann nutzen, wenn es gebraucht wird', 'liebherr-interface-world' ),
				'intro'       => __( 'Eine typische Frage aus Vertrieb oder Planung, eine strukturierte Antwort mit Quelle und Freigabestatus sowie eine mögliche nächste Aktion – nachvollziehbar statt unbelegter Chat-Ausgabe.', 'liebherr-interface-world' ),
				'question'    => __( 'Welche Finanzierungsoption passt zu einer Baumaschine für einen regionalen Bauunternehmer?', 'liebherr-interface-world' ),
				'answer'      => __( 'Für den beschriebenen Fall kommen zwei freigegebene Modelle in Frage. Die Auswahl hängt von Nutzungsdauer und geplanter Auslastung ab.', 'liebherr-interface-world' ),
				'source'      => __( 'Quelle: freigegebener Finanzierungskatalog (Beispiel)', 'liebherr-interface-world' ),
				'status'      => __( 'Freigabestatus: freigegeben · Stand: Demonstration', 'liebherr-interface-world' ),
				'next_action' => __( 'Nächste Aktion: Variante in der Simulation World vergleichen.', 'liebherr-interface-world' ),
				'points'      => [
					[ 'title' => __( 'Zugriff', 'liebherr-interface-world' ), 'text' => __( 'Nur freigegebenes firmeneigenes Wissen.', 'liebherr-interface-world' ) ],
					[ 'title' => __( 'Kontext', 'liebherr-interface-world' ), 'text' => __( 'Rollen- und kontextbezogene Antworten.', 'liebherr-interface-world' ) ],
					[ 'title' => __( 'Nachweis', 'liebherr-interface-world' ), 'text' => __( 'Quellen- und Aktualitätskennzeichnung.', 'liebherr-interface-world' ) ],
					[ 'title' => __( 'Prüfung', 'liebherr-interface-world' ), 'text' => __( 'Fachliche Prüfung oder Eskalation möglich.', 'liebherr-interface-world' ) ],
				],
			],
			// Modul 6 – Datenqualität, Sicherheit und Diskretion.
			'trust' => [
				'title' => __( 'Verlässliche Informationen brauchen eine verlässliche Herkunft', 'liebherr-interface-world' ),
				'intro' => __( 'Vier Prinzipien sichern Qualität und Diskretion – ohne pauschale Behauptung absoluter Sicherheit.', 'liebherr-interface-world' ),
				'areas' => [
					[ 'title' => __( 'Datenherkunft', 'liebherr-interface-world' ), 'text' => __( 'Quelle, Version und Freigabe werden nachvollziehbar.', 'liebherr-interface-world' ) ],
					[ 'title' => __( 'Validierung', 'liebherr-interface-world' ), 'text' => __( 'Informationen werden auf Plausibilität, Aktualität und Verlässlichkeit geprüft.', 'liebherr-interface-world' ) ],
					[ 'title' => __( 'Schutz', 'liebherr-interface-world' ), 'text' => __( 'Sensible Informationen verbleiben in der vorgesehenen geschützten Systemumgebung.', 'liebherr-interface-world' ) ],
					[ 'title' => __( 'Verantwortung', 'liebherr-interface-world' ), 'text' => __( 'Rollen, Rechte und Freigaben bestimmen, wer welche Daten verwenden darf.', 'liebherr-interface-world' ) ],
				],
			],
			// Modul 7 – Weltweit verfügbar und sprachübergreifend nutzbar.
			'global' => [
				'title'         => __( 'Ein gemeinsames Qualitätsniveau in vielen Märkten', 'liebherr-interface-world' ),
				'intro'         => __( 'Die Nutzung reicht vom Smartphone bis zur Großbildleinwand. Ein automatisierter Übersetzungs-Layer unterstützt die sprachübergreifende Kommunikation.', 'liebherr-interface-world' ),
				'devices'       => [
					__( 'Smartphone', 'liebherr-interface-world' ),
					__( 'Notebook', 'liebherr-interface-world' ),
					__( 'Arbeitsplatzmonitor', 'liebherr-interface-world' ),
					__( 'Großbildleinwand', 'liebherr-interface-world' ),
				],
				'workflow_note' => __( 'Für sensible oder verbindliche Inhalte greift ein definierter Workflow zur professionellen Übersetzung oder fachlichen Freigabe.', 'liebherr-interface-world' ),
				'languages_note' => __( 'Startsprachen Deutsch und Englisch vollständig; die Struktur ist auf weitere Plattformsprachen vorbereitet (EN, DE, TH, ES, FR, IT, NL, PT, PL, RU, FI, SV).', 'liebherr-interface-world' ),
			],
			// Modul 8 – Einsatzfelder (filterbar).
			'usecases' => [
				'title' => __( 'Einsatzfelder', 'liebherr-interface-world' ),
				'intro' => __( 'Sechs Bereiche – je mit Problem, Funktionsprinzip und Nutzen.', 'liebherr-interface-world' ),
				'items' => [
					[ 'tag' => __( 'Vertrieb', 'liebherr-interface-world' ), 'title' => __( 'Vertrieb und Angebotsvorbereitung', 'liebherr-interface-world' ), 'problem' => __( 'Angebote entstehen unter Zeitdruck und mit uneinheitlicher Datenbasis.', 'liebherr-interface-world' ), 'principle' => __( 'Freigegebene Daten und Varianten stehen strukturiert bereit.', 'liebherr-interface-world' ), 'benefit' => __( 'Schnellere, belastbarere Angebote.', 'liebherr-interface-world' ) ],
					[ 'tag' => __( 'Finanzierung', 'liebherr-interface-world' ), 'title' => __( 'Finanzierung und Szenarienvergleich', 'liebherr-interface-world' ), 'problem' => __( 'Finanzierungsvarianten sind schwer vergleichbar.', 'liebherr-interface-world' ), 'principle' => __( 'Varianten werden nebeneinander simuliert.', 'liebherr-interface-world' ), 'benefit' => __( 'Nachvollziehbare Empfehlung.', 'liebherr-interface-world' ) ],
					[ 'tag' => __( 'Markt', 'liebherr-interface-world' ), 'title' => __( 'Markt- und Absatzplanung', 'liebherr-interface-world' ), 'problem' => __( 'Marktentwicklungen sind unsicher.', 'liebherr-interface-world' ), 'principle' => __( 'Annahmen werden als Szenarien geprüft.', 'liebherr-interface-world' ), 'benefit' => __( 'Frühere Erkennung von Chancen und Risiken.', 'liebherr-interface-world' ) ],
					[ 'tag' => __( 'Produktion', 'liebherr-interface-world' ), 'title' => __( 'Produkt- und Kapazitätsplanung', 'liebherr-interface-world' ), 'problem' => __( 'Produktmix und Kapazität müssen abgestimmt werden.', 'liebherr-interface-world' ), 'principle' => __( 'Regionale Anforderungen fließen in die Planung ein.', 'liebherr-interface-world' ), 'benefit' => __( 'Bessere Abstimmung von Nachfrage und Kapazität.', 'liebherr-interface-world' ) ],
					[ 'tag' => __( 'Wissen', 'liebherr-interface-world' ), 'title' => __( 'Wissenszugriff und Qualifizierung', 'liebherr-interface-world' ), 'problem' => __( 'Wissen ist verteilt und schwer auffindbar.', 'liebherr-interface-world' ), 'principle' => __( 'Freigegebenes Wissen wird kontextbezogen bereitgestellt.', 'liebherr-interface-world' ), 'benefit' => __( 'Weniger Interpretationsfehler.', 'liebherr-interface-world' ) ],
					[ 'tag' => __( 'Management', 'liebherr-interface-world' ), 'title' => __( 'Managemententscheidung und Präsentation', 'liebherr-interface-world' ), 'problem' => __( 'Entscheidungsgrundlagen sind uneinheitlich aufbereitet.', 'liebherr-interface-world' ), 'principle' => __( 'Ergebnisse werden präsentationsfähig dargestellt.', 'liebherr-interface-world' ), 'benefit' => __( 'Klare, gemeinsame Entscheidungsbasis.', 'liebherr-interface-world' ) ],
				],
			],
			// Modul 9 – Interface Solutions als technisches Herzstück (Brücke).
			'bridge' => [
				'title'     => __( 'Die Verbindung zwischen Unternehmenssystemen und Local Intelligence', 'liebherr-interface-world' ),
				'intro'     => __( 'Interface Solutions ist die technische Befähigungsebene, die Local Intelligence mit geeigneten, freigegebenen Informationen versorgt.', 'liebherr-interface-world' ),
				'points'    => [
					__( 'Interface Solutions bindet freigegebene Quellsysteme an.', 'liebherr-interface-world' ),
					__( 'Konverter übersetzen unterschiedliche Datenstrukturen.', 'liebherr-interface-world' ),
					__( 'Definierte Übertragungsoperationen sorgen für klare Abläufe.', 'liebherr-interface-world' ),
					__( 'Validierung, Status und Fehlerbehandlung machen Datenflüsse nachvollziehbar.', 'liebherr-interface-world' ),
					__( 'Die technische Plattform versorgt Simulationen und Assistenzfunktionen mit geeigneten Informationen.', 'liebherr-interface-world' ),
				],
				'cta_label' => __( 'Interface Solutions im Detail ansehen', 'liebherr-interface-world' ),
			],
			// Modul 10 – Schrittweiser Einstieg (Rollout).
			'rollout' => [
				'title' => __( 'Vom abgegrenzten Piloten zum skalierbaren Netzwerk', 'liebherr-interface-world' ),
				'intro' => __( 'Ein nachvollziehbarer Einführungsweg – ein Vorgehensmodell, keine Aussage über einen bereits beauftragten Rollout.', 'liebherr-interface-world' ),
				'steps' => [
					[ 'title' => __( 'Anwendungsfall definieren', 'liebherr-interface-world' ), 'text' => __( 'Anwendungsfall und Datenraum festlegen.', 'liebherr-interface-world' ) ],
					[ 'title' => __( 'Quellen & Freigaben', 'liebherr-interface-world' ), 'text' => __( 'Quellen, Rollen und Freigaben festlegen.', 'liebherr-interface-world' ) ],
					[ 'title' => __( 'System aufbauen', 'liebherr-interface-world' ), 'text' => __( 'Lokales System und Interface-Anbindung aufbauen.', 'liebherr-interface-world' ) ],
					[ 'title' => __( 'Simulation testen', 'liebherr-interface-world' ), 'text' => __( 'Simulation World mit definierten Szenarien testen.', 'liebherr-interface-world' ) ],
					[ 'title' => __( 'Validieren', 'liebherr-interface-world' ), 'text' => __( 'Ergebnisse fachlich und technisch validieren.', 'liebherr-interface-world' ) ],
					[ 'title' => __( 'Ausrollen', 'liebherr-interface-world' ), 'text' => __( 'Kontrolliert auf weitere Standorte und Funktionen ausrollen.', 'liebherr-interface-world' ) ],
				],
			],
			// Modul 11 – Abschluss und Kontakt.
			'contact' => [
				'title'               => __( 'Die Zukunft beginnt mit einem klar definierten Anwendungsfall', 'liebherr-interface-world' ),
				'intro'               => __( 'Fordern Sie eine Demonstration an oder öffnen Sie die technische Architektur.', 'liebherr-interface-world' ),
				'cta_primary_label'   => __( 'Demonstration anfragen', 'liebherr-interface-world' ),
				'cta_secondary_label' => __( 'Technische Architektur öffnen', 'liebherr-interface-world' ),
			],
		];
	}

	/** @return array<string,mixed> */
	public static function get(): array {
		$stored = get_option( self::OPTION, [] );
		if ( ! is_array( $stored ) || [] === $stored ) {
			return self::defaults();
		}
		return self::sanitize( $stored );
	}

	/**
	 * @param array<string,mixed> $raw
	 * @return array<string,mixed>
	 */
	public static function save( array $raw ): array {
		$clean = self::sanitize( $raw );
		update_option( self::OPTION, $clean );
		return $clean;
	}

	/**
	 * Deckungsgleiche, tiefe Bereinigung gegen defaults(): fehlende Zweige fallen auf den Standard
	 * zurück, vorhandene Felder werden WP-sanitisiert. Mehrzeilige Felder erlauben Zeilenumbrüche.
	 *
	 * @param array<string,mixed> $raw
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $raw ): array {
		$def = self::defaults();
		$out = $def; // Basis = Standard, dann selektiv überschreiben.

		$scalar = static fn( $v ): string => sanitize_text_field( (string) $v );
		$multi  = static fn( $v ): string => sanitize_textarea_field( (string) $v );

		// Intro-Overlay.
		if ( isset( $raw['intro'] ) && is_array( $raw['intro'] ) ) {
			foreach ( [ 'title', 'subtitle', 'terms_button_label', 'terms_heading', 'math_label', 'accept_label', 'accept_hint' ] as $k ) {
				if ( isset( $raw['intro'][ $k ] ) ) { $out['intro'][ $k ] = $scalar( $raw['intro'][ $k ] ); }
			}
			foreach ( [ 'text', 'terms_body' ] as $k ) {
				if ( isset( $raw['intro'][ $k ] ) ) { $out['intro'][ $k ] = $multi( $raw['intro'][ $k ] ); }
			}
		}

		// Hero.
		if ( isset( $raw['hero'] ) && is_array( $raw['hero'] ) ) {
			foreach ( [ 'eyebrow', 'headline', 'tagline', 'cta_primary_label', 'cta_secondary_label' ] as $k ) {
				if ( isset( $raw['hero'][ $k ] ) ) { $out['hero'][ $k ] = $scalar( $raw['hero'][ $k ] ); }
			}
			if ( isset( $raw['hero']['intro'] ) ) { $out['hero']['intro'] = $multi( $raw['hero']['intro'] ); }
		}

		// Einfache {title,intro}+Liste-Module.
		foreach ( [ 'vision' => 'fields', 'flow' => 'steps', 'trust' => 'areas', 'rollout' => 'steps' ] as $mod => $listkey ) {
			if ( ! isset( $raw[ $mod ] ) || ! is_array( $raw[ $mod ] ) ) { continue; }
			if ( isset( $raw[ $mod ]['title'] ) ) { $out[ $mod ]['title'] = $scalar( $raw[ $mod ]['title'] ); }
			if ( isset( $raw[ $mod ]['intro'] ) ) { $out[ $mod ]['intro'] = $multi( $raw[ $mod ]['intro'] ); }
			$out[ $mod ][ $listkey ] = self::sanitize_pairs( $raw[ $mod ][ $listkey ] ?? null, $def[ $mod ][ $listkey ] );
		}

		// Simulation (Szenarien mit Zeilen).
		if ( isset( $raw['simulation'] ) && is_array( $raw['simulation'] ) ) {
			foreach ( [ 'title', 'demo_note' ] as $k ) {
				if ( isset( $raw['simulation'][ $k ] ) ) { $out['simulation'][ $k ] = $scalar( $raw['simulation'][ $k ] ); }
			}
			if ( isset( $raw['simulation']['intro'] ) ) { $out['simulation']['intro'] = $multi( $raw['simulation']['intro'] ); }
			if ( isset( $raw['simulation']['scenarios'] ) && is_array( $raw['simulation']['scenarios'] ) ) {
				$scen = [];
				foreach ( $raw['simulation']['scenarios'] as $i => $s ) {
					if ( ! is_array( $s ) ) { continue; }
					$key = $scalar( $s['key'] ?? chr( 65 + (int) $i ) );
					$lbl = $scalar( $s['label'] ?? '' );
					if ( '' === $lbl ) { continue; }
					$scen[] = [
						'key'     => '' !== $key ? $key : (string) ( count( $scen ) + 1 ),
						'label'   => $lbl,
						'summary' => $scalar( $s['summary'] ?? '' ),
						'rows'    => self::sanitize_pairs( $s['rows'] ?? null, [], 'label', 'value' ),
					];
				}
				if ( [] !== $scen ) { $out['simulation']['scenarios'] = $scen; }
			}
		}

		// Knowledge.
		if ( isset( $raw['knowledge'] ) && is_array( $raw['knowledge'] ) ) {
			foreach ( [ 'title', 'source', 'status' ] as $k ) {
				if ( isset( $raw['knowledge'][ $k ] ) ) { $out['knowledge'][ $k ] = $scalar( $raw['knowledge'][ $k ] ); }
			}
			foreach ( [ 'intro', 'question', 'answer', 'next_action' ] as $k ) {
				if ( isset( $raw['knowledge'][ $k ] ) ) { $out['knowledge'][ $k ] = $multi( $raw['knowledge'][ $k ] ); }
			}
			$out['knowledge']['points'] = self::sanitize_pairs( $raw['knowledge']['points'] ?? null, $def['knowledge']['points'] );
		}

		// Global.
		if ( isset( $raw['global'] ) && is_array( $raw['global'] ) ) {
			if ( isset( $raw['global']['title'] ) ) { $out['global']['title'] = $scalar( $raw['global']['title'] ); }
			foreach ( [ 'intro', 'workflow_note', 'languages_note' ] as $k ) {
				if ( isset( $raw['global'][ $k ] ) ) { $out['global'][ $k ] = $multi( $raw['global'][ $k ] ); }
			}
			if ( isset( $raw['global']['devices'] ) && is_array( $raw['global']['devices'] ) ) {
				$dev = array_values( array_filter( array_map( $scalar, $raw['global']['devices'] ), static fn( $v ): bool => '' !== $v ) );
				if ( [] !== $dev ) { $out['global']['devices'] = $dev; }
			}
		}

		// Usecases.
		if ( isset( $raw['usecases'] ) && is_array( $raw['usecases'] ) ) {
			if ( isset( $raw['usecases']['title'] ) ) { $out['usecases']['title'] = $scalar( $raw['usecases']['title'] ); }
			if ( isset( $raw['usecases']['intro'] ) ) { $out['usecases']['intro'] = $multi( $raw['usecases']['intro'] ); }
			if ( isset( $raw['usecases']['items'] ) && is_array( $raw['usecases']['items'] ) ) {
				$items = [];
				foreach ( $raw['usecases']['items'] as $it ) {
					if ( ! is_array( $it ) ) { continue; }
					$title = $scalar( $it['title'] ?? '' );
					if ( '' === $title ) { continue; }
					$items[] = [
						'tag'       => $scalar( $it['tag'] ?? '' ),
						'title'     => $title,
						'problem'   => $multi( $it['problem'] ?? '' ),
						'principle' => $multi( $it['principle'] ?? '' ),
						'benefit'   => $multi( $it['benefit'] ?? '' ),
					];
				}
				if ( [] !== $items ) { $out['usecases']['items'] = $items; }
			}
		}

		// Bridge.
		if ( isset( $raw['bridge'] ) && is_array( $raw['bridge'] ) ) {
			foreach ( [ 'title', 'cta_label' ] as $k ) {
				if ( isset( $raw['bridge'][ $k ] ) ) { $out['bridge'][ $k ] = $scalar( $raw['bridge'][ $k ] ); }
			}
			if ( isset( $raw['bridge']['intro'] ) ) { $out['bridge']['intro'] = $multi( $raw['bridge']['intro'] ); }
			if ( isset( $raw['bridge']['points'] ) && is_array( $raw['bridge']['points'] ) ) {
				$pts = array_values( array_filter( array_map( $multi, $raw['bridge']['points'] ), static fn( $v ): bool => '' !== $v ) );
				if ( [] !== $pts ) { $out['bridge']['points'] = $pts; }
			}
		}

		// Contact.
		if ( isset( $raw['contact'] ) && is_array( $raw['contact'] ) ) {
			foreach ( [ 'title', 'cta_primary_label', 'cta_secondary_label' ] as $k ) {
				if ( isset( $raw['contact'][ $k ] ) ) { $out['contact'][ $k ] = $scalar( $raw['contact'][ $k ] ); }
			}
			if ( isset( $raw['contact']['intro'] ) ) { $out['contact']['intro'] = $multi( $raw['contact']['intro'] ); }
		}

		return $out;
	}

	/**
	 * Bereinigt eine Liste aus {a:string,b:string}-Paaren; fällt bei leerer Eingabe auf Standard zurück.
	 *
	 * @param mixed                              $raw
	 * @param array<int,array<string,string>>    $fallback
	 * @return array<int,array<string,string>>
	 */
	private static function sanitize_pairs( $raw, array $fallback, string $a = 'title', string $b = 'text' ): array {
		if ( ! is_array( $raw ) ) { return $fallback; }
		$items = [];
		foreach ( $raw as $item ) {
			if ( ! is_array( $item ) ) { continue; }
			$va = sanitize_text_field( (string) ( $item[ $a ] ?? '' ) );
			if ( '' === $va ) { continue; }
			$items[] = [ $a => $va, $b => sanitize_text_field( (string) ( $item[ $b ] ?? '' ) ) ];
		}
		return [] !== $items ? $items : $fallback;
	}
}
