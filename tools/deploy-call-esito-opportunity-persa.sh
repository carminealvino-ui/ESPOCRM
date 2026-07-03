#!/usr/bin/env bash
# Call esito Non interessato → opportunità persa (+ bonifica retroattiva).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/call-esito-opportunity-persa-9999/tools/deploy-call-esito-opportunity-persa.sh?t=$(date +%s)" | bash
#   php clear_cache.php && php rebuild.php
#   php tools/bonifica-call-esito-opportunity-persa.php --dry-run
#   php tools/bonifica-call-esito-opportunity-persa.php

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/call-esito-opportunity-persa-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
LOCAL_BACKUP="${CRM_ROOT}/backup/call-esito-opportunity-persa/server-${STAMP}"

echo "=== Backup in ${LOCAL_BACKUP} ==="
mkdir -p "${LOCAL_BACKUP}"

backup_if_exists() {
  local rel="$1"
  local src="${CRM_ROOT}/${rel}"
  if [[ -f "${src}" ]]; then
    mkdir -p "${LOCAL_BACKUP}/$(dirname "${rel}")"
    cp -a "${src}" "${LOCAL_BACKUP}/${rel}"
    echo "BACKUP ${rel}"
  fi
}

FILES=(
  "custom/Espo/Custom/Services/CallEsitoOpportunitySync.php"
  "custom/Espo/Custom/Hooks/Call/SyncOpportunityFromEsito.php"
  "tools/bonifica-call-esito-opportunity-persa.php"
)

for rel in "${FILES[@]}"; do
  backup_if_exists "${rel}"
done

echo "=== Download da ${BRANCH} ==="
for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "OK ${rel}"
done

echo ""
echo "=== Deploy completato ==="
echo "Poi:"
echo "  cd ${CRM_ROOT} && php clear_cache.php && php rebuild.php"
echo "  php tools/bonifica-call-esito-opportunity-persa.php --dry-run"
echo "  php tools/bonifica-call-esito-opportunity-persa.php"
