#!/usr/bin/env bash
# Layout KPI completo PR #97 (branch fix-kpi-lordi-pianificati-9999):
# Pipeline + Rese giorno/settimana + Criticità 2x2 + CrmKpi Espo 10
#
# NON è deploy-popup-save-fix.sh (solo popup telefono).
#
# Uso:
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-contratto-stato-provvigioni-9999/tools/deploy-kpi-layout-lordi-pianificati.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
KPI_BRANCH="cursor/fix-kpi-lordi-pianificati-9999"
SAFE_BRANCH="cursor/fix-contratto-stato-provvigioni-9999"
KPI_BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${KPI_BRANCH}"
SAFE_BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${SAFE_BRANCH}"
TS="$(date +%Y%m%d-%H%M%S)"
PRE="${CRM_ROOT}/backup/pre-kpi-layout-${TS}"

cd "${CRM_ROOT}"
mkdir -p "${PRE}"

backup_if_exists() {
  local rel="$1"
  if [[ -f "${rel}" ]]; then
    mkdir -p "${PRE}/$(dirname "${rel}")"
    cp -a "${rel}" "${PRE}/${rel}"
    echo "SNAP ${rel}"
  fi
}

fetch_kpi() {
  local path="$1"
  mkdir -p "$(dirname "${path}")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${KPI_BASE}/${path}?t=${TS}" -o "${path}"
  echo "OK ${path}"
}

fetch_safe() {
  local path="$1"
  mkdir -p "$(dirname "${path}")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${SAFE_BASE}/backup/stato-noto-buono-2026-07-09/${path}?t=${TS}" -o "${path}"
  echo "OK ${path} (safe)"
}

KPI_FILES=(
  "custom/Espo/Custom/Resources/metadata/app/client.json"
  "custom/Espo/Custom/Controllers/CrmKpi.php"
  "custom/Espo/Custom/Services/CrmKpi/CrmKpiService.php"
  "custom/Espo/Custom/Tools/CrmKpi/Alerts.php"
  "custom/Espo/Custom/Tools/CrmKpi/DateRange.php"
  "custom/Espo/Custom/Tools/CrmKpi/FunnelBuilder.php"
  "custom/Espo/Custom/Tools/CrmKpi/KpiContext.php"
  "custom/Espo/Custom/Tools/CrmKpi/MonthRange.php"
  "custom/Espo/Custom/Tools/CrmKpi/OpenOpportunityPeriod.php"
  "custom/Espo/Custom/Tools/CrmKpi/Period.php"
  "custom/Espo/Custom/Tools/CrmKpi/WeekOfMonth.php"
  "custom/Espo/Custom/Tools/CrmKpi/YieldBuilder.php"
  "custom/Espo/Custom/Tools/CrmKpi/Api/GetSummary.php"
  "custom/Espo/Custom/Resources/metadata/dashlets/CrmKpi.json"
  "custom/Espo/Custom/Resources/metadata/scopes/CrmKpi.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/CrmKpi.json"
  "client/custom/src/views/dashlets/crm-kpi.js"
  "client/custom/src/views/dashlets/options/crm-kpi.js"
  "client/custom/res/templates/dashlets/crm-kpi.tpl"
  "client/custom/css/crm-kpi-dashlet.css"
)

echo "=== Snapshot pre-deploy in ${PRE} ==="
for rel in "${KPI_FILES[@]}" "custom/Espo/Custom/Controllers/Appuntamento.php"; do
  backup_if_exists "${rel}"
done

echo ""
echo "=== Deploy KPI PR #97 (${KPI_BRANCH}) ==="
for rel in "${KPI_FILES[@]}"; do
  fetch_kpi "${rel}"
done

fetch_safe "custom/Espo/Custom/Controllers/Appuntamento.php"

mkdir -p data/cache/application data/cache/application/modules
php clear_cache.php
php rebuild.php

if grep -q 'getContainer(' custom/Espo/Custom/Controllers/Appuntamento.php 2>/dev/null; then
  echo "ERRORE: Appuntamento.php contiene getContainer()" >&2
  exit 1
fi

echo ""
echo "=== Fatto: KPI come PR #97 (Rese + Criticità + pipeline) ==="
echo "Snapshot: ${PRE}"
echo "Ctrl+Shift+R sulla dashboard KPI."
