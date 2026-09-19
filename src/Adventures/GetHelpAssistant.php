<?php
/**
 * Liebherr Adventures – Get-Help-Assistent (Backlog A7, Phase 3, §22.5).
 *
 * Geführte Hilfe für Service-/Notfallsituationen: strukturierte Schritte (zuerst sichern, Lage einschätzen,
 * qualifizierte Hilfe kontaktieren, Beitrag als Service/Hilfe erfassen, nachverfolgen). Kritische Beiträge
 * werden von der Policy ohnehin nie automatisch veröffentlicht, sondern priorisiert geprüft; der Assistent
 * führt den Nutzer sicher durch den Ablauf. Reine, ohne WordPress testbare Schrittliste (`steps()`).
 *
 * @package Liebherr\InterfaceWorld\Adventures
 * @since   0.1.0-alpha.68
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Adventures;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class GetHelpAssistant {

	public const SHORTCODE = 'liw_adventures_help';

	public static function register(): void {
		add_shortcode( self::SHORTCODE, [ self::class, 'render_shortcode' ] );
	}

	/**
	 * Geführte Schritte (rein/testbar). Jeder Schritt: key, title, text.
	 *
	 * @return array<int,array{key:string,title:string,text:string}>
	 */
	public static function steps(): array {
		return [
			[ 'key' => 'secure',  'title' => __( '1. Sicherheit zuerst', 'liebherr-interface-world' ),          'text' => __( 'Maschine stoppen und sichern, Gefahrenbereich absperren, keine Eigenreparatur an spannungs-/druckführenden Teilen. Personen aus dem Gefahrenbereich bringen.', 'liebherr-interface-world' ) ],
			[ 'key' => 'assess',  'title' => __( '2. Lage einschätzen', 'liebherr-interface-world' ),           'text' => __( 'Was ist betroffen (Maschine/Modell, Bauteil)? Wie dringend ist es? Notieren Sie den Drei-Wörter-Ort, damit Hilfe den Standort exakt findet.', 'liebherr-interface-world' ) ],
			[ 'key' => 'contact', 'title' => __( '3. Qualifizierte Hilfe kontaktieren', 'liebherr-interface-world' ), 'text' => __( 'Bei kritischen Situationen umgehend qualifiziertes Fachpersonal bzw. den Liebherr-Service verständigen. Warten Sie mit weiteren Schritten, bis Hilfe bestätigt ist.', 'liebherr-interface-world' ) ],
			[ 'key' => 'report',  'title' => __( '4. Beitrag als Service/Hilfe erfassen', 'liebherr-interface-world' ), 'text' => __( 'Erfassen Sie die Situation als Beitrag vom Typ „Service/Hilfe" mit Dringlichkeit „kritisch" und dem Drei-Wörter-Ort. Kritische Beiträge werden nicht öffentlich, sondern priorisiert geprüft.', 'liebherr-interface-world' ) ],
			[ 'key' => 'follow',  'title' => __( '5. Nachverfolgen', 'liebherr-interface-world' ),              'text' => __( 'Verfolgen Sie den Status; ergänzen Sie Fotos/Infos, sobald es die Sicherheit zulässt. Halten Sie den Bereich gesperrt, bis Fachpersonal freigibt.', 'liebherr-interface-world' ) ],
		];
	}

	public static function render_shortcode(): string {
		return '<div class="liw-adv" data-liw-adv>' . self::render() . '</div>';
	}

	/** Assistent-Markup (barrierefreie `<details>`-Schritte + CTA zur Erfassung). */
	public static function render(): string {
		ob_start();
		?>
		<section class="liw-adv__help" id="liw-adv-help" aria-labelledby="liw-adv-help-h">
			<h2 class="liw-adv__help-title" id="liw-adv-help-h"><?php echo esc_html__( 'Get Help – geführte Hilfe', 'liebherr-interface-world' ); ?> <span class="liw-adv__help-suitcase"><?php echo do_shortcode( '[liw_emergency_suitcase]' ); // Hilfe-Koffer: Klick öffnet das Emergency-Hilfe-Plugin (alpha.101). ?></span></h2>
			<p class="liw-adv__help-lead"><?php echo esc_html__( 'Schritt-für-Schritt bei Service- und Notfallsituationen. Sicherheit geht vor – erst sichern, dann melden.', 'liebherr-interface-world' ); ?></p>
			<ol class="liw-adv__help-steps" role="list">
				<?php foreach ( self::steps() as $s ) : ?>
					<li class="liw-adv__help-step">
						<details>
							<summary><?php echo esc_html( (string) $s['title'] ); ?></summary>
							<p><?php echo esc_html( (string) $s['text'] ); ?></p>
						</details>
					</li>
				<?php endforeach; ?>
			</ol>
			<a class="liw-cta liw-cta--primary" href="#liw-adv-create"><?php echo esc_html__( 'Service/Hilfe-Beitrag erstellen', 'liebherr-interface-world' ); ?></a>
		</section>
		<?php
		return (string) ob_get_clean();
	}
}
