<?php
/**
 * Liebherr Adventures – Backoffice-Board (Basislogik §7/§8, alpha.60).
 *
 * Backend-Pendant zur Frontend-Insel: dieselbe Eingabemaske ({@see SubmissionForm}) in Workboard-Optik plus
 * ein Board über alle Beiträge (Status, Tokenwert, Artikelbook-Referenz, Ersteller) mit Moderations-Aktionen
 * (Validierung, Freigabe im Liebherr-World-Netz, Sperren/Archivieren) über die REST-Route `liw-adv/v1/moderate`.
 *
 * @package Liebherr\InterfaceWorld\Admin\Pages
 * @since   0.1.0-alpha.60
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Admin\Pages;

use Liebherr\InterfaceWorld\Adventures\AdventureCpt;
use Liebherr\InterfaceWorld\Adventures\RegistrationService;
use Liebherr\InterfaceWorld\Adventures\RegistrationStatus;
use Liebherr\InterfaceWorld\Adventures\Rest;
use Liebherr\InterfaceWorld\Adventures\SubmissionForm;
use Liebherr\InterfaceWorld\Adventures\Taxonomy;
use Liebherr\InterfaceWorld\Adventures\Policy;
use Liebherr\InterfaceWorld\Branding\BrandTokens;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class AdventureBoardPage {

	public const MENU_SLUG = 'liw-adventure-board';

	public static function register(): void {
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue' ] );
	}

	public static function enqueue( string $hook ): void {
		if ( ! isset( $_GET['page'] ) || self::MENU_SLUG !== sanitize_key( (string) $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reiner Seiten-Check.
			return;
		}
		$css = 'assets/css/liw-adventures.css';
		$js  = 'assets/js/liw-adventures.js';
		wp_enqueue_style( 'liw-adventures', LIW_URL . $css, [], self::ver( $css ) );
		wp_add_inline_style( 'liw-adventures', BrandTokens::css_root() );
		wp_enqueue_script( 'liw-adventures', LIW_URL . $js, [], self::ver( $js ), true );
		wp_localize_script( 'liw-adventures', 'liwAdv', [
			'rest'      => esc_url_raw( rest_url( Rest::NAMESPACE . '/' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'canCreate' => Policy::can_create(),
			'types'     => Taxonomy::content_types(),
			'urgencies' => Taxonomy::urgency_levels(),
			'i18n'      => [
				'locating'    => __( 'Standort wird ermittelt …', 'liebherr-interface-world' ),
				'located'     => __( 'Ort ermittelt', 'liebherr-interface-world' ),
				'geoErr'      => __( 'Standort nicht verfügbar – bitte Koordinaten eingeben.', 'liebherr-interface-world' ),
				'saving'      => __( 'Wird gespeichert …', 'liebherr-interface-world' ),
				'needTitle'   => __( 'Titel erforderlich.', 'liebherr-interface-world' ),
				'needRights'  => __( 'Bitte die Rechte-Zusicherung bestätigen.', 'liebherr-interface-world' ),
				'articlebook' => __( 'Artikelbook', 'liebherr-interface-world' ),
			],
		] );
		// Kleines Moderations-Skript (inline; nutzt liwAdv.rest/nonce).
		wp_add_inline_script( 'liw-adventures', self::board_js() );
	}

	private static function ver( string $rel ): string {
		$m = is_readable( LIW_PATH . $rel ) ? (int) filemtime( LIW_PATH . $rel ) : 0;
		return $m > 0 ? (string) $m : LIW_VERSION;
	}

	public static function render(): void {
		if ( ! Policy::can_moderate() && ! Policy::can_create() ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'liebherr-interface-world' ) );
		}
		$can_moderate = Policy::can_moderate();
		echo '<div class="wrap liw-adv liw-adv--admin" data-liw-adv>';
		echo '<h1>' . esc_html__( 'Liebherr Adventures – Board', 'liebherr-interface-world' ) . '</h1>';
		echo '<p>' . esc_html__( 'Eingabemaske (Workboard-Optik) und Board über alle Beiträge mit Tokenwert, Status und Artikelbook-Referenz. Registrierung, Validierung und Freigabe werden revisionssicher protokolliert.', 'liebherr-interface-world' ) . '</p>';

		// Gemeinsame Eingabemaske (Backend-Kontext).
		echo SubmissionForm::render( 'admin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- in SubmissionForm escaped.

		// Board-Liste.
		echo '<h2 class="liw-wb__board-title">' . esc_html__( 'Beiträge', 'liebherr-interface-world' ) . '</h2>';
		self::render_board( $can_moderate );
		echo '</div>';
	}

	private static function render_board( bool $can_moderate ): void {
		$posts = get_posts( [
			'post_type'   => AdventureCpt::POST_TYPE,
			'post_status' => [ 'draft', 'pending', 'publish', 'private' ],
			'numberposts' => 100,
			'orderby'     => 'modified',
			'order'       => 'DESC',
		] );
		if ( [] === $posts ) {
			echo '<p>' . esc_html__( 'Noch keine Beiträge.', 'liebherr-interface-world' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped liw-wb__board"><thead><tr>';
		foreach ( [ __( 'Titel', 'liebherr-interface-world' ), __( 'Status', 'liebherr-interface-world' ), __( 'Tokenwert', 'liebherr-interface-world' ), __( 'Version', 'liebherr-interface-world' ), __( 'Artikelbook', 'liebherr-interface-world' ), __( 'Aktionen', 'liebherr-interface-world' ) ] as $th ) {
			echo '<th>' . esc_html( $th ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $posts as $p ) {
			$reg = RegistrationService::get_registration( (int) $p->ID );
			echo '<tr>';
			echo '<td>' . esc_html( get_the_title( $p ) ) . '</td>';
			echo '<td><span class="liw-wb__status liw-wb__status--' . esc_attr( (string) $reg['status'] ) . '">' . esc_html( (string) $reg['status_label'] ) . '</span></td>';
			echo '<td>' . esc_html( (string) (int) $reg['token_value'] ) . '</td>';
			echo '<td>' . esc_html( (string) (int) $reg['version'] ) . '</td>';
			echo '<td>' . ( '' !== (string) $reg['articlebook_ref']
				? ( '' !== (string) $reg['articlebook_url']
					? '<a href="' . esc_url( (string) $reg['articlebook_url'] ) . '" target="_blank" rel="noopener">' . esc_html( (string) $reg['articlebook_ref'] ) . '</a>'
					: esc_html( (string) $reg['articlebook_ref'] ) )
				: '—' ) . '</td>';
			echo '<td class="liw-wb__row-actions">';
			if ( $can_moderate ) {
				$status = (string) $reg['status'];
				if ( RegistrationStatus::VALIDATION_REQUESTED === $status ) {
					self::action_btn( (int) $p->ID, 'validate_pass', __( 'Validieren ✓', 'liebherr-interface-world' ) );
					self::action_btn( (int) $p->ID, 'validate_fail', __( 'Nachbesserung', 'liebherr-interface-world' ) );
				}
				if ( RegistrationStatus::VALIDATED === $status ) {
					self::action_btn( (int) $p->ID, 'publish', __( 'Für World freigeben', 'liebherr-interface-world' ) );
				}
				if ( RegistrationStatus::usable_in_company_net( $status ) ) {
					self::action_btn( (int) $p->ID, 'block', __( 'Sperren', 'liebherr-interface-world' ) );
				}
				self::action_btn( (int) $p->ID, 'archive', __( 'Archivieren', 'liebherr-interface-world' ) );
			} else {
				echo '—';
			}
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '<p class="liw-wb__board-msg" data-liw-adv-board-msg role="status"></p>';
	}

	private static function action_btn( int $post_id, string $action, string $label ): void {
		printf(
			'<button type="button" class="button button-small liw-wb__act" data-liw-adv-mod="%1$s" data-post="%2$d">%3$s</button> ',
			esc_attr( $action ),
			(int) $post_id,
			esc_html( $label )
		);
	}

	/** Inline-JS: Moderations-Buttons → REST `moderate`, danach Seite neu laden. */
	private static function board_js(): string {
		return <<<'JS'
(function(){
	if(!window.liwAdv){return;}
	function post(action,postId){
		return fetch(liwAdv.rest+'moderate',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':liwAdv.nonce},body:JSON.stringify({post_id:postId,action:action})}).then(function(r){return r.json();});
	}
	document.addEventListener('click',function(e){
		var b=e.target.closest('[data-liw-adv-mod]');
		if(!b){return;}
		var msg=document.querySelector('[data-liw-adv-board-msg]');
		b.disabled=true;
		post(b.getAttribute('data-liw-adv-mod'),parseInt(b.getAttribute('data-post'),10)).then(function(res){
			if(msg){msg.textContent=res&&res.ok?'OK ('+(res.status||'')+')':((res&&res.error)||'Fehler');}
			if(res&&res.ok){setTimeout(function(){window.location.reload();},600);} else {b.disabled=false;}
		}).catch(function(){if(msg){msg.textContent='Fehler.';}b.disabled=false;});
	});
})();
JS;
	}
}
