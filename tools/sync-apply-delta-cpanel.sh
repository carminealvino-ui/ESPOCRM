#!/usr/bin/env bash
# Applica export-delta sul clone ~/ESPOCRM-git (cPanel)
# Uso: bash tools/sync-apply-delta-cpanel.sh delta-20260605-085058
set -euo pipefail

DELTA_NAME="${1:-}"
CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
GIT_CLONE="${GIT_CLONE:-$HOME/ESPOCRM-git}"

if [[ -z "$DELTA_NAME" ]]; then
  echo "Usage: bash tools/sync-apply-delta-cpanel.sh delta-YYYYMMDD-HHMMSS"
  exit 1
fi

DELTA_PATH="$CRM_ROOT/exports/sync/$DELTA_NAME"
if [[ ! -d "$DELTA_PATH" ]]; then
  echo "ERRORE: cartella delta non trovata: $DELTA_PATH"
  exit 1
fi

if [[ ! -f "$CRM_ROOT/tools/sync-custom-prod-repo.php" ]]; then
  echo "ERRORE: eseguire prima bootstrap tools in $CRM_ROOT"
  exit 1
fi

cd "$GIT_CLONE"
git checkout main
git pull origin main

php "$CRM_ROOT/tools/sync-custom-prod-repo.php" apply-delta "$DELTA_PATH"

echo "OK apply-delta. Prossimo: bash $CRM_ROOT/tools/sync-push-github-cpanel.sh"
