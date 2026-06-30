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

REL="custom/Espo/Custom/Tools/CrmKpi/YieldBuilder.php"
TARGET="${CRM_ROOT}/${REL}"

echo "=== KPI rese: percentuali sul totale colonna → ${CRM_ROOT} ==="

mkdir -p "$(dirname "${TARGET}")"
curl -fsSL -o "${TARGET}" "${BASE}/${REL}?t=$(date +%s)"
echo "OK ${REL}"

grep -q "applyColumnSharePercents" "${TARGET}" || {
  echo "ERRORE: YieldBuilder.php non aggiornato" >&2
  exit 1
}

(cd "${CRM_ROOT}" && php command.php rebuild && php command.php clearCache)

echo ""
echo "Fatto. Ricarica la dashboard KPI (Ctrl+Shift+R)."
