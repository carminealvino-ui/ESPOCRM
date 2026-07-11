#!/usr/bin/env bash
# Fix KPI: appuntamenti totali = netti (15), sospesi ordini = statoContratto Sospeso
#
# Uso:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-kpi-appuntamenti-sospesi-9999/tools/deploy-kpi-appuntamenti-sospesi.sh?t=$(date +%s)" | bash
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="cursor/fix-kpi-appuntamenti-sospesi-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS="$(date +%s)"

cd "${CRM_ROOT}" || exit 1

echo "=== Fix KPI appuntamenti + contratti sospesi ==="

fetch() {
  local path="$1"
  mkdir -p "$(dirname "$path")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}?t=${TS}" -o "${path}"
  echo "OK ${path}"
}

FILES=(
  custom/Espo/Custom/Services/CrmKpi/CrmKpiService.php
  custom/Espo/Custom/Tools/CrmKpi/KpiContext.php
  custom/Espo/Custom/Tools/CrmKpi/Alerts.php
  client/custom/src/views/dashlets/crm-kpi.js
  custom/Espo/Custom/Classes/Select/Appuntamento/PrimaryFilters/Pianificato.php
  custom/Espo/Custom/Classes/Select/Appuntamento/PrimaryFilters/Ingestibile.php
  custom/Espo/Custom/Resources/metadata/selectDefs/Appuntamento.json
  custom/Espo/Custom/Classes/Select/Quote/PrimaryFilters/ContrattiSospesiOrdini.php
  custom/Espo/Custom/Resources/metadata/selectDefs/Quote.json
  custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json
  tools/bonifica-data-appuntamento.php
)

for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

php clear_cache.php
php rebuild.php
php clear_cache.php

echo ""
echo "=== Fatto ==="
echo "  - Appuntamenti Lordi = nel periodo, esclusi Pianificati (es. 30)"
echo "  - Appuntamenti Pianificati = nel periodo, esclusi dai lordi (es. 4)"
echo "  - Appuntamenti Totali = Netti (solo Held svolti)"
echo "  - Annullati = Lordi - Netti"
echo "  - Periodo: dataAppuntamento, oppure dateStart se dataAppuntamento vuota"
echo ""
echo "Se il grafico a torta mostra meno Pianificati del KPI, eseguire:"
echo "  php tools/bonifica-data-appuntamento.php --dry-run"
echo "  - Sospesi ordini = statoContratto Sospeso (non In lavorazione)"
