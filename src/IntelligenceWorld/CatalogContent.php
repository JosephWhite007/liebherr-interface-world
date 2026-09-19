<?php
/**
 * Liebherr Intelligence World – Katalog „Navigation & Hotels" (Pflichtenheft-2 §3/§19).
 *
 * Option `liw_iw_catalog`: die 13 Liebherr-Produktsegmente, die übergreifende Lösungswelt und die
 * sechs Hotel-Knoten – jeweils als administrierbare Daten (nichts fest im Frontend codiert, §19).
 * Jeder Knoten trägt einen Drei-Wörter-Ort (englisch, Plattformlogik) als Beispiel-Verortung (§4.3).
 *
 * PROTOTYP (§21): Segment- und Hoteltexte sowie die Drei-Wörter-Orte sind kuratierbare Beispieldaten
 * und vor Produktivbetrieb redaktionell/rechtlich freizugeben. `defaults()/get()/save()/sanitize()`
 * sind rein und ohne WordPress-Laufzeit unit-testbar (Stub-fähig).
 *
 * @package Liebherr\InterfaceWorld\IntelligenceWorld
 * @since   0.1.0-alpha.54
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\IntelligenceWorld;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class CatalogContent {

	public const OPTION = 'liw_iw_catalog';

	/** @return array<string,mixed> */
	public static function defaults(): array {
		return [
			'segments' => self::default_segments(),
			'solution' => [
				'key'      => 'solution-world',
				'label'    => __( 'Lösungswelt', 'liebherr-interface-world' ),
				'tagline'  => __( 'Segmentübergreifend', 'liebherr-interface-world' ),
				'blurb'    => __( 'Die Lösungswelt verbindet die Produktsegmente zu segmentübergreifenden Anwendungsfällen: gemeinsame Datenflüsse, Schnittstellen und Simulationen entlang der Wertschöpfungskette.', 'liebherr-interface-world' ),
				'three_words' => 'shared.linked.world',
			],
			'hotels' => self::default_hotels(),
		];
	}

	/**
	 * Die 13 Liebherr-Produktsegmente als Globus-Knoten (Beispieldaten, kuratierbar).
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function default_segments(): array {
		return [
			[ 'key' => 'earthmoving',     'label' => __( 'Erdbewegung', 'liebherr-interface-world' ),                     'tagline' => __( 'Bagger, Radlader, Planierraupen', 'liebherr-interface-world' ),          'blurb' => __( 'Hydraulikbagger, Radlader und Raupen für Bau, Umschlag und Materialgewinnung.', 'liebherr-interface-world' ),                 'three_words' => 'moving.solid.ground' ],
			[ 'key' => 'material-handling','label' => __( 'Materialumschlag', 'liebherr-interface-world' ),                'tagline' => __( 'Umschlagtechnik', 'liebherr-interface-world' ),                            'blurb' => __( 'Umschlagmaschinen für Häfen, Recycling und Industrie – auf Effizienz getrimmte Materialflüsse.', 'liebherr-interface-world' ), 'three_words' => 'lifting.flowing.loads' ],
			[ 'key' => 'deep-foundation', 'label' => __( 'Spezialtiefbau', 'liebherr-interface-world' ),                  'tagline' => __( 'Gründung & Bohrtechnik', 'liebherr-interface-world' ),                     'blurb' => __( 'Drehbohr-, Seilbagger- und Rammtechnik für anspruchsvolle Gründungen.', 'liebherr-interface-world' ),                          'three_words' => 'drilling.deep.roots' ],
			[ 'key' => 'mining',          'label' => __( 'Mining', 'liebherr-interface-world' ),                          'tagline' => __( 'Bergbau-Großgeräte', 'liebherr-interface-world' ),                          'blurb' => __( 'Großbagger und Muldenkipper für den wirtschaftlichen Rohstoffabbau im Tagebau.', 'liebherr-interface-world' ),                'three_words' => 'vast.open.pit' ],
			[ 'key' => 'mobile-cranes',   'label' => __( 'Fahrzeugkrane', 'liebherr-interface-world' ),                   'tagline' => __( 'Mobil- & Raupenkrane', 'liebherr-interface-world' ),                        'blurb' => __( 'Mobil- und Raupenkrane für flexible Hübe auf wechselnden Baustellen.', 'liebherr-interface-world' ),                          'three_words' => 'rolling.reach.high' ],
			[ 'key' => 'tower-cranes',    'label' => __( 'Turmdrehkrane', 'liebherr-interface-world' ),                   'tagline' => __( 'Baustellenkrane', 'liebherr-interface-world' ),                             'blurb' => __( 'Turmdrehkrane in vielen Bauarten – das vertraute Bild jeder größeren Baustelle.', 'liebherr-interface-world' ),              'three_words' => 'tall.turning.tower' ],
			[ 'key' => 'concrete',        'label' => __( 'Betontechnik', 'liebherr-interface-world' ),                    'tagline' => __( 'Mischen & Fördern', 'liebherr-interface-world' ),                           'blurb' => __( 'Fahrmischer, Mischanlagen und Betonpumpen für zuverlässige Betonversorgung.', 'liebherr-interface-world' ),                  'three_words' => 'mixing.fresh.stone' ],
			[ 'key' => 'maritime-cranes', 'label' => __( 'Maritime Krane', 'liebherr-interface-world' ),                  'tagline' => __( 'Hafen- & Schiffskrane', 'liebherr-interface-world' ),                       'blurb' => __( 'Schiffs-, Hafenmobil- und Offshore-Krane für den weltweiten maritimen Umschlag.', 'liebherr-interface-world' ),             'three_words' => 'harbor.salt.reach' ],
			[ 'key' => 'aerospace',       'label' => __( 'Aerospace & Verkehrstechnik', 'liebherr-interface-world' ),     'tagline' => __( 'Flug- & Bahnsysteme', 'liebherr-interface-world' ),                         'blurb' => __( 'Flugsteuerungs-, Fahrwerks- und Klimasysteme für Luftfahrt und Verkehr.', 'liebherr-interface-world' ),                       'three_words' => 'flying.thin.air' ],
			[ 'key' => 'machine-tools',   'label' => __( 'Werkzeugmaschinen & Automation', 'liebherr-interface-world' ), 'tagline' => __( 'Verzahnung & Automatisierung', 'liebherr-interface-world' ),               'blurb' => __( 'Verzahnmaschinen und Automationssysteme für die industrielle Fertigung.', 'liebherr-interface-world' ),                       'three_words' => 'precise.cut.gears' ],
			[ 'key' => 'components',      'label' => __( 'Komponenten', 'liebherr-interface-world' ),                     'tagline' => __( 'Antriebe, Hydraulik, Elektronik', 'liebherr-interface-world' ),            'blurb' => __( 'Großwälzlager, Hydraulik, Antriebstechnik und Elektronik als Systembausteine.', 'liebherr-interface-world' ),                'three_words' => 'core.moving.parts' ],
			[ 'key' => 'appliances',      'label' => __( 'Kühl- & Gefriergeräte', 'liebherr-interface-world' ),           'tagline' => __( 'Haushalt & Gewerbe', 'liebherr-interface-world' ),                          'blurb' => __( 'Kühl- und Gefriergeräte für Haushalt, Handel und Labor – Frische mit System.', 'liebherr-interface-world' ),                 'three_words' => 'cold.kept.fresh' ],
			[ 'key' => 'refrigeration-tech','label' => __( 'Kältetechnik', 'liebherr-interface-world' ),                  'tagline' => __( 'Industrielle Kühlung', 'liebherr-interface-world' ),                        'blurb' => __( 'Komponenten und Systeme für industrielle und medizinische Kälteanwendungen.', 'liebherr-interface-world' ),                  'three_words' => 'chilled.clean.chain' ],
		];
	}

	/**
	 * Sechs Hotel-Knoten (Beispieldaten, vor Produktivbetrieb kuratieren, §21).
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function default_hotels(): array {
		return [
			[ 'key' => 'hotel-1', 'name' => __( 'Interalpen-Hotel Tyrol', 'liebherr-interface-world' ),  'place' => __( 'Telfs-Buchen, Österreich', 'liebherr-interface-world' ), 'blurb' => __( 'Alpines Fünf-Sterne-Resort als Erlebnis- und Simulationsknoten der Hotelwelt.', 'liebherr-interface-world' ),          'three_words' => 'quiet.alpine.retreat' ],
			[ 'key' => 'hotel-2', 'name' => __( 'Löwen Hotel Montafon', 'liebherr-interface-world' ),     'place' => __( 'Schruns, Österreich', 'liebherr-interface-world' ),      'blurb' => __( 'Berg- und Wellnesshotel im Montafon als Knoten der Hotelwelt.', 'liebherr-interface-world' ),                          'three_words' => 'valley.warm.stone' ],
			[ 'key' => 'hotel-3', 'name' => __( 'Hotel Edelweiss Berchtesgaden', 'liebherr-interface-world' ), 'place' => __( 'Berchtesgaden, Deutschland', 'liebherr-interface-world' ), 'blurb' => __( 'Panoramahotel in den Berchtesgadener Alpen als Knoten der Hotelwelt.', 'liebherr-interface-world' ),               'three_words' => 'peak.clear.view' ],
			[ 'key' => 'hotel-4', 'name' => __( 'Hotel Savoyen Vienna', 'liebherr-interface-world' ),      'place' => __( 'Wien, Österreich', 'liebherr-interface-world' ),         'blurb' => __( 'Stadthotel in Wien als urbaner Knoten der Hotelwelt.', 'liebherr-interface-world' ),                                  'three_words' => 'city.grand.hall' ],
			[ 'key' => 'hotel-5', 'name' => __( 'Hotel (Platzhalter 5)', 'liebherr-interface-world' ),     'place' => __( 'Standort folgt', 'liebherr-interface-world' ),           'blurb' => __( 'Platzhalter-Knoten – Name, Standort und Verortung werden im Backoffice gepflegt.', 'liebherr-interface-world' ),      'three_words' => 'to.be.curated' ],
			[ 'key' => 'hotel-6', 'name' => __( 'Hotel (Platzhalter 6)', 'liebherr-interface-world' ),     'place' => __( 'Standort folgt', 'liebherr-interface-world' ),           'blurb' => __( 'Platzhalter-Knoten – Name, Standort und Verortung werden im Backoffice gepflegt.', 'liebherr-interface-world' ),      'three_words' => 'to.be.curated' ],
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
	 * @param array<string,mixed> $raw
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $raw ): array {
		$def = self::defaults();
		$out = $def;

		if ( isset( $raw['segments'] ) && is_array( $raw['segments'] ) ) {
			$out['segments'] = self::sanitize_nodes( $raw['segments'], [ 'key', 'label', 'tagline', 'blurb', 'three_words' ] );
			if ( [] === $out['segments'] ) { $out['segments'] = $def['segments']; }
		}

		if ( isset( $raw['solution'] ) && is_array( $raw['solution'] ) ) {
			$one = self::sanitize_nodes( [ $raw['solution'] ], [ 'key', 'label', 'tagline', 'blurb', 'three_words' ] );
			if ( [] !== $one ) { $out['solution'] = $one[0]; }
		}

		if ( isset( $raw['hotels'] ) && is_array( $raw['hotels'] ) ) {
			$out['hotels'] = self::sanitize_nodes( $raw['hotels'], [ 'key', 'name', 'place', 'blurb', 'three_words' ] );
			if ( [] === $out['hotels'] ) { $out['hotels'] = $def['hotels']; }
		}

		return $out;
	}

	/**
	 * Säubert eine Liste von Knoten auf die erlaubten Felder. Mehrzeiliges nur für `blurb`.
	 *
	 * @param array<int|string,mixed> $rows
	 * @param array<int,string>       $fields
	 * @return array<int,array<string,string>>
	 */
	private static function sanitize_nodes( array $rows, array $fields ): array {
		$out = [];
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) { continue; }
			$node = [];
			foreach ( $fields as $f ) {
				$val = isset( $row[ $f ] ) ? (string) $row[ $f ] : '';
				if ( 'three_words' === $f ) {
					$node[ $f ] = self::sanitize_three_words( $val );
				} elseif ( 'blurb' === $f ) {
					$node[ $f ] = sanitize_textarea_field( $val );
				} else {
					$node[ $f ] = sanitize_text_field( $val );
				}
			}
			// Ein Knoten braucht mindestens einen Anzeigenamen (label ODER name).
			if ( '' !== ( $node['label'] ?? '' ) || '' !== ( $node['name'] ?? '' ) ) {
				$out[] = $node;
			}
		}
		return $out;
	}

	/**
	 * Normalisiert einen Drei-Wörter-Ort: englisch, kleingeschrieben, `word.word.word`.
	 * Ungültige Eingaben liefern einen leeren String (das Frontend blendet die Verortung dann aus).
	 */
	public static function sanitize_three_words( string $value ): string {
		$value = strtolower( trim( $value ) );
		$value = str_replace( [ ' ', '/', ',', '·' ], '.', $value );
		$parts = array_values( array_filter( array_map(
			static fn( string $p ): string => preg_replace( '/[^a-z]/', '', $p ) ?? '',
			explode( '.', $value )
		), static fn( string $p ): bool => '' !== $p ) );
		return count( $parts ) === 3 ? implode( '.', $parts ) : '';
	}

	/** @return array<int,array<string,string>> */
	public static function segments(): array {
		$c = self::get();
		return is_array( $c['segments'] ?? null ) ? $c['segments'] : [];
	}

	/** @return array<string,string> */
	public static function solution_world(): array {
		$c = self::get();
		return is_array( $c['solution'] ?? null ) ? $c['solution'] : [];
	}

	/** @return array<int,array<string,string>> */
	public static function hotels(): array {
		$c = self::get();
		return is_array( $c['hotels'] ?? null ) ? $c['hotels'] : [];
	}
}
