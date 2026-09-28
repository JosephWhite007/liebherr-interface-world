#!/usr/bin/env bash
#
# Liebherr Interface Solutions – Deploy-Skript (Live/Staging per SSH + rsync)
#
# Ersetzt das bisherige manuelle scp/ssh im Terminal (Muster: build_v10/deploy_alpha20.sh des Core).
# Ablauf: Vorprüfung (sauberer Git-Stand, Version) → Remote-Backup des Plugin-Ordners → rsync der
# Plugin-Dateien (ohne Dev-Anteile) → WP-/WP-Rocket-Cache leeren → Version auf dem Server prüfen.
#
# Aufruf (im Terminal, aus dem Plugin-Root):
#   ./scripts/deploy.sh                 # Probelauf (dry-run): zeigt nur, was übertragen würde
#   ./scripts/deploy.sh --go            # echter Deploy
#   ./scripts/deploy.sh --go --no-tests # ohne lokalen Docker-Testlauf (nur wenn Docker nicht läuft)
#
# Ziel-Server per Umgebungsvariablen überschreibbar (Defaults = ANNAHME aus deploy_alpha20.sh, Core-Live):
#   LIW_DEPLOY_HOST=root@comehome.care
#   LIW_DEPLOY_PATH=/var/www/html/wp-content/plugins/liebherr-interface-world
#   LIW_DEPLOY_WP=/var/www/html            (WordPress-Root für WP-CLI auf dem Server)
#
# Voraussetzungen: SSH-Key für den Host eingerichtet; rsync lokal und remote; WP-CLI remote optional
# (fehlt es, wird der Cache-Schritt übersprungen und gemeldet).
#
# Rollback: Das Skript legt vor dem Übertragen ein Backup an:
#   <LIW_DEPLOY_PATH>_backup_<alte Version>_<Zeitstempel>.tar.gz  (neben dem Plugin-Ordner)
# Zurückspielen:  ssh HOST "cd $(dirname PATH) && rm -rf liebherr-interface-world && tar xzf <backup>.tar.gz"
#
set -euo pipefail

cd "$(dirname "$0")/.."

HOST="${LIW_DEPLOY_HOST:-root@comehome.care}"
REMOTE="${LIW_DEPLOY_PATH:-/var/www/html/wp-content/plugins/liebherr-interface-world}"
WP_ROOT="${LIW_DEPLOY_WP:-/var/www/html}"
SLUG="$(basename "$REMOTE")"
REMOTE_PARENT="$(dirname "$REMOTE")"

GO=0
RUN_TESTS=1
for arg in "$@"; do
	case "$arg" in
		--go)       GO=1 ;;
		--no-tests) RUN_TESTS=0 ;;
		*) echo "Unbekannte Option: $arg" >&2; exit 2 ;;
	esac
done

# Nicht deployen: Git, Dev-Skripte, Tests, Commit-Message, Betriebssystem-Müll.
# docs/ bleibt drin (Handbuch-Seite verlinkt Doku; klein). languages/ bleibt drin.
EXCLUDES=(
	--exclude '.git' --exclude '.gitignore' --exclude '.DS_Store' --exclude '*.log'
	--exclude 'COMMIT_MSG.txt' --exclude 'tests/' --exclude 'scripts/commit.sh' --exclude 'scripts/deploy.sh'
	--exclude 'scripts/liw-seed-*.php' --exclude 'scripts/liw-selftest.php'
	--exclude 'node_modules/' --exclude 'vendor/'
)

VERSION="$(sed -n "s/^define( 'LIW_VERSION', '\([^']*\)' );/\1/p" liebherr-interface-world.php)"
HEADER="$(sed -n 's/^ \* Version:[[:space:]]*\(.*\)$/\1/p' liebherr-interface-world.php | head -1)"

echo ""
echo "══════════════════════════════════════════════════════════════"
echo "  Liebherr Interface Solutions – Deploy $VERSION"
echo "  Ziel: $HOST:$REMOTE"
echo "  Modus: $([ "$GO" = 1 ] && echo 'ECHT (--go)' || echo 'PROBELAUF (dry-run) – nichts wird verändert')"
echo "══════════════════════════════════════════════════════════════"
echo ""

# ── 1. Vorprüfungen (lokal) ──────────────────────────────────────────────────
echo "▸ Vorprüfungen"
[ -n "$VERSION" ] || { echo "  ✗ LIW_VERSION nicht gefunden." >&2; exit 1; }
[ "$VERSION" = "$HEADER" ] || { echo "  ✗ Plugin-Header ($HEADER) ≠ LIW_VERSION ($VERSION) – erst angleichen." >&2; exit 1; }
echo "  ✓ Version $VERSION (Header und Konstante identisch)"

if [ -n "$(git status --porcelain --untracked-files=no 2>/dev/null)" ]; then
	echo "  ✗ Uncommittete Änderungen – erst ./scripts/commit.sh ausführen:" >&2
	git status --short --untracked-files=no >&2
	exit 1
fi
echo "  ✓ Git-Arbeitsbaum sauber ($(git rev-parse --short HEAD))"

if [ "$RUN_TESTS" = 1 ]; then
	if docker ps --format '{{.Names}}' 2>/dev/null | grep -qx 'araliya_wordpress'; then
		echo "  ▸ WP-freie Tests + Docker-Selbsttest …"
		docker exec araliya_wordpress php "/var/www/html/wp-content/plugins/$SLUG/tests/run-tests.php" | tail -1
		docker exec araliya_wordpress php "/var/www/html/wp-content/plugins/$SLUG/scripts/liw-selftest.php" 2>&1 | tail -1 | tee /tmp/liw-selftest-last.txt
		grep -q ', 0 fehlgeschlagen' /tmp/liw-selftest-last.txt || { echo "  ✗ Selbsttest rot – kein Deploy." >&2; exit 1; }
		echo "  ✓ Tests grün"
	else
		echo "  ✗ Docker-Container araliya_wordpress läuft nicht – Tests nicht möglich (oder --no-tests)." >&2
		exit 1
	fi
fi

# ── 2. Remote-Stand + Backup ─────────────────────────────────────────────────
echo "▸ Remote"
if ! ssh -o BatchMode=yes -o ConnectTimeout=10 "$HOST" 'true' 2>/dev/null; then
	echo "  ✗ SSH zu $HOST nicht möglich (Key/Host prüfen; LIW_DEPLOY_HOST setzen)." >&2
	exit 1
fi
REMOTE_VERSION="$(ssh "$HOST" "sed -n \"s/^define( 'LIW_VERSION', '\([^']*\)' );/\1/p\" '$REMOTE/liebherr-interface-world.php' 2>/dev/null" || true)"
echo "  ✓ Verbunden. Installierte Version: ${REMOTE_VERSION:-<keine – Erstinstallation>}"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="${REMOTE}_backup_${REMOTE_VERSION:-none}_${STAMP}.tar.gz"
if [ "$GO" = 1 ] && [ -n "$REMOTE_VERSION" ]; then
	ssh "$HOST" "cd '$REMOTE_PARENT' && tar czf '$BACKUP' '$SLUG'"
	echo "  ✓ Backup: $BACKUP"
else
	echo "  · Backup würde angelegt: $BACKUP"
fi

# ── 3. Übertragen ────────────────────────────────────────────────────────────
echo "▸ rsync"
RSYNC_OPTS=(-az --delete --itemize-changes "${EXCLUDES[@]}")
[ "$GO" = 1 ] || RSYNC_OPTS+=(--dry-run)
ssh "$HOST" "mkdir -p '$REMOTE'"
rsync "${RSYNC_OPTS[@]}" ./ "$HOST:$REMOTE/" | sed 's/^/  /'
echo "  ✓ Übertragung $([ "$GO" = 1 ] && echo 'abgeschlossen' || echo 'simuliert')"

[ "$GO" = 1 ] || { echo ""; echo "Probelauf beendet. Echter Deploy: ./scripts/deploy.sh --go"; exit 0; }

# ── 4. Nachbereitung auf dem Server ──────────────────────────────────────────
echo "▸ Nachbereitung"
ssh "$HOST" "cd '$WP_ROOT' && command -v wp >/dev/null 2>&1 && {
	wp plugin is-active '$SLUG' --allow-root >/dev/null 2>&1 || echo '  ⚠ Plugin ist nicht aktiv – im WP-Admin aktivieren';
	wp cache flush --allow-root >/dev/null 2>&1 && echo '  ✓ WP-Objekt-Cache geleert';
	wp eval 'if ( function_exists( \"rocket_clean_domain\" ) ) { rocket_clean_domain(); echo \"  ✓ WP-Rocket-Cache geleert\n\"; } else { echo \"  · WP Rocket nicht aktiv\n\"; }' --allow-root 2>/dev/null;
	echo -n '  ✓ Plugin-Version laut WP: '; wp plugin get '$SLUG' --field=version --allow-root 2>/dev/null || echo '?';
} || echo '  ⚠ WP-CLI nicht verfügbar – Caches (WP + WP Rocket) manuell leeren, Plugin-Version im WP-Admin prüfen'"

# Datenbank-Upgrade läuft beim ersten Aufruf (maybe_upgrade_database vergleicht liw_installed_version).
CODE="$(curl -s -o /dev/null -w '%{http_code}' "https://${HOST#*@}/" || echo '000')"
echo "  · Startseite https://${HOST#*@}/ → HTTP $CODE"

echo ""
echo "══════════════════════════════════════════════════════════════"
echo "  ✅ Deploy $VERSION abgeschlossen ($REMOTE_VERSION → $VERSION)"
echo "  Rollback: ssh $HOST \"cd $REMOTE_PARENT && rm -rf $SLUG && tar xzf $BACKUP\""
echo "══════════════════════════════════════════════════════════════"
