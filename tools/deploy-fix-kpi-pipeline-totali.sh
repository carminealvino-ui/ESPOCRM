#!/usr/bin/env bash
# Fix pipeline KPI: gerarchia totali → lordi → netti (= opportunità)
#
# Uso (copia tutta la riga):
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-kpi-pipeline-totali-9999/tools/deploy-fix-kpi-pipeline-totali.sh?t=$(date +%s)" | bash
#
# Con percorso CRM custom:
#   CRM_ROOT=/path/al/crm curl -fsSL "..." | bash
set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-kpi-pipeline-totali-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS="$(date +%s)"

if [[ ! -d "${CRM_ROOT}" ]]; then
  echo "ERRORE: cartella CRM non trovata: ${CRM_ROOT}" >&2
  echo "Imposta CRM_ROOT o passa il percorso come primo argomento." >&2
  exit 1
fi

cd "${CRM_ROOT}"

PHP_BIN="${PHP_BIN:-php}"
if ! command -v "${PHP_BIN}" >/dev/null 2>&1; then
  echo "ERRORE: php non trovato nel PATH. Imposta PHP_BIN=/percorso/php" >&2
  exit 1
fi

echo "=== Fix pipeline KPI (gerarchia totali/lordi/netti) ==="
echo "CRM_ROOT=${CRM_ROOT}"
echo "BRANCH=${BRANCH}"

fetch() {
  local path="$1"
  mkdir -p "$(dirname "${path}")"
  if ! curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}?t=${TS}" -o "${path}"; then
    echo "ERRORE fetch: ${BASE}/${path}" >&2
    exit 1
  fi
  echo "OK ${path}"
}

FILES=(
  custom/Espo/Custom/Controllers/CrmKpi.php
  custom/Espo/Custom/Services/CrmKpi/CrmKpiService.php
  custom/Espo/Custom/Tools/CrmKpi/DateRange.php
  custom/Espo/Custom/Tools/CrmKpi/FunnelBuilder.php
  custom/Espo/Custom/Tools/CrmKpi/KpiContext.php
  custom/Espo/Custom/Tools/CrmKpi/WeekOfMonth.php
  custom/Espo/Custom/Tools/CrmKpi/YieldBuilder.php
  client/custom/src/views/dashlets/crm-kpi.js
  client/custom/res/templates/dashlets/crm-kpi.tpl
  client/custom/css/crm-kpi-dashlet.css
)

for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

"${PHP_BIN}" clear_cache.php
"${PHP_BIN}" rebuild.php
"${PHP_BIN}" clear_cache.php

echo ""
echo "=== Fatto ==="
echo "  - Totali = periodo meno pianificati"
echo "  - Annullati = totali - non annullati (derivato)"
echo "  - Lordi = non annullati - pianificati (es. 24 - 3 = 21)"
echo "  - Netti = lordi - ingestibili (es. 21 - 2 = 19)"
echo ""
echo "Ricarica la dashboard con Ctrl+F5."
