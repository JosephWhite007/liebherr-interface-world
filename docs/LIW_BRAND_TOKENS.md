# Liebherr Interface Solutions — Marken-Token-Referenz (KANDIDAT, Freigabe ausstehend)

**Status:** CI-005-Kandidat · **Quelle:** liebherr.com (eigener öffentlicher Auftritt, erfasst 18.09.2026)
· **Freigabe:** Liebherr, dokumentiert ausstehend. **Bis zur Freigabe nicht öffentlich verwenden**
(CI-002/CI-005, Markenschutz). Die Werte sind **nicht erfunden**, sondern unverändert aus Liebherrs
eigenem Design-System abgelesen; sie ersetzen bis zur Freigabe die neutralen Fallbacks des geplanten
Brand-Token-Boards (Release-Plan Etappe 1).

Zugehörige Datei-Assets (Logo, Webfonts, Bildmotive) liegen als Kandidaten im **Media Board**
(`_liw_media_approved = 0`); Import über `scripts/liw-import-brand-assets.php`.

## Farben (aus Liebherrs CSS)

| Rolle (Pflichtenheft §11) | Wert | Herkunft (Liebherr-Variable) |
|---|---|---|
| `--brand-primary` | `#ffd000` (Liebherr-Gelb) | `--color-yellow` / `--color-brand-yellow` / `--pl-color-primary-yellow` |
| `--brand-secondary` | `#2779c4` (Liebherr-Blau) | `--color-brand-primary` / `--pl-color-primary-blue` |
| Akzent Arctic Blue | `#003057` / `#164870` | `--color-artic-blue` / `--color-brand-artic-blue` |
| Dunkelgrund | `#202326` (auch `#151719`) | `--color-brand-background` / `--pl-surface-default` |
| `--brand-surface` (hell) | `#ffffff` | `--color-co-light-brand-background` |
| `--brand-text` | `#000000` (dunkelgrund: `#ffffff`) | `--color-text` / `--text-default` |
| `--brand-muted` | `#6b7278` | `--text-soft` / `--color-steel-700` |
| `--brand-border` | `#d3d8dd` | `--stroke-default` / `--color-steel-300` |
| Stahlgrau-Skala | `#f9fafb #f0f3f6 #e5e8ed #d3d8dd #bdc4ca #9ea4ab #888e94 #6b7278 #51585d #353a40 #282c30 #202326` | `--color-steel-50…-975` |
| Funktional: Erfolg / Warnung / Fehler | `#327e0d` / `#b87c0a` / `#d91e1e` | `--pl-on-surface-success/-warning`, `--surface-error` |

## Typografie

| Rolle | Familie | Gewichte | Webfont (Media-Board-Kandidat) |
|---|---|---|---|
| `--font-heading` | **LiebherrHead** | Black (900), Regular (400) | `LiebherrHead-Black_Web.woff2`, `LiebherrHead-Regular_Web.woff2` |
| `--font-body` | **LiebherrText** | Regular (400), Medium (500), Bold (700) | `LiebherrText-Regular/Medium/Bold_Web.woff2` |
| Fallback-Stack | `-apple-system, BlinkMacSystemFont, Arial, Helvetica, sans-serif` | — | — |

**Größen (px, Desktop):** H1 48 · H2 40 · H3 32 · H4 28 · H5 24 · H6 20 · Copy 16/18 · Klein 14/12.
**Hinweis Fonts:** LiebherrHead/LiebherrText sind lizenzierte Schriften. Einbindung erst nach
dokumentierter Liebherr-Lizenz/Freigabe; bis dahin Fallback-Stack.

## Sonstige Token (Pflichtenheft §11)

`--radius-control: 2px` · `--content-max: 1440px`.

## GoHeal / ARALIYA

GoHeal nur klein im Footer („Solution Provider: GoHeal", CI-003); ARALIYA im Hintergrund (CI-004).
