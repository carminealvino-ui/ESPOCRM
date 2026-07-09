#!/usr/bin/env bash
# Ripristina stato noto-buono sul server SENZA git (solo curl).
# Copia i file da backup/stato-noto-buono-2026-07-09 nel repo GitHub.
#
# Uso:
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-contratto-stato-provvigioni-9999/tools/deploy-restore-da-backup.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-contratto-stato-provvigioni-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
SNAP_PREFIX="backup/stato-noto-buono-2026-07-09"
TS="$(date +%Y%m%d-%H%M%S)"
PRE="${CRM_ROOT}/backup/pre-restore-${TS}"

cd "${CRM_ROOT}"
mkdir -p "${PRE}"

backup_if_exists() {
  local rel="$1"
  local src="${CRM_ROOT}/${rel}"
  if [[ -f "${src}" ]]; then
    mkdir -p "${PRE}/$(dirname "${rel}")"
    cp -a "${src}" "${PRE}/${rel}"
    echo "SNAP ${rel}"
  fi
}

fetch() {
  local rel="$1"
  local url="${BASE}/${SNAP_PREFIX}/${rel}"
  local dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${url}?t=${TS}" -o "${dest}"
  echo "OK ${rel}"
}

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

echo "=== Snapshot pre-restore in ${PRE} ==="
for rel in "${FILES[@]}"; do
  backup_if_exists "${rel}"
done

echo ""
echo "=== Ripristino da ${SNAP_PREFIX} (branch ${BRANCH}) ==="
for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

rm -f custom/Espo/Custom/Hooks/Quote/BeforeSave.php

php clear_cache.php
php rebuild.php

echo ""
echo "=== Verifica Appuntamento.php ==="
if grep -q 'getContainer(' custom/Espo/Custom/Controllers/Appuntamento.php 2>/dev/null; then
  echo "ERRORE: Appuntamento.php contiene ancora getContainer()" >&2
  exit 1
fi
if grep -q 'injectableFactory' custom/Espo/Custom/Controllers/Appuntamento.php; then
  echo "OK: controller KPI compatibile Espo 10"
else
  echo "ATTENZIONE: verificare Appuntamento.php manualmente" >&2
fi

echo ""
echo "=== Fatto ==="
echo "Snapshot precedente: ${PRE}"
echo "Ctrl+Shift+R sul browser, poi ricarica dashboard KPI."
