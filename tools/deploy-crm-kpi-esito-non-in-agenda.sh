#!/usr/bin/env bash
# KPI: rese, avvisi totali, criticità per entità.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/crm-kpi-esito-non-in-agenda-9999/tools/deploy-crm-kpi-esito-non-in-agenda.sh?t=$(date +%s)" | bash
#   php clear_cache.php && php rebuild.php

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/crm-kpi-esito-non-in-agenda-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
LOCAL_BACKUP="${CRM_ROOT}/backup/crm-kpi-rese-restore/server-${STAMP}"

echo "=== Backup KPI in ${LOCAL_BACKUP} ==="
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
  "custom/Espo/Custom/Tools/CrmKpi/FunnelBuilder.php"
  "custom/Espo/Custom/Tools/CrmKpi/YieldBuilder.php"
  "custom/Espo/Custom/Classes/Select/Appuntamento/PrimaryFilters/AppuntamentiSenzaOpportunita.php"
  "custom/Espo/Custom/Classes/Select/Appuntamento/PrimaryFilters/AppuntamentiConPiuOpportunita.php"
  "custom/Espo/Custom/Classes/Select/Opportunity/PrimaryFilters/SenzaInvioWhatsapp.php"
  "custom/Espo/Custom/Classes/Select/Call/PrimaryFilters/RichiamiPianificati.php"
  "custom/Espo/Custom/Classes/Select/Quote/PrimaryFilters/ContrattiSospesiFinanziamento.php"
  "custom/Espo/Custom/Resources/metadata/selectDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/selectDefs/Opportunity.json"
  "custom/Espo/Custom/Resources/metadata/selectDefs/Call.json"
  "custom/Espo/Custom/Resources/metadata/selectDefs/Quote.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Appuntamento.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Opportunity.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Call.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Quote.json"
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
echo "Poi: cd ${CRM_ROOT} && php clear_cache.php && php rebuild.php"
echo "Browser: Ctrl+Shift+R sulla dashboard KPI"
