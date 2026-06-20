#!/usr/bin/env bash
# Hotfix KPI dashlet: endpoint Appuntamento + popup Call
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/crm-kpi-dashlet-9999/tools/deploy-crm-kpi-hotfix.sh?t=$(date +%s)" | bash
#   php clear_cache.php && php rebuild.php
#   php tools/diagnose-crm-kpi-api.php --user=carmine_alvino

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/crm-kpi-dashlet-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
LOCAL_BACKUP="${CRM_ROOT}/backup/crm-kpi-hotfix/server-${STAMP}"

echo "=== Hotfix KPI dashlet in ${CRM_ROOT} ==="
mkdir -p "${LOCAL_BACKUP}"

FILES=(
  "custom/Espo/Custom/Controllers/Appuntamento.php"
  "custom/Espo/Custom/Controllers/CrmKpi.php"
  "custom/Espo/Custom/Resources/routes.json"
  "custom/Espo/Custom/Resources/metadata/scopes/CrmKpi.json"
  "custom/Espo/Custom/Services/CrmKpi/CrmKpiService.php"
  "custom/Espo/Custom/Tools/CrmKpi/OpenOpportunityPeriod.php"
  "custom/Espo/Custom/Classes/Select/Opportunity/PrimaryFilters/AperteMeseCorrente.php"
  "custom/Espo/Custom/Classes/Select/Opportunity/PrimaryFilters/AperteMesePrecedente.php"
  "custom/Espo/Custom/Resources/metadata/selectDefs/Opportunity.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Global.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Opportunity.json"
  "client/custom/res/templates/dashlets/crm-kpi.tpl"
  "custom/Espo/Custom/Tools/Activities/PopupNotificationsProvider.php"
  "custom/Espo/Custom/Resources/layouts/Call/detailEsitoPopup.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Call.json"
  "client/custom/src/helpers/call-esito-popup-defaults.js"
  "client/custom/src/views/appuntamento/popup-notification.js"
  "client/custom/src/init/popup-notifications-ordered.js"
  "custom/Espo/Custom/Resources/metadata/app/client.json"
  "client/custom/src/views/dashlets/crm-kpi.js"
  "tools/diagnose-crm-kpi-api.php"
  "tools/verify-crm-kpi-deploy.php"
)

for rel in "${FILES[@]}"; do
  src="${CRM_ROOT}/${rel}"
  if [[ -f "${src}" ]]; then
    mkdir -p "${LOCAL_BACKUP}/$(dirname "${rel}")"
    cp -a "${src}" "${LOCAL_BACKUP}/${rel}"
    echo "BACKUP ${rel}"
  fi
done

for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "OK ${rel}"
done

echo ""
echo "IMPORTANTE: include anche client/custom/src/views/dashlets/crm-kpi.js"
echo "Poi: cd ${CRM_ROOT} && php clear_cache.php && php rebuild.php"
echo "Test: php tools/verify-crm-kpi-deploy.php && php tools/diagnose-crm-kpi-api.php --user=carmine_alvino"
echo "Browser: Ctrl+Shift+R sulla dashboard"
