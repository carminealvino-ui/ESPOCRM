#!/usr/bin/env bash
# Recovery CRM + KPI (senza controller Appuntamento custom).
#
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-kpi-lordi-pianificati-9999/tools/emergency-recovery-crm.sh" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-kpi-lordi-pianificati-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS=$(date +%s)
BACKUP="${CRM_ROOT}/backup/emergency-${TS}"

echo "=== Recovery CRM + KPI ==="
cd "${CRM_ROOT}"
mkdir -p "${BACKUP}"

fetch() {
  mkdir -p "$(dirname "$1")"
  curl -fsSL "${BASE}/$1" -o "$1"
  echo "OK $1"
}

if [[ -f custom/Espo/Custom/Controllers/Appuntamento.php ]]; then
  mkdir -p "${BACKUP}/custom/Espo/Custom/Controllers"
  mv custom/Espo/Custom/Controllers/Appuntamento.php "${BACKUP}/custom/Espo/Custom/Controllers/Appuntamento.php"
  echo "RIMOSSO controller Appuntamento custom (usa default Espo)"
fi

fetch custom/Espo/Custom/Services/CallStandardTesto.php
fetch custom/Espo/Custom/Controllers/CallStandardTesto.php
fetch custom/Espo/Custom/Hooks/Call/PersistStandardTesto.php
fetch custom/Espo/Custom/Controllers/CrmKpi.php
fetch custom/Espo/Custom/Services/CrmKpi/CrmKpiService.php
for f in Alerts.php DateRange.php FunnelBuilder.php KpiContext.php MonthRange.php OpenOpportunityPeriod.php Period.php WeekOfMonth.php YieldBuilder.php; do
  fetch "custom/Espo/Custom/Tools/CrmKpi/$f"
done
fetch custom/Espo/Custom/Tools/CrmKpi/Api/GetSummary.php
fetch custom/Espo/Custom/Resources/metadata/dashlets/CrmKpi.json
fetch custom/Espo/Custom/Resources/metadata/scopes/CrmKpi.json
fetch custom/Espo/Custom/Resources/i18n/it_IT/CrmKpi.json
fetch client/custom/src/views/dashlets/crm-kpi.js
fetch client/custom/src/views/dashlets/options/crm-kpi.js
fetch client/custom/res/templates/dashlets/crm-kpi.tpl
fetch client/custom/css/crm-kpi-dashlet.css

php clear_cache.php && php rebuild.php

echo "=== Fatto — Ctrl+Shift+R sulla dashboard KPI ==="
