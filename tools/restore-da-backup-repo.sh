#!/usr/bin/env bash
# Ripristina file da backup/stato-noto-buono-2026-07-09 nel repo (NON da GitHub curl).
#
# Uso sul server CRM (dopo git pull del repo):
#   cd ~/public_html/crm/mec-group   # oppure path clone ESPOCRM
#   bash tools/restore-da-backup-repo.sh

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
SNAP_NAME="stato-noto-buono-2026-07-09"

# Se eseguito dalla root CRM che contiene backup/
if [[ -d "${CRM_ROOT}/backup/${SNAP_NAME}" ]]; then
  SNAP="${CRM_ROOT}/backup/${SNAP_NAME}"
elif [[ -d "${REPO_ROOT}/backup/${SNAP_NAME}" ]]; then
  SNAP="${REPO_ROOT}/backup/${SNAP_NAME}"
else
  echo "ERRORE: backup/${SNAP_NAME} non trovato in ${CRM_ROOT} né in ${REPO_ROOT}" >&2
  exit 1
fi

TS="$(date +%Y%m%d-%H%M%S)"
PRE="${CRM_ROOT}/backup/pre-restore-${TS}"
mkdir -p "${PRE}"

copy_restore() {
  local rel="$1"
  local src="${SNAP}/${rel}"
  local dest="${CRM_ROOT}/${rel}"

  if [[ ! -f "${src}" ]]; then
    echo "SKIP (manca in snapshot): ${rel}"
    return
  fi

  if [[ -f "${dest}" ]]; then
    mkdir -p "${PRE}/$(dirname "${rel}")"
    cp -a "${dest}" "${PRE}/${rel}"
  fi

  mkdir -p "$(dirname "${dest}")"
  cp -a "${src}" "${dest}"
  echo "OK ${rel}"
}

cd "${CRM_ROOT}"

echo "=== Snapshot pre-restore: ${PRE} ==="
echo "=== Ripristino da: ${SNAP} ==="

FILES=(
  "custom/Espo/Custom/Controllers/Appuntamento.php"
  "custom/Espo/Custom/Controllers/CrmKpi.php"
  "custom/Espo/Custom/Services/ProvvigioneManager.php"
  "custom/Espo/Custom/Hooks/Provvigione/AccrualAndAmount.php"
  "custom/Espo/Custom/Hooks/Quote/SetPresentedWhenNumeroContratto.php"
  "custom/Espo/Custom/Hooks/Quote/AfterSaveTotaleProvvigioni.php"
  "custom/Espo/Custom/Resources/metadata/app/client.json"
  "client/custom/css/crm-kpi-dashlet.css"
)

for rel in "${FILES[@]}"; do
  copy_restore "${rel}"
done

rm -f custom/Espo/Custom/Hooks/Quote/BeforeSave.php

php clear_cache.php
php rebuild.php

if rg -q "getContainer\(" custom/Espo/Custom/Controllers/Appuntamento.php 2>/dev/null; then
  echo "ERRORE: Appuntamento.php contiene ancora getContainer()" >&2
  exit 1
fi

echo ""
echo "=== Fatto. Per annullare: cp -a ${PRE}/custom/... ==="
