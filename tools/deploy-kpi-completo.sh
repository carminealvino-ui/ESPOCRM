#!/usr/bin/env bash
# Deploy completo KPI dashlet v2 — un solo comando, niente patch manuali.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/crm-kpi-dashlet-v2-9999/tools/deploy-kpi-completo.sh?t=$(date +%s)" | bash
#   php clear_cache.php && php rebuild.php
#
# NON sovrascrive Global.json né altri i18n it_IT (regola 11).

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/crm-kpi-rese-periodo-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
LOCAL_BACKUP="${CRM_ROOT}/backup/crm-kpi-completo/server-${STAMP}"

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
  "client/custom/src/views/dashlets/options/crm-kpi.js"
  "custom/Espo/Custom/Controllers/Appuntamento.php"
  "custom/Espo/Custom/Services/CrmKpi/CrmKpiService.php"
  "custom/Espo/Custom/Tools/CrmKpi/Alerts.php"
  "custom/Espo/Custom/Tools/CrmKpi/FunnelBuilder.php"
  "custom/Espo/Custom/Tools/CrmKpi/WeekOfMonth.php"
  "custom/Espo/Custom/Tools/CrmKpi/YieldBuilder.php"
  "custom/Espo/Custom/Tools/CrmKpi/KpiContext.php"
  "custom/Espo/Custom/Tools/Appuntamento/PendingCallDateTime.php"
  "custom/Espo/Custom/Tools/Activities/PopupNotificationsProvider.php"
  "custom/Espo/Custom/Services/CallStandardTesto.php"
  "custom/Espo/Custom/Controllers/CallStandardTesto.php"
  "custom/Espo/Custom/Hooks/Call/PersistStandardTesto.php"
  "custom/Espo/Custom/Resources/metadata/dashlets/CrmKpi.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/CrmKpi.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/DashletOptions.json"
  "tools/verify-crm-kpi-deploy.php"
)

for rel in "${FILES[@]}"; do
  backup_if_exists "${rel}"
done

echo ""
echo "=== Download da ${BRANCH} ==="
for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "OK ${rel}"
done

echo ""
echo "=== Deploy KPI completo terminato ==="
echo "Poi esegui:"
echo "  cd ${CRM_ROOT}"
echo "  php clear_cache.php"
echo "  php rebuild.php"
echo "  php tools/verify-crm-kpi-deploy.php"
echo ""
echo "Browser: Ctrl+Shift+R sulla dashboard KPI"
echo ""
echo "Etichette opzioni dashlet:"
echo "  - dialogo: Gestione Etichette > DashletOptions (fields period, productBrand)"
echo "  - oppure: Gestione Etichette > KPI CRM (scope CrmKpi) + clear_cache"
echo "NON tocca Global.json / Appuntamento.json / Quote.json / Call.json"
