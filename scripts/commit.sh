#!/usr/bin/env bash
#
# Liebherr Interface Solutions – Commit-Skript (Git-Regel, CLAUDE.md Abschnitt 1)
#
# Grund: Der Ordner-Mount verbietet Claude Löschoperationen, die Git für Commits braucht
# (.git/index.lock, tmp-Objekte). Claude bereitet Änderungen vor (git add) und die fertige
# Commit-Message (COMMIT_MSG.txt); dieses Skript führt lokal aus, was Joseph sonst manuell
# tippt: rm -f .git/index.lock && git commit -F COMMIT_MSG.txt.
#
# Ausführung (im Terminal, nicht in Cowork):
#   ./scripts/commit.sh
#
set -euo pipefail

cd "$(dirname "$0")/.."

if [ ! -f COMMIT_MSG.txt ]; then
	echo "COMMIT_MSG.txt fehlt im Plugin-Root – nichts zu committen." >&2
	exit 1
fi

rm -f .git/index.lock

git add -A
git status --short

git commit -F COMMIT_MSG.txt

echo ""
echo "Commit erstellt. Für Push (falls Remote konfiguriert): git push"
