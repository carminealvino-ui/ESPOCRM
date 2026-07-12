#!/usr/bin/env bash
# Fix pipeline KPI: gerarchia totali → lordi → netti (= opportunità)
#
# Uso:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-kpi-pipeline-totali-9999/tools/deploy-fix-kpi-pipeline-totali.sh?t=$(date +%s)" | bash
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="cursor/fix-kpi-pipeline-totali-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS="$(date +%s)"

cd "${CRM_ROOT}" || exit 1

echo "=== Fix pipeline KPI (gerarchia totali/lordi/netti) ==="

fetch() {
  local path="$1"
  mkdir -p "$(dirname "$path")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}?t=${TS}" -o "${path}"
  echo "OK ${path}"
}

FILES=(
  custom/Espo/Custom/Services/CrmKpi/CrmKpiService.php
  custom/Espo/Custom/Tools/CrmKpi/FunnelBuilder.php
  custom/Espo/Custom/Tools/CrmKpi/YieldBuilder.php
  client/custom/src/views/dashlets/crm-kpi.js
  client/custom/res/templates/dashlets/crm-kpi.tpl
)

for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

php clear_cache.php
php rebuild.php
php clear_cache.php

echo ""
echo "=== Fatto ==="
echo "  - Totali = tutti nel periodo meno pianificati (conteggio coerente)"
echo "  - Annullati = totali - lordi (non più query OR esito che gonfiava a 344)"
echo "  - Contratti lordi/netti: % su appuntamenti lordi e netti"
echo ""
echo "Ricarica la dashboard con Ctrl+F5."
