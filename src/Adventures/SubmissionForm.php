<?php
/**
 * Liebherr Adventures – gemeinsame Eingabemaske in Workboard-Optik (Basislogik §1/§5, alpha.60).
 *
 * EIN Renderer für beide Kontexte (Frontend-Shortcode `[liw_adventures]` UND Backend-Board): Kategorie
 * (Inhaltstyp) + Dringlichkeit + Titel/Kurzgeschichte, plus die geforderte **frei definierbare Token-
 * Bewertung** (Tokenwert + Nutzungsumfang) und die **Rechte-Zusicherung**. Die abgeschickten Beiträge werden
 * über {@see Rest::create()} → {@see RegistrationService::register()} im Artikelbook registriert/verlinkt.
 *
 * Das Core-Workboard ist nicht cross-plugin wiederverwendbar (kein Shortcode/REST/Hook, feste Rollen-/
 * Tabellenbindung, feste Preis-Pauschale); daher hier als Workboard-Optik nachgebaut – mit frei wählbarem
 * Tokenwert statt fester Pauschale.
 *
 * @package Liebherr\InterfaceWorld\Adventures
 * @since   0.1.0-alpha.60
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\Adventures;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SubmissionForm {

	/**
	 * Rendert die Eingabemaske.
	 *
	 * @param string $context 'frontend' | 'admin' (nur Styling/Überschrift).
	 */
	public static function render( string $context = 'frontend' ): string {
		$is_admin = 'admin' === $context;
		$cls      = 'liw-adv__create liw-wb' . ( $is_admin ? ' liw-wb--admin' : '' );

		ob_start();
		?>
		<section class="<?php echo esc_attr( $cls ); ?>" id="liw-adv-create" data-liw-adv-create>
			<div class="liw-wb__head">
				<h2 class="liw-wb__title"><?php echo esc_html__( 'Beitrag erstellen', 'liebherr-interface-world' ); ?></h2>
				<p class="liw-wb__lead"><?php echo esc_html__( 'Workboard-Maske: Kategorie und Dringlichkeit wählen, Beitrag beschreiben, Tokenwert festlegen. Mit Rechte-Zusicherung wird der Beitrag registriert und im Artikelbook eingetragen.', 'liebherr-interface-world' ); ?></p>
			</div>

			<div class="liw-wb__row">
				<label class="liw-wb__field"><span class="liw-wb__label"><?php echo esc_html__( 'Kategorie (Inhaltstyp)', 'liebherr-interface-world' ); ?> *</span>
					<select data-liw-adv-type class="liw-wb__input"><?php foreach ( Taxonomy::content_types() as $code => $label ) : ?><option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
				</label>
				<label class="liw-wb__field"><span class="liw-wb__label"><?php echo esc_html__( 'Dringlichkeit', 'liebherr-interface-world' ); ?> *</span>
					<select data-liw-adv-urgency class="liw-wb__input"><?php foreach ( Taxonomy::urgency_levels() as $code => $label ) : ?><option value="<?php echo esc_attr( $code ); ?>"<?php echo 'informative' === $code ? ' selected' : ''; ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
				</label>
			</div>

			<label class="liw-wb__field"><span class="liw-wb__label"><?php echo esc_html__( 'Titel', 'liebherr-interface-world' ); ?> *</span>
				<input type="text" data-liw-adv-title maxlength="140" class="liw-wb__input" />
			</label>
			<label class="liw-wb__field"><span class="liw-wb__label"><?php echo esc_html__( 'Beschreibung / Kurzgeschichte', 'liebherr-interface-world' ); ?></span>
				<textarea data-liw-adv-story rows="4" class="liw-wb__input"></textarea>
			</label>
			<label class="liw-wb__field"><span class="liw-wb__label"><?php echo esc_html__( 'Bild-URL (optional, Demo)', 'liebherr-interface-world' ); ?></span>
				<input type="url" data-liw-adv-image placeholder="https://…" class="liw-wb__input" />
			</label>

			<div class="liw-wb__row">
				<label class="liw-wb__field"><span class="liw-wb__label"><?php echo esc_html__( 'Maschine / Modell', 'liebherr-interface-world' ); ?></span>
					<input type="text" data-liw-adv-machine class="liw-wb__input" placeholder="<?php echo esc_attr__( 'z. B. R 9200', 'liebherr-interface-world' ); ?>" />
				</label>
				<label class="liw-wb__field"><span class="liw-wb__label"><?php echo esc_html__( 'Bauteil / Komponente', 'liebherr-interface-world' ); ?></span>
					<input type="text" data-liw-adv-component class="liw-wb__input" placeholder="<?php echo esc_attr__( 'z. B. Hydraulikpumpe', 'liebherr-interface-world' ); ?>" />
				</label>
			</div>

			<div class="liw-wb__row">
				<label class="liw-wb__field"><span class="liw-wb__label"><?php echo esc_html__( 'Sichtbarkeit', 'liebherr-interface-world' ); ?></span>
					<select data-liw-adv-visibility class="liw-wb__input"><?php foreach ( Policy::visibilities() as $code => $label ) : ?><option value="<?php echo esc_attr( $code ); ?>"<?php echo 'organization' === $code ? ' selected' : ''; ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
				</label>
				<label class="liw-wb__field"><span class="liw-wb__label"><?php echo esc_html__( 'Ortsschutz', 'liebherr-interface-world' ); ?></span>
					<select data-liw-adv-protection class="liw-wb__input">
						<option value="region" selected><?php echo esc_html__( 'Nur Region', 'liebherr-interface-world' ); ?></option>
						<option value="exact"><?php echo esc_html__( 'Exakt (nur Berechtigte)', 'liebherr-interface-world' ); ?></option>
						<option value="hidden"><?php echo esc_html__( 'Verbergen', 'liebherr-interface-world' ); ?></option>
					</select>
				</label>
			</div>

			<div class="liw-wb__token">
				<h3 class="liw-wb__token-title"><?php echo esc_html__( 'Token-Bewertung (frei festlegbar)', 'liebherr-interface-world' ); ?></h3>
				<div class="liw-wb__row">
					<label class="liw-wb__field"><span class="liw-wb__label"><?php echo esc_html__( 'Tokenwert', 'liebherr-interface-world' ); ?></span>
						<input type="number" min="0" step="1" value="0" data-liw-adv-token class="liw-wb__input" />
						<span class="liw-wb__hint"><?php echo esc_html__( 'Der Ersteller bestimmt den Wert. Eigene Beiträge sind für Sie kostenfrei.', 'liebherr-interface-world' ); ?></span>
					</label>
					<label class="liw-wb__field"><span class="liw-wb__label"><?php echo esc_html__( 'Erlaubter Nutzungsumfang', 'liebherr-interface-world' ); ?></span>
						<select data-liw-adv-usage class="liw-wb__input">
							<option value="view"><?php echo esc_html__( 'Nur ansehen', 'liebherr-interface-world' ); ?></option>
							<option value="reuse_internal"><?php echo esc_html__( 'Intern weiterverwenden', 'liebherr-interface-world' ); ?></option>
							<option value="reuse_world"><?php echo esc_html__( 'Im Liebherr-World-Netz verwenden', 'liebherr-interface-world' ); ?></option>
						</select>
					</label>
				</div>
				<label class="liw-wb__rights"><input type="checkbox" data-liw-adv-rights /> <span><?php echo esc_html__( 'Ich bin zur Bereitstellung dieser Inhalte berechtigt und reiche den Beitrag mit dem festgelegten Tokenwert zur Registrierung ein.', 'liebherr-interface-world' ); ?> *</span></label>
			</div>

			<div class="liw-adv__locate liw-wb__locate">
				<button type="button" class="liw-cta liw-cta--secondary" data-liw-adv-locate><?php echo esc_html__( 'Standort ermitteln', 'liebherr-interface-world' ); ?></button>
				<input type="number" step="any" data-liw-adv-lat placeholder="lat" class="liw-adv__coord" />
				<input type="number" step="any" data-liw-adv-lng placeholder="lng" class="liw-adv__coord" />
				<span class="liw-adv__words" data-liw-adv-words></span>
			</div>

			<div class="liw-adv__actions liw-wb__actions">
				<button type="button" class="liw-cta liw-cta--secondary" data-liw-adv-submit="draft"><?php echo esc_html__( 'Als Entwurf sichern', 'liebherr-interface-world' ); ?></button>
				<button type="button" class="liw-cta liw-cta--primary" data-liw-adv-submit="register"><?php echo esc_html__( 'Registrieren & im Artikelbook eintragen', 'liebherr-interface-world' ); ?></button>
				<p class="liw-adv__msg liw-wb__msg" role="status" data-liw-adv-msg></p>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}
}
