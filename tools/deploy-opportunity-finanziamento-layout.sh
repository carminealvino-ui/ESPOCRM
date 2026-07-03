#!/usr/bin/env bash
# Opportunità: sezione finanziamento su tutti i layout + copia su contratto.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/opportunity-finanziamento-layout-9999/tools/deploy-opportunity-finanziamento-layout.sh?t=$(date +%s)" | bash
#   php clear_cache.php && php rebuild.php

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/opportunity-finanziamento-layout-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
LOCAL_BACKUP="${CRM_ROOT}/backup/opportunity-finanziamento-layout/server-${STAMP}"

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
  "custom/Espo/Custom/Actions/Opportunity/CreateContratto.php"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Opportunity.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json"
  "custom/Espo/Custom/Resources/metadata/logicDefs/Opportunity.json"
  "custom/Espo/Custom/Resources/metadata/logicDefs/Quote.json"
  "custom/Espo/Custom/Resources/layouts/Opportunity/detail.json"
  "custom/Espo/Custom/Resources/layouts/Opportunity/detailSmall.json"
  "custom/Espo/Custom/Resources/layouts/Opportunity/list.json"
  "custom/Espo/Custom/Resources/layouts/Opportunity/listSmall.json"
  "custom/Espo/Custom/Resources/layouts/Opportunity/massUpdate.json"
  "custom/Espo/Custom/Resources/layouts/Opportunity/filters.json"
  "custom/Espo/Custom/Resources/layouts/Opportunity/defaultSidePanel.json"
  "custom/Espo/Custom/Resources/layouts/Opportunity/bottomPanelsDetail.json"
  "custom/Espo/Custom/Resources/layouts/Quote/detail.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Opportunity.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Quote.json"
)

for rel in "${FILES[@]}"; do
  backup_if_exists "${rel}"
done

echo "=== Download da ${BRANCH} ==="
for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "OK ${rel}"
done

echo ""
echo "=== Deploy completato ==="
echo "Poi: cd ${CRM_ROOT} && php clear_cache.php && php rebuild.php"
