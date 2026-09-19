<?php
/**
 * Liebherr Adventures – Service: Erstellen, Abfragen, View-Modell (§5.1/§7/§10).
 *
 * Kapselt das Anlegen eines Adventures (mit Drei-Wörter-Ort über LocationService, Status über Policy)
 * und die sichtbarkeitsgefilterte Abfrage für Stream/Karte/Detail. Ortspräzision wird gemäß Schutzstufe
 * reduziert (§4.4/§22.11): öffentlich nur Region/gerundeter Punkt, exakt nur für Berechtigte.
 *
 * @package Liebherr\InterfaceWorld\Adventures
 * @since   0.1.0-alpha.51
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Adventures;

use Liebherr\InterfaceWorld\Adventures\Location\LocationService;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AdventureService {

	/**
	 * Legt ein Adventure an. Erwartet bereits autorisierten Kontext (Policy::can_create() im Aufrufer geprüft).
	 *
	 * @param array<string,mixed> $data title, story, type, urgency, visibility, intent(draft|submit),
	 *                                   lat, lng, words, media_id, region, protection, author_id
	 * @return array{id:int,uuid:string,status:string,words:string}
	 */
	public static function create( array $data ): array {
		$type       = Taxonomy::is_valid_type( (string) ( $data['type'] ?? '' ) ) ? (string) $data['type'] : 'field_experience';
		$urgency    = Taxonomy::is_valid_urgency( (string) ( $data['urgency'] ?? '' ) ) ? (string) $data['urgency'] : 'informative';
		$visibility = Policy::is_valid_visibility( (string) ( $data['visibility'] ?? '' ) ) ? (string) $data['visibility'] : 'organization';
		$intent     = 'draft' === ( $data['intent'] ?? 'submit' ) ? 'draft' : 'submit';
		$protection = in_array( $data['protection'] ?? '', [ 'exact', 'region', 'hidden' ], true ) ? (string) $data['protection'] : 'region';

		$status = Policy::effective_status( $intent, $urgency );

		// Drei-Wörter-Ort: aus Koordinaten kodieren, sonst aus gegebenen Wörtern dekodieren.
		$loc = null;
		if ( isset( $data['lat'], $data['lng'] ) && is_numeric( $data['lat'] ) && is_numeric( $data['lng'] ) ) {
			$loc = LocationService::encode( (float) $data['lat'], (float) $data['lng'] );
		} elseif ( '' !== ( $words = LocationService::normalize_words( (string) ( $data['words'] ?? '' ) ) ) ) {
			$dec = LocationService::decode( $words );
			if ( null !== $dec ) {
				$loc = LocationService::encode( $dec['lat'], $dec['lng'] );
			}
		}

		$post_id = wp_insert_post( [
			'post_type'    => AdventureCpt::POST_TYPE,
			'post_status'  => $status,
			'post_title'   => sanitize_text_field( (string) ( $data['title'] ?? '' ) ),
			'post_content' => wp_kses_post( (string) ( $data['story'] ?? '' ) ),
			'post_author'  => (int) ( $data['author_id'] ?? get_current_user_id() ),
		], true );

		if ( is_wp_error( $post_id ) || 0 === (int) $post_id ) {
			return [ 'id' => 0, 'uuid' => '', 'status' => 'error', 'words' => '' ];
		}
		$post_id = (int) $post_id;
		$uuid    = wp_generate_uuid4();

		update_post_meta( $post_id, AdventureCpt::M_UUID, $uuid );
		update_post_meta( $post_id, AdventureCpt::M_TYPE, $type );
		update_post_meta( $post_id, AdventureCpt::M_URGENCY, $urgency );
		update_post_meta( $post_id, AdventureCpt::M_VISIBILITY, $visibility );
		update_post_meta( $post_id, AdventureCpt::M_PROTECTION, $protection );
		update_post_meta( $post_id, AdventureCpt::M_SOLVED, 'open' );
		if ( null !== $loc ) {
			update_post_meta( $post_id, AdventureCpt::M_WORDS, $loc['words'] );
			update_post_meta( $post_id, AdventureCpt::M_LAT, (string) $loc['lat'] );
			update_post_meta( $post_id, AdventureCpt::M_LNG, (string) $loc['lng'] );
			update_post_meta( $post_id, AdventureCpt::M_PROVIDER, $loc['provider'] );
			update_post_meta( $post_id, AdventureCpt::M_PROVIDER_V, $loc['provider_version'] );
			update_post_meta( $post_id, AdventureCpt::M_ACCURACY, $loc['accuracy'] );
			update_post_meta( $post_id, AdventureCpt::M_REGION, self::region_for( $loc['lat'], $loc['lng'] ) );
		}
		$media_id = (int) ( $data['media_id'] ?? 0 );
		if ( $media_id > 0 ) {
			update_post_meta( $post_id, AdventureCpt::M_MEDIA_ID, $media_id );
			set_post_thumbnail( $post_id, $media_id );
		}
		$media_url = esc_url_raw( (string) ( $data['image_url'] ?? '' ) );
		if ( '' !== $media_url ) {
			update_post_meta( $post_id, AdventureCpt::M_MEDIA_URL, $media_url );
		}
		$machine = sanitize_text_field( (string) ( $data['machine'] ?? '' ) );
		if ( '' !== $machine ) { update_post_meta( $post_id, AdventureCpt::M_MACHINE, $machine ); }
		$component = sanitize_text_field( (string) ( $data['component'] ?? '' ) );
		if ( '' !== $component ) { update_post_meta( $post_id, AdventureCpt::M_COMPONENT, $component ); }

		return [ 'id' => $post_id, 'uuid' => $uuid, 'status' => (string) get_post_status( $post_id ), 'words' => null !== $loc ? $loc['words'] : '' ];
	}

	/**
	 * Sichtbare Adventures für den aktuellen Betrachter (Stream/Karte).
	 *
	 * @param array<string,mixed> $args type, urgency, limit, search, machine, component
	 * @return array<int,array<string,mixed>>
	 */
	public static function query( array $args = [] ): array {
		$meta = [];
		if ( Taxonomy::is_valid_type( (string) ( $args['type'] ?? '' ) ) ) {
			$meta[] = [ 'key' => AdventureCpt::M_TYPE, 'value' => (string) $args['type'] ];
		}
		if ( Taxonomy::is_valid_urgency( (string) ( $args['urgency'] ?? '' ) ) ) {
			$meta[] = [ 'key' => AdventureCpt::M_URGENCY, 'value' => (string) $args['urgency'] ];
		}
		// §18 – Maschine/Bauteil als Facetten (Teilstring-Treffer, case-insensitive).
		$machine = sanitize_text_field( (string) ( $args['machine'] ?? '' ) );
		if ( '' !== $machine ) {
			$meta[] = [ 'key' => AdventureCpt::M_MACHINE, 'value' => $machine, 'compare' => 'LIKE' ];
		}
		$component = sanitize_text_field( (string) ( $args['component'] ?? '' ) );
		if ( '' !== $component ) {
			$meta[] = [ 'key' => AdventureCpt::M_COMPONENT, 'value' => $component, 'compare' => 'LIKE' ];
		}
		$search = sanitize_text_field( (string) ( $args['search'] ?? '' ) );

		$posts = get_posts( [
			'post_type'      => AdventureCpt::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => min( 60, max( 1, (int) ( $args['limit'] ?? 24 ) ) ),
			'orderby'        => 'date',
			'order'          => 'DESC',
			's'              => $search, // Freitext über Titel/Inhalt (§18).
			// phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_query'     => [] !== $meta ? array_merge( [ 'relation' => 'AND' ], $meta ) : [],
		] );

		$out          = [];
		$is_logged_in = is_user_logged_in();
		$is_moderator = Policy::can_moderate();
		$uid          = get_current_user_id();
		foreach ( $posts as $p ) {
			if ( ! $p instanceof \WP_Post ) {
				continue;
			}
			$visibility = (string) get_post_meta( $p->ID, AdventureCpt::M_VISIBILITY, true );
			$is_author  = $uid > 0 && (int) $p->post_author === $uid;
			if ( ! Policy::can_view( (string) $p->post_status, $visibility, $is_author, $is_logged_in, $is_moderator ) ) {
				continue;
			}
			$out[] = self::to_view( $p, $is_author || $is_moderator );
		}
		return $out;
	}

	/**
	 * View-Modell einer Karte/Detailansicht. `$privileged` = darf exakte Position sehen.
	 *
	 * @return array<string,mixed>
	 */
	public static function to_view( \WP_Post $p, bool $privileged = false ): array {
		$type    = (string) get_post_meta( $p->ID, AdventureCpt::M_TYPE, true );
		$urgency = (string) get_post_meta( $p->ID, AdventureCpt::M_URGENCY, true );
		$prot    = (string) get_post_meta( $p->ID, AdventureCpt::M_PROTECTION, true );
		$lat     = (float) get_post_meta( $p->ID, AdventureCpt::M_LAT, true );
		$lng     = (float) get_post_meta( $p->ID, AdventureCpt::M_LNG, true );
		$media   = (int) get_post_meta( $p->ID, AdventureCpt::M_MEDIA_ID, true );

		// Ortspräzision gemäß Schutzstufe (§4.4).
		$show_point = 'hidden' !== $prot && ( 'exact' !== $prot || $privileged );
		$map_lat    = $show_point ? ( $privileged && 'exact' === $prot ? $lat : round( $lat, 1 ) ) : null;
		$map_lng    = $show_point ? ( $privileged && 'exact' === $prot ? $lng : round( $lng, 1 ) ) : null;

		$types   = Taxonomy::content_types();
		$urgs    = Taxonomy::urgency_levels();
		$img     = $media > 0 ? wp_get_attachment_image_url( $media, 'large' ) : get_the_post_thumbnail_url( $p->ID, 'large' );
		if ( ! is_string( $img ) || '' === $img ) {
			$img = (string) get_post_meta( $p->ID, AdventureCpt::M_MEDIA_URL, true ); // externer Fallback (MVP).
		}

		return [
			'id'            => $p->ID,
			'uuid'          => (string) get_post_meta( $p->ID, AdventureCpt::M_UUID, true ),
			'title'         => get_the_title( $p ),
			'story'         => wp_trim_words( wp_strip_all_tags( (string) $p->post_content ), 32 ),
			'type'          => $type,
			'type_label'    => $types[ $type ] ?? $type,
			'urgency'       => $urgency,
			'urgency_label' => $urgs[ $urgency ] ?? $urgency,
			'words'         => (string) get_post_meta( $p->ID, AdventureCpt::M_WORDS, true ),
			'region'        => (string) get_post_meta( $p->ID, AdventureCpt::M_REGION, true ),
			'visibility'    => (string) get_post_meta( $p->ID, AdventureCpt::M_VISIBILITY, true ),
			'solved'        => (string) get_post_meta( $p->ID, AdventureCpt::M_SOLVED, true ),
			'date'          => get_the_date( '', $p ),
			'image'         => is_string( $img ) ? $img : '',
			'map_lat'       => $map_lat,
			'map_lng'       => $map_lng,
			'token_value'   => TokenPolicy::sanitize_value( get_post_meta( $p->ID, AdventureCpt::M_TOKEN_VALUE, true ) ),
			'reg_status'    => (string) get_post_meta( $p->ID, AdventureCpt::M_REG_STATUS, true ),
			'machine'       => (string) get_post_meta( $p->ID, AdventureCpt::M_MACHINE, true ),
			'component'     => (string) get_post_meta( $p->ID, AdventureCpt::M_COMPONENT, true ),
		];
	}

	/** Grobe Weltregion aus Koordinaten (nur Anzeige, §4.1 „optional lesbare Region"). */
	public static function region_for( float $lat, float $lng ): string {
		if ( $lat >= 7 && $lng >= -170 && $lng <= -50 ) { return __( 'Nordamerika', 'liebherr-interface-world' ); }
		if ( $lat < 7 && $lng >= -90 && $lng <= -30 ) { return __( 'Südamerika', 'liebherr-interface-world' ); }
		if ( $lat >= 35 && $lng >= -25 && $lng <= 45 ) { return __( 'Europa', 'liebherr-interface-world' ); }
		if ( $lat < 35 && $lng >= -20 && $lng <= 52 ) { return __( 'Afrika', 'liebherr-interface-world' ); }
		if ( $lng > 45 && $lng <= 100 ) { return __( 'Naher/Mittlerer Osten & Südasien', 'liebherr-interface-world' ); }
		if ( $lng > 100 ) { return __( 'Asien-Pazifik', 'liebherr-interface-world' ); }
		return __( 'Weltweit', 'liebherr-interface-world' );
	}
}
