#!/usr/bin/env bash
# Layout KPI completo (branch fix-kpi-lordi-pianificati-9999):
# - CSS esteso con tabelle Rese, griglia 30/70, sezione Avvisi
# - crm-kpi.js + template + CrmKpiService
# - endpoint CrmKpi/action/getSummary (Espo 10, senza getContainer)
#
# NON è deploy-popup-save-fix.sh (quello è solo popup Contatto Telefonico).
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
  echo "OK ${path} (da ${KPI_BRANCH})"
}

fetch_safe() {
  local path="$1"
  mkdir -p "$(dirname "${path}")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${SAFE_BASE}/backup/stato-noto-buono-2026-07-09/${path}?t=${TS}" -o "${path}"
  echo "OK ${path} (safe backup)"
}

KPI_FILES=(
  "custom/Espo/Custom/Resources/metadata/app/client.json"
  "custom/Espo/Custom/Services/CrmKpi/CrmKpiService.php"
  "client/custom/src/views/dashlets/crm-kpi.js"
  "client/custom/css/crm-kpi-dashlet.css"
  "client/custom/res/templates/dashlets/crm-kpi.tpl"
  "custom/Espo/Custom/Controllers/CrmKpi.php"
  "custom/Espo/Custom/Resources/i18n/it_IT/CrmKpi.json"
  "custom/Espo/Custom/Resources/metadata/dashlets/CrmKpi.json"
)

echo "=== Snapshot pre-deploy in ${PRE} ==="
for rel in "${KPI_FILES[@]}" "custom/Espo/Custom/Controllers/Appuntamento.php"; do
  backup_if_exists "${rel}"
done

echo ""
echo "=== Deploy layout KPI (${KPI_BRANCH}) ==="
for rel in "${KPI_FILES[@]}"; do
  fetch_kpi "${rel}"
done

# Alias Appuntamento sicuro se JS in cache chiama ancora Appuntamento/action/getSummary
fetch_safe "custom/Espo/Custom/Controllers/Appuntamento.php"

mkdir -p data/cache/application data/cache/application/modules
php clear_cache.php
php rebuild.php

echo ""
if grep -q 'getContainer(' custom/Espo/Custom/Controllers/Appuntamento.php 2>/dev/null; then
  echo "ERRORE: Appuntamento.php contiene getContainer()" >&2
  exit 1
fi

echo "=== Fatto: layout KPI lordi-pianificati deployato ==="
echo "Snapshot: ${PRE}"
echo "Ctrl+Shift+R sulla dashboard KPI."
echo ""
echo "Opzionale — fix popup Contatto Telefonico:"
echo "  curl -fsSL \"${KPI_BASE}/tools/deploy-popup-save-fix.sh?t=${TS}\" | bash"
