<?php
/**
 * Liebherr Interface Solutions – World Connections Map (Frontend-Ansicht)
 *
 * Öffentlicher Shortcode `[liw_world_connections_map]` (Liebherr-Pflichtenheft §17/LP-06).
 * Zeigt ausschließlich freigegebene Verbindungen (`ConnectionService::get_public()`,
 * `public_flag = 1`, seit alpha.1 bereits auf die unbedenklichen Felder Region/Partnertyp/
 * Status beschränkt).
 *
 * Entscheidung Joseph White, 18.09.2026: Darstellung als gruppiertes Regionen-Grid statt
 * einer geografischen Karte – `liw_connection` speichert Region nur als Freitext, keine
 * Koordinaten. Eine echte Karte hätte eine Datenmodelländerung (latitude/longitude) plus
 * Kartenbibliothek erfordert (Kategorie B, hier bewusst nicht umgesetzt). Reines
 * semantisches HTML, kein Inline-CSS/JS – Gestaltung über das zentrale Design System
 * (`liw-*`-Klassen, analog OnboardingForm).
 *
 * @package Liebherr\InterfaceWorld\Connection
 * @since   0.1.0-alpha.8
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Connection;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class ConnectionMapView {

	private const SHORTCODE = 'liw_world_connections_map';

	private const PARTNER_TYPE_LABELS = [
		'dealer'   => 'Händler',
		'supplier' => 'Lieferant',
		'customer' => 'Kunde',
	];

	private const STATUS_LABELS = [
		'planned'  => 'geplant',
		'active'   => 'aktiv',
		'inactive' => 'inaktiv',
	];

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'render_shortcode' ] );
	}

	public static function render_shortcode(): string {
		$rows = ConnectionService::get_public();

		if ( [] === $rows ) {
			return '<p class="liw-connections-map liw-connections-map--empty">' .
				esc_html__( 'Aktuell sind keine Verbindungen öffentlich freigegeben.', 'liebherr-interface-world' ) .
				'</p>';
		}

		$by_region = [];
		foreach ( $rows as $row ) {
			$region = (string) $row['region'];
			$by_region[ $region ][] = $row;
		}
		ksort( $by_region, SORT_NATURAL | SORT_FLAG_CASE );

		ob_start();
		?>
		<div class="liw-connections-map">
			<?php foreach ( $by_region as $region => $entries ) : ?>
				<section class="liw-connections-region">
					<h3 class="liw-connections-region__title"><?php echo esc_html( $region ); ?></h3>
					<ul class="liw-connections-region__list">
						<?php foreach ( $entries as $entry ) : ?>
							<?php
							$type_key   = (string) $entry['partner_type'];
							$status_key = (string) $entry['display_status'];
							?>
							<li class="liw-connections-entry liw-connections-entry--<?php echo esc_attr( $status_key ); ?>">
								<span class="liw-connections-entry__type"><?php echo esc_html( self::PARTNER_TYPE_LABELS[ $type_key ] ?? $type_key ); ?></span>
								<span class="liw-connections-entry__status"><?php echo esc_html( self::STATUS_LABELS[ $status_key ] ?? $status_key ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endforeach; ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}
}
