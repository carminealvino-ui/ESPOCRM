#!/usr/bin/env bash
# KPI completo coerente — branch unificata (etichette Lordi/Totali/Netto + Avvisi/Criticità).
#
# Problema tipico: branch criticita-zero ha etichette lunghe "Appuntamenti lordi";
# la branch unificata usa Lordi, Totali, Netto sotto il titolo tile.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/allinea-post-2-luglio-9999/tools/deploy-kpi-allinea-completo.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH_KPI="cursor/crm-kpi-criticita-unificata-9999"
BRANCH_PATCH="cursor/allinea-post-2-luglio-9999"
BRANCH_ESPO10="cursor/fix-espocrm-10-compat-9999"
REPO="carminealvino-ui/ESPOCRM"
STAMP=$(date +%Y%m%d-%H%M%S)
BACKUP="${CRM_ROOT}/backup_dev/KPI/completo-${STAMP}"

download() {
  local branch="$1"
  local rel="$2"
  local dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "https://raw.githubusercontent.com/${REPO}/${branch}/${rel}?t=${STAMP}" -o "${dest}"
  echo "  OK ${rel}"
}

FILES_KPI=(
  "client/custom/res/templates/dashlets/crm-kpi.tpl"
  "client/custom/src/views/dashlets/crm-kpi.js"
  "client/custom/src/views/dashlets/options/crm-kpi.js"
  "custom/Espo/Custom/Controllers/CrmKpi.php"
  "custom/Espo/Custom/Services/CrmKpi/CrmKpiService.php"
  "custom/Espo/Custom/Tools/CrmKpi/Alerts.php"
  "custom/Espo/Custom/Tools/CrmKpi/FunnelBuilder.php"
  "custom/Espo/Custom/Tools/CrmKpi/KpiContext.php"
  "custom/Espo/Custom/Tools/CrmKpi/DateRange.php"
  "custom/Espo/Custom/Tools/CrmKpi/Period.php"
  "custom/Espo/Custom/Tools/CrmKpi/MonthRange.php"
  "custom/Espo/Custom/Tools/CrmKpi/OpenOpportunityPeriod.php"
  "custom/Espo/Custom/Tools/CrmKpi/WeekOfMonth.php"
  "custom/Espo/Custom/Tools/CrmKpi/YieldBuilder.php"
  "custom/Espo/Custom/Resources/metadata/dashlets/CrmKpi.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/CrmKpi.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/DashletOptions.json"
  "tools/verify-crm-kpi-deploy.php"
)

echo "=============================================="
echo " KPI allinea-completo (${BRANCH_KPI})"
echo " Backup: ${BACKUP}"
echo "=============================================="

mkdir -p "${BACKUP}"
for rel in "${FILES_KPI[@]}" "custom/Espo/Custom/Controllers/Appuntamento.php"; do
  src="${CRM_ROOT}/${rel}"
  if [[ -f "${src}" ]]; then
    mkdir -p "${BACKUP}/$(dirname "${rel}")"
    cp -a "${src}" "${BACKUP}/${rel}"
    echo "  BACKUP ${rel}"
  fi
done

echo ""
echo "=== Download KPI (branch unica) ==="
for rel in "${FILES_KPI[@]}"; do
  download "${BRANCH_KPI}" "${rel}"
done

echo "=== CSS Criticità 4 colonne (patch allinea) ==="
download "${BRANCH_PATCH}" "client/custom/css/crm-kpi-dashlet.css"

sed -i 's/formatPaymentMeta/buildCriticita/' "${CRM_ROOT}/tools/verify-crm-kpi-deploy.php" 2>/dev/null || true

echo ""
echo "=== Appuntamento Espo 10 (no getContainer) ==="
download "${BRANCH_ESPO10}" "custom/Espo/Custom/Controllers/Appuntamento.php"

grep -q "label: 'Lordi'" "${CRM_ROOT}/client/custom/src/views/dashlets/crm-kpi.js"
grep -q "mapMetricTile" "${CRM_ROOT}/client/custom/src/views/dashlets/crm-kpi.js"
grep -q 'injectableFactory' "${CRM_ROOT}/custom/Espo/Custom/Controllers/Appuntamento.php"
test -f "${CRM_ROOT}/custom/Espo/Custom/Tools/CrmKpi/YieldBuilder.php"

echo ""
echo "=== Cache + rebuild ==="
cd "${CRM_ROOT}"
php clear_cache.php
php rebuild.php
rm -rf data/cache/*

echo ""
php tools/verify-crm-kpi-deploy.php || true
echo ""
echo "Fatto. Browser: Ctrl+Shift+R (finestra anonima) sulla dashboard KPI."
echo "Attesi: Rese per giorno/settimana con colonne Lordi | Netti | Opp. | Contr."
echo "        Avvisi con conteggi; Criticità per entità (o Nessuna criticità)."
