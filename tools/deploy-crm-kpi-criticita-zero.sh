#!/usr/bin/env bash
# KPI Criticità: box entità con conteggio 0 mostra "Nessuna criticità".
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/crm-kpi-criticita-zero-9999/tools/deploy-crm-kpi-criticita-zero.sh?t=$(date +%s)" | bash
#   php clear_cache.php && php rebuild.php

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/crm-kpi-criticita-zero-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
LOCAL_BACKUP="${CRM_ROOT}/backup/crm-kpi-criticita-zero/server-${STAMP}"

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
  "client/custom/src/views/dashlets/crm-kpi.js"
)

for rel in "${FILES[@]}"; do
  backup_if_exists "${rel}"
done

echo "=== Deploy da ${BRANCH} ==="
for rel in "${FILES[@]}"; do
  mkdir -p "${CRM_ROOT}/$(dirname "${rel}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${CRM_ROOT}/${rel}"
  echo "OK ${rel}"
done

echo "=== Fine. Esegui: php clear_cache.php && php rebuild.php (poi Ctrl+Shift+R) ==="
