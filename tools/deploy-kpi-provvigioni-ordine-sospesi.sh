#!/usr/bin/env bash
# KPI: Appuntamenti Totali→Annullati→Lordi→Ingestibili→Netti + pipeline + tile KO/Sospesi.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-kpi-provvigioni-ordine-sospesi-9999/tools/deploy-kpi-provvigioni-ordine-sospesi.sh?t=$(date +%s)" | bash
#   php clear_cache.php && php rebuild.php

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-kpi-provvigioni-ordine-sospesi-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
LOCAL_BACKUP="${CRM_ROOT}/backup/kpi-provvigioni-ordine-sospesi/server-${STAMP}"

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
  "client/custom/src/views/dashlets/crm-kpi.js"
  "client/custom/res/templates/dashlets/crm-kpi.tpl"
  "client/custom/css/crm-kpi-dashlet.css"
  "custom/Espo/Custom/Services/CrmKpi/CrmKpiService.php"
  "custom/Espo/Custom/Tools/CrmKpi/KpiContext.php"
  "custom/Espo/Custom/Tools/CrmKpi/FunnelBuilder.php"
  "custom/Espo/Custom/Tools/CrmKpi/YieldBuilder.php"
)

for rel in "${FILES[@]}"; do
  backup_if_exists "${rel}"
done

echo "=== Deploy da ${BRANCH} ==="
for rel in "${FILES[@]}"; do
  mkdir -p "${CRM_ROOT}/$(dirname "${rel}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${CRM_ROOT}/${rel}"
  echo "OK ${rel}"
done

echo "=== Fine. Esegui: php clear_cache.php && php rebuild.php (poi Ctrl+Shift+R) ==="

JS="${CRM_ROOT}/client/custom/src/views/dashlets/crm-kpi.js"
TPL="${CRM_ROOT}/client/custom/res/templates/dashlets/crm-kpi.tpl"
if [[ -f "${JS}" ]] && grep -q "kpi-periodo-andwhere-v1" "${JS}" \
  && grep -q "kpi-quote-gerarchia-v1" "${JS}" \
  && grep -q "kpi-pipeline-labels-v3" "${JS}" \
  && [[ -f "${TPL}" ]] && grep -q "crm-kpi-pipeline-results-grid" "${TPL}" \
  && ! grep -q "changePeriod" "${TPL}"; then
  echo "VERIFICA OK: periodo AND-where + quote Totali→Lordi + Periodo solo in Opzioni"
else
  echo "ATTENZIONE: verifica manuale JS/TPL"
fi
SVC="${CRM_ROOT}/custom/Espo/Custom/Services/CrmKpi/CrmKpiService.php"
CTX="${CRM_ROOT}/custom/Espo/Custom/Tools/CrmKpi/KpiContext.php"
if [[ -f "${SVC}" ]] && grep -q "function combineWhere" "${SVC}" \
  && grep -q "Allineato ad Appuntamenti" "${SVC}"; then
  echo "VERIFICA OK: ${SVC} (periodo + gerarchia Totali/Lordi quote)"
else
  echo "ATTENZIONE: manca fix gerarchia quote in ${SVC}"
fi
if [[ -f "${CTX}" ]] && grep -q "appuntamentoDateWhere" "${CTX}" && grep -q "dateStart>=" "${CTX}"; then
  echo "VERIFICA OK: ${CTX} (dataAppuntamento + fallback dateStart)"
else
  echo "ATTENZIONE: ${CTX} non aggiornato"
fi
