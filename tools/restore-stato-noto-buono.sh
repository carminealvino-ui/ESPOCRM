#!/usr/bin/env bash
# Ripristino immediato stato noto-buono (KPI + contratti) da branch unica.
# NON mescola branch diverse: evita il "un passo avanti, cinque indietro".
#
# Uso:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-contratto-stato-provvigioni-9999/tools/restore-stato-noto-buono.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-contratto-stato-provvigioni-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS="$(date +%Y%m%d-%H%M%S)"
SNAP="${CRM_ROOT}/backup/restore-snapshot-${TS}"

cd "${CRM_ROOT}"
mkdir -p "${SNAP}"

backup_file() {
  local rel="$1"
  local src="${CRM_ROOT}/${rel}"
  if [[ -f "${src}" ]]; then
    mkdir -p "${SNAP}/$(dirname "${rel}")"
    cp -a "${src}" "${SNAP}/${rel}"
    echo "SNAP ${rel}"
  fi
}

echo "=== Snapshot pre-restore in ${SNAP} ==="
FILES=(
  "custom/Espo/Custom/Controllers/Appuntamento.php"
  "custom/Espo/Custom/Controllers/CrmKpi.php"
  "custom/Espo/Custom/Controllers/Opportunity.php"
  "custom/Espo/Custom/Services/ProvvigioneManager.php"
  "custom/Espo/Custom/Hooks/Provvigione/AccrualAndAmount.php"
  "custom/Espo/Custom/Resources/metadata/app/client.json"
  "client/custom/css/crm-kpi-dashlet.css"
)

for rel in "${FILES[@]}"; do
  backup_file "${rel}"
done

fetch() {
  local path="$1"
  mkdir -p "$(dirname "${path}")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}?t=${TS}" -o "${path}"
  echo "OK ${path}"
}

echo ""
echo "=== Ripristino da ${BRANCH} ==="
for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

# Hook legacy che rompe Espo 10
rm -f custom/Espo/Custom/Hooks/Quote/BeforeSave.php

php clear_cache.php
php rebuild.php

echo ""
echo "=== Verifica Appuntamento (non deve contenere getContainer) ==="
if rg -q "getContainer\(" custom/Espo/Custom/Controllers/Appuntamento.php 2>/dev/null; then
  echo "ERRORE: Appuntamento.php contiene ancora getContainer()" >&2
  exit 1
fi
echo "OK: controller KPI compatibile Espo 10"

echo ""
echo "=== Fatto. Snapshot precedente: ${SNAP} ==="
echo "Per tornare indietro:"
echo "  cp -a ${SNAP}/custom/Espo/Custom/Controllers/Appuntamento.php custom/Espo/Custom/Controllers/"
echo "  php clear_cache.php"
