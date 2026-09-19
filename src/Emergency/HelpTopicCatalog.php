<?php
/**
 * Liebherr Emergency – kontextbezogener Hilfe-Themenkatalog (der Inhalt der Emergency-Area).
 *
 * Der Hilfe-Koffer ist überall erreichbar; nach der einstelligen Rechenaufgabe zeigt die Emergency-Area
 * eine zum aktuellen Ort passende Anleitung („auf welchem Tool bin ich gerade?"). Das erfüllt die
 * Plattformregel „zu jedem Tool gehört ein erreichbarer Anleitungs-Knopf". Fällt kein Kontext, gilt die
 * allgemeine, sicherheitsorientierte Schrittfolge – diese bleibt als SSOT im GetHelpAssistant und wird hier
 * NICHT dupliziert, sondern referenziert. Reine, ohne WordPress testbare Logik (Übersetzung übernimmt die View).
 *
 * @package Liebherr\InterfaceWorld\Emergency
 * @since   0.1.0-alpha.78
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Emergency;

use Liebherr\InterfaceWorld\Adventures\GetHelpAssistant;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class HelpTopicCatalog {

	public const DEFAULT_KEY = 'default';

	/**
	 * Bestimmt den Kontext-Schlüssel aus Ort/Bildschirm.
	 *
	 * @param string $path       Frontend-Pfad (z. B. "/intelligence-world/") oder "".
	 * @param bool   $is_admin   Ob wir im wp-admin sind.
	 * @param string $admin_page Admin-Seiten-Slug (?page=…), z. B. "liw-adventures".
	 */
	public static function detect( string $path, bool $is_admin, string $admin_page = '' ): string {
		$hay = strtolower( $is_admin ? $admin_page : $path );
		$map = [
			'intelligence' => 'intelligence-world',
			'adventure'    => 'adventures',
			'local'        => 'local-intelligence',
			'simulation'   => 'intelligence-world',
			'workboard'    => 'adventures',
		];
		foreach ( $map as $needle => $key ) {
			if ( '' !== $hay && str_contains( $hay, $needle ) ) {
				return $key;
			}
		}
		if ( $is_admin ) {
			return 'admin';
		}
		return self::DEFAULT_KEY;
	}

	/**
	 * Liefert das Thema (Titel, Einleitung, Schritte) zu einem Kontext-Schlüssel.
	 *
	 * @return array{key:string,title:string,intro:string,steps:array<int,array{title:string,text:string}>}
	 */
	public static function topic( string $key ): array {
		$topics = self::topics();
		$key     = isset( $topics[ $key ] ) ? $key : self::DEFAULT_KEY;
		return [ 'key' => $key ] + $topics[ $key ];
	}

	/**
	 * Alle Themen. Die allgemeinen Schritte stammen als SSOT aus dem GetHelpAssistant.
	 *
	 * @return array<string,array{title:string,intro:string,steps:array<int,array{title:string,text:string}>}>
	 */
	public static function topics(): array {
		$general = self::general_steps();
		return [
			'default' => [
				'title' => __( 'Emergency-Area · Hilfe', 'liebherr-interface-world' ),
				'intro' => __( 'Sie sind im Hilfe-Koffer. Wählen Sie einen Schritt – im Notfall gilt: zuerst sichern, dann melden.', 'liebherr-interface-world' ),
				'steps' => $general,
			],
			'admin' => [
				'title' => __( 'Emergency-Area · Backoffice-Hilfe', 'liebherr-interface-world' ),
				'intro' => __( 'Hilfe zum Backoffice. Jedes Modul hat einen Anleitungs-Knopf; hier finden Sie den schnellen Einstieg.', 'liebherr-interface-world' ),
				'steps' => array_merge(
					[ [ 'title' => __( 'Wo bin ich?', 'liebherr-interface-world' ), 'text' => __( 'Sie sind im WordPress-Backoffice der Liebherr-Plattform. Das linke Menü führt zu den Modulen (Intelligence World, Adventures, …).', 'liebherr-interface-world' ) ] ],
					$general
				),
			],
			'intelligence-world' => [
				'title' => __( 'Emergency-Area · Intelligence World', 'liebherr-interface-world' ),
				'intro' => __( 'Hilfe zur Intelligence World: Zugang, Segmente/Hotels, Simulation und Sitzungsprotokoll.', 'liebherr-interface-world' ),
				'steps' => array_merge(
					[
						[ 'title' => __( 'Eintritt & Zugangscode', 'liebherr-interface-world' ), 'text' => __( 'Der Eintritt erfolgt über den Zugangscode. Danach starten Sitzung und Abrechnung (pro Sekunde).', 'liebherr-interface-world' ) ],
						[ 'title' => __( 'Simulation nutzen', 'liebherr-interface-world' ), 'text' => __( 'Über den Simulations-Baustein Prognosen rechnen (konservativ/basis/ambitioniert) und Varianten vergleichen.', 'liebherr-interface-world' ) ],
					],
					$general
				),
			],
			'adventures' => [
				'title' => __( 'Emergency-Area · Adventures', 'liebherr-interface-world' ),
				'intro' => __( 'Hilfe zu Adventures: Beitrag erstellen, Tokenwert setzen, Freigabe/Registrierung im Artikelbook.', 'liebherr-interface-world' ),
				'steps' => array_merge(
					[
						[ 'title' => __( 'Beitrag erstellen', 'liebherr-interface-world' ), 'text' => __( 'Über die Workboard-Maske Maschine/Bauteil, Beschreibung, Tokenwert und Nutzungsumfang angeben und die Rechte bestätigen.', 'liebherr-interface-world' ) ],
						[ 'title' => __( 'Zugriff mit Token', 'liebherr-interface-world' ), 'text' => __( 'Kostenpflichtige Inhalte werden erst nach Tokenakzeptanz geöffnet; Autoren sehen ihre eigenen Beiträge frei.', 'liebherr-interface-world' ) ],
					],
					$general
				),
			],
			'local-intelligence' => [
				'title' => __( 'Emergency-Area · Local Intelligence', 'liebherr-interface-world' ),
				'intro' => __( 'Hilfe zur Local Intelligence und den Szenarien.', 'liebherr-interface-world' ),
				'steps' => $general,
			],
		];
	}

	/**
	 * Allgemeine Sicherheits-/Notfallschritte – SSOT ist der GetHelpAssistant (keine Redundanz).
	 *
	 * @return array<int,array{title:string,text:string}>
	 */
	private static function general_steps(): array {
		$out = [];
		foreach ( GetHelpAssistant::steps() as $s ) {
			$out[] = [ 'title' => (string) $s['title'], 'text' => (string) $s['text'] ];
		}
		return $out;
	}
}
