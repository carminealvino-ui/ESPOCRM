#!/usr/bin/env bash
# KPI: scheda unica Criticità 2x2 (Appuntamenti, Opportunità, Contratti, Chiamate).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/crm-kpi-criticita-unificata-9999/tools/deploy-crm-kpi-criticita-unificata.sh?t=$(date +%s)" | bash
#   php clear_cache.php && php rebuild.php

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/crm-kpi-criticita-unificata-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
LOCAL_BACKUP="${CRM_ROOT}/backup/crm-kpi-criticita-unificata/server-${STAMP}"

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
  "client/custom/css/crm-kpi-dashlet.css"
  "client/custom/res/templates/dashlets/crm-kpi.tpl"
  "client/custom/src/views/dashlets/crm-kpi.js"
  "custom/Espo/Custom/Services/CrmKpi/CrmKpiService.php"
  "custom/Espo/Custom/Tools/CrmKpi/Alerts.php"
  "custom/Espo/Custom/Classes/Select/Call/PrimaryFilters/ChiamateScadute.php"
  "custom/Espo/Custom/Resources/metadata/selectDefs/Call.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Call.json"
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

JS="${CRM_ROOT}/client/custom/src/views/dashlets/crm-kpi.js"
if ! grep -q "kpi-tile-labels-v3" "${JS}"; then
  echo "ERRORE: crm-kpi.js non contiene la versione attesa (kpi-tile-labels-v3)"
  exit 1
fi
if grep -q "sui lordi" "${JS}"; then
  echo "ERRORE: crm-kpi.js contiene ancora 'sui lordi' — file vecchio"
  exit 1
fi
echo "VERIFICA OK: crm-kpi.js versione tile corretta"

echo ""
echo "=== Cache ==="
cd "${CRM_ROOT}"
php clear_cache.php
php rebuild.php

echo ""
echo "=== Fine. Ctrl+Shift+R sulla dashboard KPI ==="
