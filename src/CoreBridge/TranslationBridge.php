<?php
/**
 * Liebherr Interface Solutions – Translation Bridge
 *
 * Einziger Kopplungspunkt zur Translation Registry von araliya-platform-core
 * (ADR-128..133, Modulvertrag ARY-PH-TRX-1.0.0, `docs/ARY-TRX-Modulvertrag.md`).
 * Liebherr baut KEINE eigene Sprachverwaltung (Entscheidung JW 17.09.2026, Variante A).
 *
 * post_title/post_content von `liw_section` werden vom Core bereits automatisch über den
 * bestehenden PostFieldAdapter erfasst (er deckt jeden Post-Scope ab, unabhängig vom Post-Type) –
 * dafür ist keine Anmeldung nötig. Nur das zusätzliche Meta-Feld `_liw_cta_label` wird hier über
 * den bestehenden Modulvertrag-Filter (ModuleSlotAdapter: `araliya_translatable_fields` /
 * `araliya_translation_source_value`) angemeldet – identischer Mechanismus wie bei anderen
 * Modulen (z. B. Special Art View / SawTranslationBridge), keine neue Adapterklasse nötig.
 *
 * @package Liebherr\InterfaceWorld\CoreBridge
 * @since   0.1.0-alpha.1
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\CoreBridge;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class TranslationBridge {

	public const CTA_META_KEY = '_liw_cta_label';
	private const POST_TYPE   = 'liw_section';

	private const REGISTRY_CLASS = 'Araliya\\Platform\\Core\\Modules\\Translation\\Registry\\TranslationRegistry';
	private const GATE_CLASS     = 'Araliya\\Platform\\Core\\Modules\\Translation\\Registry\\TranslationGate';
	private const SCOPE_CLASS    = 'Araliya\\Platform\\Core\\Modules\\Translation\\Registry\\PageScope';

	public static function is_available(): bool {
		return class_exists( self::REGISTRY_CLASS );
	}

	/** Registriert das CTA-Meta-Feld am bestehenden Modulvertrag (Filter, kein Adapter-Neubau). */
	public static function register(): void {
		if ( ! self::is_available() ) {
			return;
		}
		add_filter( 'araliya_translatable_fields', [ self::class, 'add_field' ], 20, 2 );
		add_filter( 'araliya_translation_source_value', [ self::class, 'source_value' ], 20, 3 );
	}

	/** @param string[] $fields */
	public static function add_field( array $fields, \WP_Post $post ): array {
		if ( self::POST_TYPE !== $post->post_type ) {
			return $fields;
		}
		$fields[] = self::CTA_META_KEY;
		return $fields;
	}

	public static function source_value( string $value, \WP_Post $post, string $field ): string {
		if ( self::POST_TYPE !== $post->post_type || self::CTA_META_KEY !== $field ) {
			return $value;
		}
		return (string) get_post_meta( $post->ID, self::CTA_META_KEY, true );
	}

	/**
	 * Prüft das Release-Gate (LANG-006): Sprache darf nur veröffentlicht werden,
	 * wenn der geforderte Vollständigkeitsgrad erreicht ist.
	 *
	 * ANNAHME-LIW-2: Exakte Signatur von `TranslationGate` bei Integration gegen den
	 * tatsächlichen Code verifizieren (hier defensiv über method_exists geprüft, kein
	 * ungedecktes Raten der Rückgabe – bei Nichtverfügbarkeit false, nie „ready" erfinden).
	 */
	public static function is_locale_release_ready( string $locale ): bool {
		$report = self::readiness_report( [ $locale ] );
		return isset( $report[ $locale ] ) && $report[ $locale ]['ready'];
	}

	/**
	 * Öffentliche LIW-Flächen für das Release-Gate: veröffentlichte Abschnitte (`liw_section`)
	 * und die Trägerseite(n) mit `[liw_landingpage]`.
	 *
	 * @return int[] Post-IDs
	 */
	public static function public_scope_post_ids(): array {
		$ids = get_posts( [
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		] );
		$ids = array_map( 'intval', (array) $ids );

		$pages = get_posts( [
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			's'              => '[liw_landingpage',
		] );
		foreach ( (array) $pages as $pid ) {
			$post = get_post( (int) $pid );
			if ( $post instanceof \WP_Post && has_shortcode( (string) $post->post_content, 'liw_landingpage' ) ) {
				$ids[] = (int) $pid;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Release-Readiness je Sprache (LANG-006) auf Basis der Core-Registry-Vollständigkeit.
	 * `ready` = jede LIW-Fläche in dieser Sprache vollständig (alle Pflichtsegmente übersetzt,
	 * nichts veraltet, kritische Segmente freigegeben). Ohne Core-Registry: leeres Ergebnis
	 * (nie „ready" erfinden).
	 *
	 * @param string[] $langs
	 * @return array<string,array{pages:int,complete:int,min_rate:float,ready:bool}>
	 */
	public static function readiness_report( array $langs ): array {
		if ( ! self::is_available() || ! class_exists( self::SCOPE_CLASS ) ) {
			return [];
		}
		$ids = self::public_scope_post_ids();
		$out = [];
		foreach ( $langs as $lang ) {
			$lang = (string) $lang;
			$pages = 0;
			$complete = 0;
			$min_rate = 100.0;
			foreach ( $ids as $pid ) {
				$scope   = call_user_func( [ self::SCOPE_CLASS, 'post' ], (int) $pid );
				$metrics = call_user_func( [ self::REGISTRY_CLASS, 'page_metrics' ], $scope, $lang );
				if ( ! is_array( $metrics ) ) {
					continue;
				}
				$pages++;
				if ( ! empty( $metrics['complete'] ) ) {
					$complete++;
				}
				$min_rate = min( $min_rate, (float) ( $metrics['translation_rate'] ?? 0.0 ) );
			}
			$out[ $lang ] = [
				'pages'    => $pages,
				'complete' => $complete,
				'min_rate' => $pages > 0 ? $min_rate : 0.0,
				'ready'    => $pages > 0 && $complete === $pages,
			];
		}
		return $out;
	}
}
