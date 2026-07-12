#!/usr/bin/env bash
# Fix pipeline KPI: Totali appuntamenti ≠ Netti, Contr. lordi da contratti lordi
#
# Uso:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-kpi-pipeline-totali-9999/tools/deploy-fix-kpi-pipeline-totali.sh?t=$(date +%s)" | bash
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="cursor/fix-kpi-pipeline-totali-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS="$(date +%s)"

cd "${CRM_ROOT}" || exit 1

echo "=== Fix pipeline KPI (totali / contratti lordi) ==="

fetch() {
  local path="$1"
  mkdir -p "$(dirname "$path")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}?t=${TS}" -o "${path}"
  echo "OK ${path}"
}

FILES=(
  custom/Espo/Custom/Services/CrmKpi/CrmKpiService.php
  client/custom/src/views/dashlets/crm-kpi.js
)

for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

php clear_cache.php
php rebuild.php
php clear_cache.php

echo ""
echo "=== Fatto ==="
echo "  - Totali appuntamenti = non annullati (Held + Not Held + Ingestibili), non più = netti"
echo "  - Annullati = lordi - totali"
echo "  - Pipeline contratti: valore lordi (non totali esclusi recesso)"
echo ""
echo "Ricarica la dashboard con Ctrl+F5."
