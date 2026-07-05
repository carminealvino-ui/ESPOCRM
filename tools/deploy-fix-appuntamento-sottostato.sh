#!/usr/bin/env bash
# Ripristina sottostato condizionato da stato (Held/Not Held/Ingestibile).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/invito-a-fatturare-selezione-9999/tools/deploy-fix-appuntamento-sottostato.sh?t=$(date +%s)" | bash
#   php clear_cache.php

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/invito-a-fatturare-selezione-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
BACKUP="${CRM_ROOT}/backup_dev/Appuntamento/sottostato-${STAMP}"

FILES=(
  "client/custom/src/helpers/appuntamento-sottostato-map.js"
  "client/custom/src/views/fields/appuntamento-sottostato.js"
  "client/custom/src/views/fields/appuntamento-sottostato-popup.js"
  "client/custom/src/views/appuntamento/popup-notification.js"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/logicDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/layouts/Appuntamento/detailEsitoPopup.json"
  "custom/Espo/Custom/Resources/metadata/app/popupNotifications.json"
)

echo "=== Backup ${BACKUP} ==="
mkdir -p "${BACKUP}"

for rel in "${FILES[@]}"; do
  src="${CRM_ROOT}/${rel}"
  if [[ -f "${src}" ]]; then
    mkdir -p "${BACKUP}/$(dirname "${rel}")"
    cp -a "${src}" "${BACKUP}/${rel}"
    echo "BACKUP ${rel}"
  fi
done

echo ""
echo "=== Download fix sottostato ==="
for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "OK ${rel}"
done

grep -q "getAllowedForStatus" "${CRM_ROOT}/client/custom/src/views/fields/appuntamento-sottostato.js"

echo ""
echo "Poi: cd ${CRM_ROOT} && php clear_cache.php && rm -rf data/cache/*"
echo ""
echo "Verifica: Stato=Non Svolto → solo Non Confermato, Non Ricevuto, Non Gestito, Annullato, Rifissato"
