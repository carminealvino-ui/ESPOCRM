#!/usr/bin/env bash
# Push su GitHub da cPanel: legge token da exports/sync/token.txt
# Uso: bash tools/sync-push-github-cpanel.sh ["messaggio commit"]
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
GIT_CLONE="${GIT_CLONE:-$HOME/ESPOCRM-git}"
TOKEN_FILE="${TOKEN_FILE:-$CRM_ROOT/exports/sync/token.txt}"
REPO_SLUG="${GITHUB_REPOSITORY:-carminealvino-ui/ESPOCRM}"
BRANCH="${GITHUB_BRANCH:-main}"
COMMIT_MSG="${1:-sync: allineamento da produzione $(date +%Y-%m-%d)}"

if [[ ! -d "$GIT_CLONE/.git" ]]; then
  echo "ERRORE: clone Git non trovato in $GIT_CLONE"
  echo "Setup: git clone https://github.com/${REPO_SLUG}.git ESPOCRM-git"
  exit 1
fi

if [[ ! -r "$TOKEN_FILE" ]]; then
  echo "ERRORE: token non leggibile: $TOKEN_FILE"
  echo "Creare il file con il PAT GitHub (solo il token, una riga). Vedi exports/sync/token.txt.example"
  exit 1
fi

TOKEN=$(tr -d '\r\n ' <"$TOKEN_FILE")
if [[ ${#TOKEN} -lt 20 ]]; then
  echo "ERRORE: token troppo corto in $TOKEN_FILE"
  exit 1
fi

cd "$GIT_CLONE"
git config user.email "${GIT_COMMITTER_EMAIL:-sync@mec-group.local}"
git config user.name "${GIT_COMMITTER_NAME:-MEC Sync Produzione}"

git add custom client/custom
if git diff --cached --quiet; then
  echo "Nessuna modifica da committare."
else
  git commit -m "$COMMIT_MSG"
fi

git push "https://x-access-token:${TOKEN}@github.com/${REPO_SLUG}.git" "$BRANCH"

echo "OK push su https://github.com/${REPO_SLUG}/commits/${BRANCH}"
