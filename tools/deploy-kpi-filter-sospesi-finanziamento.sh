#!/usr/bin/env bash
# Fix link KPI "Sospesi finanziamento" → primaryFilter contrattiSospesiFinanziamento
#
# Uso:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-kpi-filter-sospesi-finanziamento-9999/tools/deploy-kpi-filter-sospesi-finanziamento.sh?t=$(date +%s)" | bash
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="cursor/fix-kpi-filter-sospesi-finanziamento-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS="$(date +%s)"

cd "${CRM_ROOT}" || exit 1

echo "=== Fix filtro KPI Sospesi finanziamento ==="

fetch() {
  local path="$1"
  mkdir -p "$(dirname "$path")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}?t=${TS}" -o "${path}"
  echo "OK ${path}"
}

FILES=(
  custom/Espo/Custom/Classes/Select/Quote/PrimaryFilters/ContrattiSospesiFinanziamento.php
  custom/Espo/Custom/Resources/metadata/selectDefs/Quote.json
)

for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

php clear_cache.php
php rebuild.php
php clear_cache.php

echo ""
echo "=== Fatto ==="
echo "  - Registrato primaryFilter contrattiSospesiFinanziamento su Quote"
echo "  - Criteri: finanziamento=true, stato In rivalutazione / In Attesa Documentazione"
echo ""
echo "Verifica: aprire link KPI o #Quote/list/primaryFilter=contrattiSospesiFinanziamento"
