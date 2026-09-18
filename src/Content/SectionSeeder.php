<?php
/**
 * Liebherr Interface Solutions – Section Seeder
 *
 * Legt fehlende Standard-Abschnitte LP-01…LP-14 (`SectionBlueprint`) als `liw_section`-
 * Entwürfe an. Idempotent: die Zuordnung läuft über das Post-Meta `_liw_lp_code`; bereits
 * vorhandene Codes werden übersprungen, unabhängig von Titel oder Status (die Redaktion darf
 * Titel frei ändern, ohne dass der Seeder Dubletten erzeugt). Löscht und überschreibt nie.
 *
 * Kein eigenes Datenmodell (CLAUDE.md Abschnitt 5): nutzt wp_insert_post(), Post-Meta und den
 * nativen Draft-Status; Reihenfolge über `menu_order` (ANNAHME-LIW-6). Jede Anlage wird über
 * CoreBridge\AuditBridge protokolliert (SEC-005).
 *
 * @package Liebherr\InterfaceWorld\Content
 * @since   0.1.0-alpha.17
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Content;

use Liebherr\InterfaceWorld\CoreBridge\AuditBridge;
use Liebherr\InterfaceWorld\CPT\LiwSectionCpt;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SectionSeeder {

	/**
	 * Codes, zu denen bereits ein Abschnitt existiert (beliebiger Status, inkl. Papierkorb –
	 * ein bewusst gelöschter Abschnitt wird nicht ungefragt neu angelegt).
	 *
	 * @return array<string, int> code → post_id
	 */
	public static function existing_codes(): array {
		$ids = get_posts( [
			'post_type'      => LiwSectionCpt::POST_TYPE,
			'post_status'    => array_keys( get_post_stati() ), // alle registrierten Status inkl. trash (anders als 'any', das trash/auto-draft ausschließt).
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => SectionBlueprint::META_CODE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- max. 14 Beiträge, kuratierte Menge.
		] );

		$map = [];
		foreach ( $ids as $id ) {
			$code = (string) get_post_meta( (int) $id, SectionBlueprint::META_CODE, true );
			if ( '' !== $code && ! isset( $map[ $code ] ) ) {
				$map[ $code ] = (int) $id;
			}
		}
		return $map;
	}

	/** @return string[] Codes aus dem Bauplan ohne vorhandenen Abschnitt, in Seitenreihenfolge. */
	public static function missing_codes(): array {
		$existing = self::existing_codes();
		return array_values( array_filter(
			array_keys( SectionBlueprint::all() ),
			static fn( string $code ): bool => ! isset( $existing[ $code ] )
		) );
	}

	/**
	 * Legt alle fehlenden Abschnitte an.
	 *
	 * @return array{created: array<string, int>, skipped: string[], errors: array<string, string>}
	 */
	public static function seed_missing( int $actor_id ): array {
		$result = [ 'created' => [], 'skipped' => [], 'errors' => [] ];
		$missing = self::missing_codes();

		foreach ( array_keys( SectionBlueprint::all() ) as $code ) {
			if ( ! in_array( $code, $missing, true ) ) {
				$result['skipped'][] = $code;
				continue;
			}

			$post_id = self::create( $code, $actor_id );
			if ( is_wp_error( $post_id ) ) {
				$result['errors'][ $code ] = $post_id->get_error_message();
				continue;
			}
			$result['created'][ $code ] = $post_id;
		}

		return $result;
	}

	/** Legt genau einen Abschnitt an (ohne Existenzprüfung – Aufrufer prüft über missing_codes()). */
	public static function create( string $code, int $actor_id ): int|\WP_Error {
		$section = SectionBlueprint::all()[ $code ] ?? null;
		if ( null === $section ) {
			return new \WP_Error( 'liw_unknown_code', __( 'Unbekannter Abschnitts-Code.', 'liebherr-interface-world' ) );
		}

		$post_id = wp_insert_post( [
			'post_type'    => LiwSectionCpt::POST_TYPE,
			'post_status'  => 'draft',
			'post_title'   => $section['title'],
			'post_content' => SectionBlueprint::draft_content( $code ),
			'menu_order'   => SectionBlueprint::menu_order_for( $code ),
			'post_author'  => $actor_id > 0 ? $actor_id : 0,
			'meta_input'   => [ SectionBlueprint::META_CODE => $code ],
		], true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		AuditBridge::log( 'create', 'section', (int) $post_id, [], [ 'lp_code' => $code, 'title' => $section['title'], 'source' => 'blueprint' ], $actor_id );
		return (int) $post_id;
	}
}
