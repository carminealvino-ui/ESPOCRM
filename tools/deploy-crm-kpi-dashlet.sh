#!/usr/bin/env bash
# Deploy dashlet KPI CRM + filtri + script report Vendite Mese.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/crm-kpi-dashlet-9999/tools/deploy-crm-kpi-dashlet.sh?t=$(date +%s)" | bash
#   php clear_cache.php && php rebuild.php
#   php tools/applica-dashboard-crm-kpi.php --force --user=admin
#   php tools/crea-report-vendite-mese.php --force

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/crm-kpi-dashlet-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
LOCAL_BACKUP="${CRM_ROOT}/backup/crm-kpi-dashlet/server-${STAMP}"

echo "=== Backup locale pre-deploy in ${LOCAL_BACKUP} ==="
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
  "custom/Espo/Custom/Controllers/CrmKpi.php"
  "custom/Espo/Custom/Resources/routes.json"
  "custom/Espo/Custom/Resources/metadata/scopes/CrmKpi.json"
  "custom/Espo/Custom/Tools/CrmKpi/Api/GetSummary.php"
  "custom/Espo/Custom/Resources/routes.json"
  "custom/Espo/Custom/Services/CrmKpi/CrmKpiService.php"
  "custom/Espo/Custom/Tools/DateTime/BusinessDateTime.php"
  "custom/Espo/Custom/Tools/CrmKpi/DateRange.php"
  "custom/Espo/Custom/Tools/CrmKpi/MonthRange.php"
  "custom/Espo/Custom/Classes/Select/Appuntamento/PrimaryFilters/MeseCorrente.php"
  "custom/Espo/Custom/Classes/Select/Appuntamento/PrimaryFilters/MeseCorrenteSvolto.php"
  "custom/Espo/Custom/Classes/Select/Opportunity/PrimaryFilters/MeseCorrente.php"
  "custom/Espo/Custom/Classes/Select/Opportunity/PrimaryFilters/Aperte.php"
  "custom/Espo/Custom/Classes/Select/Quote/PrimaryFilters/MeseCorrente.php"
  "custom/Espo/Custom/Resources/metadata/dashlets/CrmKpi.json"
  "custom/Espo/Custom/Resources/metadata/selectDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/selectDefs/Opportunity.json"
  "custom/Espo/Custom/Resources/metadata/selectDefs/Quote.json"
  "custom/Espo/Custom/Resources/metadata/app/client.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Global.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Appuntamento.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Opportunity.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Quote.json"
  "tools/lib/dashboard-report-helpers.php"
  "tools/report-templates/vendite-mese.json"
  "tools/crea-report-vendite-mese.php"
  "tools/applica-dashboard-crm-kpi.php"
  "tools/rollback-dashboard-pre-kpi.php"
  "tools/backup-dashboard-utente.sh"
  "tools/riapplica-variazioni-post-restore.sh"
  "tools/diagnose-crm-kpi-api.php"
  "tools/deploy-crm-kpi-hotfix.sh"
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
echo "Poi sul server:"
echo "  cd ${CRM_ROOT} && php clear_cache.php && php rebuild.php"
echo ""
echo "Ripristino tab (se modificati per errore):"
echo "  php tools/rollback-dashboard-pre-kpi.php --list-backups"
echo "  php tools/rollback-dashboard-pre-kpi.php --user=carmine_alvino --restore-latest"
echo ""
echo "Aggiunta KPI (solo merge, non sostituisce tab esistenti):"
echo "  php tools/applica-dashboard-crm-kpi.php --user=carmine_alvino"
echo ""
echo "Report Vendite Mese (solo elenco CRM, non tocca dashboard):"
echo "  php tools/crea-report-vendite-mese.php --reports-only --force --user=carmine_alvino"
