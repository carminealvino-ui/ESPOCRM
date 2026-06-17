#!/usr/bin/env bash
# Allinea layout Contratto produzione -> GitHub (solo layouts/Quote).
# Eseguire sul server cPanel dopo modifiche da Layout Manager.
#
#   cd ~/public_html/crm/mec-group
#   bash tools/align-quote-layouts-prod-repo.sh
#
# Oppure con export già creato:
#   bash tools/align-quote-layouts-prod-repo.sh quote-layouts-20260617-120000
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
GIT_CLONE="${GIT_CLONE:-$HOME/ESPOCRM-git}"
EXPORT_NAME="${1:-}"

cd "${CRM_ROOT}" || exit 1

if [[ -x "${CRM_ROOT}/tools/backup-quote-layouts.sh" ]]; then
  bash "${CRM_ROOT}/tools/backup-quote-layouts.sh"
fi

if [[ -z "${EXPORT_NAME}" ]]; then
  bash "${CRM_ROOT}/tools/export-quote-layouts-for-repo.sh"
  EXPORT_NAME="$(ls -1dt "${CRM_ROOT}"/exports/sync/quote-layouts-* 2>/dev/null | head -1 | xargs basename)"
fi

EXPORT_PATH="${CRM_ROOT}/exports/sync/${EXPORT_NAME}"
if [[ ! -d "${EXPORT_PATH}" ]]; then
  echo "ERRORE: export non trovato: ${EXPORT_PATH}"
  exit 1
fi

if [[ ! -d "${GIT_CLONE}/.git" ]]; then
  echo "ERRORE: clone Git non trovato in ${GIT_CLONE}"
  echo "Setup: git clone https://github.com/carminealvino-ui/ESPOCRM.git ~/ESPOCRM-git"
  exit 1
fi

cd "${GIT_CLONE}"
git checkout main
git pull origin main

bash "${CRM_ROOT}/tools/apply-quote-layouts-from-export.sh" "${EXPORT_PATH}"

git add custom/Espo/Custom/Resources/layouts/Quote/
if git diff --cached --quiet; then
  echo "Nessuna differenza: layout Quote già allineati su main."
  exit 0
fi

COMMIT_MSG="sync: layout Contratto da produzione ${EXPORT_NAME}"
git commit -m "${COMMIT_MSG}"

if [[ -x "${CRM_ROOT}/tools/sync-push-github-cpanel.sh" ]]; then
  bash "${CRM_ROOT}/tools/sync-push-github-cpanel.sh" "${COMMIT_MSG}"
else
  echo "Eseguire manualmente: git push origin main"
fi

echo ""
echo "Fatto. Layout Contratto allineati su GitHub (branch main)."
