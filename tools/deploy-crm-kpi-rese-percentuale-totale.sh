#!/usr/bin/env bash
# Deploy fix percentuali rese KPI: % sul totale colonna (giorno/settimana).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/crm-kpi-rese-periodo-9999/tools/deploy-crm-kpi-rese-percentuale-totale.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/crm-kpi-rese-periodo-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

echo "=== KPI rese: percentuali sul totale colonna → ${CRM_ROOT} ==="

FILES=(
  "custom/Espo/Custom/Tools/CrmKpi/YieldBuilder.php"
  "custom/Espo/Custom/Services/CrmKpi/CrmKpiService.php"
  "client/custom/src/views/dashlets/crm-kpi.js"
  "client/custom/res/templates/dashlets/crm-kpi.tpl"
  "client/custom/css/crm-kpi-dashlet.css"
)

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

grep -q "applyColumnSharePercents" "${CRM_ROOT}/custom/Espo/Custom/Tools/CrmKpi/YieldBuilder.php" || {
  echo "ERRORE: YieldBuilder.php non aggiornato" >&2
  exit 1
}

grep -q "getNetAppuntamentoIds" "${CRM_ROOT}/custom/Espo/Custom/Services/CrmKpi/CrmKpiService.php" || {
  echo "ERRORE: CrmKpiService.php non aggiornato" >&2
  exit 1
}

grep -q "mapQuoteMetricTile" "${CRM_ROOT}/client/custom/src/views/dashlets/crm-kpi.js" || {
  echo "ERRORE: crm-kpi.js non aggiornato (mapQuoteMetricTile)" >&2
  exit 1
}

grep -q "mapPipelineResultsRows" "${CRM_ROOT}/client/custom/src/views/dashlets/crm-kpi.js" || {
  echo "ERRORE: crm-kpi.js non aggiornato" >&2
  exit 1
}

(cd "${CRM_ROOT}" && php command.php rebuild && php command.php clearCache)

echo ""
echo "Fatto. Ricarica la dashboard KPI (Ctrl+Shift+R)."
