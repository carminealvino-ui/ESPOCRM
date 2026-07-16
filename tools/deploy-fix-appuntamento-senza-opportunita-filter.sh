#!/usr/bin/env bash
# Fix filtro KPI "Senza opportunità" (lista vuota vs conteggio 78)
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-appuntamento-senza-opportunita-filter-9999/tools/deploy-fix-appuntamento-senza-opportunita-filter.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-appuntamento-senza-opportunita-filter-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "custom/Espo/Custom/Resources/metadata/selectDefs/Appuntamento.json"
  "custom/Espo/Custom/Classes/Select/Appuntamento/PrimaryFilters/AppuntamentiSenzaOpportunita.php"
  "custom/Espo/Custom/Classes/Select/Appuntamento/PrimaryFilters/AppuntamentiConPiuOpportunita.php"
)

echo "=== Deploy filtri Appuntamento da ${BRANCH} ==="
for rel in "${FILES[@]}"; do
  mkdir -p "${CRM_ROOT}/$(dirname "${rel}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${CRM_ROOT}/${rel}"
  echo "OK ${rel}"
done

cd "${CRM_ROOT}"
php clear_cache.php
php rebuild.php

SEL="${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/selectDefs/Appuntamento.json"
CLS="${CRM_ROOT}/custom/Espo/Custom/Classes/Select/Appuntamento/PrimaryFilters/AppuntamentiSenzaOpportunita.php"
if grep -q '"appuntamentiSenzaOpportunita"' "${SEL}" \
  && grep -q 'resolveAppuntamentoIds' "${CLS}"; then
  echo "VERIFICA OK: filtro allineato a logica KPI Alerts"
else
  echo "ATTENZIONE: verifica manuale file deployati"
fi

echo "=== Fine. Ctrl+Shift+R e riapri Senza opportunità dal KPI ==="
