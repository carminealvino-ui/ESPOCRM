#!/usr/bin/env bash
# Fix Bad request: No primary filter 'forecast' for 'Provvigione'
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-provvigione-filtro-forecast-9999/tools/deploy-fix-provvigione-filtro-forecast.sh?t=$(date +%s)" | bash
#   php clear_cache.php && php rebuild.php

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-provvigione-filtro-forecast-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
LOCAL_BACKUP="${CRM_ROOT}/backup/fix-provvigione-filtro-forecast/server-${STAMP}"

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
  "custom/Espo/Custom/Classes/Select/Provvigione/PrimaryFilters/Forecast.php"
  "custom/Espo/Custom/Classes/Select/Provvigione/PrimaryFilters/InPagamento.php"
  "custom/Espo/Custom/Classes/Select/Provvigione/PrimaryFilters/Pagato.php"
  "custom/Espo/Custom/Classes/Select/Provvigione/PrimaryFilters/Inesigibile.php"
  "custom/Espo/Custom/Resources/metadata/selectDefs/Provvigione.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Provvigione.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Provvigione.json"
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

SEL="${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/selectDefs/Provvigione.json"
CLS="${CRM_ROOT}/custom/Espo/Custom/Classes/Select/Provvigione/PrimaryFilters/Forecast.php"
if [[ -f "${SEL}" ]] && grep -q "primaryFilterClassNameMap" "${SEL}" \
  && grep -q '"forecast"' "${SEL}" \
  && [[ -f "${CLS}" ]] && grep -q "SelectBuilder" "${CLS}"; then
  echo "VERIFICA OK: filtro primario forecast registrato (Espo 10)"
else
  echo "ATTENZIONE: verifica manuale selectDefs/Forecast.php"
fi
