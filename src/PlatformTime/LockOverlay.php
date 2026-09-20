<?php
/**
 * Liebherr World – Plattformzeit: Standby-Sperr-Overlay (ADR-LIW-MYL-002 §5.2/§7, Pflichtenheft §41.8).
 *
 * Reine Anzeige der server-autoritären Sperre: ein Vollbild-Overlay mit der Rückkehr-Rechenaufgabe. Die
 * eigentliche Sperre erzwingt der Server ({@see LockGuard} – gated REST 423 + Ausgabe nur bei aktiver
 * Sperre). Das Overlay wird ausschließlich gerendert, wenn der Server „gesperrt" meldet; die Aufgabe selbst
 * (Frage + Token) holt das JS cache-sicher über die REST-Route `platform-time/challenge`. Die „Liebherr
 * World"-Leiste und der Sprachumschalter bleiben darüber sichtbar (gemeinsamer Helfer `LiwWorldbarLock`).
 *
 * Barrierearm: Ohne JavaScript bleibt das Overlay statisch sichtbar (die Plattform bleibt hinter dem
 * Server-Gate gesperrt); die Aufgabe kann dann nicht gelöst werden – bewusst, denn die Rückkehr ist an die
 * server-geprüfte Rechenlogik gebunden.
 *
 * @package Liebherr\InterfaceWorld\PlatformTime
 * @since   0.1.0-alpha.139
 */

declare( strict_types = 1 );

namespace Liebherr\InterfaceWorld\PlatformTime;

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class LockOverlay {

	/** Wählt das passende Overlay nach Sperrgrund ('settlement' = Beenden/Report, sonst Standby). */
	public static function render( string $reason = 'standby' ): string {
		return 'settlement' === $reason ? self::render_report() : self::render_standby();
	}

	/** Baut das Standby-Overlay (die Aufgabe füllt das JS aus der REST-Route nach). */
	public static function render_standby(): string {
		$title = esc_html__( 'Plattform im Standby – pausiert', 'liebherr-interface-world' );
		$intro = esc_html__( 'Die Plattform ist pausiert und bis auf die obere Leiste gesperrt. Zum Weiterarbeiten lösen Sie bitte die Rechenaufgabe.', 'liebherr-interface-world' );
		$help_s = esc_html__( 'Anleitung', 'liebherr-interface-world' );
		$help_b = esc_html__( 'Ablauf: 1) Rechenaufgabe lösen, 2) „Weiter" klicken, 3) die Sperre fällt und die Uhr läuft weiter. Es wird nichts abgerechnet. Wer die Sitzung ganz abschließen und die Zeit abrechnen will, nutzt stattdessen „Beenden" an der Zeit-Uhr.', 'liebherr-interface-world' );
		$lbl   = esc_html__( 'Antwort', 'liebherr-interface-world' );
		$enter = esc_html__( 'Weiter', 'liebherr-interface-world' );

		$html  = '<div id="liw-ptime-lock" class="liw-ptlock" data-liw-ptlock role="dialog" aria-modal="true" aria-labelledby="liw-ptlock-title">';
		$html .= '<div class="liw-ptlock__panel">';
		$html .= '<h2 id="liw-ptlock-title" class="liw-ptlock__title">' . $title . '</h2>';
		$html .= '<p class="liw-ptlock__intro">' . $intro . '</p>';
		$html .= '<details class="liw-ptlock__help"><summary>' . $help_s . '</summary><p>' . $help_b . '</p></details>';
		$html .= '<form class="liw-ptlock__form" data-liw-ptlock-form novalidate>';
		$html .= '<p class="liw-ptlock__q" data-liw-ptlock-q aria-live="polite">…</p>';
		$html .= '<input type="hidden" data-liw-ptlock-token value="" />';
		$html .= '<label class="liw-ptlock__label">' . $lbl . ' '
			. '<input type="text" inputmode="numeric" autocomplete="off" class="liw-ptlock__answer" data-liw-ptlock-answer /></label>';
		$html .= '<button type="submit" class="liw-ptlock__enter" data-liw-ptlock-enter disabled>' . $enter . '</button>';
		$html .= '<p class="liw-ptlock__hint" data-liw-ptlock-hint aria-live="polite"></p>';
		$html .= '</form>';
		$html .= '</div></div>';
		return $html;
	}

	/**
	 * Baut das Beenden-/Report-Overlay (ADR-LIW-MYL-002 §6): verbrauchte Zeit + Token, dann „Auf Wallet
	 * buchen & weiter". Die Zahlen füllt das JS aus der REST-Route `platform-time/report` nach.
	 */
	public static function render_report(): string {
		$title = esc_html__( 'Sitzung beendet – Abrechnung', 'liebherr-interface-world' );
		$intro = esc_html__( 'Deine Plattformzeit dieser Sitzung ist beendet. Bitte bestätige die Abrechnung, danach ist die Plattform wieder frei.', 'liebherr-interface-world' );
		$help_s = esc_html__( 'Anleitung', 'liebherr-interface-world' );
		$help_b = esc_html__( 'Ablauf: 1) verbrauchte Zeit und Token prüfen, 2) „Auf Wallet buchen & weiter" klicken, 3) der Betrag wird über die Wallet gebucht und die Plattform ist wieder frei. Reicht das Guthaben nicht, bleibt die Plattform gesperrt, bis aufgeladen wurde.', 'liebherr-interface-world' );
		$l_time = esc_html__( 'Verbrauchte Zeit', 'liebherr-interface-world' );
		$l_tok  = esc_html__( 'Token', 'liebherr-interface-world' );
		$l_wal  = esc_html__( 'Wallet-Saldo', 'liebherr-interface-world' );
		$enter  = esc_html__( 'Auf Wallet buchen & weiter', 'liebherr-interface-world' );

		$html  = '<div id="liw-ptime-lock" class="liw-ptlock liw-ptlock--report" data-liw-ptlock data-liw-ptreport role="dialog" aria-modal="true" aria-labelledby="liw-ptlock-title">';
		$html .= '<div class="liw-ptlock__panel">';
		$html .= '<h2 id="liw-ptlock-title" class="liw-ptlock__title">' . $title . '</h2>';
		$html .= '<p class="liw-ptlock__intro">' . $intro . '</p>';
		$html .= '<details class="liw-ptlock__help"><summary>' . $help_s . '</summary><p>' . $help_b . '</p></details>';
		$html .= '<dl class="liw-ptlock__report">';
		$html .= '<div class="liw-ptlock__row"><dt>' . $l_time . '</dt><dd data-liw-ptreport-time>–</dd></div>';
		$html .= '<div class="liw-ptlock__row"><dt>' . $l_tok . '</dt><dd><strong data-liw-ptreport-tokens>–</strong></dd></div>';
		$html .= '<div class="liw-ptlock__row" data-liw-ptreport-wallet-row hidden><dt>' . $l_wal . '</dt><dd data-liw-ptreport-wallet>–</dd></div>';
		$html .= '</dl>';
		$html .= '<form class="liw-ptlock__form" data-liw-ptreport-form novalidate>';
		$html .= '<button type="submit" class="liw-ptlock__enter" data-liw-ptreport-settle>' . $enter . '</button>';
		$html .= '<p class="liw-ptlock__hint" data-liw-ptreport-hint aria-live="polite"></p>';
		$html .= '<a class="liw-ptlock__topup" data-liw-ptreport-topup href="#" hidden></a>';
		$html .= '</form>';
		$html .= '</div></div>';
		return $html;
	}
}
