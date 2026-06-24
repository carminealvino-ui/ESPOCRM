#!/usr/bin/env bash
# Deploy solo KPI dashlet v2 (5 riquadri, brand, funnel verticali).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/crm-kpi-dashlet-v2-9999/tools/deploy-crm-kpi-v2-hotfix.sh?t=$(date +%s)" | bash
#   php clear_cache.php && php rebuild.php
#   php tools/verify-crm-kpi-deploy.php

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/crm-kpi-dashlet-v2-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
LOCAL_BACKUP="${CRM_ROOT}/backup/crm-kpi-dashlet-v2/server-${STAMP}"

echo "=== Backup KPI v2 in ${LOCAL_BACKUP} ==="
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
  "custom/Espo/Custom/Controllers/Appuntamento.php"
  "custom/Espo/Custom/Services/CrmKpi/CrmKpiService.php"
  "custom/Espo/Custom/Tools/CrmKpi/Alerts.php"
  "custom/Espo/Custom/Tools/CrmKpi/FunnelBuilder.php"
  "custom/Espo/Custom/Tools/CrmKpi/KpiContext.php"
  "custom/Espo/Custom/Resources/metadata/dashlets/CrmKpi.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/CrmKpi.json"
  "tools/verify-crm-kpi-deploy.php"
)

for rel in "${FILES[@]}"; do
  backup_if_exists "${rel}"
done

echo "=== Download KPI v2 da ${BRANCH} ==="
for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "OK ${rel}"
done

echo ""
echo "=== KPI v2 deployato ==="
echo "Poi:"
echo "  cd ${CRM_ROOT} && php clear_cache.php && php rebuild.php"
echo "  php tools/verify-crm-kpi-deploy.php"
echo "  Browser: Ctrl+Shift+R sulla dashboard KPI"
