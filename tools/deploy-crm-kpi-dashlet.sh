#!/usr/bin/env bash
# Deploy completo KPI CRM: API, dashlet, avvisi, filtri, popup Call collegati.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/crm-kpi-dashlet-v2-9999/tools/deploy-crm-kpi-dashlet.sh?t=$(date +%s)" | bash
#   php clear_cache.php && php rebuild.php
#   php tools/verify-crm-kpi-deploy.php
#   php tools/diagnose-crm-kpi-api.php --user=carmine_alvino

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/crm-kpi-dashlet-v2-9999}"
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
  "client/custom/src/views/dashlets/records.js"
  "client/custom/src/helpers/call-esito-popup-defaults.js"
  "client/custom/src/views/appuntamento/popup-notification.js"
  "client/custom/src/init/popup-notifications-ordered.js"
  "custom/Espo/Custom/Controllers/Appuntamento.php"
  "custom/Espo/Custom/Controllers/CrmKpi.php"
  "custom/Espo/Custom/Resources/routes.json"
  "custom/Espo/Custom/Resources/metadata/scopes/CrmKpi.json"
  "custom/Espo/Custom/Tools/CrmKpi/Api/GetSummary.php"
  "custom/Espo/Custom/Tools/CrmKpi/FunnelBuilder.php"
  "custom/Espo/Custom/Tools/CrmKpi/KpiContext.php"
  "custom/Espo/Custom/Tools/CrmKpi/WeekOfMonth.php"
  "custom/Espo/Custom/Tools/CrmKpi/Alerts.php"
  "custom/Espo/Custom/Tools/CrmKpi/DateRange.php"
  "custom/Espo/Custom/Tools/CrmKpi/Period.php"
  "custom/Espo/Custom/Tools/CrmKpi/MonthRange.php"
  "custom/Espo/Custom/Tools/CrmKpi/OpenOpportunityPeriod.php"
  "custom/Espo/Custom/Services/CrmKpi/CrmKpiService.php"
  "custom/Espo/Custom/Tools/DateTime/BusinessDateTime.php"
  "custom/Espo/Custom/Tools/Activities/PopupNotificationsProvider.php"
  "custom/Espo/Custom/Classes/Select/Appuntamento/PrimaryFilters/Pianificato.php"
  "custom/Espo/Custom/Classes/Select/Appuntamento/PrimaryFilters/MeseCorrente.php"
  "custom/Espo/Custom/Classes/Select/Appuntamento/PrimaryFilters/MeseCorrenteSvolto.php"
  "custom/Espo/Custom/Classes/Select/Opportunity/PrimaryFilters/MeseCorrente.php"
  "custom/Espo/Custom/Classes/Select/Opportunity/PrimaryFilters/Aperte.php"
  "custom/Espo/Custom/Classes/Select/Opportunity/PrimaryFilters/AperteMeseCorrente.php"
  "custom/Espo/Custom/Classes/Select/Opportunity/PrimaryFilters/AperteMesePrecedente.php"
  "custom/Espo/Custom/Classes/Select/Opportunity/PrimaryFilters/SenzaRiscontroTelefonico.php"
  "custom/Espo/Custom/Classes/Select/Opportunity/PrimaryFilters/SenzaRiscontroPeriodo.php"
  "custom/Espo/Custom/Classes/Select/Opportunity/PrimaryFilters/SenzaRiscontroMesePrecedente.php"
  "custom/Espo/Custom/Classes/Select/Opportunity/PrimaryFilters/ContrattiBacklog.php"
  "custom/Espo/Custom/Classes/Select/Opportunity/PrimaryFilters/ContrattiInLavorazione.php"
  "custom/Espo/Custom/Classes/Select/Opportunity/PrimaryFilters/InstallatoPeriodo.php"
  "custom/Espo/Custom/Classes/Select/Opportunity/PrimaryFilters/InstallatoMesePrecedente.php"
  "custom/Espo/Custom/Classes/Select/Quote/PrimaryFilters/MeseCorrente.php"
  "custom/Espo/Custom/Classes/Select/Quote/PrimaryFilters/DataInstallazionePeriodo.php"
  "custom/Espo/Custom/Classes/Select/Quote/PrimaryFilters/DataInstallazioneMesePrecedente.php"
  "custom/Espo/Custom/Classes/Select/Quote/PrimaryFilters/ContrattiBacklog.php"
  "custom/Espo/Custom/Classes/Select/Quote/PrimaryFilters/ContrattiInLavorazione.php"
  "custom/Espo/Custom/Classes/Select/Call/PrimaryFilters/ContattiDaFare.php"
  "custom/Espo/Custom/Resources/metadata/dashlets/CrmKpi.json"
  "custom/Espo/Custom/Resources/metadata/selectDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/selectDefs/Opportunity.json"
  "custom/Espo/Custom/Resources/metadata/selectDefs/Quote.json"
  "custom/Espo/Custom/Resources/metadata/selectDefs/Call.json"
  "custom/Espo/Custom/Resources/metadata/app/client.json"
  "custom/Espo/Custom/Resources/layouts/Call/detailEsitoPopup.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/CrmKpi.json"
  "tools/lib/dashboard-report-helpers.php"
  "tools/report-templates/vendite-mese.json"
  "tools/crea-report-vendite-mese.php"
  "tools/applica-dashboard-crm-kpi.php"
  "tools/rollback-dashboard-pre-kpi.php"
  "tools/backup-dashboard-utente.sh"
  "tools/allinea-server-da-repo.sh"
  "tools/riapplica-variazioni-post-restore.sh"
  "tools/diagnose-crm-kpi-api.php"
  "tools/verify-crm-kpi-deploy.php"
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
echo "=== Deploy KPI completato ==="
echo "Poi:"
echo "  cd ${CRM_ROOT} && php clear_cache.php && php rebuild.php"
echo "  php tools/verify-crm-kpi-deploy.php"
echo "  php tools/diagnose-crm-kpi-api.php --user=carmine_alvino"
echo "  Browser: Ctrl+Shift+R sulla dashboard KPI"
echo ""
echo "Allineamento completo (Call + KPI + tab): bash tools/allinea-server-da-repo.sh"
