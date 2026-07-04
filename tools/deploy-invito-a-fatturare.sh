#!/usr/bin/env bash
# Deploy modulo Invito a fatturare (con backup automatico).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/invito-a-fatturare-selezione-9999/tools/deploy-invito-a-fatturare.sh?t=$(date +%s)" | bash
#   php clear_cache.php && php rebuild.php
#   php tools/verify-invito-a-fatturare-deploy.php

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/invito-a-fatturare-selezione-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
LOCAL_BACKUP="${CRM_ROOT}/backup_dev/InvitoAFatturare/deploy-${STAMP}"

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
  "custom/Espo/Custom/Services/InvitoAFatturareManager.php"
  "custom/Espo/Custom/Controllers/InvitoAFatturare.php"
  "custom/Espo/Custom/Hooks/InvitoAFatturare/BeforeSave.php"
  "custom/Espo/Custom/Actions/InvitoAFatturare/GeneraDaProvvigioni.php"
  "custom/Espo/Custom/Actions/InvitoAFatturare/GetProvvigioniEleggibili.php"
  "custom/Espo/Custom/Actions/InvitoAFatturare/CollegaProvvigioni.php"
  "custom/Espo/Custom/Resources/metadata/entityDefs/InvitoAFatturare.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/InvitoAFatturare.json"
  "custom/Espo/Custom/Resources/metadata/scopes/InvitoAFatturare.json"
  "custom/Espo/Custom/Resources/metadata/app/actions.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/InvitoAFatturare.json"
  "custom/Espo/Custom/Resources/layouts/InvitoAFatturare/list.json"
  "custom/Espo/Custom/Resources/layouts/InvitoAFatturare/detail.json"
  "client/custom/src/views/invito-a-fatturare/record/detail.js"
  "client/custom/src/views/invito-a-fatturare/modals/select-provvigioni.js"
  "client/custom/res/templates/invito-a-fatturare/modals/select-provvigioni.tpl"
  "client/custom/res/css/invito-a-fatturare.css"
  "tools/verify-invito-a-fatturare-deploy.php"
)

for rel in "${FILES[@]}"; do
  backup_if_exists "${rel}"
done

echo ""
echo "=== Download da ${BRANCH} ==="
for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "OK ${rel}"
done

echo ""
echo "=== Deploy Invito a fatturare terminato ==="
echo "Poi:"
echo "  cd ${CRM_ROOT}"
echo "  php clear_cache.php"
echo "  php rebuild.php"
echo "  php tools/verify-invito-a-fatturare-deploy.php"
echo ""
echo "Rollback: cp -a ${LOCAL_BACKUP}/* ${CRM_ROOT}/"
